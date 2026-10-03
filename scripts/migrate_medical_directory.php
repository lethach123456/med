<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';

try {
    $pdo = db();
    medical_directory_run_schema_migrations($pdo);
    echo "Medical schema and default prompts ready. Existing content, reviews, ratings, translations and writer claims preserved. No demo records inserted.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Medical migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
