<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/medical_review_view.php';

// These doubles never open a connection. Every statement must be SELECT-only.
final class ReviewViewTestPDO extends PDO
{
    public array $calls = [];

    public function __construct(
        public array $rows = [],
        public array $facilities = [],
        public array $reviewColumns = ['id', 'slug', 'title', 'status', 'rating', 'author_text', 'method_text', 'condition_text', 'excerpt', 'story_json'],
        public array $facilityColumns = ['name', 'slug', 'status', 'address_text'],
    ) {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!preg_match('/^SELECT\b/i', ltrim($query))) throw new RuntimeException('Unexpected mutation: ' . $query);
        return new ReviewViewTestStatement($this, $query);
    }

    public function exec(string $statement): int|false
    {
        throw new RuntimeException('DDL or mutation must not be attempted.');
    }

    public function run(string $sql, array $params): array
    {
        $this->calls[] = ['sql' => $sql, 'params' => $params];
        preg_match_all('/(?<!:):[a-z][a-z0-9_]*/i', $sql, $matches);
        $keys = array_values(array_unique($matches[0]));
        $provided = array_keys($params);
        sort($keys); sort($provided);
        if ($keys !== $provided) throw new RuntimeException('SQL placeholders and parameters differ.');
        if (str_contains($sql, 'INFORMATION_SCHEMA.COLUMNS')) {
            return $params[':table'] === 'medical_reviews' ? $this->reviewColumns : $this->facilityColumns;
        }
        if (str_contains($sql, 'FROM medical_facilities')) {
            return array_values(array_filter($this->facilities, static function (array $row) use ($sql, $params): bool {
                return !str_contains($sql, '1=0') && ($row['status'] ?? '') === 'published'
                    && ($row['slug'] ?? '') === $params[':slug']
                    && (!isset($params[':locale']) || ($row['language_code'] ?? '') === $params[':locale']);
            }));
        }
        $rows = array_values(array_filter($this->rows, static function (array $row) use ($sql, $params): bool {
            if (str_contains($sql, '1=0') || ($row['status'] ?? '') !== 'published'
                || trim((string) ($row['slug'] ?? '')) === '' || trim((string) ($row['title'] ?? '')) === '') return false;
            if (isset($params[':locale']) && ($row['language_code'] ?? '') !== $params[':locale']) return false;
            if (isset($params[':slug'])) {
                $same = ($row['slug'] ?? '') === $params[':slug'];
                if (str_contains($sql, 'slug<>:slug') ? $same : !$same) return false;
            }
            if (str_contains($sql, medical_review_seo_indexable_where()) && !medical_review_seo_indexable($row)) return false;
            if (isset($params[':rating']) && ((float) ($row['rating'] ?? 0) < $params[':rating'] || (float) ($row['rating'] ?? 0) > 5)) return false;
            if (isset($params[':q0'])) {
                $literal = preg_replace_callback('/!([!%_])/', static fn(array $m): string => $m[1], substr($params[':q0'], 1, -1));
                $match = false;
                foreach (['title', 'facility_name', 'service_text', 'location_text', 'author_text', 'excerpt'] as $column) {
                    if (mb_stripos((string) ($row[$column] ?? ''), (string) $literal) !== false) $match = true;
                }
                if (!$match) return false;
            }
            if (str_contains($sql, ' AND (TRIM(COALESCE(before_image_url')) {
                $photo = false;
                foreach (['before_image_url', 'after_image_url'] as $column) {
                    if (preg_match('~^(https?://|/[^/])~', trim((string) ($row[$column] ?? '')))) $photo = true;
                }
                if (!$photo) return false;
            }
            return true;
        }));
        if (str_starts_with($sql, 'SELECT COUNT(*) total')) {
            $rated = array_values(array_filter($rows, static fn(array $row): bool => (float) ($row['rating'] ?? 0) > 0 && (float) ($row['rating'] ?? 0) <= 5));
            $summary = ['total' => count($rows), 'rated_count' => count($rated), 'average' => $rated ? array_sum(array_column($rated, 'rating')) / count($rated) : null,
                'verified_count' => count(array_filter($rows, static fn(array $row): bool => (int) ($row['verified'] ?? 0) === 1)),
                'photo_count' => count(array_filter($rows, static fn(array $row): bool => review_view_safe_url($row['before_image_url'] ?? '') !== '' || review_view_safe_url($row['after_image_url'] ?? '') !== ''))];
            foreach (range(1, 5) as $score) $summary['stars_' . $score] = 0;
            foreach ($rated as $row) $summary['stars_' . max(1, min(5, (int) round((float) $row['rating'])))]++;
            return [$summary];
        }
        if (str_starts_with($sql, 'SELECT COUNT(*) FROM')) return [[count($rows)]];
        usort($rows, static function (array $a, array $b) use ($sql, $params): int {
            if (isset($params[':facility'])) {
                $match = (($b['facility_slug'] ?? '') === $params[':facility'] ? 1 : 0) <=> (($a['facility_slug'] ?? '') === $params[':facility'] ? 1 : 0);
                if ($match !== 0) return $match;
            }
            if (str_contains($sql, 'ORDER BY rating DESC')) {
                $rating = ((float) ($b['rating'] ?? 0)) <=> ((float) ($a['rating'] ?? 0));
                if ($rating !== 0) return $rating;
            }
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')) ?: (($b['id'] ?? 0) <=> ($a['id'] ?? 0));
        });
        if (preg_match('/LIMIT (\d+)(?: OFFSET (\d+))?/', $sql, $limit)) $rows = array_slice($rows, (int) ($limit[2] ?? 0), (int) $limit[1]);
        return $rows;
    }
}

final class ReviewViewTestStatement extends PDOStatement
{
    private array $rows = [];
    private int $cursor = 0;
    public function __construct(private ReviewViewTestPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->rows = $this->pdo->run($this->sql, $params ?? []);
        $this->cursor = 0;
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->rows[$this->cursor++] ?? false; }
    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[$this->cursor++] ?? null;
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
    }
}

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};
$raw = ['id' => 1, 'slug' => 'chia-se', 'title' => 'Trải nghiệm <Test> & mắt', 'facility_slug' => 'phong-kham', 'facility_name' => 'Phòng khám <A>',
    'author_text' => 'Người chia sẻ', 'location_text' => 'Hà Nội', 'review_date_text' => '10/10/2026', 'rating' => '4.7', 'verified' => 0,
    'price_text' => '500.000 VND', 'excerpt' => 'Nội dung <script>alert(1)</script>', 'service_text' => 'Khám mắt', 'method_text' => 'Tư vấn',
    'duration_text' => 'Một giờ', 'start_date_text' => '', 'end_date_text' => '', 'condition_text' => 'Khô mắt', 'source_text' => 'https://example.com/review?id=1&x=2',
    'before_image_url' => '/uploads/before.webp', 'after_image_url' => 'https://example.com/after.webp',
    'story_json' => '[" Đoạn đầu ","",null,{"unexpected":"nested"},"Đoạn sau"]',
    'timeline_json' => '[{"date":"10/10","label":"Khám đầu tiên"},{"time":"11/10","title":"Tái khám","description":"Trao đổi với bác sĩ"},[],"bad"]',
    'thumbs_json' => '["/uploads/before.webp",{"url":"/uploads/reception.webp","caption":"Lễ tân","alt":"Phòng lễ tân"},"javascript:alert(1)","//unsafe.example/x.jpg"]',
    'process_during_json' => '[{"src":"/uploads/treatment.webp","label":"Khám tại phòng"}]', 'status' => 'published', 'created_at' => '2026-10-10 10:00:00'];
$item = review_view_row($raw);
$assert($item['story'] === ['Đoạn đầu', 'Đoạn sau'], 'Story rejects nested values and trims empty paragraphs');
$assert(count($item['timeline']) === 2 && $item['timeline'][0]['title'] === 'Khám đầu tiên' && $item['timeline'][1]['text'] === 'Trao đổi với bác sĩ', 'Timeline aliases preserve existing schema');
$assert(count($item['media']) === 4, 'Media deduplicates URLs and rejects unsafe schemes');
$assert($item['media'][3]['label'] === 'Lễ tân' && $item['media'][3]['alt'] === 'Phòng lễ tân', 'Real captions and alt text retained');
$assert($item['media'][2]['label'] === 'Khám tại phòng' && $item['media'][2]['alt'] === 'Khám tại phòng — ' . $item['title'], 'Missing alt has descriptive caption/title fallback');
$assert($item['source'] === 'example.com' && $item['source_url'] === $raw['source_text'], 'Source labels are based on actual recorded URL');
$assert(!$item['is_verified'] && !$item['is_editorial'] && $item['rating'] === 4.7, 'No invented verification or editorial identity');
$assert(review_view_row(array_replace($raw, ['rating' => 0]))['rating'] === null, 'Rating zero is an unrated state');
foreach ([-1, 6, null, 'bad', []] as $badRating) $assert(review_view_row(array_replace($raw, ['rating' => $badRating]))['rating'] === null, 'Invalid score excluded');
$assert(!review_view_row(array_replace($raw, ['rating' => 0]))['is_editorial'], 'Ordinary unrated review is not an editorial article');
$assert(review_view_row(array_replace($raw, ['condition_text' => 'Nội dung biên tập, không phải đánh giá khách hàng']))['is_editorial'], 'Explicit condition marker identifies editorial data');
$assert(review_view_row(array_replace($raw, ['author_text' => 'Ban biên tập MedReview', 'method_text' => 'Thông tin tổng hợp']))['is_editorial'], 'Explicit author and method identify editorial data');
$assert(!review_view_row(array_replace($raw, ['author_text' => 'Ban biên tập MedReview']))['is_editorial'], 'Author marker alone does not invent editorial type');
$empty = review_view_row(['title' => [], 'slug' => null, 'story_json' => '{invalid', 'timeline_json' => 99, 'thumbs_json' => true]);
$assert($empty['title'] === '' && $empty['story'] === [] && $empty['timeline'] === [] && $empty['media'] === [], 'Malformed JSON and scalar arrays normalize safely');
$assert(review_view_row(array_replace($raw, ['source_text' => '', 'condition_text' => 'Khô mắt']))['source'] === '', 'Condition is not misrepresented as a source');
$assert(review_view_row($raw, 'en')['media'][0]['label'] === 'Before treatment', 'English media labels do not reuse Vietnamese UI');
$manyPhotos = review_view_row(['title' => 'Ảnh', 'thumbs' => array_map(static fn(int $id): string => '/uploads/' . $id . '.jpg', range(1, 30))]);
$assert(count($manyPhotos['media']) === 18, 'Gallery has a bounded image limit');
foreach (['javascript:alert(1)', 'data:image/svg+xml,a', '//example.com/a', 'https://user:secret@example.com/a', "https://example.com/a\nb", 'https://example.com/with space', 'https:\\example.com/a', ['url' => '/a']] as $unsafe) {
    $assert(review_view_safe_url($unsafe) === '', 'Unsafe media or source URL rejected');
}
$assert(review_view_safe_url('/uploads/a.webp') === '/uploads/a.webp' && review_view_safe_url('/uploads/a.webp', false) === '', 'Local images allowed but not external source links');
$assert(review_view_safe_url('https://example.com/a?x=1&y=2') !== '' && review_view_safe_url('http://example.com/a') !== '', 'Existing HTTP and HTTPS images remain supported');
$assert(review_view_escape('<img src=x onerror="bad"> &') === '&lt;img src=x onerror=&quot;bad&quot;&gt; &amp;' && review_view_escape([]) === '', 'Text and attributes escape untrusted database values');
$assert(review_view_text('Việt', 'English', 'en') === 'English' && review_view_text('Việt', 'English', 'vi') === 'Việt', 'UI locale helper');
$assert(str_contains(review_view_icon('unknown'), 'aria-hidden="true"') && !str_contains(review_view_icon('<bad>'), '<bad>'), 'Icons are decorative and names cannot inject markup');

$filters = review_view_filters([]);
$assert($filters === ['q' => '', 'rating' => '', 'media' => '', 'sort' => 'newest'], 'Default filters are stable');
$assert(review_view_filters(['q' => [], 'rating' => [], 'media' => [], 'sort' => [], 'unknown' => 'bad']) === $filters, 'Array-shaped GET values are ignored');
$assert(mb_strlen(review_view_filters(['q' => str_repeat('á', 150)])['q']) === 120, 'Search capped by characters, not UTF-8 bytes');
$assert(review_view_filters(['q' => ' mắt ', 'rating' => '5', 'media' => 'photos', 'sort' => 'rating']) === ['q' => 'mắt', 'rating' => '5', 'media' => 'photos', 'sort' => 'rating'], 'Only allowed filters accepted');
$assert(review_view_filters(['rating' => '3', 'media' => 'all', 'sort' => 'DROP TABLE']) === $filters, 'Unsupported filters cannot control SQL');
foreach ([[], ['page' => 0], ['page' => -1], ['page' => []], ['page' => true], ['page' => '1.5'], ['page' => '1e3'], ['page' => 'bad'], ['page' => '9999999999999999999999999999999']] as $query) {
    $assert(review_view_page($query) === 1, 'Malformed, negative or overflowing page rejected');
}
$assert(review_view_page(['page' => '2']) === 2 && review_view_page(['page' => 3]) === 3, 'Positive scalar pages accepted');
$assert(review_view_url('/review', $filters) === '/review', 'Default URLs are canonical and omit stale page');
$assert(review_view_url('/en/review', ['q' => 'mắt & trẻ em', 'rating' => '4', 'media' => 'photos', 'sort' => 'rating'], 2) === '/en/review?q=m%E1%BA%AFt%20%26%20tr%E1%BA%BB%20em&rating=4&media=photos&sort=rating&page=2', 'Pagination retains locale and safely encoded filters');
$assert(review_view_detail_url('a&slug=<bad>') === '/review-chi-tiet.php?slug=a%26slug%3D%3Cbad%3E' && str_ends_with(review_view_detail_url('example', 'en'), '&lang=en'), 'Existing indexed detail route and locale preserved');

$rows = [];
foreach (range(1, 19) as $id) $rows[] = array_replace($raw, ['id' => $id, 'slug' => 'review-' . $id, 'created_at' => sprintf('2026-10-%02d 10:00:00', $id), 'rating' => $id === 1 ? 0.2 : ($id === 2 ? 0 : 4.7)]);
$rows[] = array_replace($raw, ['id' => 20, 'slug' => 'draft', 'status' => 'draft']);
$rows[] = array_replace($raw, ['id' => 21, 'slug' => ' ', 'title' => 'Missing slug']);
$rows[] = array_replace($raw, ['id' => 22, 'slug' => 'missing-title', 'title' => ' ']);
$pdo = new ReviewViewTestPDO($rows);
$page = review_view_directory($pdo, $filters, 999);
$assert($page['pagination'] === ['total' => 19, 'page' => 3, 'pages' => 3, 'per_page' => 8, 'start' => 17, 'end' => 19], 'Directory clamps page and excludes drafts or blank identities');
$assert(array_column($page['items'], 'id') === [3, 2, 1], 'SQL pagination has stable newest ordering');
$assert($page['summary']['total'] === 19 && $page['summary']['rated_count'] === 18 && $page['summary']['breakdown'][1] === 1 && $page['summary']['breakdown'][5] === 17, 'Zero ratings excluded and low fractional rating represented in one-star bucket');
$assert(array_sum($page['summary']['breakdown']) === $page['summary']['rated_count'] && array_keys($page['summary']['breakdown']) === [5, 4, 3, 2, 1], 'Every rated review belongs to exactly one of five buckets');
$assert(str_contains($pdo->calls[1]['sql'], 'GREATEST(1,LEAST(5,ROUND(rating)))'), 'Actual summary SQL clamps all fractional bucket values');
$assert(!str_contains($pdo->calls[1]['sql'], 'LIKE :q'), 'Summary remains explicitly directory-wide rather than filtered result totals');
$assert(review_view_directory(new ReviewViewTestPDO($rows), $filters, 1, 'vi', 0)['pagination']['per_page'] === 1, 'Per-page cannot divide by zero');
$assert(review_view_directory(new ReviewViewTestPDO($rows), $filters, 1, 'vi', 1000)['pagination']['per_page'] === 24, 'Per-page SQL limit bounded');
$missing = review_view_directory(new ReviewViewTestPDO([], [], []), $filters, 999);
$assert($missing['items'] === [] && $missing['summary']['total'] === 0 && $missing['pagination']['page'] === 1, 'Missing table metadata does not inject demo reviews');
$noRows = review_view_directory(new ReviewViewTestPDO(), $filters, 999);
$assert($noRows['items'] === [] && $noRows['summary']['average'] === 0.0 && $noRows['pagination']['start'] === 0 && $noRows['pagination']['end'] === 0, 'Empty database has truthful summary and valid range');
$legacyEn = review_view_directory(new ReviewViewTestPDO($rows), $filters, 1, 'en');
$assert($legacyEn['items'] === [] && $legacyEn['summary']['total'] === 0, 'Legacy Vietnamese reviews are not shown as English translations');
$mixedRows = [array_replace($raw, ['id' => 1, 'slug' => 'vietnamese', 'language_code' => 'vi']), array_replace($raw, ['id' => 2, 'slug' => 'english', 'language_code' => 'en'])];
foreach (['vi' => 'vietnamese', 'en' => 'english'] as $locale => $slug) {
    $localized = review_view_directory(new ReviewViewTestPDO($mixedRows, [], ['id', 'language_code']), $filters, 1, $locale);
    $assert(array_column($localized['items'], 'slug') === [$slug], 'Localized directory isolates ' . $locale . ' rows');
}
$searchPdo = new ReviewViewTestPDO([array_replace($raw, ['title' => "Literal %_! ' OR 1=1"])]);
$search = review_view_directory($searchPdo, review_view_filters(['q' => "%_! ' OR 1=1", 'rating' => '4', 'media' => 'photos', 'sort' => 'rating']), 1);
$assert(count($search['items']) === 1, 'Literal wildcard search remains searchable');
$filteredSql = $searchPdo->calls[count($searchPdo->calls) - 1];
$assert(!str_contains($filteredSql['sql'], "' OR 1=1") && str_contains($filteredSql['sql'], "ESCAPE '!'"), 'Search text never interpolated into SQL');
foreach (range(0, 5) as $index) $assert(($filteredSql['params'][':q' . $index] ?? '') === "%!%!_!! ' OR 1=1%", 'Distinct search placeholder safely escapes wildcards ' . $index);
$assert($filteredSql['params'][':rating'] === 4 && str_contains($filteredSql['sql'], 'ORDER BY rating DESC') && str_contains($filteredSql['sql'], "REGEXP '^(https?://|/[^/])'"), 'Rating, media filter and sort have real SELECT clauses');
$assert(review_view_directory(new ReviewViewTestPDO([$raw]), review_view_filters(['q' => 'absent']), 3)['pagination']['total'] === 0, 'Search filtering precedes pagination');
$partialPdo = new ReviewViewTestPDO([$raw]);
$partialData = review_view_directory($partialPdo, review_view_filters([]), 1, 'vi', 8, false);
$assert(count($partialData['items']) === 1 && $partialData['pagination']['total'] === 1, 'Partial updates use the same visible records and total');
$assert(count($partialPdo->calls) === 3 && !str_contains(implode(' ', array_column($partialPdo->calls, 'sql')), 'AVG('), 'Partial update skips unchanged global summary aggregation');

$facilityRaw = ['slug' => 'phong-kham', 'name' => 'Cơ sở <A>', 'status' => 'published', 'city' => 'Hà Nội', 'address_text' => '95 Nguyễn Lương Bằng', 'rating' => 4.8, 'reviews_count' => 37, 'image_url' => '/uploads/facility.webp'];
$detailPdo = new ReviewViewTestPDO([$raw,
    array_replace($raw, ['id' => 2, 'slug' => 'related', 'facility_slug' => 'phong-kham', 'excerpt' => str_repeat('Customer experience. ', 8)]),
    array_replace($raw, ['id' => 3, 'slug' => 'thin-related', 'facility_slug' => 'phong-kham']),
    array_replace($raw, ['id' => 4, 'slug' => 'editorial-related', 'facility_slug' => 'phong-kham', 'author_text' => 'Ban biên tập MedReview', 'excerpt' => str_repeat('Editorial information. ', 8)]),
], [$facilityRaw]);
$detail = review_view_detail($detailPdo, 'chia-se');
$assert($detail['review']['title'] === $raw['title'] && array_column($detail['related'], 'slug') === ['related'], 'Detail preserves actual article and excludes itself from related list');
$assert(!$detail['review']['seo_indexable'] && $detail['related'][0]['seo_indexable'], 'Thin review remains readable while only eligible customer reviews are linked as related');
$assert($detail['facility']['location'] === '95 Nguyễn Lương Bằng' && $detail['facility']['url'] === '/co-so-y-te/phong-kham', 'Actual address_text and published facility link preserved');
$assert($detail['facility']['review_count'] === 37 && $detail['facility']['rating'] === 4.8, 'Facility aggregate data is separate from individual review score');
$cityOnly = review_view_detail(new ReviewViewTestPDO([$raw], [array_replace($facilityRaw, ['address_text' => ''])]), 'chia-se');
$assert($cityOnly['facility']['location'] === 'Hà Nội', 'Missing address falls back to real city');
$unpublishedFacility = review_view_detail(new ReviewViewTestPDO([$raw], [array_replace($facilityRaw, ['status' => 'draft'])]), 'chia-se');
$assert($unpublishedFacility['facility']['name'] === $raw['facility_name'] && $unpublishedFacility['facility']['url'] === '' && $unpublishedFacility['facility']['rating'] === 0.0, 'Unpublished facility has unlinked recorded name, no guessed link or rating');
$assert(review_view_detail(new ReviewViewTestPDO([$raw]), 'does-not-exist')['review'] === null, 'Unknown review is not replaced with demo article');
$assert(review_view_detail(new ReviewViewTestPDO([array_replace($raw, ['status' => 'draft'])]), 'chia-se')['review'] === null, 'Draft detail is never public');
$assert(review_view_detail(new ReviewViewTestPDO([$raw]), 'chia-se', 'en')['review'] === null, 'Legacy detail is unavailable in English rather than duplicated');
$emptySlugPdo = new ReviewViewTestPDO([$raw]);
$assert(review_view_detail($emptySlugPdo, '')['review'] === null && $emptySlugPdo->calls === [], 'Empty slug never queries any table');
$enDetail = review_view_detail(new ReviewViewTestPDO([array_replace($raw, ['language_code' => 'en'])], [array_replace($facilityRaw, ['language_code' => 'en'])], ['language_code'], ['language_code']), 'chia-se', 'en');
$assert($enDetail['review'] !== null && $enDetail['facility']['url'] === '/en/co-so-y-te/phong-kham', 'English detail and linked facility use actual English row and localized route');
foreach (array_merge($pdo->calls, $searchPdo->calls, $detailPdo->calls) as $call) {
    $assert(str_starts_with($call['sql'], 'SELECT ') && !preg_match('/\b(?:CREATE|ALTER|INSERT|UPDATE|DELETE|REPLACE|DROP|TRUNCATE)\b/i', $call['sql']), 'Public presentation issues SELECT queries only');
}

// Render only the isolated partials, never application bootstrap or DB headers.
$locale = 'vi'; $isEnglish = false; $reviewBasePath = '/review'; $reviewContactPath = '/lien-he';
$reviewFilters = $filters; $reviewRows = [$item]; $reviewSummary = review_view_empty_summary();
$reviewPagination = ['total' => 1, 'page' => 1, 'pages' => 1, 'per_page' => 8, 'start' => 1, 'end' => 1]; $reviewUnavailable = false;
ob_start(); require dirname(__DIR__) . '/Tem/review-directory.php'; $directoryHtml = (string) ob_get_clean();
$assert(str_contains($directoryHtml, 'Trải nghiệm &lt;Test&gt; &amp; mắt') && !str_contains($directoryHtml, '<script>alert(1)</script>'), 'Directory escapes title and excerpt');
$assert(str_contains($directoryHtml, 'method="get"') && str_contains($directoryHtml, 'name="q"') && str_contains($directoryHtml, 'type="submit"'), 'Search and filters work without JavaScript');
$assert(!str_contains($directoryHtml, 'javascript:') && !str_contains($directoryHtml, 'src=""'), 'Directory has no unsafe or empty media requests');
$assert(str_contains($directoryHtml, 'id="reviewDirectoryFilter"') && substr_count($directoryHtml, 'form="reviewDirectoryFilter"') === 4, 'Detached rating, media, sort and apply button belong to the search form');
$assert(str_contains($directoryHtml, 'data-review-filters>') && !str_contains($directoryHtml, 'data-review-filters open'), 'Filter disclosure starts closed on desktop and mobile');
$assert(str_contains($directoryHtml, 'class="rv-dir-search-submit"') && str_contains($directoryHtml, 'aria-label="Tìm kiếm"'), 'Compact icon submit has an accessible name');
$assert(str_contains($directoryHtml, 'id="reviewResults"') && str_contains($directoryHtml, 'id="reviewDirectoryStatus"'), 'Progressive updates have a stable result region and status');
$review = $item; $reviewFacility = $detail['facility']; $relatedReviews = [$item];
ob_start(); require dirname(__DIR__) . '/Tem/review-detail.php'; $detailHtml = (string) ob_get_clean();
$assert(str_contains($detailHtml, 'Trải nghiệm &lt;Test&gt; &amp; mắt') && str_contains($detailHtml, 'Cơ sở &lt;A&gt;'), 'Detail escapes article and linked facility');
$assert(str_contains($detailHtml, 'data-image-label="Lễ tân"') && str_contains($detailHtml, 'data-image-alt="Phòng lễ tân"'), 'Gallery hooks carry real captions and alt text');
$assert(str_contains($detailHtml, '<dialog ') && str_contains($detailHtml, 'data-lightbox-close') && str_contains($detailHtml, 'aria-label='), 'Accessible native viewer has labelled close control');
$assert(!str_contains($detailHtml, '<form') && !str_contains($detailHtml, 'Có hóa đơn, chứng từ') && !str_contains($detailHtml, 'Đã xác minh danh tính'), 'No inert comment forms or invented verification evidence');
$assert(str_contains($detailHtml, 'rel="noopener noreferrer"') && str_contains($detailHtml, 'href="https://example.com/review?id=1&amp;x=2"'), 'Actual source links escaped and safe for new tabs');

echo "Review view: {$checks} checks passed. No database connection, network or records modified.\n";
