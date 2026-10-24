<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_public_stats.php';
require_once dirname(__DIR__) . '/medical_search_cache.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($message);
};
$profile = static fn(int $id, string $locale, bool $written, int $reviews = 0): array => [
    'id' => $id, 'slug' => 'profile-' . $id, 'name' => 'Profile ' . $id, 'language_code' => $locale,
    'has_content' => $written, 'reviews_count' => $reviews, 'search_text' => 'profile ' . $id,
    'city' => 'City', 'city_key' => 'city', 'category_key' => 'clinic',
];
$index = ['facilities' => [$profile(1, 'vi', true, 3), $profile(2, 'vi', true, 2),
    $profile(3, 'vi', false, 1000), $profile(4, 'en', true, 4), $profile(5, 'en', false, 2000)],
    'doctors' => [$profile(11, 'vi', true), $profile(12, 'vi', true), $profile(13, 'vi', true),
        $profile(14, 'vi', false), $profile(15, 'en', true), $profile(16, 'en', false)],
    'toplists' => [['language_code' => 'vi'], ['language_code' => 'en'], ['language_code' => 'en']],
    'cities' => [], 'counts' => ['facilities' => 9999, 'doctors' => 8888, 'toplists' => 7777]];
$before = $index;
foreach (['vi' => ['facilities' => 2, 'doctors' => 3, 'reviews' => 5, 'toplists' => 1],
    'en' => ['facilities' => 1, 'doctors' => 1, 'reviews' => 4, 'toplists' => 2]] as $locale => $expected) {
    $stats = medical_public_stats($locale, null, $index);
    $assert($stats === $expected, $locale . ' public counters exclude unwritten profiles, translations and raw cache totals');
    $localized = medical_search_cache_filter_locale($index, $locale);
    $facilities = medical_search_cache_directory_search($localized, []);
    $doctors = medical_search_cache_doctor_directory_search($localized, []);
    $assert($stats['facilities'] === $facilities['paging']['total'], $locale . ' counter equals actual unfiltered facility directory total');
    $assert($stats['doctors'] === $doctors['paging']['total'], $locale . ' counter equals actual unfiltered doctor directory total');
    $assert(medical_search_cache_directory_search($localized, ['q' => 'no such record'])['paging']['total'] === 0
        && medical_public_stats($locale, null, $index) === $expected, 'Local search filters cannot redefine site-wide public counters');
}
$assert($index === $before, 'Statistics leave the shared cache snapshot unchanged');
$assert(medical_public_stats('vi', null, []) === ['facilities' => 0, 'doctors' => 0, 'reviews' => 0, 'toplists' => 0], 'A valid empty snapshot has real zero counts');
$assert(medical_public_stats('unknown', null, $index) === medical_public_stats('vi', null, $index), 'Unsupported language follows directory locale normalization');

final class PublicStatsNoDatabasePDO extends PDO
{
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    { throw new PDOException('Isolated public counter test: unavailable DB'); }
}
$cacheDirectory = sys_get_temp_dir() . '/medreview-public-counts-' . bin2hex(random_bytes(8));
$previousDirectory = getenv('MEDICAL_SEARCH_CACHE_DIR');
putenv('MEDICAL_SEARCH_CACHE_DIR=' . $cacheDirectory);
try {
    $snapshot = $index + ['schema' => 3, 'facility_ratings_version' => 2,
        'generated_at' => time() - 1200, 'expires_at' => time() - 600];
    $assert(medical_search_cache_write($snapshot), 'Use isolated temporary stale JSON');
    $hash = hash_file('sha256', medical_search_cache_path());
    $assert(medical_public_stats('vi', new PublicStatsNoDatabasePDO()) === medical_public_stats('vi', null, $index), 'Counters use the directories\' valid stale snapshot during DB outages');
    $assert(hash_file('sha256', medical_search_cache_path()) === $hash, 'An outage does not replace the saved snapshot with zero totals');
} finally {
    foreach ([medical_search_cache_path(), medical_search_cache_lock_path(), medical_search_cache_invalidation_path()] as $file) {
        if (is_file($file)) unlink($file);
    }
    if (is_dir($cacheDirectory)) rmdir($cacheDirectory);
    putenv($previousDirectory === false ? 'MEDICAL_SEARCH_CACHE_DIR' : 'MEDICAL_SEARCH_CACHE_DIR=' . $previousDirectory);
}
echo "Public profile counts: {$checks} checks passed; isolated snapshots only, no live DB.\n";
