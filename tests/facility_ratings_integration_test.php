<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/medical_facility_directory_view.php';

/** Exercise the real queries against an isolated DB, adapting MySQL metadata. */
final class FacilityRatingsFixturePDO extends PDO
{
    public array $queries = [];

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        if (str_contains($query, 'INFORMATION_SCHEMA.TABLES')) {
            $query = "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1";
        } elseif (str_contains($query, 'INFORMATION_SCHEMA.COLUMNS')) {
            $query = 'SELECT 1 FROM pragma_table_info(:table) WHERE name = :column LIMIT 1';
        } elseif (preg_match('/^UPDATE\b/i', $query)) {
            $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($message);
};
$pair = static function (array $item, float $rating, int $count, string $message) use ($assert): void {
    $assert(abs((float) ($item['rating'] ?? -1) - $rating) < .0001
        && (int) ($item['reviews_count'] ?? -1) === $count, $message);
};
$external = [['source' => 'Google Maps', 'score' => 4.9, 'count' => 5287,
    'url' => 'https://maps.google.com/?cid=12345', 'checked_at' => '2026-10-18']];
$canonical = ['id' => 1, 'slug' => 'canonical-clinic', 'name' => 'Canonical clinic',
    'rating' => '4.2', 'reviews_count' => 6, 'aggregate_ratings_json' => json_encode($external)];
$legacy = ['id' => 2, 'slug' => 'legacy-clinic', 'name' => 'Legacy clinic',
    'rating' => '5.0', 'reviews_count' => 11122, 'aggregate_ratings_json' => '[]'];
$unrated = ['id' => 3, 'slug' => 'sample-only-clinic', 'name' => 'Sample-only clinic',
    'rating' => '0.0', 'reviews_count' => 0, 'aggregate_ratings_json' => '[]'];

$pair(medical_directory_facility_from_row($canonical), 4.9, 5287,
    'Facility row mapping recovers canonical external totals over stale sampled scalar values');
$pair(medical_directory_facility_from_row($legacy), 5.0, 11122,
    'Legacy aggregate pairs remain available when no per-source data exists');
$pair(medical_directory_facility_from_row(array_replace($legacy, ['reviews_count' => 0])), 0.0, 0,
    'A score with zero reviews is not presented as a real rating');
$pair(medical_directory_facility_from_row(array_replace($legacy, ['rating' => 0])), 0.0, 0,
    'A count without a usable score is not presented as a rated profile');
$mapped = medical_directory_facility_from_row($canonical);
$assert(($mapped['rating_basis'] ?? null) === 'external_aggregates', 'Mapped canonical rating exposes its origin');
$assert(!medical_search_cache_is_fresh(['generated_at' => time(), 'expires_at' => time() + 600]),
    'Pre-fix rating snapshots must be refreshed even within the normal TTL');

$pdo = new FacilityRatingsFixturePDO();
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
    id INTEGER PRIMARY KEY, slug TEXT, facility_id INTEGER, facility_slug TEXT, rating REAL,
    status TEXT DEFAULT 'published', verified INTEGER DEFAULT 0, display_order INTEGER DEFAULT 0,
    excerpt TEXT DEFAULT 'Public review excerpt'
)");
$insertFacility = $pdo->prepare('INSERT INTO medical_facilities (id, slug, name, rating, reviews_count, aggregate_ratings_json) VALUES (:id, :slug, :name, :rating, :reviews_count, :aggregate_ratings_json)');
foreach ([$canonical, $legacy, $unrated] as $row) $insertFacility->execute($row);
$insertReview = $pdo->prepare('INSERT INTO medical_reviews (id, slug, facility_id, facility_slug, rating, status, verified) VALUES (?, ?, ?, ?, ?, ?, ?)');
foreach (range(1, 6) as $id) {
    $insertReview->execute([$id, 'canonical-review-' . $id, 1, 'canonical-clinic', 5, 'published', $id <= 2 ? 1 : 0]);
}
$insertReview->execute([10, 'legacy-review', 2, 'legacy-clinic', 3, 'published', 0]);
$insertReview->execute([20, 'sample-first', 3, 'old-clinic-slug', 3, 'published', 0]);
$insertReview->execute([21, 'sample-legacy-link', null, 'sample-only-clinic', 5, 'published', 1]);
$insertReview->execute([22, 'wrong-owner', 999, 'sample-only-clinic', 1, 'published', 0]);
$insertReview->execute([23, 'unpublished', 3, 'sample-only-clinic', 1, 'draft', 0]);
$insertReview->execute([24, 'editorial-info', 3, 'sample-only-clinic', 0, 'published', 0]);
$insertReview->execute([26, 'invalid-rating', 3, 'sample-only-clinic', 8, 'published', 1]);

// Search index and directory must agree before a background aggregate repair.
$indexed = medical_search_cache_facilities($pdo);
$byId = array_column($indexed, null, 'id');
$pair($byId[1], 4.9, 5287, 'Search index selects the same canonical pair as detail mapping');
$pair($byId[2], 5.0, 11122, 'Search index retains legacy source totals');

// A detail page still exposes real sample statistics without replacing its header totals.
$linked = medical_directory_facility_with_linked_reviews($mapped, true, $pdo);
$pair($linked, 4.9, 5287, 'Loading six imported reviews cannot overwrite external 5287-review header totals');
$assert((int) ($linked['review_summary']['count'] ?? -1) === 6,
    'Review distribution reports the six stored samples rather than the external total');
$assert(abs((float) ($linked['review_summary']['rating'] ?? -1) - 5.0) < .0001,
    'Stored sample average remains independent from the external source average');
$assert((int) ($linked['review_summary']['verified_count'] ?? -1) === 2
    && (int) ($linked['review_summary']['verified_percent'] ?? -1) === 33,
    'Verification percentage uses the stored sample denominator');
$assert(count($linked['reviews_list']) === 3, 'Existing review preview limit is retained');
$repeatSamples = medical_directory_facility_with_linked_reviews($mapped, true, $pdo);
$pair($repeatSamples, 4.9, 5287, 'Repeated detail loading keeps the canonical totals stable');
$sampleLinked = medical_directory_facility_with_linked_reviews(medical_directory_facility_from_row($unrated), true, $pdo);
$pair($sampleLinked, 4.0, 2, 'A profile without source totals can use published samples as fallback');
$assert((int) $sampleLinked['review_summary']['count'] === 2,
    'ID linkage includes renamed-slug reviews and NULL-ID legacy links while rejecting another owner');
$assert((int) $sampleLinked['review_summary']['verified_count'] === 1
    && (int) $sampleLinked['review_summary']['verified_percent'] === 50,
    'Invalid ratings above five do not enter the sample count or verification ratio');

$readFacility = static function (int $id) use ($pdo): array {
    $statement = $pdo->prepare('SELECT * FROM medical_facilities WHERE id = ?');
    $statement->execute([$id]);
    return $statement->fetch();
};
medical_directory_refresh_facility_aggregates($pdo, 'canonical-clinic', false);
$pair($readFacility(1), 4.9, 5287, 'Aggregate refresh repairs stale scalars from external canonical data');
$pdo->exec('DELETE FROM medical_reviews WHERE id BETWEEN 1 AND 6');
medical_directory_refresh_facility_aggregates($pdo, 'canonical-clinic', false);
$pair($readFacility(1), 4.9, 5287, 'Deleting all imported samples does not erase canonical external totals');
$emptySamples = medical_directory_facility_with_linked_reviews(medical_directory_facility_from_row($readFacility(1)), true, $pdo);
$pair($emptySamples, 4.9, 5287, 'Detail keeps external totals when no imported reviews remain');
$assert((int) $emptySamples['review_summary']['count'] === 0
    && (int) $emptySamples['review_summary']['verified_percent'] === 0,
    'Empty imported review section has no sample count or artificial verification percentage');
medical_directory_refresh_facility_aggregates($pdo, 'legacy-clinic', false);
$pair($readFacility(2), 5.0, 11122, 'Aggregate refresh cannot replace 11122 legacy ratings with one stored sample');
medical_directory_refresh_facility_aggregates($pdo, 'sample-only-clinic', false);
$pair($readFacility(3), 4.0, 2, 'First sample-only refresh derives the published linked review average');
$sampleMetadata = json_decode((string) $readFacility(3)['review_summary_json'], true);
$assert(($sampleMetadata['basis'] ?? null) === 'imported_reviews',
    'Derived sample totals record their basis so later sample changes can be refreshed');
$insertReview->execute([25, 'sample-third', 3, 'sample-only-clinic', 4, 'published', 0]);
medical_directory_refresh_facility_aggregates($pdo, 'sample-only-clinic', false);
$pair($readFacility(3), 4.0, 3, 'A later sample addition updates a known sample-derived total');
$pdo->exec('DELETE FROM medical_reviews WHERE id IN (20, 21, 25)');
medical_directory_refresh_facility_aggregates($pdo, 'sample-only-clinic', false);
$pair($readFacility(3), 0.0, 0, 'Removing all rated samples clears only known sample-derived totals');
$assert($pdo->query('SELECT COUNT(*) FROM medical_reviews WHERE id IN (22, 23, 24, 26)')->fetchColumn() === 4,
    'Unrelated, draft, editorial and invalid rows remain untouched by aggregate refresh');

foreach (['vi' => 'Chưa có đánh giá', 'en' => 'Not rated yet'] as $locale => $label) {
    $card = facility_directory_card(['slug' => 'partial', 'name' => 'Partial rating', 'rating' => 4.9, 'reviews_count' => 0], $locale);
    $assert(str_contains($card, $label) && !str_contains($card, 'ph-fill ph-star'),
        $locale . ' directory card never renders a score with zero reviews');
}
$card = facility_directory_card(array_replace($mapped, ['image' => '', 'rating_sources' => $external]));
$assert(str_contains($card, 'Google Maps') && str_contains($card, '5.287 đánh giá'), 'The directory exposes the source of canonical totals');

echo "Facility rating integration: {$checks} checks passed. Isolated SQLite; no live DB, network or cache writes.\n";
