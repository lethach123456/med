<?php
declare(strict_types=1);

/** Pure rating helpers. A source URL records provenance; it is not verification. */
function medical_facility_rating_score(mixed $value): ?float
{
    if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) return null;
    $score = (float) $value;
    return is_finite($score) && $score > 0 && $score <= 5 ? $score : null;
}

function medical_facility_rating_count(mixed $value): ?int
{
    if (is_string($value)) {
        $value = trim($value);
        if (!preg_match('/^[0-9]+$/D', $value)) return null;
        $digits = ltrim($value, '0');
        if ($digits === '' || strlen($digits) > 10 || (strlen($digits) === 10 && strcmp($digits, '2147483647') > 0)) return null;
        $value = (int) $digits;
    }
    return is_int($value) && $value > 0 && $value <= 2147483647 ? $value : null;
}

/** Keep location/review query identifiers while removing incidental tracking. */
function medical_facility_rating_source_url(mixed $value): ?string
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($value);
    if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return null;
    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $port = isset($parts['port']) && !(($scheme === 'https' && $parts['port'] === 443) || ($scheme === 'http' && $parts['port'] === 80))
        ? ':' . $parts['port'] : '';
    $path = rtrim($parts['path'] ?? '', '/');
    $query = [];
    foreach (explode('&', $parts['query'] ?? '') as $entry) {
        if ($entry === '') continue;
        $key = strtolower(rawurldecode(explode('=', $entry, 2)[0]));
        if (str_starts_with($key, 'utm_') || in_array($key, ['gclid', 'fbclid', 'msclkid'], true)) continue;
        $query[] = $entry;
    }
    sort($query, SORT_STRING);
    return $scheme . '://' . $host . $port . $path . ($query !== [] ? '?' . implode('&', $query) : '');
}

/** Invalid/missing dates cannot displace an existing observation of a source. */
function medical_facility_rating_checked_at(mixed $value): string
{
    if (!is_string($value)) return '';
    $value = trim($value);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ]([0-2]\d):([0-5]\d)(?::([0-5]\d)(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/D', $value, $match)
        || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])
        || (isset($match[4]) && (int) $match[4] > 23)) return '';
    try { new DateTimeImmutable($value, new DateTimeZone('UTC')); }
    catch (Exception) { return ''; }
    return $value;
}

/**
 * @return list<array{source:string,score:float,count:int,url:string,checked_at:string}>
 */
function medical_facility_rating_normalize_aggregates(mixed $value): array
{
    if (is_string($value)) {
        try { $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR); }
        catch (JsonException) { return []; }
    }
    if (!is_array($value) || !array_is_list($value)) return [];
    $result = []; $seen = [];
    foreach ($value as $row) {
        if (!is_array($row) || array_is_list($row)) continue;
        $source = $row['source'] ?? $row['source_text'] ?? null;
        if (!is_string($source)) continue;
        $source = trim(preg_replace('/\s+/u', ' ', strip_tags($source)) ?? '');
        if ($source === '' || str_contains($source, "\0")) continue;
        $score = medical_facility_rating_score($row['score'] ?? null);
        $count = medical_facility_rating_count($row['count'] ?? null);
        if ($score === null || $count === null) continue;
        // Legacy aggregates did not always contain URLs. Explicitly invalid URLs
        // must not become URL-less legacy evidence after failed validation.
        $url = '';
        if (array_key_exists('url', $row) || array_key_exists('source_url', $row)) {
            $rawUrl = array_key_exists('url', $row) ? $row['url'] : $row['source_url'];
            if ($rawUrl !== null && $rawUrl !== '') {
                $url = medical_facility_rating_source_url($rawUrl);
                if ($url === null) continue;
            }
        }
        $checkedAt = medical_facility_rating_checked_at($row['checked_at'] ?? null);
        $entry = ['source' => $source, 'score' => $score, 'count' => $count, 'url' => $url, 'checked_at' => $checkedAt];
        $sourceKey = function_exists('mb_strtolower') ? mb_strtolower($source, 'UTF-8') : strtolower($source);
        $key = $url !== '' ? 'url:' . $url : 'source:' . $sourceKey;
        if (array_key_exists($key, $seen)) {
            $index = $seen[$key];
            $previousDate = $result[$index]['checked_at'];
            if ($checkedAt !== '' && ($previousDate === ''
                || (new DateTimeImmutable($checkedAt, new DateTimeZone('UTC')))->getTimestamp()
                    > (new DateTimeImmutable($previousDate, new DateTimeZone('UTC')))->getTimestamp())) {
                $result[$index] = $entry;
            }
            continue;
        }
        $seen[$key] = count($result);
        $result[] = $entry;
    }
    return $result;
}

/**
 * Imported review samples never contribute to an external aggregate's count.
 * @return array{rating:float,reviews_count:int,basis:string,sources:array}
 */
function medical_facility_rating_summary(array $row, ?array $reviewSummary = null): array
{
    $sources = medical_facility_rating_normalize_aggregates($row['aggregate_ratings'] ?? []);
    if ($sources === []) $sources = medical_facility_rating_normalize_aggregates($row['aggregate_ratings_json'] ?? []);
    if ($sources !== []) {
        $weighted = 0.0; $total = 0;
        foreach ($sources as $source) {
            $weighted += $source['score'] * $source['count'];
            $total += $source['count'];
        }
        return ['rating' => round($weighted / $total, 1), 'reviews_count' => $total, 'basis' => 'external_aggregates', 'sources' => $sources];
    }
    $metadata = $row['review_summary_json'] ?? [];
    if (is_string($metadata)) $metadata = json_decode($metadata, true);
    $basis = $row['rating_basis'] ?? (is_array($metadata) ? ($metadata['basis'] ?? '') : '');
    if ($basis === 'imported_reviews') {
        $fresh = $reviewSummary ?? $row;
        $score = medical_facility_rating_score($fresh['rating'] ?? null);
        $count = medical_facility_rating_count($fresh['count'] ?? $fresh['reviews_count'] ?? null);
        return ['rating' => $score !== null && $count !== null ? round($score, 1) : 0.0,
            'reviews_count' => $score !== null && $count !== null ? $count : 0, 'basis' => 'imported_reviews', 'sources' => []];
    }
    $score = medical_facility_rating_score($row['rating'] ?? null);
    $count = medical_facility_rating_count($row['reviews_count'] ?? null);
    if ($score !== null && $count !== null) {
        return ['rating' => round($score, 1), 'reviews_count' => $count, 'basis' => 'legacy', 'sources' => []];
    }
    $score = medical_facility_rating_score($reviewSummary['rating'] ?? null);
    $count = medical_facility_rating_count($reviewSummary['count'] ?? $reviewSummary['reviews_count'] ?? null);
    if ($score !== null && $count !== null) {
        return ['rating' => round($score, 1), 'reviews_count' => $count, 'basis' => 'imported_reviews', 'sources' => []];
    }
    return ['rating' => 0.0, 'reviews_count' => 0, 'basis' => 'none', 'sources' => []];
}

/**
 * Public score/count describe the same published review records on MedReview.
 * Never substitute a source's total or an unclassified legacy scalar here.
 * @return array{rating:float,reviews_count:int,basis:string,sources:array}
 */
function medical_facility_public_rating_summary(array $row, ?array $reviewSummary = null): array
{
    $score = medical_facility_rating_score($reviewSummary !== null
        ? ($reviewSummary['rating'] ?? null) : ($row['stored_rating'] ?? null));
    $count = medical_facility_rating_count($reviewSummary !== null
        ? ($reviewSummary['count'] ?? $reviewSummary['reviews_count'] ?? null) : ($row['stored_reviews_count'] ?? null));
    if ($score === null || $count === null) {
        return ['rating' => 0.0, 'reviews_count' => 0, 'basis' => 'none', 'sources' => []];
    }
    return ['rating' => round($score, 1), 'reviews_count' => $count, 'basis' => 'stored_reviews', 'sources' => []];
}

/**
 * Join this derived table into a facility SELECT when building public data.
 * Two indexed paths avoid a per-facility query and an OR across the join.
 * An explicit owner wins; slug matching is only for older, unlinked reviews.
 */
function medical_facility_stored_rating_sql(): string
{
    return "SELECT stored_reviews.facility_id, AVG(stored_reviews.rating) AS stored_rating,
                   COUNT(*) AS stored_reviews_count
            FROM (
                SELECT facility_id, rating FROM medical_reviews
                WHERE facility_id IS NOT NULL AND status = 'published' AND rating > 0 AND rating <= 5
                UNION ALL
                SELECT f.id AS facility_id, r.rating FROM medical_reviews r
                JOIN medical_facilities f ON f.slug = r.facility_slug
                WHERE r.facility_id IS NULL AND r.status = 'published' AND r.rating > 0 AND r.rating <= 5
            ) stored_reviews
            GROUP BY stored_reviews.facility_id";
}
