<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$arguments = array_slice($argv, 1);
if (count($arguments) !== 1 || !in_array($arguments[0], ['--check', '--apply'], true)) {
    echo "Usage: php scripts/migrate_facility_insights.php --check|--apply\n";
    echo "--check: read-only schema status. --apply: add only the missing facility_insights_json column.\n";
    exit($arguments === [] || $arguments === ['--help'] ? 0 : 2);
}
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/medical_facility_insights.php';
try {
    $pdo = db();
    if ($arguments[0] === '--apply') {
        $added = medreview_with_schema_migration(static fn(): bool => medical_facility_insights_migrate($pdo));
        echo $added ? "Added medical_facilities.facility_insights_json.\n" : "Facility insights column already exists. No changes.\n";
    }
    $ready = medical_facility_insights_has_column($pdo);
    echo 'medical_facilities.facility_insights_json: ' . ($ready ? 'ready' : 'missing') . "\n";
    exit($ready ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Facility insights migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
