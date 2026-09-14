<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/medical_directory.php';

header('Content-Type: text/plain; charset=utf-8');

$all = medical_directory_default_facilities();
foreach ($all as $i => $f) {
    $num = $i + 1;
    echo "#{$num}: slug={$f['slug']} rating={$f['rating']} reviews={$f['reviews_count']}\n";
    echo "   keys_rating_reviews: " . (isset($f['rating']) ? 'rating_OK' : 'rating_MISSING')
        . " / " . (isset($f['reviews_count']) ? 'reviews_count_OK' : 'reviews_count_MISSING') . "\n";
}
