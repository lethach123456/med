<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_image_branding.php';

// Exercise the real prepare/render/apply pipeline with an in-memory database.
// The only upload written is a newly generated, uniquely named synthetic image.
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$siteRoot = dirname(__DIR__);
$suffix = bin2hex(random_bytes(10));
$temporaryRoot = sys_get_temp_dir() . '/medreview-brand-integration-' . $suffix;
if (!mkdir($temporaryRoot, 0700)) throw new RuntimeException('Cannot create isolated integration state.');
$stateDir = $temporaryRoot . '/state';
$cacheDir = $temporaryRoot . '/cache';
$previousCacheDir = getenv('MEDICAL_SEARCH_CACHE_DIR');
$previousErrorLog = ini_get('error_log');
putenv('MEDICAL_SEARCH_CACHE_DIR=' . $cacheDir);
ini_set('error_log', $temporaryRoot . '/expected-errors.log');
$sourceUrl = '/uploads/library/co-so-y-te/facility-test-' . $suffix . '/synthetic.png';
$sourcePath = $siteRoot . $sourceUrl;
$sourceDir = dirname($sourcePath);
$outputDir = $siteRoot . '/uploads/library/medreview-branded';
$outputDirExisted = is_dir($outputDir);
$createdDirectories = [];
$outputPrefix = substr(hash('sha256', $sourceUrl), 0, 24);
$ownedOutputs = static fn(): array => glob($outputDir . '/' . $outputPrefix . '-*.png') ?: [];
$baselineOutputs = $ownedOutputs();
$sourceCreated = false;
$readJob = static fn(): array => medical_image_brand_store(
    static fn(array $state): array => $state['jobs'][hash('sha256', $sourceUrl)], false, $stateDir
);
$run = static fn(PDO $pdo): array => medical_image_brand_operation(
    static fn(): array => medical_image_brand_run($pdo, 'all', $stateDir), $stateDir
);
$scanStart = static fn(): array => medical_image_brand_operation(
    static fn(): array => medical_image_brand_scan_start($stateDir), $stateDir
);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

try {
    $assert(!is_link($outputDir), 'Output directory is not a symlink');
    $assert($baselineOutputs === [], 'Unique source starts without any owned branded output');
    $assert(!file_exists($sourceDir) && !is_link($sourceDir), 'Synthetic source directory is newly owned by this test');
    foreach ([$siteRoot . '/uploads', $siteRoot . '/uploads/library', $siteRoot . '/uploads/library/co-so-y-te', $sourceDir] as $directory) {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755)) throw new RuntimeException('Cannot create synthetic fixture directory.');
            $createdDirectories[] = $directory;
        }
        $assert(!is_link($directory), 'Fixture ancestor is a plain local directory');
    }
    $image = imagecreatetruecolor(640, 420);
    for ($y = 0; $y < 420; $y++) {
        $color = imagecolorallocate($image, 195 - (int) ($y / 6), 220 - (int) ($y / 12), 240);
        imageline($image, 0, $y, 639, $y, $color);
    }
    imagefilledellipse($image, 220, 270, 230, 230, imagecolorallocate($image, 120, 170, 200));
    if (!imagepng($image, $sourcePath)) throw new RuntimeException('Cannot write generated synthetic source.');
    $sourceCreated = true;
    imagedestroy($image);
    $originalHash = hash_file('sha256', $sourcePath);
    $source = medical_image_brand_source_identity($sourceUrl);
    $assert(is_array($source) && $source['local_path'] === realpath($sourcePath), 'Generated fixture resolves through the production source policy');

    $pdo->exec('CREATE TABLE medical_facilities (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT, gallery_json TEXT, content TEXT, full_json TEXT)');
    $pdo->exec('CREATE TABLE medical_doctors (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT, gallery_json TEXT, content TEXT, full_json TEXT)');
    $pdo->exec('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
    $unrelatedUrl = '/uploads/library/medreview-branded/' . str_repeat('a', 24) . '-' . str_repeat('b', 16) . '.png';
    $caption = 'Giữ nguyên chú thích và đường dẫn: ' . $sourceUrl;
    $alt = 'Ảnh mô phỏng, không phải ảnh cơ sở thật';
    $gallery = [$sourceUrl, ['url' => $sourceUrl, 'caption' => $caption, 'alt' => $alt, 'source' => $sourceUrl], ['url' => $unrelatedUrl, 'caption' => 'Unrelated image']];
    $content = '<figure><img src="' . $sourceUrl . '" alt="' . $alt . '"><figcaption>' . $caption . '</figcaption></figure><a href="' . $sourceUrl . '">Original evidence</a>';
    $historical = json_encode(['original_image' => $sourceUrl, 'caption' => $caption], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    foreach ([['medical_facilities', 11, 'Synthetic facility'], ['medical_doctors', 22, 'Synthetic doctor']] as [$table, $id, $name]) {
        $pdo->prepare('INSERT INTO ' . $table . ' (id,name,image_url,gallery_json,content,full_json) VALUES (?,?,?,?,?,?)')
            ->execute([$id, $name, $sourceUrl, json_encode($gallery, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $content, $historical]);
    }
    $assertRows = static function (string $outputUrl, string $stage) use ($assert, $pdo, $sourceUrl, $caption, $alt, $unrelatedUrl, $content, $historical): void {
        foreach ([['medical_facilities', 11, 'facility'], ['medical_doctors', 22, 'doctor']] as [$table, $id, $type]) {
            $row = $pdo->query('SELECT * FROM ' . $table . ' WHERE id=' . $id)->fetch();
            $savedGallery = json_decode($row['gallery_json'], true, 512, JSON_THROW_ON_ERROR);
            $assert($row['image_url'] === $outputUrl, $stage . ': ' . $type . ' primary reference points to expected output');
            $assert($savedGallery[0] === $outputUrl && $savedGallery[1]['url'] === $outputUrl, $stage . ': ' . $type . ' string and rich gallery references patched');
            $assert($savedGallery[1]['caption'] === $caption && $savedGallery[1]['alt'] === $alt && $savedGallery[1]['source'] === $sourceUrl, $stage . ': ' . $type . ' captions, alt and provenance preserved');
            $assert($savedGallery[2]['url'] === $unrelatedUrl && $row['full_json'] === $historical, $stage . ': ' . $type . ' unrelated and historical references preserved');
            $expectedContent = str_replace('src="' . $sourceUrl . '"', 'src="' . $outputUrl . '"', $content);
            $assert($row['content'] === $expectedContent, $stage . ': ' . $type . ' only img src changed, surrounding markup unchanged');
            $assert(medical_image_brand_sources_from_row($row, $type) === [], $stage . ': ' . $type . ' branded URLs are not eligible for another logo');
        }
    };

    medical_image_brand_store(static function (array &$state) use ($source): void {
        medical_image_brand_add_source($state, $source, ['type' => 'facility', 'id' => 11, 'name' => 'Synthetic facility'], true, 'facility');
        medical_image_brand_add_source($state, $source, ['type' => 'doctor', 'id' => 22, 'name' => 'Synthetic doctor']);
    }, true, $stateDir);
    $summary = medical_image_brand_status('all', 'all', 1, $stateDir)['summary'];
    $assert($summary['total'] === 1 && $summary['pending'] === 1 && count($readJob()['refs']) === 2, 'Two current rows share one deduplicated render job');
    $result = $run($pdo);
    $first = $readJob();
    $firstPath = medical_image_brand_output_path((string) $first['output_url']);
    $assert($result['processed'] === 1 && empty($result['failed']) && $first['status'] === 'done', 'Default prepare/render/apply completes the shared job');
    $assert(is_string($firstPath) && is_file($firstPath) && count($ownedOutputs()) === 1, 'Exactly one new branded file is published');
    $assert($first['source_hash'] === $originalHash && hash_file('sha256', $sourcePath) === $originalHash, 'Original source bytes remain unchanged');
    $assert($first['output_hash'] === hash_file('sha256', $firstPath) && $first['width'] === 640 && $first['height'] === 420, 'Real renderer metadata matches the prepared output');
    $assert($first['changes'] === ['updated' => 2, 'skipped' => 0], 'Both shared references commit in one default apply');
    $assertRows($first['output_url'], 'Initial branding');
    $assert(is_file($cacheDir . '/medical-search-index-v1.invalidated'), 'Successful default run invalidates only the isolated search cache');
    $result = $run($pdo);
    $assert($result['processed'] === 0 && $readJob()['output_url'] === $first['output_url'] && count($ownedOutputs()) === 1, 'Next run does not render or double-brand a completed image');

    // A missing immutable result must repair the exact old URL still in the DB.
    $assert(unlink($firstPath), 'Remove only the first generated result to simulate a missing output');
    clearstatcache(true, $firstPath);
    $scanStart();
    $assert($readJob()['status'] === 'pending' && $readJob()['output_url'] === $first['output_url'], 'Explicit scan requeues missing output while retaining its old URL');
    $result = $run($pdo);
    $second = $readJob();
    $secondPath = medical_image_brand_output_path((string) $second['output_url']);
    $assert(empty($result['failed']) && $second['status'] === 'done' && $second['output_url'] !== $first['output_url'], 'Default recovery publishes a new immutable output URL');
    $assert(is_string($secondPath) && is_file($secondPath) && count($ownedOutputs()) === 1, 'Missing-output recovery leaves exactly one extant owned result');
    $assert(!isset($second['replace_output_urls']), 'Successful recovery clears the exact-old-URL ledger after commit');
    $assertRows($second['output_url'], 'Missing-output recovery');

    // Fail on the second row: the first row's update must roll back too, while
    // the valid prepared output and the exact old URL survive for a safe retry.
    $assert(unlink($secondPath), 'Remove only the second generated result for failure recovery');
    clearstatcache(true, $secondPath);
    $scanStart();
    $pdo->exec("CREATE TRIGGER brand_fixture_abort BEFORE UPDATE ON medical_doctors BEGIN SELECT RAISE(ABORT, 'Synthetic image-brand apply failure'); END");
    $result = $run($pdo);
    $failed = $readJob();
    $preparedPath = medical_image_brand_output_path((string) $failed['output_url']);
    $assert(($result['failed'] ?? false) === true && $failed['status'] === 'failed' && !$pdo->inTransaction(), 'SQLite abort rolls back and durably marks the default run failed');
    $assert($failed['output_url'] !== $second['output_url'] && is_string($preparedPath) && is_file($preparedPath), 'DB failure retains the newly prepared branded result');
    $assert(($failed['replace_output_urls'] ?? null) === [$second['output_url']], 'Failure retains the exact missing output URL for retry repair');
    $assert(count($ownedOutputs()) === 1 && $failed['output_hash'] === hash_file('sha256', $preparedPath), 'Failed apply leaves one valid, fingerprinted prepared output');
    $assertRows($second['output_url'], 'Aborted transaction rollback');

    $preparedHash = hash_file('sha256', $preparedPath);
    $preparedStat = stat($preparedPath);
    $pdo->exec('DROP TRIGGER brand_fixture_abort');
    $assert(medical_image_brand_retry_failed('all', $stateDir) === 1, 'Retry requeues exactly the failed shared job');
    $assert($readJob()['output_url'] === $failed['output_url'] && ($readJob()['replace_output_urls'] ?? null) === [$second['output_url']], 'Retry keeps both prepared output and old reference recovery ledger');
    $result = $run($pdo);
    $retried = $readJob();
    clearstatcache(true, $preparedPath);
    $afterStat = stat($preparedPath);
    $assert(empty($result['failed']) && $retried['status'] === 'done' && $retried['output_url'] === $failed['output_url'], 'Successful retry reuses the same prepared output URL');
    $assert(hash_file('sha256', $preparedPath) === $preparedHash && $afterStat['ino'] === $preparedStat['ino'] && $afterStat['mtime'] === $preparedStat['mtime'] && count($ownedOutputs()) === 1, 'Retry does not re-encode or replace the prepared file');
    $assert(!isset($retried['replace_output_urls']), 'Recovery ledger clears only after the retry transaction succeeds');
    $assertRows($failed['output_url'], 'Prepared-output retry');
    $assert(hash_file('sha256', $sourcePath) === $originalHash, 'Source is unchanged across render, regeneration, rollback and retry');
    $assert($run($pdo)['processed'] === 0, 'Final completed retry remains idempotent');
    $assert(count($ownedOutputs()) === 1, 'Final retry keeps exactly one extant result for this unique source');
    echo "Image branding integration: {$checks} checks passed; SQLite memory DB, generated source and isolated state/cache only.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // This prefix belongs to the cryptographically unique synthetic source URL;
    // do not delete, glob-unlink or traverse any other upload or branded output.
    foreach (array_diff($ownedOutputs(), $baselineOutputs) as $path) if (is_file($path) && !is_link($path)) unlink($path);
    if ($sourceCreated && is_file($sourcePath) && !is_link($sourcePath)) unlink($sourcePath);
    if (!$outputDirExisted && is_dir($outputDir)) @rmdir($outputDir); // Only if still empty.
    foreach (array_reverse($createdDirectories) as $directory) if (is_dir($directory)) @rmdir($directory);
    $removeTemporary = static function (string $directory) use (&$removeTemporary): void {
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $directory . '/' . $name;
            if (is_dir($path) && !is_link($path)) $removeTemporary($path);
            else unlink($path);
        }
        rmdir($directory);
    };
    $removeTemporary($temporaryRoot);
    $previousCacheDir === false ? putenv('MEDICAL_SEARCH_CACHE_DIR') : putenv('MEDICAL_SEARCH_CACHE_DIR=' . $previousCacheDir);
    ini_set('error_log', (string) $previousErrorLog);
}
