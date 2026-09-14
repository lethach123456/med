<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$username = isset($body['username']) ? trim((string) $body['username']) : '';
$password = isset($body['password']) ? (string) $body['password'] : '';

if ($username === '' || $password === '') {
    json_response(['ok' => false, 'message' => 'Vui lòng nhập username và password.'], 422);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(['ok' => false, 'message' => 'Sai username hoặc password.'], 401);
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        json_response(['ok' => false, 'message' => 'Sai username hoặc password.'], 401);
    }

    $_SESSION['admin_user_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = (string) $user['username'];
    $_SESSION['admin_role'] = (string) ($user['role'] ?? 'admin');

    json_response(['ok' => true, 'message' => 'Đăng nhập OK.']);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => 'Đăng nhập thất bại: ' . $e->getMessage(),
    ], 500);
}

