<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Real-route rejection tests never reach db(). Success-path HTTP tests copy the
// exact endpoint bytes to a throwaway webroot with a SQLite-only auth/DB stub.
// There are no production writes, external HTTP requests or real API keys.
$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($message);
};
$key = 'local-insights-http-fixture-not-a-real-api-key';
$servers = [];
$temporaryFiles = [];
$temporaryDirectories = [];

$startServer = static function (string $documentRoot, array $environment) use (&$servers, $assert): string {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
    $assert(is_resource($socket), 'Reserve loopback fixture port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $process = proc_open([PHP_BINARY, '-S', $address, '-t', $documentRoot],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $documentRoot, $environment);
    $assert(is_resource($process), 'Start isolated loopback PHP server');
    foreach ($pipes as $pipe) stream_set_blocking($pipe, false);
    $servers[] = [$process, $pipes];
    $ready = false;
    for ($attempt = 0; $attempt < 60; ++$attempt) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $message, .1);
        if (is_resource($probe)) { fclose($probe); $ready = true; break; }
        usleep(50000);
    }
    $assert($ready, 'Loopback HTTP fixture ready');
    return $address;
};
$request = static function (string $address, string $path, string $method, array $headers = [], string $body = ''): array {
    if (!preg_match('/^127\.0\.0\.1:[0-9]+$/D', $address)) throw new LogicException('HTTP tests are loopback-only');
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers),
        'content' => $body, 'ignore_errors' => true, 'timeout' => 10]]);
    $response = file_get_contents('http://' . $address . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('~^HTTP/\S+ (\d+)~', $responseHeaders[0] ?? '', $status);
    return ['status' => (int) ($status[1] ?? 0), 'headers' => implode("\n", $responseHeaders),
        'body' => is_string($response) && $response !== '' ? json_decode($response, true, 64, JSON_THROW_ON_ERROR) : null];
};
$checkError = static function (array $response, int $status, ?string $reason = null) use ($assert): void {
    $assert($response['status'] === $status, 'Actual endpoint HTTP status ' . $status . ', received ' . $response['status']);
    $assert(($response['body']['ok'] ?? null) === false, 'Actual endpoint rejects error response');
    if ($reason !== null) $assert(($response['body']['reason'] ?? '') === $reason, 'Actual endpoint reason ' . $reason);
    $assert(str_contains($response['headers'], 'application/json') && !str_contains(json_encode($response['body']), 'SQLSTATE'), 'JSON errors do not expose DB diagnostics');
};
$json = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$getPath = '/api/medical/facility-insights.php';
$postPath = '/api/medical/facility-insights-update.php';
$auth = ['X-Medical-Api-Key: ' . $key];
$jsonHeaders = [...$auth, 'Content-Type: application/json'];

try {
    // These exact production routes use the real auth function. All requests
    // deliberately stop before any application DB access. Unusable local DB
    // settings also prevent accidental contact with machine-specific config.
    $actual = $startServer($root, ['MEDICAL_CONTENT_API_KEY' => $key, 'DB_HOST' => '127.0.0.1;port=1',
        'DB_NAME' => 'isolated_insights_fixture_unavailable', 'DB_USER' => 'fixture', 'DB_PASS' => 'fixture']);
    foreach ([$getPath => 'GET', $postPath => 'POST'] as $path => $method) {
        $wrongMethod = $method === 'GET' ? 'POST' : 'GET';
        $rejected = $request($actual, $path, $wrongMethod);
        $checkError($rejected, 405, 'method_not_allowed');
        $assert(str_contains($rejected['headers'], 'Allow: ' . $method . ', OPTIONS'), 'Method mismatch exposes exact Allow header');
        $preflight = $request($actual, $path, 'OPTIONS', ['Origin: https://chatgpt.com', 'Access-Control-Request-Method: ' . $method]);
        $assert($preflight['status'] === 204 && $preflight['body'] === null, 'Preflight needs no key, payload or DB connection');
        $assert(str_contains($preflight['headers'], 'Access-Control-Allow-Origin: https://chatgpt.com')
            && str_contains($preflight['headers'], 'Access-Control-Allow-Methods: ' . $method . ', OPTIONS')
            && str_contains($preflight['headers'], 'X-Medical-Api-Key'), 'Preflight returns scoped origin, method and API-key header');
        $extension = $request($actual, $path, 'OPTIONS', ['Origin: chrome-extension://' . str_repeat('a', 32)]);
        $assert(str_contains($extension['headers'], 'Access-Control-Allow-Origin: chrome-extension://' . str_repeat('a', 32)), 'Valid Chrome-extension origin is supported');
        $evil = $request($actual, $path, 'OPTIONS', ['Origin: https://chatgpt.com.evil.invalid']);
        $assert($evil['status'] === 204 && !str_contains($evil['headers'], 'Access-Control-Allow-Origin:'), 'Unknown origin is not reflected');
        $assert(str_contains($preflight['headers'], 'Cache-Control: private, no-store') && str_contains($preflight['headers'], 'Vary: Origin'), 'Responses cannot enter a shared cache');
        $checkError($request($actual, $path . '?id=1', $method, ['Content-Type: application/json'], '{}'), 401);
        $checkError($request($actual, $path . '?id=1', $method, ['X-Medical-Api-Key: wrong-key', 'Content-Type: application/json'], '{}'), 401);
    }
    foreach (['', '?id=0', '?id%5B%5D=1', '?id=1.1', '?id=01', '?id=-1'] as $query) {
        $checkError($request($actual, $getPath . $query, 'GET', $auth), 422, 'invalid_input');
    }
    $checkError($request($actual, $postPath, 'POST', [...$auth, 'Content-Type: text/plain'], '{}'), 415, 'unsupported_media_type');
    $checkError($request($actual, $postPath, 'POST', $jsonHeaders, '{invalid}'), 400, 'invalid_json');
    foreach (['[]', 'null', 'true', '1'] as $payload) $checkError($request($actual, $postPath, 'POST', $jsonHeaders, $payload), 422, 'invalid_body');
    $checkError($request($actual, $postPath, 'POST', $jsonHeaders, '{"id":1}'), 422, 'missing_insights');
    $checkError($request($actual, $postPath, 'POST', $jsonHeaders, '{"id":1,"facility_insights_json":{},"status":"published"}'), 422, 'unsupported_fields');
    $checkError($request($actual, $postPath, 'POST', $jsonHeaders, '{"id":1,"facility_insights_json":null}'), 422, 'invalid_input');
    $checkError($request($actual, $postPath, 'POST', $jsonHeaders, '{"id":1,"facility_insights_json":[]}'), 422, 'invalid_input');
    $checkError($request($actual, $postPath, 'POST', $jsonHeaders, '{"id":1,"facility_insights_json":{},"insights_revision":"bad"}'), 422, 'invalid_revision');
    $checkError($request($actual, $postPath, 'POST', $jsonHeaders, '{"id":1,"facility_insights_json":{"sources":[{"id":"s1","url":"http://127.0.0.1/private"}]}}'), 422, 'invalid_input');
    $checkError($request($actual, $postPath, 'POST', $jsonHeaders, '{"long":"' . str_repeat('a', 2097152) . '"}'), 413, 'body_too_large');

    // Build only explicitly tracked throwaway paths. No production bootstrap is
    // copied, and this stub's db() has no MySQL or network fallback whatsoever.
    $fixtureRoot = sys_get_temp_dir() . '/medreview-insights-http-' . bin2hex(random_bytes(8));
    $webRoot = $fixtureRoot . '/web';
    foreach ([$fixtureRoot, $webRoot, $webRoot . '/api', $webRoot . '/api/medical'] as $directory) {
        if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot create isolated HTTP fixture');
        $temporaryDirectories[] = $directory;
    }
    foreach (['medical_facility_insights.php', 'medical_facility_insights_api.php', 'api/medical/facility-insights.php', 'api/medical/facility-insights-update.php'] as $relative) {
        $target = $webRoot . '/' . $relative;
        if (!copy($root . '/' . $relative, $target)) throw new RuntimeException('Cannot copy HTTP fixture source');
        $temporaryFiles[] = $target;
        $assert(hash_file('sha256', $root . '/' . $relative) === hash_file('sha256', $target), 'Fixture uses byte-identical implementation: ' . $relative);
    }
    $authStub = <<<'PHP'
<?php
declare(strict_types=1);
function json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function medical_api_auth(): void {
    $provided = (string) ($_SERVER['HTTP_X_MEDICAL_API_KEY'] ?? '');
    $expected = (string) getenv('MEDICAL_CONTENT_API_KEY');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) json_response(['ok' => false, 'message' => 'Fixture API key rejected.'], 401);
}
function db(): PDO {
    $path = (string) getenv('MEDREVIEW_INSIGHTS_FIXTURE_DB');
    if ($path === '' || !is_file($path)) throw new RuntimeException('Isolated SQLite fixture unavailable');
    return new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
PHP;
    $stubPath = $webRoot . '/api/medical/_auth.php';
    $temporaryFiles[] = $stubPath;
    if (file_put_contents($stubPath, $authStub) === false) throw new RuntimeException('Cannot write isolated auth fixture');
    $dbPath = $fixtureRoot . '/fixture.sqlite';
    $temporaryFiles[] = $dbPath;
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE medical_facilities (
        id INTEGER PRIMARY KEY, slug TEXT, name TEXT, category TEXT, city TEXT, address_text TEXT,
        phone_text TEXT, website_url TEXT, google_maps_url TEXT, social_links_json TEXT, language_code TEXT, status TEXT,
        facility_insights_json TEXT NULL, content TEXT DEFAULT 'UNCHANGED_ARTICLE', full_json TEXT DEFAULT 'PRIVATE_FULL_JSON',
        patient_images_json TEXT DEFAULT 'PRIVATE_PATIENT_IMAGE', patient_images_notes TEXT DEFAULT 'PRIVATE_PATIENT_NOTES',
        image_url TEXT DEFAULT 'UNCHANGED_PHOTO', gallery_json TEXT DEFAULT 'UNCHANGED_GALLERY',
        rating REAL DEFAULT 4.7, reviews_count INTEGER DEFAULT 18, verified INTEGER DEFAULT 1,
        ai_writer_claim_json TEXT DEFAULT 'PRIVATE_WRITER_TOKEN')");
    $pdo->exec("INSERT INTO medical_facilities(id,slug,name,category,city,address_text,phone_text,website_url,google_maps_url,social_links_json,language_code,status)
        VALUES (393,'fixture-clinic','Cơ sở HTTP mẫu','Nha khoa','Đà Nẵng','12 Đường A','0123456789','https://clinic.example.org','https://maps.google.com/?cid=123','{}','vi','published'),
        (394,'draft-fixture','Cơ sở bản nháp','Phòng khám','Hà Nội','14 Đường B','','https://clinic.example.org','','{}','vi','draft')");
    $before = $pdo->query('SELECT * FROM medical_facilities WHERE id=393')->fetch();
    $isolated = $startServer($webRoot, ['MEDICAL_CONTENT_API_KEY' => $key, 'MEDREVIEW_INSIGHTS_FIXTURE_DB' => $dbPath]);
    $read = $request($isolated, $getPath . '?id=393', 'GET', $auth);
    $assert($read['status'] === 200 && $read['body']['ok'] === true, 'Dedicated GET succeeds against isolated storage');
    $assert($read['body']['facility']['id'] === 393 && $read['body']['facility']['name'] === 'Cơ sở HTTP mẫu', 'GET preserves canonical identity');
    $assert($read['body']['contract_version'] === 1 && $read['body']['stored_insights_valid'] === true, 'GET includes contract and stored JSON validity');
    $assert($read['body']['insights_revision'] === hash('sha256', 'null'), 'HTTP GET revision matches actual NULL bytes');
    $assert(!str_contains($json($read['body']), 'PRIVATE_') && !str_contains($json($read['body']), 'UNCHANGED_'), 'GET/prompt never include patient, claim, article or gallery content');
    $assert(str_contains($read['headers'], 'X-MedReview-Facility-Insights-Contract: 1'), 'GET exposes deployed contract header');
    $analysis = ['summary' => 'Phân tích biên tập từ nguồn công khai.', 'custom_notes' => 'Không phải chứng nhận chất lượng.'];
    $post = $request($isolated, $postPath, 'POST', $jsonHeaders, $json(['id' => 393, 'facility_insights_json' => $analysis,
        'insights_revision' => $read['body']['insights_revision']]));
    $assert($post['status'] === 200 && $post['body']['ok'] === true && $post['body']['saved'] === true && $post['body']['storage_verified'] === true, 'HTTP POST returns a verified storage receipt');
    $assert($post['body']['saved_fields'] === ['facility_insights_json'] && !str_contains($json($post['body']), 'Phân tích biên tập'), 'Receipt identifies saved column without echoing analysis');
    $after = $pdo->query('SELECT * FROM medical_facilities WHERE id=393')->fetch();
    $assert(json_decode($after['facility_insights_json'], true) === $analysis, 'HTTP POST actually writes the dedicated column');
    $assert(array_diff_assoc($before, $after) === ['facility_insights_json' => null], 'HTTP write preserves article, photos, patient fields, leases, rating, verification and publication');
    $assert($post['body']['insights_revision'] === hash('sha256', $after['facility_insights_json']), 'HTTP receipt revision matches DB read-back');
    $readAfter = $request($isolated, $getPath . '?id=393', 'GET', $auth);
    $assert($readAfter['body']['facility_insights_json'] === $analysis && $readAfter['body']['insights_revision'] === $post['body']['insights_revision'], 'Subsequent GET returns persisted insights, not request echo');
    $stale = $request($isolated, $postPath, 'POST', $jsonHeaders, $json(['id' => 393, 'facility_insights_json' => ['summary' => 'Stale overwrite'], 'insights_revision' => $read['body']['insights_revision']]));
    $checkError($stale, 409, 'insights_conflict');
    $assert($pdo->query('SELECT * FROM medical_facilities WHERE id=393')->fetch() === $after, 'HTTP stale revision does not overwrite any stored field');
    $draft = $request($isolated, $postPath, 'POST', $jsonHeaders, '{"id":394,"facility_insights_json":{"summary":"Independent analysis"}}');
    $assert($draft['status'] === 200 && $draft['body']['saved'] === true, 'Dedicated API can update a draft without a writer claim');
    $assert($pdo->query('SELECT status FROM medical_facilities WHERE id=394')->fetchColumn() === 'draft', 'Independent analysis never publishes the facility');
    $clear = $request($isolated, $postPath, 'POST', $jsonHeaders, $json(['id' => 393, 'facility_insights_json' => new stdClass(), 'insights_revision' => $post['body']['insights_revision']]));
    $assert($clear['status'] === 200 && $pdo->query('SELECT facility_insights_json FROM medical_facilities WHERE id=393')->fetchColumn() === '{}', 'HTTP explicit {} clears only insights');
    $checkError($request($isolated, $getPath . '?id=999', 'GET', $auth), 404, 'facility_not_found');
    $checkError($request($isolated, $postPath, 'POST', $jsonHeaders, '{"id":999,"facility_insights_json":{}}'), 404, 'facility_not_found');
    $pdo->exec("UPDATE medical_facilities SET facility_insights_json = '{PRIVATE_BROKEN_JSON' WHERE id=393");
    $badRead = $request($isolated, $getPath . '?id=393', 'GET', $auth);
    $assert($badRead['status'] === 200 && $badRead['body']['stored_insights_valid'] === false, 'HTTP GET flags invalid historic JSON');
    $assert(!str_contains($json($badRead['body']), 'PRIVATE_BROKEN_JSON'), 'Invalid historic raw text never enters API response or prompt');
    $repair = $request($isolated, $postPath, 'POST', $jsonHeaders, $json(['id' => 393, 'facility_insights_json' => new stdClass(), 'insights_revision' => $badRead['body']['insights_revision']]));
    $assert($repair['status'] === 200 && $repair['body']['storage_verified'] === true, 'HTTP explicit repair of invalid historic value succeeds');
    $pdo->exec("CREATE TRIGGER corrupt_insights AFTER UPDATE OF facility_insights_json ON medical_facilities
        BEGIN UPDATE medical_facilities SET facility_insights_json='CORRUPTED' WHERE id=NEW.id; END");
    $stable = $pdo->query('SELECT * FROM medical_facilities WHERE id=393')->fetch();
    $failedWrite = $request($isolated, $postPath, 'POST', $jsonHeaders, '{"id":393,"facility_insights_json":{"summary":"Should roll back"}}');
    $checkError($failedWrite, 500, 'save_failed');
    $assert($pdo->query('SELECT * FROM medical_facilities WHERE id=393')->fetch() === $stable, 'HTTP verification failure rolls back the write');
    $assert(!str_contains($json($failedWrite['body']), 'CORRUPTED') && !str_contains($json($failedWrite['body']), 'RuntimeException'), 'Save errors expose no internal diagnostic or stored data');
    $pdo->exec('DROP TRIGGER corrupt_insights');
    $pdo->exec('ALTER TABLE medical_facilities DROP COLUMN facility_insights_json');
    $missing = $request($isolated, $postPath, 'POST', $jsonHeaders, '{"id":393,"facility_insights_json":{}}');
    $checkError($missing, 503, 'schema_unavailable');
    $assert(str_contains($missing['body']['message'], 'migrate_facility_insights.php --apply'), 'HTTP schema failure gives isolated migration guidance');
    $checkError($request($isolated, $getPath . '?id=393', 'GET', $auth), 503, 'schema_unavailable');
    $assert($pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === 2, 'No HTTP operation implicitly creates or removes facilities');
    $pdo = null;
} finally {
    foreach (array_reverse($servers) as [$process, $pipes]) {
        proc_terminate($process);
        foreach ($pipes as $pipe) fclose($pipe);
        proc_close($process);
    }
    foreach (array_reverse($temporaryFiles) as $path) if (is_file($path)) unlink($path);
    foreach (array_reverse($temporaryDirectories) as $path) if (is_dir($path)) rmdir($path);
}
echo "Facility insights HTTP: {$checks} checks passed; loopback and isolated SQLite only, no production DB writes.\n";
