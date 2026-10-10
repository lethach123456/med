<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/medical/_writer-claim-lease.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$now = 1700000000;
$token = str_repeat('a', 64);
$claim = ['claim_token' => $token, 'task' => 'facility_image_fix', 'expires_at' => $now - 1,
    'claimed_at' => gmdate('c', $now - 180), 'request_id' => 'original-attempt', 'worker_id' => 'worker-a'];
$assert(medical_api_writer_claim_lease_seconds('facility', 'facility_image_fix') === 600, 'image-fix lease is ten minutes');
$assert(medical_api_writer_claim_lease_seconds('facility', 'facility_article') === 180, 'facility article lease remains three minutes');
$assert(medical_api_writer_claim_lease_seconds('doctor', 'doctor_article') === 180, 'doctor article lease remains three minutes');
$assert(medical_api_writer_claim_lease_seconds('doctor', 'facility_image_fix') === 180, 'long lease is scoped to facilities only');

final class WriterLeaseStatement extends PDOStatement
{
    public function __construct(private WriterLeasePDO $database, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (!$this->database->inTransaction()) throw new RuntimeException('Lease query must run inside a transaction.');
        if (str_starts_with($this->sql, 'SELECT')) {
            if (!str_contains($this->sql, 'FOR UPDATE')) throw new RuntimeException('Lease read must lock the row.');
            if (is_callable($this->database->beforeLock)) ($this->database->beforeLock)($this->database);
            $this->database->snapshotLockedRow();
            $this->database->locked = true;
        } else {
            if (!$this->database->locked) throw new RuntimeException('Lease update must follow its row lock.');
            if ($this->database->failUpdate) throw new RuntimeException('Simulated storage failure.');
            $this->database->writes++;
            $this->database->row['ai_writer_claim_json'] = $params[':claim'];
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (!$this->database->locked) throw new RuntimeException('Lease fetch requires its row lock.');
        return $this->database->row;
    }
}
final class WriterLeasePDO extends PDO
{
    public int $writes = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public bool $locked = false;
    public bool $failUpdate = false;
    public mixed $beforeLock = null;
    private bool $transaction = false;
    private array|false $snapshot;
    public function __construct(public array|false $row) {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new WriterLeaseStatement($this, $query); }
    public function snapshotLockedRow(): void { $this->snapshot = $this->row; }
    public function beginTransaction(): bool { $this->snapshot = $this->row; $this->transaction = true; return true; }
    public function commit(): bool { $this->commits++; $this->transaction = false; $this->locked = false; return true; }
    public function rollBack(): bool { $this->rollbacks++; $this->row = $this->snapshot; $this->transaction = false; $this->locked = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}
$row = ['status' => 'published', 'ai_writer_claim_json' => json_encode($claim, JSON_THROW_ON_ERROR)];
$db = new WriterLeasePDO($row);
$result = medical_api_writer_claim_refresh($db, 'facility', 71, $token, 'facility_image_fix', static function () use ($db, $assert, $now): int {
    $assert($db->locked, 'server clock is read only after the row lock');
    return $now;
});
$saved = medical_api_writer_claim_decode($db->row['ai_writer_claim_json']);
$assert($result['ok'] && $result['lease_recovered'], 'expired unchanged image-fix lease is safely recovered');
$assert($saved['claim_token'] === $token && $saved['request_id'] === $claim['request_id']
    && $saved['worker_id'] === $claim['worker_id'] && $saved['claimed_at'] === $claim['claimed_at'], 'recovery retains the original token and ownership metadata');
$assert($saved['expires_at'] === $now + 600 && $saved['heartbeat_at'] === gmdate('c', $now)
    && $result['server_now'] === $now && $result['lease_seconds'] === 600, 'renewal is based on the locked server clock');
$assert($db->writes === 1 && $db->commits === 1 && !$db->inTransaction(), 'recovery commits one atomic renewal');
$retry = medical_api_writer_claim_refresh($db, 'facility', 71, $token, 'facility_image_fix', static fn(): int => $now + 1);
$assert($retry['ok'] && !$retry['lease_recovered'] && $retry['claim']['claim_token'] === $token,
    'retry after a lost recovery response renews the same token');
$public = medical_api_writer_claim_public($saved, $now);
$assert($public !== null && !isset($public['claim_token']) && !isset($public['request_id']), 'public status cannot expose ownership secrets');

$rejectCases = [
    'replaced token' => ['claim' => array_replace($claim, ['claim_token' => str_repeat('b', 64)]), 'reason' => 'lease_lost'],
    'missing claim' => ['raw' => null, 'reason' => 'lease_lost'],
    'invalid claim' => ['raw' => '{broken', 'reason' => 'lease_lost'],
    'wrong stored task' => ['claim' => array_replace($claim, ['task' => 'facility_article']), 'reason' => 'lease_lost'],
    'wrong requested task' => ['task' => 'facility_article', 'reason' => 'lease_lost'],
    'no requested task' => ['task' => null, 'reason' => 'lease_expired'],
    'unpublished facility' => ['status' => 'draft', 'reason' => 'lease_expired'],
    'expired facility article' => ['claim' => array_replace($claim, ['task' => 'facility_article']), 'task' => 'facility_article', 'reason' => 'lease_expired'],
    'expired doctor article' => ['claim' => array_replace($claim, ['task' => 'doctor_article']), 'task' => 'doctor_article', 'type' => 'doctor', 'reason' => 'lease_expired'],
    'wrong entity type' => ['type' => 'doctor', 'reason' => 'lease_expired'],
];
foreach ($rejectCases as $label => $case) {
    $caseRow = ['status' => $case['status'] ?? 'published',
        'ai_writer_claim_json' => array_key_exists('raw', $case) ? $case['raw'] : json_encode($case['claim'] ?? $claim, JSON_THROW_ON_ERROR)];
    $db = new WriterLeasePDO($caseRow);
    $task = array_key_exists('task', $case) ? $case['task'] : 'facility_image_fix';
    $rejected = medical_api_writer_claim_refresh($db, $case['type'] ?? 'facility', 71, $token, $task, static fn(): int => $now);
    $assert(!$rejected['ok'] && $rejected['reason'] === $case['reason'], $label . ': rejected with its strict lease reason');
    $assert($db->writes === 0 && $db->row === $caseRow && $db->rollbacks === 1 && !$db->inTransaction(), $label . ': never rewrites or takes the row');
}
$db = new WriterLeasePDO(false);
$missingRow = medical_api_writer_claim_refresh($db, 'facility', 71, $token, 'facility_image_fix', static fn(): int => $now);
$assert(!$missingRow['ok'] && $missingRow['reason'] === 'lease_lost' && $db->writes === 0, 'deleted row cannot be recovered');

// A competing claim may complete while this request waits for its row lock.
$db = new WriterLeasePDO($row);
$db->beforeLock = static function (WriterLeasePDO $db) use ($claim, $now): void {
    $db->row['ai_writer_claim_json'] = json_encode(array_replace($claim, ['claim_token' => str_repeat('b', 64), 'expires_at' => $now + 600]), JSON_THROW_ON_ERROR);
};
$taken = medical_api_writer_claim_refresh($db, 'facility', 71, $token, 'facility_image_fix', static fn(): int => $now);
$assert(!$taken['ok'] && $taken['reason'] === 'lease_lost' && $db->writes === 0
    && medical_api_writer_claim_decode($db->row['ai_writer_claim_json'])['claim_token'] === str_repeat('b', 64),
    'a takeover before lock acquisition is checked from the locked row, not a stale pre-read');

// Waiting for a row lock must not create a lease that expires shortly after commit.
$db = new WriterLeasePDO($row);
$clockNow = $now - 500;
$db->beforeLock = static function () use (&$clockNow, $now): void { $clockNow = $now; };
$delayed = medical_api_writer_claim_refresh($db, 'facility', 71, $token, 'facility_image_fix', static function () use (&$clockNow): int { return $clockNow; });
$assert($delayed['server_now'] === $now && $delayed['claim']['expires_at'] === $now + 600,
    'lock wait time is excluded from the newly granted lease');
foreach (['facility' => 'facility_article', 'doctor' => 'doctor_article'] as $type => $task) {
    $db = new WriterLeasePDO(['status' => 'published', 'ai_writer_claim_json' => json_encode(array_replace($claim, ['task' => $task, 'expires_at' => $now + 1]), JSON_THROW_ON_ERROR)]);
    $live = medical_api_writer_claim_refresh($db, $type, 71, $token, null, static fn(): int => $now);
    $assert($live['ok'] && !$live['lease_recovered'] && $live['claim']['expires_at'] === $now + 180,
        $type . ': unexpired legacy heartbeat without task remains supported');
}
$db = new WriterLeasePDO($row);
$db->failUpdate = true;
try {
    medical_api_writer_claim_refresh($db, 'facility', 71, $token, 'facility_image_fix', static fn(): int => $now);
    throw new RuntimeException('Expected simulated storage failure.');
} catch (RuntimeException $error) {
    $assert($error->getMessage() === 'Simulated storage failure.' && $db->row === $row
        && $db->rollbacks === 1 && !$db->inTransaction(), 'storage failure rolls back the lease and releases its lock');
}
$endpoint = file_get_contents(__DIR__ . '/../api/medical/writer-claim.php');
$assert(is_string($endpoint) && str_contains($endpoint, '$row = $lock->fetch(PDO::FETCH_ASSOC);' . "\n" . '    $now = time();'),
    'new claim and same-request renewal also read the clock after their row lock');
echo "writer claim lease: {$checks} checks passed\n";
