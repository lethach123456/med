<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../medical_media_worker.php';

admin_require_login();
// No session data is changed below. Unlock it so the admin UI can poll the
// status endpoint while this request downloads a batch.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);

/** @return array{facilities:int,doctors:int,images:int,downloaded:int,total:int} */
function medical_media_worker_run_pending_summary(PDO $pdo, array $status): array
{
    $stmt = $pdo->query(
        "SELECT entity_type, COUNT(DISTINCT entity_id) AS total
         FROM medical_media_jobs
         WHERE status IN ('pending', 'processing', 'downloaded', 'failed')
         GROUP BY entity_type"
    );
    $entities = ['facility' => 0, 'doctor' => 0];
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $type = (string) ($row['entity_type'] ?? '');
        if (isset($entities[$type])) $entities[$type] = (int) ($row['total'] ?? 0);
    }
    $images = 0;
    foreach (['pending', 'processing', 'failed'] as $key) $images += (int) (($status['total'][$key] ?? 0));
    $downloaded = (int) (($status['total']['downloaded'] ?? 0));
    return ['facilities' => $entities['facility'], 'doctors' => $entities['doctor'], 'images' => $images, 'downloaded' => $downloaded, 'total' => $images + $downloaded];
}

$body = read_json_body();
$type = (string) ($body['type'] ?? 'all');
if (!in_array($type, ['all', 'facility', 'doctor'], true)) $type = 'all';
$limit = min(30, max(1, (int) ($body['limit'] ?? 3)));
$parallel = min(3, max(1, (int) ($body['parallel'] ?? 3)));
$action = (string) ($body['action'] ?? 'run');

$pdo = db();
medical_media_jobs_ensure_table($pdo);
try {
    if ($action === 'retry_failed') {
        $retried = medical_media_jobs_retry_failed($pdo, $type);
        $status = medical_media_jobs_status($pdo);
        json_response([
            'ok' => true,
            'message' => 'Đã đưa ' . $retried . ' ảnh lỗi trở lại hàng chờ.',
            'retried' => $retried,
            'pending' => medical_media_worker_run_pending_summary($pdo, $status),
            'status' => $status,
            'active' => medical_media_jobs_active_items($pdo),
        ]);
    }
    if ($action === 'scan') {
        $scan = medical_media_jobs_scan_remote_entities($pdo, $type, min(500, max(10, (int) ($body['scan_limit'] ?? 100))));
        $status = medical_media_jobs_status($pdo);
        json_response([
            'ok' => true,
            'message' => 'Đã quét ' . $scan['scanned'] . ' hồ sơ và thêm ' . $scan['queued'] . ' URL ảnh vào hàng chờ.',
            'scan' => $scan,
            'pending' => medical_media_worker_run_pending_summary($pdo, $status),
            'status' => $status,
            'active' => medical_media_jobs_active_items($pdo),
        ]);
    }

    // Older cached admin pages used action=run. Treat that as the new safe
    // default (download first), rather than unexpectedly doing the costly
    // one-step compression path. `full` stays available only when requested.
    $mode = $action === 'compress' ? 'compress' : ($action === 'full' ? 'full' : 'download');
    // Quét dữ liệu cũ là thao tác riêng. Các URL từ API mới đã có job ngay,
    // nên không cần tốn thêm query quét ở mỗi lượt tải/nén thủ công.
    $result = medical_media_worker_run($pdo, $type, $limit, $parallel, $action === 'run', $mode);
    $status = (array) ($result['status'] ?? medical_media_jobs_status($pdo));
    $result['pending'] = medical_media_worker_run_pending_summary($pdo, $status);
    $result['active'] = medical_media_jobs_active_items($pdo);
    $result['message'] = !empty($result['busy'])
        ? (string) ($result['message'] ?? 'Worker đang chạy ở tiến trình khác.')
        : ((int) ($result['processed'] ?? 0) > 0
            ? (($mode === 'download' ? 'Đã tải về ' : ($mode === 'compress' ? 'Đã nén ' : 'Đã xử lý ')) . (int) ($result['processed'] ?? 0) . ' ảnh.' )
            : 'Không còn URL ảnh cần xử lý.');
    json_response($result, !empty($result['busy']) ? 409 : 200);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Worker ảnh thất bại: ' . $e->getMessage()], 500);
}
