<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_facility_identity.php';
require_once dirname(__DIR__) . '/toplist_directory.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};
$reject = static function (callable $run, string $label) use ($assert): void {
    try { $run(); }
    catch (InvalidArgumentException $e) { $assert(true, $label); return; }
    $assert(false, $label . ' should reject');
};
$dana = [
    'id' => 59, 'name' => 'Dana Dental', 'city' => 'Đà Nẵng',
    'address_text' => '56 Nguyễn Văn Linh, Phường Nam Dương, Quận Hải Châu, Đà Nẵng',
    'phone_text' => '0905123456', 'website_url' => 'https://danadental.example',
    'language_code' => 'vi', 'status' => 'published',
];
$input = array_replace($dana, ['name' => 'NHA KHOA DANA DENTAL',
    'address_text' => '56 đường Nguyễn Văn Linh, P. Nam Dương, Q. Hải Châu, TP. Đà Nẵng',
    'city' => 'TP Đà Nẵng']);
unset($input['id']);
foreach (['NHA KHOA DANA DENTAL', 'Nha khoa Dana Dental - Nha khoa uy tín tại Đà Nẵng',
    'nha khoa dana dental', 'Dana Dental Đà Nẵng'] as $name) {
    $match = medical_facility_identity_match(array_replace($input, ['name' => $name]), [$dana]);
    $assert($match['status'] === 'matched' && $match['id'] === 59,
        'Brand prefixes, casing and known SEO/city suffixes reuse the older facility: ' . $name);
}
$match = medical_facility_identity_match($input, [$dana]);
$assert($match['candidate_ids'] === [59] && is_string($match['reason']),
    'Successful facility identity reports the candidate and reason');
$assert(medical_facility_identity_name('Nha khoa Dana Dental', 'Đà Nẵng')
    === medical_facility_identity_name('Dana Dental', 'Đà Nẵng'),
    'Facility brand normalization removes the generic clinic category prefix');
$mySmile = array_replace($dana, ['id' => 58, 'name' => 'Nha khoa Mỹ Smile',
    'address_text' => '170 Nguyễn Văn Linh, Đà Nẵng']);
$match = medical_facility_identity_match(array_replace($mySmile, ['name' => 'Nha khoa My Smile',
    'address_text' => '170 đường Nguyễn Văn Linh, TP Đà Nẵng']), [$mySmile]);
$assert($match['status'] === 'matched' && $match['id'] === 58,
    'Accent differences in the brand and address do not seed a duplicate');
$aliased = $input; $aliased['address'] = $aliased['address_text']; unset($aliased['address_text']);
$match = medical_facility_identity_match($aliased, [$dana]);
$assert($match['status'] === 'matched' && $match['id'] === 59,
    'The public Toplist address alias participates in identity matching');

foreach ([
    ['address_text' => '57 Nguyễn Văn Linh, Đà Nẵng', 'city' => 'Đà Nẵng'],
    ['address_text' => '56 Nguyễn Chí Thanh, Đà Nẵng', 'city' => 'Đà Nẵng'],
    ['address_text' => '56 Nguyễn Văn Linh, Huế', 'city' => 'Huế'],
] as $branch) {
    $match = medical_facility_identity_match(array_replace($input, $branch), [$dana]);
    $assert($match['status'] === 'none' && $match['id'] === null,
        'A clear different house/street/city is a distinct branch despite matching brand and shared contact');
}
$numberedStreet = array_replace($dana, ['address_text' => '12 Đường Số 2, Đà Nẵng']);
$match = medical_facility_identity_match(array_replace($input, ['address_text' => '12 Đường Số 22, Đà Nẵng']), [$numberedStreet]);
$assert($match['status'] === 'none' && $match['id'] === null,
    'Different numbered streets never match through fuzzy street similarity');
$house = array_replace($dana, ['address_text' => '129 Nguyễn Văn Linh, Đà Nẵng']);
foreach (['129/1 Nguyễn Văn Linh, Đà Nẵng', '129-1 Nguyễn Văn Linh, Đà Nẵng'] as $differentHouse) {
    $match = medical_facility_identity_match(array_replace($input, ['address_text' => $differentHouse]), [$house]);
    $assert($match['status'] === 'none' && $match['id'] === null,
        'Alley/compound house number is not flattened to the main street number: ' . $differentHouse);
}
$house = array_replace($dana, ['address_text' => '129/1 Nguyễn Văn Linh, Đà Nẵng']);
$match = medical_facility_identity_match(array_replace($input, ['address_text' => '129-1 Nguyễn Văn Linh, Đà Nẵng']), [$house]);
$assert($match['status'] === 'none' && $match['id'] === null,
    'Slash and hyphen house identifiers remain distinct branches');
$match = medical_facility_identity_match(array_replace($input,
    ['address_text' => '56 Nguyễn Văn Linh, Huế', 'city' => 'Đà Nẵng']), [$dana]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null && $match['reason'] === 'city_address_conflict',
    'Contradictory city inside an address is rejected rather than linked or seeded');
$match = medical_facility_identity_match(array_replace($input, ['address_text' => '56 Nguyễn Văn Linh']), [$dana]);
$assert($match['status'] === 'matched' && $match['id'] === 59,
    'A separately supplied city completes a house-and-street-only address');
$badCityRow = array_replace($dana, ['city' => 'Hà Nội']);
$match = medical_facility_identity_match($input, [$badCityRow]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'A stored city/address contradiction is reviewed before geographic branch skipping');
foreach (['Dana Dental - 129 Nguyễn Văn Linh', 'Dana Dental - Cơ sở 1'] as $branchName) {
    $match = medical_facility_identity_match(array_replace($input, ['name' => $branchName]), [$dana]);
    $assert($match['status'] === 'ambiguous' && $match['id'] === null,
        'An uncertain branch/address name suffix at the same physical address blocks a duplicate: ' . $branchName);
    $match = medical_facility_identity_match(array_replace($input,
        ['name' => $branchName, 'address_text' => '57 Nguyễn Văn Linh, Đà Nẵng']), [$dana]);
    $assert($match['status'] === 'none' && $match['id'] === null,
        'A name suffix does not prevent a clearly different physical branch from being considered new');
}
$match = medical_facility_identity_match(array_replace($input, ['name' => 'Dana Dental - Cơ sở 2']),
    [array_replace($dana, ['name' => 'Dana Dental - Cơ sở 1'])]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'Conflicting numbered branch names at the same address are not blindly linked or seeded');
$match = medical_facility_identity_match(['name' => 'Dana Dental', 'city' => 'Đà Nẵng'], [$dana]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'Same brand and city without branch address requires an explicit checked ID');
$match = medical_facility_identity_match(['name' => 'Dana Dentall', 'city' => 'Đà Nẵng'], [$dana]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'A near-identical brand without adequate branch evidence is not treated as a new facility');
$second = array_replace($dana, ['id' => 60]);
$match = medical_facility_identity_match($input, [$dana, $second]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'Indistinguishable existing database IDs require human disambiguation');
$ids = $match['candidate_ids']; sort($ids);
$assert($ids === [59, 60], 'Ambiguous facility response lists both existing IDs');
$match = medical_facility_identity_match($input, [$dana, $dana]);
$assert($match['status'] === 'matched' && $match['id'] === 59,
    'Repeated candidate rows with one database ID are not a false duplicate');
$match = medical_facility_identity_match($input, []);
$assert($match['status'] === 'none' && $match['id'] === null,
    'An empty facility candidate pool has no match');

foreach (['facility', 'mixed'] as $promptType) {
    $prompt = toplist_directory_research_prompt('Mẫu quản trị tùy chỉnh {{entity_type}} {{member_key}}',
        ['id' => 20, 'title' => 'Cơ sở gợi ý', 'entity_type' => $promptType]);
    $assert(str_contains($prompt, 'Mẫu quản trị tùy chỉnh')
        && str_contains($prompt, 'Cơ sở mới cần name và address')
        && str_contains($prompt, 'Chỉ gửi facility_id khi đã xác minh')
        && str_contains($prompt, 'Server đối chiếu tên và địa chỉ để dùng lại ID cũ'),
        'Custom ' . $promptType . ' prompts retain mandatory facility address and old-ID deduplication rules');
}
$doctorPrompt = toplist_directory_research_prompt('Mẫu bác sĩ tùy chỉnh',
    ['id' => 21, 'title' => 'Bác sĩ gợi ý', 'entity_type' => 'doctor'], true);
$assert(!str_contains($doctorPrompt, 'Cơ sở mới cần name và address'),
    'Doctor-only research prompts do not request the new-facility creation contract');

final class ToplistFacilityIdentitySqlite extends PDO
{
    public int $facilityCandidateSelects = 0;
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/\bFROM\s+medical_facilities\s+WHERE\s+language_code\s*=/', $query)) $this->facilityCandidateSelects++;
        return parent::prepare(preg_replace('/\s+FOR UPDATE\b/i', '', $query) ?? $query, $options);
    }
}
$pdo = new ToplistFacilityIdentitySqlite('sqlite::memory:', null, null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE medical_facilities (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT NOT NULL DEFAULT 'vi',
    name TEXT, city TEXT, address_text TEXT, phone_text TEXT, website_url TEXT, image_url TEXT,
    category TEXT, price_text TEXT, status TEXT, verified INTEGER NOT NULL DEFAULT 0,
    rating REAL NOT NULL DEFAULT 0, reviews_count INTEGER NOT NULL DEFAULT 0, content TEXT, full_json TEXT)");
$pdo->exec("CREATE TABLE medical_doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT NOT NULL DEFAULT 'vi',
    name TEXT, specialty_text TEXT, city TEXT, facility_name TEXT, address_text TEXT, status TEXT)");
$pdo->exec("CREATE TABLE medical_toplists (
    id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, slug TEXT UNIQUE, entity_type TEXT,
    language_code TEXT NOT NULL DEFAULT 'vi', excerpt TEXT, content TEXT, featured_image_url TEXT,
    status TEXT, updated_at TEXT)");
$pdo->exec('CREATE TABLE medical_toplist_doctors (toplist_id INTEGER, doctor_id INTEGER, rank_order INTEGER, UNIQUE(toplist_id,doctor_id))');
$pdo->exec('CREATE TABLE medical_toplist_facilities (toplist_id INTEGER, facility_id INTEGER, rank_order INTEGER, UNIQUE(toplist_id,facility_id))');
$insert = $pdo->prepare('INSERT INTO medical_facilities
    (id,slug,language_code,name,city,address_text,phone_text,website_url,status,verified,rating,reviews_count,content)
    VALUES (:id,:slug,:language_code,:name,:city,:address_text,:phone_text,:website_url,:status,:verified,:rating,:reviews_count,:content)');
$saved = array_replace($dana, ['slug' => 'dana-dental', 'verified' => 1, 'rating' => 4.8,
    'reviews_count' => 7, 'content' => '<p>Nội dung hồ sơ cũ phải được giữ nguyên.</p>']);
$insert->execute($saved);
$before = $pdo->query('SELECT * FROM medical_facilities WHERE id=59')->fetch();
$resolved = toplist_directory_resolve_member($pdo, 'facility', $input);
$assert($resolved['id'] === 59 && $resolved['created'] === false,
    'Actual Toplist resolver links the original facility despite display-name/address variations');
$assert($pdo->query('SELECT * FROM medical_facilities WHERE id=59')->fetch() === $before,
    'Facility linking does not overwrite the original profile content, rating or verification');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === 1,
    'Matching the original branch does not create another row');
$explicit = toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => 59,
    'name' => 'Nha khoa Dana Dental - Nha khoa uy tín tại Đà Nẵng']);
$assert($explicit['id'] === 59 && !$explicit['created'],
    'An explicit verified facility ID permits only normalized brand-name variation');
$explicitWithAddress = toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => 59] + $input);
$assert($explicitWithAddress['id'] === 59 && !$explicitWithAddress['created'],
    'An explicit facility ID accepts verified ward/street-prefix address variants');
foreach ([
    ['address' => '57 Nguyễn Văn Linh, Đà Nẵng', 'city' => 'Đà Nẵng'],
    ['address' => '56 Nguyễn Chí Thanh, Đà Nẵng', 'city' => 'Đà Nẵng'],
    ['address' => '56 Nguyễn Văn Linh, Huế', 'city' => 'Huế'],
] as $wrongBranch) {
    $reject(fn() => toplist_directory_resolve_member($pdo, 'facility',
        ['facility_id' => 59, 'name' => 'Dana Dental'] + $wrongBranch),
        'An explicit same-brand ID cannot override contradictory physical house, street or city');
}
$partial = toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => 59,
    'name' => 'Dana Dental', 'address' => 'Nguyễn Văn Linh']);
$assert($partial['id'] === 59 && !$partial['created'],
    'An existing checked ID does not require inventing a complete address from partial context');
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => 59, 'name' => 'My Smile']),
    'An explicit ID cannot claim a different brand');
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', ['name' => 'Dana Dental', 'city' => 'Đà Nẵng']),
    'A same-brand suggestion missing its address cannot seed a duplicate');
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', ['name' => 'Hoàn Toàn Mới', 'city' => 'Đà Nẵng']),
    'A new facility also requires a usable branch address');
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', array_replace($input,
    ['address_text' => '56 Nguyễn Văn Linh, Huế', 'city' => 'Đà Nẵng'])),
    'Actual resolver rejects contradictory address/city without touching the DB');
$pdo->exec("UPDATE medical_facilities SET city='Hà Nội' WHERE id=59");
$conflictCount = (int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn();
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', $input),
    'Actual resolver blocks a duplicate when the existing row city disagrees with its physical address');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === $conflictCount,
    'An inconsistent stored city never causes a new facility insertion');
$pdo->exec("UPDATE medical_facilities SET city='Đà Nẵng' WHERE id=59");
foreach (['Dana Dental - 129 Nguyễn Văn Linh', 'Dana Dental - Cơ sở 1'] as $branchName) {
    $reject(fn() => toplist_directory_resolve_member($pdo, 'facility', array_replace($input, ['name' => $branchName])),
        'Actual resolver blocks an unresolved same-address branch suffix instead of creating another row');
}
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === $conflictCount,
    'Uncertain branch suffixes leave the profile count unchanged');
$branch = array_replace($input, ['address_text' => '57 Nguyễn Văn Linh, Đà Nẵng']);
$new = toplist_directory_resolve_member($pdo, 'facility', $branch);
$assert($new['created'] && $new['id'] !== 59,
    'A genuine branch at another house number may create a separate facility');
$again = toplist_directory_resolve_member($pdo, 'facility', array_replace($branch, ['name' => 'Dana Dental']));
$assert(!$again['created'] && $again['id'] === $new['id'],
    'The newly created branch is itself reused by the next suggestion');
$newRow = $pdo->query('SELECT * FROM medical_facilities WHERE id=' . (int) $new['id'])->fetch();
$assert((int) $newRow['verified'] === 0 && (float) $newRow['rating'] === 0.0,
    'New facility seeds cannot invent verification or ratings');

$draft = array_replace($saved, ['id' => 100, 'slug' => 'dana-draft', 'name' => 'Dana Hòa Khánh',
    'address_text' => '150 Tôn Đức Thắng, Đà Nẵng', 'status' => 'draft']);
$insert->execute($draft);
$beforeCount = (int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn();
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', ['name' => 'Nha khoa Dana Hòa Khánh',
    'city' => 'Đà Nẵng', 'address' => '150 Tôn Đức Thắng, Đà Nẵng']),
    'A matching draft blocks a second published copy');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === $beforeCount,
    'Draft conflict never inserts another facility');

$english = array_replace($saved, ['id' => 101, 'slug' => 'dana-dental-en', 'language_code' => 'en']);
$insert->execute($english);
$en = toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => 101, 'name' => 'Dana Dental'], 'en');
$assert($en['id'] === 101 && !$en['created'], 'English Toplists may link an existing published English ID');
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => 59, 'name' => 'Dana Dental'], 'en'),
    'An English Toplist cannot link a Vietnamese profile ID');
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', $input, 'en'),
    'An English suggestion without explicit ID cannot fabricate a translated profile');
$vi = toplist_directory_resolve_member($pdo, 'facility', $input);
$assert($vi['id'] === 59, 'Vietnamese matching does not become ambiguous due to an English counterpart');

$missingAddress = array_replace($saved, ['id' => 200, 'slug' => 'dana-old-missing-address',
    'name' => 'DanaOldMissingAddress', 'address_text' => '']);
$insert->execute($missingAddress);
$missingAddressInput = ['name' => 'DanaOldMissingAddress', 'city' => 'Đà Nẵng',
    'address' => '320 Nguyễn Văn Linh, Đà Nẵng'];
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', $missingAddressInput),
    'An old same-brand profile missing its address still blocks automatic new-profile insertion');
$oldMissingBefore = $pdo->query('SELECT * FROM medical_facilities WHERE id=200')->fetch();
$checkedMissing = toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => 200] + $missingAddressInput);
$assert($checkedMissing['id'] === 200 && !$checkedMissing['created'],
    'A checked existing ID can resolve an old profile whose saved address is unknown');
$assert($pdo->query('SELECT * FROM medical_facilities WHERE id=200')->fetch() === $oldMissingBefore,
    'Linking a checked ID does not backfill or overwrite the old unknown address');
$reject(fn() => toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => 200,
    'name' => 'DanaOldMissingAddress', 'city' => 'Huế', 'address' => '320 Nguyễn Văn Linh, Huế']),
    'Even an old missing-address profile cannot accept an explicit conflicting city');

$pdo->exec("INSERT INTO medical_toplists(id,title,slug,entity_type,status)
    VALUES (1,'Danh sách cơ sở thử','danh-sach-co-so-thu','facility','draft')");
$newClinic = ['name' => 'Nha khoa Bình An', 'city' => 'Đà Nẵng', 'address' => '200 Nguyễn Chí Thanh, Đà Nẵng'];
$newOther = ['name' => 'Nha khoa Minh Tâm', 'city' => 'Đà Nẵng', 'address' => '240 Nguyễn Chí Thanh, Đà Nẵng'];
$pdo->facilityCandidateSelects = 0;
$pdo->beginTransaction();
$batch = toplist_directory_import_members($pdo, 1, 'facility', [
    $newClinic + ['rank_order' => 4], ['facility_id' => 59, 'rank_order' => 3],
    array_replace($newClinic, ['name' => 'Bình An', 'rank_order' => 2]),
    $newOther + ['rank_order' => 1],
]);
$assert($pdo->facilityCandidateSelects === 1,
    'All facility suggestions in a Toplist payload load candidates once');
$assert(count($batch['created_facility_ids']) === 2 && count($batch['ids']) === 3,
    'A repeated new clinic inside one JSON payload creates once and links once');
$clinicId = (int) $pdo->query("SELECT id FROM medical_facilities WHERE name='Nha khoa Bình An'")->fetchColumn();
$otherId = (int) $pdo->query("SELECT id FROM medical_facilities WHERE name='Nha khoa Minh Tâm'")->fetchColumn();
$links = $pdo->query('SELECT facility_id,rank_order FROM medical_toplist_facilities WHERE toplist_id=1 ORDER BY rank_order')->fetchAll();
$assert($batch['ids'] === [$otherId, $clinicId, 59]
    && array_map('intval', array_column($links, 'facility_id')) === $batch['ids']
    && array_map('intval', array_column($links, 'rank_order')) === [1, 2, 3],
    'Facility identity deduplication keeps requested ordering with contiguous relation ranks');
$assert($pdo->query('SELECT * FROM medical_facilities WHERE id=59')->fetch() === $before,
    'Batch imports still do not edit an existing facility profile');
$pdo->rollBack();

$beforeFacilities = (int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn();
$beforeArticles = (int) $pdo->query('SELECT COUNT(*) FROM medical_toplists')->fetchColumn();
$reject(fn() => toplist_directory_import_article($pdo, ['title' => 'Không được lưu dở', 'entity_type' => 'facility',
    'facilities' => [$newClinic, ['facility_id' => 999999]]]),
    'A later invalid member rejects the complete facility article import');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === $beforeFacilities
    && (int) $pdo->query('SELECT COUNT(*) FROM medical_toplists')->fetchColumn() === $beforeArticles
    && !$pdo->inTransaction(),
    'Failed facility import rolls back seeded clinics and the article with no open transaction');

echo "Toplist facility identity: {$checks} checks passed; database tests use in-memory SQLite only.\n";
