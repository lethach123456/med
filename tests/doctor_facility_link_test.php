<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$facility = ['id' => 7, 'slug' => 'clinic-a', 'name' => 'Phòng khám A', 'address_text' => '95 Nguyễn Lương Bằng, Hải Phòng', 'language_code' => 'vi'];
$location = ['facility_id' => null, 'facility_name' => 'PHÒNG KHÁM A', 'address_text' => '95 Nguyễn Lương Bằng - Hải Phòng', 'is_primary' => true, 'source_ids' => ['s1']];
$assert(medical_doctor_match_facility($location, [$facility])['id'] === 7, 'Name/address match ignores case and punctuation');
foreach ([['address_text' => null], ['address_text' => ''], ['address_text' => '96 Nguyễn Lương Bằng, Hải Phòng'],
    ['facility_name' => 'Phòng khám B'], ['address_text' => '95 Nguyễn Lương Bằng'], ['facility_name' => '']] as $change) {
    $assert(medical_doctor_match_facility(array_replace($location, $change), [$facility]) === null, 'Incomplete or different identity does not auto-link');
}
$assert(medical_doctor_match_facility($location, [$facility, array_replace($facility, ['id' => 8])]) === null, 'Duplicate matching records are ambiguous');
$assert(medical_doctor_match_facility($location, [$facility, $facility])['id'] === 7, 'Same DB identity is not ambiguous');
$typo = array_replace($location, ['facility_name' => 'Phòng khám A.', 'address_text' => '95 Nguyễn Lương Băng, Hải Phòng']);
$assert(medical_doctor_match_facility($typo, [$facility])['id'] === 7, 'Small street spelling error accepted above 90 percent');
$abbreviated = ['facility_name' => 'Phòng khám A', 'address_text' => 'Số 95 Nguyễn Lương Bằng, P. Lê Thanh Nghị, TP. Hải Phòng'];
$full = array_replace($facility, ['address_text' => '95 Nguyễn Lương Bằng, Phường Lê Thanh Nghị, Thành phố Hải Phòng']);
$assert(medical_doctor_match_facility($abbreviated, [$full])['id'] === 7, 'Standard address abbreviations match full form');
foreach (['96 Nguyễn Lương Bằng, Hải Phòng', '95A Nguyễn Lương Bằng, Hải Phòng', '95/1 Nguyễn Lương Bằng, Hải Phòng'] as $branch) {
    $assert(medical_doctor_match_facility(array_replace($location, ['address_text' => $branch]), [$facility]) === null, 'House/branch numbers cannot be fuzzy matched');
}
$nearDuplicate = array_replace($facility, ['id' => 9, 'address_text' => '95 Nguyễn Lương Băng, Hải Phòng']);
$assert(medical_doctor_match_facility($location, [$facility, $nearDuplicate]) === null, 'Near-tie candidates do not auto-link');
$assert(medical_doctor_facility_text_similarity(str_repeat('a', 1025), str_repeat('a', 1025)) === 0.0, 'Bounded comparison prevents oversized matching work');
$cityA = '95 Nguyễn Lương Bằng, Phường Lê Thanh Nghị, Hà Nội';
$cityB = '95 Nguyễn Lương Bằng, Phường Lê Thanh Nghị, Hà Nam';
$assert(medical_doctor_match_facility(array_replace($location, ['address_text' => $cityA]), [array_replace($facility, ['address_text' => $cityB])]) === null, 'Different localities cannot fuzzy-match despite 90 percent full string');
$assert(medical_doctor_match_facility(array_replace($location, ['address_text' => '95/1 Nguyễn Lương Bằng, Hải Phòng']), [array_replace($facility, ['address_text' => '95-1 Nguyễn Lương Bằng, Hải Phòng'])]) === null, 'House slash cannot normalize into range dash');
$ordered = ['facility_name' => 'Phòng khám Đa khoa Thu Cúc TCI Hải Phòng', 'address_text' => $facility['address_text']];
$assert(medical_doctor_match_facility($ordered, [array_replace($facility, ['name' => 'Phòng khám Đa khoa TCI Thu Cúc Hải Phòng'])])['id'] === 7, 'Brand token order does not create duplicates');
foreach (['Dana Dental', 'My Smile'] as $brand) {
    $brandedFacility = array_replace($facility, ['name' => 'Nha Khoa ' . $brand]);
    $bareBrand = array_replace($location, ['facility_name' => $brand]);
    $assert(medical_doctor_match_facility($bareBrand, [$brandedFacility])['id'] === 7,
        'Bare distinctive brand matches the same clinic with a generic prefix');
    $assert(medical_doctor_match_facility(array_replace($bareBrand, ['facility_name' => 'Nha Khoa ' . $brand]),
        [array_replace($brandedFacility, ['name' => $brand])])['id'] === 7,
        'Generic-prefixed suggestion matches the same bare-brand clinic');
    $assert(medical_doctor_match_facility(array_replace($bareBrand, ['address_text' => '96 Nguyễn Lương Bằng, Hải Phòng']),
        [$brandedFacility]) === null, 'Prefix tolerance never merges different house numbers');
    $assert(medical_doctor_match_facility($bareBrand, [$brandedFacility, array_replace($brandedFacility, ['id' => 8])]) === null,
        'Equivalent prefixed brand candidates remain ambiguous');
}
$assert(medical_doctor_match_facility(array_replace($location, ['facility_name' => 'Dana Dental']),
    [array_replace($facility, ['name' => 'Nha Khoa My Smile'])]) === null,
    'Different distinctive brands never match solely through clinic prefixes');
$assert(medical_doctor_facility_name_similarity('Nha Khoa A', 'Nha Khoa B') === 0.0
    && medical_doctor_facility_name_similarity('Dana Dental 1', 'Nha Khoa Dana Dental 2') === 0.0,
    'Distinctive single-letter and numeric branch markers must agree');
$ambiguous = false;
$assert(medical_doctor_match_facility(array_replace($location, ['facility_name' => 'Phòng khám ABCD']), [array_replace($facility, ['name' => 'Phòng khám ABCE'])], $ambiguous) === null, 'Different distinctive brand cannot match on shared generic clinic prefix');
$assert($ambiguous, 'Near distinctive brands need review instead of creating another seed');
$near = array_replace($location, ['address_text' => '95 Nguyễn Lương Bằnxxxxx, Hải Phòng']);
medical_doctor_match_facility($near, [$facility], $ambiguous);
$assert($ambiguous, 'Near but below threshold address blocks automatic creation');
foreach (['Another address', '95 Nguyễn Lương Bằng', '95 Nguyễn Lương Bằng, Phường 1', 'Hải Phòng', '95, Hải Phòng', '95 Nguyễn Lương Bằng, chưa rõ'] as $address) {
    $assert(!medical_doctor_facility_address_identity($address)['complete'], 'Incomplete/placeholder address cannot seed facility');
}
$assert(medical_doctor_facility_address_identity('95 Nguyễn Lương Bằng, Hải Phòng')['complete'], 'Complete street and city can seed facility');

final class DoctorLinkTestPDO extends PDO
{
    public array $links = [];
    public array $queries = [];
    public int $insertedId = 0;
    public bool $transaction = true;
    public function __construct(public array $facilities) {}
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $this->queries[] = $query;
        return new DoctorLinkTestStatement($this, $query);
    }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->insertedId; }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->queries[] = $query;
        return new DoctorLinkTestStatement($this, $query);
    }
}
final class DoctorLinkTestStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private DoctorLinkTestPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool {
        $params ??= [];
        if (str_starts_with($this->sql, 'SELECT')) {
            if (str_contains($this->sql, 'GET_LOCK') || str_contains($this->sql, 'RELEASE_LOCK')) { $this->rows = [[1]]; return true; }
            $this->rows = array_values(array_filter($this->pdo->facilities, static function(array $f) use ($params): bool {
                return (!isset($params[':id']) || $f['id'] === $params[':id'])
                    && (!isset($params[':locale']) || $f['language_code'] === $params[':locale'])
                    && (!isset($params[':slug']) || $f['slug'] === $params[':slug']);
            }));
        } elseif (str_starts_with($this->sql, 'INSERT INTO medical_facilities')) {
            $this->pdo->insertedId = max(array_column($this->pdo->facilities, 'id') ?: [0]) + 1;
            $this->pdo->facilities[] = ['id' => $this->pdo->insertedId, 'slug' => $params[':slug'], 'name' => $params[':name'],
                'address_text' => $params[':address'], 'language_code' => 'vi', 'city' => $params[':city'],
                'phone_text' => $params[':phone'], 'website_url' => $params[':website'], 'booking_url' => $params[':booking'], 'full_json' => $params[':provenance']];
        } elseif (str_starts_with($this->sql, 'DELETE FROM medical_doctor_facilities')) $this->pdo->links = [];
        elseif (str_starts_with($this->sql, 'INSERT INTO medical_doctor_facilities')) $this->pdo->links[] = $params;
        else throw new RuntimeException('Unexpected SQL in fixture');
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?: false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed {
        if ($this->sql === 'SELECT DATABASE()') return 'mock_isolated';
        $row = array_shift($this->rows);
        return $row ? array_values($row)[$column] : false;
    }
}
$pdo = new DoctorLinkTestPDO([$facility]);
$fields = ['locations_json' => json_encode([$location])];
medical_doctor_link_workplaces($pdo, 12, $fields, []);
$resolved = json_decode($fields['locations_json'], true)[0];
$assert($resolved['facility_id'] === 7 && $resolved['source_ids'] === ['s1'], 'Resolved ID stored in JSON without losing citations');
$assert($pdo->links[0]['doctor_id'] === 12 && $pdo->links[0]['facility_id'] === 7, 'Relational doctor/facility link inserted');
$assert($fields['facility_slug'] === 'clinic-a' && $fields['facility_name'] === 'Phòng khám A', 'Legacy primary workplace fields synced');
$fields = ['address_text' => $facility['address_text'], 'locations_json' => '[]'];
medical_doctor_link_workplaces($pdo, 13, $fields, ['facility_name' => 'Phòng khám A'], 'vi', ['admin' => true]);
$assert($pdo->links[0]['facility_id'] === 7 && $pdo->links[0]['is_primary'] === 1, 'Admin base workplace plus address can auto-link');
$fields = ['locations_json' => '[]'];
medical_doctor_link_workplaces($pdo, 14, $fields, ['facility_slug' => 'clinic-a', 'facility_name' => 'Phòng khám A'], 'vi', ['admin' => true]);
$assert($pdo->links[0]['facility_id'] === 7, 'Explicit admin facility selector works without address');
$fields = ['locations_json' => json_encode([array_replace($location, ['address_text' => '96 Nguyễn Lương Bằng, Hải Phòng'])])];
medical_doctor_link_workplaces($pdo, 15, $fields, []);
$assert($pdo->links[0]['facility_id'] === 8 && isset($fields['facility_slug']), 'New distinct address creates a separate facility and links it');
$createdSlug = $fields['facility_slug'];
$fields = ['locations_json' => json_encode([array_replace($location, ['address_text' => '96 Nguyễn Lương Bằng, Hải Phòng'])])];
medical_doctor_link_workplaces($pdo, 18, $fields, []);
$assert($pdo->links[0]['facility_id'] === 8 && $fields['facility_slug'] === $createdSlug && count($pdo->facilities) === 2, 'Repeated add reuses created facility');
$duplicate = new DoctorLinkTestPDO([$facility, array_replace($facility, ['id' => 8])]);
$fields = ['locations_json' => json_encode([$location])];
medical_doctor_link_workplaces($duplicate, 16, $fields, []);
$assert($duplicate->links[0]['facility_id'] === null, 'Ambiguous records retain unlinked workplace');
$assert(count($duplicate->facilities) === 2, 'Ambiguous match never creates another duplicate');
$english = new DoctorLinkTestPDO([array_replace($facility, ['language_code' => 'en'])]);
$fields = ['locations_json' => json_encode([$location])];
medical_doctor_link_workplaces($english, 17, $fields, [], 'vi');
$assert($english->links[0]['facility_id'] === 8 && $english->facilities[1]['language_code'] === 'vi', 'Automatic match does not link to another locale');
$new = new DoctorLinkTestPDO([]);
$fields = ['locations_json' => json_encode([$location])];
medical_doctor_link_workplaces($new, 19, $fields, []);
$assert($new->links[0]['facility_id'] === 1 && count($new->facilities) === 1, 'Missing facility created and linked within same save');
$sql = implode(' ', $new->queries);
$assert(!str_contains($sql, 'FOR UPDATE') && str_contains($sql, "'published', 0, 0, 0, 0, NULL"), 'Creation does not lock entire facility table and uses unverified zero-rating seed');
$noAddress = new DoctorLinkTestPDO([]);
$fields = ['locations_json' => json_encode([array_replace($location, ['address_text' => null])])];
medical_doctor_link_workplaces($noAddress, 20, $fields, []);
$assert($noAddress->facilities === [] && $noAddress->links[0]['facility_id'] === null, 'Missing address never creates a facility');
$metadata = new DoctorLinkTestPDO([]);
$contact = array_replace($location, ['phone_text' => '0225 123456', 'website_url' => 'https://clinic.example.org', 'booking_url' => 'https://clinic.example.org/book']);
$fields = ['locations_json' => json_encode([$contact]), 'sources_json' => json_encode([['id' => 's1', 'url' => 'https://clinic.example.org/about'], ['id' => 's2', 'url' => 'https://other.example.org']])];
medical_doctor_link_workplaces($metadata, 21, $fields, []);
$seed = $metadata->facilities[0]; $provenance = json_decode($seed['full_json'], true);
$assert($seed['city'] === 'Hải Phòng' && $seed['phone_text'] === '0225 123456' && $seed['website_url'] === 'https://clinic.example.org', 'Seed retains city and workplace-specific contact');
$assert($provenance['sources'] === [['id' => 's1', 'url' => 'https://clinic.example.org/about']] && $provenance['doctor_id'] === 21, 'Seed provenance only keeps workplace cited sources');
$b = array_replace($facility, ['id' => 22, 'slug' => 'clinic-b', 'name' => 'Phòng khám B', 'address_text' => '12 Đường Mới, Hà Nội']);
$admin = new DoctorLinkTestPDO([$facility, $b]);
$fields = ['locations_json' => json_encode([array_replace($location, ['facility_id' => 7])])];
medical_doctor_link_workplaces($admin, 23, $fields, ['facility_slug' => 'clinic-b', 'facility_name' => 'Phòng khám B'], 'vi', ['admin' => true, 'selection_changed' => true]);
$assert($admin->links[0]['facility_id'] === 22 && $fields['facility_slug'] === 'clinic-b', 'Admin explicit new selection overrides old primary JSON');
$fields = ['locations_json' => json_encode([array_replace($location, ['facility_id' => 7])])];
medical_doctor_link_workplaces($admin, 23, $fields, ['facility_slug' => '', 'facility_name' => 'Phòng khám A'], 'vi', ['admin' => true, 'selection_changed' => true]);
$assert($admin->links === [] && $fields['facility_slug'] === '' && $fields['facility_name'] === '', 'Explicit admin unlink cannot recreate stale base workplace');
$fields = ['locations_json' => json_encode([['facility_id' => null, 'facility_name' => 'Phòng khám B', 'address_text' => null, 'is_primary' => true]])];
medical_doctor_link_workplaces($admin, 24, $fields, ['facility_slug' => 'clinic-a', 'facility_name' => 'Phòng khám A']);
$assert($fields['facility_slug'] === '' && $fields['facility_name'] === 'Phòng khám B', 'Unlinked replacement clears stale slug/name');
$fields = ['locations_json' => json_encode([array_replace($location, ['facility_id' => 7, 'facility_name' => 'Clinic A'])])];
medical_doctor_link_workplaces($admin, 25, $fields, [], 'en');
$assert($fields['facility_name'] === 'Clinic A' && $fields['facility_slug'] === 'clinic-a', 'EN fallback ID preserves translated name');
$wrongId = ['locations_json' => json_encode([array_replace($location, ['facility_id' => 7, 'address_text' => '96 Nguyễn Lương Bằng, Hải Phòng'])])];
try { medical_doctor_link_workplaces($admin, 27, $wrongId, []); $assert(false, 'Explicit wrong branch must reject'); }
catch (InvalidArgumentException $e) { $assert(str_contains($e->getMessage(), 'address_text'), 'Explicit wrong branch ID rejects with address field'); }
$prefixed = new DoctorLinkTestPDO([array_replace($facility, ['name' => 'Nha Khoa Dana Dental'])]);
$fields = ['locations_json' => json_encode([array_replace($location,
    ['facility_id' => 7, 'facility_name' => 'Dana Dental'])])];
medical_doctor_link_workplaces($prefixed, 29, $fields, []);
$assert($prefixed->links[0]['facility_id'] === 7 && count($prefixed->facilities) === 1
    && $fields['facility_name'] === 'Nha Khoa Dana Dental',
    'Explicit linked ID accepts a bare brand and retains canonical clinic name');
$wrongId = ['locations_json' => json_encode([array_replace($location,
    ['facility_id' => 7, 'facility_name' => 'Dana Dental', 'address_text' => '96 Nguyễn Lương Bằng, Hải Phòng'])])];
try { medical_doctor_link_workplaces($prefixed, 30, $wrongId, []); $assert(false, 'Bare-brand wrong branch must reject'); }
catch (InvalidArgumentException $e) { $assert(str_contains($e->getMessage(), 'address_text'), 'Prefix tolerance preserves explicit-ID branch validation'); }
$wrongLocale = new DoctorLinkTestPDO([array_replace($facility, ['language_code' => 'en'])]);
$wrongId = ['locations_json' => json_encode([array_replace($location, ['facility_id' => 7])])];
try { medical_doctor_link_workplaces($wrongLocale, 28, $wrongId, []); $assert(false, 'VI doctor cannot use EN facility ID'); }
catch (InvalidArgumentException $e) { $assert(str_contains($e->getMessage(), 'ngôn ngữ'), 'Explicit wrong locale ID rejects'); }
$fields = ['locations_json' => '[]', 'insufficient_data' => 1];
$empty = new DoctorLinkTestPDO([]);
medical_doctor_link_workplaces($empty, 26, $fields, ['facility_name' => 'Old name', 'address_text' => $facility['address_text']]);
$assert($empty->facilities === [] && $fields['locations_json'] === '[]', 'Insufficient/empty research does not seed from stale base workplace');
$lock = new DoctorLinkTestPDO([]); $lock->transaction = false;
medical_doctor_with_facility_lock($lock, static function() use ($lock): void { $lock->beginTransaction(); $lock->commit(); });
$assert(str_contains(implode(' ', $lock->queries), 'GET_LOCK') && str_contains(implode(' ', $lock->queries), 'RELEASE_LOCK'), 'Mutex acquired before transaction and released after success');
$lock->queries = [];
try { medical_doctor_with_facility_lock($lock, static function() use ($lock): void { $lock->beginTransaction(); throw new RuntimeException('fail'); }); } catch (RuntimeException) {}
$assert(!$lock->inTransaction() && str_contains(implode(' ', $lock->queries), 'RELEASE_LOCK'), 'Mutex failure rolls back and releases lock');
echo "Doctor facility links: {$checks} checks passed; mocked DB only.\n";
