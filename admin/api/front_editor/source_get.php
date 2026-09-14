<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!function_exists('front_editor_page_source_get')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ đọc source toàn trang.'], 500);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? trim((string) $body['pageKey']) : '';

if ($pageKey === '') {
    json_response(['ok' => false, 'message' => 'Thiếu pageKey.'], 422);
}

$res = front_editor_page_source_get($pageKey);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
