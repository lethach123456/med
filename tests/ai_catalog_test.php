<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Offline catalog/discovery regressions: no bootstrap, DB, HTTP or writes.
$root = dirname(__DIR__);
$source = (string) file_get_contents($root . '/ai-catalog.json');
$catalog = json_decode($source, true, 32, JSON_THROW_ON_ERROR);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($message);
};
$assert(preg_match('//u', $source) === 1, 'Catalog is valid UTF-8');
$assert($catalog['specVersion'] === '1.0', 'Explicit compatible AI Catalog specification version');
$assert(array_diff(array_keys($catalog), ['specVersion', 'host', 'entries']) === [], 'Only compatible top-level catalog fields');
$assert($catalog['host']['displayName'] === 'MedReview.vn', 'Publisher display name is accurate');
$assert($catalog['host']['documentationUrl'] === 'https://medreview.vn/llms.txt', 'Publisher documentation uses the real public guide');
$assert(is_array($catalog['entries']) && array_is_list($catalog['entries']) && count($catalog['entries']) > 0, 'Catalog has real resource entries');
$identifiers = [];
foreach ($catalog['entries'] as $entry) {
    foreach (['identifier', 'displayName', 'type', 'description'] as $key) {
        $assert(is_string($entry[$key] ?? null) && trim($entry[$key]) !== '', 'Nonempty resource field: ' . $key);
    }
    $assert((bool) preg_match('/^urn:air:medreview\.vn(:[a-zA-Z0-9._-]+)+$/', $entry['identifier']), 'Stable publisher-scoped resource URN');
    $assert(isset($entry['url']) && !array_key_exists('data', $entry), 'Referenced resource has exactly one content source');
    $assert($entry['url'] === 'https://medreview.vn/llms.txt', 'Only the real public guide is advertised');
    $assert($entry['type'] === 'text/plain', 'Guide has its true media type, not a fabricated MCP/A2A protocol');
    $assert(!isset($entry['trustManifest']) && !isset($catalog['host']['trustManifest']), 'No unverifiable certification or signed-identity claim');
    $path = parse_url($entry['url'], PHP_URL_PATH);
    $assert(is_string($path) && is_file($root . $path), 'Referenced static resource exists');
    $queries = $entry['representativeQueries'] ?? [];
    $assert(is_array($queries) && count($queries) >= 2 && count($queries) <= 5, 'Two to five discovery queries are supplied');
    foreach ($queries as $query) $assert(is_string($query) && trim($query) !== '', 'Discovery query is meaningful text');
    foreach ($entry['tags'] ?? [] as $tag) $assert(is_string($tag) && trim($tag) !== '', 'Resource tag is meaningful text');
    $identifiers[] = $entry['identifier'];
}
$assert(count(array_unique($identifiers)) === count($identifiers), 'Resource identifiers are unique');

$rewrite = (string) file_get_contents($root . '/.htaccess');
preg_match_all('/^RewriteRule\s+(\S+)\s+(\S+)\s+(\[[^\]]+\])/m', $rewrite, $rules, PREG_SET_ORDER);
$matches = [];
foreach ($rules as $rule) {
    if ($rule[2] === '-') continue; // Existing-file pass-through is not an alias.
    if (preg_match('~' . $rule[1] . '~', '.well-known/ai-catalog.json') === 1) $matches[] = $rule;
}
$assert(count($matches) === 1, 'Well-known discovery path has exactly one explicit rewrite');
$assert($matches[0][2] === 'ai-catalog.json' && $matches[0][3] === '[L]', 'Well-known alias serves the same static catalog without a redirect or DB route');
$assert(strpos($rewrite, $matches[0][0]) < strpos($rewrite, 'route.php?slug='), 'Catalog alias precedes the generic page router');

$index = (string) file_get_contents($root . '/index.php');
$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
try { $dom->loadHTML(substr($index, strpos($index, '<!doctype html>'))); }
finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
$links = (new DOMXPath($dom))->query('//head/link[@rel="ai-catalog"]');
$assert($links->length === 1, 'Both homepage languages have one discoverable catalog link');
$assert($links->item(0)->getAttribute('href') === '/ai-catalog.json' && $links->item(0)->getAttribute('type') === 'application/ai-catalog+json', 'Discovery link points to the local catalog with the catalog media type');
$assert(str_contains((string) file_get_contents($root . '/llms.txt'), '[AI Catalog](https://medreview.vn/ai-catalog.json)'), 'llms.txt also links to the catalog');
$assert(str_contains((string) file_get_contents($root . '/robots.txt'), 'Disallow: /api/'), 'Catalog does not weaken private API crawling rules');

echo "AI Catalog: {$checks} static checks passed; no DB/network.\n";
