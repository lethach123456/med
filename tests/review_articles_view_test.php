<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_review_articles.php';
require_once dirname(__DIR__) . '/medical_review_articles_view.php';

// No PDO, network, live rows or fixtures are written. Templates receive arrays only.
$assertions = 0;
$check = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$check(review_articles_view_escape('<script>"&') === '&lt;script&gt;&quot;&amp;', 'Text is HTML escaped.');
$check(review_articles_view_escape(['unexpected']) === '', 'Non-scalar markup cannot leak.');
$check(review_articles_view_filters(['q' => ['nested'], 'city' => ' Đà Nẵng ', 'category' => '<b>Test</b>']) === ['q' => '', 'category' => '<b>Test</b>', 'city' => 'Đà Nẵng'], 'Filters retain safe raw text for parameterized search.');
$check(mb_strlen(review_articles_view_filters(['q' => str_repeat('ư', 160)])['q']) === 120, 'Search limit uses Unicode characters.');
foreach ([[], '0', '-1', '1.5', '1000001', '<script>'] as $value) $check(review_articles_view_page($value) === 1, 'Invalid page is bounded.');
$check(review_articles_view_page('42') === 42, 'Positive page is accepted.');
$check(review_articles_view_path('en', 'review-test') === '/en/review/review-test', 'English detail uses its own clean stream.');
$check(review_articles_view_path('vi', '<x>') === '/review/%3Cx%3E', 'Slug is URL encoded.');
$check(review_articles_view_page_url('en', ['q' => 'cơ sở & bác sĩ'], 2) === '/en/review?q=c%C6%A1%20s%E1%BB%9F%20%26%20b%C3%A1c%20s%C4%A9&page=2', 'Pagination preserves encoded query and locale.');
$check(review_articles_view_page_url('vi', [], 1) === '/review', 'Default page does not create duplicate query route.');
$check(review_articles_view_date('2026-10-22 09:30:00', 'vi')['label'] === '22/10/2026', 'Vietnamese date uses local readable format.');
$check(review_articles_view_date('2026-10-22', 'en')['label'] === 'Oct 22, 2026', 'English date is English.');
$check(review_articles_view_date('2026-10-20 20:00:00', 'vi')['label'] === '21/10/2026', 'Naive UTC storage is presented in Vietnam local time.');
$check(review_articles_view_date('2026-10-20T20:00:00Z', 'vi')['datetime'] === '2026-10-21T03:00:00+07:00', 'Explicit UTC offset retains its exact instant.');
foreach (['yesterday', '2026-02-31', '', '<time>'] as $value) $check(review_articles_view_date($value, 'vi') === [], 'Invalid dates are not invented.');
foreach (['javascript:alert(1)', 'data:image/png;base64,x', 'blob:https://example.com/x', '//example.com/x', 'https://user@example.com/a', 'https://example.com/a%0aX:bad', 'https://127.0.0.1/a'] as $value) $check(review_articles_view_safe_url($value) === '', 'Unsafe public URL is rejected: ' . $value);
$check(review_articles_view_safe_url('/uploads/library/real.webp') === '/uploads/library/real.webp', 'Local images remain usable.');
$check(review_articles_view_safe_url('https://example.com/source', false) === 'https://example.com/source', 'Public HTTP source is accepted without fetching.');
$check(review_articles_view_safe_url('/private/path', false) === '', 'Sources must use public absolute URLs.');
$safe = review_articles_view_html('<p onclick="x()">Đoạn thật <strong>rõ ràng</strong>.</p><script>secret()</script><iframe src="https://example.com"></iframe><a href="javascript:alert(1)">link</a>');
$check(str_contains($safe, '<p>') && str_contains($safe, '<strong>rõ ràng</strong>'), 'Long-form paragraphs and emphasis survive sanitization.');
$check(!str_contains($safe, 'onclick') && !str_contains($safe, '<script') && !str_contains($safe, '<iframe') && !str_contains($safe, 'javascript:'), 'Public body HTML is sanitized at render boundary.');
$check(review_articles_view_list('["Ý một","",null,"Ý hai"]') === ['Ý một', 'Ý hai'], 'Editorial lists discard malformed items.');
$check(review_articles_view_list(['not' => 'a list']) === [], 'Arbitrary objects are not rendered as lists.');

$fixture = ['id' => 90001, 'entity_type' => 'facility', 'entity_id' => 90002, 'slug' => 'review-fixture', 'language_code' => 'vi',
    'title' => 'Review cơ sở <Test> & góc nhìn', 'excerpt' => 'Thông tin <được> đối chiếu từ hồ sơ và nguồn công khai.',
    'content_html' => '<p>PHẦN DỰ PHÒNG KHÔNG HIỂN THỊ LẶP.</p>', 'verdict' => 'Nhận định tổng quan, không phải lời chứng thực.',
    'analysis_sections_json' => [['id' => 'unsafe" onclick="x', 'title' => 'Dịch vụ & cơ sở vật chất', 'content_html' => '<p>Nội dung phân tích duy nhất.</p><script>bad()</script>', 'source_ids' => ['official', 'bad', 'missing', 'official']], ['title' => '', 'content_html' => '<p>Bỏ mục thiếu tiêu đề.</p>']],
    'strengths_json' => ['Dịch vụ được mô tả rõ.'], 'considerations_json' => ['Cần xác nhận chi phí trước khi khám.'], 'suitable_for_json' => ['Người cần tìm hiểu dịch vụ đã công bố.'],
    'sources_json' => [['id' => 'official', 'title' => 'Giới thiệu <cơ sở>', 'url' => 'https://example.com/about', 'platform' => 'Website'], ['id' => 'bad', 'title' => 'Không hợp lệ', 'url' => 'javascript:alert(1)']],
    'evidence_limitations_json' => ['Không đánh giá kết quả điều trị lâm sàng.'],
    'published_at' => '2026-10-20 09:30:00', 'updated_at' => '2026-10-22 09:30:00', 'researched_at' => '2026-10-21', 'reading_minutes' => 5,
    'source_review_count' => 1248, 'source_rating' => 4.5, 'source_rating_basis' => 'external_aggregates', 'cover_image_url' => '',
    'facility' => ['id' => 90002, 'name' => 'Cơ sở <Test>', 'slug' => 'co-so-test', 'category' => 'Nha khoa', 'city' => 'Đà Nẵng', 'address_text' => 'Địa chỉ mẫu để kiểm thử', 'url' => '/co-so-y-te/co-so-test']];
$sections = review_articles_view_sections($fixture, 'vi');
$check(count($sections) === 1 && $sections[0]['id'] === 'review-analysis-1', 'Section IDs are stable and trusted, malformed sections skipped.');
$check(!str_contains($sections[0]['content_html'], '<script'), 'Section HTML is sanitized.');
$check(count($sections[0]['sources']) === 1 && $sections[0]['sources'][0]['anchor'] === 'review-source-1', 'Section citations reference valid deduplicated known sources only.');
$toc = review_articles_view_toc($fixture, $sections, 'vi');
$check(array_column($toc, 'id') === ['review-overview', 'review-analysis-1', 'review-observations', 'review-suitability', 'review-sources'], 'TOC maps exactly to rendered editorial sections.');
$tocHtml = review_articles_view_toc_html($toc);
$check(str_contains($tocHtml, 'Dịch vụ &amp; cơ sở vật chất') && !str_contains($tocHtml, 'onclick'), 'TOC labels and anchors are escaped.');
$fallback = $fixture; $fallback['analysis_sections_json'] = [];
$check(review_articles_view_sections($fallback, 'en')[0]['title'] === 'Detailed analysis', 'HTML-only article has a localized fallback reading section.');
$card = review_articles_view_card($fixture, 'vi');
$check(str_contains($card, 'Review cơ sở &lt;Test&gt; &amp; góc nhìn') && !str_contains($card, '<Test>'), 'Card title is escaped.');
$check(str_contains($card, '/review/review-fixture') && !str_contains($card, 'review-chi-tiet.php'), 'Editorial card does not link to individual feedback.');
$check(str_contains($card, '5 phút đọc') && str_contains($card, '20/10/2026'), 'Card shows honest reading time and recorded date.');
$check(!str_contains($card, '1.248') && !str_contains($card, 'ph-star'), 'Listing does not present aggregate scores as article author ratings.');
$cardEn = review_articles_view_card($fixture, 'en');
$check(str_contains($cardEn, '/en/review/review-fixture') && str_contains($cardEn, 'min read') && str_contains($cardEn, 'Read article'), 'Card localization does not mix article streams.');
$paging = ['total' => 106, 'pages' => 12, 'page' => 6, 'per_page' => 9, 'start' => 46, 'end' => 54];
$pagination = review_articles_view_pagination($paging, ['city' => 'Đà Nẵng'], 'vi');
$check(str_contains($pagination, 'aria-current="page"') && str_contains($pagination, 'rel="next"') && str_contains($pagination, 'rel="prev"'), 'Pagination exposes current and neighboring pages.');
$check(str_contains($pagination, 'city=%C4%90%C3%A0%20N%E1%BA%B5ng') && str_contains($pagination, '46–54 / 106'), 'Pagination preserves filters and count context.');
$check(review_articles_view_pagination(['total' => 1, 'pages' => 1], [], 'vi') === '', 'Single page needs no pagination controls.');

$renderDirectory = static function (array $items, bool $unavailable = false, string $language = 'vi', array $filters = []) use ($fixture): string {
    $locale = $language; $isEnglish = $locale === 'en'; $e = 'review_articles_view_escape';
    $reviewArticleFilters = review_articles_view_filters($filters);
    $reviewArticleUnavailable = $unavailable;
    $canonicalPath = review_articles_view_page_url($locale, $reviewArticleFilters);
    $reviewArticlePaging = ['total' => count($items), 'published_total' => count($items), 'items' => $items, 'page' => 1, 'pages' => 1, 'start' => count($items) ? 1 : 0, 'end' => count($items), 'facets' => ['cities' => ['Đà Nẵng'], 'categories' => ['Nha khoa']]];
    ob_start(); require dirname(__DIR__) . '/Tem/review-articles-directory.php'; return (string) ob_get_clean();
};
$directory = $renderDirectory([$fixture]);
$check(str_contains($directory, 'Review & phân tích') && str_contains($directory, 'id="review-articles-heading"'), 'Directory is an editorial list.');
$check(str_contains($directory, 'label class="ra-sr-only" for="review-article-search"') && str_contains($directory, 'method="get"'), 'Search has an accessible label and server-rendered GET fallback.');
$check(str_contains($directory, 'form="reviewArticleSearch"') && str_contains($directory, 'data-review-article-filters'), 'Native filter disclosure submits through the same search form.');
$check(!str_contains($directory, 'Chưa có bài viết phù hợp') && !str_contains($directory, 'ph-star'), 'Populated editorial list neither invents zero results nor displays patient stars.');
$check(str_contains($renderDirectory([]), 'Những góc nhìn đang được hoàn thiện'), 'True empty state contains no seeded fake article.');
$check(str_contains($renderDirectory([], false, 'vi', ['q' => '<img src=x>']), 'Chưa có bài viết phù hợp') && !str_contains($renderDirectory([], false, 'vi', ['q' => '<img src=x>']), '<img src=x>'), 'Filtered empty state is distinct and query escaped.');
$check(str_contains($renderDirectory([], true), 'Chưa tải được các bài review'), 'Unavailable state is not misrepresented as no content.');
$check(str_contains($renderDirectory([], false, 'en'), 'New perspectives are on the way') && !str_contains($renderDirectory([], false, 'en'), 'Những góc nhìn'), 'English empty list copy is localized.');

$renderDetail = static function (array $article): string {
    $locale = 'vi'; $isEnglish = false; $e = 'review_articles_view_escape';
    $reviewArticle = $article; $reviewArticleFacility = $article['facility']; $reviewArticleImage = '';
    $reviewArticleSections = review_articles_view_sections($article, $locale); $reviewArticleToc = review_articles_view_toc($article, $reviewArticleSections, $locale); $reviewArticleRelated = [];
    ob_start(); require dirname(__DIR__) . '/Tem/review-article.php'; return (string) ob_get_clean();
};
$detail = $renderDetail($fixture);
$check(str_contains($detail, 'Nội dung phân tích duy nhất.') && !str_contains($detail, 'PHẦN DỰ PHÒNG KHÔNG HIỂN THỊ LẶP'), 'Structured body replaces fallback HTML, preventing duplicate prose.');
$check(str_contains($detail, 'Phân tích có hỗ trợ AI') && str_contains($detail, 'không phải trải nghiệm khám trực tiếp'), 'Editorial provenance does not pretend to be a patient visit.');
$check(!str_contains($detail, '1.248') && !str_contains($detail, '1248') && !str_contains($detail, '4.5 / 5'), 'Internal source counts and scores never appear in the customer article.');
$check(!str_contains($detail, 'Điểm ghi nhận') && !str_contains($detail, 'Đánh giá tổng hợp') && !str_contains($detail, 'Review trên MedReview') && !str_contains($detail, 'ra-facility-facts'), 'Facility sidebar has no rating or review-count panel.');
$check(str_contains($detail, 'Dịch vụ &amp; cơ sở vật chất') && str_contains($detail, 'Điểm nổi bật') && str_contains($detail, 'Điều cần cân nhắc') && str_contains($detail, 'Phù hợp với nhu cầu nào?'), 'Qualitative review sections remain the focus for customers.');
$check(str_contains($detail, 'Bài viết tổng hợp thông tin từ hồ sơ cơ sở, nhận xét công khai') && !str_contains($detail, 'dữ liệu đánh giá được ghi nhận'), 'Methodology is concise, natural and not a statistics report.');
$check(str_contains($detail, '/co-so-y-te/co-so-test') && str_contains($detail, 'Xem hồ sơ cơ sở'), 'Article links directly to canonical facility profile.');
$check(str_contains($detail, 'noopener noreferrer') && str_contains($detail, 'Giới thiệu &lt;cơ sở&gt;') && !str_contains($detail, 'javascript:'), 'Sources open safely with escaped labels and valid URLs only.');
$check(str_contains($detail, 'href="#review-source-1"') && str_contains($detail, 'id="review-source-1"') && str_contains($detail, 'Nguồn đối chiếu:'), 'Readers can trace an analysis section to its exact cited source.');
$check(str_contains($detail, 'Không đánh giá kết quả điều trị lâm sàng.') && str_contains($detail, '21/10/2026'), 'Limitations and actual research date are visible.');
$check(substr_count($detail, 'id="review-analysis-1"') === 1 && str_contains($detail, 'href="#review-analysis-1"'), 'TOC section target is unique despite desktop/mobile navigation.');
$check(!str_contains($detail, 'patient_images_json') && !str_contains($detail, 'source_snapshot_json') && !str_contains($detail, 'ai_writer_claim'), 'Private editorial state is never shown.');
$check(str_contains($detail, 'data-review-article-share hidden') && str_contains($detail, 'role="status"'), 'Share action progressively enhances with a screen-reader status.');

// Evidence is private whether stored as its own field or inside AI metadata.
// These are row-shaped arrays only: no database connection or stored fixture.
$privateEvidenceFixture = array_replace($fixture, [
    'ai_content_json' => json_encode(['editorial_evidence_json' => ['summary' => 'PRIVATE_AI_EVIDENCE_SENTINEL']], JSON_THROW_ON_ERROR),
    'editorial_evidence_json' => json_encode(['summary' => 'PRIVATE_TOP_LEVEL_EVIDENCE_SENTINEL'], JSON_THROW_ON_ERROR),
    'facility_id' => $fixture['facility']['id'], 'facility_name' => $fixture['facility']['name'],
    'facility_slug' => $fixture['facility']['slug'], 'facility_category' => $fixture['facility']['category'],
    'facility_city' => $fixture['facility']['city'], 'facility_address' => $fixture['facility']['address_text'], 'facility_image' => '',
]);
$privateEvidenceFixture['analysis_sections_json'][0]['id'] = 'services-and-space';
$publicEvidenceDto = medical_review_articles_public_dto($privateEvidenceFixture);
$check(!array_key_exists('ai_content_json', $publicEvidenceDto), 'Public DTO excludes the entire private AI metadata field.');
$check(!array_key_exists('editorial_evidence_json', $publicEvidenceDto), 'Public DTO excludes a separate private editorial evidence field.');
$privacyOutputs = [
    'public DTO' => json_encode($publicEvidenceDto, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    'directory from public DTO' => $renderDirectory([$publicEvidenceDto]),
    'detail from public DTO' => $renderDetail($publicEvidenceDto),
    // Defense in depth: templates must not dump arbitrary keys if given a row.
    'directory from private row fixture' => $renderDirectory([$privateEvidenceFixture]),
    'detail from private row fixture' => $renderDetail($privateEvidenceFixture),
];
foreach ($privacyOutputs as $privacySurface => $privacyOutput) {
    foreach (['PRIVATE_AI_EVIDENCE_SENTINEL', 'PRIVATE_TOP_LEVEL_EVIDENCE_SENTINEL', 'ai_content_json', 'editorial_evidence_json'] as $privateEvidenceToken) {
        $check(!str_contains($privacyOutput, $privateEvidenceToken), $privacySurface . ' never exposes private evidence: ' . $privateEvidenceToken);
    }
}

$root = dirname(__DIR__);
$directoryController = (string) file_get_contents($root . '/review.php');
$detailController = (string) file_get_contents($root . '/review-bai-viet.php');
$css = (string) file_get_contents($root . '/assets/css/pages/review-articles.css');
$js = (string) file_get_contents($root . '/assets/js/review-articles.js');
$check(str_contains($directoryController, 'medical_review_articles_directory_page') && !str_contains($directoryController, 'review_view_directory('), 'The review menu now queries editorial articles only.');
$check(str_contains($detailController, "'@type' => 'Article'") && !str_contains($detailController, 'aggregateRating'), 'Article schema does not manufacture a review or aggregate rating.');
$check(str_contains($directoryController, 'noindex,follow') && str_contains($directoryController, 'site_language_metadata_tags') && str_contains($detailController, "['alternates']"), 'SEO handles filtered pages and actual language counterparts.');
$check(str_contains($css, '@media(max-width:620px)') && str_contains($css, '@media(max-width:900px)') && str_contains($css, 'minmax(0,1fr)'), 'Phone/tablet layout is adaptive and shrinkable.');
$check(str_contains($css, 'min-height:44px') && str_contains($css, 'font-size:16px') && str_contains($css, 'prefers-reduced-motion'), 'Mobile targets, readable body and reduced-motion handling exist.');
$check(!str_contains($css, 'fonts.googleapis.com') && str_contains($css, 'var(--med-font-sans'), 'No new font dependency is introduced.');
$check(str_contains($css, 'scroll-margin-top:') && str_contains($css, ':focus-visible'), 'Anchor offsets and visible keyboard focus are retained.');
$check(str_contains($js, "event.key !== 'Escape'") && str_contains($js, 'summary.focus()') && str_contains($js, 'navigator.share') && str_contains($js, 'aria-current'), 'Enhancements have keyboard dismissal, sharing and active TOC state.');
$check(!str_contains($js, 'fetch(') && !str_contains($js, 'innerHTML'), 'Reading-page JavaScript does not require network or insert untrusted HTML.');
echo 'review_articles_view_test: ' . $assertions . " assertions passed.\n";
