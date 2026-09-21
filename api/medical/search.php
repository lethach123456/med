<?php
declare(strict_types=1);

require_once __DIR__ . '/../../medical_search_cache.php';

header('Content-Type: application/json; charset=utf-8');
// Queries can contain sensitive health-related words. The server-side JSON
// snapshot already removes the database load; keep the response browser-only
// instead of letting a shared proxy cache someone else's search URL.
header('Cache-Control: private, max-age=30, stale-while-revalidate=60');

/** @param array<string,mixed> $payload */
function medical_public_search_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120, 'UTF-8');
if (mb_strlen($query, 'UTF-8') < 2) {
    medical_public_search_response([
        'ok' => true,
        'query' => $query,
        'min_length' => 2,
        'groups' => ['facilities' => [], 'doctors' => [], 'toplists' => []],
    ]);
}

try {
    $limit = min(6, max(1, (int) ($_GET['limit'] ?? 4)));
    // Fresh requests read one local JSON file only. On expiry, one request
    // rebuilds the file; other concurrent requests retain the old index.
    $cache = medical_search_cache_index();
    $results = medical_search_cache_search($cache['index'], $query, $limit);
    $index = $cache['index'];

    medical_public_search_response([
        'ok' => true,
        'query' => $query,
        'min_length' => 2,
        'groups' => $results['groups'],
        // Kept lightweight but useful when testing the rollout. Frontend does
        // not depend on it, so it can be removed later without a breaking API.
        'cache' => [
            'source' => $cache['source'],
            'stale' => $cache['stale'],
            'generated_at' => (int) ($index['generated_at'] ?? 0),
            'expires_at' => (int) ($index['expires_at'] ?? 0),
            'city' => (string) ($results['meta']['city'] ?? ''),
        ],
    ]);
} catch (Throwable $e) {
    error_log('medical public search failed: ' . $e->getMessage());
    medical_public_search_response(['ok' => false, 'message' => 'Không thể tìm kiếm lúc này.'], 500);
}
