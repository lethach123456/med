<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/front_admin.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
front_editor_page_maybe_redirect('about-en');

$pdo = db();
$hotline = site_hotline('0901 234 567');
$email = site_email('info@topdentalclinic.vn');
$address = site_address('123 ABC Street, District 1, Ho Chi Minh City');
$videoEmbed = trim((string) site_setting('site_about_video_embed', 'https://player.vimeo.com/video/76979871?title=0&byline=0&portrait=0'));
$customerShowcase = [
  [
    'slot' => 'about-en-01',
    'image' => '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
    'title' => 'Patient Story 01',
    'description' => 'Sample caption for a patient image. You can replace this text manually with the real case details.',
  ],
  [
    'slot' => 'about-en-02',
    'image' => '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
    'title' => 'Patient Story 02',
    'description' => 'Sample caption for patient image number 2. Edit the image path and wording directly in this file.',
  ],
  [
    'slot' => 'about-en-03',
    'image' => '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
    'title' => 'Patient Story 03',
    'description' => 'Use this block as a manual template for consultation moments, treatment stages, or final smile results.',
  ],
  [
    'slot' => 'about-en-04',
    'image' => '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
    'title' => 'Patient Story 04',
    'description' => 'This is a static showcase item so you can freely update the image, title, and description by hand.',
  ],
  [
    'slot' => 'about-en-05',
    'image' => '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
    'title' => 'Patient Story 05',
    'description' => 'Sample text for item 5. Replace it with the real patient note or a short highlight about the case.',
  ],
  [
    'slot' => 'about-en-06',
    'image' => '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
    'title' => 'Patient Story 06',
    'description' => 'Sample text for item 6. This can be used for before-after notes or a short clinic experience summary.',
  ],
  [
    'slot' => 'about-en-07',
    'image' => '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
    'title' => 'Patient Story 07',
    'description' => 'This gallery item is intentionally hardcoded so you can update it directly without relying on the database.',
  ],
  [
    'slot' => 'about-en-08',
    'image' => '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
    'title' => 'Patient Story 08',
    'description' => 'Sample text for item 8. Replace the image URL, title, and caption with your own content anytime.',
  ],
];
foreach ($customerShowcase as $index => $item) {
  $slotKey = trim((string) ($item['slot'] ?? ''));
  $defaultImage = trim((string) ($item['image'] ?? ''));
  if ($slotKey !== '') {
    $customerShowcase[$index]['image'] = front_editor_image_slot('about-en', $slotKey, $defaultImage);
    $customerShowcase[$index]['title'] = front_editor_text_slot('about-en', $slotKey . '-title', (string) ($item['title'] ?? ''));
    $customerShowcase[$index]['description'] = front_editor_text_slot('about-en', $slotKey . '-description', (string) ($item['description'] ?? ''));
  }
}
$heroSlides = [
  [
    'image' => '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
  ],
];

$services = [];
try {
  $stmt = $pdo->prepare(
    "SELECT p.id, p.title, p.excerpt, p.featured_image_url
     FROM posts p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'published' AND c.slug = :category_slug
     ORDER BY updated_at DESC
     LIMIT 4"
  );
  $stmt->execute([':category_slug' => 'dich-vu-en']);
  foreach ($stmt->fetchAll() ?: [] as $row) {
    $services[] = [
      'id' => (int) ($row['id'] ?? 0),
      'title' => (string) ($row['title'] ?? ''),
      'excerpt' => (string) ($row['excerpt'] ?? ''),
      'img' => (string) ($row['featured_image_url'] ?? ''),
    ];
  }
} catch (Throwable $e) {
  $services = [];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
      $seo = front_editor_page_seo('about-en', [
        'title' => 'Top Dental Clinic • About Us',
        'description' => 'Discover Top Dental Clinic, a cosmetic-focused dental clinic in Da Nang known for porcelain smile design, modern technology, and attentive patient care.',
      ]);
      $seoKeywords = (string) ($seo['keywords'] ?? '');
    ?>
    <title><?php echo htmlspecialchars((string) ($seo['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars((string) ($seo['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars((string) ($seo['canonical_path'] ?? '/about-us'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root{
        --bg: #f0f9ff;
        --surface: rgba(255,255,255,0.96);
        --surface-2: rgba(255,255,255,0.98);
        --border: rgba(37,99,235,0.12);
        --text: rgba(15,23,42,0.95);
        --muted: rgba(15,23,42,0.68);
        --brand: #1e40af;
        --brand-2: #3b82f6;
        --brand-light: #dbeafe;
        --shadow: 0 18px 50px rgba(37,99,235,0.18);
        --radius: 10px;
        --radius-lg: 18px;
        --max: 1320px;
        --img-1: url("/uploads/library/2026/06/785bb9cc02fcd0b1493d6d864b5988da.jpg");
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
      .topbar{ position: sticky; top: 0; z-index: 50; backdrop-filter: blur(12px); background: linear-gradient(180deg, rgba(255,255,255,0.78), rgba(255,255,255,0.58)); border-bottom: 1px solid var(--border); }
      .nav{ position: relative; display:flex; align-items:center; justify-content:space-between; gap: 16px; padding: 14px 0; }
      .brand{ display:flex; align-items:center; gap: 10px; min-width: max-content; }
      .brand-mark{ width: 44px; height: 44px; border-radius: 14px; background: radial-gradient(18px 18px at 30% 25%, rgba(255,255,255,0.8), transparent 60%), linear-gradient(135deg, var(--brand-2), var(--brand)); box-shadow: 0 18px 50px rgba(37,99,235,0.25); display: flex; align-items: center; justify-content: center; }
      .brand-mark i{ color: #fff; font-size: 20px; }
      .brand-title{ line-height: 1.1; }
      .brand-title strong{ font-family: "Playfair Display", serif; letter-spacing: -0.02em; font-weight: 700; color: var(--brand); }
      .brand-title span{ display:block; margin-top: 2px; font-size: 12px; color: var(--muted); }
      .navlinks{ display:flex; align-items:center; gap: 16px; }
      .navlinks a{ font-size: 14px; color: var(--muted); padding: 10px 10px; border-radius: var(--radius); transition: background 160ms ease, color 160ms ease; }
      .navlinks a:hover{ background: var(--brand-light); color: var(--brand); }
      .nav-cta{ display:flex; align-items:center; gap: 10px; min-width: max-content; }
      .pill{ display:inline-flex; align-items:center; gap: 8px; padding: 10px 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--brand-light); color: var(--brand); font-size: 13px; }
      .nav-toggle{ display:none; width: 42px; height: 42px; border-radius: 999px; border: 1px solid var(--border); background: rgba(255,255,255,0.9); align-items:center; justify-content:center; cursor: pointer; }
      .btn{
        appearance:none;
        border:0;
        cursor:pointer;
        border-radius:var(--radius);
        padding:11px 14px;
        font-weight:600;
        font-size:14px;
        display:inline-flex;
        align-items:center;
        gap:10px;
        transition: transform 160ms ease, filter 160ms ease, background 160ms ease, border-color 160ms ease;
      }
      .btn:active{ transform: translateY(1px); }
      .btn-primary{
        background: var(--brand);
        color: #fff;
        box-shadow: 0 18px 60px rgba(37,99,235,0.35);
      }
      .btn-primary:hover{ background: #1e3a8a; }
      .btn-ghost{
        background: rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.96);
        border: 1px solid rgba(255,255,255,0.2);
      }
      .btn-light{
        background: #fff;
        color: var(--brand);
        border: 1px solid var(--border);
      }
      .btn-light:hover{ background: var(--brand-light); }
      .hero{
        position: relative;
        overflow: hidden;
      }
      .hero-slider{
        position: absolute;
        inset: 0;
        display: flex;
        transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      .hero-slide{
        min-width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
      }
      .hero-inner{
        position: relative;
        padding: 122px 0 78px;
        min-height: 84vh;
        display: flex;
        align-items: center;
      }
      .hero-grid{
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
        gap: 24px;
        align-items: end;
      }
      .hero-copy{
        padding: 28px 24px;
        border-radius: var(--radius-lg);
        background: rgba(0,0,0,0.30);
        border: 1px solid rgba(255,255,255,0.12);
        box-shadow: 0 28px 80px rgba(0,0,0,0.22);
        color: rgba(255,255,255,0.96);
        backdrop-filter: blur(14px);
      }
      .hero-kicker{
        display:inline-flex;
        align-items:center;
        gap: 10px;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.82);
      }
      .hero-kicker .dot{
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #93c5fd;
        box-shadow: 0 0 0 4px rgba(147,197,253,0.16);
      }
      .hero-copy h1{
        margin: 12px 0 12px;
        font-family: "Playfair Display", serif;
        font-size: clamp(34px, 4vw, 54px);
        line-height: 1.08;
        letter-spacing: -0.03em;
      }
      .hero-copy p{
        margin: 0;
        color: rgba(255,255,255,0.84);
        line-height: 1.75;
        max-width: 66ch;
      }
      .hero-actions{
        margin-top: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
      }
      .hero-panel{
        border-radius: var(--radius-lg);
        background: rgba(255,255,255,0.10);
        border: 1px solid rgba(255,255,255,0.16);
        box-shadow: 0 18px 60px rgba(0,0,0,0.16);
        padding: 18px;
        color: #fff;
        backdrop-filter: blur(14px);
      }
      .hero-panel h3{
        margin: 0 0 12px;
        font-size: 18px;
        font-weight: 700;
      }
      .hero-stats{
        display: grid;
        gap: 10px;
      }
      .hero-stat{
        display: grid;
        gap: 4px;
        padding: 14px;
        border-radius: 14px;
        background: rgba(255,255,255,0.10);
        border: 1px solid rgba(255,255,255,0.14);
      }
      .hero-stat strong{
        font-family: "Playfair Display", serif;
        font-size: 28px;
        line-height: 1;
      }
      .hero-stat span{
        font-size: 13px;
        color: rgba(255,255,255,0.82);
      }
      .hero-slider-dots{
        position: absolute;
        left: 50%;
        bottom: 24px;
        transform: translateX(-50%);
        display: flex;
        gap: 10px;
        z-index: 10;
      }
      .hero-slider-dot{
        width: 11px;
        height: 11px;
        border-radius: 999px;
        border: 0;
        background: rgba(255,255,255,0.42);
        cursor: pointer;
        transition: transform 160ms ease, background 160ms ease;
      }
      .hero-slider-dot.active{
        background: #fff;
        transform: scale(1.22);
      }
      .section{ padding: 38px 0; }
      .section-head{
        display: grid;
        gap: 8px;
        margin-bottom: 18px;
      }
      .section-head.center{ text-align: center; }
      .eyebrow{
        font-size: 12px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--brand);
        font-weight: 700;
      }
      h2{
        margin: 0;
        font-family: "Playfair Display", serif;
        font-size: clamp(26px, 2.6vw, 38px);
        letter-spacing: -0.03em;
      }
      .section-head p{
        margin: 0;
        color: var(--muted);
        line-height: 1.75;
        font-size: 15px;
      }
      .story{
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(320px, 0.95fr);
        gap: 18px;
      }
      .card{
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow: hidden;
      }
      .card-body{
        padding: 20px;
      }
      .story-copy{
        display: grid;
        gap: 14px;
      }
      .story-copy p{
        margin: 0;
        color: var(--muted);
        line-height: 1.8;
        font-size: 15px;
      }
      .bullet-list{
        display: grid;
        gap: 12px;
      }
      .bullet-item{
        display: flex;
        gap: 12px;
        align-items: flex-start;
      }
      .bullet-icon{
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: var(--brand-light);
        color: var(--brand);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }
      .bullet-item strong{
        display: block;
        margin-bottom: 4px;
      }
      .bullet-item span{
        color: var(--muted);
        line-height: 1.7;
        font-size: 14px;
      }
      .video-shell{
        position: relative;
        aspect-ratio: 16 / 10;
        background: #0f172a;
      }
      .video-shell iframe{
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
      }
      .video-caption{
        padding: 16px 18px;
        border-top: 1px solid var(--border);
        display: grid;
        gap: 6px;
      }
      .video-caption strong{
        font-size: 16px;
      }
      .video-caption span{
        color: var(--muted);
        line-height: 1.7;
        font-size: 14px;
      }
      .values-grid,
      .customer-gallery,
      .expertise-grid,
      .journey-grid,
      .facility-grid{
        display: grid;
        gap: 14px;
      }
      .values-grid{ grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .customer-gallery{ grid-template-columns: repeat(4, minmax(0, 1fr)); }
      .expertise-grid{ grid-template-columns: repeat(4, minmax(0, 1fr)); }
      .journey-grid{ grid-template-columns: repeat(4, minmax(0, 1fr)); }
      .facility-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .value-card,
      .journey-card,
      .facility-card{
        padding: 18px;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        background: var(--surface);
        box-shadow: var(--shadow);
      }
      .value-card i,
      .journey-card i,
      .facility-card i{
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--brand-light);
        color: var(--brand);
        font-size: 18px;
        margin-bottom: 14px;
      }
      .value-card h3,
      .journey-card h3,
      .facility-card h3,
      .expertise-card h3{
        margin: 0 0 8px;
        font-size: 17px;
      }
      .value-card p,
      .journey-card p,
      .facility-card p,
      .expertise-card p{
        margin: 0;
        color: var(--muted);
        line-height: 1.75;
        font-size: 14px;
      }
      .customer-photo{
        position: relative;
        overflow: hidden;
        min-height: 240px;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
      }
      .customer-photo img{
        width: 100%;
        height: 100%;
        min-height: 240px;
        object-fit: cover;
        transition: transform 220ms ease;
      }
      .customer-photo:hover img{
        transform: scale(1.03);
      }
      .customer-photo::after{
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(15,23,42,0.04) 0%, rgba(15,23,42,0.46) 100%);
        pointer-events: none;
      }
      .customer-caption{
        position: absolute;
        left: 14px;
        right: 14px;
        bottom: 14px;
        z-index: 1;
        padding: 14px;
        border-radius: 14px;
        background: rgba(255,255,255,0.14);
        border: 1px solid rgba(255,255,255,0.18);
        backdrop-filter: blur(10px);
        color: #fff;
      }
      .customer-caption h3{
        margin: 0 0 6px;
        font-size: 16px;
        color: #fff;
      }
      .customer-caption p{
        margin: 0;
        color: rgba(255,255,255,0.84);
        line-height: 1.6;
        font-size: 13px;
      }
      .expertise-card{
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow: hidden;
      }
      .expertise-thumb{
        height: 180px;
        background: linear-gradient(180deg, rgba(30,64,175,0.18), rgba(30,64,175,0.02)), var(--img-1) center/cover no-repeat;
      }
      .expertise-body{
        padding: 18px;
      }
      .expertise-actions{
        margin-top: 12px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
      }
      .highlight{
        position: relative;
        overflow: hidden;
      }
      .highlight::before{
        content: "";
        position: absolute;
        inset: 0;
        background:
          radial-gradient(480px 180px at 12% 0%, rgba(59,130,246,0.14), transparent 72%),
          radial-gradient(480px 180px at 88% 100%, rgba(30,64,175,0.14), transparent 72%);
        pointer-events: none;
      }
      .highlight-grid{
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 18px;
        align-items: stretch;
      }
      .quote-box{
        padding: 24px;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.82);
        box-shadow: var(--shadow);
      }
      .quote-box blockquote{
        margin: 0;
        font-family: "Playfair Display", serif;
        font-size: clamp(24px, 2.8vw, 34px);
        line-height: 1.35;
        letter-spacing: -0.03em;
        color: #0f172a;
      }
      .quote-box p{
        margin: 14px 0 0;
        color: var(--muted);
        line-height: 1.8;
      }
      .contact-panel{
        padding: 20px;
        border-radius: var(--radius-lg);
        background: linear-gradient(180deg, rgba(30,64,175,0.98), rgba(37,99,235,0.92));
        color: #fff;
        box-shadow: 0 18px 60px rgba(37,99,235,0.24);
      }
      .contact-panel h3{ margin: 0 0 12px; font-size: 20px; }
      .contact-list{
        display: grid;
        gap: 12px;
        margin-bottom: 16px;
      }
      .contact-line{
        display: flex;
        gap: 12px;
        align-items: flex-start;
      }
      .contact-line i{
        width: 18px;
        margin-top: 3px;
        text-align: center;
      }
      .contact-line span{
        color: rgba(255,255,255,0.84);
        line-height: 1.7;
        font-size: 14px;
      }
      .cta{
        padding: 26px;
        border-radius: 24px;
        background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%);
        color: #fff;
        box-shadow: 0 24px 80px rgba(37,99,235,0.28);
      }
      .cta-grid{
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        align-items: center;
      }
      .cta h2{
        color: #fff;
        font-size: clamp(28px, 3vw, 40px);
      }
      .cta p{
        margin: 8px 0 0;
        color: rgba(255,255,255,0.86);
        line-height: 1.8;
      }
      .site-footer{ margin-top:34px; position:relative; overflow:hidden; color: rgba(255,255,255,0.95); background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%); }
      .site-footer::before{ content:""; position:absolute; inset:0; background: var(--img-1) center/cover no-repeat; filter: saturate(0.7) contrast(1.10); opacity:0.15; z-index:0; pointer-events:none; }
      .footer-layer{ position:relative; z-index:1; }
      .footer-cta{ border-bottom:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.34); }
      .footer-cta-inner{ padding:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
      .footer-cta-inner b{ font-family:"Playfair Display",serif; font-weight:600; color: rgba(255,255,255,0.92); }
      .footer-main{ padding:18px 0 10px; }
      .footer-cols{ display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:18px; }
      .footer-col h4{ margin:0 0 10px; font-size:14px; font-weight:700; color: rgba(255,255,255,0.92); padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.10); }
      .footer-link{ display:block; color: rgba(255,255,255,0.78); font-size:13px; padding:6px 0; }
      .footer-link:hover{ color: rgba(255,255,255,0.92); }
      .footer-contact{ display:grid; gap:8px; color: rgba(255,255,255,0.78); font-size:13px; }
      .footer-contact .item{ display:flex; gap:10px; align-items:flex-start; line-height:1.5; }
      .footer-contact i{ width:18px; margin-top:2px; text-align:center; color: rgba(255,255,255,0.90); }
      .footer-bottom{ border-top:1px solid rgba(255,255,255,0.10); padding:12px 0 14px; text-align:center; color: rgba(255,255,255,0.70); font-size:13px; }
      @media (max-width: 1024px){
        .hero-grid,
        .story,
        .highlight-grid{
          grid-template-columns: 1fr;
        }
        .customer-gallery,
        .values-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .expertise-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .journey-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .facility-grid{ grid-template-columns: 1fr; }
        .cta-grid{ grid-template-columns: 1fr; }
        .footer-cols{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      }
      @media (max-width: 680px){
        .nav-toggle{ display:inline-flex; }
        .navlinks{ display:none; position:absolute; left:16px; right:16px; top:calc(100% + 10px); padding:10px; border-radius:16px; border:1px solid var(--border); background: rgba(255,255,255,0.96); box-shadow:0 26px 80px rgba(17,24,39,0.18); flex-direction:column; align-items:stretch; gap:6px; }
        .navlinks a{ padding:12px 12px; }
        .navlinks.is-open{ display:flex; }
        .pill{ display:none; }
        .hero-inner{ min-height: auto; padding: 108px 0 74px; }
        .hero-copy{ padding: 20px 18px; }
        .hero-panel{ padding: 16px; }
        .customer-gallery,
        .values-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .expertise-grid,
        .journey-grid{ grid-template-columns: 1fr; }
        .customer-photo,
        .customer-photo img{ min-height: 220px; }
        .section{ padding: 32px 0; }
        .card-body,
        .value-card,
        .journey-card,
        .facility-card,
        .quote-box,
        .contact-panel,
        .cta{ padding: 18px; }
        .hero-slider-dots{ bottom: 18px; }
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
        <?php foreach ($heroSlides as $slide): ?>
          <?php
            $slideUrl = trim((string) ($slide['image'] ?? ''));
            $slideSlot = trim((string) ($slide['slot'] ?? ''));
          ?>
          <div
            class="hero-slide"
            style="background-image: linear-gradient(90deg, rgba(15,23,42,0.74) 0%, rgba(15,23,42,0.48) 42%, rgba(15,23,42,0.22) 72%, rgba(15,23,42,0.54) 100%), url('<?php echo htmlspecialchars($slideUrl, ENT_QUOTES, 'UTF-8'); ?>');"
            <?php if ($slideSlot !== ''): ?>
              data-fe-image-slot="<?php echo htmlspecialchars($slideSlot, ENT_QUOTES, 'UTF-8'); ?>"
              data-fe-image-url="<?php echo htmlspecialchars($slideUrl, ENT_QUOTES, 'UTF-8'); ?>"
              data-fe-image-mode="background"
            <?php endif; ?>
          ></div>
        <?php endforeach; ?>
        <?php if (empty($heroSlides)): ?>
          <div class="hero-slide" style="background-image: linear-gradient(90deg, rgba(15,23,42,0.74) 0%, rgba(15,23,42,0.48) 42%, rgba(15,23,42,0.22) 72%, rgba(15,23,42,0.54) 100%), var(--img-1);"></div>
        <?php endif; ?>
      </div>
      <div class="container hero-inner">
        <div class="hero-grid">
          <div class="hero-copy">
            <div class="hero-kicker"><span class="dot" aria-hidden="true"></span> About Top Dental Clinic</div>
            <h1>Top Dental Clinic, Focused on Refined Porcelain Smile Design in Da Nang</h1>
            <p>Top Dental Clinic follows a modern cosmetic dentistry standard, combining experienced doctors, strict sterilization, and advanced restorative technology to deliver a treatment experience that feels safe, refined, and distinctive.</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book a consultation</a>
              <a class="btn btn-ghost" href="#video-gioi-thieu"><i class="fa-solid fa-circle-play" aria-hidden="true"></i> Watch our introduction</a>
            </div>
          </div>
          <aside class="hero-panel">
            <h3>Top Dental Clinic</h3>
            <div class="hero-stats">
              <div class="hero-stat">
                <strong>12+</strong>
                <span>Years supporting patients in cosmetic and restorative dentistry.</span>
              </div>
              <div class="hero-stat">
                <strong>15.000+</strong>
                <span>Treatment and smile makeover cases managed through personalized care pathways.</span>
              </div>
              <div class="hero-stat">
                <strong>98%</strong>
                <span>Patient satisfaction across porcelain, implant, and orthodontic services.</span>
              </div>
            </div>
          </aside>
        </div>
      </div>
      <div class="hero-slider-dots" id="heroSliderDots"></div>
    </section>

    <section class="section">
      <div class="container">
        <div class="story">
          <article class="card">
            <div class="card-body story-copy">
              <div class="section-head">
                <span class="eyebrow">Our approach</span>
                <h2>We shape the clinic as a complete smile-care environment.</h2>
                <p>Top Dental Clinic does not focus only on the final aesthetic result. We also care deeply about how reassured patients feel each time they step into the clinic. From consultation and diagnosis to planning and aftercare, every touchpoint is designed to be clear, professional, and respectful of the individual experience.</p>
              </div>
              <div class="bullet-list">
                <div class="bullet-item">
                  <div class="bullet-icon"><i class="fa-solid fa-tooth"></i></div>
                  <div>
                    <strong>Strong focus on porcelain smile design</strong>
                    <span>We prioritize slim tooth form, facial harmony, natural shade selection, and a smile ratio tailored to each patient.</span>
                  </div>
                </div>
                <div class="bullet-item">
                  <div class="bullet-icon"><i class="fa-solid fa-shield-heart"></i></div>
                  <div>
                    <strong>Safety and tooth preservation first</strong>
                    <span>Every indication is explained clearly, with minimally invasive thinking and long-term stability placed at the center.</span>
                  </div>
                </div>
                <div class="bullet-item">
                  <div class="bullet-icon"><i class="fa-solid fa-microscope"></i></div>
                  <div>
                    <strong>Accurate diagnosis, transparent planning</strong>
                    <span>Images, data, and treatment options are presented in a way patients can easily understand and confidently decide on.</span>
                  </div>
                </div>
              </div>
            </div>
          </article>
          <article class="card" id="video-gioi-thieu">
            <div class="video-shell">
              <iframe src="<?php echo htmlspecialchars($videoEmbed, ENT_QUOTES, 'UTF-8'); ?>" title="Top Dental Clinic introduction video" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
            <div class="video-caption">
              <strong>A quick look at our space and service style</strong>
              <span>See how Top Dental Clinic designs the patient journey, from welcome and examination to treatment planning and a modern, private, carefully guided experience.</span>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head center">
          <span class="eyebrow">Core values</span>
          <h2>The cosmetic dentistry standard we pursue</h2>
          <p>Every service is designed around three goals: personalized beauty, safe treatment, and dependable care after completion.</p>
        </div>
        <div class="values-grid">
          <article class="value-card">
            <i class="fa-solid fa-gem" aria-hidden="true"></i>
            <h3>Refined aesthetics</h3>
            <p>We aim for smiles that feel elegant, light, and facially balanced instead of following a generic tooth shape.</p>
          </article>
          <article class="value-card">
            <i class="fa-solid fa-stethoscope" aria-hidden="true"></i>
            <h3>Precise treatment</h3>
            <p>Every case is carefully diagnosed so the treatment roadmap is clear, risk is reduced, and long-term durability is improved.</p>
          </article>
          <article class="value-card">
            <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
            <h3>Attentive care</h3>
            <p>Patients are actively followed up, reminded about review visits, and guided with practical care instructions to maintain the best outcome.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head center">
          <span class="eyebrow">Patient moments</span>
          <h2>Selected moments from patients who chose Top Dental Clinic</h2>
          <p>These visuals give new visitors a better feel for our care style, reception environment, and the real patient journey inside the clinic.</p>
        </div>
        <div class="customer-gallery">
          <?php foreach ($customerShowcase as $index => $item): ?>
            <?php
              $slotKey = trim((string) ($item['slot'] ?? ''));
              $titleSlot = $slotKey !== '' ? $slotKey . '-title' : '';
              $descriptionSlot = $slotKey !== '' ? $slotKey . '-description' : '';
              $imageUrl = trim((string) ($item['image'] ?? ''));
              $title = trim((string) ($item['title'] ?? 'Patient Story'));
              $description = trim((string) ($item['description'] ?? ''));
            ?>
            <article
              class="customer-photo"
              <?php if ($slotKey !== ''): ?>
                data-fe-image-slot="<?php echo htmlspecialchars($slotKey, ENT_QUOTES, 'UTF-8'); ?>"
                data-fe-image-url="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                data-fe-image-target="img"
              <?php endif; ?>
            >
              <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Top Dental Clinic patient <?php echo $index + 1; ?>" loading="lazy">
              <div class="customer-caption">
                <h3<?php if ($titleSlot !== ''): ?> data-fe-text-slot="<?php echo htmlspecialchars($titleSlot, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo $titleSlot !== '' ? front_editor_text_slot_html('about-en', $titleSlot, $title) : htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p<?php if ($descriptionSlot !== ''): ?> data-fe-text-slot="<?php echo htmlspecialchars($descriptionSlot, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo $descriptionSlot !== '' ? front_editor_text_slot_html('about-en', $descriptionSlot, $description) : htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section highlight">
      <div class="container">
        <div class="highlight-grid">
          <div class="quote-box">
            <span class="eyebrow">Brand promise</span>
            <blockquote>"A beautiful smile should not only look good today. It should stay stable, comfortable, and confidence-building for years to come."</blockquote>
            <p>That is why Top Dental Clinic invests consistently in doctors, materials, technology, and follow-up systems, especially for porcelain smile design and full-mouth restorative care.</p>
          </div>
          <aside class="contact-panel">
            <h3>Quick contact</h3>
            <div class="contact-list">
              <div class="contact-line">
                <i class="fa-solid fa-phone"></i>
                <span>Hotline: <a href="tel:<?php echo htmlspecialchars(str_replace(' ', '', $hotline), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($hotline, ENT_QUOTES, 'UTF-8'); ?></a></span>
              </div>
              <div class="contact-line">
                <i class="fa-solid fa-envelope"></i>
                <span>Email: <a href="mailto:<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></a></span>
              </div>
              <div class="contact-line">
                <i class="fa-solid fa-location-dot"></i>
                <span><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="contact-line">
                <i class="fa-regular fa-clock"></i>
                <span>Flexible appointment support, pre-treatment guidance, and structured follow-up after care.</span>
              </div>
            </div>
            <a class="btn btn-light" href="/contact-us"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> Request private advice</a>
          </aside>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head center">
          <span class="eyebrow">Key expertise</span>
          <h2>Services patients choose most often at Top Dental Clinic</h2>
          <p>The list below reflects the service groups currently delivered by the clinic, with strong focus on porcelain aesthetics and comprehensive smile care.</p>
        </div>
        <div class="expertise-grid">
          <?php if (!empty($services)): ?>
            <?php foreach ($services as $service): ?>
              <?php $thumb = trim((string) ($service['img'] ?? '')); ?>
              <article class="expertise-card">
                <div class="expertise-thumb" style="<?php echo $thumb !== '' ? "background-image: linear-gradient(180deg, rgba(30,64,175,0.18), rgba(30,64,175,0.02)), url('" . htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') . "');" : ''; ?>"></div>
                <div class="expertise-body">
                  <h3><?php echo htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                  <p><?php echo htmlspecialchars($service['excerpt'] !== '' ? $service['excerpt'] : 'Personalized treatment and cosmetic solutions built around your real oral condition.', ENT_QUOTES, 'UTF-8'); ?></p>
                  <div class="expertise-actions">
                    <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <article class="expertise-card">
              <div class="expertise-thumb"></div>
              <div class="expertise-body">
                <h3>Porcelain smile design</h3>
                <p>Natural-looking tooth form designed for facial harmony, refined presence, and reliable long-term wear.</p>
                <div class="expertise-actions">
                  <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                </div>
              </div>
            </article>
            <article class="expertise-card">
              <div class="expertise-thumb"></div>
              <div class="expertise-body">
                <h3>Implant restoration</h3>
                <p>Restore missing teeth with a solution that supports function, aesthetics, and comfortable long-term chewing ability.</p>
                <div class="expertise-actions">
                  <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                </div>
              </div>
            </article>
            <article class="expertise-card">
              <div class="expertise-thumb"></div>
              <div class="expertise-body">
                <h3>Modern orthodontics</h3>
                <p>Align teeth, improve bite balance, and build a better foundation for a healthy and attractive smile.</p>
                <div class="expertise-actions">
                  <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                </div>
              </div>
            </article>
            <article class="expertise-card">
              <div class="expertise-thumb"></div>
              <div class="expertise-body">
                <h3>General dentistry</h3>
                <p>Routine examinations, foundational treatment, and preventive care to maintain strong oral health over time.</p>
                <div class="expertise-actions">
                  <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book now</a>
                </div>
              </div>
            </article>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head center">
          <span class="eyebrow">Patient journey</span>
          <h2>A clear care journey that is easy to follow</h2>
          <p>From the first appointment to the aftercare phase, each step is structured so patients always understand where they are within the treatment plan.</p>
        </div>
        <div class="journey-grid">
          <article class="journey-card">
            <i class="fa-solid fa-comments" aria-hidden="true"></i>
            <h3>1. First consultation</h3>
            <p>We receive your request, listen to your smile goals, answer concerns, and arrange the right clinical appointment.</p>
          </article>
          <article class="journey-card">
            <i class="fa-solid fa-camera-retro" aria-hidden="true"></i>
            <h3>2. Diagnosis and planning</h3>
            <p>Your dental condition is analyzed in detail, with clear recommendations for timing, cost, and expected outcomes.</p>
          </article>
          <article class="journey-card">
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
            <h3>3. Treatment execution</h3>
            <p>Treatment is carried out under strict control of sterilization, technique, and patient comfort throughout the process.</p>
          </article>
          <article class="journey-card">
            <i class="fa-solid fa-heart-circle-check" aria-hidden="true"></i>
            <h3>4. Aftercare follow-up</h3>
            <p>Detailed aftercare instructions, review reminders, and ongoing support help maintain a beautiful, stable, and safe result.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-head center">
          <span class="eyebrow">Space and systems</span>
          <h2>We invest in the experience, not only the equipment</h2>
          <p>A professional clinic should create reassurance from the reception area to the unseen operating systems behind every procedure.</p>
        </div>
        <div class="facility-grid">
          <article class="facility-card">
            <i class="fa-solid fa-hospital-user" aria-hidden="true"></i>
            <h3>Private and welcoming reception</h3>
            <p>The reception and consultation areas are arranged so patients feel relaxed, comfortable asking questions, and confident in choosing the right option.</p>
          </article>
          <article class="facility-card">
            <i class="fa-solid fa-shield-virus" aria-hidden="true"></i>
            <h3>Strict sterilization workflow</h3>
            <p>Top Dental Clinic maintains strong sterilization control and disciplined internal workflows to protect safety throughout treatment.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="cta">
          <div class="cta-grid">
            <div>
              <span class="eyebrow" style="color: rgba(255,255,255,0.82);">Ready for your next smile chapter?</span>
              <h2>Book with Top Dental Clinic for a treatment plan that truly fits you.</h2>
              <p>Whether you are considering porcelain veneers, implant restoration, or a complete smile-care plan, our team is ready to guide the journey with you.</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <a class="btn btn-light" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book an appointment</a>
              <a class="btn btn-ghost" href="tel:<?php echo htmlspecialchars(str_replace(' ', '', $hotline), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> Call now</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <?php if (function_exists('front_editor_render')) { front_editor_render('about-en'); } ?>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
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
            (function(index){
              dot.addEventListener("click", function(){
                go(index);
              });
            })(i);
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
          function next(){
            go(current + 1);
          }
          function start(){
            timer = window.setInterval(next, 5000);
          }
          function reset(){
            if (timer) window.clearInterval(timer);
            start();
          }
          var touchStartX = 0;
          slider.addEventListener("touchstart", function(e){
            touchStartX = e.changedTouches[0].screenX;
          }, { passive: true });
          slider.addEventListener("touchend", function(e){
            var diff = touchStartX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 50) {
              if (diff > 0) next();
              else go(current - 1);
            }
          }, { passive: true });
          render();
          start();
        }
        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
        else boot();
      })();
    </script>
  </body>
</html>
