<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_admin_json();
admin_require_csrf_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$username = isset($body['username']) ? trim((string) $body['username']) : '';
$password = isset($body['password']) ? (string) $body['password'] : '';
$role = isset($body['role']) ? (string) $body['role'] : 'staff';

if ($username === '' || $password === '') {
    json_response(['ok' => false, 'message' => 'Thiếu username hoặc password.'], 422);
}

if (mb_strlen($username, 'UTF-8') > 50) {
    json_response(['ok' => false, 'message' => 'Username quá dài (tối đa 50 ký tự).'], 422);
}

if (strlen($password) < 6) {
    json_response(['ok' => false, 'message' => 'Password phải từ 6 ký tự trở lên.'], 422);
}

if (!in_array($role, ['admin', 'staff'], true)) {
    json_response(['ok' => false, 'message' => 'Role không hợp lệ.'], 422);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)');
    $stmt->execute([
        ':u' => $username,
        ':p' => password_hash($password, PASSWORD_DEFAULT),
        ':r' => $role,
    ]);

    json_response([
        'ok' => true,
        'message' => 'Đã tạo user.',
        'user_id' => (int) $pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'uniq_users_username') !== false) {
        json_response(['ok' => false, 'message' => 'Username đã tồn tại.'], 409);
    }
    error_log('Admin user create failed: ' . $msg);
    json_response(['ok' => false, 'message' => 'Không thể tạo tài khoản. Vui lòng thử lại.'], 500);
}
