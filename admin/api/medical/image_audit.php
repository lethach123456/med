<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../schema_migrations.php';
require_once __DIR__ . '/../../../medical_directory.php';
require_once __DIR__ . '/../../../medical_media_library.php';
require_once __DIR__ . '/../../../medical_media_worker.php';
require_once __DIR__ . '/../../../medical_facility_image_fix.php';

admin_require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_json();
}
// Unlock session early so continuous full-system batch scans never block other tabs.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = db();

/** Check whether the DB scan-tracking columns exist on the target table. */
function medical_image_audit_schema_ready(PDO $pdo, string $table): bool
{
    return db_has_column($pdo, $table, 'image_scan_status')
        && db_has_column($pdo, $table, 'image_scanned_at')
        && db_has_column($pdo, $table, 'image_scan_json');
}

/**
 * Decode gallery_json safely into an array of normalized entries.
 *
 * @return array<int,array{url:string,caption:string,source:string,raw:mixed}>
 */
function medical_image_audit_decode_gallery(mixed $raw): array
{
    $decoded = is_array($raw) ? $raw : json_decode((string) ($raw ?: '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }
    $items = [];
    foreach ($decoded as $entry) {
        if (is_string($entry)) {
            $url = trim($entry);
            if ($url !== '') {
                $items[] = ['url' => $url, 'caption' => '', 'source' => '', 'raw' => $entry];
            }
            continue;
        }
        if (is_array($entry)) {
            $url = trim((string) ($entry['url'] ?? $entry['src'] ?? ''));
            if ($url !== '') {
                $items[] = [
                    'url' => $url,
                    'caption' => trim((string) ($entry['caption'] ?? $entry['angle'] ?? '')),
                    'source' => trim((string) ($entry['source'] ?? '')),
                    'raw' => $entry,
                ];
            }
        }
    }
    return $items;
}

/**
 * Inspect a single URL using static pattern rules + local file existence + worker failure map.
 *
 * @param array<string,string> $failedJobsMap
 * @return array<int,array{code:string,severity:string,label:string}>
 */
function medical_image_audit_classify_url(string $url, array $failedJobsMap = []): array
{
    $url = trim($url);
    $issues = [];
    if ($url === '') {
        return $issues;
    }

    $lower = strtolower($url);
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

    if (str_starts_with($url, '/')) {
        $relativePath = rawurldecode($path);
        if (str_contains($relativePath, '..') || str_contains($relativePath, "\0")) {
            $issues[] = ['code' => 'missing_local', 'severity' => 'danger', 'label' => 'Đường dẫn nội bộ không hợp lệ'];
        } else {
            $fullPath = medical_media_project_root() . '/' . ltrim($relativePath, '/');
            if (!is_file($fullPath) || (int) (@filesize($fullPath) ?: 0) <= 0) {
                $issues[] = ['code' => 'missing_local', 'severity' => 'danger', 'label' => 'Mất file trên máy chủ'];
            }
        }
        return $issues;
    }

    if (!preg_match('~^https?://~i', $url)) {
        $issues[] = ['code' => 'invalid_url', 'severity' => 'danger', 'label' => 'URL không đúng chuẩn HTTP/HTTPS'];
        return $issues;
    }

    if (str_contains($lower, 'lookaside.fbsbx.com')) {
        $issues[] = ['code' => 'fbsbx', 'severity' => 'danger', 'label' => 'Link Facebook crawler (vỡ trên trình duyệt)'];
    }
    if (str_contains($lower, 'googleusercontent.com/gps-cs-s/') || str_contains($lower, 'googleusercontent.com/grass-cs/')) {
        $issues[] = ['code' => 'google_temp', 'severity' => 'warning', 'label' => 'Link Google Maps phiên tạm (dễ 403)'];
    }
    if (preg_match('~(maps\.app\.goo\.gl|goo\.gl|google\.[a-z.]+/maps)~i', $lower) || preg_match('~\.(html?|php)(?:$|/)~i', $path)) {
        $issues[] = ['code' => 'not_direct_image', 'severity' => 'danger', 'label' => 'Link trang web, không phải file ảnh'];
    }
    if (preg_match('~(?:^|[/._-])(logo|banner|avatar|favicon|icon|poster)(?:[/._-]|$|\d)~i', $lower) || str_contains($lower, 'ytimg.com') || str_contains($lower, 'youtube.com')) {
        $issues[] = ['code' => 'logo_banner', 'severity' => 'warning', 'label' => 'Nghi là Logo / Banner / Thumbnail'];
    }
    if (preg_match('~/wp-content/uploads/2026/~i', $lower)) {
        $issues[] = ['code' => 'suspicious_2026', 'severity' => 'warning', 'label' => 'Link /uploads/2026/ (nghi 404)'];
    }
    if (isset($failedJobsMap[$url])) {
        $issues[] = ['code' => 'worker_failed', 'severity' => 'danger', 'label' => 'Worker tải lỗi: ' . $failedJobsMap[$url]];
    }

    return $issues;
}

/**
 * Evaluate a single facility or doctor row, combining static/local rules and persisted DB scan data.
 *
 * @param array<string,mixed> $row
 * @param array<string,string> $failedJobsMap
 * @return array<string,mixed>
 */
function medical_image_audit_evaluate_row(string $entityType, array $row, array $failedJobsMap = []): array
{
    $id = (int) ($row['id'] ?? 0);
    $cover = trim((string) ($row['image_url'] ?? ''));
    $aiCover = $entityType === 'facility' ? trim((string) ($row['ai_image_url'] ?? '')) : '';
    $galleryEntries = medical_image_audit_decode_gallery($row['gallery_json'] ?? '[]');

    $scanStatus = strtolower(trim((string) ($row['image_scan_status'] ?? 'unscanned')));
    if (!in_array($scanStatus, ['unscanned', 'ok', 'warning', 'broken'], true)) {
        $scanStatus = 'unscanned';
    }
    $scannedAt = trim((string) ($row['image_scanned_at'] ?? ''));
    $scanJson = medical_directory_json_decode($row['image_scan_json'] ?? null);
    $probedByUrl = is_array($scanJson['url_results'] ?? null) ? $scanJson['url_results'] : [];

    $imagesByUrl = [];
    $register = static function (string $url, string $field, string $caption = '', string $source = '') use (&$imagesByUrl, $failedJobsMap, $probedByUrl): void {
        $url = trim($url);
        if ($url === '') {
            return;
        }
        if (!isset($imagesByUrl[$url])) {
            $issues = medical_image_audit_classify_url($url, $failedJobsMap);
            $probe = is_array($probedByUrl[$url] ?? null) ? $probedByUrl[$url] : null;
            if (is_array($probe)) {
                $pState = (string) ($probe['state'] ?? '');
                $pReason = trim((string) ($probe['reason'] ?? ''));
                if ($pState === 'broken') {
                    $issues[] = ['code' => 'http_broken', 'severity' => 'danger', 'label' => 'Quét HTTP: ' . ($pReason ?: 'Ảnh lỗi')];
                } elseif ($pState === 'warning' && $pReason !== '') {
                    $issues[] = ['code' => 'http_warning', 'severity' => 'warning', 'label' => 'Quét HTTP: ' . $pReason];
                }
            }
            $imagesByUrl[$url] = [
                'url' => $url,
                'is_local' => str_starts_with($url, '/'),
                'fields' => [],
                'caption' => $caption,
                'source' => $source,
                'issues' => $issues,
                'probe' => $probe,
            ];
        }
        $imagesByUrl[$url]['fields'][] = $field;
        if ($caption !== '' && $imagesByUrl[$url]['caption'] === '') {
            $imagesByUrl[$url]['caption'] = $caption;
        }
        if ($source !== '' && $imagesByUrl[$url]['source'] === '') {
            $imagesByUrl[$url]['source'] = $source;
        }
    };

    $register($cover, 'image_url');
    if ($aiCover !== '') {
        $register($aiCover, 'ai_image_url');
    }
    foreach ($galleryEntries as $idx => $entry) {
        $register($entry['url'], 'gallery_json[' . $idx . ']', $entry['caption'], $entry['source']);
    }

    $images = array_values($imagesByUrl);
    $issueCodes = [];
    $rowIssues = [];

    if ($cover === '' && count($images) === 0) {
        $issueCodes['empty'] = true;
        $rowIssues[] = ['code' => 'empty', 'severity' => 'danger', 'label' => 'Trống hoàn toàn ảnh (0 ảnh)'];
    } elseif ($cover === '') {
        $issueCodes['empty'] = true;
        $rowIssues[] = ['code' => 'empty', 'severity' => 'warning', 'label' => 'Thiếu ảnh đại diện (image_url trống)'];
    }

    $hasRemote = false;
    foreach ($images as $img) {
        if (!$img['is_local']) {
            $hasRemote = true;
        }
        foreach ($img['issues'] as $iss) {
            $code = (string) $iss['code'];
            $issueCodes[$code] = true;
        }
    }
    if ($hasRemote) {
        $issueCodes['remote_unimported'] = true;
    }

    if ($scanStatus === 'unscanned' || $scannedAt === '') {
        $issueCodes['unscanned'] = true;
    } elseif ($scanStatus === 'broken') {
        $issueCodes['scanned_broken'] = true;
    } elseif ($scanStatus === 'warning') {
        $issueCodes['scanned_warning'] = true;
    } elseif ($scanStatus === 'ok') {
        $issueCodes['scanned_ok'] = true;
    }

    $fixAudit = $entityType === 'facility' ? medical_directory_json_decode($row['image_fix_json'] ?? null) : null;
    $lastCheckedAt = is_array($fixAudit) ? (string) ($fixAudit['checked_at'] ?? '') : '';

    $hasStrictIssue = $scanStatus === 'broken'
        || $scanStatus === 'warning'
        || isset($issueCodes['empty'])
        || isset($issueCodes['fbsbx'])
        || isset($issueCodes['google_temp'])
        || isset($issueCodes['logo_banner'])
        || isset($issueCodes['suspicious_2026'])
        || isset($issueCodes['missing_local'])
        || isset($issueCodes['worker_failed'])
        || isset($issueCodes['not_direct_image'])
        || isset($issueCodes['invalid_url'])
        || isset($issueCodes['http_broken']);

    $locale = strtolower(trim((string) ($row['language_code'] ?? 'vi'))) === 'en' ? 'en' : 'vi';
    $slug = (string) ($row['slug'] ?? '');
    $publicPath = medical_public_entity_path($entityType === 'doctor' ? 'doctor' : 'facility', $slug, $locale);
    $editPath = admin_url(($entityType === 'doctor' ? 'medical_doctor_edit.php' : 'medical_facility_edit.php') . '?id=' . $id);

    return [
        'id' => $id,
        'entity_type' => $entityType,
        'slug' => $slug,
        'name' => (string) ($row['name'] ?? ''),
        'subtitle' => $entityType === 'doctor'
            ? (string) ($row['title_text'] ?? $row['specialty_text'] ?? '')
            : (string) ($row['category'] ?? ''),
        'city' => (string) ($row['city'] ?? ''),
        'address' => $entityType === 'doctor'
            ? (string) ($row['facility_name'] ?? '')
            : (string) ($row['address_text'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'language_code' => $locale,
        'image_url' => $cover,
        'ai_image_url' => $aiCover,
        'images_count' => count($images),
        'gallery_count' => count($galleryEntries),
        'images' => $images,
        'row_issues' => $rowIssues,
        'issue_codes' => array_keys($issueCodes),
        'has_issue' => $hasStrictIssue,
        'image_scan_status' => $scannedAt !== '' ? $scanStatus : 'unscanned',
        'image_scanned_at' => $scannedAt !== '' ? $scannedAt : null,
        'last_image_fix_at' => $lastCheckedAt !== '' ? $lastCheckedAt : null,
        'public_url' => site_url($publicPath),
        'edit_url' => $editPath,
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

/**
 * Lightweight 64 KB header-range probe for a single remote URL.
 * Reads at most 64 KB to inspect HTTP status, MIME type, and image dimensions
 * without downloading full multi-megabyte files or invoking GD bitmap decoding.
 *
 * @return array{url:string,state:string,http_status:?int,mime?:string,width?:int,height?:int,reason:string}
 */
function medical_image_audit_lightweight_probe_single(string $url, int $timeoutSeconds = 4): array
{
    $url = trim($url);
    $lower = strtolower($url);
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

    if (str_starts_with($url, '/')) {
        $relativePath = rawurldecode($path);
        if (str_contains($relativePath, '..') || str_contains($relativePath, "\0")) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => 400, 'reason' => 'Đường dẫn nội bộ không hợp lệ'];
        }
        $fullPath = medical_media_project_root() . '/' . ltrim($relativePath, '/');
        if (!is_file($fullPath) || (int) (@filesize($fullPath) ?: 0) <= 0) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => 404, 'reason' => 'Không tìm thấy file nội bộ trên ổ cứng'];
        }
        $info = @getimagesize($fullPath);
        if (!is_array($info)) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => 200, 'reason' => 'File nội bộ hỏng định dạng ảnh'];
        }
        $w = (int) ($info[0] ?? 0);
        $h = (int) ($info[1] ?? 0);
        return [
            'url' => $url,
            'state' => ($w < 120 || $h < 90) ? 'warning' : 'ok',
            'http_status' => 200,
            'mime' => (string) ($info['mime'] ?? 'image/jpeg'),
            'width' => $w,
            'height' => $h,
            'reason' => ($w < 120 || $h < 90) ? "Ảnh nhỏ ({$w}×{$h}px)" : "OK ({$w}×{$h}px)",
        ];
    }

    if (str_contains($lower, 'lookaside.fbsbx.com')) {
        return ['url' => $url, 'state' => 'broken', 'http_status' => 403, 'reason' => 'Link Facebook crawler (vỡ trên trình duyệt)'];
    }
    if (preg_match('~(maps\.app\.goo\.gl|goo\.gl|google\.[a-z.]+/maps)~i', $lower) || preg_match('~\.(html?|php)(?:$|/)~i', $path)) {
        return ['url' => $url, 'state' => 'broken', 'http_status' => 422, 'reason' => 'Link trang web, không phải file ảnh'];
    }

    if (!function_exists('curl_init')) {
        return ['url' => $url, 'state' => 'warning', 'http_status' => null, 'reason' => 'Máy chủ chưa bật cURL'];
    }

    $current = $url;
    $maxHeaderBytes = 65536; // 64 KB is enough for JPEG/PNG/WebP/GIF headers & dimensions.

    for ($redir = 0; $redir <= 3; $redir++) {
        $parts = parse_url($current);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || !medical_media_is_public_host($host)) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => null, 'reason' => 'Host không hợp lệ hoặc không phân giải được DNS'];
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
            CURLOPT_TIMEOUT => max(2, $timeoutSeconds),
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
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$bytes, &$abortedEarly, $maxHeaderBytes): int {
                $bytes .= $chunk;
                if (strlen($bytes) >= $maxHeaderBytes) {
                    $abortedEarly = true;
                    return 0; // Stop transfer immediately after 64 KB!
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
                return ['url' => $url, 'state' => 'broken', 'http_status' => $status, 'reason' => 'Redirect không hợp lệ'];
            }
            $current = $next;
            continue;
        }

        if (in_array($status, [404, 410], true)) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => $status, 'reason' => "HTTP {$status} (Ảnh không tồn tại)"];
        }
        if (in_array($status, [401, 403, 408, 429], true) || $status >= 500) {
            return ['url' => $url, 'state' => 'warning', 'http_status' => $status, 'reason' => "HTTP {$status} (Máy chủ đích chặn/tạm lỗi)"];
        }
        if ($status < 200 || $status >= 300 || $bytes === '') {
            return [
                'url' => $url,
                'state' => $status === 0 ? 'broken' : 'warning',
                'http_status' => $status > 0 ? $status : null,
                'reason' => $status > 0 ? "HTTP {$status}" : ('Lỗi kết nối: ' . ($err ?: 'Không phản hồi')),
            ];
        }

        if (str_contains($mime, 'text/html') || str_contains($mime, 'application/xhtml')) {
            return ['url' => $url, 'state' => 'broken', 'http_status' => $status, 'mime' => $mime, 'reason' => 'Trả về trang HTML thay vì ảnh'];
        }

        $info = @getimagesizefromstring($bytes);
        if (is_array($info)) {
            $w = (int) ($info[0] ?? 0);
            $h = (int) ($info[1] ?? 0);
            $isTiny = ($w > 0 && $h > 0 && ($w < 120 || $h < 90));
            return [
                'url' => $url,
                'state' => $isTiny ? 'warning' : 'ok',
                'http_status' => $status,
                'mime' => (string) ($info['mime'] ?? $mime),
                'width' => $w,
                'height' => $h,
                'reason' => $isTiny ? "Ảnh quá nhỏ ({$w}×{$h}px)" : "OK ({$w}×{$h}px)",
            ];
        }

        // If aborted at 64 KB and magic bytes match JPEG/PNG/WebP/GIF, accept as valid image.
        $magicOk = str_starts_with($bytes, "\xFF\xD8\xFF")
            || str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP')
            || str_starts_with($bytes, 'GIF87a')
            || str_starts_with($bytes, 'GIF89a')
            || ($abortedEarly && str_starts_with($mime, 'image/'));

        if ($magicOk) {
            return [
                'url' => $url,
                'state' => 'ok',
                'http_status' => $status,
                'mime' => $mime !== '' ? $mime : 'image/jpeg',
                'reason' => 'OK (Header ảnh hợp lệ)',
            ];
        }

        return [
            'url' => $url,
            'state' => 'broken',
            'http_status' => $status,
            'mime' => $mime,
            'reason' => 'Dữ liệu trả về không phải định dạng ảnh hợp lệ' . ($mime ? " ({$mime})" : ''),
        ];
    }

    return ['url' => $url, 'state' => 'broken', 'http_status' => null, 'reason' => 'Chuyển hướng quá nhiều lần'];
}

/**
 * Scan all URLs of a single facility/doctor row and persist `image_scan_status`,
 * `image_scanned_at`, and `image_scan_json` directly into the database.
 *
 * @param array<string,mixed> $row
 * @param array<string,string> $failedJobsMap
 * @return array<string,mixed>
 */
function medical_image_audit_scan_and_save_row(PDO $pdo, string $entityType, array $row, array $failedJobsMap = []): array
{
    $table = $entityType === 'doctor' ? 'medical_doctors' : 'medical_facilities';
    $id = (int) ($row['id'] ?? 0);
    $cover = trim((string) ($row['image_url'] ?? ''));
    $aiCover = $entityType === 'facility' ? trim((string) ($row['ai_image_url'] ?? '')) : '';
    $galleryEntries = medical_image_audit_decode_gallery($row['gallery_json'] ?? '[]');

    $urls = [];
    if ($cover !== '') $urls[$cover] = true;
    if ($aiCover !== '') $urls[$aiCover] = true;
    foreach ($galleryEntries as $entry) {
        if ($entry['url'] !== '') $urls[$entry['url']] = true;
    }
    $urlList = array_keys($urls);

    $urlResults = [];
    $brokenCount = 0;
    $warningCount = 0;
    $okCount = 0;

    // Limit remote HTTP probes per record to max 8 URLs so a giant gallery never stalls a batch.
    $remoteProbed = 0;
    foreach ($urlList as $u) {
        $staticIssues = medical_image_audit_classify_url($u, $failedJobsMap);
        $hasStaticDanger = false;
        $hasStaticWarn = false;
        foreach ($staticIssues as $iss) {
            if ($iss['severity'] === 'danger') $hasStaticDanger = true;
            if ($iss['severity'] === 'warning') $hasStaticWarn = true;
        }

        if (str_starts_with($u, '/') || $remoteProbed < 8) {
            if (!str_starts_with($u, '/')) $remoteProbed++;
            $probe = medical_image_audit_lightweight_probe_single($u, 4);
        } else {
            $probe = ['url' => $u, 'state' => 'ok', 'http_status' => null, 'reason' => 'Bỏ qua probe sâu (quá 8 ảnh/hồ sơ)'];
        }

        if ($hasStaticDanger || $probe['state'] === 'broken') {
            $probe['state'] = 'broken';
            if ($hasStaticDanger && $probe['reason'] === 'OK (Header ảnh hợp lệ)') {
                $probe['reason'] = $staticIssues[0]['label'];
            }
            $brokenCount++;
        } elseif ($hasStaticWarn || $probe['state'] === 'warning') {
            $probe['state'] = 'warning';
            if ($hasStaticWarn && str_starts_with((string) $probe['reason'], 'OK')) {
                $probe['reason'] = $staticIssues[0]['label'];
            }
            $warningCount++;
        } else {
            $okCount++;
        }

        $urlResults[$u] = $probe;
    }

    if ($cover === '' || count($urlList) === 0 || $brokenCount > 0) {
        $finalStatus = 'broken';
    } elseif ($warningCount > 0) {
        $finalStatus = 'warning';
    } else {
        $finalStatus = 'ok';
    }

    $now = gmdate('Y-m-d H:i:s');
    $scanPayload = [
        'status' => $finalStatus,
        'scanned_at' => $now,
        'total_urls' => count($urlList),
        'broken_count' => $brokenCount,
        'warning_count' => $warningCount,
        'ok_count' => $okCount,
        'empty_cover' => $cover === '',
        'url_results' => $urlResults,
    ];

    if (medical_image_audit_schema_ready($pdo, $table)) {
        $upd = $pdo->prepare(
            "UPDATE `{$table}`
             SET image_scan_status = :status,
                 image_scanned_at = :scanned_at,
                 image_scan_json = :scan_json
             WHERE id = :id"
        );
        $upd->execute([
            ':status' => $finalStatus,
            ':scanned_at' => $now,
            ':scan_json' => medical_directory_json_encode($scanPayload),
            ':id' => $id,
        ]);
    }

    return [
        'id' => $id,
        'name' => (string) ($row['name'] ?? ''),
        'status' => $finalStatus,
        'scanned_at' => $now,
        'broken_count' => $brokenCount,
        'warning_count' => $warningCount,
        'ok_count' => $okCount,
        'total_urls' => count($urlList),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $entityType = strtolower(trim((string) ($_GET['entity_type'] ?? 'facility')));
    if (!in_array($entityType, ['facility', 'doctor'], true)) {
        $entityType = 'facility';
    }
    $language = strtolower(trim((string) ($_GET['language'] ?? 'vi')));
    if (!in_array($language, ['vi', 'en', 'all'], true)) {
        $language = 'vi';
    }
    $statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'published')));
    if (!in_array($statusFilter, ['published', 'draft', 'all'], true)) {
        $statusFilter = 'published';
    }
    $issueFilter = strtolower(trim((string) ($_GET['issue'] ?? 'any_issue')));
    $allowedFilters = [
        'any_issue', 'unscanned', 'scanned_broken', 'scanned_warning', 'scanned_ok',
        'empty', 'fbsbx', 'google_temp', 'logo_banner',
        'suspicious_2026', 'missing_local', 'worker_failed', 'remote_unimported', 'all',
    ];
    if (!in_array($issueFilter, $allowedFilters, true)) {
        $issueFilter = 'any_issue';
    }
    $query = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(25, max(5, (int) ($_GET['limit'] ?? 12)));

    $table = $entityType === 'doctor' ? 'medical_doctors' : 'medical_facilities';
    $hasLangCol = db_has_column($pdo, $table, 'language_code');
    $hasAiImageCol = $entityType === 'facility' && db_has_column($pdo, $table, 'ai_image_url');
    $hasImageFixCol = $entityType === 'facility' && db_has_column($pdo, $table, 'image_fix_json');
    $schemaReady = medical_image_audit_schema_ready($pdo, $table);

    $failedByEntity = [];
    if (medical_directory_table_exists($pdo, 'medical_media_jobs')) {
        try {
            $jobStmt = $pdo->prepare(
                "SELECT entity_id, source_url, last_error
                 FROM medical_media_jobs
                 WHERE entity_type = :etype AND status = 'failed'"
            );
            $jobStmt->execute([':etype' => $entityType]);
            while (($j = $jobStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $eid = (int) ($j['entity_id'] ?? 0);
                $surl = trim((string) ($j['source_url'] ?? ''));
                if ($eid > 0 && $surl !== '') {
                    $failedByEntity[$eid][$surl] = trim((string) ($j['last_error'] ?? 'Lỗi tải ảnh'));
                }
            }
        } catch (Throwable) {
        }
    }

    $where = [];
    $params = [];
    if ($statusFilter !== 'all') {
        $where[] = 'status = :status';
        $params[':status'] = $statusFilter;
    }
    if ($hasLangCol && $language !== 'all') {
        if ($language === 'vi') {
            $where[] = "(language_code = 'vi' OR language_code IS NULL OR language_code = '')";
        } else {
            $where[] = 'language_code = :language';
            $params[':language'] = $language;
        }
    }
    if ($query !== '') {
        if (ctype_digit($query)) {
            $where[] = '(id = :exact_id OR name LIKE :q_name OR slug LIKE :q_slug)';
            $params[':exact_id'] = (int) $query;
            $params[':q_name'] = '%' . $query . '%';
            $params[':q_slug'] = '%' . $query . '%';
        } else {
            $where[] = '(name LIKE :q_name OR slug LIKE :q_slug OR city LIKE :q_city)';
            $params[':q_name'] = '%' . $query . '%';
            $params[':q_slug'] = '%' . $query . '%';
            $params[':q_city'] = '%' . $query . '%';
        }
    }

    $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';
    $scanCols = $schemaReady ? ', image_scan_status, image_scanned_at, image_scan_json' : '';
    $selectCols = $entityType === 'doctor'
        ? 'id, slug, name, title_text, specialty_text, facility_name, city, status, image_url, gallery_json, updated_at'
            . ($hasLangCol ? ', language_code' : '') . $scanCols
        : 'id, slug, name, category, city, address_text, status, image_url, gallery_json, updated_at'
            . ($hasLangCol ? ', language_code' : '')
            . ($hasAiImageCol ? ', ai_image_url' : '')
            . ($hasImageFixCol ? ', image_fix_json' : '') . $scanCols;

    $stmt = $pdo->prepare("SELECT {$selectCols} FROM {$table} {$whereSql} ORDER BY id ASC");
    $stmt->execute($params);
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $summary = [
        'all' => 0,
        'any_issue' => 0,
        'unscanned' => 0,
        'scanned_broken' => 0,
        'scanned_warning' => 0,
        'scanned_ok' => 0,
        'empty' => 0,
        'fbsbx' => 0,
        'google_temp' => 0,
        'logo_banner' => 0,
        'suspicious_2026' => 0,
        'missing_local' => 0,
        'worker_failed' => 0,
        'remote_unimported' => 0,
    ];

    $matchedItems = [];
    $matchedIds = [];
    foreach ($allRows as $row) {
        $eid = (int) ($row['id'] ?? 0);
        $evaluated = medical_image_audit_evaluate_row($entityType, $row, $failedByEntity[$eid] ?? []);
        $summary['all']++;
        if ($evaluated['has_issue']) {
            $summary['any_issue']++;
        }
        foreach ($evaluated['issue_codes'] as $code) {
            if (isset($summary[$code])) {
                $summary[$code]++;
            }
        }

        $matchesFilter = false;
        if ($issueFilter === 'all') {
            $matchesFilter = true;
        } elseif ($issueFilter === 'any_issue') {
            $matchesFilter = (bool) $evaluated['has_issue'];
        } else {
            $matchesFilter = in_array($issueFilter, $evaluated['issue_codes'], true);
        }

        if ($matchesFilter) {
            $matchedItems[] = $evaluated;
            $matchedIds[] = $eid;
        }
    }

    $total = count($matchedItems);
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;
    $pagedItems = array_slice($matchedItems, $offset, $limit);

    json_response([
        'ok' => true,
        'schema_ready' => $schemaReady,
        'entity_type' => $entityType,
        'language' => $language,
        'status' => $statusFilter,
        'issue' => $issueFilter,
        'query' => $query,
        'summary' => $summary,
        'matched_ids' => array_slice($matchedIds, 0, 300),
        'paging' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
        'items' => $pagedItems,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body();
    $action = trim((string) ($body['action'] ?? ''));

    // 1-Click DB schema migration for image_scan_* columns.
    if ($action === 'migrate_schema') {
        try {
            medreview_with_schema_migration(static function () use ($pdo): void {
                medreview_migrate_image_audit_columns($pdo);
            });
            json_response([
                'ok' => true,
                'schema_ready' => true,
                'message' => 'Đã thêm các cột image_scan_status, image_scanned_at, image_scan_json vào bảng cơ sở y tế và bác sĩ.',
            ]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'message' => 'Không thể tạo cột DB: ' . $e->getMessage()], 500);
        }
    }

    $entityType = strtolower(trim((string) ($body['entity_type'] ?? 'facility')));
    if (!in_array($entityType, ['facility', 'doctor'], true)) {
        $entityType = 'facility';
    }
    $table = $entityType === 'doctor' ? 'medical_doctors' : 'medical_facilities';

    // Full-system continuous batch scanner: picks the next unscanned records, probes them lightly, and marks them in DB.
    if ($action === 'scan_next_batch') {
        if (!medical_image_audit_schema_ready($pdo, $table)) {
            try {
                medreview_with_schema_migration(static function () use ($pdo): void {
                    medreview_migrate_image_audit_columns($pdo);
                });
            } catch (Throwable $e) {
                json_response(['ok' => false, 'message' => 'Chưa có cột đánh dấu quét trong DB: ' . $e->getMessage()], 503);
            }
        }

        $language = strtolower(trim((string) ($body['language'] ?? 'vi')));
        $statusFilter = strtolower(trim((string) ($body['status'] ?? 'published')));
        $batchSize = min(4, max(1, (int) ($body['batch_size'] ?? 2)));
        $afterId = max(0, (int) ($body['after_id'] ?? 0));
        $onlyUnscanned = !array_key_exists('only_unscanned', $body) || !empty($body['only_unscanned']);

        $hasLangCol = db_has_column($pdo, $table, 'language_code');
        $hasAiImageCol = $entityType === 'facility' && db_has_column($pdo, $table, 'ai_image_url');

        $baseWhere = [];
        $baseParams = [];
        if ($statusFilter !== 'all' && in_array($statusFilter, ['published', 'draft'], true)) {
            $baseWhere[] = 'status = :status';
            $baseParams[':status'] = $statusFilter;
        }
        if ($hasLangCol && $language !== 'all' && in_array($language, ['vi', 'en'], true)) {
            if ($language === 'vi') {
                $baseWhere[] = "(language_code = 'vi' OR language_code IS NULL OR language_code = '')";
            } else {
                $baseWhere[] = 'language_code = :language';
                $baseParams[':language'] = $language;
            }
        }

        $batchWhere = $baseWhere;
        $batchParams = $baseParams;
        if ($onlyUnscanned) {
            $batchWhere[] = "(image_scan_status = 'unscanned' OR image_scanned_at IS NULL)";
        }
        if ($afterId > 0) {
            $batchWhere[] = 'id > :after_id';
            $batchParams[':after_id'] = $afterId;
        }

        $batchWhereSql = $batchWhere !== [] ? ('WHERE ' . implode(' AND ', $batchWhere)) : '';
        $cols = 'id, name, image_url, gallery_json' . ($hasAiImageCol ? ', ai_image_url' : '');
        $stmt = $pdo->prepare("SELECT {$cols} FROM `{$table}` {$batchWhereSql} ORDER BY id ASC LIMIT {$batchSize}");
        $stmt->execute($batchParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $failedByEntity = [];
        if ($rows !== [] && medical_directory_table_exists($pdo, 'medical_media_jobs')) {
            try {
                $ids = array_map(static fn(array $r): int => (int) $r['id'], $rows);
                $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                $jStmt = $pdo->prepare(
                    "SELECT entity_id, source_url, last_error
                     FROM medical_media_jobs
                     WHERE entity_type = ? AND status = 'failed' AND entity_id IN ({$inPlaceholders})"
                );
                $jStmt->execute(array_merge([$entityType], $ids));
                while (($j = $jStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $failedByEntity[(int) $j['entity_id']][trim((string) $j['source_url'])] = trim((string) ($j['last_error'] ?? 'Lỗi tải ảnh'));
                }
            } catch (Throwable) {
            }
        }

        $processed = [];
        $lastId = $afterId;
        foreach ($rows as $row) {
            $eid = (int) ($row['id'] ?? 0);
            $lastId = max($lastId, $eid);
            $processed[] = medical_image_audit_scan_and_save_row($pdo, $entityType, $row, $failedByEntity[$eid] ?? []);
        }

        // Count global progress across the selected filter scope.
        $baseWhereSql = $baseWhere !== [] ? ('WHERE ' . implode(' AND ', $baseWhere)) : '';
        $statStmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_records,
                SUM(CASE WHEN image_scanned_at IS NOT NULL AND image_scan_status <> 'unscanned' THEN 1 ELSE 0 END) AS scanned_count,
                SUM(CASE WHEN image_scanned_at IS NULL OR image_scan_status = 'unscanned' THEN 1 ELSE 0 END) AS unscanned_count,
                SUM(CASE WHEN image_scanned_at IS NOT NULL AND image_scan_status = 'broken' THEN 1 ELSE 0 END) AS broken_count,
                SUM(CASE WHEN image_scanned_at IS NOT NULL AND image_scan_status = 'warning' THEN 1 ELSE 0 END) AS warning_count,
                SUM(CASE WHEN image_scanned_at IS NOT NULL AND image_scan_status = 'ok' THEN 1 ELSE 0 END) AS ok_count
             FROM `{$table}` {$baseWhereSql}"
        );
        $statStmt->execute($baseParams);
        $stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $unscannedRemaining = (int) ($stats['unscanned_count'] ?? 0);
        json_response([
            'ok' => true,
            'done' => count($rows) === 0 || ($onlyUnscanned && $unscannedRemaining === 0),
            'processed_count' => count($processed),
            'processed' => $processed,
            'next_after_id' => $onlyUnscanned ? 0 : $lastId,
            'progress' => [
                'total' => (int) ($stats['total_records'] ?? 0),
                'scanned' => (int) ($stats['scanned_count'] ?? 0),
                'unscanned' => $unscannedRemaining,
                'broken' => (int) ($stats['broken_count'] ?? 0),
                'warning' => (int) ($stats['warning_count'] ?? 0),
                'ok' => (int) ($stats['ok_count'] ?? 0),
            ],
        ]);
    }

    if ($action === 'reset_all_scans') {
        if (!medical_image_audit_schema_ready($pdo, $table)) {
            json_response(['ok' => false, 'message' => 'Chưa có cột đánh dấu quét trong DB.'], 422);
        }
        $pdo->exec("UPDATE `{$table}` SET image_scan_status = 'unscanned', image_scanned_at = NULL, image_scan_json = NULL");
        json_response([
            'ok' => true,
            'message' => 'Đã đặt lại trạng thái "Chưa quét" cho toàn bộ ' . ($entityType === 'doctor' ? 'bác sĩ' : 'cơ sở y tế') . '.',
        ]);
    }

    if ($action === 'probe_batch') {
        $urls = is_array($body['urls'] ?? null) ? array_slice($body['urls'], 0, 3) : [];
        $results = [];
        foreach ($urls as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $results[$u] = medical_image_audit_lightweight_probe_single($u, 4);
            }
        }
        json_response(['ok' => true, 'results' => $results]);
    }

    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'ID hồ sơ không hợp lệ.'], 422);
    }

    if ($action === 'remove_image') {
        $targetUrl = trim((string) ($body['url'] ?? ''));
        if ($targetUrl === '') {
            json_response(['ok' => false, 'message' => 'Thiếu URL ảnh cần xoá.'], 422);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $pdo->rollBack();
                json_response(['ok' => false, 'message' => 'Không tìm thấy hồ sơ.'], 404);
            }

            $rawGallery = $row['gallery_json'] ?? '[]';
            $decodedGallery = is_array($rawGallery) ? $rawGallery : json_decode((string) ($rawGallery ?: '[]'), true);
            if (!is_array($decodedGallery)) {
                $decodedGallery = [];
            }

            $nextGallery = [];
            $remainingUrls = [];
            foreach ($decodedGallery as $entry) {
                $entryUrl = is_array($entry) ? trim((string) ($entry['url'] ?? $entry['src'] ?? '')) : trim((string) $entry);
                if ($entryUrl === '' || $entryUrl === $targetUrl) {
                    continue;
                }
                $nextGallery[] = $entry;
                $remainingUrls[] = $entryUrl;
            }

            $cover = trim((string) ($row['image_url'] ?? ''));
            if ($cover === $targetUrl) {
                $cover = $remainingUrls[0] ?? '';
            }

            $sets = ['image_url = :image_url', 'gallery_json = :gallery_json'];
            $params = [
                ':id' => $id,
                ':image_url' => $cover,
                ':gallery_json' => medical_directory_json_encode($nextGallery),
            ];

            if ($entityType === 'facility' && array_key_exists('ai_image_url', $row)) {
                $aiCover = trim((string) ($row['ai_image_url'] ?? ''));
                if ($aiCover === $targetUrl) {
                    $sets[] = 'ai_image_url = :ai_image_url';
                    $params[':ai_image_url'] = '';
                }
            }
            if ($entityType === 'facility' && array_key_exists('images_label', $row)) {
                $uniqueAll = array_values(array_unique(array_filter(array_merge($remainingUrls, [$cover]))));
                $count = count($uniqueAll);
                $sets[] = 'images_label = :images_label';
                $params[':images_label'] = $count > 0 ? ($count . ' ảnh') : '';
            }
            if (medical_image_audit_schema_ready($pdo, $table)) {
                $sets[] = "image_scan_status = 'unscanned'";
                $sets[] = 'image_scanned_at = NULL';
            }

            $update = $pdo->prepare("UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = :id");
            $update->execute($params);
            $pdo->commit();
            medical_search_cache_invalidate();

            json_response([
                'ok' => true,
                'message' => 'Đã gỡ ảnh lỗi khỏi hồ sơ #' . $id . '.',
                'image_url' => $cover,
                'remaining_count' => count($nextGallery),
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_response(['ok' => false, 'message' => 'Không thể gỡ ảnh: ' . $e->getMessage()], 500);
        }
    }

    if ($action === 'set_cover') {
        $targetUrl = trim((string) ($body['url'] ?? ''));
        if ($targetUrl === '') {
            json_response(['ok' => false, 'message' => 'Thiếu URL ảnh đại diện.'], 422);
        }
        $stmt = $pdo->prepare("UPDATE `{$table}` SET image_url = :image_url WHERE id = :id");
        $stmt->execute([':image_url' => $targetUrl, ':id' => $id]);
        medical_search_cache_invalidate();
        json_response([
            'ok' => true,
            'message' => 'Đã cập nhật ảnh đại diện cho hồ sơ #' . $id . '.',
            'image_url' => $targetUrl,
        ]);
    }

    if ($action === 'reset_fix_queue' && $entityType === 'facility') {
        if (!db_has_column($pdo, 'medical_facilities', 'image_fix_json')) {
            json_response(['ok' => false, 'message' => 'Cơ sở dữ liệu chưa có cột image_fix_json.'], 422);
        }
        $stmt = $pdo->prepare('SELECT image_fix_json FROM medical_facilities WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $raw = $stmt->fetchColumn();
        $audit = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($audit)) {
            $audit = [];
        }
        $audit['checked_at_unix'] = 0;
        $audit['requeued_by_admin_at'] = gmdate('c');
        $upd = $pdo->prepare('UPDATE medical_facilities SET image_fix_json = :json WHERE id = :id');
        $upd->execute([
            ':json' => medical_directory_json_encode($audit),
            ':id' => $id,
        ]);
        json_response([
            'ok' => true,
            'message' => 'Đã đưa cơ sở #' . $id . ' lên đầu hàng đợi Fix ảnh.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Thao tác không được hỗ trợ.'], 422);
}

json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
