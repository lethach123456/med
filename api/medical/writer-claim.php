<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_writer-claim-lease.php';
medical_api_auth();
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
}

$pdo = db();
try {
    foreach (['medical_facilities', 'medical_doctors'] as $table) {
        $pdo->query("SELECT id, ai_writer_claim_json FROM {$table} LIMIT 0");
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Chưa sẵn sàng khóa viết bài. Chạy php scripts/migrate_medical_directory.php.'], 503);
}

function medical_api_writer_short_text(mixed $value, int $limit): string
{
    $value = trim((string) ($value ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
}

$body = read_json_body();
$action = strtolower(trim((string) ($body['action'] ?? 'claim')));
$type = strtolower(trim((string) ($body['type'] ?? $body['entity_type'] ?? '')));
$id = max(0, (int) ($body['id'] ?? $body['record_id'] ?? 0));
$tables = ['facility' => 'medical_facilities', 'doctor' => 'medical_doctors'];
if (!isset($tables[$type])) json_response(['ok' => false, 'message' => 'type phải là facility hoặc doctor.'], 422);
if ($id <= 0) json_response(['ok' => false, 'message' => 'Thiếu id bài viết hợp lệ.'], 422);
if (!in_array($action, ['claim', 'heartbeat', 'release', 'status'], true)) {
    json_response(['ok' => false, 'message' => 'action phải là claim, heartbeat, release hoặc status.'], 422);
}

$table = $tables[$type];
$select = $pdo->prepare("SELECT id, slug, name, status, ai_writer_claim_json FROM `{$table}` WHERE id = :id LIMIT 1");
$select->execute([':id' => $id]);
$row = $select->fetch(PDO::FETCH_ASSOC);
if (!$row) json_response(['ok' => false, 'message' => 'Không tìm thấy bài viết.'], 404);

if ($action === 'status') {
    $now = time();
    $claim = medical_api_writer_claim_public(medical_api_writer_claim_decode($row['ai_writer_claim_json'] ?? null), $now);
    json_response(['ok' => true, 'type' => $type, 'id' => $id, 'claimed' => $claim !== null, 'writer' => $claim,
        'server_now' => $now, 'lease_seconds' => medical_api_writer_claim_lease_seconds($type, (string) ($claim['task'] ?? ''))]);
}

$token = trim((string) ($body['claim_token'] ?? ''));
if ($action === 'heartbeat') {
    if ($token === '') json_response(['ok' => false, 'message' => 'Thiếu claim_token.'], 422);
    $heartbeatClient = is_array($body['client'] ?? null) ? $body['client'] : [];
    $heartbeatTask = $heartbeatClient['task'] ?? $body['task'] ?? null;
    $heartbeatTask = $heartbeatTask === null ? null : medical_api_writer_short_text($heartbeatTask, 80);
    try {
        $renewal = medical_api_writer_claim_refresh($pdo, $type, $id, $token, $heartbeatTask);
        if (!$renewal['ok']) {
            $message = $renewal['reason'] === 'lease_expired'
                ? 'Lease đã hết hạn; hãy nhận bài lại trước khi tiếp tục.'
                : 'Claim đã mất hoặc đã được máy khác nhận.';
            json_response(['ok' => false, 'message' => $message, 'reason' => $renewal['reason'], 'server_now' => $renewal['server_now']], 409);
        }
        json_response(['ok' => true, 'type' => $type, 'id' => $id, 'claim_token' => $token,
            'lease_seconds' => $renewal['lease_seconds'], 'heartbeat_interval_seconds' => 45,
            'server_now' => $renewal['server_now'], 'lease_recovered' => $renewal['lease_recovered'],
            'expires_at' => $renewal['claim']['expires_at']]);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'message' => 'Không thể cập nhật claim lúc này.'], 500);
    }
}
if ($action === 'release') {
    if ($token === '') json_response(['ok' => false, 'message' => 'Thiếu claim_token.'], 422);
    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare("SELECT ai_writer_claim_json FROM `{$table}` WHERE id = :id FOR UPDATE");
        $lock->execute([':id' => $id]);
        $claim = medical_api_writer_claim_decode($lock->fetchColumn());
        if ($claim === [] || !hash_equals((string) ($claim['claim_token'] ?? ''), $token)) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => 'Claim đã mất hoặc đã được máy khác nhận.', 'reason' => 'lease_lost'], 409);
        }
        $pdo->prepare("UPDATE `{$table}` SET ai_writer_claim_json = NULL WHERE id = :id")->execute([':id' => $id]);
        $pdo->commit();
        json_response(['ok' => true, 'released' => true, 'type' => $type, 'id' => $id, 'server_now' => time()]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok' => false, 'message' => 'Không thể cập nhật claim lúc này.'], 500);
    }
}

$client = is_array($body['client'] ?? null) ? $body['client'] : $body;
$instanceId = medical_api_writer_short_text($client['instance_id'] ?? '', 100);
$workerId = medical_api_writer_short_text($client['worker_id'] ?? '', 120);
$requestId = medical_api_writer_short_text($body['request_id'] ?? '', 120);
if ($instanceId === '' || $workerId === '' || $requestId === '') {
    json_response(['ok' => false, 'message' => 'Claim cần instance_id, worker_id và request_id ổn định cho mỗi lượt nhận bài.'], 422);
}

$provider = strtolower(medical_api_writer_short_text($client['provider'] ?? 'other', 32));
if (!preg_match('/^[a-z0-9._-]+$/', $provider)) $provider = 'other';
$task = medical_api_writer_short_text($client['task'] ?? $body['task'] ?? ($type . '_article'), 80);
$imageFix = $type === 'facility' && $task === 'facility_image_fix';
if ($task === 'facility_image_fix' && $type !== 'facility') json_response(['ok' => false, 'message' => 'Fix ảnh hiện chỉ hỗ trợ type=facility.'], 422);
if ($imageFix) {
    try { medical_facility_image_fix_require_schema($pdo); }
    catch (Throwable $e) { json_response(['ok' => false, 'message' => 'Chạy php scripts/migrate_facility_image_fix.php trước khi nhận tác vụ Fix ảnh.'], 503); }
}
$claim = [
    'claim_token' => bin2hex(random_bytes(32)),
    'instance_id' => $instanceId,
    'instance_label' => medical_api_writer_short_text($client['instance_label'] ?? 'Thiết bị chưa đặt tên', 100),
    'account_label' => medical_api_writer_short_text($client['account_label'] ?? '', 100),
    'worker_id' => $workerId,
    'provider' => $provider,
    'model' => medical_api_writer_short_text($client['model'] ?? '', 80),
    'task' => $task,
    'request_id' => $requestId,
];

try {
    $pdo->beginTransaction();
    $contentColumn = $type === 'facility' ? ', content' : ', content, language_code, last_researched_at';
    $lock = $pdo->prepare("SELECT id, slug, name, status, ai_writer_claim_json{$contentColumn} FROM `{$table}` WHERE id = :id FOR UPDATE");
    $lock->execute([':id' => $id]);
    $row = $lock->fetch(PDO::FETCH_ASSOC);
    $now = time();
    $leaseSeconds = medical_api_writer_claim_lease_seconds($type, $task);
    $claim['claimed_at'] = gmdate('c', $now);
    $claim['heartbeat_at'] = gmdate('c', $now);
    $claim['expires_at'] = $now + $leaseSeconds;
    if (!$row) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => 'Không tìm thấy bài viết.'], 404);
    }
    if ((string) ($row['status'] ?? '') !== 'published') {
        $pdo->rollBack();
        json_response(['ok' => true, 'claimed' => false, 'reason' => 'not_published', 'type' => $type, 'id' => $id, 'message' => 'Bài không còn ở trạng thái xuất bản.'], 409);
    }
    if ($type === 'facility' && !$imageFix && trim((string) ($row['content'] ?? '')) !== '') {
        $pdo->rollBack();
        json_response(['ok' => true, 'claimed' => false, 'reason' => 'content_exists', 'type' => $type, 'id' => $id, 'message' => 'Cơ sở đã có nội dung; bỏ qua để tránh viết trùng.'], 409);
    }
    if ($type === 'doctor' && !medical_doctor_needs_content($row)) {
        $pdo->rollBack();
        json_response(['ok' => true, 'claimed' => false, 'reason' => 'content_exists', 'type' => $type,
            'id' => $id, 'message' => 'Bác sĩ đã được xử lý hoặc không phải hồ sơ tiếng Việt cần viết.'], 409);
    }
    $current = medical_api_writer_claim_decode($row['ai_writer_claim_json'] ?? null);
    if ((int) ($current['expires_at'] ?? 0) > $now) {
        $sameRequest = hash_equals((string) ($current['instance_id'] ?? ''), $instanceId)
            && hash_equals((string) ($current['worker_id'] ?? ''), $workerId)
            && hash_equals((string) ($current['request_id'] ?? ''), $requestId)
            && hash_equals((string) ($current['task'] ?? ''), $task);
        if (!$sameRequest) {
            $owner = medical_api_writer_claim_public($current, $now);
            $pdo->commit();
            json_response(['ok' => true, 'claimed' => false, 'reason' => 'already_claimed', 'type' => $type, 'id' => $id, 'writer' => $owner], 409);
        }
        // Retry after a lost response: return the same token and renew its lease.
        $current['heartbeat_at'] = gmdate('c', $now);
        $current['expires_at'] = $now + $leaseSeconds;
        $claim = $current;
    }
    $pdo->prepare("UPDATE `{$table}` SET ai_writer_claim_json = :claim WHERE id = :id")
        ->execute([':claim' => medical_api_json($claim), ':id' => $id]);
    $pdo->commit();
    json_response([
        'ok' => true,
        'claimed' => true,
        'type' => $type,
        'id' => $id,
        'slug' => (string) ($row['slug'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'claim_token' => $claim['claim_token'],
        'lease_seconds' => $leaseSeconds,
        'heartbeat_interval_seconds' => 45,
        'server_now' => $now,
        'expires_at' => $claim['expires_at'],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['ok' => false, 'message' => 'Không thể nhận giữ bài lúc này.'], 500);
}
