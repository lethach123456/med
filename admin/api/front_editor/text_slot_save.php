<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? trim((string) $body['pageKey']) : '';
$slotKey = isset($body['slot']) ? trim((string) $body['slot']) : '';
$text = isset($body['text']) ? trim((string) $body['text']) : '';
$html = isset($body['html']) ? (string) $body['html'] : '';

if ($pageKey === '' || $slotKey === '' || $text === '') {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

if (!function_exists('front_editor_text_slot_set')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ lưu text theo slot.'], 500);
}

$res = front_editor_text_slot_set($pageKey, $slotKey, $text, $html);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
