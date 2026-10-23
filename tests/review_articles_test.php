<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
require_once dirname(__DIR__) . '/medical_review_articles.php';
$output = ob_get_clean();
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$reject = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); $thrown = false; } catch (InvalidArgumentException) { $thrown = true; }
    $assert($thrown, $message);
};
$assert($output === '', 'Include performs no connection/output/runtime migration');
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$assert(!medical_review_articles_schema_ready($pdo), 'Missing article schema detected read-only');
try { medical_review_articles_migrate($pdo); $blocked = false; } catch (LogicException) { $blocked = true; }
$assert($blocked, 'Runtime migration denied');
$assert(medreview_with_schema_migration(fn() => medical_review_articles_migrate($pdo)), 'Explicit additive migration creates table');
$assert(medical_review_articles_schema_ready($pdo), 'Schema ready after explicit migration');
$assert(!medreview_with_schema_migration(fn() => medical_review_articles_migrate($pdo)), 'Migration idempotent');
$assert(!medreview_schema_migration_allowed(), 'Explicit migration permission restored');
$assert((int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='medical_reviews'")->fetchColumn() === 0, 'Individual feedback table never created or changed');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_review_articles')->fetchColumn() === 0, 'Migration never seeds fake articles');

$columns = [];
foreach (medical_review_articles_facility_columns() as $field) $columns[] = $field === 'id' ? 'id INTEGER PRIMARY KEY' : $field . ' TEXT NULL';
$pdo->exec('CREATE TABLE medical_facilities (' . implode(',', $columns) . ',stored_fake_count INTEGER,patient_images_json TEXT,patient_images_notes TEXT,full_json TEXT,ai_writer_claim_json TEXT)');
$pdo->exec('CREATE TABLE medical_reviews (id INTEGER PRIMARY KEY,facility_id INTEGER NULL,facility_slug TEXT,status TEXT,rating REAL,excerpt TEXT,service_text TEXT,review_date_text TEXT,source_text TEXT,author_text TEXT,before_image_url TEXT)');
$insights = ['source' => 'photo_sweep','swept_at' => '2026-10-22T00:00:00Z','summary' => 'Không gian cơ sở có khu vực tiếp đón và nhiều phòng điều trị, cần đối chiếu thực tế.',
    'coverage' => ['lobby' => 'Khu tiếp đón rộng rãi, có bảng thông tin dành cho khách.'], 'patient_images_notes' => 'SECRET_PATIENT',
    'nested' => ['claim_token' => 'SECRET_TOKEN','patient_images_json' => ['SECRET_IMAGE'],'visible' => 'Thông tin phân tích chung công khai.']];
$baseFacility = ['id' => 1,'slug' => 'nha-khoa-a','language_code' => 'vi','name' => 'Nha khoa A','status' => 'published','category' => 'Nha khoa','city' => 'Đà Nẵng',
    'facility_insights_json' => json_encode($insights, JSON_UNESCAPED_UNICODE), 'aggregate_ratings_json' => json_encode([['source' => 'Google Maps','score' => 4.7,'count' => 81,'url' => 'https://maps.google.com/?cid=1']]),
    'image_url' => '/uploads/facility-a.webp', 'address_text' => '12 Đường A', 'website_url' => 'https://clinic.example/about',
    'social_links_json' => json_encode([['platform' => 'facebook','url' => 'https://facebook.com/clinic','patient_images_notes' => 'SECRET_PATIENT','api_token' => 'SECRET_TOKEN']]),
    'doctors_json' => json_encode([['name' => 'BS Nguyễn A','specialty' => 'Nha khoa','patient_notes' => 'SECRET_PATIENT','private_token' => 'SECRET_TOKEN']]),
    'services_json' => json_encode([['name' => 'Niềng răng','description' => 'Dịch vụ cơ sở công bố.','full_json' => 'SECRET_FULL','nested' => ['claim' => 'SECRET_TOKEN']]]),
    'content' => '<p>' . str_repeat('Nội dung hồ sơ công khai. ', 2000) . '</p><script>KHÔNG THỰC THI</script>',
    'intro_json' => json_encode(['title' => 'Giới thiệu','paragraphs' => ['Cơ sở tự công bố thông tin về dịch vụ và cơ sở vật chất.'],'patient_images_notes' => 'SECRET_PATIENT']),
    'highlights_json' => json_encode([['title' => 'Điểm đáng chú ý','description' => 'Có thông tin phục vụ khách hàng.','claim_token' => 'SECRET_TOKEN']]),
    'business_license' => 'Số giấy tờ do cơ sở cung cấp', 'medical_operation_license' => 'Thông tin tự công bố cần xác minh',
    'insurance_accepted_json' => '["Bảo hiểm cần liên hệ xác nhận"]', 'parking_info' => 'Liên hệ trước để hỏi chỗ đỗ xe.',
    'price_table_html' => '<table onclick="bad()"><tr><th>Dịch vụ</th><th>Giá tham khảo</th></tr><tr><td>Khám răng</td><td>200.000 VND/lượt</td></tr><tr><td>Hàn răng</td><td>400.000–600.000 VND/răng</td></tr></table><script>SECRET_PRICE_SCRIPT</script>',
    'price_source_scope' => 'branch_reference', 'nearby_landmarks' => 'Gần công viên trung tâm',
    'latitude' => '16.047079', 'longitude' => '108.206230', 'booking_url' => 'https://clinic.example/booking', 'branch_count' => '2',
    'utilities_json' => '["Chỗ đỗ xe",{"name":"Thanh toán thẻ","text":"Có hỗ trợ thanh toán thẻ","claim_token":"SECRET_TOKEN","patient_images_notes":"SECRET_PATIENT"}]',
    'equipment_mentioned_json' => '[{"name":"Máy quét hàm","model":"Model A","manufacturer":"Hãng A","purpose":"Lấy dấu hàm","private_token":"SECRET_TOKEN"}]',
    'patient_images_notes' => 'SECRET_PATIENT', 'patient_images_json' => '["SECRET_IMAGE"]','full_json' => 'SECRET_FULL','ai_writer_claim_json' => 'SECRET_CLAIM'];
$insertFacility = static function (array $data) use ($pdo): void {
    $keys = array_keys($data);
    $statement = $pdo->prepare('INSERT INTO medical_facilities (' . implode(',', $keys) . ') VALUES (' . implode(',', array_fill(0, count($keys), '?')) . ')');
    $statement->execute(array_values($data));
};
$insertFacility($baseFacility);
$english = $baseFacility; $english['id'] = 2; $english['translation_of_id'] = 1; $english['slug'] = 'dental-a'; $english['language_code'] = 'en'; $english['name'] = 'Dental A'; $english['city'] = 'Da Nang';
$insertFacility($english);
$other = $baseFacility; $other['id'] = 3; $other['slug'] = 'nha-khoa-b'; $other['name'] = 'Nha khoa B'; $other['city'] = 'Huế'; $other['aggregate_ratings_json'] = '[]'; $other['stored_fake_count'] = 99999;
$insertFacility($other);
$draftFacility = $baseFacility; $draftFacility['id'] = 4; $draftFacility['slug'] = 'nha-khoa-draft'; $draftFacility['status'] = 'draft';
$insertFacility($draftFacility);
$review = $pdo->prepare('INSERT INTO medical_reviews(id,facility_id,facility_slug,status,rating,excerpt,service_text,review_date_text,source_text,author_text,before_image_url) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
for ($i = 1; $i <= 45; $i++) $review->execute([$i,3,'nha-khoa-b','published',4,"Cơ sở tiếp đón chu đáo; liên hệ 0901 234 567 hoặc patient@example.com",'Khám răng','2026-10-20','Nguồn công khai','PRIVATE_AUTHOR','PRIVATE_PATIENT_IMAGE']);
$review->execute([100,1,'nha-khoa-a','published',5,'Phản hồi công khai về thời gian đợi.','Khám','2026-10-21','Google','PRIVATE_AUTHOR','PRIVATE_PATIENT_IMAGE']);
$review->execute([101,null,'nha-khoa-a','published',3,'Cần cải thiện lịch hẹn.','Khám','2026-10-22','Google','PRIVATE_AUTHOR','PRIVATE_PATIENT_IMAGE']);
$review->execute([102,1,'nha-khoa-a','draft',1,'Không công khai.','','','','PRIVATE_AUTHOR','PRIVATE_PATIENT_IMAGE']);
$review->execute([103,1,'nha-khoa-a','published',0,'Không có sao hợp lệ.','','','','PRIVATE_AUTHOR','PRIVATE_PATIENT_IMAGE']);
$source = medical_review_articles_facility_source($pdo, 1);
$assert(is_array($source) && $source['entity_id'] === 1, 'Facility source identified by DB ID');
$assert($source['review_metrics']['reviews_count'] === 81 && $source['review_metrics']['basis'] === 'external_aggregates', 'Valid external total retained separately');
$assert($source['stored_review_metrics']['reviews_count'] === 2 && $source['sample_count'] === 2, 'Owner/legacy-slug samples count actual published rated rows only');
$assert(medical_review_articles_eligibility($source)['eligible'], 'More than 40 external reviews and meaningful legacy insights qualify');
$assert(str_contains(json_encode($source), 'SECRET_') === false, 'Source DTO excludes secrets/patient material recursively');
$assert(str_contains(json_encode($source), 'PRIVATE_') === false, 'Sample authors and before/after patient images never selected');
$assert($source['facility']['social_links_json'][0] === ['platform' => 'facebook','url' => 'https://facebook.com/clinic'], 'Social source allows only public URL/platform');
$assert($source['facility']['doctors_json'][0] === ['name' => 'BS Nguyễn A','specialty' => 'Nha khoa'], 'Public doctor facts omit arbitrary extension metadata');
$assert($source['facility']['services_json'][0] === ['name' => 'Niềng răng','description' => 'Dịch vụ cơ sở công bố.'], 'Service facts use known public fields only');
$assert(mb_strlen($source['facility']['content'], 'UTF-8') === 20000 && !str_contains($source['facility']['content'], '<p>'), 'Public profile content is plain text bounded to 20k characters');
$assert($source['facility']['intro_json']['paragraphs'][0] === 'Cơ sở tự công bố thông tin về dịch vụ và cơ sở vật chất.', 'Known public intro paragraphs retained');
$assert($source['facility']['highlights_json'][0]['title'] === 'Điểm đáng chú ý', 'Known public highlights retained');
$assert($source['facility']['business_license'] === 'Số giấy tờ do cơ sở cung cấp' && str_contains($source['facility']['profile_claims_notice'], 'không phải xác minh pháp lý'), 'License strings remain explicitly unverified profile claims');
$assert(str_contains($source['facility']['price_table_html'], 'Khám răng') && str_contains($source['facility']['price_table_html'], '200.000 VND/lượt')
    && str_contains($source['facility']['price_table_html'], 'Hàn răng') && str_contains($source['facility']['price_table_html'], '400.000–600.000 VND/răng'), 'Source supplies actual price-table services, amounts, units and ranges');
$assert(!str_contains($source['facility']['price_table_html'], 'onclick') && !str_contains($source['facility']['price_table_html'], '<script'), 'Price table is sanitized without importing active content');
$assert($source['facility']['price_source_scope'] === 'branch_reference' && $source['facility']['nearby_landmarks'] === 'Gần công viên trung tâm', 'Price scope and location landmarks accompany profile facts');
$assert($source['facility']['latitude'] === 16.047079 && $source['facility']['longitude'] === 108.20623 && $source['facility']['branch_count'] === 2, 'Coordinates and branch count normalized as bounded numbers');
$assert($source['facility']['booking_url'] === 'https://clinic.example/booking', 'Public booking URL supplied for practical context');
$assert($source['facility']['utilities_json'] === ['Chỗ đỗ xe', ['name' => 'Thanh toán thẻ', 'text' => 'Có hỗ trợ thanh toán thẻ']], 'Utilities allow public descriptions, not icons/claims/private extension fields');
$assert($source['facility']['equipment_mentioned_json'][0] === ['name' => 'Máy quét hàm', 'model' => 'Model A', 'manufacturer' => 'Hãng A', 'purpose' => 'Lấy dấu hàm'], 'Equipment retains real model/manufacturer/purpose without private metadata');
$assert(!in_array('features_json', medical_review_articles_facility_columns(), true) && !array_key_exists('features_json', $source['facility']), 'Platform trust/verification features are not misrepresented as facility attributes');
$numericService = medical_review_articles_nested_facts([['name' => 'Khám', 'price' => 250000, 'unit' => 'lượt', 'currency' => 'VND', 'patient_images_notes' => 'SECRET_PATIENT']], 'services_json');
$assert($numericService === [['name' => 'Khám', 'price' => 250000, 'unit' => 'lượt', 'currency' => 'VND']], 'Numeric service prices retain units and currency for cross-checking');
foreach ([['latitude' => '91', 'longitude' => '-181', 'branch_count' => '0', 'booking_url' => 'javascript:bad()'],
    ['latitude' => INF, 'longitude' => NAN, 'branch_count' => '2.5', 'booking_url' => 'http://127.0.0.1/private'],
    ['latitude' => true, 'longitude' => 'not-a-coordinate', 'branch_count' => true, 'booking_url' => 'blob:https://clinic.example/fake']] as $invalidPractical) {
    $invalidSource = medical_review_articles_source_from_row(array_replace($baseFacility, $invalidPractical));
    $assert($invalidSource['facility']['latitude'] === null && $invalidSource['facility']['longitude'] === null
        && $invalidSource['facility']['branch_count'] === null && $invalidSource['facility']['booking_url'] === '', 'Invalid location numbers, branch count and booking URLs never become source facts');
}
$storedSource = medical_review_articles_facility_source($pdo, 3);
$assert($storedSource['review_metrics']['reviews_count'] === 45 && $storedSource['review_metrics']['basis'] === 'stored_reviews', 'Actual stored count qualifies independently');
$assert(medical_review_articles_eligibility($storedSource)['eligible'], '45 valid stored reviews qualify');
$assert($storedSource['sample_count'] === 40, 'Source samples bounded independently of total');
$samplesText = json_encode($storedSource['review_samples'], JSON_UNESCAPED_UNICODE);
$assert(!str_contains($samplesText, 'patient@example.com') && !str_contains($samplesText, '0901'), 'Samples mask contact identifiers without claiming complete anonymization');
$assert(medical_review_articles_facility_source($pdo, 999) === null, 'Unknown facility never fabricated');
$assert(medical_review_articles_facility_source($pdo, 1, 'en') === null, 'Explicit locale cannot select other language entity');
$assert(count(medical_review_articles_facility_sources($pdo, 'en')) === 1, 'Batch source reader respects language');
$assert(count(medical_review_articles_facility_sources($pdo, 'vi', [1], 1)) === 1, 'ID-scoped source reader bounded');
$assert(!medical_review_articles_eligibility(medical_review_articles_facility_source($pdo, 4))['eligible'], 'Draft facility is not eligible');
$edge = $source; $edge['review_metrics']['reviews_count'] = 40;
$assert(!medical_review_articles_eligibility($edge)['eligible'], 'Exactly 40 is not above 40');
$edge = $source; $edge['facility_insights_json'] = new stdClass();
$assert(!medical_review_articles_eligibility($edge)['eligible'], 'Empty insights do not qualify');
$edge = $source; $edge['stored_insights_valid'] = false;
$assert(!medical_review_articles_eligibility($edge)['eligible'], 'Invalid stored insights do not qualify');
$assert(!medical_review_articles_insights_meaningful(['swept_by' => str_repeat('x', 100),'source' => str_repeat('x', 100),'backfill' => str_repeat('x', 100),'richest_source' => str_repeat('x', 100)]), 'Metadata-only insights are not research');
$assert(!medical_review_articles_insights_meaningful(['sources' => [['url' => 'https://a.example/very-long-url-without-analysis']]]), 'Source URL alone is not analysis');
$assert(medical_review_articles_insights_meaningful(['services' => ['Một dịch vụ đang được cơ sở giới thiệu và cần kiểm tra.']]), 'Meaningful custom legacy insight section qualifies');
$assert(!medical_review_articles_insights_meaningful('{bad'), 'Malformed JSON never qualifies');
$assert(medical_review_articles_review_metrics(['reviews_count' => 9999,'rating' => 5])['reviews_count'] === 0, 'Unclassified legacy/fake scalar totals cannot qualify');
$lowExternal = medical_review_articles_review_metrics(['aggregate_ratings_json' => [['source' => 'Google','score' => 5,'count' => 3]],'stored_reviews_count' => 45,'stored_rating' => 4]);
$assert($lowExternal['reviews_count'] === 45 && $lowExternal['basis'] === 'stored_reviews', 'When only stored count qualifies source snapshot uses stored basis');
$duplicate = medical_review_articles_review_metrics(['aggregate_ratings_json' => [['source' => 'Google','score' => 5,'count' => 30,'url' => 'https://maps.google.com/?cid=1'],['source' => 'Google same','score' => 5,'count' => 30,'url' => 'https://maps.google.com/?cid=1']]]);
$assert($duplicate['reviews_count'] === 30, 'Duplicate external source URLs never inflate eligibility');

$revision = medical_review_articles_source_revision($source);
$reordered = $source; $reordered['facility_insights_json'] = (object) array_reverse(get_object_vars($source['facility_insights_json']), true);
$assert($revision === medical_review_articles_source_revision($reordered), 'Object key formatting does not stale source revision');
$changed = $source; $changed['facility']['name'] = 'Tên thay đổi';
$assert($revision !== medical_review_articles_source_revision($changed), 'Meaningful public fact changes source revision');
$changed = $source; $changed['facility']['price_table_html'] = str_replace('200.000', '300.000', $changed['facility']['price_table_html']);
$assert($revision !== medical_review_articles_source_revision($changed), 'Price table updates invalidate stale AI source revisions');
$changed = $source; $changed['facility']['nearby_landmarks'] = 'Gần nhà ga';
$assert($revision !== medical_review_articles_source_revision($changed), 'Practical location updates also invalidate source revisions');
$private = $source + ['full_json' => 'SECRET_FULL','ai_claim_token' => 'SECRET_TOKEN','patient_images_notes' => 'SECRET_PATIENT'];
$assert($revision === medical_review_articles_source_revision($private), 'Private extension fields cannot affect or enter snapshot');
$assert(strlen($revision) === 64, 'Source revision uses full SHA-256');
$html = '<h2 onclick="bad()">Phân tích</h2><p style="color:red">Nội dung<script>alert(1)</script><img src="https://evil.test/a" onerror="bad()"><svg><script>bad()</script></svg></p><a href="javascript:alert(1)">Xấu</a><a href="https://clinic.example/about">Nguồn</a>';
$safe = medical_review_articles_sanitize_html($html);
foreach (['onclick','style=','<script','<img','<svg','javascript:','alert(1)'] as $bad) $assert(!str_contains($safe, $bad), 'HTML sanitizer removes ' . $bad);
$assert(str_contains($safe, '<h2>') && str_contains($safe, 'noopener noreferrer') && !str_contains($safe, 'nofollow'), 'Reading structure and followable safe sourced link preserved');
$validArticle = ['title' => 'Review Nha khoa A: phân tích thông tin và trải nghiệm','excerpt' => 'Phân tích có nguồn và giới hạn của dữ liệu, không thay thế tư vấn chuyên môn.',
    'verdict' => 'Nên đối chiếu dịch vụ, chi phí và lịch hẹn trực tiếp với cơ sở.', 'content_html' => '<h2>Tổng quan</h2><p>' . str_repeat('Bài viết phân tích thông tin công khai và trải nghiệm được ghi nhận, không khẳng định hiệu quả điều trị. ', 8) . '</p>',
    'strengths_json' => ['Thông tin địa chỉ rõ ràng.'],'considerations_json' => ['Cần xác nhận chi phí trực tiếp.'],'suitable_for_json' => ['Người muốn tham khảo thông tin trước khi liên hệ.'],
    'analysis_sections_json' => [['id' => 'tong-quan','title' => 'Tổng quan','content_html' => '<p>Cơ sở công bố dịch vụ khám răng.</p>','source_ids' => ['website']]],
    'sources_json' => [['id' => 'website','title' => 'Website cơ sở','url' => 'https://clinic.example/about','platform' => 'website']],
    'evidence_limitations_json' => ['Phản hồi là mẫu đã lưu, không đại diện toàn bộ đánh giá từ nguồn.'],'researched_at' => '2026-10-22T10:00:00+07:00'];
$normalized = medical_review_articles_validate_article($validArticle);
$assert($normalized['slug'] === slugify($validArticle['title']), 'Absent article slug generated deterministically');
$assert($normalized['researched_at'] === '2026-10-22 03:00:00', 'Research timestamp normalized to UTC DB format');
$assert(is_array($normalized['sources_json']) && $normalized['analysis_sections_json'][0]['source_ids'] === ['website'], 'Validated JSON returns arrays and cross-references');
foreach (['id' => 1,'status' => 'published','entity_type' => 'doctor','source_snapshot_json' => '{}','ai_writer_claim_json' => '{}'] as $key => $value) {
    $reject(fn() => medical_review_articles_validate_article($validArticle + [$key => $value]), 'AI cannot set privileged content field ' . $key);
}
$bad = $validArticle; $bad['content_html'] = '<p>Ngắn</p>';
$reject(fn() => medical_review_articles_validate_article($bad), 'Article needs readable long-form content');
$bad = $validArticle; $bad['sources_json'] = [];
$reject(fn() => medical_review_articles_validate_article($bad), 'Sources required');
$bad = $validArticle; $bad['evidence_limitations_json'] = [];
$reject(fn() => medical_review_articles_validate_article($bad), 'Evidence limitations required');
$bad = $validArticle; $bad['analysis_sections_json'][0]['source_ids'] = ['invented'];
$reject(fn() => medical_review_articles_validate_article($bad), 'Invented evidence references rejected');
$bad = $validArticle; $bad['sources_json'][0]['url'] = 'http://127.0.0.1/private';
$reject(fn() => medical_review_articles_validate_article($bad), 'Private reference URLs rejected without fetching');
$bad = $validArticle; $bad['researched_at'] = '2026-02-29';
$reject(fn() => medical_review_articles_validate_article($bad), 'Invalid real date rejected');
$bad = $validArticle; $bad['slug'] = '../../admin';
$reject(fn() => medical_review_articles_validate_article($bad), 'Unsafe slugs rejected');
$bad = $validArticle; $bad['sources_json'][] = $bad['sources_json'][0];
$reject(fn() => medical_review_articles_validate_article($bad), 'Duplicate evidence IDs rejected');

$insertArticle = static function (int $entityId, string $locale, string $slug, string $status = 'published', string $type = 'facility') use ($pdo, $normalized, $revision): int {
    $data = $normalized + ['entity_type' => $type,'entity_id' => $entityId,'language_code' => $locale,'status' => $status,
        'source_revision' => $revision,'source_snapshot_json' => '{"PRIVATE_SNAPSHOT":true}', 'ai_writer_claim_json' => '{"PRIVATE_CLAIM":true}',
        'ai_content_json' => '{"PRIVATE_IDEMPOTENCY":true}','source_review_count' => 81,'source_rating' => 4.7,'source_rating_basis' => 'external_aggregates','published_at' => '2026-10-22 12:00:00'];
    $data['slug'] = $slug;
    foreach ($data as $key => $value) if (str_ends_with($key, '_json') && is_array($value)) $data[$key] = medical_review_articles_json_encode($value);
    $fields = array_keys($data);
    $statement = $pdo->prepare('INSERT INTO medical_review_articles (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')');
    $statement->execute(array_values($data)); return (int) $pdo->lastInsertId();
};
$insertArticle(1, 'vi', 'review-nha-khoa-a');
$insertArticle(2, 'en', 'review-dental-a');
$insertArticle(3, 'vi', 'review-nha-khoa-b', 'draft');
$insertArticle(4, 'vi', 'review-co-so-nhap');
$insertArticle(999, 'vi', 'review-mo-coi');
$insertArticle(1, 'en', 'wrong-language');
$insertArticle(1, 'vi', 'future-doctor', 'published', 'doctor');
try { $insertArticle(1,'vi','duplicate'); $duplicateBlocked = false; } catch (PDOException) { $duplicateBlocked = true; }
$assert($duplicateBlocked, 'One article per entity and locale, no duplicate article on retry');
$page = medical_review_articles_directory_page($pdo, 'vi');
$assert($page['total'] === 1 && $page['published_total'] === 1, 'Directory excludes drafts, unpublished facilities, orphans, doctors and wrong locale');
$assert($page['items'][0]['facility']['url'] === '/co-so-y-te/nha-khoa-a', 'Article links directly to canonical facility');
$assert($page['items'][0]['url'] === '/review/review-nha-khoa-a', 'Public article route canonical');
$assert($page['items'][0]['cover_image_url'] === '/uploads/facility-a.webp', 'Cover derived from facility image, never patient images');
$assert($page['items'][0]['reading_minutes'] >= 1, 'Reading time derived from article body');
$assert(!str_contains(json_encode($page), 'PRIVATE_'), 'All private snapshots, claims and submission metadata absent from directory DTO');
$assert($page['facets'] === ['cities' => ['Đà Nẵng'],'categories' => ['Nha khoa']], 'Facets scoped to published locale articles');
$assert(medical_review_articles_directory_page($pdo, 'en')['total'] === 1, 'English articles form independent stream');
$assert(medical_review_articles_directory_page($pdo, 'vi', ['q' => '%'])['total'] === 0, 'Percent treated literally in search, not wildcard');
$assert(medical_review_articles_directory_page($pdo, 'vi', ['q' => "' OR 1=1 --"])['total'] === 0, 'Search bound safely');
$assert(medical_review_articles_directory_page($pdo, 'vi', ['q' => 'Nha khoa A'])['total'] === 1, 'Facility and article search works');
$assert(medical_review_articles_directory_page($pdo, 'vi', ['city' => 'Huế'])['total'] === 0, 'City filter exact');
$assert(medical_review_articles_directory_page($pdo, 'vi', [], 999, 1000)['page'] === 1, 'Page and per-page bounded');
$public = medical_review_articles_find_public($pdo, 'review-nha-khoa-a', 'vi');
$assert($public !== null && $public['alternates'] == ['vi' => '/review/review-nha-khoa-a','en' => '/en/review/review-dental-a'], 'Only real published translated facility article alternatives linked');
$pdo->exec("UPDATE medical_facilities SET translation_of_id=0 WHERE id IN (1,3)");
$pdo->exec("UPDATE medical_review_articles SET status='published' WHERE entity_type='facility' AND entity_id=3 AND language_code='vi'");
$zeroParent = medical_review_articles_find_public($pdo, 'review-nha-khoa-a', 'vi');
$assert($zeroParent['alternates'] == ['vi' => '/review/review-nha-khoa-a','en' => '/en/review/review-dental-a'], 'Legacy zero parent cannot attach unrelated facility article alternatives');
$pdo->exec("UPDATE medical_review_articles SET status='draft' WHERE entity_type='facility' AND entity_id=3 AND language_code='vi'");
$assert(medical_review_articles_find_public($pdo, 'review-nha-khoa-a', 'en') === null, 'Detail cannot expose wrong language article');
$assert(medical_review_articles_find_public($pdo, 'review-nha-khoa-b', 'vi') === null, 'Draft article not visible directly');
$assert(medical_review_articles_find_public($pdo, 'future-doctor', 'vi') === null, 'Future doctor storage not publicly implemented prematurely');
$pdo->exec("UPDATE medical_review_articles SET content_html='<p onclick=\"bad()\">Nội dung<script>secret()</script></p>' WHERE entity_id=1 AND language_code='vi' AND entity_type='facility'");
$public = medical_review_articles_find_public($pdo, 'review-nha-khoa-a', 'vi');
$assert(!str_contains($public['content_html'], 'onclick') && !str_contains($public['content_html'], '<script'), 'Direct-DB legacy HTML sanitized again at public boundary');
$unsafeDto = medical_review_articles_public_dto(['sources_json' => [['id' => 'bad','url' => 'javascript:bad()'],['id' => 'ok','url' => 'https://clinic.example/about','title' => '<b>Website</b>','patient_images_json' => ['SECRET_IMAGE']]],
    'strengths_json' => ['<script>Text</script>', ['PRIVATE_BAD_SHAPE']],
    'analysis_sections_json' => [['id' => 'section','title' => '<b>Title</b>','content_html' => '<p onclick="bad()">Content</p>','source_ids' => ['ok','bad']]],
    'source_snapshot_json' => '{"PRIVATE_SNAPSHOT":true}']);
$assert(count($unsafeDto['sources_json']) === 1 && $unsafeDto['sources_json'][0]['id'] === 'ok', 'Public sources filter unsafe legacy URLs');
$assert(!str_contains(json_encode($unsafeDto), 'PRIVATE_') && !str_contains(json_encode($unsafeDto), 'SECRET_'), 'Public nested shapes omit arbitrary legacy/private metadata');
$assert($unsafeDto['analysis_sections_json'][0]['source_ids'] === ['ok'] && $unsafeDto['sources_json'][0]['title'] === 'Website', 'Public section references and source titles safely projected');
$assert(medical_review_articles_path('en','slug') === '/en/review/slug' && medical_review_articles_path('vi') === '/review', 'Canonical locale routes');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_reviews')->fetchColumn() === 49, 'All operations preserve individual reviews');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === 4, 'No implicit facility creation');
echo 'PASS review_articles_test (' . $checks . " checks)\n";
