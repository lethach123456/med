<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$arguments = array_slice($argv, 1);
if (count($arguments) !== 1 || !in_array($arguments[0], ['--check', '--apply'], true)) {
    echo "Usage: php scripts/migrate_review_article_translations.php --check|--apply\n";
    echo "Adds optional article-level VI/EN links; existing articles and facility links are preserved.\n";
    exit($arguments === [] || $arguments === ['--help'] ? 0 : 2);
}
require_once dirname(__DIR__) . '/medical_review_article_translations.php';
try {
    $pdo = db();
    if ($arguments[0] === '--apply') {
        $changed = medreview_with_schema_migration(static fn() => medical_review_article_translations_migrate($pdo));
        echo $changed ? "Added review article translation schema.\n" : "Review article translation schema already ready. No changes.\n";
    }
    $ready = medical_review_article_translations_schema_ready($pdo);
    echo 'medical_review_articles.translation_of_id: ' . ($ready ? 'ready' : 'missing') . "\n";
    exit($ready ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Review article translation migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
