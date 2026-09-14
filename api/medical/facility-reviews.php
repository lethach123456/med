<?php
declare(strict_types=1);
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../medical_directory.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
medical_directory_ensure_tables($pdo);
$facilityId = (int) ($_GET['facility_id'] ?? 0);
$slug = trim((string) ($_GET['facility_slug'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(20, max(1, (int) ($_GET['limit'] ?? 3)));
$offset = ($page - 1) * $limit;
$service = trim((string) ($_GET['service'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest');
$hasImages = (string) ($_GET['has_images'] ?? '') === '1';
if ($facilityId <= 0 && $slug === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Thiếu facility_id hoặc facility_slug'], JSON_UNESCAPED_UNICODE);
    exit;
}
$where = "status = 'published' AND ";
$params = [];
if ($facilityId > 0) {
    $where .= '(facility_id = :facility_id OR (facility_id IS NULL AND facility_slug = :slug))';
    $params[':facility_id'] = $facilityId;
    $params[':slug'] = $slug;
} else {
    $where .= 'facility_slug = :slug';
    $params[':slug'] = $slug;
}
$where .= $service !== '' ? ' AND service_text LIKE :service' : '';
if ($service !== '') $params[':service'] = '%' . $service . '%';
if ($hasImages) {
    // `thumbs_json` is TEXT so this stays compatible with older rows that
    // only have a before/after image. Do not treat an empty JSON array as a photo.
    $where .= " AND (COALESCE(TRIM(before_image_url), '') <> '' OR COALESCE(TRIM(after_image_url), '') <> '' OR (COALESCE(TRIM(thumbs_json), '') <> '' AND TRIM(thumbs_json) NOT IN ('[]', 'null')))";
}
$order = $sort === 'highest' ? 'rating DESC, id DESC' : 'display_order ASC, id DESC';
$count = $pdo->prepare("SELECT COUNT(*) FROM medical_reviews WHERE {$where}");
$count->execute($params);
$total = (int) $count->fetchColumn();
$stmt = $pdo->prepare("SELECT * FROM medical_reviews WHERE {$where} ORDER BY {$order} LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) $stmt->bindValue($key, $value, $key === ':facility_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = array_map('medical_directory_review_from_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
echo json_encode(['ok' => true, 'page' => $page, 'limit' => $limit, 'total' => $total, 'items' => $items, 'has_more' => ($offset + count($items)) < $total], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
