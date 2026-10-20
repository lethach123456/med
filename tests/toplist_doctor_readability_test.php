<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('TOPLIST_DETAIL_FIXTURE_ONLY', true);
require __DIR__ . '/toplist_detail_view_test.php';

/** Pure render fixtures: no database bootstrap, file writes, or remote requests. */
function td_readability_rows(int $count): array
{
    $base = toplist_detail_test_rows()[0];
    $rows = [];
    for ($index = 1; $index <= $count; $index++) {
        $paragraphs = '';
        for ($paragraph = 1; $paragraph <= 8; $paragraph++) {
            $paragraphs .= '<p>FULL_PROFILE_' . $index . '_PARAGRAPH_' . $paragraph . ' — Nội dung hồ sơ được giữ đầy đủ để đọc và đối chiếu.</p>';
        }
        $withPhoto = $index % 3 !== 0;
        $rows[] = array_replace($base, [
            'id' => 91000 + $index,
            'rank_order' => $index,
            'name' => 'Bác sĩ Kiểm thử hồ sơ ' . $index,
            'slug' => 'bac-si-kiem-thu-ho-so-' . $index,
            'content' => '<h3>Chuyên môn ' . $index . '</h3>' . $paragraphs,
            'rating' => $index === 1 ? 4.5 : 0,
            'reviews_count' => $index === 1 ? 8 : 0,
            'verified' => $index === 1 ? 1 : 0,
            'image_url' => $withPhoto ? 'https://example.org/portraits/' . $index . '.jpg' : '',
            'gallery_json' => $withPhoto ? json_encode([
                ['url' => 'https://example.org/portraits/' . $index . '.jpg', 'caption' => 'Bác sĩ ' . $index, 'alt' => 'Chân dung bác sĩ ' . $index],
                ['url' => 'https://example.org/rooms/' . $index . '.jpg', 'caption' => 'Phòng khám ' . $index, 'alt' => 'Không gian phòng khám ' . $index],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]',
        ]);
    }
    return $rows;
}

function td_readability_render(array $rows, string $locale = 'vi', string $type = 'doctor', string $featuredImage = ''): string
{
    $toplistLanguage = $locale;
    $toplistEntityType = $type;
    $title = $locale === 'en' ? 'Doctor Toplist — complete profile fixtures' : 'Top bác sĩ — kiểm tra nội dung hồ sơ đầy đủ';
    $description = $locale === 'en' ? 'Synthetic profiles for read-only presentation tests.' : 'Hồ sơ mẫu chỉ dùng kiểm tra giao diện, không phải dữ liệu y tế công khai.';
    $heroImage = $featuredImage;
    $toplist = [
        'featured_image_url' => $featuredImage,
        'content' => '<h2>Giới thiệu</h2><p>INTRO_FULL_CONTENT — thông tin tham khảo trước khi lựa chọn.</p>',
        'updated_at' => '2026-10-13 12:00:00',
    ];
    $tdLabels = medical_toplist_detail_labels($locale, $type === 'mixed');
    $tdMembers = medical_toplist_detail_members($rows, $locale);
    ob_start();
    require dirname(__DIR__) . '/Tem/toplist-doctor-detail-content.php';
    return (string) ob_get_clean();
}

function td_readability_dom(string $html): array
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return [$dom, new DOMXPath($dom)];
}

function td_readability_class(string $class): string
{
    return 'contains(concat(" ",normalize-space(@class)," ")," ' . $class . ' ")';
}

function td_readability_gallery(DOMXPath $xpath): array
{
    $script = $xpath->query('//script[@id="tdGalleryData"]')->item(0);
    if (!$script) throw new RuntimeException('Gallery JSON script must exist');
    return json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR);
}

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($label);
};
$one = static function (DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode {
    return $xpath->query($query, $context)->item(0);
};
$count = static function (DOMXPath $xpath, string $query, ?DOMNode $context = null): int {
    return $xpath->query($query, $context)->length;
};

foreach (['vi', 'en'] as $locale) {
    $labels = medical_toplist_detail_labels($locale, false);
    foreach ([0, 1, 10, 20] as $memberCount) {
        $rows = td_readability_rows($memberCount);
        $members = medical_toplist_detail_members($rows, $locale);
        $html = td_readability_render($rows, $locale);
        [$dom, $xpath] = td_readability_dom($html);
        $context = $locale . ' / ' . $memberCount . ' members: ';
        $assert($count($xpath, '//main[@id="td-main"]') === 1, $context . 'one named main article target');
        $assert($count($xpath, '//h1') === 1, $context . 'one page heading');
        $assert($count($xpath, '//article[' . td_readability_class('td-doctor') . ']') === $memberCount, $context . 'all stored members render once');
        $assert($count($xpath, '//*[' . td_readability_class('td-hero-image') . ']') === 0, $context . 'first doctor image does not become a fabricated editorial cover');
        $assert($count($xpath, '//img[@src=""]') === 0, $context . 'no empty image URLs');
        $assert(!str_contains($html, 'PRIVATE TEST'), $context . 'private editorial fields excluded');
        $assert(str_contains($html, 'INTRO_FULL_CONTENT'), $context . 'complete introduction is retained');

        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            $assert(!isset($ids[$id]), $context . 'unique DOM id ' . $id);
            $ids[$id] = true;
        }
        foreach ($xpath->query('//a[starts-with(@href,"#")]') as $anchor) {
            $fragment = substr($anchor->getAttribute('href'), 1);
            $assert($fragment !== '' && isset($ids[$fragment]), $context . 'fragment points to a rendered target ' . $fragment);
        }
        foreach ($xpath->query('//*[@aria-labelledby]') as $element) {
            foreach (preg_split('/\s+/', trim($element->getAttribute('aria-labelledby'))) as $id) {
                $assert(isset($ids[$id]), $context . 'accessible heading reference exists ' . $id);
            }
        }
        $assert($count($xpath, '//a//button | //button//a | //summary//a | //summary//button | //a//summary | //button//summary') === 0, $context . 'interactive controls are not nested');

        $summary = $one($xpath, '//*[' . td_readability_class('td-hero-summary') . ']');
        $assert($memberCount === 0 ? $summary === null : $summary !== null, $context . 'article header only summarizes actual profiles');
        if ($memberCount > 0) {
            $summaryCount = $one($xpath, './/strong', $summary);
            $assert($summaryCount !== null && preg_match('/^\s*' . $memberCount . '\s*' . preg_quote($labels['count'], '/') . '\s*$/u', $summaryCount->textContent) === 1, $context . 'summary uses actual member count and localized unit');
        }
        $avatarNodes = $xpath->query('//*[' . td_readability_class('td-summary-avatars') . ']//img');
        $assert($avatarNodes->length <= min(3, $memberCount), $context . 'header uses at most three real preview photos');
        $knownImages = array_column($members, 'image');
        foreach ($avatarNodes as $avatar) {
            $assert(in_array($avatar->getAttribute('src'), $knownImages, true), $context . 'preview portrait comes from actual member data');
        }

        if ($memberCount === 0) {
            $assert(str_contains($html, $labels['empty']), $context . 'honest empty-state text');
            $assert($count($xpath, '//*[' . td_readability_class('td-comparison') . ']') === 0, $context . 'no empty comparison');
            $assert($count($xpath, '//*[' . td_readability_class('td-sidebar') . ']') === 0, $context . 'no empty sidebar');
            $assert($count($xpath, '//*[' . td_readability_class('td-mobile-toc') . ']') === 0, $context . 'no empty mobile contents');
            continue;
        }

        $assert($count($xpath, '//aside[' . td_readability_class('td-sidebar') . ']') === 1, $context . 'one sidebar only');
        $assert($count($xpath, '//aside//nav[' . td_readability_class('td-toc') . ']') === 1, $context . 'one sidebar contents list only');
        $assert($count($xpath, '//aside//a[' . td_readability_class('td-compare-item') . ']') === 0, $context . 'comparison is not repeated in the sidebar');
        $assert($count($xpath, '//aside//*[' . td_readability_class('td-reading-note') . ']') === 1, $context . 'sidebar has a reference disclaimer');
        $assert($count($xpath, '//details[' . td_readability_class('td-mobile-toc') . ']/summary') === 1, $context . 'mobile contents is a native disclosure');
        $assert($count($xpath, '//details[' . td_readability_class('td-mobile-toc') . ']//nav[' . td_readability_class('td-toc') . ']//a[starts-with(@href,"#rank-")]') === $memberCount, $context . 'mobile contents includes every member');

        $comparison = $one($xpath, '//details[' . td_readability_class('td-comparison-disclosure') . ']');
        $assert($comparison !== null && !$comparison->hasAttribute('open'), $context . 'comparison is a native disclosure, closed initially');
        $assert($count($xpath, './summary', $comparison) === 1, $context . 'comparison has one summary control');
        $desktop = $one($xpath, './/*[' . td_readability_class('td-comparison-desktop') . ']', $comparison);
        $assert($desktop !== null, $context . 'desktop comparison is independently responsive');
        $assert($count($xpath, './/thead/tr/th[@scope="col"]', $desktop) === 5, $context . 'desktop comparison retains five semantic columns');
        $assert($count($xpath, './/tbody/tr', $desktop) === $memberCount, $context . 'desktop comparison includes every member');
        $assert($count($xpath, './/tbody/tr/th[@scope="row"]', $desktop) === $memberCount, $context . 'comparison identifies every member row');
        $assert($count($xpath, './/*[@role="region" and @tabindex="0"]', $desktop) === 1, $context . 'wide table retains keyboard scrolling');
        $mobile = $one($xpath, './/ul[' . td_readability_class('td-comparison-mobile') . ']', $comparison);
        $assert($mobile !== null && $count($xpath, './li', $mobile) === $memberCount, $context . 'mobile comparison uses a semantic list');
        $assert($count($xpath, './li/dl', $mobile) === $memberCount, $context . 'mobile comparison presents labeled facts without a squeezed table');
        $assert($count($xpath, './/a[starts-with(@href,"#rank-")]', $mobile) === $memberCount, $context . 'mobile comparison links to every full profile');

        $galleryData = td_readability_gallery($xpath);
        $assert(count($galleryData['galleries']) === $memberCount, $context . 'lightbox retains every member gallery record');
        $articles = $xpath->query('//article[' . td_readability_class('td-doctor') . ']');
        foreach ($articles as $index => $article) {
            $member = $members[$index];
            $rank = $member['rank'];
            $memberContext = $context . 'rank ' . $rank . ': ';
            $assert($article->getAttribute('id') === 'rank-' . $rank && $article->getAttribute('data-td-member') === (string) $rank, $memberContext . 'stored order and stable lightbox rank retained');
            $header = $one($xpath, './*[' . td_readability_class('td-doctor-header') . ']', $article);
            $assert($header !== null, $memberContext . 'compact identity header exists');
            $assert($count($xpath, './*[' . td_readability_class('td-doctor-identity') . ']', $header) === 1, $memberContext . 'identity sits beside portrait');
            $assert($count($xpath, './/h2[@id="tdMember' . $rank . '"]', $header) === 1, $memberContext . 'profile heading is in its identity header');
            $profileLink = $one($xpath, './/h2/a', $header);
            $assert($profileLink instanceof DOMElement && $profileLink->getAttribute('href') === $member['profile_url'], $memberContext . 'profile link retains locale and slug');
            $covers = $xpath->query('.//*[' . td_readability_class('td-cover') . ']', $article);
            $assert($covers->length === 1 && $count($xpath, './/*[' . td_readability_class('td-cover') . ']//img', $article) === ($member['image'] !== '' ? 1 : 0), $memberContext . 'portrait image only exists when an image is available');
            foreach ($covers as $cover) {
                $assert($cover->parentNode->isSameNode($header), $memberContext . 'portrait is within the compact horizontal header');
            }
            if ($member['image'] === '') {
                $assert($count($xpath, './/*[' . td_readability_class('td-cover-placeholder') . ' and @aria-hidden="true"]', $header) === 1, $memberContext . 'missing portrait uses an honest decorative icon, not an invented photograph');
            }

            $facts = $one($xpath, './/dl[' . td_readability_class('td-facts') . ']', $article);
            $prose = $one($xpath, './/*[' . td_readability_class('td-article-content') . ']', $article);
            $assert($facts !== null && $prose !== null, $memberContext . 'known facts and full content both remain');
            $articleMarkup = (string) $dom->saveHTML($article);
            $assert(strpos($articleMarkup, 'class="td-facts"') < strpos($articleMarkup, 'class="td-article-content"'), $memberContext . 'facts precede the article');
            $assert($count($xpath, 'ancestor::details | ancestor::*[@hidden or @aria-hidden="true"]', $prose) === 0, $memberContext . 'full article is never inside a disclosure or hidden region');
            $assert($count($xpath, './p', $prose) === 8, $memberContext . 'all eight paragraphs are rendered in full');
            for ($paragraph = 1; $paragraph <= 8; $paragraph++) {
                $marker = 'FULL_PROFILE_' . ($index + 1) . '_PARAGRAPH_' . $paragraph;
                $assert(substr_count($prose->textContent, $marker) === 1, $memberContext . 'paragraph marker retained exactly once: ' . $paragraph);
            }
            $assert(($galleryData['galleries'][(string) $rank]['images'] ?? null) === $member['gallery'], $memberContext . 'full original photo metadata reaches the lightbox');
            $assert($count($xpath, './/*[' . td_readability_class('td-verified') . ']', $article) === ($member['verified'] ? 1 : 0), $memberContext . 'verification is never invented');
            if (!$member['has_rating']) {
                $rating = $one($xpath, './/*[' . td_readability_class('td-rating') . ']', $article);
                $assert($rating !== null && str_contains($rating->textContent, $labels['noReviews']) && !str_contains($rating->textContent, '0.0/5'), $memberContext . 'unreviewed profiles remain explicitly unreviewed');
            }
        }
    }
}

// Many photos: compact preview is not the complete lightbox inventory.
$photoRow = td_readability_rows(1)[0];
$photos = [];
for ($index = 0; $index < 20; $index++) {
    $photos[] = ['url' => 'https://example.org/gallery/' . $index . '.jpg', 'caption' => 'Caption ' . $index, 'alt' => 'Photo alt ' . $index];
}
$photoRow['image_url'] = $photos[0]['url'];
$photoRow['gallery_json'] = json_encode($photos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
foreach (['vi', 'en'] as $locale) {
    [$dom, $xpath] = td_readability_dom(td_readability_render([$photoRow], $locale));
    $article = $one($xpath, '//article[@data-td-member="1"]');
    $disclosure = $one($xpath, './/details[' . td_readability_class('td-photo-disclosure') . ']', $article);
    $assert($disclosure !== null && !$disclosure->hasAttribute('open'), $locale . ': photo previews use a native collapsed disclosure');
    $assert($count($xpath, './summary[' . td_readability_class('td-photo-toggle') . ']', $disclosure) === 1, $locale . ': photo disclosure is keyboard operable without JavaScript');
    $links = $xpath->query('.//*[' . td_readability_class('td-gallery') . ']/a[@data-td-photo]', $disclosure);
    $assert($links->length === 4, $locale . ': at most four supplementary photo previews');
    $indexes = [];
    foreach ($links as $link) $indexes[] = (int) $link->getAttribute('data-td-photo');
    $assert($indexes === [1, 2, 3, 4], $locale . ': preview indexes match the full gallery and never duplicate the portrait');
    $more = $one($xpath, './/*[' . td_readability_class('td-gallery-more') . ']', $disclosure);
    $assert($more !== null && preg_match('/\+\s*15\b/', $more->textContent) === 1, $locale . ': overflow count excludes cover and the four visible previews');
    $gallery = td_readability_gallery($xpath)['galleries'][1]['images'];
    $assert($gallery === $photos, $locale . ': all twenty photos, captions and alt texts remain in the lightbox');
}

// Sparse legacy data must not create a photo, rating, fee, or verification claim.
$sparse = ['id' => 92001, 'rank_order' => 1, 'member_type' => 'doctor', 'name' => 'Bác sĩ chưa có dữ liệu phụ', 'slug' => 'bac-si-du-lieu-phu', 'content' => '<p>SPARSE_REAL_CONTENT</p>'];
[$dom, $xpath] = td_readability_dom(td_readability_render([$sparse]));
$article = $one($xpath, '//article[@data-td-member="1"]');
$assert($count($xpath, './/img | .//*[' . td_readability_class('td-verified') . '] | .//*[' . td_readability_class('td-fees') . ']', $article) === 0, 'Sparse profiles have no fabricated portrait, verification or fee table');
$assert($count($xpath, './/details[' . td_readability_class('td-photo-disclosure') . ']', $article) === 0, 'Sparse profiles have no unusable gallery disclosure');
$assert(str_contains($article->textContent, 'SPARSE_REAL_CONTENT') && !str_contains($article->textContent, 'VNĐ') && !str_contains($article->textContent, '0.0/5'), 'Sparse profiles preserve actual text without guessed prices or scores');

// Explicit editorial images are accepted, but are never selected from a member implicitly.
[$dom, $xpath] = td_readability_dom(td_readability_render(td_readability_rows(1), 'vi', 'doctor', 'https://example.org/editorial/featured.jpg'));
$featured = $one($xpath, '//*[' . td_readability_class('td-hero-image') . ']//img');
$assert($featured instanceof DOMElement && $featured->getAttribute('src') === 'https://example.org/editorial/featured.jpg', 'An explicit featured editorial image remains supported');

// Non-consecutive and colliding ranks retain stored order while producing valid targets.
$ranks = td_readability_rows(3);
foreach ([4, 4, 12] as $index => $rank) $ranks[$index]['rank_order'] = $rank;
$rankMembers = medical_toplist_detail_members($ranks, 'vi');
$assert(array_column($rankMembers, 'rank') === [4, 5, 12], 'Rank gaps remain, collisions resolve without reordering profiles');
[$dom, $xpath] = td_readability_dom(td_readability_render($ranks));
$actualRanks = [];
foreach ($xpath->query('//article[@data-td-member]') as $article) $actualRanks[] = (int) $article->getAttribute('data-td-member');
$assert($actualRanks === [4, 5, 12], 'Resolved original ranking order is the rendered order');
$assert(array_keys(td_readability_gallery($xpath)['galleries']) === [4, 5, 12], 'Lightbox galleries use the same resolved rank keys');

// Mixed lists must not turn facility profile links into doctor routes.
$facility = ['id' => 93001, 'rank_order' => 2, 'member_type' => 'facility', 'name' => 'Cơ sở y tế Kiểm thử', 'slug' => 'co-so-y-te-kiem-thu', 'category' => 'Nha khoa', 'city' => 'Huế', 'content' => '<p>FULL_FACILITY_CONTENT</p>', 'verified' => 1];
foreach (['vi', 'en'] as $locale) {
    [$dom, $xpath] = td_readability_dom(td_readability_render([td_readability_rows(1)[0], $facility], $locale, 'mixed'));
    $prefix = $locale === 'en' ? '/en' : '';
    $assert($count($xpath, '//article[@data-td-member="1"]//h2/a[@href="' . $prefix . '/bac-si/bac-si-kiem-thu-ho-so-1"]') === 1, $locale . ': mixed list retains doctor profile route');
    $assert($count($xpath, '//article[@data-td-member="2"]//h2/a[@href="' . $prefix . '/co-so-y-te/co-so-y-te-kiem-thu"]') === 1, $locale . ': mixed list retains facility profile route');
    $assert($count($xpath, '//article[@data-td-member="2"]//a[' . td_readability_class('td-verified') . ' and @href="' . $prefix . '/ho-so-da-xac-thuc"]') === 1, $locale . ': facility verification links to its localized explanation');
    $assert(str_contains($dom->textContent, 'FULL_FACILITY_CONTENT'), $locale . ': mixed facility content is not omitted');
}

// Long, untrusted identities and media descriptions remain data, not executable markup.
$unsafe = td_readability_rows(1)[0];
$longName = str_repeat('Bác sĩ tên dài & dữ liệu <không phải thẻ> ', 8);
$unsafe['name'] = $longName;
$unsafe['website_url'] = 'https://example.org/' . str_repeat('long-path-', 35) . '?x=1&y=2';
$unsafe['content'] = '<h2 id="td-main">SAFE_LONG_CONTENT</h2><p onclick="bad()">SAFE_PARAGRAPH</p><script>UNSAFE_SCRIPT</script><iframe src="https://evil.example"></iframe>';
$unsafe['gallery_json'] = json_encode([['url' => 'https://example.org/portraits/1.jpg', 'caption' => '</script><script>UNSAFE_GALLERY</script>', 'alt' => 'Unsafe <alt> & safe text']], JSON_UNESCAPED_SLASHES);
$unsafeHtml = td_readability_render([$unsafe]);
[$dom, $xpath] = td_readability_dom($unsafeHtml);
$assert(!str_contains($unsafeHtml, '<script>UNSAFE_GALLERY') && !str_contains($unsafeHtml, '<script>UNSAFE_SCRIPT') && !str_contains($unsafeHtml, 'onclick=') && !str_contains($unsafeHtml, '<iframe'), 'Unsafe article and gallery strings cannot inject script or event handlers');
$assert($count($xpath, '//*[@id="td-main"]') === 1, 'Article HTML cannot duplicate structural IDs');
$name = $one($xpath, '//h2[@id="tdMember1"]/a');
$expectedName = medical_toplist_detail_members([$unsafe], 'vi')[0]['name'];
$assert($name !== null && $name->textContent === $expectedName, 'Long provider names remain complete plain text');
$assert(str_contains($dom->textContent, 'SAFE_LONG_CONTENT') && str_contains($dom->textContent, 'SAFE_PARAGRAPH'), 'Safe content survives sanitization without truncation');
$assert(str_contains($unsafeHtml, '\\u003C'), 'Embedded gallery JSON uses HTML-safe escaping');
$assert(td_readability_gallery($xpath)['galleries'][1]['images'][0]['caption'] === medical_doctor_profile_text('</script><script>UNSAFE_GALLERY</script>'), 'Escaping does not corrupt gallery metadata');

$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/pages/toplist-doctors.css');
$compactCss = preg_replace('/\s+/', '', $css);
$assert(str_contains($compactCss, 'max-width:72ch') && str_contains($compactCss, 'font-size:16px') && str_contains($compactCss, 'line-height:1.75'), 'Prose has a readable line length, base size and rhythm');
$assert(!str_contains($compactCss, 'max-width:none!important'), 'Global full-width paragraph override is retired');
$assert(preg_match('/\.td-cover\{[^}]*width:148px[^}]*height:176px/', $compactCss) === 1, 'Desktop cover is a compact portrait rather than a full-width banner');
$assert(preg_match('/\.td-comparison-desktop\{display:block(?:;|\})/', $compactCss) === 1 && str_contains($compactCss, '.td-comparison-mobile{display:none'), 'Desktop shows its semantic comparison table only');
$assert(preg_match('/@media[^{}]*max-width:700px[^{}]*\{.*\.td-comparison-desktop\{display:none\}.*\.td-comparison-mobile\{display:/s', $compactCss) === 1, 'Small screens use a distinct labeled comparison list, not a squeezed desktop table');
$assert(str_contains($compactCss, 'overflow-wrap:anywhere') && str_contains($compactCss, 'minmax(0,1fr)'), 'Long names and URLs can wrap without expanding the page');
$assert(!preg_match('/\.td-article-content[^{}]*\{[^}]*(?:line-clamp|display:none|max-height:\d|overflow:hidden)/', $compactCss), 'Article prose is never visually clipped or hidden');
$assert(str_contains($compactCss, 'prefers-reduced-motion:reduce') && str_contains($compactCss, ':focus-visible'), 'Keyboard focus and reduced-motion support remain present');

echo "Toplist doctor readability: {$checks} checks passed. No database or network used.\n";
