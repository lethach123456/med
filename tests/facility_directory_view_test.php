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
$assert(substr_count($html, 'fd-service-extra" hidden') === 2 && str_contains($html, 'aria-expanded="false"'), 'Extra services are a proper disclosure');
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
    $assert($xpath->query('//details')->length === 1 && $xpath->query('//summary')->length === 1, $prefix . 'one native filter disclosure');
    $toolsPath = '//*[@id="fdResultsHeading"]/*[' . $hasClass('fd-result-tools') . ']';
    $assert($xpath->query($toolsPath . '/*[' . $hasClass('fd-sort') . ']')->length === 1
        && $xpath->query($toolsPath . '/details[@id="facilityFilterDisclosure"]')->length === 1, $prefix . 'filters and sort share the results toolbar');
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
echo "Facility directory view: {$checks} checks passed. No DB records modified.\n";
