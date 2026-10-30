<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_facility_ratings.php';
$checks = 0;
$cases = [
    ['Google', 'google'], ['Google Maps', 'google'],
    ['https://maps.google.com/?cid=123', 'google'],
    ['https://maps.app.goo.gl/example', 'google'],
    ['Facebook', 'facebook'], ['FB', 'facebook'],
    ['https://www.facebook.com/clinic/reviews', 'facebook'],
    ['https://clinic.example/reviews', 'website'],
    ['https://clinic.example', 'website'],
    ['', ''], [null, ''], [[], ''],
    ['https://google.com.attacker.example/reviews', 'website'],
    ['https://facebook.com.attacker.example/reviews', 'website'],
];
foreach ($cases as [$input, $expected]) {
    ++$checks;
    $actual = medical_rating_source_kind($input);
    if ($actual !== $expected) throw new RuntimeException('Source classification mismatch: ' . json_encode($input));
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE medical_facilities(id INTEGER PRIMARY KEY,slug TEXT)');
$pdo->exec('CREATE TABLE medical_reviews(facility_id INTEGER,facility_slug TEXT,rating REAL,status TEXT,source_text TEXT)');
$insert = $pdo->prepare("INSERT INTO medical_reviews VALUES(1,'fixture',5,'published',?)");
foreach ($cases as [$input, $expected]) {
    if (!is_string($input) && $input !== null) continue;
    $pdo->exec('DELETE FROM medical_reviews');
    $insert->execute([$input]);
    $row = $pdo->query(medical_facility_stored_rating_sql())->fetch(PDO::FETCH_ASSOC);
    ++$checks;
    if (($row['stored_review_source_kinds'] ?? '') !== $expected) throw new RuntimeException('SQL/detail source mismatch: ' . json_encode($input));
}
echo "Rating source provenance: {$checks} checks passed. Isolated SQLite; no live DB/network.\n";
