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
if ($userId <= 0) {
    json_response(['ok' => false, 'message' => 'Tài khoản không hợp lệ.'], 422);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $targetStmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
    $targetStmt->execute([':id' => $userId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($target)) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => 'Không tìm thấy tài khoản cần xoá.'], 404);
    }

    if ($userId === (int) ($_SESSION['admin_user_id'] ?? 0)) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => 'Bạn không thể tự xoá tài khoản đang đăng nhập.'], 422);
    }
    if ((string) ($target['role'] ?? '') === 'admin') {
        $adminRows = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        if (count($adminRows) <= 1) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => 'Hệ thống phải luôn có ít nhất một quản trị viên.'], 422);
        }
    }

    $pdo->prepare('DELETE FROM users WHERE id = :id LIMIT 1')->execute([':id' => $userId]);
    $pdo->commit();
    json_response(['ok' => true, 'message' => 'Đã xoá tài khoản ' . (string) ($target['username'] ?? '') . '.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin user delete failed: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Không thể xoá tài khoản. Vui lòng thử lại.'], 500);
}
