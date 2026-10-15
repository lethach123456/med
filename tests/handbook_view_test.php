<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/medical_handbook.php';

function site_localized_path(string $path, string $locale): string
{
    return ($locale === 'en' ? '/en' : '') . ($path === '/' && $locale === 'en' ? '' : $path);
}

function medical_media_image_attributes(string $url, string $sizes, array $widths = [], int $defaultWidth = 640): string
{
    $GLOBALS['handbook_test_media'] = [$url, $sizes, $widths, $defaultWidth];
    return 'src="/assets/handbook-test-640.webp" srcset="/assets/handbook-test-320.webp 320w, /assets/handbook-test-640.webp 640w" sizes="' . handbook_view_escape($sizes) . '"';
}

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};

$article = ['id' => 3, 'title' => 'Chăm sóc & sức khỏe', 'slug' => 'cham-soc-suc-khoe', 'status' => 'published',
    'language' => 'vi', 'category_slug' => 'blog', 'content' => '<p>Nội dung bài viết.</p>', 'excerpt' => '<p>Thông tin &amp; lưu ý.</p>',
    'updated_at' => '2026-10-15 09:00:00', 'featured_image_url' => ''];
$assert(handbook_row_is_article($article, 'vi'), 'Published Vietnamese blog content included');
$assert(handbook_row_is_article(array_replace($article, ['category_slug' => 'blog-en']), 'vi'), 'Authoritative posts language supports legacy blog category placement');
$assert(handbook_row_is_article(array_replace($article, ['language' => ' EN ', 'category_slug' => 'blog']), 'en'), 'Normalized English language retained in legacy category');
$assert(handbook_row_is_article(array_replace($article, ['language' => '']), 'vi'), 'Legacy blank language defaults to Vietnamese');
$assert(handbook_row_is_article(array_replace($article, ['language' => null]), 'vi'), 'Legacy null language defaults to Vietnamese');
foreach ([['status' => 'draft'], ['category_slug' => null], ['category_slug' => 'dich-vu'], ['category_slug' => 'about'],
    ['content' => null], ['content' => " \n "], ['slug' => ''], ['language' => 'en'], ['language' => 'fr']] as $change) {
    $assert(!handbook_row_is_article(array_replace($article, $change), 'vi'), 'Out-of-scope record is excluded: ' . json_encode($change));
}
$assert(!handbook_row_is_article($article, 'en'), 'No cross-locale content fallback');

foreach ([[], ['page' => 0], ['page' => -3], ['page' => 'abc'], ['page' => []], ['page' => '1.5'], ['page' => '1e3']] as $query) {
    $assert(handbook_view_requested_page($query) === 1, 'Invalid pagination input safely defaults');
}
$assert(handbook_view_requested_page(['page' => '2']) === 2, 'Valid page accepted');
$filters = handbook_view_filters(['q' => "  sức   khỏe\n ", 'page' => 2]);
$assert($filters === ['q' => 'sức khỏe'], 'Search whitespace normalized and unrelated parameters omitted');
$assert(handbook_view_filters(['q' => ['unsafe']]) === ['q' => ''], 'Array keyword rejected');
$assert(mb_strlen(handbook_view_filters(['q' => str_repeat('ứ', 200)])['q']) === 120, 'Keyword limited by characters');
$paging = handbook_view_paging(21, 2);
$assert($paging['page'] === 2 && $paging['pages'] === 3 && $paging['start'] === 10 && $paging['end'] === 18 && $paging['offset'] === 9, 'Nine-card pagination and range');
$assert(handbook_view_paging(21, PHP_INT_MAX)['page'] === 3, 'Oversized page clamps to available page');
$assert(handbook_view_paging(0, 99)['start'] === 0 && handbook_view_paging(0, 99)['end'] === 0, 'Empty range never invents results');
$assert(handbook_view_paging(5, 1, 0)['per_page'] === 1, 'Per-page division is bounded');
$assert(handbook_view_page_url('en', $filters, 2) === '/en/cam-nang?q=s%E1%BB%A9c%20kh%E1%BB%8Fe&page=2', 'Locale, keyword and pagination preserved');
$assert(handbook_view_page_url('vi', ['page' => 2]) === '/cam-nang', 'Default page omitted and filter changes reset pagination');
$assert(handbook_view_path('en', 'bài viết/a') === '/en/cam-nang/b%C3%A0i%20vi%E1%BA%BFt%2Fa', 'Article slug encoded as one path segment');

$scope = handbook_query_scope('en', ['q' => '100%_!']);
$assert(str_contains($scope['sql'], "p.status='published'") && str_contains($scope['sql'], "c.slug IN ('blog','blog-en')"), 'SQL only reads published blog articles');
$assert(str_contains($scope['sql'], "COALESCE(NULLIF(LOWER(TRIM(p.language)),''),'vi')=:locale") && $scope['parameters'][':locale'] === 'en', 'SQL authoritative locale matches helper');
$assert(str_contains($scope['sql'], "COALESCE(p.content,'')") && str_contains($scope['sql'], 'TRIM(p.slug)'), 'SQL rejects contentless and unroutable posts');
$assert($scope['parameters'][':title_query'] === '%100!%!_!!%', 'Search wildcard characters escaped literally');
$assert(!str_contains($scope['sql'], '100%') && isset($scope['parameters'][':content_query']), 'Keyword bound, never interpolated');

final class HandbookTestPDO extends PDO
{
    public array $queries = [];
    public array $counts = [21, 10];
    public array $items = [];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!str_starts_with($query, 'SELECT ')) throw new RuntimeException('A write was attempted');
        $this->queries[] = ['sql' => $query, 'parameters' => []];
        return new HandbookTestStatement($this, array_key_last($this->queries));
    }
}
final class HandbookTestStatement extends PDOStatement
{
    public function __construct(private HandbookTestPDO $connection, private int $index) {}
    public function execute(?array $params = null): bool { $this->connection->queries[$this->index]['parameters'] = $params ?? []; return true; }
    public function fetchColumn(int $column = 0): mixed { return array_shift($this->connection->counts); }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->connection->items; }
}
$fakePdo = new HandbookTestPDO();
$fakePdo->items = [$article];
$data = handbook_directory_page($fakePdo, 'vi', ['q' => 'chăm sóc'], 2);
$assert($data['published_total'] === 21 && $data['total'] === 10 && $data['page'] === 2 && $data['items'] === [$article], 'Read-only PDO returns publication count, filtered total and page');
$assert(count($fakePdo->queries) === 3, 'Bounded count and current-page reads only');
$select = $fakePdo->queries[2];
$assert(str_contains($select['sql'], 'INNER JOIN categories') && str_contains($select['sql'], 'ORDER BY p.updated_at DESC,p.id DESC LIMIT 9 OFFSET 9'), 'Bounded results, category join and stable ordering');
$assert($select['parameters'][':locale'] === 'vi' && isset($select['parameters'][':content_query']), 'Result and count scopes share bound locale and search');
$fakePdo = new HandbookTestPDO(); $fakePdo->counts = [0];
$data = handbook_directory_page($fakePdo, 'en');
$assert(count($fakePdo->queries) === 2 && $data['total'] === 0 && $data['items'] === [], 'Unfiltered empty state uses no redundant count or fallback language');

$assert(handbook_view_plain_text('<script>alert(1)</script><p>A &amp; B</p><p>C&nbsp;D</p>') === 'A & B C D', 'Excerpt strips executable markup and decodes entities');
$assert(handbook_view_excerpt(['excerpt' => '', 'content' => '<p>Actual article text</p>']) === 'Actual article text', 'Only actual article content used as excerpt fallback');
$assert(mb_strlen(handbook_view_excerpt(['excerpt' => str_repeat('Nội dung ', 40)])) <= 161, 'Excerpt length is bounded');
foreach (['javascript:alert(1)', 'data:image/svg+xml,unsafe', '//unsafe.example/image.jpg', 'https://user:pass@example.com/a.jpg', '/unsafe\image.jpg', "https://example.com/a\n.jpg"] as $url) {
    $assert(handbook_view_image_url($url) === '', 'Unsafe image URL rejected');
}
$assert(handbook_view_image_url('/uploads/library/article.jpg') !== '' && handbook_view_image_url('https://example.com/article.jpg') !== '', 'Existing root-relative and HTTPS images allowed');
$assert(handbook_view_date('2026-02-30 00:00:00', 'vi') === [] && handbook_view_date('invalid', 'en') === [], 'Invalid dates not invented');
$assert(handbook_view_date('2026-10-15 09:00:00', 'vi')['label'] === '15/10/2026', 'Vietnamese date shown with machine-readable alternative');
$card = handbook_view_card(array_replace($article, ['title' => '<script>unsafe</script> & title']), 'vi');
$assert(!str_contains($card, '<script>') && str_contains($card, '&lt;script&gt;unsafe&lt;/script&gt; &amp; title'), 'Article title escaped');
$assert(!str_contains($card, '<img') && str_contains($card, 'hb-card--text'), 'Missing image produces real text-only card, not a fake image');
$assert(str_contains($card, '/cam-nang/cham-soc-suc-khoe') && str_contains($card, 'datetime="2026-10-15"'), 'Article route and semantic date rendered');
$card = handbook_view_card(array_replace($article, ['featured_image_url' => '/uploads/library/article.jpg']), 'en');
$assert(str_contains($card, '/en/cam-nang/cham-soc-suc-khoe') && str_contains($card, 'loading="lazy"') && str_contains($card, 'width="640" height="360"') && str_contains($card, 'srcset='), 'Responsive media helper, dimensions and lazy loading used');
$assert($GLOBALS['handbook_test_media'][0] === '/uploads/library/article.jpg', 'Existing source passed directly without fetching');
$pager = handbook_view_pagination('en', ['q' => 'care & health'], handbook_view_paging(21, 2));
$assert(str_contains($pager, 'aria-current="page"') && str_contains($pager, 'aria-label="Handbook pagination"'), 'Current page and navigation accessible');
$assert(str_contains($pager, 'rel="prev"') && str_contains($pager, 'rel="next"') && str_contains($pager, 'care%20%26%20health&amp;page=3'), 'Native pagination preserves search and locale with escaped URLs');
$assert(handbook_view_pagination('vi', [], handbook_view_paging(0)) === '', 'No meaningless empty pager');
$assert(!str_contains(handbook_view_pagination('vi', [], handbook_view_paging(1)), 'hb-page-links'), 'One-page collection has range only');

$locale = 'vi'; $handbookCopy = handbook_view_copy($locale); $handbookFilters = ['q' => '']; $handbookFailed = false;
$handbookPaging = handbook_view_paging(1) + ['items' => [$article], 'published_total' => 1];
ob_start(); require dirname(__DIR__) . '/Tem/handbook-directory.php'; $html = (string) ob_get_clean();
$assert(substr_count($html, '<article class="hb-card') === 1 && str_contains($html, 'method="get"') && str_contains($html, 'for="handbookSearch"'), 'Semantic GET search and current-page-only cards rendered');
$handbookPaging = handbook_view_paging(0) + ['items' => [], 'published_total' => 0];
ob_start(); require dirname(__DIR__) . '/Tem/handbook-directory.php'; $html = (string) ob_get_clean();
$assert(str_contains($html, 'Chưa có bài viết được xuất bản') && !str_contains($html, '<article class="hb-card'), 'True empty state has no fabricated content');
$handbookFilters = ['q' => '<b>no match</b>'];
ob_start(); require dirname(__DIR__) . '/Tem/handbook-directory.php'; $html = (string) ob_get_clean();
$assert(str_contains($html, 'Chưa tìm thấy bài viết phù hợp') && str_contains($html, '&lt;b&gt;no match&lt;/b&gt;'), 'Search empty state distinguishes no matches and escapes query');
$handbookFailed = true;
ob_start(); require dirname(__DIR__) . '/Tem/handbook-directory.php'; $html = (string) ob_get_clean();
$assert(str_contains($html, 'Tạm thời chưa thể tải bài viết') && !str_contains($html, 'Chưa có bài viết được xuất bản'), 'Failure is not represented as zero publication');
$main = (string) file_get_contents(dirname(__DIR__) . '/cam-nang.php');
foreach (['site_favicon_tags()', 'medical_public_shell_styles()', 'site_page_locale(\'handbook\')', 'noindex,follow', 'hreflang="vi"', 'hreflang="en"', "Tem/header.php", "Tem/footer.php"] as $required) {
    $assert(str_contains($main, $required), 'Public shell/metadata integration: ' . $required);
}
$assert(str_contains($main, '$handbookPaging[\'page\'] === 1') && str_contains($main, '!$handbookNoindex'), 'No invented translated search/pagination hreflang');
$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/pages/handbook.css');
foreach (['1248px', 'calc(100% - 32px)', '--med-ui-control-height,40px', 'min-height:44px', ':focus-visible', 'prefers-reduced-motion:reduce'] as $required) {
    $assert(str_contains($css, $required), 'Compact responsive accessible styling: ' . $required);
}
echo "Handbook: {$checks} checks passed. No database, network or persistent writes used.\n";
