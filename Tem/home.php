<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../medical_directory.php';
require_once __DIR__ . '/../toplist_directory.php';

$locale = site_page_locale();
$isEnglish = $locale === 'en';
$facilitiesPath = medical_public_facility_path();
$doctorsPath = '/bac-si.php';
$reviewsPath = '/review.php';
$toplistsPath = medical_public_toplist_path();
$categoriesPath = '/danh-muc-y-te.php';

if (!function_exists('medical_home_image')) {
    function medical_home_image(array $row): string
    {
        $image = trim((string) ($row['image_url'] ?? $row['featured_image_url'] ?? ''));
        if ($image !== '') return $image;
        $gallery = json_decode((string) ($row['gallery_json'] ?? ''), true);
        $galleryUrls = is_array($gallery) ? medical_directory_gallery_urls($gallery) : [];
        if ($galleryUrls !== []) return (string) $galleryUrls[0];
        return '';
    }
}

if (!function_exists('medical_home_category_icon')) {
    function medical_home_category_icon(string $category): string
    {
        $value = mb_strtolower($category, 'UTF-8');
        if (str_contains($value, 'nha') || str_contains($value, 'răng')) return 'ph ph-tooth';
        if (str_contains($value, 'mắt')) return 'ph ph-eye';
        if (str_contains($value, 'da liễu')) return 'ph ph-drop';
        if (str_contains($value, 'thẩm mỹ') || str_contains($value, 'spa')) return 'ph ph-sparkle';
        if (str_contains($value, 'sản')) return 'ph ph-baby';
        return 'ph ph-hospital';
    }
}

if (!function_exists('medical_home_date')) {
    function medical_home_date(?string $value): string
    {
        $time = strtotime((string) $value);
        return $time ? date('d/m/Y', $time) : '';
    }
}

$facilities = [];
$categories = [];
$toplists = [];
$doctors = [];
$reviews = [];
$stats = ['facilities' => 0, 'reviews' => 0, 'doctors' => 0, 'toplists' => 0];

try {
    $pdo = db();
    medical_directory_ensure_tables($pdo);
    toplist_directory_ensure_tables($pdo);

    $stats['facilities'] = (int) $pdo->query("SELECT COUNT(*) FROM medical_facilities WHERE status = 'published'")->fetchColumn();
    $stats['reviews'] = (int) $pdo->query("SELECT COUNT(*) FROM medical_reviews WHERE status = 'published'")->fetchColumn();
    $stats['doctors'] = (int) $pdo->query("SELECT COUNT(*) FROM medical_doctors WHERE status = 'published'")->fetchColumn();
    $stats['toplists'] = (int) $pdo->query("SELECT COUNT(*) FROM medical_toplists WHERE status = 'published'")->fetchColumn();

    $facilities = $pdo->query(
        "SELECT id, slug, name, category, city, subtitle, rating, reviews_count, image_url, gallery_json
         FROM medical_facilities
         WHERE status = 'published'
         ORDER BY rating DESC, reviews_count DESC, updated_at DESC, id DESC
         LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);

    $categories = $pdo->query(
        "SELECT category, COUNT(*) AS facility_count
         FROM medical_facilities
         WHERE status = 'published' AND COALESCE(TRIM(category), '') <> ''
         GROUP BY category
         ORDER BY facility_count DESC, category ASC
         LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);

    $toplists = $pdo->query(
        "SELECT t.id, t.slug, t.title, t.excerpt, t.featured_image_url, t.updated_at, COUNT(tf.id) AS facility_count
         FROM medical_toplists t
         LEFT JOIN medical_toplist_facilities tf ON tf.toplist_id = t.id
         WHERE t.status = 'published'
         GROUP BY t.id, t.slug, t.title, t.excerpt, t.featured_image_url, t.updated_at
         ORDER BY t.updated_at DESC, t.id DESC
         LIMIT 3"
    )->fetchAll(PDO::FETCH_ASSOC);

    $doctors = $pdo->query(
        "SELECT id, slug, name, title_text, specialty_text, city, image_url, rating, reviews_count
         FROM medical_doctors
         WHERE status = 'published'
         ORDER BY rating DESC, reviews_count DESC, updated_at DESC, id DESC
         LIMIT 4"
    )->fetchAll(PDO::FETCH_ASSOC);

    $reviews = $pdo->query(
        "SELECT slug, facility_slug, facility_name, title, author_text, rating, excerpt, service_text, source_text, review_date_text, updated_at
         FROM medical_reviews
         WHERE status = 'published'
         ORDER BY updated_at DESC, id DESC
         LIMIT 4"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Render a usable home page even if the medical data has not been initialized yet.
}

$labels = $isEnglish ? [
    'eyebrow' => 'Verified medical discovery platform',
    'heroTitle' => 'Find medical information with more confidence',
    'heroAccent' => 'more confidence',
    'heroCopy' => 'Search healthcare facilities, doctors and curated Toplists from real data on MedReview.',
    'search' => 'Search',
    'placeholder' => 'Search facilities, doctors, Toplists...',
    'popular' => 'Popular:',
    'categories' => 'Explore by specialty',
    'categoriesCopy' => 'Healthcare groups currently available on MedReview.',
    'facilities' => 'Highly rated healthcare facilities',
    'facilitiesCopy' => 'Ranked by ratings and real review volume.',
    'toplists' => 'Featured Toplists',
    'toplistsCopy' => 'Curated lists to help compare healthcare facilities quickly.',
    'doctors' => 'Doctors to explore',
    'reviews' => 'Latest reviews',
    'viewAll' => 'View all',
    'reviewsCount' => 'reviews',
    'facilitiesCount' => 'facilities',
    'updated' => 'Updated',
    'noRating' => 'No rating yet',
    'aboutData' => 'MedReview in numbers',
    'facilityStat' => 'healthcare facilities',
    'reviewStat' => 'published reviews',
    'doctorStat' => 'doctors',
    'toplistStat' => 'Toplists',
] : [
    'eyebrow' => 'Nền tảng khám phá y tế đáng tin cậy',
    'heroTitle' => 'Cộng đồng đánh giá Y tế đáng tin cậy tại Việt Nam',
    'heroAccent' => 'đáng tin cậy',
    'heroCopy' => 'Nơi bạn tìm thấy review thật từ người thực tế về bác sĩ, phòng khám và bệnh viện trên toàn quốc.',
    'search' => 'Tìm kiếm',
    'placeholder' => 'Tìm cơ sở y tế, bác sĩ, Toplist...',
    'popular' => 'Tìm nhanh:',
    'categories' => 'Khám phá theo chuyên khoa',
    'categoriesCopy' => 'Các nhóm cơ sở đang có dữ liệu trên MedReview.',
    'facilities' => 'Cơ sở y tế được đánh giá cao',
    'facilitiesCopy' => 'Xếp theo điểm đánh giá và số lượt review thực tế.',
    'toplists' => 'Toplist nổi bật',
    'toplistsCopy' => 'Danh sách chọn lọc giúp bạn đối chiếu các cơ sở nhanh hơn.',
    'doctors' => 'Bác sĩ đáng tham khảo',
    'reviews' => 'Đánh giá mới nhất',
    'viewAll' => 'Xem tất cả',
    'reviewsCount' => 'đánh giá',
    'facilitiesCount' => 'cơ sở',
    'updated' => 'Cập nhật',
    'noRating' => 'Chưa có đánh giá',
    'aboutData' => 'MedReview qua dữ liệu thực',
    'facilityStat' => 'cơ sở y tế',
    'reviewStat' => 'review đã xuất bản',
    'doctorStat' => 'bác sĩ',
    'toplistStat' => 'Toplist',
];

$popularTerms = array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['category'] ?? ''), array_slice($categories, 0, 4))));
if ($popularTerms === []) $popularTerms = $isEnglish ? ['Dental', 'Doctors', 'Healthcare'] : ['Nha khoa', 'Bác sĩ', 'Cơ sở y tế'];
?>
<style>
  .med-home{padding:26px 0 72px;background:radial-gradient(760px 420px at 4% 0%,rgba(125,211,252,.16),transparent 62%),radial-gradient(820px 480px at 100% 18%,rgba(96,165,250,.12),transparent 66%),#f8fbff;color:#0f172a}
  .med-home *{box-sizing:border-box}
  .med-home a{text-decoration:none}
  .med-home .home-section{position:relative;margin-top:42px}
  .med-home .home-section:first-child{margin-top:0}
  .med-home .section-head{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:18px}
  .med-home .section-kicker{display:block;margin:0 0 7px;color:#2563eb;font-size:11px;font-weight:800;letter-spacing:.09em;text-transform:uppercase}
  .med-home .section-head h2{margin:0;color:#10203d;font-size:clamp(22px,2.1vw,30px);line-height:1.16;letter-spacing:-.045em}
  .med-home .section-head p{max-width:560px;margin:8px 0 0;color:#64748b;font-size:14px;line-height:1.65}
  .med-home .section-link{display:inline-flex;align-items:center;gap:7px;color:#2563eb;font-size:13px;font-weight:800;white-space:nowrap}
  .med-home .section-link i{font-size:16px}
  .med-home .hero-wrap{position:relative;min-height:560px;overflow:hidden;border:1px solid rgba(191,219,254,.8);border-radius:32px;background:url('/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg') center/cover no-repeat;box-shadow:0 24px 70px rgba(30,64,175,.13)}
  .med-home .hero-wrap::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,rgba(248,251,255,.98) 0%,rgba(248,251,255,.96) 46%,rgba(248,251,255,.77) 66%,rgba(248,251,255,.20) 100%)}
  .med-home .hero-wrap.medical-search-active{overflow:visible;z-index:60}
  .med-home .hero-inner{position:relative;z-index:1;display:flex;min-height:560px;align-items:center;padding:60px clamp(28px,5vw,70px)}
  .med-home .hero-copy{width:min(100%,720px);padding:0!important;border:0!important;background:transparent!important;box-shadow:none!important;color:#10203d!important;backdrop-filter:none!important}
  .med-home .hero-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:8px 11px;border:1px solid rgba(16,185,129,.18);border-radius:999px;background:rgba(236,253,245,.9);color:#047857;font-size:11px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
  .med-home .hero-eyebrow i{font-size:16px}
  .med-home .hero-copy h1{max-width:720px;margin:18px 0 0;color:#101c37;font-size:clamp(40px,4.2vw,58px);line-height:1.13;letter-spacing:-.065em;font-weight:800}
  .med-home .hero-copy h1 span{color:#2563eb}
  .med-home .hero-copy p{max-width:620px;margin:20px 0 0;color:#52657f!important;font-size:16px;line-height:1.7}
  .med-home .hero-search{max-width:720px;margin-top:28px}
  .med-home .hero-search-shell{position:relative;z-index:30}
  .med-home .hero-search-shell .medical-search-results{top:calc(100% + 10px);width:100%;max-height:min(510px,calc(100vh - 120px))}
  .med-home .hero-search-row{display:grid;grid-template-columns:1fr 150px;gap:6px;padding:5px;border:1px solid rgba(191,219,254,.85);border-radius:17px;background:#fff;box-shadow:0 16px 34px rgba(15,23,42,.09)}
  .med-home .hero-search-field{display:flex;align-items:center;gap:10px;min-width:0;padding:0 14px;color:#94a3b8}
  .med-home .hero-search-field i{font-size:21px;color:#2563eb}
  .med-home .hero-search-field input{width:100%;min-width:0;height:50px;border:0;outline:0;background:transparent;color:#10203d;font:inherit;font-size:14px;font-weight:600}
  .med-home .hero-search-btn{height:50px;border:0;border-radius:13px;background:linear-gradient(145deg,#3b82f6,#1d4ed8);box-shadow:0 12px 22px rgba(37,99,235,.24);color:#fff;font:inherit;font-size:14px;font-weight:800;cursor:pointer}
  .med-home .hero-terms{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:15px;color:#64748b;font-size:12px;font-weight:700}
  .med-home .hero-terms a{padding:6px 10px;border:1px solid rgba(191,219,254,.78);border-radius:999px;background:rgba(255,255,255,.72);color:#2563eb;font-size:11px;font-weight:800}
  .med-home .category-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}
  .med-home .category-card{display:flex;align-items:center;gap:13px;min-height:94px;padding:16px;border:1px solid #dce8f8;border-radius:20px;background:rgba(255,255,255,.94);box-shadow:0 12px 28px rgba(15,23,42,.045);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
  .med-home .category-card:hover{transform:translateY(-3px);border-color:#93c5fd;box-shadow:0 18px 34px rgba(37,99,235,.11)}
  .med-home .category-icon{display:flex;flex:0 0 46px;align-items:center;justify-content:center;width:46px;height:46px;border-radius:15px;background:#eff6ff;color:#2563eb;font-size:24px}
  .med-home .category-card strong{display:block;color:#152441;font-size:14px;line-height:1.35}
  .med-home .category-card small{display:block;margin-top:4px;color:#71809a;font-size:11px;font-weight:700}
  .med-home .facility-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
  .med-home .facility-card{overflow:hidden;border:1px solid #dce8f8;border-radius:23px;background:#fff;box-shadow:0 14px 36px rgba(15,23,42,.06);transition:transform .18s ease,box-shadow .18s ease}
  .med-home .facility-card:hover{transform:translateY(-4px);box-shadow:0 22px 46px rgba(37,99,235,.13)}
  .med-home .facility-media{position:relative;height:168px;background:linear-gradient(145deg,#e0edff,#f8fbff)}
  .med-home .facility-media img{width:100%;height:100%;object-fit:cover}
  .med-home .facility-media-placeholder{display:flex;align-items:center;justify-content:center;height:100%;color:#2563eb;font-size:48px}
  .med-home .facility-category{position:absolute;top:12px;left:12px;padding:6px 9px;border-radius:999px;background:rgba(255,255,255,.93);color:#1d4ed8;font-size:10px;font-weight:800}
  .med-home .facility-body{padding:16px}
  .med-home .facility-body h3{overflow:hidden;margin:0;color:#152441;font-size:17px;line-height:1.35;letter-spacing:-.035em;text-overflow:ellipsis;white-space:nowrap}
  .med-home .facility-subtitle{display:-webkit-box;min-height:38px;margin:7px 0 0;overflow:hidden;color:#71809a;font-size:12px;line-height:1.55;-webkit-box-orient:vertical;-webkit-line-clamp:2}
  .med-home .facility-info{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:13px;color:#64748b;font-size:12px;font-weight:700}
  .med-home .facility-rating{display:inline-flex;align-items:center;gap:5px;color:#f59e0b}
  .med-home .facility-rating strong{color:#152441;font-size:12px}
  .med-home .facility-city{display:inline-flex;max-width:46%;overflow:hidden;align-items:center;gap:4px;text-overflow:ellipsis;white-space:nowrap}
  .med-home .toplist-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
  .med-home .toplist-card{position:relative;display:flex;min-height:222px;flex-direction:column;justify-content:space-between;overflow:hidden;padding:20px;border:1px solid #d9e7fa;border-radius:23px;background:linear-gradient(145deg,#fff 0%,#f2f7ff 100%);box-shadow:0 14px 36px rgba(15,23,42,.055);transition:transform .18s ease,box-shadow .18s ease}
  .med-home .toplist-card:hover{transform:translateY(-4px);box-shadow:0 24px 48px rgba(37,99,235,.13)}
  .med-home .toplist-rank{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:14px;background:#2563eb;color:#fff;font-size:15px;font-weight:800;box-shadow:0 10px 18px rgba(37,99,235,.22)}
  .med-home .toplist-card h3{display:-webkit-box;margin:18px 0 0;overflow:hidden;color:#152441;font-size:19px;line-height:1.35;letter-spacing:-.04em;-webkit-box-orient:vertical;-webkit-line-clamp:2}
  .med-home .toplist-card p{display:-webkit-box;margin:9px 0 0;overflow:hidden;color:#64748b;font-size:12px;line-height:1.6;-webkit-box-orient:vertical;-webkit-line-clamp:2}
  .med-home .toplist-meta{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:18px;color:#64748b;font-size:12px;font-weight:750}
  .med-home .toplist-meta span{display:inline-flex;align-items:center;gap:5px}
  .med-home .data-band{display:grid;grid-template-columns:1.1fr repeat(4,minmax(0,1fr));gap:0;overflow:hidden;border:1px solid rgba(147,197,253,.8);border-radius:24px;background:linear-gradient(100deg,#123b86,#1d4ed8 58%,#3b82f6);box-shadow:0 22px 44px rgba(29,78,216,.18);color:#fff}
  .med-home .data-band-copy,.med-home .data-stat{padding:25px 22px}
  .med-home .data-band-copy{background:rgba(15,23,42,.12)}
  .med-home .data-band-copy h2{margin:0;color:#fff;font-size:21px;letter-spacing:-.04em}
  .med-home .data-band-copy p{margin:8px 0 0;color:rgba(255,255,255,.75);font-size:12px;line-height:1.6}
  .med-home .data-stat{border-left:1px solid rgba(255,255,255,.14)}
  .med-home .data-stat strong{display:block;font-size:28px;line-height:1;letter-spacing:-.05em}
  .med-home .data-stat span{display:block;margin-top:8px;color:rgba(255,255,255,.74);font-size:11px;font-weight:700}
  .med-home .two-column{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  .med-home .panel{padding:22px;border:1px solid #dce8f8;border-radius:24px;background:#fff;box-shadow:0 14px 34px rgba(15,23,42,.05)}
  .med-home .panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
  .med-home .panel-head h2{margin:0;color:#152441;font-size:20px;letter-spacing:-.04em}
  .med-home .doctor-list,.med-home .review-list{display:grid;gap:4px}
  .med-home .doctor-item,.med-home .review-item{display:flex;width:100%;min-width:0;align-items:center;gap:12px;padding:11px 4px;border-bottom:1px solid #edf2f8}
  .med-home .doctor-item:last-child,.med-home .review-item:last-child{border-bottom:0}
  .med-home .doctor-avatar{display:flex;flex:0 0 46px;align-items:center;justify-content:center;width:46px;height:46px;overflow:hidden;border-radius:15px;background:#eff6ff;color:#2563eb;font-size:21px}
  .med-home .doctor-avatar img{width:100%;height:100%;object-fit:cover}
  .med-home .doctor-copy,.med-home .review-copy{min-width:0;flex:1}
  .med-home .doctor-copy strong,.med-home .review-copy strong{display:block;overflow:hidden;color:#152441;font-size:13px;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}
  .med-home .doctor-copy small,.med-home .review-copy small{display:block;overflow:hidden;margin-top:4px;color:#71809a;font-size:11px;font-weight:650;text-overflow:ellipsis;white-space:nowrap}
  .med-home .doctor-rating,.med-home .review-rating{color:#f59e0b;font-size:12px;font-weight:800;white-space:nowrap}
  .med-home .review-item{align-items:flex-start}
  .med-home .review-quote{display:flex;flex:0 0 32px;align-items:center;justify-content:center;width:32px;height:32px;border-radius:11px;background:#eff6ff;color:#2563eb;font-size:17px}
  .med-home .empty-state{padding:20px;border:1px dashed #bfdbfe;border-radius:16px;color:#71809a;font-size:13px;text-align:center}
  @media (max-width:1080px){.med-home .hero-inner{grid-template-columns:1fr .68fr;padding:44px 38px}.med-home .facility-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.med-home .data-band{grid-template-columns:1fr 1fr}.med-home .data-band-copy{grid-column:span 2}.med-home .data-stat:nth-child(4){border-left:0}}
  /* Tablet: keep the information density of desktop, but make the first
     screen and card rhythm fit naturally in portrait and landscape iPad. */
  @media (min-width:821px) and (max-width:1100px){
    .med-home{padding:22px 0 58px}
    .med-home .home-section{margin-top:36px}
    .med-home .hero-wrap,.med-home .hero-inner{min-height:520px}
    .med-home .hero-inner{padding:46px 42px}
    .med-home .hero-copy{width:min(100%,650px)}
    .med-home .hero-copy h1{font-size:clamp(38px,4.4vw,48px)}
    .med-home .hero-copy p{max-width:570px;font-size:15px}
    .med-home .hero-search{max-width:650px;margin-top:24px}
    .med-home .section-head{margin-bottom:15px}
    .med-home .section-head p{font-size:13px}
    .med-home .category-card{min-height:88px;padding:14px;gap:11px}
    .med-home .category-icon{flex-basis:42px;width:42px;height:42px;border-radius:13px;font-size:22px}
    .med-home .facility-media{height:154px}
    .med-home .facility-body{padding:14px}
    .med-home .toplist-card{min-height:208px;padding:17px}
    .med-home .data-band-copy,.med-home .data-stat{padding:22px 20px}
    .med-home .panel{padding:20px}
  }
  @media (max-width:820px){.med-home{padding:18px 0 48px}.med-home .home-section{margin-top:30px}.med-home .hero-wrap{min-height:auto;border-radius:25px}.med-home .hero-inner{grid-template-columns:1fr;min-height:auto;padding:36px 26px}.med-home .hero-media{display:none}.med-home .hero-copy h1{font-size:clamp(34px,8.5vw,48px)}.med-home .category-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.med-home .facility-grid,.med-home .toplist-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.med-home .two-column{grid-template-columns:1fr}.med-home .section-head{align-items:flex-start}.med-home .data-band{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (prefers-reduced-motion:no-preference){
    @keyframes med-home-enter{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
    @keyframes med-home-hero-enter{from{opacity:0;transform:translateY(13px)}to{opacity:1;transform:translateY(0)}}
    .med-home.is-motion-ready .hero-copy > *{opacity:0;animation:med-home-hero-enter .62s cubic-bezier(.22,1,.36,1) forwards}
    .med-home.is-motion-ready .hero-copy > :nth-child(1){animation-delay:40ms}
    .med-home.is-motion-ready .hero-copy > :nth-child(2){animation-delay:120ms}
    .med-home.is-motion-ready .hero-copy > :nth-child(3){animation-delay:205ms}
    .med-home.is-motion-ready .hero-copy > :nth-child(4){animation-delay:290ms}
    .med-home.is-motion-ready .home-reveal{opacity:0;transform:translateY(18px)}
    .med-home.is-motion-ready .home-reveal.is-visible{animation:med-home-enter .58s cubic-bezier(.22,1,.36,1) forwards}
    .med-home .hero-search-row{transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease}
    .med-home .hero-search-row:focus-within{border-color:#79aaf8;box-shadow:0 18px 38px rgba(37,99,235,.16);transform:translateY(-1px)}
    .med-home .hero-terms a{transition:transform .16s ease,background .16s ease,border-color .16s ease}
    .med-home .hero-terms a:hover{transform:translateY(-1px);border-color:#93c5fd;background:#fff}
  }
  @media (max-width:540px){.med-home .hero-inner{padding:30px 18px}.med-home .hero-search-row{grid-template-columns:1fr}.med-home .hero-search-btn{height:48px}.med-home .hero-metrics{gap:12px}.med-home .hero-metric{padding-right:12px}.med-home .hero-metric strong{font-size:18px}.med-home .facility-grid,.med-home .toplist-grid{grid-template-columns:1fr}.med-home .category-grid{gap:9px}.med-home .category-card{min-height:82px;padding:12px;gap:10px}.med-home .category-icon{flex-basis:40px;width:40px;height:40px;font-size:21px}.med-home .data-band-copy,.med-home .data-stat{padding:19px 16px}.med-home .panel{min-width:0;padding:18px}.med-home .section-link{font-size:12px}.med-home .section-head h2{font-size:23px}}
</style>

<main class="med-home site-typo">
  <section class="home-section" id="hero">
    <div class="container">
      <div class="hero-wrap">
        <div class="hero-inner">
          <div class="hero-copy">
            <span class="hero-eyebrow"><i class="ph-fill ph-seal-check"></i><?= htmlspecialchars($labels['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($isEnglish): ?>
              <h1>Discover verified<br>healthcare <span>with confidence</span></h1>
            <?php else: ?>
              <h1>Cộng đồng đánh giá<br>Y tế <span>đáng tin cậy</span><br>tại Việt Nam</h1>
            <?php endif; ?>
            <p><?= htmlspecialchars($labels['heroCopy'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="hero-search">
              <div class="medical-search-shell hero-search-shell" data-medical-search>
                <form action="<?= htmlspecialchars($facilitiesPath, ENT_QUOTES, 'UTF-8') ?>" method="get" class="hero-search-row">
                  <label class="hero-search-field"><i class="ph ph-magnifying-glass"></i><input name="q" data-medical-search-input autocomplete="off" placeholder="<?= htmlspecialchars($labels['placeholder'], ENT_QUOTES, 'UTF-8') ?>"></label>
                  <button class="hero-search-btn" type="submit"><?= htmlspecialchars($labels['search'], ENT_QUOTES, 'UTF-8') ?></button>
                </form>
                <div class="medical-search-results" data-medical-search-results hidden></div>
              </div>
              <div class="hero-terms"><span><?= htmlspecialchars($labels['popular'], ENT_QUOTES, 'UTF-8') ?></span><?php foreach ($popularTerms as $term): ?><a href="<?= htmlspecialchars($facilitiesPath . '?q=' . rawurlencode((string) $term), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($categories !== []): ?>
    <section class="home-section"><div class="container"><div class="section-head"><div><span class="section-kicker">Chuyên khoa</span><h2><?= htmlspecialchars($labels['categories'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($labels['categoriesCopy'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="section-link" href="<?= htmlspecialchars($categoriesPath, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labels['viewAll'], ENT_QUOTES, 'UTF-8') ?><i class="ph ph-arrow-right"></i></a></div><div class="category-grid"><?php foreach ($categories as $category): ?><a class="category-card" href="<?= htmlspecialchars($facilitiesPath, ENT_QUOTES, 'UTF-8') ?>"><span class="category-icon"><i class="<?= htmlspecialchars(medical_home_category_icon((string) $category['category']), ENT_QUOTES, 'UTF-8') ?>"></i></span><span><strong><?= htmlspecialchars((string) $category['category'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= number_format((int) $category['facility_count'], 0, ',', '.') ?> <?= htmlspecialchars($labels['facilitiesCount'], ENT_QUOTES, 'UTF-8') ?></small></span></a><?php endforeach; ?></div></div></section>
  <?php endif; ?>

  <?php if ($facilities !== []): ?>
    <section class="home-section"><div class="container"><div class="section-head"><div><span class="section-kicker">Được quan tâm</span><h2><?= htmlspecialchars($labels['facilities'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($labels['facilitiesCopy'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="section-link" href="<?= htmlspecialchars($facilitiesPath, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labels['viewAll'], ENT_QUOTES, 'UTF-8') ?><i class="ph ph-arrow-right"></i></a></div><div class="facility-grid"><?php foreach ($facilities as $facility): $image = medical_home_image($facility); ?><a class="facility-card" href="/co-so-y-te-chi-tiet.php?slug=<?= rawurlencode((string) $facility['slug']) ?>"><div class="facility-media"><?php if ($image !== ''): ?><img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $facility['name'], ENT_QUOTES, 'UTF-8') ?>"><?php else: ?><div class="facility-media-placeholder"><i class="ph ph-hospital"></i></div><?php endif; ?><span class="facility-category"><?= htmlspecialchars((string) $facility['category'], ENT_QUOTES, 'UTF-8') ?></span></div><div class="facility-body"><h3><?= htmlspecialchars((string) $facility['name'], ENT_QUOTES, 'UTF-8') ?></h3><p class="facility-subtitle"><?= htmlspecialchars((string) ($facility['subtitle'] ?: $facility['category']), ENT_QUOTES, 'UTF-8') ?></p><div class="facility-info"><span class="facility-rating"><i class="ph-fill ph-star"></i><strong><?= (float) $facility['rating'] > 0 ? number_format((float) $facility['rating'], 1) : htmlspecialchars($labels['noRating'], ENT_QUOTES, 'UTF-8') ?></strong><?php if ((int) $facility['reviews_count'] > 0): ?><small>(<?= number_format((int) $facility['reviews_count'], 0, ',', '.') ?>)</small><?php endif; ?></span><span class="facility-city"><i class="ph ph-map-pin"></i><?= htmlspecialchars((string) $facility['city'], ENT_QUOTES, 'UTF-8') ?></span></div></div></a><?php endforeach; ?></div></div></section>
  <?php endif; ?>

  <?php if ($toplists !== []): ?>
    <section class="home-section"><div class="container"><div class="section-head"><div><span class="section-kicker">Danh sách chọn lọc</span><h2><?= htmlspecialchars($labels['toplists'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($labels['toplistsCopy'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="section-link" href="<?= htmlspecialchars($toplistsPath, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labels['viewAll'], ENT_QUOTES, 'UTF-8') ?><i class="ph ph-arrow-right"></i></a></div><div class="toplist-grid"><?php foreach ($toplists as $index => $toplist): ?><a class="toplist-card" href="/toplist-chi-tiet-mau.php?slug=<?= rawurlencode((string) $toplist['slug']) ?>"><span class="toplist-rank">0<?= $index + 1 ?></span><div><h3><?= htmlspecialchars((string) $toplist['title'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars((string) ($toplist['excerpt'] ?: $labels['toplistsCopy']), ENT_QUOTES, 'UTF-8') ?></p></div><div class="toplist-meta"><span><i class="ph ph-buildings"></i><?= number_format((int) $toplist['facility_count'], 0, ',', '.') ?> <?= htmlspecialchars($labels['facilitiesCount'], ENT_QUOTES, 'UTF-8') ?></span><span><i class="ph ph-calendar-blank"></i><?= htmlspecialchars(medical_home_date((string) $toplist['updated_at']), ENT_QUOTES, 'UTF-8') ?></span></div></a><?php endforeach; ?></div></div></section>
  <?php endif; ?>

  <section class="home-section"><div class="container"><div class="data-band"><div class="data-band-copy"><h2><?= htmlspecialchars($labels['aboutData'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= $isEnglish ? 'Counts are calculated directly from the currently published MedReview data.' : 'Các chỉ số được tính trực tiếp từ dữ liệu đang xuất bản trên MedReview.' ?></p></div><div class="data-stat"><strong><?= number_format($stats['facilities'], 0, ',', '.') ?></strong><span><?= htmlspecialchars($labels['facilityStat'], ENT_QUOTES, 'UTF-8') ?></span></div><div class="data-stat"><strong><?= number_format($stats['reviews'], 0, ',', '.') ?></strong><span><?= htmlspecialchars($labels['reviewStat'], ENT_QUOTES, 'UTF-8') ?></span></div><div class="data-stat"><strong><?= number_format($stats['doctors'], 0, ',', '.') ?></strong><span><?= htmlspecialchars($labels['doctorStat'], ENT_QUOTES, 'UTF-8') ?></span></div><div class="data-stat"><strong><?= number_format($stats['toplists'], 0, ',', '.') ?></strong><span><?= htmlspecialchars($labels['toplistStat'], ENT_QUOTES, 'UTF-8') ?></span></div></div></div></section>

  <section class="home-section"><div class="container"><div class="two-column"><section class="panel"><div class="panel-head"><h2><?= htmlspecialchars($labels['doctors'], ENT_QUOTES, 'UTF-8') ?></h2><a class="section-link" href="<?= htmlspecialchars($doctorsPath, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labels['viewAll'], ENT_QUOTES, 'UTF-8') ?></a></div><?php if ($doctors !== []): ?><div class="doctor-list"><?php foreach ($doctors as $doctor): ?><a class="doctor-item" href="/bac-si-chi-tiet.php?slug=<?= rawurlencode((string) $doctor['slug']) ?>"><span class="doctor-avatar"><?php if (trim((string) $doctor['image_url']) !== ''): ?><img src="<?= htmlspecialchars((string) $doctor['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><i class="ph ph-user-doctor"></i><?php endif; ?></span><span class="doctor-copy"><strong><?= htmlspecialchars((string) $doctor['name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(implode(' · ', array_filter([(string) ($doctor['specialty_text'] ?: $doctor['title_text']), (string) $doctor['city']])), ENT_QUOTES, 'UTF-8') ?></small></span><span class="doctor-rating"><i class="ph-fill ph-star"></i><?= number_format((float) $doctor['rating'], 1) ?></span></a><?php endforeach; ?></div><?php else: ?><div class="empty-state">Chưa có dữ liệu bác sĩ để hiển thị.</div><?php endif; ?></section><section class="panel"><div class="panel-head"><h2><?= htmlspecialchars($labels['reviews'], ENT_QUOTES, 'UTF-8') ?></h2><a class="section-link" href="<?= htmlspecialchars($reviewsPath, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labels['viewAll'], ENT_QUOTES, 'UTF-8') ?></a></div><?php if ($reviews !== []): ?><div class="review-list"><?php foreach ($reviews as $review): ?><a class="review-item" href="<?= trim((string) $review['facility_slug']) !== '' ? '/co-so-y-te-chi-tiet.php?slug=' . rawurlencode((string) $review['facility_slug']) : $reviewsPath ?>"><span class="review-quote"><i class="ph ph-quotes"></i></span><span class="review-copy"><strong><?= htmlspecialchars((string) ($review['title'] ?: $review['facility_name']), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($review['author_text'] ?: $review['facility_name']), ENT_QUOTES, 'UTF-8') ?><?= trim((string) $review['source_text']) !== '' ? ' · ' . htmlspecialchars((string) $review['source_text'], ENT_QUOTES, 'UTF-8') : '' ?></small></span><span class="review-rating"><i class="ph-fill ph-star"></i><?= number_format((float) $review['rating'], 1) ?></span></a><?php endforeach; ?></div><?php else: ?><div class="empty-state">Chưa có đánh giá để hiển thị.</div><?php endif; ?></section></div></div></section>
</main>
<script>
  (function () {
    var home = document.querySelector('.med-home');
    if (!home) return;

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reducedMotion && 'IntersectionObserver' in window) {
      home.classList.add('is-motion-ready');
      var sections = Array.prototype.slice.call(home.querySelectorAll('.home-section')).slice(1);
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        });
      }, { threshold: 0.08, rootMargin: '0px 0px -5% 0px' });
      sections.forEach(function (section) {
        section.classList.add('home-reveal');
        observer.observe(section);
      });
    }

    var input = home.querySelector('.hero-search-field input[name="q"]');
    if (!input || reducedMotion) return;
    var samples = <?= json_encode($isEnglish ? ['Trusted dental clinics in Hanoi', 'Spa services in Da Nang', 'Experienced dermatologists'] : ['Nha khoa Hà Nội uy tín', 'Spa tại Đà Nẵng', 'Bác sĩ da liễu giỏi'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var defaultPlaceholder = input.getAttribute('placeholder') || '';
    var phraseIndex = 0;
    var characterIndex = 0;
    var deleting = false;
    var timer = null;

    function clearTimer() {
      if (timer !== null) window.clearTimeout(timer);
      timer = null;
    }
    function schedule(delay) {
      clearTimer();
      timer = window.setTimeout(typeNext, delay);
    }
    function typeNext() {
      if (document.hidden || input.value || document.activeElement === input) return;
      var phrase = samples[phraseIndex] || defaultPlaceholder;
      if (!deleting) {
        characterIndex += 1;
        input.placeholder = phrase.slice(0, characterIndex);
        if (characterIndex >= phrase.length) {
          deleting = true;
          schedule(1450);
          return;
        }
        schedule(52);
        return;
      }
      characterIndex -= 1;
      input.placeholder = phrase.slice(0, Math.max(0, characterIndex));
      if (characterIndex <= 0) {
        deleting = false;
        phraseIndex = (phraseIndex + 1) % samples.length;
        schedule(330);
        return;
      }
      schedule(28);
    }
    function startTyping() {
      if (input.value || document.activeElement === input || document.hidden) return;
      schedule(650);
    }
    input.addEventListener('focus', function () {
      clearTimer();
      input.placeholder = defaultPlaceholder;
    });
    input.addEventListener('input', clearTimer);
    input.addEventListener('blur', function () {
      if (input.value) return;
      characterIndex = 0;
      deleting = false;
      startTyping();
    });
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) clearTimer();
      else if (!input.value && document.activeElement !== input) startTyping();
    });
    startTyping();
  })();
</script>
