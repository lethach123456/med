<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/medical_directory.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

$forceReset = true;

echo "=== Seeding Medical Directory Data ===\n\n";

try {
    medical_directory_ensure_tables($pdo);
    echo "[OK] Tables ensured (created if missing).\n";
} catch (Throwable $e) {
    echo "[FAIL] Ensure tables: " . $e->getMessage() . "\n";
    exit(1);
}

$beforeFc = (int) $pdo->query("SELECT COUNT(*) FROM medical_facilities")->fetchColumn();
$beforeRc = (int) $pdo->query("SELECT COUNT(*) FROM medical_reviews")->fetchColumn();
$beforeDc = (int) $pdo->query("SELECT COUNT(*) FROM medical_doctors")->fetchColumn();
echo "[BEFORE] facilities={$beforeFc}, reviews={$beforeRc}, doctors={$beforeDc}\n";

if ($forceReset) {
    echo "\n[RESET] Truncating medical tables (force seed latest 10 facilities)...\n";
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE medical_reviews");
        $pdo->exec("TRUNCATE TABLE medical_doctors");
        $pdo->exec("TRUNCATE TABLE medical_facilities");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "[OK] Tables cleared.\n";
    } catch (Throwable $e) {
        echo "[FAIL] Clear tables: " . $e->getMessage() . "\n";
        exit(1);
    }
}

try {
    medical_directory_seed_defaults($pdo);
    echo "[OK] Seed defaults executed.\n";
} catch (Throwable $e) {
    echo "[FAIL] Seed defaults: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Row counts ---\n";

$fc = (int) $pdo->query("SELECT COUNT(*) FROM medical_facilities")->fetchColumn();
echo "medical_facilities: {$fc} rows\n";

$rc = (int) $pdo->query("SELECT COUNT(*) FROM medical_reviews")->fetchColumn();
echo "medical_reviews:    {$rc} rows\n";

$dc = (int) $pdo->query("SELECT COUNT(*) FROM medical_doctors")->fetchColumn();
echo "medical_doctors:    {$dc} rows\n";

echo "\n--- Facilities list (by display_order) ---\n";
$rows = $pdo->query("SELECT id, slug, name, rating, reviews_count, followers_count, display_order FROM medical_facilities ORDER BY display_order ASC, id ASC")->fetchAll();
foreach ($rows as $r) {
    echo sprintf(
        "  %2d. [id:%2d] %-28s | %s ★ | %4d rv | %5d flw | %s\n",
        (int)$r['display_order'],
        (int)$r['id'],
        $r['slug'],
        $r['rating'],
        (int)$r['reviews_count'],
        (int)$r['followers_count'],
        $r['name']
    );
}

echo "\n--- Reviews sample (by facility) ---\n";
$rows = $pdo->query("SELECT id, facility_slug, title, rating, author_text FROM medical_reviews ORDER BY display_order ASC, id ASC LIMIT 10")->fetchAll();
foreach ($rows as $r) {
    echo sprintf(
        "  [id:%2d] %-22s | %s ★ | %-14s | %s\n",
        (int)$r['id'],
        $r['facility_slug'],
        $r['rating'],
        $r['author_text'],
        $r['title']
    );
}

echo "\n--- Doctors list ---\n";
$rows = $pdo->query("SELECT id, slug, name, specialty_text, facility_slug, rating FROM medical_doctors ORDER BY display_order ASC, id ASC")->fetchAll();
foreach ($rows as $r) {
    echo sprintf(
        "  [id:%d] %-22s | %s ★ | %-18s | @ %s\n",
        (int)$r['id'],
        $r['slug'],
        $r['rating'],
        $r['specialty_text'],
        $r['facility_slug']
    );
}

echo "\nDone.\n";
