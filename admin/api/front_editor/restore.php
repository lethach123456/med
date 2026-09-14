<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$id = isset($body['id']) ? (int) $body['id'] : 0;
if ($id <= 0) {
    json_response(['ok' => false, 'message' => 'Thiếu id.'], 422);
}

if (!function_exists('front_editor_allowed_pages')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ.'], 500);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, page_key, element_id, admin_user_id, content_before
                           FROM front_editor_history
                           WHERE id = :id
                           LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(['ok' => false, 'message' => 'Không tìm thấy lịch sử.'], 404);
    }

    $pageKey = (string) ($row['page_key'] ?? '');
    $map = front_editor_allowed_pages();
    if (!isset($map[$pageKey])) {
        json_response(['ok' => false, 'message' => 'Page không hợp lệ.'], 422);
    }
    $file = $map[$pageKey];
    if (!is_file($file) || !is_writable($file)) {
        json_response(['ok' => false, 'message' => 'File không tồn tại hoặc không ghi được.'], 500);
    }

    $current = file_get_contents($file);
    if (!is_string($current)) {
        json_response(['ok' => false, 'message' => 'Không đọc được file hiện tại.'], 500);
    }
    $restoreTo = (string) ($row['content_before'] ?? '');
    $ok = file_put_contents($file, $restoreTo);
    if ($ok === false) {
        json_response(['ok' => false, 'message' => 'Khôi phục thất bại.'], 500);
    }

    $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
    $ins = $pdo->prepare("INSERT INTO front_editor_history
        (page_key, element_id, admin_user_id, action, old_text, new_text, content_before, content_after)
        VALUES (:page_key, :element_id, :admin_user_id, 'restore', :old_text, :new_text, :before, :after)");
    $ins->execute([
        ':page_key' => $pageKey,
        ':element_id' => (string) ($row['element_id'] ?? ''),
        ':admin_user_id' => $adminUserId,
        ':old_text' => 'restore_from_' . $id,
        ':new_text' => 'restore_to_before',
        ':before' => $current,
        ':after' => $restoreTo,
    ]);

    json_response(['ok' => true, 'message' => 'Đã khôi phục file.']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}

