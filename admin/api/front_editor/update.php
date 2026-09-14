<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? (string) $body['pageKey'] : '';
$id = isset($body['id']) ? (string) $body['id'] : '';
$text = isset($body['text']) ? (string) $body['text'] : '';
$html = isset($body['html']) ? (string) $body['html'] : '';
$type = isset($body['type']) ? (string) $body['type'] : 'text';
$url = isset($body['url']) ? (string) $body['url'] : '';
$iconClass = isset($body['iconClass']) ? (string) $body['iconClass'] : '';

if ($pageKey === '' || $id === '') {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

if ($type === 'image') {
    if ($url === '') {
        json_response(['ok' => false, 'message' => 'Thiếu URL ảnh.'], 422);
    }
    if (!function_exists('front_editor_update_image')) {
        json_response(['ok' => false, 'message' => 'Chưa hỗ trợ sửa ảnh.'], 500);
    }
    $res = front_editor_update_image($pageKey, $id, $url);
    json_response($res, ($res['ok'] ?? false) ? 200 : 500);
}

if ($type === 'icon') {
    if ($iconClass === '') {
        json_response(['ok' => false, 'message' => 'Thiếu class icon.'], 422);
    }
    if (!function_exists('front_editor_update_icon')) {
        json_response(['ok' => false, 'message' => 'Chưa hỗ trợ sửa icon.'], 500);
    }
    $res = front_editor_update_icon($pageKey, $id, $iconClass);
    json_response($res, ($res['ok'] ?? false) ? 200 : 500);
}

if ($text === '') {
    json_response(['ok' => false, 'message' => 'Thiếu nội dung text.'], 422);
}

if (!function_exists('front_editor_update')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ.'], 500);
}

$res = front_editor_update($pageKey, $id, $text, $html);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
