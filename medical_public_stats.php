<?php
declare(strict_types=1);

/** Count exactly the profiles exposed by the unfiltered public directories. */
function medical_public_stats_from_index(array $index, string $locale): array
{
    require_once __DIR__ . '/medical_search_cache.php';
    $localized = medical_search_cache_filter_locale($index, $locale);
    $facilities = medical_search_cache_facility_directory_index($localized)['facilities'];
    $doctors = medical_search_cache_doctor_directory_index($localized)['doctors'];
    $reviews = 0;
    foreach ($facilities as $facility) $reviews += max(0, (int) ($facility['reviews_count'] ?? 0));
    return ['facilities' => count($facilities), 'doctors' => count($doctors),
        'reviews' => $reviews, 'toplists' => count($localized['toplists'])];
}

/** Share the directories' JSON TTL/stale fallback, rather than count DB rows separately. */
function medical_public_stats(string $locale, ?PDO $pdo = null, ?array $index = null): array
{
    require_once __DIR__ . '/medical_search_cache.php';
    $index ??= medical_search_cache_index($pdo)['index'];
    return medical_public_stats_from_index($index, $locale);
}
