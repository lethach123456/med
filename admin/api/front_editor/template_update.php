<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!function_exists('front_editor_template_update')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ template.'], 500);
}

$body = read_json_body();
$id = isset($body['id']) ? (int) $body['id'] : 0;
$name = isset($body['name']) ? (string) $body['name'] : '';
$html = isset($body['html']) ? (string) $body['html'] : '';

if ($id <= 0 || $name === '' || $html === '') {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

$res = front_editor_template_update($id, $name, $html);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
