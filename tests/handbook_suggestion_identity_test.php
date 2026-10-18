<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_handbook_suggestions.php';

$checks = 0;
$failures = [];
$assert = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) $failures[] = $label;
};
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE medical_facilities (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT, name TEXT,
    address_text TEXT, city TEXT, category TEXT, phone_text TEXT, website_url TEXT,
    full_json TEXT, status TEXT, verified INTEGER, content TEXT)');
$pdo->exec('CREATE TABLE medical_doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT, name TEXT,
    specialty_text TEXT, facility_name TEXT, address_text TEXT, city TEXT, website_url TEXT,
    full_json TEXT, status TEXT, verified INTEGER, content TEXT)');
$insertFacility = static function (array $row) use ($pdo): void {
    $row = array_replace(['language_code' => 'vi', 'city' => 'Đà Nẵng', 'category' => 'Nha khoa',
        'phone_text' => '', 'website_url' => '', 'status' => 'published', 'verified' => 0, 'content' => null], $row);
    $columns = array_keys($row);
    $stmt = $pdo->prepare('INSERT INTO medical_facilities (' . implode(',', $columns) . ') VALUES ('
        . implode(',', array_fill(0, count($columns), '?')) . ')');
    $stmt->execute(array_values($row));
};
$dana = ['name' => 'Dana Dental', 'address' => '129 Nguyễn Hoàng, Phường Hải Châu, Đà Nẵng',
    'city' => 'Đà Nẵng', 'category' => 'Nha khoa', 'phone' => '0788588588',
    'website' => 'https://nhakhoadana.com/', 'evidence_url' => 'https://nhakhoadana.com/gioi-thieu/'];
$smile = ['name' => 'Nha khoa My Smile', 'address' => '85 Điện Biên Phủ, Chính Gián, Đà Nẵng',
    'city' => 'Đà Nẵng', 'category' => 'Nha khoa', 'phone' => '08 8888 4418',
    'website' => 'https://mysmileclinic.vn/', 'evidence_url' => 'https://mysmileclinic.vn/gioi-thieu/'];
foreach ([
    ['id' => 59, 'slug' => 'dana-dental-original', 'name' => 'Dana Dental - Nha Khoa Đà Nẵng',
        'address_text' => '129 Nguyễn Hoàng, Hải Châu, Đà Nẵng', 'phone_text' => '0788588588',
        'website_url' => 'https://nhakhoadana.com', 'content' => '<p>Hồ sơ đầy đủ</p>'],
    ['id' => 1948, 'slug' => 'dana-dental-seed', 'name' => 'Dana Dental',
        'address_text' => $dana['address'], 'website_url' => 'https://nhakhoadana.com/'],
    ['id' => 58, 'slug' => 'my-smile-original', 'name' => 'Nha khoa My Smile',
        'address_text' => '85 Điện Biên Phủ, Thanh Khê, Đà Nẵng', 'phone_text' => '0888 884 418',
        'website_url' => 'https://mysmileclinic.vn', 'content' => '<p>Hồ sơ đầy đủ</p>'],
    ['id' => 94, 'slug' => 'my-smile-written-duplicate', 'name' => 'My Smile',
        'address_text' => '85 Điện Biên Phủ, Phường Chính Gián, Quận Thanh Khê, Đà Nẵng',
        'phone_text' => '+84 888 884 418', 'website_url' => 'https://www.mysmileclinic.vn/',
        'content' => '<p>Hồ sơ đã viết</p>'],
    ['id' => 1949, 'slug' => 'my-smile-seed-1', 'name' => 'Nha Khoa My Smile',
        'address_text' => $smile['address'], 'website_url' => 'https://mysmileclinic.vn'],
    ['id' => 1954, 'slug' => 'my-smile-seed-2', 'name' => 'My Smile',
        'address_text' => '85 Điện Biên Phủ, Chính Gián, Thanh Khê, Đà Nẵng', 'phone_text' => '08 8888 4418'],
] as $row) $insertFacility($row);

$resolve = static function (array $facilities = [], array $doctors = [], string $locale = 'vi') use ($pdo): array {
    $pdo->beginTransaction();
    try {
        return handbook_suggested_entities_resolve($pdo, $facilities, $doctors, $locale);
    } finally {
        $pdo->rollBack();
    }
};
$originalCount = (int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn();
$pdo->beginTransaction();
$resolved = handbook_suggested_entities_resolve($pdo, [$dana, $smile], []);
$assert($resolved['facilities'][0]['facility_id'] === 59 && $resolved['facilities'][0]['resolution'] === 'matched',
    'Dana Dental SEO suffix and changed ward reuse written original 59 instead of seed 1948');
$assert($resolved['facilities'][1]['facility_id'] === 58 && $resolved['facilities'][1]['resolution'] === 'matched',
    'My Smile written duplicates and seeds reuse oldest written original 58');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === $originalCount,
    'Resolving real-world duplicate fixtures inserts no additional facility');
$pdo->rollBack();

foreach (['Dana Dental', 'Nha Khoa Dana Dental', 'Dana Dental - Nha khoa uy tín tại Đà Nẵng'] as $name) {
    $resolved = $resolve([array_replace($dana, ['name' => $name])])['facilities'][0];
    $assert($resolved['facility_id'] === 59 && $resolved['resolution'] === 'matched',
        'Generic prefix and city SEO suffix preserve Dana Dental physical identity: ' . $name);
}
foreach (['130 Nguyễn Hoàng, Hải Châu, Đà Nẵng', '129A Nguyễn Hoàng, Hải Châu, Đà Nẵng',
    '129/1 Nguyễn Hoàng, Hải Châu, Đà Nẵng', '129-1 Nguyễn Hoàng, Hải Châu, Đà Nẵng',
    '129 Trần Phú, Hải Châu, Đà Nẵng'] as $address) {
    $resolved = $resolve([array_replace($dana, ['address' => $address])])['facilities'][0];
    $assert(!in_array($resolved['facility_id'], [59, 1948], true),
        'Same brand/contact never merges a different physical branch: ' . $address);
}
$assert(handbook_suggestion_address_score('129/1 Nguyễn Hoàng, Đà Nẵng', '129-1 Nguyễn Hoàng, Đà Nẵng', 'Đà Nẵng', 'Đà Nẵng') === 0.0,
    'Slash house number is never normalized into a dash range');
$assert(handbook_suggestion_address_score('10 Đường 2/9, Đà Nẵng', '10 Đường 3/9, Đà Nẵng', 'Đà Nẵng', 'Đà Nẵng') === 0.0,
    'Ward tolerance never discards numbered street identity');
$assert(handbook_suggestion_address_score('85 Nguyễn Văn Linh, Phường Thanh Khê Đông, Quận Thanh Khê, Đà Nẵng',
    '85 Nguyễn Văn Thoại, Phường Thanh Khê Đông, Quận Thanh Khê, Đà Nẵng', 'Đà Nẵng', 'Đà Nẵng') === 0.0,
    'Long identical ward and district cannot outweigh two different streets');
$assert(handbook_suggestion_address_score('85 Điện Biên Phủ, Phường 1, Đà Nẵng',
    '85 Điện Biên Phủ, Phường 2, Đà Nẵng', 'Đà Nẵng', 'Đà Nẵng') === 1.0,
    'Changed ward numbers do not create a new branch when house, street and city agree');
$assert(!handbook_suggestion_shared_contact(['website' => 'https://facebook.com/clinic-a'],
    ['website_url' => 'https://www.facebook.com/clinic-b']), 'Different social pages cannot corroborate duplicates on a shared domain');
$assert(!handbook_suggestion_shared_contact(['website' => 'https://facebook.com/'],
    ['website_url' => 'https://facebook.com/']), 'A bare shared platform domain proves no business identity');
$assert(handbook_suggestion_shared_contact(['website' => 'https://facebook.com/myclinic'],
    ['website_url' => 'https://www.facebook.com/myclinic/']), 'The same explicit social page can corroborate a matched branch');
$assert(handbook_suggestion_shared_contact(['phone' => '+84 888 884 418'], ['phone_text' => '08 8888 4418']),
    'Phone formatting and Vietnamese country prefix do not hide a contact match');
$resolved = $resolve([array_replace($dana, ['address' => '129 Nguyễn Hoàng, Hải Phòng', 'city' => 'Hải Phòng'])])['facilities'][0];
$assert(!in_array($resolved['facility_id'], [59, 1948], true), 'Same street and brand in another city do not match');

$insertFacility(['id' => 2001, 'slug' => 'uncertain-clinic-1', 'name' => 'Nha khoa Minh An',
    'address_text' => '88 Trần Phú, Hải Châu, Đà Nẵng', 'website_url' => 'https://minhan-one.example/',
    'phone_text' => '0901111111', 'content' => '<p>Written</p>']);
$insertFacility(['id' => 2002, 'slug' => 'uncertain-clinic-2', 'name' => 'Minh An',
    'address_text' => '88 Trần Phú, Phường Hải Châu, Đà Nẵng', 'website_url' => 'https://minhan-two.example/',
    'phone_text' => '0902222222', 'content' => '<p>Written</p>']);
$pdo->beginTransaction();
$before = (int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn();
$resolved = handbook_suggested_entities_resolve($pdo, [[
    'name' => 'Nha khoa Minh An', 'address' => '88 Trần Phú, Đà Nẵng', 'city' => 'Đà Nẵng',
    'evidence_url' => 'https://minhan-one.example/about',
]], [])['facilities'][0];
$assert($resolved['resolution'] === 'unresolved' && $resolved['facility_id'] === null,
    'Conflicting contacts at otherwise matching branches stay unresolved');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === $before,
    'Ambiguous matching branches never seed a third duplicate');
$pdo->rollBack();

$insertFacility(['id' => 2003, 'slug' => 'incomplete-known-clinic', 'name' => 'Nha khoa Cũ',
    'address_text' => '', 'city' => 'Đà Nẵng']);
$resolved = $resolve([['name' => 'Nha khoa Cũ', 'address' => '12 Trần Phú, Đà Nẵng', 'city' => 'Đà Nẵng',
    'evidence_url' => 'https://known.example/about']])['facilities'][0];
$assert($resolved['resolution'] === 'unresolved', 'Existing same-brand/city with incomplete address blocks duplicate seeding');

$english = $resolve([$dana], [], 'en')['facilities'][0];
$assert($english['resolution'] === 'created' && !in_array($english['facility_id'], [59, 1948], true),
    'English suggestions cannot reuse Vietnamese facility records');
$insertFacility(['id' => 3001, 'slug' => 'dana-dental-en', 'language_code' => 'en', 'name' => 'Dana Dental',
    'address_text' => $dana['address'], 'website_url' => $dana['website'], 'content' => '<p>English profile</p>']);
$assert($resolve([$dana], [], 'en')['facilities'][0]['facility_id'] === 3001,
    'English suggestion reuses the corresponding English record');
$assert($resolve([$dana])['facilities'][0]['facility_id'] === 59,
    'English duplicate cannot displace the canonical Vietnamese record');

$pdo->exec("INSERT INTO medical_doctors(id,slug,language_code,name,specialty_text,facility_name,address_text,city,status,verified,content)
    VALUES (42,'tran-minh-an','vi','BS. Trần Minh An','Nhãn khoa','Phòng khám Mắt An Tâm',
    '12 Lê Duẩn, Hải Châu, Đà Nẵng','Đà Nẵng','published',0,'<p>Hồ sơ bác sĩ</p>')");
$doctor = ['name' => 'Trần Minh An', 'specialty' => 'Nhãn khoa', 'facility_name' => 'Mắt An Tâm',
    'address' => '12 Lê Duẩn, Phường Hải Châu, Đà Nẵng', 'city' => 'Đà Nẵng',
    'evidence_url' => 'https://antam.example/doctor'];
$resolved = $resolve([], [$doctor])['doctors'][0];
$assert($resolved['resolution'] === 'matched' && $resolved['doctor_id'] === 42,
    'Strong specialty and physical workplace evidence reuses doctor across generic prefixes and ward changes');
foreach (['BS.CKII. Trần Minh An', 'ThS.BS. Trần Minh An', 'Bác sĩ chuyên khoa II Trần Minh An'] as $name) {
    $resolved = $resolve([], [array_replace($doctor, ['name' => $name])])['doctors'][0];
    $assert($resolved['resolution'] === 'matched' && $resolved['doctor_id'] === 42,
        'Doctor degree/title formatting is not a new person: ' . $name);
}
foreach ([['specialty' => 'Nội khoa'], ['facility_name' => 'Phòng khám Mắt Khác'],
    ['specialty' => 'Tim mạch', 'facility_name' => 'Bệnh viện Mới', 'address' => '72 Trần Phú, Đà Nẵng']] as $change) {
    $pdo->beginTransaction();
    $before = (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn();
    $resolved = handbook_suggested_entities_resolve($pdo, [], [array_replace($doctor, $change)])['doctors'][0];
    $assert($resolved['resolution'] === 'unresolved' && $resolved['doctor_id'] === null,
        'Same full doctor name and city with conflicting specialty/workplace needs review');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === $before,
        'Changed doctor context never creates another same-name profile');
    $pdo->rollBack();
}
$resolved = $resolve([], [$doctor], 'en')['doctors'][0];
$assert($resolved['resolution'] === 'created' && $resolved['doctor_id'] !== 42,
    'Doctor matching is isolated by language');

if ($failures !== []) throw new RuntimeException(implode("\n", $failures));
echo "Handbook suggestion identity: {$checks} checks passed.\n";
