<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!function_exists('front_editor_block_delete')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ xoá section.'], 500);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? (string) $body['pageKey'] : '';
$blockId = isset($body['blockId']) ? (string) $body['blockId'] : '';

if ($pageKey === '' || $blockId === '') {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

$res = front_editor_block_delete($pageKey, $blockId);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
