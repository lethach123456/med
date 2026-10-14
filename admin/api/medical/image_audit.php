<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../schema_migrations.php';
require_once __DIR__ . '/../../../medical_directory.php';

admin_require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_json();
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = db();

function medical_image_audit_schema_ready(PDO $pdo, string $table): bool
{
    return db_has_column($pdo, $table, 'image_scan_status')
        && db_has_column($pdo, $table, 'image_scanned_at')
        && db_has_column($pdo, $table, 'image_scan_json');
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

/**
 * SQL condition to filter ONLY facilities/doctors that already have written article content.
 */
function medical_image_audit_written_where(PDO $pdo, string $table, string $entityType): string
{
    $clauses = [];
    if (db_has_column($pdo, $table, 'content')) {
        $clauses[] = "(content IS NOT NULL AND TRIM(content) <> '')";
    }
    if ($entityType === 'facility' && db_has_column($pdo, $table, 'overview_json')) {
        $clauses[] = "(overview_json IS NOT NULL AND TRIM(overview_json) NOT IN ('', '[]', '{}', 'null'))";
    }
    if ($entityType === 'doctor' && $clauses === [] && db_has_column($pdo, $table, 'bio_json')) {
        $clauses[] = "(bio_json IS NOT NULL AND TRIM(bio_json) NOT IN ('', '[]', '{}', 'null'))";
    }
    if ($clauses === []) {
        return '1=1';
    }
    return '(' . implode(' OR ', $clauses) . ')';
}

function medical_image_audit_count_images(array $row): int
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
    return count($urls);
}

function medical_image_audit_summary(PDO $pdo, string $table, string $entityType, bool $schemaReady): array
{
    $writtenWhere = medical_image_audit_written_where($pdo, $table, $entityType);
    if (!$schemaReady) {
        $total = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$writtenWhere}")->fetchColumn();
        return ['total' => $total, 'scanned' => 0, 'unscanned' => $total];
    }
    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN image_scanned_at IS NOT NULL AND image_scan_status <> 'unscanned' THEN 1 ELSE 0 END) AS scanned,
            SUM(CASE WHEN image_scanned_at IS NULL OR image_scan_status = 'unscanned' THEN 1 ELSE 0 END) AS unscanned
         FROM `{$table}`
         WHERE {$writtenWhere}"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'total' => (int) ($row['total'] ?? 0),
        'scanned' => (int) ($row['scanned'] ?? 0),
        'unscanned' => (int) ($row['unscanned'] ?? 0),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $entityType = strtolower(trim((string) ($_GET['entity_type'] ?? 'facility')));
    if (!in_array($entityType, ['facility', 'doctor'], true)) {
        $entityType = 'facility';
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;

    $table = $entityType === 'doctor' ? 'medical_doctors' : 'medical_facilities';
    $schemaReady = medical_image_audit_ensure_schema($pdo, $table);
    $hasLangCol = db_has_column($pdo, $table, 'language_code');
    $writtenWhere = medical_image_audit_written_where($pdo, $table, $entityType);

    $summary = medical_image_audit_summary($pdo, $table, $entityType, $schemaReady);
    $total = $summary['total'];
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;

    $scanCols = $schemaReady ? ', image_scan_status, image_scanned_at' : '';
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

        $items[] = [
            'id' => $id,
            'name' => (string) ($row['name'] ?? ''),
            'subtitle' => (string) ($row['subtitle'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'images_count' => medical_image_audit_count_images($row),
            'is_scanned' => $isScanned,
            'image_scan_status' => $isScanned ? 'scanned' : 'unscanned',
            'image_scanned_at' => $isScanned ? $scannedAt : null,
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
        $batchSize = min(15, max(1, (int) ($body['batch_size'] ?? 8)));
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
             SET image_scan_status = 'scanned',
                 image_scanned_at = :scanned_at,
                 image_scan_json = :scan_json
             WHERE id = :id"
        );

        $processed = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $imgCount = medical_image_audit_count_images($row);
            $payload = json_encode([
                'status' => 'scanned',
                'images_count' => $imgCount,
                'scanned_at' => $now,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $upd->execute([
                ':scanned_at' => $now,
                ':scan_json' => $payload ?: '{}',
                ':id' => $id,
            ]);
            $processed[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? ''),
                'images_count' => $imgCount,
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

    if ($action === 'toggle_row_scan') {
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) {
            json_response(['ok' => false, 'message' => 'ID không hợp lệ.'], 422);
        }
        $markScanned = !empty($body['scanned']);
        if ($markScanned) {
            $now = gmdate('Y-m-d H:i:s');
            $stmt = $pdo->prepare(
                "UPDATE `{$table}`
                 SET image_scan_status = 'scanned', image_scanned_at = :now
                 WHERE id = :id"
            );
            $stmt->execute([':now' => $now, ':id' => $id]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE `{$table}`
                 SET image_scan_status = 'unscanned', image_scanned_at = NULL, image_scan_json = NULL
                 WHERE id = :id"
            );
            $stmt->execute([':id' => $id]);
        }
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

json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
