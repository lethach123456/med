<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_review_article_admin.php';
define('MEDREVIEW_SITEMAP_FUNCTIONS_ONLY', true);
require_once dirname(__DIR__) . '/sitemap.php';
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException('FAIL: ' . $label); };
$throws = static function (callable $call, string $label) use ($assert): void {
    try { $call(); } catch (InvalidArgumentException | RuntimeException) { $assert(true, $label); return; }
    $assert(false, $label);
};
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
medreview_with_schema_migration(static fn() => medical_review_articles_migrate($pdo));
$payload = ['title' => 'Phân tích cơ sở mẫu', 'slug' => 'phan-tich-co-so-mau',
    'excerpt' => 'Tóm tắt thận trọng từ dữ liệu công khai của cơ sở.', 'verdict' => 'Thông tin cần tiếp tục đối chiếu trước khi đặt lịch.',
    'content_html' => '<p>' . str_repeat('Đây là nhận định biên tập từ dữ liệu đã lưu, không phải trải nghiệm khám bệnh và không chứng minh kết quả điều trị. ', 5) . '</p>',
    'strengths_json' => ['Có thông tin liên hệ.'], 'considerations_json' => ['Cần xác nhận chi phí trực tiếp.'], 'suitable_for_json' => [],
    'analysis_sections_json' => [], 'sources_json' => [['id'=>'profile','url'=>'https://medreview.vn/co-so-y-te/co-so-mau','title'=>'Hồ sơ cơ sở','platform'=>'MedReview']],
    'evidence_limitations_json' => ['Dữ liệu nguồn chưa được kiểm chứng độc lập.'], 'seo_title'=>'Phân tích cơ sở mẫu',
    'seo_description'=>'Những thông tin cần đối chiếu trước khi chọn cơ sở.', 'researched_at'=>'2026-10-22T10:00:00+07:00'];
$fields = medical_review_articles_validate_article($payload);
$values = ['entity_type'=>'facility','entity_id'=>393,'language_code'=>'vi','status'=>'draft',
    'source_snapshot_json'=>'{"PRIVATE_SOURCE":true}', 'source_revision'=>str_repeat('a',64),
    'source_review_count'=>41,'source_rating'=>4.3,'source_rating_basis'=>'external_aggregates',
    'ai_content_json'=>'{"old":"submission-receipt"}'];
foreach ($fields as $key=>$value) $values[$key] = str_ends_with($key,'_json') ? medical_review_articles_json_encode($value) : $value;
$slots = array_map(static fn($key)=>':'.$key,array_keys($values));
$pdo->prepare('INSERT INTO medical_review_articles('.implode(',',array_keys($values)).') VALUES('.implode(',',$slots).')')->execute(array_combine($slots,array_values($values)));
$read = static fn()=> $pdo->query('SELECT * FROM medical_review_articles WHERE id=1')->fetch();
$original = $read();
$version = medical_review_article_admin_version($original);
$receipt = medical_review_article_admin_save($pdo,1,$payload+['status'=>'published'],$version);
$saved = $read();
$assert($receipt === ['id'=>1,'status'=>'published','saved'=>true,'storage_verified'=>true], 'Admin confirms stored publication');
$assert($saved['published_at'] !== null && $saved['status']==='published', 'Explicit publish stores publication timestamp');
$assert($saved['ai_content_json'] === null && $saved['ai_writer_claim_json'] === null, 'Manual edit invalidates old AI replay receipt');
foreach (['entity_type','entity_id','language_code','source_snapshot_json','source_revision','source_review_count','source_rating','source_rating_basis'] as $key) $assert($saved[$key] === $original[$key], 'Editor cannot change source identity/provenance: '.$key);
$throws(static fn()=>medical_review_article_admin_save($pdo,1,$payload+['status'=>'draft'],$version), 'Stale editor version cannot overwrite newer publication');
$assert($read() === $saved && !$pdo->inTransaction(), 'Stale editor rolls back without changing article');
$throws(static fn()=>medical_review_article_admin_save($pdo,1,$payload+['status'=>'published','entity_id'=>99],medical_review_article_admin_version($saved)), 'Admin cannot move article onto a different facility');
$throws(static fn()=>medical_review_article_admin_save($pdo,1,$payload+['status'=>'invalid'],medical_review_article_admin_version($saved)), 'Unknown publication state rejected');
$throws(static fn()=>medical_review_article_admin_save($pdo,1,array_replace($payload,['content_html'=>'<script>alert(1)</script>']),medical_review_article_admin_version($saved)), 'Unsafe/empty article cannot be published');
$pdo->prepare('UPDATE medical_review_articles SET ai_writer_claim_json=:claim WHERE id=1')->execute([':claim'=>json_encode(['expires_at'=>time()+600,'token_sha256'=>str_repeat('b',64)])]);
$claimed=$read();
$throws(static fn()=>medical_review_article_admin_save($pdo,1,$payload+['status'=>'draft'],medical_review_article_admin_version($claimed)), 'Active AI writer prevents simultaneous manual edit');
$assert($read()===$claimed && !$pdo->inTransaction(), 'Active writer state preserved');
$pdo->exec('UPDATE medical_review_articles SET ai_writer_claim_json=NULL WHERE id=1');
$beforeArchive=$read();
medical_review_article_admin_save($pdo,1,$payload+['status'=>'archived'],medical_review_article_admin_version($beforeArchive));
$assert($read()['status']==='archived', 'Archive hides article without deleting evidence');
$controller=file_get_contents(dirname(__DIR__).'/admin/medical_review_articles.php');
$assert(str_contains($controller,'admin_require_login()') && str_contains($controller,'hash_equals($csrf'), 'Admin form requires login and CSRF');
$assert(str_contains($controller,'name="version"') && str_contains($controller,'role="alert"') && str_contains($controller,'id="article-errors"'), 'Editor retains optimistic version and focusable error summary');
$htaccess=file_get_contents(dirname(__DIR__).'/.htaccess');
foreach (['^review/([^/]+)/?$', '^en/review/([^/]+)/?$'] as $route) $assert(str_contains($htaccess,$route), 'Canonical article route exists: '.$route);
$sitemap=file_get_contents(dirname(__DIR__).'/sitemap.php');
$assert(str_contains($sitemap,"\$sources['review-articles']") && str_contains($sitemap,"a.status='published'") && str_contains($sitemap,'medical_review_articles_path('), 'Sitemap includes published editorial article canonical routes');
$assert(str_contains($sitemap, 'medreview_sitemap_sources($pdo, $language)') && str_contains($sitemap, 'medreview_sitemap_sources($pdo, $lang)'), 'Article sitemaps use the same locale-aware builder in leaf and root indexes');
$pdo->exec("CREATE TABLE medical_facilities(id INTEGER PRIMARY KEY,status TEXT,language_code TEXT,content TEXT)");
$pdo->exec("INSERT INTO medical_facilities VALUES(393,'published',NULL,'<p>Profile</p>')");
$articleMap = medreview_sitemap_sources($pdo)['review-articles'];
$assert((int)$pdo->query($articleMap['count'])->fetchColumn()===0, 'Sitemap excludes archived articles');
$pdo->exec("UPDATE medical_review_articles SET status='published' WHERE id=1");
$assert((int)$pdo->query($articleMap['count'])->fetchColumn()===1, 'Sitemap matches public Vietnamese facility with NULL legacy locale');
$pdo->exec("UPDATE medical_facilities SET language_code='' WHERE id=393");
$assert((int)$pdo->query($articleMap['count'])->fetchColumn()===1, 'Sitemap normalizes blank legacy facility locale to Vietnamese');
$pdo->exec("UPDATE medical_review_articles SET title='' WHERE id=1");
$assert((int)$pdo->query($articleMap['count'])->fetchColumn()===0, 'Sitemap excludes malformed blank-title article');
$pdo->exec("CREATE TABLE medical_reviews (id INTEGER PRIMARY KEY, slug TEXT, status TEXT, excerpt TEXT, story_json TEXT, updated_at TEXT,
    title TEXT DEFAULT 'Review fixture', rating REAL DEFAULT 5, author_text TEXT DEFAULT '', method_text TEXT DEFAULT '', condition_text TEXT DEFAULT '')");
$reviewMap = medreview_sitemap_sources($pdo)['reviews'];
$reviewInsert = $pdo->prepare('INSERT INTO medical_reviews (id,slug,status,excerpt,story_json,updated_at) VALUES(?,?,?,?,?,?)');
foreach ([
    [1, 'review-rich', 'published', str_repeat('x', 80), null, '2026-10-01'],
    [2, 'review-story', 'published', '', str_repeat('y', 120), '2026-10-02'],
    [3, 'review-thin', 'published', str_repeat('x', 79), null, '2026-10-03'],
    [4, 'review-draft', 'draft', str_repeat('x', 200), null, '2026-10-04'],
] as $reviewFixture) $reviewInsert->execute($reviewFixture);
$pdo->exec("INSERT INTO medical_reviews (id,slug,status,excerpt,story_json,updated_at,rating,author_text,method_text,condition_text)
    VALUES (5,'editorial-info','published','" . str_repeat('x', 80) . "',NULL,'2026-10-05',0,'Ban biên tập MedReview','Thông tin tổng hợp','Nội dung biên tập, không phải đánh giá khách hàng')");
$assert((int)$pdo->query($reviewMap['count'])->fetchColumn()===2, 'Sitemap only includes published rated customer reviews with a minimum amount of narrative; excludes editorial profile entries');
$reviewRowsQuery = $pdo->prepare($reviewMap['rows']);
$reviewRowsQuery->bindValue(':limit', 100, PDO::PARAM_INT);
$reviewRowsQuery->bindValue(':offset', 0, PDO::PARAM_INT);
$reviewRowsQuery->execute();
$assert(count($reviewRowsQuery->fetchAll(PDO::FETCH_ASSOC))===2, 'Sitemap review count and page query share the same quality floor');
$pdo->exec("CREATE TABLE medical_toplists (id INTEGER PRIMARY KEY, slug TEXT, status TEXT, entity_type TEXT, language_code TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE medical_toplist_facilities (toplist_id INTEGER, facility_id INTEGER)");
$pdo->exec("CREATE TABLE medical_toplist_doctors (toplist_id INTEGER, doctor_id INTEGER)");
$pdo->exec("CREATE TABLE medical_doctors (id INTEGER PRIMARY KEY, status TEXT, language_code TEXT, content TEXT)");
$toplistMap = medreview_sitemap_sources($pdo)['toplists'];
$pdo->exec("INSERT INTO medical_toplists VALUES (1,'ready-facilities','published','facility','vi','2026-10-06'),
    (2,'empty-toplist','published','facility','vi','2026-10-06'),(3,'draft-member','published','doctor','vi','2026-10-06'),
    (4,'wrong-locale','published','facility','en','2026-10-06')");
$pdo->exec("INSERT INTO medical_toplist_facilities VALUES (1,393),(4,393)");
$pdo->exec("INSERT INTO medical_toplist_doctors VALUES (3,7)");
$pdo->exec("INSERT INTO medical_doctors VALUES (7,'draft','vi','<p>Profile</p>')");
$assert((int)$pdo->query($toplistMap['count'])->fetchColumn()===1, 'Toplist sitemap excludes empty lists, unpublished members, and wrong-language member profiles');
$toplistRowsQuery = $pdo->prepare($toplistMap['rows']);
$toplistRowsQuery->bindValue(':limit', 100, PDO::PARAM_INT);
$toplistRowsQuery->bindValue(':offset', 0, PDO::PARAM_INT);
$toplistRowsQuery->execute();
$assert(array_column($toplistRowsQuery->fetchAll(PDO::FETCH_ASSOC), 'slug')===['ready-facilities'], 'Toplist sitemap page query mirrors its validated count query');
echo 'Review article admin: '.$checks." checks passed; isolated SQLite only.\n";
