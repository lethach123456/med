<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MEDREVIEW_SITEMAP_FUNCTIONS_ONLY', true);
require_once dirname(__DIR__) . '/sitemap.php';

$cases = [
    200 => ['Cache-Control: public, max-age=300, s-maxage=900, stale-while-revalidate=3600'],
    404 => ['Cache-Control: no-store'],
    500 => ['Cache-Control: no-store'],
    503 => ['Cache-Control: no-store', 'Retry-After: 300'],
];
foreach ($cases as $status => $expected) {
    if (medreview_sitemap_cache_headers($status) !== $expected) {
        throw new RuntimeException('Incorrect sitemap cache headers for status ' . $status);
    }
}
echo 'Sitemap cache headers: ' . count($cases) . " checks passed. No network or database used.\n";
