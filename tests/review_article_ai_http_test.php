<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Real routes on loopback; synthetic auth key and deliberately unavailable local DB only. */
$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $value, string $message) use (&$checks): void { ++$checks; if (!$value) throw new RuntimeException($message); };
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
$assert(is_resource($socket), 'Reserve loopback port');
$address = stream_socket_get_name($socket, false); fclose($socket);
$key = 'review-article-loopback-fixture-not-a-production-key';
$process = proc_open([PHP_BINARY, '-S', $address, '-t', $root], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root,
    ['MEDICAL_CONTENT_API_KEY' => $key, 'DB_HOST' => '127.0.0.1;port=1', 'DB_NAME' => 'isolated_no_database', 'DB_USER' => 'isolated', 'DB_PASS' => 'not-a-real-password']);
$assert(is_resource($process), 'Start HTTP fixture with no production DB access');
try {
    $ready = false;
    for ($attempt = 0; $attempt < 60; ++$attempt) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $message, .1);
        if (is_resource($probe)) { fclose($probe); $ready = true; break; }
        usleep(50000);
    }
    $assert($ready, 'HTTP fixture ready');
    $request = static function (string $path, string $method, array $headers = [], string $body = '') use ($address): array {
        $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true, 'timeout' => 10]]);
        $raw = file_get_contents('http://' . $address . $path, false, $context);
        $headers = $http_response_header ?? [];
        preg_match('~^HTTP/\S+ (\d+)~', $headers[0] ?? '', $status);
        return ['status' => (int) ($status[1] ?? 0), 'headers' => implode("\n", $headers),
            'body' => is_string($raw) && trim($raw) !== '' ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null];
    };
    $checkError = static function (array $response, int $status, ?string $reason = null) use ($assert, $key): void {
        $assert($response['status'] === $status, 'HTTP status ' . $status . ', actual ' . $response['status']);
        $assert(($response['body']['ok'] ?? null) === false, 'Structured JSON rejection');
        if ($reason !== null) $assert(($response['body']['reason'] ?? '') === $reason, 'Reason ' . $reason);
        $body = json_encode($response['body']);
        $assert(!str_contains($body, 'SQLSTATE') && !str_contains($body, 'not-a-real-password') && !str_contains($body, $key), 'No SQL, password or token leaks');
        $assert(str_contains($response['headers'], 'application/json') && str_contains($response['headers'], 'no-store'), 'JSON errors are not cached');
    };
    foreach (['/api/medical/review-articles-needing-content.php', '/api/medical/review-article-content-update.php'] as $path) {
        $preflight = $request($path, 'OPTIONS', ['Origin: https://chatgpt.com', 'Access-Control-Request-Method: POST']);
        $assert($preflight['status'] === 204 && $preflight['body'] === null, 'OPTIONS needs no key or DB');
        $assert(str_contains($preflight['headers'], 'Access-Control-Allow-Origin: https://chatgpt.com') && str_contains($preflight['headers'], 'X-Medical-Api-Key'), 'Exact approved origin and auth header');
        $extension = $request($path, 'OPTIONS', ['Origin: chrome-extension://' . str_repeat('a', 32)]);
        $assert(str_contains($extension['headers'], 'Access-Control-Allow-Origin: chrome-extension://'), 'Chrome extension CORS');
        $foreign = $request($path, 'OPTIONS', ['Origin: https://attacker.example.org']);
        $assert(!str_contains($foreign['headers'], 'Access-Control-Allow-Origin:'), 'Unapproved origin gets no permissive CORS');
        $checkError($request($path, 'POST', ['Content-Type: application/json'], '{}'), 401);
        $checkError($request($path, 'POST', ['Content-Type: application/json', 'X-Medical-Api-Key: wrong']), 401);
        $checkError($request($path, 'PUT'), 405, 'method_not_allowed');
        $checkError($request($path, 'POST', ['X-Medical-Api-Key: ' . $key, 'Content-Type: text/plain'], '{}'), 415, 'content_type');
        $checkError($request($path, 'POST', ['X-Medical-Api-Key: ' . $key, 'Content-Type: application/json'], '{'), 400, 'invalid_json');
        $checkError($request($path, 'POST', ['X-Medical-Api-Key: ' . $key, 'Content-Type: application/json'], '[]'), 400, 'invalid_body');
        $checkError($request($path, 'POST', ['X-Medical-Api-Key: ' . $key, 'Content-Type: application/json'], '{"x":[' . str_repeat('0,', 35000) . '0]}'),
            $path === '/api/medical/review-articles-needing-content.php' ? 413 : 400,
            $path === '/api/medical/review-articles-needing-content.php' ? 'body_too_large' : 'json_too_complex');
    }
    $checkError($request('/api/medical/review-article-content-update.php', 'GET'), 405, 'method_not_allowed');
    $checkError($request('/api/medical/review-articles-needing-content.php', 'GET', ['X-Medical-Api-Key: ' . $key]), 503, 'schema_unavailable');
    $checkError($request('/api/medical/review-article-content-update.php', 'POST', ['X-Medical-Api-Key: ' . $key, 'Content-Type: application/json'], '{"entity_id":1}'), 503, 'schema_unavailable');
    echo 'Review article HTTP: ' . $checks . " checks passed.\n";
} finally {
    proc_terminate($process);
    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    proc_close($process);
}
