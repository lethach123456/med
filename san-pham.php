<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
$pdo = db();
require_once __DIR__ . '/front_admin.php';
// Hero slider de mau cung 1 anh. Ban co the tu thay URL anh ngay tai file nay.
$heroSlides = [
  '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
];
$cats = [];
$catCounts = [];
try {
  $rs = $pdo->query("SELECT id, name, slug FROM product_categories ORDER BY name ASC")->fetchAll();
  foreach ($rs as $row) {
    $slug = (string) ($row['slug'] ?? '');
    if ($slug === '') continue;
    $cats[$slug] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => $slug];
  }
  $cnt = $pdo->query("SELECT category_id, COUNT(*) AS c FROM products WHERE status = 'published' GROUP BY category_id")->fetchAll();
  foreach ($cnt as $row) {
    $cid = (int) ($row['category_id'] ?? 0);
    $c = (int) ($row['c'] ?? 0);
    $catCounts[$cid] = $c;
  }
} catch (Throwable $e) {
}
$productSlug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$isDetail = $productSlug !== '';
$detail = null;
$detailGallery = [];

$cat = isset($_GET['cat']) ? (string)$_GET['cat'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 9;
$total = 0;
$paged = [];
$totalPages = 1;

if ($isDetail) {
  front_editor_page_maybe_redirect('products');
  try {
    $st = $pdo->prepare(
      "SELECT p.id, p.category_id, p.name, p.slug, p.featured_image_url, p.short_description, p.content, p.price_int, p.price_text, p.gallery_json,
              c.name AS category_name, c.slug AS category_slug
       FROM products p
       LEFT JOIN product_categories c ON c.id = p.category_id
       WHERE p.status = 'published' AND p.slug = :s
       LIMIT 1"
    );
    $st->execute([':s' => $productSlug]);
    $detail = $st->fetch();
  } catch (Throwable $e) {
    $detail = null;
  }

  if (!$detail) {
    http_response_code(404);
    $isDetail = false;
    $productSlug = '';
  } else {
    $detailCatSlug = (string) ($detail['category_slug'] ?? '');
    if ($detailCatSlug !== '' && isset($cats[$detailCatSlug])) {
      $cat = $detailCatSlug;
    }
    $galleryJson = (string) ($detail['gallery_json'] ?? '');
    if ($galleryJson !== '') {
      $decoded = json_decode($galleryJson, true);
      if (is_array($decoded)) {
        foreach ($decoded as $u) {
          $u = trim((string) $u);
          if ($u !== '') $detailGallery[] = $u;
        }
      }
    }
  }
}

if (!$isDetail) {
  if ($cat === '' || !isset($cats[$cat])) {
    $first = array_key_first($cats);
    $cat = $first ? (string) $first : '';
  }

  if ($cat !== '' && isset($cats[$cat])) {
    $cid = (int) $cats[$cat]['id'];
    try {
      $st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE status = 'published' AND category_id = :cid");
      $st->execute([':cid' => $cid]);
      $total = (int) ($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
      $total = 0;
    }
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;
    try {
      $st = $pdo->prepare("SELECT id, name, slug, price_int, price_text, featured_image_url FROM products WHERE status = 'published' AND category_id = :cid ORDER BY updated_at DESC LIMIT :l OFFSET :o");
      $st->bindValue(':cid', $cid, PDO::PARAM_INT);
      $st->bindValue(':l', $perPage, PDO::PARAM_INT);
      $st->bindValue(':o', $offset, PDO::PARAM_INT);
      $st->execute();
      $paged = $st->fetchAll();
    } catch (Throwable $e) {
      $paged = [];
    }
  }
}
function money_vnd_text(?int $value, ?string $text): string {
  if ($value !== null) return number_format($value, 0, ',', '.') . ' đ';
  return $text !== null && $text !== '' ? $text : 'Liên hệ báo giá';
}
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
      $seo = !$isDetail ? front_editor_page_seo('products', [
        'title' => 'Top Dental Clinic • Sản phẩm',
        'description' => 'Trang sản phẩm và giải pháp nổi bật tại Top Dental Clinic.',
      ]) : ['title' => '', 'description' => '', 'keywords' => '', 'canonical_path' => ''];
      $pageTitle = $isDetail && $detail ? (string) ($detail['name'] ?? 'Sản phẩm') : (string) (($seo['title'] ?? '') !== '' ? $seo['title'] : 'Top Dental Clinic • Sản phẩm');
      $pageDescription = !$isDetail ? (string) ($seo['description'] ?? '') : '';
      $seoKeywords = !$isDetail ? (string) ($seo['keywords'] ?? '') : '';
    ?>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if ($pageDescription !== ''): ?><meta name="description" content="<?php echo htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <?php if (!$isDetail && (string) ($seo['canonical_path'] ?? '') !== ''): ?><link rel="canonical" href="<?php echo htmlspecialchars((string) $seo['canonical_path'], ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        font-family:inherit;
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
      .section{
        padding: 26px 0 34px;
      }
      .layout{
        display:grid;
        grid-template-columns: 280px 1fr;
        gap: 18px;
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
        color: rgba(17,24,39,0.72);
        background: rgba(255,255,255,0.70);
        border-bottom: 1px solid var(--border);
      }
      .panel .bd{ padding: 10px 14px; }
      .cat-list{
        display:grid;
        gap: 4px;
      }
      .cat-item{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 10px;
        padding: 10px 10px;
        border-radius: 14px;
        color: rgba(17,24,39,0.78);
        border: 1px solid transparent;
      }
      .cat-item:hover{
        background: rgba(17,24,39,0.05);
        border-color: rgba(17,24,39,0.08);
      }
      .cat-item.is-active{
        background: rgba(154,133,95,0.16);
        border-color: rgba(154,133,95,0.22);
        color: rgba(17,24,39,0.90);
      }
      .cat-item .arrow{ color: rgba(17,24,39,0.35); }
      .assist{
        display:grid;
        gap: 10px;
        padding: 12px 14px;
      }
      .assist b{
        font-family:inherit;
        font-size: 16px;
      }
      .assist p{
        margin: 0;
        color: var(--muted);
        line-height: 1.6;
        font-size: 13px;
      }
      .assist .hotline{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 14px;
        border: 1px solid rgba(17,24,39,0.10);
        background: rgba(255,255,255,0.80);
        color: rgba(17,24,39,0.86);
        font-weight: 700;
        font-size: 13px;
      }
      .assist .hotline span{
        color: rgba(17,24,39,0.60);
        font-weight: 600;
      }
      .grid{
        display:grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
      }
      .product{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow:hidden;
      }
      .product .img{
        height: 170px;
        background: var(--img-1) center/cover no-repeat;
      }
      .product .body{
        padding: 12px 12px 14px;
        display:grid;
        gap: 8px;
        text-align:center;
      }
      .product h3{
        margin: 0;
        font-size: 13px;
        letter-spacing: -0.01em;
      }
      .price{
        font-weight: 800;
        letter-spacing: -0.01em;
        color: rgba(17,24,39,0.90);
        font-size: 14px;
      }
      .price.is-quote{
        font-weight: 700;
        color: rgba(17,24,39,0.66);
      }
      .product .btn{
        justify-content:center;
        padding: 10px 12px;
        font-size: 13px;
        border-radius: 10px;
      }
      .product .btn-primary{
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 13px;
        box-shadow: none;
      }
      .pager{
        margin-top: 14px;
        display:flex;
        justify-content:center;
        gap: 8px;
        flex-wrap: wrap;
      }
      .page{
        width: 34px;
        height: 34px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.78);
        color: rgba(17,24,39,0.72);
        font-size: 13px;
        font-weight: 700;
      }
      .page:hover{
        background: rgba(255,255,255,0.94);
        color: rgba(17,24,39,0.90);
      }
      .page.is-active{
        background: rgba(47,42,36,0.92);
        border-color: rgba(47,42,36,0.30);
        color: rgba(255,255,255,0.92);
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
        font-family:inherit;
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
        .layout{ grid-template-columns: 1fr; }
        .grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
        .grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .footer-cols{ grid-template-columns: 1fr; }
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <section class="hero">
      <div class="hero-bg" aria-hidden="true" data-hero-bg="1"></div>
      <div class="container hero-inner">
        <div class="hero-copy">
          <div class="crumbs">
            <a href="/template1.php#home">Trang chủ</a>
            <span>•</span>
            <a href="/san-pham">Sản phẩm</a>
            <?php if ($isDetail && $detail): ?>
              <span>•</span>
              <span><?php echo htmlspecialchars((string) ($detail['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
          </div>
          <?php if ($isDetail && $detail): ?>
            <h1><?php echo htmlspecialchars((string) ($detail['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p><?php echo htmlspecialchars((string) (($detail['short_description'] ?? '') !== '' ? $detail['short_description'] : 'Sản phẩm cao cấp, thiết kế tinh gọn và bền vững.'), ENT_QUOTES, 'UTF-8'); ?></p>
          <?php else: ?>
            <h1>Sản phẩm cao cấp<br>cho phòng tắm sang trọng</h1>
            <p>Tuyển chọn bồn tắm, lavabo và phụ kiện cao cấp. Thiết kế tinh gọn, bền vững và đồng bộ theo dự án</p>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="layout">
          <aside class="panel">
            <div class="hd">Danh mục sản phẩm</div>
            <div class="bd">
              <div class="cat-list">
                <?php foreach ($cats as $key => $row): ?>
                  <?php $isActive = $cat === $key; $count = $catCounts[(int)$row['id']] ?? 0; ?>
                  <a class="cat-item <?php echo $isActive ? 'is-active' : ''; ?>" href="/san-pham?cat=<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="arrow"><span style="opacity:.65;margin-right:8px;"><?php echo $count; ?></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="assist">
              <b>Tư vấn dự án chuyên nghiệp</b>
              <p>Giải pháp đồng bộ theo mặt bằng, phong cách kiến trúc và ngân sách. Tối ưu vận hành và tiến độ.</p>
              <div class="hotline"><span>Hotline</span><?php require_once __DIR__ . '/db.php'; echo htmlspecialchars(site_hotline('0988 123 456'), ENT_QUOTES, 'UTF-8'); ?></div>
              <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> Nhận tư vấn</a>
            </div>
          </aside>
          <main>
            <?php if ($isDetail && $detail): ?>
              <?php
                $img = (string) ($detail['featured_image_url'] ?? '');
                $priceInt = $detail['price_int'] !== null ? (int) $detail['price_int'] : null;
                $priceText = isset($detail['price_text']) ? (string) $detail['price_text'] : null;
                $price = money_vnd_text($priceInt, $priceText);
                $content = (string) ($detail['content'] ?? '');
              ?>
              <div class="panel" style="padding:16px 16px;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items:start;">
                  <div class="product" style="box-shadow:none;">
                    <div class="img" aria-hidden="true" style="height:280px;<?php echo $img !== '' ? "background-image:url('".htmlspecialchars($img, ENT_QUOTES, 'UTF-8')."')" : ""; ?>"></div>
                  </div>
                  <div>
                    <div style="font-family:Inter,system-ui,sans-serif; font-weight:700; font-size:22px; letter-spacing:-0.02em; margin-bottom: 6px;"><?php echo htmlspecialchars((string) ($detail['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="price <?php echo $priceInt === null ? 'is-quote' : ''; ?>" style="font-size:18px;"><?php echo htmlspecialchars($price, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                      <a class="btn btn-primary" href="/lien-he.php"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> Nhận tư vấn</a>
                      <a class="btn btn-ghost" href="/san-pham?cat=<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Quay lại</a>
                    </div>
                    <?php
                      if ($detail && isset($detail['id'])) {
                        front_admin_render_edit_bar([
                          ['label' => 'Sửa sản phẩm', 'href' => '/admin/content_product_edit.php?id=' . (int) $detail['id'], 'icon' => 'fa-solid fa-pen-to-square'],
                          ['label' => 'Admin', 'href' => '/admin/dashboard.php', 'icon' => 'fa-solid fa-shield-halved'],
                        ]);
                      }
                    ?>
                    <?php if ((string) ($detail['short_description'] ?? '') !== ''): ?>
                      <div style="margin-top:12px; color: var(--muted); line-height:1.7;"><?php echo htmlspecialchars((string) $detail['short_description'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="margin-top: 14px; max-width: 80ch; line-height: 1.85; font-size: 16px;">
                  <?php echo trim($content) !== '' ? $content : '<p style="color:var(--muted)">Nội dung sản phẩm đang cập nhật.</p>'; ?>
                </div>
                <?php if (count($detailGallery) > 0): ?>
                  <div style="margin-top: 16px;">
                    <div style="font-weight:800; font-size:13px; letter-spacing:0.08em; text-transform:uppercase; color: rgba(17,24,39,0.72); margin-bottom: 10px;">Gallery</div>
                    <div class="grid-3">
                      <?php foreach ($detailGallery as $u): ?>
                        <div class="card">
                          <div class="img" style="height:170px;background-image:url('<?php echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8'); ?>')"></div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="grid">
                <?php foreach ($paged as $p): ?>
                  <article class="product">
                    <?php $img = (string) ($p['featured_image_url'] ?? ''); $style = $img !== '' ? "background-image:url('".htmlspecialchars($img, ENT_QUOTES, 'UTF-8')."')" : ""; ?>
                    <div class="img" aria-hidden="true" style="<?php echo $style; ?>"></div>
                    <div class="body">
                      <h3><?php echo htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                      <?php $priceInt = $p['price_int'] !== null ? (int)$p['price_int'] : null; $priceText = isset($p['price_text']) ? (string)$p['price_text'] : null; $price = money_vnd_text($priceInt, $priceText); ?>
                      <div class="price <?php echo $priceInt === null ? 'is-quote' : ''; ?>"><?php echo htmlspecialchars($price, ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php $href = '/san-pham/' . rawurlencode((string) ($p['slug'] ?? '')); ?>
                      <a class="btn btn-primary" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Xem chi tiết</a>
                      <?php if (function_exists('admin_front_is_logged_in') && admin_front_is_logged_in()): ?>
                        <a class="btn btn-ghost" href="/admin/content_product_edit.php?id=<?php echo (int) ($p['id'] ?? 0); ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Sửa</a>
                      <?php endif; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
              <?php if ($totalPages > 1): ?>
                <nav class="pager" aria-label="pagination">
                  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="page <?php echo $i === $page ? 'is-active' : ''; ?>" href="/san-pham?cat=<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                  <?php endfor; ?>
                </nav>
              <?php endif; ?>
            <?php endif; ?>
          </main>
        </div>
      </div>
    </section>
    <?php if (function_exists('front_editor_render')) { front_editor_render('products'); } ?>
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
    </script>
  </body>
</html>
