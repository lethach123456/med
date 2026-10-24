<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/site_analytics.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($label);
};
$request = static fn(string $host, string $uri = '/', string $script = '/index.php'): array => ['HTTP_HOST' => $host, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => $script];
foreach (['medreview.vn', 'www.medreview.vn', 'MEDREVIEW.VN', 'medreview.vn:443', 'www.medreview.vn:80'] as $host) {
    $check(site_tag_manager_enabled($request($host), []), 'Production host enabled: ' . $host);
    $check(site_tag_manager_enabled($request($host, '/en/co-so-y-te/vi-du'), []), 'English pages use the same container.');
}
foreach (['localhost', '127.0.0.1:8799', 'medreview.test', 'staging.medreview.vn', 'medreview.vn.example.org', 'example.org', 'medreview.vn:8799', 'medreview.vn/path', 'medreview.vn@evil.test', ''] as $host) {
    $check(!site_tag_manager_enabled($request($host), []), 'No analytics pollution from preview/untrusted host: ' . $host);
}
foreach (['/admin/dashboard.php', '/api/medical/search.php', '/builder/builder.php', '/scripts/migrate.php', '/cron/run.php', '/en/admin/test', '/%61dmin/dashboard.php'] as $path) {
    $check(!site_tag_manager_enabled($request('medreview.vn', $path), []), 'Non-public endpoint excluded: ' . $path);
    $check(!site_tag_manager_enabled($request('medreview.vn', '/', $path), []), 'Rewritten non-public controller excluded: ' . $path);
}
foreach (['edit_template', 'front_editor_no_redirect'] as $key) $check(!site_tag_manager_enabled($request('medreview.vn'), [$key => '1']), 'Editor preview excluded.');

$_SERVER = $request('medreview.vn');
$_GET = [];
unset($GLOBALS['site_tag_manager_head_emitted'], $GLOBALS['site_tag_manager_body_emitted']);
$head = site_tag_manager_head();
$body = site_tag_manager_body();
$check(substr_count($head, "'GTM-WQKB3F22'") === 1 && str_contains($head, 'j.async=true'), 'User container loads asynchronously.');
$check(str_contains($head, "'dataLayer'") && str_contains($head, "event:'gtm.js'"), 'Standard GTM dataLayer bootstrap retained.');
$check(!str_contains($head, '<iframe') && !str_contains($head, 'gtag('), 'No invalid iframe in head or duplicate GA loader.');
$check(str_contains($body, '<noscript><iframe') && str_contains($body, 'ns.html?id=GTM-WQKB3F22'), 'Exact noscript container installed.');
$check(str_contains($body, 'height="0" width="0"') && str_contains($body, 'display:none;visibility:hidden'), 'Fallback never alters the layout.');
$check(site_tag_manager_head() === '' && site_tag_manager_body() === '', 'Repeated includes emit no duplicate tags.');
unset($GLOBALS['site_tag_manager_head_emitted'], $GLOBALS['site_tag_manager_body_emitted']);
$_SERVER = $request('localhost:8799');
$check(site_tag_manager_head() === '' && site_tag_manager_body() === '', 'Local rendered documents contain no tracking calls.');
$check(empty($GLOBALS['site_tag_manager_head_emitted']) && empty($GLOBALS['site_tag_manager_body_emitted']), 'Suppressed preview does not mark container as emitted.');

$root = dirname(__DIR__);
$documents = ['index.php', 'bac-si.php', 'co-so-y-te.php', 'bac-si-chi-tiet.php', 'co-so-y-te-chi-tiet.php', 'toplist.php', 'cam-nang.php', 'review.php', 'review-bai-viet.php', 'homepage-new.php', 'about-us.php', 'blog.php', 'danh-muc-y-te.php', 'dich-vu.php', 'news.php', 'post.php', 'san-pham.php', 'services-en.php', 've-chung-toi.php', 'ho-so-da-xac-thuc.php', 'Tem/handbook-article.php', 'Tem/toplist-doctor-detail.php', 'Tem/contact-medreview.php', 'Tem/public-404.php'];
foreach ($documents as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    $check(preg_match('~<head>\s*<\?(?:php\s+echo\s+|=\s*)site_tag_manager_head\(\)~', $source) === 1, 'Head hook starts document head: ' . $file);
    $check(preg_match('~<body\b[^>]*>\s*<\?(?:php\s+echo\s+|=\s*)site_tag_manager_body\(\)~', $source) === 1, 'Body hook precedes rendered content: ' . $file);
}
$fragment = (string) file_get_contents($root . '/Tem/review-head.php');
$check(str_contains($fragment, 'site_tag_manager_head()'), 'Individual review head fragment covered.');
$detail = (string) file_get_contents($root . '/review-chi-tiet.php');
$check(str_contains($detail, 'site_tag_manager_body()'), 'Individual review body covered.');
$legacy = (string) file_get_contents($root . '/toplist-chi-tiet-mau.php');
$check(str_contains($legacy, 'site_tag_manager_head()') && str_contains((string) file_get_contents($root . '/Tem/header.php'), 'site_tag_manager_body()'), 'Legacy Toplist receives one head hook and the idempotent shared body fallback.');
$check(str_contains((string) file_get_contents($root . '/db.php'), "require_once __DIR__ . '/site_analytics.php'"), 'Pure helper is available through the common bootstrap.');
echo "Site analytics: {$checks} checks passed; no DB or network requests.\n";
