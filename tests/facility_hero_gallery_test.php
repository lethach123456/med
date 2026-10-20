<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_facility_gallery_view.php';

// Pure model and isolated template fragment renders: no bootstrap, DB or network.
$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/co-so-y-te-chi-tiet.php');
$css = (string) file_get_contents($root . '/assets/css/pages/facility-hero-gallery.css');
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($message); };
$urls = static fn (int $count): array => $count ? array_map(static fn (int $i): string => '/images/facility-' . $i . '.jpg', range(1, $count)) : [];
if (!preg_match('~<!-- Facility hero gallery start -->([\s\S]*?)<!-- Facility hero gallery end -->~', $page, $match)) throw new RuntimeException('Missing isolated gallery template markers.');
$fragment = $match[1];
$render = static function (array $model, string $locale = 'vi', array $metadata = [], string $name = 'Phòng khám & chăm sóc') use ($fragment): array {
    $facilityHeroGallery = $model; $facilityGalleryCount = $model['count'];
    $facilityGalleryLabel = $facilityGalleryCount . ($locale === 'en' ? ' photos' : ' ảnh');
    $facility = ['name' => $name, 'gallery_metadata' => $metadata];
    $tr = static fn (string $vi, string $en): string => $locale === 'en' ? $en : $vi;
    $formatFacilityCount = static fn (int $count): string => (string) $count;
    ob_start();
    try { eval('?>' . $fragment); $html = (string) ob_get_contents(); }
    finally { ob_end_clean(); }
    $dom = new DOMDocument(); $previous = libxml_use_internal_errors(true);
    try { $dom->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>'); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    return [new DOMXPath($dom), $html];
};

foreach ([0 => 'empty', 1 => 'single', 2 => 'duo', 3 => 'trio', 4 => 'quad', 5 => 'mosaic', 6 => 'mosaic', 20 => 'mosaic'] as $count => $layout) {
    $images = $urls($count); $model = facility_hero_gallery_view_model($images);
    $assert($model['images'] === $images && $model['count'] === $count, 'Full image list is retained for count ' . $count);
    $assert($model['layout'] === $layout && $model['hero_index'] === 0 && $model['hero_image'] === ($images[0] ?? ''), 'Correct adaptive layout and hero for count ' . $count);
    $assert(count($model['thumbnails']) === min(4, max(0, $count - 1)) && !array_key_exists(0, $model['thumbnails']), 'At most four previews exclude actual cover for count ' . $count);
    $assert($model['remaining'] === max(0, $count - 1 - count($model['thumbnails'])), 'Overflow count is exact for count ' . $count);
    foreach ($model['thumbnails'] as $index => $url) $assert($images[$index] === $url, 'Thumbnail index maps to full lightbox list');
    foreach (['vi', 'en'] as $locale) {
        [$xpath, $html] = $render($model, $locale);
        $gallery = $xpath->query('//div[@data-gallery-layout]')->item(0);
        $assert($gallery !== null && $gallery->getAttribute('data-gallery-layout') === $layout && (int) $gallery->getAttribute('data-gallery-count') === $count, 'Rendered layout matches model: ' . $count . ' ' . $locale);
        $assert($xpath->query('//img[@src=""]')->length === 0 && !str_contains($html, 'src=""'), 'No empty image request: ' . $count . ' ' . $locale);
        if ($count === 0) {
            $assert($xpath->query('//button | //img')->length === 0, 'Empty gallery is noninteractive and reserves no broken image: ' . $locale);
            $assert(str_contains($html, $locale === 'en' ? 'No facility photos yet' : 'Chưa có ảnh cơ sở'), 'Empty state is localized: ' . $locale);
            continue;
        }
        $hero = $xpath->query('//button[@data-hero-gallery-carousel]')->item(0);
        $assert($hero !== null && $hero->getAttribute('data-gallery-index') === '0', 'One hero button opens correct first image: ' . $count . ' ' . $locale);
        $assert($xpath->query('./img[@loading="eager" and @fetchpriority="high"]', $hero)->length === 1, 'Hero image remains prioritized: ' . $count . ' ' . $locale);
        $thumbs = $xpath->query('//button[contains(concat(" ",normalize-space(@class)," ")," thumb ")]');
        $assert($thumbs->length === count($model['thumbnails']), 'Rendered preview count matches model: ' . $count . ' ' . $locale);
        foreach ($thumbs as $thumb) {
            $index = (int) $thumb->getAttribute('data-gallery-index');
            $assert($xpath->query('./img', $thumb)->item(0)->getAttribute('src') === $model['images'][$index] && $index !== $model['hero_index'], 'Preview never duplicates cover and keeps its actual lightbox index');
        }
        $assert($xpath->query('//button[@data-hero-gallery-prev or @data-hero-gallery-next]')->length === ($count > 1 ? 2 : 0), 'Navigation appears only when needed: ' . $count . ' ' . $locale);
        $assert($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," hero-swipe-hint ")]')->length === ($count > 1 ? 1 : 0), 'Swipe hint appears only for multiple photos');
        $more = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," thumb-more ")]');
        $assert($more->length === ($model['remaining'] > 0 ? 1 : 0), 'Only one overflow overlay appears when extra photos exist');
        if ($model['remaining'] > 0) $assert(str_contains($more->item(0)->textContent, '+' . $model['remaining']), 'Overflow overlay shows true hidden photo count');
        $status = $xpath->query('//*[@data-hero-gallery-status]')->item(0);
        $assert($status !== null && $status->getAttribute('aria-live') === 'polite', 'Dynamic image status is accessible');
    }
}

$nonzero = facility_hero_gallery_view_model($urls(6), '/images/facility-3.jpg');
$assert($nonzero['hero_index'] === 2 && array_keys($nonzero['thumbnails']) === [0, 1, 3, 4] && $nonzero['remaining'] === 1, 'Non-first cover is excluded without reindexing previews');
[$xpath] = $render($nonzero);
$assert($xpath->query('//button[@data-hero-gallery-carousel]')->item(0)->getAttribute('data-gallery-index') === '2', 'Non-first hero keeps original full-list index in markup');
$thumbIndexes = [];
foreach ($xpath->query('//button[contains(concat(" ",normalize-space(@class)," ")," thumb ")]') as $thumb) $thumbIndexes[] = (int) $thumb->getAttribute('data-gallery-index');
$assert($thumbIndexes === [0, 1, 3, 4], 'Rendered thumbnail indexes match non-first-cover model');
$appended = facility_hero_gallery_view_model($urls(2), '/images/new-cover.jpg');
$assert($appended['images'] === ['/images/new-cover.jpg', '/images/facility-1.jpg', '/images/facility-2.jpg'] && $appended['hero_index'] === 0, 'A separate valid cover is prepended exactly once');
$duplicates = facility_hero_gallery_view_model(['/images/a.jpg', ' /images/a.jpg ', '/images/b.jpg', '/images/b.jpg'], '/images/b.jpg');
$assert($duplicates['images'] === ['/images/a.jpg', '/images/b.jpg'] && $duplicates['hero_index'] === 1 && $duplicates['thumbnails'] === [0 => '/images/a.jpg'], 'Duplicate and padded URLs do not create duplicate tiles');
$clean = facility_hero_gallery_view_model(['', ' ', null, [], 'data:image/png;base64,abc', 'javascript:alert(1)', 'file:///tmp/a.jpg', '//evil.example.org/a.jpg', "https://images.example.org/a\n.jpg", 'https://user@images.example.org/a.jpg', 'https://user:pass@images.example.org/a.jpg', '/images/good.jpg'], 'blob:bad');
$assert($clean['images'] === ['/images/good.jpg'] && $clean['hero_image'] === '/images/good.jpg', 'Invalid types, schemes, controls and credential URLs are discarded');
$public = facility_hero_gallery_view_model(['https://images.example.org/a.jpg?x=1&y=2', 'http://images.example.org/b.jpg']);
$assert($public['count'] === 2, 'Valid remote HTTP(S) photos remain usable without fetching');

$dangerName = 'Phòng khám "><img src=x onerror=alert(1)> & cộng đồng';
$specialUrl = 'https://images.example.org/photo.jpg?x=1&y=%22';
$escaped = facility_hero_gallery_view_model([$specialUrl, '/images/other.jpg']);
$metadata = [$specialUrl => ['alt' => 'Lễ tân "sáng" & <b>đẹp</b>'], '/images/other.jpg' => ['caption' => 'Phòng chờ & ghế khám']];
[$xpath, $html] = $render($escaped, 'vi', $metadata, $dangerName);
$assert($xpath->query('//script | //*[@onerror]')->length === 0 && $xpath->query('//img')->length === 2, 'Facility name and metadata cannot inject markup or event handlers');
$hero = $xpath->query('//button[@data-hero-gallery-carousel]/img')->item(0);
$assert($hero->getAttribute('src') === $specialUrl && $hero->getAttribute('alt') === $metadata[$specialUrl]['alt'], 'URL and rich-looking metadata remain escaped attribute values');
$assert(str_contains($html, '&amp;') && str_contains($html, '&quot;') && str_contains($html, '&lt;'), 'Gallery escapes ampersands, quotes and angle brackets');
$assert($xpath->query('//button[contains(concat(" ",normalize-space(@class)," ")," thumb ")]/img')->item(0)->getAttribute('alt') === 'Phòng chờ & ghế khám', 'Thumbnail caption is used when alt is absent');

$assert(str_contains($page, '$facilityHeroGallery = facility_hero_gallery_view_model('), 'Actual detail page consumes pure gallery model');
$assert(str_contains($page, "const gallerySources = <?php echo json_encode(\$facilityHeroGallery['images']"), 'Full normalized image list, not only preview tiles, feeds the lightbox');
$polishAt = strpos($page, '/assets/css/pages/facility-detail-polish.css?v=');
$galleryAt = strpos($page, '/assets/css/pages/facility-hero-gallery.css?v=');
$navAt = strpos($page, '/assets/css/pages/facility-detail-nav.css?v=');
$assert($polishAt !== false && $galleryAt > $polishAt && $navAt > $galleryAt, 'Count-aware gallery CSS loads after polish and before navigation');
$assert(substr_count($page, '/assets/css/pages/facility-hero-gallery.css?v=') === 1 && str_contains($page, "filemtime(__DIR__ . '/assets/css/pages/facility-hero-gallery.css')"), 'Gallery CSS is included once with file-based cache busting');
$assert(str_contains($css, '[data-gallery-layout="single"]') && str_contains($css, '[data-gallery-layout="duo"]') && str_contains($css, '[data-gallery-layout="trio"]') && str_contains($css, '[data-gallery-layout="quad"]'), 'CSS provides explicit count-aware layouts');
$assert(str_contains($css, '@media(max-width:767px)') && str_contains($css, 'touch-action:pan-y') && str_contains($css, 'width:44px;height:44px'), 'Mobile carousel preserves vertical scrolling and usable navigation targets');
$helper = (string) file_get_contents($root . '/medical_facility_gallery_view.php');
$assert(!preg_match('/\b(?:db|file_put_contents|curl_init|dns_get_record|imagejpeg)\s*\(/', $helper), 'Gallery model does not access DB/network or transform/write images');
echo "Facility hero gallery: {$checks} checks passed. Pure model/fragment fixtures only; no DB/network.\n";
