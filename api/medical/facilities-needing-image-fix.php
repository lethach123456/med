<?php
declare(strict_types=1);
require_once __DIR__ . '/_image-fix.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, OPTIONS'); json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ GET.'], 405);
}

/** Check if a column exists on medical_facilities without failing if unmigrated. */
function facility_image_fix_queue_has_col(PDO $pdo, string $col): bool
{
    static $cache = [];
    if (array_key_exists($col, $cache)) return $cache[$col];
    try {
        $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "medical_facilities" AND COLUMN_NAME = :c LIMIT 1');
        $st->execute([':c' => $col]);
        return $cache[$col] = (bool) $st->fetchColumn();
    } catch (Throwable) {
        return $cache[$col] = false;
    }
}

/** Human-readable reason explaining why a facility is prioritized in the Fix ảnh queue. */
function facility_image_fix_priority_reason(array $row): string
{
    $scanStatus = strtolower(trim((string) ($row['image_scan_status'] ?? '')));
    $cover = trim((string) ($row['image_url'] ?? ''));
    $gallery = (string) ($row['gallery_json'] ?? '');
    $combined = strtolower($cover . ' ' . $gallery);

    if ($scanStatus === 'broken') return 'Đã quét DB: Phát hiện ảnh lỗi';
    if ($cover === '' || trim($gallery) === '' || trim($gallery) === '[]') return 'Trống ảnh bìa / thư viện ảnh';
    if (str_contains($combined, 'lookaside.fbsbx.com')) return 'Link Facebook fbsbx vỡ';
    if (str_contains($combined, '/wp-content/uploads/2026/')) return 'Link /uploads/2026/ nghi 404';
    if ($scanStatus === 'warning') return 'Đã quét DB: Cảnh báo ảnh';
    if (str_contains($combined, 'gps-cs-s/') || str_contains($combined, 'grass-cs/')) return 'Link Google Maps phiên tạm';
    if (preg_match('~(?:logo|banner|avatar|favicon|icon|poster|ytimg\.com)~i', $combined)) return 'Ảnh nghi Logo / Banner';
    return 'Cần kiểm định định kỳ';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
$targetImages = min(12, max(5, (int) ($_GET['target_images'] ?? 6)));
$recheckDays = min(365, max(1, (int) ($_GET['recheck_days'] ?? 30)));
$onlyPending = !in_array(strtolower((string) ($_GET['only_pending'] ?? '1')), ['0', 'false', 'no'], true);
$onlyBroken = in_array(strtolower((string) ($_GET['only_broken'] ?? '0')), ['1', 'true', 'yes'], true);
$language = (string) ($_GET['language'] ?? 'vi');
if (!in_array($language, ['vi', 'en'], true)) json_response(['ok' => false, 'message' => 'language phải là vi hoặc en.'], 422);

$ids = [];
if (isset($_GET['ids']) || isset($_GET['id'])) {
    $raw = $_GET['ids'] ?? $_GET['id'];
    if (!is_string($raw)) json_response(['ok' => false, 'message' => 'ids phải là danh sách ID nguyên dương phân cách bằng dấu phẩy.'], 422);
    foreach (explode(',', $raw) as $value) {
        $value = trim($value);
        if (!ctype_digit($value) || (int) $value <= 0) json_response(['ok' => false, 'message' => 'ids chứa ID không hợp lệ.'], 422);
        $ids[(int) $value] = (int) $value;
    }
    if (count($ids) > 50) json_response(['ok' => false, 'message' => 'Tối đa 50 ID mỗi lần.'], 422);
}

$hasScanCol = facility_image_fix_queue_has_col($pdo, 'image_scan_status');
$hasContentCol = facility_image_fix_queue_has_col($pdo, 'content');

$brokenSqlCond = "("
    . ($hasScanCol ? "image_scan_status IN ('broken', 'warning') OR " : "")
    . "image_url IS NULL OR TRIM(image_url) = '' "
    . "OR gallery_json IS NULL OR TRIM(gallery_json) IN ('', '[]', 'null') "
    . "OR image_url LIKE '%lookaside.fbsbx.com%' OR gallery_json LIKE '%lookaside.fbsbx.com%' "
    . "OR image_url LIKE '%/wp-content/uploads/2026/%' OR gallery_json LIKE '%/wp-content/uploads/2026/%' "
    . "OR image_url LIKE '%gps-cs-s/%' OR gallery_json LIKE '%gps-cs-s/%' "
    . "OR image_url LIKE '%grass-cs/%' OR gallery_json LIKE '%grass-cs/%' "
    . "OR LOWER(image_url) REGEXP '(logo|banner|avatar|icon|poster|ytimg\\\\.com)'"
    . ")";

$where = ["status='published'", 'language_code=:language'];
$params = [':language' => $language];
if ($ids !== []) {
    $keys = [];
    foreach (array_values($ids) as $index => $id) { $key = ':id_' . $index; $keys[] = $key; $params[$key] = $id; }
    $where[] = 'id IN (' . implode(',', $keys) . ')';
} else {
    if ($onlyPending) {
        $json = "CASE WHEN JSON_VALID(image_fix_json) THEN image_fix_json ELSE '{}' END";
        $where[] = "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$json}, '$.checked_at_unix')), '0') AS UNSIGNED) <= :cutoff";
        $params[':cutoff'] = time() - $recheckDays * 86400;
    }
    if ($onlyBroken) {
        $where[] = $brokenSqlCond;
    }
}
if (isset($_GET['after_id'])) {
    $after = filter_var($_GET['after_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($after === false) json_response(['ok' => false, 'message' => 'after_id phải là số nguyên không âm.'], 422);
    $where[] = 'id > :after_id'; $params[':after_id'] = $after;
}
$whereSql = implode(' AND ', $where);

// Priority ordering:
// 0 = Confirmed broken in DB scan or broken URL pattern (empty, fbsbx, /2026/)
// 1 = Warning in DB scan, temporary Google Maps token, or Logo/Banner
// 2 = Other facilities needing periodic verification
// Written facilities (content <> '') always rank ahead of unwritten stubs.
$contentOrderSql = $hasContentCol
    ? "CASE WHEN content IS NOT NULL AND TRIM(content) <> '' THEN 0 ELSE 1 END ASC, "
    : "";

$priorityTierSql = "CASE "
    . ($hasScanCol ? "WHEN image_scan_status = 'broken' THEN 0 " : "")
    . "WHEN image_url IS NULL OR TRIM(image_url) = '' OR gallery_json IS NULL OR TRIM(gallery_json) IN ('', '[]', 'null') THEN 0 "
    . "WHEN image_url LIKE '%lookaside.fbsbx.com%' OR gallery_json LIKE '%lookaside.fbsbx.com%' THEN 0 "
    . "WHEN image_url LIKE '%/wp-content/uploads/2026/%' OR gallery_json LIKE '%/wp-content/uploads/2026/%' THEN 0 "
    . ($hasScanCol ? "WHEN image_scan_status = 'warning' THEN 1 " : "")
    . "WHEN image_url LIKE '%gps-cs-s/%' OR gallery_json LIKE '%gps-cs-s/%' OR image_url LIKE '%grass-cs/%' OR gallery_json LIKE '%grass-cs/%' THEN 1 "
    . "WHEN LOWER(image_url) REGEXP '(logo|banner|avatar|icon|poster|ytimg\\\\.com)' THEN 1 "
    . ($hasScanCol ? "WHEN image_scan_status = 'ok' THEN 3 " : "")
    . "ELSE 2 END";

// When the extension uses `after_id` cursor pagination without `page`, keep id ASC inside the current cursor window,
// otherwise sort by priority tier first so broken-image facilities always appear at the top of the queue.
$orderBySql = isset($_GET['after_id'])
    ? "id ASC"
    : "{$contentOrderSql}{$priorityTierSql} ASC, id ASC";

try {
    $resolved = medical_directory_resolve_ai_prompt($pdo, 'facility_image_fix');
    if (trim($resolved['template']) === '') {
        json_response(['ok' => false, 'message' => 'Chưa có prompt Fix ảnh. Chạy php scripts/migrate_facility_image_fix.php rồi chỉnh tại Admin > Prompt AI y tế.'], 503);
    }
    $count = $pdo->prepare("SELECT COUNT(*) FROM medical_facilities WHERE {$whereSql}");
    $count->execute($params); $total = (int) $count->fetchColumn();

    $scanSelect = $hasScanCol ? ',image_scan_status,image_scanned_at' : '';
    $stmt = $pdo->prepare(
        "SELECT id,slug,name,category,city,address_text,phone_text,website_url,google_maps_url,language_code,image_url,ai_image_url,gallery_json,image_fix_json,ai_writer_claim_json,updated_at{$scanSelect}, ({$priorityTierSql}) AS priority_tier
         FROM medical_facilities
         WHERE {$whereSql}
         ORDER BY {$orderBySql}
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', isset($_GET['after_id']) ? 0 : ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute(); $items = []; $errors = [];
    $sourceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sourceRows as $row) {
        try {
            $images = medical_facility_image_fix_inventory($row);
            $claim = medical_facility_image_fix_public_claim($row['ai_writer_claim_json'] ?? null);
            $audit = medical_directory_json_decode($row['image_fix_json'] ?? null);
            $item = array_intersect_key($row, array_flip(['id', 'slug', 'name', 'category', 'city', 'address_text', 'phone_text', 'website_url', 'google_maps_url', 'language_code', 'image_url', 'ai_image_url', 'updated_at']));
            $item['id'] = (int) $row['id']; $item['title'] = $row['name']; $item['type'] = 'facility';
            $item['priority_tier'] = (int) ($row['priority_tier'] ?? 2);
            $item['priority_reason'] = facility_image_fix_priority_reason($row);
            $item['image_scan_status'] = (string) ($row['image_scan_status'] ?? 'unscanned');
            $item['images_revision'] = medical_facility_image_fix_revision($row);
            $item['gallery_json'] = medical_facility_image_fix_gallery($row); $item['images'] = $images;
            $item['images_count'] = count($images); $item['target_images'] = $targetImages;
            $item['writer_claimed'] = $claim !== null; $item['writer_claim'] = $claim;
            $item['last_image_fix_at'] = $audit['checked_at'] ?? null;
            $item['prompt_type'] = 'facility_image_fix'; $item['prompt_label'] = $resolved['label'];
            $item['prompt_source'] = $resolved['source']; $item['prompt_key_used'] = $resolved['prompt_key_used'];
            $item['output_template'] = medical_facility_image_fix_output_template($row);
            $item['prompt'] = medical_facility_image_fix_prompt($resolved['template'], $row, $targetImages);
            $items[] = $item;
        } catch (InvalidArgumentException $e) { $errors[] = ['id' => (int) $row['id'], 'message' => $e->getMessage()]; }
    }
    $lastRow = $sourceRows === [] ? null : $sourceRows[array_key_last($sourceRows)];
    json_response(['ok' => true, 'contract_version' => 1, 'task' => 'facility_image_fix', 'prompt_type' => 'facility_image_fix',
        'page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int) ceil($total / max(1, $limit)),
        'only_pending' => $onlyPending, 'only_broken' => $onlyBroken, 'recheck_days' => $recheckDays, 'language' => $language, 'checked_ids' => array_values($ids),
        'priority_order' => 'broken_first',
        'next_after_id' => $lastRow !== null ? (int) $lastRow['id'] : null,
        'claim_required' => true, 'claim_endpoint' => '/api/medical/writer-claim.php',
        'claim_task' => 'facility_image_fix', 'receive_endpoint' => '/api/medical/facility-image-fix-update.php',
        'verification' => 'URLs are source data, not server-confirmed failures. AI must inspect and use uncertain if inaccessible.',
        'items' => $items, 'errors' => $errors]);
} catch (Throwable $e) {
    error_log('Facility image-fix queue: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Không thể lấy danh sách Fix ảnh lúc này.'], 500);
}
