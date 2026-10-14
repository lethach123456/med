<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/schema_migrations.php';
try {
    medreview_with_schema_migration(static function (): void {
        medreview_migrate_image_audit_columns(db());
    });
    echo "Image audit columns (image_scan_status, image_scanned_at, image_scan_json) ready on medical_facilities and medical_doctors.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Image audit migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
