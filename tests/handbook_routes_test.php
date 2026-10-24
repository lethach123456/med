<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/medical_handbook_routes.php';
$root = dirname(__DIR__);
$checks = 0;
$assert = static function (bool $value, string $label) use (&$checks): void {
    $checks++;
    if (!$value) throw new RuntimeException($label);
};
$assert(site_page_script_map()['cam-nang.php'] === 'handbook', 'Listing has a dedicated page key');
$assert(site_bilingual_public_page_paths()['handbook'] === '/cam-nang', 'Language switch maps stable listing');
foreach (['/cam-nang', '/cam-nang/article', '/cam-nang.php'] as $path) {
    $_SERVER['REQUEST_URI'] = $path . '?lang=en'; $_GET = ['lang' => 'en'];
    $assert(site_page_locale('handbook') === 'vi', 'VI physical route ignores contradictory lang query');
}
foreach (['/en/cam-nang', '/en/cam-nang/article'] as $path) {
    $_SERVER['REQUEST_URI'] = $path . '?lang=vi'; $_GET = ['lang' => 'vi'];
    $assert(site_page_locale('handbook') === 'en', 'EN physical route ignores contradictory lang query');
}
$assert(site_localized_path('/cam-nang.php', 'vi') === '/cam-nang', 'PHP alias normalizes');
$assert(site_localized_path('/en/cam-nang', 'en') === '/en/cam-nang', 'EN route not double-prefixed');
$assert(site_localized_path('/en/cam-nang/example', 'vi') === '/cam-nang/example', 'Detail route can return to neutral locale');
$_GET = ['slug' => 'private-route', 'id' => 1, 'route' => 'handbook', 'front_editor_route_page' => 'handbook', 'edit_template' => 1, 'page' => 3, 'q' => 'care'];
$links = site_language_switch_links('handbook');
$assert($links['vi'] === '/cam-nang?q=care' && $links['en'] === '/en/cam-nang?q=care', 'Language switch strips internal routing and paging state');
$rewrite = (string) file_get_contents($root . '/.htaccess');
foreach (['RewriteRule ^cam-nang/?$ cam-nang.php', 'RewriteRule ^en/cam-nang/?$ cam-nang.php', 'RewriteRule ^cam-nang/([^/]+)/?$ post.php?slug=$1&route=handbook', 'RewriteRule ^en/cam-nang/([^/]+)/?$ post.php?slug=$1&route=handbook'] as $rule) {
    $assert(str_contains($rewrite, $rule), 'Explicit route: ' . $rule);
    $assert(strpos($rewrite, $rule) < strpos($rewrite, 'RewriteRule ^([^/]+)/?$'), 'Handbook route precedes generic slug');
}
foreach (['/cam-nang', '/en/cam-nang'] as $path) {
    $assert(str_contains((string) file_get_contents($root . '/sitemap.php'), "'" . $path . "'"), 'Listing included in localized sitemap');
}
$sitemapSource = (string) file_get_contents($root . '/sitemap.php');
$assert(
    str_contains($sitemapSource, 'medreview_sitemap_handbook_count')
    && str_contains($sitemapSource, 'medreview_sitemap_handbook_items')
    && str_contains($sitemapSource, "\$parts['handbook']")
    && str_contains($sitemapSource, "\$entries['handbook']"),
    'Handbook articles have dedicated localized sitemap entries'
);

// Execute the real post controller query block against an isolated SQLite fixture.
// Do not include the rest of the page or connect to runtime MySQL.
$source = (string) file_get_contents($root . '/post.php');
$start = strpos($source, '$slug =');
$end = strpos($source, 'if (is_array($post) && !$canEditTemplate');
$assert($start !== false && $end !== false && $end > $start, 'Controller query block can be isolated');
$queryBlock = substr($source, $start, $end - $start);
$queryBlock = str_replace('$pdo = db();', '$pdo = $fixturePdo;', $queryBlock);
$fixturePdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$fixturePdo->exec('CREATE TABLE categories(id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
$fixturePdo->exec('CREATE TABLE posts(id INTEGER PRIMARY KEY, category_id INTEGER, title TEXT, slug TEXT, featured_image_url TEXT, excerpt TEXT, content TEXT, seo_title TEXT, seo_description TEXT, updated_at TEXT, template INTEGER, status TEXT, language TEXT)');
$fixturePdo->exec("INSERT INTO categories VALUES(1,'Cẩm nang','blog'),(2,'Guides','blog-en'),(3,'Services','dich-vu')");
$insert = $fixturePdo->prepare('INSERT INTO posts VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ([
    [1,1,'viet','published','vi','<p>VI article</p>'],
    [2,2,'english','published','en','<p>EN article</p>'],
    [3,1,'draft','draft','vi','<p>Private draft</p>'],
    [4,3,'service','published','vi','<p>Service</p>'],
    [5,null,'standalone','published','vi','<p>Page</p>'],
    [6,1,'blank','published','vi',''],
    [7,1,'','published','vi','<p>No slug</p>'],
    [8,1,'legacy-en','published',' EN ','<p>Legacy EN</p>'],
    [9,2,'legacy-null','published',null,'<p>Legacy VI</p>'],
    [10,1,'template','published','vi',''],
] as [$id,$category,$slug,$status,$language,$content]) {
    $insert->execute([$id,$category,'Title '.$id,$slug,'','',$content,'','','2026-10-15 10:00:00',$id === 10 ? 1 : 0,$status,$language]);
}
$run = static function (array $query, string $path) use ($fixturePdo, $queryBlock): ?array {
    $_GET = $query; $_SERVER['REQUEST_URI'] = $path;
    eval($queryBlock);
    return is_array($post) ? $post : null;
};
foreach (['slug', 'id'] as $key) {
    foreach ([1 => true,2 => false,3 => false,4 => false,5 => false,6 => false,7 => false,8 => false,9 => true,10 => false] as $id => $expected) {
        $slugMap = [1=>'viet',2=>'english',3=>'draft',4=>'service',5=>'standalone',6=>'blank',7=>'',8=>'legacy-en',9=>'legacy-null',10=>'template'];
        if ($slugMap[$id] === '') continue;
        $query = ['route' => 'handbook', $key => $key === 'id' ? $id : $slugMap[$id]];
        $result = $run($query, '/cam-nang/' . $slugMap[$id]);
        $assert(($result !== null) === $expected, "VI scope {$key} {$id}");
    }
    $result = $run(['route'=>'handbook',$key=>$key === 'id' ? 2 : 'english','lang'=>'vi'], '/en/cam-nang/english');
    $assert(($result['id'] ?? 0) === 2, 'EN detail uses route locale for ' . $key);
    $result = $run(['route'=>'handbook',$key=>$key === 'id' ? 1 : 'viet','lang'=>'vi'], '/en/cam-nang/viet');
    $assert($result === null, 'Opposite-language detail is unavailable for ' . $key);
    $result = $run(['route'=>'handbook',$key=>$key === 'id' ? 3 : 'draft','edit_template'=>'1'], '/cam-nang/draft');
    $assert($result === null, 'Guest preview query cannot expose draft for ' . $key);
}
$assert(str_contains($source, 'if (!$isHandbookDetailRoute) $post = post_template_resolve_content($post);'), 'Handbook never resolves a blank template through another slug');
$assert(str_contains($source, '$articleCanonicalPath = handbook_public_article_path($post);'), 'All public handbook contexts share the same canonical resolver');

foreach ([
    ['/cam-nang/viet', ['slug'=>'english','id'=>2,'route'=>'news','lang'=>'en'], 1],
    ['/en/cam-nang/english', ['slug'=>'viet','id'=>1,'route'=>'blog','lang'=>'vi'], 2],
    ['/blog/viet', ['slug'=>'english','id'=>2,'route'=>'handbook'], 1],
    ['/news/english', ['slug'=>'viet','id'=>1,'route'=>'handbook'], 2],
] as [$path, $query, $expectedId]) {
    $result = $run($query, $path);
    $assert(($result['id'] ?? 0) === $expectedId, 'Physical slug/locale ignores contradictory query: ' . $path);
}
foreach ([
    ['slug'=>'english'], ['id'=>2], ['route'=>'handbook','id'=>2,'lang'=>'vi'],
] as $query) {
    $result = $run($query, '/post.php');
    $assert(($result['id'] ?? 0) === 2 && handbook_article_redirect_target($result, '/post.php') === '/en/cam-nang/english', 'Direct PHP requests resolve the stored language and canonical URL');
}
$viet = $run(['id'=>1], '/post.php');
$english = $run(['id'=>8], '/post.php');
$legacyVi = $run(['id'=>9], '/post.php');
$assert(handbook_public_article_path($english) === '/en/cam-nang/legacy-en', 'Stored language wins over legacy category');
$assert(handbook_public_article_path($legacyVi) === '/cam-nang/legacy-null', 'Blank legacy language means Vietnamese, matching listing and sitemap');
foreach (['/blog/viet', '/news/viet', '/blog-chi-tiet.php?slug=viet', '/post.php?id=1', '/viet', '/cam-nang/viet/'] as $legacyPath) {
    $assert(handbook_article_redirect_target($viet, $legacyPath) === '/cam-nang/viet', 'Legacy alias redirects once: ' . $legacyPath);
}
$assert(handbook_article_redirect_target($viet, '/cam-nang/viet?route=news&lang=en') === '', 'Canonical route has no redirect loop');
$assert(handbook_article_redirect_target($viet, '/post.php?id=1&edit_template=1', true) === '', 'Authenticated preview remains editable without canonical redirect');
foreach ([3,6,7,10] as $id) {
    $result = $run(['id'=>$id], '/post.php');
    $assert($result === null || handbook_public_article_path($result) === '', 'Draft/blank/slugless articles never gain public canonical redirects: ' . $id);
}
$assert($run(['id'=>999], '/post.php') === null, 'Unknown direct ID stays not found');
$_SESSION['admin_user_id'] = 1;
$preview = $run(['id'=>3,'edit_template'=>'1'], '/post.php');
$assert(($preview['id'] ?? 0) === 3, 'Authenticated draft template preview is preserved');
unset($_SESSION['admin_user_id']);
$assert($run(['id'=>3,'edit_template'=>'1'], '/post.php') === null, 'Guest cannot expose draft via preview flag');
$assert(handbook_public_article_path(array_replace($viet, ['slug'=>"//example.com/\r\nLocation:bad"])) === '/cam-nang/example.com%2F%0D%0ALocation%3Abad', 'Stored slug cannot create an external redirect or response header');
foreach (['vi'=>'blog.php','en'=>'news.php'] as $locale=>$file) {
    $legacySource = (string) file_get_contents($root . '/' . $file);
    $assert(str_contains($legacySource, "handbook_view_page_url('" . $locale . "', \$_GET, handbook_view_requested_page(\$_GET))") && str_contains($legacySource, 'true, 301'), 'Legacy listing permanently redirects to the correct language');
    $assert(strpos($legacySource, 'if (!$legacyTemplatePreview)') < strpos($legacySource, 'front_editor_page_maybe_redirect('), 'Legacy page slug customization cannot create a redirect chain');
}
$assert(handbook_view_page_url('en', ['q'=>"  dental   care  ",'page'=>'3','route'=>'https://evil.test','lang'=>'vi','id'=>9], handbook_view_requested_page(['page'=>'3'])) === '/en/cam-nang?q=dental%20care&page=3', 'Listing aliases keep only normalized search and pagination');
$assert(handbook_view_page_url('vi', ['q'=>['bad'],'page'=>['bad']], handbook_view_requested_page(['page'=>['bad']])) === '/cam-nang', 'Malformed listing query cannot override the target');
$assert(strpos($source, "require __DIR__ . '/Tem/handbook-article.php'") < strpos($source, '$suggestedFacilities ='), 'Handbook reader does not load unrelated facility/review recommendation data');
$header = (string) file_get_contents($root . '/Tem/header.php');
$assert(str_contains($header, "'label' => 'Cẩm nang'") && str_contains($header, "'label' => 'Guides'") && substr_count($header, '$isHandbookPage &&') === 2, 'Desktop and mobile menu expose guides with article active state');
echo "Handbook routes: {$checks} checks passed; SQLite fixture only, no network or persistent writes.\n";
