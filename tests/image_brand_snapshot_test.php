<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_image_branding.php';

/** SQLite keeps these regression checks completely separate from the live DB. */
final class ImageBrandSnapshotPDO extends PDO
{
    public array $sql = [];
    public bool $forbidSql = false;
    public ?string $failOnSelect = null;
    public ?Closure $selectProbe = null;

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function record(string $sql): void
    {
        if ($this->forbidSql) throw new RuntimeException('Unexpected database access: ' . $sql);
        $this->sql[] = $sql;
        if (preg_match('/^SELECT\b/i', $sql)) {
            if ($this->selectProbe !== null) ($this->selectProbe)($sql);
            if ($this->failOnSelect !== null && str_contains($sql, $this->failOnSelect)) {
                throw new RuntimeException('Synthetic snapshot SELECT failure');
            }
        }
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->record($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->record($query);
        return parent::prepare($query, $options);
    }

    public function exec(string $statement): int|false
    {
        $this->record($statement);
        return parent::exec($statement);
    }

    public function beginTransaction(): bool { $this->record('BEGIN'); return parent::beginTransaction(); }
    public function commit(): bool { $this->record('COMMIT'); return parent::commit(); }
    public function rollBack(): bool { $this->record('ROLLBACK'); return parent::rollBack(); }
    public function inTransaction(): bool { $this->record('TRANSACTION STATUS'); return parent::inTransaction(); }
}

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($message);
};
$suffix = bin2hex(random_bytes(8));
$temporaryRoot = sys_get_temp_dir() . '/medreview-brand-snapshot-' . $suffix;
$stateDir = $temporaryRoot . '/inventory';
$legacyDir = $temporaryRoot . '/legacy';
$cacheDir = $temporaryRoot . '/search-cache';
$sourceUrl = '/uploads/library/branding-snapshot-test-' . $suffix;
$sourceDir = dirname(__DIR__) . $sourceUrl;
$createdPaths = [];
$createdDirectories = [];
$previousCacheDir = getenv('MEDICAL_SEARCH_CACHE_DIR');
$pdo = new ImageBrandSnapshotPDO();
$read = static fn(string $directory): array => medical_image_brand_store(static fn(array $state): array => $state, false, $directory);
$scan = static fn(string $directory): array => medical_image_brand_operation(static fn(): array => medical_image_brand_scan_snapshot_start($pdo, $directory), $directory);
$key = static fn(string $url): string => hash('sha256', $url);

try {
    if (!mkdir($temporaryRoot, 0700)) throw new RuntimeException('Cannot create isolated snapshot fixture.');
    foreach ([dirname(__DIR__) . '/uploads', dirname(__DIR__) . '/uploads/library', $sourceDir] as $directory) {
        $assert(!is_link($directory), 'Fixture paths must not traverse symlinks');
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755)) throw new RuntimeException('Cannot create synthetic upload directory.');
            $createdDirectories[] = $directory;
        }
    }
    $makeSource = static function (string $name) use ($sourceDir, $sourceUrl, &$createdPaths): string {
        $path = $sourceDir . '/' . $name . '.png';
        if (file_exists($path)) throw new RuntimeException('Synthetic source must be new.');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8AAAAABJRU5ErkJggg==', true);
        if (!is_string($png) || file_put_contents($path, $png) !== strlen($png)) throw new RuntimeException('Cannot write synthetic image.');
        $createdPaths[] = $path;
        return $sourceUrl . '/' . $name . '.png';
    };
    $sharedUrl = $makeSource('shared');
    $otherUrl = $makeSource('other');
    $removedUrl = $sourceUrl . '/removed.png';
    $excludedUrl = $sourceUrl . '/configured-site-icon.png';
    foreach (['medical_facilities', 'medical_doctors'] as $table) {
        $pdo->exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT, gallery_json TEXT, content TEXT)');
    }
    $pdo->exec('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
    $pdo->prepare('INSERT INTO site_settings VALUES (?, ?)')->execute(['site_icon_href', $excludedUrl]);
    $pdo->prepare('INSERT INTO medical_facilities VALUES (?, ?, ?, ?, ?)')->execute([10, 'Current facility', $sharedUrl, json_encode([$otherUrl]), '']);
    $pdo->prepare('INSERT INTO medical_facilities VALUES (?, ?, ?, ?, ?)')->execute([11, 'Configured icon owner', $excludedUrl, '[]', '']);
    $pdo->prepare('INSERT INTO medical_doctors VALUES (?, ?, ?, ?, ?)')->execute([20, 'Current doctor', $sharedUrl, '[]', '']);
    medical_image_brand_store(static function (array &$state) use ($sharedUrl, $removedUrl): void {
        $shared = medical_image_brand_source_identity($sharedUrl);
        medical_image_brand_add_source($state, $shared, ['type' => 'facility', 'id' => 999, 'name' => 'Stale facility'], true, 'facility');
        medical_image_brand_add_source($state, $shared, ['type' => 'doctor', 'id' => 998, 'name' => 'Stale doctor']);
        $state['jobs'][$shared['key']] += ['mode' => 'in_place', 'output_hash' => hash_file('sha256', $shared['local_path'])];
        $state['jobs'][$shared['key']]['status'] = 'done';
        $state['jobs'][$shared['key']]['output_url'] = $sharedUrl;
        $removed = medical_image_brand_source_identity($removedUrl);
        medical_image_brand_add_source($state, $removed, ['type' => 'facility', 'id' => 777, 'name' => 'Deleted owner']);
        $state['jobs'][$removed['key']]['status'] = 'failed';
        $state['jobs'][$removed['key']]['output_hash'] = str_repeat('a', 64);
    }, true, $stateDir);

    $probeCount = 0;
    $pdo->selectProbe = static function (string $sql) use ($stateDir, $assert, &$probeCount): void {
        if (!preg_match('/\bFROM medical_(?:facilities|doctors)\b/', $sql)) return;
        $handle = fopen($stateDir . '/state.lock', 'r');
        $locked = $handle !== false && flock($handle, LOCK_SH | LOCK_NB);
        $assert($locked, 'Reading status remains possible while a full entity SELECT runs');
        if ($handle !== false) { if ($locked) flock($handle, LOCK_UN); fclose($handle); }
        $probeCount++;
    };
    $pdo->sql = [];
    $firstScan = $scan($stateDir);
    $state = $read($stateDir);
    $assert($firstScan['snapshot_version'] === 2 && $firstScan['phase'] === 'library' && $firstScan['scanned'] === 3, 'New snapshot inventories both entity tables and records its version');
    $assert($probeCount === 2, 'Both entity reads execute outside the exclusive state lock');
    foreach (['medical_facilities', 'medical_doctors'] as $table) {
        $selects = array_filter($pdo->sql, static fn(string $sql): bool => preg_match('/^SELECT\b.*\bFROM ' . $table . '\b/', $sql) === 1);
        $assert(count($selects) === 1, 'Snapshot performs exactly one data SELECT for ' . $table);
    }
    $assert(in_array($excludedUrl, $state['scan']['excluded_urls'], true) && !isset($state['jobs'][$key($excludedUrl)]), 'Configured site icon exclusions are persisted and respected');
    $shared = $state['jobs'][$key($sharedUrl)];
    $assert(array_column($shared['refs'], 'id') === [10, 20] && $shared['types'] === ['facility' => true, 'doctor' => true] && !$shared['library'], 'Fresh references replace stale profile IDs, types and library membership');
    $assert($shared['status'] === 'done' && $shared['output_hash'] === hash_file('sha256', dirname(__DIR__) . $sharedUrl), 'Unchanged in-place hash preserves completed branding');
    $removed = $state['jobs'][$key($removedUrl)];
    $assert($removed['active'] === false && $removed['refs'] === [] && $removed['types'] === [] && $removed['output_hash'] === str_repeat('a', 64), 'Deleted sources retain hashes/history but lose active references');
    $assert(medical_image_brand_status('all', 'all', 1, $stateDir)['summary']['total'] === 2, 'Deleted history is hidden from active totals and rows');

    medical_image_brand_store(static function (array &$state) use ($otherUrl, $key): void {
        $job =& $state['jobs'][$key($otherUrl)];
        $job['status'] = 'done'; $job['mode'] = 'in_place'; $job['output_url'] = $otherUrl;
        $job['output_hash'] = hash_file('sha256', dirname(__DIR__) . $otherUrl);
    }, true, $stateDir);

    $pdo->exec('DELETE FROM medical_facilities WHERE id = 10');
    $scan($stateDir);
    $state = $read($stateDir);
    $shared = $state['jobs'][$key($sharedUrl)];
    $assert(array_column($shared['refs'], 'id') === [20] && $shared['types'] === ['doctor' => true], 'A shared photo drops removed facility membership on the next snapshot');
    $assert($state['jobs'][$key($otherUrl)]['active'] === false, 'A photo removed from its last DB field becomes historical');
    $assert(medical_image_brand_status('facility', 'all', 1, $stateDir)['summary']['total'] === 0 && medical_image_brand_status('doctor', 'all', 1, $stateDir)['summary']['total'] === 1, 'Type filters use current DB ownership');
    $assert(medical_image_brand_retry_failed('all', $stateDir) === 0, 'Retry ignores failed historical photos');

    medical_image_brand_store(static function (array &$state) use ($sourceDir): void {
        $state['scan']['directories'] = [basename($sourceDir)]; $state['scan']['directory'] = null;
    }, true, $stateDir);
    $pdo->forbidSql = true;
    medical_image_brand_operation(static fn(): array => medical_image_brand_scan_step(null, $stateDir), $stateDir);
    $state = $read($stateDir);
    $other = $state['jobs'][$key($otherUrl)];
    $assert($other['active'] === true && $other['library'] && $other['refs'] === [], 'A photo still present in the library keeps no obsolete DB owners');
    $assert($other['status'] === 'done' && $other['output_hash'] === hash_file('sha256', dirname(__DIR__) . $otherUrl), 'An unchanged historical image reappears without double-branding');
    $pdo->forbidSql = false;
    $scan($stateDir);

    // Finish only this synthetic scan's empty filesystem queue. Do not inspect
    // or process any actual medical-library uploads during this test.
    medical_image_brand_store(static function (array &$state) use ($sharedUrl, $key): void {
        $state['scan']['directories'] = []; $state['scan']['directory'] = null;
        $state['jobs'][$key($sharedUrl)]['status'] = 'pending';
    }, true, $stateDir);
    $pdo->sql = []; $pdo->forbidSql = true;
    $complete = medical_image_brand_operation(static fn(): array => medical_image_brand_scan_step(null, $stateDir), $stateDir);
    $assert(medical_image_brand_scan_ready($complete) && medical_image_brand_status('all', 'all', 1, $stateDir)['scan']['ready'], 'Filesystem completion makes the current snapshot ready without DB queries');
    $assert(!medical_image_brand_scan_ready(['phase' => 'complete', 'snapshot_at' => gmdate('c'), 'excluded_urls' => []]), 'A completed legacy scan without snapshot version is not ready');
    putenv('MEDICAL_SEARCH_CACHE_DIR=' . $cacheDir);
    $prepareCount = 0;
    $prepare = static function (array $job) use (&$prepareCount): array {
        $prepareCount++;
        return ['mode' => 'in_place', 'output_url' => $job['url'], 'output_hash' => $job['output_hash']];
    };
    $result = medical_image_brand_operation(static fn(): array => medical_image_brand_run($pdo, 'doctor', $stateDir, $prepare), $stateDir);
    $assert($result['processed'] === 1 && empty($result['failed']) && $pdo->sql === [], 'Same-URL in-place run makes zero SQL calls and opens no transactions');
    $assert(!is_dir($cacheDir), 'Unchanged profile URLs do not invalidate the search cache');
    $assert(medical_image_brand_run($pdo, 'all', $stateDir, $prepare)['processed'] === 0 && $prepareCount === 1, 'Historical pending/failed photos cannot run after the active queue is complete');
    $assert($read($stateDir)['jobs'][$key($removedUrl)]['status'] === 'failed', 'Running and retrying active jobs preserve retired history');

    $pdo->forbidSql = false;
    $previousJson = file_get_contents($stateDir . '/state.json');
    $pdo->failOnSelect = 'FROM medical_doctors';
    try { $scan($stateDir); $assert(false, 'Synthetic snapshot failure unexpectedly succeeded'); }
    catch (RuntimeException $error) { $assert($error->getMessage() === 'Synthetic snapshot SELECT failure', 'A snapshot SELECT error is surfaced'); }
    $assert(file_get_contents($stateDir . '/state.json') === $previousJson, 'Failed snapshot leaves the complete previous JSON byte-for-byte intact');
    $pdo->failOnSelect = null; $pdo->selectProbe = null;

    $legacyA = '/uploads/library/medreview-branded/' . substr($key($sharedUrl), 0, 24) . '-' . str_repeat('b', 16) . '.png';
    $legacyB = '/uploads/library/medreview-branded/' . substr($key($otherUrl), 0, 24) . '-' . str_repeat('c', 16) . '.png';
    $unrelatedLegacy = '/uploads/library/medreview-branded/' . str_repeat('d', 24) . '-' . str_repeat('e', 16) . '.png';
    $pdo->exec('DELETE FROM medical_facilities');
    $pdo->exec('DELETE FROM medical_doctors');
    $pdo->prepare('INSERT INTO medical_facilities VALUES (?, ?, ?, ?, ?)')->execute([30, 'New legacy owner', $legacyA, '[]', '']);
    $pdo->prepare('INSERT INTO medical_doctors VALUES (?, ?, ?, ?, ?)')->execute([40, 'Other new legacy owner', $legacyB, '[]', '']);
    $pdo->prepare('INSERT INTO medical_facilities VALUES (?, ?, ?, ?, ?)')->execute([31, 'Untracked output owner', $unrelatedLegacy, '[]', '']);
    medical_image_brand_store(static function (array &$state) use ($sharedUrl, $otherUrl, $legacyA, $legacyB): void {
        foreach ([[$sharedUrl, $legacyA], [$otherUrl, $legacyB]] as $index => [$url, $legacy]) {
            $source = medical_image_brand_source_identity($url);
            medical_image_brand_add_source($state, $source, ['type' => 'facility', 'id' => 999, 'name' => 'Deleted legacy owner']);
            $job =& $state['jobs'][$source['key']];
            $job['status'] = 'done'; $job['mode'] = 'in_place';
            $job['output_hash'] = hash_file('sha256', $source['local_path']);
            $job['output_url'] = $index === 0 ? $legacy : $url;
            if ($index === 1) $job['legacy_output_urls'] = [$legacy];
            unset($job);
        }
    }, true, $legacyDir);
    $scan($legacyDir);
    $state = $read($legacyDir);
    $assert(array_column($state['jobs'][$key($sharedUrl)]['refs'], 'id') === [30] && $state['jobs'][$key($sharedUrl)]['replace_output_urls'] === [$legacyA], 'Saved legacy output_url maps exact current references back to its original photo');
    $assert(array_column($state['jobs'][$key($otherUrl)]['refs'], 'id') === [40] && $state['jobs'][$key($otherUrl)]['replace_output_urls'] === [$legacyB], 'Saved legacy_output_urls rebuild references after a previous migration');
    $assert($state['jobs'][$key($sharedUrl)]['status'] === 'pending' && $state['jobs'][$key($otherUrl)]['status'] === 'pending', 'Exact legacy references queue repair even when original in-place hashes match');
    $assert(count($state['jobs']) === 2 && !isset($state['jobs'][$key($unrelatedLegacy)]), 'Unregistered generated-image URLs are never guessed or adopted');
    $assert($state['jobs'][$key($sharedUrl)]['output_hash'] === hash_file('sha256', dirname(__DIR__) . $sharedUrl), 'Legacy reference rebuilding preserves the branded image hash');

    echo "Image branding snapshots: {$checks} checks passed; SQLite, synthetic files and isolated JSON only.\n";
} finally {
    putenv($previousCacheDir === false ? 'MEDICAL_SEARCH_CACHE_DIR' : 'MEDICAL_SEARCH_CACHE_DIR=' . $previousCacheDir);
    foreach ($createdPaths as $path) if (is_file($path)) unlink($path);
    foreach (array_reverse($createdDirectories) as $directory) if (is_dir($directory)) rmdir($directory);
    foreach ([$stateDir, $legacyDir, $cacheDir] as $directory) {
        foreach (glob($directory . '/*') ?: [] as $path) if (is_file($path)) unlink($path);
        if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($temporaryRoot)) rmdir($temporaryRoot);
}
