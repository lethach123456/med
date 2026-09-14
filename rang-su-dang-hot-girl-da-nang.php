<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/front_admin.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
front_editor_page_maybe_redirect('rang-su-dang-hot-girl-da-nang');

$pdo = db();
$hotline = site_hotline('+84 777 265 444');
$email = site_email('info@topdentaldanang.com');
$address = site_address('46 Trần Tống - TP Đà Nẵng');
$galleryLines = site_setting('site_home_gallery_lines', '');
$galleryImages = [];
if (trim($galleryLines) !== '') {
    $lines = preg_split('/\r\n|\r|\n/', $galleryLines) ?: [];
    foreach ($lines as $line) {
        $url = trim((string) $line);
        if ($url !== '') {
            $galleryImages[] = $url;
        }
    }
}
if (count($galleryImages) === 0) {
    $galleryImages = [
        '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
        '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
    ];
}

// Hero slider de mau cung 1 anh. Ban co the tu thay URL anh ngay tai file nay.
$heroImages = [
    '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
];
$services = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, excerpt, featured_image_url
         FROM posts
         WHERE status = 'published' AND category_id = 2
         ORDER BY updated_at DESC
         LIMIT 6"
    );
    $stmt->execute();
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $services[] = [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'excerpt' => trim((string) ($row['excerpt'] ?? '')),
            'img' => trim((string) ($row['featured_image_url'] ?? '')),
        ];
    }
} catch (Throwable $e) {
    $services = [];
}

$smileStyles = [
    [
        'title' => 'Dáng hot girl tự nhiên',
        'desc' => 'Form răng thanh, đều, ôm cung cười mềm và trong sáng, ưu tiên vẻ đẹp nhẹ, nữ tính và hợp gương mặt.',
        'icon' => 'fa-solid fa-sparkles',
    ],
    [
        'title' => 'Dáng sang, chụp ảnh đẹp',
        'desc' => 'Tỉ lệ răng và đường cười được tính để lên hình đẹp, tạo cảm giác gương mặt sáng và nụ cười có điểm nhấn.',
        'icon' => 'fa-solid fa-camera-retro',
    ],
    [
        'title' => 'Dáng chuẩn cá nhân hóa',
        'desc' => 'Không làm theo một form có sẵn. Mỗi case được cân đối theo gương mặt, môi, da mặt và phong cách riêng của từng khách hàng.',
        'icon' => 'fa-solid fa-gem',
    ],
];

$reasons = [
    [
        'title' => 'Chuyên sâu về răng sứ thẩm mỹ',
        'desc' => 'Top Dental tập trung mạnh vào nhóm dịch vụ tạo hình nụ cười, từ thăm khám, mockup, chọn dáng răng đến theo dõi sau gắn.',
        'icon' => 'fa-solid fa-tooth',
    ],
    [
        'title' => 'Tư vấn rõ dáng răng trước khi làm',
        'desc' => 'Khách hàng được trao đổi kỹ về style răng, độ trắng, độ dài và tổng thể đường cười trước khi vào bước xử lý.',
        'icon' => 'fa-solid fa-comments',
    ],
    [
        'title' => 'Tối ưu thẩm mỹ và sự tự nhiên',
        'desc' => 'Mục tiêu không chỉ là trắng và đều mà là đẹp sang, mềm, hợp gương mặt và không bị giả nể.',
        'icon' => 'fa-solid fa-face-smile-beam',
    ],
    [
        'title' => 'Chăm sóc sau làm răng rõ ràng',
        'desc' => 'Sau khi hoàn tất, phòng khám vẫn theo dõi, hẹn tái khám và hướng dẫn chăm sóc để duy trì kết quả lâu dài.',
        'icon' => 'fa-solid fa-heart-circle-check',
    ],
];

$faqs = [
    [
        'q' => 'Răng sứ dáng hot girl là gì?',
        'a' => 'Đây là cách gọi của form răng ưu tiên đường cong nụ cười mềm, răng đều và thanh, tổng thể sang nhưng vẫn giữ cảm giác tự nhiên khi nói chuyện và chụp ảnh.',
    ],
    [
        'q' => 'Có phải ai cũng hợp cùng một dáng răng?',
        'a' => 'Không. Top Dental luôn cân đối theo gương mặt, độ dày môi, nền răng và mong muốn thẩm mỹ để chọn form răng hợp nhất cho từng người.',
    ],
    [
        'q' => 'Trước khi làm có được xem định hướng nụ cười không?',
        'a' => 'Có. Ở bước tư vấn, bác sĩ sẽ phân tích tổng thể nụ cười và trao đổi rõ style mong muốn trước khi quyết định phương án.',
    ],
    [
        'q' => 'Trang ads này dùng để đặt lịch nhanh được không?',
        'a' => 'Được. Bạn có thể gọi trực tiếp hotline hoặc bấm nút đặt lịch để phòng khám liên hệ tư vấn riêng trong khung giờ phù hợp.',
    ],
];

$seo = front_editor_page_seo('rang-su-dang-hot-girl-da-nang', [
    'title' => 'Top Dental Đà Nẵng • Chuyên răng sứ dáng hot girl hàng đầu',
    'description' => 'Landing page quảng cáo cho Top Dental Đà Nẵng, tập trung giới thiệu răng sứ dáng hot girl, gallery ảnh, quy trình tư vấn và CTA đặt lịch nhanh.',
]);
$title = (string) ($seo['title'] ?? '');
$description = (string) ($seo['description'] ?? '');
$seoKeywords = (string) ($seo['keywords'] ?? '');
$heroImage = $heroImages[0] ?? '';
$galleryCards = [];
for ($i = 0; $i < 8; $i++) {
    $galleryCards[] = $galleryImages[$i % count($galleryImages)];
}
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars((string) ($seo['canonical_path'] ?? '/rang-su-dang-hot-girl-da-nang'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root{
        --bg:#eef6ff;
        --surface:rgba(255,255,255,0.96);
        --surface-2:rgba(255,255,255,0.90);
        --border:rgba(37,99,235,0.14);
        --text:rgba(15,23,42,0.96);
        --muted:rgba(15,23,42,0.68);
        --brand:#1e40af;
        --brand-2:#3b82f6;
        --brand-3:#60a5fa;
        --brand-light:#dbeafe;
        --shadow:0 18px 55px rgba(37,99,235,0.16);
        --shadow-lg:0 28px 90px rgba(15,23,42,0.22);
        --radius:14px;
        --radius-lg:24px;
        --max:1200px;
        --hero:url("<?php echo htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8'); ?>");
      }
      *{ box-sizing:border-box; }
      html{ height:100%; scroll-behavior:smooth; }
      body{
        min-height:100%;
        margin:0;
        font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        color:var(--text);
        background:
          radial-gradient(900px 520px at 15% 0%, rgba(96,165,250,0.24), transparent 62%),
          radial-gradient(900px 520px at 85% 8%, rgba(30,64,175,0.18), transparent 62%),
          linear-gradient(180deg, #ffffff 0%, var(--bg) 60%, #ffffff 100%);
      }
      a{ color:inherit; text-decoration:none; }
      img{ display:block; max-width:100%; }
      .container{ width:min(100% - 32px, var(--max)); margin:0 auto; }
      .topbar{
        position:sticky;
        top:0;
        z-index:60;
        backdrop-filter:blur(14px);
        background:linear-gradient(180deg, rgba(255,255,255,0.86), rgba(255,255,255,0.66));
        border-bottom:1px solid var(--border);
      }
      .nav{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        padding:14px 0;
      }
      .brand{
        display:flex;
        align-items:center;
        gap:12px;
      }
      .brand-mark{
        width:46px;
        height:46px;
        border-radius:16px;
        background:radial-gradient(18px 18px at 30% 25%, rgba(255,255,255,0.82), transparent 60%), linear-gradient(135deg, var(--brand-2), var(--brand));
        box-shadow:0 20px 50px rgba(37,99,235,0.22);
        display:flex;
        align-items:center;
        justify-content:center;
      }
      .brand-mark i{ color:#fff; font-size:21px; }
      .brand-copy strong{
        display:block;
        font-family:"Playfair Display", serif;
        letter-spacing:-0.02em;
        color:var(--brand);
        font-size:20px;
      }
      .brand-copy span{ color:var(--muted); font-size:13px; }
      .nav-actions{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
      }
      .pill{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 12px;
        border-radius:999px;
        border:1px solid var(--border);
        background:var(--brand-light);
        color:var(--brand);
        font-size:13px;
        font-weight:700;
      }
      .btn{
        appearance:none;
        border:0;
        cursor:pointer;
        border-radius:12px;
        padding:12px 16px;
        font-weight:700;
        font-size:14px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:10px;
        transition:transform 160ms ease, filter 160ms ease, background 160ms ease, border-color 160ms ease;
      }
      .btn:hover{ filter:brightness(1.02); }
      .btn:active{ transform:translateY(1px); }
      .btn-primary{
        background:var(--brand);
        color:#fff;
        box-shadow:0 18px 60px rgba(37,99,235,0.32);
      }
      .btn-ghost{
        background:#fff;
        color:var(--brand);
        border:1px solid var(--border);
      }
      .btn-light{
        background:rgba(255,255,255,0.12);
        color:rgba(255,255,255,0.96);
        border:1px solid rgba(255,255,255,0.18);
      }
      .hero{
        position:relative;
        overflow:hidden;
      }
      .hero-slider{
        position:absolute;
        inset:0;
        display:flex;
        transition:transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      .hero-slide{
        min-width:100%;
        height:100%;
        background-size:cover;
        background-position:center;
      }
      .hero-inner{
        position:relative;
        padding:120px 0 86px;
        min-height:92vh;
        display:flex;
        align-items:center;
      }
      .hero-grid{
        display:grid;
        grid-template-columns:minmax(0, 1.12fr) minmax(320px, 0.88fr);
        gap:22px;
        align-items:end;
      }
      .hero-copy{
        padding:28px 24px;
        border-radius:var(--radius-lg);
        background:rgba(0,0,0,0.34);
        border:1px solid rgba(255,255,255,0.14);
        box-shadow:var(--shadow-lg);
        color:rgba(255,255,255,0.96);
        backdrop-filter:blur(16px);
      }
      .hero-kicker{
        display:inline-flex;
        align-items:center;
        gap:10px;
        font-weight:800;
        font-size:12px;
        letter-spacing:0.16em;
        text-transform:uppercase;
        color:rgba(255,255,255,0.84);
      }
      .hero-kicker .dot{
        width:8px;
        height:8px;
        border-radius:999px;
        background:#93c5fd;
        box-shadow:0 0 0 4px rgba(147,197,253,0.18);
      }
      .hero-copy h1{
        margin:14px 0 12px;
        font-family:"Playfair Display", serif;
        font-size:clamp(38px, 4.6vw, 62px);
        line-height:1.04;
        letter-spacing:-0.04em;
      }
      .hero-copy p{
        margin:0;
        max-width:66ch;
        line-height:1.8;
        color:rgba(255,255,255,0.86);
      }
      .hero-actions{
        margin-top:22px;
        display:flex;
        flex-wrap:wrap;
        gap:12px;
      }
      .hero-points{
        margin-top:18px;
        display:grid;
        grid-template-columns:repeat(3, minmax(0,1fr));
        gap:10px;
      }
      .hero-point{
        border-radius:14px;
        border:1px solid rgba(255,255,255,0.14);
        background:rgba(255,255,255,0.10);
        padding:12px;
        font-size:13px;
        line-height:1.6;
      }
      .hero-panel{
        border-radius:var(--radius-lg);
        background:rgba(255,255,255,0.12);
        border:1px solid rgba(255,255,255,0.18);
        box-shadow:0 20px 65px rgba(0,0,0,0.18);
        color:#fff;
        backdrop-filter:blur(16px);
        overflow:hidden;
      }
      .hero-panel-head{
        padding:18px 18px 10px;
      }
      .hero-panel h3{
        margin:0 0 8px;
        font-size:20px;
      }
      .hero-panel p{
        margin:0;
        color:rgba(255,255,255,0.82);
        font-size:14px;
        line-height:1.7;
      }
      .hero-stats{
        display:grid;
        gap:10px;
        padding:0 18px 18px;
      }
      .hero-stat{
        display:grid;
        gap:4px;
        padding:14px;
        border-radius:16px;
        background:rgba(255,255,255,0.10);
        border:1px solid rgba(255,255,255,0.14);
      }
      .hero-stat strong{
        font-family:"Playfair Display", serif;
        font-size:30px;
        line-height:1;
      }
      .hero-stat span{
        color:rgba(255,255,255,0.84);
        font-size:13px;
        line-height:1.5;
      }
      .hero-slider-dots{
        position:absolute;
        left:50%;
        bottom:28px;
        transform:translateX(-50%);
        display:flex;
        gap:10px;
        z-index:10;
      }
      .hero-slider-dot{
        width:11px;
        height:11px;
        border-radius:999px;
        border:0;
        background:rgba(255,255,255,0.42);
        cursor:pointer;
      }
      .hero-slider-dot.active{
        background:#fff;
        transform:scale(1.18);
      }
      .section{ padding:42px 0; }
      .section h2{
        margin:0 0 12px;
        text-align:center;
        font-family:"Playfair Display", serif;
        font-size:clamp(28px, 2.8vw, 40px);
        letter-spacing:-0.03em;
      }
      .section-lead{
        margin:0 auto 18px;
        max-width:74ch;
        text-align:center;
        color:var(--muted);
        line-height:1.8;
        font-size:15px;
      }
      .trust-grid,
      .style-grid,
      .reason-grid,
      .service-grid,
      .faq-grid{
        display:grid;
        gap:14px;
      }
      .trust-grid{ grid-template-columns:repeat(4, minmax(0,1fr)); }
      .style-grid{ grid-template-columns:repeat(3, minmax(0,1fr)); }
      .reason-grid{ grid-template-columns:repeat(4, minmax(0,1fr)); }
      .service-grid{ grid-template-columns:repeat(3, minmax(0,1fr)); }
      .faq-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
      .trust-card,
      .style-card,
      .reason-card,
      .service-card,
      .faq-card,
      .cta-banner,
      .journey-card,
      .gallery-card,
      .compare-card{
        border-radius:var(--radius-lg);
        border:1px solid var(--border);
        background:var(--surface);
        box-shadow:var(--shadow);
      }
      .trust-card,
      .style-card,
      .reason-card,
      .faq-card,
      .compare-card{
        padding:18px;
      }
      .trust-card strong{
        display:block;
        font-family:"Playfair Display", serif;
        font-size:34px;
        color:var(--brand);
        margin-bottom:4px;
      }
      .trust-card span,
      .style-card p,
      .reason-card p,
      .faq-card p,
      .compare-card p{
        color:var(--muted);
        line-height:1.75;
        font-size:14px;
        margin:0;
      }
      .style-card i,
      .reason-card i,
      .faq-card i,
      .compare-card i{
        width:50px;
        height:50px;
        border-radius:16px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:var(--brand-light);
        color:var(--brand);
        font-size:20px;
        margin-bottom:12px;
      }
      .style-card h3,
      .reason-card h3,
      .service-card h3,
      .faq-card h3,
      .compare-card h3{
        margin:0 0 8px;
        font-size:17px;
      }
      .gallery-grid{
        display:grid;
        grid-template-columns:repeat(4, minmax(0,1fr));
        gap:14px;
      }
      .gallery-card{
        overflow:hidden;
        background:#fff;
      }
      .gallery-card img{
        width:100%;
        height:100%;
        min-height:220px;
        object-fit:cover;
      }
      .gallery-card.tall img{ min-height:320px; }
      .service-card{
        overflow:hidden;
      }
      .service-thumb{
        height:220px;
        background:linear-gradient(180deg, rgba(30,64,175,0.16), rgba(30,64,175,0.02)), var(--hero) center/cover no-repeat;
      }
      .service-body{
        padding:18px;
        display:grid;
        gap:10px;
      }
      .service-body p{
        margin:0;
        color:var(--muted);
        line-height:1.75;
        font-size:14px;
      }
      .journey{
        display:grid;
        grid-template-columns:minmax(0, 1.1fr) minmax(320px, 0.9fr);
        gap:18px;
        align-items:start;
      }
      .journey-card{
        padding:20px;
      }
      .timeline{
        display:grid;
        gap:14px;
      }
      .timeline-item{
        display:grid;
        grid-template-columns:54px minmax(0,1fr);
        gap:12px;
        align-items:start;
      }
      .timeline-step{
        width:54px;
        height:54px;
        border-radius:16px;
        display:flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(135deg, var(--brand-2), var(--brand));
        color:#fff;
        font-weight:800;
        box-shadow:0 12px 30px rgba(37,99,235,0.24);
      }
      .timeline-copy strong{
        display:block;
        margin-bottom:4px;
        font-size:15px;
      }
      .timeline-copy span{
        color:var(--muted);
        line-height:1.75;
        font-size:14px;
      }
      .compare-card{
        background:linear-gradient(180deg, rgba(30,64,175,0.06), rgba(59,130,246,0.10));
      }
      .compare-card blockquote{
        margin:0;
        font-family:"Playfair Display", serif;
        font-size:clamp(24px, 2.4vw, 34px);
        line-height:1.34;
        letter-spacing:-0.03em;
      }
      .compare-card .compare-list{
        margin-top:14px;
        display:grid;
        gap:10px;
      }
      .compare-line{
        display:flex;
        gap:10px;
        align-items:flex-start;
        color:var(--muted);
        font-size:14px;
        line-height:1.7;
      }
      .compare-line i{
        margin-top:4px;
        color:var(--brand);
      }
      .cta-banner{
        padding:24px;
        background:linear-gradient(135deg, #1e3a8a 0%, #1e40af 46%, #3b82f6 100%);
        color:#fff;
      }
      .cta-grid{
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        gap:18px;
        align-items:center;
      }
      .cta-banner h2{
        color:#fff;
        text-align:left;
        margin-bottom:8px;
      }
      .cta-banner p{
        margin:0;
        color:rgba(255,255,255,0.86);
        line-height:1.8;
      }
      .cta-actions{
        display:flex;
        gap:12px;
        flex-wrap:wrap;
      }
      .sticky-cta{
        position:fixed;
        left:12px;
        right:12px;
        bottom:12px;
        z-index:70;
        display:none;
        gap:10px;
        padding:10px;
        border-radius:16px;
        border:1px solid rgba(255,255,255,0.16);
        background:rgba(15,23,42,0.76);
        backdrop-filter:blur(16px);
        box-shadow:0 28px 80px rgba(0,0,0,0.28);
      }
      .sticky-cta .btn{
        flex:1 1 auto;
      }
      .site-footer{
        margin-top:34px;
        position:relative;
        overflow:hidden;
        color:rgba(255,255,255,0.95);
        background:linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%);
      }
      .site-footer::before{
        content:"";
        position:absolute;
        inset:0;
        background:var(--hero) center/cover no-repeat;
        filter:saturate(0.7) contrast(1.10);
        opacity:0.15;
        z-index:0;
        pointer-events:none;
      }
      .footer-layer{ position:relative; z-index:1; }
      .footer-cta{ border-bottom:1px solid rgba(255,255,255,0.10); background:rgba(0,0,0,0.34); }
      .footer-cta-inner{ padding:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
      .footer-cta-inner b{ font-family:"Playfair Display", serif; font-weight:600; color:rgba(255,255,255,0.92); }
      .footer-main{ padding:18px 0 10px; }
      .footer-cols{ display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:18px; }
      .footer-col h4{ margin:0 0 10px; font-size:14px; font-weight:700; color:rgba(255,255,255,0.92); padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.10); }
      .footer-link{ display:block; color:rgba(255,255,255,0.78); font-size:13px; padding:6px 0; }
      .footer-link:hover{ color:rgba(255,255,255,0.92); }
      .footer-contact{ display:grid; gap:8px; color:rgba(255,255,255,0.78); font-size:13px; }
      .footer-contact .item{ display:flex; gap:10px; align-items:flex-start; line-height:1.5; }
      .footer-contact i{ width:18px; margin-top:2px; text-align:center; color:rgba(255,255,255,0.90); }
      .footer-bottom{ border-top:1px solid rgba(255,255,255,0.10); padding:12px 0 14px; text-align:center; color:rgba(255,255,255,0.70); font-size:13px; }
      @media (max-width: 1024px){
        .hero-grid,
        .journey,
        .cta-grid{ grid-template-columns:1fr; }
        .trust-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .style-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .reason-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .service-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .faq-grid{ grid-template-columns:1fr; }
        .gallery-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .footer-cols{ grid-template-columns:repeat(2, minmax(0,1fr)); }
      }
      @media (max-width: 680px){
        .nav{ align-items:flex-start; flex-direction:column; }
        .nav-actions{ width:100%; }
        .pill{ display:none; }
        .nav-actions .btn{ flex:1 1 auto; }
        .hero-inner{ min-height:auto; padding:106px 0 84px; }
        .hero-copy{ padding:22px 18px; }
        .hero-points,
        .trust-grid,
        .style-grid,
        .reason-grid,
        .service-grid,
        .faq-grid{ grid-template-columns:1fr; }
        .gallery-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .gallery-card.tall img{ min-height:220px; }
        .footer-cols{ grid-template-columns:1fr; gap:12px; }
        .footer-cta-inner{ padding:12px 0; flex-direction:column; align-items:flex-start; gap:10px; }
        .footer-cta-inner .btn{ width:100%; justify-content:center; }
        .sticky-cta{ display:flex; }
      }
    </style>
  </head>
  <body>
    <header class="topbar">
      <div class="container nav">
        <a class="brand" href="/">
          <div class="brand-mark"><i class="fa-solid fa-tooth" aria-hidden="true"></i></div>
          <div class="brand-copy">
            <strong>Top Dental</strong>
            <span>Chuyên răng sứ dáng hot girl tại Đà Nẵng</span>
          </div>
        </a>
        <div class="nav-actions">
          <span class="pill"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> 46 Trần Tống - TP Đà Nẵng</span>
          <a class="btn btn-ghost" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $hotline) ?? $hotline, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?php echo htmlspecialchars($hotline, ENT_QUOTES, 'UTF-8'); ?></a>
          <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Đặt lịch tư vấn</a>
        </div>
      </div>
    </header>

    <section class="hero">
      <div class="hero-slider" id="heroSlider">
        <?php foreach ($heroImages as $slideUrl): ?>
          <div class="hero-slide" style="background-image: linear-gradient(90deg, rgba(15,23,42,0.78) 0%, rgba(15,23,42,0.56) 38%, rgba(15,23,42,0.28) 70%, rgba(15,23,42,0.54) 100%), url('<?php echo htmlspecialchars($slideUrl, ENT_QUOTES, 'UTF-8'); ?>');"></div>
        <?php endforeach; ?>
      </div>
      <div class="container hero-inner">
        <div class="hero-grid">
          <div class="hero-copy">
            <div class="hero-kicker"><span class="dot" aria-hidden="true"></span> Landing page quảng cáo • Top Dental Đà Nẵng</div>
            <h1>Nha khoa chuyên răng sứ dáng hot girl hàng đầu Đà Nẵng</h1>
            <p>Nếu bạn đang tìm một nụ cười sáng, trong, tự nhiên và lên hình đẹp, Top Dental là điểm hẹn được nhiều khách hàng ưu tiên khi muốn làm răng sứ theo phong cách nữ tính, thanh và có thần thái.</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Nhận tư vấn dáng răng</a>
              <a class="btn btn-light" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $hotline) ?? $hotline, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-phone-volume" aria-hidden="true"></i> Gọi hotline ngay</a>
              <a class="btn btn-light" href="#thu-vien-anh"><i class="fa-solid fa-images" aria-hidden="true"></i> Xem thư viện ảnh</a>
            </div>
            <div class="hero-points">
              <div class="hero-point"><strong>Form răng hot girl</strong><br>Đẹp sang, mềm, trong, hợp gương mặt.</div>
              <div class="hero-point"><strong>Tư vấn rõ style</strong><br>Chốt style răng trước khi làm, tránh bị giả nể.</div>
              <div class="hero-point"><strong>Tập trung conversion</strong><br>Đặt lịch nhanh, gọi ngay và tư vấn riêng.</div>
            </div>
          </div>
          <aside class="hero-panel">
            <div class="hero-panel-head">
              <h3>Vì sao ads này chốt nhanh</h3>
              <p>Thông điệp rõ, hình ảnh sang, CTA mạnh và nội dung tập trung đúng mong muốn của nhóm khách hàng yêu thích răng sứ dáng hot girl.</p>
            </div>
            <div class="hero-stats">
              <div class="hero-stat">
                <strong>15+</strong>
                <span>Năm đồng hành trong nha khoa thẩm mỹ và thiết kế nụ cười cá nhân hóa.</span>
              </div>
              <div class="hero-stat">
                <strong>Top</strong>
                <span>Định hướng thương hiệu Top Dental tại Đà Nẵng, tập trung mạnh vào răng sứ thẩm mỹ.</span>
              </div>
              <div class="hero-stat">
                <strong>1:1</strong>
                <span>Tư vấn riêng về style răng, tổng thể khuôn cười và mục tiêu lên hình của từng khách hàng.</span>
              </div>
            </div>
          </aside>
        </div>
      </div>
      <div class="hero-slider-dots" id="heroSliderDots"></div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Top Dental phù hợp với ai</h2>
        <p class="section-lead">Trang này được viết để chạm đúng nhóm khách hàng muốn có nụ cười sáng, đẹp thanh, dễ chụp ảnh, quay video và giao tiếp tự tin hơn trong công việc và cuộc sống.</p>
        <div class="trust-grid">
          <article class="trust-card">
            <strong>1</strong>
            <span>Bạn muốn làm răng sứ nhưng sợ bị giả, bị dày hoặc quá trắng.</span>
          </article>
          <article class="trust-card">
            <strong>2</strong>
            <span>Bạn thích form răng hot girl, nữ tính, đẹp sang và hợp xu hướng hiện tại.</span>
          </article>
          <article class="trust-card">
            <strong>3</strong>
            <span>Bạn ưu tiên một nơi tư vấn kỹ, giải thích rõ và có định hướng thẩm mỹ cá nhân hóa.</span>
          </article>
          <article class="trust-card">
            <strong>4</strong>
            <span>Bạn cần một landing page chuyên nghiệp để chạy ads và chốt lịch nhanh tại Đà Nẵng.</span>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>3 dáng nụ cười được quan tâm nhiều</h2>
        <p class="section-lead">Khách hàng thường đến Top Dental với một kỳ vọng rất cụ thể: nụ cười phải sáng, có thần thái và phải hợp gương mặt. Vì thế phần tư vấn style răng là một điểm then chốt.</p>
        <div class="style-grid">
          <?php foreach ($smileStyles as $style): ?>
            <article class="style-card">
              <i class="<?php echo htmlspecialchars($style['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
              <h3><?php echo htmlspecialchars($style['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
              <p><?php echo htmlspecialchars($style['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="thu-vien-anh">
      <div class="container">
        <h2>Thư viện ảnh không gian và phong cách</h2>
        <p class="section-lead">Một landing page ads mạnh cần có gallery đẹp và sạch. Top Dental có thể sử dụng ngay bộ ảnh hiện có của thư viện để tạo cảm giác chuyên nghiệp, sang và đáng tin khi lên quảng cáo.</p>
        <div class="gallery-grid">
          <?php foreach ($galleryCards as $index => $imageUrl): ?>
            <article class="gallery-card<?php echo ($index % 5 === 0) ? ' tall' : ''; ?>">
              <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Thư viện ảnh Top Dental <?php echo $index + 1; ?>">
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Vì sao Top Dental dễ chốt nhóm răng sứ hot girl</h2>
        <p class="section-lead">Landing page này tập trung vào những lý do mà khách hàng cần trước khi đặt lịch: tin tưởng, thẩm mỹ, sự rõ ràng và trải nghiệm tư vấn có chiều sâu.</p>
        <div class="reason-grid">
          <?php foreach ($reasons as $reason): ?>
            <article class="reason-card">
              <i class="<?php echo htmlspecialchars($reason['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
              <h3><?php echo htmlspecialchars($reason['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
              <p><?php echo htmlspecialchars($reason['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="dich-vu">
      <div class="container">
        <h2>Dịch vụ nổi bật để up-sell và tạo tin tưởng</h2>
        <p class="section-lead">Ngoài răng sứ thẩm mỹ, trang quảng cáo có thể nhắc thêm nhóm dịch vụ liên quan để tăng độ dày thương hiệu và giúp khách hàng thấy phòng khám có chuyên môn tổng thể.</p>
        <div class="service-grid">
          <?php if (!empty($services)): ?>
            <?php foreach ($services as $service): ?>
              <?php
                $thumb = $service['img'] !== '' ? $service['img'] : $galleryImages[array_rand($galleryImages)];
                $excerpt = $service['excerpt'] !== '' ? $service['excerpt'] : 'Dịch vụ được tư vấn rõ ràng, theo dõi sát và hướng đến kết quả đẹp tự nhiên, phù hợp mục tiêu từng khách hàng.';
              ?>
              <article class="service-card">
                <div class="service-thumb" style="background-image: linear-gradient(180deg, rgba(30,64,175,0.16), rgba(30,64,175,0.02)), url('<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>');"></div>
                <div class="service-body">
                  <h3><?php echo htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                  <p><?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?></p>
                  <div class="hero-actions" style="margin-top:4px;">
                    <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Đặt lịch</a>
                    <?php if (function_exists('admin_front_is_logged_in') && admin_front_is_logged_in()): ?>
                      <a class="btn btn-ghost" href="/admin/content_post_edit.php?id=<?php echo (int) $service['id']; ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Sửa</a>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="journey">
          <div class="journey-card">
            <h2 style="text-align:left; margin-bottom:8px;">Quy trình chốt lịch từ ads đến tư vấn</h2>
            <p class="section-lead" style="text-align:left; margin:0 0 16px; max-width:none;">Một trang quảng cáo hiệu quả không chỉ đẹp mà còn phải dẫn được khách hàng từ quan tâm đến hành động. Vì thế luồng nội dung dưới đây được sắp xếp theo đúng tâm lý mua dịch vụ răng sứ thẩm mỹ.</p>
            <div class="timeline">
              <div class="timeline-item">
                <div class="timeline-step">01</div>
                <div class="timeline-copy">
                  <strong>Khách hàng nhìn thấy thông điệp đúng mong muốn</strong>
                  <span>Thông điệp "răng sứ dáng hot girl" đánh trúng nhu cầu về nụ cười đẹp sang, lên hình đẹp và có thần thái.</span>
                </div>
              </div>
              <div class="timeline-item">
                <div class="timeline-step">02</div>
                <div class="timeline-copy">
                  <strong>Gallery và proof tạo cảm giác tin tưởng</strong>
                  <span>Thư viện ảnh, badge thương hiệu và các block giải thích rõ ràng giúp người xem không bị nghi ngờ về mức độ chuyên nghiệp.</span>
                </div>
              </div>
              <div class="timeline-item">
                <div class="timeline-step">03</div>
                <div class="timeline-copy">
                  <strong>CTA đặt lịch ở nhiều điểm chạm</strong>
                  <span>Nút gọi ngay, đặt lịch và phần footer/CTA dày để khách hàng ra quyết định nhanh hơn mà không phải tìm lại thông tin liên hệ.</span>
                </div>
              </div>
              <div class="timeline-item">
                <div class="timeline-step">04</div>
                <div class="timeline-copy">
                  <strong>Tư vấn 1:1 để chuyển đổi</strong>
                  <span>Sau khi để lại thông tin, phòng khám có thể tiếp cận bằng kịch bản tư vấn riêng theo style răng và mục tiêu thẩm mỹ của từng khách.</span>
                </div>
              </div>
            </div>
          </div>
          <aside class="compare-card">
            <i class="fa-solid fa-crown" aria-hidden="true"></i>
            <h3>Top Dental cần thể hiện gì trên ads</h3>
            <blockquote>"Nụ cười đẹp là nụ cười hợp gương mặt, đẹp lên hình và vẫn giữ được cảm giác sang tự nhiên."</blockquote>
            <div class="compare-list">
              <div class="compare-line"><i class="fa-solid fa-check"></i><span>Nha khoa tại Đà Nẵng tập trung mạnh vào răng sứ thẩm mỹ.</span></div>
              <div class="compare-line"><i class="fa-solid fa-check"></i><span>Thông điệp dễ hiểu, dễ nhớ và đúng insight nhóm khách hàng nữ.</span></div>
              <div class="compare-line"><i class="fa-solid fa-check"></i><span>Hình ảnh sạch, màu xanh chuyên nghiệp và bố cục chuẩn landing page.</span></div>
              <div class="compare-line"><i class="fa-solid fa-check"></i><span>Đặt lịch nhanh qua hotline, form liên hệ hoặc CTA cuối trang.</span></div>
            </div>
            <a class="btn btn-primary" href="/lien-he.php" style="margin-top:16px;"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Nhận kịch bản tư vấn</a>
          </aside>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Câu hỏi thường gặp trước khi đặt lịch</h2>
        <p class="section-lead">Phần FAQ giúp giảm cản trở tâm lý, đặc biệt với nhóm khách hàng quảng cáo lạnh từ Facebook, TikTok hoặc Google Ads.</p>
        <div class="faq-grid">
          <?php foreach ($faqs as $faq): ?>
            <article class="faq-card">
              <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
              <h3><?php echo htmlspecialchars($faq['q'], ENT_QUOTES, 'UTF-8'); ?></h3>
              <p><?php echo htmlspecialchars($faq['a'], ENT_QUOTES, 'UTF-8'); ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="cta-banner">
          <div class="cta-grid">
            <div>
              <h2>Muốn được tư vấn form răng hot girl phù hợp gương mặt?</h2>
              <p>Bạn có thể đặt lịch ngay với Top Dental để được trao đổi riêng về dáng răng, tone răng, tổng thể đường cười và lộ trình thực hiện phù hợp nhất tại Đà Nẵng.</p>
            </div>
            <div class="cta-actions">
              <a class="btn btn-ghost" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $hotline) ?? $hotline, ENT_QUOTES, 'UTF-8'); ?>" style="background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.20);"><i class="fa-solid fa-phone"></i> Gọi ngay</a>
              <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check"></i> Đặt lịch ngay</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <?php include __DIR__ . '/Tem/footer.php'; ?>

    <div class="sticky-cta" aria-label="mobile cta">
      <a class="btn btn-ghost" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $hotline) ?? $hotline, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-phone"></i> Gọi ngay</a>
      <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check"></i> Đặt lịch</a>
    </div>

    <script>
      (function(){
        function boot(){
          var slider = document.getElementById("heroSlider");
          var dotsContainer = document.getElementById("heroSliderDots");
          if (!slider || !dotsContainer) return;
          var slides = slider.querySelectorAll(".hero-slide");
          if (!slides.length) return;
          var current = 0;
          var timer = null;
          for (var i = 0; i < slides.length; i++) {
            var dot = document.createElement("button");
            dot.type = "button";
            dot.className = "hero-slider-dot" + (i === 0 ? " active" : "");
            dot.setAttribute("aria-label", "Slide " + (i + 1));
            (function(index, button){
              button.addEventListener("click", function(){
                go(index);
              });
            })(i, dot);
            dotsContainer.appendChild(dot);
          }
          var dots = dotsContainer.querySelectorAll(".hero-slider-dot");
          function render(){
            slider.style.transform = "translateX(" + (-current * 100) + "%)";
            dots.forEach(function(dot, index){
              dot.classList.toggle("active", index === current);
            });
          }
          function go(index){
            current = index;
            if (current < 0) current = slides.length - 1;
            if (current >= slides.length) current = 0;
            render();
            reset();
          }
          function next(){ go(current + 1); }
          function start(){ timer = window.setInterval(next, 5000); }
          function reset(){
            if (timer) window.clearInterval(timer);
            start();
          }
          render();
          start();
        }
        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
        else boot();
      })();
    </script>
    <?php if (function_exists('front_editor_render')) { front_editor_render('rang-su-dang-hot-girl-da-nang'); } ?>
    <?php
      front_admin_render_edit_bar([
        ['label' => 'Admin', 'href' => '/admin/dashboard.php', 'icon' => 'fa-solid fa-shield-halved'],
        ['label' => 'Quản lý nội dung', 'href' => '/admin/content.php', 'icon' => 'fa-solid fa-pen-to-square'],
      ]);
    ?>
  </body>
</html>
