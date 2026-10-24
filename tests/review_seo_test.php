<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_review_view.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
// MySQL LENGTH counts bytes; SQLite normally counts characters.
$pdo->sqliteCreateFunction('LENGTH', static fn(?string $value): ?int => $value === null ? null : strlen($value), 1);
$pdo->exec('CREATE TABLE medical_reviews (id INTEGER PRIMARY KEY, status TEXT, slug TEXT, title TEXT, rating REAL,
    author_text TEXT, method_text TEXT, condition_text TEXT, excerpt TEXT, story_json TEXT)');
$base = ['status' => 'published', 'slug' => 'customer-review', 'title' => 'An actual customer experience', 'rating' => 4.5,
    'author_text' => 'A customer', 'method_text' => 'Consultation', 'condition_text' => '', 'excerpt' => str_repeat('a', 80), 'story_json' => '[]'];
$cases = [
    ['Excerpt at minimum', [], true],
    ['Excerpt below minimum', ['excerpt' => str_repeat('a', 79)], false],
    ['Story at minimum', ['excerpt' => '', 'story_json' => '["' . str_repeat('b', 116) . '"]'], true],
    ['Story below minimum', ['excerpt' => '', 'story_json' => '["' . str_repeat('b', 115) . '"]'], false],
    ['UTF-8 byte threshold', ['excerpt' => str_repeat('đ', 40)], true],
    ['UTF-8 below byte threshold', ['excerpt' => str_repeat('đ', 39)], false],
    ['Whitespace does not meet minimum', ['excerpt' => str_repeat(' ', 160)], false],
    ['Draft', ['status' => 'draft'], false],
    ['No slug', ['slug' => '  '], false],
    ['No title', ['title' => null], false],
    ['Unrated', ['rating' => 0], false],
    ['Negative rating', ['rating' => -1], false],
    ['Invalid high rating', ['rating' => 5.1], false],
    ['Null rating', ['rating' => null], false],
    ['Five stars', ['rating' => 5], true],
    ['Small valid fractional rating', ['rating' => 0.2], true],
    ['Editorial author', ['author_text' => 'Ban biên tập MedReview'], false],
    ['Editorial method', ['method_text' => 'Thông tin tổng hợp'], false],
    ['Editorial condition', ['condition_text' => 'Nội dung biên tập, không phải đánh giá khách hàng'], false],
    ['Padded editorial marker', ['condition_text' => ' Nội dung biên tập, không phải đánh giá khách hàng '], false],
];
$insert = $pdo->prepare('INSERT INTO medical_reviews VALUES (:id,:status,:slug,:title,:rating,:author_text,:method_text,:condition_text,:excerpt,:story_json)');
$expectedIds = [];
foreach ($cases as $offset => [$name, $changes, $expected]) {
    $row = ['id' => $offset + 1] + array_replace($base, $changes);
    $insert->execute($row);
    if ($expected) $expectedIds[] = $row['id'];
    $assert(medical_review_seo_indexable($row) === $expected, $name . ' uses expected detail indexability');
    $assert(review_view_row($row)['seo_indexable'] === $expected, $name . ' keeps indexability on the view DTO');
}
foreach (['', 'r', 'r.'] as $prefix) {
    $alias = $prefix === '' ? '' : ' r';
    $ids = array_map('intval', $pdo->query('SELECT id FROM medical_reviews' . $alias . ' WHERE ' . medical_review_seo_indexable_where($prefix) . ' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    $assert($ids === $expectedIds, 'Shared SQL and detail PHP agree for table prefix ' . ($prefix ?: '(none)'));
}
$assert(!medical_review_seo_indexable([]), 'Legacy/missing data fails closed without changing content');
foreach (medical_review_seo_required_columns() as $column) {
    $missingField = $base;
    unset($missingField[$column]);
    $assert(!medical_review_seo_indexable($missingField), 'Missing ' . $column . ' data cannot be assumed indexable');
}
$threw = false;
try { medical_review_seo_indexable_where('r; DROP TABLE medical_reviews'); } catch (InvalidArgumentException) { $threw = true; }
$assert($threw, 'Only SQL identifier aliases are accepted');

// Run the actual detail query against old table shapes. Only MySQL's metadata
// query is adapted; referencing a missing review column would throw in SQLite.
final class ReviewSeoLegacyPDO extends PDO
{
    public array $preparedSql = [];
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedSql[] = $query;
        if (str_contains($query, 'INFORMATION_SCHEMA.COLUMNS')) {
            $query = 'SELECT name FROM pragma_table_info(:table)';
        }
        return parent::prepare($query, $options);
    }
}
foreach ([['method_text'], ['condition_text'], ['method_text', 'condition_text']] as $missingColumns) {
    $legacy = new ReviewSeoLegacyPDO();
    $columns = array_values(array_diff(medical_review_seo_required_columns(), $missingColumns));
    $legacy->exec('CREATE TABLE medical_reviews (id INTEGER PRIMARY KEY, facility_slug TEXT, facility_name TEXT, created_at TEXT, '
        . implode(',', array_map(static fn(string $column): string => $column . ($column === 'rating' ? ' REAL' : ' TEXT'), $columns)) . ')');
    $legacyRow = array_intersect_key($base, array_flip($columns));
    $legacy->prepare('INSERT INTO medical_reviews (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(static fn(string $column): string => ':' . $column, $columns)) . ')')->execute($legacyRow);
    $detail = review_view_detail($legacy, $base['slug']);
    $assert($detail['review'] !== null && $detail['review']['title'] === $base['title'], 'Legacy detail stays readable without ' . implode('/', $missingColumns));
    $assert(!$detail['review']['seo_indexable'] && $detail['related'] === [], 'Legacy markers remain noindex and do not promote unclassified related entries');
    $assert(!str_contains(implode('\n', $legacy->preparedSql), 'slug<>:slug'), 'No incompatible related SELECT runs against the old table');
}

// Render the real shared head for indexable and noindex review details.
function site_favicon_tags(): string { return ''; }
function site_language_metadata_tags(string $locale, array $alternates = []): string { return ''; }
function site_absolute_url(string $path): string { return 'https://medreview.example' . $path; }
function site_social_meta_tags(array $meta): string { return ''; }
$locale = 'vi'; $title = 'A review'; $description = 'A customer experience'; $seoKeywords = '';
$canonicalPath = '/review-chi-tiet.php?slug=customer-review'; $reviewStylesheet = 'review-detail.css';
$review = review_view_row($base);
foreach ([true, false] as $reviewNoindex) {
    ob_start(); require dirname(__DIR__) . '/Tem/review-head.php'; $html = (string) ob_get_clean();
    $assert(str_contains($html, 'name="robots" content="noindex,follow"') === $reviewNoindex, 'Rendered head applies noindex only when ineligible');
    $assert(str_contains($html, 'rel="canonical"') && str_contains($html, '<title>A review</title>'), 'Noindex keeps readable detail metadata');
}
echo "Review SEO: {$checks} fixture checks passed. No network or production data modified.\n";
