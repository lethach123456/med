<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MEDREVIEW_SITEMAP_FUNCTIONS_ONLY', true);
require_once dirname(__DIR__) . '/sitemap.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};
$database = static fn (): PDO => new PDO('sqlite::memory:', null, null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$rows = static function (PDO $pdo, array $source) use ($assert): array {
    $stmt = $pdo->prepare($source['rows']);
    $stmt->bindValue(':limit', 10000, PDO::PARAM_INT);
    $stmt->bindValue(':offset', 0, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll();
    $assert(medreview_sitemap_source_count($pdo, $source) === count($result), 'Index count and leaf rows agree');
    $assert(!str_contains($source['rows'], 't.t.'), 'Aliased projections never duplicate the table alias');
    return $result;
};
$slugs = static fn (array $rows): array => array_column($rows, 'slug');

$empty = $database();
$assert(medreview_sitemap_sources($empty) === [] && medreview_sitemap_sources($empty, 'en') === [], 'Empty schemas omit unsupported maps');
$assert(medreview_sitemap_handbook_count($empty, 'vi') === 0 && medreview_sitemap_handbook_items($empty, 'en', 1) === [], 'Missing CMS tables safely omit handbook entries');
$assert(!in_array('/blog', medreview_sitemap_static_paths('vi', $empty), true)
    && !in_array('/news', medreview_sitemap_static_paths('en', $empty), true), 'Redirected blog/news listing routes stay out of static sitemap');

// An old facility-only install has no language, timestamps, entity_type, or
// doctor relation table. Its valid Vietnamese toplists must remain available.
$old = $database();
$old->exec('CREATE TABLE medical_facilities(id INTEGER PRIMARY KEY,slug TEXT,status TEXT,content TEXT)');
$old->exec("INSERT INTO medical_facilities VALUES(1,'good','published','<p>Profile</p>'),(2,'blank','published',''),(3,'draft','draft','Profile')");
$old->exec('CREATE TABLE medical_toplists(id INTEGER PRIMARY KEY,slug TEXT,status TEXT)');
$old->exec("INSERT INTO medical_toplists VALUES(1,'ready','published'),(2,'blank-profile','published'),(3,'draft-profile','published'),(4,'orphan','published'),(5,'empty','published')");
$old->exec('CREATE TABLE medical_toplist_facilities(toplist_id INTEGER,facility_id INTEGER)');
$old->exec('INSERT INTO medical_toplist_facilities VALUES(1,1),(2,2),(3,3),(4,99)');
$oldSources = medreview_sitemap_sources($old);
$assert($slugs($rows($old, $oldSources['facilities'])) === ['good'], 'Legacy profile map retains only authored public profiles');
$legacyRows = $rows($old, $oldSources['toplists']);
$assert($slugs($legacyRows) === ['ready'], 'Legacy facility-only toplists still require complete public linked profiles');
$assert($legacyRows[0]['language_code'] === 'vi' && $legacyRows[0]['updated_at'] === '', 'Missing optional columns use explicit literal projections');
$assert($oldSources['toplists']['path']($legacyRows[0]) === '/toplist/ready', 'Legacy toplist projection builds the canonical route');
$assert($rows($old, medreview_sitemap_sources($old, 'en')['toplists']) === [], 'Unlocalized legacy records cannot leak into English sitemap');
$assert(!isset($oldSources['doctors'], $oldSources['review-articles']), 'Absent newer source tables are omitted');
$assert(!medreview_sitemap_table_exists($empty, 'medical_facilities'), 'Schema cache is isolated per PDO connection');

// Partly migrated locales are real data even before translation_of_id exists.
$old->exec('ALTER TABLE medical_facilities ADD COLUMN language_code TEXT');
$old->exec('ALTER TABLE medical_toplists ADD COLUMN language_code TEXT');
$old->exec('ALTER TABLE medical_toplists ADD COLUMN entity_type TEXT DEFAULT \'facility\'');
$old->exec("INSERT INTO medical_facilities VALUES(4,'english','published','English profile',' EN ')");
$old->exec("INSERT INTO medical_toplists VALUES(6,'english-top','published','en','facility'),(7,'wrong-language','published','en','facility'),(8,'unsupported-doctors','published','vi','doctor'),(9,'unsupported-mixed','published','vi','mixed')");
$old->exec('INSERT INTO medical_toplist_facilities VALUES(6,4),(7,1),(8,1),(9,1)');
$assert($slugs($rows($old, medreview_sitemap_sources($old)['toplists'])) === ['ready'], 'Unsupported doctor/mixed types are excluded when doctor relation table is absent');
$english = $rows($old, medreview_sitemap_sources($old, 'en')['toplists']);
$assert($slugs($english) === ['english-top'], 'Locale-only schema respects English without translation_of_id');
$assert($oldSources['toplists']['path']($english[0]) === '/en/toplist/english-top', 'Normalized English data gets the English canonical route');

$old->exec('CREATE TABLE medical_doctors(id INTEGER PRIMARY KEY,slug TEXT,status TEXT,content TEXT,language_code TEXT,created_at TEXT)');
$old->exec("INSERT INTO medical_doctors VALUES(1,'doctor','published','Profile','vi','2026-10-24'),(2,'null-status',NULL,'Profile','vi','2026-10-24')");
$old->exec('CREATE TABLE medical_toplist_doctors(toplist_id INTEGER,doctor_id INTEGER)');
$old->exec('INSERT INTO medical_toplist_doctors VALUES(8,1),(9,1)');
$old->exec("INSERT INTO medical_toplists VALUES(10,'unpublished-doctor','published','vi','doctor'),(11,'missing-doctor','published','vi','doctor')");
$old->exec('INSERT INTO medical_toplist_doctors VALUES(10,2),(11,99)');
$schemaBefore = $old->query('SELECT sql FROM sqlite_master ORDER BY name')->fetchAll();
$full = medreview_sitemap_sources($old);
$assert($slugs($rows($old, $full['toplists'])) === ['ready','unsupported-doctors','unsupported-mixed'], 'Available relation tables support doctor/mixed lists and reject NULL status or missing profiles');
$doctorRows = $rows($old, $full['doctors']);
$assert($doctorRows[0]['updated_at'] === '2026-10-24', 'Timestamp projection falls back to created_at');
$assert($schemaBefore === $old->query('SELECT sql FROM sqlite_master ORDER BY name')->fetchAll(), 'Building and reading maps never changes schema');

$old->exec('CREATE TABLE medical_review_articles(id INTEGER PRIMARY KEY,entity_type TEXT,entity_id INTEGER,status TEXT,slug TEXT,title TEXT,content_html TEXT)');
$old->exec("INSERT INTO medical_review_articles VALUES(1,'facility',1,'published','review','Review','<p>Review</p>')");
$assert($slugs($rows($old, medreview_sitemap_sources($old)['review-articles'])) === ['review'], 'Legacy review article schema without language or timestamp still renders safely');
$assert($rows($old, medreview_sitemap_sources($old, 'en')['review-articles']) === [], 'Unlocalized review articles remain Vietnamese only');

$cms = $database();
$cms->exec('CREATE TABLE posts(id INTEGER PRIMARY KEY,category_id INTEGER,slug TEXT,status TEXT,content TEXT)');
$cms->exec('CREATE TABLE categories(id INTEGER PRIMARY KEY,slug TEXT)');
$cms->exec("INSERT INTO categories VALUES(1,'blog'),(2,'blog-en'),(3,'dich-vu'),(4,'dich-vu-en')");
$cms->exec("INSERT INTO posts VALUES(1,1,'viet-guide','published','Guide'),(2,2,'english-guide','published','Guide'),(3,3,'service','published','Service'),(4,4,'english-service','published','Service')");
$assert(medreview_sitemap_handbook_count($cms, 'vi') === 1 && medreview_sitemap_handbook_count($cms, 'en') === 1, 'Legacy handbook category fallback splits locales');
$assert(array_column(medreview_sitemap_handbook_items($cms, 'vi', 1), 'path') === ['/cam-nang/viet-guide']
    && array_column(medreview_sitemap_handbook_items($cms, 'en', 1), 'path') === ['/en/cam-nang/english-guide'], 'Handbook entries use only canonical localized routes without optional timestamp');
$assert($slugs($rows($cms, medreview_sitemap_sources($cms)['articles'])) === ['service']
    && $slugs($rows($cms, medreview_sitemap_sources($cms, 'en')['articles'])) === ['english-service'], 'Old article maps retain service posts but never duplicate handbook posts');
$cms->exec('ALTER TABLE posts ADD COLUMN language TEXT');
$cms->exec("UPDATE posts SET language='en' WHERE id=1");
$assert(medreview_sitemap_handbook_count($cms, 'vi') === 1 && medreview_sitemap_handbook_count($cms, 'en') === 1, 'Available post locale overrides category and normalizes NULL to Vietnamese');
$assert(array_column(medreview_sitemap_handbook_items($cms, 'en', 1), 'path') === ['/en/cam-nang/viet-guide'], 'Partly migrated post locales follow the actual content language');

echo "Sitemap schema compatibility: {$checks} checks passed; isolated SQLite only.\n";
