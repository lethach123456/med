<?php
declare(strict_types=1);

/**
 * Adds public, source-verified facility contact profiles.
 * Run with: php seed_verified_facilities.php
 */
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/medical_directory.php';

$facilities = [
    ['slug' => 'vinmec-times-city', 'name' => 'Bệnh viện Đa khoa Quốc tế Vinmec Times City', 'city' => 'Hà Nội', 'address' => '458 Minh Khai, phường Vĩnh Tuy, thành phố Hà Nội', 'phone' => '024 3974 3556', 'website' => 'https://www.vinmec.com/', 'hours' => 'Thứ 2 - Thứ 6: 08:00 - 17:00; Thứ 7: 08:00 - 17:00', 'services' => ['Khám đa khoa', 'Cấp cứu', 'Chẩn đoán hình ảnh'], 'tags' => ['Bệnh viện đa khoa', 'Hà Nội', 'Đặt lịch trực tuyến']],
    ['slug' => 'tam-anh-ha-noi', 'name' => 'Bệnh viện Đa khoa Tâm Anh Hà Nội', 'city' => 'Hà Nội', 'address' => '108 Phố Hoàng Như Tiếp, phường Bồ Đề, thành phố Hà Nội', 'phone' => '024 7106 6858', 'website' => 'https://tamanhhospital.vn/', 'hours' => 'Thứ 2 - Thứ 7: 07:30 - 16:30; Cấp cứu 24/7', 'services' => ['Khám đa khoa', 'Khám ngoài giờ', 'Cấp cứu'], 'tags' => ['Bệnh viện đa khoa', 'Hà Nội', 'Cấp cứu 24/7']],
    ['slug' => 'tam-anh-ho-chi-minh', 'name' => 'Bệnh viện Đa khoa Tâm Anh TP.HCM', 'city' => 'TP. Hồ Chí Minh', 'address' => '2B Phổ Quang, phường Tân Sơn Hòa, TP. Hồ Chí Minh', 'phone' => '028 7102 6789', 'website' => 'https://tamanhhospital.vn/', 'hours' => 'Thứ 2 - Thứ 7: 06:00 - 16:00; Cấp cứu 24/7', 'services' => ['Khám đa khoa', 'Khám ngoài giờ', 'Cấp cứu'], 'tags' => ['Bệnh viện đa khoa', 'TP. Hồ Chí Minh', 'Cấp cứu 24/7']],
    ['slug' => 'tam-anh-quan-8', 'name' => 'Bệnh viện Đa khoa Tâm Anh Quận 8', 'city' => 'TP. Hồ Chí Minh', 'address' => '316C Phạm Hùng, phường Chánh Hưng, TP. Hồ Chí Minh', 'phone' => '028 7102 6789', 'website' => 'https://tamanhhospital.vn/', 'hours' => 'Thứ 2 - Thứ 7: 07:00 - 16:00; Cấp cứu 24/7', 'services' => ['Khám đa khoa', 'Khám ngoài giờ', 'Cấp cứu'], 'tags' => ['Bệnh viện đa khoa', 'TP. Hồ Chí Minh', 'Cấp cứu 24/7']],
    ['slug' => 'fv-hospital', 'name' => 'Bệnh viện FV', 'city' => 'TP. Hồ Chí Minh', 'address' => '6 Nguyễn Lương Bằng, phường Tân Mỹ, TP. Hồ Chí Minh', 'phone' => '028 3511 3333', 'website' => 'https://www.fvhospital.com/', 'hours' => 'Thứ 2 - Thứ 6: 08:00 - 17:00; Thứ 7: 08:00 - 12:00', 'services' => ['Khám đa khoa', 'Tim mạch', 'Cấp cứu'], 'tags' => ['Bệnh viện đa khoa', 'TP. Hồ Chí Minh', 'Đặt hẹn']],
    ['slug' => 'hoan-my-sai-gon', 'name' => 'Bệnh viện Hoàn Mỹ Sài Gòn', 'city' => 'TP. Hồ Chí Minh', 'address' => '60-60A Phan Xích Long, phường Cầu Kiệu, TP. Hồ Chí Minh', 'phone' => '028 3990 2468', 'website' => 'https://hoanmy.com/saigon/', 'hours' => 'Mở cửa 24 giờ', 'services' => ['Ung bướu', 'Tim mạch', 'Tiêu hóa'], 'tags' => ['Bệnh viện đa khoa', 'TP. Hồ Chí Minh', 'Mở cửa 24 giờ']],
    ['slug' => 'city-international-hospital', 'name' => 'Bệnh viện Quốc tế City', 'city' => 'TP. Hồ Chí Minh', 'address' => '3 Đường 17A, phường An Lạc, TP. Hồ Chí Minh', 'phone' => '', 'website' => 'https://cih.com.vn/', 'hours' => 'Liên hệ bệnh viện để cập nhật lịch khám', 'services' => ['Khám đa khoa', 'Sản phụ khoa', 'Khám chuyên khoa'], 'tags' => ['Bệnh viện đa khoa', 'TP. Hồ Chí Minh']],
    ['slug' => 'hanh-phuc-international-hospital', 'name' => 'Bệnh viện Đa khoa Quốc tế Hạnh Phúc', 'city' => 'TP. Hồ Chí Minh', 'address' => '18 Đại lộ Bình Dương, phường Bình Hòa, TP. Hồ Chí Minh', 'phone' => '1900 6765', 'website' => 'https://www.hanhphuchospital.com/', 'hours' => 'Thứ Hai - Thứ Bảy: 07:30 - 16:30; Cấp cứu 24/7', 'services' => ['Khám đa khoa', 'Sản phụ khoa', 'Hỗ trợ sinh sản'], 'tags' => ['Bệnh viện đa khoa', 'TP. Hồ Chí Minh', 'Cấp cứu 24/7']],
    ['slug' => 'university-medical-center-hcmc', 'name' => 'Bệnh viện Đại học Y Dược TP. Hồ Chí Minh', 'city' => 'TP. Hồ Chí Minh', 'address' => '215 Hồng Bàng, phường Chợ Lớn, TP. Hồ Chí Minh', 'phone' => '028 3855 4269', 'website' => 'https://bvdaihoc.com.vn/', 'hours' => 'Thứ 2 - Thứ 6: 06:30 - 16:30; Thứ 7: 06:30 - 12:00', 'services' => ['Khám đa khoa', 'Khám chuyên khoa', 'Cấp cứu'], 'tags' => ['Bệnh viện đại học', 'TP. Hồ Chí Minh', 'BHYT']],
    ['slug' => 'central-military-hospital-108', 'name' => 'Bệnh viện Trung ương Quân đội 108', 'city' => 'Hà Nội', 'address' => '1 Trần Hưng Đạo, phường Hai Bà Trưng, Hà Nội', 'phone' => '', 'website' => 'https://benhvien108.vn/', 'hours' => 'Liên hệ bệnh viện để cập nhật lịch khám', 'services' => ['Khám đa khoa', 'Khám chuyên sâu', 'Cấp cứu'], 'tags' => ['Bệnh viện đa khoa', 'Hà Nội', 'Tuyến cuối']],
];

$pdo = db();
medical_directory_ensure_tables($pdo);

$statement = $pdo->prepare(
    'INSERT INTO medical_facilities (
        slug, name, category, city, subtitle, verified, rating, reviews_count, followers_count,
        hours_text, address_text, phone_text, website_url, price_text, image_url, images_label,
        featured_services_json, tags_json, gallery_json, intro_json, utilities_json, status, display_order
    ) VALUES (
        :slug, :name, :category, :city, :subtitle, 0, 0, 0, 0,
        :hours, :address, :phone, :website, :price, :image, :images_label,
        :services, :tags, :gallery, :intro, :utilities, "published", :display_order
    ) ON DUPLICATE KEY UPDATE
        name = VALUES(name), category = VALUES(category), city = VALUES(city), subtitle = VALUES(subtitle),
        hours_text = VALUES(hours_text), address_text = VALUES(address_text), phone_text = VALUES(phone_text),
        website_url = VALUES(website_url), featured_services_json = VALUES(featured_services_json),
        tags_json = VALUES(tags_json), intro_json = VALUES(intro_json), utilities_json = VALUES(utilities_json),
        status = VALUES(status), display_order = VALUES(display_order)'
);

foreach ($facilities as $index => $facility) {
    $intro = [
        $facility['name'] . ' là cơ sở y tế đang hoạt động tại ' . $facility['city'] . '.',
        'Thông tin liên hệ và lịch khám nên được xác nhận trực tiếp trên website hoặc tổng đài của cơ sở trước khi đến khám.',
    ];
    $statement->execute([
        ':slug' => $facility['slug'],
        ':name' => $facility['name'],
        ':category' => 'Bệnh viện đa khoa',
        ':city' => $facility['city'],
        ':subtitle' => 'Thông tin liên hệ công khai; vui lòng xác nhận lịch khám trước khi đến.',
        ':hours' => $facility['hours'],
        ':address' => $facility['address'],
        ':phone' => $facility['phone'],
        ':website' => $facility['website'],
        ':price' => 'Liên hệ để được tư vấn chi phí',
        ':image' => 'https://images.unsplash.com/photo-1586773860418-d37222d8fce3?auto=format&fit=crop&w=1400&q=80',
        ':images_label' => 'Hình minh họa',
        ':services' => medical_directory_json_encode($facility['services']),
        ':tags' => medical_directory_json_encode($facility['tags']),
        ':gallery' => medical_directory_json_encode([]),
        ':intro' => medical_directory_json_encode($intro),
        ':utilities' => medical_directory_json_encode(['Đặt lịch qua website hoặc tổng đài']),
        ':display_order' => 100 + $index,
    ]);
}

medical_search_cache_invalidate();
echo 'Đã thêm hoặc cập nhật ' . count($facilities) . " cơ sở y tế.\n";
