<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/medical_facility_ratings.php';

// Load only the receiver's pure rating functions, never auth, DB or HTTP handling.
$endpoint = file_get_contents(dirname(__DIR__) . '/api/medical/facility-content-update.php');
$start = strpos($endpoint, 'function medical_api_content_rating_list(');
$end = strpos($endpoint, 'function medical_api_content_bool(', $start);
if ($start === false || $end === false) throw new RuntimeException('Receiver rating helpers missing.');
eval(substr($endpoint, $start, $end - $start));

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function (callable $run, string $label) use ($assert): void {
    try { $run(); }
    catch (InvalidArgumentException) { $assert(true, $label); return; }
    $assert(false, $label);
};
$aggregate = ['source' => 'Google Maps', 'score' => 4.8, 'count' => 1000,
    'url' => 'https://maps.google.com/place/test?cid=10&utm_source=chat', 'checked_at' => '2026-10-18'];
$reviews = [
    ['author' => 'A', 'rating' => 5, 'excerpt' => 'Review A'],
    ['author' => 'B', 'rating' => 3, 'excerpt' => 'Review B'],
];
$summary = medical_api_content_rating_summary([$aggregate], $reviews);
$assert($summary === ['rating' => 4.8, 'reviews_count' => 1000, 'basis' => 'external_aggregates'], 'external source wins over two imported samples');
$normal = medical_api_content_normalize_rating_payload(['aggregate_ratings' => json_encode([$aggregate, $aggregate]),
    'reviews_list' => json_encode($reviews)]);
$assert(!isset($normal['aggregate_ratings'], $normal['reviews_list']), 'transport aliases removed after normalization');
$assert(count($normal['aggregate_ratings_json']) === 1, 'duplicate aggregate never doubles external review count');
$assert($normal['aggregate_ratings_json'][0]['url'] === 'https://maps.google.com/place/test?cid=10', 'canonical stored evidence preserves place identifier, removes tracking');
$assert($normal['reviews'] === $reviews, 'JSON string reviews become the same array that is imported');
$assert(medical_api_content_rating_summary([], json_encode($reviews)) === ['rating' => 4.0, 'reviews_count' => 2, 'basis' => 'imported_reviews'], 'JSON string samples are accepted by summary');
$assert(medical_api_content_rating_summary([], []) === null, 'no evidence does not invent or erase a rating');
$assert(medical_api_content_rating_summary([], $reviews, ['rating' => 4.9, 'reviews_count' => 5287])
    === ['rating' => 4.9, 'reviews_count' => 5287, 'basis' => 'legacy'], 'small imported sample does not replace existing legacy total');
$assert(medical_api_content_rating_summary([], $reviews, ['rating' => 3, 'reviews_count' => 2,
    'aggregate_ratings_json' => json_encode([$aggregate])]) === $summary, 'default empty new aggregates preserve old external evidence');
$assert(medical_api_content_rating_summary([], [], ['rating' => 4.9, 'reviews_count' => 5287])
    === ['rating' => 4.9, 'reviews_count' => 5287, 'basis' => 'legacy'], 'content-only update preserves legacy rating and count');
$assert(medical_api_content_rating_summary([array_replace($aggregate, ['score' => 10])], []) === null, 'ten-point source is skipped rather than clamped to five');
$assert(medical_api_content_rating_summary([], [['rating' => 10]]) === null, 'invalid sample never becomes a five-star review');
foreach ([6, -1, 0, INF, NAN] as $score) {
    $reject(fn() => medical_api_content_normalize_rating_payload(['aggregate_ratings' => [array_replace($aggregate, ['score' => $score])]]), 'invalid external score rejected before writes');
}
foreach ([0, -1, 1.5, '1,000', '1000.0', 2147483648] as $count) {
    $reject(fn() => medical_api_content_normalize_rating_payload(['aggregate_ratings_json' => [array_replace($aggregate, ['count' => $count])]]), 'invalid external count rejected before writes');
}
$reject(fn() => medical_api_content_normalize_rating_payload(['aggregate_ratings' => [array_replace($aggregate, ['url' => 'not a URL'])]]), 'malformed proof URL rejected');
$reject(fn() => medical_api_content_normalize_rating_payload(['aggregate_ratings' => 'broken JSON']), 'malformed JSON source list rejected');
$reject(fn() => medical_api_content_normalize_rating_payload(['aggregate_ratings' => $aggregate]), 'single source object cannot masquerade as list');
$reject(fn() => medical_api_content_normalize_rating_payload(['aggregate_ratings_json' => [$aggregate],
    'aggregate_ratings' => [array_replace($aggregate, ['count' => 9])]]), 'conflicting rating aliases rejected');
$reject(fn() => medical_api_content_normalize_rating_payload(['reviews' => $reviews,
    'reviews_list' => [['rating' => 1]]]), 'conflicting sample aliases rejected');
$canonical = medical_api_content_normalize_rating_payload(['aggregate_ratings_json' => [], 'aggregate_ratings' => [$aggregate],
    'reviews' => [], 'reviews_json' => json_encode($reviews)]);
$assert(count($canonical['aggregate_ratings_json']) === 1 && $canonical['reviews'] === $reviews, 'nonempty aliases win over empty template fields');
$assert(medical_api_content_rating_summary($canonical['aggregate_ratings_json'], $canonical['reviews']) === $summary, 'persisted normalized evidence and summary use exactly the same records');
$max = array_replace($aggregate, ['count' => 2147483647]);
$second = array_replace($max, ['url' => 'https://maps.google.com/place/second']);
$reject(fn() => medical_api_content_rating_summary([$max, $second], []), 'combined count overflow rejected rather than truncated');
$filtered = medical_api_content_normalize_rating_payload(['reviews_list' => json_encode([['rating' => 7], ['rating' => '4.5'], ['rating' => 0]])]);
$assert($filtered['reviews'] === [['rating' => '4.5']], 'invalid samples are filtered from both persistence and summary');
$marked = medical_api_content_rating_summary([], [['rating' => 5], ['rating' => 5]], [
    'rating' => 3, 'reviews_count' => 8, 'review_summary_json' => '{"basis":"imported_reviews"}',
]);
$assert($marked === ['rating' => 5.0, 'reviews_count' => 2, 'basis' => 'imported_reviews'], 'known sample totals can be refreshed using fresh review evidence');

echo "PASS facility content ratings: {$checks} checks\n";
