<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$arguments = array_slice($argv, 1);
if (count($arguments) !== 1 || !in_array($arguments[0], ['--check','--apply'], true)) {
    echo "Usage: php scripts/migrate_review_articles.php --check|--apply\n";
    echo "Creates only medical_review_articles; never seeds articles or changes individual reviews.\n";
    exit($arguments === [] || $arguments === ['--help'] ? 0 : 2);
}
require_once dirname(__DIR__) . '/medical_review_articles.php';
try {
    $pdo = db();
    if ($arguments[0] === '--apply') {
        $added = medreview_with_schema_migration(static fn() => medical_review_articles_migrate($pdo));
        echo $added ? "Created medical_review_articles.\n" : "Review article table already ready. No changes.\n";
    }
    $ready = medical_review_articles_schema_ready($pdo);
    echo 'medical_review_articles: ' . ($ready ? 'ready' : 'missing/incomplete') . "\n";
    exit($ready ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Review article migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
