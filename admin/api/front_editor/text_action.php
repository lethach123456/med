<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? trim((string) $body['pageKey']) : '';
$id = isset($body['id']) ? trim((string) $body['id']) : '';
$slotKey = isset($body['slot']) ? trim((string) $body['slot']) : '';
$action = isset($body['action']) ? trim((string) $body['action']) : '';
$direction = isset($body['direction']) ? trim((string) $body['direction']) : '';

if ($pageKey === '' || $action === '') {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

if ($slotKey !== '') {
    if ($action !== 'delete') {
        json_response(['ok' => false, 'message' => 'Text slot chỉ hỗ trợ xoá nội dung.'], 422);
    }
    if (!function_exists('front_editor_text_slot_delete')) {
        json_response(['ok' => false, 'message' => 'Chưa hỗ trợ xoá text slot.'], 500);
    }
    $res = front_editor_text_slot_delete($pageKey, $slotKey);
    json_response($res, ($res['ok'] ?? false) ? 200 : 500);
}

if ($id === '') {
    json_response(['ok' => false, 'message' => 'Thiếu text id.'], 422);
}

if ($action === 'delete') {
    $res = front_editor_text_delete($pageKey, $id);
    json_response($res, ($res['ok'] ?? false) ? 200 : 500);
}

if ($action === 'duplicate') {
    $res = front_editor_text_duplicate($pageKey, $id);
    json_response($res, ($res['ok'] ?? false) ? 200 : 500);
}

if ($action === 'move') {
    $res = front_editor_text_move($pageKey, $id, $direction === 'down' ? 'down' : 'up');
    json_response($res, ($res['ok'] ?? false) ? 200 : 500);
}

json_response(['ok' => false, 'message' => 'Thao tác không hỗ trợ.'], 422);
