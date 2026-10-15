<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/scripts/build_home_image_variants.php';
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$sources = medical_media_remote_variant_sources();
$assert(count($sources) === 1, 'Only the visually verified public image source is cached');
foreach (['8.8.8.8', '1.1.1.1', '2001:4860:4860::8888', '2606:4700:4700::1111'] as $ip) {
    $assert(medical_home_image_is_public_ip($ip), 'Allow global unicast address: ' . $ip);
}
foreach (['not-an-ip', '0.0.0.0', '10.0.0.1', '100.64.0.1', '100.127.255.254', '127.0.0.1', '169.254.169.254',
    '172.16.0.1', '192.168.1.1', '192.0.0.1', '192.0.2.1', '198.18.0.1', '198.51.100.1', '203.0.113.1',
    '224.0.0.1', '255.255.255.255', '::', '::1', '::ffff:127.0.0.1', '::ffff:8.8.8.8', 'fc00::1', 'fe80::1',
    'ff02::1', '2001:db8::1', '2001::1', '2002:7f00:1::'] as $ip) {
    $assert(!medical_home_image_is_public_ip($ip), 'Reject private/special-use address: ' . $ip);
}
$source = $sources[0];
$assert(medical_home_image_is_safe_target($source, $source), 'Exact source is safe');
$assert(medical_home_image_is_safe_target('https://gonight.vn/redirected-image.webp', $source), 'Same-host HTTPS redirect is safe');
foreach (['http://gonight.vn/image.webp', 'https://example.org/image.webp', 'https://127.0.0.1/image.webp',
    'https://gonight.vn:444/image.webp', 'https://user@ gonight.vn/image.webp', 'https://user@gonight.vn/image.webp',
    'https://gonight.vn/image.webp#fragment', "https://gonight.vn/\nimage.webp", 'file:///private/image.webp'] as $url) {
    $assert(!medical_home_image_is_safe_target($url, $source), 'Unsafe redirect denied: ' . $url);
}
$assert(medical_home_image_redirect_url($source, '/new.webp') === 'https://gonight.vn/new.webp', 'Root-relative redirect resolved');
$assert(medical_home_image_redirect_url($source, 'new.webp') === 'https://gonight.vn/wp-content/uploads/2026/04/new.webp', 'Path-relative redirect resolved');
$assert(medical_home_image_redirect_url($source, '?size=1') === $source . '?size=1', 'Query-only redirect resolved');
$assert(medical_home_image_redirect_url($source, "\nhttps://example.org") === null, 'Control character redirect rejected');
$manifest = medical_media_remote_variant_manifest();
foreach ($sources as $url) {
    $image = medical_media_remote_responsive_image($url);
    $assert(is_array($image) && str_contains($image['src'], '-640.webp'), 'Prebuilt 640px default source available');
    $assert(str_contains($image['srcset'], '320w') && str_contains($image['srcset'], '640w') && str_contains($image['srcset'], '960w'), 'All three responsive widths are available');
    $entry = $manifest['images'][$url];
    foreach ($entry['variants'] as $variant) {
        $path = dirname(__DIR__) . $variant['url'];
        $info = getimagesize($path);
        $assert($info[2] === IMAGETYPE_WEBP && $info[0] === $variant['width'] && $info[1] === $variant['height'], 'Valid WebP width/height');
        $assert($info[1] === max(1, (int) round($entry['height'] * $info[0] / $entry['width'])), 'Original aspect ratio preserved without crop');
        $assert(str_contains($variant['url'], substr(hash_file('sha256', $path), 0, 24)), 'URL fingerprint hashes actual variant contents');
        $assert($variant['bytes'] === filesize($path) && $variant['bytes'] < $entry['source_bytes'], 'Variant byte count matches and is smaller');
    }
    $bad = $manifest;
    $bad['images'][$url]['variants'][0]['url'] = '/assets/media/variants/remote-' . str_repeat('0', 24) . '-320.webp';
    $assert(medical_media_remote_image_from_manifest($url, $bad) === null, 'Missing generated file disables entry');
    $bad['images'][$url]['variants'][0]['url'] = 'https://example.org/image.webp';
    $assert(medical_media_remote_image_from_manifest($url, $bad) === null, 'Manifest cannot inject a remote URL');
    $bad['images'][$url]['variants'][0]['url'] = '/assets/media/variants/../../icons/favicon-32.png';
    $assert(medical_media_remote_image_from_manifest($url, $bad) === null, 'Manifest path traversal rejected');
    $bad = $manifest;
    $bad['images'][$url]['variants'][0]['bytes']++;
    $assert(medical_media_remote_image_from_manifest($url, $bad) === null, 'Corrupt/mismatched generated file disabled');
    $bad = $manifest;
    $bad['images'][$url]['variants'][0]['height']++;
    $assert(medical_media_remote_image_from_manifest($url, $bad) === null, 'Aspect ratio metadata must be exact');
    $assert(medical_media_remote_image_from_manifest($url, $manifest, [64, 128], 64) === null, 'Unbuilt requested widths do not override image');
    $only = medical_media_remote_image_from_manifest($url, $manifest, [320], 960);
    $assert(is_array($only) && str_contains($only['src'], '-320.webp') && !str_contains($only['srcset'], '640w'), 'Width filtering retains largest available fallback');
}
$assert(medical_media_remote_responsive_image($source . '?custom=1') === null, 'Customized URL is not silently replaced');
$assert(medical_media_remote_responsive_image('https://example.org/image.jpg') === null, 'Unknown URL has no proxy/cache behavior');
$assert(medical_media_remote_responsive_image('https://cdn.bcare.vn/resize_1000x1000/2020/09/09/BS.Lexuancung_mat_09092020113633.jpg') === null, 'CDN returning a generic doctor placeholder is not frozen as a real profile image');
$assert(medical_media_remote_image_from_manifest($source, []) === null, 'Missing manifest keeps original');
$helper = (string) file_get_contents(dirname(__DIR__) . '/medical_media_remote_variants.php');
foreach (['curl_', 'dns_get_record(', 'file_put_contents(', 'mkdir(', 'imagewebp(', 'imagecreate', 'db('] as $forbidden) {
    $assert(!str_contains($helper, $forbidden), 'Runtime helper is read-only/no network: ' . $forbidden);
}
echo "Home image variants: {$checks} checks passed; tests use no DB/network.\n";
