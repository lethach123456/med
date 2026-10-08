<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
try {
    medreview_with_schema_migration(static function (): void { medical_facility_image_fix_migrate(db()); });
    echo "Facility image-fix schema and default prompt ready. Existing images and custom prompts preserved. No seed data inserted.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Image-fix migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
