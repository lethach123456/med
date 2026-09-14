#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Hostinger / server cron entry example (every minute):
 * * * * * /usr/bin/php /absolute/path/to/top/cron/medical-media-worker.php --type=all --mode=download --limit=10 --parallel=3 >> /absolute/path/to/top/storage/logs/medical-media-worker.log 2>&1
 */

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/medical_media_worker.php';

$options = ['type' => 'all', 'mode' => 'download', 'limit' => 10, 'parallel' => 3, 'scan' => true];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--no-scan') { $options['scan'] = false; continue; }
    if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) continue;
    [$key, $value] = explode('=', substr($argument, 2), 2);
    if ($key === 'type' && in_array($value, ['all', 'facility', 'doctor'], true)) $options['type'] = $value;
    if ($key === 'mode' && in_array($value, ['download', 'compress', 'full'], true)) $options['mode'] = $value;
    if ($key === 'limit') $options['limit'] = min(30, max(1, (int) $value));
    if ($key === 'parallel') $options['parallel'] = min(3, max(1, (int) $value));
}

try {
    $result = medical_media_worker_run(db(), (string) $options['type'], (int) $options['limit'], (int) $options['parallel'], (bool) $options['scan'], (string) $options['mode']);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(!empty($result['ok']) ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'Medical media worker failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
