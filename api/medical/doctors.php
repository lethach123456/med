<?php
declare(strict_types=1);

require_once __DIR__ . '/../../medical_search_cache.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=30, stale-while-revalidate=60');

try {
    $filters = [
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
        'limit' => min(24, max(6, (int) ($_GET['limit'] ?? 12))),
        'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120, 'UTF-8'),
        'city' => mb_substr(trim((string) ($_GET['city'] ?? '')), 0, 120, 'UTF-8'),
        'specialty' => mb_substr(trim((string) ($_GET['specialty'] ?? '')), 0, 120, 'UTF-8'),
        'min_rating' => (float) ($_GET['min_rating'] ?? 0),
        'sort' => (string) ($_GET['sort'] ?? 'recommended'),
    ];
    $cache = medical_search_cache_index();
    $locale = site_normalize_locale((string) ($_GET['locale'] ?? $_GET['lang'] ?? 'vi'));
    $localizedIndex = medical_search_cache_filter_locale($cache['index'], $locale);
    $result = medical_search_cache_doctor_directory_search($localizedIndex, $filters);
    $index = $cache['index'];

    echo json_encode([
        'ok' => true,
        'items' => $result['items'],
        'paging' => $result['paging'],
        'cache' => [
            'source' => $cache['source'],
            'stale' => $cache['stale'],
            'generated_at' => (int) ($index['generated_at'] ?? 0),
            'expires_at' => (int) ($index['expires_at'] ?? 0),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('medical doctors list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Không thể tải danh sách bác sĩ.'], JSON_UNESCAPED_UNICODE);
}
