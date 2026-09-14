<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
require_once __DIR__ . '/front_admin.php';
$pdo = db();
$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$project = null;
try {
  if ($slug !== '') {
    $st = $pdo->prepare(
      "SELECT p.id, p.title, p.slug, p.featured_image_url, p.short_description, p.content, p.project_type, p.style, p.location, p.year, p.area, p.materials, p.gallery_json,
              c.name AS category_name, c.slug AS category_slug
       FROM projects p
       LEFT JOIN project_categories c ON c.id = p.category_id
       WHERE p.status = 'published' AND p.slug = :s
       LIMIT 1"
    );
    $st->execute([':s' => $slug]);
    $project = $st->fetch();
  } elseif ($id > 0) {
    $st = $pdo->prepare(
      "SELECT p.id, p.title, p.slug, p.featured_image_url, p.short_description, p.content, p.project_type, p.style, p.location, p.year, p.area, p.materials, p.gallery_json,
              c.name AS category_name, c.slug AS category_slug
       FROM projects p
       LEFT JOIN project_categories c ON c.id = p.category_id
       WHERE p.status = 'published' AND p.id = :id
       LIMIT 1"
    );
    $st->execute([':id' => $id]);
    $project = $st->fetch();
  }
} catch (Throwable $e) {
  $project = null;
}
if (!$project) {
  http_response_code(404);
  echo 'Không tìm thấy dự án.';
  exit;
}
$safeTitle = htmlspecialchars((string) ($project['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$heroImg = (string) ($project['featured_image_url'] ?? '');
$heroImg = $heroImg !== '' ? $heroImg : '/uploads/library/2026/03/6a55a84cd37cfcf62fdc196ddd24f2af.jpg';
$desc = (string) ($project['short_description'] ?? '');
$ptype = (string) (($project['project_type'] ?? '') !== '' ? $project['project_type'] : ($project['category_name'] ?? ''));
$style = (string) ($project['style'] ?? '');
$location = (string) ($project['location'] ?? '');
$year = (string) ($project['year'] ?? '');
$area = (string) ($project['area'] ?? '');
$materials = (string) ($project['materials'] ?? '');
$content = (string) ($project['content'] ?? '');
$gallery = [];
$galleryJson = (string) ($project['gallery_json'] ?? '');
if ($galleryJson !== '') {
  $decoded = json_decode($galleryJson, true);
  if (is_array($decoded)) {
    foreach ($decoded as $u) {
      $u = trim((string) $u);
      if ($u !== '') $gallery[] = $u;
    }
  }
}
if (count($gallery) === 0 && $heroImg !== '') {
  $gallery = [$heroImg, $heroImg, $heroImg];
}
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RB Concept • <?php echo $safeTitle; ?></title>
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
        --img-1: url("<?php echo htmlspecialchars($heroImg, ENT_QUOTES, 'UTF-8'); ?>");
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
          linear-gradient(90deg, rgba(0,0,0,0.54) 0%, rgba(0,0,0,0.30) 42%, rgba(0,0,0,0.12) 72%, rgba(0,0,0,0.24) 100%),
          var(--img-1) center/cover no-repeat;
        transform: scale(1.03);
        filter: saturate(0.98) contrast(1.02);
      }
      .hero-inner{
        position: relative;
        padding: 120px 0 78px;
        min-height: 78vh;
        display: flex;
        align-items: center;
      }
      .hero-grid{
        display:grid;
        grid-template-columns: 1.15fr 0.85fr;
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
        margin: 10px 0 10px;
        font-family: "Playfair Display", serif;
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
      .info-card{
        border-radius: var(--radius);
        background: rgba(255,255,255,0.86);
        border: 1px solid rgba(255,255,255,0.34);
        box-shadow: 0 24px 80px rgba(0,0,0,0.22);
        overflow: hidden;
        backdrop-filter: blur(12px);
      }
      .info-card .body{
        padding: 18px;
        display:grid;
        gap: 10px;
      }
      .info-card b{
        font-family: "Playfair Display", serif;
        font-weight: 700;
      }
      .kv{
        display:grid;
        gap: 8px;
        margin-top: 8px;
      }
      .kv .row{
        display:flex;
        justify-content:space-between;
        gap: 10px;
        padding: 10px 10px;
        border-radius: 16px;
        border: 1px solid rgba(17,24,39,0.08);
        background: rgba(255,255,255,0.82);
        font-size: 13px;
        color: var(--muted);
      }
      .kv .row span:first-child{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        color: rgba(17,24,39,0.72);
      }
      .kv .row strong{
        color: rgba(17,24,39,0.92);
        font-weight: 700;
      }
      .section{
        padding: 34px 0;
      }
      .section h2{
        margin: 0 0 12px;
        font-family: "Playfair Display", serif;
        font-size: 20px;
        letter-spacing: -0.02em;
      }
      .split{
        display:grid;
        grid-template-columns: 1.35fr 0.65fr;
        gap: 14px;
        align-items:start;
      }
      .panel{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        padding: 16px 16px;
      }
      .panel p{
        margin: 0;
        color: var(--muted);
        line-height: 1.8;
        font-size: 14px;
      }
      .highlights{
        display:grid;
        gap: 10px;
      }
      .hl{
        border-radius: 16px;
        border: 1px solid rgba(17,24,39,0.10);
        background: rgba(255,255,255,0.78);
        padding: 12px 12px;
      }
      .hl b{
        display:block;
        font-size: 13px;
        margin-bottom: 4px;
      }
      .hl span{
        display:block;
        font-size: 13px;
        color: var(--muted);
        line-height: 1.6;
      }
      .gallery{
        display:grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
      }
      .shot{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow:hidden;
      }
      .shot .img{
        height: 190px;
        background: var(--img-1) center/cover no-repeat;
      }
      .shot .cap{
        padding: 10px 12px 12px;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.5;
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
        .split{ grid-template-columns: 1fr; }
        .gallery{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
        .gallery{ grid-template-columns: 1fr; }
        .footer-cols{ grid-template-columns: 1fr; }
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <?php
      if (isset($project['id'])) {
        front_admin_render_edit_bar([
          ['label' => 'Sửa dự án', 'href' => '/admin/content_project_edit.php?id=' . (int) $project['id'], 'icon' => 'fa-solid fa-pen-to-square'],
          ['label' => 'Admin', 'href' => '/admin/dashboard.php', 'icon' => 'fa-solid fa-shield-halved'],
        ]);
      }
    ?>
    <section class="hero">
      <div class="hero-bg" aria-hidden="true"></div>
      <div class="container hero-inner">
        <div class="hero-grid">
          <div class="hero-copy">
            <div class="crumbs">
              <a href="/template1.php#home">Trang chủ</a>
              <span>•</span>
              <a href="/du-an.php">Dự án</a>
              <span>•</span>
              <span><?php echo $safeTitle; ?></span>
            </div>
            <h1><?php echo $safeTitle; ?></h1>
            <p><?php echo htmlspecialchars($desc !== '' ? $desc : 'Dự án thực tế được triển khai theo mặt bằng và yêu cầu sử dụng.', ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="hero-actions">
              <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> Nhận tư vấn</a>
              <a class="btn btn-ghost" href="/du-an.php#projects"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Quay lại danh sách</a>
            </div>
          </div>
          <div class="info-card">
            <div class="body">
              <b>Thông tin dự án</b>
              <div class="kv">
                <div class="row"><span><i class="fa-solid fa-layer-group" aria-hidden="true"></i>Loại</span><strong><?php echo htmlspecialchars($ptype !== '' ? $ptype : '-', ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div class="row"><span><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>Phong cách</span><strong><?php echo htmlspecialchars($style !== '' ? $style : '-', ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div class="row"><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>Vị trí</span><strong><?php echo htmlspecialchars($location !== '' ? $location : '-', ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div class="row"><span><i class="fa-regular fa-calendar" aria-hidden="true"></i>Năm</span><strong><?php echo htmlspecialchars($year !== '' ? $year : '-', ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div class="row"><span><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i>Diện tích</span><strong><?php echo htmlspecialchars($area !== '' ? $area : '-', ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div class="row"><span><i class="fa-solid fa-leaf" aria-hidden="true"></i>Vật liệu</span><strong><?php echo htmlspecialchars($materials !== '' ? $materials : '-', ENT_QUOTES, 'UTF-8'); ?></strong></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="split">
          <div class="panel">
            <h2>Mô tả</h2>
            <?php if (trim($content) !== ''): ?>
              <div style="line-height:1.85; font-size:14px; color: var(--muted);"><?php echo $content; ?></div>
            <?php else: ?>
              <p><?php echo htmlspecialchars($desc !== '' ? $desc : 'Nội dung dự án đang cập nhật.', ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
          </div>
          <div class="panel">
            <h2>Điểm nổi bật</h2>
            <div class="highlights">
              <div class="hl"><b>Phân khu hợp lý</b><span>Tách khu tắm &amp; khu lavabo, hạn chế bắn nước.</span></div>
              <div class="hl"><b>Ánh sáng &amp; gương</b><span>Gương LED giúp không gian sáng và hiện đại.</span></div>
              <div class="hl"><b>Vật liệu bền</b><span>Chọn bề mặt chống ẩm, chống bám bẩn.</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <h2>Hình ảnh dự án</h2>
        <div class="gallery">
          <?php foreach ($gallery as $i => $u): ?>
            <div class="shot">
              <div class="img" style="background-image:url('<?php echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8'); ?>')"></div>
              <div class="cap">Ảnh dự án <?php echo (int) ($i + 1); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
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
    </script>
  </body>
</html>
