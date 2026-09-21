<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
require_once __DIR__ . '/front_admin.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$routeContext = isset($_GET['route']) ? trim((string) $_GET['route']) : '';
$editTemplate = isset($_GET['edit_template']) && (string) $_GET['edit_template'] === '1';
$canEditTemplate = $editTemplate && function_exists('admin_front_is_logged_in') && admin_front_is_logged_in();
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? trim($requestPath) : '';
$isBlogDetailRoute = $routeContext === 'blog'
    || $routeContext === 'news'
    || preg_match('~^/(?:blog|news)/[^/]+/?$~', $requestPath) === 1;

$pdo = db();

$post = null;
if ($slug !== '') {
    $sql = "SELECT p.id, p.title, p.slug, p.featured_image_url, p.excerpt, p.content, p.seo_title, p.seo_description, p.updated_at, p.template,
                   p.status, c.name AS category_name, c.slug AS category_slug
            FROM posts p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.slug = :slug";
    if ($isBlogDetailRoute) {
        $sql .= " AND c.slug IN ('blog', 'blog-en')";
    }
    if (!$canEditTemplate) {
        $sql .= " AND p.status = 'published'";
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $post = $stmt->fetch();
} elseif ($id > 0) {
    $sql = "SELECT p.id, p.title, p.slug, p.featured_image_url, p.excerpt, p.content, p.seo_title, p.seo_description, p.updated_at, p.template,
                   p.status, c.name AS category_name, c.slug AS category_slug
            FROM posts p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = :id";
    if ($isBlogDetailRoute) {
        $sql .= " AND c.slug IN ('blog', 'blog-en')";
    }
    if (!$canEditTemplate) {
        $sql .= " AND p.status = 'published'";
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $post = $stmt->fetch();
}

if (!$post) {
    http_response_code(404);
    $title = 'Không tìm thấy bài viết';
    $excerpt = '';
    $content = '';
    $featured = '';
    $updatedAt = '';
    $categoryName = '';
    $templateMode = false;
} else {
    $post = post_template_seed_content($post);
    $title = (string) ($post['title'] ?? '');
    $excerpt = (string) ($post['excerpt'] ?? '');
    $content = (string) ($post['content'] ?? '');
    $featured = (string) ($post['featured_image_url'] ?? '');
    $updatedAt = (string) ($post['updated_at'] ?? '');
    $categoryName = (string) ($post['category_name'] ?? '');
    $templateMode = (int) ($post['template'] ?? 0) === 1;
}
$frontEditorPostKey = ($templateMode && $post && isset($post['id']) && $canEditTemplate) ? ('post:' . (int) $post['id']) : '';

$safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
$heroImg = $featured !== '' ? $featured : '/uploads/library/2026/03/6a55a84cd37cfcf62fdc196ddd24f2af.jpg';
$kicker = $categoryName !== '' ? $categoryName : 'Bài viết';
$postCategorySlug = trim((string) ($post['category_slug'] ?? ''));
$isServicePost = $postCategorySlug === 'dich-vu' || $postCategorySlug === 'dich-vu-en';
$isEnglishPost = $postCategorySlug === 'blog-en'
    || $postCategorySlug === 'dich-vu-en'
    || $routeContext === 'news'
    || preg_match('~^/news/[^/]+/?$~', $requestPath) === 1;
$htmlLang = $isEnglishPost ? 'en' : 'vi';
$seoTitle = trim((string) ($post['seo_title'] ?? '')) !== '' ? (string) $post['seo_title'] : $title;
$featuredImageAbsolute = site_absolute_media_url($featured);
$descriptionSource = trim((string) ($post['seo_description'] ?? ''));
if ($descriptionSource === '') {
    $descriptionSource = $excerpt !== '' ? $excerpt : $content;
}
$seoDescription = site_meta_description($descriptionSource !== '' ? $descriptionSource : $title . ' — bài viết trên MedReview.');
$isIndexableArticle = is_array($post) && (string) ($post['status'] ?? '') === 'published' && !$canEditTemplate;
$articleCanonicalPath = '';
if ($isIndexableArticle) {
    $articleSlug = rawurlencode((string) ($post['slug'] ?? ''));
    if ($postCategorySlug === 'blog-en') {
        $articleCanonicalPath = '/news/' . $articleSlug;
    } elseif ($postCategorySlug === 'blog') {
        $articleCanonicalPath = '/blog/' . $articleSlug;
    } else {
        $articleCanonicalPath = $requestPath !== '' && $requestPath !== '/' ? $requestPath : '/' . $articleSlug;
    }
}
$articleSchema = [];
if ($isIndexableArticle) {
    $articleCanonicalUrl = site_absolute_url($articleCanonicalPath);
    $articleModified = '';
    try {
        $articleModified = (new DateTimeImmutable((string) ($post['updated_at'] ?? '')))->format(DATE_ATOM);
    } catch (Throwable $e) {
        $articleModified = '';
    }
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $articleCanonicalUrl],
        'headline' => $seoTitle,
        'description' => $seoDescription,
        'inLanguage' => $htmlLang === 'en' ? 'en' : 'vi-VN',
        'publisher' => ['@type' => 'Organization', 'name' => 'MedReview', 'url' => site_absolute_url('/')],
    ];
    if ($featuredImageAbsolute !== '') {
        $articleSchema['image'] = $featuredImageAbsolute;
    }
    if ($articleModified !== '') {
        $articleSchema['dateModified'] = $articleModified;
    }
}
$GLOBALS['site_page_key'] = $isServicePost
    ? ($isEnglishPost ? 'services-en' : 'dich-vu')
    : ($isEnglishPost ? 'blog-en' : 'blog');
$homePath = $isEnglishPost ? '/services' : '/';
$homeLabel = $isEnglishPost ? 'Home' : 'Trang chủ';
$listingPath = $isServicePost
    ? ($isEnglishPost ? '/services' : '/dich-vu')
    : '/blog';
$listingLabel = $isServicePost
    ? ($isEnglishPost ? 'Services' : 'Dịch vụ')
    : ($isEnglishPost ? 'News' : 'Tin tức');
$backLabel = $isServicePost
    ? ($isEnglishPost ? 'Back to services' : 'Quay lại dịch vụ')
    : ($isEnglishPost ? 'Back to news' : 'Quay lại');
$contactPath = $isEnglishPost ? '/contact-us' : '/lien-he';
$contactLabel = $isEnglishPost ? 'Contact us' : 'Nhận tư vấn';
$updatedLabel = $isEnglishPost ? 'Updated' : 'Cập nhật';
$missingPostTitle = $isEnglishPost ? 'Article not found' : 'Không tìm thấy bài viết';
$missingPostText = $isEnglishPost ? 'Article not found.' : 'Không tìm thấy bài viết.';
$emptyContentText = $isEnglishPost ? 'No content yet.' : 'Chưa có nội dung.';
$suggestedFacilities = array_slice(medical_directory_facility_rows(true), 0, 3);
$suggestedReviews = array_slice(medical_directory_review_rows(true), 0, 3);
if (!$post) {
    $title = $missingPostTitle;
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8'); ?>">
  <head>
    <?php echo site_favicon_tags(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $safeTitle; ?></title>
    <?php if (!$isIndexableArticle): ?><meta name="robots" content="noindex,follow"><?php else: ?>
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars(site_absolute_url($articleCanonicalPath), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="article"><meta property="og:title" content="<?php echo htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?>"><meta property="og:description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($featuredImageAbsolute !== ''): ?><meta property="og:image" content="<?php echo htmlspecialchars($featuredImageAbsolute, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <?php echo site_json_ld($articleSchema); ?>
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root{
        --bg: #f0f9ff;
        --surface: rgba(255,255,255,0.86);
        --surface-2: rgba(255,255,255,0.94);
        --border: rgba(37,99,235,0.12);
        --text: rgba(15,23,42,0.95);
        --muted: rgba(15,23,42,0.68);
        --brand: #1e40af;
        --brand-2: #3b82f6;
        --brand-light: #dbeafe;
        --shadow: 0 18px 50px rgba(37,99,235,0.18);
        --radius: 10px;
        --max: 1320px;
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
      .brand-mark{ width: 40px; height: 40px; border-radius: 14px; background: radial-gradient(18px 18px at 30% 25%, rgba(255,255,255,0.72), transparent 60%), linear-gradient(135deg, var(--brand-2), var(--brand)); box-shadow: 0 18px 50px rgba(37,99,235,0.25); display: flex; align-items: center; justify-content: center; }
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
      .btn-primary{ background: var(--brand); color: rgba(255,255,255,0.95); box-shadow: 0 18px 60px rgba(37,99,235,0.35); }
      .btn-primary:hover{ filter: brightness(1.05); background: #1e3a8a; }
      .btn-ghost{ background: rgba(255,255,255,0.72); color: var(--text); border: 1px solid var(--border); }
      .btn-ghost:hover{ background: var(--brand-light); }
      .hero{ position: relative; overflow: hidden; }
      .hero-bg{ position:absolute; inset: 0; background: linear-gradient(90deg, rgba(30,64,175,0.75) 0%, rgba(30,64,175,0.55) 42%, rgba(30,64,175,0.35) 72%, rgba(30,64,175,0.5) 100%), var(--img-1) center/cover no-repeat; transform: scale(1.03); filter: saturate(0.98) contrast(1.02); }
      .hero-inner{ position: relative; padding: 110px 0 72px; min-height: 78vh; display:flex; align-items:center; }
      .hero-copy{ max-width: 860px; padding: 22px 20px; border-radius: var(--radius); background: rgba(0,0,0,0.26); border: 1px solid rgba(255,255,255,0.14); box-shadow: 0 28px 80px rgba(0,0,0,0.22); color: rgba(255,255,255,0.96); backdrop-filter: blur(12px); }
      .crumbs{ display:flex; gap: 10px; flex-wrap: wrap; align-items:center; font-size: 13px; color: rgba(255,255,255,0.82); }
      .crumbs a{ color: rgba(255,255,255,0.90); }
      .crumbs a:hover{ color: rgba(255,255,255,0.98); }
      .hero-copy h1{ margin: 10px 0 10px; font-family:inherit; font-weight: 700; letter-spacing: -0.03em; line-height: 1.1; font-size: clamp(30px, 3.0vw, 44px); }
      .hero-copy p{ margin: 0; color: rgba(255,255,255,0.82); line-height: 1.7; max-width: 70ch; }
      .hero-actions{ margin-top: 18px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
      .section{ padding: 28px 0 38px; }
      .article{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: var(--surface-2);
        box-shadow: var(--shadow);
        overflow: hidden;
      }
      .article .body{ padding: 18px 18px; }
      .meta{ color: var(--muted); font-size: 13px; display:flex; gap: 12px; flex-wrap: wrap; align-items:center; margin-bottom: 12px; }
      .meta span{ display:inline-flex; gap: 8px; align-items:center; }
      .content{ max-width: 80ch; line-height: 1.85; font-size: 16px; }
      .suggest-wrap{ padding-top: 0; }
      .suggest-grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
      .suggest-box{
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.92);
        box-shadow: var(--shadow);
        overflow: hidden;
      }
      .suggest-head{ padding:18px 18px 10px; }
      .suggest-head h2{ margin:0; font-size:24px; letter-spacing:-0.03em; color:var(--text); }
      .suggest-head p{ margin:8px 0 0; color:var(--muted); line-height:1.7; font-size:14px; }
      .suggest-list{ display:grid; gap:12px; padding:0 18px 18px; }
      .suggest-card{
        display:grid;
        grid-template-columns:96px minmax(0,1fr);
        gap:14px;
        padding:14px;
        border:1px solid var(--border);
        border-radius:16px;
        background:#fff;
      }
      .suggest-card img{ width:96px; height:96px; border-radius:14px; object-fit:cover; background:#e5eefc; }
      .suggest-card h3{ margin:0; font-size:16px; line-height:1.4; }
      .suggest-card p{ margin:8px 0 0; color:var(--muted); font-size:13px; line-height:1.65; }
      .suggest-meta{ display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; color:var(--muted); font-size:12px; }
      .suggest-link{ display:inline-flex; align-items:center; gap:8px; margin-top:10px; color:var(--brand); font-weight:700; font-size:13px; }
      .site-footer{ margin-top:34px; position:relative; overflow:hidden; color: rgba(255,255,255,0.95); background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%); }
      .site-footer::before{ content:""; position:absolute; inset:0; background: var(--img-1) center/cover no-repeat; filter: saturate(0.7) contrast(1.10); opacity:0.15; z-index:0; pointer-events:none; }
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
      @media (max-width:1024px){ .footer-cols{ grid-template-columns: repeat(2,minmax(0,1fr)); } }
      @media (max-width:680px){
        .nav-toggle{ display:inline-flex; }
        .navlinks{ display:none; position:absolute; left:16px; right:16px; top:calc(100% + 10px); padding:10px; border-radius:var(--radius); border:1px solid var(--border); background: rgba(255,255,255,0.96); box-shadow:0 26px 80px rgba(17,24,39,0.18); flex-direction: column; align-items:stretch; gap:6px; }
        .navlinks a{ padding: 12px 12px; }
        .navlinks.is-open{ display:flex; }
        .pill{ display:none; }
        .suggest-grid{ grid-template-columns:1fr; }
        .suggest-card{ grid-template-columns:1fr; }
        .suggest-card img{ width:100%; height:200px; }
        main .gallery-grid.gallery-grid,
        main .customer-gallery.customer-gallery,
        main .gallery.gallery,
        .content .gallery-grid.gallery-grid,
        .content .customer-gallery.customer-gallery,
        .content .gallery.gallery,
        .article .gallery-grid.gallery-grid,
        .article .customer-gallery.customer-gallery,
        .article .gallery.gallery{
          display:grid;
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
          gap: 12px;
        }
        main .gallery-grid.gallery-grid > *,
        main .customer-gallery.customer-gallery > *,
        main .gallery.gallery > *,
        .content .gallery-grid.gallery-grid > *,
        .content .customer-gallery.customer-gallery > *,
        .content .gallery.gallery > *,
        .article .gallery-grid.gallery-grid > *,
        .article .customer-gallery.customer-gallery > *,
        .article .gallery.gallery > *{
          min-width: 0;
        }
        .footer-cols{ grid-template-columns: 1fr; }
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <?php
      if ($post && isset($post['id'])) {
        $adminLinks = [
          ['label' => 'Sửa bài viết', 'href' => '/admin/content_post_edit.php?id=' . (int) $post['id'], 'icon' => 'fa-solid fa-pen-to-square'],
        ];
        if ($templateMode && !empty($post['slug'])) {
          $adminLinks[] = ['label' => 'Edit bằng template', 'href' => '/' . rawurlencode((string) $post['slug']) . '?edit_template=1', 'icon' => 'fa-solid fa-pen-ruler'];
        }
        $adminLinks[] = ['label' => 'Admin', 'href' => '/admin/dashboard.php', 'icon' => 'fa-solid fa-shield-halved'];
        front_admin_render_edit_bar($adminLinks);
      }
    ?>
    <?php if ($templateMode && $post): ?>
      <main>
        <?php echo trim($content) !== '' ? $content : '<section class="section"><div class="container"><div class="article"><div class="body"><p style="color:var(--muted)">Chưa có nội dung template.</p></div></div></div></section>'; ?>
      </main>
      <?php if ($frontEditorPostKey !== '') { front_editor_render($frontEditorPostKey); } ?>
    <?php else: ?>
      <section class="hero">
        <div class="hero-bg" aria-hidden="true"></div>
        <div class="container hero-inner">
          <div class="hero-copy">
            <div class="crumbs">
              <a href="<?php echo htmlspecialchars($homePath, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($homeLabel, ENT_QUOTES, 'UTF-8'); ?></a>
              <span>•</span>
              <a href="<?php echo htmlspecialchars($listingPath, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($listingLabel, ENT_QUOTES, 'UTF-8'); ?></a>
              <span>•</span>
              <span><?php echo $safeTitle; ?></span>
            </div>
            <h1><?php echo $safeTitle; ?></h1>
            <?php if ($excerpt !== ''): ?>
              <p><?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php elseif ($updatedAt !== ''): ?>
              <p><?php echo htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <div class="hero-actions">
              <a class="btn btn-ghost" href="<?php echo htmlspecialchars($listingPath, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?php echo htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8'); ?></a>
              <a class="btn btn-primary" href="<?php echo htmlspecialchars($contactPath, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> <?php echo htmlspecialchars($contactLabel, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
          </div>
        </div>
      </section>
      <section class="section">
        <div class="container">
          <div class="article">
            <div class="body">
              <div class="meta">
                <span><i class="fa-solid fa-tag" aria-hidden="true"></i><?php echo htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($updatedAt !== ''): ?><span><i class="fa-regular fa-calendar" aria-hidden="true"></i><?php echo htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              </div>
              <div class="content">
                <?php if ($post): ?>
                  <?php echo trim($content) !== '' ? $content : '<p style="color:var(--muted)">' . htmlspecialchars($emptyContentText, ENT_QUOTES, 'UTF-8') . '</p>'; ?>
                <?php else: ?>
                  <p style="color:var(--muted)"><?php echo htmlspecialchars($missingPostText, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </section>
      <section class="section suggest-wrap">
        <div class="container">
          <div class="suggest-grid">
            <div class="suggest-box">
              <div class="suggest-head">
                <h2><?php echo $isEnglishPost ? 'Recommended Facilities' : 'Cơ sở y tế đề xuất'; ?></h2>
                <p><?php echo $isEnglishPost ? 'Explore highly rated facilities related to this topic.' : 'Khám phá các cơ sở y tế được đánh giá cao để tiếp tục tham khảo.'; ?></p>
              </div>
              <div class="suggest-list">
                <?php foreach ($suggestedFacilities as $item): ?>
                  <article class="suggest-card">
                    <img src="<?php echo htmlspecialchars((string) ($item['image_url'] ?? $item['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                      <h3><?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                      <div class="suggest-meta">
                        <span><?php echo htmlspecialchars((string) ($item['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars((string) ($item['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars((string) ($item['rating'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>/5</span>
                      </div>
                      <p><?php echo htmlspecialchars((string) ($item['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                      <a class="suggest-link" href="/co-so-y-te-chi-tiet.php?slug=<?php echo rawurlencode((string) ($item['slug'] ?? '')); ?>"><?php echo $isEnglishPost ? 'View facility' : 'Xem cơ sở y tế'; ?></a>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="suggest-box">
              <div class="suggest-head">
                <h2><?php echo $isEnglishPost ? 'Related Reviews' : 'Review liên quan'; ?></h2>
                <p><?php echo $isEnglishPost ? 'Real treatment stories and before-after results from patients.' : 'Câu chuyện điều trị thực tế và kết quả before/after để bạn tham khảo thêm.'; ?></p>
              </div>
              <div class="suggest-list">
                <?php foreach ($suggestedReviews as $item): ?>
                  <article class="suggest-card">
                    <img src="<?php echo htmlspecialchars((string) ($item['before_image_url'] ?? $item['before'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                      <h3><?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                      <div class="suggest-meta">
                        <span><?php echo htmlspecialchars((string) ($item['facility_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars((string) ($item['service_text'] ?? $item['service'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars((string) ($item['rating'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>/5</span>
                      </div>
                      <p><?php echo htmlspecialchars((string) ($item['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                      <a class="suggest-link" href="/review-chi-tiet.php?slug=<?php echo rawurlencode((string) ($item['slug'] ?? '')); ?>"><?php echo $isEnglishPost ? 'View review' : 'Xem review'; ?></a>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>
    <style>
      @media (max-width:680px){
        body main .gallery-grid,
        body main .customer-gallery,
        body main .gallery,
        body .article .body .gallery-grid,
        body .article .body .customer-gallery,
        body .article .body .gallery,
        body .content .gallery-grid,
        body .content .customer-gallery,
        body .content .gallery{
          display:grid !important;
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
          gap: 12px !important;
        }
        body main .gallery-grid > *,
        body main .customer-gallery > *,
        body main .gallery > *,
        body .article .body .gallery-grid > *,
        body .article .body .customer-gallery > *,
        body .article .body .gallery > *,
        body .content .gallery-grid > *,
        body .content .customer-gallery > *,
        body .content .gallery > *{
          min-width: 0 !important;
          width: auto !important;
        }
      }
    </style>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
  </body>
</html>
