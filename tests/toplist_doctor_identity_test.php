<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_doctor_identity.php';
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
$doctor = [
    'id' => 41, 'name' => 'Nguyễn Văn Huy', 'specialty_text' => 'Nhãn khoa',
    'city' => 'Hải Phòng', 'facility_name' => 'Phòng khám Mắt An Bình',
    'address_text' => '95 Nguyễn Lương Bằng, Phường Lê Thanh Nghị, Hải Phòng',
    'status' => 'published', 'language_code' => 'vi',
];
$input = array_replace($doctor, ['name' => 'BS. CKII. Nguyen Van Huy']);
unset($input['id']);
$match = medical_doctor_identity_match($input, [$doctor]);
$assert($match['status'] === 'matched' && $match['id'] === 41,
    'Honorifics and missing diacritics link the original doctor ID with supporting identity clues');
$assert(($match['candidate_ids'] ?? []) === [41] && is_string($match['reason'] ?? null),
    'Matcher reports the resolved candidate and a machine-readable explanation');
foreach (['BSCKII Nguyễn Văn Huy', 'BS. CK. II. Nguyễn Văn Huy', 'BSNT Nguyễn Văn Huy',
    'PGS.TS.BS. Nguyễn Văn Huy', 'Nguyễn Văn Huy (MD, PhD)', 'Nguyễn Văn Huy - Tiến sĩ, Bác sĩ'] as $variant) {
    $assert(medical_doctor_identity_name($variant) === medical_doctor_identity_name($doctor['name']),
        'Recognized professional title/credential normalizes: ' . $variant);
    $variantMatch = medical_doctor_identity_match(array_replace($input, ['name' => $variant]), [$doctor]);
    $assert($variantMatch['status'] === 'matched' && $variantMatch['id'] === 41,
        'Professional title/credential variant links the older doctor: ' . $variant);
}

$addressVariant = array_replace($input, [
    'address_text' => '95 đường Nguyễn Lương Bằng, P. Lê Thanh Nghị, TP. Hải Phòng',
    'city' => 'TP Hải Phòng',
]);
$match = medical_doctor_identity_match($addressVariant, [$doctor]);
$assert($match['status'] === 'matched' && $match['id'] === 41,
    'Ward, street and city prefixes do not create a duplicate doctor');
$aliases = $input;
$aliases['specialty'] = $aliases['specialty_text']; unset($aliases['specialty_text']);
$aliases['address'] = $aliases['address_text']; unset($aliases['address_text']);
$match = medical_doctor_identity_match($aliases, [$doctor]);
$assert($match['status'] === 'matched' && $match['id'] === 41,
    'Common JSON specialty and address aliases support the same identity check');

$match = medical_doctor_identity_match(['name' => 'Nguyễn Văn Huy'], [$doctor]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'An identical full name without corroboration is held for review, never treated as new');
foreach (['Nguyễn Huy', 'Văn Huy Nguyễn'] as $unsafeName) {
    $match = medical_doctor_identity_match(array_replace($input, ['name' => $unsafeName]), [$doctor]);
    $assert($match['status'] === 'ambiguous' && $match['id'] === null,
        'A shortened or reordered name is neither silently linked nor considered a new person: ' . $unsafeName);
}
$nearName = ['name' => 'Nguyễn Hoàng Minh Đức', 'city' => 'Hải Phòng', 'specialty_text' => 'Nhãn khoa'];
$nearCandidate = ['id' => 73, 'name' => 'Nguyễn Hoành Minh Đức', 'city' => 'Hải Phòng',
    'specialty_text' => 'Nhãn khoa', 'status' => 'published'];
$match = medical_doctor_identity_match($nearName, [$nearCandidate]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'Similar middle names with only city and specialty require strong workplace/address evidence');
$conflict = array_replace($input, ['specialty_text' => 'Tim mạch', 'facility_name' => 'Bệnh viện Tim Mới',
    'address_text' => '500 Lạch Tray, Hải Phòng']);
$match = medical_doctor_identity_match($conflict, [$doctor]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'Same full name in the same city with conflicting specialty and workplace cannot create a duplicate');

$match = medical_doctor_identity_match(array_replace($input, ['name' => 'Nguyễn Văn Huyt']), [$doctor]);
$assert($match['status'] !== 'matched' && $match['id'] === null,
    'A different short given name Huyt is not an approximate match for Huy');
$longName = array_replace($doctor, ['id' => 72, 'name' => 'Nguyễn Thị Phương Linh']);
$match = medical_doctor_identity_match(array_replace($longName, ['name' => 'Nguyễn Thị Phươngg Linh']), [$longName]);
$assert($match['status'] === 'matched' && $match['id'] === 72,
    'A minor typo in a long full name can match when all strong context agrees');
$longAmbiguous = array_replace($doctor, ['id' => 74, 'name' => 'Nguyễn Hoàng Minh Thanh Anh An Đức']);
foreach (['Nguyễn Hoàng Minh Thanh An Anh Đức', 'Nguyễn Hoàng Minh Thanh Anh Đức'] as $unsafeName) {
    $match = medical_doctor_identity_match(array_replace($longAmbiguous, ['name' => $unsafeName]), [$longAmbiguous]);
    $assert($match['status'] === 'ambiguous' && $match['id'] === null,
        'Reordered or omitted name tokens remain ambiguous even above the fuzzy threshold');
}

$second = array_replace($doctor, ['id' => 42]);
$match = medical_doctor_identity_match($input, [$doctor, $second]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'Two plausible doctors are never selected by row order');
$candidateIds = $match['candidate_ids']; sort($candidateIds);
$assert($candidateIds === [41, 42], 'Ambiguity reports both candidate IDs');
$match = medical_doctor_identity_match($input, [$doctor, $doctor]);
$assert($match['status'] === 'matched' && $match['id'] === 41,
    'Repeated copies of the same database ID do not create false ambiguity');
$match = medical_doctor_identity_match($input, [$doctor, array_replace($second, ['status' => 'draft'])]);
$assert($match['status'] === 'ambiguous' && $match['id'] === null,
    'A draft and published profile with indistinguishable identity fail closed');
$match = medical_doctor_identity_match(array_replace($input, ['name' => 'Trần Minh Quân']), [$doctor]);
$assert($match['status'] === 'none' && $match['id'] === null && $match['candidate_ids'] === [],
    'A genuinely different full name is eligible for new-profile validation');
$match = medical_doctor_identity_match($input, []);
$assert($match['status'] === 'none' && $match['id'] === null,
    'An empty candidate pool has no match');

foreach (['doctor', 'mixed'] as $promptType) {
    $customPrompt = toplist_directory_research_prompt('Nội dung riêng do admin sửa {{entity_type}} {{member_key}}',
        ['id' => 19, 'title' => 'Danh sách thử', 'entity_type' => $promptType], $promptType === 'doctor');
    $assert(str_contains($customPrompt, 'Nội dung riêng do admin sửa')
        && str_contains($customPrompt, 'Server đối chiếu hồ sơ cũ trước khi tạo')
        && str_contains($customPrompt, 'không tạo bản trùng'),
        'Custom ' . $promptType . ' prompts still include mandatory old-profile identity rules');
}
$mixedOutput = toplist_directory_output_template(['id' => 19, 'entity_type' => 'mixed']);
$mixedDoctor = array_values(array_filter($mixedOutput['members'], static fn(array $row): bool => $row['type'] === 'doctor'))[0] ?? [];
$assert(array_key_exists('address', $mixedDoctor) && array_key_exists('facility_name', $mixedDoctor)
    && array_key_exists('specialty_text', $mixedDoctor),
    'Mixed doctor JSON template requests address, workplace and specialty for identity checks');

// In-memory SQLite only: importing this test never opens the configured website database.
final class ToplistDoctorIdentitySqlite extends PDO
{
    public int $candidateSelects = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/\bFROM\s+medical_doctors\s+WHERE\s+language_code\s*=/', $query)) $this->candidateSelects++;
        // Preserve real statements/transactions; SQLite alone does not support MySQL row-lock syntax.
        return parent::prepare(preg_replace('/\s+FOR UPDATE\b/i', '', $query) ?? $query, $options);
    }
}
$pdo = new ToplistDoctorIdentitySqlite('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE medical_doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT NOT NULL DEFAULT 'vi',
    name TEXT, specialty_text TEXT, city TEXT, facility_name TEXT, facility_slug TEXT,
    address_text TEXT, phone_text TEXT, website_url TEXT, image_url TEXT, title_text TEXT,
    status TEXT, verified INTEGER NOT NULL DEFAULT 0, rating REAL NOT NULL DEFAULT 0,
    reviews_count INTEGER NOT NULL DEFAULT 0, content TEXT, full_json TEXT)");
$pdo->exec("CREATE TABLE medical_facilities (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT NOT NULL DEFAULT 'vi',
    name TEXT, status TEXT)");
$insert = $pdo->prepare('INSERT INTO medical_doctors
    (id,slug,language_code,name,specialty_text,city,facility_name,address_text,status,verified,rating,reviews_count,content)
    VALUES (:id,:slug,:language_code,:name,:specialty_text,:city,:facility_name,:address_text,:status,:verified,:rating,:reviews_count,:content)');
$saved = array_replace($doctor, ['slug' => 'nguyen-van-huy', 'verified' => 1, 'rating' => 4.8,
    'reviews_count' => 8, 'content' => '<p>Hồ sơ gốc đã được biên tập.</p>']);
$insert->execute($saved);
$before = $pdo->query('SELECT * FROM medical_doctors WHERE id=41')->fetch();
$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn();
$resolved = toplist_directory_resolve_member($pdo, 'doctor', $addressVariant);
$assert($resolved['id'] === 41 && $resolved['created'] === false,
    'Toplist JSON reuses the older doctor through the actual resolver');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === $countBefore,
    'Reusing a doctor does not insert another profile');
$assert($pdo->query('SELECT * FROM medical_doctors WHERE id=41')->fetch() === $before,
    'Linking does not overwrite existing content, rating, verified state or identity fields');

$reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', ['name' => 'Nguyễn Văn Huy']),
    'An existing same-name doctor cannot bypass review by omitting context');
$reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', $conflict),
    'A same-name conflicting workplace is rejected rather than inserted');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === $countBefore,
    'Rejected ambiguous suggestions leave the profile count unchanged');

$explicit = toplist_directory_resolve_member($pdo, 'doctor', ['doctor_id' => 41, 'name' => 'BS CKII. Nguyen Van Huy']);
$assert($explicit['id'] === 41 && $explicit['created'] === false,
    'A verified explicit ID tolerates title and accent differences in the supplied name');
$reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', ['doctor_id' => 41, 'name' => 'Nguyễn Văn Quân']),
    'A supplied ID cannot override a conflicting full name');

$new = toplist_directory_resolve_member($pdo, 'doctor', ['name' => 'Trần Minh Quân',
    'city' => 'Hà Nội', 'specialty_text' => 'Nhãn khoa', 'facility_name' => 'Bệnh viện Mắt Hà Nội']);
$assert($new['created'] === true && $new['id'] !== 41,
    'A genuinely new doctor with adequate context still creates a profile');
$again = toplist_directory_resolve_member($pdo, 'doctor', ['name' => 'BS. Trần Minh Quân',
    'city' => 'Hà Nội', 'specialty_text' => 'Nhãn khoa', 'facility_name' => 'Bệnh viện Mắt Hà Nội']);
$assert($again['created'] === false && $again['id'] === $new['id'],
    'A doctor just inserted by a prior suggestion is reused on the next resolve');
$newRow = $pdo->query('SELECT * FROM medical_doctors WHERE id=' . (int) $new['id'])->fetch();
$assert((int) $newRow['verified'] === 0 && (float) $newRow['rating'] === 0.0,
    'New suggestions cannot invent verification or review ratings');

$draft = array_replace($saved, ['id' => 100, 'slug' => 'nguyen-van-huy-draft', 'status' => 'draft']);
$insert->execute($draft);
$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn();
$reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', $input),
    'Published and draft duplicate candidates require an explicit ID');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === $countBefore,
    'Ambiguous published/draft matches never insert a third duplicate');

$singleDraft = array_replace($saved, ['id' => 101, 'slug' => 'pham-hong-son-draft',
    'name' => 'Phạm Hồng Sơn', 'status' => 'draft']);
$insert->execute($singleDraft);
$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn();
$reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', array_replace($input, ['name' => 'BS Phạm Hồng Sơn'])),
    'A single matching draft is found and blocks duplicate creation');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === $countBefore,
    'Finding a draft never seeds a second published copy');

$pdo->exec("CREATE TABLE medical_toplists (
    id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, slug TEXT UNIQUE, entity_type TEXT,
    language_code TEXT NOT NULL DEFAULT 'vi', excerpt TEXT, content TEXT, featured_image_url TEXT,
    status TEXT, updated_at TEXT)");
$pdo->exec('CREATE TABLE medical_toplist_doctors (
    toplist_id INTEGER, doctor_id INTEGER, rank_order INTEGER, UNIQUE(toplist_id,doctor_id))');
$pdo->exec('CREATE TABLE medical_toplist_facilities (
    toplist_id INTEGER, facility_id INTEGER, rank_order INTEGER, UNIQUE(toplist_id,facility_id))');
$pdo->exec("INSERT INTO medical_toplists(id,title,slug,entity_type,language_code,status)
    VALUES (1,'Danh sách thử','danh-sach-thu','doctor','vi','draft')");
$newNam = ['name' => 'Lê Hoàng Nam', 'specialty_text' => 'Nhãn khoa',
    'city' => 'Đà Nẵng', 'facility_name' => 'Bệnh viện Mắt Đà Nẵng'];
$newHa = ['name' => 'Đỗ Thu Hà', 'specialty_text' => 'Tim mạch',
    'city' => 'Đà Nẵng', 'facility_name' => 'Bệnh viện Đà Nẵng'];
$pdo->candidateSelects = 0;
$pdo->beginTransaction();
$batch = toplist_directory_import_members($pdo, 1, 'doctor', [
    $newNam + ['rank_order' => 4],
    ['doctor_id' => 41, 'rank_order' => 3],
    array_replace($newNam, ['name' => 'BS. Lê Hoàng Nam', 'rank_order' => 2]),
    $newHa + ['rank_order' => 1],
]);
$assert($pdo->candidateSelects === 1,
    'A whole Toplist payload loads doctor candidates only once');
$assert(count($batch['created_doctor_ids']) === 2 && count($batch['ids']) === 3,
    'Repeated new doctors in one payload create once and produce one relation');
$namId = (int) $pdo->query("SELECT id FROM medical_doctors WHERE name='Lê Hoàng Nam'")->fetchColumn();
$haId = (int) $pdo->query("SELECT id FROM medical_doctors WHERE name='Đỗ Thu Hà'")->fetchColumn();
$assert($batch['ids'] === [$haId, $namId, 41],
    'After merging identity duplicates, member IDs retain their requested order');
$links = $pdo->query('SELECT doctor_id,rank_order FROM medical_toplist_doctors WHERE toplist_id=1 ORDER BY rank_order')->fetchAll();
$assert(array_map('intval', array_column($links, 'doctor_id')) === [$haId, $namId, 41]
    && array_map('intval', array_column($links, 'rank_order')) === [1, 2, 3],
    'Real relation replacement writes unique doctor IDs with contiguous ranks');
$assert($pdo->query('SELECT * FROM medical_doctors WHERE id=41')->fetch() === $before,
    'Importing full ranked members still leaves the older profile untouched');
$pdo->rollBack();

$beforeDoctors = (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn();
$beforeArticles = (int) $pdo->query('SELECT COUNT(*) FROM medical_toplists')->fetchColumn();
$reject(fn() => toplist_directory_import_article($pdo, ['title' => 'Không được lưu dở',
    'entity_type' => 'doctor', 'doctors' => [$newNam, ['doctor_id' => 999999]]]),
    'An invalid later member fails the complete article import');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === $beforeDoctors
    && (int) $pdo->query('SELECT COUNT(*) FROM medical_toplists')->fetchColumn() === $beforeArticles,
    'Article import rolls back both seeded doctors and the new article on a later failure');
$assert(!$pdo->inTransaction(), 'Failed owned import closes its transaction');

// No MySQL connection: verify that the Toplist wrapper shares the existing writer mutex.
final class ToplistIdentityLockPDO extends PDO
{
    public array $events = [];
    private bool $transaction = false;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool { $this->events[] = 'BEGIN'; $this->transaction = true; return true; }
    public function commit(): bool { $this->events[] = 'COMMIT'; $this->transaction = false; return true; }
    public function rollBack(): bool { $this->events[] = 'ROLLBACK'; $this->transaction = false; return true; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    { return new ToplistIdentityLockStatement($this, $query); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    { return new ToplistIdentityLockStatement($this, $query); }
}
final class ToplistIdentityLockStatement extends PDOStatement
{
    public function __construct(private ToplistIdentityLockPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (str_contains($this->sql, 'GET_LOCK')) $this->pdo->events[] = 'GET_LOCK';
        elseif (str_contains($this->sql, 'RELEASE_LOCK')) $this->pdo->events[] = 'RELEASE_LOCK';
        else throw new RuntimeException('Unexpected lock fixture SQL');
        return true;
    }
    public function fetchColumn(int $column = 0): mixed { return $this->sql === 'SELECT DATABASE()' ? 'isolated-test-only' : 1; }
}
$lock = new ToplistIdentityLockPDO();
$result = toplist_directory_with_member_lock($lock, static function () use ($lock): string {
    $lock->beginTransaction(); $lock->commit(); return 'saved';
});
$assert($result === 'saved' && $lock->events === ['GET_LOCK', 'BEGIN', 'COMMIT', 'RELEASE_LOCK'],
    'Toplist writer acquires the shared mutex before the transaction and releases it after commit');
$lock->events = [];
try {
    toplist_directory_with_member_lock($lock, static function () use ($lock): void {
        $lock->beginTransaction(); throw new RuntimeException('Expected fixture failure');
    });
    $assert(false, 'Writer failure propagates');
} catch (RuntimeException $e) {
    $assert($e->getMessage() === 'Expected fixture failure', 'Writer failure propagates unchanged');
}
$assert(!$lock->inTransaction() && $lock->events === ['GET_LOCK', 'BEGIN', 'ROLLBACK', 'RELEASE_LOCK'],
    'Failed Toplist writer rolls back before releasing the shared mutex');

echo "Toplist doctor identity: {$checks} checks passed; database tests use in-memory SQLite only.\n";
