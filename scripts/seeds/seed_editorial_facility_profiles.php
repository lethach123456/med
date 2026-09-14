<?php
declare(strict_types=1);

/**
 * Creates transparent editorial information entries for imported facilities.
 * These records are deliberately not customer reviews: rating is 0 and the
 * content identifies its source and editorial status.
 * Run with: php seed_editorial_facility_profiles.php
 */
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/medical_directory.php';

$facilitySlugs = [
    'vinmec-times-city', 'tam-anh-ha-noi', 'tam-anh-ho-chi-minh', 'tam-anh-quan-8',
    'fv-hospital', 'hoan-my-sai-gon', 'city-international-hospital',
    'hanh-phuc-international-hospital', 'university-medical-center-hcmc',
    'central-military-hospital-108',
];

$pdo = db();
medical_directory_ensure_tables($pdo);

$placeholders = implode(', ', array_fill(0, count($facilitySlugs), '?'));
$facilityStmt = $pdo->prepare(
    "SELECT slug, name, city, address_text, phone_text, website_url, hours_text, featured_services_json
     FROM medical_facilities WHERE slug IN ({$placeholders})"
);
$facilityStmt->execute($facilitySlugs);
$facilities = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);

$save = $pdo->prepare(
    'INSERT INTO medical_reviews (
        slug, facility_slug, facility_name, title, verified, rating, author_text, location_text,
        review_date_text, price_text, excerpt, likes_count, comments_count, shares_count,
        service_text, method_text, duration_text, condition_text, story_json, timeline_json,
        thumbs_json, process_before_json, process_during_json, process_after_json, status, display_order
    ) VALUES (
        :slug, :facility_slug, :facility_name, :title, 0, 0, "Ban biên tập MedReview", :location,
        "Cập nhật từ website chính thức", "Liên hệ trực tiếp cơ sở", :excerpt, 0, 0, 0,
        :service, "Thông tin tổng hợp", "Không áp dụng", "Nội dung biên tập, không phải đánh giá khách hàng",
        :story, :timeline, "[]", "[]", "[]", "[]", "published", :display_order
    ) ON DUPLICATE KEY UPDATE
        facility_name = VALUES(facility_name), title = VALUES(title), location_text = VALUES(location_text),
        excerpt = VALUES(excerpt), service_text = VALUES(service_text), story_json = VALUES(story_json),
        timeline_json = VALUES(timeline_json), display_order = VALUES(display_order), status = VALUES(status)'
);

$saved = 0;
foreach ($facilities as $facilityIndex => $facility) {
    $name = trim((string) $facility['name']);
    $city = trim((string) $facility['city']);
    $address = trim((string) $facility['address_text']);
    $phone = trim((string) $facility['phone_text']);
    $website = trim((string) $facility['website_url']);
    $hours = trim((string) $facility['hours_text']);
    $services = medical_directory_json_decode((string) $facility['featured_services_json'], []);
    $serviceList = $services === [] ? 'các dịch vụ khám chữa bệnh' : implode(', ', $services);

    $entries = [
        [
            'suffix' => 'thong-tin-lien-he',
            'title' => 'Thông tin liên hệ và đặt lịch — ' . $name,
            'service' => 'Thông tin liên hệ',
            'excerpt' => "Đây là thông tin biên tập từ kênh chính thức của {$name}; không phải nhận xét của khách hàng.",
            'story' => [
                "Địa chỉ được công bố: {$address}.",
                $phone !== '' ? "Số điện thoại liên hệ: {$phone}." : 'Số điện thoại chưa được cập nhật trong hồ sơ này.',
                $website !== '' ? "Thông tin và đặt hẹn nên được xác nhận tại {$website}." : 'Người bệnh nên liên hệ trực tiếp cơ sở để xác nhận thông tin.',
            ],
        ],
        [
            'suffix' => 'dich-vu',
            'title' => 'Dịch vụ và chuyên khoa — ' . $name,
            'service' => 'Thông tin dịch vụ',
            'excerpt' => "Hồ sơ biên tập tổng hợp các nhóm dịch vụ được cơ sở công bố, không thay thế tư vấn y khoa cá nhân.",
            'story' => [
                "Các nhóm dịch vụ được ghi nhận trong hồ sơ: {$serviceList}.",
                'Tình trạng tiếp nhận, bác sĩ phụ trách và chỉ định điều trị có thể thay đổi theo thời điểm.',
                'Người bệnh nên đặt lịch hoặc trao đổi với cơ sở trước khi đến để được hướng dẫn phù hợp.',
            ],
        ],
        [
            'suffix' => 'luu-y-kham',
            'title' => 'Lưu ý trước khi đến khám — ' . $name,
            'service' => 'Hướng dẫn trước khám',
            'excerpt' => "Nội dung hướng dẫn do Ban biên tập tổng hợp; không phải review hoặc xếp hạng chất lượng dịch vụ.",
            'story' => [
                $hours !== '' ? "Khung giờ được công bố: {$hours}." : 'Giờ khám cần được xác nhận trực tiếp với cơ sở.',
                'Hãy mang theo giấy tờ tùy thân, hồ sơ bệnh án và kết quả xét nghiệm/chẩn đoán trước đó nếu có.',
                'Chi phí, điều kiện bảo hiểm và thời gian chờ cần được cơ sở tư vấn trực tiếp trước khi sử dụng dịch vụ.',
            ],
        ],
    ];

    foreach ($entries as $entryIndex => $entry) {
        $save->execute([
            ':slug' => $facility['slug'] . '-' . $entry['suffix'],
            ':facility_slug' => $facility['slug'],
            ':facility_name' => $name,
            ':title' => $entry['title'],
            ':location' => $city,
            ':excerpt' => $entry['excerpt'],
            ':service' => $entry['service'],
            ':story' => medical_directory_json_encode($entry['story']),
            ':timeline' => medical_directory_json_encode([
                ['date' => 'Nguồn', 'title' => 'Website chính thức', 'text' => $website !== '' ? $website : 'Liên hệ trực tiếp cơ sở'],
            ]),
            ':display_order' => 500 + ($facilityIndex * 10) + $entryIndex,
        ]);
        $saved++;
    }
    medical_directory_refresh_facility_aggregates($pdo, (string) $facility['slug']);
}

echo "Đã thêm hoặc cập nhật {$saved} hồ sơ thông tin biên tập cho " . count($facilities) . " cơ sở.\n";
