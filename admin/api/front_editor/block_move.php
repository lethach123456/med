<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!function_exists('front_editor_block_move')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ di chuyển section.'], 500);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? (string) $body['pageKey'] : '';
$blockId = isset($body['blockId']) ? (string) $body['blockId'] : '';
$direction = isset($body['direction']) ? (string) $body['direction'] : 'up';

if ($pageKey === '' || $blockId === '') {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

$res = front_editor_block_move($pageKey, $blockId, $direction);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
