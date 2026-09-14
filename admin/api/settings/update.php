<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();

$fields = [
    'site_title',
    'site_description',
    'site_icon_href',
    'site_home_gallery_lines',
    'site_hotline',
    'site_email',
    'site_address',
    'site_facebook',
    'site_zalo',
    'site_instagram',
];

$updates = [];
foreach ($fields as $f) {
    if (array_key_exists($f, $body)) {
        $val = (string) $body[$f];
        $updates[$f] = trim($val);
    }
}

if (array_key_exists('site_title', $updates) && $updates['site_title'] === '') {
    json_response(['ok' => false, 'message' => 'Vui lòng nhập tiêu đề trang.'], 422);
}

try {
    foreach ($updates as $k => $v) {
        site_setting_set($k, $v);
    }
    json_response(['ok' => true, 'message' => 'Đã lưu cài đặt.']);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => 'Lưu thất bại: ' . $e->getMessage(),
    ], 500);
}
