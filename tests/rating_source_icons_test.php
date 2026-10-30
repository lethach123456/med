<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/medical_rating_source_icons.php';
$cases = [
    [[], 'website'],
    [[['source' => 'Google Maps']], 'google'],
    [[['url' => 'https://www.facebook.com/clinic']], 'facebook'],
    [[['url' => 'https://clinic.vn/reviews']], 'website'],
    [[['url' => 'https://google.com.attacker.example']], 'website'],
];
foreach ($cases as [$sources, $kind]) {
    $html = medical_rating_source_icons($sources);
    if (!str_contains($html, 'review-source-' . $kind . '.svg') || !str_contains($html, 'alt=')) throw new RuntimeException('Incorrect icon: ' . $kind);
}
$html = medical_rating_source_icons([['source' => 'Google'], ['source' => 'Google Maps'], ['source' => 'Facebook']]);
if (substr_count($html, '<img') !== 2) throw new RuntimeException('Sources must be deduplicated');
echo "Rating source icons: 6 checks passed. No DB/network.\n";
