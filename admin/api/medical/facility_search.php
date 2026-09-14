<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_directory.php';

admin_require_login();
$pdo = db();
medical_directory_ensure_tables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = read_json_body();
    $name = trim((string) ($payload['name'] ?? ''));
    $address = trim((string) ($payload['address_text'] ?? ''));
    $category = trim((string) ($payload['category'] ?? 'Cơ sở y tế')) ?: 'Cơ sở y tế';
    if ($name === '' || $address === '') {
        json_response(['ok' => false, 'message' => 'Vui lòng nhập tên và địa chỉ cơ sở.'], 422);
    }
    $slug = unique_slug($pdo, 'medical_facilities', $name . '-' . $address);
    $stmt = $pdo->prepare("INSERT INTO medical_facilities (slug, name, category, address_text, city, status) VALUES (:slug, :name, :category, :address, '', 'published')");
    $stmt->execute([':slug' => $slug, ':name' => $name, ':category' => $category, ':address' => $address]);
    $facility = ['id' => (int) $pdo->lastInsertId(), 'name' => $name, 'city' => '', 'address_text' => $address, 'image_url' => '', 'rating' => '0.0', 'reviews_count' => 0];
    json_response(['ok' => true, 'item' => $facility]);
}

$term = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($term, 'UTF-8') < 2) {
    json_response(['ok' => true, 'items' => []]);
}

$excluded = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_GET['exclude'] ?? ''))))));
$sql = "SELECT id, name, city, address_text, image_url, rating, reviews_count
        FROM medical_facilities
        WHERE status = 'published'
          AND (name LIKE :name_term OR city LIKE :city_term)";
$likeTerm = '%' . $term . '%';
$params = [':name_term' => $likeTerm, ':city_term' => $likeTerm];

if ($excluded !== []) {
    $placeholders = [];
    foreach ($excluded as $index => $facilityId) {
        $key = ':excluded_' . $index;
        $placeholders[] = $key;
        $params[$key] = $facilityId;
    }
    $sql .= ' AND id NOT IN (' . implode(',', $placeholders) . ')';
}

$sql .= ' ORDER BY rating DESC, reviews_count DESC, display_order ASC, id DESC LIMIT 20';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

json_response(['ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
