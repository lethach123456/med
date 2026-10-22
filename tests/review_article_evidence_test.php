<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Pure in-memory fixtures. No PDO connection, network, application configuration or writes.
require_once dirname(__DIR__) . '/medical_review_article_evidence.php';

function review_article_evidence_fixture(): array
{
    return [
        'entity_type' => 'facility', 'entity_id' => 393, 'source_revision' => str_repeat('a', 64),
        'facility' => [
            'id' => 393, 'translation_of_id' => 392, 'slug' => 'nha-khoa-thu-nghiem', 'name' => 'Nha khoa Thử nghiệm',
            'category' => 'Nha khoa', 'city' => 'Đà Nẵng', 'subtitle' => 'Khám và tư vấn nha khoa',
            'address_text' => '12 Đường A', 'phone_text' => '02361234567', 'website_url' => 'https://clinic.example.org/about',
            'google_maps_url' => 'https://maps.google.com/?cid=123', 'hours_text' => 'Thứ hai đến thứ bảy',
            'price_text' => 'Liên hệ để được báo giá', 'featured_services_json' => [['name' => 'Niềng răng']],
            'services_json' => [['name' => 'Khám tổng quát', 'patient_images_notes' => 'PRIVATE_SERVICE']],
            'doctors_json' => [['name' => 'Bác sĩ A', 'title' => 'Bác sĩ', 'claim_token' => 'PRIVATE_DOCTOR_TOKEN']],
            'equipment_mentioned_json' => ['Máy chụp phim'], 'established_year' => 2015,
            'parking_info' => 'Có khu vực để xe', 'content' => '<p>Thông tin giới thiệu công khai.</p>',
            'insurance_accepted_json' => [], 'business_license' => '',
            'rating' => 4.9, 'reviews_count' => 5000, 'aggregate_ratings_json' => [['source' => 'example', 'score' => 5, 'count' => 5000]],
            'patient_images_json' => ['PRIVATE_PATIENT_IMAGE'], 'patient_images_notes' => 'PRIVATE_PATIENT_NOTE',
            'full_json' => 'PRIVATE_FULL_JSON', 'ai_writer_claim_json' => 'PRIVATE_CLAIM_TOKEN',
        ],
        'review_metrics' => ['rating' => 4.9, 'reviews_count' => 5000, 'basis' => 'external_aggregates'], 'sample_count' => 5,
        'review_samples' => [
            ['excerpt' => 'Nhân viên hướng dẫn lịch hẹn rõ ràng.', 'author_text' => 'PRIVATE_AUTHOR', 'rating' => 5],
            ['excerpt' => '  NHÂN VIÊN&nbsp;hướng dẫn lịch hẹn rõ ràng! ', 'rating' => 4],
            ['excerpt' => '<p>Khu vực chờ sạch sẽ và có chỗ ngồi.</p>', 'rating' => 5],
            ['excerpt' => '   '], ['excerpt' => '<p>&nbsp;</p>'], ['rating' => 5],
            ['excerpt' => 123], ['excerpt' => 'Có ý kiến muốn được thông báo chi phí rõ hơn.'],
        ],
        'stored_insights_valid' => true,
        'facility_insights_json' => (object) [
            'schema_version' => 1, 'summary' => 'Thông tin hiện có mô tả khu vực chờ và một số dịch vụ công khai.',
            'sources' => [(object) ['id' => 'web', 'url' => 'https://clinic.example.org/about'], (object) ['id' => 'social', 'url' => 'https://facebook.com/clinic']],
            'findings' => [
                (object) ['topic' => 'services', 'summary' => 'Cơ sở công bố dịch vụ khám tổng quát.', 'source_ids' => ['web'], 'evidence_type' => 'official_statement'],
                (object) ['topic' => 'reception', 'summary' => 'Phản hồi công khai đề cập việc hướng dẫn lịch hẹn.', 'source_ids' => ['social'], 'evidence_type' => 'public_feedback'],
                (object) ['topic' => 'facilities', 'summary' => 'Thông tin công khai từ các nguồn mô tả khu vực chờ.', 'source_ids' => ['web', 'social'], 'evidence_type' => 'cross_checked'],
                (object) ['topic' => 'costs', 'summary' => 'Thông tin về chi phí chưa được đối chiếu.', 'source_ids' => ['web']],
            ],
            'patient_images_notes' => 'PRIVATE_INSIGHT_NOTE', 'claim_token' => 'PRIVATE_INSIGHT_TOKEN',
        ],
    ];
}

function review_article_evidence_claim(array $replace = []): array
{
    return array_replace(['id' => 'c1', 'topic' => 'services', 'statement' => 'Cơ sở có thông tin công khai về dịch vụ khám tổng quát.',
        'evidence_refs' => ['p-name', 'p-services'], 'feedback_scope' => 'none', 'opposing_refs' => [], 'disposition' => 'use'], $replace);
}

function review_article_evidence_ledger(array $claims): array { return ['schema_version' => 1, 'claims' => $claims]; }

$checks = 0;
$assert = static function (bool $value, string $message) use (&$checks): void { ++$checks; if (!$value) throw new RuntimeException($message); };
$rejects = static function (callable $call, string $message) use ($assert): void {
    try { $call(); } catch (InvalidArgumentException) { $assert(true, $message); return; }
    throw new RuntimeException('Expected InvalidArgumentException: ' . $message);
};

$source = review_article_evidence_fixture();
$before = serialize($source);
$catalog = medical_review_article_evidence_catalog($source);
$assert($catalog['schema_version'] === 1 && array_is_list($catalog['items']), 'Versioned catalog list');
$byId = array_column($catalog['items'], null, 'id');
$assert($byId['p-name'] === ['id' => 'p-name', 'kind' => 'profile', 'path' => 'facility.name', 'evidence_type' => 'official_statement'], 'Exact stable profile path');
$assert($byId['r-1']['path'] === 'review_samples.0.excerpt' && $byId['r-3']['path'] === 'review_samples.2.excerpt', 'Direct feedback refers to original array index');
$assert($byId['r-8']['path'] === 'review_samples.7.excerpt', 'Skipped blank entries do not renumber references');
foreach (['r-4', 'r-5', 'r-6', 'r-7', 'p-rating', 'p-reviews-count', 'p-id', 'p-translation-of-id', 'p-business-license', 'p-insurance'] as $absent) $assert(!isset($byId[$absent]), 'No non-evidence reference ' . $absent);
$assert($byId['i-summary']['path'] === 'facility_insights_json.summary' && $byId['i-summary']['evidence_type'] === 'unverified', 'Summary is always unverified');
$assert($byId['i-1']['evidence_type'] === 'official_statement' && $byId['i-2']['evidence_type'] === 'public_feedback' && $byId['i-3']['evidence_type'] === 'cross_checked' && $byId['i-4']['evidence_type'] === 'unverified', 'Validated finding evidence types retained, missing type unverified');
$assert($byId['i-3']['path'] === 'facility_insights_json.findings.2.summary', 'Finding path points at its summary');
foreach ($catalog['items'] as $item) $assert(array_keys($item) === ['id', 'kind', 'path', 'evidence_type'], 'Catalog contains references only, not values');
$catalogJson = medical_review_articles_json_encode($catalog);
foreach (['PRIVATE_', 'Nha khoa Thử nghiệm', '02361234567', 'patient_', 'token', 'full_json', 'review_metrics', 'sample_count', 'rating', 'reviews_count', 'translation_of_id', 'source_revision', 'aggregate_ratings'] as $private) $assert(!str_contains($catalogJson, $private), 'Catalog does not copy private or metric value ' . $private);
$assert(medical_review_article_evidence_catalog($source) === $catalog && serialize($source) === $before, 'Catalog is stable and never mutates source');
$rawProfile = $source; $rawProfile['facility']['services_json'] = ['claim_token' => 'PRIVATE_ONLY'];
$assert(!in_array('p-services', array_column(medical_review_article_evidence_catalog($rawProfile)['items'], 'id'), true), 'Known nested field with private keys only is not evidence');
$badInsights = $source; $badInsights['facility_insights_json']->findings[0]->source_ids = ['missing'];
$assert(!in_array('i-summary', array_column(medical_review_article_evidence_catalog($badInsights)['items'], 'id'), true), 'Malformed insights are not cataloged');
$source = review_article_evidence_fixture();
$badInsights = $source; $badInsights['stored_insights_valid'] = false;
$assert(!in_array('i-1', array_column(medical_review_article_evidence_catalog($badInsights)['items'], 'id'), true), 'Explicit invalid-insights marker is honored');
$summaryOnly = ['facility_insights_json' => (object) ['summary' => 'Một ghi chú tóm tắt chưa có nguồn xác minh.']];
$assert(medical_review_article_evidence_catalog($summaryOnly)['items'] === [['id' => 'i-summary', 'kind' => 'insight', 'path' => 'facility_insights_json.summary', 'evidence_type' => 'unverified']], 'Valid legacy summary is a qualified-only reference');
$assert(medical_review_article_evidence_catalog([]) === ['schema_version' => 1, 'items' => []], 'Empty source produces no fabricated references');
$practicalSource = review_article_evidence_fixture();
$practicalSource['facility'] += [
    'price_table_html' => '<table><tr><td>Khám</td><td>200000 VND/lượt</td></tr></table>',
    'price_source_scope' => 'branch_reference', 'nearby_landmarks' => 'Gần công viên',
    'booking_url' => 'https://clinic.example.org/booking', 'utilities_json' => ['Chỗ đỗ xe'],
    'latitude' => 16.047079, 'longitude' => 108.20623, 'branch_count' => 2,
];
$practicalCatalog = array_column(medical_review_article_evidence_catalog($practicalSource)['items'], null, 'id');
foreach (['p-price-table' => 'price_table_html', 'p-price-scope' => 'price_source_scope', 'p-landmarks' => 'nearby_landmarks',
    'p-booking' => 'booking_url', 'p-utilities' => 'utilities_json', 'p-latitude' => 'latitude', 'p-longitude' => 'longitude', 'p-branches' => 'branch_count'] as $ref => $field) {
    $assert(($practicalCatalog[$ref]['path'] ?? '') === 'facility.' . $field && $practicalCatalog[$ref]['kind'] === 'profile', 'Practical evidence links the real source field ' . $field);
}
$invalidCoordinates = $practicalSource;
$invalidCoordinates['facility']['latitude'] = 91; $invalidCoordinates['facility']['longitude'] = INF; $invalidCoordinates['facility']['branch_count'] = 0;
$invalidCatalogIds = array_column(medical_review_article_evidence_catalog($invalidCoordinates)['items'], 'id');
$assert(!in_array('p-latitude', $invalidCatalogIds, true) && !in_array('p-longitude', $invalidCatalogIds, true) && !in_array('p-branches', $invalidCatalogIds, true), 'Invalid coordinates and branch counts are not evidence');

$legacy = ['stored_insights_valid' => true, 'facility_insights_json' => (object) [
    'services' => ['Niềng răng', 'Khám răng tổng quát'],
    'equipment_seen' => [(object) ['name' => 'Máy chụp phim CBCT', 'confidence' => 'high', 'count' => 2]],
    'premises' => (object) ['reception' => (object) ['description' => 'Khu vực lễ tân có bàn tiếp đón và chỗ ngồi.']],
    'staff_signals' => 'Có ảnh đội ngũ làm việc tại phòng khám.',
    'price_signals' => (object) ['text' => 'Bài đăng có nêu mức phí khám 200.000 VND/lượt.', 'price' => 200000],
    'activity' => (object) ['description' => 'Trang có cập nhật thông tin lịch làm việc.'],
    'comment_signals' => (object) ['themes' => [(object) ['text' => 'Có bình luận đề cập việc hướng dẫn lịch hẹn.']], 'count' => 4],
    'data_conflicts' => (object) ['details' => 'Hai bài đăng có cách mô tả lịch hẹn chưa thống nhất.'],
    'dominant_content' => 'Nội dung chủ yếu giới thiệu dịch vụ và khu vực điều trị.',
    'coverage' => ['description' => 'PRIVATE_COVERAGE_METADATA'], 'scores' => ['description' => 'PRIVATE_SCORE_METADATA'],
    'patient_images_json' => ['PRIVATE_PATIENT_MEDIA'], 'media' => ['description' => 'PRIVATE_MEDIA_METADATA'],
]];
$legacyBefore = serialize($legacy);
$legacyItems = array_column(medical_review_article_evidence_catalog($legacy)['items'], null, 'id');
$assert(count($legacyItems) === 9, 'Only the nine known legacy scan sections can add references');
foreach (medical_review_article_evidence_legacy_insight_fields() as $field => $ref) {
    $assert(($legacyItems[$ref] ?? null) === ['id' => $ref, 'kind' => 'insight', 'path' => 'facility_insights_json.' . $field, 'evidence_type' => 'unverified'], 'Legacy scan text has a stable qualified-only field reference: ' . $field);
    $rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => [$ref]])]), $legacy), 'Legacy scan insight cannot be used as verified direct support: ' . $field);
    foreach (['qualify','omit'] as $disposition) {
        $qualifiedLegacy = review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => [$ref], 'disposition' => $disposition])]);
        $assert(medical_review_article_evidence_validate($qualifiedLegacy, $legacy) === $qualifiedLegacy, 'Legacy scan insight can be qualified or omitted: ' . $field);
    }
}
$legacyJson = medical_review_articles_json_encode(array_values($legacyItems));
foreach (['PRIVATE_', 'CBCT', '200.000', 'Niềng răng', 'Khu vực lễ tân', 'coverage', 'patient_images', 'media', 'scores'] as $value) $assert(!str_contains($legacyJson, $value), 'Legacy catalog never copies values or creates private/metric references: ' . $value);
$assert(array_values($legacyItems) === medical_review_article_evidence_catalog($legacy)['items'] && serialize($legacy) === $legacyBefore, 'Legacy scan catalog is stable and never mutates source');
$noFacts = [null, false, true, 0, 4.9, '123', '99%', '2026-10-23', '2026-10-23T10:30:00Z', 'https://example.org/image.jpg',
    'https://example.org/a?title=waiting-room', 'data:image/png;base64,AAAA', 'blob:https://example.org/id', '//example.org/image.jpg',
    'example.org/image.jpg', 'id-393', '550e8400-e29b-41d4-a716-446655440000', str_repeat('a', 64), 'unknown', 'published',
    [], new stdClass(), "\u{200B}&nbsp;", '<script>PRIVATE_CODE_ONLY</script>',
    (object) ['id' => 'PRIVATE_ID_ONLY', 'url' => 'https://example.org/image.jpg', 'source_ids' => ['s1'],
        'updated_at' => '2026-10-23T00:00:00Z', 'confidence' => 'PRIVATE_CONFIDENCE_METADATA', 'status' => 'PRIVATE_STATUS_METADATA',
        'counts' => ['notes' => 'PRIVATE_COUNT_METADATA'], 'coverage' => ['description' => 'PRIVATE_COVERAGE_METADATA'],
        'photo' => ['description' => 'PRIVATE_PHOTO_METADATA'], 'assets' => ['description' => 'PRIVATE_ASSET_METADATA'],
        'filename' => 'PRIVATE_FILE_NAME.webp', 'base64' => 'UklGRPRIVATE_IMAGE_BYTES',
        'patient_images_notes' => 'PRIVATE_PATIENT_NOTE', 'api_token' => 'PRIVATE_TOKEN'],
];
foreach ($noFacts as $index => $value) {
    $metadataOnly = ['facility_insights_json' => (object) ['equipment_seen' => $value]];
    $assert(medical_review_article_evidence_catalog($metadataOnly)['items'] === [], 'Empty/numeric/link/ID/metadata-only legacy section is not evidence: ' . $index);
}
$tooDeep = 'Nội dung quá sâu không được duyệt làm căn cứ.';
for ($depth = 0; $depth < 7; ++$depth) $tooDeep = (object) ['details' => $tooDeep];
$assert(medical_review_article_evidence_catalog(['facility_insights_json' => (object) ['premises' => $tooDeep]])['items'] === [], 'Legacy text-presence traversal is bounded by depth');
$tooWide = array_fill(0, 50, (object) ['id' => 'metadata']); $tooWide[] = 'Nội dung ngoài số phần tử được duyệt.';
$assert(medical_review_article_evidence_catalog(['facility_insights_json' => (object) ['services' => $tooWide]])['items'] === [], 'Legacy text-presence traversal bounds array width');
$invalidLegacy = $legacy; $invalidLegacy['stored_insights_valid'] = false;
$assert(medical_review_article_evidence_catalog($invalidLegacy)['items'] === [], 'Invalid stored-insights marker also blocks legacy references');

$empty = review_article_evidence_ledger([]);
$assert(medical_review_article_evidence_normalize($empty) === $empty && medical_review_article_evidence_validate($empty, []) === $empty, 'Empty claims are an explicit safe omission');
$ledger = review_article_evidence_ledger([review_article_evidence_claim()]);
$normalized = medical_review_article_evidence_normalize($ledger);
$assert($normalized === $ledger && medical_review_article_evidence_validate($normalized, $source) === $normalized, 'Validation preserves canonical normalized ledger for hashing');
$assert(medical_review_article_evidence_normalize(medical_review_articles_json_encode($ledger)) === $normalized, 'JSON object string normalization');
$assert(medical_review_article_evidence_normalize(json_decode(medical_review_articles_json_encode($ledger))) === $normalized, 'stdClass normalization');
$assert(medical_review_article_evidence_normalize($normalized) === $normalized, 'Repeated normalization is idempotent');
foreach ([null, false, true, 1, 1.0, [], new stdClass(), 'null', '[]', '123', '{', '{"schema_version":1,"claims":[],"extra":1}', "{\"x\":\"\xFF\"}"] as $odd) $rejects(static fn() => medical_review_article_evidence_normalize($odd), 'Invalid ledger root/type/extra field');
foreach (['1', 1.0, true, 2, null] as $version) $rejects(static fn() => medical_review_article_evidence_normalize(['schema_version' => $version, 'claims' => []]), 'Strict integer schema version');
foreach ([null, new stdClass(), ['key' => review_article_evidence_claim()], [null], [new DateTimeImmutable()]] as $claims) $rejects(static fn() => medical_review_article_evidence_normalize(['schema_version' => 1, 'claims' => $claims]), 'Claims must be a list of strict objects');
foreach (['id', 'topic', 'statement', 'evidence_refs', 'feedback_scope', 'opposing_refs', 'disposition'] as $field) {
    $claim = review_article_evidence_claim(); unset($claim[$field]);
    $rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([$claim])), 'Required claim field ' . $field);
}
$rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['unknown_nested' => ['secret' => 'never accepted']])])), 'Unknown arbitrary payload rejected');
$rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(), review_article_evidence_claim()])), 'Duplicate claim IDs rejected');
foreach (['', 'c 1', 'c.1', '1', str_repeat('c', 81), [], null, true] as $id) $rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['id' => $id])])), 'Invalid claim ID');
foreach (['', 'unknown', null, [], true] as $topic) $rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['topic' => $topic])])), 'Unknown topic');
foreach (['', '   ', '<p></p>', '<p>Nhận định</p>', '<script>alert(1)</script>Nhận định', '&nbsp;', null, [], true, "a\0b", "bad\xFF", str_repeat('á', 801)] as $statement) $rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['statement' => $statement])])), 'Invalid/empty/HTML/too long statement');
$comparison = review_article_evidence_ledger([review_article_evidence_claim(['statement' => 'Ghi chú có ký hiệu < 5 nhưng không chứa thẻ HTML.'])]);
$assert(medical_review_article_evidence_normalize($comparison) === $comparison, 'Plain comparison syntax is not blanket rejected as HTML');
$longText = review_article_evidence_ledger([review_article_evidence_claim(['statement' => str_repeat('á', 800)])]);
$assert(mb_strlen(medical_review_article_evidence_normalize($longText)['claims'][0]['statement']) === 800, 'Statement limit counts UTF-8 characters, not bytes');
foreach ([[], null, new stdClass(), ['p-name', 'p-name'], [false], ['p name'], ['key' => 'p-name']] as $refs) $rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => $refs])])), 'Invalid support references');
foreach (['single-ish', null, [], true] as $scope) $rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['feedback_scope' => $scope])])), 'Unknown feedback scope');
foreach (['publish', null, [], true] as $disposition) $rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['disposition' => $disposition])])), 'Unknown disposition');
$manyClaims = [];
for ($i = 1; $i <= 30; ++$i) $manyClaims[] = review_article_evidence_claim(['id' => 'c' . $i]);
$assert(count(medical_review_article_evidence_normalize(review_article_evidence_ledger($manyClaims))['claims']) === 30, 'Thirty claims allowed');
$manyClaims[] = review_article_evidence_claim(['id' => 'c31']);
$rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger($manyClaims)), 'More than thirty claims rejected');
$refs = array_map(static fn($number) => 'r-' . $number, range(1, 20));
$assert(count(medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => $refs])]))['claims'][0]['evidence_refs']) === 20, 'Twenty refs allowed structurally');
$rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => array_merge($refs, ['r-21'])])])), 'More than twenty support refs rejected');
$rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => $refs, 'opposing_refs' => ['p-name']])])), 'Total supporting plus opposing refs limited to twenty');
$oversized = [];
for ($i = 1; $i <= 30; ++$i) $oversized[] = review_article_evidence_claim(['id' => 'c' . $i, 'statement' => str_repeat('字', 800)]);
$rejects(static fn() => medical_review_article_evidence_normalize(review_article_evidence_ledger($oversized)), 'Canonical ledger byte bound applies to multi-byte statements');
$rejects(static fn() => medical_review_article_evidence_normalize(str_repeat(' ', 65537) . '{}'), 'Raw string byte limit checked before decode');

$rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => ['p-missing']])]), $source), 'Unknown support ref');
$rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['opposing_refs' => ['r-99'], 'disposition' => 'qualify'])]), $source), 'Unknown opposing ref');
$rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['opposing_refs' => ['p-name'], 'disposition' => 'qualify'])]), $source), 'Support and opposing refs disjoint');
$rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['opposing_refs' => ['r-8']])]), $source), 'Contradiction cannot be used without qualification');
foreach (['qualify', 'omit'] as $disposition) {
    $qualified = review_article_evidence_ledger([review_article_evidence_claim(['opposing_refs' => ['r-8'], 'disposition' => $disposition])]);
    $assert(medical_review_article_evidence_validate($qualified, $source) === $qualified, 'Contradiction may be qualified/omitted');
}
foreach (['i-summary', 'i-4'] as $ref) {
    $rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => [$ref]])]), $source), 'Unverified support cannot use directly');
    foreach (['qualify', 'omit'] as $disposition) {
        $qualified = review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => [$ref], 'disposition' => $disposition])]);
        $assert(medical_review_article_evidence_validate($qualified, $source) === $qualified, 'Unverified insight safely qualified/omitted');
    }
}
$crossChecked = review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => ['i-3']])]);
$assert(medical_review_article_evidence_validate($crossChecked, $source) === $crossChecked, 'Cross-checked label is preserved as metadata, not a truth guarantee');
$rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['feedback_scope' => 'single'])]), $source), 'Single scope requires direct feedback');
$single = review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => ['r-1'], 'feedback_scope' => 'single'])]);
$assert(medical_review_article_evidence_validate($single, $source) === $single, 'Single direct feedback ref');
$none = review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => ['p-name', 'r-1'], 'feedback_scope' => 'none'])]);
$assert(medical_review_article_evidence_validate($none, $source) === $none, 'None scope can cautiously cite feedback for a non-recurring observation');
$rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => ['r-1'], 'feedback_scope' => 'recurring'])]), $source), 'Recurring requires more than a direct feedback ref');
$rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => ['r-1', 'r-2'], 'feedback_scope' => 'recurring'])]), $source), 'Whitespace/case/HTML entities/punctuation duplicate feedback does not count twice');
$rejects(static fn() => medical_review_article_evidence_validate(review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => ['r-1', 'i-2'], 'feedback_scope' => 'recurring'])]), $source), 'Insight labeled public feedback cannot replace a direct distinct excerpt');
$recurring = review_article_evidence_ledger([review_article_evidence_claim(['evidence_refs' => ['r-1', 'r-3'], 'feedback_scope' => 'recurring'])]);
$assert(medical_review_article_evidence_validate($recurring, $source) === $recurring, 'Two distinct direct excerpts meet structural recurring requirement only');
$sameText = $source; $sameText['review_samples'][2]['excerpt'] = "<p>Nhân viên\n hướng dẫn lịch hẹn rõ ràng.</p>";
$rejects(static fn() => medical_review_article_evidence_validate($recurring, $sameText), 'Different reference IDs with duplicate text do not establish recurrence');
$assert(serialize($source) === serialize(review_article_evidence_fixture()), 'Normalization and validation never mutate the source');
echo 'Review article evidence: ' . $checks . " checks passed.\n";
