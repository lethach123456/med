<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/medical_home_next_data.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};

$assert(medical_home_next_empty() === ['stats' => ['facilities' => 0, 'doctors' => 0, 'toplists' => 0], 'facilities' => [], 'doctors' => [], 'toplists' => []], 'Stable empty contract');
$assert(!function_exists('db'), 'Pure helpers do not include or open the database');
$resolve = static fn (string $url): string => str_starts_with($url, '/') ? 'https://example.test' . $url : $url;
$gallery = '["/uploads/a.webp", {"url":"/uploads/b.webp"}, {"src":"/uploads/a.webp"}, null, ""]';
$assert(medical_home_next_gallery($gallery, $resolve) === ['https://example.test/uploads/a.webp', 'https://example.test/uploads/b.webp'], 'Gallery shapes, media resolution and deduplication');
$assert(medical_home_next_gallery('invalid json') === [] && medical_home_next_gallery(null) === [], 'Malformed gallery is empty');
$assert(medical_home_next_image(['image_url' => '/uploads/main.webp', 'gallery_json' => $gallery], $resolve) === 'https://example.test/uploads/main.webp', 'Profile cover takes priority');
$assert(medical_home_next_image(['image_url' => '', 'gallery_json' => $gallery], $resolve) === 'https://example.test/uploads/a.webp', 'Real gallery fallback');
$assert(medical_home_next_image([]) === '', 'No fabricated image for an empty profile');
$assert(medical_home_next_media('javascript:alert(1)') === '' && medical_home_next_media('data:image/svg+xml,test') === '', 'Unsafe media schemes are rejected without dependencies');
$assert(medical_home_next_media('/uploads/real.webp', static fn (string $url): string => 'javascript:alert(1)') === '', 'Media resolver cannot introduce unsafe schemes');

$facility = medical_home_next_facility_row(['id' => '7', 'slug' => ' real-clinic ', 'name' => ' Cơ sở <A> ', 'verified' => '1', 'rating' => '4.3', 'reviews_count' => '17', 'gallery_json' => $gallery], $resolve);
$assert($facility['id'] === 7 && $facility['slug'] === 'real-clinic' && $facility['name'] === 'Cơ sở <A>', 'Normalize identifiers without rendering or inventing text');
$assert($facility['verified'] === true && $facility['rating'] === 4.3 && $facility['reviews_count'] === 17, 'Published verification and review values retain correct types');
$assert($facility['image_url'] === 'https://example.test/uploads/a.webp' && $facility['gallery_json'] === $gallery, 'Facility normalized image and original gallery');
$doctor = medical_home_next_doctor_row(['id' => 11, 'specialty_text' => 'Mắt', 'title_text' => 'BS. CKII', 'reviews_count' => -2, 'verified' => 0]);
$assert($doctor['specialty_text'] === 'Mắt' && $doctor['title_text'] === 'BS. CKII' && !$doctor['verified'], 'Doctor schema uses actual specialty/title fields');
$assert($doctor['reviews_count'] === 0 && $doctor['image_url'] === '' && $doctor['rating'] === 0.0, 'Missing doctor values do not invent reviews or portrait');

$members = [['image_url' => '/uploads/a.webp'], ['gallery_json' => $gallery], ['image_url' => '/uploads/b.webp'], ['image_url' => '/uploads/c.webp'], ['image_url' => '/uploads/d.webp']];
$toplist = medical_home_next_toplist_row(['id' => 3, 'entity_type' => 'mixed', 'language_code' => 'en', 'facility_count' => 4, 'doctor_count' => 2], $members, $resolve);
$assert($toplist['member_count'] === 6 && $toplist['language_code'] === 'en', 'Mixed member count uses actual matching-locale totals');
$assert($toplist['featured_image_url'] === 'https://example.test/uploads/a.webp' && count($toplist['collage_images']) === 3 && count(array_unique($toplist['collage_images'])) === 3, 'Toplist real member-photo fallback and bounded unique collage');
$assert(medical_home_next_toplist_row(['entity_type' => 'doctor', 'facility_count' => 8, 'doctor_count' => 2])['member_count'] === 2, 'Doctor-only count excludes facilities');
$assert(medical_home_next_toplist_row(['entity_type' => 'facility', 'facility_count' => 8, 'doctor_count' => 2])['member_count'] === 8, 'Facility-only count excludes doctors');
$assert(medical_home_next_toplist_row([])['collage_images'] === [] && medical_home_next_toplist_row([])['featured_image_url'] === '', 'Empty Toplist has no fake cover or members');
$cover = medical_home_next_toplist_row(['featured_image_url' => '/uploads/cover.webp'], $members, $resolve);
$assert($cover['featured_image_url'] === 'https://example.test/uploads/cover.webp' && $cover['collage_images'][0] === $cover['featured_image_url'], 'Editorial cover takes priority');

// PDO doubles exercise the SELECT-only loader without a database or driver.
final class HomeNextTestPDO extends PDO
{
    public array $executed = [];
    public function __construct(public array $fixtures = [], public bool $fail = false) {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->fail) throw new PDOException('Unavailable test database');
        return new HomeNextTestStatement($this, $query);
    }
}
final class HomeNextTestStatement extends PDOStatement
{
    public function __construct(private HomeNextTestPDO $database, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->database->executed[] = ['sql' => $this->sql, 'params' => $params];
        return true;
    }
    private function fixture(): array
    {
        foreach (['medical_facilities', 'medical_doctors', 'medical_toplists'] as $table) {
            if (str_contains($this->sql, 'FROM ' . $table) || str_contains($this->sql, 'FROM ' . $table . ' t')) {
                // The Toplist query includes child counts before its outer FROM.
                if (str_contains($this->sql, 'FROM medical_toplists t')) return $this->database->fixtures['medical_toplists'] ?? [];
                return $this->database->fixtures[$table] ?? [];
            }
        }
        return [];
    }
    public function fetchColumn(int $column = 0): mixed { return $this->fixture()['count'] ?? 0; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->fixture()['rows'] ?? []; }
}
$pdo = new HomeNextTestPDO([
    'medical_facilities' => ['count' => 12, 'rows' => array_fill(0, 9, ['id' => 7, 'slug' => 'clinic', 'name' => 'Real clinic'])],
    'medical_doctors' => ['count' => 5, 'rows' => array_fill(0, 5, ['id' => 11, 'slug' => 'doctor', 'name' => 'Real doctor'])],
    'medical_toplists' => ['count' => 4, 'rows' => array_fill(0, 5, ['id' => 3, 'slug' => 'list', 'title' => 'Real list', 'language_code' => 'en', 'entity_type' => 'mixed', 'facility_count' => 2, 'doctor_count' => 1])],
]);
$data = medical_home_next_data($pdo, ' EN ');
$assert($data['stats'] === ['facilities' => 12, 'doctors' => 5, 'toplists' => 4], 'Statistics use database values');
$assert(count($data['facilities']) === 6 && count($data['doctors']) === 3 && count($data['toplists']) === 3, 'Defensive per-section result limits');
$assert(count($pdo->executed) === 6, 'Exactly six read-only main queries');
foreach ($pdo->executed as $query) {
    $assert(str_starts_with(ltrim($query['sql']), 'SELECT ') && !preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|CREATE|TRUNCATE|DROP|REPLACE)\b/i', $query['sql']), 'Only SELECT statements execute');
    $assert(str_contains($query['sql'], "status = 'published'") && str_contains($query['sql'], 'language_code = :locale') && $query['params'] === [':locale' => 'en'], 'Every section and statistic filters published locale');
}
$assert($data['toplists'][0]['member_count'] === 3, 'Loader normalizes actual Toplist member total');
$assert(medical_home_next_data(new HomeNextTestPDO(), 'vi') === medical_home_next_empty(), 'Empty database stays genuinely empty');
$assert(medical_home_next_data(new HomeNextTestPDO([], true), 'vi') === medical_home_next_empty(), 'Database errors return a stable empty payload');
$fallback = new HomeNextTestPDO();
medical_home_next_data($fallback, 'unknown');
$assert($fallback->executed[0]['params'] === [':locale' => 'vi'], 'Unsupported locale defaults safely to Vietnamese');

echo "Home next data: {$checks} checks passed. No DB connection or records modified.\n";
