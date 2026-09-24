<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/toplist_directory.php';
require_once __DIR__ . '/medical_directory.php';
medical_redirect_legacy_path('/toplist.php', medical_public_toplist_path());
$locale = site_page_locale('toplist');
$isEnglish = $locale === 'en';
$pdo = db();
toplist_directory_ensure_tables($pdo);
$rows = $pdo->query("SELECT t.id,t.title,t.slug,t.excerpt,t.featured_image_url,t.updated_at,COUNT(tf.id) AS facility_count FROM medical_toplists t LEFT JOIN medical_toplist_facilities tf ON tf.toplist_id=t.id WHERE t.status='published' GROUP BY t.id ORDER BY t.updated_at DESC,t.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$collageStatement = $pdo->prepare('SELECT f.image_url, f.gallery_json FROM medical_toplist_facilities tf JOIN medical_facilities f ON f.id=tf.facility_id WHERE tf.toplist_id=:toplist_id AND f.status=\'published\' ORDER BY tf.rank_order ASC, tf.id ASC LIMIT 4');
foreach ($rows as &$row) {
  $collageStatement->execute([':toplist_id' => (int) $row['id']]);
  $images = [];
  foreach ($collageStatement->fetchAll(PDO::FETCH_ASSOC) as $facility) {
    $image = trim((string) ($facility['image_url'] ?? ''));
    if ($image === '') {
      $gallery = medical_directory_gallery_urls((string) ($facility['gallery_json'] ?? ''));
      $image = trim((string) ($gallery[0] ?? ''));
    }
    if ($image !== '') { $images[] = $image; }
  }
  $row['collage_images'] = array_values(array_unique($images));
}
unset($row);
$seo = front_editor_page_seo('toplist', [
  'title' => 'Toplist cơ sở y tế | MedReview',
  'description' => 'Khám phá các danh sách cơ sở y tế được tổng hợp để bạn dễ so sánh và lựa chọn trên MedReview.',
  'canonical_path' => medical_public_toplist_path(),
]);
$seoTitle = (string) ($seo['title'] ?? 'Toplist cơ sở y tế | MedReview');
$seoDescription = (string) ($seo['description'] ?? '');
$seoKeywords = (string) ($seo['keywords'] ?? '');
$seoCanonical = (string) ($seo['canonical_path'] ?? medical_public_toplist_path());
$labels = $isEnglish ? [
  'title' => 'Healthcare Toplists', 'breadcrumbHome' => 'Home', 'articles' => 'articles',
  'subtitle' => 'Curated healthcare lists to help you compare and choose providers.',
  'editorial' => 'Editorially curated', 'editorialCopy' => 'Provider information is organized to make comparisons easier.',
  'search' => 'Search', 'searchHint' => 'Search Toplist articles...', 'sort' => 'Sort by', 'updated' => 'Recently updated',
  'toplist' => 'Toplist', 'explore' => 'Explore this list', 'updatedPrefix' => 'Updated',
  'facilities' => 'facilities in this list', 'details' => 'View details', 'overview' => 'Toplist overview',
  'published' => 'published Toplist articles, based on healthcare facility data on MedReview.',
  'tools' => 'Helpful tools', 'compare' => 'Quick comparison', 'compareCopy' => 'Compare multiple providers in one article.',
  'curatedInfo' => 'Curated information', 'curatedCopy' => 'Review clear information and facility lists.',
  'fallbackExcerpt' => 'Explore a curated list of healthcare facilities.', 'imageAlt' => 'Facility photo in a Toplist',
] : [
  'title' => 'Toplist cơ sở y tế', 'breadcrumbHome' => 'Trang chủ', 'articles' => 'bài viết',
  'subtitle' => 'Các danh sách tổng hợp giúp bạn so sánh và lựa chọn cơ sở phù hợp.',
  'editorial' => 'Danh sách được biên tập', 'editorialCopy' => 'Thông tin cơ sở được tổng hợp rõ ràng để bạn dễ đối chiếu.',
  'search' => 'Tìm kiếm', 'searchHint' => 'Tìm bài viết Toplist...', 'sort' => 'Sắp xếp', 'updated' => 'Mới cập nhật',
  'toplist' => 'Toplist', 'explore' => 'Khám phá danh sách', 'updatedPrefix' => 'Cập nhật',
  'facilities' => 'cơ sở trong danh sách', 'details' => 'Xem chi tiết', 'overview' => 'Tổng quan Toplist',
  'published' => 'Bài viết toplist đã xuất bản, cập nhật theo dữ liệu cơ sở y tế trên MedReview.',
  'tools' => 'Tiện ích', 'compare' => 'So sánh nhanh', 'compareCopy' => 'Đối chiếu nhiều cơ sở trong cùng bài viết.',
  'curatedInfo' => 'Thông tin có chọn lọc', 'curatedCopy' => 'Xem nội dung và danh sách cơ sở rõ ràng.',
  'fallbackExcerpt' => 'Khám phá danh sách cơ sở y tế được chọn lọc.', 'imageAlt' => 'Ảnh cơ sở trong Toplist',
];
if ($isEnglish) {
  $seoTitle = 'Healthcare Toplists | MedReview';
  $seoDescription = 'Explore curated healthcare facility lists on MedReview to compare providers and discover options across Vietnam.';
  $seoKeywords = 'healthcare Toplists, clinic lists, compare clinics, Vietnam healthcare';
}
$seoCanonical = site_localized_path($seoCanonical, $locale);
?><!doctype html>
<html lang="<?= $isEnglish ? 'en' : 'vi' ?>"><head>
    <?php echo site_favicon_tags(); ?><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?></title><meta name="description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>"><?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?= htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?><link rel="canonical" href="<?= htmlspecialchars(site_absolute_url($seoCanonical), ENT_QUOTES, 'UTF-8') ?>"><link rel="alternate" hreflang="vi" href="<?= htmlspecialchars(site_absolute_url(medical_public_toplist_path()), ENT_QUOTES, 'UTF-8') ?>"><link rel="alternate" hreflang="en" href="<?= htmlspecialchars(site_absolute_url(site_localized_path(medical_public_toplist_path(), 'en')), ENT_QUOTES, 'UTF-8') ?>"><link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars(site_absolute_url(medical_public_toplist_path()), ENT_QUOTES, 'UTF-8') ?>"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/css/core/shared-typography.css"><style>
:root{--bg:#f6f8fc;--surface:#fff;--border:#e7eef8;--text:#0f172a;--muted:#64748b;--brand:#2563eb;--soft:#eff6ff;--max:1320px}*{box-sizing:border-box}a{text-decoration:none;color:inherit}body{margin:0;background:var(--bg);color:var(--text);font-family:"Inter",system-ui,sans-serif}.container{width:min(var(--max),calc(100% - 32px));margin:auto}.facility-page{padding:28px 0 64px}.breadcrumb{display:flex;gap:10px;align-items:center;color:#94a3b8;font-size:13px;font-weight:700}.breadcrumb strong{color:var(--brand)}.hero-head{display:flex;justify-content:space-between;gap:20px;align-items:end;margin:22px 0}.hero-title-row{display:flex;gap:10px;align-items:center}.hero-head h1{margin:0;font-size:1.75em;letter-spacing:-.03em}.title-pill{padding:7px 10px;border-radius:999px;background:var(--soft);color:var(--brand);font-size:12px;font-weight:800}.hero-sub{margin:8px 0 0;color:var(--muted)}.hero-note{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:14px;background:#fff;max-width:340px}.hero-note .icon{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:var(--soft);color:var(--brand)}.hero-note strong,.hero-note span{display:block}.hero-note strong{font-size:12px}.hero-note span:last-child{margin-top:3px;color:#64748b;font-size:11px;line-height:1.45}.filter-bar{display:grid;grid-template-columns:minmax(240px,1fr) 170px;gap:14px;padding:16px;margin-bottom:18px;border:1px solid var(--border);border-radius:18px;background:#fff}.filter-item label{display:block;margin-bottom:7px;color:#64748b;font-size:11px;font-weight:800}.filter-input,.filter-select{height:42px;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 12px;border:1px solid var(--border);border-radius:11px;color:#64748b;font-size:13px}.filter-input i,.filter-select i{color:#2563eb}.content-grid{display:grid;grid-template-columns:minmax(0,1fr) 286px;gap:18px}.list-wrap{display:grid;gap:14px}.facility-card{display:grid;grid-template-columns:190px minmax(0,1fr) 132px 150px;gap:0;border:1px solid var(--border);border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.04)}.facility-media{position:relative;aspect-ratio:1/1;background:#eaf2ff}.facility-media>img{width:100%;height:100%;object-fit:cover}.toplist-collage{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-template-rows:repeat(2,minmax(0,1fr));gap:2px;width:100%;height:100%;background:#dbeafe}.toplist-collage img{display:block;width:100%;height:100%;min-height:0;object-fit:cover}.media-count{position:absolute;bottom:10px;left:10px;padding:6px 9px;border-radius:999px;background:#0f172acc;color:#fff;font-size:11px;font-weight:700}.facility-main{padding:18px}.facility-title{display:flex;align-items:center;gap:8px}.rank-badge{display:inline-grid;place-items:center;width:27px;height:27px;border-radius:8px;background:var(--soft);color:var(--brand);font-size:12px;font-weight:800}.facility-title h2{margin:0;font-size:17px;line-height:1.4}.facility-sub{margin:9px 0 0;color:#64748b;font-size:13px;line-height:1.6}.meta-row{margin-top:14px;display:flex;gap:7px;color:#64748b;font-size:12px}.meta-row i{width:16px;color:#2563eb}.score-col,.cta-col{padding:18px 14px;border-left:1px solid var(--border);display:flex;flex-direction:column;justify-content:center}.score-main{color:#1d4ed8;font-size:25px;font-weight:800}.score-main small{font-size:12px;color:#64748b}.score-meta{margin-top:8px;color:#64748b;font-size:11px}.cta-col strong{font-size:12px}.detail-btn{display:inline-flex;justify-content:center;margin-top:12px;padding:10px;border-radius:10px;background:#2563eb;color:#fff;font-size:12px;font-weight:800}.sidebar{display:grid;gap:16px;align-content:start}.summary-card,.utility-card{padding:18px;border:1px solid var(--border);border-radius:18px;background:#fff}.summary-card strong,.utility-card>strong{font-size:15px}.summary-number{margin:15px 0 5px;color:#2563eb;font-size:34px;font-weight:800}.summary-card p{margin:0;color:#64748b;font-size:12px;line-height:1.6}.utility-list{display:grid;gap:13px;margin-top:16px}.utility-item{display:flex;gap:10px}.utility-icon{display:grid;place-items:center;width:30px;height:30px;border-radius:9px;background:var(--soft);color:#2563eb}.utility-item strong{font-size:12px}.utility-item p{margin:4px 0 0;color:#64748b;font-size:11px;line-height:1.5}@media(max-width:1040px){.content-grid{grid-template-columns:1fr}.sidebar{grid-template-columns:1fr 1fr}}@media(max-width:760px){.facility-card{grid-template-columns:150px minmax(0,1fr) 120px}.cta-col{grid-column:2/4;border-top:1px solid var(--border);border-left:0;flex-direction:row;align-items:center;justify-content:space-between}.detail-btn{margin:0;padding:9px 14px}}@media(max-width:560px){.container{width:min(var(--max),calc(100% - 24px))}.hero-head{align-items:flex-start;flex-direction:column}.hero-title-row{align-items:flex-start;flex-direction:column}.filter-bar{grid-template-columns:1fr}.facility-card{grid-template-columns:1fr}.facility-media{height:auto;min-height:0}.score-col{border-left:0;border-top:1px solid var(--border);flex-direction:row;gap:12px;align-items:center}.cta-col{grid-column:auto}.sidebar{grid-template-columns:1fr}}
/* Subtle motion stays local to the directory and avoids extra JavaScript. */
@view-transition{navigation:auto}
::view-transition-old(root){animation:toplist-page-out .16s ease both}
::view-transition-new(root){animation:toplist-page-in .24s cubic-bezier(.2,.8,.2,1) both}
@keyframes toplist-page-out{to{opacity:0;transform:translateY(-3px)}}
@keyframes toplist-page-in{from{opacity:0;transform:translateY(8px)}}
@keyframes toplist-surface-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.hero-head,.filter-bar,.content-grid{animation:toplist-surface-in .42s cubic-bezier(.2,.8,.2,1) both}.filter-bar{animation-delay:.055s}.content-grid{animation-delay:.1s}.list-wrap{transition:opacity .18s ease}.list-wrap.is-revealing .facility-card{animation:toplist-surface-in .34s cubic-bezier(.2,.8,.2,1) both;animation-delay:calc(min(var(--reveal-index,0),6) * 42ms)}.facility-card,.detail-btn,.filter-input,.filter-select,.summary-card,.utility-card{transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background-color .18s ease}.facility-card:focus-within{border-color:#a9c8f7;box-shadow:0 16px 34px rgba(30,64,175,.1)}.detail-btn:focus-visible{outline:3px solid rgba(37,99,235,.23);outline-offset:3px}@media(hover:hover){.facility-card:hover{transform:translateY(-2px);border-color:#c8daf5;box-shadow:0 16px 34px rgba(30,64,175,.09)}.facility-card:hover .facility-media>img{transform:scale(1.035)}.detail-btn:hover{transform:translateY(-1px);box-shadow:0 10px 20px rgba(37,99,235,.22)}.summary-card:hover,.utility-card:hover{transform:translateY(-2px);box-shadow:0 13px 28px rgba(15,23,42,.06)}}@media(hover:none){.facility-card:active,.detail-btn:active{transform:scale(.985)}.detail-btn:active{box-shadow:none}}.facility-media>img,.toplist-collage img{transition:transform .32s ease,opacity .22s ease}.toplist-collage img{opacity:0}.toplist-collage.is-ready img{opacity:1}.toplist-collage.is-ready img:nth-child(2){transition-delay:.035s}.toplist-collage.is-ready img:nth-child(3){transition-delay:.07s}.toplist-collage.is-ready img:nth-child(4){transition-delay:.105s}@media(prefers-reduced-motion:reduce){.hero-head,.filter-bar,.content-grid,.list-wrap.is-revealing .facility-card{animation:none!important}.facility-card,.detail-btn,.filter-input,.filter-select,.summary-card,.utility-card,.facility-media>img,.toplist-collage img{transition-duration:.01ms!important}}
</style><style id="toplist-directory-mobile-balance">
@media(max-width:560px){
  .facility-page{padding:14px 0 36px}
  .breadcrumb{gap:7px;font-size:11px;overflow:hidden;white-space:nowrap}
  .hero-head{gap:12px;margin:14px 0}
  .hero-title-row{flex-direction:row;align-items:center;flex-wrap:wrap;gap:7px}
  .hero-head h1{font-size:23px;line-height:1.2}
  .title-pill{padding:5px 8px;font-size:11px}
  .hero-sub{font-size:14px;line-height:1.55}
  .hero-note{width:100%;max-width:none;padding:10px 12px}
  .hero-note span:last-child{font-size:12px}
  .facility-card{grid-template-columns:88px minmax(0,1fr);grid-template-areas:"media main" "score cta";gap:0 10px;padding:9px;border-radius:15px}
  .facility-media{grid-area:media;aspect-ratio:auto;height:88px;min-height:0;border-radius:10px}
  .media-count{bottom:5px;left:5px;padding:4px 6px;font-size:9px}
  .facility-main{grid-area:main;min-width:0;padding:0 2px 8px 0}
  .facility-title{align-items:flex-start;gap:6px}
  .rank-badge{flex:0 0 24px;width:24px;height:24px;font-size:11px}
  .facility-title h2{font-size:14px;line-height:1.35;overflow-wrap:anywhere}
  .facility-sub{display:-webkit-box;margin-top:4px;overflow:hidden;font-size:12px;line-height:1.45;-webkit-box-orient:vertical;-webkit-line-clamp:2}
  .meta-row{margin-top:5px;font-size:11px}
  .score-col{grid-area:score;flex-direction:row;align-items:center;gap:6px;padding:8px 3px 2px 4px;border-top:1px solid var(--border);border-left:0}
  .score-main{font-size:20px}
  .score-main small{font-size:11px}
  .score-meta{margin:0;font-size:11px}
  .cta-col{grid-area:cta;flex-direction:row;align-items:center;justify-content:flex-end;gap:8px;padding:8px 2px 2px;border-top:1px solid var(--border);border-left:0}
  .cta-col strong{display:none}
  .detail-btn{min-height:36px;margin:0;padding:8px 11px;font-size:11px}
  .sidebar{grid-template-columns:1fr}
  .summary-card,.utility-card{padding:14px;border-radius:15px}
  .summary-card p,.utility-item p{font-size:12px;line-height:1.55}
}
@media(max-width:360px){
  .container{width:calc(100% - 20px)}
  .hero-head h1{font-size:21px}
  .facility-card{grid-template-columns:78px minmax(0,1fr);column-gap:8px}
  .facility-media{height:78px}
  .facility-title h2{font-size:13.5px}
  .facility-sub{font-size:11.5px}
}
</style></head><body>
<?php include __DIR__ . '/Tem/header.php'; ?>
<main class="facility-page site-typo"><section class="container">
  <nav class="breadcrumb"><a href="<?= htmlspecialchars(site_localized_path('/', $locale), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labels['breadcrumbHome'], ENT_QUOTES, 'UTF-8') ?></a><span>›</span><strong>Toplist</strong></nav>
  <div class="hero-head"><div><div class="hero-title-row"><h1><?= htmlspecialchars($labels['title'], ENT_QUOTES, 'UTF-8') ?></h1><span class="title-pill"><?= count($rows) ?> <?= htmlspecialchars($labels['articles'], ENT_QUOTES, 'UTF-8') ?></span></div><p class="hero-sub"><?= htmlspecialchars($labels['subtitle'], ENT_QUOTES, 'UTF-8') ?></p></div><div class="hero-note"><span class="icon"><i data-lucide="shield-check"></i></span><div><strong><?= htmlspecialchars($labels['editorial'], ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars($labels['editorialCopy'], ENT_QUOTES, 'UTF-8') ?></span></div></div></div>
  <div class="filter-bar"><div class="filter-item"><label><?= htmlspecialchars($labels['search'], ENT_QUOTES, 'UTF-8') ?></label><div class="filter-input"><span><?= htmlspecialchars($labels['searchHint'], ENT_QUOTES, 'UTF-8') ?></span><i data-lucide="search"></i></div></div><div class="filter-item"><label><?= htmlspecialchars($labels['sort'], ENT_QUOTES, 'UTF-8') ?></label><div class="filter-select"><span><?= htmlspecialchars($labels['updated'], ENT_QUOTES, 'UTF-8') ?></span><i data-lucide="chevron-down"></i></div></div></div>
  <div class="content-grid"><div class="list-wrap"><?php foreach($rows as $index=>$row): ?><article class="facility-card"><div class="facility-media"><?php if(trim((string)$row['featured_image_url']) !== ''): ?><img src="<?= htmlspecialchars($row['featured_image_url'],ENT_QUOTES) ?>" alt="<?= htmlspecialchars($row['title'],ENT_QUOTES) ?>" loading="lazy" decoding="async"><?php endif; ?><span class="media-count"><i data-lucide="list-ordered"></i> Toplist</span></div><div class="facility-main"><div class="facility-title"><span class="rank-badge"><?= $index+1 ?></span><h2><?= htmlspecialchars($row['title'],ENT_QUOTES) ?></h2></div><p class="facility-sub"><?= htmlspecialchars((string)($row['excerpt'] ?: $labels['fallbackExcerpt']),ENT_QUOTES) ?></p><div class="meta-row"><i data-lucide="calendar-days"></i><span><?= htmlspecialchars($labels['updatedPrefix'],ENT_QUOTES) ?> <?= htmlspecialchars(date('d/m/Y',strtotime((string)$row['updated_at'])),ENT_QUOTES) ?></span></div></div><div class="score-col"><div class="score-main"><?= (int)$row['facility_count'] ?></div><div class="score-meta"><?= htmlspecialchars($labels['facilities'], ENT_QUOTES, 'UTF-8') ?></div></div><div class="cta-col"><strong><?= htmlspecialchars($labels['explore'], ENT_QUOTES, 'UTF-8') ?></strong><a class="detail-btn" href="/toplist-chi-tiet-mau.php?slug=<?= rawurlencode($row['slug']) ?>"><?= htmlspecialchars($labels['details'], ENT_QUOTES, 'UTF-8') ?></a></div></article><?php endforeach; ?></div><aside class="sidebar"><section class="summary-card"><strong><?= htmlspecialchars($labels['overview'], ENT_QUOTES, 'UTF-8') ?></strong><div class="summary-number"><?= count($rows) ?></div><p><?= count($rows) ?> <?= htmlspecialchars($labels['published'], ENT_QUOTES, 'UTF-8') ?></p></section><section class="utility-card"><strong><?= htmlspecialchars($labels['tools'], ENT_QUOTES, 'UTF-8') ?></strong><div class="utility-list"><div class="utility-item"><span class="utility-icon"><i data-lucide="scale"></i></span><div><strong><?= htmlspecialchars($labels['compare'], ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars($labels['compareCopy'], ENT_QUOTES, 'UTF-8') ?></p></div></div><div class="utility-item"><span class="utility-icon"><i data-lucide="shield-check"></i></span><div><strong><?= htmlspecialchars($labels['curatedInfo'], ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars($labels['curatedCopy'], ENT_QUOTES, 'UTF-8') ?></p></div></div></div></section></aside></div>
</section></main>
<?php include __DIR__ . '/Tem/footer.php'; ?><script src="https://unpkg.com/lucide@latest"></script><script>window.lucide&&lucide.createIcons()</script></body></html>
<?php $toplistCollageMap = []; foreach ($rows as $row) { $toplistCollageMap[(string) $row['slug']] = (array) ($row['collage_images'] ?? []); } ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const collages = <?= json_encode($toplistCollageMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const imageAlt = <?= json_encode($labels['imageAlt'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    const list = document.querySelector('.list-wrap');
    if (list) {
      list.querySelectorAll(':scope > .facility-card').forEach((card, index) => card.style.setProperty('--reveal-index', String(index)));
      if (!reduceMotion) requestAnimationFrame(() => list.classList.add('is-revealing'));
    }
    document.querySelectorAll('.facility-card').forEach(card => {
      const href = card.querySelector('.detail-btn')?.getAttribute('href') || '';
      const slug = new URL(href, location.origin).searchParams.get('slug') || '';
      const images = (collages[slug] || []).slice(0, 4);
      if (images.length < 2) return;
      const media = card.querySelector('.facility-media');
      const original = media?.querySelector(':scope > img');
      if (!media) return;
      const collage = document.createElement('div');
      collage.className = 'toplist-collage';
      images.forEach(source => {
        const image = document.createElement('img');
        image.src = source;
        image.alt = imageAlt;
        collage.append(image);
      });
      if (original) {
        original.replaceWith(collage);
      } else {
        media.prepend(collage);
      }
      if (!reduceMotion) requestAnimationFrame(() => collage.classList.add('is-ready'));
      else collage.classList.add('is-ready');
    });
  });
</script>
