<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_search_cache.php';

final class RatingCacheUnavailablePDO extends PDO
{
    public int $queries = 0;
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $this->queries++;
        throw new PDOException('Isolated cache test: database unavailable');
    }
}

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($message);
};
$directory = sys_get_temp_dir() . '/medreview-rating-cache-' . bin2hex(random_bytes(8));
$previousDirectory = getenv('MEDICAL_SEARCH_CACHE_DIR');
putenv('MEDICAL_SEARCH_CACHE_DIR=' . $directory);
try {
    $snapshot = ['schema' => 3, 'facility_ratings_version' => 1,
        'generated_at' => time(), 'expires_at' => time() + 600,
        'facilities' => [['id' => 1, 'name' => 'Clinic', 'rating' => 5, 'reviews_count' => 3474,
            'rating_basis' => 'external_aggregates', 'rating_sources' => [['source' => 'Google Maps']]]],
        'doctors' => [], 'toplists' => [], 'cities' => []];
    $assert(medical_search_cache_write($snapshot), 'Write an isolated old JSON snapshot');
    $old = medical_search_cache_read();
    $assert($old['facilities'][0]['reviews_count'] === 0 && $old['facilities'][0]['rating'] === 0.0,
        'Old cache is usable but cannot restore external thousands');
    $assert($old['facilities'][0]['rating_sources'] === [] && $old['facilities'][0]['rating_basis'] === 'none',
        'Old cache does not present external provenance as local review provenance');
    $assert(!medical_search_cache_is_fresh($old), 'Old policy version triggers automatic regeneration');
    $pdo = new RatingCacheUnavailablePDO();
    $fallback = medical_search_cache_index($pdo);
    $assert($fallback['source'] === 'stale-json' && $fallback['stale'] === true,
        'A DB outage retains available facility content');
    $assert($fallback['index']['facilities'][0]['name'] === 'Clinic'
        && $fallback['index']['facilities'][0]['reviews_count'] === 0,
        'DB outage never resurrects the invalid count from stale JSON');

    $snapshot['facility_ratings_version'] = 2;
    $snapshot['facilities'][0] = array_replace($snapshot['facilities'][0], [
        'rating' => 4.5, 'reviews_count' => 4, 'rating_basis' => 'stored_reviews', 'rating_sources' => []]);
    $assert(medical_search_cache_write($snapshot), 'Write the current JSON snapshot atomically');
    $oldSources = medical_search_cache_read();
    $assert(!medical_search_cache_is_fresh($oldSources), 'Pre-source snapshots automatically rebuild even while their rating policy and TTL are current');
    $assert($oldSources['facilities'][0]['rating'] === 4.5 && $oldSources['facilities'][0]['reviews_count'] === 4,
        'Missing source metadata never erases an already-correct local rating/count');
    $snapshot['facility_rating_sources_version'] = 1;
    $snapshot['facilities'][0]['rating_sources'] = [['source' => 'Google', 'kind' => 'google']];
    $assert(medical_search_cache_write($snapshot), 'Write source-aware current JSON atomically');
    $pdo = new RatingCacheUnavailablePDO();
    $warm = medical_search_cache_index($pdo);
    $assert($warm['source'] === 'json-ttl' && !$warm['stale'] && $pdo->queries === 0,
        'Warm directory reads the complete JSON without querying any ratings or DB');
    $assert($warm['index']['facilities'][0]['rating'] === 4.5
        && $warm['index']['facilities'][0]['reviews_count'] === 4,
        'Current JSON returns the score/count together unchanged');
    $assert(($warm['index']['facilities'][0]['rating_sources'][0]['kind'] ?? '') === 'google',
        'Warm JSON includes the review platform without any database request');
} finally {
    foreach ([medical_search_cache_path(), medical_search_cache_lock_path(), medical_search_cache_invalidation_path()] as $file) {
        if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
    putenv($previousDirectory === false ? 'MEDICAL_SEARCH_CACHE_DIR' : 'MEDICAL_SEARCH_CACHE_DIR=' . $previousDirectory);
}
echo "Facility rating JSON cache: {$checks} checks passed. Temporary files only; no live DB.\n";
