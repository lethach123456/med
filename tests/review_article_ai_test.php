<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_review_article_ai.php';

/** No configured DB, real network or filesystem writes: fixtures live only in memory. */
final class ReviewArticleAiTestPDO extends PDO
{
    public bool $runtime = false;
    public array $statements = [];
    public function __construct() { parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
    private function record(string $sql): void
    {
        if (!$this->runtime) return;
        $this->statements[] = $sql;
        if (preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|REPLACE)\b/i', $sql)) throw new RuntimeException('Runtime DDL is forbidden.');
        if (preg_match('/^\s*(UPDATE|INSERT|DELETE)\b/i', $sql) && !preg_match('/^\s*(UPDATE medical_review_articles|INSERT INTO medical_review_articles)\b/i', $sql)) throw new RuntimeException('Only article metadata may be changed.');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { $this->record($query); return parent::prepare($query, $options); }
    public function exec(string $statement): int|false { $this->record($statement); return parent::exec($statement); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->record($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

function review_article_ai_fixture(): ReviewArticleAiTestPDO
{
    $pdo = new ReviewArticleAiTestPDO();
    $columns = [];
    foreach (medical_review_articles_facility_columns() as $field) $columns[] = $field . ($field === 'id' ? ' INTEGER PRIMARY KEY' : ' TEXT');
    $pdo->exec('CREATE TABLE medical_facilities (' . implode(',', $columns) . ',rating REAL,reviews_count INTEGER,full_json TEXT,patient_images_json TEXT,patient_images_notes TEXT,ai_writer_claim_json TEXT)');
    $pdo->exec('CREATE TABLE medical_reviews (id INTEGER PRIMARY KEY AUTOINCREMENT,facility_id INTEGER,facility_slug TEXT,status TEXT,rating REAL,excerpt TEXT,service_text TEXT,review_date_text TEXT,source_text TEXT,author_text TEXT)');
    medreview_with_schema_migration(static fn() => medical_review_articles_migrate($pdo));
    $insert = $pdo->prepare('INSERT INTO medical_facilities (id,slug,name,category,city,status,language_code,website_url,google_maps_url,social_links_json,aggregate_ratings_json,facility_insights_json,rating,reviews_count,full_json,patient_images_json,patient_images_notes,ai_writer_claim_json) VALUES (:id,:slug,:name,\'Nha khoa\',\'Đà Nẵng\',:status,:locale,\'https://clinic.example.org/about\',\'https://maps.google.com/?cid=123\',:social,:aggregates,:insights,5,9000,\'PRIVATE_FULL\',\'PRIVATE_PATIENT_IMAGES\',\'PRIVATE_PATIENT_NOTES\',\'PRIVATE_FACILITY_TOKEN\')');
    foreach (range(1, 8) as $id) {
        $aggregates = in_array($id, [2, 7], true) ? [] : [['source' => 'Google Maps', 'score' => 4.4, 'count' => $id === 3 ? 40 : 80, 'url' => 'https://maps.google.com/?cid=123']];
        $insights = $id === 4 ? '{}' : json_encode(['summary' => 'Cơ sở có thông tin về phòng chờ và dịch vụ trên trang giới thiệu công khai.']);
        $insert->execute([':id' => $id, ':slug' => 'clinic-' . $id, ':name' => 'Cơ sở ' . $id, ':status' => $id === 5 ? 'draft' : 'published',
            ':locale' => $id === 6 ? 'en' : 'vi', ':social' => json_encode([['platform' => 'Facebook', 'url' => 'https://facebook.com/clinic', 'patient_images_notes' => 'PRIVATE_NESTED']]),
            ':aggregates' => json_encode($aggregates), ':insights' => $insights]);
    }
    $review = $pdo->prepare("INSERT INTO medical_reviews (facility_id,facility_slug,status,rating,excerpt,service_text,review_date_text,source_text,author_text) VALUES (:id,:slug,'published',4.2,:excerpt,'Khám tư vấn','2026-10-01','Google Maps','PRIVATE_REVIEW_AUTHOR')");
    foreach ([1 => 3, 2 => 45] as $id => $count) for ($i = 0; $i < $count; ++$i) {
        $review->execute([':id' => $id, ':slug' => 'clinic-' . $id, ':excerpt' => 'Phòng chờ sạch và nhân viên hướng dẫn lịch hẹn. Liên hệ 0901234567 user@example.org.']);
    }
    $pdo->runtime = true;
    return $pdo;
}

$checks = 0;
$assert = static function (bool $value, string $message) use (&$checks): void { ++$checks; if (!$value) throw new RuntimeException($message); };
$throws = static function (callable $call, int $status, ?string $reason = null) use ($assert): void {
    try { $call(); }
    catch (MedicalReviewArticleAiException $error) {
        $assert($error->status === $status, 'Unexpected API status: ' . $error->getMessage());
        if ($reason !== null) $assert($error->reason === $reason, 'Unexpected reason ' . $error->reason);
        return;
    }
    throw new RuntimeException('Expected API exception ' . $status . ' ' . $reason);
};

foreach (['', '{', 'null', '[]', '123', '{"x":"' . chr(255) . '"}'] as $raw) $throws(static fn() => medical_review_article_ai_parse_body($raw), 400);
$assert(medical_review_article_ai_parse_body('{}') === [], 'An empty JSON object is still an object root');
$throws(static fn() => medical_review_article_ai_parse_body('{"x":[' . str_repeat('0,', 21000) . '0]}'), 400, 'json_too_complex');
$throws(static fn() => medical_review_article_ai_parse_body('{"x":123}', 2), 413);
foreach ([null, 0, -1, 1.5, true, '01', '1 OR 1=1', '4294967296', []] as $id) $throws(static fn() => medical_review_article_ai_id($id), 422);
$assert(medical_review_article_ai_id('4294967295') === 4294967295, 'Unsigned entity range');
$pdo = review_article_ai_fixture();
$queue = medical_review_article_ai_queue($pdo, ['limit' => 1], null, 1000);
$assert($queue['total'] === 3 && $queue['pages'] === 3 && count($queue['items']) === 1, 'Only eligible external/stored facilities are queued');
$assert($queue['items'][0]['entity_id'] === 1, 'Stable source ordering');
$assert($queue['items'][0]['source']['sample_count'] === 3 && $queue['items'][0]['source']['review_metrics']['reviews_count'] === 80, 'Sample count is not aggregate count');
$assert($queue['save_status'] === 'draft' && $queue['claim_required'] === true, 'Draft and ownership defaults');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_review_articles')->fetchColumn() === 0, 'GET never creates draft placeholders');
$queueJson = medical_review_article_ai_json($queue);
foreach (['PRIVATE_FULL', 'PRIVATE_PATIENT_IMAGES', 'PRIVATE_PATIENT_NOTES', 'PRIVATE_FACILITY_TOKEN', 'PRIVATE_REVIEW_AUTHOR', 'PRIVATE_NESTED', '0901234567', 'user@example.org'] as $private) $assert(!str_contains($queueJson, $private), 'AI source must not expose ' . $private);
foreach ($pdo->statements as $sql) $assert(!preg_match('/^\s*(UPDATE|INSERT|DELETE|CREATE|ALTER)/i', $sql), 'GET is read-only');
$second = medical_review_article_ai_queue($pdo, ['page' => 2, 'limit' => 1], null, 1000);
$assert($second['items'][0]['entity_id'] === 2 && $second['items'][0]['source']['sample_count'] === 40, 'Actual stored review count also qualifies, sample bounded');
$english = medical_review_article_ai_queue($pdo, ['language' => 'en'], null, 1000);
$assert($english['total'] === 1 && $english['items'][0]['entity_id'] === 6, 'Language queues separated');
$throws(static fn() => medical_review_article_ai_queue($pdo, ['limit' => 26]), 422);
$throws(static fn() => medical_review_article_ai_target(['entity_type' => 'doctor', 'entity_id' => 1]), 422);
$throws(static fn() => medical_review_article_ai_target(['entity_id' => 1, 'id' => 2]), 422);
$source = $queue['items'][0]['source']; $revision = $queue['items'][0]['source_revision'];
$prompt = medical_review_article_ai_prompt($source, $revision, 'Custom {{source_json}} {{output_template}}');
$assert(!str_contains($prompt, '{{output_template}}') && str_contains($prompt, 'RÀNG BUỘC BẮT BUỘC'), 'Variants receive source/output and mandatory editorial constraints');
$assert(str_contains(medical_review_article_ai_default_prompt(), 'không giả vờ đã đến khám'), 'No fabricated personal experience');
$leaseBody = ['entity_type' => 'facility', 'entity_id' => 1, 'language_code' => 'vi', 'source_revision' => $revision,
    'request_id' => 'article-1', 'client' => ['instance_id' => 'device-a', 'worker_id' => 'worker-a', 'provider' => 'ChatGPT']];
$lease = medical_review_article_ai_lease($pdo, $leaseBody, 1000);
$assert(strlen($lease['claim_token']) === 64 && $lease['expires_at'] === 1900, 'Claim returns bounded lease token');
$stored = medical_review_article_ai_article_row($pdo, 1, 'vi');
$assert(!str_contains((string) $stored['ai_writer_claim_json'], $lease['claim_token']), 'Only token hash is persisted');
$assert(str_contains((string) $stored['ai_writer_claim_json'], hash('sha256', $lease['claim_token'])), 'Hash binds owner');
$throws(static fn() => medical_review_article_ai_lease($pdo, $leaseBody, 1001), 409, 'already_claimed');
$renew = medical_review_article_ai_lease($pdo, $leaseBody + ['claim_token' => $lease['claim_token']], 1050);
$assert($renew['claim_token'] === $lease['claim_token'], 'Owner may retry claim only with retained token');
$status = medical_review_article_ai_lease($pdo, ['entity_id' => 1, 'action' => 'status'], 1051);
$statusJson = medical_review_article_ai_json($status);
$assert($status['claimed'] === true && !str_contains($statusJson, 'token_sha256') && !str_contains($statusJson, $lease['claim_token']), 'Status never discloses owner secret');
$assert(medical_review_article_ai_queue($pdo, [], null, 1051)['total'] === 2, 'Active claim excluded');
$heartbeat = medical_review_article_ai_lease($pdo, ['entity_id' => 1, 'action' => 'heartbeat', 'claim_token' => $lease['claim_token']], 1100);
$assert($heartbeat['expires_at'] === 2000, 'Heartbeat refresh');
$articleBody = medical_review_article_ai_output_template($source, $revision);
$articleBody['title'] = 'Review Cơ sở 1: dịch vụ và những điểm cần cân nhắc';
$articleBody['excerpt'] = 'Phân tích thông tin cơ sở và tín hiệu từ một số phản hồi công khai.';
$articleBody['verdict'] = 'Dữ liệu hiện có hỗ trợ tìm hiểu dịch vụ, không đủ để kết luận hiệu quả điều trị.';
$articleBody['content_html'] = '<h2>Tổng quan</h2><p>' . str_repeat('Thông tin này dựa trên hồ sơ công khai và một số phản hồi được lưu; người đọc nên xác nhận trực tiếp trước khi đặt lịch. ', 5) . '</p><script>alert(1)</script>';
$articleBody['strengths_json'] = ['Có thông tin địa điểm công khai'];
$articleBody['considerations_json'] = ['Chưa có thông tin chi phí đầy đủ'];
$articleBody['suitable_for_json'] = ['Người cần tìm hiểu địa điểm và hỏi lịch khám'];
$articleBody['evidence_limitations_json'] = ['Chỉ đọc 3 mẫu phản hồi, không phải toàn bộ 80 đánh giá của nguồn bên ngoài.'];
$articleBody['claim_token'] = $lease['claim_token']; $articleBody['request_id'] = 'article-1';
$articleBody['researched_at'] = '2026-10-22';
$wrong = $articleBody; $wrong['claim_token'] = str_repeat('a', 64);
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 409, 'lease_lost');
$wrong = $articleBody; $wrong['sources_json'][0]['url'] = 'https://unprovided.example.org';
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 422, 'unknown_source');
$wrong = $articleBody; $wrong['patient_images_notes'] = 'do not accept';
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 422);
$receipt = medical_review_article_ai_save($pdo, $articleBody, 1150);
$assert($receipt['saved'] && $receipt['storage_verified'] && !$receipt['idempotent'] && $receipt['status'] === 'draft', 'Real save verified by DB readback, draft by default');
$saved = medical_review_article_ai_article_row($pdo, 1, 'vi');
$assert($saved['ai_writer_claim_json'] === null && !str_contains((string) $saved['content_html'], '<script'), 'Claim released and unsafe HTML removed');
$assert((int) $saved['source_review_count'] === 80 && $saved['source_rating_basis'] === 'external_aggregates', 'Stored source metrics preserve provenance');
$assert(!str_contains((string) $saved['source_snapshot_json'], 'PRIVATE_'), 'Snapshot contains allowed source only');
$retry = medical_review_article_ai_save($pdo, $articleBody, 9999);
$assert($retry['idempotent'] && $retry['article_id'] === $receipt['article_id'], 'Safe exact replay after lease expiration');
$wrong = $articleBody; $wrong['title'] .= ' CHANGED';
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1200), 409, 'idempotency_conflict');
$pdo->prepare('UPDATE medical_review_articles SET verdict=:verdict WHERE id=:id')->execute([':verdict' => 'Edited after AI save', ':id' => $receipt['article_id']]);
$throws(static fn() => medical_review_article_ai_save($pdo, $articleBody, 1200), 409, 'article_changed');
$assert(!medical_review_article_ai_needs_content($saved), 'Saved draft is not silently rewritten');

// A suppressed SQL update cannot be mistaken for successful storage.
$two = $second['items'][0];
$claimTwo = medical_review_article_ai_lease($pdo, ['entity_id' => 2, 'source_revision' => $two['source_revision'], 'request_id' => 'article-2', 'client' => ['instance_id' => 'd', 'worker_id' => 'w']], 1000);
$bodyTwo = $articleBody; $bodyTwo['entity_id'] = 2; $bodyTwo['source_revision'] = $two['source_revision']; $bodyTwo['claim_token'] = $claimTwo['claim_token']; $bodyTwo['request_id'] = 'article-2';
$bodyTwo['title'] = 'Review Cơ sở 2'; $bodyTwo['status'] = 'published'; $bodyTwo['sources_json'] = medical_review_article_ai_source_catalog($two['source']);
$pdo->runtime = false;
$pdo->exec("CREATE TRIGGER suppress_review_update BEFORE UPDATE OF content_html ON medical_review_articles WHEN NEW.entity_id=2 BEGIN SELECT RAISE(IGNORE); END");
$pdo->runtime = true;
$failed = false;
try { medical_review_article_ai_save($pdo, $bodyTwo, 1100); } catch (RuntimeException $error) { $failed = str_contains($error->getMessage(), 'readback'); }
$assert($failed && !$pdo->inTransaction(), 'Suppressed update rolls back after failed readback');
$assert(trim((string) medical_review_article_ai_article_row($pdo, 2, 'vi')['content_html']) === '', 'No false content receipt');
$pdo->runtime = false; $pdo->exec('DROP TRIGGER suppress_review_update'); $pdo->runtime = true;
$published = medical_review_article_ai_save($pdo, $bodyTwo, 1100);
$assert($published['status'] === 'published' && medical_review_article_ai_article_row($pdo, 2, 'vi')['published_at'] !== null, 'Explicit publishing supported');

// Changed evidence invalidates the lease without overwriting article or source data.
$eight = medical_review_articles_facility_source($pdo, 8);
$claimEight = medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'source_revision' => medical_review_articles_source_revision($eight), 'request_id' => 'article-8', 'client' => ['instance_id' => 'd', 'worker_id' => 'w']], 1000);
$pdo->runtime = false; $pdo->exec("UPDATE medical_facilities SET subtitle='Updated by editor' WHERE id=8"); $pdo->runtime = true;
$throws(static fn() => medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'action' => 'heartbeat', 'claim_token' => $claimEight['claim_token']], 1100), 409, 'source_revision_changed');
$bodyEight = $articleBody; $bodyEight['entity_id'] = 8; $bodyEight['source_revision'] = $claimEight['source_revision']; $bodyEight['claim_token'] = $claimEight['claim_token']; $bodyEight['request_id'] = 'article-8';
$throws(static fn() => medical_review_article_ai_save($pdo, $bodyEight, 1100), 409, 'source_revision_changed');
$release = medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'action' => 'release', 'claim_token' => $claimEight['claim_token']], 1100);
$assert($release['released'] === true, 'Owner can release stale evidence task');
$throws(static fn() => medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'action' => 'heartbeat', 'claim_token' => $claimEight['claim_token']], 1101), 409, 'lease_lost');
$eight = medical_review_articles_facility_source($pdo, 8);
$newClaim = medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'source_revision' => medical_review_articles_source_revision($eight), 'request_id' => 'article-8-new', 'client' => ['instance_id' => 'd', 'worker_id' => 'w']], 1200);
$throws(static fn() => medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'action' => 'heartbeat', 'claim_token' => $newClaim['claim_token']], 2200), 409, 'lease_lost');
echo 'Review article AI: ' . $checks . " checks passed.\n";
