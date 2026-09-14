<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$type = isset($body['type']) ? (string) $body['type'] : '';
$id = isset($body['id']) ? (int) $body['id'] : 0;

$map = [
    'post' => ['table' => 'posts', 'label' => 'Trang'],
    'category' => ['table' => 'categories', 'label' => 'Chuyên mục'],
    'product' => ['table' => 'products', 'label' => 'Sản phẩm'],
    'product_category' => ['table' => 'product_categories', 'label' => 'Chuyên mục sản phẩm'],
    'project' => ['table' => 'projects', 'label' => 'Dự án'],
    'project_category' => ['table' => 'project_categories', 'label' => 'Chuyên mục dự án'],
];

if (!isset($map[$type]) || $id <= 0) {
    json_response(['ok' => false, 'message' => 'Tham số không hợp lệ.'], 422);
}

try {
    $pdo = db();
    $table = $map[$type]['table'];
    $label = $map[$type]['label'];

    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() <= 0) {
        json_response(['ok' => false, 'message' => 'Không tìm thấy dữ liệu để xoá.'], 404);
    }

    json_response(['ok' => true, 'message' => "Đã xoá: {$label}."]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Xoá thất bại: ' . $e->getMessage()], 500);
}

