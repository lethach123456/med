<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!function_exists('front_editor_template_apply')) {
    json_response(['ok' => false, 'message' => 'Chưa hỗ trợ template.'], 500);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? (string) $body['pageKey'] : '';
$blockId = isset($body['blockId']) ? (string) $body['blockId'] : '';
$templateId = isset($body['templateId']) ? (int) $body['templateId'] : 0;
$position = isset($body['position']) ? (string) $body['position'] : 'replace';

if ($pageKey === '' || $blockId === '' || $templateId <= 0) {
    json_response(['ok' => false, 'message' => 'Thiếu dữ liệu.'], 422);
}

$res = front_editor_template_apply($pageKey, $blockId, $templateId, $position);
json_response($res, ($res['ok'] ?? false) ? 200 : 500);
