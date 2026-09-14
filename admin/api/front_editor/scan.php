<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$pageKey = isset($body['pageKey']) ? (string) $body['pageKey'] : '';

$items = [];
if (function_exists('front_editor_scan')) {
    $items = front_editor_scan($pageKey);
}

$out = [];
foreach ($items as $it) {
    $out[] = [
        'id' => (string) ($it['id'] ?? ''),
        'tag' => (string) ($it['tag'] ?? ''),
        'text' => (string) ($it['text'] ?? ''),
    ];
}

json_response(['ok' => true, 'items' => $out]);

