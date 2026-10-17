<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_image_branding.php';

// Real filesystem renderer and DB apply, but synthetic uniquely owned uploads,
// an in-memory SQLite DB and isolated ledger/cache. No production DB/network.
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$json = static fn(mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$siteRoot = dirname(__DIR__);
$suffix = bin2hex(random_bytes(10));
$temporaryRoot = sys_get_temp_dir() . '/medreview-brand-integration-' . $suffix;
if (!mkdir($temporaryRoot, 0700)) throw new RuntimeException('Cannot create isolated integration state.');
$cacheDir = $temporaryRoot . '/cache';
$previousCacheDir = getenv('MEDICAL_SEARCH_CACHE_DIR');
$previousErrorLog = ini_get('error_log');
putenv('MEDICAL_SEARCH_CACHE_DIR=' . $cacheDir);
ini_set('error_log', $temporaryRoot . '/expected-errors.log');
$sourceDirectoryUrl = '/uploads/library/co-so-y-te/facility-test-' . $suffix;
$sourceDir = $siteRoot . $sourceDirectoryUrl;
$outputDir = $siteRoot . '/uploads/library/medreview-branded';
$outputDirExisted = is_dir($outputDir);
$createdDirectories = [];
$createdSources = [];
$legacyPath = null;
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$stateFor = static fn(string $name): string => $temporaryRoot . '/state-' . $name;
$jobFor = static fn(string $name, string $url): array => medical_image_brand_store(static fn(array $state): array => $state['jobs'][hash('sha256', $url)], false, $stateFor($name));
$run = static fn(string $name, ?callable $apply = null): array => medical_image_brand_operation(static fn(): array => medical_image_brand_run($pdo, 'all', $stateFor($name), null, $apply), $stateFor($name));
$scanStart = static fn(string $name): array => medical_image_brand_operation(static fn(): array => medical_image_brand_scan_start($stateFor($name)), $stateFor($name));
$snapshot = static function (string $path): array {
    clearstatcache(true, $path); $stat = stat($path);
    return ['hash' => hash_file('sha256', $path), 'ino' => $stat['ino'], 'mtime' => $stat['mtime'], 'size' => $stat['size']];
};

try {
    $assert(!file_exists($sourceDir) && !is_link($sourceDir), 'Synthetic upload directory belongs only to this run');
    foreach ([$siteRoot . '/uploads', $siteRoot . '/uploads/library', $siteRoot . '/uploads/library/co-so-y-te', $sourceDir] as $directory) {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755)) throw new RuntimeException('Cannot create fixture directory.');
            $createdDirectories[] = $directory;
        }
        $assert(!is_link($directory), 'Fixture ancestor is a plain local directory');
    }
    $makeSource = static function (string $name) use ($sourceDirectoryUrl, $siteRoot, &$createdSources): array {
        $url = $sourceDirectoryUrl . '/' . $name . '.png'; $path = $siteRoot . $url;
        $image = imagecreatetruecolor(640, 420);
        for ($y = 0; $y < 420; $y++) {
            imageline($image, 0, $y, 639, $y, imagecolorallocate($image, 195 - (int) ($y / 6), 220 - (int) ($y / 12), 240));
        }
        imagefilledellipse($image, 220, 270, 230, 230, imagecolorallocate($image, 120, 170, 200));
        if (!imagepng($image, $path)) throw new RuntimeException('Cannot write synthetic source.');
        imagedestroy($image); $createdSources[] = $path;
        return [$url, $path, medical_image_brand_source_identity($url)];
    };
    foreach (['medical_facilities', 'medical_doctors'] as $table) $pdo->exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT, gallery_json TEXT, content TEXT, full_json TEXT)');
    $pdo->exec('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
    $pdo->exec('CREATE TABLE synthetic_audit (message TEXT)');
    $unrelatedUrl = '/uploads/library/medreview-branded/' . str_repeat('a', 24) . '-' . str_repeat('b', 16) . '.png';
    $seedRows = static function (int $facilityId, int $doctorId, string $sourceUrl, string $imageUrl) use ($pdo, $json, $unrelatedUrl): array {
        $caption = 'Giữ nguyên chú thích: ' . $sourceUrl . ' / ' . $imageUrl;
        $alt = 'Ảnh mô phỏng, không phải ảnh thật: ' . $imageUrl;
        $gallery = [$imageUrl, ['url' => $imageUrl, 'caption' => $caption, 'alt' => $alt, 'source' => $sourceUrl], ['url' => $unrelatedUrl, 'caption' => 'Unrelated']];
        $content = '<figure><img src="' . $imageUrl . '" alt="' . $alt . '"><figcaption>' . $caption . '</figcaption></figure><a href="' . $imageUrl . '">Evidence</a>';
        $historical = $json(['original_image' => $sourceUrl, 'historical_output' => $imageUrl, 'caption' => $caption]); $rows = [];
        foreach ([['medical_facilities', $facilityId, 'facility'], ['medical_doctors', $doctorId, 'doctor']] as [$table, $id, $type]) {
            $pdo->prepare('INSERT INTO ' . $table . ' (id,name,image_url,gallery_json,content,full_json) VALUES (?,?,?,?,?,?)')->execute([$id, 'Synthetic ' . $type, $imageUrl, $json($gallery), $content, $historical]);
            $rows[$table] = $pdo->query('SELECT * FROM ' . $table . ' WHERE id=' . $id)->fetch();
        }
        return $rows;
    };
    $rowsMatch = static function (array $initial, string $beforeUrl, string $afterUrl, string $stage) use ($assert, $pdo, $json): void {
        foreach ($initial as $table => $before) {
            $expected = $before;
            if ($beforeUrl !== $afterUrl) {
                $expected['image_url'] = $afterUrl;
                $gallery = json_decode($before['gallery_json'], true, 512, JSON_THROW_ON_ERROR); $gallery[0] = $afterUrl; $gallery[1]['url'] = $afterUrl;
                $expected['gallery_json'] = $json($gallery);
                $expected['content'] = str_replace('src="' . $beforeUrl . '"', 'src="' . $afterUrl . '"', $before['content']);
            }
            $current = $pdo->query('SELECT * FROM ' . $table . ' WHERE id=' . $before['id'])->fetch();
            $assert($current === $expected, $stage . ': ' . $table . ' only image URLs affected; captions/alt/evidence/unrelated/history preserved');
        }
    };
    $addJob = static function (string $name, array $source, int $facilityId, int $doctorId) use ($stateFor): void {
        medical_image_brand_store(static function (array &$state) use ($source, $facilityId, $doctorId): void {
            medical_image_brand_add_source($state, $source, ['type' => 'facility', 'id' => $facilityId, 'name' => 'Synthetic facility'], true, 'facility');
            medical_image_brand_add_source($state, $source, ['type' => 'doctor', 'id' => $doctorId, 'name' => 'Synthetic doctor']);
        }, true, $stateFor($name));
    };

    [$normalUrl, $normalPath, $normalSource] = $makeSource('normal'); $normalBefore = $snapshot($normalPath);
    $normalRows = $seedRows(11, 22, $normalUrl, $normalUrl); $addJob('normal', $normalSource, 11, 22);
    $summary = medical_image_brand_status('all', 'all', 1, $stateFor('normal'))['summary'];
    $assert($summary['total'] === 1 && $summary['pending'] === 1 && count($jobFor('normal', $normalUrl)['refs']) === 2, 'Two rows share exactly one normal render job');
    $result = $run('normal'); $normalDone = $jobFor('normal', $normalUrl); $normalAfter = $snapshot($normalPath);
    $assert($result['processed'] === 1 && empty($result['failed']) && $normalDone['status'] === 'done', 'Default in-place pipeline completes');
    $assert($normalDone['mode'] === 'in_place' && $normalDone['output_url'] === $normalUrl && medical_image_brand_output_path($normalUrl) === realpath($normalPath), 'Original URL, filename and directory retained');
    $assert($normalDone['source_hash'] === $normalBefore['hash'] && $normalAfter['hash'] !== $normalBefore['hash'], 'Source bytes replaced, initial hash journalled');
    $assert($normalDone['output_hash'] === $normalAfter['hash'] && $normalDone['width'] === 640 && $normalDone['height'] === 420, 'Metadata matches overwritten image');
    $assert($normalDone['changes'] === ['updated' => 0, 'skipped' => 0], 'Same-URL branding bypasses profile DB reads and updates');
    $rowsMatch($normalRows, $normalUrl, $normalUrl, 'Normal overwrite');
    $assert(!is_file($cacheDir . '/medical-search-index-v1.invalidated'), 'Same-URL branding keeps search cache fresh to avoid indirect database rebuilds');
    $assert((glob($outputDir . '/' . substr($normalSource['key'], 0, 24) . '-*') ?: []) === [], 'Normal job creates no old-style copies');
    $assert(!is_dir($stateFor('normal') . '/originals'), 'Normal job creates no original backup directory');
    $scanStart('normal'); $addJob('normal', $normalSource, 11, 22);
    $assert($jobFor('normal', $normalUrl)['status'] === 'done', 'Rescan and repeated live DB references preserve done status');
    $assert($run('normal')['processed'] === 0 && $snapshot($normalPath) === $normalAfter, 'Rescan never re-encodes or double-brands unchanged completed bytes');

    // Real prepare overwrites before injected DB apply fails. A retry must use
    // the pre-publish hash journal and not composite a second logo.
    [$failureUrl, $failurePath, $failureSource] = $makeSource('apply-failure'); $failureBefore = $snapshot($failurePath);
    $failureRows = $seedRows(33, 44, $failureUrl, $failureUrl); $addJob('failure', $failureSource, 33, 44);
    $result = $run('failure', static function (PDO $database): never {
        $database->exec("INSERT INTO synthetic_audit (message) VALUES ('must roll back')");
        throw new RuntimeException('Synthetic post-overwrite DB apply failure.');
    });
    $failed = $jobFor('failure', $failureUrl); $failurePrepared = $snapshot($failurePath);
    $assert(($result['failed'] ?? false) === true && $failed['status'] === 'failed' && !$pdo->inTransaction(), 'DB failure durably records failed job and closes transaction');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM synthetic_audit')->fetchColumn() === 0, 'Injected apply transaction rolls back');
    $assert($failurePrepared['hash'] !== $failureBefore['hash'] && $failed['mode'] === 'in_place' && $failed['output_hash'] === $failurePrepared['hash'], 'Pre-publish journal survives post-overwrite DB failure');
    $rowsMatch($failureRows, $failureUrl, $failureUrl, 'Post-overwrite apply rollback');
    $assert(medical_image_brand_retry_failed('all', $stateFor('failure')) === 1, 'Failed in-place job requeued exactly once');
    $result = $run('failure');
    $assert(empty($result['failed']) && $jobFor('failure', $failureUrl)['status'] === 'done', 'Default retry commits after injected failure');
    $assert($snapshot($failurePath) === $failurePrepared, 'Retry reuses exact in-place bytes, inode, size and mtime');
    $rowsMatch($failureRows, $failureUrl, $failureUrl, 'Post-overwrite retry');

    // Migrate legacy copied URLs back to the original exact pathname. Keep the
    // old copy itself; broad file deletion is intentionally not part of this.
    [$legacySourceUrl, $legacySourcePath, $legacySource] = $makeSource('legacy-original'); $legacySourceBefore = $snapshot($legacySourcePath);
    if (!is_dir($outputDir) && !mkdir($outputDir, 0755)) throw new RuntimeException('Cannot create legacy fixture directory.');
    $assert(!is_link($outputDir), 'Legacy fixture output directory is plain local');
    $legacyUrl = '/uploads/library/medreview-branded/' . substr($legacySource['key'], 0, 24) . '-' . bin2hex(random_bytes(8)) . '.png';
    $legacyPath = $siteRoot . $legacyUrl; $legacyRender = medical_image_brand_render($legacySourcePath, $legacyPath); $legacyCopyBefore = $snapshot($legacyPath);
    $legacyRows = $seedRows(55, 66, $legacySourceUrl, $legacyUrl); $addJob('legacy', $legacySource, 55, 66);
    medical_image_brand_store(static function (array &$state) use ($legacySource, $legacyUrl, $legacyRender): void {
        $job =& $state['jobs'][$legacySource['key']]; $job['status'] = 'done'; $job['output_url'] = $legacyUrl;
        $job['output_hash'] = $legacyRender['hash']; $job['source_hash'] = $legacyRender['source_hash'];
    }, true, $stateFor('legacy'));
    $scanStart('legacy'); $assert($jobFor('legacy', $legacySourceUrl)['status'] === 'pending', 'Legacy done-copy job requeues for in-place migration');
    $pdo->exec("CREATE TRIGGER brand_fixture_abort BEFORE UPDATE ON medical_doctors WHEN OLD.id=66 BEGIN SELECT RAISE(ABORT, 'Synthetic second-row failure'); END");
    $result = $run('legacy'); $legacyFailed = $jobFor('legacy', $legacySourceUrl); $legacyPrepared = $snapshot($legacySourcePath);
    $assert(($result['failed'] ?? false) === true && $legacyFailed['status'] === 'failed' && !$pdo->inTransaction(), 'Second-row trigger rolls back exact-old-URL migration');
    $assert($legacyPrepared['hash'] !== $legacySourceBefore['hash'] && $legacyFailed['output_url'] === $legacySourceUrl && $legacyFailed['mode'] === 'in_place', 'Legacy original now branded once at original path');
    $assert($legacyFailed['output_hash'] === $legacyPrepared['hash'] && ($legacyFailed['replace_output_urls'] ?? null) === [$legacyUrl], 'Failure retains output hash and exact prior URL journal');
    $rowsMatch($legacyRows, $legacyUrl, $legacyUrl, 'Legacy second-row rollback');
    $assert($snapshot($legacyPath) === $legacyCopyBefore, 'Legacy copy preserved after failure');
    $pdo->exec('DROP TRIGGER brand_fixture_abort');
    $assert(medical_image_brand_retry_failed('all', $stateFor('legacy')) === 1, 'Legacy failure requeues');
    $result = $run('legacy'); $legacyDone = $jobFor('legacy', $legacySourceUrl);
    $assert(empty($result['failed']) && $legacyDone['status'] === 'done' && $legacyDone['changes'] === ['updated' => 2, 'skipped' => 0], 'Legacy retry restores both exact old image references');
    $assert(is_file($cacheDir . '/medical-search-index-v1.invalidated'), 'Actual legacy URL changes still invalidate the search index');
    $assert($snapshot($legacySourcePath) === $legacyPrepared, 'Legacy retry never re-encodes already-overwritten original');
    $assert(!isset($legacyDone['replace_output_urls']), 'Prior URL journal clears only after successful transaction');
    $rowsMatch($legacyRows, $legacyUrl, $legacySourceUrl, 'Legacy migration committed');
    $assert($snapshot($legacyPath) === $legacyCopyBefore, 'Legacy copy stays unchanged after migration');
    $scanStart('legacy'); $addJob('legacy', $legacySource, 55, 66);
    $assert($run('legacy')['processed'] === 0 && $snapshot($legacySourcePath) === $legacyPrepared, 'Completed legacy migration stays idempotent on rescan');

    // A persisted journal is only an expectation until the atomic rename. If
    // journalling or the request fails before publication, retry still renders.
    [$beforePublishUrl, $beforePublishPath, $beforePublishSource] = $makeSource('before-publish-crash');
    $beforePublishInitial = $snapshot($beforePublishPath);
    $seedRows(77, 88, $beforePublishUrl, $beforePublishUrl); $addJob('before-publish', $beforePublishSource, 77, 88);
    $journalBeforePublish = [];
    try {
        medical_image_brand_operation(static function () use ($jobFor, $stateFor, $beforePublishUrl, &$journalBeforePublish): void {
            medical_image_brand_prepare($jobFor('before-publish', $beforePublishUrl), static function (array $prepared) use ($stateFor, $beforePublishUrl, &$journalBeforePublish): void {
                $journalBeforePublish = $prepared;
                medical_image_brand_store(static function (array &$state) use ($prepared, $beforePublishUrl): void {
                    $key = hash('sha256', $beforePublishUrl);
                    $state['jobs'][$key] = array_replace($state['jobs'][$key], $prepared, ['status' => 'failed']);
                }, true, $stateFor('before-publish'));
                throw new RuntimeException('Synthetic lost request before atomic rename.');
            });
        }, $stateFor('before-publish'));
        $assert(false, 'Pre-publication synthetic crash throws');
    } catch (RuntimeException $error) {
        $assert($error->getMessage() === 'Synthetic lost request before atomic rename.', 'Pre-publication synthetic crash happens after journalling');
    }
    $assert($snapshot($beforePublishPath) === $beforePublishInitial, 'Crash before rename leaves original bytes and inode unchanged');
    $beforePublishJob = $jobFor('before-publish', $beforePublishUrl);
    $assert($journalBeforePublish !== [] && $beforePublishJob['mode'] === 'in_place' && $beforePublishJob['output_hash'] !== $beforePublishInitial['hash'], 'Pre-publish ledger contains intended but not yet applied hash');
    $assert(medical_image_brand_retry_failed('all', $stateFor('before-publish')) === 1, 'Pre-publication failed job requeues');
    $result = $run('before-publish');
    $assert(empty($result['failed']) && $jobFor('before-publish', $beforePublishUrl)['status'] === 'done' && $snapshot($beforePublishPath)['hash'] === $journalBeforePublish['output_hash'], 'Retry brands exactly once when intended hash was not published');

    // Conversely a lost response after rename leaves the journal + branded
    // bytes. An explicit scan recovers processing and retry must not re-encode.
    [$afterPublishUrl, $afterPublishPath, $afterPublishSource] = $makeSource('after-publish-crash');
    $seedRows(99, 100, $afterPublishUrl, $afterPublishUrl); $addJob('after-publish', $afterPublishSource, 99, 100);
    medical_image_brand_operation(static function () use ($jobFor, $stateFor, $afterPublishUrl): void {
        medical_image_brand_prepare($jobFor('after-publish', $afterPublishUrl), static function (array $prepared) use ($stateFor, $afterPublishUrl): void {
            medical_image_brand_store(static function (array &$state) use ($prepared, $afterPublishUrl): void {
                $key = hash('sha256', $afterPublishUrl);
                $state['jobs'][$key] = array_replace($state['jobs'][$key], $prepared, ['status' => 'processing', 'processing_at' => time()]);
            }, true, $stateFor('after-publish'));
        });
    }, $stateFor('after-publish'));
    $afterPublishPrepared = $snapshot($afterPublishPath);
    $assert($jobFor('after-publish', $afterPublishUrl)['status'] === 'processing' && $jobFor('after-publish', $afterPublishUrl)['output_hash'] === $afterPublishPrepared['hash'], 'After-publication response loss leaves recoverable hash journal');
    $scanStart('after-publish');
    $assert($jobFor('after-publish', $afterPublishUrl)['status'] === 'pending', 'Scan recovers interrupted processing job');
    $result = $run('after-publish');
    $assert(empty($result['failed']) && $jobFor('after-publish', $afterPublishUrl)['status'] === 'done' && $snapshot($afterPublishPath) === $afterPublishPrepared, 'After-publication recovery reuses bytes, inode and mtime without double logo');

    $filesBeforeRemote = scandir($sourceDir); $remoteUrl = 'https://images.example.org/clinic/integration-' . $suffix . '.png';
    $remote = medical_image_brand_source_identity($remoteUrl);
    $assert(is_array($remote) && $remote['local_path'] === null, 'Remote syntactic URL admitted to inventory without fetch');
    $addJob('remote', $remote, 11, 22); $result = $run('remote'); $remoteFailed = $jobFor('remote', $remoteUrl);
    $assert(($result['failed'] ?? false) === true && $remoteFailed['status'] === 'failed' && str_contains($remoteFailed['error'], 'website bên ngoài'), 'Remote cannot be overwritten and fails explicitly before network');
    $assert($remoteFailed['output_url'] === null && !isset($remoteFailed['output_hash']) && scandir($sourceDir) === $filesBeforeRemote, 'Rejected remote creates no replacement file');
    $assert(!is_dir($stateFor('remote') . '/originals'), 'Rejected remote creates no backup');
    $assert((glob($outputDir . '/' . substr($legacySource['key'], 0, 24) . '-*.png') ?: []) === [$legacyPath], 'Legacy migration creates no additional old-style copies');
    $assert((glob($outputDir . '/' . substr($failureSource['key'], 0, 24) . '-*') ?: []) === [], 'Apply failure and retry create no old-style copies');
    $assert((glob($sourceDir . '/.medreview-brand-*') ?: []) === [], 'No staging files remain');
    echo "Image branding integration: {$checks} checks passed; SQLite memory DB, synthetic uploads and isolated state/cache only.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Exact files registered by this run only; never glob-delete real uploads,
    // existing copies, source originals, or production ledger/cache entries.
    if (is_string($legacyPath) && is_file($legacyPath) && !is_link($legacyPath)) unlink($legacyPath);
    foreach ($createdSources as $path) if (is_file($path) && !is_link($path)) unlink($path);
    if (!$outputDirExisted && is_dir($outputDir)) @rmdir($outputDir);
    foreach (array_reverse($createdDirectories) as $directory) if (is_dir($directory)) @rmdir($directory);
    $removeTemporary = static function (string $directory) use (&$removeTemporary): void {
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue; $path = $directory . '/' . $name;
            if (is_dir($path) && !is_link($path)) $removeTemporary($path); else unlink($path);
        }
        rmdir($directory);
    };
    $removeTemporary($temporaryRoot);
    $previousCacheDir === false ? putenv('MEDICAL_SEARCH_CACHE_DIR') : putenv('MEDICAL_SEARCH_CACHE_DIR=' . $previousCacheDir);
    ini_set('error_log', (string) $previousErrorLog);
}
