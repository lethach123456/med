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
$assert(str_contains($sitemap, "medical_review_seo_indexable_where('r.')"),
    'Standalone review sitemap retains its content selection policy');
$assert(str_contains($sitemap, "'medical_toplist_facilities'")
    && str_contains($sitemap, "'medical_toplist_doctors'")
    && str_contains($sitemap, "medreview_sitemap_locale_sql(\$pdo, \$table, \$alias)")
    && str_contains($sitemap, "LEFT JOIN {\$table} {\$alias}")
    && str_contains($sitemap, "COALESCE({\$alias}.status,'')<>'published'"),
    'Toplist sitemap validates available linked profile schemas before checking public content and language');
foreach (['bac-si.php', 'co-so-y-te.php'] as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    $assert(str_contains($source, 'name="robots" content="index,follow"')
        && !str_contains($source, 'noindex'), $file . ' allows indexing filtered listings');
    $assert(str_contains($source, 'if (!$hasSeoFilters && $canonicalPage === 1)'), $file . ' does not label a filtered page as a translated unfiltered listing');
    $assert(str_contains($source, "if (\$canonicalPage > 1) \$canonicalQuery['page'] = \$canonicalPage"), $file . ' uses a page-specific canonical URL');
}
$assert(str_contains((string) file_get_contents($root . '/bac-si.php'), "medical_redirect_legacy_path('/bac-si.php'") , 'Legacy doctor listing redirects to clean route');
foreach (['review.php', 'cam-nang.php', 'toplist.php', 'review-chi-tiet.php', 'Tem/review-head.php',
          'co-so-y-te-chi-tiet.php', 'bac-si-chi-tiet.php', 'toplist-chi-tiet-mau.php', 'Tem/public-404.php',
          'homepage-new.php', 'homepage-concept.html', 'homepage-concept-02.html', 'homepage-concept-03.html',
          'assets/js/facility-directory.js', 'assets/js/doctor-directory.js'] as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    $assert(!preg_match('/\b(?:noindex|nofollow|x-robots-tag)\b/i', $source), $file . ' has no restrictive public directive');
}
foreach (['post.php', 'Tem/handbook-article.php'] as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    $assert(substr_count($source, 'noindex') === 1
        && str_contains($source, '<?php if ($canEditTemplate): ?><meta name="robots" content="noindex,follow">'),
        $file . ' keeps the directive only for authenticated edit previews');
}
$robots = (string) file_get_contents($root . '/robots.txt');
foreach (['/admin/', '/api/', '/config/', '/storage/'] as $blockedPath) {
    $assert(str_contains($robots, 'Disallow: ' . $blockedPath), 'Internal crawler exclusion remains: ' . $blockedPath);
}
echo "SEO directory rules: {$checks} checks passed. Static checks only; no network or production data touched.\n";
