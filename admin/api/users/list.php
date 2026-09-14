<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

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
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => 'Không lấy được danh sách user: ' . $e->getMessage(),
    ], 500);
}

