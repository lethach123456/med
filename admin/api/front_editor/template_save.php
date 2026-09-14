<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!function_exists('front_editor_template_save')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ template.'], 500);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? (string) $body['pageKey'] : '';
$blockId = isset($body['blockId']) ? (string) $body['blockId'] : '';
$name = isset($body['name']) ? (string) $body['name'] : '';
$saveAll = !empty($body['saveAll']);

if ($pageKey === '' || $name === '' || (!$saveAll && $blockId === '')) {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

$res = front_editor_template_save($pageKey, $blockId, $name, $saveAll);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
