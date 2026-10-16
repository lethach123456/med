<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_image_branding.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
$root = sys_get_temp_dir() . '/medreview-brand-jobs-' . bin2hex(random_bytes(8));
$stateDir = $root . '/state';
mkdir($root, 0700);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = static fn(string $name): array => medical_image_brand_source_identity('/uploads/library/co-so-y-te/facility-10/' . $name . '.jpg');
$ref = ['type' => 'facility', 'id' => 10, 'name' => 'Fixture'];
try {
    $empty = medical_image_brand_status('all', 'all', 1, $stateDir);
    $assert($empty['summary']['total'] === 0 && $empty['scan']['phase'] === 'idle', 'Read-only empty status');
    $assert(!is_dir($stateDir), 'Reading status never creates tracking files');
    medical_image_brand_operation(static function () use ($assert, $stateDir): void {
        try { medical_image_brand_operation(static function (): void {}, $stateDir); $assert(false, 'Concurrent operation accepted'); }
        catch (RuntimeException $error) { $assert($error->getCode() === 409, 'An overlapping operation is rejected without waiting'); }
    }, $stateDir);
    medical_image_brand_store(static function (array &$state) use ($identity, $ref): void {
        medical_image_brand_add_source($state, $identity('one'), $ref);
        medical_image_brand_add_source($state, $identity('one'), $ref, true, 'facility');
        medical_image_brand_add_source($state, $identity('two'), ['type' => 'doctor', 'id' => 20, 'name' => 'Fixture doctor']);
    }, true, $stateDir);
    $summary = medical_image_brand_status('all', 'all', 1, $stateDir)['summary'];
    $assert($summary['total'] === 2 && $summary['pending'] === 2, 'Inventory deduplicates DB and library occurrences');
    $assert(medical_image_brand_status('doctor', 'all', 1, $stateDir)['summary']['total'] === 1, 'Type filter preserves counters');
    $prepared = 0; $applied = 0;
    $prepare = static function (array $job) use (&$prepared): array {
        $prepared++;
        return ['output_url' => '/uploads/library/medreview-branded/' . substr($job['key'], 0, 24) . '-fixture.jpg',
            'source_hash' => str_repeat('a', 64), 'output_hash' => str_repeat('b', 64)];
    };
    $apply = static function (PDO $db, array $refs, string $key, string $url) use ($assert, &$applied): array {
        $applied++;
        $assert($db->inTransaction() && str_starts_with($url, '/uploads/library/medreview-branded/'), 'Reference writes share a transaction');
        return ['updated' => 0, 'skipped' => 0];
    };
    $result = medical_image_brand_operation(static fn(): array => medical_image_brand_run($pdo, 'facility', $stateDir, $prepare, $apply), $stateDir);
    $assert($result['processed'] === 1, 'One image per batch');
    $summary = medical_image_brand_status('all', 'all', 1, $stateDir)['summary'];
    $assert($summary['done'] === 1 && $summary['pending'] === 1, 'Completed image counted durably');
    $assert(medical_image_brand_run($pdo, 'facility', $stateDir, $prepare, $apply)['processed'] === 0 && $prepared === 1, 'A completed job is not branded twice');
    medical_image_brand_store(static function (array &$state) use ($identity): void {
        medical_image_brand_add_source($state, $identity('one'), null, true, 'facility');
    }, true, $stateDir);
    $assert(medical_image_brand_status('facility', 'all', 1, $stateDir)['summary']['pending'] === 1, 'Legacy copied jobs requeue to adopt in-place mode');
    medical_image_brand_store(static function (array &$state) use ($identity, $ref): void {
        medical_image_brand_add_source($state, $identity('one'), $ref);
    }, true, $stateDir);
    $restored = medical_image_brand_store(static fn(array $state): array => $state['jobs'][$identity('one')['key']], false, $stateDir);
    $assert($restored['status'] === 'pending' && $restored['output_url'] !== null, 'Restored source URL on a known profile queues existing output reuse');
    $fail = static function (PDO $db): array {
        $db->exec("INSERT INTO fixture_changes VALUES ('must rollback')");
        throw new InvalidArgumentException('Synthetic apply failure');
    };
    $oldOutput = '/uploads/library/medreview-branded/' . str_repeat('c', 24) . '-' . str_repeat('d', 16) . '.jpg';
    medical_image_brand_store(static function (array &$state) use ($identity, $oldOutput): void {
        $state['jobs'][$identity('one')['key']]['output_url'] = $oldOutput;
    }, true, $stateDir);
    $pdo->exec('CREATE TABLE fixture_changes (value TEXT)');
    $result = medical_image_brand_run($pdo, 'facility', $stateDir, $prepare, $fail);
    $assert(($result['failed'] ?? false) && !$pdo->inTransaction(), 'Failed application releases transaction and records failure');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM fixture_changes')->fetchColumn() === 0, 'Reference failure rolls back changes');
    $state = medical_image_brand_store(static fn(array $state): array => $state, false, $stateDir);
    $assert($state['jobs'][$identity('one')['key']]['output_url'] !== null, 'Prepared output survives DB failure for safe retry');
    $assert($state['jobs'][$identity('one')['key']]['replace_output_urls'] === [$oldOutput], 'Exact old output links survive failed regeneration');
    $assert(medical_image_brand_retry_failed('doctor', $stateDir) === 0, 'Retry respects selected entity type');
    $assert(medical_image_brand_retry_failed('facility', $stateDir) === 1, 'Retry only resets failed images');
    medical_image_brand_run($pdo, 'facility', $stateDir, $prepare, $apply);
    $assert(medical_image_brand_status('all', 'failed', 1, $stateDir)['items'] === [], 'A successful retry clears failure');
    $state = medical_image_brand_store(static fn(array $state): array => $state, false, $stateDir);
    $assert(!isset($state['jobs'][$identity('one')['key']]['replace_output_urls']), 'Old output recovery history clears only after successful commit');
    medical_image_brand_store(static function (array &$state) use ($identity): void {
        $job =& $state['jobs'][$identity('two')['key']];
        $job['status'] = 'processing'; $job['processing_at'] = time() - 600;
    }, true, $stateDir);
    $result = medical_image_brand_run($pdo, 'doctor', $stateDir, $prepare, $apply);
    $assert($result['processed'] === 1 && medical_image_brand_status('all', 'all', 1, $stateDir)['summary']['done'] === 2, 'An interrupted old processing claim can resume');
    medical_image_brand_scan_start($stateDir);
    $summary = medical_image_brand_status('all', 'all', 1, $stateDir)['summary'];
    $assert($summary['total'] === 2 && $summary['pending'] === 2, 'A scan retains history and requeues missing output files');
    medical_image_brand_store(static function (array &$state) use ($identity): void {
        $job =& $state['jobs'][$identity('two')['key']];
        $job['status'] = 'processing'; $job['processing_at'] = time();
    }, true, $stateDir);
    medical_image_brand_operation(static fn(): array => medical_image_brand_scan_start($stateDir), $stateDir);
    $assert(medical_image_brand_status('all', 'all', 1, $stateDir)['summary']['processing'] === 0, 'An explicit scan recovers interrupted jobs under the operation lock');
    $pdo->exec('CREATE TABLE medical_facilities (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT)');
    $pdo->exec('CREATE TABLE medical_doctors (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT)');
    $pdo->exec("INSERT INTO medical_facilities VALUES (30,'New fixture','/uploads/library/co-so-y-te/facility-30/new.jpg')");
    $scan = medical_image_brand_scan_step($pdo, $stateDir);
    $assert($scan['phase'] === 'doctor' && $scan['scanned'] === 1 && $scan['steps'] === 1, 'Keyset scan inventories installed facility image columns');
    $scan = medical_image_brand_scan_step($pdo, $stateDir);
    $assert($scan['phase'] === 'library', 'DB scan progresses without migration');
    $assert(medical_image_brand_status('all', 'all', 1, $stateDir)['summary']['total'] === 3, 'New source joins existing inventory');
    $keys = array_keys(medical_image_brand_status('all', 'all', 1, $stateDir)['items'][0]);
    $assert(!in_array('output_hash', $keys, true) && !in_array('source_hash', $keys, true), 'Public status excludes internal render metadata');
    $remote = medical_image_brand_source_identity('https://example.com/photo.jpg');
    try { medical_image_brand_prepare($remote); $assert(false, 'External origin unexpectedly accepted for overwrite'); }
    catch (InvalidArgumentException $error) { $assert(str_contains($error->getMessage(), 'website bên ngoài'), 'Remote files report that hosting cannot overwrite their origin'); }
    $assert(!is_dir($stateDir . '/originals') && !function_exists('medical_image_brand_preserve_original'), 'In-place implementation no longer creates original backups');
    file_put_contents($stateDir . '/state.json', '{invalid');
    try { medical_image_brand_status('all', 'all', 1, $stateDir); $assert(false, 'Corrupt ledger silently accepted'); }
    catch (JsonException) { $assert(true, 'Corrupt ledger cannot silently reset completed status'); }
    echo "Image branding jobs: {$checks} checks passed; SQLite and isolated tracking files only.\n";
} finally {
    foreach (glob($stateDir . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($stateDir)) rmdir($stateDir);
    rmdir($root);
}
