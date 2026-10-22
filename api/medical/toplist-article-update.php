<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../toplist_directory.php';
require_once __DIR__ . '/../../medical_search_cache.php';
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('X-MedReview-Toplist-Article-Contract: 1');
medical_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 8 * 1024 * 1024) json_response(['ok' => false, 'message' => 'Body tối đa 8MB.'], 413);
try { $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
catch (JsonException $e) { json_response(['ok' => false, 'message' => 'JSON không hợp lệ.'], 400); }
if (!is_array($body)) json_response(['ok' => false, 'message' => 'Body cần object hoặc danh sách object.'], 422);
$items = $body['items'] ?? (array_is_list($body) ? $body : [$body]);
if (!is_array($items) || !array_is_list($items) || $items === [] || count($items) > 25) json_response(['ok' => false, 'message' => 'items cần 1–25 object.'], 422);

/** SEO columns were added to medical_toplists outside the base schema; use them only when present. */
function toplist_article_seo_columns(PDO $pdo): array
{
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = [];
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $found = [];
            foreach ($pdo->query('PRAGMA table_info(medical_toplists)') as $column) $found[] = (string) ($column['name'] ?? '');
        } else {
            $found = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='medical_toplists'")->fetchAll(PDO::FETCH_COLUMN);
        }
        $columns = array_values(array_intersect(['seo_title', 'seo_description', 'seo_keywords'], is_array($found) ? $found : []));
    } catch (Throwable $e) { $columns = []; }
    return $columns;
}

/** Unique slug for medical_toplists, ignoring the record being updated. */
function toplist_article_unique_slug(PDO $pdo, string $value, ?int $excludeId): string
{
    $base = substr(slugify($value), 0, 170) ?: bin2hex(random_bytes(6));
    $candidate = $base; $suffix = 2;
    $sql = 'SELECT 1 FROM medical_toplists WHERE slug=:slug' . ($excludeId ? ' AND id<>:exclude' : '') . ' LIMIT 1';
    $check = $pdo->prepare($sql);
    while (true) {
        $params = [':slug' => $candidate];
        if ($excludeId) $params[':exclude'] = $excludeId;
        $check->execute($params);
        if (!$check->fetchColumn()) return $candidate;
        $candidate = $base . '-' . $suffix++;
    }
}

/**
 * Validate the SEO/language fields the article importer does not know about and
 * return them as [column => value]. Throws on invalid input.
 */
function toplist_article_extra_fields(PDO $pdo, array $item, bool $isCreate): array
{
    $fields = [];
    $limits = ['seo_title' => 220, 'seo_description' => 300, 'seo_keywords' => 255];
    foreach (toplist_article_seo_columns($pdo) as $column) {
        if (!array_key_exists($column, $item)) continue;
        $value = $item[$column];
        if (!is_string($value)) throw new InvalidArgumentException($column . ' phải là chuỗi.');
        if (mb_strlen($value) > $limits[$column]) throw new InvalidArgumentException($column . ' tối đa ' . $limits[$column] . ' ký tự.');
        $fields[$column] = $value;
    }
    if (array_key_exists('language_code', $item)) {
        $language = strtolower(trim((string) $item['language_code']));
        if (!in_array($language, ['vi', 'en'], true)) throw new InvalidArgumentException('language_code phải là vi hoặc en.');
        $fields['language_code'] = $language;
    }
    if ($isCreate && array_key_exists('translation_of_id', $item)) {
        $parentId = filter_var($item['translation_of_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$parentId) throw new InvalidArgumentException('translation_of_id phải là số nguyên dương.');
        $parent = $pdo->prepare('SELECT 1 FROM medical_toplists WHERE id=:id LIMIT 1');
        $parent->execute([':id' => $parentId]);
        if (!$parent->fetchColumn()) throw new InvalidArgumentException('Không tìm thấy Toplist gốc #' . $parentId . ' cho translation_of_id.');
        $fields['translation_of_id'] = $parentId;
    }
    return $fields;
}

function toplist_article_apply_fields(PDO $pdo, int $id, array $fields): void
{
    if ($fields === []) return;
    $sets = []; $params = [':id' => $id];
    foreach ($fields as $column => $value) {
        $sets[] = $column . '=:' . $column;
        $params[':' . $column] = $value;
    }
    $pdo->prepare('UPDATE medical_toplists SET ' . implode(', ', $sets) . ' WHERE id=:id')->execute($params);
}

function toplist_article_readback(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT id, title, slug, language_code, entity_type, status, updated_at FROM medical_toplists WHERE id=:id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Không đọc lại được Toplist #' . $id . ' sau khi lưu.');
    return ['toplist_id' => (int) $row['id'], 'title' => (string) $row['title'], 'slug' => (string) $row['slug'],
        'language_code' => (string) $row['language_code'], 'entity_type' => (string) $row['entity_type'],
        'status' => (string) $row['status'], 'updated_at' => (string) $row['updated_at']];
}

function toplist_article_create(PDO $pdo, array $item): array
{
    $extra = toplist_article_extra_fields($pdo, $item, true);
    $titleOnly = !empty($item['title_only']);
    if (!$titleOnly) {
        // The article importer expects a member list. An article-only payload
        // means "create the article now, link members later": feed it an empty
        // list of the matching type instead of failing.
        $hasMembers = false;
        foreach (['facilities', 'doctors', 'members', 'co_so', 'co_so_y_te', 'bac_si'] as $key) if (array_key_exists($key, $item)) { $hasMembers = true; break; }
        if (!$hasMembers) {
            $type = toplist_directory_entity_type($item, 'facility');
            if ($type === 'doctor') $item['doctors'] = [];
            elseif ($type === 'mixed') $item['members'] = [];
            else $item['facilities'] = [];
        }
    }
    $created = toplist_directory_import_article($pdo, $item, 'facility', $titleOnly);
    $id = (int) $created['id'];
    toplist_article_apply_fields($pdo, $id, $extra);
    return ['action' => 'created'] + toplist_article_readback($pdo, $id) + [
        'member_ids' => $created['ids'] ?? [],
        'created_facility_ids' => $created['created_facility_ids'] ?? [],
        'created_doctor_ids' => $created['created_doctor_ids'] ?? [],
    ];
}

function toplist_article_update(PDO $pdo, int $id, array $item): array
{
    $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare('SELECT * FROM medical_toplists WHERE id=:id' . $lock);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Không tìm thấy Toplist #' . $id . '.');

    $sets = []; $params = [':id' => $id]; $updatedFields = [];
    $pick = static function (array $aliases) use ($item): array {
        foreach ($aliases as $alias) if (array_key_exists($alias, $item)) return [true, $item[$alias]];
        return [false, null];
    };

    [$hasTitle, $title] = $pick(['title', 'tieu_de']);
    if ($hasTitle) {
        if (!is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > 220) throw new InvalidArgumentException('title là bắt buộc và tối đa 220 ký tự.');
        $sets[] = 'title=:title'; $params[':title'] = trim($title); $updatedFields[] = 'title';
    }
    $slugProvided = array_key_exists('slug', $item);
    if ($slugProvided) {
        if (!is_string($item['slug'])) throw new InvalidArgumentException('slug phải là chuỗi.');
        $slugSource = trim($item['slug']) !== '' ? $item['slug'] : (string) ($params[':title'] ?? $row['title']);
        $slug = toplist_article_unique_slug($pdo, $slugSource, $id);
        if ($slug !== (string) $row['slug']) { $sets[] = 'slug=:slug'; $params[':slug'] = $slug; $updatedFields[] = 'slug'; }
    }
    foreach (['excerpt' => ['excerpt', 'mo_ta'], 'content' => ['content', 'noi_dung'], 'featured_image_url' => ['featured_image_url', 'image_url', 'anh']] as $field => $aliases) {
        [$hasField, $value] = $pick($aliases);
        if (!$hasField) continue;
        if (!is_string($value)) throw new InvalidArgumentException($field . ' phải là chuỗi.');
        if ($field === 'featured_image_url' && mb_strlen($value) > 500) throw new InvalidArgumentException('featured_image_url tối đa 500 ký tự.');
        $sets[] = $field . '=:' . $field; $params[':' . $field] = $value; $updatedFields[] = $field;
    }
    if (array_key_exists('status', $item)) {
        if (!in_array($item['status'], ['draft', 'published'], true)) throw new InvalidArgumentException('status phải là draft hoặc published.');
        $sets[] = 'status=:status'; $params[':status'] = $item['status']; $updatedFields[] = 'status';
    }
    // entity_type is fixed once a Toplist exists (same rule as the members endpoints).
    $type = toplist_directory_entity_type($item, (string) $row['entity_type']);
    if ($type !== (string) $row['entity_type']) throw new InvalidArgumentException('Loại danh sách không khớp Toplist. Đổi đối tượng xếp hạng trong trang chỉnh sửa trước.');
    foreach (toplist_article_extra_fields($pdo, $item, false) as $column => $value) {
        $sets[] = $column . '=:' . $column; $params[':' . $column] = $value; $updatedFields[] = $column;
    }

    $hasMembers = false;
    foreach (['facilities', 'doctors', 'members', 'co_so', 'co_so_y_te', 'bac_si'] as $key) if (array_key_exists($key, $item)) { $hasMembers = true; break; }
    $members = null;
    if ($hasMembers) {
        $members = toplist_directory_payload_members($item, (string) $row['entity_type']);
        if ($members === []) throw new InvalidArgumentException('Cần danh sách hồ sơ không rỗng.');
    }
    if ($sets === [] && !$hasMembers && !$slugProvided) throw new InvalidArgumentException('Không có trường nào để cập nhật.');

    if ($sets !== []) $pdo->prepare('UPDATE medical_toplists SET ' . implode(', ', $sets) . ' WHERE id=:id')->execute($params);
    $saved = $hasMembers ? toplist_directory_import_members($pdo, $id, (string) $row['entity_type'], $members) : null;

    return ['action' => 'updated'] + toplist_article_readback($pdo, $id) + [
        'updated_fields' => $updatedFields,
        'member_ids' => $saved['ids'] ?? null,
        'created_facility_ids' => $saved['created_facility_ids'] ?? [],
        'created_doctor_ids' => $saved['created_doctor_ids'] ?? [],
    ];
}

$pdo = db();
$createdIds = []; $updatedIds = []; $createdFacilities = []; $createdDoctors = []; $errors = []; $results = [];
foreach ($items as $index => $item) {
    try {
        if (!is_array($item) || array_is_list($item)) throw new InvalidArgumentException('Item phải là JSON object.');
        $saved = toplist_directory_with_member_lock($pdo, static function () use ($pdo, $item): array {
            $pdo->beginTransaction();
            try {
                $hasId = array_key_exists('toplist_id', $item) || array_key_exists('id', $item);
                $id = filter_var($item['toplist_id'] ?? $item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($hasId && !$id) throw new InvalidArgumentException('Cần toplist_id nguyên dương.');
                $result = $id ? toplist_article_update($pdo, (int) $id, $item) : toplist_article_create($pdo, $item);
                $pdo->commit();
                return $result;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        });
        if ($saved['action'] === 'created') $createdIds[] = $saved['toplist_id']; else $updatedIds[] = $saved['toplist_id'];
        $createdFacilities = array_merge($createdFacilities, $saved['created_facility_ids'] ?? []);
        $createdDoctors = array_merge($createdDoctors, $saved['created_doctor_ids'] ?? []);
        $results[] = ['index' => $index] + $saved;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $status = $e instanceof InvalidArgumentException ? 422 : 500;
        if ($status === 500) error_log('Toplist article save: ' . $e->getMessage());
        $errors[] = ['index' => $index, 'toplist_id' => is_array($item) ? ($item['toplist_id'] ?? $item['id'] ?? null) : null,
            'status' => $status, 'message' => $status === 422 ? $e->getMessage() : 'Không thể lưu bài Toplist; item này chưa được cập nhật.'];
    }
}
if ($createdIds !== [] || $updatedIds !== []) medical_search_cache_invalidate();
json_response(['ok' => $errors === [], 'created_toplist_ids' => $createdIds, 'created_count' => count($createdIds),
    'updated_toplist_ids' => $updatedIds, 'updated_count' => count($updatedIds),
    'created_facility_ids' => $createdFacilities, 'created_doctor_ids' => $createdDoctors,
    'results' => $results, 'errors' => $errors],
    $errors === [] ? 200 : ($results !== [] ? 207 : max(array_column($errors, 'status'))));
