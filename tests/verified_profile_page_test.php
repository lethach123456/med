<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/db.php';
$root = dirname(__DIR__);
$page = (string) file_get_contents($root . '/ho-so-da-xac-thuc.php');
$css = (string) file_get_contents($root . '/assets/css/pages/verified-profile.css');
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
foreach (['ho-so-da-xac-thuc.php', 'ho-so-da-xac-thuc'] as $route) {
    // The clean page is bilingual; its PHP source remains a supported entry.
    if ($route === 'ho-so-da-xac-thuc') {
        $assert(site_localized_path('/' . $route, 'en') === '/en/ho-so-da-xac-thuc', 'English route');
    }
}
$assert(site_bilingual_public_page_paths()['verification'] === '/ho-so-da-xac-thuc', 'Language switch route registered');
foreach (['y-nghia', 'tieu-chi', 'gioi-han', 'cau-hoi'] as $id) {
    $assert(str_contains($page, 'id="' . $id . '"') && str_contains($page, 'href="#' . $id . '"'), 'Section link: ' . $id);
}
$assert(str_contains($page, 'Nhãn do quản trị đánh dấu') && str_contains($page, 'chưa có bảng bằng chứng'), 'Current label scope is transparent');
$assert(str_contains($page, 'Không chứng nhận chất lượng hoặc kết quả điều trị'), 'No unsupported clinical guarantee');
$assert(str_contains($page, '<details>') && str_contains($page, '<summary>'), 'Native keyboard accessible FAQ');
$assert(str_contains($page, 'hreflang="en"') && str_contains($page, 'rel="canonical"'), 'Localized SEO');
$assert(str_contains($css, 'prefers-reduced-motion:reduce'), 'Reduced motion');
$assert(str_contains($css, 'focus-visible'), 'Visible keyboard focus');
$assert(str_contains($css, 'grid-template-columns:1fr 1fr') && str_contains($css, 'min-height:44px'), 'Mobile navigation and touch targets');
foreach (['/ho-so-da-xac-thuc', '/en/ho-so-da-xac-thuc'] as $route) {
    $assert(str_contains((string) file_get_contents($root . '/sitemap.php'), "'" . $route . "'"), 'Sitemap: ' . $route);
}
$assert(str_contains((string) file_get_contents($root . '/Tem/footer.php'), "site_localized_path('/ho-so-da-xac-thuc', \$locale)"), 'Footer entry');
$assert(str_contains((string) file_get_contents($root . '/co-so-y-te-chi-tiet.php'), "site_localized_path('/ho-so-da-xac-thuc', \$facilityLanguage)"), 'Facility badge entry');
echo "Verified profile page: {$checks} checks passed. No database or network calls.\n";
