<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$sitemap = (string) file_get_contents($root . '/sitemap.php');
foreach (["site_localized_path('/bac-si', 'vi')", "site_localized_path('/bac-si', 'en')", "site_localized_path('/review', 'vi')", "site_localized_path('/ve-chung-toi', 'en')", "site_localized_path('/danh-muc-y-te', 'vi')"] as $canonicalPath) {
    $assert(str_contains($sitemap, $canonicalPath), 'Sitemap uses clean route: ' . $canonicalPath);
}
foreach (["'/bac-si.php'", "'/review.php'", "'/ve-chung-toi.php'", "'/danh-muc-y-te.php'"] as $legacyPath) {
    $assert(!str_contains($sitemap, $legacyPath), 'Legacy PHP route removed from sitemap: ' . $legacyPath);
}
$assert(str_contains($sitemap, "LENGTH(TRIM(COALESCE(excerpt,'')))>=80")
    && str_contains($sitemap, "LENGTH(TRIM(COALESCE(story_json,'')))>=120"), 'Thin individual review records are excluded from sitemap entries');
foreach (['bac-si.php', 'co-so-y-te.php'] as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    $assert(str_contains($source, 'noindex,follow') && str_contains($source, 'index,follow')
        && str_contains($source, '$hasSeoFilters'), $file . ' marks filtered listings noindex');
    $assert(str_contains($source, "if (\$canonicalPage > 1) \$canonicalQuery['page'] = \$canonicalPage"), $file . ' uses a page-specific canonical URL');
}
$assert(str_contains((string) file_get_contents($root . '/bac-si.php'), "medical_redirect_legacy_path('/bac-si.php'") , 'Legacy doctor listing redirects to clean route');
echo "SEO directory rules: {$checks} checks passed. Static checks only; no network or production data touched.\n";
