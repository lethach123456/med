<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_public_assets.php';
require_once dirname(__DIR__) . '/medical_media_variants.php';
$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
foreach (['shell', 'home'] as $type) {
    $manifest = json_decode((string) file_get_contents($root . '/assets/css/generated/public-' . $type . '-manifest.json'), true);
    $assert(medical_public_bundle_valid($manifest, $type), 'Generated bundle is current: ' . $type);
    $assert(medical_public_shell_bundle($type) === $manifest['url'], 'Runtime selects prebuilt bundle: ' . $type);
    $css = (string) file_get_contents($root . $manifest['url']);
    $offset = 0;
    foreach (medical_public_shell_sources($type) as $source) {
        $content = (string) file_get_contents($root . $source);
        $position = strpos($css, "/* Source: {$source} */\n" . $content, $offset);
        $assert($position !== false, 'Exact declarations and source cascade retained: ' . $source);
        $offset = $position + strlen($content);
    }
    $stale = $manifest;
    $stale['sources'][array_key_first($stale['sources'])] = str_repeat('0', 64);
    $assert(!medical_public_bundle_valid($stale, $type), 'A changed source invalidates build: ' . $type);
    $stale = $manifest; $stale['sha256'] = str_repeat('0', 64);
    $assert(!medical_public_bundle_valid($stale, $type), 'A damaged bundle invalidates build: ' . $type);
    $stale = $manifest; $stale['url'] = '/assets/css/generated/../../../db.php';
    $assert(!medical_public_bundle_valid($stale, $type), 'Reject path traversal in manifest: ' . $type);
    $stale = $manifest; $stale['url'] = 'https://example.com/style.css';
    $assert(!medical_public_bundle_valid($stale, $type), 'Reject remote manifest URL: ' . $type);
    $stale = $manifest; $stale['sources'] = array_reverse($stale['sources'], true);
    $assert(!medical_public_bundle_valid($stale, $type), 'Reject reordered source cascade: ' . $type);
}
unset($GLOBALS['medical_public_shell_styles_emitted'], $GLOBALS['medical_public_home_assets_emitted']);
$html = medical_public_shell_styles('home');
$assert(substr_count($html, '<link ') === 2, 'Homepage requires only two local CSS requests');
$assert(str_contains($html, '@phosphor-icons/web') && str_contains($html, '/public-home.'), 'Editor can discover local full icon library');
$assert(medical_public_shell_styles() === '', 'Header does not emit duplicate links after homepage head');
$assert(!str_contains($html, 'https://'), 'No font CDN stylesheet is needed');
$assert(!empty($GLOBALS['medical_public_home_assets_emitted']), 'Home and footer suppress their already-bundled styles');
$index = (string) file_get_contents($root . '/index.php');
$home = (string) file_get_contents($root . '/Tem/home.php');
$assert(strpos($index, "medical_public_shell_styles('home')") < strpos($index, '</head>'), 'All first-paint CSS is discovered early in the head');
$assert(!str_contains($index, 'font-awesome') && !str_contains($index, 'fonts.googleapis.com'), 'Unused homepage icon CSS/Google Fonts removed');
$assert(str_contains($index, 'imagesrcset=') && str_contains($index, 'imagesizes=') && str_contains($index, 'media="(max-width:760px)"'), 'Preload matches responsive desktop/mobile hero sources');
$assert(str_contains($home, '<source media="(max-width:760px)"') && str_contains($home, '--home-hero-mobile-image'), 'Mobile picture and background share one prebuilt image');
foreach (['Tem/header.php', 'Tem/home.php', 'Tem/footer.php'] as $template) {
    $assert(str_contains((string) file_get_contents($root . '/' . $template), 'medical_media_image_attributes('), 'Local responsive variants integrated: ' . $template);
}
$media = (string) file_get_contents($root . '/medical_media_variants.php');
$assert(str_contains($media, "hash_file('sha256', \$candidate)"), 'Image cache fingerprint survives deployment mtime changes');
$cache = (string) file_get_contents($root . '/assets/.htaccess');
$assert(str_contains($cache, 'immutable') && !str_contains($cache, 'text/html') && !str_contains($cache, 'application/json'), 'Long cache applies only to static assets, not HTML/API');
echo "Public assets: {$checks} checks passed; no DB, network or writes.\n";
