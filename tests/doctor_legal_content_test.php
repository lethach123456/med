<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/medical_directory.php';

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function (callable $run, string $label, string $class = InvalidArgumentException::class) use ($assert): void {
    try { $run(); }
    catch (Throwable $error) { $assert($error instanceof $class, $label . ' exception type: ' . $error->getMessage()); return; }
    $assert(false, $label . ' must reject');
};
$decode = static fn(array $fields, string $key): array => medical_doctor_decode_array($fields[$key] ?? null, $key);
$legalKeys = ['professional_profile_url', 'practice_license_type', 'practice_license_number',
    'practice_license_issuer', 'practice_license_issued_date', 'practice_license_scope',
    'practice_registry_url', 'practice_registration_json', 'legal_documents_json',
    'legal_notes', 'legal_source_ids_json'];
$definitions = medical_doctor_column_definitions();
foreach ($legalKeys as $key) $assert(array_key_exists($key, $definitions), 'schema includes ' . $key);
$assert(count($definitions) === 48, '11 additive fields preserve the original 37 research columns');
$assert($definitions['practice_license_issued_date'] === 'DATE NULL', 'license issue date uses nullable DATE');
$assert(isset($definitions['practice_license_json']), 'legacy license column remains');
$template = medical_doctor_output_template(1, 'BS. Legal Test');
foreach ($legalKeys as $key) $assert(array_key_exists($key, $template), 'output template includes ' . $key);
foreach (['practice_registration_json', 'legal_documents_json', 'legal_source_ids_json'] as $key) {
    $assert($template[$key] === [], 'unknown legal list stays empty: ' . $key);
}
$assert($template['practice_license_issued_date'] === null, 'unknown date stays null');
$assert($template['practice_license_json'] === null, 'unknown legacy license stays null');

$token = str_repeat('c', 64);
$base = array_replace($template, [
    'writer_claim_token' => $token,
    'insufficient_data' => false,
    'content' => '<h2>Hồ sơ bác sĩ</h2><p>Nội dung có nguồn đối chiếu.</p>',
    'sources_json' => [
        ['id' => 's1', 'url' => 'https://hospital.example.org/doctors/test', 'publisher' => 'Test hospital'],
        ['id' => 's2', 'url' => 'https://regulator.example.org/practitioners/test', 'publisher' => 'Test register'],
    ],
    'evidence_json' => ['identity_status' => 'matched', 'missing_fields' => [], 'conflicts' => []],
]);
$license = ['document_type' => 'Giấy phép hành nghề', 'number' => 'TEST/001',
    'issuer' => 'Test authority', 'issued_date' => '2024-02-29', 'scope' => 'Nhãn khoa', 'source_ids' => ['s2']];
$legal = array_replace($base, [
    'professional_profile_url' => 'https://hospital.example.org/doctors/test',
    'practice_license_type' => $license['document_type'],
    'practice_license_number' => $license['number'],
    'practice_license_issuer' => $license['issuer'],
    'practice_license_issued_date' => $license['issued_date'],
    'practice_license_scope' => $license['scope'],
    'practice_registry_url' => 'https://regulator.example.org/practitioners/test',
    'legal_source_ids_json' => ['s1', 's2'],
    'practice_registration_json' => [['facility_name' => 'Test hospital', 'department' => 'Eye unit',
        'scope' => 'Nhãn khoa', 'schedule_text' => 'Theo lịch đăng ký công khai', 'source_ids' => ['s2']]],
    'legal_documents_json' => [['document_type' => 'Giấy phép hành nghề', 'title' => 'Thông tin giấy phép',
        'number' => 'TEST/001', 'issuer' => 'Test authority', 'issued_date' => '2024-02-29',
        'url' => 'https://regulator.example.org/documents/test', 'source_ids' => ['s2']]],
    'legal_notes' => 'Thông tin công khai cần đối chiếu lại khi đăng ký khám.',
]);
$normalized = medical_doctor_normalize_payload($legal);
foreach ($legalKeys as $key) $assert(array_key_exists($key, $normalized), 'normalization includes ' . $key);
$assert($normalized['practice_license_issued_date'] === '2024-02-29', 'valid leap-day date preserved');
$assert($decode($normalized, 'legal_source_ids_json') === ['s1', 's2'], 'legal evidence IDs preserved');
$assert($decode($normalized, 'practice_registration_json')[0]['facility_name'] === 'Test hospital', 'practice registration preserved');
$assert($decode($normalized, 'legal_documents_json')[0]['url'] === 'https://regulator.example.org/documents/test', 'public legal document preserved');
$generatedLegacy = $decode($normalized, 'practice_license_json');
foreach (['document_type', 'number', 'issuer', 'issued_date', 'scope'] as $key) {
    $assert(($generatedLegacy[$key] ?? null) === $license[$key], 'new license derives legacy ' . $key);
}
$assert(($generatedLegacy['source_ids'] ?? []) === ['s1', 's2'], 'derived legacy license carries legal evidence');

// Old extensions remain valid: omit all newly added keys, retaining their old license shape.
$legacy = $base;
foreach ($legalKeys as $key) unset($legacy[$key]);
$legacy['practice_license_json'] = $license;
$legacyNormalized = medical_doctor_normalize_payload($legacy);
foreach (['practice_license_type' => 'document_type', 'practice_license_number' => 'number',
    'practice_license_issuer' => 'issuer', 'practice_license_issued_date' => 'issued_date',
    'practice_license_scope' => 'scope'] as $column => $key) {
    $assert(($legacyNormalized[$column] ?? null) === $license[$key], 'legacy license derives ' . $column);
}
$assert($decode($legacyNormalized, 'legal_source_ids_json') === ['s2'], 'old source refs derive legal refs');
foreach (['2024', '2024-02'] as $partialDate) {
    $partial = $legacy; $partial['practice_license_json']['issued_date'] = $partialDate;
    $partialNormalized = medical_doctor_normalize_payload($partial);
    $assert(($partialNormalized['practice_license_issued_date'] ?? null) === null, 'legacy incomplete date never invents a DATE');
    $assert($decode($partialNormalized, 'practice_license_json')['issued_date'] === $partialDate, 'legacy incomplete date source text retained');
    $completed = array_replace($partial, ['practice_license_issued_date' => '2024-02-29']);
    $assert(medical_doctor_normalize_payload($completed)['practice_license_issued_date'] === '2024-02-29', 'complete date consistent with known legacy year/month accepted');
}
foreach (['2023', '2024-01', '2023-02', 'Ngày cấp chưa rõ'] as $contradictoryDate) {
    $conflict = array_replace($legacy, ['practice_license_issued_date' => '2024-02-29']);
    $conflict['practice_license_json']['issued_date'] = $contradictoryDate;
    $reject(fn() => medical_doctor_normalize_payload($conflict), 'contradictory or ambiguous old date is not silently overwritten');
}
foreach ([str_repeat('đ', 121), "2024\0-02"] as $unsafeDate) {
    $unsafeLegacy = $legacy; $unsafeLegacy['practice_license_json']['issued_date'] = $unsafeDate;
    $reject(fn() => medical_doctor_normalize_payload($unsafeLegacy), 'legacy date length/NUL validation');
}
$assert(medical_doctor_normalize_payload($base)['insufficient_data'] === 0, 'article without legal findings remains valid');
$same = array_replace($legal, ['practice_license_json' => array_replace($license, ['source_ids' => ['s1', 's2']])]);
$assert(medical_doctor_normalize_payload($same)['practice_license_number'] === 'TEST/001', 'matching old/new values accepted');
$reject(fn() => medical_doctor_normalize_payload(array_replace($same, ['practice_license_number' => 'OTHER/002'])), 'conflicting license number');
$reject(fn() => medical_doctor_normalize_payload(array_replace($same, ['practice_license_issued_date' => '2024-03-01'])), 'conflicting issue date');
$nullNew = array_replace($legacy, ['practice_license_number' => null, 'practice_license_issued_date' => null]);
$assert(medical_doctor_normalize_payload($nullNew)['practice_license_number'] === 'TEST/001', 'null new scalar can derive from old license');
$noLicense = array_replace($base, ['practice_license_json' => null]);
$assert($decode(medical_doctor_normalize_payload($noLicense), 'practice_license_json') === [], 'absent license is not fabricated');

foreach (['2023-02-29', '2024-04-31', '2024-13-01', '2024-00-12', '2024-2-9',
    '2024-02', '2024', '29/02/2024', '2024-02-29T00:00:00Z', 2024, true, []] as $date) {
    $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, ['practice_license_issued_date' => $date])), 'invalid scalar legal date');
    $documents = $legal['legal_documents_json']; $documents[0]['issued_date'] = $date;
    $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, ['legal_documents_json' => $documents])), 'invalid document legal date');
}
$undated = array_replace($legal, ['practice_license_issued_date' => null]);
$undated['legal_documents_json'][0]['issued_date'] = null;
$assert(medical_doctor_normalize_payload($undated)['practice_license_issued_date'] === null, 'incomplete date remains null without guessing');

foreach (['professional_profile_url', 'practice_registry_url'] as $key) {
    foreach (['javascript:alert(1)', '[profile](https://hospital.example.org)', 'https://user:pass@hospital.example.org/', true, []] as $url) {
        $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => $url])), 'unsafe legal profile URL: ' . $key);
    }
    $assert(medical_doctor_normalize_payload(array_replace($base, [$key => null]))[$key] === null, 'unknown legal URL: ' . $key);
}
$badDocument = $legal['legal_documents_json']; $badDocument[0]['url'] = 'data:text/html,x';
$reject(fn() => medical_doctor_normalize_payload(array_replace($legal, ['legal_documents_json' => $badDocument])), 'unsafe document URL');
foreach (['professional_profile_url', 'practice_license_type', 'practice_license_number',
    'practice_license_issuer', 'practice_license_issued_date', 'practice_license_scope', 'practice_registry_url', 'legal_notes'] as $key) {
    $missingEvidence = array_replace($base, [$key => $legal[$key], 'legal_source_ids_json' => []]);
    $reject(fn() => medical_doctor_normalize_payload($missingEvidence), 'legal scalar requires evidence: ' . $key);
}
foreach ([['missing'], [123], 's1', ['s1' => 's2'], [['id' => 's1']]] as $refs) {
    $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, ['legal_source_ids_json' => $refs])), 'invalid legal source IDs');
}
$assert($decode(medical_doctor_normalize_payload(array_replace($legal, ['legal_source_ids_json' => ['s1', 's2', 's1']])), 'legal_source_ids_json') === ['s1', 's2'], 'duplicate legal refs normalized deterministically');
foreach (['practice_registration_json', 'legal_documents_json'] as $key) {
    foreach ([['unexpected' => 'object'], ['not an object'], [true], [[]]] as $entries) {
        $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => $entries])), 'malformed legal object list: ' . $key);
    }
    foreach ([[], ['missing'], [123], ['s1' => 's2']] as $refs) {
        $entries = $legal[$key]; $entries[0]['source_ids'] = $refs;
        $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => $entries])), 'invalid entry evidence: ' . $key);
    }
    $entries = $legal[$key]; $firstText = $key === 'practice_registration_json' ? 'facility_name' : 'title';
    $entries[0][$firstText] = ['not text'];
    $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => $entries])), 'entry text must be a string: ' . $key);
    $entries[0][$firstText] = "Text\0with NUL";
    $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => $entries])), 'entry text cannot contain NUL: ' . $key);
    $thirty = array_fill(0, 30, $legal[$key][0]);
    $assert(count($decode(medical_doctor_normalize_payload(array_replace($legal, [$key => $thirty])), $key)) === 30, 'legal list boundary of 30: ' . $key);
    $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => array_fill(0, 31, $legal[$key][0])])), 'legal list maximum of 30: ' . $key);
    $taintedEntries = $legal[$key];
    $taintedEntries[0] += ['verified' => true, 'verification_status' => 'verified', 'reviewed_by' => 99, 'claim_token' => 'SECRET'];
    $filteredEntry = $decode(medical_doctor_normalize_payload(array_replace($legal, [$key => $taintedEntries])), $key)[0];
    foreach (['verified', 'verification_status', 'reviewed_by', 'claim_token'] as $protectedKey) {
        $assert(!array_key_exists($protectedKey, $filteredEntry), 'legal object strips operational key: ' . $key . '.' . $protectedKey);
    }
}
foreach (medical_doctor_legal_text_fields() as $key => $limit) {
    if (in_array($key, ['professional_profile_url', 'practice_registry_url', 'practice_license_issued_date'], true)) continue;
    $assert(mb_strlen(medical_doctor_normalize_payload(array_replace($legal, [$key => str_repeat('đ', $limit)]))[$key], 'UTF-8') === $limit, 'legal scalar character limit boundary: ' . $key);
    $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => str_repeat('đ', $limit + 1)])), 'legal scalar maximum: ' . $key);
    $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => true])), 'legal scalar strict string type: ' . $key);
}
foreach (['practice_registration_json' => ['facility_name' => 160, 'department' => 190, 'scope' => 10000, 'schedule_text' => 2000],
    'legal_documents_json' => ['document_type' => 120, 'title' => 255, 'number' => 120, 'issuer' => 255]] as $key => $limits) {
    foreach ($limits as $column => $limit) {
        $entries = $legal[$key]; $entries[0][$column] = str_repeat('đ', $limit);
        $assert(mb_strlen($decode(medical_doctor_normalize_payload(array_replace($legal, [$key => $entries])), $key)[0][$column], 'UTF-8') === $limit, 'legal entry limit boundary: ' . $column);
        $entries[0][$column] .= 'đ';
        $reject(fn() => medical_doctor_normalize_payload(array_replace($legal, [$key => $entries])), 'legal entry maximum: ' . $column);
    }
}
$atLimit = array_replace($legal, ['legal_notes' => str_repeat('đ', 10000)]);
$assert(mb_strlen(medical_doctor_normalize_payload($atLimit)['legal_notes'], 'UTF-8') === 10000, 'legal notes Unicode character limit boundary');
$reject(fn() => medical_doctor_normalize_payload(array_replace($legal, ['legal_notes' => str_repeat('đ', 10001)])), 'legal notes maximum');
$reject(fn() => medical_doctor_normalize_payload(array_replace($legal, ['legal_notes' => "x\0y"])), 'legal notes NUL byte');
$protected = medical_doctor_normalize_payload(array_replace($legal, ['verified' => 1, 'verification_status' => 'verified', 'reviewed_by' => 999, 'rating' => 5]));
foreach (['verified', 'verification_status', 'reviewed_by', 'rating'] as $key) $assert(!array_key_exists($key, $protected), 'legal research cannot approve/change ' . $key);
foreach (['title_text', 'specialty_text', 'city'] as $key) $assert(medical_doctor_normalize_payload($base)[$key] === '', 'null template remains compatible with legacy NOT NULL column: ' . $key);

$prompt = medical_doctor_research_prompt('Custom {{name}}', ['id' => 1, 'name' => 'BS. Legal Test',
    'ai_writer_claim_json' => '{"claim_token":"PRIVATE_TOKEN"}', 'full_json' => '{"writer_claim_token":"PRIVATE_TOKEN"}']);
$defaultPrompt = medical_doctor_default_prompt();
$assert(str_contains($prompt, 'MEDREVIEW_DOCTOR_RESEARCH_CONTRACT_V2'), 'custom old prompt gains mandatory legal contract v2');
$assert(str_contains($defaultPrompt, 'MEDREVIEW_DOCTOR_EDITORIAL_PROMPT_V3'), 'default prompt updated to v3');
$assert(!str_contains($prompt, 'PRIVATE_TOKEN'), 'legal prompt never exposes private lease');
$legalAddendum = medical_doctor_legal_prompt_addendum();
$legalMarker = 'MEDREVIEW_DOCTOR_PROFILE_LEGAL_V1';
$assert(str_starts_with($legalAddendum, $legalMarker), 'saved legal addendum has stable idempotency marker');
$assert(substr_count($defaultPrompt, $legalMarker) === 1 && str_ends_with($defaultPrompt, $legalAddendum), 'built-in V3 ends with exactly one legal addendum');
foreach ($legalKeys as $key) {
    $assert(str_contains($prompt, $key), 'custom prompt includes new field ' . $key);
    $assert(str_contains($defaultPrompt, $key), 'default prompt documents new field ' . $key);
    $assert(str_contains($legalAddendum, $key), 'saved legal addendum documents new field ' . $key);
}
$migration = file_get_contents(dirname(__DIR__) . '/scripts/migrate_doctor_content.php');
$assert(is_string($migration), 'doctor migration source readable');
$assert(str_contains($migration, "if (PHP_SAPI !== 'cli')") && strpos($migration, "if (PHP_SAPI !== 'cli')") < strpos($migration, '$pdo = db()'), 'doctor migration exits HTTP before connecting to database');
$assert(substr_count($migration, 'WHERE id=:id AND template=:old') === 2, 'both builtin replacement and custom append compare current saved text');
$assert(str_contains($migration, "hash_equals('1edfcb7ec8212163a7cef61cbd8b4d15f61b357b09a6e99482e471040d778726'"), 'only exact previous builtin V2 is upgraded automatically');
$assert(str_contains($migration, "!str_contains((string) \$row['template'], 'MEDREVIEW_DOCTOR_PROFILE_LEGAL_V1')"), 'custom addendum guarded against duplicate appends');
$assert(str_contains($migration, 'rtrim((string) $row[\'template\'])') && str_contains($migration, 'medical_doctor_legal_prompt_addendum()'), 'custom wording is retained before appended legal rules');
$assert(str_contains($migration, '$stmt->rowCount() !== 1') && str_contains($migration, '--replace-doctor-prompt'), 'concurrent custom edits fail safely; wholesale replacement requires explicit flag');
foreach (['_doctor.php', 'doctors-needing-content.php', 'doctor-content.php', 'doctor-content-update.php', 'prompt.php'] as $entry) {
    $source = file_get_contents(dirname(__DIR__) . '/api/medical/' . $entry);
    $assert(is_string($source) && !str_contains($source, 'medical_directory_ensure_doctor_content_columns(')
        && !str_contains($source, 'medreview_with_schema_migration(')
        && !str_contains($source, 'medical_doctor_legal_prompt_addendum('), 'HTTP endpoint does not run doctor DDL/saved-prompt migration: ' . $entry);
}
$row = array_replace(['name' => 'BS. Legal Test'], $normalized);
$mapped = medical_directory_doctor_from_row($row);
$assert($mapped['practice_license_number'] === 'TEST/001', 'public mapper includes legal scalar');
$assert($mapped['practice_license_issued_date'] === '2024-02-29', 'public mapper includes issue date');
$assert($mapped['legal_documents_json'][0]['number'] === 'TEST/001', 'public mapper includes document array');
$assert($mapped['legal_source_ids_json'] === ['s1', 's2'], 'public mapper includes legal refs');

if (in_array('--mysql-temporary', $argv, true)) {
    $pdo = db();
    // Connection-local TEMPORARY tables shadow the names. This test never writes real records.
    foreach (['medical_doctors', 'medical_facilities', 'medical_doctor_facilities'] as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
        $pdo->exec(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create, 1));
    }
    $pdo->exec("INSERT INTO medical_doctors (id,slug,name,status,language_code,verified,rating,reviews_count) VALUES
        (1,'test-legal','BS. Legal Test','published','vi',1,4.4,7),
        (2,'test-legacy-legal','BS. Legal Test','published','vi',0,0,0)");
    $lease = ['claim_token' => $token, 'request_id' => 'doctor-legal-attempt', 'expires_at' => time() + 180];
    $setLease = static function (int $id) use ($pdo, $lease): void {
        $pdo->prepare('UPDATE medical_doctors SET ai_writer_claim_json=:claim WHERE id=:id')
            ->execute([':claim' => json_encode($lease), ':id' => $id]);
    };
    $setLease(1);
    $reject(fn() => medical_doctor_save_research($pdo, array_replace($legal, ['writer_claim_token' => str_repeat('d', 64)])), 'foreign lease with legal payload', MedicalDoctorConflict::class);
    $assert($pdo->query('SELECT practice_license_number FROM medical_doctors WHERE id=1')->fetchColumn() === null, 'foreign worker does not save legal data');
    $reject(fn() => medical_doctor_save_research($pdo, array_replace($same, ['practice_license_number' => 'CONFLICT'])), 'legal conflict before save');
    $assert($pdo->query('SELECT last_researched_at FROM medical_doctors WHERE id=1')->fetchColumn() === null, 'failed legal validation leaves research eligible');
    $result = medical_doctor_save_research($pdo, array_replace($legal, ['verified' => 1, 'verification_status' => 'verified']));
    $saved = $pdo->query('SELECT * FROM medical_doctors WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    foreach ($legalKeys as $key) $assert($saved[$key] === $normalized[$key], 'database persists ' . $key);
    $assert((int) $saved['verified'] === 0 && $saved['verification_status'] === 'unreviewed', 'legal evidence does not automatically verify');
    $assert((float) $saved['rating'] === 4.4 && (int) $saved['reviews_count'] === 7, 'legal save keeps user rating metadata');
    $assert($result['writer_released'] && $saved['ai_writer_claim_json'] === null, 'legal save releases writer lease');
    $assert(!str_contains($saved['full_json'], $token), 'legal full JSON receipt does not store lease token');
    $assert(json_decode($saved['full_json'], true)['writer_request_id'] === 'doctor-legal-attempt', 'legal save retains retry receipt');
    $reject(fn() => medical_doctor_save_research($pdo, $legal), 'completed legal article cannot be resubmitted', MedicalDoctorConflict::class);
    $setLease(2);
    medical_doctor_save_research($pdo, array_replace($legacy, ['id' => 2]));
    $oldSaved = $pdo->query('SELECT * FROM medical_doctors WHERE id=2')->fetch(PDO::FETCH_ASSOC);
    $assert($oldSaved['practice_license_number'] === 'TEST/001' && $oldSaved['practice_license_issued_date'] === '2024-02-29', 'old client persists individual license fields');
    echo "Doctor legal persistence verified with connection-local temporary tables only.\n";
}
echo "Doctor legal content: {$checks} checks passed.\n";
