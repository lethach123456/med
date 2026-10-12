<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/site_favicon.php';
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$brand = '/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg';
foreach (['', $brand, 'https://medreview.vn' . $brand, 'https://www.medreview.vn' . $brand . '?v=old'] as $url) {
    $tags = site_favicon_markup($url);
    foreach (['/favicon.ico?v=', 'sizes="32x32"', 'sizes="180x180"', 'type="image/png"'] as $part) {
        $assert(str_contains($tags, $part), 'Brand icon output: ' . $part);
    }
    $assert(!str_contains($tags, '.jpg'), 'Large logo is not used as browser favicon');
}
$custom = site_favicon_markup('/uploads/custom.png?a=1&b=2');
$assert(str_contains($custom, '/uploads/custom.png?a=1&amp;b=2') && !str_contains($custom, '/favicon.ico'), 'Custom setting preserved and escaped');
$assert(str_contains(site_favicon_markup('https://example.org' . $brand), 'https://example.org'), 'Foreign brand-like path does not override custom icon');
$root = dirname(__DIR__);
foreach ([32 => 'favicon-32.png', 180 => 'apple-touch-icon.png'] as $size => $name) {
    $info = getimagesize($root . '/assets/icons/' . $name);
    $assert($info !== false && $info[0] === $size && $info[1] === $size && $info[2] === IMAGETYPE_PNG, 'Correct PNG asset dimensions: ' . $name);
}
$ico = file_get_contents($root . '/favicon.ico');
$assert(substr($ico, 0, 6) === pack('vvv', 0, 1, 1), 'Valid one-image ICO header');
$assert(substr($ico, 22) === file_get_contents($root . '/assets/icons/favicon-32.png'), 'ICO contains the same complete PNG');
foreach (['index.php', 'co-so-y-te.php', 'bac-si.php'] as $page) {
    $assert(str_contains((string) file_get_contents($root . '/' . $page), 'site_favicon_tags()'), 'Shared favicon used by ' . $page);
}
echo "Favicon: {$checks} checks passed; no DB or network.\n";
