<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Static metadata checks only: no application bootstrap, DB or network.
$root = dirname(__DIR__);
$contents = (string) file_get_contents($root . '/llms.txt');
$rewrite = (string) file_get_contents($root . '/.htaccess');
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($message);
};
$assert(preg_match('//u', $contents) === 1, 'llms.txt is valid UTF-8');
$assert(str_starts_with($contents, "# MedReview.vn\n\n> "), 'Site title and summary follow the llms.txt Markdown format');
$assert(preg_match_all('/^# /m', $contents) === 1, 'Exactly one top-level title');
$assert(strlen($contents) < 12000, 'The curated overview stays small');
$assert(!preg_match('/<\?(?:php|=)|<html|data:image\//i', $contents), 'No executable code, HTML document or inline images');
$assert(str_contains($rewrite, 'RewriteCond %{REQUEST_FILENAME} -f [OR]'), 'Existing static files bypass application routing');

preg_match_all('/^RewriteRule\s+(\S+)\s+(\S+)/m', $rewrite, $rules, PREG_SET_ORDER);
preg_match_all('/^- \[([^\]\n]+)\]\(([^)\s]+)\): .+$/m', $contents, $links, PREG_SET_ORDER);
$assert(count($links) >= 12, 'Both languages and core content have descriptive links');
$urls = [];
foreach ($links as [, $label, $url]) {
    $parts = parse_url($url);
    $assert(is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === 'medreview.vn', 'Public production HTTPS URL: ' . $label);
    $path = $parts['path'] ?? '/';
    $assert(!preg_match('~^/(?:admin|api|config|storage)(?:/|$)~', $path), 'No private or authenticated endpoint: ' . $label);
    $assert(!isset($parts['query']) && !isset($parts['user']) && !isset($parts['pass']), 'No credentials or query parameters: ' . $label);
    $reachable = $path === '/' || is_file($root . $path);
    foreach ($rules as [, $pattern, $target]) {
        if ($target === '-' || str_starts_with($target, 'route.php')) continue;
        if (preg_match('~' . $pattern . '~', ltrim($path, '/')) === 1) {
            $reachable = is_file($root . '/' . explode('?', $target, 2)[0]);
            if ($reachable) break;
        }
    }
    $assert($reachable, 'Static file or explicit public route exists: ' . $label);
    $urls[] = $url;
}
$assert(count($urls) === count(array_unique($urls)), 'No duplicate navigation URLs');
foreach (['/co-so-y-te', '/bac-si', '/review', '/toplist', '/cam-nang'] as $path) {
    $assert(in_array('https://medreview.vn' . $path, $urls, true) && in_array('https://medreview.vn/en' . $path, $urls, true), 'Both directory languages are present: ' . $path);
}
echo "llms.txt: {$checks} static checks passed; no DB/network.\n";
