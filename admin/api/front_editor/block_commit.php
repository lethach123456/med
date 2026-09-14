<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!function_exists('front_editor_block_commit')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ lưu thay đổi block.'], 500);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? (string) $body['pageKey'] : '';
$ops = isset($body['ops']) && is_array($body['ops']) ? $body['ops'] : [];

if ($pageKey === '') {
    json_response(['ok' => false, 'message' => 'Thiếu page key.'], 422);
}

$res = front_editor_block_commit($pageKey, $ops);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
