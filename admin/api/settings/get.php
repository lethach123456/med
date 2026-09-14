<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = db();
    $keys = [
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
    $in = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($in)");
    $stmt->execute($keys);
    $rows = $stmt->fetchAll();
    $settings = [];
    foreach ($rows as $r) {
        $k = (string) ($r['setting_key'] ?? '');
        $v = (string) ($r['setting_value'] ?? '');
        if ($k !== '') {
            $settings[$k] = $v;
        }
    }
    json_response(['ok' => true, 'settings' => $settings]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => 'Không tải được settings: ' . $e->getMessage(),
    ], 500);
}
