<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_search_cache.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$ids = static fn(array $items): array => array_column($items, 'id');

$blankContent = [null, '', " \t\r\n", '<p></p><br>', '<p>&nbsp; &#160;</p>',
    "<p>\u{00A0}\u{200B}\n</p>", '<!-- No article -->'];
foreach ($blankContent as $position => $content) {
    $assert(!medical_search_cache_has_written_content((string) $content), 'Blank content variant ' . $position . ' is hidden');
}
foreach (['<p>Bác sĩ có kinh nghiệm khám bệnh.</p>', '<h2>Profile</h2><p>Written profile.</p>', '0'] as $content) {
    $assert(medical_search_cache_has_written_content($content), 'Meaningful text remains visible');
}

// These doubles have no database connection and reject every non-SELECT call.
final class DoctorDirectoryContentPDO extends PDO
{
    public array $queries = [];
    public function __construct(private array $doctors) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (!preg_match('/^\s*SELECT\b/i', $query)) throw new RuntimeException('Non-SELECT SQL attempted');
        $this->queries[] = $query;
        $rows = str_contains($query, 'SELECT * FROM medical_doctors') ? $this->doctors : [];
        return new DoctorDirectoryContentStatement($rows);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw new RuntimeException('Unexpected prepare: no database operations are allowed');
    }
    public function exec(string $statement): int|false
    {
        throw new RuntimeException('Unexpected SQL write');
    }
}
final class DoctorDirectoryContentStatement extends PDOStatement
{
    public function __construct(private array $rows) {}
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}

$rows = [];
$localeFixtures = [
    'vi' => ['offset' => 0, 'name' => 'Bác sĩ', 'cities' => ['Hà Nội', 'TP Hồ Chí Minh'], 'specialties' => ['Tim mạch', 'Mắt']],
    'en' => ['offset' => 200, 'name' => 'Dr', 'cities' => ['Hanoi', 'Ho Chi Minh City'], 'specialties' => ['Cardiology', 'Ophthalmology']],
];
foreach ($localeFixtures as $locale => $fixture) {
    for ($number = 1; $number <= 13; $number++) {
        $rows[] = ['id' => $fixture['offset'] + $number, 'slug' => $locale . '-written-' . $number,
            'name' => $fixture['name'] . ' Visible ' . $number, 'status' => 'published', 'language_code' => $locale,
            'content' => '<p>' . ($locale === 'vi' ? 'Hồ sơ bác sĩ đầy đủ.' : 'Written doctor profile.') . '</p>',
            'city' => $fixture['cities'][$number % 2 === 1 ? 0 : 1],
            'specialty_text' => $fixture['specialties'][$number % 3 === 0 ? 0 : 1],
            'services_json' => '[{"name":"Consultation"}]', 'rating' => ($number % 4) + 1,
            'reviews_count' => $number * 3, 'updated_at' => sprintf('2026-10-%02d 12:00:00', 14 - $number)];
    }
    foreach (array_merge($blankContent, [null]) as $position => $content) {
        $rows[] = ['id' => $fixture['offset'] + 101 + $position, 'slug' => $locale . '-seed-' . $position,
            'name' => 'Seedtoken ' . $locale . ' ' . $position, 'status' => 'published', 'language_code' => $locale,
            'content' => $content, 'bio_json' => '["Legacy summary is not a written article"]',
            'city' => 'Only Seed City ' . $locale, 'specialty_text' => 'Only Seed Specialty ' . $locale,
            'rating' => 5, 'reviews_count' => 10000, 'updated_at' => '2026-10-31 12:00:00'];
    }
}
$pdo = new DoctorDirectoryContentPDO($rows);
$doctors = medical_search_cache_doctors($pdo);
$assert(count($doctors) === count($rows), 'Shared loader retains published seeds for non-directory consumers');
foreach ($doctors as $doctor) {
    $assert($doctor['has_content'] === str_contains($doctor['slug'], '-written-'), 'Loader computes content flag for ' . $doctor['slug']);
}
$rebuilt = medical_search_cache_rebuild($pdo);
$assert(($rebuilt['schema'] ?? null) === 3, 'Rebuilt index uses schema 3');
$assert(count($rebuilt['doctors']) === count($rows), 'Rebuild does not apply directory-only eligibility globally');
$assert(count($pdo->queries) > 0, 'Fixtures exercise real cache loader with SELECT-only doubles');

$facility = ['id' => 900, 'name' => 'Facility', 'city' => 'Facility Only City', 'language_code' => 'vi'];
$index = ['doctors' => $doctors, 'facilities' => [$facility], 'toplists' => [],
    'cities' => medical_search_cache_cities([$facility], $doctors),
    'counts' => ['doctors' => count($doctors), 'facilities' => 1, 'toplists' => 0]];
$original = $index;
$filtered = medical_search_cache_doctor_directory_index($index);
$assert(count($filtered['doctors']) === 26 && $filtered['counts']['doctors'] === 26, 'Directory count includes written doctors in both locales');
$assert($filtered['facilities'] === [$facility] && $filtered['counts']['facilities'] === 1, 'Directory helper preserves other entity data/counts');
$assert(!in_array('Facility Only City', array_column($filtered['cities'], 'label'), true), 'Doctor city detection excludes facility-only cities');
$assert($index === $original, 'Directory helper does not mutate caller shared index');

$flagItems = [];
foreach ([true, 1, '1', false, 0, '0', null] as $position => $flag) $flagItems[] = ['id' => $position, 'has_content' => $flag];
$flagItems[] = ['id' => 7];
$flagIndex = medical_search_cache_doctor_directory_index(['doctors' => $flagItems]);
$assert($ids($flagIndex['doctors']) === [0, 1, 2], 'Only affirmative content flags pass; missing flags fail closed');

$expectedOrders = [
    'recommended' => [11, 7, 3, 10, 6, 2, 13, 9, 5, 1, 12, 8, 4],
    'rating' => [11, 7, 3, 10, 6, 2, 13, 9, 5, 1, 12, 8, 4],
    'reviews' => range(13, 1),
    'newest' => range(1, 13),
];
foreach ($localeFixtures as $locale => $fixture) {
    $localized = medical_search_cache_filter_locale($index, $locale);
    $directory = medical_search_cache_doctor_directory_index($localized);
    $assert(count($directory['doctors']) === 13 && $directory['counts']['doctors'] === 13, 'Written count matches locale ' . $locale);
    $assert(array_sum(array_column($directory['doctors'], 'reviews_count')) === 273, 'Seed review counts excluded from SSR statistics ' . $locale);
    $assert(count($directory['cities']) === 2 && !in_array('Only Seed City ' . $locale, array_column($directory['cities'], 'label'), true), 'Seed-only city excluded from facets ' . $locale);
    $specialties = array_column($directory['doctors'], 'specialty_text');
    $assert(!in_array('Only Seed Specialty ' . $locale, $specialties, true), 'Seed-only specialty excluded from facets ' . $locale);
    foreach ($expectedOrders as $sort => $order) {
        $order = array_map(static fn(int $id): int => $fixture['offset'] + $id, $order);
        $first = medical_search_cache_doctor_directory_search($localized, ['sort' => $sort, 'limit' => 6]);
        $assert($first['paging']['total'] === 13 && $first['paging']['total_pages'] === 3 && $first['paging']['has_next'] && !$first['paging']['has_prev'], 'Filtered first-page totals ' . $locale . '/' . $sort);
        $assert($ids($first['items']) === array_slice($order, 0, 6), 'Sort preserved after eligibility filtering ' . $locale . '/' . $sort);
        $last = medical_search_cache_doctor_directory_search($localized, ['sort' => $sort, 'limit' => 6, 'page' => 999]);
        $assert($last['paging']['page'] === 3 && $last['paging']['has_prev'] && !$last['paging']['has_next'], 'Out-of-range page clamped after filtering ' . $locale . '/' . $sort);
        $assert($ids($last['items']) === array_slice($order, 12), 'Last page contains only the final written doctor ' . $locale . '/' . $sort);
    }
    foreach (['city' => 'Only Seed City ' . $locale, 'specialty' => 'Only Seed Specialty ' . $locale, 'q' => 'Seedtoken'] as $key => $value) {
        $empty = medical_search_cache_doctor_directory_search($localized, [$key => $value, 'page' => 999]);
        $assert($empty['items'] === [] && $empty['paging']['total'] === 0 && $empty['paging']['page'] === 1 && $empty['paging']['total_pages'] === 1, 'Seed-only ' . $key . ' returns stable empty paging ' . $locale);
    }
    $city = medical_search_cache_doctor_directory_search($localized, ['q' => $fixture['name'] . ' ' . $fixture['cities'][0]]);
    $assert($city['paging']['total'] === 7, 'Written-city detection and doctor intent words preserved ' . $locale);
    $specialty = medical_search_cache_doctor_directory_search($localized, ['specialty' => $fixture['specialties'][0]]);
    $assert($specialty['paging']['total'] === 4, 'Written specialty filtering preserved ' . $locale);
    $rating = medical_search_cache_doctor_directory_search($localized, ['min_rating' => 4]);
    $assert($rating['paging']['total'] === 3, 'Rating filter does not expose high-rated blank seeds ' . $locale);
    $global = medical_search_cache_search($localized, 'Seedtoken', 24);
    $assert(count($global['groups']['doctors']) === count($blankContent) + 1, 'Global autocomplete retains its pre-existing broad scope ' . $locale);
}
$assert($index === $original, 'Search/filter operations leave original global snapshot unchanged');

// Verify HTTP wiring without executing entry points, opening DBs or creating caches.
$sourceOf = static function (string $function): string {
    $reflection = new ReflectionFunction($function);
    $lines = file($reflection->getFileName());
    return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
};
$assert((bool) preg_match('/\[\x27schema\x27\].*?!==\s*3/s', $sourceOf('medical_search_cache_read')), 'Cache reader rejects pre-content-flag schemas');
$assert(str_contains($sourceOf('medical_search_cache_doctor_directory_search'), 'medical_search_cache_doctor_directory_index('), 'AJAX search applies directory eligibility itself');
$page = (string) file_get_contents(dirname(__DIR__) . '/bac-si.php');
$helperPosition = strpos($page, 'medical_search_cache_doctor_directory_index(');
$statsPosition = strpos($page, "foreach ((array) (\$index['doctors']");
$assert($helperPosition !== false && $statsPosition !== false && $helperPosition < $statsPosition, 'SSR filters index before counts, ratings and facets');
$endpoint = (string) file_get_contents(dirname(__DIR__) . '/api/medical/doctors.php');
$assert(str_contains($endpoint, 'medical_search_cache_doctor_directory_search('), 'Public endpoint uses the tested directory search pipeline');

echo "Doctor directory content: {$checks} checks passed. No DB connection, cache writes or network requests.\n";
