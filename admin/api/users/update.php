<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_admin_json();
admin_require_csrf_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$userId = (int) ($body['id'] ?? 0);
$username = trim((string) ($body['username'] ?? ''));
$role = (string) ($body['role'] ?? '');
$password = (string) ($body['password'] ?? '');

if ($userId <= 0) {
    json_response(['ok' => false, 'message' => 'Tài khoản không hợp lệ.'], 422);
}
if ($username === '' || mb_strlen($username, 'UTF-8') > 50) {
    json_response(['ok' => false, 'message' => 'Username là bắt buộc và tối đa 50 ký tự.'], 422);
}
if (!in_array($role, ['admin', 'staff'], true)) {
    json_response(['ok' => false, 'message' => 'Role không hợp lệ.'], 422);
}
if ($password !== '' && strlen($password) < 6) {
    json_response(['ok' => false, 'message' => 'Password phải từ 6 ký tự trở lên.'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $targetStmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
    $targetStmt->execute([':id' => $userId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($target)) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => 'Không tìm thấy tài khoản cần cập nhật.'], 404);
    }

    $currentUserId = (int) ($_SESSION['admin_user_id'] ?? 0);
    $targetRole = (string) ($target['role'] ?? 'staff');
    if ($userId === $currentUserId && $role !== 'admin') {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => 'Bạn không thể tự hạ quyền quản trị của mình.'], 422);
    }
    if ($targetRole === 'admin' && $role !== 'admin') {
        $adminRows = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        if (count($adminRows) <= 1) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => 'Hệ thống phải luôn có ít nhất một quản trị viên.'], 422);
        }
    }

    $params = [':id' => $userId, ':username' => $username, ':role' => $role];
    $set = ['username = :username', 'role = :role'];
    if ($password !== '') {
        $set[] = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id LIMIT 1');
    $stmt->execute($params);
    $pdo->commit();

    if ($userId === $currentUserId) {
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_role'] = $role;
    }

    json_response([
        'ok' => true,
        'message' => $password === '' ? 'Đã cập nhật tài khoản.' : 'Đã cập nhật tài khoản và mật khẩu.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = $e->getMessage();
    if (stripos($message, 'Duplicate') !== false || stripos($message, 'uniq_users_username') !== false) {
        json_response(['ok' => false, 'message' => 'Username đã tồn tại.'], 409);
    }
    error_log('Admin user update failed: ' . $message);
    json_response(['ok' => false, 'message' => 'Không thể cập nhật tài khoản. Vui lòng thử lại.'], 500);
}
