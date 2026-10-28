<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_facility_directory_view.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$item = ['name' => 'Clinic <Test>', 'slug' => 'clinic-test', 'url' => '/co-so-y-te/clinic-test',
    'image' => 'https://example.org/photo.jpg', 'category' => 'Dental', 'city' => 'Hà Nội',
    'subtitle' => 'Short public profile', 'address' => 'A & B street', 'rating' => 4.3,
    'reviews_count' => 1200, 'verified' => true, 'services' => ['Service A', 'Service B', 'Service C'], 'price' => '250.000 VND'];
$html = facility_directory_card($item, 'vi');
$assert(str_contains($html, 'Clinic &lt;Test&gt;') && str_contains($html, 'A &amp; B street'), 'Public content escaped');
$assert(str_contains($html, '4.3<span>/5</span>') && !str_contains($html, '★★★★★'), 'Rating not represented as five gold stars');
$assert(str_contains($html, '1.200 đánh giá'), 'Vietnamese count format');
$assert(str_contains($html, 'loading="lazy"') && str_contains($html, 'width="360" height="300"'), 'Images lazy with reserved dimensions');
$assert(substr_count($html, 'fd-service-extra') === 0 && str_contains($html, 'fd-service-more-mobile-only')
    && str_contains($html, 'data-mobile-extra-count="2"'), 'Three short services retain a mobile-only +2 disclosure without changing desktop visibility');
$two = facility_directory_card(array_replace($item, ['services' => ['Khám', 'Tư vấn']]));
$assert(str_contains($two, 'fd-service-more-mobile-only') && str_contains($two, 'data-mobile-extra-count="1"'), 'Two short services provide a mobile-only +1 disclosure');
$single = facility_directory_card(array_replace($item, ['services' => ['Khám']]));
$assert(!str_contains($single, 'data-service-tags-more'), 'One short service has no redundant disclosure');
$many = facility_directory_card(array_replace($item, ['services' => ['Niềng răng', 'Bọc sứ', 'Implant', 'Khám tổng quát', 'Dịch vụ thứ năm']]));
$assert(substr_count($many, 'fd-service-extra" hidden') === 2 && str_contains($many, 'data-extra-count="2"')
    && str_contains($many, 'data-mobile-extra-count="4"') && str_contains($many, 'fd-service-count-desktop')
    && str_contains($many, 'fd-service-count-mobile'), 'Five services preserve desktop +2 and mobile +4 counts');
$long = facility_directory_card(array_replace($item, ['services' => ['Niềng răng thẩm mỹ với mắc cài cá nhân hóa & khay trong suốt']]));
$assert(str_contains($long, 'data-extra-count="0"') && str_contains($long, 'Xem đủ') && str_contains($long, '&amp;'), 'Long single label retains full escaped text with touch disclosure');
$assert(str_contains($html, 'Giá tham khảo: 250.000 VND'), 'Actual price retained');
$en = facility_directory_card($item, 'en');
$assert(str_contains($en, '1,200 reviews') && str_contains($en, 'View profile'), 'English labels/counts');
$empty = facility_directory_card(['slug' => 'empty', 'name' => 'Empty profile', 'rating' => 0, 'reviews_count' => 0]);
$assert(str_contains($empty, 'Chưa có đánh giá') && !str_contains($empty, 'ph-star'), 'Missing ratings not invented');
$assert(str_contains($empty, 'fd-media-fallback') && !str_contains($empty, '<img') && !str_contains($empty, 'src=""'), 'Missing image fallback');
$assert(!str_contains($empty, 'Giá tham khảo'), 'Missing price omitted');
$assert(str_contains(facility_directory_card(['slug' => 'english', 'name' => 'English'], 'en'), '/en/co-so-y-te/english'), 'Localized fallback URL');
$unsafe = facility_directory_card(array_replace($item, ['url' => 'javascript:alert(1)', 'image' => '//evil.example/image.jpg']));
$assert(!str_contains($unsafe, 'javascript:') && !str_contains($unsafe, 'evil.example'), 'Unsafe URL schemes rejected');
foreach (['/\\evil.example', "https://example.org/\nmalicious", 'data:image/svg+xml,test'] as $url) {
    $assert(facility_directory_safe_url($url) === '', 'Invalid URL rejected');
}
$assert(facility_directory_pagination('/co-so-y-te', [], 1, 1, 'vi') === '', 'Single page has no redundant pagination');
$pager = facility_directory_pagination('/co-so-y-te', ['city' => 'Đà Nẵng', 'sort' => 'recommended'], 2, 36, 'vi');
$assert(str_contains($pager, 'aria-current="page"') && str_contains($pager, 'data-page="36"'), 'Current and boundary pages rendered');
$assert(str_contains($pager, 'city=') && !str_contains($pager, 'sort=recommended'), 'Pagination preserves meaningful filters');
$assert(facility_directory_page_url('/co-so-y-te', [], 2) === '/co-so-y-te?page=2', 'Unfiltered facility pages have distinct canonicalizable URLs');
$assert(str_contains(facility_directory_pagination('/en/co-so-y-te', [], 1, 2, 'en'), 'Next page'), 'English pagination');

// Render the public template with isolated fixtures, never the DB-backed page controller.
function site_localized_path(string $path, string $locale): string
{
    return ($locale === 'en' ? '/en' : '') . $path;
}
function medical_public_facility_path(): string
{
    return '/co-so-y-te';
}
$fixtureCity = 'Hà Nội <Central> & "Care"';
$renderTemplate = static function (string $locale) use ($item, $fixtureCity): string {
    $isEnglish = $locale === 'en';
    $filters = ['q' => 'Clinic <Test> & "Care"', 'city' => $fixtureCity, 'category' => 'Dental',
        'service' => 'Service A', 'min_rating' => '4.5', 'sort' => 'rating'];
    $cities = [$fixtureCity, 'Đà Nẵng', 'Huế', 'Cần Thơ'];
    $categories = ['Dental', 'Hospital'];
    $services = ['Service A', 'Service B'];
    $cityCounts = [$fixtureCity => 1234, 'Đà Nẵng' => 56];
    $directoryStats = ['facilities' => 2345, 'reviews' => 9876];
    $initial = ['items' => [$item], 'total' => 1234, 'page' => 1, 'total_pages' => 2];
    ob_start();
    try {
        require dirname(__DIR__) . '/Tem/facility-directory.php';
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
$hasClass = static fn(string $class): string => "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
foreach (['vi', 'en'] as $locale) {
    $template = $renderTemplate($locale);
    $document = new DOMDocument();
    $previousErrors = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<?xml encoding="UTF-8">' . $template, LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }
    $xpath = new DOMXPath($document);
    $prefix = strtoupper($locale) . ' template: ';
    $assert($xpath->query('//section[' . $hasClass('fd-results') . '][@aria-labelledby="fdResultsTitle"]')->length === 1
        && $xpath->query('//*[@id="fdResultsTitle"][self::h2]')->length === 1, $prefix . 'results landmark is named by its heading, not the whole toolbar');
    $assert($xpath->query('//*[@id="fdResultsHeading"][@tabindex="-1"]')->length === 1, $prefix . 'existing focus and scroll target is retained');
    $cardPath = '//article[' . $hasClass('fd-card') . ']/*[' . $hasClass('fd-card-content') . ']';
    $mainPath = $cardPath . '/*[' . $hasClass('fd-card-main') . ']';
    $assert($xpath->query($mainPath)->length === 1 && $xpath->query($mainPath . '/*')->length === 6
        && $xpath->query($mainPath . '/*[1][' . $hasClass('fd-card-category') . ']')->length === 1
        && $xpath->query($mainPath . '/*[2][self::h2]')->length === 1
        && $xpath->query($mainPath . '/*[3][' . $hasClass('fd-address') . ']')->length === 1
        && $xpath->query($mainPath . '/*[4][' . $hasClass('fd-summary') . ']')->length === 1
        && $xpath->query($mainPath . '/*[5][' . $hasClass('fd-services') . ']')->length === 1
        && $xpath->query($mainPath . '/*[6][' . $hasClass('fd-card-footer') . ']')->length === 1
        && $xpath->query($mainPath . '/h2/a[not(' . $hasClass('fd-verified') . ')]')->length === 1
        && $xpath->query($mainPath . '/h2/a[' . $hasClass('fd-verified') . ']')->length === 1, $prefix . 'mobile identity groups category, title and address beside the image with a separate verification link');
    $assert($xpath->query($mainPath . '/*[' . $hasClass('fd-address') . ']')->length === 1
        && $xpath->query($cardPath . '/*[' . $hasClass('fd-address') . ']')->length === 0
        && $xpath->query($mainPath . '/*[' . $hasClass('fd-summary') . ']')->length === 1
        && $xpath->query($mainPath . '/*[' . $hasClass('fd-services') . ']')->length === 1
        && $xpath->query($mainPath . '/*[' . $hasClass('fd-card-footer') . ']')->length === 1
        && $xpath->query($cardPath . '/*[' . $hasClass('fd-card-footer') . ']')->length === 0, $prefix . 'address, services and rating share the mobile identity column without a full-width footer');
    $assert($xpath->query($mainPath . '/*[' . $hasClass('fd-card-footer') . ']/*[' . $hasClass('fd-rating') . ']/*[' . $hasClass('fd-review-count') . ']')->length === 1
        && $xpath->query($mainPath . '/h2/a[not(' . $hasClass('fd-verified') . ')]')->item(0)->getAttribute('href')
            === $xpath->query('//article[' . $hasClass('fd-card') . ']/a[' . $hasClass('fd-media') . ']')->item(0)->getAttribute('href'),
        $prefix . 'rating count is retained and photo/name still open the same facility profile');
    $assert($xpath->query('//*[' . $hasClass('fd-description-desktop') . ']')->length === 1
        && $xpath->query('//*[' . $hasClass('fd-description-mobile') . ']')->length === 1, $prefix . 'responsive hero copy has both localized variants');
    $assert($xpath->query('//details')->length === 1 && $xpath->query('//summary')->length === 1, $prefix . 'one native filter disclosure');
    $toolsPath = '//*[@id="fdResultsHeading"]/*[' . $hasClass('fd-result-tools') . ']';
    $assert($xpath->query($toolsPath . '/details[@id="facilityFilterDisclosure"]')->length === 1
        && $xpath->query('//*[@id="facilityFilterOptions"]/*[' . $hasClass('fd-sort-slot') . ']/*[' . $hasClass('fd-sort') . ']/select[@id="filterSort"]')->length === 1,
        $prefix . 'one shared disclosure contains sorting before enhancement');
    $assert($xpath->query('//*[@id="facilityFilterOptions"]/*[' . $hasClass('fd-sort-slot') . ']/following-sibling::*[1][' . $hasClass('fd-filter-field') . ']')->length === 1,
        $prefix . 'sort field precedes location and other filters');
    $assert($xpath->query('//*[@id="fdResultsHeading"]/*[' . $hasClass('fd-results-copy') . ']/*[@id="fdResultsTitle"]')->length === 1,
        $prefix . 'results title and tools have separate same-row layout hooks');
    $assert($xpath->query('//*[@id="facilityFilterDisclosure"]/*[1][self::summary][@id="facilityFilterToggle"][@aria-controls="facilityFilterOptions"]')->length === 1,
        $prefix . 'summary is the first disclosure child and controls the existing panel');
    $assert($xpath->query('//*[@id="facilityFilterDisclosure"][@open]')->length === 0
        && $xpath->query('//*[@id="facilityFilterDisclosure"]/*[@id="facilityFilterOptions"][@aria-labelledby="fdFilterHeading"]')->length === 1,
        $prefix . 'panel starts closed with its accessible heading');
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $node) $ids[] = $node->getAttribute('id');
    $assert(count($ids) === count(array_unique($ids)), $prefix . 'all rendered IDs are unique');
    foreach (['filterCity' => 'city', 'filterCategory' => 'category', 'filterService' => 'service', 'filterRating' => 'min_rating', 'filterSort' => 'sort'] as $id => $name) {
        $selects = $xpath->query('//select[@id="' . $id . '"][@name="' . $name . '"][@form="facilityDirectoryFilter"][@data-facility-filter-control]');
        $assert($selects->length === 1 && !$selects->item(0)->hasAttribute('disabled'), $prefix . $name . ' remains associated with the search form');
        $assert($xpath->query('//label[@for="' . $id . '"]')->length === 1, $prefix . $name . ' keeps its visible label');
    }
    $sidebarPath = '//aside[' . $hasClass('fd-sidebar') . ']';
    $assert($xpath->query($sidebarPath . '//select | ' . $sidebarPath . '//input | ' . $sidebarPath . '//*[@data-facility-filter-control]')->length === 0,
        $prefix . 'discovery sidebar does not duplicate filter controls');
    $assert($xpath->query($sidebarPath . '//button[@data-open-filters]')->length === 1, $prefix . 'discovery sidebar can open the filters');
    $cityLinks = $xpath->query($sidebarPath . '//a[@data-city]');
    $assert($cityLinks->length === 3 && $cityLinks->item(0)->getAttribute('data-city') === $fixtureCity
        && $cityLinks->item(0)->getAttribute('aria-current') === 'true', $prefix . 'three discovery cities with current-city state');
    $assert(str_contains($template, 'data-city="Hà Nội &lt;Central&gt; &amp; &quot;Care&quot;"')
        && str_contains($template, '<strong>Hà Nội &lt;Central&gt; &amp; &quot;Care&quot;</strong>')
        && !str_contains($template, '<Central>'), $prefix . 'city values and visible text are escaped');
    $cityQuery = [];
    parse_str((string) parse_url($cityLinks->item(0)->getAttribute('href'), PHP_URL_QUERY), $cityQuery);
    $assert(($cityQuery['city'] ?? '') === $fixtureCity && ($cityQuery['q'] ?? '') === 'Clinic <Test> & "Care"'
        && ($cityQuery['sort'] ?? '') === 'rating' && !isset($cityQuery['page']), $prefix . 'city discovery preserves search and sorting and resets pagination');
    $enLocale = $locale === 'en';
    $assert($xpath->query('//*[' . $hasClass('fd-filter-label-full') . ']')->item(0)->textContent === ($enLocale ? 'Filter & sort' : 'Lọc & sắp xếp')
        && $xpath->query('//*[' . $hasClass('fd-filter-label-short') . ']')->item(0)->textContent === ($enLocale ? 'Filter & sort' : 'Lọc & xếp'),
        $prefix . 'combined control has localized full and small-screen labels');
    $assert(str_contains($cityLinks->item(0)->textContent, $enLocale ? '1,234 facilities' : '1.234 cơ sở')
        && str_contains($cityLinks->item(2)->textContent, $enLocale ? '0 facilities' : '0 cơ sở'), $prefix . 'city counts are localized with a zero fallback');
    $assert(str_contains($xpath->query('//*[@id="facilityFilterToggle"]')->item(0)->textContent, $enLocale ? 'Filters' : 'Bộ lọc')
        && $xpath->query('//label[@for="filterSort"]')->item(0)->textContent === ($enLocale ? 'Sort by' : 'Sắp xếp')
        && str_contains($xpath->query('//*[@id="fdFilterHeading"]')->item(0)->textContent, $enLocale ? 'Refine your search' : 'Lọc theo nhu cầu')
        && $xpath->query('//*[@id="facilityFilterClose"]')->item(0)->getAttribute('aria-label') === ($enLocale ? 'Close filters' : 'Đóng bộ lọc'),
        $prefix . 'filter toolbar, heading and close control are localized');
    $assert(str_contains($xpath->query($sidebarPath)->item(0)->textContent, $enLocale ? 'Choose another area' : 'Chọn khu vực khác')
        && $xpath->query('//*[@id="facilityDirectoryFilter"]')->item(0)->getAttribute('action') === ($enLocale ? '/en/co-so-y-te' : '/co-so-y-te'),
        $prefix . 'discovery label and search action are localized');
}
$pageSource = file_get_contents(dirname(__DIR__) . '/co-so-y-te.php');
$mobileAsset = '/assets/css/pages/facility-directory-mobile.css?v=';
$assert(str_contains($pageSource, $mobileAsset)
    && strpos($pageSource, $mobileAsset) > strpos($pageSource, '/assets/css/core/directory-service-labels.css?v='), 'Cache-busted mobile refinement follows shared service chip styling');
$jsSource = file_get_contents(dirname(__DIR__) . '/assets/js/facility-directory.js');
$assert(str_contains($jsSource, '<div class="fd-card-main">')
    && preg_match('~<div class="fd-card-main">\s*<div class="fd-card-category">.*?<h2>.*?</h2>\s*\$\{item\.address.*?\$\{item\.subtitle.*?class="fd-services".*?<div class="fd-card-footer">.*?\n\s*</div>\s*\$\{item\.price~s', $jsSource) === 1, 'AJAX cards keep the SSR identity, services and footer inside the same layout-transparent group');
$assert(str_contains($jsSource, 'sortSlot.append(sortGroup)') && str_contains($jsSource, 'resultTools.insertBefore(sortGroup, disclosure)')
    && str_contains($jsSource, "compact.addEventListener('change', arrangeControls)") && !str_contains($jsSource, 'cloneNode('), 'Responsive sorting moves the original form control, never a duplicate');
$assert(str_contains($jsSource, 'if (hadFocus && !disclosure.open) setPanel(true)')
    && str_contains($jsSource, 'sortControl.focus({preventScroll: true})'), 'Focused sorting remains accessible when resizing into the closed mobile panel');
$mobileCss = file_get_contents(dirname(__DIR__) . '/assets/css/pages/facility-directory-mobile.css');
$assert(str_contains($mobileCss, 'body.fd-page main.fd-directory .fd-card-main{display:contents}'), 'Desktop card identity stays layout-transparent');
foreach (['@media(max-width:800px)', '@media(max-width:600px)', 'grid-template-columns:minmax(0,1fr) auto',
    'min-height:44px', 'font-size:16px', 'grid-template-columns:104px minmax(0,1fr)',
    'grid-template-columns:90px minmax(0,1fr)', 'min-height:132px', 'min-height:128px',
    '.fd-sort-slot:empty{display:none}', '.fd-sort-slot .fd-sort label{display:block'] as $rule) {
    $assert(str_contains($mobileCss, $rule), 'Mobile refinement retains ' . $rule);
}
$assert(preg_match('~\.fd-media\{[^}]*height:auto;[^}]*min-height:132px;[^}]*align-self:stretch~', $mobileCss) === 1,
    'Mobile photo grows with the adjacent identity instead of staying a fixed square');
$assert(preg_match('~\.fd-card-main\{[^}]*min-height:132px~', $mobileCss) === 1,
    'Photo and category/name/address group share a readable minimum height');
$assert(preg_match('~\.fd-address\{[^}]*font-size:12px;~', $mobileCss) === 1
    && !preg_match('~\.fd-address(?:>span)?\{[^}]*(?:line-clamp|text-overflow)~', $mobileCss),
    'Compact address remains fully wrapped and is not clipped');
$assert(str_contains($mobileCss, '.fd-summary{display:none}')
    && str_contains($mobileCss, 'fd-service-count-mobile') && str_contains($mobileCss, 'fd-service-count-desktop'),
    'Mobile hides the summary and switches to the accurate mobile service count');
$assert(str_contains($mobileCss, '.fd-services:not(.is-expanded)') && str_contains($mobileCss, 'white-space:nowrap')
    && str_contains($mobileCss, '.fd-services.is-expanded'), 'Mobile services collapse to one line and can still expand');
$assert(preg_match('~\.fd-card-footer\{[^}]*order:1~', $mobileCss) === 1
    && preg_match('~\.fd-services\{[^}]*order:2~', $mobileCss) === 1,
    'Mobile rating is presented before the compact service row without changing desktop DOM order');
$assert(str_contains($mobileCss, ':is(.fd-rating-source,.fd-profile-link){display:none}')
    || (preg_match('~\.fd-profile-link\{[^}]*display:none~', $mobileCss) === 1
    && preg_match('~\.fd-rating-source\{[^}]*display:none~', $mobileCss) === 1),
    'Mobile hides the redundant profile CTA and rating source label');
echo "Facility directory view: {$checks} checks passed. No DB records modified.\n";
