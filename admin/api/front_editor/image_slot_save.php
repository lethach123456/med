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
$url = isset($body['url']) ? trim((string) $body['url']) : '';

if ($pageKey === '' || $slotKey === '' || $url === '') {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

if (!function_exists('front_editor_image_slot_set')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ lưu ảnh theo slot.'], 500);
}

$res = front_editor_image_slot_set($pageKey, $slotKey, $url);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
