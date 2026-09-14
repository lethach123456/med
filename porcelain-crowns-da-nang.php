<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/front_admin.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
front_editor_page_maybe_redirect('porcelain-crowns-da-nang');

$hotline = site_hotline('+84 777 265 444');
$email = site_email('info@topdentaldanang.com');
$address = site_address('46 Tran Tong, Da Nang, Vietnam');
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

// Static hero slider with 1 sample image. You can replace this URL directly in this file.
$heroImages = [
    '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
];
$heroImage = $heroImages[0] ?? '';
$galleryCards = [];
for ($i = 0; $i < 8; $i++) {
    $galleryCards[] = $galleryImages[$i % count($galleryImages)];
}

$benefits = [
    [
        'title' => 'Natural-looking smile design',
        'desc' => 'We focus on smile proportions, tooth shape, lip support and facial harmony, not just a brighter shade.',
        'icon' => 'fa-solid fa-sparkles',
    ],
    [
        'title' => 'Porcelain specialists in Da Nang',
        'desc' => 'Top Dental positions this service as a signature treatment for clients seeking premium cosmetic improvement.',
        'icon' => 'fa-solid fa-tooth',
    ],
    [
        'title' => 'Consultation before commitment',
        'desc' => 'You get a clear plan, treatment logic and realistic expectations before deciding to move forward.',
        'icon' => 'fa-solid fa-comments',
    ],
];

$reasons = [
    [
        'title' => 'Made for image-conscious clients',
        'desc' => 'Ideal for professionals, creators, entrepreneurs and anyone who wants a polished, camera-friendly smile.',
        'icon' => 'fa-solid fa-camera-retro',
    ],
    [
        'title' => 'Designed around facial balance',
        'desc' => 'We refine shape, length and brightness so the final look fits your face instead of looking copied from someone else.',
        'icon' => 'fa-solid fa-face-smile-beam',
    ],
    [
        'title' => 'Clear treatment pathway',
        'desc' => 'Every step is explained from assessment and shade selection to fitting, review and aftercare.',
        'icon' => 'fa-solid fa-file-medical',
    ],
    [
        'title' => 'Ongoing follow-up support',
        'desc' => 'Post-treatment guidance helps you keep the smile stable, bright and comfortable for the long term.',
        'icon' => 'fa-solid fa-heart-circle-check',
    ],
];

$serviceCards = [
    [
        'title' => 'Porcelain crowns in Da Nang',
        'desc' => 'A strong option for restoring shape, improving color and rebuilding confidence when teeth are worn, uneven or discolored.',
    ],
    [
        'title' => 'Smile design for feminine aesthetics',
        'desc' => 'For clients who want a softer, refined and photogenic smile with balanced tooth shape and a graceful smile arc.',
    ],
    [
        'title' => 'Premium consultation and planning',
        'desc' => 'A detailed consultation that helps you understand materials, timing, costs and the most suitable aesthetic direction.',
    ],
];

$processSteps = [
    [
        'step' => '01',
        'title' => 'Private consultation',
        'desc' => 'We assess your smile goals, current dental condition and the look you want to achieve.',
    ],
    [
        'step' => '02',
        'title' => 'Smile analysis and planning',
        'desc' => 'Tooth shape, smile line, lip support and shade are reviewed before any final decision is made.',
    ],
    [
        'step' => '03',
        'title' => 'Treatment execution',
        'desc' => 'Your porcelain work is completed according to the agreed plan with attention to fit, comfort and aesthetics.',
    ],
    [
        'step' => '04',
        'title' => 'Review and aftercare',
        'desc' => 'We guide you through maintenance, review the final look and support you after the treatment is complete.',
    ],
];

$faqs = [
    [
        'q' => 'Do you focus on porcelain crowns or veneers?',
        'a' => 'This page is built for English keywords around porcelain crowns in Da Nang, while also speaking to clients searching for porcelain veneers and smile design options.',
    ],
    [
        'q' => 'Can I ask for a natural but bright result?',
        'a' => 'Yes. Many clients want a brighter smile without the overly fake look, so shape, shade and facial harmony are planned together.',
    ],
    [
        'q' => 'Is this page meant for ads and SEO?',
        'a' => 'Yes. The structure is optimized for conversion and for English search terms related to porcelain crowns and cosmetic dental work in Da Nang.',
    ],
    [
        'q' => 'Can I book a consultation directly?',
        'a' => 'Yes. You can call the clinic immediately or use the contact button to request a consultation at a suitable time.',
    ],
];

$seo = front_editor_page_seo('porcelain-crowns-da-nang', [
    'title' => 'Top Dental Da Nang | Porcelain Crowns In Da Nang',
    'description' => 'English landing page for Top Dental Da Nang focused on porcelain crowns, smile design, consultation and premium cosmetic dental care in Da Nang.',
]);
$title = (string) ($seo['title'] ?? '');
$description = (string) ($seo['description'] ?? '');
$seoKeywords = (string) ($seo['keywords'] ?? '');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars((string) ($seo['canonical_path'] ?? '/porcelain-crowns-da-nang'), ENT_QUOTES, 'UTF-8'); ?>">
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
        --brand-light:#dbeafe;
        --shadow:0 18px 55px rgba(37,99,235,0.16);
        --shadow-lg:0 28px 90px rgba(15,23,42,0.22);
        --radius:14px;
        --radius-lg:24px;
        --max:1200px;
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
        background:rgba(0,0,0,0.36);
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
      .hero-panel-head{ padding:18px 18px 10px; }
      .hero-panel h3{ margin:0 0 8px; font-size:20px; }
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
      .benefit-grid,
      .reason-grid,
      .service-grid,
      .faq-grid{
        display:grid;
        gap:14px;
      }
      .trust-grid{ grid-template-columns:repeat(4, minmax(0,1fr)); }
      .benefit-grid{ grid-template-columns:repeat(3, minmax(0,1fr)); }
      .reason-grid{ grid-template-columns:repeat(4, minmax(0,1fr)); }
      .service-grid{ grid-template-columns:repeat(3, minmax(0,1fr)); }
      .faq-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
      .trust-card,
      .benefit-card,
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
      .benefit-card,
      .reason-card,
      .faq-card,
      .compare-card{ padding:18px; }
      .trust-card strong{
        display:block;
        font-family:"Playfair Display", serif;
        font-size:34px;
        color:var(--brand);
        margin-bottom:4px;
      }
      .trust-card span,
      .benefit-card p,
      .reason-card p,
      .service-card p,
      .faq-card p,
      .compare-card p{
        color:var(--muted);
        line-height:1.75;
        font-size:14px;
        margin:0;
      }
      .benefit-card i,
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
      .benefit-card h3,
      .reason-card h3,
      .service-card h3,
      .faq-card h3,
      .compare-card h3{ margin:0 0 8px; font-size:17px; }
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
        padding:18px;
        display:grid;
        gap:10px;
      }
      .journey{
        display:grid;
        grid-template-columns:minmax(0, 1.1fr) minmax(320px, 0.9fr);
        gap:18px;
        align-items:start;
      }
      .journey-card{ padding:20px; }
      .timeline{ display:grid; gap:14px; }
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
      .compare-list{
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
      .site-footer{
        margin-top:34px;
        color:rgba(255,255,255,0.94);
        background:linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%);
      }
      .footer-inner{
        padding:26px 0 20px;
        display:grid;
        grid-template-columns:minmax(0, 1.2fr) repeat(2, minmax(0, 0.9fr));
        gap:20px;
      }
      .footer-brand strong{
        display:block;
        font-family:"Playfair Display", serif;
        font-size:26px;
        margin-bottom:8px;
      }
      .footer-brand p,
      .footer-links a,
      .footer-contact .item{
        color:rgba(255,255,255,0.82);
        line-height:1.75;
        font-size:14px;
      }
      .footer-links h4,
      .footer-contact h4{
        margin:0 0 10px;
        font-size:15px;
      }
      .footer-links a{
        display:block;
        padding:6px 0;
      }
      .footer-contact{
        display:grid;
        gap:8px;
      }
      .footer-contact .item{
        display:flex;
        gap:10px;
        align-items:flex-start;
      }
      .footer-contact i{
        width:18px;
        margin-top:2px;
        text-align:center;
      }
      .footer-bottom{
        border-top:1px solid rgba(255,255,255,0.12);
        padding:12px 0 16px;
        text-align:center;
        color:rgba(255,255,255,0.72);
        font-size:13px;
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
      .sticky-cta .btn{ flex:1 1 auto; }
      @media (max-width: 1024px){
        .hero-grid,
        .journey,
        .cta-grid,
        .footer-inner{ grid-template-columns:1fr; }
        .trust-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .benefit-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .reason-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .service-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .faq-grid{ grid-template-columns:1fr; }
        .gallery-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
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
        .benefit-grid,
        .reason-grid,
        .service-grid,
        .faq-grid{ grid-template-columns:1fr; }
        .gallery-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
        .gallery-card.tall img{ min-height:220px; }
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
            <span>Porcelain crowns and smile design in Da Nang</span>
          </div>
        </a>
        <div class="nav-actions">
          <span class="pill"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Da Nang, Vietnam</span>
          <a class="btn btn-ghost" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $hotline) ?? $hotline, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?php echo htmlspecialchars($hotline, ENT_QUOTES, 'UTF-8'); ?></a>
          <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book consultation</a>
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
            <div class="hero-kicker"><span class="dot" aria-hidden="true"></span> English landing page for ads and SEO</div>
            <h1>Porcelain Crowns In Da Nang For A More Refined, Natural Smile</h1>
            <p>If you are searching for porcelain crowns in Da Nang or a premium smile makeover with a softer, more polished aesthetic, Top Dental is positioned to help you plan the right cosmetic direction with clarity and confidence.</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Request consultation</a>
              <a class="btn btn-light" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $hotline) ?? $hotline, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-phone-volume" aria-hidden="true"></i> Call now</a>
              <a class="btn btn-light" href="#gallery"><i class="fa-solid fa-images" aria-hidden="true"></i> View gallery</a>
            </div>
            <div class="hero-points">
              <div class="hero-point"><strong>English keyword ready</strong><br>Built around porcelain crowns and smile design in Da Nang.</div>
              <div class="hero-point"><strong>Conversion focused</strong><br>Multiple CTAs, proof blocks and consultation prompts.</div>
              <div class="hero-point"><strong>Front editor enabled</strong><br>Admin can edit this landing page directly on the front end.</div>
            </div>
          </div>
          <aside class="hero-panel">
            <div class="hero-panel-head">
              <h3>Why this page works</h3>
              <p>It combines premium branding, trust-building content, English ad copy and a clean visual story tailored for dental cosmetic intent.</p>
            </div>
            <div class="hero-stats">
              <div class="hero-stat">
                <strong>Premium</strong>
                <span>Positioned for clients looking for a more refined cosmetic result rather than a generic dental page.</span>
              </div>
              <div class="hero-stat">
                <strong>Targeted</strong>
                <span>Written for searchers looking for porcelain crowns, porcelain veneers and cosmetic dentistry in Da Nang.</span>
              </div>
              <div class="hero-stat">
                <strong>Editable</strong>
                <span>Designed to be updated quickly through the existing front editor workflow already used on your project.</span>
              </div>
            </div>
          </aside>
        </div>
      </div>
      <div class="hero-slider-dots" id="heroSliderDots"></div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Who this page is for</h2>
        <p class="section-lead">This landing page is built for clients who want a brighter, cleaner and more attractive smile in Da Nang without sacrificing natural facial harmony.</p>
        <div class="trust-grid">
          <article class="trust-card">
            <strong>1</strong>
            <span>You want porcelain crowns in Da Nang but need a more premium, trustworthy first impression.</span>
          </article>
          <article class="trust-card">
            <strong>2</strong>
            <span>You care about shape, softness, smile line and how your teeth look in photos and conversations.</span>
          </article>
          <article class="trust-card">
            <strong>3</strong>
            <span>You want a consultation-led process rather than a one-size-fits-all cosmetic recommendation.</span>
          </article>
          <article class="trust-card">
            <strong>4</strong>
            <span>You need an English page that can run ads, support SEO and still feel elegant and credible.</span>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Why Top Dental stands out</h2>
        <p class="section-lead">A strong cosmetic dental page needs more than bright words. It needs a believable promise, a premium visual feel and a clear treatment logic that matches what international and English-speaking clients expect.</p>
        <div class="benefit-grid">
          <?php foreach ($benefits as $benefit): ?>
            <article class="benefit-card">
              <i class="<?php echo htmlspecialchars($benefit['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
              <h3><?php echo htmlspecialchars($benefit['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
              <p><?php echo htmlspecialchars($benefit['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="gallery">
      <div class="container">
        <h2>Gallery and brand feel</h2>
        <p class="section-lead">A premium dental landing page should feel clean, calm and visually consistent. The image gallery below helps support that first impression for both ads and organic traffic.</p>
        <div class="gallery-grid">
          <?php foreach ($galleryCards as $index => $imageUrl): ?>
            <article class="gallery-card<?php echo ($index % 5 === 0) ? ' tall' : ''; ?>">
              <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Top Dental gallery <?php echo $index + 1; ?>">
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Why clients choose porcelain work in Da Nang with Top Dental</h2>
        <p class="section-lead">This section speaks to practical decision-making: trust, aesthetics, communication and the confidence that the clinic understands beauty as well as treatment planning.</p>
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

    <section class="section">
      <div class="container">
        <h2>Core services highlighted on this page</h2>
        <p class="section-lead">Even when the page targets porcelain crowns in Da Nang, it helps to frame the clinic as a cosmetic smile destination rather than a single-service listing.</p>
        <div class="service-grid">
          <?php foreach ($serviceCards as $card): ?>
            <article class="service-card">
              <h3><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
              <p><?php echo htmlspecialchars($card['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
              <div class="hero-actions" style="margin-top:4px;">
                <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="journey">
          <div class="journey-card">
            <h2 style="text-align:left; margin-bottom:8px;">From search click to treatment confidence</h2>
            <p class="section-lead" style="text-align:left; margin:0 0 16px; max-width:none;">A strong English landing page should guide visitors from curiosity to a consultation request. The flow below is designed around real cosmetic intent, not generic dental copy.</p>
            <div class="timeline">
              <?php foreach ($processSteps as $step): ?>
                <div class="timeline-item">
                  <div class="timeline-step"><?php echo htmlspecialchars($step['step'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="timeline-copy">
                    <strong><?php echo htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars($step['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <aside class="compare-card">
            <i class="fa-solid fa-crown" aria-hidden="true"></i>
            <h3>The message this page should communicate</h3>
            <blockquote>"A beautiful smile is not simply whiter teeth. It is proportion, softness, confidence and harmony with your face."</blockquote>
            <div class="compare-list">
              <div class="compare-line"><i class="fa-solid fa-check"></i><span>English-first positioning for search and ads.</span></div>
              <div class="compare-line"><i class="fa-solid fa-check"></i><span>Premium cosmetic tone rather than generic clinic copy.</span></div>
              <div class="compare-line"><i class="fa-solid fa-check"></i><span>Clear Da Nang location relevance for local keyword intent.</span></div>
              <div class="compare-line"><i class="fa-solid fa-check"></i><span>Fast route to consultation with visible contact prompts.</span></div>
            </div>
            <a class="btn btn-primary" href="/lien-he.php" style="margin-top:16px;"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Request private consultation</a>
          </aside>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Frequently asked questions</h2>
        <p class="section-lead">FAQ blocks reduce hesitation, especially for paid traffic from Google, Meta or international visitors searching in English.</p>
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
              <h2>Looking for porcelain crowns in Da Nang with a premium cosmetic approach?</h2>
              <p>Book a consultation with Top Dental to discuss smile shape, brightness, treatment suitability and the best direction for a natural, refined result.</p>
            </div>
            <div class="cta-actions">
              <a class="btn btn-ghost" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $hotline) ?? $hotline, ENT_QUOTES, 'UTF-8'); ?>" style="background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.20);"><i class="fa-solid fa-phone"></i> Call now</a>
              <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check"></i> Book consultation</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <footer class="site-footer">
      <div class="container footer-inner">
        <div class="footer-brand">
          <strong>Top Dental</strong>
          <p>English landing page for cosmetic dentistry and porcelain crowns in Da Nang, built to support premium positioning, local search intent and paid campaign conversion.</p>
        </div>
        <div class="footer-links">
          <h4>Useful Links</h4>
          <a href="/">Home</a>
          <a href="/dich-vu">Services</a>
          <a href="/ve-chung-toi.php">About Top Dental</a>
          <a href="/blog">Blog</a>
        </div>
        <div class="footer-contact">
          <h4>Contact</h4>
          <div class="item"><i class="fa-solid fa-phone" aria-hidden="true"></i><span><?php echo htmlspecialchars($hotline, ENT_QUOTES, 'UTF-8'); ?></span></div>
          <div class="item"><i class="fa-solid fa-envelope" aria-hidden="true"></i><span><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></span></div>
          <div class="item"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
      </div>
      <div class="footer-bottom">Top Dental Da Nang. English page for porcelain crowns, porcelain veneers and premium smile consultation.</div>
    </footer>

    <div class="sticky-cta" aria-label="mobile cta">
      <a class="btn btn-ghost" href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $hotline) ?? $hotline, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-phone"></i> Call</a>
      <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-calendar-check"></i> Book</a>
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
    <?php if (function_exists('front_editor_render')) { front_editor_render('porcelain-crowns-da-nang'); } ?>
    <?php
      front_admin_render_edit_bar([
        ['label' => 'Admin', 'href' => '/admin/dashboard.php', 'icon' => 'fa-solid fa-shield-halved'],
        ['label' => 'Quản lý nội dung', 'href' => '/admin/content.php', 'icon' => 'fa-solid fa-pen-to-square'],
      ]);
    ?>
  </body>
</html>
