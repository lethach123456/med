<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$doctor = medical_directory_doctor_row_by_slug($slug, true);
$facility = null;
$relatedReviews = [];
$relatedDoctors = [];

if (is_array($doctor) && $doctor !== []) {
    $facilitySlug = (string) ($doctor['facility_slug'] ?? '');
    if ($facilitySlug !== '') {
        $facility = medical_directory_facility_row_by_slug($facilitySlug, true);
        $relatedReviews = array_slice(medical_directory_reviews_for_facility_slug($facilitySlug, true), 0, 4);
    }

    $relatedDoctors = array_values(array_filter(medical_directory_doctor_rows(true), static function (array $item) use ($doctor): bool {
        return (string) ($item['slug'] ?? '') !== (string) ($doctor['slug'] ?? '');
    }));
    $relatedDoctors = array_slice($relatedDoctors, 0, 3);
}

if (!is_array($doctor) || $doctor === []) {
    http_response_code(404);
    $doctor = [
        'name' => 'ThS.BS Phạm Hoàng Nam',
        'title_text' => 'Chuyên khoa Răng Hàm Mặt',
        'specialty_text' => 'Răng Hàm Mặt',
        'specialties' => [],
        'bio' => [],
        'gallery' => [],
        'image_url' => 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=900&q=80',
        'rating' => '4.9',
        'reviews' => '1.248 đánh giá',
        'reviews_count' => 1248,
        'followers' => '2.500+',
        'hours_text' => '08:00 - 17:00',
        'price_text' => '300.000đ',
        'city' => 'Quận 1, TP.HCM',
        'verified' => 'Đã xác thực',
        'facility_name' => 'Nha khoa Kim',
        'facility_slug' => '',
    ];
}

$title = (string) ($doctor['name'] ?? 'Bác sĩ') . ' • MedReview';
$description = trim((string) ($doctor['title_text'] ?? ''));
$heroImage = trim((string) ($doctor['image_url'] ?? ''));
$specialties = array_values(array_filter((array) ($doctor['specialties'] ?? []), static function ($item): bool {
    return trim((string) $item) !== '';
}));
if ($specialties === []) {
    $specialties = [
        'Trồng răng Implant: Implant đơn lẻ, Implant toàn hàm, All-on-4, All-on-6',
        'Răng sứ thẩm mỹ: Veneer, Crown, Smile Design',
        'Niềng răng mắc cài và invisalign: Niềng mắc cài kim loại trong suốt và Invisalign',
        'Phục hình thẩm mỹ toàn diện: Nụ cười hài hoà, nâng tầm gương mặt',
        'Điều trị tổng quát: Điều trị tuỷ, nhổ răng, trám răng thẩm mỹ',
    ];
}

$bio = array_values(array_filter((array) ($doctor['bio'] ?? []), static function ($item): bool {
    return trim((string) $item) !== '';
}));
if ($bio === []) {
    $bio = [
        'ThS.BS Phạm Hoàng Nam tốt nghiệp Thạc sĩ Răng Hàm Mặt tại Đại học Y Dược TP.HCM.',
        'Với hơn 10 năm kinh nghiệm, bác sĩ đã điều trị thành công hàng nghìn ca phục hình, implant và chỉnh nha phức tạp.',
        'Bác sĩ nổi bật với định hướng tư vấn rõ ràng, kế hoạch điều trị chi tiết và phong cách theo dõi sát sau điều trị.',
    ];
}

$totalReviewCount = (int) ($doctor['reviews_count'] ?? 0);
$doctorRatingValue = (float) ($doctor['rating'] ?? 0);
$ratingBars = [5 => 85, 4 => 11, 3 => 2, 2 => 1, 1 => 0];
if ($relatedReviews !== []) {
    $ratingBars = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($relatedReviews as $item) {
        $star = max(1, min(5, (int) round((float) ($item['rating'] ?? 0))));
        $ratingBars[$star] = (int) ($ratingBars[$star] ?? 0) + 1;
    }
    $sumBars = array_sum($ratingBars);
    if ($sumBars > 0) {
        foreach ($ratingBars as $star => $count) {
            $ratingBars[$star] = (int) round(($count / $sumBars) * 100);
        }
    }
}

$facilityName = (string) ($facility['name'] ?? $doctor['facility_name'] ?? 'Nha khoa Kim');
$facilityCity = (string) ($facility['city'] ?? $doctor['city'] ?? 'Quận 1, TP.HCM');
$facilityAddress = (string) ($facility['address_text'] ?? '181 Nguyễn Thị Minh Khai, Quận 1, TP.HCM');
$facilityPhone = (string) ($facility['phone_text'] ?? '1900 6899');
$facilityWebsite = (string) ($facility['website_url'] ?? 'www.nhakhoakim.com');
$facilityRating = (string) ($facility['rating'] ?? '4.8');
$followersText = (string) ($doctor['followers'] ?? '2.500+');

$profileFacts = [
    ['icon' => 'fa-solid fa-user-doctor', 'label' => 'Học vị', 'value' => 'Thạc sĩ Răng Hàm Mặt'],
    ['icon' => 'fa-solid fa-graduation-cap', 'label' => 'Tốt nghiệp', 'value' => 'ĐH Y Dược TP.HCM'],
    ['icon' => 'fa-solid fa-stethoscope', 'label' => 'Chuyên khoa', 'value' => (string) ($doctor['specialty_text'] ?? 'Răng Hàm Mặt')],
    ['icon' => 'fa-solid fa-briefcase-medical', 'label' => 'Kinh nghiệm', 'value' => '10+ năm'],
    ['icon' => 'fa-solid fa-language', 'label' => 'Ngoại ngữ', 'value' => 'Tiếng Việt, English'],
    ['icon' => 'fa-solid fa-users', 'label' => 'Số bệnh nhân', 'value' => (string) $followersText],
];

$certificates = [
    ['title' => 'Thống chỉ Implant', 'subtitle' => 'Nobel Biocare'],
    ['title' => 'Chứng chỉ Invisalign', 'subtitle' => 'Invisalign Provider'],
    ['title' => 'Chứng chỉ nâng xoang', 'subtitle' => 'Straumann Course'],
    ['title' => 'Chứng chỉ Phục hình', 'subtitle' => 'Prosthodontics'],
    ['title' => 'Chứng chỉ Laser', 'subtitle' => 'Laser Dentistry'],
];

$associations = [
    ['title' => 'Hội Răng Hàm Mặt Việt Nam', 'short' => 'VOSA'],
    ['title' => 'Hiệp hội Implant Quốc tế', 'short' => 'ICOI'],
    ['title' => 'Hội Chỉnh nha Thẩm mỹ', 'short' => 'AAO'],
    ['title' => 'Hội Nha khoa Thẩm mỹ', 'short' => 'AACD'],
];

$serviceCards = [
    [
        'title' => 'Trồng răng Implant',
        'subtitle' => 'Phục hình răng mất',
        'price' => '25.000.000đ',
        'duration' => 'Thời gian: 2 - 3 tháng',
        'image' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=700&q=80',
    ],
    [
        'title' => 'Răng sứ thẩm mỹ',
        'subtitle' => 'Veneer Ceramic',
        'price' => '6.000.000đ/răng',
        'duration' => 'Thời gian: 2 - 3 ngày',
        'image' => 'https://images.unsplash.com/photo-1629909615957-be7fc68c89ad?auto=format&fit=crop&w=700&q=80',
    ],
    [
        'title' => 'Niềng răng trong suốt',
        'subtitle' => 'Invisalign',
        'price' => '60.000.000đ',
        'duration' => 'Thời gian: 12 - 24 tháng',
        'image' => 'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=700&q=80',
    ],
    [
        'title' => 'Niềng răng mắc cài',
        'subtitle' => 'Kim loại / sứ',
        'price' => '35.000.000đ',
        'duration' => 'Thời gian: 18 - 24 tháng',
        'image' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=700&q=80',
    ],
];

$patientReviews = [];
foreach ($relatedReviews as $item) {
    $thumbs = [];
    foreach ((array) ($item['thumbs'] ?? []) as $thumb) {
        $thumb = trim((string) $thumb);
        if ($thumb !== '') {
            $thumbs[] = $thumb;
        }
    }
    if ($thumbs === []) {
        $beforeThumb = trim((string) ($item['before_image_url'] ?? $item['before'] ?? ''));
        $afterThumb = trim((string) ($item['after_image_url'] ?? $item['after'] ?? ''));
        if ($beforeThumb !== '') {
            $thumbs[] = $beforeThumb;
        }
        if ($afterThumb !== '') {
            $thumbs[] = $afterThumb;
        }
    }
    $patientReviews[] = [
        'author' => (string) ($item['author'] ?? 'Khách hàng ẩn danh'),
        'title' => (string) ($item['title'] ?? 'Review điều trị'),
        'excerpt' => (string) ($item['excerpt'] ?? 'Trải nghiệm điều trị thực tế đang được cập nhật.'),
        'rating' => (string) ($item['rating'] ?? '4.9'),
        'price' => (string) ($item['price'] ?? $item['price_text'] ?? 'Chi phí đang cập nhật'),
        'date' => (string) ($item['review_date'] ?? $item['review_date_text'] ?? '26/03/2024'),
        'service' => (string) ($item['service'] ?? $item['service_text'] ?? 'Răng sứ'),
        'slug' => (string) ($item['slug'] ?? ''),
        'thumbs' => array_slice($thumbs, 0, 2),
    ];
}
if ($patientReviews === []) {
    $patientReviews = [
        [
            'author' => 'Nguyễn Thị Trang',
            'title' => 'Bọc răng sứ tự nhiên, khớp cắn hài hoà',
            'excerpt' => 'Bác sĩ tư vấn kỹ, xử lý kỹ lưỡng. Làm răng sứ nhẹ, tự nhiên, ăn nhai thoải mái.',
            'rating' => '5.0',
            'price' => 'Chi phí: 12.000.000đ',
            'date' => '26/03/2024',
            'service' => 'Răng sứ',
            'slug' => '',
            'thumbs' => [
                'https://images.unsplash.com/photo-1629909615957-be7fc68c89ad?auto=format&fit=crop&w=700&q=80',
                'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=700&q=80',
            ],
        ],
        [
            'author' => 'Lê Minh Đức',
            'title' => 'Trồng Implant nhẹ nhàng, phục hình nhanh',
            'excerpt' => 'Trồng implant không đau như mình nghĩ, bác sĩ làm nhẹ nhàng và theo dõi rất kỹ.',
            'rating' => '5.0',
            'price' => 'Chi phí: 25.500.000đ',
            'date' => '12/05/2024',
            'service' => 'Implant',
            'slug' => '',
            'thumbs' => [
                'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=700&q=80',
                'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=700&q=80',
            ],
        ],
        [
            'author' => 'Phạm Hồng An',
            'title' => 'Niềng răng thay đổi nụ cười rõ rệt',
            'excerpt' => 'Niềng rất vừa ý, không ảnh hưởng giao tiếp nhiều. Bác sĩ theo dõi sát suốt quá trình.',
            'rating' => '4.9',
            'price' => 'Chi phí: 60.000.000đ',
            'date' => '28/04/2024',
            'service' => 'Nha chu',
            'slug' => '',
            'thumbs' => [
                'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=700&q=80',
                'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=700&q=80',
            ],
        ],
    ];
}

$patientMetaDefaults = [
    ['age' => '32 tuổi', 'location' => 'Đà Nẵng', 'likes' => 76, 'comments' => 15],
    ['age' => '29 tuổi', 'location' => 'Hội An', 'likes' => 58, 'comments' => 7],
    ['age' => '26 tuổi', 'location' => 'Huế', 'likes' => 33, 'comments' => 4],
    ['age' => '31 tuổi', 'location' => 'Quảng Nam', 'likes' => 24, 'comments' => 3],
];
foreach ($patientReviews as $index => &$item) {
    $meta = $patientMetaDefaults[$index % count($patientMetaDefaults)];
    $item['age'] = (string) ($item['age'] ?? $meta['age']);
    $item['location'] = (string) ($item['location'] ?? $meta['location']);
    $item['likes'] = (int) ($item['likes'] ?? $meta['likes']);
    $item['comments'] = (int) ($item['comments'] ?? $meta['comments']);
    $item['verified'] = (bool) ($item['verified'] ?? true);
    $item['avatar'] = (string) ($item['thumbs'][0] ?? '');
    if (count((array) $item['thumbs']) < 3) {
        $item['thumbs'][] = 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=700&q=80';
    }
    $item['thumbs'] = array_slice((array) $item['thumbs'], 0, 3);
}
unset($item);

?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
    <style>
      :root{
        --page:#f6f8fc;
        --surface:#ffffff;
        --surface-soft:#fbfcff;
        --border:#e8edf5;
        --text:#111827;
        --muted:#667085;
        --brand:#2563eb;
        --brand-dark:#1749c8;
        --brand-soft:#eef4ff;
        --warning:#f5b301;
        --success:#17b26a;
        --max:1280px;
      }
      *{box-sizing:border-box}
      html{scroll-behavior:smooth}
      body{
        margin:0;
        font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        color:var(--text);
        background:var(--page);
      }
      a{color:inherit;text-decoration:none}
      img{display:block;max-width:100%}
      .container{width:min(100% - 36px, var(--max));margin:0 auto}
      .doctor-detail{padding:18px 0 80px}
      .breadcrumbs{
        display:flex;align-items:center;gap:8px;flex-wrap:wrap;
        color:#98a2b3;font-size:11px;font-weight:600;
      }
      .breadcrumbs a{color:#98a2b3}
      .breadcrumbs .active{color:var(--brand)}
      .page-grid{
        margin-top:14px;
        display:grid;
        grid-template-columns:minmax(0,1fr) 308px;
        gap:18px;
        align-items:start;
      }
      .main-stack,.side-stack{display:grid;gap:16px}
      .card{
        border:1px solid var(--border);
        border-radius:16px;
        background:var(--surface);
        box-shadow:0 2px 10px rgba(16,24,40,.03);
      }
      .hero-card{padding:18px}
      .hero-grid{
        display:grid;
        grid-template-columns:minmax(0,1fr) 324px;
        gap:18px;
        align-items:stretch;
      }
      .hero-copy{display:grid;gap:14px}
      .hero-name h1{margin:0;font-size:2.15em;line-height:1.06;letter-spacing:-.04em}
      .hero-name p{margin:8px 0 0;color:#475467;font-size:14px;font-weight:600}
      .hero-rating{
        display:flex;align-items:center;gap:8px;flex-wrap:wrap;
        font-size:12px;color:#667085;
      }
      .hero-rating strong{font-size:15px;color:var(--warning)}
      .stars{display:inline-flex;align-items:center;gap:2px;color:var(--warning);font-size:12px}
      .hero-desc{margin:0;color:#475467;font-size:13px;line-height:1.75}
      .mini-stats{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:10px;
      }
      .mini-stat{
        padding:12px;
        border:1px solid var(--border);
        border-radius:12px;
        background:var(--surface-soft);
      }
      .mini-stat strong{display:block;font-size:14px}
      .mini-stat span{display:block;margin-top:4px;color:#98a2b3;font-size:11px;font-weight:700}
      .action-row{display:flex;gap:8px;flex-wrap:wrap}
      .action-btn{
        display:inline-flex;align-items:center;justify-content:center;gap:8px;
        min-height:40px;padding:0 14px;border-radius:10px;border:1px solid #d7e2fb;
        background:#fff;color:#31538d;font-size:12px;font-weight:800;
      }
      .action-btn.primary{background:var(--brand);border-color:var(--brand);color:#fff}
      .icon-only{min-width:40px;padding:0}
      .hero-photo{
        border:1px solid var(--border);
        border-radius:14px;
        background:
          radial-gradient(circle at 50% 18%, rgba(255,255,255,.96), transparent 34%),
          linear-gradient(180deg,#fbfdff 0%,#edf4ff 100%);
        display:flex;align-items:flex-end;justify-content:center;
        overflow:hidden;padding:16px 16px 0;
      }
      .hero-photo img{width:100%;max-height:420px;object-fit:contain}
      .tabs{
        display:flex;align-items:center;flex-wrap:wrap;gap:0;
        padding:0 12px;border:1px solid var(--border);border-radius:16px;background:#fff;
      }
      .tab{
        position:relative;display:inline-flex;align-items:center;gap:8px;
        min-height:48px;padding:0 14px;color:#64748b;font-size:12px;font-weight:800;
      }
      .tab.active{color:var(--brand)}
      .tab.active::after{
        content:"";position:absolute;left:14px;right:14px;bottom:-1px;height:2px;border-radius:999px;background:var(--brand);
      }
      .section-card{padding:18px}
      .section-head{
        display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;
      }
      .section-head h2{margin:0;font-size:15px}
      .section-head a{color:var(--brand);font-size:12px;font-weight:700}
      .section-icon{
        width:28px;height:28px;border-radius:9px;background:var(--brand-soft);color:var(--brand);
        display:inline-flex;align-items:center;justify-content:center;font-size:12px;margin-right:8px;
      }
      .intro-text{display:grid;gap:10px}
      .intro-text p{margin:0;color:#475467;font-size:13px;line-height:1.75}
      .fact-grid{
        margin-top:14px;
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:10px;
      }
      .fact-item{
        padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--surface-soft);
      }
      .fact-item .fact-icon{
        width:28px;height:28px;border-radius:10px;background:var(--brand-soft);color:var(--brand);
        display:inline-flex;align-items:center;justify-content:center;font-size:12px;
      }
      .fact-item strong{display:block;margin-top:8px;font-size:11px;color:#98a2b3}
      .fact-item span{display:block;margin-top:5px;font-size:13px;font-weight:700;line-height:1.45}
      .highlight-list{display:grid;gap:8px}
      .highlight-item{
        display:flex;align-items:flex-start;gap:10px;
        padding:11px 12px;border:1px solid var(--border);border-radius:12px;background:#fff;
      }
      .highlight-bullet{
        width:22px;height:22px;border-radius:999px;background:var(--brand-soft);color:var(--brand);
        display:inline-flex;align-items:center;justify-content:center;font-size:11px;flex:0 0 auto;
      }
      .highlight-item span:last-child{font-size:13px;line-height:1.65;color:#334155}
      .cert-grid{
        display:grid;
        grid-template-columns:repeat(5,minmax(0,1fr));
        gap:10px;
      }
      .cert-card{
        padding:10px;border:1px solid var(--border);border-radius:12px;background:#fff;text-align:center;
      }
      .cert-thumb{
        height:74px;border-radius:10px;border:1px solid #efe6d6;
        background:
          linear-gradient(135deg,#fbf5ea 0%,#f7ecd9 100%);
        display:flex;align-items:center;justify-content:center;
        color:#a78954;font-size:22px;
      }
      .cert-card strong{display:block;margin-top:10px;font-size:11px;line-height:1.45}
      .cert-card span{display:block;margin-top:4px;color:#98a2b3;font-size:10px}
      .assoc-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:10px;
      }
      .assoc-card{
        min-height:74px;padding:12px;border:1px solid var(--border);border-radius:12px;background:#fff;
        display:flex;align-items:center;gap:10px;
      }
      .assoc-badge{
        width:38px;height:38px;border-radius:12px;background:var(--brand-soft);color:var(--brand);
        display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex:0 0 auto;
      }
      .assoc-card strong{display:block;font-size:11px;line-height:1.45}
      .assoc-card span{display:block;margin-top:4px;color:#98a2b3;font-size:10px}
      .service-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
      }
      .service-card{
        border:1px solid var(--border);border-radius:14px;background:#fff;overflow:hidden;
      }
      .service-card img{width:100%;height:126px;object-fit:cover}
      .service-card .body{padding:12px}
      .service-card strong{display:block;font-size:13px}
      .service-card .sub{display:block;margin-top:4px;color:#98a2b3;font-size:11px;font-weight:700}
      .service-card .price{display:block;margin-top:10px;color:var(--brand);font-size:14px;font-weight:800}
      .service-card .meta{display:flex;gap:8px;align-items:center;margin-top:8px;color:#98a2b3;font-size:11px}
      .doctor-review-shell{
        display:grid;
        grid-template-columns:248px minmax(0,1fr);
        gap:16px;
        align-items:start;
      }
      .review-summary-card{
        border:1px solid var(--border);
        border-radius:14px;
        background:#fff;
        padding:18px 16px;
      }
      .review-summary-card h3{
        margin:0;
        font-size:15px;
      }
      .review-summary-card p{
        margin:6px 0 0;
        color:#98a2b3;
        font-size:11px;
        line-height:1.6;
      }
      .review-score{
        margin-top:16px;
        display:flex;
        align-items:flex-end;
        gap:6px;
      }
      .review-score strong{font-size:52px;line-height:1;color:var(--brand)}
      .review-score span{padding-bottom:8px;font-size:15px;font-weight:800;color:#475467}
      .review-summary-card .stars{margin-top:8px;font-size:15px}
      .review-total{
        margin-top:10px;
        color:#667085;
        font-size:13px;
        font-weight:700;
      }
      .review-summary-bars{
        display:grid;
        gap:8px;
        margin-top:16px;
      }
      .review-summary-row{
        display:grid;
        grid-template-columns:26px 1fr 34px;
        gap:8px;
        align-items:center;
        color:#667085;
        font-size:11px;
      }
      .review-summary-row .bar{height:5px}
      .review-verified{
        margin-top:14px;
        padding:14px;
        border:1px solid var(--border);
        border-radius:12px;
        background:var(--surface-soft);
      }
      .review-verified strong{
        display:block;
        color:var(--brand);
        font-size:28px;
        line-height:1;
      }
      .review-verified span{
        display:block;
        margin-top:6px;
        color:#475467;
        font-size:12px;
        font-weight:800;
      }
      .review-verified small{
        display:block;
        margin-top:6px;
        color:#98a2b3;
        font-size:11px;
        line-height:1.6;
      }
      .review-write-btn{
        margin-top:14px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:100%;
        min-height:40px;
        border-radius:10px;
        border:1px solid #cfe0ff;
        color:var(--brand);
        background:#fff;
        font-size:12px;
        font-weight:800;
      }
      .review-feed{
        border:1px solid var(--border);
        border-radius:14px;
        background:#fff;
        overflow:hidden;
      }
      .review-feed-toolbar{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        padding:14px 16px;
        border-bottom:1px solid var(--border);
      }
      .review-toolbar-group{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
      }
      .review-filter-pill,
      .review-sort-pill{
        display:inline-flex;
        align-items:center;
        gap:8px;
        min-height:34px;
        padding:0 12px;
        border:1px solid var(--border);
        border-radius:10px;
        background:#fff;
        color:#475467;
        font-size:11px;
        font-weight:800;
      }
      .review-switch{
        display:inline-flex;
        align-items:center;
        gap:10px;
        color:#667085;
        font-size:11px;
        font-weight:700;
      }
      .review-switch-toggle{
        width:34px;
        height:20px;
        border-radius:999px;
        background:var(--brand);
        position:relative;
      }
      .review-switch-toggle::after{
        content:"";
        position:absolute;
        top:3px;
        right:3px;
        width:14px;
        height:14px;
        border-radius:999px;
        background:#fff;
      }
      .review-list{display:grid}
      .review-row{
        display:grid;
        grid-template-columns:190px minmax(0,1fr) 214px 78px;
        gap:14px;
        align-items:start;
        padding:16px;
        border-top:1px solid var(--border);
      }
      .review-row:first-child{border-top:0}
      .review-author{
        display:flex;
        align-items:flex-start;
        gap:10px;
      }
      .avatar{
        width:46px;height:46px;border-radius:999px;background:#e8eef9;overflow:hidden;flex:0 0 auto;
        display:flex;align-items:center;justify-content:center;color:#31538d;font-weight:800;
      }
      .avatar img{width:100%;height:100%;object-fit:cover}
      .review-author strong{display:block;font-size:13px}
      .review-author .meta-line{
        display:block;
        margin-top:4px;
        color:#98a2b3;
        font-size:11px;
      }
      .verified-tag{
        display:inline-flex;
        align-items:center;
        gap:5px;
        margin-top:8px;
        color:var(--success);
        font-size:11px;
        font-weight:800;
      }
      .review-content-top{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
      }
      .review-content-top .stars{
        font-size:11px;
      }
      .review-content-top strong{
        color:#111827;
        font-size:13px;
      }
      .review-date{
        color:#98a2b3;
        font-size:11px;
        font-weight:700;
      }
      .review-content p{
        margin:8px 0 0;
        color:#475467;
        font-size:12px;
        line-height:1.7;
      }
      .review-service{
        margin-top:8px;
        color:#667085;
        font-size:11px;
      }
      .review-media{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:8px;
      }
      .review-media-item{
        position:relative;
      }
      .review-media img{
        width:100%;
        aspect-ratio:1.02/1;
        border-radius:10px;
        object-fit:cover;
        background:#edf4ff;
      }
      .review-media-more{
        position:absolute;
        right:8px;
        bottom:8px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:24px;
        height:24px;
        padding:0 6px;
        border-radius:999px;
        background:rgba(17,24,39,.72);
        color:#fff;
        font-size:11px;
        font-weight:800;
      }
      .review-actions{
        display:flex;
        align-items:flex-start;
        justify-content:flex-end;
        gap:12px;
        color:#98a2b3;
        font-size:12px;
      }
      .review-actions span{
        display:inline-flex;
        align-items:center;
        gap:6px;
      }
      .review-more{
        display:flex;
        justify-content:center;
        padding:0 16px 16px;
      }
      .review-more a{
        display:inline-flex;
        align-items:center;
        gap:8px;
        min-height:38px;
        padding:0 16px;
        border-radius:999px;
        border:1px solid #d4def5;
        background:#fff;
        color:var(--brand);
        font-size:12px;
        font-weight:800;
      }
      .doctor-reviews-wide{
        margin-top:20px;
        padding:24px;
        border-color:#dbe7fb;
        box-shadow:0 16px 36px rgba(24,75,145,.08);
      }
      .doctor-reviews-wide .section-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        margin-bottom:20px;
      }
      .review-section-count{
        display:inline-flex;
        align-items:center;
        min-height:30px;
        padding:0 11px;
        border-radius:999px;
        background:var(--brand-soft);
        color:var(--brand);
        font-size:12px;
        font-weight:800;
        white-space:nowrap;
      }
      .doctor-reviews-wide .doctor-review-shell{
        grid-template-columns:280px minmax(0,1fr);
        gap:20px;
      }
      .doctor-reviews-wide .review-summary-card{
        position:sticky;
        top:92px;
        padding:20px;
        border-radius:18px;
        border-color:#dbe7fb;
        background:linear-gradient(180deg,#f6faff 0%,#fff 48%);
      }
      .doctor-reviews-wide .review-feed{border-radius:18px}
      .doctor-reviews-wide .review-feed-toolbar{padding:16px 20px}
      .doctor-reviews-wide .review-row{
        grid-template-columns:190px minmax(0,1fr) 230px 82px;
        gap:18px;
        padding:20px;
      }
      .doctor-reviews-wide .review-media{gap:9px}
      .doctor-reviews-wide .review-actions{gap:10px}
      .side-card{padding:16px}
      .side-card h3{margin:0 0 14px;font-size:15px}
      .rating-large{
        display:flex;align-items:flex-end;gap:8px;
      }
      .rating-large strong{font-size:44px;line-height:1}
      .rating-large span{padding-bottom:7px;color:#98a2b3;font-size:12px}
      .rating-bars{display:grid;gap:7px;margin-top:10px}
      .rating-row{
        display:grid;grid-template-columns:12px 1fr 30px;gap:8px;align-items:center;color:#667085;font-size:11px;
      }
      .bar{height:6px;border-radius:999px;background:#edf2f8;overflow:hidden}
      .bar span{display:block;height:100%;background:linear-gradient(90deg,#f6c44f 0%, #f59e0b 100%)}
      .side-link{display:inline-flex;align-items:center;gap:8px;margin-top:12px;color:var(--brand);font-size:12px;font-weight:700}
      .schedule-list,.contact-list,.doctor-side-list{display:grid;gap:10px}
      .schedule-row,.contact-row{
        display:flex;align-items:flex-start;justify-content:space-between;gap:10px;color:#475467;font-size:12px;
      }
      .schedule-row strong,.contact-row strong{font-size:12px}
      .contact-row strong{min-width:92px;color:#98a2b3}
      .side-button{
        display:inline-flex;align-items:center;justify-content:center;gap:8px;
        width:100%;min-height:42px;border-radius:10px;background:var(--brand);color:#fff;font-size:12px;font-weight:800;
      }
      .map-box{
        min-height:186px;border:1px solid #dce5f5;border-radius:12px;
        background:
          radial-gradient(circle at 66% 42%, rgba(239,68,68,.14), transparent 10%),
          linear-gradient(0deg, transparent 26px, rgba(203,213,225,.34) 27px),
          linear-gradient(90deg, transparent 26px, rgba(203,213,225,.34) 27px),
          linear-gradient(135deg,#fbfdff 0%, #eef4ff 100%);
        background-size:auto, 28px 28px, 28px 28px, auto;
        display:flex;align-items:flex-end;justify-content:center;padding:14px;text-align:center;
      }
      .map-box span{display:block;font-size:11px;color:#667085;line-height:1.7}
      .clinic-card,.doctor-side-card{
        border:1px solid var(--border);border-radius:12px;background:#fff;padding:12px;
      }
      .clinic-card{display:grid;grid-template-columns:70px minmax(0,1fr);gap:10px;align-items:start}
      .clinic-card img,.doctor-side-card img{
        width:70px;height:70px;border-radius:12px;object-fit:cover;background:#edf4ff;
      }
      .clinic-card strong,.doctor-side-card strong{display:block;font-size:13px}
      .clinic-card span,.doctor-side-card span{display:block;margin-top:4px;color:#98a2b3;font-size:11px}
      .doctor-side-card{
        display:grid;grid-template-columns:54px minmax(0,1fr);gap:10px;align-items:start;
      }
      .doctor-side-card img{width:54px;height:54px;border-radius:12px}
      .bottom-cta{
        border-radius:16px;
        background:linear-gradient(90deg,var(--brand-dark) 0%, var(--brand) 100%);
        color:#fff;
        padding:18px 20px;
        display:flex;align-items:center;justify-content:space-between;gap:14px;
      }
      .bottom-cta strong{display:block;font-size:18px}
      .bottom-cta span{display:block;margin-top:6px;font-size:12px;opacity:.92}
      .bottom-cta a{
        display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:10px;
        background:#fff;color:var(--brand-dark);font-size:12px;font-weight:800;
      }
      @media (max-width:1180px){
        .page-grid{grid-template-columns:1fr}
      }
      @media (max-width:920px){
        .hero-grid{grid-template-columns:1fr}
        .mini-stats,.service-grid,.fact-grid,.assoc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        .cert-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
        .doctor-review-shell{grid-template-columns:1fr}
        .review-row{grid-template-columns:1fr}
        .review-actions{justify-content:flex-start}
        .doctor-reviews-wide .review-summary-card{position:static}
        .doctor-reviews-wide .review-row{grid-template-columns:1fr}
      }
      @media (max-width:680px){
        .container{width:min(100% - 20px, var(--max))}
        .mini-stats,.service-grid,.fact-grid,.assoc-grid,.cert-grid{grid-template-columns:1fr}
        .tabs{padding:8px}
        .tab{width:100%;min-height:40px;border-radius:10px}
        .tab.active{background:var(--brand-soft)}
        .tab.active::after{display:none}
        .bottom-cta{flex-direction:column;align-items:flex-start}
        .doctor-reviews-wide{padding:18px 14px}
        .doctor-reviews-wide .section-head{align-items:flex-start;flex-direction:column;margin-bottom:16px}
        .doctor-reviews-wide .review-feed-toolbar{padding:14px}
        .doctor-reviews-wide .review-row{padding:16px 14px}
        .review-feed-toolbar{align-items:flex-start;flex-direction:column}
        .review-media{grid-template-columns:repeat(2,minmax(0,1fr))}
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <main class="doctor-detail site-typo">
      <section class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
          <a href="/">Trang chủ</a>
          <span>/</span>
          <a href="/bac-si.php">Bác sĩ</a>
          <span>/</span>
          <span class="active"><?php echo htmlspecialchars((string) ($doctor['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
        </nav>

        <div class="page-grid">
          <div class="main-stack">
            <section class="card hero-card">
              <div class="hero-grid">
                <div class="hero-copy">
                  <div class="hero-name">
                    <h1><?php echo htmlspecialchars((string) ($doctor['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p><?php echo htmlspecialchars((string) ($doctor['title_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>

                  <div class="hero-rating">
                    <strong><?php echo htmlspecialchars((string) ($doctor['rating'] ?? '4.9'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="stars">
                      <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </span>
                    <span><?php echo number_format($totalReviewCount > 0 ? $totalReviewCount : 1248, 0, ',', '.'); ?> đánh giá</span>
                    <?php if ((string) ($doctor['verified'] ?? '') !== ''): ?>
                      <span style="color:var(--brand);font-weight:800"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars((string) ($doctor['verified'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                  </div>

                  <p class="hero-desc"><?php echo htmlspecialchars($bio[0] ?? 'Hơn 10 năm kinh nghiệm trong lĩnh vực Răng Hàm Mặt. Thế mạnh: trồng implant, răng sứ thẩm mỹ, niềng răng trong suốt.', ENT_QUOTES, 'UTF-8'); ?></p>

                  <div class="mini-stats">
                    <div class="mini-stat">
                      <strong>10+ năm</strong>
                      <span>Kinh nghiệm</span>
                    </div>
                    <div class="mini-stat">
                      <strong><?php echo htmlspecialchars($followersText, ENT_QUOTES, 'UTF-8'); ?></strong>
                      <span>Bệnh nhân</span>
                    </div>
                    <div class="mini-stat">
                      <strong>98%</strong>
                      <span>Hài lòng</span>
                    </div>
                    <div class="mini-stat">
                      <strong>4.9/5</strong>
                      <span>Đánh giá</span>
                    </div>
                  </div>

                  <div class="action-row">
                    <a class="action-btn primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check"></i>Đặt lịch khám</a>
                    <a class="action-btn" href="/lien-he.php"><i class="fa-solid fa-phone"></i>Gọi ngay</a>
                    <a class="action-btn" href="/lien-he.php"><i class="fa-brands fa-facebook-messenger"></i>Nhắn Zalo</a>
                    <a class="action-btn icon-only" href="/lien-he.php"><i class="fa-regular fa-heart"></i></a>
                  </div>
                </div>

                <div class="hero-photo">
                  <img src="<?php echo htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($doctor['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
              </div>
            </section>

            <nav class="tabs" aria-label="Điều hướng hồ sơ bác sĩ">
              <a class="tab active" href="#gioi-thieu"><i class="fa-regular fa-user"></i>Giới thiệu</a>
              <a class="tab" href="#kinh-nghiem"><i class="fa-solid fa-briefcase-medical"></i>Kinh nghiệm</a>
              <a class="tab" href="#dich-vu"><i class="fa-solid fa-tooth"></i>Dịch vụ</a>
              <a class="tab" href="#danh-gia"><i class="fa-regular fa-star"></i>Đánh giá</a>
              <a class="tab" href="#hoi-dap"><i class="fa-regular fa-comments"></i>Hỏi đáp</a>
            </nav>

            <section class="card section-card" id="gioi-thieu">
              <div class="section-head">
                <h2><span class="section-icon"><i class="fa-regular fa-user"></i></span>Giới thiệu về bác sĩ</h2>
              </div>
              <div class="intro-text">
                <?php foreach ($bio as $paragraph): ?>
                  <p><?php echo htmlspecialchars((string) $paragraph, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
              </div>
              <div class="fact-grid">
                <?php foreach ($profileFacts as $fact): ?>
                  <div class="fact-item">
                    <span class="fact-icon"><i class="<?php echo htmlspecialchars((string) $fact['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                    <strong><?php echo htmlspecialchars((string) $fact['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars((string) $fact['value'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section class="card section-card" id="kinh-nghiem">
              <div class="section-head">
                <h2><span class="section-icon"><i class="fa-solid fa-stethoscope"></i></span>Chuyên môn & thế mạnh</h2>
              </div>
              <div class="highlight-list">
                <?php foreach ($specialties as $specialty): ?>
                  <div class="highlight-item">
                    <span class="highlight-bullet"><i class="fa-solid fa-check"></i></span>
                    <span><?php echo htmlspecialchars((string) $specialty, ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section class="card section-card">
              <div class="section-head">
                <h2><span class="section-icon"><i class="fa-solid fa-certificate"></i></span>Chứng chỉ đã đào tạo</h2>
                <a href="#">Xem tất cả</a>
              </div>
              <div class="cert-grid">
                <?php foreach ($certificates as $item): ?>
                  <div class="cert-card">
                    <div class="cert-thumb"><i class="fa-regular fa-file-lines"></i></div>
                    <strong><?php echo htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars((string) $item['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section class="card section-card">
              <div class="section-head">
                <h2><span class="section-icon"><i class="fa-solid fa-people-group"></i></span>Hiệp hội & tổ chức</h2>
              </div>
              <div class="assoc-grid">
                <?php foreach ($associations as $item): ?>
                  <div class="assoc-card">
                    <span class="assoc-badge"><?php echo htmlspecialchars((string) $item['short'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <div>
                      <strong><?php echo htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <span><?php echo htmlspecialchars((string) $item['short'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section class="card section-card" id="dich-vu">
              <div class="section-head">
                <h2><span class="section-icon"><i class="fa-solid fa-tooth"></i></span>Dịch vụ chuyên môn</h2>
                <a href="#">Xem tất cả</a>
              </div>
              <div class="service-grid">
                <?php foreach ($serviceCards as $item): ?>
                  <article class="service-card">
                    <img src="<?php echo htmlspecialchars((string) $item['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="body">
                      <strong><?php echo htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <span class="sub"><?php echo htmlspecialchars((string) $item['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <span class="price"><?php echo htmlspecialchars((string) $item['price'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <div class="meta">
                        <i class="fa-regular fa-clock"></i>
                        <span><?php echo htmlspecialchars((string) $item['duration'], ENT_QUOTES, 'UTF-8'); ?></span>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>

            <?php ob_start(); ?>
            <section class="card section-card doctor-reviews-wide" id="danh-gia">
              <div class="section-head">
                <h2><span class="section-icon"><i class="fa-regular fa-star"></i></span>Đánh giá từ bệnh nhân</h2>
                <span class="review-section-count"><?php echo number_format($totalReviewCount > 0 ? $totalReviewCount : 1248, 0, ',', '.'); ?> đánh giá</span>
              </div>
              <div class="doctor-review-shell">
                <div class="review-summary-card">
                  <h3>Đánh giá thực tế</h3>
                  <p>Dựa trên <?php echo number_format($totalReviewCount > 0 ? $totalReviewCount : 1248, 0, ',', '.'); ?> đánh giá xác thực</p>
                  <div class="review-score">
                    <strong><?php echo htmlspecialchars((string) ($doctor['rating'] ?? '4.7'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span>/5</span>
                  </div>
                  <div class="stars">
                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i>
                  </div>
                  <div class="review-total"><?php echo number_format($totalReviewCount > 0 ? $totalReviewCount : 1248, 0, ',', '.'); ?> đánh giá</div>
                  <div class="review-summary-bars">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                      <div class="review-summary-row">
                        <span><?php echo $star; ?> sao</span>
                        <span class="bar"><span style="width:<?php echo (int) ($ratingBars[$star] ?? 0); ?>%"></span></span>
                        <span><?php echo (int) ($ratingBars[$star] ?? 0); ?>%</span>
                      </div>
                    <?php endfor; ?>
                  </div>
                  <div class="review-verified">
                    <strong>100%</strong>
                    <span>Đánh giá xác thực</span>
                    <small>Tất cả đánh giá đều được xác minh thông tin.</small>
                  </div>
                  <a class="review-write-btn" href="/lien-he.php">Viết đánh giá</a>
                </div>

                <div class="review-feed">
                  <div class="review-feed-toolbar">
                    <div class="review-toolbar-group">
                      <span class="review-filter-pill"><i class="fa-solid fa-filter"></i>Tất cả dịch vụ</span>
                      <span class="review-sort-pill"><i class="fa-solid fa-arrow-down-wide-short"></i>Sắp xếp: Mới nhất</span>
                    </div>
                    <div class="review-switch">
                      <span>Chỉ hiển thị đánh giá có hình ảnh</span>
                      <span class="review-switch-toggle" aria-hidden="true"></span>
                    </div>
                  </div>

                  <div class="review-list">
                    <?php foreach ($patientReviews as $index => $item): ?>
                      <article class="review-row">
                        <div class="review-author">
                          <span class="avatar">
                            <?php if ((string) ($item['avatar'] ?? '') !== ''): ?>
                              <img src="<?php echo htmlspecialchars((string) $item['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $item['author'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                              <?php echo htmlspecialchars(mb_substr((string) $item['author'], 0, 1), ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                          </span>
                          <div>
                            <strong><?php echo htmlspecialchars((string) $item['author'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="meta-line"><?php echo htmlspecialchars((string) ($item['age'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars((string) ($item['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($item['verified'])): ?>
                              <span class="verified-tag"><i class="fa-solid fa-circle-check"></i>Đã xác thực</span>
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="review-content">
                          <div class="review-content-top">
                            <div class="stars">
                              <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                              <strong><?php echo htmlspecialchars((string) ($item['rating'] ?? '5.0'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <span class="review-date"><?php echo htmlspecialchars((string) ($item['date'] ?? '26/03/2024'), ENT_QUOTES, 'UTF-8'); ?></span>
                          </div>
                          <p><?php echo htmlspecialchars((string) $item['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                          <div class="review-service">Dịch vụ: <?php echo htmlspecialchars((string) ($item['service'] ?? 'Răng sứ'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="review-media">
                          <?php foreach ((array) ($item['thumbs'] ?? []) as $thumbIndex => $thumb): ?>
                            <div class="review-media-item">
                              <img src="<?php echo htmlspecialchars((string) $thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'); ?>">
                              <?php if ($thumbIndex === 2): ?>
                                <span class="review-media-more">+2</span>
                              <?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        </div>

                        <div class="review-actions">
                          <span><i class="fa-regular fa-heart"></i><?php echo number_format((int) ($item['likes'] ?? 0), 0, ',', '.'); ?></span>
                          <span><i class="fa-regular fa-message"></i><?php echo number_format((int) ($item['comments'] ?? 0), 0, ',', '.'); ?></span>
                          <span><i class="fa-solid fa-ellipsis-vertical"></i></span>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  </div>

                  <div class="review-more">
                    <a href="#"><i class="fa-solid fa-angle-down"></i>Xem thêm đánh giá</a>
                  </div>
                </div>
              </div>
            </section>
            <?php $doctorReviewSection = ob_get_clean(); ?>

            <section class="bottom-cta" id="hoi-dap">
              <div>
                <strong>Đặt lịch khám với <?php echo htmlspecialchars((string) ($doctor['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Đội ngũ tư vấn hỗ trợ nhanh, giúp bạn chọn đúng dịch vụ và khung giờ phù hợp.</span>
              </div>
              <a href="/lien-he.php">Đặt lịch ngay</a>
            </section>
          </div>

          <aside class="side-stack">
            <section class="card side-card">
              <h3><span class="section-icon"><i class="fa-regular fa-star"></i></span>Điểm đánh giá tổng thể</h3>
              <div class="rating-large">
                <strong><?php echo htmlspecialchars((string) ($doctor['rating'] ?? '4.9'), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>/5</span>
              </div>
              <div class="stars">
                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
              </div>
              <div style="margin-top:6px;color:#98a2b3;font-size:11px"><?php echo number_format($totalReviewCount > 0 ? $totalReviewCount : 1248, 0, ',', '.'); ?> đánh giá</div>
              <div class="rating-bars">
                <?php for ($star = 5; $star >= 1; $star--): ?>
                  <div class="rating-row">
                    <span><?php echo $star; ?></span>
                    <span class="bar"><span style="width:<?php echo (int) ($ratingBars[$star] ?? 0); ?>%"></span></span>
                    <span><?php echo (int) ($ratingBars[$star] ?? 0); ?>%</span>
                  </div>
                <?php endfor; ?>
              </div>
              <a class="side-link" href="#danh-gia">Xem tất cả đánh giá</a>
            </section>

            <section class="card side-card">
              <h3><span class="section-icon"><i class="fa-regular fa-clock"></i></span>Lịch khám</h3>
              <div class="schedule-list">
                <div class="schedule-row"><strong>Thứ 2</strong><span><?php echo htmlspecialchars((string) ($doctor['hours_text'] ?? '08:00 - 17:00'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="schedule-row"><strong>Thứ 3</strong><span><?php echo htmlspecialchars((string) ($doctor['hours_text'] ?? '08:00 - 17:00'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="schedule-row"><strong>Thứ 4</strong><span><?php echo htmlspecialchars((string) ($doctor['hours_text'] ?? '08:00 - 17:00'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="schedule-row"><strong>Thứ 5</strong><span><?php echo htmlspecialchars((string) ($doctor['hours_text'] ?? '08:00 - 17:00'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="schedule-row"><strong>Thứ 6</strong><span><?php echo htmlspecialchars((string) ($doctor['hours_text'] ?? '08:00 - 17:00'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="schedule-row"><strong>Thứ 7</strong><span>08:00 - 12:00</span></div>
                <div class="schedule-row"><strong>Chủ nhật</strong><span>Nghỉ</span></div>
              </div>
              <div style="margin-top:14px">
                <a class="side-button" href="/lien-he.php"><i class="fa-solid fa-calendar-check"></i>Đặt lịch khám</a>
              </div>
            </section>

            <section class="card side-card">
              <h3><span class="section-icon"><i class="fa-regular fa-address-book"></i></span>Thông tin liên hệ</h3>
              <div class="contact-list">
                <div class="contact-row"><strong>Nơi khám</strong><span><?php echo htmlspecialchars($facilityName, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="contact-row"><strong>Khu vực</strong><span><?php echo htmlspecialchars($facilityCity, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="contact-row"><strong>Điện thoại</strong><span><?php echo htmlspecialchars($facilityPhone, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="contact-row"><strong>Website</strong><span><?php echo htmlspecialchars($facilityWebsite, ENT_QUOTES, 'UTF-8'); ?></span></div>
              </div>
            </section>

            <section class="card side-card">
              <h3><span class="section-icon"><i class="fa-solid fa-map-location-dot"></i></span>Vị trí phòng khám</h3>
              <div class="map-box">
                <span><i class="fa-solid fa-location-dot" style="color:#ef4444;margin-right:6px"></i><?php echo htmlspecialchars($facilityAddress, ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <?php if (is_array($facility) && $facility !== []): ?>
                <a class="side-link" href="/co-so-y-te-chi-tiet.php?slug=<?php echo rawurlencode((string) ($facility['slug'] ?? '')); ?>">Xem bản đồ lớn</a>
              <?php else: ?>
                <a class="side-link" href="#">Xem bản đồ lớn</a>
              <?php endif; ?>
            </section>

            <section class="card side-card">
              <h3><span class="section-icon"><i class="fa-solid fa-hospital"></i></span>Phòng khám đang công tác</h3>
              <article class="clinic-card">
                <img src="<?php echo htmlspecialchars((string) ($facility['image_url'] ?? 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=700&q=80'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($facilityName, ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                  <strong><?php echo htmlspecialchars($facilityName, ENT_QUOTES, 'UTF-8'); ?></strong>
                  <span><?php echo htmlspecialchars($facilityCity, ENT_QUOTES, 'UTF-8'); ?></span>
                  <span><?php echo htmlspecialchars($facilityRating, ENT_QUOTES, 'UTF-8'); ?>/5</span>
                  <?php if (is_array($facility) && $facility !== []): ?>
                    <a class="side-link" href="/co-so-y-te-chi-tiet.php?slug=<?php echo rawurlencode((string) ($facility['slug'] ?? '')); ?>">Xem chi tiết</a>
                  <?php endif; ?>
                </div>
              </article>
            </section>

            <section class="card side-card">
              <h3><span class="section-icon"><i class="fa-solid fa-user-doctor"></i></span>Bác sĩ tương tự</h3>
              <div class="doctor-side-list">
                <?php foreach ($relatedDoctors as $item): ?>
                  <article class="doctor-side-card">
                    <img src="<?php echo htmlspecialchars((string) ($item['image_url'] ?? 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=400&q=80'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                      <strong><?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <span><?php echo htmlspecialchars((string) ($item['specialty_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                      <span><?php echo htmlspecialchars((string) ($item['rating'] ?? '4.8'), ENT_QUOTES, 'UTF-8'); ?>/5</span>
                      <a class="side-link" href="/bac-si-chi-tiet.php?slug=<?php echo rawurlencode((string) ($item['slug'] ?? '')); ?>">Xem hồ sơ</a>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>
          </aside>
        </div>
        <?php echo $doctorReviewSection ?? ''; ?>
      </section>
    </main>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
  </body>
</html>
