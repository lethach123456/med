<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_search_cache.php';

// Pure summaries and an isolated SQLite cache build: no production DB or files.
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
};
$pair = static function (array $item, float $score, int $count, string $message) use ($assert): void {
    $assert(abs((float) ($item['rating'] ?? -1) - $score) < .0001
        && (int) ($item['reviews_count'] ?? -1) === $count, $message);
};
$source = ['source' => 'Google Maps', 'score' => 4.9, 'count' => 5287,
    'url' => 'https://maps.google.com/?cid=12345'];
$externalRow = ['rating' => 5, 'reviews_count' => 3474,
    'aggregate_ratings_json' => json_encode([$source], JSON_THROW_ON_ERROR)];

$pair(medical_facility_public_rating_summary($externalRow), 0, 0,
    'External and legacy scalar totals cannot become public MedReview totals');
$pair(medical_facility_public_rating_summary(array_replace($externalRow, [
    'stored_rating' => '4.25', 'stored_reviews_count' => '4',
])), 4.3, 4, 'SQL aliases supply the public rating and review count together');
$summary = medical_facility_public_rating_summary(array_replace($externalRow, [
    'stored_rating' => 5, 'stored_reviews_count' => 999,
]), ['rating' => 4.25, 'count' => 4]);
$pair($summary, 4.3, 4, 'An explicitly loaded review summary overrides older SQL aliases');
$assert($summary['basis'] === 'stored_reviews' && $summary['sources'] === [],
    'Public summary identifies stored reviews, never external source provenance');
$pair(medical_facility_public_rating_summary($externalRow, ['rating' => '3.5', 'reviews_count' => '2']), 3.5, 2,
    'A loaded summary accepts the reviews_count alias');
$pair(medical_facility_public_rating_summary(array_replace($externalRow, [
    'stored_rating' => 5, 'stored_reviews_count' => 4,
]), ['rating' => 0, 'count' => 0]), 0, 0,
    'Deleting all stored reviews clears the public rating instead of restoring thousands');
$pair(medical_facility_public_rating_summary(array_replace($externalRow, [
    'rating_basis' => 'imported_reviews', 'review_summary_json' => '{"basis":"imported_reviews","rating":5,"count":3474}',
])), 0, 0, 'Old basis markers and serialized summaries do not substitute for freshly counted reviews');
foreach ([
    ['stored_rating' => 5],
    ['stored_reviews_count' => 2],
    ['stored_rating' => 5, 'stored_reviews_count' => 0],
    ['stored_rating' => 0, 'stored_reviews_count' => 2],
    ['stored_rating' => 6, 'stored_reviews_count' => 2],
    ['stored_rating' => INF, 'stored_reviews_count' => 2],
    ['stored_rating' => 4, 'stored_reviews_count' => '1,000'],
    ['stored_rating' => 4, 'stored_reviews_count' => -1],
    ['stored_rating' => 4, 'stored_reviews_count' => true],
] as $invalidPair) {
    $pair(medical_facility_public_rating_summary(array_replace($externalRow, $invalidPair)), 0, 0,
        'An incomplete or invalid stored pair fails closed without external fallback');
}
$pair(medical_facility_public_rating_summary([], ['rating' => 4.8, 'count' => 1200]), 4.8, 1200,
    'Genuinely stored counts are not limited by an arbitrary numeric cap');
$assert(medical_facility_rating_summary($externalRow)['reviews_count'] === 5287,
    'External source history remains separately available to storage and source detail');

final class PublicRatingsFixturePDO extends PDO
{
    /** @var list<string> */
    public array $statements = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->statements[] = $query;
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->statements[] = $query;
        if (str_contains($query, 'INFORMATION_SCHEMA.TABLES')) {
            $query = "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1";
        } elseif (str_contains($query, 'INFORMATION_SCHEMA.COLUMNS')) {
            $query = 'SELECT 1 FROM pragma_table_info(:table) WHERE name = :column LIMIT 1';
        }
        return parent::prepare($query, $options);
    }
}
$pdo = new PublicRatingsFixturePDO();
$pdo->exec("CREATE TABLE medical_facilities (
    id INTEGER PRIMARY KEY, slug TEXT UNIQUE, name TEXT DEFAULT '', language_code TEXT DEFAULT 'vi',
    translation_of_id INTEGER, rating REAL DEFAULT 0, reviews_count INTEGER DEFAULT 0,
    aggregate_ratings_json TEXT DEFAULT '[]', review_summary_json TEXT DEFAULT '{}',
    status TEXT DEFAULT 'published', subtitle TEXT DEFAULT '', content TEXT DEFAULT '<p>Written profile</p>',
    category TEXT DEFAULT 'Nha khoa', city TEXT DEFAULT '', address_text TEXT DEFAULT '',
    image_url TEXT DEFAULT '', gallery_json TEXT DEFAULT '[]', verified INTEGER DEFAULT 0,
    followers_count INTEGER DEFAULT 0, price_text TEXT DEFAULT '', hours_text TEXT DEFAULT '',
    images_label TEXT DEFAULT '', featured_services_json TEXT DEFAULT '[]', services_json TEXT DEFAULT '[]',
    tags_json TEXT DEFAULT '[]', highlights_json TEXT DEFAULT '[]', display_order INTEGER DEFAULT 0,
    updated_at TEXT DEFAULT '2026-10-18'
)");
$pdo->exec("CREATE TABLE medical_reviews (
    id INTEGER PRIMARY KEY, facility_id INTEGER, facility_slug TEXT, rating REAL,
    status TEXT DEFAULT 'published'
)");
$insertFacility = $pdo->prepare('INSERT INTO medical_facilities (id, slug, name, rating, reviews_count, aggregate_ratings_json) VALUES (?, ?, ?, ?, ?, ?)');
$insertFacility->execute([1, 'alpha-clinic', 'Alpha clinic', 5, 3474, json_encode([$source], JSON_THROW_ON_ERROR)]);
$insertFacility->execute([2, 'beta-clinic', 'Beta clinic', 5, 2446, '[]']);
$insertFacility->execute([3, 'no-reviews', 'No reviews', 5, 11122, '[]']);
$insertReview = $pdo->prepare('INSERT INTO medical_reviews (id, facility_id, facility_slug, rating, status) VALUES (?, ?, ?, ?, ?)');
foreach ([
    [1, 1, 'alpha-clinic', 5, 'published'],
    [2, 1, 'old-alpha-slug', 3, 'published'],
    [3, null, 'alpha-clinic', 4, 'published'],
    [4, 2, 'alpha-clinic', 1, 'published'],
    [5, 1, 'alpha-clinic', 5, 'draft'],
    [6, 1, 'alpha-clinic', 0, 'published'],
    [7, 1, 'alpha-clinic', 6, 'published'],
    [8, null, 'unknown-clinic', 4, 'published'],
    [9, 1, 'beta-clinic', 2, 'published'],
    [10, 999, 'alpha-clinic', 5, 'published'],
    [11, null, 'beta-clinic', 5, 'published'],
    [12, 2, 'old-beta-slug', 4.5, 'published'],
    [13, 1, 'alpha-clinic', null, 'published'],
    [14, 1, 'alpha-clinic', -1, 'published'],
] as $review) $insertReview->execute($review);

$sql = medical_facility_stored_rating_sql();
$assert(is_string($sql) && trim($sql) !== '', 'Stored rating SQL can be composed into one facility load');
$stored = $pdo->query('SELECT * FROM (' . $sql . ') counted_reviews')->fetchAll();
$storedById = array_column($stored, null, 'facility_id');
$assert((int) $storedById[1]['stored_reviews_count'] === 4
    && abs((float) $storedById[1]['stored_rating'] - 3.5) < .0001,
    'Grouped SQL counts published valid ID reviews plus NULL-ID slug reviews exactly once');
$assert((int) $storedById[2]['stored_reviews_count'] === 3
    && abs((float) $storedById[2]['stored_rating'] - 3.5) < .0001,
    'A review with an ID belongs to that ID even when its legacy slug names another facility');
$assert(!isset($storedById[3]) || (int) $storedById[3]['stored_reviews_count'] === 0,
    'A facility without stored rated reviews never inherits legacy scalar totals');

$pdo->statements = [];
$items = medical_search_cache_facilities($pdo);
$byId = array_column($items, null, 'id');
$pair($byId[1], 3.5, 4, 'Cache generation stores the actual local count instead of 3474 or 5287');
$pair($byId[2], 3.5, 3, 'Cache generation replaces a legacy 2446-review scalar with the stored three');
$pair($byId[3], 0, 0, 'Cached facilities without published rated reviews are unrated');
$assert(($byId[1]['rating_basis'] ?? null) === 'stored_reviews',
    'Cache JSON records the local review provenance');
$dataQueries = array_filter($pdo->statements, static fn(string $statement): bool =>
    !str_contains($statement, 'INFORMATION_SCHEMA') && !preg_match('/\bLIMIT\s+0\b/i', $statement)
        && str_contains($statement, 'medical_facilities'));
$assert(count($dataQueries) === 1,
    'All facility ratings are counted in one batch data query, never one query per card');
$json = json_encode(['facilities' => $items], JSON_THROW_ON_ERROR);
$snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
$jsonById = array_column($snapshot['facilities'], null, 'id');
$pair($jsonById[1], 3.5, 4, 'The same JSON payload carries correct score and count without a later rating request');
$directoryItem = medical_search_cache_directory_item($jsonById[1]);
$pair($directoryItem, 3.5, 4, 'Directory serialization preserves the public cached score/count pair');

$pdo->exec('DELETE FROM medical_reviews WHERE facility_id = 1 OR facility_id IS NULL AND facility_slug = \'alpha-clinic\'');
$afterDelete = array_column(medical_search_cache_facilities($pdo), null, 'id');
$pair($afterDelete[1], 0, 0,
    'The next cache generation clears deleted reviews without restoring external thousands');
$remaining = $pdo->query('SELECT rating, reviews_count, aggregate_ratings_json FROM medical_facilities WHERE id = 1')->fetch();
$pair($remaining, 5, 3474, 'Building display JSON does not overwrite source scalar history in the DB');
$assert(json_decode($remaining['aggregate_ratings_json'], true, 512, JSON_THROW_ON_ERROR)[0]['count'] === 5287,
    'External aggregate evidence is preserved independently of public JSON totals');

echo "Facility public ratings: {$checks} checks passed. Isolated SQLite; no live DB, network or cache writes.\n";
