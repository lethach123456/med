<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/medical_directory.php';

// Pure contract tests: never connect to the configured database or fetch URLs.
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function (callable $run, string $label) use ($assert): void {
    try { $run(); }
    catch (InvalidArgumentException $error) { $assert(true, $label . ': ' . $error->getMessage()); return; }
    $assert(false, $label . ' must reject');
};
$decode = static fn(array $fields, string $key): array => medical_doctor_decode_array($fields[$key] ?? null, $key);
$template = medical_doctor_output_template(1, 'BS. Test');
$base = array_replace($template, [
    'content' => '<h2>Hồ sơ</h2><p>Bác sĩ Test theo hồ sơ chính thức.</p>',
    'sources_json' => [['id' => 's1', 'url' => 'https://hospital.example.org/doctor/test', 'publisher' => 'Test hospital']],
    'evidence_json' => ['identity_status' => 'matched', 'missing_fields' => [], 'conflicts' => []],
    'insufficient_data' => false,
]);
$aggregate = [
    'entity_type' => 'doctor', 'doctor_id' => 1, 'doctor_name' => 'BS. Test',
    'source' => 'Google Maps', 'score' => 4.6, 'count' => 10,
    'url' => 'https://maps.google.com/doctor', 'checked_at' => '2026-10-14',
    'evidence_text' => 'Trang đánh giá riêng bác sĩ BS. Test, không phải trang cơ sở.',
];
$review = [
    'entity_type' => 'doctor', 'doctor_id' => 1, 'doctor_name' => 'BS. Test',
    'source_text' => 'Google Maps', 'rating' => 5, 'author_text' => 'Tên công khai',
    'excerpt' => 'Bác sĩ Test giải thích rõ các bước khám.', 'review_date_text' => '2026-10-01',
    'url' => 'https://maps.google.com/review/1',
    'evidence_text' => 'Đánh giá công khai nhắc đích danh BS. Test.',
];
$normal = static fn(array $overrides): array => medical_doctor_normalize_payload(array_replace($base, $overrides));

foreach (['aggregate_ratings_json', 'reviews_json'] as $key) {
    $assert(in_array($key, medical_doctor_json_fields(), true), 'doctor JSON contract includes ' . $key);
    $assert(array_key_exists($key, medical_doctor_column_definitions()), 'explicit migration includes ' . $key);
    $assert(($template[$key] ?? null) === [], 'unknown reviews are an empty list: ' . $key);
}
$normalized = $normal(['aggregate_ratings_json' => [$aggregate], 'reviews_json' => [$review]]);
$aggregates = $decode($normalized, 'aggregate_ratings_json');
$reviews = $decode($normalized, 'reviews_json');
$assert(count($aggregates) === 1 && $aggregates[0]['score'] === 4.6, 'verified aggregate score preserved');
$assert($aggregates[0]['count'] === 10 && $aggregates[0]['doctor_id'] === 1, 'aggregate count and identity preserved');
$assert($aggregates[0]['url'] === $aggregate['url'], 'raw aggregate proof URL preserved');
$assert(count($reviews) === 1 && (float) $reviews[0]['rating'] === 5.0, 'individual real rating preserved');
$assert($reviews[0]['excerpt'] === $review['excerpt'], 'public review text preserved');
$assert(($reviews[0]['verified'] ?? null) === false, 'imported review never grants verification');
$assert($decode($normalized, 'sources_json') === $base['sources_json'], 'external reviews do not contaminate clinical profile sources');

$legacy = $base;
unset($legacy['aggregate_ratings_json'], $legacy['reviews_json']);
foreach (['aggregate_ratings', 'reviews', 'reviews_list', 'reviews_list_json'] as $alias) {
    $fields = medical_doctor_normalize_payload($legacy + [$alias => str_starts_with($alias, 'aggregate') ? [$aggregate] : [$review]]);
    $canonical = str_starts_with($alias, 'aggregate') ? 'aggregate_ratings_json' : 'reviews_json';
    $assert(count($decode($fields, $canonical)) === 1, 'legacy transport alias supported: ' . $alias);
    $defaultAlias = $normal([$alias => str_starts_with($alias, 'aggregate') ? [$aggregate] : [$review]]);
    $assert(count($decode($defaultAlias, $canonical)) === 1, 'nonempty alias is not discarded by default canonical empty list: ' . $alias);
}
$reject(fn() => $normal(['aggregate_ratings_json' => [$aggregate],
    'aggregate_ratings' => [array_replace($aggregate, ['score' => 3.2])]]), 'conflicting aggregate aliases rejected');
$reject(fn() => $normal(['reviews_json' => [$review],
    'reviews' => [array_replace($review, ['rating' => 3])]]), 'conflicting individual review aliases rejected');
$reject(fn() => medical_doctor_normalize_payload($legacy + ['reviews' => [$review],
    'reviews_list' => [array_replace($review, ['rating' => 3])]]), 'conflicting old review aliases rejected');
$encoded = $normal(['aggregate_ratings_json' => json_encode([$aggregate]), 'reviews_json' => json_encode([$review])]);
$assert(count($decode($encoded, 'aggregate_ratings_json')) === 1 && count($decode($encoded, 'reviews_json')) === 1, 'JSON encoded arrays supported');
foreach (['aggregate_ratings_json' => $aggregate, 'reviews_json' => $review] as $key => $object) {
    $reject(fn() => $normal([$key => $object]), $key . ' refuses an object instead of a list');
}
foreach (['aggregate_ratings_json' => $aggregate, 'reviews_json' => $review] as $key => $entry) {
    foreach ([null, true, 'text'] as $invalidEntry) $reject(fn() => $normal([$key => [$invalidEntry]]), $key . ' rejects invalid entry');
    foreach ([['entity_type' => 'facility'], ['entity_type' => 'clinic'], ['entity_type' => null],
        ['doctor_id' => 2], ['doctor_id' => 0], ['doctor_id' => '1'], ['doctor_name' => 'Different Doctor'],
        ['doctor_name' => 'BS. Other'], ['evidence_text' => ''], ['evidence_text' => null],
        ['url' => null], ['url' => 'javascript:alert(1)'], ['url' => 'https://user:pass@example.org'],
        ['url' => '[Review](https://example.org/review)'], ['url' => ['https://example.org']]] as $bad) {
        $reject(fn() => $normal([$key => [array_replace($entry, $bad)]]), $key . ' rejects wrong identity or unproven URL');
    }
    $noId = $entry; unset($noId['doctor_id']);
    $reject(fn() => $normal([$key => [$noId]]), $key . ' requires explicit doctor id');
    $noType = $entry; unset($noType['entity_type']);
    $reject(fn() => $normal([$key => [$noType]]), $key . ' requires explicit doctor entity type');
    $aliasUrl = $entry; $aliasUrl['source_url'] = $aliasUrl['url']; unset($aliasUrl['url']);
    $assert($decode($normal([$key => [$aliasUrl]]), $key)[0]['url'] === $entry['url'], $key . ' accepts source_url alias');
}
foreach ([0, -1, 5.1, 6, '4.6', true, null] as $score) {
    $reject(fn() => $normal(['aggregate_ratings_json' => [array_replace($aggregate, ['score' => $score])]]), 'invalid aggregate score');
}
foreach ([0, -1, 1.5, '10', true, null, 1000000001] as $count) {
    $reject(fn() => $normal(['aggregate_ratings_json' => [array_replace($aggregate, ['count' => $count])]]), 'invalid aggregate count');
}
foreach ([0, -1, 5.1, 6, '5/5', true, null] as $rating) {
    $reject(fn() => $normal(['reviews_json' => [array_replace($review, ['rating' => $rating])]]), 'invalid review rating');
}
foreach (['author_text', 'excerpt', 'evidence_text'] as $key) {
    $reject(fn() => $normal(['reviews_json' => [array_replace($review, [$key => ['bad']])]]), 'review text cannot be structured data: ' . $key);
}
foreach (['doctor_name' => 191, 'source_text' => 191, 'evidence_text' => 2001, 'author_text' => 191,
    'excerpt' => 4001, 'review_date_text' => 121] as $key => $length) {
    $reject(fn() => $normal(['reviews_json' => [array_replace($review, [$key => str_repeat('á', $length)])]]), 'Unicode text bound enforced: ' . $key);
}
$reject(fn() => $normal(['aggregate_ratings_json' => array_fill(0, 21, $aggregate)]), 'aggregate input list bound enforced before deduplication');
$reject(fn() => $normal(['reviews_json' => array_fill(0, 51, $review)]), 'individual review input list bound enforced before deduplication');
$reject(fn() => $normal(['aggregate_ratings_json' => [array_replace($aggregate, ['checked_at' => '2026-02-30'])]]), 'impossible checked_at date rejected');
$reject(fn() => $normal(['reviews_json' => [array_replace($review, ['excerpt' => '<script></script>'])]]), 'empty HTML cannot masquerade as a public review');
$reject(fn() => $normal(['aggregate_ratings_json' => [array_replace($aggregate, ['source' => ''])]]), 'aggregate requires publisher/platform');
$reject(fn() => $normal(['reviews_json' => [array_replace($review, ['source_text' => ''])]]), 'review requires publisher/platform');
$alternateAggregate = $aggregate; $alternateAggregate['source_text'] = $alternateAggregate['source']; unset($alternateAggregate['source']);
$alternateReview = $review; $alternateReview['source'] = $alternateReview['source_text']; unset($alternateReview['source_text']);
$assert(count($decode($normal(['aggregate_ratings_json' => [$alternateAggregate]]), 'aggregate_ratings_json')) === 1, 'aggregate accepts source_text alias');
$assert(count($decode($normal(['reviews_json' => [$alternateReview]]), 'reviews_json')) === 1, 'review accepts source alias');

$duplicateAggregate = $normal(['aggregate_ratings_json' => [$aggregate, $aggregate]]);
$duplicateReview = $normal(['reviews_json' => [$review, $review]]);
$assert(count($decode($duplicateAggregate, 'aggregate_ratings_json')) === 1, 'duplicate aggregate URL deduplicated');
$assert(count($decode($duplicateReview, 'reviews_json')) === 1, 'duplicate review URL deduplicated');
$assert(count($decode($normal(['reviews_json' => [$review, array_replace($review, ['rating' => 5.0])]]), 'reviews_json')) === 1,
    'semantically equal integer/float review ratings deduplicate');
$intAggregate = array_replace($aggregate, ['score' => 4]);
$assert(count($decode($normal(['aggregate_ratings_json' => [$intAggregate, array_replace($intAggregate, ['score' => 4.0])]]), 'aggregate_ratings_json')) === 1,
    'semantically equal integer/float aggregate scores deduplicate');
$mirroredReview = array_replace($review, ['url' => 'https://official.example.org/test/reviews/1']);
$assert(count($decode($normal(['reviews_json' => [$review, $mirroredReview]]), 'reviews_json')) === 1, 'same public review mirrored across source URLs counted once');
$reject(fn() => $normal(['reviews_json' => [$review, array_replace($mirroredReview, ['rating' => 3])]]), 'same public review at different URL cannot conflict on rating');
$samePageReview = array_replace($review, ['author_text' => 'Người khác', 'excerpt' => 'Bác sĩ Test tư vấn theo dõi rõ ràng.', 'rating' => 4]);
$sharedPage = $normal(['reviews_json' => [$review, $samePageReview]]);
$assert(count($decode($sharedPage, 'reviews_json')) === 2, 'different public reviews on one doctor source page both retained');
$sharedPageSummary = medical_doctor_review_rating_summary($sharedPage);
$assert($sharedPageSummary !== null && (float) $sharedPageSummary['rating'] === 4.5 && $sharedPageSummary['reviews_count'] === 2, 'shared source page preserves distinct review summary count');
$reject(fn() => $normal(['aggregate_ratings_json' => [$aggregate, array_replace($aggregate, ['score' => 3.2])]]), 'conflicting aggregate URL rejected');
$reject(fn() => $normal(['reviews_json' => [$review, array_replace($review, ['rating' => 3])]]), 'conflicting review URL rejected');
$forged = $decode($normal(['reviews_json' => [array_replace($review, ['verified' => true])]]), 'reviews_json');
$assert(($forged[0]['verified'] ?? null) === false, 'AI supplied verified true is never retained');

$summary = medical_doctor_review_rating_summary($normalized);
$assert($summary !== null && (float) $summary['rating'] === 4.6 && $summary['reviews_count'] === 10, 'aggregate takes precedence over sampled individual reviews');
$assert(is_string($summary['basis'] ?? null) && $summary['basis'] !== '', 'summary identifies its provenance basis');
$secondAggregate = array_replace($aggregate, ['url' => 'https://maps.google.com/doctor/second-source', 'score' => 4, 'count' => 30]);
$weighted = medical_doctor_review_rating_summary($normal(['aggregate_ratings_json' => [$aggregate, $secondAggregate], 'reviews_json' => [$review]]));
$assert($weighted !== null && (float) $weighted['rating'] === 4.2 && $weighted['reviews_count'] === 40, 'weighted sources derive rounded summary without counting samples again');
$secondReview = array_replace($review, ['url' => 'https://maps.google.com/review/2', 'rating' => 3,
    'author_text' => 'Người đánh giá thứ hai', 'excerpt' => 'Bác sĩ Test hướng dẫn theo dõi sau khám.']);
$individual = medical_doctor_review_rating_summary($normal(['reviews_json' => [$review, $secondReview]]));
$assert($individual !== null && (float) $individual['rating'] === 4.0 && $individual['reviews_count'] === 2, 'individual reviews provide fallback average');
$assert(medical_doctor_review_rating_summary($normal(['aggregate_ratings_json' => [], 'reviews_json' => []])) === null, 'empty review lists do not manufacture a score or erase existing rating');
$direct = $normal(['rating' => 5, 'reviews_count' => 500]);
$assert(!isset($direct['rating'], $direct['reviews_count']) && medical_doctor_review_rating_summary($direct) === null, 'direct AI system scalars still ignored without evidence');
$insufficient = array_replace($base, ['insufficient_data' => true, 'notes_for_editor' => 'Không đủ nhận diện bác sĩ.',
    'content' => null, 'sources_json' => [], 'evidence_json' => ['identity_status' => 'insufficient']]);
$assert(medical_doctor_review_rating_summary(medical_doctor_normalize_payload($insufficient)) === null, 'insufficient identity with no reviews remains supported');
foreach (['aggregate_ratings_json' => [$aggregate], 'reviews_json' => [$review]] as $key => $items) {
    $reject(fn() => medical_doctor_normalize_payload(array_replace($insufficient, [$key => $items])), 'insufficient identity cannot import ' . $key);
}

$defaultPrompt = medical_doctor_default_prompt();
$customPrompt = medical_doctor_research_prompt('Custom legacy prompt for {{name}}', ['id' => 1, 'name' => 'BS. Test']);
foreach (['aggregate_ratings_json', 'reviews_json', 'entity_type', 'doctor_id', 'evidence_text'] as $key) {
    $assert(str_contains($defaultPrompt, $key) && str_contains($customPrompt, $key), 'default and legacy custom prompt document review contract: ' . $key);
}
$assert(str_contains($defaultPrompt, 'MEDREVIEW_DOCTOR_PRIMARY_SOURCES_V1') && str_contains($customPrompt, 'MEDREVIEW_DOCTOR_PRIMARY_SOURCES_V1'), 'review support retains primary clinical-source policy');
$mapped = medical_directory_doctor_from_row(['name' => 'BS. Test', 'aggregate_ratings_json' => $normalized['aggregate_ratings_json'], 'reviews_json' => $normalized['reviews_json']]);
$assert(is_array($mapped['aggregate_ratings_json'] ?? null) && is_array($mapped['reviews_json'] ?? null), 'new doctor review arrays available from public row mapping');

// An English profile represents the same original practitioner. Trusted admin
// code supplies the source row's identity, while the AI receive path must never
// gain a bypass for the current doctor's identity from its own payload.
$english = array_replace($base, ['id' => 2, 'name' => 'Dr. Test',
    'aggregate_ratings_json' => [$aggregate], 'reviews_json' => [$review]]);
$reject(fn() => medical_doctor_normalize_payload($english, false), 'English copied reviews need trusted original identity');
$englishNormalized = medical_doctor_normalize_payload($english, false, ['id' => 1, 'name' => 'BS. Test']);
$assert($decode($englishNormalized, 'aggregate_ratings_json')[0]['doctor_id'] === 1
    && $decode($englishNormalized, 'reviews_json')[0]['doctor_id'] === 1, 'English admin retains original doctor identity in copied feedback');
$assert($decode($englishNormalized, 'aggregate_ratings_json')[0]['doctor_name'] === 'BS. Test'
    && $decode($englishNormalized, 'reviews_json')[0]['doctor_name'] === 'BS. Test', 'English admin retains original evidence doctor name');
$assert($decode($englishNormalized, 'aggregate_ratings_json')[0]['url'] === $aggregate['url']
    && $decode($englishNormalized, 'reviews_json')[0]['excerpt'] === $review['excerpt'], 'English trusted identity does not translate source evidence');
foreach ([['id' => 99, 'name' => 'BS. Test'], ['id' => 1, 'name' => 'Different Doctor'],
    ['id' => '1', 'name' => 'BS. Test'], ['id' => 0, 'name' => 'BS. Test'], []] as $identity) {
    $reject(fn() => medical_doctor_normalize_payload($english, false, $identity), 'wrong or incomplete trusted source identity rejected');
}
$reject(fn() => medical_doctor_normalize_payload($english, true, ['id' => 1, 'name' => 'BS. Test']), 'strict AI research cannot override parent doctor identity');
$forgedIdentity = array_replace($english, ['review_identity' => ['id' => 1, 'name' => 'BS. Test']]);
$reject(fn() => medical_doctor_normalize_payload($forgedIdentity), 'AI payload metadata cannot supply trusted original identity');

// Source-level checks deliberately avoid loading the authenticated translation
// endpoint, which would read credentials and connect to the configured DB.
$translationCode = (string) file_get_contents(dirname(__DIR__) . '/api/medical/translate-content.php');
$doctorMapStart = strpos($translationCode, "if (\$type === 'doctor') {");
$doctorMapEnd = strpos($translationCode, 'return $fields;', $doctorMapStart === false ? 0 : $doctorMapStart);
$assert($doctorMapStart !== false && $doctorMapEnd !== false, 'doctor translation field map located for source guard');
$doctorMap = substr($translationCode, $doctorMapStart, $doctorMapEnd - $doctorMapStart);
$excludedMatched = preg_match('/if \(in_array\(\$field, \[(.*?)\], true\)\) continue;/s', $doctorMap, $excluded);
$assert($excludedMatched === 1, 'doctor translation evidence exclusion remains explicit');
foreach (['aggregate_ratings_json', 'reviews_json', 'sources_json', 'legal_source_ids_json'] as $key) {
    $assert(str_contains($excluded[1] ?? '', "'{$key}'"), 'translation AI cannot overwrite preserved doctor evidence: ' . $key);
}
$assert(str_contains($doctorMap, '$fields[$field] = $json([$field]);'), 'other supported doctor JSON facts remain translatable');

// In-memory PDO doubles exercise the real locked save without any connection,
// production credentials, migrations, media fetching, or network access.
final class DoctorReviewsTestPDO extends PDO
{
    public array $queries = [];
    public array $doctor;
    public int $commits = 0;
    public int $rollbacks = 0;
    public int $locationRows = 1;
    public bool $failUpdate = false;
    private bool $active = false;
    private array $snapshot = [];
    public function __construct(array $doctor) { $this->doctor = $doctor; }
    public function beginTransaction(): bool
    {
        if ($this->active) throw new RuntimeException('Unexpected nested transaction');
        $this->snapshot = [$this->doctor, $this->locationRows];
        $this->active = true;
        return true;
    }
    public function commit(): bool { $this->active = false; $this->commits++; return true; }
    public function rollBack(): bool
    {
        [$this->doctor, $this->locationRows] = $this->snapshot;
        $this->active = false;
        $this->rollbacks++;
        return true;
    }
    public function inTransaction(): bool { return $this->active; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!str_starts_with($query, 'SELECT * FROM medical_doctors WHERE id=:id FOR UPDATE')
            && !str_starts_with($query, 'DELETE FROM medical_doctor_facilities WHERE doctor_id=:id')
            && !str_starts_with($query, 'UPDATE medical_doctors SET ')) {
            throw new RuntimeException('Unexpected SQL in isolated review save: ' . $query);
        }
        $this->queries[] = $query;
        return new DoctorReviewsTestStatement($this, $query);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        throw new RuntimeException('Unexpected direct query in isolated review save');
    }
    public function exec(string $statement): int|false { throw new RuntimeException('Unexpected DDL/exec'); }
}
final class DoctorReviewsTestStatement extends PDOStatement
{
    public function __construct(private DoctorReviewsTestPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (!$this->pdo->inTransaction()) throw new RuntimeException('Database operation outside transaction');
        if (str_starts_with($this->sql, 'DELETE')) $this->pdo->locationRows = 0;
        elseif (str_starts_with($this->sql, 'UPDATE')) {
            if ($this->pdo->failUpdate) throw new RuntimeException('Simulated update failure');
            foreach ($params ?? [] as $key => $value) if ($key !== ':id') $this->pdo->doctor[ltrim($key, ':')] = $value;
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return str_starts_with($this->sql, 'SELECT') ? $this->pdo->doctor : false;
    }
}
$token = str_repeat('d', 64);
$claim = ['claim_token' => $token, 'request_id' => 'reviews-attempt', 'expires_at' => time() + 180];
$row = ['id' => 1, 'slug' => 'review-test', 'name' => 'BS. Test', 'status' => 'published', 'language_code' => 'vi',
    'content' => null, 'last_researched_at' => null, 'facility_name' => '', 'facility_slug' => '',
    'rating' => 4.3, 'reviews_count' => 8, 'verified' => 1, 'ai_writer_claim_json' => json_encode($claim)];
$savePayload = array_replace($base, ['writer_claim_token' => $token, 'aggregate_ratings_json' => [$aggregate], 'reviews_json' => [$review],
    'rating_summary' => ['rating' => 5, 'reviews_count' => 999999, 'basis' => 'AI invented']]);
$pdo = new DoctorReviewsTestPDO($row);
$savedResult = medical_doctor_save_research_locked($pdo, $savePayload);
$assert($pdo->commits === 1 && $pdo->rollbacks === 0, 'research and reviews commit together');
$assert((float) $pdo->doctor['rating'] === 4.6 && (int) $pdo->doctor['reviews_count'] === 10, 'save derives doctor rating from validated aggregate');
$assert($savedResult['rating_summary']['reviews_count'] === 10 && $savedResult['reviews_imported'] === 1
    && $savedResult['aggregate_sources'] === 1, 'API receipt reports persisted review totals');
$assert($pdo->doctor['ai_writer_claim_json'] === null && $savedResult['writer_released'] === true, 'successful review save releases writer lease');
$assert((int) $pdo->doctor['verified'] === 0 && $pdo->doctor['verification_status'] === 'unreviewed', 'imported feedback cannot approve doctor profile');
$assert(!str_contains($pdo->doctor['full_json'], $token), 'raw saved research never leaks writer token');
$savedRaw = json_decode($pdo->doctor['full_json'], true);
$assert(($savedRaw['rating_summary'] ?? null) === $savedResult['rating_summary']
    && ($savedRaw['rating_summary']['reviews_count'] ?? null) === 10, 'server overwrites forged AI summary with derived provenance in raw saved research');
$assert(!array_filter($pdo->queries, static fn(string $sql): bool => str_contains($sql, 'medical_reviews')), 'doctor import never creates facility review records');
$emptyPdo = new DoctorReviewsTestPDO($row);
$emptyResult = medical_doctor_save_research_locked($emptyPdo, array_replace($base, ['writer_claim_token' => $token, 'rating' => 5, 'reviews_count' => 999]));
$assert((float) $emptyPdo->doctor['rating'] === 4.3 && (int) $emptyPdo->doctor['reviews_count'] === 8, 'no proven feedback preserves existing doctor rating');
$assert($emptyResult['rating_summary'] === null && $emptyResult['reviews_imported'] === 0, 'empty receipt does not pretend reviews imported');
$withEarlierFeedback = $row + [
    'aggregate_ratings_json' => medical_directory_json_encode([array_replace($aggregate, ['score' => 4.3, 'count' => 8])]),
    'reviews_json' => medical_directory_json_encode([$review]),
];
$preservedPdo = new DoctorReviewsTestPDO($withEarlierFeedback);
$preservedResult = medical_doctor_save_research_locked($preservedPdo, array_replace($base, ['writer_claim_token' => $token]));
foreach (['aggregate_ratings_json', 'reviews_json', 'rating', 'reviews_count'] as $key) {
    $assert($preservedPdo->doctor[$key] === $withEarlierFeedback[$key], 'empty new feedback preserves prior evidence/statistic: ' . $key);
}
$assert(!in_array('aggregate_ratings_json', $preservedResult['saved_fields'], true)
    && !in_array('reviews_json', $preservedResult['saved_fields'], true), 'receipt does not claim empty feedback replaced existing evidence');
$failurePdo = new DoctorReviewsTestPDO($row);
$failurePdo->failUpdate = true;
try { medical_doctor_save_research_locked($failurePdo, $savePayload); $assert(false, 'simulated save failure must propagate'); }
catch (RuntimeException $error) { $assert($error->getMessage() === 'Simulated update failure', 'simulated save failure propagated'); }
$assert($failurePdo->commits === 0 && $failurePdo->rollbacks === 1 && !$failurePdo->inTransaction(), 'failed save rolls back full transaction');
$assert($failurePdo->doctor === $row && $failurePdo->locationRows === 1, 'rollback preserves existing review statistics, lease and workplace link');
$foreignPdo = new DoctorReviewsTestPDO($row);
try { medical_doctor_save_research_locked($foreignPdo, array_replace($savePayload, ['writer_claim_token' => str_repeat('e', 64)])); $assert(false, 'foreign lease must reject'); }
catch (MedicalDoctorConflict $error) { $assert($error->getMessage() === 'lease_lost_or_expired', 'review save enforces owning writer lease'); }
$assert($foreignPdo->doctor === $row && $foreignPdo->locationRows === 1 && $foreignPdo->rollbacks === 1, 'foreign writer cannot change doctor feedback or workplaces');

echo "Doctor reviews: {$checks} checks passed.\n";
