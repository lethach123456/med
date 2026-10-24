<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Real controllers with a deliberately unavailable loopback DB and isolated JSON.
$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException($message);
};
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
if (!is_resource($socket)) throw new RuntimeException('Cannot reserve loopback port: ' . $message);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$cacheDirectory = sys_get_temp_dir() . '/medreview-seo-outage-' . bin2hex(random_bytes(8));
mkdir($cacheDirectory, 0700);
$process = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/seo_directory_router.php'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root,
    ['DB_HOST' => '127.0.0.1;port=1', 'DB_NAME' => 'isolated_no_database', 'DB_USER' => 'isolated',
        'DB_PASS' => 'not-a-real-password', 'MEDICAL_SEARCH_CACHE_DIR' => $cacheDirectory]);
if (!is_resource($process)) throw new RuntimeException('Cannot start isolated HTTP fixture');
foreach ([1, 2] as $pipe) stream_set_blocking($pipes[$pipe], false);
try {
    $ready = false;
    for ($attempt = 0; $attempt < 60; ++$attempt) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $message, .1);
        if (is_resource($probe)) { fclose($probe); $ready = true; break; }
        usleep(50000);
    }
    $assert($ready, 'Loopback fixture ready');
    $request = static function (string $path) use ($address): array {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 15]]);
        $body = file_get_contents('http://' . $address . $path, false, $context);
        $headers = $http_response_header ?? [];
        preg_match('~^HTTP/\S+ (\d+)~', $headers[0] ?? '', $status);
        return [(int) ($status[1] ?? 0), implode("\n", $headers), (string) $body];
    };
    foreach (['/bac-si', '/co-so-y-te', '/en/bac-si', '/en/co-so-y-te'] as $path) {
        [$status, $headers, $body] = $request($path);
        $assert($status === 503, $path . ' reports a temporary outage rather than an empty HTTP 200');
        $assert(str_contains($headers, 'Retry-After: 300') && str_contains($headers, 'no-store'), $path . ' advises retry without caching the outage');
        $assert(str_contains($body, 'name="robots" content="noindex,follow"'), $path . ' cannot index the temporary error page');
        $assert(str_contains($body, 'aria-hidden="true">503</p>') && !str_contains($body, 'SQLSTATE')
            && !str_contains($body, 'not-a-real-password'), $path . ' displays a truthful error without connection details');
        $assert(str_contains($body, str_starts_with($path, '/en/') ? 'could not be loaded' : 'Chưa tải được'), $path . ' uses the requested language');
    }

    $profile = ['id' => 1, 'slug' => 'cache-profile', 'name' => 'Saved profile fixture', 'language_code' => 'vi',
        'has_content' => true, 'city' => 'Hà Nội', 'category' => 'Phòng khám', 'specialty_text' => 'Nội tổng quát',
        'search_text' => 'saved profile fixture ha noi', 'city_key' => 'ha noi'];
    $snapshot = ['schema' => 3, 'facility_ratings_version' => 2, 'generated_at' => time() - 1200,
        'expires_at' => time() - 600, 'facilities' => [$profile], 'doctors' => [$profile], 'toplists' => [], 'cities' => []];
    file_put_contents($cacheDirectory . '/medical-search-index-v1.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    foreach (['/bac-si', '/co-so-y-te'] as $path) {
        [$status, $headers, $body] = $request($path);
        $assert($status === 200 && str_contains($body, 'Saved profile fixture'), $path . ' keeps serving a valid stale snapshot during the same DB outage');
        $assert(str_contains($body, 'name="robots" content="index,follow"') && !str_contains($headers, 'Retry-After:'), $path . ' remains indexable when real cached content is available');
    }
    [$homeStatus, , $homeHtml] = $request('/');
    [$aboutStatus, , $aboutHtml] = $request('/ve-chung-toi');
    $assert($homeStatus === 200 && $aboutStatus === 200, 'Home/about remain readable using public counters from the saved snapshot');
    $xpath = static function (string $html): DOMXPath {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try { $dom->loadHTML($html); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        return new DOMXPath($dom);
    };
    $homeProof = $xpath($homeHtml)->query('//div[@class="hero-proof"]/span/strong');
    $assert($homeProof->length === 1 && trim($homeProof->item(0)->textContent) === '1', 'Home shows the same one public facility as the directory even during a DB outage');
    $aboutProof = $xpath($aboutHtml);
    foreach (['cơ sở y tế', 'hồ sơ bác sĩ'] as $label) {
        $counter = $aboutProof->query('//dl/div[dt="' . $label . '"]/dd');
        $assert($counter->length === 1 && trim($counter->item(0)->textContent) === '1', 'About counter matches the corresponding directory: ' . $label);
    }
    echo "SEO directory HTTP: {$checks} checks passed; isolated loopback DB failure and temporary JSON only.\n";
} finally {
    proc_terminate($process);
    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    proc_close($process);
    foreach (['medical-search-index-v1.json', 'medical-search-index-v1.lock', 'medical-search-index-v1.invalidated'] as $file) {
        if (is_file($cacheDirectory . '/' . $file)) unlink($cacheDirectory . '/' . $file);
    }
    rmdir($cacheDirectory);
}
