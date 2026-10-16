<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_image_brand_renderer.php';
require_once __DIR__ . '/medical_image_brand_sources.php';

/** Explicit admin batches only. No schema changes, scan, or writes on include. */
function medical_image_brand_store_dir(): string
{
    return __DIR__ . '/storage/image-branding';
}

function medical_image_brand_empty_state(): array
{
    return ['version' => 1, 'scan' => ['phase' => 'idle', 'after_id' => 0, 'scanned' => 0, 'steps' => 0, 'directories' => [],
        'directory' => null, 'after_file' => '', 'updated_at' => null], 'jobs' => []];
}

function medical_image_brand_read_state(string $directory): array
{
    $path = $directory . '/state.json';
    if (!is_file($path)) return medical_image_brand_empty_state();
    $size = filesize($path);
    if ($size === false || $size > 64 * 1024 * 1024) throw new RuntimeException('Sổ theo dõi ảnh quá lớn hoặc không đọc được.');
    $state = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($state) || ($state['version'] ?? 0) !== 1 || !is_array($state['jobs'] ?? null) || !is_array($state['scan'] ?? null)) {
        throw new RuntimeException('Sổ theo dõi ảnh không hợp lệ; cần khôi phục bản lưu trước khi chạy tiếp.');
    }
    return $state;
}

/** A separate lock survives atomic replacement of the JSON file. */
function medical_image_brand_store(callable $operation, bool $write = false, ?string $directory = null): mixed
{
    $directory ??= medical_image_brand_store_dir();
    if (!is_dir($directory)) {
        if (!$write) { $empty = medical_image_brand_empty_state(); return $operation($empty); }
        if (!@mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Không tạo được sổ theo dõi gắn logo.');
    }
    $lock = @fopen($directory . '/state.lock', $write ? 'c' : 'r');
    if ($lock === false && !$write) {
        if (is_file($directory . '/state.json')) throw new RuntimeException('Không đọc được khóa sổ theo dõi ảnh.');
        // No writer has ever initialized this store. Reading never creates it.
        $empty = medical_image_brand_empty_state();
        return $operation($empty);
    }
    if ($lock === false || !flock($lock, $write ? LOCK_EX : LOCK_SH)) throw new RuntimeException('Không khóa được sổ theo dõi ảnh.');
    $temporary = null;
    try {
        $state = medical_image_brand_read_state($directory);
        $result = $operation($state);
        if ($write) {
            $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($json) > 64 * 1024 * 1024) throw new RuntimeException('Sổ theo dõi ảnh đạt giới hạn; cần chia kho ảnh trước khi tiếp tục.');
            $temporary = tempnam($directory, '.state-');
            if (!is_string($temporary) || file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)
                || !@chmod($temporary, 0640) || !@rename($temporary, $directory . '/state.json')) {
                throw new RuntimeException('Không lưu được trạng thái gắn logo; chưa chạy thêm ảnh.');
            }
        }
        return $result;
    } finally {
        if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Scan/run/retry are serialized, while the status endpoint stays responsive. */
function medical_image_brand_operation(callable $operation, ?string $directory = null): mixed
{
    $directory ??= medical_image_brand_store_dir();
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Không tạo được thư mục theo dõi gắn logo.');
    }
    $lock = @fopen($directory . '/operation.lock', 'c');
    if ($lock === false) throw new RuntimeException('Không mở được khóa xử lý ảnh.');
    if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('Một lượt quét/gắn logo đang chạy; hãy đợi lượt hiện tại hoàn tất.', 409); }
    try { return $operation(); }
    finally { flock($lock, LOCK_UN); fclose($lock); }
}

function medical_image_brand_add_source(array &$state, array $source, ?array $ref = null, bool $library = false, string $libraryType = ''): void
{
    $key = $source['key'];
    if (!isset($state['jobs'][$key])) {
        $state['jobs'][$key] = ['key' => $key, 'url' => $source['url'], 'refs' => [], 'types' => [], 'library' => false,
            'status' => 'pending', 'output_url' => null, 'error' => '', 'created_at' => gmdate('c'), 'updated_at' => gmdate('c')];
    }
    $job =& $state['jobs'][$key];
    if ($job['status'] === 'done') {
        // In-place images are still found under their original URL on every
        // scan. Only changed bytes (not another DB occurrence) need branding.
        $path = $source['local_path'] ?? null;
        if (($job['mode'] ?? '') !== 'in_place' || !is_string($path)
            || ($job['output_hash'] ?? '') !== @hash_file('sha256', $path)) {
            $job['status'] = 'pending'; $job['error'] = '';
        }
    }
    if ($ref !== null) {
        $refKey = $ref['type'] . ':' . $ref['id'];
        $exists = false;
        foreach ($job['refs'] as $index => $known) {
            if ($known['type'] . ':' . $known['id'] === $refKey) { $job['refs'][$index] = $ref; $exists = true; break; }
        }
        if (!$exists) {
            $job['refs'][] = $ref;
        }
        $job['types'][$ref['type']] = true;
    }
    if ($library) $job['library'] = true;
    if ($libraryType !== '') $job['types'][$libraryType] = true;
}

function medical_image_brand_scan_start(?string $directory = null): array
{
    return medical_image_brand_store(static function (array &$state): array {
        foreach ($state['jobs'] as &$job) {
            // The caller holds operation.lock: no active image worker can be
            // processing while a new scan starts. Recover interrupted requests.
            $path = medical_image_brand_output_path((string) ($job['output_url'] ?? ''));
            if ($job['status'] === 'processing' || ($job['status'] === 'done'
                && (($job['mode'] ?? '') !== 'in_place' || $path === null))) {
                $job['status'] = 'pending'; $job['error'] = '';
            }
        }
        unset($job);
        $state['scan'] = ['phase' => 'facility', 'after_id' => 0, 'scanned' => 0, 'steps' => 0,
            'directories' => ['co-so-y-te', 'bac-si', 'co-so-y-te-ai'], 'directory' => null, 'after_file' => '', 'updated_at' => gmdate('c')];
        return $state['scan'];
    }, true, $directory);
}

/** Keyset DB scan; directory walking is bounded and never follows symlinks. */
function medical_image_brand_scan_step(PDO $pdo, ?string $directory = null): array
{
    $snapshot = medical_image_brand_store(static fn(array $state): array => $state['scan'], false, $directory);
    $phase = $snapshot['phase'];
    if (in_array($phase, ['idle', 'complete'], true)) return $snapshot;
    if (in_array($phase, ['facility', 'doctor'], true)) {
        $page = medical_image_brand_source_page($pdo, $phase, (int) $snapshot['after_id'], 25);
        return medical_image_brand_store(static function (array &$state) use ($page, $phase): array {
            foreach ($page['rows'] as $row) {
                foreach ($row['sources'] as $source) medical_image_brand_add_source($state, $source,
                    ['type' => $phase, 'id' => (int) $row['id'], 'name' => (string) $row['name']]);
            }
            $state['scan']['scanned'] += $page['scanned'];
            $state['scan']['steps'] = (int) ($state['scan']['steps'] ?? 0) + 1;
            $state['scan']['after_id'] = $page['next_id'];
            if (!$page['has_more']) { $state['scan']['phase'] = $phase === 'facility' ? 'doctor' : 'library'; $state['scan']['after_id'] = 0; }
            $state['scan']['updated_at'] = gmdate('c');
            return $state['scan'];
        }, true, $directory);
    }
    medical_image_brand_source_load_exclusions($pdo);
    $library = realpath(__DIR__ . '/uploads/library');
    return medical_image_brand_store(static function (array &$state) use ($library): array {
        $scan =& $state['scan'];
        for ($budget = 0; $budget < 200;) {
            if ($scan['directory'] === null) {
                $scan['directory'] = array_shift($scan['directories']);
                $scan['after_file'] = '';
                if ($scan['directory'] === null) { $scan['phase'] = 'complete'; break; }
            }
            $relative = $scan['directory'];
            $path = is_string($library) ? $library . '/' . $relative : '';
            $real = $path !== '' ? realpath($path) : false;
            if (!$real || !str_starts_with($real, $library . '/') || !is_dir($real) || is_link($path)) { $scan['directory'] = null; continue; }
            $names = scandir($real);
            if ($names === false) throw new RuntimeException('Không đọc được thư mục ảnh: ' . $relative);
            $exhausted = true;
            foreach ($names as $name) {
                if ($name === '.' || $name === '..' || strcmp($name, $scan['after_file']) <= 0) continue;
                $scan['after_file'] = $name;
                $budget++;
                $entry = $real . '/' . $name;
                if (!is_link($entry)) {
                    if (is_dir($entry)) $scan['directories'][] = $relative . '/' . $name;
                    elseif (is_file($entry) && !str_starts_with($name, '.') && !str_starts_with($name, 'source-') && preg_match('/\.(?:jpe?g|png|webp)$/iD', $name)) {
                        $source = medical_image_brand_source_identity('/uploads/library/' . $relative . '/' . $name);
                        if ($source !== null) medical_image_brand_add_source($state, $source, null, true,
                            str_starts_with($relative, 'bac-si') ? 'doctor' : 'facility');
                    }
                }
                if ($budget >= 200) { $exhausted = false; break; }
            }
            if ($exhausted) $scan['directory'] = null;
        }
        $scan['updated_at'] = gmdate('c');
        $scan['steps'] = (int) ($scan['steps'] ?? 0) + 1;
        return $scan;
    }, true, $directory);
}

function medical_image_brand_matches(array $job, string $type): bool
{
    return $type === 'all' || !empty($job['types'][$type]);
}

function medical_image_brand_status(string $type = 'all', string $status = 'all', int $page = 1, ?string $directory = null): array
{
    return medical_image_brand_store(static function (array $state) use ($type, $status, $page): array {
        $summary = ['total' => 0, 'done' => 0, 'pending' => 0, 'failed' => 0, 'processing' => 0];
        $items = [];
        foreach ($state['jobs'] as $job) {
            if (!medical_image_brand_matches($job, $type)) continue;
            $summary['total']++;
            $summary[$job['status']]++;
            if ($status === 'all' || $job['status'] === $status) $items[] = $job;
        }
        $pages = max(1, (int) ceil(count($items) / 20));
        $page = min(max(1, $page), $pages);
        $items = array_slice(array_reverse($items), ($page - 1) * 20, 20);
        // Do not expose local paths, binary metadata or internal processing tokens.
        $items = array_map(static fn(array $job): array => array_intersect_key($job, array_flip([
            'key', 'url', 'refs', 'library', 'status', 'output_url', 'error', 'updated_at']))
            + ['cache_revision' => substr((string) ($job['output_hash'] ?? ''), 0, 20)], $items);
        return ['ok' => true, 'summary' => $summary, 'scan' => array_intersect_key($state['scan'], array_flip(['phase', 'scanned', 'steps', 'updated_at'])),
            'items' => $items, 'page' => $page, 'pages' => $pages];
    }, false, $directory);
}

function medical_image_brand_retry_failed(string $type = 'all', ?string $directory = null): int
{
    return medical_image_brand_store(static function (array &$state) use ($type): int {
        $count = 0;
        foreach ($state['jobs'] as &$job) {
            if ($job['status'] === 'failed' && medical_image_brand_matches($job, $type)) {
                $job['status'] = 'pending'; $job['error'] = ''; $job['updated_at'] = gmdate('c'); $count++;
            }
        }
        return $count;
    }, true, $directory);
}

function medical_image_brand_output_path(string $url): ?string
{
    $source = medical_image_brand_source_normalize($url);
    return $source !== null && str_starts_with($source['url'], '/') ? $source['local_path'] : null;
}

/** One image per HTTP request keeps GD memory use and hosting latency bounded. */
function medical_image_brand_run(PDO $pdo, string $type = 'all', ?string $directory = null, ?callable $prepare = null, ?callable $apply = null): array
{
    $job = medical_image_brand_store(static function (array &$state) use ($type): ?array {
        foreach ($state['jobs'] as &$candidate) {
            if ($candidate['status'] === 'processing' && (int) ($candidate['processing_at'] ?? 0) < time() - 300) $candidate['status'] = 'pending';
            if ($candidate['status'] !== 'pending' || !medical_image_brand_matches($candidate, $type)) continue;
            $candidate['status'] = 'processing'; $candidate['processing_at'] = time(); $candidate['updated_at'] = gmdate('c');
            return $candidate;
        }
        return null;
    }, true, $directory);
    if ($job === null) return ['processed' => 0, 'message' => 'Không còn ảnh chờ gắn logo trong nhóm này.'];
    try {
        medical_image_brand_source_load_exclusions($pdo);
        $replacedOutputs = is_array($job['replace_output_urls'] ?? null) ? $job['replace_output_urls'] : [];
        $journal = static function (array $prepared) use ($job, $directory, &$replacedOutputs): void {
            if (is_string($job['output_url'] ?? null) && $job['output_url'] !== $prepared['output_url']) $replacedOutputs[] = $job['output_url'];
            $replacedOutputs = array_values(array_unique($replacedOutputs));
            medical_image_brand_store(static function (array &$state) use ($job, $prepared, $replacedOutputs): void {
                // Write expected output hash BEFORE atomic replacement. After
                // a lost response, matching bytes are reused, never branded twice.
                $state['jobs'][$job['key']] = array_replace($state['jobs'][$job['key']], $prepared, ['replace_output_urls' => $replacedOutputs]);
            }, true, $directory);
        };
        $prepared = $prepare === null ? medical_image_brand_prepare($job, $journal) : $prepare($job);
        $journal($prepared);
        if ($pdo->inTransaction()) throw new LogicException('Lượt gắn logo cần transaction riêng.');
        $pdo->beginTransaction();
        try {
            if ($apply !== null) $changes = $apply($pdo, $job['refs'], $job['key'], $prepared['output_url']);
            else {
                $changes = ['updated' => 0, 'skipped' => 0];
                foreach ($replacedOutputs ?: [null] as $previousOutput) {
                    $batch = medical_image_brand_apply($pdo, $job['refs'], $job['key'], $prepared['output_url'], $previousOutput);
                    $changes['updated'] += $batch['updated']; $changes['skipped'] += $batch['skipped'];
                }
            }
            $pdo->commit();
        } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
        medical_image_brand_store(static function (array &$state) use ($job, $changes): void {
            $row =& $state['jobs'][$job['key']];
            $row['status'] = 'done'; $row['error'] = ''; $row['updated_at'] = gmdate('c'); $row['changes'] = $changes;
            unset($row['replace_output_urls']);
        }, true, $directory);
        if (($changes['updated'] ?? 0) > 0 || ($prepared['mode'] ?? '') === 'in_place') {
            require_once __DIR__ . '/medical_search_cache.php';
            try { medical_search_cache_invalidate(); }
            catch (Throwable $cacheError) { error_log('Image branding cache invalidation failed.'); }
        }
        return ['processed' => 1, 'message' => 'Đã ghi đè ảnh có logo, giữ nguyên thư mục và tên file.'];
    } catch (Throwable $error) {
        error_log('Medical image branding failed (' . get_class($error) . '): ' . $error->getMessage());
        $message = $error instanceof InvalidArgumentException ? $error->getMessage()
            : 'Chưa hoàn tất lượt xử lý. Ảnh có thể đã được ghi đè; bấm thử lại để kiểm tra hash và tiếp tục, không đóng logo chồng.';
        medical_image_brand_store(static function (array &$state) use ($job, $message): void {
            $row =& $state['jobs'][$job['key']]; $row['status'] = 'failed'; $row['error'] = mb_substr($message, 0, 700); $row['updated_at'] = gmdate('c');
        }, true, $directory);
        return ['processed' => 1, 'failed' => true, 'message' => 'Ảnh lỗi đã được ghi lại; các ảnh khác vẫn có thể tiếp tục.'];
    }
}

/** Local-only in-place writes. The journal is persisted before atomic rename. */
function medical_image_brand_prepare(array $job, ?callable $journal = null): array
{
    $source = medical_image_brand_source_identity($job['url']);
    if ($source === null || $source['key'] !== $job['key']) throw new InvalidArgumentException('Ảnh nguồn không còn hợp lệ.');
    if (!str_starts_with($source['url'], '/')) throw new InvalidArgumentException('Ảnh ở website bên ngoài không thể ghi đè. Hãy lưu ảnh vào thư viện hosting trước.');
    $path = $source['local_path'];
    if ($path === null) throw new InvalidArgumentException('File ảnh nguồn không tồn tại trên hosting; không còn bản gốc để tạo lại.');
    clearstatcache(true, $path);
    $currentHash = hash_file('sha256', $path);
    $stat = @stat($path);
    if (($job['mode'] ?? '') === 'in_place' && ($job['output_hash'] ?? '') === $currentHash) {
        // Covers a crash after rename, DB failure, a lost response and rescan.
        // The already-branded file is the only copy; do not composite again.
        return ['mode' => 'in_place', 'output_url' => $source['url'], 'output_hash' => $currentHash,
            'source_hash' => $job['source_hash'] ?? $currentHash,
            'source_mtime' => $stat['mtime'], 'source_size' => $stat['size']];
    }
    $toPrepared = static fn(array $metadata): array => ['mode' => 'in_place', 'output_url' => $source['url'],
        'source_hash' => $metadata['source_hash'], 'output_hash' => $metadata['hash'], 'render_version' => $metadata['version'],
        'width' => $metadata['width'], 'height' => $metadata['height']];
    $metadata = medical_image_brand_render_in_place($path, static function (array $result) use ($journal, $toPrepared): void {
        if ($journal !== null) $journal($toPrepared($result));
    });
    clearstatcache(true, $path);
    $stat = stat($path);
    return $toPrepared($metadata) + ['source_mtime' => $stat['mtime'], 'source_size' => $stat['size']];
}
