<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_facility_ratings.php';

// Pure fixtures: no configured database, HTTP requests, or production data.
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$source = ['source' => 'Google Maps', 'score' => 4.8, 'count' => 1000,
    'url' => 'https://maps.google.com/?cid=123', 'checked_at' => '2026-10-01'];
$summary = medical_facility_rating_summary([
    'aggregate_ratings_json' => json_encode([$source]), 'rating' => 5, 'reviews_count' => 3,
], ['rating' => 5, 'count' => 3]);
$assert($summary['rating'] === 4.8 && $summary['reviews_count'] === 1000, 'JSON source recovers aggregate overwritten by three imported samples');
$assert($summary['basis'] === 'external_aggregates' && count($summary['sources']) === 1, 'summary identifies source basis');
$assert($summary['reviews_count'] !== 1003, 'imported samples are never added to aggregate count');
$second = ['source' => 'Facebook', 'score' => 4.0, 'count' => 100, 'url' => 'https://facebook.com/facility/reviews'];
$weighted = medical_facility_rating_summary(['aggregate_ratings' => [$source, $second], 'rating' => 1, 'reviews_count' => 1]);
$assert($weighted['rating'] === 4.7 && $weighted['reviews_count'] === 1100, 'distinct valid sources preserve count-weighted algorithm');

$url = 'https://Reviews.example.org/facility/?utm_source=chat&cid=123&query=clinic#reviews';
$alias = 'https://reviews.example.org/facility?query=clinic&cid=123';
$old = array_replace($source, ['url' => $url, 'checked_at' => '2026-09-30', 'count' => 10]);
$new = array_replace($source, ['source' => 'Google', 'url' => $alias, 'checked_at' => '2026-10-02', 'score' => 4.6, 'count' => 15]);
$dedup = medical_facility_rating_normalize_aggregates([$old, $new, $new]);
$assert(count($dedup) === 1 && $dedup[0]['count'] === 15 && $dedup[0]['score'] === 4.6, 'same canonical URL counts once despite renamed publisher and repeated input');
$assert($dedup[0]['url'] === 'https://reviews.example.org/facility?cid=123&query=clinic', 'normalization keeps location identity, drops fragment and known tracking');
$assert(medical_facility_rating_normalize_aggregates([$new, $old]) === $dedup, 'latest dated observation wins in either input order');
$undated = array_replace($new, ['score' => 1, 'checked_at' => '']);
$assert(medical_facility_rating_normalize_aggregates([$old, $undated])[0]['score'] === $old['score'], 'undated conflicts do not replace a dated observation');
$assert(medical_facility_rating_normalize_aggregates([$undated, $new])[0]['score'] === $new['score'], 'dated observation replaces undated source');
$assert(medical_facility_rating_normalize_aggregates([$new, array_replace($new, ['count' => 999])])[0]['count'] === 15, 'equal-date conflict keeps first deterministic record');
$assert(count(medical_facility_rating_normalize_aggregates([$source, array_replace($source, ['url' => 'https://maps.google.com/?cid=456'])])) === 2, 'different Google Maps cid identifiers do not collapse');
$assert(count(medical_facility_rating_normalize_aggregates([$source, array_replace($source, ['url' => 'https://maps.google.com/?query=different-clinic'])])) === 2, 'different Maps query identities do not collapse');
$assert(count(medical_facility_rating_normalize_aggregates([$source, array_replace($source, ['url' => 'https://maps.google.com/?cid=123&hl=vi'])])) === 2, 'unknown query parameters are retained conservatively');
$assert(medical_facility_rating_normalize_aggregates($dedup) === $dedup, 'canonical source rows remain stable when mapped again');

$legacySource = ['source' => 'Google Maps', 'score' => '4.9', 'count' => '005287'];
$normalLegacy = medical_facility_rating_normalize_aggregates([$legacySource, array_replace($legacySource, ['source' => ' google   maps '])]);
$assert(count($normalLegacy) === 1 && $normalLegacy[0]['url'] === '' && $normalLegacy[0]['count'] === 5287, 'old source rows without URL are preserved and deduplicate by source');
$sourceAlias = ['source_text' => 'Google Maps', 'score' => '4.8', 'count' => '1000', 'source_url' => $source['url']];
$assert(medical_facility_rating_normalize_aggregates([$sourceAlias])[0]['url'] === medical_facility_rating_source_url($source['url']), 'supported legacy field aliases normalize');
$assert(medical_facility_rating_normalize_aggregates([$source, $second]) === medical_facility_rating_normalize_aggregates(json_encode([$source, $second])), 'JSON and native lists have the same interpretation');

foreach ([6, 100, -1, 0, INF, -INF, NAN, true, null, [], '', 'five', '4,8'] as $invalidScore) {
    $assert(medical_facility_rating_normalize_aggregates([array_replace($source, ['score' => $invalidScore])]) === [], 'invalid score is rejected rather than clamped');
}
foreach ([0, -1, 3.5, 3.0, INF, NAN, true, null, [], '', '1,000', '1.000', '1 000', '3.0', '1e3', '+3', '2147483648'] as $invalidCount) {
    $assert(medical_facility_rating_normalize_aggregates([array_replace($source, ['count' => $invalidCount])]) === [], 'invalid count is rejected rather than truncated');
}
foreach (['javascript:alert(1)', '/relative', '//example.org/rating', 'https://user:pass@example.org/reviews', "https://example.org/\nreviews", '[Source](https://example.org)', ['https://example.org']] as $invalidUrl) {
    $assert(medical_facility_rating_normalize_aggregates([array_replace($source, ['url' => $invalidUrl])]) === [], 'malformed provided URL cannot become URL-less evidence');
}
foreach ([null, true, 1, 'not json', '{"score":5}', ['source' => 'Maps'], [null], [[]], [['source' => []]], [array_replace($source, ['source' => ''])], [array_replace($source, ['source' => '<b></b>'])]] as $invalidSources) {
    $assert(medical_facility_rating_normalize_aggregates($invalidSources) === [], 'invalid list or source cannot manufacture ratings');
}
$assert(medical_facility_rating_normalize_aggregates([array_replace($source, ['checked_at' => '2026-02-30'])])[0]['checked_at'] === '', 'impossible observation date cannot override source');
$assert(medical_facility_rating_normalize_aggregates([array_replace($source, ['checked_at' => 'tomorrow'])])[0]['checked_at'] === '', 'relative date is not an observation timestamp');
$timestamp = array_replace($new, ['checked_at' => '2026-10-02T23:00:00+07:00']);
$olderTimestamp = array_replace($new, ['checked_at' => '2026-10-02T10:00:00Z', 'count' => 7]);
$assert(medical_facility_rating_normalize_aggregates([$olderTimestamp, $timestamp])[0]['count'] === 15, 'timestamp timezone determines newest observation');

$legacy = medical_facility_rating_summary(['rating' => '4.9', 'reviews_count' => '5287'], ['rating' => 5, 'count' => 12]);
$assert($legacy === ['rating' => 4.9, 'reviews_count' => 5287, 'basis' => 'legacy', 'sources' => []], 'existing numeric pair stays together when no source JSON exists');
$assert(medical_facility_rating_summary(['aggregate_ratings_json' => '[]', 'rating' => '4.9', 'reviews_count' => '5287']) === $legacy, 'explicit empty aggregates do not erase existing public rating');
$assert(medical_facility_rating_summary(['aggregate_ratings' => [], 'aggregate_ratings_json' => json_encode([$source])])['reviews_count'] === 1000, 'empty mapped alias does not hide nonempty source JSON');
$fallback = medical_facility_rating_summary(['rating' => 4.9, 'reviews_count' => 0], ['rating' => 4.7, 'count' => 3]);
$assert($fallback === ['rating' => 4.7, 'reviews_count' => 3, 'basis' => 'imported_reviews', 'sources' => []], 'actual stored reviews are final fallback when legacy pair is unusable');
$assert(medical_facility_rating_summary([], ['rating' => '4.7', 'reviews_count' => '3']) === $fallback, 'review fallback supports reviews_count alias');
$marked = ['rating' => 4.7, 'reviews_count' => 3, 'review_summary_json' => '{"basis":"imported_reviews"}'];
$assert(medical_facility_rating_summary($marked, ['rating' => 4.5, 'count' => 4]) === [
    'rating' => 4.5, 'reviews_count' => 4, 'basis' => 'imported_reviews', 'sources' => [],
], 'known imported total refreshes after another stored review is added');
$assert(medical_facility_rating_summary($marked, ['rating' => 0, 'count' => 0]) === [
    'rating' => 0.0, 'reviews_count' => 0, 'basis' => 'imported_reviews', 'sources' => [],
], 'removing all stored reviews clears a known imported total');
$assert(medical_facility_rating_summary($marked) === $fallback, 'known imported scalar pair remains available without fresh review summary');
$assert(medical_facility_rating_summary(['rating_basis' => 'imported_reviews', 'rating' => 4.7, 'reviews_count' => 3]) === $fallback, 'explicit rating basis supports mapped data');
$marked['aggregate_ratings'] = [$source];
$assert(medical_facility_rating_summary($marked, ['rating' => 0, 'count' => 0])['basis'] === 'external_aggregates', 'external evidence takes precedence over old imported metadata');
$none = ['rating' => 0.0, 'reviews_count' => 0, 'basis' => 'none', 'sources' => []];
foreach ([[], ['rating' => 5], ['reviews_count' => 2], ['rating' => 8, 'reviews_count' => 2], ['rating' => 5, 'reviews_count' => '1,000'], ['aggregate_ratings_json' => 'invalid']] as $emptyRow) {
    $assert(medical_facility_rating_summary($emptyRow) === $none, 'no complete valid source returns an unrated pair');
}
$assert(medical_facility_rating_summary([], ['rating' => 9, 'count' => 4]) === $none, 'invalid fallback rating is never clamped');
$assert(medical_facility_rating_summary(['aggregate_ratings' => [$source]])['sources'][0] === medical_facility_rating_normalize_aggregates([$source])[0], 'summary exposes normalized provenance without verification claims');

fwrite(STDOUT, "PASS: {$checks} facility rating checks\n");
