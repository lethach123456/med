<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Pure tag rendering and existing/local scratch images only: no DB or network.
$previousOrigin = getenv('SITE_CANONICAL_ORIGIN');
putenv('SITE_CANONICAL_ORIGIN=https://social.medreview.vn');
$_SERVER['HTTP_HOST'] = 'attacker.example.org';
$_SERVER['HTTP_X_FORWARDED_HOST'] = 'attacker.example.org';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/site_social_metadata.php';
$root = dirname(__DIR__);
$origin = 'https://social.medreview.vn';
$checks = 0; $scratchFiles = [];
$assert = static function (bool $ok, string $message) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($message); };
$parse = static function (array $meta): array {
    $html = site_social_meta_tags($meta);
    $dom = new DOMDocument(); $previous = libxml_use_internal_errors(true);
    try { $dom->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><head>' . $html . '</head><body></body></html>'); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    $xpath = new DOMXPath($dom); $tags = []; $counts = [];
    foreach ($xpath->query('//meta[@property or @name]') as $node) {
        $key = $node->getAttribute('property') ?: $node->getAttribute('name');
        $tags[$key] = $node->getAttribute('content'); $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    return [$tags, $counts, $xpath, $html];
};
$assertTagSet = static function (array $tags, array $counts) use ($assert): void {
    foreach (['og:type', 'og:title', 'og:description', 'og:url', 'og:site_name', 'og:image', 'og:image:alt',
        'twitter:card', 'twitter:title', 'twitter:description', 'twitter:image', 'twitter:image:alt'] as $key) {
        $assert(isset($tags[$key]) && $tags[$key] !== '' && ($counts[$key] ?? 0) === 1, 'Exactly one non-empty social tag: ' . $key);
    }
    $assert(in_array($tags['twitter:card'], ['summary_large_image', 'summary'], true), 'Twitter receives a valid image card type');
    foreach (['title', 'description', 'image'] as $field) $assert($tags['og:' . $field] === $tags['twitter:' . $field], 'OG/Twitter agree on ' . $field);
    $assert($tags['og:image:alt'] === $tags['twitter:image:alt'], 'OG/Twitter image descriptions agree');
    $assert(!isset($tags['og:locale']) && !isset($tags['Content-Language']), 'Social helper does not duplicate language metadata');
    $assert(array_filter($counts, static fn (int $count): bool => $count !== 1) === [], 'All social property names are unique');
};

try {
    [$empty, $emptyCounts, $emptyXPath] = $parse([]);
    $assertTagSet($empty, $emptyCounts);
    $fallback = $origin . '/assets/social/medreview-share.jpg';
    $fallbackInfo = getimagesize($root . '/assets/social/medreview-share.jpg');
    $assert($fallbackInfo[0] === 1200 && $fallbackInfo[1] === 630 && $fallbackInfo[2] === IMAGETYPE_JPEG, 'Committed default card is an actual 1200×630 JPEG');
    $assert($empty['og:title'] === 'MedReview' && $empty['og:type'] === 'website' && $empty['og:site_name'] === 'MedReview', 'Empty metadata has honest site-level defaults');
    $assert($empty['og:url'] === $origin . '/' && !str_contains(json_encode($empty), 'attacker.example.org'), 'Canonical origin never trusts request host headers');
    $assert($empty['og:image'] === $fallback && $empty['og:image:alt'] === 'MedReview.vn', 'Fallback card receives its actual wordmark alt, not a fictional article photo caption');
    $assert($empty['og:image:width'] === '1200' && $empty['og:image:height'] === '630' && $empty['og:image:type'] === 'image/jpeg', 'Fallback dimensions/MIME reflect actual bytes');
    $assert($empty['og:image:secure_url'] === $fallback, 'HTTPS card exposes matching secure URL');
    $assert($empty['twitter:card'] === 'summary_large_image', 'Wide fallback uses a large Twitter card');
    $assert($emptyXPath->query('//link[@rel="canonical"]')->length === 0, 'Existing page canonical tag remains the sole canonical source');
    $assert(!isset($empty['article:published_time']) && !isset($empty['article:modified_time']), 'Missing publication dates are never invented');

    $meta = ['title' => " <strong>Chăm sóc &amp; sức khỏe</strong> \n \"Nụ cười\" ",
        'description' => "<p>Thông tin đáng tin cậy &amp; hữu ích.</p>\n  Đọc trước khi chọn.",
        'url' => '/en/co-so-y-te?page=2&city=Da%20Nang#providers', 'type' => 'profile',
        'image' => '/assets/brand/medreview-wordmark.png', 'image_alt' => '<b>MedReview</b> &amp; cộng đồng'];
    [$tags, $counts, $xpath, $html] = $parse($meta);
    $assertTagSet($tags, $counts);
    $assert($tags['og:title'] === 'Chăm sóc & sức khỏe "Nụ cười"' && $tags['og:description'] === 'Thông tin đáng tin cậy & hữu ích. Đọc trước khi chọn.', 'Text is decoded, plain and whitespace-collapsed');
    $assert($tags['og:url'] === $origin . '/en/co-so-y-te?page=2&city=Da%20Nang' && $tags['og:type'] === 'profile', 'Canonical filters/page are retained but fragment is stripped');
    $localInfo = getimagesize($root . $meta['image']);
    $assert($tags['og:image'] === $origin . $meta['image'] && $tags['og:image:type'] === 'image/png' && (int) $tags['og:image:width'] === $localInfo[0] && (int) $tags['og:image:height'] === $localInfo[1], 'Existing local image exposes actual PNG dimensions instead of guessed card dimensions');
    $assert($tags['og:image:alt'] === 'MedReview & cộng đồng' && str_contains($html, '&quot;') && str_contains($html, '&amp;'), 'Attribute values are escaped once while DOM values retain their meaning');
    [$bounded] = $parse(['title' => str_repeat('đ', 140), 'description' => str_repeat('ấ', 250), 'image' => $meta['image'], 'image_alt' => str_repeat('ộ', 500)]);
    $assert(mb_strlen($bounded['og:title'], 'UTF-8') <= 100 && mb_strlen($bounded['og:description'], 'UTF-8') <= 200 && mb_strlen($bounded['og:image:alt'], 'UTF-8') <= 420, 'Title/description/alt are bounded in Unicode characters');
    [$injection, $injectionCounts, $injectionXPath] = $parse(['title' => '"><img src=x onerror="alert(1)">', 'description' => '<script>alert(1)</script> &quot; onload=&quot;bad', 'url' => '/?q=%22%3E%3Cscript%3E']);
    $assert($injectionXPath->query('//script | //img | //*[@onload or @onerror]')->length === 0 && ($injectionCounts['og:title'] ?? 0) === 1, 'Text/URL injection cannot create elements, events or duplicate tags');
    $assert(!str_contains($injection['og:title'], '<img') && !str_contains($injection['og:description'], '<script'), 'HTML is not retained as visible meta text');

    foreach (['https://evil.example.org/toplist/a', '//evil.example.org/path', 'javascript:alert(1)', 'data:text/html,test', 'https://person:password@social.medreview.vn/path'] as $url) {
        [$rejected] = $parse(['url' => $url]);
        $assert($rejected['og:url'] === $origin . '/', 'Off-origin/unsafe page URL falls back to canonical homepage: ' . $url);
    }
    [$absolute] = $parse(['url' => $origin . '/cam-nang/topic?page=3#read']);
    $assert($absolute['og:url'] === $origin . '/cam-nang/topic?page=3', 'Trusted absolute canonical URL is retained without its fragment');
    [$invalidType] = $parse(['type' => 'malicious:type']);
    $assert($invalidType['og:type'] === 'website', 'Unknown Open Graph type is not emitted');
    [$wrongTypes] = $parse(['title' => [], 'description' => 42, 'url' => [], 'image' => [], 'image_alt' => [], 'published_time' => []]);
    $assert($wrongTypes['og:title'] === 'MedReview' && $wrongTypes['og:image'] === $fallback && $wrongTypes['og:url'] === $origin . '/', 'Unexpected metadata value types degrade to safe defaults');

    foreach (['https://images.example.org/photo.webp?size=1200', 'http://images.example.org/photo.jpg'] as $image) {
        [$remote] = $parse(['title' => 'Ảnh công khai', 'image' => $image, 'image_alt' => 'Phòng chờ']);
        $assert($remote['og:image'] === $image && $remote['og:image:alt'] === 'Phòng chờ', 'Public HTTP(S) remote image URL and meaningful alt are retained');
        $assert(!isset($remote['og:image:width']) && !isset($remote['og:image:height']) && !isset($remote['og:image:type']), 'Remote dimensions/MIME are never invented from an extension');
        $assert(isset($remote['og:image:secure_url']) === str_starts_with($image, 'https://'), 'secure_url exists only for an HTTPS image');
    }
    foreach (['data:image/png;base64,abc', 'blob:https://medreview.vn/123', 'sandbox:/mnt/data/photo.webp', 'file:///private/tmp/photo.png', 'javascript:alert(1)',
        'https://person:password@images.example.org/p.png', 'http://localhost/p.png', 'http://127.0.0.1/p.png', 'http://10.0.0.2/p.png', 'http://192.168.1.5/p.png',
        'http://169.254.169.254/p.png', 'http://[::1]/p.png', '/assets/social/not-found-' . bin2hex(random_bytes(6)) . '.jpg', '/tests/social_metadata_test.php',
        '/assets/brand/../brand/medreview-wordmark.png', '/assets/brand/%2e%2e/brand/medreview-wordmark.png', '/assets/brand%2fmedreview-wordmark.png', '/assets/icons/apple-touch-icon.png'] as $image) {
        [$invalidImage] = $parse(['title' => 'Bài viết', 'image' => $image, 'image_alt' => 'Không được áp dụng alt ảnh bài cho fallback']);
        $assert($invalidImage['og:image'] === $fallback && $invalidImage['og:image:alt'] === $empty['og:image:alt'], 'Unsafe/missing/non-raster image gets real site fallback: ' . $image);
    }

    // Real valid raster outside the project must not become public via a symlink.
    $outside = tempnam(sys_get_temp_dir(), 'medreview-social-image-');
    if (!is_string($outside)) throw new RuntimeException('Cannot create isolated social test fixture.');
    $scratchFiles[] = $outside;
    if (!copy($root . '/assets/brand/medreview-wordmark.png', $outside)) throw new RuntimeException('Cannot copy synthetic test raster.');
    $linkName = '/assets/social/test-outside-' . bin2hex(random_bytes(10)) . '.png'; $link = $root . $linkName;
    if (!symlink($outside, $link)) throw new RuntimeException('Cannot create isolated symlink fixture.');
    $scratchFiles[] = $link;
    [$outsideTags] = $parse(['image' => $linkName]);
    $assert($outsideTags['og:image'] === $fallback, 'Symlink to a valid raster outside project root is rejected');

    $portraitName = '/assets/social/test-portrait-' . bin2hex(random_bytes(10)) . '.jpg';
    $scratchFiles[] = $root . $portraitName;
    $portrait = imagecreatetruecolor(400, 700); imagefill($portrait, 0, 0, 0xdce8f3);
    try { if (!imagejpeg($portrait, $root . $portraitName, 80)) throw new RuntimeException('Cannot encode isolated portrait fixture.'); }
    finally { imagedestroy($portrait); }
    [$portraitTags] = $parse(['type' => 'profile', 'image' => $portraitName, 'image_alt' => 'Chân dung bác sĩ']);
    $assert($portraitTags['twitter:card'] === 'summary' && $portraitTags['og:image:width'] === '400' && $portraitTags['og:image:height'] === '700', 'Real portrait uses uncropped summary card with actual dimensions');
    $assert($portraitTags['og:image'] === $origin . $portraitName && $portraitTags['og:image:alt'] === 'Chân dung bác sĩ', 'Portrait is retained rather than replaced by generic wide branding');

    [$article] = $parse(['type' => 'article', 'title' => 'Cẩm nang chăm sóc răng', 'url' => '/cam-nang/topic',
        'published_time' => '2026-10-19T09:30:00+07:00', 'modified_time' => '2026-10-20T02:30:00Z', 'section' => 'Nha khoa &amp; sức khỏe']);
    $assert($article['og:type'] === 'article' && strtotime($article['article:published_time']) === strtotime('2026-10-19T09:30:00+07:00') && strtotime($article['article:modified_time']) === strtotime('2026-10-20T02:30:00Z'), 'Article receives explicitly supplied valid ISO dates without changing their instants');
    $assert($article['article:section'] === 'Nha khoa & sức khỏe', 'Article section is decoded plain text');
    foreach (['2026-99-99T12:00:00Z', 'yesterday', '"><script>x</script>'] as $time) {
        [$invalidTime] = $parse(['type' => 'article', 'published_time' => $time, 'modified_time' => $time]);
        $assert(!isset($invalidTime['article:published_time']) && !isset($invalidTime['article:modified_time']), 'Invalid dates are omitted rather than repaired/invented');
    }
    [$notArticle] = $parse(['type' => 'website', 'published_time' => '2026-10-19T09:30:00+07:00', 'section' => 'Nha khoa']);
    $assert(!isset($notArticle['article:published_time']) && !isset($notArticle['article:section']), 'Website does not pretend to be a dated article');

    $pages = ['index.php', 'co-so-y-te.php', 'bac-si.php', 'toplist.php', 'cam-nang.php', 'blog.php', 'news.php',
        'about-us.php', 've-chung-toi.php', 'Tem/contact-medreview.php', 'dich-vu.php', 'services-en.php',
        'ho-so-da-xac-thuc.php', 'danh-muc-y-te.php', 'san-pham.php', 'homepage-new.php',
        'co-so-y-te-chi-tiet.php', 'bac-si-chi-tiet.php', 'toplist-chi-tiet-mau.php', 'Tem/toplist-doctor-detail.php',
        'Tem/handbook-article.php', 'post.php', 'Tem/review-head.php'];
    foreach ($pages as $page) {
        $pageSource = (string) file_get_contents($root . '/' . $page);
        $assert(substr_count($pageSource, 'site_social_meta_tags(') === 1, $page . ': exactly one shared social hook is wired');
        $assert(!preg_match('~<meta\b[^>]*(?:property|name)\s*=\s*[\'"](?:og:|twitter:)~i', $pageSource), $page . ': no competing hardcoded OG/Twitter block remains');
        $assert(str_contains($pageSource, 'rel="canonical"'), $page . ': canonical markup is preserved');
    }
    $dbSource = (string) file_get_contents($root . '/db.php');
    $assert(substr_count($dbSource, '/site_social_metadata.php') === 1, 'Shared bootstrap loads the helper exactly once');
    $helperSource = (string) file_get_contents($root . '/site_social_metadata.php');
    $assert(!preg_match('/\b(?:db|site_setting|file_put_contents|curl_init|dns_get_record|imagejpeg|imagepng|imagewebp)\s*\(/', $helperSource), 'Metadata helper does not query DB, fetch remotely, transform images or write runtime files');

    echo "Social metadata: {$checks} checks passed. Pure tags/local fixtures only; no DB/network.\n";
} finally {
    foreach (array_reverse($scratchFiles) as $file) if (is_link($file) || is_file($file)) unlink($file);
    $previousOrigin === false ? putenv('SITE_CANONICAL_ORIGIN') : putenv('SITE_CANONICAL_ORIGIN=' . $previousOrigin);
}
