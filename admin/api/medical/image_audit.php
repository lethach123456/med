<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../schema_migrations.php';
require_once __DIR__ . '/../../../medical_directory.php';
require_once __DIR__ . '/../../../medical_media_library.php';

admin_require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_json();
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = db();

/** Fresh column check without stale static cache after migration. */
function medical_image_audit_has_col(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
        );
        $stmt->execute([':t' => $table, ':c' => $column]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function medical_image_audit_schema_ready(PDO $pdo, string $table): bool
{
    return medical_image_audit_has_col($pdo, $table, 'image_scan_status')
        && medical_image_audit_has_col($pdo, $table, 'image_scanned_at')
        && medical_image_audit_has_col($pdo, $table, 'image_scan_json');
}

function medical_image_audit_ensure_schema(PDO $pdo, string $table): bool
{
    if (medical_image_audit_schema_ready($pdo, $table)) {
        return true;
    }
    try {
        medreview_with_schema_migration(static function () use ($pdo): void {
            medreview_migrate_image_audit_columns($pdo);
        });
    } catch (Throwable) {
    }
    return medical_image_audit_schema_ready($pdo, $table);
}

/** Only include facilities/doctors that already have written content. */
function medical_image_audit_written_where(PDO $pdo, string $table, string $entityType): string
{
    $clauses = [];
    if (medical_image_audit_has_col($pdo, $table, 'content')) {
        $clauses[] = "(content IS NOT NULL AND TRIM(content) <> '')";
    }
    if ($entityType === 'facility' && medical_image_audit_has_col($pdo, $table, 'overview_json')) {
        $clauses[] = "(overview_json IS NOT NULL AND TRIM(overview_json) NOT IN ('', '[]', '{}', 'null'))";
    }
    if ($entityType === 'doctor' && $clauses === [] && medical_image_audit_has_col($pdo, $table, 'bio_json')) {
        $clauses[] = "(bio_json IS NOT NULL AND TRIM(bio_json) NOT IN ('', '[]', '{}', 'null'))";
    }
    return $clauses !== [] ? ('(' . implode(' OR ', $clauses) . ')') : '1=1';
}

/** @return array<int,string> */
function medical_image_audit_extract_urls(array $row): array
{
    $urls = [];
    $cover = trim((string) ($row['image_url'] ?? ''));
    if ($cover !== '') {
        $urls[$cover] = true;
    }
    $rawGallery = $row['gallery_json'] ?? '[]';
    $decoded = is_array($rawGallery) ? $rawGallery : json_decode((string) ($rawGallery ?: '[]'), true);
    if (is_array($decoded)) {
        foreach ($decoded as $entry) {
            $u = is_array($entry) ? trim((string) ($entry['url'] ?? $entry['src'] ?? '')) : trim((string) $entry);
            if ($u !== '') {
                $urls[$u] = true;
            }
        }
    }
    return array_keys($urls);
}

/** Fast static check of a URL before/alongside 64 KB probe. */
function medical_image_audit_static_check(string $url): ?array
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    $lower = strtolower($url);
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

    if (str_starts_with($url, '/')) {
        $rel = rawurldecode($path);
        if (str_contains($rel, '..') || str_contains($rel, "\0")) {
            return ['state' => 'broken', 'reason' => 'Đường dẫn nội bộ lỗi'];
        }
        $full = medical_media_project_root() . '/' . ltrim($rel, '/');
        if (!is_file($full) || (int) (@filesize($full) ?: 0) <= 0) {
            return ['state' => 'broken', 'reason' => 'Mất file nội bộ trên máy chủ'];
        }
        return null;
    }
    if (!preg_match('~^https?://~i', $url)) {
        return ['state' => 'broken', 'reason' => 'URL sai định dạng HTTP/HTTPS'];
    }
    if (str_contains($lower, 'lookaside.fbsbx.com')) {
        return ['state' => 'broken', 'reason' => 'Link Facebook fbsbx (vỡ trên trình duyệt)'];
    }
    if (preg_match('~(maps\.app\.goo\.gl|goo\.gl|google\.[a-z.]+/maps)~i', $lower) || preg_match('~\.(html?|php)(?:$|/)~i', $path)) {
        return ['state' => 'broken', 'reason' => 'Link trang web, không phải file ảnh'];
    }
    if (str_contains($lower, 'googleusercontent.com/gps-cs-s/') || str_contains($lower, 'googleusercontent.com/grass-cs/')) {
        return ['state' => 'warning', 'reason' => 'Link Google Maps phiên tạm (dễ 403)'];
    }
    if (preg_match('~(?:^|[/._-])(logo|banner|avatar|favicon|icon|poster)(?:[/._-]|$|\d)~i', $lower) || str_contains($lower, 'ytimg.com')) {
        return ['state' => 'warning', 'reason' => 'Ảnh Logo / Banner / Thumbnail'];
    }
    return null;
}

/**
 * Lightweight 64 KB header probe for a single URL.
 * Downloads at most the first 64 KB to verify HTTP status, MIME type, and dimensions without GD decoding.
 */
function medical_image_audit_probe_64kb(string $url, int $timeout = 4): array
{
    $static = medical_image_audit_static_check($url);
    if ($static !== null && $static['state'] === 'broken') {
        return ['url' => $url, 'state' => 'broken', 'http_status' => null, 'reason' => $static['reason']];
    }

    if (str_starts_with($url, '/')) {
        $rel = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        $full = medical_media_project_root() . '/' . ltrim($rel, '/');
        $info = @getimagesize($full);
        if (!is_array($info)) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => 200, 'reason' => 'File nội bộ hỏng định dạng ảnh'];
        }
        $w = (int) ($info[0] ?? 0);
        $h = (int) ($info[1] ?? 0);
        return [
            'url' => $url,
            'state' => ($w < 120 || $h < 90) ? 'warning' : 'ok',
            'http_status' => 200,
            'reason' => ($w < 120 || $h < 90) ? "Ảnh nhỏ ({$w}×{$h}px)" : "OK ({$w}×{$h}px)",
        ];
    }

    if (!function_exists('curl_init')) {
        return ['url' => $url, 'state' => $static['state'] ?? 'ok', 'http_status' => null, 'reason' => $static['reason'] ?? 'Chưa kiểm tra cURL'];
    }

    $current = $url;
    $maxBytes = 65536; // 64 KB limit

    for ($redir = 0; $redir <= 3; $redir++) {
        $parts = parse_url($current);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || !medical_media_is_public_host($host)) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => null, 'reason' => 'Tên miền ảnh không tồn tại / lỗi DNS'];
        }

        $bytes = '';
        $location = '';
        $abortedEarly = false;
        $ch = curl_init($current);
        if ($ch === false) {
            return ['url' => $url, 'state' => 'warning', 'http_status' => null, 'reason' => 'Không khởi tạo được cURL'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => max(2, $timeout),
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MedReviewImageAudit/1.0)',
            CURLOPT_HTTPHEADER => [
                'Accept: image/avif,image/webp,image/apng,image/jpeg,image/png,image/gif;q=0.9,*/*;q=0.1',
                'Range: bytes=0-65535',
            ],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$bytes, &$abortedEarly, $maxBytes): int {
                $bytes .= $chunk;
                if (strlen($bytes) >= $maxBytes) {
                    $abortedEarly = true;
                    return 0;
                }
                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $mime = strtolower(trim((string) strtok((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE), ';')));
        $err = (string) curl_error($ch);
        curl_close($ch);

        if ($status >= 300 && $status < 400 && $location !== '') {
            $next = medical_media_redirect_url($current, $location);
            if ($next === null) {
                return ['url' => $url, 'state' => 'broken', 'http_status' => $status, 'reason' => 'Redirect lỗi'];
            }
            $current = $next;
            continue;
        }

        if (in_array($status, [404, 410], true)) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => $status, 'reason' => "HTTP {$status} (Không tìm thấy ảnh)"];
        }
        if (in_array($status, [401, 403, 408, 429], true) || $status >= 500) {
            return ['url' => $url, 'state' => 'warning', 'http_status' => $status, 'reason' => "HTTP {$status} (Bị chặn/quá tải)"];
        }
        if ($status < 200 || $status >= 300 || $bytes === '') {
            return [
                'url' => $url,
                'state' => $status === 0 ? 'broken' : 'warning',
                'http_status' => $status > 0 ? $status : null,
                'reason' => $status > 0 ? "HTTP {$status}" : ('Không kết nối được: ' . ($err ?: 'Timeout')),
            ];
        }
        if (str_contains($mime, 'text/html') || str_contains($mime, 'application/xhtml')) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => $status, 'reason' => 'Link trả về trang HTML thay vì ảnh'];
        }

        $info = @getimagesizefromstring($bytes);
        if (is_array($info)) {
            $w = (int) ($info[0] ?? 0);
            $h = (int) ($info[1] ?? 0);
            if ($w > 0 && $h > 0 && ($w < 120 || $h < 90)) {
                return ['url' => $url, 'state' => 'warning', 'http_status' => $status, 'reason' => "Ảnh quá nhỏ ({$w}×{$h}px)"];
            }
            if ($static !== null) {
                return ['url' => $url, 'state' => $static['state'], 'http_status' => $status, 'reason' => $static['reason']];
            }
            return ['url' => $url, 'state' => 'ok', 'http_status' => $status, 'reason' => "OK ({$w}×{$h}px)"];
        }

        $magicOk = str_starts_with($bytes, "\xFF\xD8\xFF")
            || str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP')
            || str_starts_with($bytes, 'GIF8')
            || ($abortedEarly && str_starts_with($mime, 'image/'));

        if ($magicOk) {
            if ($static !== null) {
                return ['url' => $url, 'state' => $static['state'], 'http_status' => $status, 'reason' => $static['reason']];
            }
            return ['url' => $url, 'state' => 'ok', 'http_status' => $status, 'reason' => 'OK (64KB Header hợp lệ)'];
        }

        return ['url' => $url, 'state' => 'broken', 'http_status' => $status, 'reason' => 'Dữ liệu không phải file ảnh hợp lệ'];
    }

    return ['url' => $url, 'state' => 'broken', 'http_status' => null, 'reason' => 'Redirect quá nhiều lần'];
}

/** Build the error report list for a row (combining saved DB scan JSON + static checks if unscanned). */
function medical_image_audit_build_row_report(array $row): array
{
    $cover = trim((string) ($row['image_url'] ?? ''));
    $urls = medical_image_audit_extract_urls($row);
    $scanJson = is_string($row['image_scan_json'] ?? null) ? json_decode($row['image_scan_json'], true) : null;
    $savedResults = is_array($scanJson['url_results'] ?? null) ? $scanJson['url_results'] : [];

    $errors = [];
    if ($cover === '' && count($urls) === 0) {
        $errors[] = ['severity' => 'danger', 'reason' => 'Trống hoàn toàn ảnh (0 ảnh)', 'url' => ''];
    } elseif ($cover === '') {
        $errors[] = ['severity' => 'warning', 'reason' => 'Thiếu ảnh đại diện (image_url trống)', 'url' => ''];
    }

    foreach ($urls as $u) {
        if (isset($savedResults[$u]) && is_array($savedResults[$u])) {
            $st = (string) ($savedResults[$u]['state'] ?? 'ok');
            if ($st === 'broken' || $st === 'warning') {
                $errors[] = [
                    'severity' => $st === 'broken' ? 'danger' : 'warning',
                    'reason' => (string) ($savedResults[$u]['reason'] ?? 'Lỗi ảnh'),
                    'url' => $u,
                ];
            }
            continue;
        }
        $static = medical_image_audit_static_check($u);
        if ($static !== null) {
            $errors[] = [
                'severity' => $static['state'] === 'broken' ? 'danger' : 'warning',
                'reason' => $static['reason'],
                'url' => $u,
            ];
        }
    }

    return $errors;
}

function medical_image_audit_summary(PDO $pdo, string $table, string $entityType, bool $schemaReady): array
{
    $writtenWhere = medical_image_audit_written_where($pdo, $table, $entityType);
    if (!$schemaReady) {
        $total = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$writtenWhere}")->fetchColumn();
        return ['total' => $total, 'scanned' => 0, 'unscanned' => $total, 'broken' => 0, 'ok' => 0];
    }
    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN image_scanned_at IS NOT NULL AND image_scan_status <> 'unscanned' THEN 1 ELSE 0 END) AS scanned,
            SUM(CASE WHEN image_scanned_at IS NULL OR image_scan_status = 'unscanned' THEN 1 ELSE 0 END) AS unscanned,
            SUM(CASE WHEN image_scanned_at IS NOT NULL AND image_scan_status IN ('broken', 'warning') THEN 1 ELSE 0 END) AS broken,
            SUM(CASE WHEN image_scanned_at IS NOT NULL AND image_scan_status IN ('ok', 'scanned') THEN 1 ELSE 0 END) AS ok
         FROM `{$table}`
         WHERE {$writtenWhere}"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'total' => (int) ($row['total'] ?? 0),
        'scanned' => (int) ($row['scanned'] ?? 0),
        'unscanned' => (int) ($row['unscanned'] ?? 0),
        'broken' => (int) ($row['broken'] ?? 0),
        'ok' => (int) ($row['ok'] ?? 0),
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $entityType = strtolower(trim((string) ($_GET['entity_type'] ?? 'facility')));
        if (!in_array($entityType, ['facility', 'doctor'], true)) {
            $entityType = 'facility';
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 20;

        $table = $entityType === 'doctor' ? 'medical_doctors' : 'medical_facilities';
        $schemaReady = medical_image_audit_ensure_schema($pdo, $table);
        $hasLangCol = medical_image_audit_has_col($pdo, $table, 'language_code');
        $writtenWhere = medical_image_audit_written_where($pdo, $table, $entityType);

        $summary = medical_image_audit_summary($pdo, $table, $entityType, $schemaReady);
        $total = $summary['total'];
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        $scanCols = $schemaReady ? ', image_scan_status, image_scanned_at, image_scan_json' : '';
        $langCol = $hasLangCol ? ', language_code' : '';
        $subtitleCol = $entityType === 'doctor' ? 'title_text' : 'category';

        $stmt = $pdo->prepare(
            "SELECT id, slug, name, {$subtitleCol} AS subtitle, city, status, image_url, gallery_json{$langCol}{$scanCols}
             FROM `{$table}`
             WHERE {$writtenWhere}
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $locale = strtolower(trim((string) ($row['language_code'] ?? 'vi'))) === 'en' ? 'en' : 'vi';
            $slug = (string) ($row['slug'] ?? '');
            $scannedAt = trim((string) ($row['image_scanned_at'] ?? ''));
            $rawStatus = strtolower(trim((string) ($row['image_scan_status'] ?? 'unscanned')));
            $isScanned = $scannedAt !== '' && $rawStatus !== 'unscanned';
            $urls = medical_image_audit_extract_urls($row);
            $errors = medical_image_audit_build_row_report($row);

            $items[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? ''),
                'subtitle' => (string) ($row['subtitle'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'images_count' => count($urls),
                'is_scanned' => $isScanned,
                'image_scan_status' => $isScanned ? $rawStatus : 'unscanned',
                'image_scanned_at' => $isScanned ? $scannedAt : null,
                'errors' => $errors,
                'public_url' => site_url(medical_public_entity_path($entityType, $slug, $locale)),
                'edit_url' => admin_url(($entityType === 'doctor' ? 'medical_doctor_edit.php' : 'medical_facility_edit.php') . '?id=' . $id),
            ];
        }

        json_response([
            'ok' => true,
            'schema_ready' => $schemaReady,
            'entity_type' => $entityType,
            'summary' => $summary,
            'paging' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'items' => $items,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = read_json_body();
        $action = trim((string) ($body['action'] ?? ''));
        $entityType = strtolower(trim((string) ($body['entity_type'] ?? 'facility')));
        if (!in_array($entityType, ['facility', 'doctor'], true)) {
            $entityType = 'facility';
        }
        $table = $entityType === 'doctor' ? 'medical_doctors' : 'medical_facilities';
        $schemaReady = medical_image_audit_ensure_schema($pdo, $table);

        if (!$schemaReady) {
            json_response(['ok' => false, 'message' => 'Không thể khởi tạo các cột đánh dấu quét trong DB.'], 503);
        }

        $writtenWhere = medical_image_audit_written_where($pdo, $table, $entityType);

        if ($action === 'scan_next_batch') {
            $batchSize = min(5, max(1, (int) ($body['batch_size'] ?? 2)));
            $stmt = $pdo->prepare(
                "SELECT id, name, image_url, gallery_json
                 FROM `{$table}`
                 WHERE ({$writtenWhere})
                   AND (image_scanned_at IS NULL OR image_scan_status = 'unscanned')
                 ORDER BY id ASC
                 LIMIT :limit"
            );
            $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $now = gmdate('Y-m-d H:i:s');
            $upd = $pdo->prepare(
                "UPDATE `{$table}`
                 SET image_scan_status = :status,
                     image_scanned_at = :scanned_at,
                     image_scan_json = :scan_json
                 WHERE id = :id"
            );

            $processed = [];
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $cover = trim((string) ($row['image_url'] ?? ''));
                $urls = array_slice(medical_image_audit_extract_urls($row), 0, 8);

                $urlResults = [];
                $brokenCount = 0;
                $warningCount = 0;
                $okCount = 0;

                foreach ($urls as $u) {
                    $probe = medical_image_audit_probe_64kb($u, 4);
                    $urlResults[$u] = $probe;
                    if ($probe['state'] === 'broken') {
                        $brokenCount++;
                    } elseif ($probe['state'] === 'warning') {
                        $warningCount++;
                    } else {
                        $okCount++;
                    }
                }

                if ($cover === '' || count($urls) === 0 || $brokenCount > 0) {
                    $finalStatus = 'broken';
                } elseif ($warningCount > 0) {
                    $finalStatus = 'warning';
                } else {
                    $finalStatus = 'ok';
                }

                $payload = json_encode([
                    'status' => $finalStatus,
                    'scanned_at' => $now,
                    'images_count' => count($urls),
                    'broken_count' => $brokenCount,
                    'warning_count' => $warningCount,
                    'ok_count' => $okCount,
                    'url_results' => $urlResults,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $upd->execute([
                    ':status' => $finalStatus,
                    ':scanned_at' => $now,
                    ':scan_json' => $payload ?: '{}',
                    ':id' => $id,
                ]);
                $processed[] = [
                    'id' => $id,
                    'name' => (string) ($row['name'] ?? ''),
                    'status' => $finalStatus,
                    'images_count' => count($urls),
                    'broken_count' => $brokenCount,
                    'warning_count' => $warningCount,
                    'scanned_at' => $now,
                ];
            }

            $summary = medical_image_audit_summary($pdo, $table, $entityType, true);
            json_response([
                'ok' => true,
                'done' => count($rows) === 0 || $summary['unscanned'] === 0,
                'processed' => $processed,
                'summary' => $summary,
            ]);
        }

        if ($action === 'remove_image') {
            $id = (int) ($body['id'] ?? 0);
            $targetUrl = trim((string) ($body['url'] ?? ''));
            if ($id <= 0 || $targetUrl === '') {
                json_response(['ok' => false, 'message' => 'Thiếu ID hoặc URL ảnh cần xoá.'], 422);
            }
            $stmt = $pdo->prepare("SELECT image_url, gallery_json FROM `{$table}` WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                json_response(['ok' => false, 'message' => 'Không tìm thấy hồ sơ.'], 404);
            }
            $decoded = json_decode((string) ($row['gallery_json'] ?: '[]'), true);
            $nextGallery = [];
            $remainingUrls = [];
            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    $u = is_array($entry) ? trim((string) ($entry['url'] ?? $entry['src'] ?? '')) : trim((string) $entry);
                    if ($u === '' || $u === $targetUrl) continue;
                    $nextGallery[] = $entry;
                    $remainingUrls[] = $u;
                }
            }
            $cover = trim((string) ($row['image_url'] ?? ''));
            if ($cover === $targetUrl) {
                $cover = $remainingUrls[0] ?? '';
            }
            $upd = $pdo->prepare(
                "UPDATE `{$table}`
                 SET image_url = :cover, gallery_json = :gallery, image_scan_status = 'unscanned', image_scanned_at = NULL, image_scan_json = NULL
                 WHERE id = :id"
            );
            $upd->execute([
                ':cover' => $cover,
                ':gallery' => json_encode($nextGallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
                ':id' => $id,
            ]);
            medical_search_cache_invalidate();
            json_response(['ok' => true, 'message' => 'Đã xoá URL ảnh lỗi khỏi hồ sơ #' . $id]);
        }

        if ($action === 'toggle_row_scan') {
            $id = (int) ($body['id'] ?? 0);
            if ($id <= 0) {
                json_response(['ok' => false, 'message' => 'ID không hợp lệ.'], 422);
            }
            $stmt = $pdo->prepare(
                "UPDATE `{$table}`
                 SET image_scan_status = 'unscanned', image_scanned_at = NULL, image_scan_json = NULL
                 WHERE id = :id"
            );
            $stmt->execute([':id' => $id]);
            json_response([
                'ok' => true,
                'summary' => medical_image_audit_summary($pdo, $table, $entityType, true),
            ]);
        }

        if ($action === 'reset_all_scans') {
            $pdo->exec("UPDATE `{$table}` SET image_scan_status = 'unscanned', image_scanned_at = NULL, image_scan_json = NULL WHERE {$writtenWhere}");
            json_response([
                'ok' => true,
                'message' => 'Đã đặt lại toàn bộ hồ sơ có bài viết về trạng thái Chưa quét.',
                'summary' => medical_image_audit_summary($pdo, $table, $entityType, true),
            ]);
        }

        json_response(['ok' => false, 'message' => 'Thao tác không hợp lệ.'], 422);
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Lỗi xử lý: ' . $e->getMessage()], 500);
}

json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
