<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/schema_migrations.php';
try {
    medreview_run_all_schema_migrations(db());
    echo "Core, content, editor, medical and media-queue schema ready. Existing records preserved. No demo seed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Schema migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
