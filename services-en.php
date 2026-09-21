<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/front_admin.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
front_editor_page_maybe_redirect('services-en');
$pdo = db();
// Static hero slider with 1 sample image. You can replace this URL directly in this file.
$heroSlides = [
  '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
];

// Fetch services from English services category first, fallback if empty.
$services = [];
try {
  $st = $pdo->prepare(
    "SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image_url, DATE_FORMAT(p.updated_at, '%d.%m.%Y') AS d
     FROM posts p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'published' AND c.slug = :category_slug
     ORDER BY p.updated_at DESC"
  );
  $st->execute([':category_slug' => 'dich-vu-en']);
  $rows = $st->fetchAll();
  if (!$rows) {
    $st = $pdo->prepare(
      "SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image_url, DATE_FORMAT(p.updated_at, '%d.%m.%Y') AS d
       FROM posts p
       LEFT JOIN categories c ON c.id = p.category_id
       WHERE p.status = 'published' AND c.slug = :category_slug
       ORDER BY p.updated_at DESC"
    );
    $st->execute([':category_slug' => 'dich-vu']);
    $rows = $st->fetchAll();
  }
  foreach ($rows as $r) {
    $services[] = [
      'id' => (int) ($r['id'] ?? 0),
      'title' => (string) ($r['title'] ?? ''),
      'slug' => (string) ($r['slug'] ?? ''),
      'date' => (string) ($r['d'] ?? ''),
      'excerpt' => (string) ($r['excerpt'] ?? ''),
      'img' => (string) ($r['featured_image_url'] ?? ''),
    ];
  }
} catch (Throwable $e) {
  $services = [];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <?php echo site_favicon_tags(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
      $seo = front_editor_page_seo('services-en', [
        'title' => 'Top Dental Clinic • Dental Services',
        'description' => 'Explore premium dental services at Top Dental Clinic, from porcelain smile design and implants to comprehensive smile care in Da Nang.',
      ]);
      $seoKeywords = (string) ($seo['keywords'] ?? '');
    ?>
    <title><?php echo htmlspecialchars((string) ($seo['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars((string) ($seo['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars((string) ($seo['canonical_path'] ?? '/services'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root{
        --bg: #f0f9ff;
        --surface: rgba(255,255,255,0.96);
        --surface-2: rgba(255,255,255,0.98);
        --border: rgba(37,99,235,0.12);
        --text: rgba(15,23,42,0.95);
        --muted: rgba(15,23,42,0.65);
        --brand: #1e40af;
        --brand-light: #dbeafe;
        --brand-2: #3b82f6;
        --shadow: 0 18px 50px rgba(37,99,235,0.18);
        --radius: 10px;
        --max: 1320px;
        --img-1: url("/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png");
      }
      *{ box-sizing: border-box; }
      html{ height: 100%; }
      body{ min-height: 100%; }
      body{
        margin: 0;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        color: var(--text);
        background:
          radial-gradient(900px 520px at 15% 0%, rgba(59,130,246,0.22), transparent 62%),
          radial-gradient(900px 520px at 85% 8%, rgba(30,64,175,0.18), transparent 62%),
          linear-gradient(180deg, #ffffff 0%, var(--bg) 60%, #ffffff 100%);
      }
      a{ color: inherit; text-decoration: none; }
      img{ display:block; max-width:100%; }
      .container{ width: min(100% - 32px, var(--max)); margin: 0 auto; }
      .icon-gap{ margin-right: 8px; }
      .topbar{ position: sticky; top: 0; z-index: 50; backdrop-filter: blur(12px); background: linear-gradient(180deg, rgba(255,255,255,0.78), rgba(255,255,255,0.58)); border-bottom: 1px solid var(--border); }
      .nav{ position: relative; display:flex; align-items:center; justify-content:space-between; gap: 16px; padding: 14px 0; }
      .brand{ display:flex; align-items:center; gap: 10px; min-width: max-content; }
      .brand-mark{ width: 44px; height: 44px; border-radius: 14px; background: radial-gradient(18px 18px at 30% 25%, rgba(255,255,255,0.8), transparent 60%), linear-gradient(135deg, var(--brand-2), var(--brand)); box-shadow: 0 18px 50px rgba(37,99,235,0.25); display: flex; align-items: center; justify-content: center; }
      .brand-mark i{ color: #fff; font-size: 20px; }
      .brand-title{ line-height: 1.1; }
      .brand-title strong{ font-family:inherit; letter-spacing: -0.02em; font-weight: 700; color: var(--brand); }
      .brand-title span{ display:block; margin-top: 2px; font-size: 12px; color: var(--muted); }
      .navlinks{ display:flex; align-items:center; gap: 16px; }
      .navlinks a{ font-size: 14px; color: var(--muted); padding: 10px 10px; border-radius: var(--radius); transition: background 160ms ease, color 160ms ease; }
      .navlinks a:hover{ background: var(--brand-light); color: var(--brand); }
      .nav-cta{ display:flex; align-items:center; gap: 10px; min-width: max-content; }
      .pill{ display:inline-flex; align-items:center; gap: 8px; padding: 10px 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--brand-light); color: var(--brand); font-size: 13px; }
      .nav-toggle{ display:none; width: 42px; height: 42px; border-radius: 999px; border: 1px solid var(--border); background: rgba(255,255,255,0.9); align-items:center; justify-content:center; cursor: pointer; }
      .btn{ appearance:none; border:0; cursor:pointer; border-radius:var(--radius); padding:11px 14px; font-weight:600; font-size:14px; display:inline-flex; align-items:center; gap:10px; transition: transform 160ms ease, filter 160ms ease, background 160ms ease, border-color 160ms ease; }
      .btn:active{ transform: translateY(1px); }
      .btn-primary{ background: var(--brand); color: #fff; box-shadow: 0 18px 60px rgba(37,99,235,0.35); }
      .btn-primary:hover{ background: #1e3a8a; }
      .btn-ghost{ background: #fff; color: var(--brand); border: 1px solid var(--border); }
      .btn-ghost:hover{ background: var(--brand-light); }
      .hero{ position: relative; overflow: hidden; }
      .hero-slider{ position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94); }
      .hero-slide{ min-width: 100%; height: 100%; background-size: cover; background-position: center; }
      .hero-slider-controls{ position: absolute; top: 50%; left: 0; right: 0; transform: translateY(-50%); display: flex; justify-content: space-between; padding: 0 20px; z-index: 10; }
      .hero-slider-btn{ width: 48px; height: 48px; border-radius: 50%; background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(37, 99, 235, 0.2); color: var(--brand); font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
      .hero-slider-btn:hover{ background: var(--brand); color: white; border-color: var(--brand); transform: scale(1.05); }
      .hero-slider-dots{ position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%); display: flex; gap: 10px; z-index: 10; }
      .hero-slider-dot{ width: 12px; height: 12px; border-radius: 50%; background: rgba(255,255,255,0.5); border: none; cursor: pointer; transition: all 0.3s ease; }
      .hero-slider-dot.active{ background: white; transform: scale(1.3); box-shadow: 0 0 10px rgba(255,255,255,0.8); }
      .hero-inner{ position: relative; padding: 110px 0 72px; min-height: 78vh; display:flex; align-items:center; }
      .hero-grid{ display:grid; grid-template-columns: 1.05fr 0.95fr; gap: 28px; align-items: center; }
      .hero-copy{ padding: 22px 20px; border-radius: var(--radius); background: rgba(0,0,0,0.26); border: 1px solid rgba(255,255,255,0.14); box-shadow: 0 28px 80px rgba(0,0,0,0.22); color: rgba(255,255,255,0.96); backdrop-filter: blur(12px); }
      .hero-kicker{ display:inline-flex; align-items:center; gap: 10px; font-weight: 600; font-size: 12px; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255,255,255,0.86); }
      .hero-kicker .dot{ width: 8px; height: 8px; border-radius: 999px; background: var(--brand-2); box-shadow: 0 0 0 4px rgba(59,130,246,0.2); }
      .hero-copy h1{ margin: 0 0 10px; font-family:inherit; font-weight: 700; letter-spacing: -0.03em; line-height: 1.1; font-size: clamp(30px, 3.0vw, 44px); }
      .hero-copy p{ margin:0; color: rgba(255,255,255,0.82); line-height: 1.7; max-width: 62ch; }
      .hero-actions{ margin-top:18px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
      .hero-actions .btn-ghost{ border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.94); }
      .section{ padding: 34px 0; }
      .section h2{ margin:0 0 14px; text-align:center; font-family:inherit; font-size:22px; letter-spacing:-0.02em; }
      .section-lead{ text-align:center; color:var(--muted); max-width:70ch; margin:0 auto 16px; line-height:1.7; font-size:14px; }
      .grid{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:14px; }
      .card{ border-radius: var(--radius); border:1px solid var(--border); background: var(--surface-2); box-shadow: var(--shadow); overflow:hidden; transition: transform 180ms ease, box-shadow 180ms ease, filter 180ms ease; }
      .card:hover{ transform: translateY(-2px); box-shadow: 0 24px 70px rgba(17,24,39,0.16); }
      .card .img{ height: 180px; background: var(--img-1) center/cover no-repeat; }
      .card .body{ padding:14px 14px 16px; display:grid; gap:8px; }
      .card h3{ margin:0; font-size:16px; letter-spacing:-0.01em; }
      .card .excerpt{ color:var(--muted); line-height:1.7; font-size:14px; }
      .card .actions{ margin-top:2px; display:flex; gap:10px; flex-wrap:wrap; }
      /* About Section */
      .about-section{ padding: 40px 0; }
      .about-grid{ display:grid; grid-template-columns: 1fr 1fr; gap: 28px; align-items: center; }
      .about-img{ height: 400px; border-radius: var(--radius); background: linear-gradient(135deg, var(--brand-light) 0%, #fff 100%), var(--img-1) center/cover no-repeat; box-shadow: var(--shadow); }
      .about-content h2{ text-align:left; }
      .about-content p{ color:var(--muted); line-height:1.7; font-size:14px; margin-bottom:12px; }
      .about-stats{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; margin-top:16px; }
      .stat-item{ text-align:center; padding:16px; border-radius:var(--radius); background:#fff; border:1px solid var(--border); box-shadow:0 4px 12px rgba(37,99,235,0.1); }
      .stat-item h3{ font-family:inherit; font-size:28px; color:var(--brand); margin:0 0 4px; }
      .stat-item span{ color:var(--muted); font-size:13px; }
      /* Services Scroll */
      .services-scroll-container{ width:100%; }
      .services-scroll-grid{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:14px; }
      .feature-grid,
      .process-grid,
      .faq-grid{ display:grid; gap:14px; }
      .feature-grid{ grid-template-columns: repeat(3, minmax(0,1fr)); }
      .process-grid{ grid-template-columns: repeat(4, minmax(0,1fr)); }
      .faq-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      .feature-card,
      .process-card,
      .faq-card{
        border-radius: var(--radius);
        border:1px solid var(--border);
        background: var(--surface);
        box-shadow: var(--shadow);
        padding:18px;
      }
      .feature-card i,
      .process-card i,
      .faq-card i{
        width:48px;
        height:48px;
        border-radius:14px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background: var(--brand-light);
        color: var(--brand);
        font-size:18px;
        margin-bottom:12px;
      }
      .feature-card h3,
      .process-card h3,
      .faq-card h3{ margin:0 0 8px; font-size:16px; }
      .feature-card p,
      .process-card p,
      .faq-card p{ margin:0; color:var(--muted); line-height:1.75; font-size:14px; }
      .timeline{
        display:grid;
        grid-template-columns: 1.05fr 0.95fr;
        gap:18px;
        align-items:start;
      }
      .timeline-panel,
      .quote-panel{
        border-radius: var(--radius);
        border:1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        padding:20px;
      }
      .timeline-list{
        display:grid;
        gap:14px;
        margin-top:10px;
      }
      .timeline-item{
        display:grid;
        grid-template-columns: 54px minmax(0,1fr);
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
        background: linear-gradient(135deg, var(--brand-2), var(--brand));
        color:#fff;
        font-weight:700;
        box-shadow: 0 12px 30px rgba(37,99,235,0.22);
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
      .quote-panel{
        background: linear-gradient(180deg, rgba(30,64,175,0.06), rgba(59,130,246,0.10));
      }
      .quote-panel blockquote{
        margin:0;
        font-family:inherit;
        font-size: clamp(24px, 2.2vw, 32px);
        line-height:1.35;
        letter-spacing:-0.03em;
      }
      .quote-panel p{
        margin:14px 0 0;
        color:var(--muted);
        line-height:1.8;
        font-size:14px;
      }
      .quote-panel .btn{ margin-top:16px; }
      /* Footer */
      .site-footer{ margin-top:34px; position:relative; overflow:hidden; color: rgba(255,255,255,0.95); background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%); }
      .site-footer::before{ content:""; position:absolute; inset:0; background: var(--img-1) center/cover no-repeat; filter: saturate(0.7) contrast(1.10); opacity:0.15; z-index:0; pointer-events:none; }
      .footer-layer{ position:relative; z-index:1; }
      .footer-cta{ border-bottom:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.34); }
      .footer-cta-inner{ padding:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
      .footer-cta-inner b{ font-family:inherit; font-weight:600; color:rgba(255,255,255,0.92); }
      .footer-cta .btn-ghost{ border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.10); color: rgba(255,255,255,0.92); }
      .footer-cta .btn-ghost:hover{ background: rgba(255,255,255,0.16); }
      .footer-main{ padding:18px 0 10px; }
      .footer-cols{ display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:18px; }
      .footer-col h4{ margin:0 0 10px; font-size:14px; font-weight:700; color: rgba(255,255,255,0.92); padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.10); }
      .footer-link{ display:block; color:rgba(255,255,255,0.78); font-size:13px; padding:6px 0; }
      .footer-link:hover{ color: rgba(255,255,255,0.92); }
      .footer-contact{ display:grid; gap:8px; color: rgba(255,255,255,0.78); font-size:13px; }
      .footer-contact .item{ display:flex; gap:10px; align-items:flex-start; line-height:1.5; }
      .footer-contact i{ width:18px; margin-top:2px; text-align:center; color: rgba(255,255,255,0.90); }
      .footer-bottom{ border-top:1px solid rgba(255,255,255,0.10); padding:12px 0 14px; text-align:center; color: rgba(255,255,255,0.70); font-size:13px; }
      /* Responsive Styles */
      @media (max-width:1024px){
        .hero-grid{ grid-template-columns:1fr; }
        .about-grid{ grid-template-columns:1fr; }
        .feature-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); }
        .process-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); }
        .faq-grid,
        .timeline{ grid-template-columns:1fr; }
        .about-img{ height: 280px; }
        .grid, .services-scroll-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); }
        .footer-cols{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      }
      @media (max-width:680px){
        .nav-toggle{ display:inline-flex; }
        .navlinks{ display:none; position:absolute; left:16px; right:16px; top:calc(100% + 10px); padding:10px; border-radius:16px; border:1px solid var(--border); background: rgba(255,255,255,0.96); box-shadow:0 26px 80px rgba(17,24,39,0.18); flex-direction:column; align-items:stretch; gap:6px; }
        .navlinks a{ padding:12px 12px; }
        .navlinks.is-open{ display:flex; }
        .pill{ display:none; }
        .feature-grid,
        .process-grid,
        .faq-grid,
        .grid, .services-scroll-grid{ grid-template-columns:1fr; }
        .services-scroll-container{ overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; padding-bottom:8px; }
        .services-scroll-container::-webkit-scrollbar{ display:none; }
        .services-scroll-grid{ display:flex; width:max-content; gap:14px; }
        .services-scroll-grid .admin-hover-wrap{ width:280px; flex-shrink:0; }
        .about-stats{ grid-template-columns:1fr; }
        .hero-slider-btn{ width:40px; height:40px; font-size:16px; }
        .hero-slider-dots{ bottom:20px; }
        .hero-slider-dot{ width:10px; height:10px; }
        .footer-cols{ grid-template-columns:1fr; gap:12px; }
        .footer-col h4{ margin:0 0 8px; }
        .footer-link{ padding:8px 0; font-size:14px; }
        .footer-cta-inner{ padding:12px 0; flex-direction:column; align-items:flex-start; gap:10px; }
        .footer-cta-inner .btn{ width:100%; justify-content:center; }
        .footer-contact{ gap:10px; }
        .footer-bottom{ font-size:12px; padding:10px 0 12px; }
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <section class="hero">
      <div class="hero-slider" id="heroSlider">
        <?php foreach ($heroSlides as $slideUrl): ?>
          <div class="hero-slide" style="background: linear-gradient(90deg, rgba(30,64,175,0.75) 0%, rgba(30,64,175,0.55) 42%, rgba(30,64,175,0.35) 72%, rgba(30,64,175,0.5) 100%), url('<?php echo htmlspecialchars($slideUrl, ENT_QUOTES, 'UTF-8'); ?>') center/cover no-repeat;"></div>
        <?php endforeach; ?>
        <?php if (empty($heroSlides)): ?>
          <div class="hero-slide" style="background: linear-gradient(90deg, rgba(30,64,175,0.75) 0%, rgba(30,64,175,0.55) 42%, rgba(30,64,175,0.35) 72%, rgba(30,64,175,0.5) 100%), var(--img-1) center/cover no-repeat;"></div>
        <?php endif; ?>
      </div>
      <div class="container hero-inner">
        <div class="hero-grid">
          <div class="hero-copy">
            <div class="hero-kicker"><span class="dot" aria-hidden="true"></span> Dental services</div>
            <h1>Professional Dental Services at Top Dental Clinic</h1>
            <p>We provide a full range of high-quality dental solutions, with particular strength in porcelain smile design, restorative care, and personalized treatment planning.</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book a consultation</a>
              <a class="btn btn-ghost" href="#services"><i class="fa-solid fa-teeth" aria-hidden="true"></i> Explore all services</a>
            </div>
          </div>
        </div>
      </div>
      <div class="hero-slider-controls">
        <button class="hero-slider-btn hero-slider-prev" id="heroSliderPrev" aria-label="Previous slide">
          <i class="fa-solid fa-chevron-left"></i>
        </button>
        <button class="hero-slider-btn hero-slider-next" id="heroSliderNext" aria-label="Next slide">
          <i class="fa-solid fa-chevron-right"></i>
        </button>
      </div>
      <div class="hero-slider-dots" id="heroSliderDots"></div>
    </section>

    <!-- About Section -->
    <section class="about-section">
      <div class="container">
        <div class="about-grid">
          <div class="about-img" style="background: var(--img-1) center/cover no-repeat;"></div>
          <div class="about-content">
            <h2>About Top Dental Clinic</h2>
            <p>With more than 15 years of experience in dentistry, Top Dental Clinic has become a trusted destination for thousands of patients. We continue to elevate our service quality and invest in modern equipment to deliver a refined treatment experience.</p>
            <p>Our porcelain smile design service is especially appreciated for both aesthetics and durability. Visit Top Dental Clinic to build a smile that looks natural, elegant, and confidently yours.</p>
            <div class="about-stats">
              <div class="stat-item">
                <h3>15+</h3>
                <span>Years of experience</span>
              </div>
              <div class="stat-item">
                <h3>10.000+</h3>
                <span>Satisfied patients</span>
              </div>
              <div class="stat-item">
                <h3>20+</h3>
                <span>Dental specialists</span>
              </div>
            </div>
            <a class="btn btn-primary" href="/contact-us" style="margin-top: 16px;"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book your visit</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Services Section -->
    <section class="section" id="services">
      <div class="container">
        <h2>Service Portfolio</h2>
        <p class="section-lead">A curated list of dental services at Top Dental Clinic, delivered with careful diagnosis, transparent planning, and attentive care from our clinical team.</p>
        <div class="services-scroll-container">
          <div class="services-scroll-grid">
            <?php if (!empty($services)): ?>
            <?php foreach ($services as $service): ?>
              <div class="admin-hover-wrap" style="height: 100%;">
                <article class="card" style="height: 100%;">
                  <?php $img = $service['img']; $style = $img !== '' ? "background-image:url('".htmlspecialchars($img, ENT_QUOTES, 'UTF-8')."')" : ""; ?>
                  <div class="img" aria-hidden="true" style="<?php echo $style; ?>"></div>
                  <div class="body">
                    <h3><?php echo htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="excerpt"><?php echo htmlspecialchars($service['excerpt'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="actions">
                      <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                      <?php if (function_exists('admin_front_is_logged_in') && admin_front_is_logged_in()): ?>
                        <a class="btn btn-ghost" href="/admin/content_post_edit.php?id=<?php echo (int) $service['id']; ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="admin-hover-wrap" style="height: 100%;">
              <article class="card" style="height: 100%;">
                <div class="img" aria-hidden="true" style="background: url('https://images.unsplash.com/photo-1606811841689-23dfddce3e95?w=600&q=80') center/cover no-repeat;"></div>
                <div class="body">
                  <h3>Porcelain Smile Design</h3>
                  <div class="excerpt">Premium porcelain restorations crafted to deliver a bright, balanced, and confident smile.</div>
                  <div class="actions">
                    <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                  </div>
                </div>
              </article>
            </div>
            <div class="admin-hover-wrap" style="height: 100%;">
              <article class="card" style="height: 100%;">
                <div class="img" aria-hidden="true" style="background: url('https://images.unsplash.com/photo-1596464711277-47041586ef15?w=600&q=80') center/cover no-repeat;"></div>
                <div class="body">
                  <h3>Dental Implants</h3>
                  <div class="excerpt">A reliable tooth replacement solution designed to restore function, comfort, and natural aesthetics.</div>
                  <div class="actions">
                    <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                  </div>
                </div>
              </article>
            </div>
            <div class="admin-hover-wrap" style="height: 100%;">
              <article class="card" style="height: 100%;">
                <div class="img" aria-hidden="true" style="background: url('https://images.unsplash.com/photo-1609840114035-3c981b782dfe?w=600&q=80') center/cover no-repeat;"></div>
                <div class="body">
                  <h3>Orthodontics</h3>
                  <div class="excerpt">Correct bite alignment and straighten teeth with a treatment roadmap tailored to your needs.</div>
                  <div class="actions">
                    <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                  </div>
                </div>
              </article>
            </div>
          <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Why Patients Choose Us</h2>
        <p class="section-lead">Every service is built around safety, transparency, and long-term aesthetic results so patients feel confident from consultation through aftercare.</p>
        <div class="feature-grid">
          <article class="feature-card">
            <i class="fa-solid fa-user-doctor" aria-hidden="true"></i>
            <h3>Clear clinical expertise</h3>
            <p>Each treatment group is guided by experienced clinicians who assess the real condition and build a suitable treatment plan instead of applying a one-size-fits-all approach.</p>
          </article>
          <article class="feature-card">
            <i class="fa-solid fa-microscope" aria-hidden="true"></i>
            <h3>Modern diagnostic support</h3>
            <p>Images, diagnostic data, and treatment steps are presented clearly so patients understand their condition, treatment time, and budget before making a decision.</p>
          </article>
          <article class="feature-card">
            <i class="fa-solid fa-heart-circle-check" aria-hidden="true"></i>
            <h3>Structured aftercare</h3>
            <p>We do more than complete a service. Follow-up visits, reminders, and detailed care instructions help maintain stable, beautiful results over time.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Consultation and Treatment Flow</h2>
        <p class="section-lead">Top Dental Clinic uses a clear step-by-step workflow so you always know where you are in the process, what to prepare, and what to expect at each stage.</p>
        <div class="process-grid">
          <article class="process-card">
            <i class="fa-solid fa-comments" aria-hidden="true"></i>
            <h3>1. Initial consultation</h3>
            <p>We review your concerns, listen to your aesthetic goals, and note your treatment history to define the right examination path from the start.</p>
          </article>
          <article class="process-card">
            <i class="fa-solid fa-camera-retro" aria-hidden="true"></i>
            <h3>2. Imaging and examination</h3>
            <p>Your oral condition is evaluated through imaging and clinical findings, creating a treatment plan grounded in real diagnostic data.</p>
          </article>
          <article class="process-card">
            <i class="fa-solid fa-file-medical" aria-hidden="true"></i>
            <h3>3. Treatment planning</h3>
            <p>The dentist explains the recommended option, timeline, materials, and preparation notes before treatment begins.</p>
          </article>
          <article class="process-card">
            <i class="fa-solid fa-hand-holding-medical" aria-hidden="true"></i>
            <h3>4. Scheduled follow-up</h3>
            <p>After treatment, patients receive follow-up appointments and detailed care guidance to maintain stable outcomes and reduce relapse risk.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="timeline">
          <div class="timeline-panel">
            <h2 style="text-align:left; margin-bottom:8px;">A Personalized Treatment Journey</h2>
            <p class="section-lead" style="text-align:left; margin:0; max-width:none;">No two patients need exactly the same roadmap. We adapt the plan based on dental condition, aesthetic goals, available time, and each patient's priorities.</p>
            <div class="timeline-list">
              <div class="timeline-item">
                <div class="timeline-step">01</div>
                <div class="timeline-copy">
                  <strong>Understand the real need</strong>
                  <span>Recommendations are based on your actual concern, not simply on a service name or a general trend.</span>
                </div>
              </div>
              <div class="timeline-item">
                <div class="timeline-step">02</div>
                <div class="timeline-copy">
                  <strong>Prioritize the right solution</strong>
                  <span>Every option is balanced across aesthetics, chewing function, longevity, budget, and treatment time.</span>
                </div>
              </div>
              <div class="timeline-item">
                <div class="timeline-step">03</div>
                <div class="timeline-copy">
                  <strong>Guide the full experience</strong>
                  <span>Our team closely follows progress and patient comfort, adjusting the schedule when needed to keep treatment smooth and manageable.</span>
                </div>
              </div>
            </div>
          </div>
          <aside class="quote-panel">
            <blockquote>"A great dental service is not only about the final result. It must also feel clear, reassuring, and easy to follow at every step."</blockquote>
            <p>That is why we invest evenly in consultation, diagnostics, craftsmanship, and aftercare instead of focusing on just one touchpoint.</p>
            <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Get your treatment roadmap</a>
          </aside>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <h2>Frequently Asked Questions</h2>
        <p class="section-lead">Common questions patients ask before starting treatment, especially for cosmetic and restorative procedures.</p>
        <div class="faq-grid">
          <article class="faq-card">
            <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
            <h3>Should I schedule an exam first?</h3>
            <p>Yes. An exam and consultation allow the dentist to assess your real condition and recommend the right solution before treatment begins.</p>
          </article>
          <article class="faq-card">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            <h3>How long does treatment take?</h3>
            <p>The timeline depends on the service. Some treatments finish quickly, while others need multiple visits. We explain the expected timeframe from the beginning.</p>
          </article>
          <article class="faq-card">
            <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
            <h3>How do you ensure safety?</h3>
            <p>We maintain strict sterilization, careful treatment indications, and clear aftercare protocols. Every step is discussed before it is performed.</p>
          </article>
          <article class="faq-card">
            <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
            <h3>Can the plan be customized to my smile goals?</h3>
            <p>Absolutely. We tailor smile design and treatment recommendations to your face, oral condition, and personal preferences.</p>
          </article>
        </div>
      </div>
    </section>

    <?php if (function_exists('front_editor_render')) { front_editor_render('services-en'); } ?>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
    <script>
      (function(){
        function boot(){
          var hero = document.querySelector(".hero");
          if (!hero) return;
          var slider = document.getElementById("heroSlider");
          var slides = slider ? slider.querySelectorAll(".hero-slide") : [];
          if (!slider || slides.length === 0) return;
          var prevBtn = document.getElementById("heroSliderPrev");
          var nextBtn = document.getElementById("heroSliderNext");
          var dotsContainer = document.getElementById("heroSliderDots");
          var currentSlide = 0;
          var autoSlideInterval;

          // Create dots
          for (var i = 0; i < slides.length; i++) {
            var dot = document.createElement("button");
            dot.className = "hero-slider-dot" + (i === 0 ? " active" : "");
            dot.setAttribute("aria-label", "Slide " + (i + 1));
            (function(index){
              dot.addEventListener("click", function(){
                goToSlide(index);
              });
            })(i);
            dotsContainer.appendChild(dot);
          }
          var dots = dotsContainer.querySelectorAll(".hero-slider-dot");

          function updateSlider(){
            slider.style.transform = "translateX(" + (-currentSlide * 100) + "%)";
            dots.forEach(function(dot, index){ dot.classList.toggle("active", index === currentSlide); });
          }
          function goToSlide(index){
            currentSlide = index;
            if (currentSlide < 0) currentSlide = slides.length - 1;
            if (currentSlide >= slides.length) currentSlide = 0;
            updateSlider();
            resetAutoSlide();
          }
          function nextSlide(){ goToSlide(currentSlide + 1); }
          function prevSlide(){ goToSlide(currentSlide - 1); }
          function startAutoSlide(){
            autoSlideInterval = setInterval(nextSlide, 5000);
          }
          function resetAutoSlide(){
            clearInterval(autoSlideInterval);
            startAutoSlide();
          }
          if (prevBtn) prevBtn.addEventListener("click", prevSlide);
          if (nextBtn) nextBtn.addEventListener("click", nextSlide);

          // Touch Swipe Support
          var touchStartX = 0;
          var touchEndX = 0;
          slider.addEventListener("touchstart", function(e){
            touchStartX = e.changedTouches[0].screenX;
          }, { passive: true });
          slider.addEventListener("touchend", function(e){
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
          }, { passive: true });
          function handleSwipe(){
            var swipeThreshold = 50;
            var diff = touchStartX - touchEndX;
            if (Math.abs(diff) > swipeThreshold){
              if (diff > 0) nextSlide();
              else prevSlide();
            }
          }
          startAutoSlide();
        }
        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
        else boot();
      })();
    </script>
    <script>
      (function(){
        var toggle = document.querySelector('[data-nav-toggle="1"]');
        var nav = document.querySelector('.navlinks');
        if (!toggle || !nav) return;
        toggle.addEventListener('click', function(){ nav.classList.toggle('is-open'); });
        document.addEventListener('click', function(e){
          if (!nav.classList.contains('is-open')) return;
          if (e.target === toggle || toggle.contains(e.target)) return;
          if (e.target === nav || nav.contains(e.target)) return;
          nav.classList.remove('is-open');
        });
      })();
    </script>
    <?php
      front_admin_render_edit_bar([
        ['label' => 'Quản lý dịch vụ', 'href' => '/admin/content.php', 'icon' => 'fa-solid fa-pen-to-square'],
        ['label' => 'Admin', 'href' => '/admin/dashboard.php', 'icon' => 'fa-solid fa-shield-halved'],
      ]);
    ?>
  </body>
</html>
