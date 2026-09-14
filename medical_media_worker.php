<?php
declare(strict_types=1);

/**
 * Background queue for remote facility/doctor images.
 *
 * The public article API only records the original URLs and queues them. This
 * file is deliberately independent from an HTTP request: it is used by the
 * authenticated admin worker as well as a CLI cron command.
 */

require_once __DIR__ . '/medical_directory.php';
require_once __DIR__ . '/medical_media_library.php';

if (!function_exists('medical_media_jobs_ensure_table')) {
    function medical_media_jobs_ensure_table(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) return;

        if (!medical_directory_table_exists($pdo, 'medical_media_jobs')) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS medical_media_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type ENUM('facility','doctor') NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                field_name VARCHAR(32) NOT NULL DEFAULT 'gallery_json',
                source_url TEXT NOT NULL,
                source_hash CHAR(64) NOT NULL,
                status ENUM('pending','processing','downloaded','done','failed') NOT NULL DEFAULT 'pending',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                local_url VARCHAR(512) NULL,
                last_error TEXT NULL,
                next_attempt_at DATETIME NULL,
                locked_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_medical_media_job (entity_type, entity_id, field_name, source_hash),
                KEY idx_medical_media_job_run (status, next_attempt_at, id),
                KEY idx_medical_media_job_entity (entity_type, entity_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } else {
            // Earlier deployments only had pending/processing/done/failed.
            // Keep the migration local to this queue table and add the
            // intermediate `downloaded` state used by the two-step worker.
            $column = $pdo->query("SHOW COLUMNS FROM medical_media_jobs LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
            $type = strtolower((string) ($column['Type'] ?? ''));
            if (!str_contains($type, "'downloaded'")) {
                $pdo->exec("ALTER TABLE medical_media_jobs MODIFY COLUMN status ENUM('pending','processing','downloaded','done','failed') NOT NULL DEFAULT 'pending'");
            }
        }
        $ready = true;
    }

    function medical_media_jobs_is_remote_url(string $url): bool
    {
        $url = trim($url);
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        return $url !== '' && in_array($scheme, ['http', 'https'], true) && !empty($parts['host']);
    }

    /** A local library URL must never go back through the network queue. */
    function medical_media_jobs_is_owned_url(string $url): bool
    {
        if (medical_media_local_library_path($url) !== null) return true;
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        return str_starts_with($path, '/uploads/library/');
    }

    /**
     * @param array<mixed> $sources Accepts ['url', ...] or
     *   ['image_url'=>['url'], 'gallery_json'=>['url', ...]].
     * @return array{queued:int,skipped:int}
     */
    function medical_media_jobs_enqueue_entity_urls(PDO $pdo, string $entityType, int $entityId, array $sources, bool $requeueExisting = true): array
    {
        medical_media_jobs_ensure_table($pdo);
        $entityType = $entityType === 'doctor' ? 'doctor' : 'facility';
        if ($entityId <= 0) return ['queued' => 0, 'skipped' => 0];

        $normalized = [];
        foreach ($sources as $key => $value) {
            $field = is_string($key) && in_array($key, ['image_url', 'gallery_json'], true) ? $key : 'gallery_json';
            $list = is_array($value) ? $value : [$value];
            foreach ($list as $url) {
                $url = trim((string) $url);
                if ($url === '' || !medical_media_jobs_is_remote_url($url) || medical_media_jobs_is_owned_url($url)) continue;
                $normalized[$field . "\n" . $url] = [$field, $url];
            }
        }
        if ($normalized === []) return ['queued' => 0, 'skipped' => 0];

        $sql = $requeueExisting
            ? "INSERT INTO medical_media_jobs (entity_type, entity_id, field_name, source_url, source_hash, status, attempts, local_url, last_error, next_attempt_at, locked_at)
                VALUES (:entity_type, :entity_id, :field_name, :source_url, :source_hash, 'pending', 0, NULL, NULL, NULL, NULL)
                ON DUPLICATE KEY UPDATE status = 'pending', attempts = 0, local_url = NULL, last_error = NULL, next_attempt_at = NULL, locked_at = NULL, updated_at = CURRENT_TIMESTAMP"
            : "INSERT IGNORE INTO medical_media_jobs (entity_type, entity_id, field_name, source_url, source_hash, status, attempts, local_url, last_error, next_attempt_at, locked_at)
                VALUES (:entity_type, :entity_id, :field_name, :source_url, :source_hash, 'pending', 0, NULL, NULL, NULL, NULL)";
        $stmt = $pdo->prepare($sql);
        $queued = 0;
        foreach ($normalized as [$field, $url]) {
            $stmt->execute([
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':field_name' => $field,
                ':source_url' => $url,
                ':source_hash' => hash('sha256', $url),
            ]);
            if ($requeueExisting || $stmt->rowCount() > 0) $queued++;
        }
        return ['queued' => $queued, 'skipped' => 0];
    }

    /** Convenience alias for the article receiver. */
    function medical_media_jobs_enqueue_facility_urls(PDO $pdo, int $facilityId, array $sources, bool $requeueExisting = true): array
    {
        return medical_media_jobs_enqueue_entity_urls($pdo, 'facility', $facilityId, $sources, $requeueExisting);
    }

    /** @return array<int,string> */
    function medical_media_jobs_decode_urls(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }
        if (!is_array($value)) return [];
        $urls = [];
        $walk = static function (mixed $item) use (&$walk, &$urls): void {
            if (is_string($item)) {
                $item = trim($item);
                if ($item !== '') $urls[] = $item;
                return;
            }
            if (!is_array($item)) return;
            // Gallery objects carry context such as caption/source. Only the
            // actual image location is a download candidate.
            if (array_key_exists('url', $item) || array_key_exists('src', $item)) {
                $walk($item['url'] ?? $item['src'] ?? '');
                return;
            }
            foreach ($item as $child) $walk($child);
        };
        $walk($value);
        return array_values(array_unique($urls));
    }

    /**
     * Finds existing external image URLs and creates jobs without changing the
     * retry state of jobs that are already known. This makes the queue useful
     * for content received before the worker was installed.
     *
     * @return array{scanned:int,queued:int}
     */
    function medical_media_jobs_scan_remote_entities(PDO $pdo, string $type = 'all', int $limit = 100): array
    {
        medical_media_jobs_ensure_table($pdo);
        $type = in_array($type, ['facility', 'doctor', 'all'], true) ? $type : 'all';
        $limit = max(1, min(500, $limit));
        $configs = [];
        if ($type === 'all' || $type === 'facility') $configs[] = ['facility', 'medical_facilities'];
        if ($type === 'all' || $type === 'doctor') $configs[] = ['doctor', 'medical_doctors'];

        $scanned = 0; $queued = 0; $left = $limit;
        foreach ($configs as [$entityType, $table]) {
            if ($left < 1 || !medical_directory_table_exists($pdo, $table)) continue;
            // Prioritise entities that do not have any queue rows yet. A few
            // permanently failed URLs therefore cannot keep newer/unseen
            // facilities or doctors from ever being discovered by Cron.
            $stmt = $pdo->prepare(
                "SELECT entity.id, entity.image_url, entity.gallery_json
                 FROM {$table} AS entity
                 WHERE entity.image_url REGEXP '^https?://' OR entity.gallery_json LIKE '%http%'
                 ORDER BY CASE WHEN EXISTS (
                    SELECT 1 FROM medical_media_jobs AS job
                    WHERE job.entity_type = :entity_type AND job.entity_id = entity.id
                 ) THEN 1 ELSE 0 END ASC, entity.updated_at DESC, entity.id DESC
                 LIMIT {$left}"
            );
            $stmt->execute([':entity_type' => $entityType]);
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $scanned++;
                $sources = [
                    'image_url' => [(string) ($row['image_url'] ?? '')],
                    'gallery_json' => medical_media_jobs_decode_urls($row['gallery_json'] ?? null),
                ];
                $result = medical_media_jobs_enqueue_entity_urls($pdo, $entityType, (int) $row['id'], $sources, false);
                $queued += $result['queued'];
                $left--;
                if ($left < 1) break;
            }
        }
        return ['scanned' => $scanned, 'queued' => $queued];
    }

    /**
     * A batch claims several URLs before it starts downloading them. Give a
     * normal 3-way batch enough lease time to finish, then safely recover an
     * abandoned browser/PHP request. Five minutes avoids a second worker
     * stealing the final URLs from a healthy larger batch.
     */
    function medical_media_jobs_recover_stale(PDO $pdo, int $seconds = 300): int
    {
        medical_media_jobs_ensure_table($pdo);
        $seconds = max(90, min(1800, $seconds));
        $stmt = $pdo->prepare("UPDATE medical_media_jobs
            SET status = IF(local_url IS NULL, 'failed', 'downloaded'),
                last_error = 'Worker trước đó bị ngắt.',
                next_attempt_at = NOW(),
                locked_at = NULL
            WHERE status = 'processing'
              AND locked_at < DATE_SUB(NOW(), INTERVAL {$seconds} SECOND)");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** @return array{facility:array<string,int>,doctor:array<string,int>,total:array<string,int>} */
    function medical_media_jobs_status(PDO $pdo): array
    {
        medical_media_jobs_ensure_table($pdo);
        medical_media_jobs_recover_stale($pdo);
        $zero = ['pending' => 0, 'processing' => 0, 'downloaded' => 0, 'done' => 0, 'failed' => 0];
        $result = ['facility' => $zero, 'doctor' => $zero, 'total' => $zero];
        $stmt = $pdo->query('SELECT entity_type, status, COUNT(*) AS total FROM medical_media_jobs GROUP BY entity_type, status');
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $type = (string) ($row['entity_type'] ?? '');
            $status = (string) ($row['status'] ?? '');
            $total = (int) ($row['total'] ?? 0);
            if (isset($result[$type][$status])) $result[$type][$status] = $total;
            if (isset($result['total'][$status])) $result['total'][$status] += $total;
        }
        return $result;
    }

    /**
     * Small, admin-only snapshot used by the Worker UI while a batch runs.
     * It intentionally exposes only the currently claimed jobs, never the
     * whole queue, so the browser can say exactly which source is being
     * downloaded or compressed without a heavy polling query.
     *
     * @return array<int,array<string,mixed>>
     */
    function medical_media_jobs_active_items(PDO $pdo, int $limit = 8): array
    {
        medical_media_jobs_ensure_table($pdo);
        $limit = max(1, min(12, $limit));
        $rows = $pdo->query("SELECT id, entity_type, entity_id, source_url, local_url, locked_at
            FROM medical_media_jobs
            WHERE status = 'processing'
            ORDER BY locked_at ASC, id ASC
            LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) return [];

        $entities = medical_media_jobs_load_entities($pdo, $rows);
        foreach ($rows as &$row) {
            $key = (string) ($row['entity_type'] ?? '') . ':' . (int) ($row['entity_id'] ?? 0);
            $entity = $entities[$key] ?? [];
            $row['name'] = is_array($entity) ? (string) ($entity['name'] ?? '') : '';
            $row['phase'] = trim((string) ($row['local_url'] ?? '')) === '' ? 'download' : 'compress';
            unset($row['local_url']);
        }
        unset($row);
        return $rows;
    }

    /** @return array{field:string,changed:bool,value:mixed} */
    function medical_media_jobs_replace_url(mixed $value, string $source, string $localUrl): array
    {
        $changed = false;
        $replace = static function (mixed $item) use (&$replace, &$changed, $source, $localUrl): mixed {
            if (is_string($item)) {
                if (trim($item) === $source) {
                    $changed = true;
                    return $localUrl;
                }
                return $item;
            }
            if (is_array($item)) {
                // Preserve angle/caption/source fields; only rewrite the
                // image location on rich gallery objects.
                if (array_key_exists('url', $item) || array_key_exists('src', $item)) {
                    foreach (['url', 'src'] as $key) {
                        if (array_key_exists($key, $item)) {
                            $item[$key] = $replace($item[$key]);
                        }
                    }
                    return $item;
                }
                foreach ($item as $key => $child) $item[$key] = $replace($child);
            }
            return $item;
        };
        $replaced = $replace($value);
        return ['field' => '', 'changed' => $changed, 'value' => $replaced];
    }

    /** @return array{changed:bool,error?:string} */
    function medical_media_jobs_apply_local_url(PDO $pdo, array $job, string $localUrl, ?string $matchUrl = null): array
    {
        $entityType = (string) ($job['entity_type'] ?? '');
        $table = $entityType === 'doctor' ? 'medical_doctors' : 'medical_facilities';
        if (!medical_directory_table_exists($pdo, $table)) return ['changed' => false, 'error' => 'Không tìm thấy bảng dữ liệu.'];
        $id = (int) ($job['entity_id'] ?? 0);
        if ($id <= 0) return ['changed' => false, 'error' => 'ID dữ liệu không hợp lệ.'];
        $select = $pdo->prepare("SELECT image_url, gallery_json FROM {$table} WHERE id = :id LIMIT 1");
        $select->execute([':id' => $id]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return ['changed' => false, 'error' => 'Không còn tìm thấy hồ sơ cần cập nhật.'];

        $source = trim($matchUrl ?? (string) ($job['source_url'] ?? ''));
        $cover = (string) ($row['image_url'] ?? '');
        $galleryRaw = $row['gallery_json'] ?? '[]';
        $gallery = is_string($galleryRaw) ? json_decode($galleryRaw, true) : $galleryRaw;
        if (!is_array($gallery)) $gallery = [];
        $galleryResult = medical_media_jobs_replace_url($gallery, $source, $localUrl);
        $coverChanged = trim($cover) === $source;
        if (!$coverChanged && !$galleryResult['changed']) return ['changed' => false];

        $set = []; $params = [':id' => $id];
        if ($coverChanged) {
            $set[] = 'image_url = :image_url';
            $params[':image_url'] = $localUrl;
        }
        if ($galleryResult['changed']) {
            $set[] = 'gallery_json = :gallery_json';
            $params[':gallery_json'] = json_encode($galleryResult['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $pdo->prepare("UPDATE {$table} SET " . implode(', ', $set) . ' WHERE id = :id')->execute($params);
        return ['changed' => true];
    }

    function medical_media_jobs_folder(array $job, array $entity): string
    {
        $type = (string) ($job['entity_type'] ?? 'facility');
        $prefix = $type === 'doctor' ? 'bac-si/doctor' : 'co-so-y-te/facility';
        $slug = medical_media_folder_slug((string) ($entity['slug'] ?? ''));
        if ($slug === '') $slug = medical_media_folder_slug((string) ($entity['name'] ?? ''));
        if ($slug === '') $slug = $type === 'doctor' ? 'bac-si' : 'co-so-y-te';
        return $prefix . '-' . max(1, (int) ($job['entity_id'] ?? 0)) . '-' . $slug;
    }

    /** @return array<int,array{ok:bool,bytes?:string,mime?:string,error?:string,http_status?:int,curl_error?:string,timing?:array<string,mixed>}> */
    function medical_media_jobs_download_parallel(array $urls, int $parallel = 3, int $timeout = 12): array
    {
        $parallel = max(1, min(3, $parallel));
        $timeout = max(3, min(20, $timeout));
        $urls = array_values(array_unique(array_filter(array_map(static fn($url): string => trim((string) $url), $urls), static fn(string $url): bool => $url !== '')));
        $result = [];
        foreach ($urls as $url) {
            $parts = parse_url($url);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = (string) ($parts['host'] ?? '');
            if (!in_array($scheme, ['http', 'https'], true) || !medical_media_is_public_host($host)) {
                $result[$url] = ['ok' => false, 'error' => 'URL ảnh không hợp lệ hoặc trỏ tới mạng nội bộ.'];
            }
        }
        $pending = array_values(array_filter($urls, static fn(string $url): bool => !isset($result[$url])));
        if ($pending === []) return $result;
        if (!function_exists('curl_multi_init') || !function_exists('curl_init')) {
            foreach ($pending as $url) $result[$url] = medical_media_download_remote_image($url, $timeout);
            return $result;
        }

        $maxBytes = 12 * 1024 * 1024;
        foreach (array_chunk($pending, $parallel) as $batch) {
            $multi = curl_multi_init();
            $contexts = [];
            foreach ($batch as $url) {
                $curl = curl_init($url);
                if ($curl === false) {
                    $result[$url] = ['ok' => false, 'error' => 'Không khởi tạo được kết nối tải ảnh.'];
                    continue;
                }
                $key = is_object($curl) ? spl_object_id($curl) : (int) $curl;
                $contexts[$key] = ['curl' => $curl, 'url' => $url, 'bytes' => '', 'location' => '', 'too_large' => false];
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_USERAGENT => 'MedReview Image Worker/1.0',
                    CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/jpeg,image/png,image/gif;q=0.9,*/*;q=0.1'],
                    CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$contexts, $key): int {
                        if (stripos($header, 'Location:') === 0) $contexts[$key]['location'] = trim(substr($header, 9));
                        return strlen($header);
                    },
                    CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$contexts, $key, $maxBytes): int {
                        if (strlen($contexts[$key]['bytes']) + strlen($chunk) > $maxBytes) {
                            $contexts[$key]['too_large'] = true;
                            return 0;
                        }
                        $contexts[$key]['bytes'] .= $chunk;
                        return strlen($chunk);
                    },
                ]);
                curl_multi_add_handle($multi, $curl);
            }
            do {
                $running = 0;
                do { $code = curl_multi_exec($multi, $running); } while ($code === CURLM_CALL_MULTI_PERFORM);
                if ($running > 0) {
                    $selected = curl_multi_select($multi, 1.0);
                    if ($selected === -1) usleep(10000);
                }
            } while ($running > 0 && $code === CURLM_OK);

            foreach ($contexts as $context) {
                $curl = $context['curl'];
                $url = (string) $context['url'];
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $mime = strtolower(trim((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE)));
                $error = (string) curl_error($curl);
                $timing = medical_media_timing_merge([], medical_media_curl_timing($curl, $status), $url);
                curl_multi_remove_handle($multi, $curl);
                curl_close($curl);
                if ($context['too_large']) {
                    $result[$url] = ['ok' => false, 'error' => 'Ảnh vượt quá giới hạn 12MB.', 'http_status' => $status, 'timing' => $timing];
                } elseif ($status >= 300 && $status < 400 && $context['location'] !== '') {
                    // Let the single-url helper follow redirects safely: it
                    // revalidates every destination against SSRF protections.
                    $followed = medical_media_download_remote_image($url, $timeout);
                    $result[$url] = $followed;
                    $result[$url]['timing'] = medical_media_timing_join($timing, (array) ($followed['timing'] ?? []));
                    $result[$url]['http_status'] = (int) ($result[$url]['timing']['http_status'] ?? $status);
                } elseif ($status >= 200 && $status < 300 && $context['bytes'] !== '') {
                    $result[$url] = ['ok' => true, 'bytes' => $context['bytes'], 'mime' => strtok($mime, ';') ?: '', 'http_status' => $status, 'timing' => $timing];
                } else {
                    $message = $status > 0 ? 'Máy chủ ảnh trả về HTTP ' . $status . '.' : 'Không thể kết nối tới máy chủ ảnh.';
                    if ($error !== '') $message .= ' ' . $error;
                    $result[$url] = ['ok' => false, 'error' => $message, 'http_status' => $status, 'curl_error' => $error, 'timing' => $timing];
                }
            }
            curl_multi_close($multi);
        }
        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    function medical_media_jobs_claim(PDO $pdo, string $type, int $limit, string $mode = 'full'): array
    {
        medical_media_jobs_ensure_table($pdo);
        $mode = in_array($mode, ['download', 'compress', 'full'], true) ? $mode : 'full';
        // A browser request can be interrupted mid-batch. A short recovery
        // time keeps the UI responsive without a second Worker ever claiming
        // an active job (the shared file lock still protects active work).
        medical_media_jobs_recover_stale($pdo);
        $type = in_array($type, ['facility', 'doctor', 'all'], true) ? $type : 'all';
        $limit = max(1, min(30, $limit));
        if ($mode === 'compress') {
            $where = "status = 'downloaded' AND local_url IS NOT NULL AND local_url <> ''";
            $allowed = "'downloaded'";
        } else {
            $where = "(status = 'pending' OR (status = 'failed' AND (next_attempt_at IS NULL OR next_attempt_at <= NOW()))) AND attempts < 5";
            $allowed = "'pending','failed'";
        }
        $params = [];
        if ($type !== 'all') {
            $where .= ' AND entity_type = :entity_type';
            $params[':entity_type'] = $type;
        }
        $stmt = $pdo->prepare("SELECT * FROM medical_media_jobs WHERE {$where} ORDER BY id ASC LIMIT {$limit}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) return [];
        $claim = $pdo->prepare("UPDATE medical_media_jobs SET status = 'processing', locked_at = NOW(), last_error = NULL WHERE id = :id AND status IN ({$allowed})");
        $claimed = [];
        foreach ($rows as $row) {
            $claim->execute([':id' => (int) $row['id']]);
            if ($claim->rowCount() > 0) $claimed[] = $row;
        }
        return $claimed;
    }

    /** @return array<string,array<string,mixed>> */
    function medical_media_jobs_load_entities(PDO $pdo, array $jobs): array
    {
        $entities = [];
        foreach ($jobs as $job) {
            $type = (string) $job['entity_type'];
            $table = $type === 'doctor' ? 'medical_doctors' : 'medical_facilities';
            $key = $type . ':' . (int) $job['entity_id'];
            if (isset($entities[$key]) || !medical_directory_table_exists($pdo, $table)) continue;
            $stmt = $pdo->prepare("SELECT id, slug, name FROM {$table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int) $job['entity_id']]);
            $entity = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($entity)) $entities[$key] = $entity;
        }
        return $entities;
    }

    /** @return array<string,mixed> */
    function medical_media_jobs_process_compression(PDO $pdo, array $jobs): array
    {
        if ($jobs === []) return ['processed' => 0, 'imported' => 0, 'failed' => 0, 'items' => []];
        $entities = medical_media_jobs_load_entities($pdo, $jobs);
        $markDone = $pdo->prepare("UPDATE medical_media_jobs SET status = 'done', local_url = :local_url, last_error = NULL, next_attempt_at = NULL, locked_at = NULL WHERE entity_type = :entity_type AND entity_id = :entity_id AND source_hash = :source_hash");
        $markFailed = $pdo->prepare("UPDATE medical_media_jobs SET status = 'failed', attempts = attempts + 1, last_error = :last_error, next_attempt_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), locked_at = NULL WHERE entity_type = :entity_type AND entity_id = :entity_id AND source_hash = :source_hash");
        $handled = []; $items = []; $imported = 0; $failed = 0;
        foreach ($jobs as $job) {
            $entityKey = (string) $job['entity_type'] . ':' . (int) $job['entity_id'];
            $source = trim((string) $job['source_url']);
            $key = $entityKey . ':' . hash('sha256', $source);
            $entity = $entities[$entityKey] ?? null;
            $item = ['job_id' => (int) $job['id'], 'entity_type' => (string) $job['entity_type'], 'entity_id' => (int) $job['entity_id'], 'name' => is_array($entity) ? (string) ($entity['name'] ?? '') : '', 'source_url' => $source, 'phase' => 'compressed', 'ok' => false];
            if (isset($handled[$key])) { $items[] = array_merge($item, $handled[$key]); continue; }
            if (!is_array($entity)) {
                $message = 'Không còn tìm thấy hồ sơ cần xử lý.';
                $markFailed->execute([':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $source), ':last_error' => $message]);
                $handled[$key] = ['error' => $message]; $item['error'] = $message; $items[] = $item; $failed++; continue;
            }
            $rawUrl = trim((string) ($job['local_url'] ?? ''));
            $rawPath = medical_media_local_library_path($rawUrl);
            $bytes = is_string($rawPath) ? @file_get_contents($rawPath) : false;
            if (!is_string($bytes) || $bytes === '') {
                $message = 'Không còn đọc được file ảnh đã tải; cần tải lại.';
                $markFailed->execute([':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $source), ':last_error' => $message]);
                $handled[$key] = ['error' => $message]; $item['error'] = $message; $items[] = $item; $failed++; continue;
            }
            $stored = medical_media_store_compressed_jpeg($bytes, medical_media_jobs_folder($job, $entity));
            if (!($stored['ok'] ?? false) || empty($stored['url'])) {
                $message = (string) ($stored['error'] ?? 'Không nén được ảnh đã tải.');
                $markFailed->execute([':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $source), ':last_error' => $message]);
                $handled[$key] = ['error' => $message]; $item['error'] = $message; $items[] = $item; $failed++; continue;
            }
            $localUrl = (string) $stored['url'];
            $applied = medical_media_jobs_apply_local_url($pdo, $job, $localUrl, $rawUrl);
            if (isset($applied['error'])) {
                $message = (string) $applied['error'];
                $markFailed->execute([':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $source), ':last_error' => $message]);
                $handled[$key] = ['error' => $message]; $item['error'] = $message; $items[] = $item; $failed++; continue;
            }
            $markDone->execute([':local_url' => $localUrl, ':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $source)]);
            $item = array_merge($item, ['ok' => true, 'local_url' => $localUrl, 'download_bytes' => strlen($bytes), 'stored_bytes' => (int) ($stored['size'] ?? 0), 'updated' => (bool) $applied['changed']]);
            $handled[$key] = ['ok' => true, 'local_url' => $localUrl, 'download_bytes' => strlen($bytes), 'stored_bytes' => (int) ($stored['size'] ?? 0)];
            $items[] = $item; $imported++;
        }
        return ['processed' => count($jobs), 'imported' => $imported, 'failed' => $failed, 'items' => $items];
    }

    /** @return array<string,mixed> */
    function medical_media_jobs_process(PDO $pdo, array $jobs, int $parallel = 3, string $mode = 'full'): array
    {
        if ($mode === 'compress') return medical_media_jobs_process_compression($pdo, $jobs);
        if ($jobs === []) return ['processed' => 0, 'imported' => 0, 'failed' => 0, 'items' => []];
        $downloadOnly = $mode === 'download';
        $entities = medical_media_jobs_load_entities($pdo, $jobs);
        $urlJobs = [];
        foreach ($jobs as $job) $urlJobs[trim((string) $job['source_url'])][] = $job;
        $downloads = medical_media_jobs_download_parallel(array_keys($urlJobs), $parallel);
        $markSuccess = $pdo->prepare("UPDATE medical_media_jobs SET status = :status, local_url = :local_url, last_error = NULL, next_attempt_at = NULL, locked_at = NULL WHERE entity_type = :entity_type AND entity_id = :entity_id AND source_hash = :source_hash");
        $markFailed = $pdo->prepare("UPDATE medical_media_jobs SET status = 'failed', attempts = attempts + 1, last_error = :last_error, next_attempt_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), locked_at = NULL WHERE entity_type = :entity_type AND entity_id = :entity_id AND source_hash = :source_hash");
        $items = []; $imported = 0; $failed = 0;
        foreach ($urlJobs as $url => $sourceJobs) {
            $download = $downloads[$url] ?? ['ok' => false, 'error' => 'Không nhận được phản hồi tải ảnh.'];
            $downloadMeta = [];
            if (array_key_exists('http_status', $download)) $downloadMeta['http_status'] = (int) $download['http_status'];
            if (isset($download['timing']) && is_array($download['timing'])) $downloadMeta['timing'] = $download['timing'];
            $entityResults = [];
            foreach ($sourceJobs as $job) {
                $key = (string) $job['entity_type'] . ':' . (int) $job['entity_id'];
                $entity = $entities[$key] ?? null;
                $item = array_merge(['job_id' => (int) $job['id'], 'entity_type' => (string) $job['entity_type'], 'entity_id' => (int) $job['entity_id'], 'name' => is_array($entity) ? (string) ($entity['name'] ?? '') : '', 'source_url' => $url, 'phase' => $downloadOnly ? 'downloaded' : 'optimized', 'ok' => false], $downloadMeta);
                if (isset($entityResults[$key])) { $items[] = array_merge($item, $entityResults[$key]); continue; }
                if (!is_array($entity)) {
                    $message = 'Không còn tìm thấy hồ sơ cần xử lý.';
                    $markFailed->execute([':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $url), ':last_error' => $message]);
                    $entityResults[$key] = array_merge($downloadMeta, ['error' => $message]); $item['error'] = $message; $items[] = $item; $failed++; continue;
                }
                if (!($download['ok'] ?? false) || !isset($download['bytes'])) {
                    $message = (string) ($download['error'] ?? 'Không thể tải ảnh.');
                    $markFailed->execute([':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $url), ':last_error' => $message]);
                    $entityResults[$key] = array_merge($downloadMeta, ['error' => $message]); $item['error'] = $message; $items[] = $item; $failed++; continue;
                }
                $bytes = (string) $download['bytes'];
                $stored = $downloadOnly
                    ? medical_media_store_downloaded_original($bytes, medical_media_jobs_folder($job, $entity))
                    : medical_media_store_compressed_jpeg($bytes, medical_media_jobs_folder($job, $entity));
                unset($download['bytes']);
                if (!($stored['ok'] ?? false) || empty($stored['url'])) {
                    $message = (string) ($stored['error'] ?? ($downloadOnly ? 'Không lưu được ảnh đã tải.' : 'Không lưu được ảnh đã nén.'));
                    $markFailed->execute([':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $url), ':last_error' => $message]);
                    $entityResults[$key] = array_merge($downloadMeta, ['error' => $message]); $item['error'] = $message; $items[] = $item; $failed++; continue;
                }
                $localUrl = (string) $stored['url'];
                $applied = medical_media_jobs_apply_local_url($pdo, $job, $localUrl);
                if (isset($applied['error'])) {
                    $message = (string) $applied['error'];
                    $markFailed->execute([':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $url), ':last_error' => $message]);
                    $entityResults[$key] = array_merge($downloadMeta, ['error' => $message]); $item['error'] = $message; $items[] = $item; $failed++; continue;
                }
                $markSuccess->execute([':status' => $downloadOnly ? 'downloaded' : 'done', ':local_url' => $localUrl, ':entity_type' => (string) $job['entity_type'], ':entity_id' => (int) $job['entity_id'], ':source_hash' => hash('sha256', $url)]);
                $successData = array_merge($downloadMeta, ['ok' => true, 'local_url' => $localUrl, 'download_bytes' => strlen($bytes), 'stored_bytes' => (int) ($stored['size'] ?? 0), 'updated' => (bool) $applied['changed']]);
                $item = array_merge($item, $successData);
                $entityResults[$key] = $successData;
                $items[] = $item; $imported++;
            }
            // The batch downloader holds raw bytes in its result map. Once
            // every job using this URL has been stored, release that buffer
            // before the next source is handled.
            unset($downloads[$url]);
        }
        return ['processed' => count($jobs), 'imported' => $imported, 'failed' => $failed, 'items' => $items];
    }

    /**
     * Process a bounded queue batch under one shared lock. `$limit` means the
     * number of image URLs, while `$parallel` is 1–3 simultaneous downloads.
     * `download` preserves the original file first; `compress` works later on
     * those local files; `full` remains available for a one-step import.
     *
     * @return array<string,mixed>
     */
    function medical_media_worker_run(PDO $pdo, string $type = 'all', int $limit = 3, int $parallel = 3, bool $scan = true, string $mode = 'download'): array
    {
        medical_media_jobs_ensure_table($pdo);
        $mode = in_array($mode, ['download', 'compress', 'full'], true) ? $mode : 'download';
        $lockPath = sys_get_temp_dir() . '/medreview-medical-media-worker.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            return ['ok' => false, 'busy' => true, 'message' => 'Worker ảnh đang chạy ở tiến trình khác.', 'status' => medical_media_jobs_status($pdo)];
        }
        try {
            $scanResult = $scan && $mode !== 'compress' ? medical_media_jobs_scan_remote_entities($pdo, $type, max(30, $limit * 12)) : ['scanned' => 0, 'queued' => 0];
            $jobs = medical_media_jobs_claim($pdo, $type, $limit, $mode);
            $result = medical_media_jobs_process($pdo, $jobs, $parallel, $mode);
            return array_merge(['ok' => true, 'busy' => false, 'mode' => $mode, 'scan' => $scanResult], $result, ['status' => medical_media_jobs_status($pdo)]);
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    function medical_media_jobs_retry_failed(PDO $pdo, string $type = 'all'): int
    {
        medical_media_jobs_ensure_table($pdo);
        $type = in_array($type, ['facility', 'doctor', 'all'], true) ? $type : 'all';
        $sql = "UPDATE medical_media_jobs SET status = 'pending', attempts = 0, last_error = NULL, next_attempt_at = NULL, locked_at = NULL WHERE status = 'failed'";
        $params = [];
        if ($type !== 'all') { $sql .= ' AND entity_type = :entity_type'; $params[':entity_type'] = $type; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->rowCount();
    }
}
