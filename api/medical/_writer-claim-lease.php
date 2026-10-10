<?php
declare(strict_types=1);

/** Pure lease helpers: no authentication, configuration or database connection. */
function medical_api_writer_claim_decode(mixed $raw): array
{
    if (!is_string($raw) || trim($raw) === '') return [];
    $claim = json_decode($raw, true);
    return is_array($claim) ? $claim : [];
}

/** Public lease metadata never contains its private claim token. */
function medical_api_writer_claim_public(array $claim, int $now): ?array
{
    if ($claim === [] || (int) ($claim['expires_at'] ?? 0) <= $now) return null;
    $keys = ['provider', 'model', 'task', 'instance_id', 'instance_label', 'account_label', 'worker_id', 'claimed_at', 'heartbeat_at', 'expires_at'];
    return array_intersect_key($claim, array_flip($keys));
}

function medical_api_writer_claim_lease_seconds(string $type, string $task): int
{
    return $type === 'facility' && $task === 'facility_image_fix' ? 600 : 180;
}

/** Decide renewal using only the row locked by the caller and the server clock. */
function medical_api_writer_claim_heartbeat_decision(array $claim, string $token, string $type, string $status, ?string $task, int $now): array
{
    $storedToken = $claim['claim_token'] ?? null;
    if ($token === '' || !is_string($storedToken) || $storedToken === '' || !hash_equals($storedToken, $token)) {
        return ['ok' => false, 'reason' => 'lease_lost', 'server_now' => $now];
    }
    $storedTask = (string) ($claim['task'] ?? '');
    if ($task !== null && !hash_equals($storedTask, $task)) {
        return ['ok' => false, 'reason' => 'lease_lost', 'server_now' => $now];
    }
    $expired = (int) ($claim['expires_at'] ?? 0) <= $now;
    // Never issue a new token or take another owner's row to recover old output.
    $recoverable = $type === 'facility' && $task === 'facility_image_fix'
        && $storedTask === 'facility_image_fix' && $status === 'published';
    if ($expired && !$recoverable) {
        return ['ok' => false, 'reason' => 'lease_expired', 'server_now' => $now];
    }
    $leaseSeconds = medical_api_writer_claim_lease_seconds($type, $storedTask);
    $claim['heartbeat_at'] = gmdate('c', $now);
    $claim['expires_at'] = $now + $leaseSeconds;
    return ['ok' => true, 'claim' => $claim, 'lease_seconds' => $leaseSeconds,
        'lease_recovered' => $expired, 'server_now' => $now];
}

/** Renew atomically; read the clock only after FOR UPDATE has acquired the row. */
function medical_api_writer_claim_refresh(PDO $pdo, string $type, int $id, string $token, ?string $task = null, ?callable $clock = null): array
{
    $tables = ['facility' => 'medical_facilities', 'doctor' => 'medical_doctors'];
    if (!isset($tables[$type])) throw new InvalidArgumentException('Unknown writer claim type.');
    $table = $tables[$type];
    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare("SELECT status, ai_writer_claim_json FROM `{$table}` WHERE id = :id FOR UPDATE");
        $lock->execute([':id' => $id]);
        $row = $lock->fetch(PDO::FETCH_ASSOC);
        $now = $clock !== null ? (int) $clock() : time();
        $claim = medical_api_writer_claim_decode($row['ai_writer_claim_json'] ?? null);
        $result = medical_api_writer_claim_heartbeat_decision($claim, $token, $type, (string) ($row['status'] ?? ''), $task, $now);
        if (!$result['ok']) {
            $pdo->rollBack();
            return $result;
        }
        $pdo->prepare("UPDATE `{$table}` SET ai_writer_claim_json = :claim WHERE id = :id")
            ->execute([':claim' => json_encode($result['claim'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ':id' => $id]);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
