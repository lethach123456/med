<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
if (!isset($_GET['slug']) || trim((string) ($_GET['slug'] ?? '')) === '') {
  front_editor_page_maybe_redirect('blog');
}
$pdo = db();
// Hero slider de mau cung 1 anh. Ban co the tu thay URL anh ngay tai file nay.
$heroSlides = [
  '/uploads/library/2026/06/b890073d530dd3cef0300b419481bb5e.png',
];
$blogCatId = 0;
try {
  $st = $pdo->prepare("SELECT id FROM categories WHERE slug = 'blog' LIMIT 1");
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
      'read' => 'Đọc nhanh',
    ];
  }
} catch (Throwable $e) {
  $paged = [];
}
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
      $seo = front_editor_page_seo('blog', [
        'title' => 'Top Dental Clinic • Tin tức & Kiến thức',
        'description' => 'Tin tức, kiến thức và bài viết mới nhất về răng sứ, thẩm mỹ nha khoa và xu hướng nụ cười đẹp tại Top Dental.',
      ]);
      $seoKeywords = (string) ($seo['keywords'] ?? '');
    ?>
    <title><?php echo htmlspecialchars((string) ($seo['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars((string) ($seo['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars((string) ($seo['canonical_path'] ?? '/blog'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
    <style>
      :root{
        --bg:#f6f8fc;
        --surface:#ffffff;
        --surface-soft:#f6f9ff;
        --border:#e7eef8;
        --text:#0f172a;
        --muted:#64748b;
        --brand:#2563eb;
        --brand-soft:rgba(37,99,235,.10);
        --warning:#f59e0b;
        --max:1320px;
      }
      *{box-sizing:border-box}
      html{height:100%}
      body{min-height:100%}
      body{
        margin:0;
        font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        color:var(--text);
        background:
          radial-gradient(900px 320px at 0% 0%, rgba(59,130,246,.045), transparent 60%),
          linear-gradient(180deg,#fafcff 0%, #f5f8fd 100%);
      }
      a{color:inherit;text-decoration:none}
      img{display:block;max-width:100%}
      .container{width:min(100% - 32px, var(--max));margin:0 auto}
      .toplist-page{padding:22px 0 64px}
      .breadcrumb{
        display:flex;
        align-items:center;
        gap:10px;
        color:#94a3b8;
        font-size:13px;
        font-weight:700;
      }
      .breadcrumb a{color:#94a3b8}
      .breadcrumb .active{color:var(--brand)}
      .crumb-sep{width:14px;height:14px;display:inline-flex;align-items:center;justify-content:center;color:#cbd5e1}
      .crumb-sep svg{width:14px;height:14px;stroke-width:1.9}
      .hero-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:18px;
        margin-top:12px;
      }
      .hero-title-row{
        display:flex;
        align-items:center;
        gap:12px;
        flex-wrap:wrap;
      }
      .hero-head h1{margin:0;font-size:2.6em;line-height:1.05;letter-spacing:-.045em}
      .title-pill{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 10px;
        border-radius:999px;
        background:rgba(59,130,246,.10);
        color:var(--brand);
        font-size:12px;
        font-weight:700;
      }
      .title-pill svg{width:14px;height:14px;stroke-width:1.9}
      .hero-sub{
        margin:10px 0 0;
        color:var(--muted);
        font-size:13px;
        line-height:1.65;
        max-width:72ch;
      }
      .hero-note{
        min-width:304px;
        padding:14px 16px;
        border-radius:16px;
        border:1px solid var(--border);
        background:#fff;
        display:flex;
        align-items:flex-start;
        gap:12px;
        box-shadow:0 12px 30px rgba(15,23,42,.04);
      }
      .hero-note .icon{
        width:36px;height:36px;border-radius:12px;background:rgba(37,99,235,.10);color:var(--brand);
        display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
      }
      .hero-note .icon svg{width:18px;height:18px;stroke-width:2}
      .hero-note strong{display:block;font-size:13px}
      .hero-note span{display:block;margin-top:4px;color:var(--muted);font-size:12px;line-height:1.55}
      .filter-bar{
        margin-top:14px;
        display:grid;
        grid-template-columns:1.5fr repeat(4,minmax(0,1fr));
        gap:14px;
        padding:12px;
        border:1px solid var(--border);
        border-radius:18px;
        background:#fff;
        box-shadow:0 8px 20px rgba(15,23,42,.035);
      }
      .filter-item{display:grid;gap:8px}
      .filter-item label{font-size:12px;font-weight:700;color:#334155}
      .filter-input,
      .filter-select{
        height:46px;
        border:1px solid var(--border);
        border-radius:12px;
        background:#f9fbff;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        padding:0 12px;
        color:#475569;
        font-size:12px;
      }
      .filter-input input{
        width:100%;border:0;background:transparent;outline:none;font:inherit;color:var(--text);
      }
      .filter-icon svg{width:18px;height:18px;stroke-width:1.9}
      .content-grid{
        margin-top:12px;
        display:grid;
        grid-template-columns:minmax(0,1fr) 292px;
        gap:12px;
        align-items:start;
      }
      .list-wrap{display:grid;gap:14px}
      .panel{
        border:1px solid var(--border);
        border-radius:18px;
        background:#fff;
        box-shadow:0 8px 18px rgba(15,23,42,.035);
      }
      .side-card{padding:16px}
      .side-card h3{
        margin:0 0 14px;
        font-size:1.02em;
      }
      .cat-list{display:grid;gap:8px}
      .cat-item{
        display:flex;align-items:center;justify-content:space-between;gap:10px;
        padding:11px 12px;border-radius:12px;color:#475569;border:1px solid var(--border);background:#fbfdff;
      }
      .cat-item.is-active{
        background:rgba(37,99,235,.08);
        border-color:rgba(37,99,235,.22);
        color:var(--brand);
      }
      .cat-item .count{
        display:inline-flex;align-items:center;gap:8px;color:#94a3b8;font-weight:700;font-size:13px;
      }
      .tips{
        display:grid;gap:10px;
      }
      .tips b{
        font-size:16px;
      }
      .tips p{
        margin:0;color:var(--muted);line-height:1.7;font-size:13px;
      }
      .btn{
        appearance:none;border:0;cursor:pointer;border-radius:12px;padding:11px 14px;font-weight:700;font-size:14px;
        display:inline-flex;align-items:center;justify-content:center;gap:10px;
      }
      .btn svg{width:16px;height:16px;stroke-width:2}
      .btn-primary{background:linear-gradient(180deg,#3b82f6,#2563eb);color:#fff;box-shadow:0 10px 18px rgba(37,99,235,.16)}
      .btn-ghost{background:#fff;color:var(--brand);border:1px solid var(--border)}
      .post{
        display:grid;
        grid-template-columns:284px minmax(0,1fr);
        gap:16px;
        padding:14px;
      }
      .post-media{
        height:214px;
        border-radius:16px;
        overflow:hidden;
        background:#e2e8f0 center/cover no-repeat;
      }
      .post-body{
        display:grid;
        gap:12px;
        align-content:start;
      }
      .post-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:14px;
      }
      .post h3{
        margin:0;
        font-size:1.5em;
        line-height:1.25;
        letter-spacing:-.03em;
      }
      .post-pill{
        display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;
        background:#f8fbff;border:1px solid var(--border);color:#2563eb;font-size:11px;font-weight:700;white-space:nowrap;
      }
      .post-pill svg{width:14px;height:14px;stroke-width:2}
      .meta{
        display:flex;gap:14px;flex-wrap:wrap;align-items:center;color:var(--muted);font-size:13px;
      }
      .meta span{display:inline-flex;gap:8px;align-items:center}
      .meta svg{width:16px;height:16px;stroke-width:2;color:#60a5fa}
      .excerpt{
        color:var(--muted);line-height:1.75;font-size:14px;
      }
      .post-actions{
        display:flex;gap:10px;flex-wrap:wrap;align-items:center;
      }
      .empty-state{
        padding:40px 20px;
        text-align:center;
        color:var(--muted);
      }
      .pager{
        margin-top:24px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;
      }
      .page{
        width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:12px;
        border:1px solid var(--border);background:#fff;color:#475569;font-size:14px;font-weight:700;
      }
      .page.is-active{
        background:var(--brand);border-color:var(--brand);color:#fff;
      }
      .stats-list{
        display:grid;
        gap:10px;
      }
      .stat-item{
        padding:12px;
        border-radius:12px;
        background:#fbfdff;
        border:1px solid var(--border);
      }
      .stat-item strong{
        display:block;
        font-size:1.15em;
        color:#2563eb;
      }
      .stat-item span{
        display:block;
        margin-top:4px;
        color:#64748b;
        font-size:12px;
      }
      @media (max-width:1240px){
        .content-grid{grid-template-columns:1fr}
      }
      @media (max-width:980px){
        .hero-head{flex-direction:column}
        .hero-note{min-width:0;width:100%}
        .hero-head h1{font-size:2em}
        .filter-bar{grid-template-columns:repeat(2,minmax(0,1fr))}
        .post{grid-template-columns:1fr}
        .post-media{height:240px}
      }
      @media (max-width:720px){
        .container{width:min(100% - 24px, var(--max))}
        .toplist-page{padding:20px 0 48px}
        .filter-bar{grid-template-columns:1fr}
        .hero-title-row{align-items:flex-start}
        .hero-head h1{font-size:1.7em}
        .post-head{display:grid}
        .post-actions{display:grid}
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <main class="toplist-page site-typo">
      <section class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="/">Trang chủ</a>
          <span class="crumb-sep" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
          <span class="active">Toplist</span>
        </nav>

        <div class="hero-head">
          <div>
            <div class="hero-title-row">
              <h1>Toplist & bài viết nổi bật</h1>
              <span class="title-pill"><i data-lucide="badge-check"></i> <?php echo (int) $total; ?> bài viết</span>
            </div>
            <p class="hero-sub">Tổng hợp các bài viết toplist, kinh nghiệm thực tế và nội dung khám chữa bệnh nổi bật để bạn dễ tìm, dễ so sánh và ra quyết định nhanh hơn.</p>
          </div>
          <div class="hero-note">
            <span class="icon"><i data-lucide="newspaper"></i></span>
            <div>
              <strong>Nội dung được sắp xếp theo mức độ hữu ích</strong>
              <span>Bài mới, toplist nổi bật và các chủ đề được quan tâm sẽ được ưu tiên hiển thị.</span>
            </div>
          </div>
        </div>

        <div class="filter-bar">
          <div class="filter-item">
            <label>Tìm kiếm</label>
            <div class="filter-input">
              <input type="text" value="Tìm theo tiêu đề, từ khóa, chủ đề...">
              <span class="filter-icon"><i data-lucide="search"></i></span>
            </div>
          </div>
          <div class="filter-item">
            <label>Chuyên mục</label>
            <div class="filter-select"><span>Toplist y tế</span><i data-lucide="chevron-down"></i></div>
          </div>
          <div class="filter-item">
            <label>Khu vực</label>
            <div class="filter-select"><span>Toàn quốc</span><i data-lucide="chevron-down"></i></div>
          </div>
          <div class="filter-item">
            <label>Nội dung</label>
            <div class="filter-select"><span>Bài viết mới nhất</span><i data-lucide="chevron-down"></i></div>
          </div>
          <div class="filter-item">
            <label>Sắp xếp</label>
            <div class="filter-select"><span>Mới nhất</span><i data-lucide="chevron-down"></i></div>
          </div>
        </div>

        <div class="content-grid">
          <div>
            <div class="list-wrap">
              <?php foreach ($paged as $p): ?>
                <?php
                $img = (string) $p['img'];
                $style = $img !== '' ? "background-image:url('".htmlspecialchars($img, ENT_QUOTES, 'UTF-8')."')" : "background-image:url('https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80')";
                ?>
                <article class="panel post">
                  <div class="post-media" aria-hidden="true" style="<?php echo $style; ?>"></div>
                  <div class="post-body">
                    <div class="post-head">
                      <h3><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                      <span class="post-pill"><i data-lucide="sparkles"></i> Toplist</span>
                    </div>
                    <div class="meta">
                      <span><i data-lucide="calendar-days"></i><?php echo htmlspecialchars($p['date'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <span><i data-lucide="clock-3"></i><?php echo htmlspecialchars($p['read'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <span><i data-lucide="badge-check"></i> Đã kiểm duyệt</span>
                    </div>
                    <div class="excerpt"><?php echo htmlspecialchars($p['excerpt'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="post-actions">
                      <a class="btn btn-primary" href="/blog/<?php echo htmlspecialchars(rawurlencode((string)$p['slug']), ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="arrow-right"></i> Đọc tiếp</a>
                      <a class="btn btn-ghost" href="/review.php"><i data-lucide="messages-square"></i> Xem review liên quan</a>
                      <?php if (function_exists('admin_front_is_logged_in') && admin_front_is_logged_in()): ?>
                        <a class="btn btn-ghost" href="/admin/content_post_edit.php?id=<?php echo (int) ($p['id'] ?? 0); ?>"><i data-lucide="square-pen"></i> Sửa</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
              <?php if (empty($paged)): ?>
                <article class="panel empty-state">
                  <h3>Chưa có bài viết nào</h3>
                  <p>Vui lòng quay lại sau.</p>
                </article>
              <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
              <nav class="pager" aria-label="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <a class="page <?php echo $i === $page ? 'is-active' : ''; ?>" href="/blog?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
              </nav>
            <?php endif; ?>
          </div>

          <aside class="sidebar">
            <section class="panel side-card">
              <h3>Chuyên mục</h3>
              <div class="cat-list">
                <a class="cat-item is-active" href="/blog.php">
                  <span>Tất cả bài viết</span>
                  <span class="count"><?php echo $total; ?> <i data-lucide="chevron-right"></i></span>
                </a>
                <a class="cat-item" href="/blog.php">
                  <span>Toplist phòng khám</span>
                  <span class="count">12 <i data-lucide="chevron-right"></i></span>
                </a>
                <a class="cat-item" href="/blog.php">
                  <span>Top bác sĩ nổi bật</span>
                  <span class="count">8 <i data-lucide="chevron-right"></i></span>
                </a>
                <a class="cat-item" href="/blog.php">
                  <span>Kinh nghiệm điều trị</span>
                  <span class="count">15 <i data-lucide="chevron-right"></i></span>
                </a>
              </div>
            </section>

            <section class="panel side-card">
              <h3>Tổng quan</h3>
              <div class="stats-list">
                <div class="stat-item"><strong><?php echo (int) $total; ?>+</strong><span>Bài viết đang hiển thị</span></div>
                <div class="stat-item"><strong>24/7</strong><span>Nội dung được cập nhật liên tục</span></div>
                <div class="stat-item"><strong>Toplist</strong><span>Tập trung vào nội dung dễ so sánh</span></div>
              </div>
            </section>

            <section class="panel side-card">
              <div class="tips">
                <b>Cần tư vấn nhanh?</b>
                <p>Gửi yêu cầu để nhận gợi ý cơ sở, bác sĩ hoặc review phù hợp theo nhu cầu của bạn.</p>
                <a class="btn btn-primary" href="/lien-he.php"><i data-lucide="message-circle-more"></i> Liên hệ ngay</a>
              </div>
            </section>
          </aside>
        </div>
      </section>
    </main>
    <?php if (function_exists('front_editor_render')) { front_editor_render('blog'); } ?>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      if (window.lucide) {
        window.lucide.createIcons();
      }
    </script>
  </body>
</html>
