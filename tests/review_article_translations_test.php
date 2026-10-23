<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
require_once dirname(__DIR__) . '/medical_review_article_translations.php';
$includeOutput = ob_get_clean();
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$newPdo = static fn() => new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo = $newPdo();
$assert($includeOutput === '', 'Include has no connection, migration or output');
$assert(!medical_review_article_translations_schema_ready($pdo), 'Missing optional table is detected read-only');
medreview_with_schema_migration(static fn() => medical_review_articles_migrate($pdo));
$assert(medical_review_articles_schema_ready($pdo) && !medical_review_article_translations_schema_ready($pdo), 'Base schema remains available before optional pairing migration');
try { medical_review_article_translations_migrate($pdo); $denied = false; } catch (LogicException) { $denied = true; }
$assert($denied, 'Normal runtime cannot migrate translation schema');
$pdo->exec("INSERT INTO medical_review_articles (id,entity_id,language_code,slug,status,title,content_html) VALUES
    (101,1,'vi','review-viet-a','published','Bài Việt A','<p>Nội dung Việt A</p>'),
    (102,2,'en','review-english-a','published','English review A','<p>English content A</p>'),
    (103,3,'vi','review-viet-b','published','Bài Việt B','<p>Nội dung Việt B</p>'),
    (104,4,'en','review-english-b','published','English review B','<p>English content B</p>')");
$columns = [];
foreach (medical_review_articles_facility_columns() as $field) $columns[] = $field === 'id' ? 'id INTEGER PRIMARY KEY' : $field . ' TEXT NULL';
$pdo->exec('CREATE TABLE medical_facilities (' . implode(',', $columns) . ')');
$pdo->exec("INSERT INTO medical_facilities (id,translation_of_id,slug,status,language_code,name) VALUES
    (1,NULL,'co-so-a','published','vi','Cơ sở A'), (2,1,'facility-a','published','en','Facility A'),
    (3,NULL,'co-so-b','published','vi','Cơ sở B'), (4,3,'facility-b','published','en','Facility B')");
$legacy = medical_review_articles_find_public($pdo, 'review-viet-a', 'vi');
$assert($legacy['alternates'] == ['vi' => '/review/review-viet-a', 'en' => '/en/review/review-english-a'], 'Unmigrated deployments keep legacy facility-family alternatives');
$assert(medreview_with_schema_migration(static fn() => medical_review_article_translations_migrate($pdo)), 'Explicit additive migration adds column and index');
$assert(medical_review_article_translations_schema_ready($pdo), 'Optional schema ready after migration');
$assert(!medreview_with_schema_migration(static fn() => medical_review_article_translations_migrate($pdo)), 'Migration is idempotent');
$assert(!medreview_schema_migration_allowed(), 'Migration permission restored');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_review_articles')->fetchColumn() === 4
    && (int) $pdo->query('SELECT COUNT(*) FROM medical_review_articles WHERE translation_of_id IS NOT NULL')->fetchColumn() === 0, 'Migration neither seeds relations nor changes existing article rows');
$assert(medical_review_article_translations_public_alternates($pdo, $legacy) === null, 'Unpinned migrated article still uses legacy fallback');
$pdo->exec('UPDATE medical_review_articles SET translation_of_id=101 WHERE id=102');
$vi = medical_review_articles_find_public($pdo, 'review-viet-a', 'vi');
$en = medical_review_articles_find_public($pdo, 'review-english-a', 'en');
$expected = ['vi' => '/review/review-viet-a', 'en' => '/en/review/review-english-a'];
$assert($vi['alternates'] === $expected && $en['alternates'] === $expected, 'Both article languages resolve the same explicit primary-key relation');
$pdo->exec("UPDATE medical_facilities SET translation_of_id=NULL WHERE id=2");
$assert(medical_review_articles_find_public($pdo, 'review-viet-a', 'vi')['alternates'] === $expected
    && medical_review_articles_find_public($pdo, 'review-english-a', 'en')['alternates'] === $expected, 'Stored article IDs remain authoritative if facility-family metadata later changes');
$pdo->exec("UPDATE medical_review_articles SET slug='review-viet-a-seo' WHERE id=101");
$assert(medical_review_articles_find_public($pdo, 'review-english-a', 'en')['alternates']['vi'] === '/review/review-viet-a-seo', 'Changing SEO slug resolves the new URL through the same article ID');
$pdo->exec('UPDATE medical_facilities SET translation_of_id=1 WHERE id=2');
$pdo->exec('UPDATE medical_review_articles SET translation_of_id=103 WHERE id=102');
$wrongFamily = medical_review_articles_find_public($pdo, 'review-viet-a-seo', 'vi');
$assert($wrongFamily['alternates'] === ['vi' => '/review/review-viet-a-seo'], 'Legacy fallback does not attach an English article explicitly pinned elsewhere');
$pdo->exec('UPDATE medical_review_articles SET translation_of_id=101 WHERE id=102');
foreach (["status='draft'", "status='archived'", "title=' '", "slug=' '", "content_html=NULL", "language_code='en'", "entity_type='doctor'"] as $invalid) {
    $pdo->exec("UPDATE medical_review_articles SET status='published',title='Bài Việt A',slug='review-viet-a-seo',content_html='<p>Nội dung</p>',language_code='vi',entity_type='facility' WHERE id=101");
    $pdo->exec('UPDATE medical_review_articles SET ' . $invalid . ' WHERE id=101');
    $assert(medical_review_articles_find_public($pdo, 'review-english-a', 'en')['alternates'] === ['en' => '/en/review/review-english-a'], 'A nonpublic or wrong-scope explicit Vietnamese target never falls back to a different article: ' . $invalid);
}
$pdo->exec("UPDATE medical_review_articles SET status='published',title='Bài Việt A',slug='review-viet-a-seo',content_html='<p>Nội dung</p>',language_code='vi',entity_type='facility' WHERE id=101");
foreach (["status='draft'", "status='archived'", "language_code='en'"] as $invalidFacility) {
    $pdo->exec("UPDATE medical_facilities SET status='published',language_code='vi' WHERE id=1");
    $pdo->exec('UPDATE medical_facilities SET ' . $invalidFacility . ' WHERE id=1');
    $assert(medical_review_articles_find_public($pdo, 'review-english-a', 'en')['alternates'] === ['en' => '/en/review/review-english-a'], 'Facility publication and language bound explicit public target: ' . $invalidFacility);
}
$pdo->exec("UPDATE medical_facilities SET status='published',language_code='vi' WHERE id=1");
$pdo->exec("UPDATE medical_review_articles SET status='draft' WHERE id=102");
$assert(medical_review_articles_find_public($pdo, 'review-viet-a-seo', 'vi')['alternates'] === ['vi' => '/review/review-viet-a-seo'], 'Draft explicit English counterpart is not published through legacy fallback');
$pdo->exec("UPDATE medical_review_articles SET status='published' WHERE id=102");
foreach ([0, 99999, 102, 104] as $invalidParent) {
    $pdo->exec('UPDATE medical_review_articles SET translation_of_id=' . $invalidParent . ' WHERE id=102');
    $assert(medical_review_articles_find_public($pdo, 'review-english-a', 'en')['alternates'] === ['en' => '/en/review/review-english-a'], 'Invalid explicit reference never guesses a replacement: ' . $invalidParent);
}
$pdo->exec('UPDATE medical_review_articles SET translation_of_id=101 WHERE id=102');
$pdo->exec("UPDATE medical_review_articles SET ai_content_json='{\"SECRET_RECEIPT\":true}',ai_writer_claim_json='{\"SECRET_TOKEN\":true}' WHERE id=102");
$publicEnglish = medical_review_articles_find_public($pdo, 'review-english-a', 'en');
$assert(!array_key_exists('translation_of_id', $publicEnglish) && !str_contains(json_encode($publicEnglish), 'SECRET_'), 'Public DTO never exposes internal translation column or AI metadata');
try { $pdo->exec('UPDATE medical_review_articles SET translation_of_id=101 WHERE id=104'); $duplicateBlocked = false; } catch (PDOException) { $duplicateBlocked = true; }
$assert($duplicateBlocked, 'Unique parent/language index prevents a second English child');
$pdo->exec('DROP INDEX uniq_review_article_translation');
$pdo->exec('UPDATE medical_review_articles SET translation_of_id=101 WHERE id=104');
try { medreview_with_schema_migration(static fn() => medical_review_article_translations_migrate($pdo)); $duplicatesRejected = false; } catch (RuntimeException) { $duplicatesRejected = true; }
$assert($duplicatesRejected && (int) $pdo->query('SELECT COUNT(*) FROM medical_review_articles')->fetchColumn() === 4, 'Migration detects duplicate links without merging or deleting articles');
$pdo->exec('UPDATE medical_review_articles SET translation_of_id=NULL WHERE id=104');
$assert(medreview_with_schema_migration(static fn() => medical_review_article_translations_migrate($pdo)), 'Partial additive migration can be resumed after duplicate links are repaired');
$pdo->exec('DROP INDEX uniq_review_article_translation');
$pdo->exec('CREATE INDEX uniq_review_article_translation ON medical_review_articles(translation_of_id)');
try { medreview_with_schema_migration(static fn() => medical_review_article_translations_migrate($pdo)); $badIndexRejected = false; } catch (RuntimeException) { $badIndexRejected = true; }
$assert($badIndexRejected, 'Migration never silently accepts or overwrites an incompatible same-name index');
echo 'Review article translations: ' . $checks . " checks passed.\n";
