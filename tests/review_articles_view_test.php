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
$check(array_column($toc, 'id') === ['review-overview', 'review-analysis-1', 'review-observations', 'review-suitability'], 'TOC omits hidden sources and maps to rendered sections.');
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

$renderDetail = static function (array $article, array $photos = [], string $language = 'vi'): string {
    $locale = $language; $isEnglish = $locale === 'en'; $e = 'review_articles_view_escape';
    $reviewArticleCustomerImages = $photos;
    $reviewArticle = $article; $reviewArticleFacility = $article['facility']; $reviewArticleImage = '';
    $reviewArticleSections = review_articles_view_sections($article, $locale); $reviewArticleToc = review_articles_view_toc($article, $reviewArticleSections, $locale); $reviewArticleRelated = [];
    if ($photos !== []) $reviewArticleToc[] = ['id' => 'review-customer-images', 'title' => $isEnglish ? 'Customer photos' : 'Hình ảnh khách hàng'];
    ob_start(); require dirname(__DIR__) . '/Tem/review-article.php'; return (string) ob_get_clean();
};
$detail = $renderDetail($fixture);
$check(str_contains($detail, 'Nội dung phân tích duy nhất.') && !str_contains($detail, 'PHẦN DỰ PHÒNG KHÔNG HIỂN THỊ LẶP'), 'Structured body replaces fallback HTML, preventing duplicate prose.');
$check(!str_contains($detail, 'Phân tích có hỗ trợ AI') && str_contains($detail, 'Review & đánh giá'), 'Detail uses requested review label without AI badge.');
$check(!str_contains($detail, '1.248') && !str_contains($detail, '1248') && !str_contains($detail, '4.5 / 5'), 'Internal source counts and scores never appear in the customer article.');
$check(!str_contains($detail, 'Điểm ghi nhận') && !str_contains($detail, 'Đánh giá tổng hợp') && !str_contains($detail, 'Review trên MedReview') && !str_contains($detail, 'ra-facility-facts'), 'Facility sidebar has no rating or review-count panel.');
$check(str_contains($detail, 'Dịch vụ &amp; cơ sở vật chất') && str_contains($detail, 'Điểm nổi bật') && str_contains($detail, 'Điều cần cân nhắc') && str_contains($detail, 'Phù hợp với nhu cầu nào?'), 'Qualitative review sections remain the focus for customers.');
$check(!str_contains($detail, 'ra-method') && str_contains($detail, 'Review & Đánh giá cơ sở'), 'Sidebar is renamed and methodology is hidden.');
$check(str_contains($detail, '/co-so-y-te/co-so-test') && str_contains($detail, 'Xem hồ sơ cơ sở'), 'Article links directly to canonical facility profile.');
$check(!str_contains($detail, 'javascript:') && !str_contains($detail, 'Nguồn & giới hạn dữ liệu'), 'Hidden source section is not rendered.');
$check(!str_contains($detail, 'href="#review-source-1"') && !str_contains($detail, 'Nguồn đối chiếu:'), 'Hidden sources leave no broken citation navigation.');
$check(str_contains($detail, 'không thay thế tư vấn y khoa'), 'General medical disclaimer is retained.');
$check(substr_count($detail, 'id="review-analysis-1"') === 1 && str_contains($detail, 'href="#review-analysis-1"'), 'TOC section target is unique despite desktop/mobile navigation.');
$check(!str_contains($detail, 'patient_images_json') && !str_contains($detail, 'source_snapshot_json') && !str_contains($detail, 'ai_writer_claim'), 'Private editorial state is never shown.');
$check(str_contains($detail, 'data-review-article-share hidden') && str_contains($detail, 'role="status"'), 'Share action progressively enhances with a screen-reader status.');

$photos = review_articles_view_customer_images(json_encode([
    ['url' => '/uploads/customer.webp', 'consent_confirmed' => true, 'caption' => '<b>Ảnh khách</b>', 'alt' => 'Ảnh <khách>', 'source_url' => 'PRIVATE_SOURCE', 'notes' => 'PRIVATE_NOTES'],
    ['url' => '/uploads/private.webp', 'consent_confirmed' => false],
    ['url' => '/uploads/missing-consent.webp'],
    ['url' => '/uploads/string-consent.webp', 'consent_confirmed' => 'true'],
    ['url' => 'javascript:alert(1)', 'consent_confirmed' => true],
    ['url' => '/uploads/%2e%2e/secret.webp', 'consent_confirmed' => true],
    ['url' => '/uploads/customer.webp#duplicate', 'consent_confirmed' => true],
    ['url' => 'https://example.com/customer.jpg', 'consent_confirmed' => true],
], JSON_THROW_ON_ERROR));
$check(count($photos) === 5 && array_keys($photos[0]) === ['url', 'alt', 'caption'], 'Gallery shows all valid stored photos regardless of consent metadata and exposes only display fields.');
$check(in_array('/uploads/private.webp', array_column($photos, 'url'), true) && in_array('/uploads/missing-consent.webp', array_column($photos, 'url'), true), 'False or omitted consent metadata does not hide a stored photo.');
$manyPhotos = array_map(static fn(int $i): array => ['url' => '/uploads/customer-' . $i . '.webp'], range(1, 100));
$check(count(review_articles_view_customer_images($manyPhotos)) === 100, 'All 100 photos allowed by the storage schema remain visible, not just the first 24.');
$check(review_articles_view_customer_images('{bad') === [] && review_articles_view_customer_images(['url' => '/uploads/test.webp']) === [], 'Malformed gallery metadata fails closed.');
$check(!str_contains($detail, 'id="review-customer-images"') && !str_contains($detail, '<dialog'), 'No stored photos means no empty gallery or modal.');
$galleryDetail = $renderDetail($fixture, $photos);
$check(str_contains($galleryDetail, 'id="review-customer-images"') && str_contains($galleryDetail, '<dialog') && str_contains($galleryDetail, 'loading="lazy"'), 'Customer gallery is progressively enhanced and lazy-loaded.');
$check(str_contains($galleryDetail, '&lt;b&gt;Ảnh khách&lt;/b&gt;') && !str_contains($galleryDetail, 'PRIVATE_SOURCE') && !str_contains($galleryDetail, 'PRIVATE_NOTES'), 'Customer captions are escaped; private photo metadata never appears.');
$check(str_contains($galleryDetail, 'data-review-photo-prev') && str_contains($galleryDetail, 'data-review-photo-close') && str_contains($galleryDetail, 'không đại diện cho kết quả điều trị'), 'Gallery includes viewer controls and a non-promissory treatment disclaimer.');
$check(str_contains($galleryDetail, 'alt="Ảnh &lt;khách&gt;"') && !str_contains($galleryDetail, 'alt="Ảnh <khách>"'), 'Customer image alt text remains escaped in the polished card.');
$check(str_contains($galleryDetail, 'data-review-photo-stage') && str_contains($galleryDetail, 'data-review-photo-status') && str_contains($galleryDetail, 'data-review-photo-retry hidden'), 'Photo viewer has a loading/status stage and a progressively revealed retry action.');

$galleryXpath = static function (string $html): DOMXPath {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previousErrors = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>');
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }
    return new DOMXPath($dom);
};
foreach ([1, 2, 4, 5, 8, 100] as $photoCount) {
    $storedPhotos = review_articles_view_customer_images(array_slice($manyPhotos, 0, $photoCount));
    $photoHtml = $renderDetail($fixture, $storedPhotos);
    $photoDom = $galleryXpath($photoHtml);
    $photoRoot = '//section[@id="review-customer-images"]';
    $links = $photoDom->query($photoRoot . '//a[@data-review-photo]/@href');
    $actualUrls = [];
    foreach ($links as $link) $actualUrls[] = $link->nodeValue;
    $check($actualUrls === array_column($storedPhotos, 'url'), $photoCount . '-photo gallery retains every original image href in stored order, including native disclosure content.');
    $check($photoDom->query($photoRoot . '/div[contains(concat(" ", normalize-space(@class), " "), " ra-customer-grid ")]/figure')->length === min(4, $photoCount), $photoCount . '-photo gallery initially presents at most four preview cards.');
    $more = $photoDom->query($photoRoot . '/details[contains(concat(" ", normalize-space(@class), " "), " ra-gallery-more ")]');
    $check($more->length === ($photoCount > 4 ? 1 : 0), $photoCount . '-photo gallery only needs show-more when more than four stored photos exist.');
    $check($photoDom->query($photoRoot . '/div[contains(concat(" ", normalize-space(@class), " "), " ra-customer-grid--single ")]')->length === ($photoCount === 1 ? 1 : 0), $photoCount . '-photo gallery uses full-width single-photo framing only for one photo.');
    $check(trim($photoDom->query($photoRoot . '/div[contains(@class,"ra-gallery-heading")]/span[contains(@class,"ra-gallery-count")]')->item(0)->textContent) === $photoCount . ' ảnh', $photoCount . '-photo gallery displays the exact total, not the preview count.');
    $check($photoDom->query('//*[@id="review-customer-images"]')->length === 1 && $photoDom->query('//a[@href="#review-customer-images"]')->length === 2, $photoCount . '-photo gallery has a unique section target shared by mobile and desktop contents links.');
    if ($photoCount > 4) {
        $check($photoDom->query($photoRoot . '/details/summary')->length === 1 && $photoDom->query($photoRoot . '/details[@hidden or @open]')->length === 0, $photoCount . '-photo show-more uses a closed native details/summary, not a JavaScript-only hidden region.');
        $check($photoDom->query($photoRoot . '/details/div/figure/a[@data-review-photo]')->length === $photoCount - 4, $photoCount . '-photo gallery places every remaining image inside the native disclosure.');
        $check(str_contains($photoHtml, 'Xem thêm ' . ($photoCount - 4) . ' ảnh'), $photoCount . '-photo show-more names the exact remaining image count.');
    }
}
$check(!str_contains($detail, 'href="#review-customer-images"'), 'No customer photos means neither a gallery nor a dead contents link.');
$englishGallery = $renderDetail($fixture, $photos, 'en');
$check(str_contains($englishGallery, 'Customer photos') && str_contains($englishGallery, 'A closer look') && str_contains($englishGallery, '5 photos') && str_contains($englishGallery, 'View 1 more photos') && str_contains($englishGallery, 'Show fewer photos'), 'English gallery headings, total and native show-more labels are localized.');
$check(str_contains($englishGallery, 'Customer photo viewer') && str_contains($englishGallery, 'Try again') && str_contains($englishGallery, 'Close photo') && !str_contains($englishGallery, 'Hình ảnh khách hàng'), 'English gallery viewer controls do not introduce Vietnamese UI labels.');
$fallbackPhoto = review_articles_view_customer_images([['url' => '/uploads/no-caption.webp']]);
$englishFallback = $renderDetail($fixture, $fallbackPhoto, 'en');
$check(str_contains($englishFallback, 'alt="Customer photo at the facility"') && str_contains($englishFallback, 'aria-label="View photo 1: Customer photo at the facility"'), 'Missing caption and alt use an accessible localized image description.');
$check(!str_contains($englishFallback, '<figcaption>') && !str_contains($englishFallback, 'ra-gallery-more'), 'One image without a caption creates neither an empty card caption nor an unnecessary show-more control.');
$galleryDb = new PDO('sqlite::memory:');
$galleryDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$galleryDb->exec('CREATE TABLE medical_facilities(id INTEGER, status TEXT, language_code TEXT)');
$check(review_articles_view_load_customer_images($galleryDb, 1, 'vi') === [], 'Older schema without patient image column remains readable.');
$galleryDb->exec('ALTER TABLE medical_facilities ADD COLUMN patient_images_json TEXT');
$insertGallery = $galleryDb->prepare('INSERT INTO medical_facilities VALUES(?,?,?,?)');
$testPhotos = json_encode([['url' => '/uploads/allowed.webp', 'consent_confirmed' => false]]);
$insertGallery->execute([1, 'published', 'vi', $testPhotos]);
$insertGallery->execute([2, 'draft', 'vi', $testPhotos]);
$check(count(review_articles_view_load_customer_images($galleryDb, 1, 'vi')) === 1, 'Detail-only reader loads selected published-facility photos.');
$check(review_articles_view_load_customer_images($galleryDb, 1, 'en') === [] && review_articles_view_load_customer_images($galleryDb, 2, 'vi') === [], 'Reader never crosses language or unpublished facility boundaries.');
$check(review_articles_view_load_customer_images($galleryDb, 0, 'vi') === [], 'Invalid facility ID does not select media.');

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
$detailCss = (string) file_get_contents($root . '/assets/css/pages/review-article-detail.css');
$js = (string) file_get_contents($root . '/assets/js/review-articles.js');
$check(str_contains($directoryController, 'medical_review_articles_directory_page') && !str_contains($directoryController, 'review_view_directory('), 'The review menu now queries editorial articles only.');
$check(str_contains($detailController, "'@type' => 'Article'") && !str_contains($detailController, 'aggregateRating'), 'Article schema does not manufacture a review or aggregate rating.');
$check(str_contains($directoryController, 'noindex,follow') && str_contains($directoryController, 'site_language_metadata_tags') && str_contains($detailController, "['alternates']"), 'SEO handles filtered pages and actual language counterparts.');
$check(str_contains($css, '@media(max-width:620px)') && str_contains($css, '@media(max-width:900px)') && str_contains($css, 'minmax(0,1fr)'), 'Phone/tablet layout is adaptive and shrinkable.');
$check(str_contains($css, 'min-height:44px') && str_contains($css, 'font-size:16px') && str_contains($css, 'prefers-reduced-motion'), 'Mobile targets, readable body and reduced-motion handling exist.');
$check(!str_contains($css, 'fonts.googleapis.com') && str_contains($css, 'var(--med-font-sans'), 'No new font dependency is introduced.');
$check(str_contains($css, 'scroll-margin-top:') && str_contains($css, ':focus-visible'), 'Anchor offsets and visible keyboard focus are retained.');
$check(str_contains($detailController, '/assets/css/pages/review-article-detail.css') && !str_contains($directoryController, '/assets/css/pages/review-article-detail.css'), 'Detail polish is loaded only for the review article, leaving directory styles unchanged.');
$check(str_contains($detailController, "['id' => 'review-customer-images'") && str_contains($detailController, 'if ($reviewArticleCustomerImages !== [])'), 'Detail controller appends customer photos to the contents only after stored photos have been loaded.');
$check(str_contains($detailCss, '.ra-customer-gallery .ra-customer-card img') && str_contains($detailCss, 'object-fit:contain') && !str_contains($detailCss, 'object-fit:cover'), 'Detail photo polish contains rather than crops customer comparison images.');
$check(str_contains($detailCss, 'grid-template-columns:repeat(2,minmax(0,1fr))') && str_contains($detailCss, '.ra-customer-grid--single{grid-template-columns:minmax(0,1fr)}') && str_contains($detailCss, '@media(max-width:620px)'), 'Customer grid has shrinkable two-column and single-image layouts with a mobile breakpoint.');
$check(str_contains($detailCss, '@media(max-width:900px){.ra-article .ra-article-side{grid-template-columns:minmax(0,1fr)}') && str_contains($detailCss, 'overflow-y:auto'), 'Tablet facility sidebar spans the available width and long desktop contents can scroll.');
$check(str_contains($detailCss, '[data-photo-state="loading"]') && str_contains($detailCss, '[data-photo-state="error"]') && str_contains($detailCss, '[data-photo-state="ready"]') && str_contains($detailCss, 'prefers-reduced-motion'), 'Photo loading/error presentation and reduced-motion behavior are styled explicitly.');
$check(str_contains($js, "event.key !== 'Escape'") && str_contains($js, 'summary.focus()') && str_contains($js, 'navigator.share') && str_contains($js, 'aria-current'), 'Enhancements have keyboard dismissal, sharing and active TOC state.');
$check(!str_contains($js, 'fetch(') && !str_contains($js, 'innerHTML'), 'Reading-page JavaScript does not require network or insert untrusted HTML.');
echo 'review_articles_view_test: ' . $assertions . " assertions passed.\n";
