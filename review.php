<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

$seo = front_editor_page_seo('review', [
  'title' => 'Review thực tế • MedReview',
  'description' => 'Khám phá các review điều trị thực tế, hình ảnh trước sau và trải nghiệm thật từ người dùng trên MedReview.',
  'canonical_path' => '/review.php',
]);
$title = (string) ($seo['title'] ?? 'Review thực tế • MedReview');
$description = (string) ($seo['description'] ?? '');
$canonicalPath = (string) ($seo['canonical_path'] ?? '/review.php');
$seoKeywords = (string) ($seo['keywords'] ?? '');

$filters = [
  'search' => 'Tìm theo dịch vụ, review, cơ sở...',
  'region' => 'Tất cả khu vực',
  'service' => 'Tất cả dịch vụ',
  'rating' => 'Tất cả đánh giá',
  'price' => '0đ - 50.000.000đ+',
  'sort' => 'Mới nhất',
];

// The directory opens with a compact, predictable first page. More reviews
// can be paginated independently later without rendering the whole table.
$reviews = medical_directory_review_rows(true, 10);

$ratingBreakdown = [
  ['label' => '5 sao', 'value' => 86],
  ['label' => '4 sao', 'value' => 10],
  ['label' => '3 sao', 'value' => 3],
  ['label' => '2 sao', 'value' => 0],
  ['label' => '1 sao', 'value' => 1],
];

$utilities = [
  ['icon' => 'images', 'title' => 'Xem before / after', 'text' => 'Ưu tiên review có hình ảnh rõ ràng và đầy đủ'],
  ['icon' => 'bookmark', 'title' => 'Lưu review nổi bật', 'text' => 'Lưu các ca phù hợp để so sánh trước khi quyết định'],
  ['icon' => 'shield-check', 'title' => 'Ưu tiên review xác minh', 'text' => 'Giúp bạn lọc ra các trải nghiệm đáng tin cậy hơn'],
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
        --bg:#f6f8fc;
        --surface:#ffffff;
        --surface-soft:#f5f8ff;
        --border:#e7eef8;
        --text:#0f172a;
        --muted:#64748b;
        --brand:#2563eb;
        --brand-soft:rgba(37,99,235,.10);
        --success:#16a34a;
        --warning:#f59e0b;
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
          radial-gradient(900px 320px at 0% 0%, rgba(59,130,246,.045), transparent 60%),
          linear-gradient(180deg,#fafcff 0%, #f5f8fd 100%);
      }
      a{color:inherit;text-decoration:none}
      .container{width:min(100% - 32px, var(--max));margin:0 auto}
      .review-page{padding:22px 0 64px}
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
      .crumb-sep{
        width:14px;height:14px;display:inline-flex;align-items:center;justify-content:center;color:#cbd5e1;
      }
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
        grid-template-columns:1.45fr repeat(5, minmax(0,1fr));
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
      .filter-range{
        padding:10px 14px 12px;
        border:1px solid var(--border);
        border-radius:14px;
        background:#f9fbff;
      }
      .filter-range input{width:100%}
      .range-value{display:block;margin-top:6px;color:#64748b;font-size:12px;text-align:center}
      .content-grid{
        margin-top:12px;
        display:grid;
        grid-template-columns:minmax(0,1fr) 260px;
        gap:12px;
        align-items:start;
      }
      .list-wrap{display:grid;gap:14px}
      .review-card{
        display:grid;
        grid-template-columns:312px minmax(0,1fr) 138px;
        gap:16px;
        padding:14px;
        border:1px solid var(--border);
        border-radius:18px;
        background:#fff;
        box-shadow:0 8px 18px rgba(15,23,42,.035);
        align-items:start;
      }
      .review-media{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
      }
      .review-shot{
        position:relative;
        overflow:hidden;
        border-radius:14px;
        height:220px;
        background:#e2e8f0;
      }
      .review-shot img{width:100%;height:100%;object-fit:cover}
      .shot-label{
        position:absolute;
        left:10px;
        bottom:10px;
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 10px;
        border-radius:999px;
        background:rgba(255,255,255,.92);
        color:#334155;
        font-size:11px;
        font-weight:700;
      }
      .review-main{padding:2px 0}
      .review-title{
        display:flex;
        align-items:flex-start;
        gap:10px;
        flex-wrap:wrap;
      }
      .review-title h3{margin:0;font-size:1.9em;line-height:1.15;letter-spacing:-.03em}
      .verified{
        display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:999px;
        background:rgba(22,163,74,.08);color:var(--success);font-size:11px;font-weight:700;
      }
      .verified svg{width:14px;height:14px;stroke-width:2}
      .rating-row-inline{
        display:flex;
        align-items:center;
        gap:10px;
        margin-top:8px;
      }
      .rating-row-inline .stars{
        margin-top:0;
      }
      .rating-inline-value{
        color:#334155;
        font-size:1em;
        font-weight:700;
      }
      .review-meta{
        margin-top:10px;
        display:grid;
        gap:6px;
      }
      .review-meta-line{
        display:flex;align-items:center;gap:10px;flex-wrap:wrap;color:#475569;font-size:13px;line-height:1.5;
      }
      .review-meta-item{
        display:inline-flex;align-items:center;gap:7px;
      }
      .review-meta-item svg{width:16px;height:16px;stroke-width:2;color:#60a5fa}
      .review-excerpt{
        margin:12px 0 0;
        color:#475569;
        font-size:14px;
        line-height:1.7;
      }
      .review-footer{
        display:flex;
        align-items:center;
        gap:18px;
        margin-top:16px;
        color:#64748b;
        font-size:14px;
      }
      .review-reaction{
        display:inline-flex;
        align-items:center;
        gap:8px;
      }
      .review-reaction svg{width:18px;height:18px;stroke-width:1.9}
      .stars{
        display:flex;
        gap:2px;
        color:var(--warning);
      }
      .stars svg{width:16px;height:16px;fill:currentColor;stroke:currentColor}
      .action-col{
        display:grid;
        gap:14px;
        align-content:end;
        min-height:220px;
      }
      .action-card,
      .summary-card strong,
      .customer-box strong{
        display:block;font-size:13px;margin-bottom:10px;color:#334155;
      }
      .price-badge{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 12px;
        border-radius:14px;
        background:#f8fbff;
        border:1px solid var(--border);
        color:#1e3a8a;
        font-size:13px;
        font-weight:700;
      }
      .price-badge svg{width:16px;height:16px;stroke-width:2}
      .detail-btn{
        display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 16px;border-radius:10px;
        background:linear-gradient(180deg,#3b82f6,#2563eb);color:#fff;font-size:12px;font-weight:700;
        box-shadow:0 10px 18px rgba(37,99,235,.16);
      }
      .sidebar{display:grid;gap:10px;position:sticky;top:98px}
      .summary-card,
      .utility-card,
      .customer-box{
        padding:16px;
        border:1px solid var(--border);
        border-radius:18px;
        background:#fff;
        box-shadow:0 8px 18px rgba(15,23,42,.035);
      }
      .summary-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
      .summary-score{
        font-size:2.2em;
        line-height:1;
        font-weight:700;
      }
      .summary-score small{font-size:.45em;color:#64748b}
      .summary-stars{display:flex;gap:2px;margin-top:8px;color:var(--warning)}
      .summary-stars svg{width:15px;height:15px;fill:currentColor;stroke:currentColor}
      .summary-caption{margin-top:6px;color:#64748b;font-size:12px}
      .rating-bars{display:grid;gap:10px;margin-top:16px}
      .rating-row{
        display:grid;
        grid-template-columns:36px 1fr 28px;
        gap:8px;
        align-items:center;
        color:#64748b;
        font-size:12px;
      }
      .bar{
        height:6px;border-radius:999px;background:#eef2f7;overflow:hidden;
      }
      .bar span{
        display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#60a5fa,#2563eb);
      }
      .utility-list{display:grid;gap:12px}
      .utility-item{display:flex;align-items:flex-start;gap:10px}
      .utility-icon{
        width:36px;height:36px;border-radius:12px;background:var(--brand-soft);color:var(--brand);
        display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
      }
      .utility-icon svg{width:18px;height:18px;stroke-width:2}
      .utility-item strong{margin-bottom:4px}
      .utility-item p,
      .customer-box p{
        margin:0;color:var(--muted);font-size:12px;line-height:1.65;
      }
      .customer-head{
        display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;
      }
      .customer-icon{
        width:40px;height:40px;border-radius:12px;background:rgba(59,130,246,.10);color:var(--brand);
        display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
      }
      .customer-icon svg{width:20px;height:20px;stroke-width:2}
      .write-btn{
        display:inline-flex;align-items:center;justify-content:center;width:100%;height:42px;border-radius:12px;
        background:#fff;border:1px solid var(--border);color:var(--brand);font-size:13px;font-weight:700;
      }
      .page-foot{
        margin-top:12px;
        color:#94a3b8;
        font-size:11px;
        text-align:center;
      }
      @media (max-width:1240px){
        .filter-bar{grid-template-columns:repeat(3,minmax(0,1fr))}
        .content-grid{grid-template-columns:1fr}
        .sidebar{position:static}
        .review-card{grid-template-columns:1fr}
        .action-col{min-height:0;align-content:start}
      }
      @media (max-width:980px){
        .hero-head{flex-direction:column}
        .hero-note{min-width:0;width:100%}
        .hero-head h1{font-size:2em}
        .review-card{grid-template-columns:1fr}
        .review-media{grid-template-columns:1fr 1fr}
        .review-shot{height:210px}
      }
      @media (max-width:720px){
        .container{width:min(100% - 24px, var(--max))}
        .review-page{padding:20px 0 48px}
        .filter-bar{grid-template-columns:1fr}
        .hero-title-row{align-items:flex-start}
        .hero-head h1{font-size:1.7em}
        .review-media{grid-template-columns:1fr}
        .review-shot{height:240px}
        .review-title h3{font-size:1.45em}
        .review-footer{gap:14px;font-size:13px;flex-wrap:wrap}
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <main class="review-page site-typo">
      <section class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="/">Trang chủ</a>
          <span class="crumb-sep" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
          <a href="/review.php">Review</a>
          <span class="crumb-sep" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
          <span class="active">Review thực tế</span>
        </nav>

        <div class="hero-head">
          <div>
            <div class="hero-title-row">
              <h1>Review thực tế</h1>
              <span class="title-pill"><i data-lucide="badge-check"></i> Tìm thấy 2.456 review</span>
            </div>
            <p class="hero-sub">Tổng hợp review có hình ảnh trước sau, chi phí và trải nghiệm thực tế để bạn dễ so sánh trước khi quyết định.</p>
          </div>
          <div class="hero-note">
            <span class="icon"><i data-lucide="shield-check"></i></span>
            <div>
              <strong>Ưu tiên review đã xác minh và có hình ảnh thực tế</strong>
              <span>Thông tin minh bạch - trải nghiệm thật - dễ đối chiếu trước sau</span>
            </div>
          </div>
        </div>

        <div class="filter-bar">
          <div class="filter-item">
            <label>Tìm kiếm</label>
            <div class="filter-input">
              <input type="text" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
              <span class="filter-icon"><i data-lucide="search"></i></span>
            </div>
          </div>
          <div class="filter-item">
            <label>Khu vực</label>
            <div class="filter-select"><span><?php echo htmlspecialchars($filters['region'], ENT_QUOTES, 'UTF-8'); ?></span><i data-lucide="chevron-down"></i></div>
          </div>
          <div class="filter-item">
            <label>Dịch vụ</label>
            <div class="filter-select"><span><?php echo htmlspecialchars($filters['service'], ENT_QUOTES, 'UTF-8'); ?></span><i data-lucide="chevron-down"></i></div>
          </div>
          <div class="filter-item">
            <label>Đánh giá</label>
            <div class="filter-select"><span><?php echo htmlspecialchars($filters['rating'], ENT_QUOTES, 'UTF-8'); ?></span><i data-lucide="chevron-down"></i></div>
          </div>
          <div class="filter-item">
            <label>Chi phí</label>
            <div class="filter-range">
              <input type="range" min="0" max="100" value="72">
              <span class="range-value"><?php echo htmlspecialchars($filters['price'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="filter-item">
            <label>Sắp xếp</label>
            <div class="filter-select"><span><?php echo htmlspecialchars($filters['sort'], ENT_QUOTES, 'UTF-8'); ?></span><i data-lucide="chevron-down"></i></div>
          </div>
        </div>

        <div class="content-grid">
          <div class="list-wrap">
            <?php foreach ($reviews as $item): ?>
              <article class="review-card">
                <div class="review-media">
                  <div class="review-shot">
                    <img src="<?php echo htmlspecialchars($item['before'], ENT_QUOTES, 'UTF-8'); ?>" alt="Before">
                    <span class="shot-label">Before</span>
                  </div>
                  <div class="review-shot">
                    <img src="<?php echo htmlspecialchars($item['after'], ENT_QUOTES, 'UTF-8'); ?>" alt="After">
                    <span class="shot-label">After</span>
                  </div>
                </div>

                <div class="review-main">
                  <div class="review-title">
                    <h3><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                  </div>
                  <div class="rating-row-inline">
                    <div class="stars">
                      <i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i>
                    </div>
                    <span class="rating-inline-value"><?php echo htmlspecialchars($item['rating'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="verified"><i data-lucide="badge-check"></i><?php echo htmlspecialchars($item['verified'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                  <div class="review-meta">
                    <div class="review-meta-line">
                      <span class="review-meta-item"><i data-lucide="user-round"></i><span><?php echo htmlspecialchars($item['author'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                      <span class="review-meta-item"><i data-lucide="map-pin"></i><span><?php echo htmlspecialchars($item['location'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                    </div>
                    <div class="review-meta-line">
                      <span class="review-meta-item"><i data-lucide="hospital"></i><span><?php echo htmlspecialchars($item['facility'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                    </div>
                    <div class="review-meta-line">
                      <span class="review-meta-item"><i data-lucide="wallet"></i><span><?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                    </div>
                  </div>
                  <p class="review-excerpt"><?php echo htmlspecialchars($item['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                  <div class="review-footer">
                    <span class="review-reaction"><i data-lucide="heart"></i><?php echo (int) $item['likes']; ?></span>
                    <span class="review-reaction"><i data-lucide="message-circle"></i><?php echo (int) $item['comments']; ?> bình luận</span>
                  </div>
                </div>

                <div class="action-col">
                  <div class="action-card">
                    <strong>Chi phí</strong>
                    <div class="price-badge"><i data-lucide="wallet"></i><?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <a class="detail-btn" href="/review-chi-tiet.php?slug=<?php echo rawurlencode((string) $item['slug']); ?>">Xem chi tiết</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <aside class="sidebar">
            <section class="summary-card">
              <strong>Tổng quan review</strong>
              <div class="summary-top">
                <div>
                  <div class="summary-score">4.9<small>/5</small></div>
                  <div class="summary-stars">
                    <i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i>
                  </div>
                  <div class="summary-caption">2.456 review</div>
                </div>
              </div>
              <div class="rating-bars">
                <?php foreach ($ratingBreakdown as $row): ?>
                  <div class="rating-row">
                    <span><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="bar"><span style="width:<?php echo (int) $row['value']; ?>%"></span></span>
                    <span><?php echo (int) $row['value']; ?>%</span>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section class="utility-card">
              <strong>Tiện ích</strong>
              <div class="utility-list">
                <?php foreach ($utilities as $item): ?>
                  <div class="utility-item">
                    <span class="utility-icon"><i data-lucide="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                    <div>
                      <strong><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      <p><?php echo htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <section class="customer-box">
              <div class="customer-head">
                <span class="customer-icon"><i data-lucide="clipboard-check"></i></span>
                <div>
                  <strong>Bạn đã trải nghiệm dịch vụ?</strong>
                  <p>Chia sẻ review thật để giúp mọi người chọn đúng cơ sở và dịch vụ.</p>
                </div>
              </div>
              <a class="write-btn" href="/lien-he.php">Viết đánh giá</a>
            </section>
          </aside>
        </div>

        <p class="page-foot">Dữ liệu review hiện là nội dung mẫu để dựng giao diện. Bạn có thể thay trực tiếp trong file sau.</p>
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
