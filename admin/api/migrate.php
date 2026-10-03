<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../schema_migrations.php';

admin_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = db();
    medreview_run_all_schema_migrations($pdo);
    try {
        $pdo->prepare('INSERT INTO migration_log (notes) VALUES (:n)')
            ->execute([':n' => 'Explicit core/medical/media schema migration; no demo seed or editorial reset.']);
    } catch (Throwable $e) { /* optional migration audit log */ }
    json_response([
        'ok' => true,
        'message' => 'Đã cập nhật cấu trúc DB, trình sửa giao diện và hàng chờ ảnh. Không thêm dữ liệu mẫu, không reset nội dung.',
        'db_name' => (string) $pdo->query('SELECT DATABASE()')->fetchColumn(),
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Migrate thất bại: ' . $e->getMessage()], 500);
}
