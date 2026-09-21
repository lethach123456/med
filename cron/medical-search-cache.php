<?php
declare(strict_types=1);

require_once __DIR__ . '/../medical_search_cache.php';

try {
    $state = medical_search_cache_index(db(), true);
    $index = $state['index'];
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'source' => $state['source'],
        'cache_file' => medical_search_cache_path(),
        'generated_at' => gmdate('c', (int) ($index['generated_at'] ?? time())),
        'expires_at' => gmdate('c', (int) ($index['expires_at'] ?? time())),
        'counts' => $index['counts'] ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, 'Medical search cache failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
