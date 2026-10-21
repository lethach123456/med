<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$arguments = array_slice($argv, 1);
if (count($arguments) !== 1 || !in_array($arguments[0], ['--check', '--apply'], true)) {
    echo "Usage: php scripts/migrate_patient_images.php --check|--apply\n";
    echo "--check: read-only schema status. --apply: add only missing private patient image/notes columns.\n";
    exit($arguments === [] || $arguments === ['--help'] ? 0 : 2);
}

// Load DB definitions only after an explicit valid action; usage never connects.
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/medical_patient_images.php';

try {
    $pdo = db();
    if ($arguments[0] === '--apply') {
        $added = medreview_with_schema_migration(static fn(): array => medical_patient_images_migrate($pdo));
        echo $added === [] ? "Patient image/notes columns already exist. No changes.\n" : 'Added missing patient image/notes columns to: ' . implode(', ', $added) . ".\n";
    }
    $ready = true;
    foreach (['facility', 'doctor'] as $entity) {
        foreach (['patient_images_json', 'patient_images_notes'] as $column) {
            $exists = medical_patient_images_has_column($pdo, $entity, $column);
            echo medical_patient_images_table($entity) . '.' . $column . ': ' . ($exists ? 'ready' : 'missing') . "\n";
            $ready = $ready && $exists;
        }
    }
    exit($ready ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Patient image migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
