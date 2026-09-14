<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

$seo = front_editor_page_seo('danh-muc-y-te', [
  'title' => 'Danh mục y tế • MedReview',
  'description' => 'Khám phá đầy đủ các danh mục y tế trên MedReview: nha khoa, mắt, da liễu, tai mũi họng, tim mạch, sản phụ khoa và nhiều chuyên khoa khác.',
  'canonical_path' => '/danh-muc-y-te.php',
]);
$title = (string) ($seo['title'] ?? 'Danh mục y tế • MedReview');
$description = (string) ($seo['description'] ?? '');
$canonicalPath = (string) ($seo['canonical_path'] ?? '/danh-muc-y-te.php');
$seoKeywords = (string) ($seo['keywords'] ?? '');
$locale = 'vi';
$pageKey = 'danh-muc-y-te';
$categories = [
  ['icon' => 'badge-plus', 'title' => 'Nha khoa', 'count' => '1.248 cơ sở', 'color' => '#3b82f6', 'soft' => 'rgba(59,130,246,.14)'],
  ['icon' => 'ear', 'title' => 'Tai mũi họng', 'count' => '842 cơ sở', 'color' => '#2563eb', 'soft' => 'rgba(37,99,235,.12)'],
  ['icon' => 'sparkles', 'title' => 'Da liễu', 'count' => '766 cơ sở', 'color' => '#8b5cf6', 'soft' => 'rgba(139,92,246,.14)'],
  ['icon' => 'baby', 'title' => 'Sản phụ khoa', 'count' => '1.106 cơ sở', 'color' => '#7c3aed', 'soft' => 'rgba(124,58,237,.12)'],
  ['icon' => 'eye', 'title' => 'Mắt', 'count' => '621 cơ sở', 'color' => '#3b82f6', 'soft' => 'rgba(59,130,246,.12)'],
  ['icon' => 'heart-pulse', 'title' => 'Tim mạch', 'count' => '632 cơ sở', 'color' => '#ef4444', 'soft' => 'rgba(239,68,68,.12)'],
  ['icon' => 'bone', 'title' => 'Cơ xương khớp', 'count' => '573 cơ sở', 'color' => '#2563eb', 'soft' => 'rgba(37,99,235,.12)'],
  ['icon' => 'sparkle', 'title' => 'Thẩm mỹ', 'count' => '489 cơ sở', 'color' => '#ec4899', 'soft' => 'rgba(236,72,153,.12)'],
  ['icon' => 'baby', 'title' => 'Nhi khoa', 'count' => '1.032 cơ sở', 'color' => '#6366f1', 'soft' => 'rgba(99,102,241,.12)'],
  ['icon' => 'test-tube', 'title' => 'Xét nghiệm', 'count' => '312 cơ sở', 'color' => '#0ea5e9', 'soft' => 'rgba(14,165,233,.12)'],
  ['icon' => 'shield-plus', 'title' => 'Ung bướu', 'count' => '245 cơ sở', 'color' => '#f97316', 'soft' => 'rgba(249,115,22,.12)'],
  ['icon' => 'pill', 'title' => 'Tiêu hóa', 'count' => '423 cơ sở', 'color' => '#14b8a6', 'soft' => 'rgba(20,184,166,.12)'],
  ['icon' => 'brain', 'title' => 'Thần kinh', 'count' => '378 cơ sở', 'color' => '#6366f1', 'soft' => 'rgba(99,102,241,.12)'],
  ['icon' => 'lungs', 'title' => 'Hô hấp', 'count' => '316 cơ sở', 'color' => '#0f766e', 'soft' => 'rgba(15,118,110,.12)'],
  ['icon' => 'droplets', 'title' => 'Tiết niệu', 'count' => '289 cơ sở', 'color' => '#2563eb', 'soft' => 'rgba(37,99,235,.12)'],
];
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalPath, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
    <style>
      :root{
        --bg:#ffffff;
        --surface:#ffffff;
        --soft:#f5f8ff;
        --border:#eef2f7;
        --text:#101828;
        --muted:#667085;
        --brand:#3b82f6;
        --max:1320px;
      }
      *{box-sizing:border-box}
      html{height:100%}
      body{
        min-height:100%;
        margin:0;
        font-family:"Plus Jakarta Sans",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        color:var(--text);
        background:
          radial-gradient(720px 280px at 0% 0%, rgba(59,130,246,.05), transparent 62%),
          linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);
      }
      a{color:inherit;text-decoration:none}
      .container{width:min(100% - 32px, var(--max));margin:0 auto}
      .categories-page{padding:34px 0 80px}
      .breadcrumb{
        display:flex;
        align-items:center;
        gap:10px;
        color:#98a2b3;
        font-size:13px;
        font-weight:700;
      }
      .breadcrumb a{color:#98a2b3}
      .breadcrumb .is-active{color:var(--brand)}
      .breadcrumb .crumb-sep{
        width:14px;
        height:14px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        color:#c0c7d4;
      }
      .breadcrumb .crumb-sep svg{
        width:14px;
        height:14px;
        stroke-width:1.8;
      }
      .page-head{margin-top:34px}
      .page-head h1{
        margin:0;
      }
      .page-head p{
        margin:14px 0 0;
        color:var(--muted);
        font-size:18px;
        line-height:1.7;
        font-weight:500;
        max-width:36ch;
      }
      .category-grid{
        margin-top:34px;
        display:grid;
        grid-template-columns:repeat(5,minmax(0,1fr));
        gap:18px;
      }
      .category-card{
        position:relative;
        overflow:hidden;
        min-height:174px;
        padding:28px 16px 22px;
        border-radius:22px;
        background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);
        border:1px solid var(--border);
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:flex-start;
        text-align:center;
        box-shadow:0 10px 26px rgba(15,23,42,.04);
        transition:border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
      }
      .category-card::before{
        content:'';
        position:absolute;
        inset:auto -28px -42px auto;
        width:100px;
        height:100px;
        border-radius:999px;
        background:radial-gradient(circle, rgba(59,130,246,.10) 0%, rgba(59,130,246,0) 72%);
        pointer-events:none;
      }
      .category-card:hover{
        border-color:rgba(147,197,253,.85);
        box-shadow:0 18px 40px rgba(15,23,42,.08);
        transform:translateY(-3px);
      }
      .category-icon{
        position:relative;
        width:68px;
        height:68px;
        border-radius:22px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        margin-bottom:18px;
        color:var(--icon-color, var(--brand));
        background:
          radial-gradient(circle at 30% 28%, rgba(255,255,255,.92) 0%, rgba(255,255,255,.26) 36%, transparent 58%),
          linear-gradient(180deg, rgba(255,255,255,.98) 0%, var(--icon-soft, rgba(59,130,246,.12)) 100%);
        border:1px solid rgba(255,255,255,.88);
        box-shadow:
          inset 0 1px 0 rgba(255,255,255,.95),
          0 12px 22px rgba(59,130,246,.10);
      }
      .category-icon::after{
        content:'';
        position:absolute;
        inset:10px;
        border-radius:16px;
        border:1px solid rgba(255,255,255,.46);
        pointer-events:none;
      }
      .category-icon i{
        position:relative;
        z-index:1;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        filter:drop-shadow(0 3px 8px rgba(59,130,246,.10));
      }
      .category-icon svg{
        width:31px;
        height:31px;
        stroke-width:1.75;
      }
      .category-card h3{
        margin:0;
        color:#111827;
      }
      .category-count{
        display:block;
        margin-top:10px;
        color:#98a2b3;
        font-size:15px;
        font-weight:600;
      }
      .suggest-box{
        margin-top:36px;
        padding:28px 30px;
        border-radius:22px;
        background:linear-gradient(180deg,#f7faff 0%,#f3f8ff 100%);
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:18px;
        border:1px solid #e8f0ff;
        box-shadow:0 12px 30px rgba(15,23,42,.04);
      }
      .suggest-copy strong{
        display:block;
        font-size:30px;
        line-height:1.2;
        letter-spacing:-.04em;
        font-weight:600;
      }
      .suggest-copy p{
        margin:10px 0 0;
        color:var(--muted);
        font-size:17px;
        line-height:1.7;
        font-weight:500;
      }
      .suggest-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:140px;
        height:56px;
        padding:0 24px;
        border-radius:16px;
        border:1px solid rgba(59,130,246,.18);
        color:#fff;
        background:linear-gradient(180deg,#60a5fa 0%,#2563eb 100%);
        font-size:16px;
        font-weight:700;
        box-shadow:0 14px 28px rgba(37,99,235,.18);
      }
      @media (max-width:1100px){
        .category-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
      }
      @media (max-width:780px){
        .page-head p{font-size:15px}
        .category-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        .category-card{min-height:150px;padding:22px 14px 18px}
        .category-count{font-size:14px}
        .suggest-box{padding:22px 18px;flex-direction:column;align-items:flex-start}
        .suggest-copy strong{font-size:24px}
        .suggest-copy p{font-size:15px}
      }
      @media (max-width:520px){
        .container{width:min(100% - 24px, var(--max))}
        .categories-page{padding:24px 0 56px}
        .category-grid{gap:14px}
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <main class="categories-page site-typo">
      <section class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="/">Trang chủ</a>
          <span class="crumb-sep" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
          <span class="is-active">Danh mục y tế</span>
        </nav>
        <div class="page-head">
          <h1>Danh mục y tế</h1>
          <p>Khám phá các chuyên khoa và dịch vụ y tế</p>
        </div>
        <div class="category-grid">
          <?php foreach ($categories as $item): ?>
            <article class="category-card">
              <span class="category-icon" style="--icon-color:<?php echo htmlspecialchars((string) $item['color'], ENT_QUOTES, 'UTF-8'); ?>;--icon-soft:<?php echo htmlspecialchars((string) $item['soft'], ENT_QUOTES, 'UTF-8'); ?>;">
                <i data-lucide="<?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
              </span>
              <h3><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
              <span class="category-count"><?php echo htmlspecialchars($item['count'], ENT_QUOTES, 'UTF-8'); ?></span>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="suggest-box">
          <div class="suggest-copy">
            <strong>Không tìm thấy chuyên khoa bạn cần?</strong>
            <p>Gợi ý cho chúng tôi để cập nhật thêm chuyên khoa phù hợp với bạn.</p>
          </div>
          <a class="suggest-btn" href="<?php echo htmlspecialchars(front_editor_page_public_path('contact'), ENT_QUOTES, 'UTF-8'); ?>">Gửi gợi ý</a>
        </div>
      </section>
    </main>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      if (window.lucide) {
        window.lucide.createIcons();
      }
    </script>
  </body>
</html>
