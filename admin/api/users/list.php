<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_admin_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = db();
    $stmt = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY id DESC');
    $users = $stmt->fetchAll();

    json_response([
        'ok' => true,
        'users' => $users,
        'current_user_id' => (int) ($_SESSION['admin_user_id'] ?? 0),
    ]);
} catch (Throwable $e) {
    error_log('Admin user list failed: ' . $e->getMessage());
    json_response([
        'ok' => false,
        'message' => 'Không thể tải danh sách người dùng. Vui lòng thử lại.',
    ], 500);
}
