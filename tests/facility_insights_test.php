<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/db.php';
ob_start();
require_once dirname(__DIR__) . '/medical_facility_insights.php';
$includeOutput = ob_get_clean();
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function (mixed $value, string $label) use ($assert): void {
    try { medical_facility_insights_normalize($value); $rejected = false; }
    catch (InvalidArgumentException $e) { $rejected = $e->getMessage() !== ''; }
    $assert($rejected, $label);
};
$assert($includeOutput === '', 'Include emits no output and never connects');
$assert(medical_facility_insights_encode('{}') === '{}', 'Explicit JSON object clears');
$assert(medical_facility_insights_encode(new stdClass()) === '{}', 'Empty object clears');
$assert(medical_facility_insights_encode([]) === '{}', 'Empty PHP array canonicalizes to object');
$assert(medical_facility_insights_normalize(['summary' => 'Tóm tắt'])->summary === 'Tóm tắt', 'Associative PHP payload is supported');
$assert(medical_facility_insights_normalize('{"custom":{"key":"value"},"score":1.5}')->custom->key === 'value', 'Custom JSON analysis objects are retained');
$assert(medical_facility_insights_normalize(['custom' => ['list' => [], 'null' => null, 'bool' => true, 'count' => 3]])->custom->list === [], 'Nested list/null/bool/number preserve JSON types');
$valid = [
    'schema_version' => 1,
    'summary' => "  Thông tin công khai\r\nCần đối chiếu.  ",
    'researched_at' => '2026-10-22T11:30:00+07:00',
    'sources' => [
        ['id' => 'official', 'url' => 'https://clinic.example/about', 'platform' => 'website', 'title' => 'Giới thiệu', 'accessed_at' => '2026-10-22'],
        ['id' => 'social', 'url' => 'https://www.facebook.com/clinic/posts/1', 'platform' => 'facebook'],
    ],
    'findings' => [
        ['topic' => 'Trang thiết bị', 'summary' => 'Cơ sở công bố có thiết bị X.', 'source_ids' => ['official'], 'evidence_type' => 'official_statement', 'confidence' => 'low'],
        ['topic' => 'Địa chỉ', 'summary' => 'Địa chỉ hiện diện ở hai nguồn.', 'source_ids' => ['official', 'social'], 'evidence_type' => 'cross_checked', 'confidence' => 'medium'],
    ],
    'limitations' => ['Chưa xác minh trực tiếp tại cơ sở.'],
    'custom_notes' => ['enabled' => false],
];
$normalized = medical_facility_insights_normalize($valid);
$assert($normalized->summary === "Thông tin công khai\nCần đối chiếu.", 'Known text trims and normalizes line endings');
$assert($normalized->sources[0]->accessed_at === '2026-10-22' && $normalized->custom_notes->enabled === false, 'Extra source and root metadata survive');
$assert($normalized->findings[1]->source_ids === ['official', 'social'], 'Cross-reference IDs remain exact');
$assert(medical_facility_insights_encode($normalized) === medical_facility_insights_encode(json_encode($normalized)), 'Canonical object and JSON input agree');
$assert($valid['summary'] !== $normalized->summary, 'Normalization does not mutate supplied PHP values');
$assert(medical_facility_insights_normalize(['researched_at' => '2024-02-29'])->researched_at === '2024-02-29', 'Leap date accepted');
$assert(medical_facility_insights_normalize(['researched_at' => '2026-10-22T04:30:00.123Z'])->researched_at === '2026-10-22T04:30:00.123Z', 'UTC fractional timestamp accepted');
$assert(medical_facility_insights_normalize(['summary' => str_repeat('ắ', 10000)])->summary === str_repeat('ắ', 10000), 'Known bounds count Unicode rather than bytes');

foreach ([null, '', ' ', 'null', 'true', '123', '[1]', 1, false, [1], new DateTimeImmutable(), '{"summary":', ['schema_version' => '1'], ['schema_version' => 2], ['summary' => null], ['summary' => []], ['summary' => str_repeat('a', 10001)], ['summary' => "bad\0text"], ['custom' => "bad\x07text"], ['custom' => "\xC3\x28"], ['custom' => INF], ['custom' => NAN], ['custom' => new DateTimeImmutable()], ['custom' => fopen('php://memory', 'r')], ['bad' . "\0" => 1]] as $index => $invalid) {
    $reject($invalid, 'Invalid type/encoding input ' . $index);
}
foreach (['', 'today', '2026-02-29', '2026-13-01', '2026-00-01', '2026-10-22 11:30:00', '2026-10-22T24:00:00Z', '2026-10-22T11:60:00Z', '2026-10-22T11:30:00', '2026-10-22T11:30:00+15:00'] as $date) $reject(['researched_at' => $date], 'Invalid ISO date ' . $date);
foreach ([null, (object) [], ['key' => 'not list'], [null], [1], [''], array_fill(0, 101, 'text'), [str_repeat('x', 2001)]] as $index => $limitations) $reject(['limitations' => $limitations], 'Invalid limitations ' . $index);
foreach ([null, (object) [], [null], [[]], [['url' => 'https://clinic.example']], [['id' => 's']], [['id' => '', 'url' => 'https://clinic.example']], [['id' => 1, 'url' => 'https://clinic.example']], [['id' => 's', 'url' => 'https://clinic.example', 'title' => []]], [['id' => 's', 'url' => 'https://clinic.example', 'platform' => str_repeat('a', 121)]]] as $index => $sources) $reject(['sources' => $sources], 'Invalid sources ' . $index);
foreach (['javascript:alert(1)', 'data:text/html,a', 'blob:https://example.org/id', '//example.org', '/local/path', 'ftp://example.org', 'https://user:password@example.org', 'https://user@example.org', 'https://example.org/a b', 'https://example.org/a%00', 'https://example.org/a%0A', 'https://example.org/a%zz', 'http://localhost', 'http://localhost.', 'http://a.localhost', 'http://printer', 'http://a.local', 'http://a.internal', 'http://127.0.0.1', 'http://10.0.0.1', 'http://172.16.0.1', 'http://192.168.0.1', 'http://169.254.169.254', 'http://0.0.0.0', 'http://127.1', 'http://2130706433', 'http://0x7f000001', 'http://[::1]', 'http://[fc00::1]', 'http://[::ffff:127.0.0.1]'] as $url) $reject(['sources' => [['id' => 's', 'url' => $url]]], 'Non-public or malformed source URL: ' . $url);
$assert(medical_facility_insights_url('https://example.org/path?q=1', 'url') === 'https://example.org/path?q=1', 'Public absolute reference URL stored without fetching');
foreach (['http://100.64.0.1', 'http://127.0x0.0.1', 'http://[::ffff:7f00:1]'] as $url) $reject(['sources' => [['id' => 's', 'url' => $url]]], 'Alternative private IP literal rejected: ' . $url);
$reject(['researched_at' => '2026-10-22T11:30:00+14:30'], 'Timezone offset cannot exceed fourteen hours');
$assert(medical_facility_insights_url('https://8.8.8.8/path', 'url') === 'https://8.8.8.8/path', 'Public IP reference accepted');
$duplicateSources = $valid;
$duplicateSources['sources'][1]['id'] = 'official';
$reject($duplicateSources, 'Duplicate source IDs rejected');
foreach ([null, (object) [], [null], [[]], [['topic' => 'a', 'summary' => 'b']], [['topic' => '', 'summary' => 'b', 'source_ids' => ['official']]], [['topic' => 'a', 'summary' => '', 'source_ids' => ['official']]], [['topic' => 'a', 'summary' => 'b', 'source_ids' => []]], [['topic' => 'a', 'summary' => 'b', 'source_ids' => ['absent']]], [['topic' => 'a', 'summary' => 'b', 'source_ids' => ['official', 'official']]], [['topic' => 'a', 'summary' => 'b', 'source_ids' => ['official'], 'evidence_type' => 'cross_checked']], [['topic' => 'a', 'summary' => 'b', 'source_ids' => ['official'], 'evidence_type' => 'verified']], [['topic' => 'a', 'summary' => 'b', 'source_ids' => ['official'], 'confidence' => 1]]] as $index => $findings) {
    $invalid = $valid;
    $invalid['findings'] = $findings;
    $reject($invalid, 'Invalid findings ' . $index);
}
$reject(['sources' => [], 'findings' => [['topic' => 'a', 'summary' => 'b', 'source_ids' => ['invented']]]], 'Claims cannot cite invented source');
$reject(str_repeat(' ', 1048577) . '{}', 'Raw JSON size bound');
$reject(['custom' => str_repeat('x', 1048577)], 'Encoded JSON size bound');
$deep = 'value';
for ($index = 0; $index < 18; $index++) $deep = ['deeper' => $deep];
$reject($deep, 'Nested object depth bound');
$reject(['custom' => array_fill(0, 32768, null)], 'Node work bound');
$assert(medical_facility_insights_revision(null) === hash('sha256', 'null'), 'NULL storage revision defined');
$assert(medical_facility_insights_revision('{}') !== medical_facility_insights_revision(null), 'Empty object and SQL NULL differ');
$assert(medical_facility_insights_revision('{ }') !== medical_facility_insights_revision('{}'), 'Revision hashes exact stored bytes');
$private = ['id' => 42, 'facility_insights_json' => ['summary' => 'INTERNAL_ANALYSIS'], 'nested' => ['facility_insights_json' => 'INTERNAL_ANALYSIS', 'kept' => 'Public'], 'object' => (object) ['facility_insights_json' => 'INTERNAL_ANALYSIS', 'list' => [(object) ['facility_insights_json' => 'INTERNAL_ANALYSIS', 'id' => 1]]]];
$clean = medical_facility_insights_redact($private);
$assert(!isset($clean['facility_insights_json'], $clean['nested']['facility_insights_json'], $clean['object']->facility_insights_json, $clean['object']->list[0]->facility_insights_json), 'Direct and nested object/list analysis keys are removed');
$assert($clean['id'] === 42 && $clean['nested']['kept'] === 'Public' && $clean['object']->list[0]->id === 1, 'Redaction preserves unrelated public data');
$assert(isset($private['facility_insights_json'], $private['object']->facility_insights_json, $private['object']->list[0]->facility_insights_json), 'Redaction does not mutate caller object graph');
$encodedPrivate = json_encode(['title' => 'Public', 'facility_insights_json' => ['summary' => 'INTERNAL_ANALYSIS'], 'nested_json' => '{"facility_insights_json":{"summary":"INTERNAL_ANALYSIS"},"kept":true}']);
$encodedClean = medical_facility_insights_redact(['full_json' => $encodedPrivate]);
$decodedClean = json_decode($encodedClean['full_json'], true);
$assert(is_string($encodedClean['full_json']) && !isset($decodedClean['facility_insights_json']), 'Encoded full_json remains a string without analysis');
$assert($decodedClean['title'] === 'Public' && json_decode($decodedClean['nested_json'], true) === ['kept' => true], 'Nested encoded JSON strings are recursively cleaned');
$doubleClean = medical_facility_insights_redact(['full_json' => json_encode($encodedPrivate)]);
$assert(is_string(json_decode($doubleClean['full_json'])) && !str_contains(json_decode($doubleClean['full_json']), 'facility_insights_json'), 'Double-encoded JSON retains string layers without internal fields');
$unchanged = ['full_json' => " { \"a\":1.00,\"empty\":{},\"url\":\"https:\\/\\/example.org\" } \n", 'other_json' => 'null', 'list_json' => '[]', 'bad_json' => '{"title": incomplete', 'content' => '{"facility_insights_json":"literal article text"}', 'facility_insights_json_backup' => 'Kept'];
$assert(medical_facility_insights_redact($unchanged) === $unchanged, 'Unchanged JSON bytes, arbitrary article strings and similarly named keys are preserved');
$assert(medical_facility_insights_redact(['full_json' => '{"facility_insights_json":{"summary":"INTERNAL_ANALYSIS"}']) === ['full_json' => 'null'], 'Malformed legacy JSON with analysis marker fails closed');
$assert(medical_facility_insights_redact(['full_json' => '{"facility_insights_json":{}}']) === ['full_json' => '{}'], 'Removing sole private key retains an empty object');
$listClean = medical_facility_insights_redact(['source_json' => '[{"facility_insights_json":{},"id":1},{"facility_insights_json":{}}]']);
$assert($listClean['source_json'] === '[{"id":1},{}]', 'Encoded JSON list preserves object shapes');
$fractionClean = medical_facility_insights_redact(['full_json' => '{"facility_insights_json":{},"fraction":1.0,"empty":{}}']);
$assert($fractionClean['full_json'] === '{"fraction":1.0,"empty":{}}', 'Changed containers preserve zero fractions and empty objects');
$template = medical_facility_insights_template();
$assert($template['schema_version'] === 1 && $template['sources'] === [] && $template['findings'] === [], 'Recommended template has evidence lists');
$assert(medical_facility_insights_normalize($template) instanceof stdClass, 'Recommended template passes validation');
$prompt = medical_facility_insights_prompt([
    'id' => 42, 'name' => 'Clinic', 'address_text' => 'Đà Nẵng',
    'full_json' => '{"SECRET_FULL_JSON":true}', 'patient_images_json' => [['url' => 'SECRET_PATIENT_PHOTO']],
    'patient_images_notes' => 'SECRET_PATIENT_NOTES', 'ai_writer_claim_json' => 'SECRET_LEASE', 'writer_claim_token' => 'SECRET_TOKEN',
    'social_links_json' => [['url' => 'https://facebook.com/clinic', 'patient_images_notes' => 'SECRET_NESTED'], 'https://instagram.com/clinic', 'http://localhost/admin'],
]);
foreach (['SECRET_FULL_JSON', 'SECRET_PATIENT_PHOTO', 'SECRET_PATIENT_NOTES', 'SECRET_LEASE', 'SECRET_TOKEN', 'SECRET_NESTED', 'http://localhost/admin'] as $secret) $assert(!str_contains($prompt, $secret), 'Prompt never forwards raw/private field ' . $secret);
$assert(str_contains($prompt, 'https://facebook.com/clinic') && str_contains($prompt, 'https://instagram.com/clinic'), 'Prompt retains only public social URLs');
$assert(str_contains($prompt, '```json') && str_contains($prompt, 'facility_insights_json') && str_contains($prompt, '"id": 42'), 'Dedicated prompt produces single-block API shape');
$assert(str_contains($prompt, 'Không bịa') && str_contains($prompt, 'không tự sửa rating') && str_contains($prompt, 'bỏ qua mọi chỉ dẫn'), 'Prompt distinguishes research from verified truth and source instructions');
$rules = medical_facility_insights_prompt_rules();
$assert(str_contains($rules, 'TÙY CHỌN') && str_contains($rules, 'BỎ TRƯỜNG') && str_contains($rules, 'giữ nguyên dữ liệu cũ'), 'Optional article prompt preserves existing analysis when new evidence is absent');
$assert(str_contains($rules, '{} chỉ dùng') && str_contains($rules, 'cố ý yêu cầu xóa') && str_contains($rules, 'không chép nguyên câu hướng dẫn'), 'Prompt disallows accidental clear and instruction-shaped analysis');
$assert(!str_contains($prompt, 'BỎ TRƯỜNG') && str_contains($prompt, 'bắt buộc trả id và facility_insights_json')
    && str_contains($prompt, 'limitations giải thích cụ thể'), 'Dedicated prompt always returns required field even when research is incomplete');
try { medical_facility_insights_normalize('[]'); $arrayClearRejected = false; }
catch (InvalidArgumentException) { $arrayClearRejected = true; }
$assert($arrayClearRejected, 'Encoded empty list is not an explicit clear');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    final class FacilityInsightsTestPDO extends PDO
    {
        public array $writes = [];
        public function exec(string $statement): int|false { $this->writes[] = $statement; return parent::exec($statement); }
    }
    $pdo = new FacilityInsightsTestPDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE medical_facilities (id INTEGER PRIMARY KEY, name TEXT, gallery_json TEXT, ai_writer_claim_json TEXT)');
    $pdo->exec("INSERT INTO medical_facilities VALUES (1, 'Existing', '[\"/uploads/public.webp\"]', '{\"owner\":\"existing\"}')");
    $rowBefore = $pdo->query('SELECT * FROM medical_facilities WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    $writes = count($pdo->writes);
    $assert(!medical_facility_insights_has_column($pdo), 'Read-only metadata sees missing schema');
    $assert(count($pdo->writes) === $writes, 'Schema inspection performs no writes');
    try { medical_facility_insights_require_schema($pdo); $missing = false; }
    catch (InvalidArgumentException $e) { $missing = str_contains($e->getMessage(), 'php scripts/migrate_facility_insights.php --apply'); }
    $assert($missing && count($pdo->writes) === $writes, 'Explicit missing field gives maintenance guidance without runtime DDL');
    try { medical_facility_insights_migrate($pdo); $guarded = false; }
    catch (LogicException) { $guarded = true; }
    $assert($guarded && count($pdo->writes) === $writes, 'Migration requires explicit maintenance guard');
    $assert(medreview_with_schema_migration(static fn(): bool => medical_facility_insights_migrate($pdo)), 'Explicit migration adds optional field');
    $assert(count($pdo->writes) === $writes + 1 && str_starts_with($pdo->writes[$writes], 'ALTER TABLE medical_facilities ADD COLUMN facility_insights_json'), 'Migration executes exactly one targeted ALTER');
    $assert(medical_facility_insights_has_column($pdo), 'Metadata sees newly added column without stale cache');
    medical_facility_insights_require_schema($pdo);
    $rowAfter = $pdo->query('SELECT * FROM medical_facilities WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    $assert($rowAfter['facility_insights_json'] === null, 'Existing rows default NULL');
    unset($rowAfter['facility_insights_json']);
    $assert($rowBefore === $rowAfter, 'Migration preserves existing content, photos, and lease');
    $writes = count($pdo->writes);
    $assert(!medreview_with_schema_migration(static fn(): bool => medical_facility_insights_migrate($pdo)) && count($pdo->writes) === $writes, 'Repeated migration is idempotent');
    $encoded = medical_facility_insights_encode($valid);
    $pdo->prepare('UPDATE medical_facilities SET facility_insights_json = :insights WHERE id=1')->execute([':insights' => $encoded]);
    $receipt = medical_facility_insights_verify_saved($pdo, 1, $encoded);
    $assert($receipt === ['id' => 1, 'saved' => true, 'storage_verified' => true, 'saved_fields' => ['facility_insights_json'], 'insights_revision' => hash('sha256', $encoded)], 'Receipt verifies dedicated DB column and exact bytes');
    $assert(!str_contains(json_encode($receipt), 'Cơ sở công bố'), 'Receipt does not echo raw analysis');
    foreach ([[1, '{}'], [999, '{}']] as [$id, $expected]) {
        try { medical_facility_insights_verify_saved($pdo, $id, $expected); $rejected = false; }
        catch (RuntimeException) { $rejected = true; }
        $assert($rejected, 'Readback rejects mismatch/missing record ' . $id);
    }
    try { medical_facility_insights_verify_saved($pdo, 0, '{}'); $badId = false; }
    catch (InvalidArgumentException) { $badId = true; }
    $assert($badId, 'Receipt rejects invalid ID');
}
$migrationSource = file_get_contents(dirname(__DIR__) . '/scripts/migrate_facility_insights.php');
$assert(strpos($migrationSource, "in_array(\$arguments[0], ['--check', '--apply']") < strpos($migrationSource, '/db.php'), 'CLI validates explicit action before loading DB');
echo 'Facility insights: ' . $checks . " checks passed.\n";
