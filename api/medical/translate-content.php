<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../toplist_directory.php';
require_once __DIR__ . '/../../medical_search_cache.php';

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');

// The API is used by authenticated browser-based utilities as well as server
// integrations. Match the allowlist used by the image receive endpoint.
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = ['https://chatgpt.com', 'https://grok.com', 'https://x.com', 'https://www.x.com'];
header('Vary: Origin');
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Medical-Api-Key, Content-Type');
    header('Access-Control-Max-Age: 600');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

medical_api_auth();

function medical_api_translation_table(string $type): ?string
{
    return [
        'facility' => 'medical_facilities',
        'doctor' => 'medical_doctors',
        'toplist' => 'medical_toplists',
    ][strtolower(trim($type))] ?? null;
}

/** @return array<int,string> */
function medical_api_translation_fields(string $type): array
{
    return match ($type) {
        'facility' => [
            'slug', 'name', 'category', 'city', 'subtitle', 'content', 'seo_title', 'seo_description', 'seo_keywords',
            'hours_text', 'parking_info', 'nearby_landmarks', 'warranty_policy', 'price_text', 'price_table_html',
            'featured_services', 'services', 'tags', 'highlights', 'features', 'intro', 'utilities',
        ],
        'doctor' => ['slug', 'name', 'title_text', 'specialty_text', 'city', 'facility_name', 'hours_text', 'price_text', 'tags', 'specialties', 'bio'],
        'toplist' => ['title', 'excerpt', 'content', 'slug'],
        default => [],
    };
}

/** A deliberately small, public-safe JSON object for the translation model. */
function medical_api_translation_source(string $type, array $row): array
{
    $base = ['id' => (int) ($row['id'] ?? 0), 'source_id' => (int) ($row['id'] ?? 0), 'slug' => (string) ($row['slug'] ?? '')];
    if ($type === 'facility') {
        foreach ([
            'name', 'category', 'city', 'subtitle', 'content', 'seo_title', 'seo_description', 'seo_keywords',
            'hours_text', 'address_text', 'phone_text', 'website_url', 'parking_info', 'nearby_landmarks',
            'warranty_policy', 'price_text', 'price_table_html',
        ] as $field) {
            if (array_key_exists($field, $row) && trim((string) ($row[$field] ?? '')) !== '') $base[$field] = (string) $row[$field];
        }
        foreach ([
            'featured_services_json' => 'featured_services', 'services_json' => 'services', 'tags_json' => 'tags',
            'highlights_json' => 'highlights', 'features_json' => 'features', 'intro_json' => 'intro',
            'utilities_json' => 'utilities',
        ] as $column => $field) {
            $decoded = medical_directory_json_value_decode((string) ($row[$column] ?? ''), []);
            if (is_array($decoded) && $decoded !== []) $base[$field] = $decoded;
        }
        return $base;
    }
    if ($type === 'doctor') {
        foreach (['name', 'title_text', 'specialty_text', 'city', 'facility_name', 'hours_text', 'price_text'] as $field) {
            if (array_key_exists($field, $row) && trim((string) ($row[$field] ?? '')) !== '') $base[$field] = (string) $row[$field];
        }
        foreach (['tags_json' => 'tags', 'specialties_json' => 'specialties', 'bio_json' => 'bio'] as $column => $field) {
            $decoded = medical_directory_json_value_decode((string) ($row[$column] ?? ''), []);
            if (is_array($decoded) && $decoded !== []) $base[$field] = $decoded;
        }
        return $base;
    }
    foreach (['title', 'excerpt', 'content', 'featured_image_url'] as $field) {
        if (array_key_exists($field, $row) && trim((string) ($row[$field] ?? '')) !== '') $base[$field] = (string) $row[$field];
    }
    return $base;
}

/** @return array<string,mixed> */
function medical_api_translation_output_template(string $type): array
{
    $template = [];
    foreach (medical_api_translation_fields($type) as $field) {
        $template[$field] = in_array($field, ['featured_services', 'services', 'tags', 'highlights', 'features', 'intro', 'utilities', 'specialties', 'bio'], true) ? [] : '';
    }
    return $template;
}

/** @return array<int,array<string,mixed>> */
function medical_api_translation_parse_items(array $body): array
{
    if (isset($body['items']) && is_array($body['items'])) return array_values($body['items']);
    if (isset($body['content']) && is_string($body['content'])) {
        $decoded = json_decode($body['content'], true);
        if (is_array($decoded)) {
            if (isset($decoded['items']) && is_array($decoded['items'])) return array_values($decoded['items']);
            if ($decoded !== [] && array_keys($decoded) === range(0, count($decoded) - 1)) return $decoded;
            return [$decoded];
        }
    }
    if ($body !== [] && array_keys($body) === range(0, count($body) - 1)) return $body;
    return $body === [] ? [] : [$body];
}

/** @return array<string,array{aliases:array<int,string>,kind:string,max:int}> */
function medical_api_translation_field_map(string $type): array
{
    if ($type === 'facility') {
        return [
            'slug' => ['aliases' => ['slug'], 'kind' => 'slug', 'max' => 191],
            'name' => ['aliases' => ['name'], 'kind' => 'text', 'max' => 160],
            'category' => ['aliases' => ['category'], 'kind' => 'text', 'max' => 120],
            'city' => ['aliases' => ['city'], 'kind' => 'text', 'max' => 120],
            'subtitle' => ['aliases' => ['subtitle'], 'kind' => 'text', 'max' => 10000],
            'content' => ['aliases' => ['content', 'content_html'], 'kind' => 'text', 'max' => 1000000],
            'seo_title' => ['aliases' => ['seo_title'], 'kind' => 'text', 'max' => 160],
            'seo_description' => ['aliases' => ['seo_description'], 'kind' => 'text', 'max' => 300],
            'seo_keywords' => ['aliases' => ['seo_keywords'], 'kind' => 'text', 'max' => 255],
            'hours_text' => ['aliases' => ['hours_text', 'hours'], 'kind' => 'text', 'max' => 10000],
            'parking_info' => ['aliases' => ['parking_info'], 'kind' => 'text', 'max' => 10000],
            'nearby_landmarks' => ['aliases' => ['nearby_landmarks'], 'kind' => 'text', 'max' => 10000],
            'warranty_policy' => ['aliases' => ['warranty_policy'], 'kind' => 'text', 'max' => 10000],
            'price_text' => ['aliases' => ['price_text', 'price'], 'kind' => 'text', 'max' => 120],
            'price_table_html' => ['aliases' => ['price_table_html'], 'kind' => 'text', 'max' => 1000000],
            'featured_services_json' => ['aliases' => ['featured_services', 'featured_services_json'], 'kind' => 'json', 'max' => 200000],
            'services_json' => ['aliases' => ['services', 'services_json'], 'kind' => 'json', 'max' => 200000],
            'tags_json' => ['aliases' => ['tags', 'tags_json'], 'kind' => 'json', 'max' => 200000],
            'highlights_json' => ['aliases' => ['highlights', 'highlights_json'], 'kind' => 'json', 'max' => 200000],
            'features_json' => ['aliases' => ['features', 'features_json'], 'kind' => 'json', 'max' => 200000],
            'intro_json' => ['aliases' => ['intro', 'intro_json'], 'kind' => 'json', 'max' => 200000],
            'utilities_json' => ['aliases' => ['utilities', 'utilities_json'], 'kind' => 'json', 'max' => 200000],
        ];
    }
    if ($type === 'doctor') {
        return [
            'slug' => ['aliases' => ['slug'], 'kind' => 'slug', 'max' => 191],
            'name' => ['aliases' => ['name'], 'kind' => 'text', 'max' => 160],
            'title_text' => ['aliases' => ['title_text', 'title'], 'kind' => 'text', 'max' => 190],
            'specialty_text' => ['aliases' => ['specialty_text', 'specialty'], 'kind' => 'text', 'max' => 160],
            'city' => ['aliases' => ['city'], 'kind' => 'text', 'max' => 120],
            'facility_name' => ['aliases' => ['facility_name'], 'kind' => 'text', 'max' => 160],
            'hours_text' => ['aliases' => ['hours_text', 'hours'], 'kind' => 'text', 'max' => 120],
            'price_text' => ['aliases' => ['price_text', 'price'], 'kind' => 'text', 'max' => 120],
            'tags_json' => ['aliases' => ['tags', 'tags_json'], 'kind' => 'json', 'max' => 200000],
            'specialties_json' => ['aliases' => ['specialties', 'specialties_json'], 'kind' => 'json', 'max' => 200000],
            'bio_json' => ['aliases' => ['bio', 'bio_json'], 'kind' => 'json', 'max' => 500000],
        ];
    }
    return [
        'title' => ['aliases' => ['title', 'name'], 'kind' => 'text', 'max' => 220],
        'excerpt' => ['aliases' => ['excerpt', 'summary'], 'kind' => 'text', 'max' => 20000],
        'content' => ['aliases' => ['content', 'content_html'], 'kind' => 'text', 'max' => 1000000],
        'slug' => ['aliases' => ['slug'], 'kind' => 'slug', 'max' => 191],
    ];
}

/** @return array<string,mixed> */
function medical_api_translation_normalize_fields(string $type, array $translated): array
{
    $fields = [];
    foreach (medical_api_translation_field_map($type) as $column => $rule) {
        $found = false;
        $value = null;
        foreach ($rule['aliases'] as $alias) {
            if (array_key_exists($alias, $translated)) {
                $found = true;
                $value = $translated[$alias];
                break;
            }
        }
        if (!$found || $value === null) continue;
        if ($rule['kind'] === 'json') {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (!is_array($decoded)) throw new InvalidArgumentException('Trường ' . $column . ' phải là JSON array/object hợp lệ.');
                $value = $decoded;
            }
            if (!is_array($value)) throw new InvalidArgumentException('Trường ' . $column . ' phải là array/object.');
            $encoded = medical_directory_json_encode($value);
            if (strlen($encoded) > $rule['max']) throw new InvalidArgumentException('Trường ' . $column . ' vượt giới hạn kích thước.');
            $fields[$column] = $encoded;
            continue;
        }
        if (!is_scalar($value)) throw new InvalidArgumentException('Trường ' . $column . ' phải là chuỗi.');
        $value = trim((string) $value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $rule['max']) throw new InvalidArgumentException('Trường ' . $column . ' vượt giới hạn ' . $rule['max'] . ' ký tự.');
        if ($rule['kind'] === 'slug' && $value !== '') {
            $value = function_exists('slugify') ? slugify($value) : strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
            $value = trim(substr($value, 0, 191), '-');
            if ($value === '') throw new InvalidArgumentException('Slug tiếng Anh không hợp lệ.');
        }
        $fields[$column] = $value;
    }
    return $fields;
}

function medical_api_translation_unique_slug(PDO $pdo, string $table, string $slug, int $excludeId): string
{
    $base = substr(trim($slug, '-'), 0, 180);
    $candidate = $base !== '' ? $base : 'translated-content';
    $check = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE slug = :slug AND id <> :id LIMIT 1");
    $suffix = 2;
    while (true) {
        $check->execute([':slug' => $candidate, ':id' => $excludeId]);
        if (!$check->fetchColumn()) return $candidate;
        $tail = '-' . $suffix++;
        $candidate = substr($base, 0, 191 - strlen($tail)) . $tail;
    }
}

function medical_api_translation_prepare_tables(PDO $pdo): bool
{
    medical_directory_ensure_tables($pdo);
    toplist_directory_ensure_tables($pdo);
    foreach (['medical_facilities', 'medical_doctors', 'medical_toplists'] as $table) {
        if (!medical_directory_table_exists($pdo, $table) || !medreview_ensure_translation_columns($pdo, $table)) return false;
    }
    return true;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST, OPTIONS');
    json_response(['ok' => false, 'message' => 'Dùng GET để lấy bài nguồn hoặc POST để nhận bản dịch.'], 405);
}

$pdo = db();
if (!medical_api_translation_prepare_tables($pdo)) {
    json_response(['ok' => false, 'message' => 'Chưa thể khởi tạo cột ngôn ngữ cho cơ sở, bác sĩ và Toplist.'], 503);
}

if ($method === 'GET') {
    $type = strtolower(trim((string) ($_GET['type'] ?? 'facility')));
    $table = medical_api_translation_table($type);
    if ($table === null) json_response(['ok' => false, 'message' => 'type phải là facility, doctor hoặc toplist.'], 422);

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 10)));
    $sourceId = max(0, (int) ($_GET['id'] ?? $_GET['source_id'] ?? 0));
    $offset = ($page - 1) * $limit;
    $where = "s.language_code = 'vi' AND s.status = 'published'";
    $params = [];
    if ($sourceId > 0) {
        $where .= ' AND s.id = :source_id';
        $params[':source_id'] = $sourceId;
    } else {
        $where .= " AND (e.id IS NULL OR e.status = 'draft')";
    }
    $join = " LEFT JOIN `{$table}` e ON e.translation_of_id = s.id AND e.language_code = 'en'";
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` s{$join} WHERE {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $order = $type === 'toplist'
        ? 's.updated_at DESC, s.id ASC'
        : str_replace('COALESCE(city', 'COALESCE(s.city', medical_directory_major_city_priority_sql()) . ', s.id ASC';
    $stmt = $pdo->prepare("SELECT s.*, e.id AS target_translation_id, e.slug AS target_translation_slug, e.status AS target_translation_status
        FROM `{$table}` s{$join}
        WHERE {$where}
        ORDER BY {$order}
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $promptResult = medical_directory_resolve_ai_prompt($pdo, 'translation');
    if (trim((string) ($promptResult['template'] ?? '')) === '') {
        json_response(['ok' => false, 'message' => 'Chưa có Prompt dịch VI→EN. Mở Admin > Prompt AI y tế và lưu prompt Dịch nội dung y tế sang tiếng Anh.'], 404);
    }
    $items = [];
    foreach ($rows as $row) {
        $source = medical_api_translation_source($type, $row);
        $fields = medical_api_translation_fields($type);
        $outputTemplate = medical_api_translation_output_template($type);
        $sourceIdValue = (int) ($row['id'] ?? 0);
        $prompt = medical_directory_ai_prompt_render_template((string) $promptResult['template'], [
            'type' => $type,
            'source_id' => (string) $sourceIdValue,
            'id' => (string) $sourceIdValue,
            'name' => (string) ($source['name'] ?? ''),
            'title' => (string) ($source['title'] ?? $source['name'] ?? ''),
            'fields' => medical_api_json($fields),
            'source_json' => medical_api_json($source),
            'output_template' => medical_api_json($outputTemplate),
        ]);
        $items[] = [
            'id' => $sourceIdValue,
            'source_id' => $sourceIdValue,
            'type' => $type,
            'source_language' => 'vi',
            'target_language' => 'en',
            'source' => $source,
            'translation_id' => (int) ($row['target_translation_id'] ?? 0) ?: null,
            'translation_slug' => (string) ($row['target_translation_slug'] ?? ''),
            'translation_status' => (string) ($row['target_translation_status'] ?? ''),
            'prompt_type' => 'translation',
            'prompt_label' => (string) ($promptResult['label'] ?? ''),
            'prompt_key_used' => (string) ($promptResult['prompt_key_used'] ?? 'translation'),
            'translation_fields' => $fields,
            'output_template' => $outputTemplate,
            'prompt' => $prompt,
        ];
    }

    json_response([
        'ok' => true,
        'type' => $type,
        'source_language' => 'vi',
        'target_language' => 'en',
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => (int) ceil($total / $limit),
        'workflow' => 'GET lấy source và prompt; dịch bằng AI; POST gửi {type, items:[{source_id, translated:{...}}]}. Bản mới được tạo ở trạng thái draft để biên tập và xuất bản trong admin.',
        'receive_contract' => ['type' => $type, 'items' => [['source_id' => 123, 'translated' => $items[0]['output_template'] ?? new stdClass()]]],
        'items' => $items,
    ]);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 8 * 1024 * 1024) json_response(['ok' => false, 'message' => 'JSON bản dịch vượt giới hạn 8MB.'], 413);
$body = read_json_body();
$items = medical_api_translation_parse_items($body);
if ($items === []) json_response(['ok' => false, 'message' => 'Body phải là JSON object hoặc danh sách items.'], 400);
if (count($items) > 100) json_response(['ok' => false, 'message' => 'Mỗi lần chỉ nhận tối đa 100 bản dịch.'], 413);
$defaultType = strtolower(trim((string) ($body['type'] ?? $body['entity_type'] ?? '')));
$results = [];
$errors = [];

foreach ($items as $index => $item) {
    if (!is_array($item)) {
        $errors[] = ['index' => $index, 'message' => 'Item phải là JSON object.'];
        continue;
    }
    $type = strtolower(trim((string) ($item['type'] ?? $item['entity_type'] ?? $defaultType)));
    $table = medical_api_translation_table($type);
    if ($table === null) {
        $errors[] = ['index' => $index, 'message' => 'type phải là facility, doctor hoặc toplist.'];
        continue;
    }
    $sourceId = (int) ($item['source_id'] ?? $item['original_id'] ?? $item['translation_of_id'] ?? $item['id'] ?? 0);
    $sourceSlug = trim((string) ($item['source_slug'] ?? ''));
    if ($sourceId <= 0 && $sourceSlug === '') {
        $errors[] = ['index' => $index, 'type' => $type, 'message' => 'Thiếu source_id (ID bài tiếng Việt gốc).'];
        continue;
    }

    $translated = $item['translated'] ?? $item['translation'] ?? $item['result'] ?? $item['data'] ?? null;
    if (!is_array($translated)) {
        $translated = $item;
        foreach (['type', 'entity_type', 'id', 'source_id', 'original_id', 'translation_of_id', 'source_slug', 'language_code', 'target_language', 'status'] as $metadataKey) unset($translated[$metadataKey]);
    }
    try {
        $normalized = medical_api_translation_normalize_fields($type, $translated);
        if ($normalized === []) throw new InvalidArgumentException('Không có trường dịch hợp lệ trong translated.');

        if ($sourceId > 0) {
            $sourceStmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id AND language_code = 'vi' AND status = 'published' LIMIT 1");
            $sourceStmt->execute([':id' => $sourceId]);
        } else {
            $sourceStmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE slug = :slug AND language_code = 'vi' AND status = 'published' LIMIT 1");
            $sourceStmt->execute([':slug' => $sourceSlug]);
        }
        $sourceRow = $sourceStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($sourceRow)) throw new RuntimeException('Không tìm thấy bài tiếng Việt đã xuất bản.');
        $sourceId = (int) $sourceRow['id'];

        $targetId = medical_directory_create_translation_copy($pdo, $type, $sourceId);
        $targetStmt = $pdo->prepare("SELECT slug, status FROM `{$table}` WHERE id = :id AND translation_of_id = :source_id AND language_code = 'en' LIMIT 1");
        $targetStmt->execute([':id' => $targetId, ':source_id' => $sourceId]);
        $targetRow = $targetStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $targetStatus = (string) ($targetRow['status'] ?? '');
        if ($targetStatus === '') throw new RuntimeException('Không tìm thấy bản tiếng Anh liên kết với bài gốc.');

        if (isset($normalized['slug'])) {
            $normalized['slug'] = medical_api_translation_unique_slug($pdo, $table, (string) $normalized['slug'], $targetId);
        }
        $sets = [];
        $params = [':target_id' => $targetId, ':source_id' => $sourceId];
        foreach ($normalized as $column => $value) {
            if ($column === 'slug' || preg_match('/^[a-z_]+$/', $column) === 1) {
                $placeholder = ':translated_' . $column;
                $sets[] = '`' . $column . '` = ' . $placeholder;
                $params[$placeholder] = $value;
            }
        }
        if ($sets === []) throw new InvalidArgumentException('Không có trường dịch để lưu.');
        $update = $pdo->prepare("UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = :target_id AND translation_of_id = :source_id AND language_code = 'en'");
        $update->execute($params);
        medical_search_cache_invalidate();
        $results[] = [
            'type' => $type,
            'source_id' => $sourceId,
            'translation_id' => $targetId,
            'language_code' => 'en',
            'slug' => (string) ($normalized['slug'] ?? $targetRow['slug'] ?? ''),
            'status' => $targetStatus,
            'saved_fields' => array_keys($normalized),
            'message' => $targetStatus === 'draft' ? 'Đã nhận bản dịch tiếng Anh ở trạng thái nháp.' : 'Đã cập nhật bản tiếng Anh đã xuất bản.',
        ];
    } catch (InvalidArgumentException $e) {
        $errors[] = ['index' => $index, 'type' => $type, 'source_id' => $sourceId, 'message' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('medical translation receive failed: ' . $e->getMessage());
        $errors[] = ['index' => $index, 'type' => $type, 'source_id' => $sourceId, 'message' => 'Không thể lưu bản dịch này. Kiểm tra source_id, cột dữ liệu và thử lại.'];
    }
}

json_response([
    'ok' => $errors === [],
    'received_count' => count($results),
    'results' => $results,
    'errors' => $errors,
], $results === [] ? 422 : 200);
