<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/admin/_cam_nang.php';

// Render the real admin page against an in-memory fixture without opening a session
// or loading runtime database credentials. Production authentication stays intact.
function db(): PDO { return $GLOBALS['handbookFixturePdo']; }
function admin_require_login(): void {}
function admin_url(string $path = ''): string { return '/admin/' . ltrim($path, '/'); }
function site_url(string $path = ''): string { return '/' . ltrim($path, '/'); }
function admin_is_logged_in(): bool { return true; }
function admin_is_admin(): bool { return false; }
function handbook_admin_render_fixture(array $query): string
{
    $_GET = $query;
    $directory = dirname(__DIR__) . '/admin';
    $source = (string) file_get_contents($directory . '/cam_nang.php');
    $source = str_replace("require_once __DIR__ . '/_bootstrap.php';", '', $source);
    $source = str_replace('__DIR__', var_export($directory, true), $source);
    ob_start();
    eval(substr($source, 5));
    return (string) ob_get_clean();
}

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$GLOBALS['handbookFixturePdo'] = $pdo;
$pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY, category_id INTEGER, title TEXT, slug TEXT, language TEXT, status TEXT, updated_at TEXT, excerpt TEXT, content TEXT DEFAULT '<p>Article content</p>')");
$pdo->exec("INSERT INTO categories VALUES (1, 'Cẩm nang', 'blog'), (2, 'Handbook', 'blog-en'), (3, 'Services', 'services')");
$insert = $pdo->prepare('INSERT INTO posts (id, category_id, title, slug, language, status, updated_at, excerpt) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
for ($id = 1; $id <= 41; $id++) {
    $insert->execute([$id, 1, $id === 3 ? 'Literal %_ example' : 'Article ' . $id, 'article-' . $id, 'vi', $id % 2 === 0 ? 'draft' : 'published', '2026-10-14 12:00:00', $id === 5 ? 'Searchable note' : '']);
}
foreach ([
    [42, 1, 'Legacy English', 'legacy-english', 'en', 'published'],
    [43, 1, 'Null language', 'null-language', null, 'published'],
    [44, 2, 'Blank language', 'blank-language', ' ', 'draft'],
    [45, 2, 'English normalized', 'english-normalized', ' EN ', 'published'],
    [46, 1, 'Unsupported', 'unsupported', 'fr', 'published'],
    [47, null, 'Standalone page', 'page', 'vi', 'published'],
    [48, 3, 'Service', 'service', 'vi', 'published'],
    [49, 999, 'Orphan article', 'orphan', 'vi', 'published'],
] as $row) {
    $insert->execute(array_merge($row, ['2026-10-14 12:00:00', '']));
}
if (($argv[1]??'')==='--render-fixture') {
    $destination=$argv[2]??'';
    if(!str_starts_with($destination,sys_get_temp_dir().'/')&&!str_starts_with($destination,'/private/tmp/'))throw new RuntimeException('Preview must be a temporary artifact');
    file_put_contents($destination,handbook_admin_render_fixture([]));
    echo "Rendered isolated list fixture: ".$destination."\n";exit;
}
$categories = admin_handbook_categories($pdo);
$assert(array_keys($categories) === ['blog', 'blog-en'], 'Only handbook categories are selected');
$assert(admin_handbook_category_id($categories, 'vi') === 1 && admin_handbook_category_id($categories, 'en') === 2, 'Creation uses each language category');
$assert(admin_handbook_category_id([], 'vi') === 0, 'Missing categories are not synthesized');
$assert(admin_handbook_contains_post($categories, ['category_id' => 1, 'language' => 'en']), 'Legacy cross-category language stays in scope');
$assert(admin_handbook_contains_post($categories, ['category_id' => 2, 'language' => null]), 'Null language keeps legacy VI default');
$assert(admin_handbook_contains_post($categories, ['category_id' => 2, 'language' => ' EN ']), 'Language whitespace and case normalize');
$assert(!admin_handbook_contains_post($categories, ['category_id' => 3, 'language' => 'vi']), 'Other categories cannot enter handbook editor');
$assert(!admin_handbook_contains_post($categories, ['category_id' => 1, 'language' => 'fr']), 'Unsupported languages cannot enter handbook editor');
foreach (['0', '-2', 'wrong', '1.2', '1e3', '100000000000', []] as $page) {
    $assert(admin_handbook_filters(['page' => $page])['page'] === 1, 'Malformed page falls back safely');
}
$filters = admin_handbook_filters(['q' => [], 'language' => [], 'status' => []]);
$assert($filters === ['q' => '', 'status' => '', 'language' => '', 'page' => 1], 'Malformed filters normalize without warnings');
$assert(mb_strlen(admin_handbook_filters(['q' => str_repeat('a', 200)])['q']) === 160, 'Search has a bounded length');
$filters = admin_handbook_filters([]);
$all = admin_handbook_page($pdo, $filters);
$assert($all['total'] === 45 && $all['pages'] === 3 && count($all['items']) === 20, 'Admin includes all scoped articles, both drafts and published');
$assert($all['start'] === 1 && $all['end'] === 20 && $all['items'][0]['id'] === 45, 'First page range and deterministic ordering');
$last = admin_handbook_page($pdo, admin_handbook_filters(['page' => 999]));
$assert($last['page'] === 3 && $last['start'] === 41 && $last['end'] === 45 && count($last['items']) === 5, 'Out-of-range page clamps to last page');
$middle = admin_handbook_page($pdo, admin_handbook_filters(['page' => 2]));
$allIds = array_merge(array_column($all['items'], 'id'), array_column($middle['items'], 'id'), array_column($last['items'], 'id'));
$assert($allIds === range(45, 1), 'Pages neither lose nor repeat posts');
$vi = admin_handbook_page($pdo, admin_handbook_filters(['language' => 'vi']));
$assert($vi['total'] === 43, 'VI includes null and blank legacy languages, regardless of category locale');
$en = admin_handbook_page($pdo, admin_handbook_filters(['language' => 'en', 'status' => 'published']));
$assert($en['total'] === 2 && array_column($en['items'], 'language') === ['en', 'en'], 'Language and status filters compose and normalize output');
$draft = admin_handbook_page($pdo, admin_handbook_filters(['status' => 'draft']));
$assert($draft['total'] === 21, 'Draft filter includes correct scoped records');
$literal = admin_handbook_page($pdo, admin_handbook_filters(['q' => '%_']));
$assert($literal['total'] === 1 && $literal['items'][0]['id'] === 3, 'Search escapes SQL LIKE wildcards literally');
$assert(admin_handbook_page($pdo, admin_handbook_filters(['q' => 'Searchable']))['total'] === 1, 'Search includes excerpts');
$none = admin_handbook_page($pdo, admin_handbook_filters(['q' => "' OR 1=1 --", 'page' => 8]));
$assert($none['total'] === 0 && $none['page'] === 1 && $none['start'] === 0 && $none['end'] === 0, 'Search is bound and empty ranges stay valid');
$url = admin_handbook_page_url(admin_handbook_filters(['q' => 'care & rest', 'status' => 'draft', 'language' => 'en']), 2);
$assert($url === '/admin/cam_nang.php?q=care+%26+rest&status=draft&language=en&page=2', 'Pagination retains all filters safely');
$assert(admin_handbook_page_url($filters, 1) === '/admin/cam_nang.php', 'First page has no redundant query');
$assert(admin_handbook_public_url(['slug' => 'sức khỏe', 'language' => 'en']) === '/en/cam-nang/s%E1%BB%A9c%20kh%E1%BB%8Fe', 'Public links retain locale and escape slug');
$menu = (string) file_get_contents(dirname(__DIR__) . '/admin/_layout_start.php');
$assert(substr_count($menu, "admin_url('cam_nang.php')") === 2, 'Desktop and mobile both link to Cẩm nang');
$assert(!str_contains($menu, "admin_url('content.php')") && !str_contains($menu, 'Quản lý nội dung'), 'Legacy content menu item is hidden in both navigation surfaces');
$editor = (string) file_get_contents(dirname(__DIR__) . '/admin/content_post_edit.php');
$assert(str_contains($editor, "\$adminActive = \$isHandbook ? 'cam-nang' : 'content'"), 'Article editor keeps handbook navigation active');
$assert(substr_count($editor, 'name="handbook"') === 2 && substr_count($editor, 'name="csrf_token"') === 2, 'Both editor forms preserve handbook context and CSRF token');
$assert(str_contains($editor, 'hash_equals($expectedToken, $providedToken)') && str_contains($editor, 'admin_handbook_contains_post($handbookCategories, $row)'), 'Handbook editor checks CSRF and scoped record access');
$assert(is_file(dirname(__DIR__) . '/admin/content.php'), 'Old content backend remains available directly');
$html = handbook_admin_render_fixture(['language' => 'en', 'status' => 'published']);
$assert(str_contains($html, 'Hiển thị 1–2 / 2 bài viết') && str_contains($html, 'Legacy English'), 'Real list renders filtered global count and scoped records');
$assert(str_contains($html, '/en/cam-nang/legacy-english') && str_contains($html, 'handbook=1&amp;id=42'), 'Real list exposes localized public and scoped edit links');
$assert(!str_contains($html, '>Standalone page<') && !str_contains($html, '>Service<'), 'Real list never renders standalone pages or unrelated articles');
$html = handbook_admin_render_fixture(['page' => 2, 'status' => 'draft']);
$assert(str_contains($html, 'aria-current="page"') && str_contains($html, '/admin/cam_nang.php?status=draft'), 'Real pagination preserves status and marks current page');
$insert->execute([50, 1, 'Unlinked article', ' ', 'vi', 'published', '2026-10-15 12:00:00', '']);
$html = handbook_admin_render_fixture(['q' => 'Unlinked article']);
$assert(str_contains($html, 'handbook=1&amp;id=50') && !str_contains($html, 'aria-label="Xem bài Unlinked article"'), 'Blank-slug records remain editable without a broken public link');
$updateContent = $pdo->prepare('UPDATE posts SET content = :content WHERE id = 42');
foreach (['', '   ', null] as $emptyContent) {
    $updateContent->execute([':content' => $emptyContent]);
    $empty = admin_handbook_page($pdo, admin_handbook_filters(['q' => 'Legacy English']));
    $assert($empty['total'] === 1 && (int) $empty['items'][0]['has_public_content'] === 0, 'Empty content remains in admin without public eligibility');
    $html = handbook_admin_render_fixture(['q' => 'Legacy English']);
    $assert(str_contains($html, 'handbook=1&amp;id=42') && !str_contains($html, 'aria-label="Xem bài Legacy English"'), 'Empty published article retains edit action but hides public View');
}
$updateContent->execute([':content' => '<p>Published article</p>']);
$html = handbook_admin_render_fixture(['q' => 'Legacy English']);
$assert(str_contains($html, 'aria-label="Xem bài Legacy English"'), 'Published article with content restores View');
$pdo->exec('DELETE FROM categories WHERE id = 2');
$html = handbook_admin_render_fixture([]);
$assert(substr_count($html,'href="/admin/content_post_edit.php?handbook=1"')===1 && str_contains($html,'>Thêm bài</a>'),'List offers a single Add article action even when categories are missing');
$assert(!str_contains($html,'Thêm bài tiếng Việt')&&!str_contains($html,'Thêm bài tiếng Anh')&&!str_contains($html,'handbook-category-en'),'Language choices and category setup do not clutter the list');
$assert(!str_contains($html,'id="handbook-status"')&&!str_contains($html,'id="handbook-language"')&&str_contains($html,'id="handbook-search"'),'List retains simple search without extra language/status selectors');
$assert((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 2, 'Rendering never seeds missing categories');
echo "Handbook admin: {$checks} checks passed (SQLite memory only; no database/network changes).\n";
