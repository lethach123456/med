<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_review_articles.php';

/** Optional article-level pairing. Checking readiness never alters the database. */
function medical_review_article_translations_schema_ready(PDO $pdo): bool
{
    try { $pdo->query('SELECT translation_of_id FROM medical_review_articles LIMIT 0'); return true; }
    catch (PDOException) { return false; }
}

/** Explicit maintenance only; existing articles and facility links are never rewritten. */
function medical_review_article_translations_migrate(PDO $pdo): bool
{
    if (!medreview_schema_migration_allowed()) throw new LogicException('Review article translation changes require an explicit migration scope.');
    medical_review_articles_require_schema($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $changed = false;
    if (!medical_review_article_translations_schema_ready($pdo)) {
        $pdo->exec('ALTER TABLE medical_review_articles ADD COLUMN translation_of_id ' . ($sqlite ? 'INTEGER' : 'INT UNSIGNED') . ' NULL');
        $changed = true;
    }
    $indexName = 'uniq_review_article_translation';
    $indexExists = false; $unique = false; $columns = [];
    if ($sqlite) {
        foreach ($pdo->query('PRAGMA index_list(medical_review_articles)')->fetchAll(PDO::FETCH_ASSOC) as $index) {
            if (($index['name'] ?? '') !== $indexName) continue;
            $indexExists = true; $unique = (int) ($index['unique'] ?? 0) === 1 && (int) ($index['partial'] ?? 0) === 0;
            foreach ($pdo->query('PRAGMA index_info(uniq_review_article_translation)')->fetchAll(PDO::FETCH_ASSOC) as $column) $columns[(int) $column['seqno']] = $column['name'];
        }
    } else {
        foreach ($pdo->query("SHOW INDEX FROM medical_review_articles WHERE Key_name='uniq_review_article_translation'")->fetchAll(PDO::FETCH_ASSOC) as $index) {
            $indexExists = true; $unique = (int) ($index['Non_unique'] ?? 1) === 0;
            $columns[(int) $index['Seq_in_index']] = $index['Column_name'];
        }
    }
    ksort($columns);
    if ($indexExists && (!$unique || array_values($columns) !== ['translation_of_id', 'language_code'])) {
        throw new RuntimeException('Existing review translation index does not have the expected unique columns; inspect it before retrying.');
    }
    if (!$indexExists) {
        if ($pdo->query('SELECT translation_of_id FROM medical_review_articles WHERE translation_of_id IS NOT NULL GROUP BY translation_of_id,language_code HAVING COUNT(*)>1 LIMIT 1')->fetchColumn() !== false) {
            throw new RuntimeException('Duplicate article translation links exist; resolve them before adding the unique index. No articles were merged or deleted.');
        }
        $pdo->exec($sqlite
            ? 'CREATE UNIQUE INDEX uniq_review_article_translation ON medical_review_articles(translation_of_id,language_code)'
            : 'ALTER TABLE medical_review_articles ADD UNIQUE KEY uniq_review_article_translation(translation_of_id,language_code)');
        $changed = true;
    }
    return $changed;
}

/**
 * Null means an unpinned legacy article may use its facility translation family.
 * An explicit but unavailable counterpart returns only the current article, never a guessed replacement.
 */
function medical_review_article_translations_public_alternates(PDO $pdo, array $article): ?array
{
    $id = (int) ($article['id'] ?? 0);
    if ($id < 1 || !medical_review_article_translations_schema_ready($pdo)) return null;
    $public = "a.entity_type='facility' AND a.status='published' AND f.status='published'
        AND a.language_code IN ('vi','en') AND COALESCE(NULLIF(LOWER(TRIM(f.language_code)),''),'vi')=a.language_code
        AND TRIM(a.title)<>'' AND TRIM(a.slug)<>'' AND TRIM(COALESCE(a.content_html,''))<>''";
    $query = $pdo->prepare('SELECT a.id,a.entity_id,a.language_code,a.slug,a.translation_of_id
        FROM medical_review_articles a INNER JOIN medical_facilities f ON f.id=a.entity_id
        WHERE a.id=:id AND ' . $public . ' LIMIT 1');
    $query->execute([':id' => $id]);
    $current = $query->fetch(PDO::FETCH_ASSOC);
    if (!is_array($current) || (int) $current['entity_id'] !== (int) ($article['entity_id'] ?? 0)
        || $current['language_code'] !== ($article['language_code'] ?? null)) return null;
    $locale = (string) $current['language_code'];
    $alternates = [$locale => medical_review_articles_path($locale, (string) $current['slug'])];
    if ($locale === 'en') {
        if ($current['translation_of_id'] === null) return null;
        $parentId = (int) $current['translation_of_id'];
        if ($parentId < 1 || $parentId === $id) return $alternates;
        $query->execute([':id' => $parentId]);
        $parent = $query->fetch(PDO::FETCH_ASSOC);
        if (is_array($parent) && $parent['language_code'] === 'vi' && $parent['translation_of_id'] === null) {
            $alternates = ['vi' => medical_review_articles_path('vi', (string) $parent['slug'])] + $alternates;
        }
        return $alternates;
    }
    // A Vietnamese row is a root, not a second hop in a translation chain.
    if ($current['translation_of_id'] !== null) return $alternates;
    $children = $pdo->prepare("SELECT a.id,a.slug,CASE WHEN " . $public . " THEN 1 ELSE 0 END AS is_public
        FROM medical_review_articles a LEFT JOIN medical_facilities f ON f.id=a.entity_id
        WHERE a.translation_of_id=:parent_id AND a.language_code='en' ORDER BY a.id LIMIT 2");
    $children->execute([':parent_id' => $id]);
    $rows = $children->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) return null;
    if (count($rows) === 1 && (int) $rows[0]['is_public'] === 1) {
        $alternates['en'] = medical_review_articles_path('en', (string) $rows[0]['slug']);
    }
    return $alternates;
}
