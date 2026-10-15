<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_media_variants.php';
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$brand = '/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg';
$hero = '/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg';
$source = medical_media_variant_source($brand);
$assert(is_array($source), 'Existing brand resolves to local source');
$assert(medical_media_variant_source('https://medreview.vn' . $brand)['key'] === $source['key'], 'Canonical URL shares local variants');
$assert(medical_media_variant_source($brand . '?v=1')['key'] === $source['key'], 'Query parameters do not create variant files');
foreach ([
    'https://example.org' . $brand, 'http://127.0.0.1' . $brand, '//medreview.vn' . $brand,
    'file://' . dirname(__DIR__) . $brand, '/uploads/library/../../db.php',
    '/uploads/library/%2e%2e/' . basename($brand), '/uploads/library/./' . basename($brand),
    '/uploads/library/' . basename($brand) . "\0", 'uploads/library/' . basename($brand),
    '/assets/../uploads/library/2026/07/' . basename($brand), '/assets/icons/favicon.ico',
    'https://medreview.vn:444' . $brand, 'https://other@medreview.vn' . $brand,
] as $unsafe) $assert(medical_media_variant_source($unsafe) === null, 'Reject unsupported/nonlocal path: ' . $unsafe);
$assert(medical_media_variant_widths([128, 64, 128, 65, '640', 1801]) === [64, 128], 'Width list is bounded, sorted and deduplicated');
$before = hash_file('sha256', $source['path']);
foreach ([$brand => [64, 128], $hero => [640, 960, 1280, 1800]] as $url => $widths) {
    $local = medical_media_variant_source($url);
    $originalInfo = getimagesize($local['path']);
    foreach ($widths as $width) {
        $variantUrl = medical_media_variant_url($local, $width);
        $variantPath = dirname(__DIR__) . $variantUrl;
        $info = getimagesize($variantPath);
        $assert($info[2] === IMAGETYPE_WEBP && $info[0] === $width, 'Real WebP with correct width: ' . $variantUrl);
        $assert($info[1] === (int) round($originalInfo[1] * $width / $originalInfo[0]), 'Uncropped original aspect ratio: ' . $variantUrl);
        $assert(filesize($variantPath) < $local['bytes'], 'Variant is smaller than original: ' . $variantUrl);
    }
}
$attributes = medical_media_image_attributes($brand, '53px', [64, 128], 64);
$assert(str_contains($attributes, '-64.webp"') && str_contains($attributes, '128w') && str_contains($attributes, 'sizes="53px"'), 'Small brand source and retina srcset');
$assert(!str_contains($attributes, '.jpg'), 'Oversized brand original is not requested when prebuilt');
$assert(medical_media_responsive_image($hero, [640, 960, 1280, 1800], 1280)['src'] === medical_media_variant_url(medical_media_variant_source($hero), 1280), 'Hero default width selection');
$remote = 'https://example.org/photo.jpg?a=1&b=2';
$assert(medical_media_image_attributes($remote, '100vw') === 'src="https://example.org/photo.jpg?a=1&amp;b=2"', 'Remote image is preserved and escaped without proxy');
$custom = '/assets/icons/apple-touch-icon.png';
$assert(medical_media_image_attributes($custom, '48px', [64, 128], 64) === 'src="' . $custom . '"', 'Custom unbuilt logo is not replaced by MedReview branding');
$assert(!str_contains(medical_media_image_attributes($brand, '53px" onerror="bad', [64, 128], 64), ' onerror="bad'), 'Sizes attribute is escaped');
$assert(hash_file('sha256', $source['path']) === $before, 'Rendering preserves original artwork bytes');
$expectedKey = substr(hash('sha256', 'webp-q78-v1|' . $brand . '|' . hash_file('sha256', $source['path'])), 0, 24);
$assert($source['key'] === $expectedKey, 'Variant identity uses actual content, never checkout timestamps');
$helper = (string) file_get_contents(dirname(__DIR__) . '/medical_media_variants.php');
foreach (['curl_', 'file_put_contents(', 'imagewebp(', 'mkdir(', 'db(', 'medical_media_download'] as $forbidden) {
    $assert(!str_contains($helper, $forbidden), 'Render helper has no network/write/decode operation: ' . $forbidden);
}
echo "Media variants: {$checks} checks passed; no DB or network.\n";
