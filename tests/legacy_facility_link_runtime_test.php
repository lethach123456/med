<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/medical_toplist_detail_view.php';
require_once dirname(__DIR__) . '/medical_handbook.php';
require_once dirname(__DIR__) . '/medical_handbook_media.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$legacy = '/co-so-y-te-chi-tiet.php?slug=nha-khoa-mau';
foreach (['vi' => '/co-so-y-te/nha-khoa-mau', 'en' => '/en/co-so-y-te/nha-khoa-mau'] as $locale => $expected) {
    $cached = ['id' => 3, 'slug' => 'nha-khoa-mau', 'language_code' => $locale, 'url' => $legacy, 'name' => 'Cơ sở mẫu'];
    $assert(medical_search_cache_public_item('facilities', $cached)['url'] === $expected, $locale . ' autocomplete regenerates cached legacy URL');
    $assert(medical_search_cache_directory_item($cached)['url'] === $expected, $locale . ' directory regenerates cached legacy URL without rebuilding');
    $cached['url'] = 'https://untrusted.example/redirect';
    $assert(medical_search_cache_directory_item($cached)['url'] === $expected, $locale . ' cached arbitrary URL never overrides known facility identity');
}
$article = '<h2>Giới thiệu</h2><p><a href="' . $legacy . '#gioi-thieu" class="ref">Phòng khám</a></p>';
$prepared = handbook_prepare_content_with_toc($article, [], 'vi');
$assert(str_contains($prepared['html'], 'href="/co-so-y-te/nha-khoa-mau#gioi-thieu"'), 'Stored handbook article links normalized while building TOC');
$assert(str_contains($prepared['html'], 'class="ref"') && str_contains($prepared['toc_html'], 'Giới thiệu'), 'Article formatting and TOC retained');
$assert(handbook_ai_media_link($legacy) === 'https://medreview.vn/co-so-y-te/nha-khoa-mau', 'Article receiver upgrades relative legacy link before HTTP-only sanitization');
$assert(handbook_ai_media_link('https://other.example' . $legacy) === 'https://other.example' . $legacy, 'Article receiver leaves external source URL unchanged');
$doctor = medical_doctor_sanitize_html($article);
$assert(str_contains($doctor, 'href="https://medreview.vn/co-so-y-te/nha-khoa-mau#gioi-thieu"'), 'Doctor HTML keeps relative legacy facility anchor as safe canonical absolute URL');
$assert(!str_contains($doctor, 'nofollow') && str_contains($doctor, 'rel="noopener noreferrer"') && !str_contains($doctor, 'class="ref"'), 'Doctor sanitizer preserves followable safe link and existing allowlist');
$toplist = medical_toplist_detail_html($article);
$assert(str_contains($toplist, 'href="https://medreview.vn/co-so-y-te/nha-khoa-mau#gioi-thieu"'), 'Doctor/mixed Toplist HTML also keeps normalized internal link');

$root = dirname(__DIR__);
$facility = (string) file_get_contents($root . '/co-so-y-te-chi-tiet.php');
$assert(str_contains($facility, 'medical_normalize_facility_links_html($paragraph, site_canonical_origin())'), 'Facility stored introduction normalized at output');
$assert(str_contains($facility, 'medical_normalize_facility_links_html(facility_detail_compact_price_html('), 'Facility price HTML normalized without changing table layout');
$assert(str_contains($facility, "medical_redirect_legacy_path('/co-so-y-te-chi-tiet.php'"), 'Legacy incoming URL still has its 301 redirect');
$rules = (string) file_get_contents($root . '/.htaccess');
$assert(str_contains($rules, 'RewriteRule ^co-so-y-te/([^/]+)/?$ co-so-y-te-chi-tiet.php?slug=$1 [QSA,L]'), 'Canonical route retains necessary internal controller rewrite');
$admin = (string) file_get_contents($root . '/admin/api/medical/facilities_list.php');
$assert(str_contains($admin, '$languageColumn') && str_contains($admin, "'vi' AS language_code"), 'Admin listing returns locale with read-only fallback for legacy schema');
echo "Legacy facility runtime links: {$checks} checks passed; no DB, cache writes or network.\n";
