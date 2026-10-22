<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/medical_patient_images.php';
require_once dirname(__DIR__) . '/medical_toplist_detail_view.php';
require_once dirname(__DIR__) . '/medical_image_brand_sources.php';

// Pure PHP/PDO doubles only: no production database, network, files or patient photos.
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
};
$reject = static function (callable $operation, string $label) use ($assert): Throwable {
    try { $operation(); }
    catch (Throwable $error) { $assert(true, $label); return $error; }
    $assert(false, $label . ' must reject');
    throw new LogicException('Unreachable');
};
$json = static fn(mixed $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$secretUrl = 'https://private.example.org/PATIENT_IMAGE_DO_NOT_EXPOSE.webp';
$privateNotes = 'PATIENT_NOTES_DO_NOT_EXPOSE — ảnh trước/sau điều trị cần đối chiếu thủ công.';
$patientList = [['url' => $secretUrl, 'caption' => 'PATIENT_CAPTION_DO_NOT_EXPOSE', 'stage' => 'before', 'consent_confirmed' => false]];
$privateJson = $json($patientList);
$assertPrivateAbsent = static function (mixed $value, string $label) use ($assert, $json, $secretUrl, $privateNotes): void {
    $encoded = is_string($value) ? $value : $json($value);
    $assert(!str_contains($encoded, $secretUrl) && !str_contains($encoded, 'PATIENT_CAPTION_DO_NOT_EXPOSE')
        && !str_contains($encoded, $privateNotes) && !str_contains($encoded, 'PATIENT_NOTES_DO_NOT_EXPOSE')
        && !str_contains($encoded, 'patient_images_json') && !str_contains($encoded, 'patient_images_notes')
        && !str_contains($encoded, '"patient_images"'), $label);
};

$rawDoctor = ['id' => 301, 'slug' => 'privacy-doctor', 'name' => 'Bác sĩ mẫu', 'language_code' => 'vi',
    'title_text' => 'Bác sĩ', 'specialty_text' => 'Nhãn khoa', 'facility_name' => 'Cơ sở mẫu',
    'content' => '<p>Nội dung hồ sơ công khai.</p>', 'image_url' => '/uploads/public-doctor.webp',
    'gallery_json' => $json([['url' => '/uploads/public-room.webp', 'caption' => 'Phòng khám']]),
    'patient_images_json' => $privateJson, 'patient_images' => $patientList,
    'patient_images_notes' => $privateNotes,
    'full_json' => $json(['nested' => ['patient_images_json' => $patientList, 'patient_images_notes' => $privateNotes]]),
    'rating' => 0, 'reviews_count' => 0, 'status' => 'published'];
$rawFacility = array_replace($rawDoctor, ['id' => 302, 'slug' => 'privacy-facility', 'name' => 'Cơ sở mẫu', 'category' => 'Phòng khám']);

foreach (['vi', 'en'] as $locale) {
    $doctor = medical_directory_doctor_from_row(array_replace($rawDoctor, ['language_code' => $locale]));
    $facility = medical_directory_facility_from_row(array_replace($rawFacility, ['language_code' => $locale]));
    $assertPrivateAbsent($doctor, $locale . ' public doctor mapper omits private image metadata');
    $assertPrivateAbsent($facility, $locale . ' public facility mapper omits private image metadata');
    $assert($doctor['image_url'] === '/uploads/public-doctor.webp' && count($doctor['gallery']) === 2,
        $locale . ' public gallery and portrait remain separate and unchanged');
    $assertPrivateAbsent(medical_doctor_profile_model($doctor), $locale . ' doctor display model receives no patient images');
    $assertPrivateAbsent(medical_toplist_detail_members([
        array_replace($rawDoctor, ['member_type' => 'doctor', 'rank_order' => 1]),
        array_replace($rawFacility, ['member_type' => 'facility', 'rank_order' => 2]),
    ], $locale), $locale . ' doctor/mixed Toplist member DTOs never include patient images');
}

// A fake driver deliberately returns extra private columns even for a narrow
// SELECT, checking the search builders' output allowlists rather than fixtures.
final class PatientImagesSearchPDO extends PDO
{
    public array $queries = [];
    public function __construct(private array $doctor, private array $facility) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (!preg_match('/^\s*SELECT\b/i', $query)) throw new RuntimeException('Only read-only SELECT permitted');
        $this->queries[] = $query;
        $rows = str_contains($query, 'LIMIT 0') ? [] : (str_contains($query, 'medical_doctors') ? [$this->doctor] : [$this->facility]);
        return new PatientImagesRowsStatement($rows);
    }
    public function exec(string $statement): int|false { throw new RuntimeException('Unexpected write'); }
    public function prepare(string $query, array $options = []): PDOStatement|false { throw new RuntimeException('Unexpected prepare'); }
}
final class PatientImagesRowsStatement extends PDOStatement
{
    public function __construct(private array $rows) {}
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
$searchPdo = new PatientImagesSearchPDO($rawDoctor, $rawFacility);
$doctorIndex = medical_search_cache_doctors($searchPdo);
$facilityIndex = medical_search_cache_facilities($searchPdo);
$assert(count($doctorIndex) === 1 && count($facilityIndex) === 1, 'public search fixture really produces both entity types');
$assertPrivateAbsent($doctorIndex, 'doctor JSON search index excludes private image metadata');
$assertPrivateAbsent($facilityIndex, 'facility JSON search index excludes private image metadata');
$assertPrivateAbsent(medical_search_cache_doctor_directory_item($doctorIndex[0]), 'doctor directory JSON excludes patient images');
$assertPrivateAbsent(medical_search_cache_directory_item($facilityIndex[0]), 'facility directory JSON excludes patient images');
foreach (['doctor', 'facility'] as $type) {
    $assert(!array_key_exists('patient_images_json', medical_image_brand_source_fields($type)), $type . ' branding DB source allowlist excludes patient images');
    $assert(!array_key_exists('patient_images_notes', medical_image_brand_source_fields($type)), $type . ' branding DB source allowlist excludes private overall notes');
    $seen = [];
    $raw = $type === 'doctor' ? $rawDoctor : $rawFacility;
    medical_image_brand_source_patch($raw, $type, static function (string $url) use (&$seen): string { $seen[] = $url; return $url; });
    $assert(!in_array($secretUrl, $seen, true), $type . ' branding mapper never visits private patient image URLs');
}

$template = medical_doctor_output_template(301, 'Bác sĩ mẫu');
$assert(!array_key_exists('patient_images_json', $template), 'AI output template does not request patient photos');
$assert(!array_key_exists('patient_images_notes', $template), 'AI output template does not request or investigate private patient notes');
$assert(!in_array('patient_images_json', medical_doctor_json_fields(), true), 'public doctor JSON facts exclude private image column');
$assert(!array_key_exists('patient_images_notes', medical_doctor_text_fields()), 'public doctor text facts exclude private notes column');
$assert(!array_key_exists('patient_images_json', medical_doctor_column_definitions()), 'optional private column is not generic doctor readiness dependency');
$assert(!array_key_exists('patient_images_notes', medical_doctor_column_definitions()), 'optional note column is not generic doctor readiness dependency');
$source = array_replace($rawDoctor, [
    'bio_json' => $json([['text' => 'Thông tin công khai', 'patient_images_json' => $patientList, 'patient_images_notes' => $privateNotes]]),
    'nested' => ['patient_images' => $patientList, 'patient_images_notes' => $privateNotes, 'public' => 'SAFE_NESTED_VALUE'],
    'ai_writer_claim_json' => $json(['claim_token' => 'PRIVATE_WRITER_TOKEN', 'expires_at' => time() + 300]),
]);
$prompt = medical_doctor_research_prompt('Nguồn: {{source_json}}', $source);
$assertPrivateAbsent($prompt, 'doctor AI prompt redacts patient keys before and after decoding JSON fields');
$assert(!str_contains($prompt, 'PRIVATE_WRITER_TOKEN') && str_contains($prompt, 'SAFE_NESTED_VALUE'), 'prompt retains public context without writer secrets');

// Load only the pure response function; never execute _doctor.php authentication,
// header emission, configured DB connection or endpoint-level readiness checks.
$apiSource = (string) file_get_contents(dirname(__DIR__) . '/api/medical/_doctor.php');
$tokens = token_get_all($apiSource);
$function = '';
$capture = false;
$depth = 0;
$opened = false;
foreach ($tokens as $index => $token) {
    if (!$capture && is_array($token) && $token[0] === T_FUNCTION) {
        $next = $index + 1;
        while (isset($tokens[$next]) && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) $next++;
        if (is_array($tokens[$next] ?? null) && $tokens[$next][1] === 'medical_doctor_api_source') $capture = true;
    }
    if (!$capture) continue;
    $text = is_array($token) ? $token[1] : $token;
    $function .= $text;
    if (!is_array($token) && $token === '{') { $depth++; $opened = true; }
    elseif (!is_array($token) && $token === '}') {
        $depth--;
        if ($opened && $depth === 0) break;
    }
}
$assert(str_starts_with($function, 'function medical_doctor_api_source(') && $depth === 0, 'extract only pure authenticated research response mapper');
eval($function);
$response = medical_doctor_api_source($source);
$assertPrivateAbsent($response, 'authenticated doctor research response also omits nested/full_json patient metadata');
$assert(($response['writer_claimed'] ?? false) === true && !str_contains($json($response), 'PRIVATE_WRITER_TOKEN'), 'response preserves safe writer status without token');

$base = array_replace($template, [
    'content' => '<h2>Giới thiệu</h2><p>Hồ sơ công khai có bằng chứng.</p>',
    'sources_json' => [['id' => 's1', 'url' => 'https://hospital.example.org/doctor', 'publisher' => 'Bệnh viện mẫu']],
    'evidence_json' => ['identity_status' => 'matched', 'missing_fields' => [], 'conflicts' => []],
    'insufficient_data' => false,
]);
$assert(!array_key_exists('patient_images_json', medical_doctor_normalize_payload($base)), 'omitted patient field remains omitted during research normalization');
$assert(!array_key_exists('patient_images_notes', medical_doctor_normalize_payload($base)), 'omitted patient notes remain omitted during research normalization');
$withPatients = array_replace($base, ['patient_images_json' => $patientList, 'patient_images_notes' => $privateNotes]);
$normalized = medical_doctor_normalize_payload($withPatients);
$assert(json_decode($normalized['patient_images_json'] ?? '', true) === medical_patient_images_normalize($patientList), 'explicit patient list normalizes into dedicated column');
$assert(($normalized['patient_images_notes'] ?? null) === $privateNotes, 'explicit patient notes normalize into a separate private column');
$assert((medical_doctor_normalize_payload(array_replace($base, ['patient_images_json' => []]))['patient_images_json'] ?? null) === '[]', 'explicit empty list clears dedicated field');
$assert((medical_doctor_normalize_payload(array_replace($base, ['patient_images_notes' => '']))['patient_images_notes'] ?? null) === '', 'explicit empty notes string clears only private notes');

// Strict in-memory transaction double: covers optional-column compatibility,
// missing-column failure and rollback of research + workplace + patient metadata.
final class PatientImagesSavePDO extends PDO
{
    public array $queries = [];
    public int $commits = 0;
    public int $rollbacks = 0;
    public int $locationRows = 1;
    public bool $failUpdate = false;
    private bool $active = false;
    private array $snapshot = [];
    public function __construct(public array $doctor, public bool $hasPatientColumn = true, public bool $hasPatientNotesColumn = true) {}
    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) return 'mysql';
        throw new RuntimeException('Unexpected PDO attribute');
    }
    public function beginTransaction(): bool
    {
        if ($this->active) throw new RuntimeException('Unexpected nested transaction');
        $this->snapshot = [$this->doctor, $this->locationRows];
        return $this->active = true;
    }
    public function commit(): bool { $this->active = false; $this->commits++; return true; }
    public function rollBack(): bool
    {
        [$this->doctor, $this->locationRows] = $this->snapshot;
        $this->active = false; $this->rollbacks++; return true;
    }
    public function inTransaction(): bool { return $this->active; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!str_contains($query, 'INFORMATION_SCHEMA.COLUMNS')
            && !str_starts_with($query, 'SELECT * FROM medical_doctors WHERE id=:id FOR UPDATE')
            && !str_starts_with($query, 'DELETE FROM medical_doctor_facilities WHERE doctor_id=:id')
            && !str_starts_with($query, 'UPDATE medical_doctors SET ')) throw new RuntimeException('Unexpected isolated save SQL: ' . $query);
        $this->queries[] = $query;
        return new PatientImagesSaveStatement($this, $query);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (!str_starts_with($query, 'SELECT ') || !str_contains($query, 'LIMIT 0')) throw new RuntimeException('Unexpected query');
        $this->queries[] = $query;
        if ((!$this->hasPatientColumn && str_contains($query, 'patient_images_json'))
            || (!$this->hasPatientNotesColumn && str_contains($query, 'patient_images_notes'))) throw new PDOException('Optional column absent');
        return new PatientImagesRowsStatement([]);
    }
    public function exec(string $statement): int|false { throw new RuntimeException('Unexpected migration/write'); }
}
final class PatientImagesSaveStatement extends PDOStatement
{
    private string $metadataColumn = '';
    public function __construct(private PatientImagesSavePDO $pdo, private string $query) {}
    public function execute(?array $params = null): bool
    {
        if (str_contains($this->query, 'INFORMATION_SCHEMA.COLUMNS')) {
            $this->metadataColumn = (string) ($params[':column'] ?? '');
            $this->pdo->queries[] = 'TEST_METADATA_COLUMN:' . $this->metadataColumn;
            return true;
        }
        if (!$this->pdo->inTransaction()) throw new RuntimeException('Save outside transaction');
        if (str_starts_with($this->query, 'DELETE')) $this->pdo->locationRows = 0;
        elseif (str_starts_with($this->query, 'UPDATE')) {
            if ($this->pdo->failUpdate) throw new RuntimeException('Simulated dedicated patient column write failure');
            foreach ($params ?? [] as $key => $value) if ($key !== ':id') $this->pdo->doctor[ltrim($key, ':')] = $value;
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return str_starts_with($this->query, 'SELECT * FROM medical_doctors') ? $this->pdo->doctor : false;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        if (!str_contains($this->query, 'INFORMATION_SCHEMA.COLUMNS')) return false;
        return match ($this->metadataColumn) {
            'patient_images_json' => $this->pdo->hasPatientColumn ? 1 : false,
            'patient_images_notes' => $this->pdo->hasPatientNotesColumn ? 1 : false,
            default => throw new RuntimeException('Unexpected metadata column: ' . $this->metadataColumn),
        };
    }
}
$token = str_repeat('a', 64);
$savedRow = array_replace($rawDoctor, [
    'content' => null, 'last_researched_at' => null, 'facility_name' => '', 'facility_slug' => '',
    'ai_writer_claim_json' => $json(['claim_token' => $token, 'request_id' => 'patient-fixture', 'expires_at' => time() + 300]),
]);
$saveBody = array_replace($base, ['writer_claim_token' => $token]);

$legacyPdo = new PatientImagesSavePDO($savedRow, false, false);
medical_doctor_require_schema($legacyPdo);
$result = medical_doctor_save_research_locked($legacyPdo, $saveBody);
$assert($legacyPdo->commits === 1 && $result['writer_released'] === true, 'old schema and omitted new field still save old research successfully');
$assert($legacyPdo->doctor['patient_images_json'] === $privateJson, 'omitted patient field preserves prior metadata');
$assert($legacyPdo->doctor['patient_images_notes'] === $privateNotes, 'omitted notes preserve prior private note');
$assert(!array_filter($legacyPdo->queries, static fn(string $sql): bool => str_contains($sql, 'patient_images_json') || str_contains($sql, 'patient_images_notes') || str_contains($sql, 'INFORMATION_SCHEMA')),
    'old doctor payload performs no optional patient-column readiness query or update');

$savePdo = new PatientImagesSavePDO($savedRow);
$receipt = medical_doctor_save_research_locked($savePdo, array_replace($saveBody, ['patient_images_json' => $patientList, 'patient_images_notes' => $privateNotes,
    'nested' => ['patient_images' => $patientList, 'patient_images_notes' => $privateNotes, 'public' => 'KEPT_PUBLIC_VALUE']]));
$assert($savePdo->commits === 1 && $savePdo->rollbacks === 0, 'patient metadata and doctor research commit together');
$assert(json_decode($savePdo->doctor['patient_images_json'], true) === medical_patient_images_normalize($patientList), 'private images persist in dedicated column only');
$assert($savePdo->doctor['patient_images_notes'] === $privateNotes, 'private notes persist in their dedicated column only');
$assertPrivateAbsent($savePdo->doctor['full_json'], 'saved raw research JSON removes canonical and nested alias patient metadata');
$assert(!str_contains($json($receipt), $secretUrl) && !str_contains($json($receipt), 'PATIENT_CAPTION_DO_NOT_EXPOSE')
    && !str_contains($json($receipt), 'PATIENT_NOTES_DO_NOT_EXPOSE') && !array_key_exists('patient_images_json', $receipt)
    && !array_key_exists('patient_images_notes', $receipt), 'save receipt may name saved columns but contains no private photo/note values');
$assert(str_contains($savePdo->doctor['full_json'], 'KEPT_PUBLIC_VALUE'), 'raw research preserves unrelated public context after redaction');

$clearPdo = new PatientImagesSavePDO($savedRow);
medical_doctor_save_research_locked($clearPdo, array_replace($saveBody, ['patient_images_json' => '[]']));
$assert($clearPdo->doctor['patient_images_json'] === '[]', 'explicit encoded empty list clears without deleting an image file');
$assert($clearPdo->doctor['patient_images_notes'] === $privateNotes, 'clearing images never implicitly clears notes');

$notesOnlyPdo = new PatientImagesSavePDO($savedRow, false, true);
medical_doctor_save_research_locked($notesOnlyPdo, array_replace($saveBody, ['patient_images_notes' => "  Ghi chú mới\r\nCần đối chiếu thủ công.  "]));
$assert($notesOnlyPdo->doctor['patient_images_notes'] === "Ghi chú mới\nCần đối chiếu thủ công."
    && $notesOnlyPdo->doctor['patient_images_json'] === $privateJson, 'notes update normalizes multiline text and preserves independent image list');
$assert(in_array('TEST_METADATA_COLUMN:patient_images_notes', $notesOnlyPdo->queries, true)
    && !in_array('TEST_METADATA_COLUMN:patient_images_json', $notesOnlyPdo->queries, true), 'notes-only research requires only notes schema, not image schema');
$notesClearPdo = new PatientImagesSavePDO($savedRow, false, true);
medical_doctor_save_research_locked($notesClearPdo, array_replace($saveBody, ['patient_images_notes' => '']));
$assert($notesClearPdo->doctor['patient_images_notes'] === '' && $notesClearPdo->doctor['patient_images_json'] === $privateJson, 'explicit empty string clears notes without clearing images');
$imageOnlyPdo = new PatientImagesSavePDO($savedRow, true, false);
medical_doctor_save_research_locked($imageOnlyPdo, array_replace($saveBody, ['patient_images_json' => $patientList]));
$assert($imageOnlyPdo->commits === 1 && $imageOnlyPdo->doctor['patient_images_notes'] === $privateNotes
    && !in_array('TEST_METADATA_COLUMN:patient_images_notes', $imageOnlyPdo->queries, true), 'old image-only installation accepts image updates without requiring new notes column');

$missingPdo = new PatientImagesSavePDO($savedRow, false);
$missingError = $reject(fn() => medical_doctor_save_research_locked($missingPdo, array_replace($saveBody, ['patient_images_json' => $patientList])), 'new field on missing schema fails safely');
$assert($missingError instanceof InvalidArgumentException && str_contains($missingError->getMessage(), 'migrate_patient_images.php --apply'), 'missing schema gives explicit migration guidance, not a generic 503');
$assert($missingPdo->doctor === $savedRow && $missingPdo->locationRows === 1 && $missingPdo->commits === 0 && !$missingPdo->inTransaction(), 'missing schema cannot partially save research, release lease or change workplaces');
$missingNotesPdo = new PatientImagesSavePDO($savedRow, true, false);
$missingNotesError = $reject(fn() => medical_doctor_save_research_locked($missingNotesPdo, array_replace($saveBody, ['patient_images_notes' => 'Ghi chú mới'])), 'explicit notes on image-only schema reject safely');
$assert($missingNotesError instanceof InvalidArgumentException && str_contains($missingNotesError->getMessage(), 'patient_images_notes')
    && str_contains($missingNotesError->getMessage(), 'migrate_patient_images.php --apply'), 'missing note column identifies notes and explicit migration');
$assert($missingNotesPdo->doctor === $savedRow && $missingNotesPdo->commits === 0 && !$missingNotesPdo->inTransaction(), 'missing notes schema cannot partially save, clear old images or release lease');

$failurePdo = new PatientImagesSavePDO($savedRow);
$failurePdo->failUpdate = true;
$failure = $reject(fn() => medical_doctor_save_research_locked($failurePdo, array_replace($saveBody, ['patient_images_json' => $patientList, 'patient_images_notes' => 'Ghi chú mới'])), 'dedicated column update failure propagates');
$assert($failure->getMessage() === 'Simulated dedicated patient column write failure', 'isolated write failure is the intended failure');
$assert($failurePdo->doctor === $savedRow && $failurePdo->locationRows === 1 && $failurePdo->rollbacks === 1 && $failurePdo->commits === 0, 'write failure restores previous private list, research, lease and relational links');

$invalidPdo = new PatientImagesSavePDO($savedRow);
$error = $reject(fn() => medical_doctor_save_research_locked($invalidPdo, array_replace($saveBody, ['patient_images_json' => [['url' => 'blob:https://example.org/image']]])), 'invalid image URL rejects before persistence');
$assert($error instanceof InvalidArgumentException && $invalidPdo->doctor === $savedRow && $invalidPdo->commits === 0, 'invalid patient list never mutates existing record');
$invalidNotesPdo = new PatientImagesSavePDO($savedRow);
$notesError = $reject(fn() => medical_doctor_save_research_locked($invalidNotesPdo, array_replace($saveBody, ['patient_images_notes' => null])), 'invalid null notes reject before persistence');
$assert($notesError instanceof InvalidArgumentException && $invalidNotesPdo->doctor === $savedRow && $invalidNotesPdo->commits === 0, 'invalid notes never mutate research, images or earlier notes');

$facilityEndpoint = (string) file_get_contents(dirname(__DIR__) . '/api/medical/facility-content-update.php');
$ratingStart = strpos($facilityEndpoint, 'function medical_api_content_rating_list(');
$ratingEnd = strpos($facilityEndpoint, 'function medical_api_content_bool(', $ratingStart);
$assert($ratingStart !== false && $ratingEnd !== false, 'extract pure facility rating dependencies without endpoint bootstrap');
eval(substr($facilityEndpoint, $ratingStart, $ratingEnd - $ratingStart));
$preflightStart = strpos($facilityEndpoint, '$patientImagesRequested = false;');
$preflightEnd = strpos($facilityEndpoint, '// Schema is installed by explicit maintenance', $preflightStart);
$assert($preflightStart !== false && $preflightEnd !== false, 'extract pure batch preflight before configured DB access');
$preflightCode = substr($facilityEndpoint, $preflightStart, $preflightEnd - $preflightStart);
final class PatientImagesPreflightResponse extends RuntimeException
{
    public function __construct(public array $payload, public int $statusCode) { parent::__construct('Isolated JSON response'); }
}
function json_response(array $payload, int $statusCode = 200): void
{
    // This test never includes the HTTP bootstrap. A response becomes an
    // exception, preventing any exit, real DB connection or endpoint write.
    throw new PatientImagesPreflightResponse($payload, $statusCode);
}
$preflight = static function (array $items) use ($preflightCode): array {
    eval($preflightCode);
    return ['items' => $items, 'requested' => $patientImagesRequested, 'notes_requested' => $patientImageNotesRequested ?? false];
};
$legacyBatch = $preflight([['id' => 302, 'content' => '<p>Legacy article</p>']]);
$assert($legacyBatch['requested'] === false && !array_key_exists('patient_images_json', $legacyBatch['items'][0]), 'legacy facility batch never opts into private column readiness');
$assert($legacyBatch['notes_requested'] === false && !array_key_exists('patient_images_notes', $legacyBatch['items'][0]), 'legacy facility batch never opts into optional notes readiness');
$privateBatch = $preflight([['id' => 302, 'patient_images_json' => $privateJson], ['id' => 303, 'patient_images_json' => []]]);
$assert($privateBatch['requested'] === true && $privateBatch['items'][0]['patient_images_json'] === medical_patient_images_normalize($patientList)
    && $privateBatch['items'][1]['patient_images_json'] === [], 'facility preflight supports JSON-string lists and explicit clear together');
$notesBatch = $preflight([['id' => 302, 'patient_images_notes' => "  Ghi chú\r\nDòng mới  "], ['id' => 303, 'patient_images_notes' => '']]);
$assert($notesBatch['requested'] === false && $notesBatch['notes_requested'] === true
    && $notesBatch['items'][0]['patient_images_notes'] === "Ghi chú\nDòng mới" && $notesBatch['items'][1]['patient_images_notes'] === '', 'facility preflight independently accepts notes normalization and explicit clear without requiring image schema');
$badBatchError = $reject(fn() => $preflight([
    ['id' => 302, 'content' => '<p>Earlier valid article must not be written</p>', 'patient_images_json' => $patientList],
    ['id' => 303, 'patient_images_json' => [['url' => 'data:image/png;base64,not-real']]],
]), 'later invalid patient item aborts pure facility batch before writes');
$assert($badBatchError instanceof PatientImagesPreflightResponse && $badBatchError->statusCode === 422
    && str_contains($badBatchError->payload['message'] ?? '', '[index 1]'), 'invalid facility patient item identifies its index and 422 before DB access');
$badNotesBatch = $reject(fn() => $preflight([['id' => 302, 'patient_images_json' => $patientList], ['id' => 303, 'patient_images_notes' => []]]), 'invalid notes in later facility item abort batch before writes');
$assert($badNotesBatch instanceof PatientImagesPreflightResponse && $badNotesBatch->statusCode === 422
    && str_contains($badNotesBatch->payload['message'] ?? '', '[index 1]'), 'invalid facility notes identifies indexed 422 before DB access');
$assert(str_contains($facilityEndpoint, 'medical_patient_images_encode(') && str_contains($facilityEndpoint, "array_key_exists('patient_images_json'"), 'facility API validates only explicitly supplied patient list');
$assert(str_contains($facilityEndpoint, "medical_patient_images_require_schema($" . "pdo, 'facility')"), 'facility API checks optional schema only for new field use');
$assert(str_contains($facilityEndpoint, "medical_patient_images_require_schema($" . "pdo, 'facility', 'patient_images_notes')"), 'facility API checks notes schema independently');
$assert(str_contains($facilityEndpoint, 'medical_patient_images_redact('), 'facility raw payload and API result have a patient metadata redaction boundary');
$preflightAt = strpos($facilityEndpoint, '$patientImagesRequested = false;');
$loopAt = strpos($facilityEndpoint, 'try { foreach ($items as $item)');
$schemaAt = strpos($facilityEndpoint, "medical_patient_images_require_schema($" . "pdo, 'facility')");
$assert($preflightAt !== false && $schemaAt !== false && $loopAt !== false && $preflightAt < $schemaAt && $schemaAt < $loopAt,
    'facility batch patient validation and optional schema check happen before any per-record writes');
$assert(str_contains($facilityEndpoint, '$set = $patientImagesOnly ? [] : [\'full_json\' => \':full_json\'];')
    && str_contains($facilityEndpoint, "if (!$" . "patientImagesOnly) $" . "params[':full_json']"), 'patient-only facility update preserves the existing public raw article JSON');
$assert(str_contains($facilityEndpoint, "$" . "patientImagesOnly = ($" . "patientImagesProvided || $" . "patientImageNotesProvided)"), 'notes-only facility patch also preserves existing full_json');
$privatePatchStart = strpos($facilityEndpoint, '$normalizedItem = medical_patient_images_redact($item);');
$privatePatchEnd = strpos($facilityEndpoint, 'foreach ($fields as $input => $column)', $privatePatchStart);
$assert($privatePatchStart !== false && $privatePatchEnd !== false, 'extract pure facility private-field SQL parameter preparation');
$privatePatchCode = substr($facilityEndpoint, $privatePatchStart, $privatePatchEnd - $privatePatchStart);
$privatePatch = static function (array $item) use ($privatePatchCode): array {
    $facilityId = (int) ($item['id'] ?? 302);
    $facilityRow = [];
    eval($privatePatchCode);
    return ['set' => $set, 'params' => $params, 'normalized' => $normalizedItem, 'only' => $patientImagesOnly];
};
$notesPatch = $privatePatch(['id' => 302, 'patient_images_notes' => $privateNotes]);
$assert($notesPatch['only'] === true && $notesPatch['set'] === ['patient_images_notes' => ':patient_images_notes']
    && $notesPatch['params'] === [':id' => 302, ':patient_images_notes' => $privateNotes], 'notes-only facility update binds only notes and id, preserving full_json and existing images');
$assertPrivateAbsent($notesPatch['normalized'], 'notes-only facility API echo removes private overall notes');
$notesClearPatch = $privatePatch(['id' => 302, 'patient_images_notes' => '']);
$assert($notesClearPatch['only'] === true && $notesClearPatch['params'][':patient_images_notes'] === ''
    && !isset($notesClearPatch['set']['full_json'], $notesClearPatch['set']['patient_images_json']), 'explicit notes clear leaves article provenance and image column untouched');
$bothPrivatePatch = $privatePatch(['id' => 302, 'patient_images_notes' => $privateNotes, 'patient_images_json' => $patientList]);
$assert($bothPrivatePatch['only'] === true && !isset($bothPrivatePatch['set']['full_json'])
    && $bothPrivatePatch['params'][':patient_images_notes'] === $privateNotes
    && json_decode($bothPrivatePatch['params'][':patient_images_json'], true) === medical_patient_images_normalize($patientList), 'combined private image/note facility update preserves public full_json');
$mixedPublicPatch = $privatePatch(['id' => 302, 'patient_images_notes' => $privateNotes, 'content' => '<p>Updated public article</p>']);
$assert($mixedPublicPatch['only'] === false && isset($mixedPublicPatch['set']['full_json'])
    && ($mixedPublicPatch['normalized']['content'] ?? '') === '<p>Updated public article</p>', 'mixed public/private facility update still records public provenance while removing private notes');
$assertPrivateAbsent($mixedPublicPatch['normalized'], 'mixed facility public payload never echoes private notes');

// Regression for the reported facility #393: eight patient photos already
// exist, but the previous deployment only echoed the note while its DB column
// stayed NULL. Run the real pure parameter-preparation and read-back blocks
// against an isolated SQLite database, without endpoint bootstrap/auth/network.
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    final class PatientImagesFacility393PDO extends PDO
    {
        public array $prepared = [];
        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            $this->prepared[] = $query;
            return parent::prepare($query, $options);
        }
    }
    $fixturePdo = new PatientImagesFacility393PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $fixturePdo->exec('CREATE TABLE medical_facilities (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT, gallery_json TEXT, full_json TEXT, patient_images_json TEXT, patient_images_notes TEXT)');
    $eightImages = [];
    for ($index = 1; $index <= 8; $index++) {
        $eightImages[] = ['url' => 'https://private.example.org/PRIVATE_393_' . $index . '.webp', 'caption' => 'Ảnh riêng ' . $index,
            'stage' => $index <= 4 ? 'before' : 'after', 'consent_confirmed' => false];
    }
    $eightJson = medical_patient_images_encode($eightImages);
    $publicGallery = $json([['url' => '/uploads/public-room-393.webp', 'caption' => 'Phòng khám']]);
    $publicFullJson = $json(['id' => 393, 'content' => '<p>Public article provenance unchanged.</p>']);
    $fixturePdo->prepare('INSERT INTO medical_facilities (id,name,image_url,gallery_json,full_json,patient_images_json,patient_images_notes) VALUES (393,:name,:image,:gallery,:full,:patients,NULL)')->execute([
        ':name' => 'Cơ sở kiểm thử 393', ':image' => '/uploads/public-facility-393.webp', ':gallery' => $publicGallery,
        ':full' => $publicFullJson, ':patients' => $eightJson,
    ]);
    $fixtureRead = static fn(): array => $fixturePdo->query('SELECT * FROM medical_facilities WHERE id=393')->fetch(PDO::FETCH_ASSOC);
    $initial393 = $fixtureRead();
    $assert($initial393['patient_images_notes'] === null && count(json_decode($initial393['patient_images_json'], true)) === 8, 'Fixture reproduces #393 with eight stored patient images and NULL notes');

    // Emulate the old incorrect behavior: putting a note in full_json is not
    // a successful write to the patient_images_notes column.
    $fixturePdo->prepare('UPDATE medical_facilities SET full_json=:full WHERE id=393')->execute([
        ':full' => $json(['id' => 393, 'patient_images_notes' => $privateNotes]),
    ]);
    $echoOnlyError = $reject(fn() => medical_patient_images_verify_saved($fixturePdo, 'facility', 393, ['patient_images_notes' => $privateNotes]), 'Readback rejects old full_json-only note echo');
    $assert($echoOnlyError instanceof RuntimeException && str_contains($echoOnlyError->getMessage(), 'patient_images_notes')
        && !str_contains($echoOnlyError->getMessage(), 'PATIENT_NOTES_DO_NOT_EXPOSE'), 'Old echo-only failure names the column without leaking the private note');
    $assert($fixtureRead()['patient_images_notes'] === null && $fixtureRead()['patient_images_json'] === $eightJson, 'Readback failure never invents a saved note or rewrites existing eight images');
    $fixturePdo->prepare('UPDATE medical_facilities SET full_json=:full WHERE id=393')->execute([':full' => $publicFullJson]);

    $prepared393 = $privatePatch(['id' => 393, 'patient_images_notes' => $privateNotes]);
    $set393 = [];
    foreach ($prepared393['set'] as $column => $placeholder) $set393[] = '`' . $column . '`=' . $placeholder;
    $fixturePdo->prepare('UPDATE medical_facilities SET ' . implode(', ', $set393) . ' WHERE id=:id')->execute($prepared393['params']);

    // Execute the receiver's own read-back and success-marker ordering, not
    // a fixture reimplementation that might mask an omitted verification call.
    $verifyStart = strpos($facilityEndpoint, '$patientExpected = [];');
    $verifyEnd = strpos($facilityEndpoint, '$updated[] = $facilityId;', $verifyStart);
    $assert($verifyStart !== false && $verifyEnd !== false, 'Receiver exposes read-back verification before adding updated id');
    $verifyCode = substr($facilityEndpoint, $verifyStart, $verifyEnd + strlen('$updated[] = $facilityId;') - $verifyStart);
    $verifyReceiver = static function (PDO $pdo, array $params, int $facilityId = 393) use ($verifyCode): array {
        $patientImageUpdates = [];
        $facilityInsightsProvided = array_key_exists(':facility_insights_json', $params);
        $facilityInsightUpdates = [];
        $updated = [];
        eval($verifyCode);
        return ['patient_images_updates' => $patientImageUpdates, 'updated_ids' => $updated];
    };
    $verified393 = $verifyReceiver($fixturePdo, $prepared393['params']);
    $assert($verified393 === ['patient_images_updates' => [['id' => 393, 'saved_fields' => ['patient_images_notes'], 'verified' => true]], 'updated_ids' => [393]], 'Real receiver readback produces verified note receipt and updated id only after actual column write');
    $after393 = $fixtureRead();
    $assert($after393['patient_images_notes'] === $privateNotes, 'Notes-only #393 update persists the correct dedicated column');
    foreach (['patient_images_json', 'gallery_json', 'image_url', 'full_json', 'name'] as $unchanged) {
        $assert($after393[$unchanged] === $initial393[$unchanged], '#393 notes-only patch preserves existing ' . $unchanged);
    }
    $assert(!str_contains($json($verified393), 'PATIENT_NOTES_DO_NOT_EXPOSE') && !str_contains($json($verified393), 'PRIVATE_393_'), 'Verified #393 receipt never includes patient images or note text');
    $assertPrivateAbsent($prepared393['normalized'], 'Processed-item DTO excludes patient metadata after verified notes save');
    $lastReadback = array_values(array_filter($fixturePdo->prepared, static fn(string $sql): bool => str_starts_with($sql, 'SELECT ')));
    $assert($lastReadback !== [] && !str_contains($lastReadback[array_key_last($lastReadback)], 'patient_images_json')
        && !str_contains($lastReadback[array_key_last($lastReadback)], 'full_json'), 'Notes-only persistence receipt reads only the submitted private note column');

    $wrongValueError = $reject(fn() => $verifyReceiver($fixturePdo, [':id' => 393, ':patient_images_notes' => $privateNotes . ' DIFFERENT']), 'Receiver refuses receipt and updated id when stored note differs');
    $assert($wrongValueError instanceof RuntimeException && !str_contains($wrongValueError->getMessage(), 'PATIENT_NOTES_DO_NOT_EXPOSE'), 'Mismatch failure keeps note value private');
    $missingRowError = $reject(fn() => $verifyReceiver($fixturePdo, [':id' => 394, ':patient_images_notes' => $privateNotes], 394), 'Receiver refuses receipt for missing record');
    $assert($missingRowError instanceof RuntimeException && !str_contains($missingRowError->getMessage(), 'PATIENT_NOTES_DO_NOT_EXPOSE'), 'Missing-record verification never echoes note text');
    $assert($verifyReceiver($fixturePdo, [':id' => 393]) === ['patient_images_updates' => [], 'updated_ids' => [393]], 'Legacy facility payload produces no private readback receipts');
}
$assert(str_contains($facilityEndpoint, "header('X-MedReview-Patient-Images-Contract: 2')"), 'Facility endpoint identifies deployed readback contract in response header');
$assert(substr_count($facilityEndpoint, "'patient_images_contract_version' => 2") >= 2
    && substr_count($facilityEndpoint, "'patient_images_updates' => $" . 'patientImageUpdates') >= 2, 'Success and processing-error JSON both expose version 2 and privacy-safe receipts');
$verificationAt = strpos($facilityEndpoint, 'medical_patient_images_verify_saved(');
$successAt = strpos($facilityEndpoint, '$updated[] = $facilityId;', $verificationAt);
$updateAt = strpos($facilityEndpoint, '$updateStmt->execute($params);');
$leaseCheckAt = strpos($facilityEndpoint, 'if ($updateStmt->rowCount() === 0)');
$assert($updateAt !== false && $leaseCheckAt !== false && $verificationAt !== false && $successAt !== false
    && $updateAt < $leaseCheckAt && $leaseCheckAt < $verificationAt && $verificationAt < $successAt, 'Dedicated-column verification runs after UPDATE and lease checks but before success is reported');
$assert(!str_contains($facilityEndpoint, 'medical_patient_images_migrate('), 'facility request never runs patient-image DDL');
$assert(!str_contains($apiSource, 'medical_patient_images_require_schema('), 'doctor endpoint bootstrap does not make optional column an unconditional 503 dependency');

echo "Patient image integration: {$checks} checks passed. No real DB, network or image files used.\n";
