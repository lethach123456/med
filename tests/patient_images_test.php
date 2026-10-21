<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/db.php';
ob_start();
require_once dirname(__DIR__) . '/medical_patient_images.php';
$includeOutput = ob_get_clean();
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function (mixed $input, string $label) use ($assert): void {
    try { medical_patient_images_normalize($input); $rejected = false; }
    catch (InvalidArgumentException $e) { $rejected = trim($e->getMessage()) !== ''; }
    $assert($rejected, $label);
};
$assert($includeOutput === '', 'Helper include has no output or connection side effects');
$assert(medical_patient_images_normalize([]) === [], 'Explicit empty array clears');
$assert(medical_patient_images_encode('[]') === '[]', 'Explicit empty JSON array clears');
$example = [['url' => '/uploads/patients/patient-1.webp', 'caption' => 'Ảnh sau điều trị', 'alt' => 'Kết quả được phép lưu', 'stage' => 'after', 'consent_confirmed' => true, 'source_url' => 'https://clinic.example/case-1']];
$assert(medical_patient_images_normalize($example) === $example, 'Full metadata is retained without fetching images');
$assert(medical_patient_images_normalize(json_encode($example, JSON_UNESCAPED_UNICODE)) === $example, 'JSON object entries normalize identically');
$minimal = medical_patient_images_normalize([['url' => 'https://clinic.example/patient.jpg']]);
$assert($minimal[0] === ['url' => 'https://clinic.example/patient.jpg', 'caption' => '', 'alt' => '', 'stage' => 'other', 'consent_confirmed' => false], 'Consent defaults false, not implied by image URL');
$assert(medical_patient_images_normalize([(object) ['url' => '/uploads/patient.png']])[0]['url'] === '/uploads/patient.png', 'Decoded object entry supported');
$assert(medical_patient_images_encode($example) === json_encode($example, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Canonical encoding keeps Unicode without escaped slashes');
$assert(medical_patient_images_normalize([['url' => 'https://clinic.example/patient.jpg?width=800&v=2']])[0]['url'] === 'https://clinic.example/patient.jpg?width=800&v=2', 'Public URL query is retained');

foreach ([null, '', ' ', 'null', '{}', '{"url":"/uploads/a.jpg"}', true, 1, new stdClass(), ['url' => '/uploads/a.jpg'], '["https://clinic.example/a.jpg"]', [null], [[]], [new DateTimeImmutable()], [['caption' => 'missing url']], [['url' => '/uploads/a.jpg', 'unknown' => 'x']], [['url' => null]], [['url' => ['/uploads/a.jpg']]], [['url' => '/uploads/a.jpg', 'caption' => null]], [['url' => '/uploads/a.jpg', 'alt' => new stdClass()]], [['url' => '/uploads/a.jpg', 'stage' => null]], [['url' => '/uploads/a.jpg', 'stage' => 'AFTER']], [['url' => '/uploads/a.jpg', 'stage' => []]], [['url' => '/uploads/a.jpg', 'consent_confirmed' => null]], [['url' => '/uploads/a.jpg', 'consent_confirmed' => 1]], [['url' => '/uploads/a.jpg', 'consent_confirmed' => 'true']], [['url' => '/uploads/a.jpg', 'source_url' => null]], [['url' => '/uploads/a.jpg', 'source_url' => '']], [['url' => '/uploads/a.jpg', 'caption' => "bad\0text"]], [['url' => '/uploads/a.jpg', 'caption' => "bad\ntext"]], [['url' => '/uploads/a.jpg', 'alt' => "\xC3\x28"]], [['url' => '/uploads/a.jpg', 'caption' => str_repeat('ắ', 501)]], [['url' => '/uploads/a.jpg', 'alt' => str_repeat('x', 501)]], [['url' => '/uploads/' . str_repeat('a', 4000)]]] as $index => $invalid) {
    $reject($invalid, 'Invalid type/field input ' . $index . ' rejected');
}
foreach (['javascript:alert(1)', 'data:image/png;base64,AAAA', 'blob:https://clinic.example/id', '//clinic.example/patient.jpg', 'http://user:pass@clinic.example/a.jpg', 'https://user@clinic.example/a.jpg', '/private/patient.jpg', '../uploads/patient.jpg', '/uploads/', '/uploads/folder/', '/uploads/../secret.jpg', '/uploads/%2e%2e/secret.jpg', '/uploads/%252e%252e/secret.jpg', '/uploads/./a.jpg', '/uploads/%00.jpg', '/uploads/%5csecret.jpg', '/uploads/a%zz.jpg', "/uploads/a\tb.jpg", "https://clinic.example/a\nb.jpg", 'https://clinic.example/a b.jpg', 'ftp://clinic.example/a.jpg', 'https:///a.jpg', 'http:/clinic.example/a.jpg', '/uploads/a\\b.jpg'] as $url) {
    $reject([['url' => $url]], 'Unsafe URL rejected: ' . str_replace(["\n", "\t"], ['\\n', '\\t'], $url));
}
$reject('[{"url":"/uploads/a.jpg","caption":"' . "\xC3\x28" . '"}]', 'Invalid UTF-8 JSON rejected');
$reject('[{"url":"/uploads/a.jpg"}', 'Malformed JSON rejected');
$reject([['url' => '/uploads/a.jpg'], ['url' => '/uploads/a.jpg']], 'Duplicate local image rejected');
$reject([['url' => 'HTTPS://CLINIC.EXAMPLE/a.jpg#first'], ['url' => 'https://clinic.example/a.jpg#second']], 'Duplicate host casing / fragment image rejected');
$reject([['url' => '/uploads/a.jpg#first'], ['url' => '/uploads/a.jpg#second']], 'Duplicate local fragments rejected');
$hundred = [];
for ($index = 0; $index < 100; $index++) $hundred[] = ['url' => '/uploads/patients/patient-' . $index . '.webp'];
$assert(count(medical_patient_images_normalize($hundred)) === 100, '100 image limit accepted');
$hundred[] = ['url' => '/uploads/extra.webp'];
$reject($hundred, '101 image limit rejected');
$reject(str_repeat(' ', 1048577) . '[]', 'Oversized raw JSON is rejected before decoding');
$large = [];
for ($index = 0; $index < 100; $index++) {
    $large[] = ['url' => 'https://clinic.example/' . str_repeat('a', 3950) . $index . '.jpg', 'source_url' => 'https://clinic.example/' . str_repeat('b', 3950) . $index, 'caption' => str_repeat('😀', 500), 'alt' => str_repeat('😀', 500)];
}
$reject($large, 'Oversized canonical UTF-8 JSON rejected');
$assert(medical_patient_images_normalize([['url' => '/uploads/a.jpg', 'caption' => str_repeat('😀', 500)]])[0]['caption'] === str_repeat('😀', 500), 'Limits count Unicode characters, not UTF-8 bytes');

$assert(medical_patient_images_notes_normalize('') === '', 'Explicit empty notes clears the private note');
$assert(medical_patient_images_notes_normalize("  Ảnh trước điều trị\r\nẢnh sau điều trị\r\n  ") === "Ảnh trước điều trị\nẢnh sau điều trị", 'Overall notes trim outer whitespace and normalize CRLF without removing line breaks');
$assert(medical_patient_images_notes_normalize(str_repeat('ắ', 4000)) === str_repeat('ắ', 4000), 'Notes limit counts 4000 Unicode characters rather than bytes');
$assert(medical_patient_images_notes_normalize(str_repeat('😀', 4000)) === str_repeat('😀', 4000), 'Notes allow full Unicode within the character bound');
foreach ([null, 0, true, [], new stdClass(), str_repeat('ắ', 4001), "ghi\0chú", "ghi\x07chú", "\xC3\x28"] as $index => $badNotes) {
    try { medical_patient_images_notes_normalize($badNotes); $notesRejected = false; }
    catch (InvalidArgumentException $error) { $notesRejected = trim($error->getMessage()) !== ''; }
    $assert($notesRejected, 'Invalid private notes type/text rejected: ' . $index);
}

$private = ['id' => 1, 'patient_images_json' => $example, 'nested' => ['patient_images' => $example, 'content' => 'Kept', 'list' => [['patient_images_json' => 'SECRET', 'gallery_json' => ['url' => '/uploads/public.jpg']]]], 'object' => (object) ['patient_images' => $example, 'name' => 'Public']];
$redacted = medical_patient_images_redact($private);
$assert(!isset($redacted['patient_images_json'], $redacted['nested']['patient_images'], $redacted['nested']['list'][0]['patient_images_json'], $redacted['object']->patient_images), 'Recursive arrays and JSON objects redact private fields');
$assert($redacted['nested']['content'] === 'Kept' && $redacted['nested']['list'][0]['gallery_json']['url'] === '/uploads/public.jpg' && $redacted['object']->name === 'Public', 'Redaction preserves non-patient content');
$assert(isset($private['patient_images_json'], $private['object']->patient_images), 'Redaction does not mutate the supplied object graph');
$encodedPrivate = json_encode(['title' => 'Kept', 'patient_images_json' => $example, 'nested' => ['patient_images' => $example], 'legacy_json' => json_encode(['patient_images' => $example, 'url' => '/uploads/public.jpg'])]);
$encodedRedacted = medical_patient_images_redact(['full_json' => $encodedPrivate]);
$decodedRedacted = json_decode($encodedRedacted['full_json'], true);
$assert(is_string($encodedRedacted['full_json']) && !isset($decodedRedacted['patient_images_json'], $decodedRedacted['nested']['patient_images']), 'JSON-string full_json remains a string and drops nested private keys');
$assert(json_decode($decodedRedacted['legacy_json'], true) === ['url' => '/uploads/public.jpg'] && $decodedRedacted['title'] === 'Kept', 'Nested encoded *_json container is redacted recursively');
$doubleEncoded = json_encode($encodedPrivate);
$doubleRedacted = medical_patient_images_redact(['full_json' => $doubleEncoded]);
$assert(is_string(json_decode($doubleRedacted['full_json'])) && !str_contains(json_decode($doubleRedacted['full_json']), 'patient_images'), 'Double encoded named JSON container keeps string layering without private metadata');
$unchangedJson = " { \"a\":1.00, \"nested\": {\"empty\":{}}, \"url\":\"https:\\/\\/example.org\" } \n";
$unchangedCases = ['full_json' => $unchangedJson, 'other_json' => 'null', 'empty_json' => '{}', 'list_json' => '[]', 'number_json' => '123', 'bad_json' => '{"title": incomplete', 'content' => '{"patient_images": "literal article text"}'];
$assert(medical_patient_images_redact($unchangedCases) === $unchangedCases, 'Unchanged valid JSON, unrelated malformed JSON and article text retain exact bytes');
$assert(medical_patient_images_redact(['full_json' => '{"patient_images_json": [{"url":"PRIVATE"}']) === ['full_json' => 'null'], 'Corrupt legacy JSON with patient metadata fails closed');
$arrayObjectJson = medical_patient_images_redact(['data_json' => '[{"patient_images":[],"id":1},{"nested_json":"{\\"patient_images_json\\":[],\\"kept\\":true}"}]']);
$arrayDecoded = json_decode($arrayObjectJson['data_json'], true);
$assert($arrayDecoded[0] === ['id' => 1] && json_decode($arrayDecoded[1]['nested_json'], true) === ['kept' => true], 'JSON arrays and nested string containers both lose private keys');
$assert(medical_patient_images_redact(['full_json' => '{"patient_images":[]}']) === ['full_json' => '{}'], 'JSON object stays object when its only private key is removed');
$notesPrivate = ['patient_images_notes' => 'PRIVATE_NOTES', 'nested' => [
    'patient_images_notes' => 'PRIVATE_NOTES', 'kept' => 'Public',
    'record_json' => '{"patient_images_notes":"PRIVATE_NOTES","public":true}',
], 'full_json' => '{"patient_images_notes":"PRIVATE_NOTES","patient_images_json":[],"id":12}'];
$notesRedacted = medical_patient_images_redact($notesPrivate);
$assert(!isset($notesRedacted['patient_images_notes'], $notesRedacted['nested']['patient_images_notes'])
    && $notesRedacted['nested']['kept'] === 'Public', 'Overall patient notes are private at every array level');
$assert(json_decode($notesRedacted['nested']['record_json'], true) === ['public' => true]
    && json_decode($notesRedacted['full_json'], true) === ['id' => 12], 'Encoded JSON containers redact notes and image lists together');
$assert(medical_patient_images_redact(['full_json' => '{"patient_images_notes":"PRIVATE_NOTES"']) === ['full_json' => 'null'], 'Malformed JSON with private note marker fails closed');
$assert(medical_patient_images_table('facility') === 'medical_facilities' && medical_patient_images_table('doctor') === 'medical_doctors', 'Entity table allowlist is exact');
try { medical_patient_images_table('medical_facilities; DROP TABLE x'); $invalidEntity = false; }
catch (InvalidArgumentException) { $invalidEntity = true; }
$assert($invalidEntity, 'Arbitrary SQL identifiers rejected');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    final class PatientImagesTestPDO extends PDO
    {
        public array $writes = [];
        public array $reads = [];
        public function exec(string $statement): int|false { $this->writes[] = $statement; return parent::exec($statement); }
        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            if (preg_match('/^\s*SELECT\b/i', $query)) $this->reads[] = $query;
            return parent::prepare($query, $options);
        }
    }
    $pdo = new PatientImagesTestPDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach (['medical_facilities', 'medical_doctors'] as $table) {
        $pdo->exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, name TEXT, gallery_json TEXT, ai_writer_claim_json TEXT)');
        $pdo->prepare('INSERT INTO ' . $table . ' (id,name,gallery_json,ai_writer_claim_json) VALUES (1,:name,:gallery,:claim)')->execute([':name' => 'Existing', ':gallery' => '["/uploads/public.jpg"]', ':claim' => '{"owner":"existing-task"}']);
    }
    $writes = count($pdo->writes);
    $assert(!medical_patient_images_has_column($pdo, 'facility') && !medical_patient_images_has_column($pdo, 'doctor'), 'Read-only metadata detects missing columns');
    $assert(!medical_patient_images_has_column($pdo, 'facility', 'patient_images_notes')
        && !medical_patient_images_has_column($pdo, 'doctor', 'patient_images_notes'), 'Read-only metadata independently detects missing notes columns');
    $assert(count($pdo->writes) === $writes, 'Metadata checks never issue DDL');
    try { medical_patient_images_require_schema($pdo, 'doctor'); $missing = false; }
    catch (InvalidArgumentException $e) { $missing = str_contains($e->getMessage(), 'scripts/migrate_patient_images.php --apply'); }
    $assert($missing && count($pdo->writes) === $writes, 'Missing optional schema gives explicit migration instruction without writes');
    try { medical_patient_images_migrate($pdo); $guarded = false; }
    catch (LogicException) { $guarded = true; }
    $assert($guarded && count($pdo->writes) === $writes, 'Ordinary CLI/runtime cannot migrate');
    $added = medreview_with_schema_migration(static fn(): array => medical_patient_images_migrate($pdo));
    $migrationWrites = count($pdo->writes);
    $assert($added === ['medical_facilities', 'medical_doctors'] && $migrationWrites > $writes, 'Explicit migration installs image and note columns on both tables');
    $assert(!array_filter(array_slice($pdo->writes, $writes), static fn(string $sql): bool => !str_starts_with($sql, 'ALTER TABLE ')), 'Dedicated migration performs only additive ALTER statements');
    $assert(!medreview_schema_migration_allowed(), 'Migration scope restored');
    foreach (['facility', 'doctor'] as $entity) {
        $assert(medical_patient_images_has_column($pdo, $entity), $entity . ' metadata sees newly added column without stale cache');
        $assert(medical_patient_images_has_column($pdo, $entity, 'patient_images_notes'), $entity . ' metadata sees separately added notes column');
        medical_patient_images_require_schema($pdo, $entity);
        medical_patient_images_require_schema($pdo, $entity, 'patient_images_notes');
        $row = $pdo->query('SELECT * FROM ' . medical_patient_images_table($entity))->fetch(PDO::FETCH_ASSOC);
        $assert($row['name'] === 'Existing' && $row['gallery_json'] === '["/uploads/public.jpg"]' && $row['ai_writer_claim_json'] === '{"owner":"existing-task"}'
            && $row['patient_images_json'] === null && $row['patient_images_notes'] === null, $entity . ' existing rows, gallery and writer claim unchanged while both optional fields start null');
    }
    $assert(medreview_with_schema_migration(static fn(): array => medical_patient_images_migrate($pdo)) === [] && count($pdo->writes) === $migrationWrites, 'Explicit migration is idempotent');
    $assert($pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === 1 && $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === 1, 'No row insert or delete during migration');
    $missingPdo = new PatientImagesTestPDO('sqlite::memory:');
    $assert(!medical_patient_images_has_column($missingPdo, 'doctor') && $missingPdo->writes === [], 'Missing table readiness remains read-only');
    $imageOnlyPdo = new PatientImagesTestPDO('sqlite::memory:');
    foreach (['medical_facilities', 'medical_doctors'] as $table) {
        $imageOnlyPdo->exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, patient_images_json MEDIUMTEXT NULL, gallery_json TEXT, ai_writer_claim_json TEXT)');
        $imageOnlyPdo->prepare('INSERT INTO ' . $table . ' VALUES (1,:private,:gallery,:claim)')->execute([
            ':private' => medical_patient_images_encode($example), ':gallery' => '["/uploads/public.jpg"]', ':claim' => '{"owner":"existing-task"}',
        ]);
    }
    $beforeUpgrade = count($imageOnlyPdo->writes);
    $assert(medical_patient_images_has_column($imageOnlyPdo, 'doctor')
        && !medical_patient_images_has_column($imageOnlyPdo, 'doctor', 'patient_images_notes'), 'Image-only existing installation distinguishes notes readiness');
    $assert(medreview_with_schema_migration(static fn(): array => medical_patient_images_migrate($imageOnlyPdo)) === ['medical_facilities', 'medical_doctors'], 'Migration upgrades installations with the original image-only columns');
    foreach (['medical_facilities', 'medical_doctors'] as $table) {
        $row = $imageOnlyPdo->query('SELECT * FROM ' . $table)->fetch(PDO::FETCH_ASSOC);
        $assert($row['patient_images_json'] === medical_patient_images_encode($example) && $row['patient_images_notes'] === null
            && $row['gallery_json'] === '["/uploads/public.jpg"]' && $row['ai_writer_claim_json'] === '{"owner":"existing-task"}', $table . ' image-only upgrade preserves private images, public gallery and writer lease');
        $assert($imageOnlyPdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() === 1, $table . ' image-only upgrade never seeds or deletes rows');
    }
    $upgradeWrites = array_slice($imageOnlyPdo->writes, $beforeUpgrade);
    $assert(!array_filter($upgradeWrites, static fn(string $sql): bool => !str_contains($sql, 'patient_images_notes') || str_contains($sql, 'ADD COLUMN patient_images_json')),
        'Image-only upgrade adds only the missing notes columns');
    $afterUpgrade = count($imageOnlyPdo->writes);
    $assert(medreview_with_schema_migration(static fn(): array => medical_patient_images_migrate($imageOnlyPdo)) === []
        && count($imageOnlyPdo->writes) === $afterUpgrade, 'Notes upgrade is idempotent too');
    foreach (['unknown', 'patient_images_notes; DROP TABLE medical_doctors', 'gallery_json'] as $column) {
        try { medical_patient_images_has_column($pdo, 'doctor', $column); $columnRejected = false; }
        catch (InvalidArgumentException) { $columnRejected = true; }
        $assert($columnRejected, 'Readiness column allowlist rejects arbitrary identifier: ' . $column);
    }

    // Read-back verification is isolated to this in-memory DB. Receipts/errors
    // describe columns only and must never copy patient notes or image URLs.
    $verifyNotes = 'PRIVATE_VERIFICATION_NOTE — cần đối chiếu thủ công.';
    $verifyImages = medical_patient_images_encode($example);
    foreach (['medical_facilities', 'medical_doctors'] as $table) {
        $pdo->prepare('UPDATE ' . $table . ' SET patient_images_notes=:notes, patient_images_json=:images WHERE id=1')->execute([
            ':notes' => $verifyNotes, ':images' => $verifyImages,
        ]);
    }
    $readCount = count($pdo->reads);
    $writeCount = count($pdo->writes);
    $assert(medical_patient_images_verify_saved($pdo, 'facility', 1, []) === []
        && count($pdo->reads) === $readCount && count($pdo->writes) === $writeCount, 'Empty verification does not query or change a database');
    foreach (['facility', 'doctor'] as $entity) {
        $receipt = medical_patient_images_verify_saved($pdo, $entity, 1, ['patient_images_notes' => $verifyNotes]);
        $assert($receipt === ['id' => 1, 'saved_fields' => ['patient_images_notes'], 'verified' => true], $entity . ' verification returns only privacy-safe receipt fields');
        $lastSelect = $pdo->reads[array_key_last($pdo->reads)];
        $assert(str_contains($lastSelect, 'patient_images_notes') && !str_contains($lastSelect, 'patient_images_json')
            && !str_contains($lastSelect, 'full_json') && !str_contains($lastSelect, 'gallery_json'), $entity . ' notes verification SELECT reads only the requested note column');
        $bothReceipt = medical_patient_images_verify_saved($pdo, $entity, 1, ['patient_images_json' => $verifyImages, 'patient_images_notes' => $verifyNotes]);
        $assert($bothReceipt === ['id' => 1, 'saved_fields' => ['patient_images_json', 'patient_images_notes'], 'verified' => true], $entity . ' dual verification preserves expected field order without private values');
        $assert(!str_contains(json_encode($bothReceipt), 'PRIVATE_VERIFICATION_NOTE') && !str_contains(json_encode($bothReceipt), '/uploads/patients/'), $entity . ' verification receipt never leaks patient metadata');
    }
    $invalidVerify = [
        ['facility', 0, ['patient_images_notes' => $verifyNotes]],
        ['doctor', -1, ['patient_images_notes' => $verifyNotes]],
        ['medical_facilities; DROP TABLE x', 1, ['patient_images_notes' => $verifyNotes]],
        ['facility', 1, ['full_json' => $verifyNotes]],
        ['facility', 1, ['patient_images_notes; DROP TABLE x' => $verifyNotes]],
        ['facility', 1, ['patient_images_notes' => null]],
        ['facility', 1, ['patient_images_notes' => []]],
        ['facility', 1, ['patient_images_json' => true]],
        ['facility', 1, ['patient_images_json' => 1]],
        ['facility', 1, [$verifyNotes]],
    ];
    foreach ($invalidVerify as $index => [$entity, $id, $expected]) {
        $beforeReads = count($pdo->reads);
        try { medical_patient_images_verify_saved($pdo, $entity, $id, $expected); $invalidRejected = false; }
        catch (InvalidArgumentException $error) { $invalidRejected = true; }
        $assert($invalidRejected && count($pdo->reads) === $beforeReads, 'Invalid verification input rejected before SELECT: ' . $index);
    }
    foreach ([
        [1, ['patient_images_notes' => $verifyNotes . ' changed']],
        [1, ['patient_images_json' => '[]']],
        [999, ['patient_images_notes' => $verifyNotes]],
    ] as $index => [$id, $expected]) {
        try { medical_patient_images_verify_saved($pdo, 'facility', $id, $expected); $verifyError = null; }
        catch (RuntimeException $error) { $verifyError = $error; }
        $assert($verifyError !== null, 'Read-back rejects mismatching or missing row: ' . $index);
        $assert(!str_contains($verifyError->getMessage(), 'PRIVATE_VERIFICATION_NOTE') && !str_contains($verifyError->getMessage(), '/uploads/patients/'), 'Verification failure message never echoes expected metadata: ' . $index);
    }
    $pdo->exec('UPDATE medical_facilities SET patient_images_notes=NULL WHERE id=1');
    try { medical_patient_images_verify_saved($pdo, 'facility', 1, ['patient_images_notes' => $verifyNotes]); $nullRejected = false; }
    catch (RuntimeException $error) { $nullRejected = str_contains($error->getMessage(), 'patient_images_notes') && !str_contains($error->getMessage(), $verifyNotes); }
    $assert($nullRejected, 'A NULL note cannot masquerade as successful string storage');
    $pdo->exec("UPDATE medical_facilities SET patient_images_notes='' WHERE id=1");
    $assert(medical_patient_images_verify_saved($pdo, 'facility', 1, ['patient_images_notes' => ''])['verified'] === true, 'An explicitly cleared empty note verifies exactly');
}

$directory = file_get_contents(dirname(__DIR__) . '/medical_directory.php');
$assert(substr_count($directory, 'patient_images_json MEDIUMTEXT NULL') === 2, 'Fresh schemas include both private nullable columns');
$assert(substr_count($directory, 'patient_images_notes TEXT NULL') === 2, 'Fresh schemas include both private nullable note columns');
$assert(str_contains($directory, "if (array_key_exists('patient_images_json', \$source)) \$source['patient_images_json'] = null;"), 'Translation copy never inherits private patient media');
$assert(str_contains($directory, "medical_patient_images_redact(['full_json' => \$source['full_json']])"), 'Translation copy also redacts patient metadata embedded in legacy full_json');
$assert(str_contains($directory, "if (array_key_exists('patient_images_notes', \$source)) \$source['patient_images_notes'] = null;"), 'Translation copy never inherits private overall notes');
$migrationSource = file_get_contents(dirname(__DIR__) . '/scripts/migrate_patient_images.php');
$assert(strpos($migrationSource, '$arguments = array_slice($argv, 1)') < strpos($migrationSource, "'/db.php'"), 'CLI action validation runs before loading DB definitions');
$assert(!str_contains($migrationSource, 'medical_directory_run_schema_migrations') && !str_contains($migrationSource, 'seed_'), 'Dedicated migration never triggers unrelated migrations or seeds');
if (function_exists('proc_open')) {
    foreach ([[], ['--help'], ['--invalid'], ['--check', '--apply']] as $arguments) {
        $command = array_merge([PHP_BINARY, dirname(__DIR__) . '/scripts/migrate_patient_images.php'], $arguments);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), ['DB_HOST' => 'invalid-host-no-connect', 'DB_NAME' => 'must-not-connect']);
        if (!is_resource($process)) throw new RuntimeException('Could not run usage check');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $status = proc_close($process);
        $assert(str_contains($output, 'Usage:') && $error === '' && $status === ($arguments === [] || $arguments === ['--help'] ? 0 : 2), 'Usage/invalid arguments never connect to DB: ' . implode(' ', $arguments));
    }
}

echo "Patient images: {$checks} checks passed.\n";
