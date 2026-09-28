<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
medical_api_auth();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
}

$pdo = db();
if (!medical_directory_table_exists($pdo, 'medical_facilities') || !medical_directory_table_exists($pdo, 'medical_doctors')) {
    medical_directory_ensure_tables($pdo);
}
medical_directory_ensure_ai_writer_claim_columns($pdo);

/** Decode a stored writer lease without ever returning its secret token. */
function medical_api_writer_claim_decode(mixed $raw): array
{
    if (!is_string($raw) || trim($raw) === '') return [];
    $claim = json_decode($raw, true);
    return is_array($claim) ? $claim : [];
}

/** Public lease metadata for queue/status responses; claim_token is private. */
function medical_api_writer_claim_public(array $claim, int $now): ?array
{
    if ($claim === [] || (int) ($claim['expires_at'] ?? 0) <= $now) return null;
    $keys = ['provider', 'model', 'task', 'instance_id', 'instance_label', 'account_label', 'worker_id', 'claimed_at', 'heartbeat_at', 'expires_at'];
    $public = [];
    foreach ($keys as $key) {
        if (array_key_exists($key, $claim)) $public[$key] = $claim[$key];
    }
    return $public;
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
$now = time();
$select = $pdo->prepare("SELECT id, slug, name, status, ai_writer_claim_json FROM `{$table}` WHERE id = :id LIMIT 1");
$select->execute([':id' => $id]);
$row = $select->fetch(PDO::FETCH_ASSOC);
if (!$row) json_response(['ok' => false, 'message' => 'Không tìm thấy bài viết.'], 404);

if ($action === 'status') {
    $claim = medical_api_writer_claim_public(medical_api_writer_claim_decode($row['ai_writer_claim_json'] ?? null), $now);
    json_response(['ok' => true, 'type' => $type, 'id' => $id, 'claimed' => $claim !== null, 'writer' => $claim]);
}

$token = trim((string) ($body['claim_token'] ?? ''));
if ($action === 'heartbeat' || $action === 'release') {
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
        if ($action === 'release') {
            $pdo->prepare("UPDATE `{$table}` SET ai_writer_claim_json = NULL WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();
            json_response(['ok' => true, 'released' => true, 'type' => $type, 'id' => $id]);
        }
        if ((int) ($claim['expires_at'] ?? 0) <= $now) {
            $pdo->rollBack();
            json_response(['ok' => false, 'message' => 'Lease đã hết hạn; hãy nhận bài lại trước khi tiếp tục.', 'reason' => 'lease_expired'], 409);
        }
        $claim['heartbeat_at'] = gmdate('c', $now);
        $claim['expires_at'] = $now + 180;
        $pdo->prepare("UPDATE `{$table}` SET ai_writer_claim_json = :claim WHERE id = :id")
            ->execute([':claim' => medical_api_json($claim), ':id' => $id]);
        $pdo->commit();
        json_response(['ok' => true, 'type' => $type, 'id' => $id, 'claim_token' => $token, 'lease_seconds' => 180, 'expires_at' => $claim['expires_at']]);
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
$claim = [
    'claim_token' => bin2hex(random_bytes(32)),
    'instance_id' => $instanceId,
    'instance_label' => medical_api_writer_short_text($client['instance_label'] ?? 'Thiết bị chưa đặt tên', 100),
    'account_label' => medical_api_writer_short_text($client['account_label'] ?? '', 100),
    'worker_id' => $workerId,
    'provider' => $provider,
    'model' => medical_api_writer_short_text($client['model'] ?? '', 80),
    'task' => medical_api_writer_short_text($client['task'] ?? ($type . '_article'), 80),
    'request_id' => $requestId,
    'claimed_at' => gmdate('c', $now),
    'heartbeat_at' => gmdate('c', $now),
    'expires_at' => $now + 180,
];

try {
    $pdo->beginTransaction();
    $lock = $pdo->prepare("SELECT id, slug, name, status, ai_writer_claim_json FROM `{$table}` WHERE id = :id FOR UPDATE");
    $lock->execute([':id' => $id]);
    $row = $lock->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => 'Không tìm thấy bài viết.'], 404);
    }
    $current = medical_api_writer_claim_decode($row['ai_writer_claim_json'] ?? null);
    if ((int) ($current['expires_at'] ?? 0) > $now) {
        $sameRequest = hash_equals((string) ($current['instance_id'] ?? ''), $instanceId)
            && hash_equals((string) ($current['worker_id'] ?? ''), $workerId)
            && hash_equals((string) ($current['request_id'] ?? ''), $requestId);
        if (!$sameRequest) {
            $owner = medical_api_writer_claim_public($current, $now);
            $pdo->commit();
            json_response(['ok' => true, 'claimed' => false, 'reason' => 'already_claimed', 'type' => $type, 'id' => $id, 'writer' => $owner], 409);
        }
        // Retry after a lost response: return the same token and renew its lease.
        $current['heartbeat_at'] = gmdate('c', $now);
        $current['expires_at'] = $now + 180;
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
        'lease_seconds' => 180,
        'heartbeat_interval_seconds' => 45,
        'expires_at' => $claim['expires_at'],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['ok' => false, 'message' => 'Không thể nhận giữ bài lúc này.'], 500);
}
