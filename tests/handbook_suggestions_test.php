<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_handbook_suggestions.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$assert(handbook_suggested_entities_resolve($pdo, [], []) === ['facilities' => [], 'doctors' => []],
    'Empty suggestions do not need profile tables or a transaction');
$pdo->exec("CREATE TABLE medical_facilities (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT, name TEXT,
    address_text TEXT, city TEXT, category TEXT, phone_text TEXT, website_url TEXT,
    full_json TEXT, status TEXT, verified INTEGER, content TEXT)");
$pdo->exec("CREATE TABLE medical_doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT, name TEXT,
    specialty_text TEXT, facility_name TEXT, address_text TEXT, city TEXT, website_url TEXT,
    full_json TEXT, status TEXT, verified INTEGER, content TEXT)");
$pdo->exec("INSERT INTO medical_facilities(id,slug,language_code,name,address_text,city,category,status,verified)
    VALUES (1,'phong-kham-an-binh','vi','Phòng khám An Bình','95 Nguyễn Lương Bằng, Hải Phòng','Hải Phòng','Phòng khám','published',1)");
$pdo->exec("INSERT INTO medical_doctors(id,slug,language_code,name,specialty_text,facility_name,address_text,city,status,verified)
    VALUES (1,'nguyen-van-huy','vi','BS. Nguyễn Văn Huy','Nhãn khoa','Phòng khám An Bình',
    '95 Nguyễn Lương Bằng, Hải Phòng','Hải Phòng','published',1)");
$facility = ['name' => 'Phòng khám An Bình', 'address' => '95 Nguyễn Lương Băng, Hải Phòng',
    'city' => 'Hải Phòng', 'evidence_url' => 'https://example.vn/clinic'];
$doctor = ['name' => 'Nguyễn Văn Huy', 'specialty' => 'Nhãn khoa', 'facility_name' => 'Phòng khám An Bình',
    'address' => '95 Nguyễn Lương Bằng, Hải Phòng', 'city' => 'Hải Phòng',
    'evidence_url' => 'https://example.vn/doctor'];
try { handbook_suggested_entities_resolve($pdo, [$facility], []); $assert(false, 'Missing transaction rejected'); }
catch (LogicException $e) { $assert(true, 'Missing transaction rejected'); }
$pdo->beginTransaction();
$resolved = handbook_suggested_entities_resolve($pdo, [$facility], [$doctor]);
$assert($resolved['facilities'][0]['facility_id'] === 1 && $resolved['facilities'][0]['resolution'] === 'matched',
    'Ninety-percent facility address typo reuses existing profile');
$assert($resolved['doctors'][0]['doctor_id'] === 1 && $resolved['doctors'][0]['resolution'] === 'matched',
    'Doctor title prefix, specialty and workplace resolve to existing ID');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === 1
    && (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === 1,
    'Matches do not duplicate profiles');

$branch = array_replace($facility, ['address' => '96 Nguyễn Lương Bằng, Hải Phòng']);
$created = handbook_suggested_entities_resolve($pdo, [$branch], []);
$facilityId = $created['facilities'][0]['facility_id'];
$assert($created['facilities'][0]['resolution'] === 'created' && $facilityId > 1,
    'Different street number creates a distinct facility');
$facilityRow = $pdo->query('SELECT * FROM medical_facilities WHERE id=' . (int) $facilityId)->fetch(PDO::FETCH_ASSOC);
$assert($facilityRow['status'] === 'published' && (int) $facilityRow['verified'] === 0 && $facilityRow['content'] === null,
    'Suggested facility is unverified and enters the existing writing queue');
$assert(json_decode($facilityRow['full_json'], true)['evidence_url'] === $branch['evidence_url'],
    'Public evidence remains in facility provenance');
$duplicate = handbook_suggested_entities_resolve($pdo, [$branch, $branch], []);
$assert($duplicate['facilities'][0]['facility_id'] === $facilityId
    && $duplicate['facilities'][1]['facility_id'] === $facilityId,
    'Repeated suggestions reuse an existing newly seeded facility');

$newDoctor = array_replace($doctor, ['name' => 'Nguyễn Thị Minh Châu', 'specialty' => 'Nhãn khoa trẻ em',
    'facility_name' => 'Phòng khám Mắt Mới']);
$created = handbook_suggested_entities_resolve($pdo, [], [$newDoctor]);
$doctorId = $created['doctors'][0]['doctor_id'];
$doctorRow = $pdo->query('SELECT * FROM medical_doctors WHERE id=' . (int) $doctorId)->fetch(PDO::FETCH_ASSOC);
$assert($created['doctors'][0]['resolution'] === 'created' && $doctorRow['status'] === 'published'
    && (int) $doctorRow['verified'] === 0 && $doctorRow['content'] === null,
    'Missing doctor is created unverified and enters the existing writing queue');
$assert(json_decode($doctorRow['full_json'], true)['origin'] === 'handbook_suggestion',
    'Doctor provenance identifies the handbook pipeline');
$duplicateDoctor = handbook_suggested_entities_resolve($pdo, [], [$newDoctor]);
$assert($duplicateDoctor['doctors'][0]['doctor_id'] === $doctorId
    && $duplicateDoctor['doctors'][0]['resolution'] === 'matched',
    'Repeat doctor suggestion reuses a seeded record');

$withoutEvidence = array_replace($branch, ['address' => '97 Nguyễn Lương Bằng, Hải Phòng', 'evidence_url' => '']);
$unresolved = handbook_suggested_entities_resolve($pdo, [$withoutEvidence], []);
$assert($unresolved['facilities'][0]['resolution'] === 'unresolved'
    && $unresolved['facilities'][0]['facility_id'] === null,
    'An uncited AI facility cannot create a record');
$incompleteDoctor = array_replace($newDoctor, ['name' => 'Bác sĩ Khác', 'specialty' => '', 'facility_name' => '', 'address' => '']);
$unresolved = handbook_suggested_entities_resolve($pdo, [], [$incompleteDoctor]);
$assert($unresolved['doctors'][0]['resolution'] === 'unresolved',
    'A doctor without specialty/workplace is left for review');

$wrongId = array_replace($facility, ['facility_id' => 1, 'address' => '98 Nguyễn Lương Bằng, Hải Phòng']);
$unresolved = handbook_suggested_entities_resolve($pdo, [$wrongId], []);
$assert($unresolved['facilities'][0]['resolution'] === 'unresolved'
    && $unresolved['facilities'][0]['resolution_reason'] === 'id_mismatch',
    'An explicit ID cannot override conflicting branch address');
$wrongDoctorId = array_replace($doctor, ['doctor_id' => 1, 'name' => 'Nguyễn Văn Quân']);
$unresolved = handbook_suggested_entities_resolve($pdo, [], [$wrongDoctorId]);
$assert($unresolved['doctors'][0]['resolution'] === 'unresolved',
    'An explicit doctor ID cannot override a different name');
$sameClinicDifferentGivenName = array_replace($doctor, ['name' => 'Nguyễn Văn Huyt']);
$unresolved = handbook_suggested_entities_resolve($pdo, [], [$sameClinicDifferentGivenName]);
$assert($unresolved['doctors'][0]['resolution'] === 'unresolved',
    'A short different given name is never treated as a typo for another doctor');
$pdo->commit();

$pdo->exec("INSERT INTO medical_doctors(slug,language_code,name,specialty_text,facility_name,address_text,city,status,verified)
    VALUES ('same-doctor-2','vi','Nguyễn Văn Huy','Nhãn khoa','Phòng khám An Bình',
    '95 Nguyễn Lương Bằng, Hải Phòng','Hải Phòng','draft',0)");
$pdo->beginTransaction();
$ambiguous = handbook_suggested_entities_resolve($pdo, [], [$doctor]);
$assert($ambiguous['doctors'][0]['resolution'] === 'unresolved'
    && $ambiguous['doctors'][0]['resolution_reason'] === 'ambiguous_match',
    'Two near-identical doctor records are never selected arbitrarily');
$pdo->rollBack();

echo "Handbook suggestions: {$checks} checks passed.\n";
