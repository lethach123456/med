<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/medical_handbook_ai.php';
require_once dirname(__DIR__) . '/medical_handbook_translations.php';
try {
    $pdo = db();
    medreview_with_schema_migration(static function() use($pdo):void {
        handbook_ai_migrate($pdo);
        handbook_translations_migrate($pdo);
    });
    echo "Handbook AI ready: writer lease + result metadata columns, editable handbook prompt. Existing content/custom prompts preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Handbook AI migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
