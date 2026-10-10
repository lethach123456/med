<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !(PHP_SAPI === 'cli-server' && defined('DOCTOR_LEGAL_PROFILE_FIXTURE_ONLY')
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true))) {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/medical_doctor_profile.php';

/** Synthetic presentation fixtures only. This test never opens or writes a database. */
function doctor_legal_profile_test_fixture(string $locale = 'vi', array $overrides = []): array
{
    $row = array_replace([
        'id' => 900002,
        'slug' => 'legal-profile-fixture',
        'language_code' => $locale,
        'name' => 'Bác sĩ Hồ Sơ Kiểm Thử',
        'title_text' => 'Public legal profile fixture',
        'content' => '<p>Synthetic profile information for presentation tests.</p>',
        'verified' => 1,
        'verification_status' => 'unreviewed',
        'education_json' => [['institution' => 'Synthetic medical university', 'degree' => 'MD', 'source_ids' => ['s1']]],
        'sources_json' => [
            ['id' => 's1', 'url' => 'https://hospital.example.org/doctors/test', 'title' => 'Published professional profile', 'publisher' => 'Synthetic hospital'],
            ['id' => 's2', 'url' => 'https://regulator.example.org/practitioners/test', 'title' => 'Public practitioner register', 'publisher' => 'Synthetic authority'],
        ],
        'professional_profile_url' => 'https://hospital.example.org/doctors/legal-fixture?lang=vi&view=profile',
        'practice_license_type' => 'Giấy phép hành nghề thử nghiệm',
        'practice_license_number' => 'LEGAL-TEST/123',
        'practice_license_issuer' => 'Synthetic licence authority',
        'practice_license_issued_date' => '2024-02-29',
        'practice_license_scope' => 'Synthetic scope of clinical practice',
        'practice_registry_url' => 'https://regulator.example.org/practitioners/legal-fixture',
        'practice_registration_json' => [[
            'facility_name' => 'Synthetic registered hospital',
            'department' => 'Synthetic eye department',
            'scope' => 'Synthetic registered clinical scope',
            'schedule_text' => 'Published registration: Monday 09:00–11:00',
            'source_ids' => ['s2'],
            'notes_for_editor' => 'PRIVATE_REGISTRATION_MARKER',
        ]],
        'legal_documents_json' => [[
            'document_type' => 'Published supporting document',
            'title' => 'Synthetic public legal document',
            'number' => 'PUBLIC-DOC/456',
            'issuer' => 'Synthetic document issuer',
            'issued_date' => '2025-03-01',
            'url' => 'https://regulator.example.org/documents/legal-fixture?format=html&public=1',
            'source_ids' => ['s2'],
            'notes_for_editor' => 'PRIVATE_DOCUMENT_MARKER',
        ]],
        'legal_notes' => 'Published legal details should be confirmed with the provider.',
        'legal_source_ids_json' => ['s1', 's2'],
        'practice_license_json' => null,
        'notes_for_editor' => 'PRIVATE_EDITOR_MARKER',
        'ai_writer_claim_json' => ['token' => 'PRIVATE_WORKER_MARKER'],
    ], $overrides);
    foreach ($row as $key => $value) {
        if (str_ends_with($key, '_json') && !is_string($value)) {
            $row[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    return medical_directory_doctor_from_row($row);
}

if (PHP_SAPI !== 'cli' || defined('DOCTOR_LEGAL_PROFILE_FIXTURE_ONLY')) return;

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$render = static function (array $doctor, string $doctorLanguage = 'vi'): string {
    $profile = medical_doctor_profile_model($doctor);
    $escape = static fn(mixed $value): string => htmlspecialchars(medical_doctor_profile_text($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $relatedDoctors = [];
    ob_start();
    try { require dirname(__DIR__) . '/Tem/doctor-profile.php'; return (string) ob_get_clean(); }
    catch (Throwable $error) { ob_end_clean(); throw $error; }
};
$parse = static function (string $html): DOMXPath {
    $document = new DOMDocument();
    $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    return new DOMXPath($document);
};
$nodes = static fn(DOMXPath $xpath, string $query): DOMNodeList => $xpath->query($query);
$legalSection = static function (DOMXPath $xpath): ?DOMNode {
    return $xpath->query('//section[@id="ho-so-phap-ly"]')->item(0);
};
$emptyDoctor = static function (array $overrides = []): array {
    return doctor_legal_profile_test_fixture('vi', array_replace([
        'education_json' => [],
        'professional_profile_url' => '',
        'practice_license_type' => '',
        'practice_license_number' => '',
        'practice_license_issuer' => '',
        'practice_license_issued_date' => null,
        'practice_license_scope' => '',
        'practice_registry_url' => '',
        'practice_registration_json' => [],
        'legal_documents_json' => [],
        'legal_notes' => '',
        'legal_source_ids_json' => [],
        'practice_license_json' => null,
    ], $overrides));
};

$doctor = doctor_legal_profile_test_fixture();
$model = medical_doctor_profile_model($doctor);
$legal = $model['legal'];
$assert($legal['has_content'] === true, 'new legal fields create a legal section');
$assert($legal['professional_profile_url'] === $doctor['professional_profile_url'], 'professional profile URL retained');
$assert($legal['practice_registry_url'] === $doctor['practice_registry_url'], 'registry URL retained');
$assert($legal['notes'] === $doctor['legal_notes'], 'public legal notes retained');
$assert($legal['source_ids'] === ['s1', 's2'], 'legal source IDs resolve to known sources');
foreach (['document_type' => 'practice_license_type', 'number' => 'practice_license_number',
    'issuer' => 'practice_license_issuer', 'scope' => 'practice_license_scope'] as $key => $column) {
    $assert($legal['license'][$key] === $doctor[$column], 'new scalar licence ' . $column);
}
$assert(count($legal['registrations']) === 1, 'one public practice registration');
$assert(count($legal['documents']) === 1, 'one public legal document');
$assert(array_diff(array_keys($legal['license']), ['document_type', 'number', 'issuer', 'issued_date', 'scope', 'source_ids']) === [], 'licence exposes only public fields');
$assert(array_diff(array_keys($legal['registrations'][0]), ['facility_name', 'department', 'scope', 'schedule_text', 'source_ids']) === [], 'registration exposes only public fields');
$assert(array_diff(array_keys($legal['documents'][0]), ['document_type', 'title', 'number', 'issuer', 'issued_date', 'url', 'source_ids']) === [], 'document exposes only public fields');

$html = $render($doctor);
$xpath = $parse($html);
$section = $legalSection($xpath);
$assert($section !== null, 'legal section rendered');
$assert($nodes($xpath, '//nav[contains(@class,"dp-nav")]/a[@href="#ho-so-phap-ly"]')->length === 1, 'legal section has one navigation link');
$assert(str_contains($section->textContent, 'Hồ sơ & pháp lý'), 'Vietnamese legal heading');
foreach (['Giấy phép hành nghề thử nghiệm', 'LEGAL-TEST/123', 'Synthetic licence authority',
    'Synthetic scope of clinical practice', 'Synthetic registered hospital', 'Synthetic eye department',
    'Synthetic registered clinical scope', 'Published registration: Monday 09:00–11:00',
    'Published supporting document', 'Synthetic public legal document', 'PUBLIC-DOC/456',
    'Synthetic document issuer', 'Published legal details should be confirmed with the provider.'] as $value) {
    $assert(str_contains($section->textContent, $value), 'legal public value rendered: ' . $value);
}
$assert(str_contains($section->textContent, '29/02/2024') || str_contains($section->textContent, '2024-02-29'), 'licence issue date rendered without inventing a date');
$assert(str_contains($section->textContent, '01/03/2025') || str_contains($section->textContent, '2025-03-01'), 'public document issue date rendered');
foreach ([$doctor['professional_profile_url'], $doctor['practice_registry_url'], $doctor['legal_documents_json'][0]['url']] as $url) {
    $links = $xpath->query('.//a[@href="' . $url . '"]', $section);
    $assert($links->length === 1, 'public legal URL rendered once: ' . $url);
    $link = $links->item(0);
    $assert($link->getAttribute('target') === '_blank' && str_contains($link->getAttribute('rel'), 'noopener')
        && str_contains($link->getAttribute('rel'), 'noreferrer'), 'external legal link has safe target/rel');
}
$assert($xpath->query('.//a[@href="#doctor-source-1"]', $section)->length > 0, 'section-level source 1 citation rendered');
$assert($xpath->query('.//a[@href="#doctor-source-2"]', $section)->length > 0, 'section and entry source 2 citations rendered');
$details = $xpath->query('.//details[contains(concat(" ",normalize-space(@class)," ")," dp-legal-document ")]', $section);
$assert($details->length === 1, 'public legal documents use native details');
$assert($xpath->query('./summary', $details->item(0))->length === 1, 'legal document details has a keyboard-operable summary');
$assert($xpath->query('.//a[@href="#doctor-source-2"]', $details->item(0))->length === 1, 'document retains its own source reference');
$assert(substr_count($html, 'LEGAL-TEST/123') === 1, 'licence number is not duplicated elsewhere');
$education = $xpath->query('//section[@id="dao-tao"]')->item(0);
$assert($education !== null && !str_contains($education->textContent, 'LEGAL-TEST/123'), 'licence is no longer under education');
$assert(!str_contains($html, 'Hồ sơ đã duyệt'), 'legal information never promotes unreviewed profile to reviewed');
foreach (['PRIVATE_EDITOR_MARKER', 'PRIVATE_WORKER_MARKER', 'PRIVATE_DOCUMENT_MARKER', 'PRIVATE_REGISTRATION_MARKER', 'notes_for_editor', 'ai_writer_claim_json'] as $value) {
    $assert(!str_contains($html, $value), 'private data not rendered: ' . $value);
}

$english = $render(doctor_legal_profile_test_fixture('en'), 'en');
$englishXPath = $parse($english);
$assert(str_contains($legalSection($englishXPath)->textContent, 'Profile & licensing'), 'English legal heading');
$assert($englishXPath->query('//nav[contains(@class,"dp-nav")]/a[@href="#ho-so-phap-ly" and text()="Profile & licensing"]')->length === 1, 'English legal navigation label');
$assert(!str_contains($english, 'Profile reviewed'), 'unreviewed English legal fixture stays unreviewed');

// Old records can provide only legacy JSON, including incomplete source dates.
foreach (['2024', '2024-02'] as $date) {
    $legacy = $emptyDoctor(['practice_license_json' => [
        'document_type' => 'Legacy published licence', 'number' => 'LEGACY/789',
        'issuer' => 'Legacy authority', 'issued_date' => $date, 'scope' => 'Legacy scope',
        'source_ids' => ['s2'], 'notes_for_editor' => 'PRIVATE_LEGACY_MARKER',
    ]]);
    $legacyLegal = medical_doctor_profile_model($legacy)['legal'];
    foreach (['document_type' => 'Legacy published licence', 'number' => 'LEGACY/789', 'issuer' => 'Legacy authority',
        'issued_date' => $date, 'scope' => 'Legacy scope'] as $key => $value) {
        $assert($legacyLegal['license'][$key] === $value, 'legacy fallback preserves ' . $key . ' with ' . $date);
    }
    $assert($legacyLegal['license']['source_ids'] === ['s2'], 'legacy licence source remains associated');
    $legacyHtml = $render($legacy);
    $assert(str_contains($legacyHtml, 'id="ho-so-phap-ly"') && str_contains($legacyHtml, $date), 'legacy-only licence section visible');
    $assert(!str_contains($legacyHtml, 'id="dao-tao"') && !str_contains($legacyHtml, 'href="#dao-tao"'), 'licence alone does not create an education section');
    $assert(!str_contains($legacyHtml, 'PRIVATE_LEGACY_MARKER'), 'legacy private licence field omitted');
    $preferred = medical_doctor_profile_model(array_replace($legacy, [
        'practice_license_number' => 'NEW/321', 'practice_license_issuer' => ' ', 'practice_license_issued_date' => '2024-02-29',
    ]))['legal']['license'];
    $assert($preferred['number'] === 'NEW/321', 'nonempty new licence scalar wins');
    $assert($preferred['issuer'] === 'Legacy authority', 'blank new licence scalar falls back to legacy value');
    $assert($preferred['issued_date'] !== $date, 'complete new issue date wins over partial legacy date');
}

// Source-only metadata and invalid actions must not create empty public sections.
$emptyVariants = [
    'empty fields' => $emptyDoctor(),
    'source IDs only' => $emptyDoctor(['legal_source_ids_json' => ['s1', 'unknown'], 'practice_license_json' => ['source_ids' => ['s2']]]),
    'unsafe URLs only' => $emptyDoctor(['professional_profile_url' => 'javascript:alert(1)', 'practice_registry_url' => 'data:text/html,test',
        'legal_documents_json' => [['url' => 'javascript:alert(2)', 'source_ids' => ['s1']]]]),
    'malformed JSON only' => $emptyDoctor(['practice_registration_json' => '{invalid', 'legal_documents_json' => 'not JSON', 'practice_license_json' => 'null']),
    'wrong JSON shapes only' => $emptyDoctor(['practice_registration_json' => ['facility_name' => 'Not a list'],
        'legal_documents_json' => [null, 'not an entry', ['source_ids' => ['s1']], ['title' => ['nested']], ['issuer' => new stdClass()]],
        'practice_license_json' => [['number' => 'Not a licence object']]]),
];
foreach ($emptyVariants as $label => $variant) {
    $emptyLegal = medical_doctor_profile_model($variant)['legal'];
    $assert($emptyLegal['has_content'] === false, 'empty legal model: ' . $label);
    $emptyHtml = $render($variant);
    $assert(!str_contains($emptyHtml, 'id="ho-so-phap-ly"') && !str_contains($emptyHtml, 'href="#ho-so-phap-ly"'), 'empty legal section and navigation omitted: ' . $label);
}

$malformed = array_replace($emptyDoctor(), [
    'professional_profile_url' => ['url' => 'https://example.org/not-a-scalar'],
    'practice_registry_url' => new stdClass(),
    'practice_license_number' => ['nested'],
    'practice_license_json' => ['number' => ['nested'], 'source_ids' => ['s1', ['s2']]],
    'practice_registration_json' => [false, 'bad', ['facility_name' => ['nested']], ['facility_name' => 'Valid registration', 'source_ids' => ['s2', 'unknown', ['s1'], 's2']]],
    'legal_documents_json' => [null, 'bad', ['title' => ['nested']], ['title' => 'Valid document', 'url' => 'javascript:alert(3)', 'source_ids' => ['s1', 'unknown', false, 's1']]],
    'legal_source_ids_json' => ['s1', 'unknown', ['s2'], false, 's1'],
]);
$malformedLegal = medical_doctor_profile_model($malformed)['legal'];
$assert($malformedLegal['professional_profile_url'] === '' && $malformedLegal['practice_registry_url'] === '', 'nonscalar legal URLs discarded');
$assert(count($malformedLegal['registrations']) === 1 && $malformedLegal['registrations'][0]['facility_name'] === 'Valid registration', 'malformed registrations skipped without losing a valid entry');
$assert(count($malformedLegal['documents']) === 1 && $malformedLegal['documents'][0]['title'] === 'Valid document', 'malformed documents skipped without losing a valid entry');
$assert($malformedLegal['documents'][0]['url'] === '', 'unsafe document URL removed while public metadata stays');
$assert($malformedLegal['source_ids'] === ['s1'], 'legal source references are known scalar IDs and deduplicated');
$assert($malformedLegal['registrations'][0]['source_ids'] === ['s2'], 'registration refs preserve known IDs only');
$assert($malformedLegal['documents'][0]['source_ids'] === ['s1'], 'document refs preserve known IDs only');
$malformedHtml = $render($malformed);
$assert(str_contains($malformedHtml, 'Valid registration') && str_contains($malformedHtml, 'Valid document'), 'valid records survive malformed input during rendering');
$assert(!str_contains($malformedHtml, 'javascript:') && !str_contains($malformedHtml, '#unknown'), 'invalid URLs and unknown source anchors not rendered');

// Treat all legal prose as plain text, even if a stale/imported row contains markup.
$attack = '<script>LEGAL_XSS_MARKER</script>';
$hostile = array_replace($doctor, [
    'legal_notes' => $attack,
    'practice_license_number' => $attack,
    'practice_registration_json' => [['facility_name' => 'Public facility ' . $attack, 'department' => $attack,
        'scope' => $attack, 'schedule_text' => $attack, 'source_ids' => ['s2']]],
    'legal_documents_json' => [['title' => 'Public document ' . $attack, 'document_type' => $attack,
        'number' => $attack, 'issuer' => $attack, 'url' => 'https://regulator.example.org/documents/safe?x=1&y=2', 'source_ids' => ['s2']]],
]);
$hostileHtml = $render($hostile);
$hostileXPath = $parse($hostileHtml);
$hostileSection = $legalSection($hostileXPath);
$assert($hostileSection !== null && str_contains($hostileHtml, '&lt;script&gt;LEGAL_XSS_MARKER&lt;/script&gt;'), 'legal text is escaped and still visible');
$assert($hostileXPath->query('.//script|.//iframe|.//*[@onclick or @onerror or @onload]', $hostileSection)->length === 0, 'legal markup cannot become active content');
$assert($hostileXPath->query('.//a[@href="https://regulator.example.org/documents/safe?x=1&y=2"]', $hostileSection)->length === 1, 'safe legal document query URL survives escaping');
$assert(!str_contains($hostileHtml, 'src=""') && !str_contains($hostileHtml, 'href="#"'), 'legal rendering creates no broken placeholder actions');

restore_error_handler();
echo "Doctor legal profile: {$checks} checks passed. No DB records modified.\n";
