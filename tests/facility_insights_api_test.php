<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Isolated SQLite fixtures only; never connect to the site's configured database.
require_once dirname(__DIR__) . '/medical_facility_insights_api.php';

final class FacilityInsightsApiFixturePDO extends PDO
{
    public bool $runtime = false;
    public array $queries = [];
    public array $transactions = [];
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }
    private function record(string $sql): void
    {
        if (!$this->runtime) return;
        $this->queries[] = $sql;
        if (preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|REPLACE)\b/i', $sql)) throw new RuntimeException('Runtime insights API must not migrate or replace tables');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { $this->record($query); return parent::prepare($query, $options); }
    public function exec(string $statement): int|false { $this->record($statement); return parent::exec($statement); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->record($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
    public function beginTransaction(): bool { $this->transactions[] = 'begin'; return parent::beginTransaction(); }
    public function commit(): bool { $this->transactions[] = 'commit'; return parent::commit(); }
    public function rollBack(): bool { $this->transactions[] = 'rollback'; return parent::rollBack(); }
}

function facility_insights_api_fixture(bool $insightsColumn = true): FacilityInsightsApiFixturePDO
{
    $pdo = new FacilityInsightsApiFixturePDO();
    $pdo->exec("CREATE TABLE medical_facilities (
        id INTEGER PRIMARY KEY, slug TEXT, name TEXT, category TEXT DEFAULT 'Nha khoa', city TEXT DEFAULT 'Đà Nẵng',
        address_text TEXT DEFAULT '12 Đường A', phone_text TEXT DEFAULT '0123456789', website_url TEXT DEFAULT 'https://example.org',
        google_maps_url TEXT DEFAULT 'https://maps.google.com/?cid=123', social_links_json TEXT DEFAULT '{}',
        language_code TEXT DEFAULT 'vi', status TEXT DEFAULT 'published', content TEXT DEFAULT '<p>Public article</p>',
        full_json TEXT DEFAULT '{\"patient_images_notes\":\"PRIVATE_FULL_JSON\"}', patient_images_json TEXT DEFAULT 'PRIVATE_PATIENT_URL',
        patient_images_notes TEXT DEFAULT 'PRIVATE_PATIENT_NOTE', ai_writer_claim_json TEXT DEFAULT 'PRIVATE_WRITER_TOKEN',
        gallery_json TEXT DEFAULT '[\"https://example.org/gallery.webp\"]', image_url TEXT DEFAULT 'https://example.org/cover.webp',
        rating REAL DEFAULT 4.5, reviews_count INTEGER DEFAULT 16, aggregate_ratings_json TEXT DEFAULT '[]',
        verified INTEGER DEFAULT 1, updated_at TEXT DEFAULT '2026-10-22 00:00:00'"
        . ($insightsColumn ? ', facility_insights_json TEXT NULL' : '') . ')');
    $insert = $pdo->prepare('INSERT INTO medical_facilities (id,slug,name,status) VALUES (:id,:slug,:name,:status)');
    foreach ([1 => 'published', 2 => 'draft', 3 => 'written'] as $id => $status) {
        $insert->execute([':id' => $id, ':slug' => 'clinic-' . $id, ':name' => 'Cơ sở ' . $id, ':status' => $status]);
    }
    $social = [
        'facebook' => 'https://www.facebook.com/example',
        ['platform' => 'Youtube', 'url' => 'https://youtube.com/@example', 'patient_images_notes' => 'PRIVATE_SOCIAL_NOTE'],
        ['url' => 'javascript:alert(1)'], ['url' => 'https://user:secret@example.org'], ['url' => 'http://127.0.0.1/private'],
        ['patient_images_json' => 'PRIVATE_SOCIAL_URL'],
    ];
    $pdo->prepare('UPDATE medical_facilities SET social_links_json = :social WHERE id = 1')->execute([':social' => json_encode($social)]);
    $pdo->runtime = true;
    return $pdo;
}

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $call, int $status, ?string $reason = null) use ($assert): MedicalFacilityInsightsApiException {
    try { $call(); }
    catch (MedicalFacilityInsightsApiException $error) {
        $assert($error->status === $status, 'Expected HTTP ' . $status . ', got ' . $error->status);
        if ($reason !== null) $assert($error->reason === $reason, 'Expected reason ' . $reason . ', got ' . $error->reason);
        return $error;
    }
    throw new RuntimeException('Expected API error HTTP ' . $status);
};

$empty = medical_facility_insights_api_parse_body('{"id":1,"facility_insights_json":{}}');
$assert($empty['facility_insights_json'] instanceof stdClass, 'Parser preserves {} as an object');
$assert(medical_facility_insights_api_normalize($empty)['encoded'] === '{}', 'Explicit empty object clears the analysis');
foreach (['[]', '[{"summary":"not an object"}]', '"[]"'] as $value) {
    $throws(static fn () => medical_facility_insights_api_normalize(medical_facility_insights_api_parse_body('{"id":1,"facility_insights_json":' . $value . '}')), 422);
}
foreach (['', '{', "{\"value\":\"\xFF\"}", str_repeat('{"a":', 70) . '0' . str_repeat('}', 70)] as $raw) {
    $throws(static fn () => medical_facility_insights_api_parse_body($raw), 400, 'invalid_json');
}
foreach (['null', '[]', '12', '"text"', 'true'] as $raw) {
    $throws(static fn () => medical_facility_insights_api_parse_body($raw), 422, 'invalid_body');
}
$throws(static fn () => medical_facility_insights_api_parse_body('{"long":"text"}', 4), 413, 'body_too_large');
foreach ([null, 0, -1, 1.0, true, '01', '1 OR 1=1', '1.2', '4294967296', [], new stdClass()] as $id) {
    $throws(static fn () => medical_facility_insights_api_id($id), 422);
}
$assert(medical_facility_insights_api_id('4294967295') === 4294967295, 'Unsigned facility primary key range is supported');
$throws(static fn () => medical_facility_insights_api_normalize(['id' => 1]), 422, 'missing_insights');
$throws(static fn () => medical_facility_insights_api_normalize(['id' => 1, 'facility_insights_json' => null]), 422);
$throws(static fn () => medical_facility_insights_api_normalize(['id' => 1, 'facility_insights_json' => '']), 422);
$throws(static fn () => medical_facility_insights_api_normalize($empty + ['status' => 'published']), 422, 'unsupported_fields');
$throws(static fn () => medical_facility_insights_api_normalize($empty + ['content' => '<p>Changed</p>']), 422, 'unsupported_fields');
foreach (['', 'no revision', 123, null, str_repeat('g', 64)] as $revision) {
    $throws(static fn () => medical_facility_insights_api_normalize($empty + ['insights_revision' => $revision]), 422, 'invalid_revision');
}
$assert(medical_facility_insights_api_normalize($empty + ['insights_revision' => str_repeat('A', 64)])['insights_revision'] === str_repeat('a', 64), 'Hex revision normalizes case');

$pdo = facility_insights_api_fixture();
$initial = $pdo->query('SELECT * FROM medical_facilities WHERE id=1')->fetch();
$pdo->queries = [];
$read = medical_facility_insights_api_read($pdo, '1');
$assert($read['ok'] === true && $read['type'] === 'facility_insights' && $read['contract_version'] === 1, 'Read contract metadata is explicit');
$assert($read['facility']['id'] === 1 && $read['facility']['name'] === 'Cơ sở 1', 'Read returns canonical facility identity');
$assert(array_keys($read['facility']) === MEDICAL_FACILITY_INSIGHTS_API_PUBLIC_FIELDS, 'Facility DTO uses an explicit public allowlist');
$assert($read['facility']['social_links_json'] === [
    ['platform' => 'facebook', 'url' => 'https://www.facebook.com/example'],
    ['platform' => 'Youtube', 'url' => 'https://youtube.com/@example'],
], 'Social source includes link-only metadata, no arbitrary nested fields');
$assert($read['stored_insights_valid'] === true && $read['facility_insights_json'] instanceof stdClass, 'NULL insights is a valid empty analysis');
$assert($read['insights_revision'] === medical_facility_insights_revision(null), 'Read revision represents the raw NULL value');
$assert($read['output_template']['id'] === 1 && array_key_exists('facility_insights_json', $read['output_template']), 'Output template targets the canonical ID');
$readJson = json_encode($read, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
foreach (['PRIVATE_', 'gallery.webp', 'cover.webp', 'user:secret', '<p>Public article</p>'] as $sensitive) {
    $assert(!str_contains($readJson, $sensitive), 'Public AI source excludes ' . $sensitive);
}
foreach ($pdo->queries as $query) {
    $assert(!preg_match('/SELECT\s+\*/i', $query), 'GET never selects all facility fields');
    $assert(!preg_match('/patient_images|full_json|writer_claim|gallery_json|image_url/i', $query), 'GET never reads patient/article/claim/media columns');
}
$assert($pdo->transactions === [], 'GET is read-only and does not claim work');
$unsafeSource = medical_facility_insights_api_source(array_replace($initial, ['website_url' => 'file:///etc/passwd', 'google_maps_url' => 'http://localhost/private']));
$assert($unsafeSource['website_url'] === '' && $unsafeSource['google_maps_url'] === '', 'Historic unsafe URLs are not forwarded as source destinations');
$throws(static fn () => medical_facility_insights_api_read($pdo, 999), 404, 'facility_not_found');
$pdo->queries = [];
$analysis = ['summary' => 'Cơ sở có thông tin trên các kênh công khai.', 'custom_editor_note' => 'Cần kiểm tra lại nguồn công khai.'];
$saved = medical_facility_insights_api_update($pdo, [
    'id' => 1, 'facility_insights_json' => $analysis, 'insights_revision' => $read['insights_revision'],
]);
$after = $pdo->query('SELECT * FROM medical_facilities WHERE id=1')->fetch();
$assert($saved['ok'] === true && $saved['saved'] === true && $saved['storage_verified'] === true, 'POST confirms actual stored value');
$assert($saved['id'] === 1 && $saved['saved_fields'] === ['facility_insights_json'], 'Receipt names only the dedicated field');
$assert($saved['insights_revision'] === medical_facility_insights_revision($after['facility_insights_json']), 'Receipt revision matches actual stored bytes');
$assert(json_decode($after['facility_insights_json'], true) === $analysis, 'Analysis and custom editorial metadata are stored');
$assert(array_diff_assoc($initial, $after) === ['facility_insights_json' => null], 'Article, photos, claims, ratings, publication and verification remain unchanged');
$assert($pdo->transactions === ['begin', 'commit'], 'One atomic write transaction');
$assert(!str_contains(json_encode($saved), 'Cơ sở có') && !str_contains(json_encode($saved), 'PRIVATE_'), 'Receipt does not echo analysis or private content');
foreach ($pdo->queries as $query) {
    if (preg_match('/^\s*UPDATE\b/i', $query)) {
        $assert((bool) preg_match('/SET\s+facility_insights_json\s*=\s*:insights\s+WHERE/i', $query), 'UPDATE writes one dedicated column only');
    }
}

$stable = $after;
$throws(static fn () => medical_facility_insights_api_update($pdo, [
    'id' => 1, 'facility_insights_json' => ['summary' => 'Stale overwrite'], 'insights_revision' => $read['insights_revision'],
]), 409, 'insights_conflict');
$assert($pdo->query('SELECT * FROM medical_facilities WHERE id=1')->fetch() === $stable, 'Stale revision is rolled back without an overwrite');
$assert(array_slice($pdo->transactions, -2) === ['begin', 'rollback'], 'CAS conflict rolls back its transaction');

foreach ([2 => 'draft', 3 => 'written'] as $id => $status) {
    $result = medical_facility_insights_api_update($pdo, ['id' => $id, 'facility_insights_json' => ['summary' => 'Public research only']]);
    $assert($result['saved'] === true, 'Dedicated analysis can be saved for ' . $status . ' facilities without an article writer lease');
    $assert($pdo->query('SELECT status FROM medical_facilities WHERE id=' . $id)->fetchColumn() === $status, 'Saving analysis does not publish ' . $status . ' facility');
}
$clear = medical_facility_insights_api_update($pdo, ['id' => 1, 'facility_insights_json' => new stdClass(), 'insights_revision' => $saved['insights_revision']]);
$assert($pdo->query('SELECT facility_insights_json FROM medical_facilities WHERE id=1')->fetchColumn() === '{}', 'Explicit {} clears an existing analysis');
$assert($clear['insights_revision'] === medical_facility_insights_revision('{}'), 'Cleared value has its own revision');
$beforeMissing = $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn();
$throws(static fn () => medical_facility_insights_api_update($pdo, ['id' => 999, 'facility_insights_json' => new stdClass()]), 404, 'facility_not_found');
$assert($pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === $beforeMissing, 'Unknown ID never creates a facility');

// Historic bad JSON is repairable using its exact revision, never echoed or silently rewritten by GET.
$pdo->prepare('UPDATE medical_facilities SET facility_insights_json=:raw WHERE id=1')->execute([':raw' => '{PRIVATE_BROKEN_JSON']);
$badRead = medical_facility_insights_api_read($pdo, 1);
$assert($badRead['stored_insights_valid'] === false && $badRead['facility_insights_json'] instanceof stdClass, 'Malformed historic JSON is explicitly flagged');
$assert(!str_contains(json_encode($badRead), 'PRIVATE_BROKEN_JSON'), 'Malformed stored raw text is never exposed to AI');
$assert($badRead['insights_revision'] === medical_facility_insights_revision('{PRIVATE_BROKEN_JSON'), 'Invalid historic value retains repair revision');
$assert($pdo->query('SELECT facility_insights_json FROM medical_facilities WHERE id=1')->fetchColumn() === '{PRIVATE_BROKEN_JSON', 'GET does not modify bad stored data');
medical_facility_insights_api_update($pdo, ['id' => 1, 'facility_insights_json' => new stdClass(), 'insights_revision' => $badRead['insights_revision']]);
$assert(medical_facility_insights_api_read($pdo, 1)['stored_insights_valid'] === true, 'Explicit revision-aware repair succeeds');

// Verify-before-commit catches DB-side mutation and rolls back the complete write.
$pdo->runtime = false;
$pdo->exec("CREATE TRIGGER corrupt_insights AFTER UPDATE OF facility_insights_json ON medical_facilities
    BEGIN UPDATE medical_facilities SET facility_insights_json = 'corrupted-by-trigger' WHERE id = NEW.id; END");
$pdo->runtime = true;
$beforeCorrupt = $pdo->query('SELECT * FROM medical_facilities WHERE id=1')->fetch();
try {
    medical_facility_insights_api_update($pdo, ['id' => 1, 'facility_insights_json' => ['summary' => 'Must not survive failed verification']]);
    throw new LogicException('Expected storage verification failure');
} catch (Throwable $error) {
    $assert(!$error instanceof LogicException, 'Failed read-back aborts success response');
}
$assert($pdo->query('SELECT * FROM medical_facilities WHERE id=1')->fetch() === $beforeCorrupt, 'Read-back verification failure rolls back persisted changes');
$assert(array_slice($pdo->transactions, -2) === ['begin', 'rollback'], 'Read-back failure rolls back before returning');

$missing = facility_insights_api_fixture(false);
$missingBefore = $missing->query('SELECT * FROM medical_facilities')->fetchAll();
$schemaError = $throws(static fn () => medical_facility_insights_api_update($missing, ['id' => 1, 'facility_insights_json' => new stdClass()]), 503, 'schema_unavailable');
$assert(str_contains($schemaError->getMessage(), 'migrate_facility_insights.php'), 'Missing column gives the isolated migration instruction');
$assert($missing->transactions === [] && $missing->query('SELECT * FROM medical_facilities')->fetchAll() === $missingBefore, 'Missing schema never writes data or starts migrations');
$throws(static fn () => medical_facility_insights_api_read($missing, 1), 503, 'schema_unavailable');

$module = file_get_contents(dirname(__DIR__) . '/medical_facility_insights_api.php');
$assert(!preg_match('/\bdb\s*\(/', $module), 'Shared API module never opens application DB on include');
$assert(!str_contains($module, 'medical_media_worker') && !str_contains($module, 'search_cache') && !str_contains($module, 'writer_claim_token'), 'Insights updates never enqueue media, rebuild public caches or manage writer leases');
foreach (['facility-insights.php' => 'GET', 'facility-insights-update.php' => 'POST'] as $file => $method) {
    $source = file_get_contents(dirname(__DIR__) . '/api/medical/' . $file);
    $assert(str_contains($source, "require_once __DIR__ . '/_auth.php'"), $file . ' uses existing API-key authentication');
    $assert(str_contains($source, 'Cache-Control: private, no-store') && str_contains($source, 'Vary: Origin'), $file . ' responses are not shared-cached');
    $assert(str_contains($source, 'Access-Control-Allow-Methods: ' . $method . ', OPTIONS'), $file . ' advertises only supported method');
    $assert(str_contains($source, 'X-Medical-Api-Key') && str_contains($source, "https://chatgpt.com") && str_contains($source, 'chrome-extension:'), $file . ' supports known AI and extension origins');
    $assert(!str_contains($source, 'Access-Control-Allow-Origin: *'), $file . ' never reflects arbitrary origins');
    $assert(strpos($source, "\$method === 'OPTIONS'") < strpos($source, 'medical_api_auth();'), $file . ' answers preflight before API authentication');
    $assert(str_contains($source, 'header(\'Allow: ' . $method . ', OPTIONS\')'), $file . ' sends Allow on method mismatch');
    $assert(!str_contains($source, 'display_errors') && !str_contains($source, 'getMessage(), 500'), $file . ' does not expose server exception text');
}
$postSource = file_get_contents(dirname(__DIR__) . '/api/medical/facility-insights-update.php');
$assert(str_contains($postSource, 'MEDICAL_FACILITY_INSIGHTS_API_MAX_BODY_BYTES + 1'), 'POST has a bounded stream read even without Content-Length');
$assert(strpos($postSource, 'medical_facility_insights_api_normalize($body)') < strpos($postSource, 'medical_facility_insights_api_update(db()'), 'POST validates payload before obtaining a write connection');
$assert(str_contains($postSource, 'unsupported_media_type'), 'POST requires JSON Content-Type');

echo 'Facility insights API: ' . $checks . " checks passed.\n";
