<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

$seo = front_editor_page_seo('home', [
  'title' => 'MedReview • Nen tang review y te va co so kham chua benh',
  'description' => 'MedReview giup nguoi dung tim kiem bac si, co so y te, danh muc chuyen khoa va noi dung review huu ich.',
]);
$title = $seo['title'];
$description = $seo['description'];
$canonicalPath = (string) ($seo['canonical_path'] ?? '/');
$seoKeywords = (string) ($seo['keywords'] ?? '');
$locale = site_page_locale('home');
?>
<!doctype html>
<html lang="<?php echo $locale === 'en' ? 'en' : 'vi'; ?>">
  <head>
    <?php echo site_favicon_tags(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalPath, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
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
        --img-1: url("https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=1200&q=80");
      }
      *{ box-sizing: border-box; }
      html{ height: 100%; }
      body{ min-height: 100%; }
      body{
        margin: 0;
        font-family: var(--ui-font, "Inter", system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial);
        color: var(--text);
        background:
          radial-gradient(900px 520px at 15% 0%, rgba(59,130,246,0.10), transparent 62%),
          radial-gradient(900px 520px at 85% 8%, rgba(30,64,175,0.08), transparent 62%),
          linear-gradient(180deg, #f8fbff 0%, #eef5ff 46%, #ffffff 100%);
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
      .brand-title strong{ letter-spacing: -0.03em; font-weight: 800; color: var(--brand); }
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
      .hero-bg{ position:absolute; inset: 0; background: linear-gradient(90deg, rgba(0,0,0,0.54) 0%, rgba(0,0,0,0.30) 42%, rgba(0,0,0,0.12) 72%, rgba(0,0,0,0.24) 100%), var(--img-1) center/cover no-repeat; transform: scale(1.03); filter: saturate(0.98) contrast(1.02); pointer-events:none; }
      .hero-inner{ position: relative; padding: 110px 0 72px; min-height: 78vh; display:flex; align-items:center; }
      .hero-grid{ display:grid; grid-template-columns: 1.05fr 0.95fr; gap: 28px; align-items: center; }
      .hero-copy{ padding: 22px 20px; border-radius: var(--radius); background: rgba(0,0,0,0.26); border: 1px solid rgba(255,255,255,0.14); box-shadow: 0 28px 80px rgba(0,0,0,0.22); color: rgba(255,255,255,0.96); backdrop-filter: blur(12px); }
      .hero-kicker{ display:inline-flex; align-items:center; gap: 10px; font-weight: 600; font-size: 12px; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255,255,255,0.86); }
      .hero-kicker .dot{ width: 8px; height: 8px; border-radius: 999px; background: var(--brand-2); box-shadow: 0 0 0 4px rgba(59,130,246,0.2); }
      .hero-copy h1{ margin: 0 0 10px; font-weight: 800; letter-spacing: -0.05em; line-height: 1.1; font-size: clamp(32px, 3.2vw, 46px); }
      .hero-copy p{ margin:0; color: rgba(255,255,255,0.82); line-height: 1.7; max-width: 62ch; }
      .hero-actions{ margin-top:18px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
      .hero-actions .btn-ghost{ border-color: rgba(255,255,255,0.22); background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.94); }
      .features{ margin-top: 18px; display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:10px; }
      .feature{ display:flex; gap:10px; align-items:center; border-radius:var(--radius); border:1px solid rgba(255,255,255,0.18); padding:10px 12px; background: linear-gradient(180deg, rgba(255,255,255,0.16), rgba(255,255,255,0.10)); color: rgba(255,255,255,0.96); backdrop-filter: blur(10px); }
      .section{ padding: 34px 0; }
      .section h2{ margin:0 0 14px; text-align:center; font-size:22px; font-weight:800; letter-spacing:-0.04em; }
      .section-lead{ text-align:center; color:var(--muted); max-width:70ch; margin:0 auto 16px; line-height:1.7; font-size:14px; }
      .grid{ display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:14px; }
      .grid-3{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:14px; }
      .card{ border-radius: var(--radius); border:1px solid var(--border); background: var(--surface-2); box-shadow: var(--shadow); overflow:hidden; transition: transform 180ms ease, box-shadow 180ms ease, filter 180ms ease; }
      .card:hover{ transform: translateY(-2px); box-shadow: 0 24px 70px rgba(17,24,39,0.16); }
      .card .img{ height:140px; background: var(--img-1) center/cover no-repeat; }
      .card .body{ padding:12px 12px 14px; display:grid; gap:6px; text-align:center; }
      .project-grid{ display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:14px; }
      .project{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow:hidden;
        transition: transform 180ms ease, box-shadow 180ms ease;
      }
      .project:hover{
        transform: translateY(-2px);
        box-shadow: 0 24px 70px rgba(17,24,39,0.16);
      }
      .project .img{
        height: 220px;
        background: var(--img-1) center/cover no-repeat;
        position: relative;
      }
      .project .img::after{
        content:"";
        position:absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0,0,0,0.00), rgba(0,0,0,0.22));
      }
      .badge-row{
        position:absolute;
        left: 12px;
        bottom: 12px;
        display:flex;
        gap: 8px;
        flex-wrap: wrap;
        z-index: 1;
      }
      .badge{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.18);
        background: rgba(0,0,0,0.34);
        color: rgba(255,255,255,0.92);
        font-size: 12px;
        backdrop-filter: blur(10px);
      }
      .project .body{
        padding: 12px 12px 14px;
        display:grid;
        gap: 8px;
      }
      .project h3{
        margin: 0;
        font-size: 14px;
        letter-spacing: -0.01em;
      }
      .meta{
        display:flex;
        gap: 10px;
        align-items:center;
        flex-wrap: wrap;
        color: var(--muted);
        font-size: 13px;
      }
      .meta span{
        display:inline-flex;
        gap: 8px;
        align-items:center;
      }
      .project .actions{
        margin-top: 2px;
        display:flex;
        gap: 10px;
        flex-wrap: wrap;
      }
      .project .actions .btn{
        padding: 10px 12px;
        font-size: 13px;
      }
      .news{ display:grid; grid-template-columns: 1fr 320px; gap:14px; align-items:stretch; }
      .contact{ border-radius: var(--radius); border:1px solid var(--border); background: var(--surface-2); box-shadow: var(--shadow); padding:14px; height:100%; display:flex; flex-direction:column; }
      .contact h3{ margin:0 0 10px; font-size:16px; }
      .contact .line{ display:flex; gap:10px; align-items:flex-start; padding:10px 0; border-top:1px dashed var(--border); color:var(--muted); font-size:13px; }
      .contact i{ margin-top:2px; width:18px; text-align:center; color: rgba(255,255,255,0.92); }
      .site-footer{ margin-top:34px; position:relative; overflow:hidden; color: rgba(255,255,255,0.95); background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%); }
      .site-footer::before{ content:""; position:absolute; inset:0; background: var(--img-1) center/cover no-repeat; filter: saturate(0.7) contrast(1.05); opacity:0.15; z-index:0; pointer-events:none; }
      .footer-layer{ position:relative; z-index:1; }
      .footer-cta{ border-bottom:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.34); }
      .footer-cta-inner{ padding:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
      .footer-cta-inner b{ font-family:inherit; font-weight:600; color:rgba(255,255,255,0.92); }
      .footer-main{ padding:18px 0 10px; }
      .footer-cols{ display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:18px; }
      .footer-col h4{ margin:0 0 10px; font-size:14px; font-weight:700; color: rgba(255,255,255,0.92); padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.10); }
      .footer-link{ display:block; color:rgba(255,255,255,0.78); font-size:13px; padding:6px 0; }
      .footer-link:hover{ color: rgba(255,255,255,0.92); }
      .footer-contact{ display:grid; gap:8px; color: rgba(255,255,255,0.78); font-size:13px; }
      .footer-contact i{ width:18px; margin-top:2px; text-align:center; color: rgba(255,255,255,0.90); }
      .footer-bottom{ border-top:1px solid rgba(255,255,255,0.10); padding:12px 0 14px; text-align:center; color: rgba(255,255,255,0.70); font-size:13px; }
      /* Mobile Optimized Footer & Services */
      @media (max-width:1024px){ 
        .hero-grid{ grid-template-columns:1fr; } 
        .features{ grid-template-columns: repeat(2, minmax(0,1fr)); } 
        .grid{ grid-template-columns: repeat(2,minmax(0,1fr)); } 
        .project-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); } 
        .news{ grid-template-columns:1fr; } 
        .footer-cols{ grid-template-columns: repeat(2,minmax(0,1fr)); } 
      }
      @media (max-width:680px){
        .nav-toggle{ display:inline-flex; }
        .navlinks{ display:none; position:absolute; left:16px; right:16px; top:calc(100% + 10px); padding:10px; border-radius:16px; border:1px solid var(--border); background: rgba(255,255,255,0.96); box-shadow: 0 26px 80px rgba(17,24,39,0.18); flex-direction: column; align-items:stretch; gap:6px; }
        .navlinks a{ padding: 12px; }
        .navlinks.is-open{ display:flex; }
        .pill{ display:none; }
        .grid, .project-grid{ grid-template-columns: 1fr; }
        .grid-3{ grid-template-columns: 1fr; }
        .footer-main{ padding: 16px 0 8px; }
        .footer-cols{ grid-template-columns: 1fr; gap: 12px; }
        .footer-col h4{ margin: 0 0 8px; }
        .footer-link{ padding: 8px 0; font-size: 14px; }
        .footer-cta-inner{
          padding: 12px 0;
          flex-direction: column;
          align-items: flex-start;
          gap: 10px;
        }
        .footer-cta-inner .btn{
          width: 100%;
          justify-content: center;
        }
        .footer-contact{ gap: 10px; }
        .footer-bottom{ font-size: 12px; padding: 10px 0 12px; }
      }

      /*
       * Home-page composition
       * ---------------------
       * The header, footer and directory components intentionally keep their
       * own styles.  These rules only refine the public home canvas so it can
       * use the same calm, high-density visual language as the new back office
       * without changing its search markup or real-data queries.
       */
      html{ scroll-behavior:smooth; }
      body.site-home{
        min-width:320px;
        overflow-x:hidden;
        color:#10203d;
        background:
          radial-gradient(860px 460px at -10% 4%, rgba(96,165,250,.13), transparent 68%),
          radial-gradient(900px 520px at 108% 30%, rgba(125,211,252,.14), transparent 67%),
          linear-gradient(180deg,#f8fbff 0%,#f3f8ff 38%,#ffffff 82%);
      }
      body.site-home::selection{ background:rgba(96,165,250,.26); color:#10203d; }
      body.site-home .container{ width:min(100% - 40px, 1320px); }
      body.site-home .med-home{
        position:relative;
        isolation:isolate;
        overflow:clip;
        padding:28px 0 84px;
        background:transparent;
      }
      body.site-home .med-home::before,
      body.site-home .med-home::after{
        content:"";
        position:absolute;
        z-index:-1;
        pointer-events:none;
        border-radius:999px;
        filter:blur(1px);
      }
      body.site-home .med-home::before{
        width:440px;
        height:440px;
        top:260px;
        left:-260px;
        background:radial-gradient(circle,rgba(147,197,253,.18),rgba(147,197,253,0) 69%);
      }
      body.site-home .med-home::after{
        width:500px;
        height:500px;
        top:760px;
        right:-320px;
        background:radial-gradient(circle,rgba(191,219,254,.25),rgba(191,219,254,0) 70%);
      }
      body.site-home .med-home .home-section{ margin-top:54px; }
      body.site-home .med-home .home-section:first-child{ margin-top:0; }
      body.site-home .med-home .section-head{
        align-items:flex-end;
        margin-bottom:20px;
      }
      body.site-home .med-home .section-head > div{ min-width:0; }
      body.site-home .med-home .section-kicker{
        display:inline-flex;
        align-items:center;
        gap:7px;
        margin-bottom:8px;
        color:#2563eb;
        font-size:10px;
        letter-spacing:.115em;
      }
      body.site-home .med-home .section-kicker::before{
        content:"";
        width:18px;
        height:2px;
        border-radius:99px;
        background:linear-gradient(90deg,#60a5fa,#2563eb);
      }
      body.site-home .med-home .section-head h2{
        color:#0f1f3d;
        font-size:clamp(25px,2.1vw,32px);
        letter-spacing:-.052em;
      }
      body.site-home .med-home .section-head p{
        max-width:590px;
        color:#64748b;
        font-size:13px;
      }
      body.site-home .med-home .section-link{
        padding:9px 2px;
        color:#1d4ed8;
        font-size:12px;
        transition:color .18s ease,transform .18s ease;
      }
      body.site-home .med-home .section-link:hover{
        color:#1e40af;
        transform:translateX(2px);
      }

      /* Preserve the existing family-photo hero, while giving it a cleaner
       * editorial frame and a more legible search focal point. */
      body.site-home .med-home .hero-wrap{
        min-height:clamp(520px,47vw,580px);
        border:1px solid rgba(191,219,254,.9);
        border-radius:30px;
        box-shadow:0 26px 70px rgba(30,64,175,.14), inset 0 1px 0 rgba(255,255,255,.8);
      }
      body.site-home .med-home .hero-wrap::before{
        background:
          linear-gradient(90deg,rgba(248,251,255,.985) 0%,rgba(248,251,255,.955) 45%,rgba(248,251,255,.73) 66%,rgba(248,251,255,.13) 100%),
          linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));
      }
      body.site-home .med-home .hero-inner{
        min-height:clamp(520px,47vw,580px);
        padding:64px clamp(34px,5vw,76px);
      }
      body.site-home .med-home .hero-copy{ width:min(100%,650px); }
      body.site-home .med-home .hero-eyebrow{
        padding:8px 12px;
        box-shadow:0 10px 22px rgba(5,150,105,.08);
      }
      body.site-home .med-home .hero-copy h1{
        max-width:650px;
        margin-top:19px;
        color:#0f1e3b;
        font-size:clamp(42px,4.25vw,59px);
        line-height:1.1;
        letter-spacing:-.07em;
      }
      body.site-home .med-home .hero-copy p{
        max-width:575px;
        margin-top:21px;
        color:#526581!important;
        font-size:15px;
        line-height:1.76;
      }
      body.site-home .med-home .hero-search{ max-width:660px; margin-top:29px; }
      body.site-home .med-home .hero-search-row{
        grid-template-columns:minmax(0,1fr) 48px;
        gap:8px;
        padding:6px;
        border-color:rgba(191,219,254,.95);
        border-radius:18px;
        box-shadow:0 18px 38px rgba(15,23,42,.09),0 2px 8px rgba(37,99,235,.04);
      }
      body.site-home .med-home .hero-search-field{ padding:0 15px; }
      body.site-home .med-home .hero-search-field input{ font-size:14px; }
      body.site-home .med-home .hero-search-btn{
        display:grid;
        width:48px;
        min-width:48px;
        height:48px;
        place-items:center;
        padding:0;
        border-radius:13px;
        background:linear-gradient(135deg,#3b82f6 0%,#2563eb 55%,#1d4ed8 100%);
        box-shadow:0 12px 22px rgba(37,99,235,.22);
        transition:transform .18s ease,box-shadow .18s ease,filter .18s ease;
      }
      body.site-home .med-home .hero-search-btn > i{ font-size:19px; line-height:1; }
      body.site-home .med-home .hero-search-btn-label{ display:none; }
      body.site-home .med-home .hero-search-btn:hover{
        transform:translateY(-1px);
        filter:brightness(1.03);
        box-shadow:0 15px 28px rgba(37,99,235,.3);
      }
      body.site-home .med-home .hero-terms{ margin-top:16px; }
      body.site-home .med-home .hero-terms a{
        padding:6px 11px;
        background:rgba(255,255,255,.82);
        box-shadow:0 5px 12px rgba(15,23,42,.035);
      }

      /* Data-driven discovery areas. The components remain unchanged; only
       * their density, hierarchy and surface treatment are refined here. */
      body.site-home .med-home .category-grid{ gap:14px; }
      body.site-home .med-home .category-card{
        min-height:96px;
        border-color:rgba(219,234,254,.96);
        border-radius:19px;
        background:linear-gradient(145deg,rgba(255,255,255,.98),rgba(248,251,255,.94));
        box-shadow:0 12px 28px rgba(15,23,42,.045),inset 0 1px 0 rgba(255,255,255,.9);
      }
      body.site-home .med-home .category-card:hover{ border-color:#93c5fd; }
      body.site-home .med-home .category-icon{
        background:linear-gradient(145deg,#eff6ff,#dbeafe);
        box-shadow:inset 0 1px 0 rgba(255,255,255,.8);
      }
      body.site-home .med-home .facility-grid,
      body.site-home .med-home .toplist-grid{ gap:18px; }
      body.site-home .med-home .facility-card{
        border-color:rgba(219,234,254,.95);
        border-radius:22px;
        box-shadow:0 14px 34px rgba(15,23,42,.06),inset 0 1px 0 rgba(255,255,255,.92);
      }
      body.site-home .med-home .facility-media{ height:176px; }
      body.site-home .med-home .facility-media::after{
        content:"";
        position:absolute;
        inset:auto 0 0;
        height:42%;
        background:linear-gradient(180deg,transparent,rgba(15,23,42,.08));
        pointer-events:none;
      }
      body.site-home .med-home .facility-category{ z-index:1; box-shadow:0 6px 18px rgba(15,23,42,.08); }
      body.site-home .med-home .facility-body{ padding:17px; }
      body.site-home .med-home .facility-body h3{ font-size:16px; }
      body.site-home .med-home .facility-subtitle{ min-height:39px; }
      body.site-home .med-home .facility-info{ margin-top:14px; }
      body.site-home .med-home .toplist-card{
        min-height:232px;
        border-color:rgba(219,234,254,.98);
        border-radius:22px;
        background:linear-gradient(145deg,#ffffff 0%,#f4f8ff 72%,#edf5ff 100%);
        box-shadow:0 14px 34px rgba(15,23,42,.055),inset 0 1px 0 rgba(255,255,255,.95);
      }
      body.site-home .med-home .toplist-card:nth-child(2){ background:linear-gradient(145deg,#ffffff 0%,#f0f9ff 73%,#e7f5ff 100%); }
      body.site-home .med-home .toplist-card:nth-child(3){ background:linear-gradient(145deg,#ffffff 0%,#f5f7ff 73%,#eff3ff 100%); }
      body.site-home .med-home .toplist-card h3{ font-size:18px; }
      body.site-home .med-home .data-band{
        border-radius:25px;
        background:linear-gradient(110deg,#102d6c 0%,#1d4ed8 56%,#3b82f6 100%);
        box-shadow:0 22px 48px rgba(29,78,216,.2),inset 0 1px 0 rgba(255,255,255,.16);
      }
      body.site-home .med-home .data-band-copy{ background:linear-gradient(135deg,rgba(15,23,42,.2),rgba(15,23,42,.06)); }
      body.site-home .med-home .data-stat strong{ font-size:30px; }
      body.site-home .med-home .panel{
        border-color:rgba(219,234,254,.96);
        border-radius:23px;
        box-shadow:0 14px 34px rgba(15,23,42,.055),inset 0 1px 0 rgba(255,255,255,.92);
      }
      body.site-home .med-home .panel-head{ margin-bottom:14px; }
      body.site-home .med-home .doctor-item,
      body.site-home .med-home .review-item{
        border-radius:13px;
        transition:background .18s ease,transform .18s ease;
      }
      body.site-home .med-home .doctor-item:hover,
      body.site-home .med-home .review-item:hover{
        background:#f7faff;
        transform:translateX(2px);
      }

      @media (max-width:1080px){
        body.site-home .med-home{ padding-top:24px; }
        body.site-home .med-home .home-section{ margin-top:44px; }
        body.site-home .med-home .hero-wrap,
        body.site-home .med-home .hero-inner{ min-height:520px; }
        body.site-home .med-home .hero-inner{ padding:50px 48px; }
        body.site-home .med-home .hero-copy h1{ font-size:clamp(40px,5.15vw,54px); }
      }
      @media (max-width:820px){
        body.site-home .container{ width:min(100% - 30px, 1320px); }
        body.site-home .med-home{ padding:18px 0 58px; }
        body.site-home .med-home .home-section{ margin-top:36px; }
        body.site-home .med-home .hero-wrap{ border-radius:25px; }
        body.site-home .med-home .hero-wrap,
        body.site-home .med-home .hero-inner{ min-height:510px; }
        body.site-home .med-home .hero-inner{ padding:42px 34px; }
        body.site-home .med-home .hero-copy{ width:min(100%,590px); }
        body.site-home .med-home .section-head{ margin-bottom:16px; }
        body.site-home .med-home .facility-grid,
        body.site-home .med-home .toplist-grid{ gap:14px; }
        body.site-home .med-home .facility-media{ height:160px; }
        body.site-home .med-home .data-stat strong{ font-size:27px; }
      }
      @media (max-width:560px){
        body.site-home .container{ width:min(100% - 24px, 1320px); }
        body.site-home .med-home{ padding-top:14px; }
        body.site-home .med-home .home-section{ margin-top:32px; }
        body.site-home .med-home .section-head{ align-items:flex-start; gap:10px; }
        body.site-home .med-home .section-head h2{ font-size:23px; }
        body.site-home .med-home .section-head p{ margin-top:7px; font-size:12px; line-height:1.62; }
        body.site-home .med-home .section-link{ padding-top:3px; }
        body.site-home .med-home .hero-wrap,
        body.site-home .med-home .hero-inner{ min-height:auto; }
        body.site-home .med-home .hero-inner{ padding:33px 21px 31px; }
        body.site-home .med-home .hero-copy h1{ margin-top:16px; font-size:clamp(33px,10vw,43px); line-height:1.12; }
        body.site-home .med-home .hero-copy p{ margin-top:16px; font-size:13px; line-height:1.7; }
        body.site-home .med-home .hero-search{ margin-top:23px; }
        body.site-home .med-home .hero-search-row{ grid-template-columns:minmax(0,1fr) 48px; padding:5px; }
        body.site-home .med-home .hero-search-field input{ height:47px; }
        body.site-home .med-home .hero-search-btn{ width:48px; min-width:48px; height:48px; }
        body.site-home .med-home .hero-terms{ gap:6px; margin-top:13px; }
        body.site-home .med-home .hero-terms span{ width:100%; }
        body.site-home .med-home .category-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
        body.site-home .med-home .category-card{ min-height:86px; padding:12px; gap:10px; }
        body.site-home .med-home .category-card strong{ font-size:12px; }
        body.site-home .med-home .category-card small{ font-size:10px; }
        body.site-home .med-home .category-icon{ flex-basis:40px; width:40px; height:40px; font-size:20px; }
        body.site-home .med-home .facility-grid,
        body.site-home .med-home .toplist-grid{ grid-template-columns:1fr; }
        body.site-home .med-home .facility-media{ height:174px; }
        body.site-home .med-home .data-band{ border-radius:20px; }
        body.site-home .med-home .data-band-copy,
        body.site-home .med-home .data-stat{ padding:18px 15px; }
        body.site-home .med-home .data-band-copy h2{ font-size:19px; }
        body.site-home .med-home .data-stat strong{ font-size:25px; }
        body.site-home .med-home .panel{ padding:18px; border-radius:20px; }
      }

      /* Phone app shell ---------------------------------------------------
       * The homepage stays a normal document, but on touch screens its
       * rhythm follows familiar native-app patterns: one compact dashboard
       * card, quick-action rails and short, thumb-friendly surfaces. */
      @media (max-width:640px){
        body.site-home{
          background:
            radial-gradient(440px 300px at 102% 2%,rgba(96,165,250,.18),transparent 68%),
            radial-gradient(430px 300px at -12% 26%,rgba(125,211,252,.16),transparent 70%),
            linear-gradient(180deg,#f6faff 0%,#eef6ff 46%,#f8fbff 100%);
        }
        body.site-home .med-home{
          padding:10px 0 calc(52px + env(safe-area-inset-bottom, 0px));
        }
        body.site-home .med-home::before{
          top:170px; left:-310px; width:430px; height:430px; opacity:.78;
        }
        body.site-home .med-home::after{
          top:680px; right:-370px; width:470px; height:470px; opacity:.72;
        }
        body.site-home .med-home .home-section{ margin-top:28px; }
        body.site-home .med-home .section-head{
          align-items:flex-start; gap:10px; margin-bottom:13px;
        }
        body.site-home .med-home .section-kicker{
          margin-bottom:5px; font-size:10px; letter-spacing:.08em;
        }
        body.site-home .med-home .section-head h2{
          font-size:21px; line-height:1.17; letter-spacing:-.048em;
        }
        body.site-home .med-home .section-head p{
          display:-webkit-box; max-width:275px; margin-top:5px; overflow:hidden;
          font-size:13px; line-height:1.55; -webkit-box-orient:vertical;
          -webkit-line-clamp:2;
        }
        body.site-home .med-home .section-link{
          flex:0 0 auto; min-height:34px; padding:7px 1px 5px; font-size:12px;
        }

        /* Dashboard-like hero */
        body.site-home .med-home .hero-wrap{
          min-height:0; border-color:rgba(191,219,254,.88); border-radius:25px;
          background-position:69% center;
          box-shadow:0 18px 42px rgba(30,64,175,.14),inset 0 1px 0 rgba(255,255,255,.92);
        }
        body.site-home .med-home .hero-wrap::before{
          background:
            radial-gradient(circle at 92% 10%,rgba(147,197,253,.22),transparent 33%),
            linear-gradient(103deg,rgba(249,252,255,.985) 0%,rgba(249,252,255,.965) 56%,rgba(248,251,255,.80) 78%,rgba(248,251,255,.28) 100%);
        }
        body.site-home .med-home .hero-inner{ min-height:0; padding:25px 18px 22px; }
        body.site-home .med-home .hero-copy{ width:100%; }
        body.site-home .med-home .hero-eyebrow{
          gap:6px; padding:6px 9px; border-color:rgba(16,185,129,.16);
          font-size:9px; letter-spacing:.018em;
        }
        body.site-home .med-home .hero-eyebrow i{ font-size:14px; }
        body.site-home .med-home .hero-copy h1{
          max-width:342px; margin-top:14px; font-size:clamp(30px,8.55vw,34px);
          line-height:1.085; letter-spacing:-.068em;
        }
        body.site-home .med-home .hero-copy p{
          max-width:298px; margin-top:13px; color:#536681!important;
          font-size:14px; line-height:1.6;
        }
        body.site-home .med-home .hero-search{ max-width:none; margin-top:18px; }
        body.site-home .med-home .hero-search-row{
          grid-template-columns:minmax(0,1fr) 48px; gap:4px; padding:4px;
          border-radius:17px;
          box-shadow:0 12px 28px rgba(15,35,66,.10),inset 0 1px 0 rgba(255,255,255,.92);
        }
        body.site-home .med-home .hero-search-field{ gap:8px; padding:0 10px; }
        body.site-home .med-home .hero-search-field i{ font-size:18px; }
        body.site-home .med-home .hero-search-field input{ height:48px; font-size:16px; }
        body.site-home .med-home .hero-search-btn{
          width:48px; min-width:48px; height:48px; border-radius:13px;
          box-shadow:0 9px 17px rgba(37,99,235,.24);
        }
        body.site-home .med-home .hero-terms{
          flex-wrap:nowrap; gap:7px; margin:12px -18px -5px; padding:0 18px 7px;
          overflow-x:auto; overscroll-behavior-x:contain; scrollbar-width:none;
          -webkit-overflow-scrolling:touch;
        }
        body.site-home .med-home .hero-terms::-webkit-scrollbar{ display:none; }
        body.site-home .med-home .hero-terms span,
        body.site-home .med-home .hero-terms a{ flex:0 0 auto; width:auto; }
        body.site-home .med-home .hero-terms a{ padding:6px 10px; }

        /* Horizontal app feed shelves */
        body.site-home .med-home .category-grid,
        body.site-home .med-home .facility-grid,
        body.site-home .med-home .toplist-grid{
          display:flex; align-items:stretch;
          overflow-x:auto; overscroll-behavior-x:contain; scroll-snap-type:x mandatory;
          scrollbar-width:none; -webkit-overflow-scrolling:touch;
        }
        body.site-home .med-home .category-grid::-webkit-scrollbar,
        body.site-home .med-home .facility-grid::-webkit-scrollbar,
        body.site-home .med-home .toplist-grid::-webkit-scrollbar{ display:none; }
        body.site-home .med-home .category-grid{
          gap:9px; padding:2px 18px 9px 1px;
        }
        body.site-home .med-home .facility-grid{
          gap:12px; padding:2px 26px 12px 1px;
        }
        body.site-home .med-home .toplist-grid{
          gap:12px; padding:2px 26px 12px 1px;
        }
        body.site-home .med-home .category-card,
        body.site-home .med-home .facility-card,
        body.site-home .med-home .toplist-card{ scroll-snap-align:start; }
        body.site-home .med-home .category-card{
          flex:0 0 136px; min-height:112px; flex-direction:column; align-items:flex-start;
          justify-content:space-between; padding:11px; gap:8px; border-radius:17px;
          box-shadow:0 8px 20px rgba(15,35,66,.05),inset 0 1px 0 rgba(255,255,255,.92);
        }
        body.site-home .med-home .category-card:active,
        body.site-home .med-home .facility-card:active,
        body.site-home .med-home .toplist-card:active{ transform:scale(.985); }
        body.site-home .med-home .category-icon{
          flex-basis:38px; width:38px; height:38px; border-radius:13px; font-size:19px;
        }
        body.site-home .med-home .category-card > span:last-child{ width:100%; min-width:0; }
        body.site-home .med-home .category-card strong{
          display:-webkit-box; overflow:hidden; font-size:11.5px; line-height:1.25;
          -webkit-box-orient:vertical; -webkit-line-clamp:2;
        }
        body.site-home .med-home .category-card small{ margin-top:3px; font-size:9.5px; }
        body.site-home .med-home .facility-card{ flex:0 0 min(272px,79vw); border-radius:20px; }
        body.site-home .med-home .facility-media{ height:158px; }
        body.site-home .med-home .facility-category{ top:10px; left:10px; padding:5px 8px; font-size:9px; }
        body.site-home .med-home .facility-body{ padding:13px 14px 14px; }
        body.site-home .med-home .facility-body h3{ font-size:15px; }
        body.site-home .med-home .facility-subtitle{ min-height:35px; margin-top:5px; font-size:11px; }
        body.site-home .med-home .facility-info{ margin-top:10px; font-size:11px; }
        body.site-home .med-home .toplist-card{ flex:0 0 min(272px,81vw); min-height:196px; padding:16px; border-radius:20px; }
        body.site-home .med-home .toplist-rank{ width:34px; height:34px; border-radius:12px; font-size:13px; }
        body.site-home .med-home .toplist-card h3{ margin-top:13px; font-size:16px; }
        body.site-home .med-home .toplist-card p{ margin-top:6px; font-size:11px; }
        body.site-home .med-home .toplist-meta{ margin-top:12px; font-size:10.5px; }

        body.site-home .med-home .data-band{
          grid-template-columns:repeat(2,minmax(0,1fr)); border-radius:22px;
          box-shadow:0 16px 34px rgba(29,78,216,.18),inset 0 1px 0 rgba(255,255,255,.18);
        }
        body.site-home .med-home .data-band-copy{
          grid-column:1 / -1; padding:18px 17px 16px;
          background:linear-gradient(135deg,rgba(5,20,57,.22),rgba(5,20,57,.05));
        }
        body.site-home .med-home .data-band-copy h2{ font-size:18px; }
        body.site-home .med-home .data-band-copy p{ margin-top:5px; font-size:11px; }
        body.site-home .med-home .data-stat{ min-height:76px; padding:15px 16px; }
        body.site-home .med-home .data-stat:nth-child(4){ border-left:1px solid rgba(255,255,255,.14); }
        body.site-home .med-home .data-stat strong{ font-size:23px; }
        body.site-home .med-home .data-stat span{ margin-top:5px; font-size:9.5px; }
        body.site-home .med-home .two-column{ gap:14px; }
        body.site-home .med-home .panel{ padding:16px; border-radius:20px; }
        body.site-home .med-home .panel-head{ margin-bottom:7px; }
        body.site-home .med-home .panel-head h2{ font-size:18px; }
        body.site-home .med-home .doctor-item,
        body.site-home .med-home .review-item{ min-height:58px; padding:9px 2px; }
        body.site-home .med-home .doctor-avatar{ flex-basis:42px; width:42px; height:42px; border-radius:13px; }
        body.site-home .med-home .review-quote{ flex-basis:30px; width:30px; height:30px; }
      }
      @media (max-width:360px){
        body.site-home .med-home .hero-copy h1{ font-size:29px; }
        body.site-home .med-home .hero-search-row{ grid-template-columns:minmax(0,1fr) 48px; }
        body.site-home .med-home .hero-search-btn{ width:48px; min-width:48px; }
        body.site-home .med-home .category-card{ flex-basis:132px; }
        body.site-home .med-home .facility-grid,
        body.site-home .med-home .toplist-grid{ grid-auto-columns:86vw; }
        body.site-home .med-home .facility-card,
        body.site-home .med-home .toplist-card{ flex-basis:86vw; }
      }
    </style>
  </head>
  <body class="site-home">
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <?php include __DIR__ . '/Tem/home.php'; ?>
    <?php if (function_exists('front_editor_render')) { front_editor_render('home'); } ?>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
  </body>
</html>
