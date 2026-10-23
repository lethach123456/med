<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_review_article_ai.php';

/** No configured DB, real network or filesystem writes: fixtures live only in memory. */
final class ReviewArticleAiTestPDO extends PDO
{
    public bool $runtime = false;
    public array $statements = [];
    public function __construct() { parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
    private function record(string $sql): void
    {
        if (!$this->runtime) return;
        $this->statements[] = $sql;
        if (preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|REPLACE)\b/i', $sql)) throw new RuntimeException('Runtime DDL is forbidden.');
        if (preg_match('/^\s*(UPDATE|INSERT|DELETE)\b/i', $sql) && !preg_match('/^\s*(UPDATE medical_review_articles|INSERT INTO medical_review_articles)\b/i', $sql)) throw new RuntimeException('Only article metadata may be changed.');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { $this->record($query); return parent::prepare($query, $options); }
    public function exec(string $statement): int|false { $this->record($statement); return parent::exec($statement); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->record($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

function review_article_ai_fixture(): ReviewArticleAiTestPDO
{
    $pdo = new ReviewArticleAiTestPDO();
    $columns = [];
    foreach (medical_review_articles_facility_columns() as $field) $columns[] = $field . ($field === 'id' ? ' INTEGER PRIMARY KEY' : ' TEXT');
    $pdo->exec('CREATE TABLE medical_facilities (' . implode(',', $columns) . ',rating REAL,reviews_count INTEGER,full_json TEXT,patient_images_json TEXT,patient_images_notes TEXT,ai_writer_claim_json TEXT)');
    $pdo->exec('CREATE TABLE medical_reviews (id INTEGER PRIMARY KEY AUTOINCREMENT,facility_id INTEGER,facility_slug TEXT,status TEXT,rating REAL,excerpt TEXT,service_text TEXT,review_date_text TEXT,source_text TEXT,author_text TEXT)');
    medreview_with_schema_migration(static fn() => medical_review_articles_migrate($pdo));
    $insert = $pdo->prepare('INSERT INTO medical_facilities (id,slug,name,category,city,status,language_code,website_url,google_maps_url,social_links_json,aggregate_ratings_json,facility_insights_json,rating,reviews_count,full_json,patient_images_json,patient_images_notes,ai_writer_claim_json) VALUES (:id,:slug,:name,\'Nha khoa\',\'Đà Nẵng\',:status,:locale,\'https://clinic.example.org/about\',\'https://maps.google.com/?cid=123\',:social,:aggregates,:insights,5,9000,\'PRIVATE_FULL\',\'PRIVATE_PATIENT_IMAGES\',\'PRIVATE_PATIENT_NOTES\',\'PRIVATE_FACILITY_TOKEN\')');
    foreach (range(1, 8) as $id) {
        $aggregates = in_array($id, [2, 7], true) ? [] : [['source' => 'Google Maps', 'score' => 4.4, 'count' => $id === 3 ? 40 : 80, 'url' => 'https://maps.google.com/?cid=123']];
        $insights = $id === 4 ? '{}' : json_encode(['summary' => 'Cơ sở có thông tin về phòng chờ và dịch vụ trên trang giới thiệu công khai.']);
        $insert->execute([':id' => $id, ':slug' => 'clinic-' . $id, ':name' => 'Cơ sở ' . $id, ':status' => $id === 5 ? 'draft' : 'published',
            ':locale' => $id === 6 ? 'en' : 'vi', ':social' => json_encode([['platform' => 'Facebook', 'url' => 'https://facebook.com/clinic', 'patient_images_notes' => 'PRIVATE_NESTED']]),
            ':aggregates' => json_encode($aggregates), ':insights' => $insights]);
    }
    $review = $pdo->prepare("INSERT INTO medical_reviews (facility_id,facility_slug,status,rating,excerpt,service_text,review_date_text,source_text,author_text) VALUES (:id,:slug,'published',4.2,:excerpt,'Khám tư vấn','2026-10-01','Google Maps','PRIVATE_REVIEW_AUTHOR')");
    foreach ([1 => 3, 2 => 45] as $id => $count) for ($i = 0; $i < $count; ++$i) {
        $review->execute([':id' => $id, ':slug' => 'clinic-' . $id, ':excerpt' => 'Phòng chờ sạch và nhân viên hướng dẫn lịch hẹn. Liên hệ 0901234567 user@example.org.']);
    }
    $pdo->runtime = true;
    return $pdo;
}

$checks = 0;
$assert = static function (bool $value, string $message) use (&$checks): void { ++$checks; if (!$value) throw new RuntimeException($message); };
$throws = static function (callable $call, int $status, ?string $reason = null) use ($assert): void {
    try { $call(); }
    catch (MedicalReviewArticleAiException $error) {
        $assert($error->status === $status, 'Unexpected API status: ' . $error->getMessage());
        if ($reason !== null) $assert($error->reason === $reason, 'Unexpected reason ' . $error->reason);
        return;
    }
    throw new RuntimeException('Expected API exception ' . $status . ' ' . $reason);
};

foreach (['', '{', 'null', '[]', '123', '{"x":"' . chr(255) . '"}'] as $raw) $throws(static fn() => medical_review_article_ai_parse_body($raw), 400);
$assert(medical_review_article_ai_parse_body('{}') === [], 'An empty JSON object is still an object root');
$throws(static fn() => medical_review_article_ai_parse_body('{"x":[' . str_repeat('0,', 21000) . '0]}'), 400, 'json_too_complex');
$throws(static fn() => medical_review_article_ai_parse_body('{"x":123}', 2), 413);
foreach ([null, 0, -1, 1.5, true, '01', '1 OR 1=1', '4294967296', []] as $id) $throws(static fn() => medical_review_article_ai_id($id), 422);
$assert(medical_review_article_ai_id('4294967295') === 4294967295, 'Unsigned entity range');
$pdo = review_article_ai_fixture();
$queue = medical_review_article_ai_queue($pdo, ['limit' => 1], null, 1000);
$assert($queue['total'] === 3 && $queue['pages'] === 3 && count($queue['items']) === 1, 'Only eligible external/stored facilities are queued');
$assert($queue['items'][0]['entity_id'] === 1, 'Stable source ordering');
$assert($queue['items'][0]['source']['sample_count'] === 3 && $queue['items'][0]['source']['review_metrics']['reviews_count'] === 80, 'Sample count is not aggregate count');
$assert($queue['save_status'] === 'published' && $queue['claim_required'] === true && $queue['updates_supported'], 'Publication and ownership defaults');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_review_articles')->fetchColumn() === 0, 'GET never creates draft placeholders');
$queueJson = medical_review_article_ai_json($queue);
foreach (['PRIVATE_FULL', 'PRIVATE_PATIENT_IMAGES', 'PRIVATE_PATIENT_NOTES', 'PRIVATE_FACILITY_TOKEN', 'PRIVATE_REVIEW_AUTHOR', 'PRIVATE_NESTED', '0901234567', 'user@example.org'] as $private) $assert(!str_contains($queueJson, $private), 'AI source must not expose ' . $private);
foreach ($pdo->statements as $sql) $assert(!preg_match('/^\s*(UPDATE|INSERT|DELETE|CREATE|ALTER)/i', $sql), 'GET is read-only');
$second = medical_review_article_ai_queue($pdo, ['page' => 2, 'limit' => 1], null, 1000);
$assert($second['items'][0]['entity_id'] === 2 && $second['items'][0]['source']['sample_count'] === 40, 'Actual stored review count also qualifies, sample bounded');
$english = medical_review_article_ai_queue($pdo, ['language' => 'en'], null, 1000);
$assert($english['total'] === 1 && $english['items'][0]['entity_id'] === 6, 'Language queues separated');
$throws(static fn() => medical_review_article_ai_queue($pdo, ['limit' => 26]), 422);
$throws(static fn() => medical_review_article_ai_target(['entity_type' => 'doctor', 'entity_id' => 1]), 422);
$throws(static fn() => medical_review_article_ai_target(['entity_id' => 1, 'id' => 2]), 422);
$source = $queue['items'][0]['source']; $revision = $queue['items'][0]['source_revision'];
$prompt = medical_review_article_ai_prompt($source, $revision, 'Custom {{source_json}} {{output_template}}');
$assert(!str_contains($prompt, '{{output_template}}') && str_contains($prompt, 'RÀNG BUỘC BẮT BUỘC'), 'Variants receive source/output and mandatory editorial constraints');
$assert(str_contains(medical_review_article_ai_default_prompt(), 'không giả vờ đã đến khám'), 'No fabricated personal experience');
$writingRules = medical_review_article_ai_customer_writing_rules();
$assert(str_starts_with($writingRules, 'MEDREVIEW_REVIEW_ARTICLE_CUSTOMER_WRITING_RULES v' . MEDICAL_REVIEW_ARTICLE_AI_CUSTOMER_WRITING_VERSION), 'Customer writing policy has a permanent version marker');
$assert(MEDICAL_REVIEW_ARTICLE_AI_CUSTOMER_WRITING_VERSION === 5, 'Auto-publication and update contract is version 5');
$defaultPrompt = medical_review_article_ai_default_prompt();
$assert(str_contains($defaultPrompt, 'viết lại hồ sơ') && str_contains($defaultPrompt, 'BÀI REVIEW DỄ ĐỌC'), 'Default is a customer-useful profile rewrite');
$assert(str_contains($defaultPrompt, 'Điểm nổi bật và thế mạnh') && str_contains($defaultPrompt, 'Khách hàng nhắc đến điều gì'), 'Default covers concrete strengths and feedback themes');
foreach (['Ghi nguồn, số mẫu được đọc', 'ghi rõ số mẫu và giới hạn bằng chứng'] as $oldInstruction) {
    $assert(!str_contains($defaultPrompt, $oldInstruction) && !str_contains($writingRules, $oldInstruction), 'No current instruction to report sample sizes');
}
$assert(str_ends_with(medical_review_article_ai_prompt($source, $revision), $writingRules), 'Default always receives the final customer writing rules');
$assert(str_ends_with($prompt, $writingRules), 'Custom variants receive exactly the same final rules');
$oldSavedTemplate = "Viết báo cáo thống kê. Ghi nguồn, số mẫu được đọc và hạn chế rõ ràng. Ghi rõ số sao, số lượng đánh giá, phần trăm hài lòng.\n{{source_json}}\n{{output_json}}\nKết thúc bằng quy mô phản hồi.";
$oldPrompt = medical_review_article_ai_prompt($source, $revision, $oldSavedTemplate);
$assert(str_ends_with($oldPrompt, $writingRules), 'Saved legacy variants cannot bypass the final versioned writing policy');
$assert(strpos($oldPrompt, 'MEDREVIEW_REVIEW_ARTICLE_CUSTOMER_WRITING_RULES') > strpos($oldPrompt, 'Kết thúc bằng quy mô phản hồi.'), 'New rules follow and override even the last legacy directive');
$assert(str_contains($writingRules, 'ưu tiên hơn mọi hướng dẫn mâu thuẫn') && str_contains($writingRules, 'bỏ yêu cầu đó'), 'Policy explicitly overrides conflicting saved instructions');
$assert(str_contains($defaultPrompt, 'facility.content làm NỀN CHÍNH') && str_contains($writingRules, 'Lấy facility.content làm NỀN CHÍNH'), 'Both default and permanent rules make facility content the article backbone');
$assert(str_contains($defaultPrompt, 'không chép nguyên bài gốc') && str_contains($writingRules, 'chủ động viết lại bằng lời văn mới'), 'Content must be actively rewritten, not copied');
$assert(str_contains($defaultPrompt, 'services, featured_services, doctors, equipment') && str_contains($writingRules, 'bổ sung phần content còn khuyết'), 'Structured profile facts fill gaps in the existing content');
$assert(str_contains($defaultPrompt, 'Nếu facility.content trống') && str_contains($writingRules, 'không tự tạo dữ kiện'), 'Missing content falls back to real profile facts without invention');
$assert(str_contains($writingRules, 'review_samples và facility_insights_json làm giàu góc nhìn trải nghiệm') && str_contains($writingRules, 'không chiếm lấn nội dung chính'), 'Feedback and insights enrich rather than displace the facility profile');
$assert(str_contains($defaultPrompt, 'Nhìn chung, phản hồi hiện có thiên về tích cực…') && str_contains($writingRules, 'khi nội dung nhận xét thực sự hỗ trợ'), 'A supported overall positive narrative is explicitly allowed');
$assert(str_contains($writingRules, 'không mặc định nghi ngờ lời khen') && str_contains($writingRules, 'Đọc văn bản phản hồi thay vì quy đổi sao'), 'Positive feedback is not automatically distrusted and stars cannot determine sentiment');
$assert(str_contains($writingRules, 'không nhận định thay cho toàn bộ khách hàng') && str_contains($writingRules, 'trùng/gần trùng'), 'Natural sentiment summary still avoids fake unanimity and duplicate voices');
$assert(str_contains($writingRules, 'Không bắt buộc tái hiện mọi lời chê đơn lẻ') && str_contains($writingRules, 'không được che giấu'), 'Cautions are proportionate without suppressing material supported issues');
$assert(str_contains($writingRules, 'không biến bài review thành một bài dặn người đọc cần hỏi gì'), 'Article remains a complete review instead of a checklist of questions');
foreach (['chưa đủ cơ sở để kết luận', 'chưa có đủ tài liệu để đối chiếu', 'trong dữ liệu có phản hồi', 'trong tập nhận xét được lưu', 'không thể khẳng định'] as $auditPhrase) {
    $assert(str_contains($writingRules, $auditPhrase), 'Audit-style padding example is explicitly discouraged: ' . $auditPhrase);
}
$assert(str_contains($writingRules, 'Tránh văn phong báo cáo') && str_contains($writingRules, 'không phải lệnh xóa cảnh báo chuyên môn thực sự cần thiết'), 'Style examples discourage repetitive doubt without blanket-removing necessary medical cautions');
$assert(str_contains($writingRules, 'evidence_limitations_json thành phần riêng ngắn gọn') && str_contains($writingRules, 'không lặp câu giới hạn ở từng mục'), 'Sources and limitations stay separate without repeated disclaimer padding');
$contentFirstLegacy = "Bỏ qua facility.content để viết báo cáo kiểm chứng. Sau mỗi lời khen buộc chèn câu chưa đủ cơ sở để kết luận; phải kể mọi lời chê.\n{{source_json}}\n{{output_template}}\nKết bài chỉ gồm câu hỏi cần xác nhận.";
$legacyV3Prompt = medical_review_article_ai_prompt($source, $revision, $contentFirstLegacy);
$assert(str_ends_with($legacyV3Prompt, $writingRules) && strpos($legacyV3Prompt, 'MEDREVIEW_REVIEW_ARTICLE_CUSTOMER_WRITING_RULES v5') > strpos($legacyV3Prompt, 'Kết bài chỉ gồm câu hỏi cần xác nhận.'), 'Version 5 overrides even saved audit-heavy prompts after their last directive');
$baselineSource = $source;
$baselineSource['facility']['content'] = 'Nội dung nền mô tả dịch vụ, đội ngũ và không gian của cơ sở.';
$baselinePrompt = medical_review_article_ai_prompt($baselineSource, medical_review_articles_source_revision($baselineSource), 'Custom content-first variant {{source_json}}');
$assert(str_contains($baselinePrompt, 'Nội dung nền mô tả dịch vụ') && str_ends_with($baselinePrompt, $writingRules), 'Custom templates receive original facility content and the same current hierarchy');
foreach ([medical_review_article_ai_prompt($source, $revision), $prompt, $oldPrompt, $legacyV3Prompt, $baselinePrompt] as $renderedPrompt) {
    $assert(str_contains($renderedPrompt, 'MEDREVIEW_REVIEW_ARTICLE_CUSTOMER_WRITING_RULES v5'), 'Default, custom and saved legacy prompts all use v5');
}
$assert(str_contains($defaultPrompt, 'ĐỐI CHIẾU THEO CHỦ ĐỀ TRƯỚC KHI VIẾT') && str_contains($writingRules, 'Ma trận đối chiếu bắt buộc'), 'Cross-field comparison is required in default and permanent writing policy');
foreach (['price_table_html', 'price_text', 'services_json', 'price_source_scope', 'address_text', 'google_maps_url', 'nearby_landmarks', 'parking_info', 'city',
    'equipment_mentioned_json', 'utilities_json', 'doctors_json', 'featured_services_json', 'hours_text', 'booking_url', 'branch_count', 'insurance_accepted_json',
    'payment_methods_json', 'warranty_policy', 'languages_supported_json'] as $factField) {
    $assert(str_contains($defaultPrompt, $factField) && str_contains($writingRules, $factField), 'Default and saved variants compare field ' . $factField);
}
$assert(str_contains($defaultPrompt, 'ưu tiên cao nhất THEO TỪNG CHỦ ĐỀ') && str_contains($writingRules, 'nội dung quét cụ thể, mới và căn cứ phù hợp'), 'Scanned insights have topic-specific priority when concrete, fresh and supported');
$assert(str_contains($defaultPrompt, 'summary rà soát ảnh không được ghi đè bảng giá, địa chỉ') && str_contains($writingRules, 'không cho một summary ảnh ghi đè'), 'Photo summaries cannot blindly override unrelated structured facts');
$assert(str_contains($writingRules, 'Insights thiếu chủ đề nào thì dùng trường hồ sơ của chủ đề đó') && str_contains($writingRules, 'không tự suy điền từ ảnh'), 'Missing insight topics fall back to actual profile fields, not photo guesses');
$assert(str_contains($writingRules, 'hiệu chỉnh content cũ') && str_contains($writingRules, 'nguồn cụ thể và độ mới'), 'Specific current evidence may correct older content internally');
$assert(str_contains($writingRules, 'không biến giá toàn hệ thống/tham khảo thành giá riêng chi nhánh') && str_contains($writingRules, 'không suy tổng phí từ một hạng mục'), 'Price scope and per-service units do not become invented branch totals');
$assert(str_contains($writingRules, 'không được tự tính khoảng cách/thời gian đi từ tọa độ') && str_contains($writingRules, 'bịa mốc đường/chỗ đỗ xe'), 'Location prose cannot invent distances, travel times or parking');
$assert(str_contains($writingRules, 'không được đoán model, chứng nhận vô trùng') && str_contains($writingRules, 'không đoán danh tính/bằng cấp'), 'Photos cannot establish equipment model, certification or staff identity');
$assert(str_contains($writingRules, 'Không đánh đồng thanh toán với bảo hiểm chi trả') && str_contains($writingRules, 'bảo hành với cam kết mọi kết quả'), 'Operational/payment facts do not become insurance or clinical guarantees');
$assert(!str_contains($defaultPrompt, 'features_json') && !str_contains($writingRules, 'features_json'), 'Platform feature messages are not requested as facility evidence fields');
$assert(str_contains($defaultPrompt, 'của nền tảng MedReview không phải đặc điểm cơ sở') && str_contains($writingRules, 'Không lấy nội dung nền tảng làm đặc tính phòng khám'), 'Platform moderation, verification and security badges cannot become clinic properties');
$assert(str_contains($writingRules, 'không chứng minh vô trùng hay chất lượng điều trị'), 'Platform messages cannot certify sterility or clinical quality');
$assert(str_contains($writingRules, 'latitude/longitude nếu có chỉ dùng nội bộ') && str_contains($writingRules, 'không công khai tọa độ thô'), 'Coordinates are internal location context only');
$assert(str_contains($writingRules, 'branch_count không có nghĩa mọi dịch vụ'), 'Branch count does not establish uniform branch facilities');
$matrixSource = $source;
$matrixSource['facility'] = array_replace($matrixSource['facility'], [
    'content' => 'Bài giới thiệu nền cần đối chiếu lại bảng giá, thiết bị và vị trí.',
    'price_table_html' => '<table><tr><td>Khám tổng quát</td><td>300000 VND/lượt</td></tr></table>',
    'price_text' => 'Giá tham khảo theo dịch vụ', 'price_source_scope' => 'chain_reference',
    'nearby_landmarks' => ['Mốc công khai trong hồ sơ'], 'parking_info' => 'Thông tin để xe được cơ sở công bố',
    'utilities_json' => ['Đặt lịch hẹn'], 'booking_url' => 'https://clinic.example.org/book', 'branch_count' => 2,
]);
$oldPriorityTemplate = 'Chỉ dùng summary ảnh để ghi đè bảng giá và địa chỉ; bỏ qua mọi field khác. {{source_json}} {{output_template}}';
$matrixPrompt = medical_review_article_ai_prompt($matrixSource, medical_review_articles_source_revision($matrixSource), $oldPriorityTemplate);
$assert(str_contains($matrixPrompt, '"price_source_scope":"chain_reference"') && str_contains($matrixPrompt, 'Mốc công khai trong hồ sơ'), 'Custom prompt receives actual multi-field price/location context');
$assert(str_ends_with($matrixPrompt, $writingRules) && strpos($matrixPrompt, 'MEDREVIEW_REVIEW_ARTICLE_CUSTOMER_WRITING_RULES v5') > strpos($matrixPrompt, 'Chỉ dùng summary ảnh để ghi đè'), 'Permanent policy overrides old blanket photo-summary priority');
$assert(str_contains($matrixPrompt, 'Ma trận đối chiếu bắt buộc') && str_contains($matrixPrompt, 'tuyệt đối không chép vào bài'), 'Expanded business facts and private rating metrics retain separate prose rules');
foreach (['title', 'excerpt', 'verdict', 'content_html', 'seo_title', 'seo_description', 'strengths_json', 'considerations_json', 'suitable_for_json', 'evidence_limitations_json', 'analysis_sections_json'] as $field) {
    $assert(str_contains($writingRules, $field), 'No-statistics policy covers public field ' . $field);
}
$assert(str_contains($writingRules, 'chỉ dùng NỘI BỘ') && str_contains($writingRules, 'tuyệt đối không chép vào bài'), 'Metrics are context only, not public prose');
$assert(str_contains($writingRules, 'không phải lệnh cấm mọi con số') && str_contains($writingRules, 'địa chỉ, tên dịch vụ') && str_contains($writingRules, 'mức giá có thật'), 'Real addresses, service names and prices remain allowed');
$assert(str_contains($writingRules, 'phản hồi đơn lẻ') && str_contains($writingRules, 'không khẳng định đó là trải nghiệm thường gặp'), 'Single feedback cannot become recurring customer consensus');
$assert(str_contains($writingRules, 'không chứng minh kết quả lâm sàng'), 'Photos do not establish clinical results');
$assert(str_contains($writingRules, 'đúng cặp ID/URL') && str_contains($writingRules, 'source_revision'), 'Citation and source revision protocols remain mandatory');
$assert(str_contains($oldPrompt, '"sample_count":3') && str_contains($oldPrompt, '"reviews_count":80'), 'Internal evidence metrics remain intact in the AI source DTO');
$assert(medical_review_article_ai_output_template($source, $revision)['sources_json'] === medical_review_article_ai_source_catalog($source), 'Writing style leaves the source catalog IDs and URLs untouched');
$assert(medical_review_articles_source_revision($source) === $revision, 'Prompt style does not alter the source revision');
$assert(str_contains($queue['items'][0]['prompt'], 'MEDREVIEW_REVIEW_ARTICLE_CUSTOMER_WRITING_RULES'), 'Queue prompts use the same permanent customer writing policy');
$evidenceCatalog = medical_review_article_evidence_catalog($source);
$assert($queue['items'][0]['evidence_catalog'] === $evidenceCatalog, 'Queue serves the same deterministic evidence catalog used by validation');
$assert(str_contains($defaultPrompt, 'THỰC HIỆN HAI BƯỚC') && str_contains($defaultPrompt, 'bản ghi căn cứ, không phải chuỗi suy nghĩ'), 'Default directs a concise evidence summary then article in the same task');
$assert(str_contains($writingRules, 'editorial_evidence_json') && str_contains($writingRules, 'lưu NỘI BỘ'), 'Two-step output is a private evidence summary, not public prose');
$assert(str_contains($oldPrompt, '"path":"review_samples.0.excerpt"') && !str_contains($oldPrompt, '{{evidence_catalog}}'), 'Legacy templates receive the actual evidence catalog automatically');
$explicitEvidencePrompt = medical_review_article_ai_prompt($source, $revision, '{{evidence_catalog}} {{source_json}} {{output_template}}');
$assert(!str_contains($explicitEvidencePrompt, '{{evidence_catalog}}') && str_contains($explicitEvidencePrompt, '"id":"p-name"'), 'Custom evidence catalog placeholder is supported');
$assert(!in_array('editorial_evidence_json', MEDICAL_REVIEW_ARTICLE_CONTENT_FIELDS, true)
    && !in_array('editorial_evidence_json', MEDICAL_REVIEW_ARTICLE_AI_CONTENT_FIELDS, true), 'Private evidence must never become a public field or dynamic SQL column');
$outputKeys = array_keys(medical_review_article_ai_output_template($source, $revision));
$assert(array_search('editorial_evidence_json', $outputKeys, true) < array_search('content_html', $outputKeys, true), 'Output template puts the evidence summary before prose, not as a post-hoc justification');
$leaseBody = ['entity_type' => 'facility', 'entity_id' => 1, 'language_code' => 'vi', 'source_revision' => $revision,
    'request_id' => 'article-1', 'client' => ['instance_id' => 'device-a', 'worker_id' => 'worker-a', 'provider' => 'ChatGPT']];
$lease = medical_review_article_ai_lease($pdo, $leaseBody, 1000);
$assert(strlen($lease['claim_token']) === 64 && $lease['expires_at'] === 1900, 'Claim returns bounded lease token');
$stored = medical_review_article_ai_article_row($pdo, 1, 'vi');
$assert(!str_contains((string) $stored['ai_writer_claim_json'], $lease['claim_token']), 'Only token hash is persisted');
$assert(str_contains((string) $stored['ai_writer_claim_json'], hash('sha256', $lease['claim_token'])), 'Hash binds owner');
$throws(static fn() => medical_review_article_ai_lease($pdo, $leaseBody, 1001), 409, 'already_claimed');
$renew = medical_review_article_ai_lease($pdo, $leaseBody + ['claim_token' => $lease['claim_token']], 1050);
$assert($renew['claim_token'] === $lease['claim_token'], 'Owner may retry claim only with retained token');
$status = medical_review_article_ai_lease($pdo, ['entity_id' => 1, 'action' => 'status'], 1051);
$statusJson = medical_review_article_ai_json($status);
$assert($status['claimed'] === true && !str_contains($statusJson, 'token_sha256') && !str_contains($statusJson, $lease['claim_token']), 'Status never discloses owner secret');
$assert(medical_review_article_ai_queue($pdo, [], null, 1051)['total'] === 2, 'Active claim excluded');
$heartbeat = medical_review_article_ai_lease($pdo, ['entity_id' => 1, 'action' => 'heartbeat', 'claim_token' => $lease['claim_token']], 1100);
$assert($heartbeat['expires_at'] === 2000, 'Heartbeat refresh');
$articleBody = medical_review_article_ai_output_template($source, $revision);
$articleBody['title'] = 'Review Cơ sở 1: dịch vụ và những điểm cần cân nhắc';
$articleBody['excerpt'] = 'Tìm hiểu dịch vụ, điểm đáng chú ý và những điều nên hỏi trước khi đặt lịch tại cơ sở.';
$articleBody['verdict'] = 'Có thể tìm hiểu thêm nếu bạn cần một địa điểm tư vấn; hãy hỏi rõ dịch vụ và chi phí trước khi quyết định.';
$articleBody['content_html'] = '<h2>Tìm hiểu cơ sở trước khi đặt lịch</h2><p>' . str_repeat('Trước khi đặt lịch tại cơ sở, bạn nên trao đổi về nhu cầu thăm khám, hỏi rõ dịch vụ phù hợp, lịch hẹn và cách tính chi phí. Việc xác nhận trực tiếp sẽ giúp chuẩn bị thuận tiện hơn. ', 4) . '</p><script>alert(1)</script>';
$articleBody['strengths_json'] = ['Có thông tin địa điểm công khai'];
$articleBody['considerations_json'] = ['Chưa có thông tin chi phí đầy đủ'];
$articleBody['suitable_for_json'] = ['Người cần tìm hiểu địa điểm và hỏi lịch khám'];
$articleBody['evidence_limitations_json'] = ['Nhận xét công khai là trải nghiệm cá nhân; nên xác nhận trực tiếp thông tin dịch vụ trước khi đặt lịch.'];
$articleBody['claim_token'] = $lease['claim_token']; $articleBody['request_id'] = 'article-1';
$articleBody['researched_at'] = '2026-10-22';
$articleBody['editorial_evidence_json'] = ['schema_version' => 1, 'claims' => [
    ['id' => 'c1', 'topic' => 'other', 'statement' => 'Tên cơ sở có trong hồ sơ được cung cấp.', 'evidence_refs' => ['p-name'],
        'feedback_scope' => 'none', 'opposing_refs' => [], 'disposition' => 'use'],
    ['id' => 'c2', 'topic' => 'reception', 'statement' => 'Có phản hồi nhắc đến hướng dẫn lịch hẹn.', 'evidence_refs' => ['r-1'],
        'feedback_scope' => 'single', 'opposing_refs' => [], 'disposition' => 'qualify'],
]];
$wrong = $articleBody; $wrong['claim_token'] = str_repeat('a', 64);
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 409, 'lease_lost');
$wrong = $articleBody; $wrong['sources_json'][0]['url'] = 'https://unprovided.example.org';
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 422, 'unknown_source');
$wrong = $articleBody; $wrong['patient_images_notes'] = 'do not accept';
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 422);
$wrong = $articleBody; $wrong['editorial_evidence_json'] = null;
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 422, 'invalid_evidence');
$wrong = $articleBody; $wrong['editorial_evidence_json']['claims'][0]['evidence_refs'] = ['p-rating'];
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 422, 'invalid_evidence');
$wrong = $articleBody; $wrong['editorial_evidence_json']['claims'][1]['evidence_refs'] = ['r-1', 'r-2'];
$wrong['editorial_evidence_json']['claims'][1]['feedback_scope'] = 'recurring';
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1150), 422, 'invalid_evidence');
$assert(trim((string) medical_review_article_ai_article_row($pdo, 1, 'vi')['content_html']) === '', 'Invalid evidence never partially saves article content');
$receipt = medical_review_article_ai_save($pdo, $articleBody, 1150);
$assert($receipt['saved'] && $receipt['storage_verified'] && !$receipt['idempotent'] && $receipt['status'] === 'published', 'Real save verified by DB readback and published automatically');
$saved = medical_review_article_ai_article_row($pdo, 1, 'vi');
$assert($saved['ai_writer_claim_json'] === null && !str_contains((string) $saved['content_html'], '<script'), 'Claim released and unsafe HTML removed');
$assert((int) $saved['source_review_count'] === 80 && $saved['source_rating_basis'] === 'external_aggregates', 'Stored source metrics preserve provenance');
$assert(!str_contains((string) $saved['source_snapshot_json'], 'PRIVATE_'), 'Snapshot contains allowed source only');
$savedMetadata = medical_review_article_ai_decode($saved['ai_content_json']);
$assert(($savedMetadata['editorial_evidence_json'] ?? null) === medical_review_article_ai_evidence($articleBody['editorial_evidence_json']), 'Evidence is persisted and read back only inside private AI metadata');
$assert(!str_contains(medical_review_article_ai_json($receipt), 'editorial_evidence_json')
    && !str_contains(medical_review_article_ai_json($receipt), 'evidence_refs'), 'Save receipt never echoes private evidence');
$assert(!str_contains((string) $saved['source_snapshot_json'], 'editorial_evidence_json'), 'Source snapshot does not absorb AI-authored evidence');
$retry = medical_review_article_ai_save($pdo, $articleBody, 9999);
$assert($retry['idempotent'] && $retry['article_id'] === $receipt['article_id'], 'Safe exact replay after lease expiration');
$wrong = $articleBody; $wrong['editorial_evidence_json']['claims'][0]['statement'] .= ' Đã sửa.';
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1200), 409, 'idempotency_conflict');
$tamperedMetadata = $savedMetadata;
$tamperedMetadata['editorial_evidence_json']['claims'][0]['statement'] .= ' Chỉnh nội bộ.';
$pdo->prepare('UPDATE medical_review_articles SET ai_content_json=:metadata WHERE id=:id')->execute([':metadata' => medical_review_article_ai_json($tamperedMetadata), ':id' => $receipt['article_id']]);
$throws(static fn() => medical_review_article_ai_save($pdo, $articleBody, 1200), 409, 'article_changed');
$pdo->prepare('UPDATE medical_review_articles SET ai_content_json=:metadata WHERE id=:id')->execute([':metadata' => $saved['ai_content_json'], ':id' => $receipt['article_id']]);
$wrong = $articleBody; $wrong['title'] .= ' CHANGED';
$throws(static fn() => medical_review_article_ai_save($pdo, $wrong, 1200), 409, 'idempotency_conflict');
$pdo->prepare('UPDATE medical_review_articles SET verdict=:verdict WHERE id=:id')->execute([':verdict' => 'Edited after AI save', ':id' => $receipt['article_id']]);
$throws(static fn() => medical_review_article_ai_save($pdo, $articleBody, 1200), 409, 'article_changed');
$assert(!medical_review_article_ai_needs_content($saved), 'Published content is excluded from the automatic new-article queue');

// A suppressed SQL update cannot be mistaken for successful storage.
$two = $second['items'][0];
$claimTwo = medical_review_article_ai_lease($pdo, ['entity_id' => 2, 'source_revision' => $two['source_revision'], 'request_id' => 'article-2', 'client' => ['instance_id' => 'd', 'worker_id' => 'w']], 1000);
$bodyTwo = $articleBody; $bodyTwo['entity_id'] = 2; $bodyTwo['source_revision'] = $two['source_revision']; $bodyTwo['claim_token'] = $claimTwo['claim_token']; $bodyTwo['request_id'] = 'article-2';
$bodyTwo['title'] = 'Review Cơ sở 2'; $bodyTwo['status'] = 'published'; $bodyTwo['sources_json'] = medical_review_article_ai_source_catalog($two['source']);
unset($bodyTwo['editorial_evidence_json']);
$legacyContent = medical_review_article_ai_content($bodyTwo);
$assert(medical_review_article_ai_payload_hash(2, 'vi', $two['source_revision'], 'published', $legacyContent, null)
    === hash('sha256', medical_review_article_ai_json([2, 'vi', $two['source_revision'], 'published', $legacyContent])), 'Omitted evidence preserves the exact legacy payload hash formula');
$pdo->runtime = false;
$pdo->exec("CREATE TRIGGER suppress_review_update BEFORE UPDATE OF content_html ON medical_review_articles WHEN NEW.entity_id=2 BEGIN SELECT RAISE(IGNORE); END");
$pdo->runtime = true;
$failed = false;
try { medical_review_article_ai_save($pdo, $bodyTwo, 1100); } catch (RuntimeException $error) { $failed = str_contains($error->getMessage(), 'readback'); }
$assert($failed && !$pdo->inTransaction(), 'Suppressed update rolls back after failed readback');
$assert(trim((string) medical_review_article_ai_article_row($pdo, 2, 'vi')['content_html']) === '', 'No false content receipt');
$pdo->runtime = false; $pdo->exec('DROP TRIGGER suppress_review_update'); $pdo->runtime = true;
$published = medical_review_article_ai_save($pdo, $bodyTwo, 1100);
$assert($published['status'] === 'published' && medical_review_article_ai_article_row($pdo, 2, 'vi')['published_at'] !== null, 'Explicit publishing supported');
$assert(!array_key_exists('editorial_evidence_json', medical_review_article_ai_decode(medical_review_article_ai_article_row($pdo, 2, 'vi')['ai_content_json'])), 'Legacy client omission does not fabricate an evidence record');
$assert(medical_review_article_ai_save($pdo, $bodyTwo, 9999)['idempotent'], 'Legacy client can replay after its lease expires');

// Exact retries keep their original source context even if the live catalog has since changed.
$replayPdo = review_article_ai_fixture();
$replaySource = medical_review_articles_facility_source($replayPdo, 1);
$replayRevision = medical_review_articles_source_revision($replaySource);
$replayClaim = medical_review_article_ai_lease($replayPdo, ['entity_id' => 1, 'source_revision' => $replayRevision, 'request_id' => 'evidence-replay', 'client' => ['instance_id' => 'd', 'worker_id' => 'w']], 1000);
$replayBody = $articleBody;
$replayBody['source_revision'] = $replayRevision; $replayBody['claim_token'] = $replayClaim['claim_token']; $replayBody['request_id'] = 'evidence-replay';
medical_review_article_ai_save($replayPdo, $replayBody, 1100);
$replayPdo->runtime = false; $replayPdo->exec('DELETE FROM medical_reviews WHERE facility_id=1'); $replayPdo->runtime = true;
$assert(medical_review_article_ai_save($replayPdo, $replayBody, 9999)['idempotent'], 'Exact evidence replay is checked against stored metadata, not a changed live catalog');

// Changed evidence invalidates the lease without overwriting article or source data.
$eight = medical_review_articles_facility_source($pdo, 8);
$claimEight = medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'source_revision' => medical_review_articles_source_revision($eight), 'request_id' => 'article-8', 'client' => ['instance_id' => 'd', 'worker_id' => 'w']], 1000);
$pdo->runtime = false; $pdo->exec("UPDATE medical_facilities SET subtitle='Updated by editor' WHERE id=8"); $pdo->runtime = true;
$throws(static fn() => medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'action' => 'heartbeat', 'claim_token' => $claimEight['claim_token']], 1100), 409, 'source_revision_changed');
$bodyEight = $articleBody; $bodyEight['entity_id'] = 8; $bodyEight['source_revision'] = $claimEight['source_revision']; $bodyEight['claim_token'] = $claimEight['claim_token']; $bodyEight['request_id'] = 'article-8';
$throws(static fn() => medical_review_article_ai_save($pdo, $bodyEight, 1100), 409, 'source_revision_changed');
$release = medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'action' => 'release', 'claim_token' => $claimEight['claim_token']], 1100);
$assert($release['released'] === true, 'Owner can release stale evidence task');
$throws(static fn() => medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'action' => 'heartbeat', 'claim_token' => $claimEight['claim_token']], 1101), 409, 'lease_lost');
$eight = medical_review_articles_facility_source($pdo, 8);
$newClaim = medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'source_revision' => medical_review_articles_source_revision($eight), 'request_id' => 'article-8-new', 'client' => ['instance_id' => 'd', 'worker_id' => 'w']], 1200);
$throws(static fn() => medical_review_article_ai_lease($pdo, ['entity_id' => 8, 'action' => 'heartbeat', 'claim_token' => $newClaim['claim_token']], 2200), 409, 'lease_lost');

// Updating an existing article is an explicit new operation, not an endless default queue.
$updatePdo = review_article_ai_fixture();
$prepareUpdate = static function (PDO $connection, array $base, string $request, int $clock) : array {
    $currentSource = medical_review_articles_facility_source($connection, 1);
    $currentRevision = medical_review_articles_source_revision($currentSource);
    $owner = medical_review_article_ai_lease($connection, ['entity_id' => 1, 'source_revision' => $currentRevision,
        'request_id' => $request, 'client' => ['instance_id' => 'update-device', 'worker_id' => 'update-worker']], $clock);
    $payload = array_replace($base, ['entity_id' => 1, 'source_revision' => $currentRevision,
        'request_id' => $request, 'claim_token' => $owner['claim_token'], 'sources_json' => medical_review_article_ai_source_catalog($currentSource)]);
    return [$owner, $payload];
};
[$seedOwner, $seedBody] = $prepareUpdate($updatePdo, $articleBody, 'update-seed', 1000);
unset($seedBody['status']);
$seedReceipt = medical_review_article_ai_save($updatePdo, $seedBody, 1050);
$seedRow = medical_review_article_ai_article_row($updatePdo, 1, 'vi');
$assert($seedReceipt['status'] === 'published' && !$seedReceipt['updated_existing'], 'Omitted status publishes a complete new article automatically');
$assert(!in_array(1, array_column(medical_review_article_ai_queue($updatePdo, [], null, 1100)['items'], 'entity_id'), true), 'Default queue excludes articles already written');
$targeted = medical_review_article_ai_queue($updatePdo, ['id' => '1'], null, 1100);
$assert($targeted['total'] === 1 && $targeted['include_existing'] && $targeted['items'][0]['has_content']
    && $targeted['items'][0]['article_id'] === $seedReceipt['article_id'], 'Explicit ID lookup supplies existing article for an update');
$assert(medical_review_article_ai_queue($updatePdo, ['id' => '1', 'include_existing' => '0'], null, 1100)['total'] === 0, 'Caller may explicitly select only unfinished content');
$assert(medical_review_article_ai_queue($updatePdo, ['include_existing' => '1'], null, 1100)['total']
    === medical_review_article_ai_queue($updatePdo, [], null, 1100)['total'] + 1, 'Opt-in batch retrieval includes existing articles');
foreach (['true', 'yes', 2, true, []] as $badFlag) $throws(static fn() => medical_review_article_ai_filters(['include_existing' => $badFlag]), 422);
$writtenStatus = medical_review_article_ai_lease($updatePdo, ['entity_id' => 1, 'action' => 'status'], 1100);
$assert(!$writtenStatus['needs_content'] && $writtenStatus['updates_supported'] && $writtenStatus['save_status'] === 'published', 'Status distinguishes completed content from supported updates');
[$editOwner, $editBody] = $prepareUpdate($updatePdo, $seedBody, 'update-1', 1100);
$duringEdit = medical_review_article_ai_article_row($updatePdo, 1, 'vi');
$assert($duringEdit['status'] === 'published' && $duringEdit['content_html'] === $seedRow['content_html'], 'Claim does not hide or replace the article currently being read');
$assert(!str_contains(medical_review_article_ai_json(medical_review_article_ai_lease($updatePdo, ['entity_id' => 1, 'action' => 'status'], 1110)), 'article_revision'), 'Internal article revision is not exposed in public claim metadata');
$assert(medical_review_article_ai_lease($updatePdo, ['entity_id' => 1, 'action' => 'heartbeat', 'claim_token' => $editOwner['claim_token']], 1120)['claimed'], 'Existing published article supports heartbeats');
$throws(static fn() => medical_review_article_ai_lease($updatePdo, ['entity_id' => 1, 'source_revision' => $editBody['source_revision'],
    'request_id' => 'competing-update', 'client' => ['instance_id' => 'another-device', 'worker_id' => 'another-worker']], 1130), 409, 'already_claimed');
$editBody['title'] = 'Bản cập nhật: dịch vụ và trải nghiệm tại cơ sở';
$editBody['content_html'] = '<h2>Nội dung cập nhật</h2>' . $editBody['content_html'];
$editBody['status'] = 'draft'; // Previously saved prompts/clients still submit this value.
$wrong = $editBody; $wrong['claim_token'] = $seedOwner['claim_token'];
$throws(static fn() => medical_review_article_ai_save($updatePdo, $wrong, 1140), 409, 'lease_lost');
$editReceipt = medical_review_article_ai_save($updatePdo, $editBody, 1150);
$editedRow = medical_review_article_ai_article_row($updatePdo, 1, 'vi');
$assert($editReceipt['updated_existing'] && $editReceipt['status'] === 'published' && $editReceipt['article_id'] === $seedReceipt['article_id'], 'Update publishes immediately on the same article even when an old client sends draft');
$assert($editedRow['title'] === $editBody['title'] && str_contains($editedRow['content_html'], 'Nội dung cập nhật'), 'Updated content actually reaches article columns');
$assert($editedRow['slug'] === $seedRow['slug'] && $editedRow['created_at'] === $seedRow['created_at']
    && $editedRow['published_at'] === $seedRow['published_at'], 'Title changes preserve URL, original creation and first publication dates by default');
$assert((int) $updatePdo->query('SELECT COUNT(*) FROM medical_review_articles WHERE entity_id=1')->fetchColumn() === 1, 'Repeated writes do not create duplicate articles');
$assert(medical_review_article_ai_save($updatePdo, $editBody, 9999)['idempotent'], 'Exact updated payload safely replays after the lease expires');
$throws(static fn() => medical_review_article_ai_save($updatePdo, $seedBody, 1200), 409, 'lease_lost');
$wrong = $editBody; $wrong['excerpt'] .= ' Nội dung khác.';
$throws(static fn() => medical_review_article_ai_save($updatePdo, $wrong, 1200), 409, 'idempotency_conflict');
$throws(static fn() => $prepareUpdate($updatePdo, $editBody, 'update-1', 1200), 409, 'idempotency_conflict');
$updatePdo->exec("UPDATE medical_review_articles SET slug='edited-manually' WHERE entity_id=1");
$throws(static fn() => medical_review_article_ai_save($updatePdo, $editBody, 1200), 409, 'article_changed');
$updatePdo->prepare('UPDATE medical_review_articles SET slug=:slug WHERE entity_id=1')->execute([':slug' => $seedRow['slug']]);

// A new update can republish an existing draft; an explicit slug is still honored.
$updatePdo->exec("UPDATE medical_review_articles SET status='draft' WHERE entity_id=1");
[$draftOwner, $draftBody] = $prepareUpdate($updatePdo, $editBody, 'update-draft', 1200);
$draftBody['slug'] = 'review-updated-explicit-url';
$draftReceipt = medical_review_article_ai_save($updatePdo, $draftBody, 1250);
$assert($draftReceipt['status'] === 'published' && $draftReceipt['slug'] === 'review-updated-explicit-url', 'Existing draft is published and an explicit valid replacement slug is accepted');

// Detect concurrent edits, without confusing our own lease heartbeat with an edit.
[$concurrentOwner, $concurrentBody] = $prepareUpdate($updatePdo, $draftBody, 'update-concurrent', 1300);
$updatePdo->exec("UPDATE medical_review_articles SET verdict='Manual edit while worker was writing' WHERE entity_id=1");
$throws(static fn() => medical_review_article_ai_lease($updatePdo, ['entity_id' => 1, 'action' => 'heartbeat', 'claim_token' => $concurrentOwner['claim_token']], 1310), 409, 'article_changed');
$throws(static fn() => medical_review_article_ai_save($updatePdo, $concurrentBody, 1320), 409, 'article_changed');
$assert(medical_review_article_ai_article_row($updatePdo, 1, 'vi')['verdict'] === 'Manual edit while worker was writing', 'Concurrent manual edit is not overwritten');
$assert(medical_review_article_ai_lease($updatePdo, ['entity_id' => 1, 'action' => 'release', 'claim_token' => $concurrentOwner['claim_token']], 1330)['released'], 'Worker can release a stale article-version claim');

// Failed storage during update must leave the old public article intact.
[$proofOwner, $proofBody] = $prepareUpdate($updatePdo, $draftBody, 'update-proof', 1400);
$proofBody['content_html'] = '<h2>Bản cập nhật đã kiểm tra lưu</h2>' . $proofBody['content_html'];
$beforeProof = medical_review_article_ai_article_row($updatePdo, 1, 'vi');
$updatePdo->runtime = false;
$updatePdo->exec('CREATE TRIGGER suppress_existing_update BEFORE UPDATE OF content_html ON medical_review_articles WHEN NEW.entity_id=1 BEGIN SELECT RAISE(IGNORE); END');
$updatePdo->runtime = true;
$proofFailed = false;
try { medical_review_article_ai_save($updatePdo, $proofBody, 1450); } catch (RuntimeException $error) { $proofFailed = str_contains($error->getMessage(), 'readback'); }
$afterProof = medical_review_article_ai_article_row($updatePdo, 1, 'vi');
$assert($proofFailed && $afterProof['content_html'] === $beforeProof['content_html'] && $afterProof['ai_content_json'] === $beforeProof['ai_content_json']
    && $afterProof['status'] === 'published', 'Readback failure rolls back update without unpublishing the previous article');
$updatePdo->runtime = false; $updatePdo->exec('DROP TRIGGER suppress_existing_update'); $updatePdo->runtime = true;
$assert(medical_review_article_ai_save($updatePdo, $proofBody, 1460)['updated_existing'], 'Same valid update can retry successfully after a storage failure');

// Receipts created by the previous draft-default API remain read-only/idempotent.
$legacyPdo = review_article_ai_fixture();
[$legacyOwner, $legacyBody] = $prepareUpdate($legacyPdo, $articleBody, 'legacy-draft-receipt', 1000);
unset($legacyBody['status'], $legacyBody['editorial_evidence_json']);
medical_review_article_ai_save($legacyPdo, $legacyBody, 1050);
$legacyRow = medical_review_article_ai_article_row($legacyPdo, 1, 'vi');
$legacyMetadata = medical_review_article_ai_decode($legacyRow['ai_content_json']);
unset($legacyMetadata['auto_publish'], $legacyMetadata['operation']);
$legacyMetadata['payload_sha256'] = medical_review_article_ai_payload_hash(1, 'vi', $legacyBody['source_revision'], 'draft', medical_review_article_ai_content($legacyBody), null);
$legacyPdo->prepare("UPDATE medical_review_articles SET status='draft',published_at=NULL,ai_content_json=:metadata WHERE entity_id=1")
    ->execute([':metadata' => medical_review_article_ai_json($legacyMetadata)]);
$legacyReplay = medical_review_article_ai_save($legacyPdo, $legacyBody, 9999);
$assert($legacyReplay['idempotent'] && $legacyReplay['status'] === 'draft', 'Exact legacy retry preserves its original receipt rather than silently publishing');
[$legacyUpdateOwner, $legacyUpdateBody] = $prepareUpdate($legacyPdo, $legacyBody, 'legacy-now-publish', 1100);
$assert(medical_review_article_ai_save($legacyPdo, $legacyUpdateBody, 1150)['status'] === 'published', 'A new update publishes a legacy draft without changing its article identity');
$assert(medical_review_article_ai_output_template($source, $revision)['status'] === 'published'
    && str_contains($writingRules, 'thay mọi yêu cầu draft trong mẫu cũ'), 'New and saved prompts consistently request publication');

// Optional Vietnamese references describe an explicit translation; IDs remain stable when SEO paths change.
$linksPdo = review_article_ai_fixture();
$linksQueue = static fn() => medical_review_article_ai_queue($linksPdo, ['language' => 'en', 'id' => '6'], null, 1000);
$unlinkedEnglish = $linksQueue()['items'][0];
$assert(array_key_exists('vietnamese_facility_url', $unlinkedEnglish) && $unlinkedEnglish['vietnamese_facility_url'] === null
    && array_key_exists('vietnamese_review_url', $unlinkedEnglish) && $unlinkedEnglish['vietnamese_review_url'] === null
    && array_key_exists('vietnamese_facility_id', $unlinkedEnglish) && $unlinkedEnglish['vietnamese_facility_id'] === null
    && array_key_exists('vietnamese_review_id', $unlinkedEnglish) && $unlinkedEnglish['vietnamese_review_id'] === null,
    'English items expose explicit null IDs and links when no Vietnamese parent is recorded');
$linksFacilityChange = static function (string $sql) use ($linksPdo): void {
    $linksPdo->runtime = false;
    try { $linksPdo->exec($sql); } finally { $linksPdo->runtime = true; }
};
$linksFacilityChange('UPDATE medical_facilities SET translation_of_id=1 WHERE id=6');
$parentOnly = $linksQueue()['items'][0];
$assert($parentOnly['vietnamese_facility_url'] === '/co-so-y-te/clinic-1' && $parentOnly['vietnamese_review_url'] === null
    && $parentOnly['vietnamese_facility_id'] === 1 && $parentOnly['vietnamese_review_id'] === null,
    'An explicitly linked published Vietnamese facility is usable before its review exists');
$englishRevisionBeforeReview = $parentOnly['source_revision'];
$englishSourceBeforeReview = $parentOnly['source'];
$linksPdo->exec("INSERT INTO medical_review_articles (id,entity_type,entity_id,language_code,slug,status,title,content_html,source_snapshot_json,ai_content_json,ai_writer_claim_json)
    VALUES (101,'facility',1,'vi','bai-review-co-so-mot','draft','Review cơ sở một','<p>Nội dung tiếng Việt</p>','PRIVATE_VI_SNAPSHOT','PRIVATE_VI_METADATA','PRIVATE_VI_TOKEN')");
$draftVietnamese = $linksQueue()['items'][0];
$assert($draftVietnamese['vietnamese_review_url'] === null && $draftVietnamese['vietnamese_review_id'] === null,
    'A draft Vietnamese article exposes neither its review ID nor its URL');
$linksPdo->exec("UPDATE medical_review_articles SET status='published' WHERE entity_id=1");
$linkedEnglish = $linksQueue()['items'][0];
$assert($linkedEnglish['vietnamese_facility_url'] === '/co-so-y-te/clinic-1'
    && $linkedEnglish['vietnamese_review_url'] === '/review/bai-review-co-so-mot', 'English queue links the exact parent facility and its distinct published review slug');
$assert($linkedEnglish['vietnamese_facility_id'] === 1 && $linkedEnglish['vietnamese_review_id'] === 101,
    'Vietnamese review ID is the integer article primary key, not its facility entity ID');
$assert($linkedEnglish['language_code'] === 'en' && $linkedEnglish['source']['facility']['url'] === '/en/co-so-y-te/clinic-6'
    && $linkedEnglish['output_template']['language_code'] === 'en', 'Reference links do not change the English writing target or its canonical facility URL');
$assert($linkedEnglish['source_revision'] === $englishRevisionBeforeReview
    && medical_review_article_ai_json($linkedEnglish['source']) === medical_review_article_ai_json($englishSourceBeforeReview),
    'Publishing a Vietnamese review does not invalidate an English source or its lease revision');
$assert(!array_key_exists('vietnamese_facility_url', $linkedEnglish['source'])
    && !array_key_exists('vietnamese_review_url', $linkedEnglish['source']['facility'])
    && !array_key_exists('vietnamese_review_url', $linkedEnglish['output_template'])
    && !array_key_exists('vietnamese_facility_id', $linkedEnglish['source'])
    && !array_key_exists('vietnamese_review_id', $linkedEnglish['source']['facility'])
    && !array_key_exists('vietnamese_review_id', $linkedEnglish['output_template']), 'Vietnamese references remain response metadata, not source data or AI output fields');
$linksPdo->exec("UPDATE medical_review_articles SET slug='review-tieng-viet-da-doi',content_html='<p>Bản cập nhật tiếng Việt</p>' WHERE entity_id=1");
$changedVietnamese = $linksQueue()['items'][0];
$assert($changedVietnamese['vietnamese_review_url'] === '/review/review-tieng-viet-da-doi'
    && $changedVietnamese['vietnamese_facility_id'] === 1 && $changedVietnamese['vietnamese_review_id'] === 101
    && $changedVietnamese['source_revision'] === $englishRevisionBeforeReview, 'Vietnamese article slug changes refresh its link without changing either reference ID or the English source revision');
$linksFacilityChange("UPDATE medical_facilities SET slug='clinic-one-seo-updated' WHERE id=1");
$changedVietnameseFacility = $linksQueue()['items'][0];
$assert($changedVietnameseFacility['vietnamese_facility_url'] === '/co-so-y-te/clinic-one-seo-updated'
    && $changedVietnameseFacility['vietnamese_facility_id'] === 1 && $changedVietnameseFacility['vietnamese_review_id'] === 101
    && $changedVietnameseFacility['source_revision'] === $englishRevisionBeforeReview,
    'Vietnamese facility SEO slug changes leave both reference IDs and the English source revision stable');
$linksFacilityChange("UPDATE medical_facilities SET slug='clinic-1' WHERE id=1");
$linkedMap = medical_review_article_ai_vietnamese_links($linksPdo, [$changedVietnamese['source']]);
$assert($linkedMap[6] === ['vietnamese_facility_id' => 1, 'vietnamese_review_id' => 101,
    'vietnamese_facility_url' => '/co-so-y-te/clinic-1', 'vietnamese_review_url' => '/review/review-tieng-viet-da-doi'],
    'Batch helper returns only the four documented optional reference fields for an English entity');

foreach (["status='archived'", "title='   '", "slug='   '", "content_html='   '", 'content_html=NULL', "language_code='en'", "entity_type='doctor'"] as $invalidReview) {
    $linksPdo->exec("UPDATE medical_review_articles SET entity_type='facility',language_code='vi',status='published',title='Review cơ sở một',slug='review-tieng-viet-da-doi',content_html='<p>Nội dung tiếng Việt</p>' WHERE entity_id=1");
    $linksPdo->exec('UPDATE medical_review_articles SET ' . $invalidReview . ' WHERE entity_id=1');
    $invalidReviewLinks = $linksQueue()['items'][0];
    $assert($invalidReviewLinks['vietnamese_facility_url'] === '/co-so-y-te/clinic-1' && $invalidReviewLinks['vietnamese_review_url'] === null
        && $invalidReviewLinks['vietnamese_facility_id'] === 1 && $invalidReviewLinks['vietnamese_review_id'] === null,
        'Nonpublic, incomplete, wrong-language or non-facility articles cannot become Vietnamese review links: ' . $invalidReview);
}
$linksPdo->exec("UPDATE medical_review_articles SET entity_type='facility',language_code='vi',status='published',title='Review cơ sở một',slug='review-tieng-viet-da-doi',content_html='<p>Nội dung tiếng Việt</p>' WHERE entity_id=1");
$linksPdo->exec("INSERT INTO medical_review_articles (entity_type,entity_id,language_code,slug,status,title,content_html)
    VALUES ('facility',2,'vi','unrelated-vietnamese-review','published','Unrelated clinic','<p>Other clinic content</p>')");
foreach (["status='draft'", "status='archived'", "language_code='en'", "slug='   '"] as $invalidParent) {
    $linksFacilityChange("UPDATE medical_facilities SET status='published',language_code='vi',slug='clinic-1' WHERE id=1");
    $linksFacilityChange('UPDATE medical_facilities SET ' . $invalidParent . ' WHERE id=1');
    $invalidParentLinks = $linksQueue()['items'][0];
    $assert($invalidParentLinks['vietnamese_facility_url'] === null && $invalidParentLinks['vietnamese_review_url'] === null
        && $invalidParentLinks['vietnamese_facility_id'] === null && $invalidParentLinks['vietnamese_review_id'] === null,
        'An unavailable or wrong-language parent suppresses both links: ' . $invalidParent);
}
$linksFacilityChange("UPDATE medical_facilities SET status='published',language_code='vi',slug='clinic-1' WHERE id=1");
foreach (['0', 'NULL', '99999', '6'] as $invalidParentId) {
    $linksFacilityChange('UPDATE medical_facilities SET translation_of_id=' . $invalidParentId . ' WHERE id=6');
    $withoutParentLinks = $linksQueue()['items'][0];
    $assert($withoutParentLinks['vietnamese_facility_url'] === null && $withoutParentLinks['vietnamese_review_url'] === null
        && $withoutParentLinks['vietnamese_facility_id'] === null && $withoutParentLinks['vietnamese_review_id'] === null,
        'Zero, null, nonexistent and English self parents cannot attach unrelated Vietnamese reviews: ' . $invalidParentId);
}
$linksFacilityChange("UPDATE medical_facilities SET translation_of_id=NULL,slug='clinic-2',name='Cơ sở 2' WHERE id=6");
$sameNameLinks = $linksQueue()['items'][0];
$assert($sameNameLinks['vietnamese_facility_url'] === null && $sameNameLinks['vietnamese_review_url'] === null
    && $sameNameLinks['vietnamese_facility_id'] === null && $sameNameLinks['vietnamese_review_id'] === null,
    'The same facility name or slug never substitutes for an explicit translation link');
$linksFacilityChange("UPDATE medical_facilities SET translation_of_id=1,slug='clinic-6',name='Cơ sở 6' WHERE id=6");
$linksPdo->statements = [];
$privateLinksQueue = $linksQueue();
$linksJson = medical_review_article_ai_json($privateLinksQueue);
foreach (['PRIVATE_FULL', 'PRIVATE_PATIENT_IMAGES', 'PRIVATE_PATIENT_NOTES', 'PRIVATE_FACILITY_TOKEN', 'PRIVATE_VI_SNAPSHOT', 'PRIVATE_VI_METADATA', 'PRIVATE_VI_TOKEN'] as $private) {
    $assert(!str_contains($linksJson, $private), 'Optional language links cannot expose private fields: ' . $private);
}
foreach ($linksPdo->statements as $sql) $assert(!preg_match('/^\s*(UPDATE|INSERT|DELETE|CREATE|ALTER|DROP)/i', $sql), 'Resolving language links keeps English GET strictly read-only');
$linksPdo->statements = [];
$vietnameseQueue = medical_review_article_ai_queue($linksPdo, ['language' => 'vi', 'id' => '2'], null, 1000);
$assert(!array_key_exists('vietnamese_facility_url', $vietnameseQueue['items'][0])
    && !array_key_exists('vietnamese_review_url', $vietnameseQueue['items'][0])
    && !array_key_exists('vietnamese_facility_id', $vietnameseQueue['items'][0])
    && !array_key_exists('vietnamese_review_id', $vietnameseQueue['items'][0]), 'Existing Vietnamese queue response fields are unchanged');
$assert(count(array_filter($linksPdo->statements, static fn(string $sql): bool => str_contains($sql, 'FROM medical_facilities en'))) === 0,
    'Vietnamese queue does not execute the optional English-to-Vietnamese link query');
$linksPdo->statements = [];
$assert(medical_review_article_ai_vietnamese_links($linksPdo, []) === []
    && medical_review_article_ai_vietnamese_links($linksPdo, [array_replace($englishSourceBeforeReview, ['language_code' => 'vi'])]) === []
    && $linksPdo->statements === [], 'Empty or Vietnamese-only source batches do not query for optional links');

// POST links English articles to Vietnamese ARTICLE IDs, not mutable SEO URLs or entity IDs.
$translationFixture = static function (bool $withTranslationSchema = true): ReviewArticleAiTestPDO {
    $connection = review_article_ai_fixture();
    $connection->runtime = false;
    try {
        if ($withTranslationSchema) medreview_with_schema_migration(static fn() => medical_review_article_translations_migrate($connection));
        $connection->exec('UPDATE medical_facilities SET translation_of_id=1 WHERE id=6');
        $connection->exec("INSERT INTO medical_review_articles (id,entity_type,entity_id,language_code,slug,status,title,content_html)
            VALUES (101,'facility',1,'vi','review-vietnamese-parent','published','Review cơ sở tiếng Việt','<p>Nội dung bài review tiếng Việt.</p>')");
    } finally { $connection->runtime = true; }
    return $connection;
};
$prepareEnglishTranslation = static function (ReviewArticleAiTestPDO $connection, string $request, int $clock = 1000, int $entityId = 6) use ($articleBody): array {
    $englishSource = medical_review_articles_facility_source($connection, $entityId);
    $englishRevision = medical_review_articles_source_revision($englishSource);
    $owner = medical_review_article_ai_lease($connection, ['entity_id' => $entityId, 'language_code' => 'en',
        'source_revision' => $englishRevision, 'request_id' => $request,
        'client' => ['instance_id' => 'translation-device', 'worker_id' => 'translation-worker']], $clock);
    return array_replace($articleBody, ['entity_id' => $entityId, 'language_code' => 'en', 'source_revision' => $englishRevision,
        'claim_token' => $owner['claim_token'], 'request_id' => $request, 'title' => 'Review English clinic',
        'sources_json' => medical_review_article_ai_source_catalog($englishSource),
        'editorial_evidence_json' => ['schema_version' => 1, 'claims' => []]]);
};
$translationCase = 0;
foreach ([['vietnamese_review_id' => 101], ['vietnamese_facility_id' => 1],
    ['vietnamese_review_id' => '101', 'vietnamese_facility_id' => '1']] as $translationAssertions) {
    ++$translationCase;
    $translationPdo = $translationFixture();
    $translationBody = $prepareEnglishTranslation($translationPdo, 'translation-success-' . $translationCase) + $translationAssertions;
    $translationBody['status'] = 'draft';
    $translatedReceipt = medical_review_article_ai_save($translationPdo, $translationBody, 1100);
    $translatedRow = medical_review_article_ai_article_row($translationPdo, 6, 'en');
    $assert($translatedReceipt['status'] === 'published' && $translatedReceipt['saved'] && $translatedReceipt['storage_verified'],
        'Successful linked English writes publish even when the sender requested draft');
    $assert((int) $translatedRow['translation_of_id'] === 101 && $translatedReceipt['translation_of_id'] === 101
        && $translatedReceipt['vietnamese_review_id'] === 101 && $translatedReceipt['vietnamese_facility_id'] === 1,
        'Either Vietnamese assertion resolves and persists the same distinct article parent');
    $assert((int) $translationPdo->query("SELECT COUNT(*) FROM medical_review_articles WHERE entity_id=6 AND language_code='en'")->fetchColumn() === 1,
        'Linking never creates a duplicate English article or a replacement Vietnamese article');
    $translationPdo->exec("UPDATE medical_review_articles SET slug='parent-new-seo-url',status='archived' WHERE id=101");
    $translatedReplay = medical_review_article_ai_save($translationPdo, $translationBody, 9999);
    $assert($translatedReplay['idempotent'] && $translatedReplay['vietnamese_review_id'] === 101,
        'Exact linked replay uses the saved ID receipt even after Vietnamese slug and visibility change');
    $changedAssertions = $translationBody;
    $changedAssertions[array_key_exists('vietnamese_review_id', $translationAssertions) ? 'vietnamese_review_id' : 'vietnamese_facility_id'] = 9999;
    $throws(static fn() => medical_review_article_ai_save($translationPdo, $changedAssertions, 9999), 409, 'idempotency_conflict');
    $translationPdo->exec('UPDATE medical_review_articles SET translation_of_id=NULL WHERE entity_id=6');
    $throws(static fn() => medical_review_article_ai_save($translationPdo, $translationBody, 9999), 409, 'article_changed');
    $assert(!$translationPdo->inTransaction(), 'Failed linked replay rolls back and closes its transaction');
}

// A facility-only assertion does not pin an unpublished Vietnamese draft.
$facilityOnlyPdo = $translationFixture();
$facilityOnlyPdo->exec("UPDATE medical_review_articles SET status='draft' WHERE id=101");
$facilityOnlyBody = $prepareEnglishTranslation($facilityOnlyPdo, 'facility-only-without-published-review') + ['vietnamese_facility_id' => 1, 'vietnamese_review_id' => null];
$facilityOnlyReceipt = medical_review_article_ai_save($facilityOnlyPdo, $facilityOnlyBody, 1100);
$assert($facilityOnlyReceipt['status'] === 'published' && $facilityOnlyReceipt['vietnamese_facility_id'] === 1
    && $facilityOnlyReceipt['vietnamese_review_id'] === null && $facilityOnlyReceipt['translation_of_id'] === null,
    'A valid facility-only assertion accepts English content without linking a Vietnamese draft');

$migrationDuringClaimPdo = $translationFixture(false);
$migrationDuringClaimBody = $prepareEnglishTranslation($migrationDuringClaimPdo, 'migration-during-claim') + ['vietnamese_review_id' => 101];
$migrationDuringClaimPdo->runtime = false;
medreview_with_schema_migration(static fn() => medical_review_article_translations_migrate($migrationDuringClaimPdo));
$migrationDuringClaimPdo->runtime = true;
$assert(medical_review_article_ai_save($migrationDuringClaimPdo, $migrationDuringClaimBody, 1100)['vietnamese_review_id'] === 101,
    'Adding the optional null column does not invalidate an otherwise unchanged active claim');

// Reject malformed IDs, wrong article identities and mismatched source families atomically.
$invalidTranslationPdo = $translationFixture();
$invalidTranslationBody = $prepareEnglishTranslation($invalidTranslationPdo, 'translation-invalid');
$beforeInvalidTranslation = medical_review_article_ai_article_row($invalidTranslationPdo, 6, 'en');
foreach (['vietnamese_review_id', 'vietnamese_facility_id'] as $idField) {
    foreach (['', 0, -1, true, false, 1.5, 101.0, '01', '1 OR 1=1', '4294967296', [], new stdClass()] as $invalidId) {
        $badTranslation = $invalidTranslationBody;
        $badTranslation[$idField] = $invalidId;
        $throws(static fn() => medical_review_article_ai_save($invalidTranslationPdo, $badTranslation, 1100), 422, 'invalid_translation');
    }
}
foreach ([['vietnamese_review_id' => 9999], ['vietnamese_facility_id' => 9999], ['vietnamese_facility_id' => 6],
    ['vietnamese_facility_id' => 2], ['vietnamese_review_id' => 101, 'vietnamese_facility_id' => 2]] as $invalidAssertions) {
    $throws(static fn() => medical_review_article_ai_save($invalidTranslationPdo, $invalidTranslationBody + $invalidAssertions, 1100), 422, 'invalid_translation');
}
foreach (["status='draft'", "status='archived'", "language_code='en'", "entity_type='doctor'", 'entity_id=2', "title=' '", "slug=' '", "content_html=' '"] as $invalidParentArticle) {
    $invalidTranslationPdo->exec("UPDATE medical_review_articles SET entity_type='facility',entity_id=1,language_code='vi',status='published',title='Vietnamese review',slug='parent-review',content_html='<p>Vietnamese content</p>' WHERE id=101");
    $invalidTranslationPdo->exec('UPDATE medical_review_articles SET ' . $invalidParentArticle . ' WHERE id=101');
    $throws(static fn() => medical_review_article_ai_save($invalidTranslationPdo, $invalidTranslationBody + ['vietnamese_review_id' => 101], 1100), 422, 'invalid_translation');
}
$assert(medical_review_article_ai_article_row($invalidTranslationPdo, 6, 'en') === $beforeInvalidTranslation
    && !$invalidTranslationPdo->inTransaction(), 'Every invalid translation leaves content, publication, relation and writer ownership unchanged');
foreach (['NULL', '2', '6', '9999'] as $wrongFacilityParent) {
    $wrongFamilyPdo = $translationFixture();
    $wrongFamilyPdo->runtime = false;
    $wrongFamilyPdo->exec('UPDATE medical_facilities SET translation_of_id=' . $wrongFacilityParent . ' WHERE id=6');
    $wrongFamilyPdo->runtime = true;
    $wrongFamilyBody = $prepareEnglishTranslation($wrongFamilyPdo, 'translation-wrong-family-' . $wrongFacilityParent)
        + ['vietnamese_review_id' => 101];
    $throws(static fn() => medical_review_article_ai_save($wrongFamilyPdo, $wrongFamilyBody, 1100), 422, 'invalid_translation');
}

$duplicateTranslationPdo = $translationFixture();
$duplicateOriginalBody = $prepareEnglishTranslation($duplicateTranslationPdo, 'translation-first-child') + ['vietnamese_review_id' => 101];
medical_review_article_ai_save($duplicateTranslationPdo, $duplicateOriginalBody, 1100);
$duplicateTranslationPdo->runtime = false;
$duplicateTranslationPdo->exec("UPDATE medical_facilities SET language_code='en',translation_of_id=1 WHERE id=8");
$duplicateTranslationPdo->runtime = true;
$duplicateTranslationBody = $prepareEnglishTranslation($duplicateTranslationPdo, 'translation-second-child', 1200, 8)
    + ['vietnamese_review_id' => 101];
$duplicateBefore = medical_review_article_ai_article_row($duplicateTranslationPdo, 8, 'en');
$throws(static fn() => medical_review_article_ai_save($duplicateTranslationPdo, $duplicateTranslationBody, 1250), 409, 'translation_conflict');
$assert(medical_review_article_ai_article_row($duplicateTranslationPdo, 8, 'en') === $duplicateBefore,
    'A Vietnamese review cannot be attached to a second English article or partially saved after a binding conflict');

// Optional fields do not silently move Vietnamese originals or clear an existing English pairing.
$preserveTranslationPdo = $translationFixture();
$firstTranslationBody = $prepareEnglishTranslation($preserveTranslationPdo, 'translation-initial') + ['vietnamese_review_id' => 101];
$firstTranslationReceipt = medical_review_article_ai_save($preserveTranslationPdo, $firstTranslationBody, 1100);
$omittedTranslationBody = $prepareEnglishTranslation($preserveTranslationPdo, 'translation-omitted', 1200);
$omittedTranslationBody['title'] .= ' updated without references';
$omittedTranslationReceipt = medical_review_article_ai_save($preserveTranslationPdo, $omittedTranslationBody, 1250);
$assert($omittedTranslationReceipt['article_id'] === $firstTranslationReceipt['article_id']
    && $omittedTranslationReceipt['vietnamese_review_id'] === 101
    && (int) medical_review_article_ai_article_row($preserveTranslationPdo, 6, 'en')['translation_of_id'] === 101,
    'Updating English content with omitted IDs preserves the previously stored article pairing');
$nullableTranslationBody = $prepareEnglishTranslation($preserveTranslationPdo, 'translation-null', 1300)
    + ['vietnamese_review_id' => null, 'vietnamese_facility_id' => null];
$assert(medical_review_article_ai_save($preserveTranslationPdo, $nullableTranslationBody, 1350)['vietnamese_review_id'] === 101,
    'Explicit null means no translation assertion, not an unlink instruction');
$relationChangedBody = $prepareEnglishTranslation($preserveTranslationPdo, 'translation-concurrent', 1400);
$preserveTranslationPdo->exec('UPDATE medical_review_articles SET translation_of_id=NULL WHERE entity_id=6');
$throws(static fn() => medical_review_article_ai_lease($preserveTranslationPdo, ['entity_id' => 6, 'language_code' => 'en', 'action' => 'heartbeat',
    'claim_token' => $relationChangedBody['claim_token']], 1450), 409, 'article_changed');
$throws(static fn() => medical_review_article_ai_save($preserveTranslationPdo, $relationChangedBody, 1450), 409, 'article_changed');
$preserveTranslationPdo->exec('UPDATE medical_review_articles SET translation_of_id=101 WHERE entity_id=6');
medical_review_article_ai_lease($preserveTranslationPdo, ['entity_id' => 6, 'language_code' => 'en', 'action' => 'release', 'claim_token' => $relationChangedBody['claim_token']], 1450);
require_once dirname(__DIR__) . '/medical_review_article_admin.php';
$manualEnglish = medical_review_article_ai_article_row($preserveTranslationPdo, 6, 'en');
$manualBody = array_intersect_key($nullableTranslationBody, array_flip(MEDICAL_REVIEW_ARTICLE_CONTENT_FIELDS));
$manualBody['status'] = 'published';
medical_review_article_admin_save($preserveTranslationPdo, (int) $manualEnglish['id'], $manualBody, medical_review_article_admin_version($manualEnglish));
$assert((int) medical_review_article_ai_article_row($preserveTranslationPdo, 6, 'en')['translation_of_id'] === 101,
    'Manual article editing preserves the stable translation column');

$viAssertionsPdo = $translationFixture();
[$viAssertionsOwner, $viAssertionsBody] = $prepareUpdate($viAssertionsPdo, $articleBody, 'vietnamese-cannot-be-child', 1000);
foreach ([['vietnamese_review_id' => 101], ['vietnamese_facility_id' => 1]] as $notEnglishAssertions) {
    $throws(static fn() => medical_review_article_ai_save($viAssertionsPdo, $viAssertionsBody + $notEnglishAssertions, 1100), 422, 'invalid_translation');
}
$viAssertionsBody['vietnamese_review_id'] = null;
$viAssertionsBody['vietnamese_facility_id'] = null;
$viWithoutAssertionsReceipt = medical_review_article_ai_save($viAssertionsPdo, $viAssertionsBody, 1100);
$assert($viWithoutAssertionsReceipt['translation_of_id'] === null && $viWithoutAssertionsReceipt['vietnamese_review_id'] === null
    && $viWithoutAssertionsReceipt['vietnamese_facility_id'] === null, 'Vietnamese originals accept nullable no-op reference fields without creating a self-link');

// The optional migration is explicit: untouched legacy schema and clients still work without it.
$noTranslationSchemaPdo = $translationFixture(false);
$noTranslationSchemaBody = $prepareEnglishTranslation($noTranslationSchemaPdo, 'translation-schema-unavailable');
$noTranslationSchemaBefore = medical_review_article_ai_article_row($noTranslationSchemaPdo, 6, 'en');
$throws(static fn() => medical_review_article_ai_save($noTranslationSchemaPdo, $noTranslationSchemaBody + ['vietnamese_review_id' => 101], 1100), 503, 'translation_schema_unavailable');
$assert(medical_review_article_ai_article_row($noTranslationSchemaPdo, 6, 'en') === $noTranslationSchemaBefore,
    'Missing optional schema cannot partially publish a submitted relation');
$noTranslationSchemaPdo->statements = [];
$legacyUnlinkedBody = $noTranslationSchemaBody;
unset($legacyUnlinkedBody['editorial_evidence_json']);
$legacyUnlinkedReceipt = medical_review_article_ai_save($noTranslationSchemaPdo, $legacyUnlinkedBody, 1100);
$legacyUnlinkedMetadata = medical_review_article_ai_decode(medical_review_article_ai_article_row($noTranslationSchemaPdo, 6, 'en')['ai_content_json']);
$assert($legacyUnlinkedReceipt['status'] === 'published' && $legacyUnlinkedReceipt['translation_of_id'] === null
    && $legacyUnlinkedReceipt['vietnamese_review_id'] === null && $legacyUnlinkedReceipt['vietnamese_facility_id'] === null,
    'Legacy clients on pre-migration schema remain usable and return explicit null article references');
$assert($legacyUnlinkedMetadata['payload_sha256'] === medical_review_article_ai_payload_hash(6, 'en', $legacyUnlinkedBody['source_revision'],
    'published', medical_review_article_ai_content($legacyUnlinkedBody), null), 'Omitted translation IDs keep the exact established legacy payload hash');
$legacyNullReplayBody = $legacyUnlinkedBody + ['vietnamese_review_id' => null, 'vietnamese_facility_id' => null];
$assert(medical_review_article_ai_save($noTranslationSchemaPdo, $legacyNullReplayBody, 9999)['idempotent'],
    'Null optional IDs normalize to omission and do not break legacy receipt replay');
foreach ($noTranslationSchemaPdo->statements as $sql) $assert(!preg_match('/^\s*(CREATE|ALTER|DROP)/i', $sql),
    'Runtime write never attempts optional translation DDL');
echo 'Review article AI: ' . $checks . " checks passed.\n";
