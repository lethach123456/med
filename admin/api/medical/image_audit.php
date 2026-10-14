<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_directory.php';
require_once __DIR__ . '/../../../medical_media_library.php';
require_once __DIR__ . '/../../../medical_media_worker.php';
require_once __DIR__ . '/../../../medical_facility_image_fix.php';

admin_require_login();
$csrfToken = admin_csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_json();
}
// Unlock session early so gentle batch probes never block other admin tabs.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = db();

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
 * No remote network I/O happens here so listing 1,000+ records stays instantaneous.
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
 * Evaluate a single facility or doctor row for static/local/worker issues.
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

    $imagesByUrl = [];
    $register = static function (string $url, string $field, string $caption = '', string $source = '') use (&$imagesByUrl, $failedJobsMap): void {
        $url = trim($url);
        if ($url === '') {
            return;
        }
        if (!isset($imagesByUrl[$url])) {
            $issues = medical_image_audit_classify_url($url, $failedJobsMap);
            $imagesByUrl[$url] = [
                'url' => $url,
                'is_local' => str_starts_with($url, '/'),
                'fields' => [],
                'caption' => $caption,
                'source' => $source,
                'issues' => $issues,
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

    $fixAudit = $entityType === 'facility' ? medical_directory_json_decode($row['image_fix_json'] ?? null) : null;
    $lastCheckedAt = is_array($fixAudit) ? (string) ($fixAudit['checked_at'] ?? '') : '';
    $fixInsufficient = is_array($fixAudit) && !empty($fixAudit['insufficient_images']);
    if ($fixInsufficient) {
        $rowIssues[] = ['code' => 'insufficient_images', 'severity' => 'warning', 'label' => 'Lịch sử Fix ảnh báo thiếu ảnh chuẩn'];
    }

    $hasStrictIssue = isset($issueCodes['empty'])
        || isset($issueCodes['fbsbx'])
        || isset($issueCodes['google_temp'])
        || isset($issueCodes['logo_banner'])
        || isset($issueCodes['suspicious_2026'])
        || isset($issueCodes['missing_local'])
        || isset($issueCodes['worker_failed'])
        || isset($issueCodes['not_direct_image'])
        || isset($issueCodes['invalid_url']);

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
        'last_image_fix_at' => $lastCheckedAt !== '' ? $lastCheckedAt : null,
        'public_url' => site_url($publicPath),
        'edit_url' => $editPath,
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

/**
 * Lightweight, throttled remote probe for at most 3 URLs at a time.
 * Reads max 512 KB per URL and validates that response is a real image (not an HTML page).
 *
 * @param array<int,string> $urls
 * @return array<string,array<string,mixed>>
 */
function medical_image_audit_probe_urls(array $urls): array
{
    $urls = array_slice(array_values(array_unique(array_filter(array_map('trim', $urls)))), 0, 3);
    $results = [];

    foreach ($urls as $url) {
        if (str_starts_with($url, '/')) {
            $path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? ''));
            $fullPath = medical_media_project_root() . '/' . ltrim($path, '/');
            if (!is_file($fullPath) || (int) (@filesize($fullPath) ?: 0) <= 0) {
                $results[$url] = [
                    'url' => $url,
                    'state' => 'broken',
                    'http_status' => 404,
                    'reason' => 'Không tìm thấy file nội bộ trên ổ cứng máy chủ.',
                ];
            } else {
                $info = @getimagesize($fullPath);
                if (!is_array($info)) {
                    $results[$url] = [
                        'url' => $url,
                        'state' => 'broken',
                        'http_status' => 200,
                        'reason' => 'File nội bộ bị hỏng định dạng ảnh.',
                    ];
                } else {
                    $w = (int) ($info[0] ?? 0);
                    $h = (int) ($info[1] ?? 0);
                    $results[$url] = [
                        'url' => $url,
                        'state' => ($w < 120 || $h < 90) ? 'warning' : 'ok',
                        'http_status' => 200,
                        'mime' => (string) ($info['mime'] ?? 'image/jpeg'),
                        'width' => $w,
                        'height' => $h,
                        'size_bytes' => (int) (@filesize($fullPath) ?: 0),
                        'reason' => ($w < 120 || $h < 90) ? "Ảnh kích thước quá nhỏ ({$w}×{$h}px)" : "OK ({$w}×{$h}px)",
                    ];
                }
            }
            continue;
        }

        // Remote URL probe with tight 6-second timeout so it never hangs PHP workers.
        $dl = medical_media_download_remote_image($url, 6);
        $httpStatus = (int) ($dl['http_status'] ?? 0);
        if (!($dl['ok'] ?? false) || !isset($dl['bytes']) || $dl['bytes'] === '') {
            $err = (string) ($dl['error'] ?? 'Không tải được ảnh.');
            $isTempBlock = in_array($httpStatus, [401, 403, 408, 429], true) || $httpStatus >= 500;
            $results[$url] = [
                'url' => $url,
                'state' => $isTempBlock ? 'warning' : 'broken',
                'http_status' => $httpStatus > 0 ? $httpStatus : null,
                'reason' => $err,
            ];
            continue;
        }

        $bytes = (string) $dl['bytes'];
        $mime = strtolower((string) ($dl['mime'] ?? ''));
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info)) {
            $results[$url] = [
                'url' => $url,
                'state' => 'broken',
                'http_status' => $httpStatus ?: 200,
                'mime' => $mime,
                'reason' => 'URL trả về dữ liệu không phải ảnh hợp lệ' . ($mime !== '' ? " (MIME: {$mime})" : '') . '.',
            ];
            continue;
        }

        $w = (int) ($info[0] ?? 0);
        $h = (int) ($info[1] ?? 0);
        $detectedMime = (string) ($info['mime'] ?? $mime);
        $isTiny = ($w < 120 || $h < 90);
        $results[$url] = [
            'url' => $url,
            'state' => $isTiny ? 'warning' : 'ok',
            'http_status' => $httpStatus ?: 200,
            'mime' => $detectedMime,
            'width' => $w,
            'height' => $h,
            'size_bytes' => strlen($bytes),
            'reason' => $isTiny ? "Ảnh quá nhỏ ({$w}×{$h}px)" : "Ảnh hợp lệ ({$w}×{$h}px)",
        ];
    }

    return $results;
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
        'any_issue', 'empty', 'fbsbx', 'google_temp', 'logo_banner',
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

    // Load failed media jobs map for this entity_type if table exists.
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
            // Ignore if schema not migrated yet.
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
    $selectCols = $entityType === 'doctor'
        ? 'id, slug, name, title_text, specialty_text, facility_name, city, status, image_url, gallery_json, updated_at' . ($hasLangCol ? ', language_code' : '')
        : 'id, slug, name, category, city, address_text, status, image_url, gallery_json, updated_at'
            . ($hasLangCol ? ', language_code' : '')
            . ($hasAiImageCol ? ', ai_image_url' : '')
            . ($hasImageFixCol ? ', image_fix_json' : '');

    $stmt = $pdo->prepare("SELECT {$selectCols} FROM {$table} {$whereSql} ORDER BY id ASC");
    $stmt->execute($params);
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $summary = [
        'all' => 0,
        'any_issue' => 0,
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
        'entity_type' => $entityType,
        'language' => $language,
        'status' => $statusFilter,
        'issue' => $issueFilter,
        'query' => $query,
        'summary' => $summary,
        'matched_ids' => array_slice($matchedIds, 0, 200),
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

    if ($action === 'probe_batch') {
        $urls = is_array($body['urls'] ?? null) ? $body['urls'] : [];
        $probed = medical_image_audit_probe_urls($urls);
        json_response([
            'ok' => true,
            'results' => $probed,
        ]);
    }

    $entityType = strtolower(trim((string) ($body['entity_type'] ?? 'facility')));
    if (!in_array($entityType, ['facility', 'doctor'], true)) {
        json_response(['ok' => false, 'message' => 'Loại hồ sơ không hợp lệ.'], 422);
    }
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'ID hồ sơ không hợp lệ.'], 422);
    }
    $table = $entityType === 'doctor' ? 'medical_doctors' : 'medical_facilities';

    if ($action === 'remove_image') {
        $targetUrl = trim((string) ($body['url'] ?? ''));
        if ($targetUrl === '') {
            json_response(['ok' => false, 'message' => 'Thiếu URL ảnh cần xoá.'], 422);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id FOR UPDATE");
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

            $update = $pdo->prepare("UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id");
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
        $stmt = $pdo->prepare("UPDATE {$table} SET image_url = :image_url WHERE id = :id");
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
