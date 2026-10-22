<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';

// Execute the real receiver's pure boundaries against in-memory fixtures only.
// Never include its authentication/bootstrap or connect to the configured DB.
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};
$reject = static function (callable $call, string $label) use ($assert): Throwable {
    try { $call(); } catch (Throwable $e) { $assert(true, $label); return $e; }
    $assert(false, $label . ' must fail');
    throw new LogicException('Unreachable');
};
function medical_api_json($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
final class InsightsContentTestResponse extends RuntimeException
{
    public function __construct(public array $body, public int $status) { parent::__construct('Isolated response'); }
}
function json_response(array $payload, int $statusCode = 200): void
{
    throw new InsightsContentTestResponse($payload, $statusCode);
}
$endpoint = (string) file_get_contents(dirname(__DIR__) . '/api/medical/facility-content-update.php');
$extract = static function (string $source, string $start, string $end) use ($assert): string {
    $from = strpos($source, $start);
    $to = $from === false ? false : strpos($source, $end, $from);
    $assert($from !== false && $to !== false, 'Locate actual boundary: ' . $start);
    return substr($source, $from, $to - $from);
};
eval($extract($endpoint, 'function medical_api_content_rating_list(', 'function medical_api_content_bool('));
$preflightCode = $extract($endpoint, '$patientImagesRequested = false;', '// Schema is installed by explicit maintenance');
$preflight = static function (array $items) use ($preflightCode): array {
    eval($preflightCode);
    return ['items' => $items, 'insights' => $facilityInsightsRequested,
        'patient_images' => $patientImagesRequested, 'patient_notes' => $patientImageNotesRequested];
};
$analysis = ['schema_version' => 1, 'summary' => 'INTERNAL_ANALYSIS_ONLY',
    'sources' => [['id' => 's1', 'url' => 'https://clinic.example.org/about']],
    'findings' => [['topic' => 'Dịch vụ', 'summary' => 'Cơ sở công bố thông tin trên website.',
        'source_ids' => ['s1'], 'evidence_type' => 'official_statement', 'confidence' => 'medium']],
    'limitations' => ['Chưa đối chiếu độc lập.']];
$encoded = medical_facility_insights_encode($analysis);
$legacy = $preflight([['id' => 9, 'content' => '<p>Old client</p>']]);
$assert($legacy['insights'] === false && !$legacy['patient_images'] && !$legacy['patient_notes'], 'Legacy payload does not request any optional schema');
$batch = $preflight([['id' => 9, 'facility_insights_json' => $analysis], ['id' => 10, 'facility_insights_json' => $encoded]]);
$assert($batch['insights'] && !$batch['patient_images'] && !$batch['patient_notes'], 'Insights does not require patient schema');
$assert(medical_facility_insights_encode($batch['items'][0]['facility_insights_json']) === $encoded
    && medical_facility_insights_encode($batch['items'][1]['facility_insights_json']) === $encoded, 'Object and JSON-string batch inputs normalize identically');
$badBatch = $reject(static fn() => $preflight([
    ['id' => 9, 'facility_insights_json' => $analysis],
    ['id' => 10, 'facility_insights_json' => ['findings' => [['topic' => 'Unsupported']]]],
]), 'Invalid later record rejects entire preflight');
$assert($badBatch instanceof InsightsContentTestResponse && $badBatch->status === 422
    && str_contains($badBatch->body['message'] ?? '', '[index 1]'), 'Validation identifies failed index before any persistence');
$schemaAt = strpos($endpoint, 'medical_facility_insights_require_schema($pdo)');
$loopAt = strpos($endpoint, 'try { foreach ($items as $item)');
$assert($schemaAt !== false && $loopAt !== false && $schemaAt < $loopAt, 'Optional insights schema check precedes per-record writes');
$assert(str_contains($endpoint, 'if ($facilityInsightsRequested) {'), 'Omitted insights never requires new column');

$prepareCode = $extract($endpoint, '$normalizedItem = medical_patient_images_redact($item);', 'foreach ($fields as $input => $column)');
$fullBinding = $extract($endpoint, "if (!\$patientImagesOnly) \$params[':full_json']", '// Clear a completed lease');
$prepare = static function (array $item) use ($prepareCode, $fullBinding): array {
    $facilityId = (int) $item['id'];
    $facilityRow = [];
    eval($prepareCode);
    eval($fullBinding);
    return ['set' => $set, 'params' => $params, 'normalized' => $normalizedItem, 'only' => $patientImagesOnly, 'insights_only' => $insightsOnly];
};
$patch = $prepare(['id' => 9, 'facility_insights_json' => $analysis, 'writer_claim_token' => str_repeat('a', 64)]);
$assert($patch['only'] && $patch['set'] === ['facility_insights_json' => ':facility_insights_json'], 'Insights-only patch binds exactly its dedicated column');
$assert($patch['params'] === [':id' => 9, ':facility_insights_json' => $encoded], 'No full_json or claim secret in SQL provenance parameters');
$assert(!str_contains(medical_api_json($patch['normalized']), 'INTERNAL_ANALYSIS_ONLY'), 'Processed article DTO does not echo analysis');
$mixed = $prepare(['id' => 9, 'content' => '<p>Updated article</p>', 'facility_insights_json' => $analysis,
    'nested' => ['facility_insights_json' => $analysis, 'public' => 'KEEP_PUBLIC'],
    'patient_images_notes' => 'PRIVATE_PATIENT_NOTE']);
$assert(!$mixed['only'] && isset($mixed['params'][':full_json']), 'Mixed public article still records public provenance');
$assert(str_contains($mixed['params'][':full_json'], 'Updated article') && str_contains($mixed['params'][':full_json'], 'KEEP_PUBLIC'), 'Public content survives metadata redaction');
$assert(!str_contains($mixed['params'][':full_json'], 'INTERNAL_ANALYSIS_ONLY')
    && !str_contains($mixed['params'][':full_json'], 'PRIVATE_PATIENT_NOTE'), 'Nested internal insights and patient metadata stay out of public provenance');
$assert($mixed['params'][':facility_insights_json'] === $encoded
    && $mixed['params'][':patient_images_notes'] === 'PRIVATE_PATIENT_NOTE', 'Independent dedicated fields both retain submitted metadata');
$clear = $prepare(['id' => 9, 'facility_insights_json' => new stdClass()]);
$assert($clear['only'] && $clear['params'][':facility_insights_json'] === '{}', 'Explicit clear preserves unrelated columns');
$omitted = $prepare(['id' => 9, 'content' => '<p>Legacy</p>']);
$assert(!array_key_exists('facility_insights_json', $omitted['set']), 'Omitted analysis is not overwritten by legacy clients');

// Follow metadata-only writes past the parameter-preparation boundary: old
// aggregate evidence must not refresh ratings or finish an article writer lease.
$ratingCode = $extract($endpoint, '$ratingSummary = $insightsOnly ? null', '// Save gallery URLs');
$claimCode = $extract($endpoint, '$safeClaimJsonSql =', "$" . "sql = 'UPDATE medical_facilities SET '");
$effects = static function (array $prepared, array $item, array $facilityRow, string $claimToken) use ($ratingCode, $claimCode): array {
    $set = $prepared['set'];
    $params = $prepared['params'];
    $insightsOnly = $prepared['insights_only'];
    eval($ratingCode);
    eval($claimCode);
    return ['set' => $set, 'params' => $params, 'rating' => $ratingSummary, 'where' => $leaseWhere];
};
$staleRatings = ['rating' => 4.2, 'reviews_count' => 12,
    'aggregate_ratings_json' => '[{"source":"Google Maps","score":4.9,"count":100,"url":"https://maps.google.com/?cid=123"}]'];
$claim = str_repeat('a', 64);
$insightsEffects = $effects($patch, ['id' => 9, 'facility_insights_json' => $analysis], $staleRatings, $claim);
$assert($insightsEffects['set'] === ['facility_insights_json' => ':facility_insights_json'] && $insightsEffects['rating'] === null,
    'Metadata-only full receiver does not refresh rating, summary, count or writer claim');
$assert(!isset($insightsEffects['params'][':claim_clear_now'], $insightsEffects['params'][':claim_clear_token'])
    && $insightsEffects['params'][':claim_where_token'] === $claim && str_contains($insightsEffects['where'], ':claim_where_token'),
    'Metadata-only updates validate ownership without releasing a live writer claim');
$articleEffects = $effects($mixed, ['id' => 9, 'content' => '<p>Updated</p>'], $staleRatings, $claim);
$assert(isset($articleEffects['set']['ai_writer_claim_json'], $articleEffects['set']['rating'], $articleEffects['set']['reviews_count']),
    'Mixed article update retains normal rating and lease completion behavior');
$assert(str_contains($endpoint, 'if (!$insightsOnly && is_array($reviewItems))'), 'Insights-only receiver skips review persistence queries');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE medical_facilities (id INTEGER PRIMARY KEY, name TEXT, content TEXT, full_json TEXT,
        facility_insights_json TEXT NULL, gallery_json TEXT, image_url TEXT, rating REAL, reviews_count INTEGER,
        status TEXT, verified INTEGER, ai_writer_claim_json TEXT, patient_images_notes TEXT)');
    $initial = [9, 'Fixture clinic', '<p>Keep article</p>', '{"existing":true}', null,
        '["https://clinic.example.org/photo.webp"]', '/uploads/keep.webp', 4.2, 12, 'draft', 0, '{"claim":"keep"}', 'Keep note'];
    $pdo->prepare('INSERT INTO medical_facilities VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($initial);
    $before = $pdo->query('SELECT * FROM medical_facilities WHERE id=9')->fetch(PDO::FETCH_ASSOC);
    $sqlSet = array_map(static fn($column, $placeholder) => $column . '=' . $placeholder, array_keys($patch['set']), array_values($patch['set']));
    $pdo->prepare('UPDATE medical_facilities SET ' . implode(', ', $sqlSet) . ' WHERE id=:id')->execute($patch['params']);
    $after = $pdo->query('SELECT * FROM medical_facilities WHERE id=9')->fetch(PDO::FETCH_ASSOC);
    $assert($after['facility_insights_json'] === $encoded, 'Real SQL parameters persist dedicated insights, not just full_json');
    foreach ($before as $key => $value) if ($key !== 'facility_insights_json') $assert($after[$key] === $value, 'Insights-only write preserves ' . $key);
    $verifyCode = $extract($endpoint, '$patientExpected = [];', '$updated[] = $facilityId;');
    $verify = static function (array $params, bool $facilityInsightsProvided = true) use ($verifyCode, $pdo): array {
        $facilityId = (int) $params[':id'];
        $patientImageUpdates = $facilityInsightUpdates = [];
        eval($verifyCode);
        return $facilityInsightUpdates;
    };
    $receipt = $verify($patch['params']);
    $assert(($receipt[0]['storage_verified'] ?? false) && ($receipt[0]['saved_fields'] ?? []) === ['facility_insights_json'], 'Actual receiver verifies saved column before success receipt');
    $assert(!str_contains(medical_api_json($receipt), 'INTERNAL_ANALYSIS_ONLY'), 'Receipt does not contain complete analysis');
    $assert($verify([':id' => 9], false) === [], 'Legacy receipt does not read optional column');
    $reject(static fn() => $verify([':id' => 9, ':facility_insights_json' => '{}']), 'Mismatched actual storage cannot claim success');
}

$rules = medical_facility_insights_prompt_rules();
foreach (['Custom article prompt', 'Custom article prompt MEDREVIEW_JSON_TRANSPORT_RULES:'] as $template) {
    $once = medical_directory_facility_json_transport_rules($template);
    $assert(str_contains($once, $rules), 'Existing custom prompt receives insights contract');
    $assert(medical_directory_facility_json_transport_rules($once) === $once, 'Transport rules are idempotent');
}
$public = medical_directory_facility_from_row(['id' => 9, 'name' => 'Fixture clinic', 'rating' => 4.2,
    'reviews_count' => 12, 'verified' => 0, 'facility_insights_json' => $encoded, 'full_json' => $encoded]);
$assert(!array_key_exists('facility_insights_json', $public) && !array_key_exists('full_json', $public), 'Public facility mapper never publishes analysis');
$assert($public['is_verified'] === false, 'AI analysis cannot award verified badge');
$translation = (string) file_get_contents(dirname(__DIR__) . '/api/medical/translate-content.php');
eval($extract($translation, 'function medical_api_translation_source(', '/** Preserve each field'));
$source = medical_api_translation_source('facility', ['id' => 9, 'name' => 'Public clinic',
    'facility_insights_json' => $encoded, 'patient_images_notes' => 'PRIVATE_PATIENT_NOTE',
    'full_json' => medical_api_json(['nested' => ['facility_insights_json' => $analysis], 'public' => 'KEEP_TRANSLATION'])]);
$assert(!str_contains(medical_api_json($source), 'INTERNAL_ANALYSIS_ONLY') && !str_contains(medical_api_json($source), 'PRIVATE_PATIENT_NOTE'), 'Translation source excludes internal analysis and patient metadata');
$assert(str_contains(medical_api_json($source), 'KEEP_TRANSLATION'), 'Translation retains unrelated public context');
$directory = (string) file_get_contents(dirname(__DIR__) . '/medical_directory.php');
$assert(str_contains($directory, "if (array_key_exists('facility_insights_json', \$source)) \$source['facility_insights_json'] = null;"), 'Translation copy resets source-language insights');
$queue = (string) file_get_contents(dirname(__DIR__) . '/api/medical/facilities-needing-content.php');
$assert(str_contains($queue, 'medical_facility_insights_redact($item)'), 'Old article queue redacts nested historic analysis');
$admin = (string) file_get_contents(dirname(__DIR__) . '/admin/medical_facility_edit.php');
$assert(str_contains($admin, 'for="facility_insights_json"') && str_contains($admin, 'id="facility_insights_error"')
    && str_contains($admin, 'aria-invalid="true"'), 'Admin input has associated label and field-level error');
$assert(str_contains($admin, '$facilityInsightsParams') && str_contains($admin, 'facility_insights_json = :facility_insights_json'), 'Admin binds insights in the same save statement');
echo 'Facility insights content integration: ' . $checks . " checks passed.\n";
