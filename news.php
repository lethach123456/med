<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
if (!isset($_GET['slug']) || trim((string) ($_GET['slug'] ?? '')) === '') {
  front_editor_page_maybe_redirect('blog-en');
}
$pdo = db();
// Static hero slider with 1 sample image. You can replace this URL directly in this file.
$heroSlides = [
  '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
];
$blogCatId = 0;
try {
  $st = $pdo->prepare("SELECT id FROM categories WHERE slug IN ('blog-en', 'blog') ORDER BY CASE WHEN slug = 'blog-en' THEN 0 ELSE 1 END, id ASC LIMIT 1");
  $st->execute();
  $blogCatId = (int) ($st->fetchColumn() ?: 0);
} catch (Throwable $e) { $blogCatId = 0; }
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 6;
$total = 0;
try {
  if ($blogCatId > 0) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE status = 'published' AND category_id = :cid");
    $st->execute([':cid' => $blogCatId]);
    $total = (int) ($st->fetchColumn() ?: 0);
  } else {
    $st = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'");
    $total = (int) ($st->fetchColumn() ?: 0);
  }
} catch (Throwable $e) { $total = 0; }
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$paged = [];
try {
  if ($blogCatId > 0) {
    $st = $pdo->prepare(
      "SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image_url, DATE_FORMAT(p.updated_at, '%d.%m.%Y') AS d
       FROM posts p
       WHERE p.status = 'published' AND p.category_id = :cid
       ORDER BY p.updated_at DESC
       LIMIT :l OFFSET :o"
    );
    $st->bindValue(':cid', $blogCatId, PDO::PARAM_INT);
  } else {
    $st = $pdo->prepare(
      "SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image_url, DATE_FORMAT(p.updated_at, '%d.%m.%Y') AS d
       FROM posts p
       WHERE p.status = 'published'
       ORDER BY p.updated_at DESC
       LIMIT :l OFFSET :o"
    );
  }
  $st->bindValue(':l', $perPage, PDO::PARAM_INT);
  $st->bindValue(':o', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll();
  foreach ($rows as $r) {
    $paged[] = [
      'id' => (int) ($r['id'] ?? 0),
      'title' => (string) ($r['title'] ?? ''),
      'slug' => (string) ($r['slug'] ?? ''),
      'date' => (string) ($r['d'] ?? ''),
      'excerpt' => (string) ($r['excerpt'] ?? ''),
      'img' => (string) ($r['featured_image_url'] ?? ''),
      'read' => 'Quick read',
    ];
  }
} catch (Throwable $e) {
  $paged = [];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
      $seo = front_editor_page_seo('blog-en', [
        'title' => 'Top Dental Clinic • News & Insights',
        'description' => 'Latest updates, dental insights, and articles on porcelain smiles, cosmetic dentistry, and modern smile trends at Top Dental Clinic.',
      ]);
      $seoKeywords = (string) ($seo['keywords'] ?? '');
    ?>
    <title><?php echo htmlspecialchars((string) ($seo['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars((string) ($seo['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars((string) ($seo['canonical_path'] ?? '/news'), ENT_QUOTES, 'UTF-8'); ?>">
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
        --img-1: url("https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=1200&q=80");
      }
      *{ box-sizing: border-box; }
      html{ height: 100%; }
      body{ min-height: 100%; }
      body{
        margin: 0;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        color: var(--text);
        background:
          radial-gradient(900px 520px at 15% 0%, rgba(59,130,246,0.18), transparent 62%),
          radial-gradient(900px 520px at 85% 8%, rgba(30,64,175,0.12), transparent 62%),
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
      .hero-slider{
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }
      .hero-slide{
        min-width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
      }

      .hero-slider-dots{
        position: absolute;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap:10px;
        z-index:10;
      }
      .hero-slider-dot{
        width:12px;
        height:12px;
        border-radius:50%;
        background: rgba(255,255,255,0.5);
        border:0;
        cursor:pointer;
        transition: all 0.3s ease;
      }
      .hero-slider-dot.active{
        background: white;
        transform: scale(1.3);
        box-shadow: 0 0 10px rgba(255,255,255,0.8);
      }
      .hero-inner{
        position: relative;
        padding: 110px 0 72px;
        min-height: 78vh;
        display:flex;
        align-items:center;
      }
      .hero-copy{
        max-width: 760px;
        padding: 22px 20px;
        border-radius: var(--radius);
        background: rgba(0,0,0,0.26);
        border: 1px solid rgba(255,255,255,0.14);
        box-shadow: 0 28px 80px rgba(0,0,0,0.22);
        color: rgba(255,255,255,0.96);
        backdrop-filter: blur(12px);
      }
      .crumbs{
        display:flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items:center;
        font-size: 13px;
        color: rgba(255,255,255,0.82);
      }
      .crumbs a{ color: rgba(255,255,255,0.90); }
      .crumbs a:hover{ color: rgba(255,255,255,0.98); }
      .hero-copy h1{
        margin: 10px 0 8px;
        font-family:inherit;
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.1;
        font-size: clamp(30px, 3.0vw, 44px);
      }
      .hero-copy p{
        margin: 0;
        color: rgba(255,255,255,0.82);
        line-height: 1.7;
        max-width: 62ch;
      }
      .section{ padding: 40px 0; }
      .layout{
        display:grid;
        grid-template-columns: 320px 1fr;
        gap: 24px;
        align-items:start;
      }
      .panel{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow:hidden;
      }
      .panel .hd{
        padding: 12px 14px;
        font-weight: 800;
        font-size: 13px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(15,23,42,0.72);
        background: var(--brand-light);
        border-bottom: 1px solid var(--border);
      }
      .panel .bd{ padding: 12px 14px; }
      .cat-list{ display:grid; gap: 6px; }
      .cat-item{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 10px;
        padding: 10px 10px;
        border-radius: var(--radius);
        color: rgba(15,23,42,0.78);
        border: 1px solid transparent;
      }
      .cat-item:hover{
        background: var(--brand-light);
        border-color: rgba(37,99,235,0.22);
      }
      .cat-item.is-active{
        background: var(--brand-light);
        border-color: rgba(37,99,235,0.22);
        color: var(--brand);
      }
      .cat-item .count{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        color: rgba(15,23,42,0.40);
        font-weight: 700;
        font-size: 13px;
      }
      .tips{
        display:grid;
        gap: 10px;
        padding: 12px 14px;
      }
      .tips b{
        font-family:inherit;
        font-size: 16px;
      }
      .tips p{
        margin: 0;
        color: var(--muted);
        line-height: 1.7;
        font-size: 13px;
      }
      .list{ display:grid; gap: 20px; }
      .post{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow:hidden;
        display:grid;
        grid-template-columns: 240px 1fr;
        gap: 16px;
      }
      .post .img{
        min-height: 170px;
        background: url("https://images.unsplash.com/photo-1606811841689-23dfddce3e95?w=600&q=80") center/cover no-repeat;
      }
      .post .body{
        padding: 16px;
        display:grid;
        gap: 10px;
      }
      .post h3{
        margin: 0;
        font-size: 18px;
        letter-spacing: -0.01em;
        line-height: 1.35;
      }
      .meta{
        display:flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items:center;
        color: var(--muted);
        font-size: 13px;
      }
      .meta span{
        display:inline-flex;
        gap: 8px;
        align-items:center;
      }
      .excerpt{
        color: var(--muted);
        line-height: 1.7;
        font-size: 14px;
      }
      .post .actions{
        margin-top: 6px;
        display:flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items:center;
      }
      .pager{
        margin-top: 24px;
        display:flex;
        justify-content:center;
        gap: 8px;
        flex-wrap: wrap;
      }
      .page{
        width: 40px;
        height: 40px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.78);
        color: rgba(15,23,42,0.72);
        font-size: 14px;
        font-weight: 700;
      }
      .page:hover{
        background: var(--brand-light);
        color: var(--brand);
        border-color: rgba(37,99,235,0.22);
      }
      .page.is-active{
        background: var(--brand);
        border-color: var(--brand);
        color: rgba(255,255,255,0.92);
      }
      .site-footer{ margin-top: 34px; position: relative; overflow: hidden; color: rgba(255,255,255,0.96); background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%); }
      .site-footer::before{ content:""; position:absolute; inset:0; background: var(--img-1) center/cover no-repeat; filter: saturate(0.7) contrast(1.10); opacity:0.15; z-index:0; pointer-events:none; }
      .footer-layer{ position:relative; z-index:1; }
      .footer-cta{ border-bottom:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.34); }
      .footer-cta-inner{ padding:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
      .footer-cta-inner b{ font-family:inherit; font-weight:600; color: rgba(255,255,255,0.92); }
      .footer-cta .btn-ghost{ border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.10); color: rgba(255,255,255,0.94); }
      .footer-cta .btn-ghost:hover{ background: rgba(255,255,255,0.16); }
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
        .layout{ grid-template-columns: 1fr; }
        .post{ grid-template-columns: 1fr; }
        .post .img{ min-height: 220px; }
        .footer-cols{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      }
      @media (max-width: 680px){
        .nav-toggle{ display:inline-flex; }
        .navlinks{ display:none; position:absolute; left:16px; right:16px; top:calc(100% + 10px); padding:10px; border-radius:var(--radius); border:1px solid var(--border); background: rgba(255,255,255,0.96); box-shadow:0 26px 80px rgba(17,24,39,0.18); flex-direction:column; align-items:stretch; gap:6px; }
        .navlinks a{ padding:12px 12px; }
        .navlinks.is-open{ display:flex; }
        .pill{ display:none; }
        .panel{ order: 2; }
        main{ order: 1; }
        .list{ gap: 16px; }
        .footer-cols{ grid-template-columns:1fr; gap:12px; }
        .footer-cta-inner{ padding:12px 0; flex-direction:column; align-items:flex-start; gap:10px; }
        .footer-cta-inner .btn{ width:100%; justify-content:center; }
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <section class="hero">
      <div class="hero-slider" id="heroSlider">
        <?php 
        $displaySlides = !empty($heroSlides) ? $heroSlides : ['https://images.unsplash.com/photo-1629909613654-23dfddce3e95?w=1200&q=80'];
        foreach ($displaySlides as $slideUrl): ?>
          <div class="hero-slide" style="background: linear-gradient(90deg, rgba(30,64,175,0.75) 0%, rgba(30,64,175,0.55) 42%, rgba(30,64,175,0.35) 72%, rgba(30,64,175,0.5) 100%), url('<?php echo htmlspecialchars($slideUrl, ENT_QUOTES, 'UTF-8'); ?>') center/cover no-repeat;"></div>
        <?php endforeach; ?>
      </div>
      <div class="container hero-inner">
        <div class="hero-copy">
          <div class="crumbs">
            <a href="/services">Home</a>
            <span>•</span>
            <span>News & Insights</span>
          </div>
          <h1>Dental News & Insights</h1>
          <p>Explore useful updates about oral care, dental services, cosmetic treatment planning, and current smile design trends.</p>
        </div>
      </div>
      <div class="hero-slider-dots" id="heroSliderDots"></div>
    </section>
    <section class="section">
      <div class="container">
        <div class="layout">
          <aside class="panel">
            <div class="hd">Categories</div>
            <div class="bd">
              <div class="cat-list">
                <a class="cat-item is-active" href="/news">
                  <span>All</span>
                  <span class="count"><?php echo $total; ?> <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
                </a>
              </div>
            </div>
            <div class="tips">
              <b>Need quick guidance?</b>
              <p>Send us your request to receive tailored suggestions based on your goals and budget.</p>
              <a class="btn btn-primary" href="/contact-us"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> Contact us</a>
            </div>
          </aside>
          <main>
            <div class="list">
              <?php foreach ($paged as $p): ?>
                <article class="post">
                  <?php 
                  $img = (string) $p['img'];
                  $style = $img !== '' ? "background-image:url('".htmlspecialchars($img, ENT_QUOTES, 'UTF-8')."')" : "";
                  ?>
                  <div class="img" aria-hidden="true" style="<?php echo $style; ?>"></div>
                  <div class="body">
                    <h3><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="meta">
                      <span><i class="fa-regular fa-calendar" aria-hidden="true"></i><?php echo htmlspecialchars($p['date'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <span><i class="fa-regular fa-clock" aria-hidden="true"></i><?php echo htmlspecialchars($p['read'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="excerpt"><?php echo htmlspecialchars($p['excerpt'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="actions">
                      <a class="btn btn-primary" href="/news/<?php echo htmlspecialchars(rawurlencode((string)$p['slug']), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Read more</a>
                      <a class="btn btn-ghost" href="/contact-us"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book a consultation</a>
                      <?php if (function_exists('admin_front_is_logged_in') && admin_front_is_logged_in()): ?>
                        <a class="btn btn-ghost" href="/admin/content_post_edit.php?id=<?php echo (int) ($p['id'] ?? 0); ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
              <?php if (empty($paged)): ?>
                <article class="post">
                  <div class="body" style="padding: 40px 20px; text-align: center;">
                    <h3>No articles available yet</h3>
                    <p style="color: var(--muted);">Please check back soon.</p>
                  </div>
                </article>
              <?php endif; ?>
            </div>
            <?php if ($totalPages > 1): ?>
              <nav class="pager" aria-label="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <a class="page <?php echo $i === $page ? 'is-active' : ''; ?>" href="/news?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
              </nav>
            <?php endif; ?>
          </main>
        </div>
      </div>
    </section>
    <?php if (function_exists('front_editor_render')) { front_editor_render('blog-en'); } ?>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
    <script>
      (function(){
        function boot(){
          var hero = document.querySelector(".hero");
          if (!hero) return;
          var slider = document.getElementById("heroSlider");
          var slides = slider ? slider.querySelectorAll(".hero-slide") : [];
          if (!slider || slides.length === 0) return;
          var dotsContainer = document.getElementById("heroSliderDots");
          var currentSlide = 0;
          var autoSlideInterval;

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
            dots.forEach(function(dot, index){
              dot.classList.toggle("active", index === currentSlide);
            });
          }

          function goToSlide(index){
            currentSlide = index;
            if (currentSlide < 0) currentSlide = slides.length - 1;
            if (currentSlide >= slides.length) currentSlide = 0;
            updateSlider();
            resetAutoSlide();
          }

          function nextSlide(){
            goToSlide(currentSlide + 1);
          }

          function prevSlide(){
            goToSlide(currentSlide - 1);
          }

          function startAutoSlide(){
            autoSlideInterval = setInterval(nextSlide, 5000);
          }

          function resetAutoSlide(){
            clearInterval(autoSlideInterval);
            startAutoSlide();
          }

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
            if (Math.abs(diff) > swipeThreshold) {
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
  </body>
</html>
