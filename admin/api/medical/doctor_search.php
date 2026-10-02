<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_directory.php';
require_once __DIR__ . '/../../../toplist_directory.php';
admin_require_login();
$pdo = db(); medical_directory_ensure_tables($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = read_json_body();
    if (!hash_equals(admin_csrf_token(), (string) ($payload['_csrf'] ?? ''))) json_response(['ok' => false, 'message' => 'Phiên biểu mẫu hết hạn.'], 403);
    try {
        $pdo->beginTransaction();
        $result = toplist_directory_resolve_member($pdo, 'doctor', $payload);
        $stmt = $pdo->prepare('SELECT id,name,specialty_text,facility_name,city,address_text,image_url,rating,reviews_count FROM medical_doctors WHERE id=:id');
        $stmt->execute([':id' => $result['id']]); $item = $stmt->fetch(PDO::FETCH_ASSOC);
        $pdo->commit(); medical_search_cache_invalidate();
        json_response(['ok' => true, 'item' => $item, 'created' => $result['created']]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Không thể lưu hồ sơ bác sĩ.'], $e instanceof InvalidArgumentException ? 422 : 500);
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ GET/POST.'], 405);
$term = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($term) < 2) json_response(['ok' => true, 'items' => []]);
$locale = site_normalize_locale((string) ($_GET['locale'] ?? 'vi'));
$sql = "SELECT id,name,specialty_text,facility_name,city,address_text,image_url,rating,reviews_count FROM medical_doctors
    WHERE status='published' AND language_code=:locale AND (name LIKE :name OR city LIKE :city OR specialty_text LIKE :specialty OR facility_name LIKE :facility)";
$params = [':locale' => $locale, ':name' => '%' . $term . '%', ':city' => '%' . $term . '%', ':specialty' => '%' . $term . '%', ':facility' => '%' . $term . '%'];
$excluded = array_slice(array_unique(array_filter(array_map('intval', explode(',', (string) ($_GET['exclude'] ?? ''))), static fn(int $id): bool => $id > 0)), 0, 1000);
if ($excluded !== []) {
    $placeholders = [];
    foreach ($excluded as $index => $id) { $key = ':exclude_' . $index; $placeholders[] = $key; $params[$key] = $id; }
    $sql .= ' AND id NOT IN (' . implode(',', $placeholders) . ')';
}
$stmt = $pdo->prepare($sql . ' ORDER BY display_order ASC,id DESC LIMIT 20'); $stmt->execute($params);
json_response(['ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
