<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Execute the actual editor in separate PHP processes: its redirects/exit must
// not stop the test runner. No production bootstrap, sessions or database load.
$runner = <<<'PHP'
$root = $argv[1];
$scenario = $argv[2];
$_SESSION = ['admin_user_id' => 1, 'admin_role' => 'staff', 'admin_csrf_token' => str_repeat('a', 64)];
$_SERVER['SCRIPT_NAME'] = '/admin/content_post_edit.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, language TEXT)');
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER, title TEXT, slug TEXT UNIQUE, language TEXT, featured_image_url TEXT, template INTEGER DEFAULT 0, excerpt TEXT, content TEXT, seo_title TEXT, seo_description TEXT, seo_keywords TEXT, status TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
if (!str_contains($scenario,'missing-category')) $pdo->exec("INSERT INTO categories VALUES (1,'Cẩm nang','blog','vi'), (2,'Guides','blog-en','en')");
$state = ['headers' => [], 'json' => null, 'status' => null, 'flash' => null];
function db(): PDO { return $GLOBALS['pdo']; }
function admin_require_login(): void {}
function admin_is_logged_in(): bool { return true; }
function admin_is_admin(): bool { return false; }
function admin_url(string $path = ''): string { return '/admin/' . ltrim($path, '/'); }
function site_url(string $path = ''): string { return '/' . ltrim($path, '/'); }
function admin_csrf_token(): string { return $_SESSION['admin_csrf_token']; }
function flash_toast_set(string $type, string $message, ?string $icon = null): void { $GLOBALS['state']['flash'] = compact('type', 'message'); }
function handbook_editor_fixture_header(string $header): void { $GLOBALS['state']['headers'][] = $header; }
function db_has_column(PDO $pdo, string $table, string $column): bool { return in_array($column, array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name'), true); }
function json_response(array $payload, int $statusCode = 200): void { $GLOBALS['state']['json'] = $payload; $GLOBALS['state']['status'] = $statusCode; exit; }
$bootstrap = file_get_contents($root . '/admin/_bootstrap.php');
$start = strpos($bootstrap, 'function slugify('); $end = strpos($bootstrap, 'function flash_toast_set(');
eval(substr($bootstrap, $start, $end - $start));
$start = strpos($bootstrap, 'function normalize_content_language('); $end = strpos($bootstrap, 'function ensure_content_language_columns(');
eval(substr($bootstrap, $start, $end - $start));
$language = str_starts_with($scenario, 'en-') ? 'en' : 'vi';
$category = str_contains($scenario,'missing-category') ? 0 : ($language === 'en' ? 2 : 1);
$_GET = ['handbook' => '1', 'language' => $language, 'category_id' => (string) $category];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = str_contains($scenario, '-post') ? 'POST' : 'GET';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST = ['handbook' => '1', 'csrf_token' => str_repeat('a', 64), 'category_id' => (string) $category, 'language' => $language,
        'title' => 'Chủ đề Cẩm nang', 'slug' => 'new-topic-' . $language, 'status' => 'draft', 'template' => '0',
        'excerpt' => '', 'content' => '', 'seo_title' => '', 'seo_description' => '', 'seo_keywords' => '', 'featured_image_url' => ''];
}
if ($scenario === 'en-modal-post') { $_GET = ['modal' => '1', 'handbook' => '1']; $_POST['modal'] = '1'; }
if ($scenario === 'en-queryless-post') $_GET = [];
if ($scenario === 'en-switched-post') { $_GET['language']='vi'; $_GET['category_id']='1'; $_POST['category_id']='1'; }
if ($scenario === 'vi-bad-csrf-post') $_POST['csrf_token'] = 'wrong';
if ($scenario === 'vi-bad-category-post') $_POST['category_id'] = '2';
if ($scenario === 'vi-missing-category-bad-csrf-post') $_POST['csrf_token'] = 'wrong';
if ($scenario === 'vi-missing-category-empty-title-post') $_POST['title'] = '';
if (str_starts_with($scenario,'vi-linked-') || str_contains($scenario,'-add-linked-') || $scenario==='en-add-same-language-post' || $scenario==='vi-bad-category-post') {
    $pdo->exec('CREATE TABLE handbook_translations(vi_post_id INTEGER PRIMARY KEY,en_post_id INTEGER UNIQUE,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec("INSERT INTO posts(id,category_id,title,slug,language,status,excerpt,content) VALUES(1,1,'Chăm sóc sức khỏe chủ động','cham-soc-suc-khoe','vi','draft','Chủ đề Cẩm nang dành cho người đọc MedReview.',''),(2,2,'A guide to proactive healthcare','proactive-healthcare','en','draft','','')");
    if (str_starts_with($scenario,'vi-linked-') || $scenario==='vi-bad-category-post') {
        $pdo->exec('INSERT INTO handbook_translations(vi_post_id,en_post_id) VALUES(1,2)');
        $_GET['id']='1';
        if ($_SERVER['REQUEST_METHOD']==='POST') { $_POST['id']='1'; $_POST['linked_post_id']='2'; }
    } elseif ($_SERVER['REQUEST_METHOD']==='POST') { $_POST['linked_post_id']=$scenario==='en-add-same-language-post'?'2':'1'; }
}
if ($scenario==='vi-linked-language-change-post') $_POST['language']='en';
ob_start();
register_shutdown_function(static function () use ($pdo, &$state): void {
    $html = ob_get_clean();
    $links=$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='handbook_translations'")->fetchColumn() ? $pdo->query('SELECT vi_post_id,en_post_id FROM handbook_translations')->fetchAll() : [];
    echo json_encode(['state' => $state, 'html' => $html, 'rows' => $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll(), 'categories'=>$pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll(), 'links'=>$links, 'fatal' => error_get_last()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
});
$source = file_get_contents($root . '/admin/content_post_edit.php');
$source = str_replace("require_once __DIR__ . '/_bootstrap.php';", '', $source);
$source = str_replace('__DIR__', var_export($root . '/admin', true), $source);
$source = str_replace('header(', 'handbook_editor_fixture_header(', $source);
eval(substr($source, 5));
PHP;

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
$run = static function (string $scenario) use ($runner): array {
    $process = proc_open([PHP_BINARY, '-r', $runner, dirname(__DIR__), $scenario], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start fixture process.');
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($process);
    if ($status !== 0 || trim($error) !== '') throw new RuntimeException($scenario . ': ' . $error);
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
};
if (($argv[1]??'')==='--render-fixture') {
    $destination=$argv[2]??'';
    if(!str_starts_with($destination,sys_get_temp_dir().'/')&&!str_starts_with($destination,'/private/tmp/'))throw new RuntimeException('Preview must be a temporary artifact');
    file_put_contents($destination,$run($argv[3]??'vi-add-linked-get')['html']);
    echo"Rendered isolated editor fixture: ".$destination."\n";exit;
}
foreach (['vi-get' => 'vi', 'en-get' => 'en'] as $scenario => $language) {
    $result = $run($scenario); $html = $result['html'];
    $assert(str_contains($html, 'id="postEditForm"') && str_contains($html, 'name="handbook" value="1"'), $scenario . ' opens full-page handbook form');
    $assert(str_contains($html, 'id="handbook-article-language"') && str_contains($html, '<option value="'.$language.'" selected>') && substr_count($html,'name="language"')===1 && str_contains($html, 'name="csrf_token"'), $scenario . ' offers one language chooser inside form with CSRF');
    $assert($result['rows'] === [], $scenario . ' is read-only');
}
foreach (['vi-post' => 'vi', 'en-post' => 'en', 'en-switched-post'=>'en', 'en-queryless-post' => 'en', 'en-modal-post' => 'en'] as $scenario => $language) {
    $result = $run($scenario);
    $assert(count($result['rows']) === 1, $scenario . ' creates exactly one draft from title alone');
    $row = $result['rows'][0];
    $assert($row['language'] === $language && (int) $row['category_id'] === ($language === 'en' ? 2 : 1) && $row['status'] === 'draft' && $row['content'] === null, $scenario . ' preserves handbook category/language and empty draft content');
    if ($scenario === 'en-modal-post') $assert($result['state']['json']['ok'] === true, 'Modal creation returns success JSON');
    else $assert(in_array('Location: /admin/content_post_edit.php?id=1&handbook=1', $result['state']['headers'], true), $scenario . ' redirects to scoped editor');
}
foreach (['vi-bad-csrf-post'] as $scenario) {
    $result = $run($scenario);
    $assert($result['rows'] === [] && str_contains($result['html'], 'alert alert-danger'), $scenario . ' blocks invalid request and preserves visible form');
}
foreach(['vi-bad-category-post','vi-linked-language-change-post'] as $scenario) {
    $result=$run($scenario);
    $assert(count($result['rows'])===2 && $result['rows'][0]['language']==='vi' && (int)$result['rows'][0]['category_id']===1 && str_contains($result['html'],'alert alert-danger'),$scenario.' prevents changing an existing article language/category');
    $assert($result['links']===[['vi_post_id'=>1,'en_post_id'=>2]],$scenario.' preserves original translation pair');
}
$missing = $run('missing-category-get');
$assert($missing['rows'] === [], 'Missing-category GET never creates a post');
$assert(str_contains($missing['html'],'id="postEditForm"') && $missing['state']['headers']===[], 'Missing category no longer blocks opening Add');
$assert($missing['categories']===[], 'Missing category GET never seeds a category');
foreach(['vi-missing-category-post'=>'vi','en-missing-category-post'=>'en']as$scenario=>$language){
    $result=$run($scenario);
    $assert(count($result['rows'])===1&&count($result['categories'])===1,'Valid explicit save creates article and missing category together');
    $assert($result['rows'][0]['language']===$language&&$result['categories'][0]['slug']===($language==='en'?'blog-en':'blog'),'Auto-created category matches chosen article language');
}
foreach(['vi-missing-category-bad-csrf-post','vi-missing-category-empty-title-post']as$scenario){
    $result=$run($scenario);$assert($result['rows']===[]&&$result['categories']===[],'Invalid POST does not create category or article');
}
$linked=$run('vi-linked-get');
$assert(str_contains($linked['html'],'id="handbook-linked-post"')&&preg_match('/<option value="2"[^>]* selected/',$linked['html'])===1,'Existing language pair is selectable in actual editor');
$linkedSave=$run('vi-linked-post');
$assert(count($linkedSave['rows'])===2&&$linkedSave['state']['flash']['type']==='success','Linked article edit saves without cloning or dropping counterpart');
$newLinked=$run('vi-add-linked-get');
$assert(str_contains($newLinked['html'],'data-handbook-target-language="en"')&&str_contains($newLinked['html'],'data-handbook-target-language="vi"'),'New form includes both candidate pools for in-form language changes');
$assert(substr_count($newLinked['html'],'name="language"')===1&&str_contains($newLinked['html'],'data-handbook-language="1"'),'New form has a single editable language field');
$newLinkedSave=$run('en-add-linked-post');
$assert(count($newLinkedSave['rows'])===3&&$newLinkedSave['links']===[['vi_post_id'=>1,'en_post_id'=>3]],'New English article links atomically to an existing Vietnamese article');
$sameLanguage=$run('en-add-same-language-post');
$assert(count($sameLanguage['rows'])===2&&$sameLanguage['links']===[]&&str_contains($sameLanguage['html'],'alert alert-danger'),'Invalid same-language link rolls back the new article');
$footer = (string) file_get_contents(dirname(__DIR__) . '/admin/_layout_end.php');
$start = strpos($footer, 'function initPostForms(root)'); $end = strpos($footer, 'window.adminInitPostForms = initPostForms;', $start);
$assert($start !== false && $end !== false && !str_contains(substr($footer, $start, $end - $start), 'preventDefault'), 'Full-page post initialization does not intercept form submission');
echo "Handbook editor: {$checks} checks passed; real GET/POST source with isolated SQLite/auth fixtures, no production DB or writes.\n";
