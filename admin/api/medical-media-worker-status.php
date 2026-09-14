<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../medical_media_worker.php';

admin_require_login();
// The run endpoint can take a few seconds. Releasing the read-only session
// lock lets the browser poll this endpoint and show live per-image progress.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

/** @return array{facilities:int,doctors:int,images:int,downloaded:int,total:int} */
function medical_media_worker_pending_summary(PDO $pdo, array $status): array
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
    return [
        'facilities' => $entities['facility'],
        'doctors' => $entities['doctor'],
        'images' => $images,
        'downloaded' => $downloaded,
        'total' => $images + $downloaded,
    ];
}

$pdo = db();
medical_media_jobs_ensure_table($pdo);
$status = medical_media_jobs_status($pdo);

json_response([
    'ok' => true,
    'pending' => medical_media_worker_pending_summary($pdo, $status),
    'status' => $status,
    'active' => medical_media_jobs_active_items($pdo),
]);
