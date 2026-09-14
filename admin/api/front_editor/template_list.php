<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!function_exists('front_editor_template_list')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ template.'], 500);
}

$body = read_json_body();
$limit = isset($body['limit']) ? (int) $body['limit'] : 100;
$res = front_editor_template_list($limit);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
