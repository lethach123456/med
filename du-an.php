<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
front_editor_page_maybe_redirect('projects');
$pdo = db();
$projectCards = [];
$filters = [];
// Hero slider de mau cung 1 anh. Ban co the tu thay URL anh ngay tai file nay.
$heroSlides = [
  '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
];
try {
  $filters = $pdo->query("SELECT id, name, slug FROM project_categories ORDER BY name ASC")->fetchAll();
} catch (Throwable $e) {
  $filters = [];
}
try {
  $stmt = $pdo->query(
    "SELECT p.id, p.title, p.slug, p.featured_image_url, p.location, p.year, p.style, c.slug AS category_slug, c.name AS category_name
     FROM projects p
     LEFT JOIN project_categories c ON c.id = p.category_id
     WHERE p.status = 'published'
     ORDER BY p.updated_at DESC
     LIMIT 12"
  );
  $projectCards = $stmt->fetchAll();
} catch (Throwable $e) {
  $projectCards = [];
}
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
      $seo = front_editor_page_seo('projects', [
        'title' => 'Top Dental Clinic • Dự án',
        'description' => 'Khám phá các dự án, hình ảnh và case nổi bật được Top Dental giới thiệu.',
      ]);
      $seoKeywords = (string) ($seo['keywords'] ?? '');
    ?>
    <title><?php echo htmlspecialchars((string) ($seo['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars((string) ($seo['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars((string) ($seo['canonical_path'] ?? '/du-an'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root{
        --bg: #f5f2eb;
        --surface: rgba(255,255,255,0.86);
        --surface-2: rgba(255,255,255,0.94);
        --border: rgba(17,24,39,0.10);
        --text: rgba(17,24,39,0.92);
        --muted: rgba(17,24,39,0.68);
        --brand: #2f2a24;
        --brand-2: #9a855f;
        --shadow: 0 18px 50px rgba(17,24,39,0.14);
        --radius: 18px;
        --max: 1120px;
        --img-1: url("/uploads/library/2026/03/6a55a84cd37cfcf62fdc196ddd24f2af.jpg");
      }
      *{ box-sizing: border-box; }
      html{ height: 100%; }
      body{ min-height: 100%; }
      body{
        margin: 0;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        color: var(--text);
        background:
          radial-gradient(900px 520px at 15% 0%, rgba(154,133,95,0.22), transparent 62%),
          radial-gradient(900px 520px at 85% 8%, rgba(47,42,36,0.18), transparent 62%),
          linear-gradient(180deg, #ffffff 0%, var(--bg) 60%, #ffffff 100%);
      }
      a{ color: inherit; text-decoration: none; }
      img{ display:block; max-width:100%; }
      .container{ width: min(100% - 32px, var(--max)); margin: 0 auto; }
      .icon-gap{ margin-right: 8px; }
      .topbar{
        position: sticky;
        top: 0;
        z-index: 50;
        backdrop-filter: blur(12px);
        background: linear-gradient(180deg, rgba(255,255,255,0.78), rgba(255,255,255,0.58));
        border-bottom: 1px solid var(--border);
      }
      .nav{
        position: relative;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 16px;
        padding: 14px 0;
      }
      .brand{
        display:flex;
        align-items:center;
        gap: 10px;
        min-width: max-content;
      }
      .brand-mark{
        width: 40px;
        height: 40px;
        border-radius: 14px;
        background:
          radial-gradient(18px 18px at 30% 25%, rgba(255,255,255,0.72), transparent 60%),
          linear-gradient(135deg, rgba(154,133,95,1), rgba(47,42,36,1));
        box-shadow: 0 18px 50px rgba(47,42,36,0.18);
      }
      .brand-title{ line-height: 1.1; }
      .brand-title strong{
        font-family: "Playfair Display", serif;
        letter-spacing: -0.02em;
        font-weight: 700;
      }
      .brand-title span{
        display:block;
        margin-top: 2px;
        font-size: 12px;
        color: var(--muted);
      }
      .navlinks{
        display:flex;
        align-items:center;
        gap: 16px;
      }
      .navlinks a{
        font-size: 14px;
        color: var(--muted);
        padding: 10px 10px;
        border-radius: 12px;
        transition: background 160ms ease, color 160ms ease;
      }
      .navlinks a:hover{
        background: rgba(17,24,39,0.06);
        color: var(--text);
      }
      .navlinks a.is-active{
        background: rgba(17,24,39,0.07);
        color: var(--text);
      }
      .nav-cta{
        display:flex;
        align-items:center;
        gap: 10px;
        min-width: max-content;
      }
      .pill{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 13px;
      }
      .nav-toggle{
        display:none;
        width: 42px;
        height: 42px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.78);
        align-items:center;
        justify-content:center;
        cursor: pointer;
      }
      .btn{
        appearance: none;
        border: 0;
        cursor: pointer;
        border-radius: 999px;
        padding: 11px 14px;
        font-weight: 600;
        font-size: 14px;
        display:inline-flex;
        align-items:center;
        gap: 10px;
        transition: transform 160ms ease, filter 160ms ease, background 160ms ease, border-color 160ms ease;
      }
      .btn:active{ transform: translateY(1px); }
      .btn-primary{
        background: rgba(47,42,36,0.90);
        color: rgba(255,255,255,0.95);
        box-shadow: 0 18px 60px rgba(47,42,36,0.20);
      }
      .btn-primary:hover{ filter: brightness(1.05); }
      .btn-ghost{
        background: rgba(255,255,255,0.72);
        color: var(--text);
        border: 1px solid var(--border);
      }
      .btn-ghost:hover{ background: rgba(255,255,255,0.90); }
      .hero{
        position: relative;
        overflow: hidden;
      }
      .hero-bg{
        position:absolute;
        inset: 0;
        background:
          linear-gradient(90deg, rgba(0,0,0,0.52) 0%, rgba(0,0,0,0.26) 42%, rgba(0,0,0,0.10) 72%, rgba(0,0,0,0.22) 100%),
          var(--hero-img, var(--img-1)) center/cover no-repeat;
        transform: scale(1.03);
        filter: saturate(0.98) contrast(1.02);
        transition: opacity 800ms ease;
        opacity: 1;
      }
      .hero-bg.is-fading{ opacity: 0; }
      .hero-inner{
        position: relative;
        padding: 110px 0 72px;
        min-height: 78vh;
        display: flex;
        align-items: center;
      }
      .hero-grid{
        display:grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 28px;
        align-items: center;
        width: 100%;
      }
      .hero-copy{
        padding: 22px 20px;
        border-radius: var(--radius);
        background: rgba(0,0,0,0.26);
        border: 1px solid rgba(255,255,255,0.14);
        box-shadow: 0 28px 80px rgba(0,0,0,0.22);
        color: rgba(255,255,255,0.96);
        backdrop-filter: blur(12px);
      }
      .hero-kicker{
        display:inline-flex;
        align-items:center;
        gap: 10px;
        font-weight: 600;
        font-size: 12px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.86);
      }
      .hero-kicker .dot{
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: rgba(154,133,95,0.95);
        box-shadow: 0 0 0 4px rgba(154,133,95,0.18);
      }
      .hero-copy h1{
        margin: 10px 0 10px;
        font-family: "Playfair Display", serif;
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.1;
        font-size: clamp(32px, 3.2vw, 46px);
      }
      .hero-copy p{
        margin: 0;
        color: rgba(255,255,255,0.82);
        line-height: 1.7;
        max-width: 62ch;
      }
      .hero-actions{
        margin-top: 18px;
        display:flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
      }
      .hero-actions .btn-ghost{
        border-color: rgba(255,255,255,0.22);
        background: rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.94);
      }
      .hero-actions .btn-ghost:hover{ background: rgba(255,255,255,0.18); }
      .hero-card{
        border-radius: var(--radius);
        background: rgba(255,255,255,0.86);
        border: 1px solid rgba(255,255,255,0.34);
        box-shadow: 0 24px 80px rgba(0,0,0,0.22);
        overflow: hidden;
        backdrop-filter: blur(12px);
      }
      .hero-card .body{
        padding: 18px;
        display:grid;
        gap: 10px;
      }
      .hero-card b{
        font-family: "Playfair Display", serif;
        font-weight: 700;
      }
      .hero-card p{
        margin: 0;
        color: var(--muted);
        line-height: 1.6;
        font-size: 14px;
      }
      .hero-card .stats{
        display:grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 8px;
      }
      .stat{
        border-radius: 16px;
        border: 1px solid rgba(17,24,39,0.08);
        background: rgba(255,255,255,0.82);
        padding: 10px 10px;
        display:grid;
        gap: 2px;
      }
      .stat strong{
        font-size: 16px;
        letter-spacing: -0.02em;
      }
      .stat span{
        font-size: 12px;
        color: var(--muted);
        line-height: 1.35;
      }
      .section{
        padding: 34px 0;
      }
      .section h2{
        margin: 0 0 10px;
        text-align:center;
        font-family: "Playfair Display", serif;
        font-size: 22px;
        letter-spacing: -0.02em;
      }
      .section-lead{
        text-align:center;
        color: var(--muted);
        max-width: 74ch;
        margin: 0 auto 16px;
        line-height: 1.7;
        font-size: 14px;
      }
      .filters{
        display:flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content:center;
        margin: 14px 0 18px;
      }
      .chip{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.78);
        color: var(--muted);
        font-size: 13px;
        cursor: pointer;
        user-select: none;
        transition: background 160ms ease, color 160ms ease, border-color 160ms ease;
      }
      .chip:hover{
        background: rgba(255,255,255,0.94);
        color: var(--text);
      }
      .chip.is-active{
        background: rgba(47,42,36,0.92);
        border-color: rgba(47,42,36,0.30);
        color: rgba(255,255,255,0.92);
      }
      .project-grid{
        display:grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
      }
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
      .site-footer{
        margin-top: 34px;
        position: relative;
        overflow: hidden;
        color: rgba(255,255,255,0.90);
        background: #0f0f10;
      }
      .site-footer::before{
        content:"";
        position: absolute;
        inset: 0;
        background: var(--img-1) center/cover no-repeat;
        filter: saturate(0.85) contrast(1.10);
        opacity: 0.55;
        z-index: 0;
        pointer-events: none;
      }
      .site-footer::after{
        content:"";
        position:absolute;
        inset: 0;
        background:
          linear-gradient(180deg, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0.62) 55%, rgba(0,0,0,0.76) 100%);
        z-index: 0;
        pointer-events: none;
      }
      .footer-layer{ position: relative; z-index: 1; }
      .footer-cta{
        border-bottom: 1px solid rgba(255,255,255,0.10);
        background: rgba(0,0,0,0.34);
      }
      .footer-cta-inner{
        padding: 14px 0;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 12px;
        flex-wrap: wrap;
      }
      .footer-cta-inner b{
        font-family:"Playfair Display", serif;
        font-weight: 600;
        color: rgba(255,255,255,0.92);
      }
      .footer-cta .btn-ghost{
        border-color: rgba(255,255,255,0.18);
        background: rgba(255,255,255,0.10);
        color: rgba(255,255,255,0.92);
      }
      .footer-cta .btn-ghost:hover{ background: rgba(255,255,255,0.16); }
      .footer-main{ padding: 18px 0 10px; }
      .footer-cols{
        display:grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
      }
      .footer-col h4{
        margin: 0 0 10px;
        font-size: 14px;
        font-weight: 700;
        color: rgba(255,255,255,0.92);
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(255,255,255,0.10);
      }
      .footer-link{
        display:block;
        color: rgba(255,255,255,0.78);
        font-size: 13px;
        padding: 6px 0;
      }
      .footer-link:hover{ color: rgba(255,255,255,0.92); }
      .footer-contact{
        display:grid;
        gap: 8px;
        color: rgba(255,255,255,0.78);
        font-size: 13px;
      }
      .footer-contact .item{
        display:flex;
        gap: 10px;
        align-items:flex-start;
        line-height: 1.5;
      }
      .footer-contact i{
        width: 18px;
        margin-top: 2px;
        text-align:center;
        color: rgba(255,255,255,0.90);
      }
      .footer-bottom{
        border-top: 1px solid rgba(255,255,255,0.10);
        padding: 12px 0 14px;
        text-align: center;
        color: rgba(255,255,255,0.70);
        font-size: 13px;
      }
      @media (max-width: 1024px){
        .hero-grid{ grid-template-columns: 1fr; }
        .project-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .footer-cols{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
      }
      @media (max-width: 680px){
        .nav-toggle{ display:inline-flex; }
        .navlinks{
          display:none;
          position: absolute;
          left: 16px;
          right: 16px;
          top: calc(100% + 10px);
          padding: 10px;
          border-radius: 16px;
          border: 1px solid var(--border);
          background: rgba(255,255,255,0.96);
          box-shadow: 0 26px 80px rgba(17,24,39,0.18);
          flex-direction: column;
          align-items: stretch;
          gap: 6px;
        }
        .navlinks a{ padding: 12px 12px; }
        .navlinks.is-open{ display:flex; }
        .pill{ display:none; }
        .project-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .footer-cols{ grid-template-columns: 1fr; }
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <section class="hero">
      <div class="hero-bg" aria-hidden="true" data-hero-bg="1"></div>
      <div class="container hero-inner">
        <div class="hero-grid">
          <div class="hero-copy">
            <div class="hero-kicker"><span class="dot" aria-hidden="true"></span> Project Showcase</div>
            <h1>Dự Án</h1>
            <p>Tổng hợp một số concept phòng tắm theo phong cách hiện đại, sang trọng và tối ưu công năng. Bố cục, vật liệu và tỉ lệ được đồng bộ với trang chủ</p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="#projects"><i class="fa-solid fa-images" aria-hidden="true"></i> Xem dự án</a>
              <a class="btn btn-ghost" href="/san-pham.php"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Xem sản phẩm</a>
            </div>
          </div>
          <div class="hero-card">
            <div class="body">
              <b>Tư vấn theo mặt bằng</b>
              <p>Gợi ý giải pháp theo diện tích, ánh sáng, phong cách kiến trúc và ngân sách.</p>
              <div class="stats">
                <div class="stat"><strong>12+</strong><span>Concept mẫu</span></div>
                <div class="stat"><strong>3</strong><span>Phong cách chính</span></div>
                <div class="stat"><strong>1</strong><span>Đồng bộ UI</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="section" id="projects">
      <div class="container">
        <h2>Showcase dự án</h2>
        <p class="section-lead">Chọn nhanh theo loại công trình. Mỗi dự án có thẻ thông tin, vị trí và năm triển khai để bạn dễ hình dung.</p>
        <div class="filters" data-filters="1">
          <span class="chip is-active" data-filter="all"><i class="fa-solid fa-layer-group" aria-hidden="true"></i>Tất cả</span>
          <?php foreach ($filters as $f): ?>
            <span class="chip" data-filter="<?php echo htmlspecialchars((string)$f['slug'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-tag" aria-hidden="true"></i><?php echo htmlspecialchars((string)$f['name'], ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endforeach; ?>
        </div>
        <div class="project-grid" data-projects="1">
          <?php foreach ($projectCards as $card): ?>
            <?php $img = (string) ($card['featured_image_url'] ?? ''); $style = $img !== '' ? "background-image:url('".htmlspecialchars($img, ENT_QUOTES, 'UTF-8')."')" : ""; ?>
            <article class="project" data-type="<?php echo htmlspecialchars((string)($card['category_slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <div class="img" style="<?php echo $style; ?>">
                <div class="badge-row">
                  <?php if (!empty($card['category_name'])): ?><span class="badge"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><?php echo htmlspecialchars((string)$card['category_name'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                  <?php if (!empty($card['style'])): ?><span class="badge"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><?php echo htmlspecialchars((string)$card['style'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                </div>
              </div>
              <div class="body">
                <h3><?php echo htmlspecialchars((string)$card['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="meta">
                  <?php if (!empty($card['location'])): ?><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?php echo htmlspecialchars((string)$card['location'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                  <?php if (!empty($card['year'])): ?><span><i class="fa-regular fa-calendar" aria-hidden="true"></i><?php echo htmlspecialchars((string)$card['year'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                </div>
                <div class="actions">
                  <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> Tư vấn</a>
                  <a class="btn btn-ghost" href="/du-an/<?php echo htmlspecialchars(rawurlencode((string)$card['slug']), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Xem chi tiết</a>
                  <?php if (function_exists('admin_front_is_logged_in') && admin_front_is_logged_in()): ?>
                    <a class="btn btn-ghost" href="/admin/content_project_edit.php?id=<?php echo (int) ($card['id'] ?? 0); ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Sửa</a>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php if (function_exists('front_editor_render')) { front_editor_render('projects'); } ?>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
    <script>
      (function(){
        function boot(){
          var hero = document.querySelector(".hero");
          if (!hero) return;
          var bg = hero.querySelector("[data-hero-bg='1']");
          if (!bg) return;
          var slides = <?php echo json_encode(array_values($heroSlides), JSON_UNESCAPED_UNICODE); ?>;
          if (!Array.isArray(slides) || slides.length === 0) return;
          var idx = 0;
          hero.style.setProperty("--hero-img", "url('" + String(slides[0]).replace(/'/g, "\\'") + "')");
          if (slides.length === 1) return;
          setInterval(function(){
            idx = (idx + 1) % slides.length;
            bg.classList.add("is-fading");
            setTimeout(function(){
              hero.style.setProperty("--hero-img", "url('" + String(slides[idx]).replace(/'/g, "\\'") + "')");
              bg.classList.remove("is-fading");
            }, 450);
          }, 5200);
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
        toggle.addEventListener('click', function(){
          nav.classList.toggle('is-open');
        });
        document.addEventListener('click', function(e){
          if (!nav.classList.contains('is-open')) return;
          if (e.target === toggle || toggle.contains(e.target)) return;
          if (e.target === nav || nav.contains(e.target)) return;
          nav.classList.remove('is-open');
        });
      })();
      (function(){
        var box = document.querySelector('[data-filters="1"]');
        var grid = document.querySelector('[data-projects="1"]');
        if (!box || !grid) return;
        function setFilter(type){
          box.querySelectorAll('[data-filter]').forEach(function(el){
            el.classList.toggle('is-active', el.getAttribute('data-filter') === type);
          });
          grid.querySelectorAll('[data-type]').forEach(function(card){
            var cardType = card.getAttribute('data-type');
            card.style.display = (type === 'all' || cardType === type) ? '' : 'none';
          });
        }
        box.addEventListener('click', function(e){
          var el = e.target.closest('[data-filter]');
          if (!el) return;
          setFilter(el.getAttribute('data-filter'));
        });
        setFilter('all');
      })();
    </script>
  </body>
</html>
