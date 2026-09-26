<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../medical_directory.php';
require_once __DIR__ . '/../toplist_directory.php';

$locale = site_page_locale();
$isEnglish = $locale === 'en';
$facilitiesBasePath = medical_public_facility_path();
$facilitiesPath = site_localized_path($facilitiesBasePath, $locale);
$doctorsPath = site_localized_path('/bac-si.php', $locale);
$reviewsPath = site_localized_path('/review.php', $locale);
$toplistsPath = site_localized_path(medical_public_toplist_path(), $locale);
$categoriesPath = $facilitiesPath;
$homeHeroImage = '/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg';

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

if (!function_exists('medical_home_region')) {
    function medical_home_region(string $city): string
    {
        $city = mb_strtolower(trim($city), 'UTF-8');
        if (str_contains($city, 'hồ chí minh') || str_contains($city, 'ho chi minh') || str_contains($city, 'sài gòn')) return 'hcm';
        if (str_contains($city, 'hà nội') || str_contains($city, 'ha noi')) return 'hanoi';
        if (str_contains($city, 'đà nẵng') || str_contains($city, 'da nang')) return 'danang';
        return 'other';
    }
}

if (!function_exists('medical_home_tags')) {
    function medical_home_tags(array $facility): array
    {
        $tags = [];
        foreach (['tags_json', 'featured_services_json'] as $field) {
            $items = json_decode((string) ($facility[$field] ?? ''), true);
            if (!is_array($items)) continue;
            foreach ($items as $item) {
                $label = is_string($item) ? $item : (is_array($item) ? (string) ($item['name'] ?? $item['title'] ?? $item['label'] ?? '') : '');
                $label = trim($label);
                if ($label !== '' && !in_array($label, $tags, true)) $tags[] = $label;
                if (count($tags) >= 3) return $tags;
            }
        }
        if ($tags === [] && trim((string) ($facility['category'] ?? '')) !== '') $tags[] = trim((string) $facility['category']);
        return $tags;
    }
}

if (!function_exists('medical_home_description')) {
    function medical_home_description(array $facility, bool $isEnglish = false): string
    {
        $description = html_entity_decode(
            strip_tags((string) ($facility['subtitle'] ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $description = trim((string) preg_replace('/\s+/u', ' ', $description));

        if ($description === '') {
            $fallback = medical_home_tags($facility);
            $description = (string) ($fallback[0] ?? ($isEnglish
                ? 'See services and details in the full profile.'
                : 'Xem dịch vụ và thông tin chi tiết trong hồ sơ.'));
        }

        if (mb_strlen($description, 'UTF-8') > 100) {
            $description = rtrim(mb_substr($description, 0, 97, 'UTF-8')) . '…';
        }

        return $description;
    }
}

$facilities = [];
$toplists = [];
$stats = ['facilities' => 0];

try {
    $pdo = db();
    medical_directory_ensure_tables($pdo);
    toplist_directory_ensure_tables($pdo);

    $stats['facilities'] = (int) $pdo->query("SELECT COUNT(*) FROM medical_facilities WHERE status = 'published'")->fetchColumn();
    $facilities = $pdo->query(
        "SELECT id, slug, name, category, city, subtitle, verified, rating, reviews_count, address_text,
                image_url, gallery_json, tags_json, featured_services_json
         FROM medical_facilities
         WHERE status = 'published'
         ORDER BY rating DESC, reviews_count DESC, updated_at DESC, id DESC
         LIMIT 24"
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

} catch (Throwable $e) {
    // Render a usable home page even if the medical data has not been initialized yet.
}

$labels = $isEnglish ? [
    'eyebrow' => 'Verified medical discovery platform',
    'heroTitle' => 'Find medical information with more confidence',
    'heroAccent' => 'more confidence',
    'heroCopy' => 'Find real reviews and useful information about doctors, clinics and hospitals across Vietnam.',
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
    'categoryKicker' => 'Specialties',
    'featuredKicker' => 'Popular',
    'toplistKicker' => 'Curated lists',
    'noDoctors' => 'No doctor profiles are available yet.',
    'noReviews' => 'No reviews are available yet.',
    'heroPhotoCaption' => 'Helping you choose with more confidence.',
    'heroPhotoAlt' => 'A family in a bright, welcoming healthcare setting',
    'heroNoteTitle' => 'Understand before you choose',
    'heroNoteMeta' => 'Profiles · Services · Experiences',
    'heroNoteBottomTitle' => 'Your health comes first',
    'heroNoteBottomMeta' => 'Find care that feels right for you',
    'heroProof' => 'Healthcare profiles',
    'heroProofReviews' => 'Published reviews',
    'heroProofVerified' => 'Information with sources',
    'stepTitle' => 'A clearer choice, in 3 steps.',
    'stepOne' => 'Find what you need',
    'stepOneCopy' => 'Search by specialty, service or nearby location.',
    'stepTwo' => 'Compare your options',
    'stepTwoCopy' => 'Review profiles, reference prices and real experiences.',
    'stepThree' => 'Connect with confidence',
    'stepThreeCopy' => 'Contact the provider that feels right for you.',
    'communityTitle' => 'Your experience. A little more peace of mind for everyone.',
    'communityCopy' => 'Every honest review helps someone else make a more informed healthcare decision.',
    'communityCta' => 'Explore community reviews',
] : [
    'eyebrow' => 'Nền tảng khám phá y tế đáng tin cậy',
    'heroTitle' => 'Cộng đồng đánh giá Y tế đáng tin cậy tại Việt Nam',
    'heroAccent' => 'đáng tin cậy',
    'heroCopy' => 'Tìm hiểu cơ sở y tế, gặp đúng bác sĩ và tham khảo trải nghiệm thực tế — để mỗi lựa chọn sức khỏe đều có cơ sở.',
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
    'categoryKicker' => 'Chuyên khoa',
    'featuredKicker' => 'Được quan tâm',
    'toplistKicker' => 'Danh sách chọn lọc',
    'noDoctors' => 'Chưa có dữ liệu bác sĩ để hiển thị.',
    'noReviews' => 'Chưa có đánh giá để hiển thị.',
    'heroPhotoCaption' => 'Cùng bạn, từ lựa chọn đến an tâm.',
    'heroPhotoAlt' => 'Gia đình trong không gian y tế sáng, thân thiện — ảnh minh họa',
    'heroNoteTitle' => 'Hiểu rõ trước khi lựa chọn',
    'heroNoteMeta' => 'Hồ sơ · Dịch vụ · Trải nghiệm',
    'heroNoteBottomTitle' => 'Sức khỏe của bạn là ưu tiên',
    'heroNoteBottomMeta' => 'Tìm nơi chăm sóc phù hợp với mình',
    'heroProof' => 'cơ sở y tế',
    'heroProofReviews' => 'đánh giá đã xuất bản',
    'heroProofVerified' => 'Thông tin có nguồn',
    'stepTitle' => 'An tâm hơn, trong 3 bước.',
    'stepOne' => 'Tìm đúng nhu cầu',
    'stepOneCopy' => 'Tìm theo chuyên khoa, dịch vụ hoặc địa điểm gần bạn.',
    'stepTwo' => 'Hiểu rõ lựa chọn',
    'stepTwoCopy' => 'Đối chiếu hồ sơ, chi phí tham khảo và trải nghiệm thực tế.',
    'stepThree' => 'Chủ động kết nối',
    'stepThreeCopy' => 'Liên hệ trực tiếp với cơ sở phù hợp để được tư vấn cụ thể.',
    'communityTitle' => 'Trải nghiệm của bạn. An tâm cho nhiều người.',
    'communityCopy' => 'Mỗi chia sẻ chân thực giúp người khác có thêm thông tin trước khi quyết định về sức khỏe.',
    'communityCta' => 'Khám phá những trải nghiệm',
];

$popularTerms = $isEnglish ? ['Dentist in Hanoi', 'Dental crowns in Da Nang', 'Invisalign'] : ['Nha khoa Hà Nội', 'Răng sứ Đà Nẵng', 'Invisalign'];
$specialties = $isEnglish ? [
    ['Dental', 'A healthier smile', 'nha khoa', 'tooth'], ['Beauty & Spa', 'Confidence starts here', 'spa thẩm mỹ', 'sparkle'],
    ['Dermatology', 'Care for healthy skin', 'da liễu', 'leaf'], ['General care', 'Know your health', 'đa khoa', 'building'],
    ['Women’s health', 'Care through every stage', 'sản phụ khoa', 'heart'], ['Eye care', 'See life clearly', 'mắt', 'eye'],
] : [
    ['Nha khoa', 'Chăm chút nụ cười', 'nha khoa', 'tooth'], ['Thẩm mỹ & Spa', 'Tự tin là chính mình', 'spa thẩm mỹ', 'sparkle'],
    ['Da liễu', 'Yêu làn da khỏe', 'da liễu', 'leaf'], ['Khám tổng quát', 'Hiểu cơ thể hơn', 'đa khoa', 'building'],
    ['Sản phụ khoa', 'Đồng hành yêu thương', 'sản phụ khoa', 'heart'], ['Chuyên khoa mắt', 'Nhìn cuộc sống rõ hơn', 'mắt', 'eye'],
];

// Keep a varied first row like the concept, while every profile remains real data.
$featuredFacilities = [];
foreach (['hcm', 'hanoi', 'danang'] as $region) {
    foreach ($facilities as $candidate) {
        if (medical_home_region((string) ($candidate['city'] ?? '')) === $region) {
            $featuredFacilities[(int) $candidate['id']] = $candidate;
            break;
        }
    }
}
foreach ($facilities as $candidate) {
    $featuredFacilities[(int) $candidate['id']] = $candidate;
    if (count($featuredFacilities) >= 12) break;
}
$featuredFacilities = array_values($featuredFacilities);
?>
<?php $homeConceptStylesheet = __DIR__ . '/../assets/css/pages/home-concept-live.css'; ?>
<link rel="stylesheet" href="/assets/css/pages/home-concept-live.css?v=<?= file_exists($homeConceptStylesheet) ? (int) filemtime($homeConceptStylesheet) : 1 ?>">
<main class="med-home hc-home site-typo" id="main">
<svg class="sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="10.7" cy="10.7" r="6.7"/><path d="m16 16 4.5 4.5"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="m21 3-6.8 18-3.5-7.7L3 9.8 21 3Z"/><path d="m10.7 13.3 5.5-5.5"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M4 12h16m-6-6 6 6-6 6"/></symbol>
  <symbol id="i-up-right" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6h12v12"/></symbol>
  <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
  <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></symbol>
  <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8.5 11.5 2.5 2.5 4.5-5"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
  <symbol id="i-verified" viewBox="0 0 24 24"><path d="m12 2 3 2 3.5.5.5 3.5 2 4-2 3-.5 3.5-3.5.5-3 3-3-3-3.5-.5L4 15l-2-3 2-4 .5-3.5L8 4l4-2Z"/><path d="m8 12 2.5 2.5L16 9"/></symbol>
  <symbol id="i-star" viewBox="0 0 24 24"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3L7.5 14 3 9.6l6.2-.9L12 3Z"/></symbol>
  <symbol id="i-bookmark" viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4V3Z"/></symbol>
  <symbol id="i-tooth" viewBox="0 0 24 24"><path d="M12 5C9 1 3 3 4 9c.5 3 1 10 3 12 2 1 2-7 5-7s3 8 5 7c2-2 2.5-9 3-12 1-6-5-8-8-4Z"/><path d="m9 4 3 2 3-2"/></symbol>
  <symbol id="i-building" viewBox="0 0 24 24"><path d="M6 21V3h12v18M3 21h18M10 21v-4h4v4M9 7h6m-3-3v6M9 13h1m4 0h1"/></symbol>
  <symbol id="i-sparkle" viewBox="0 0 24 24"><path d="m12 5 2.5 6.5L21 14l-6.5 2.5L12 23l-2.5-6.5L3 14l6.5-2.5L12 5ZM5 1v6M2 4h6m12-2v5m-2.5-2.5h5"/></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="i-leaf" viewBox="0 0 24 24"><path d="M20 4C10 4 5 7 4 13c-1 5 3 7 7 6 6-1 9-6 9-15Z"/><path d="M3 21 14 10"/></symbol>
  <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20.5 4.8c-2-2-5.7-1.6-8.5 1.4-2.8-3-6.5-3.4-8.5-1.4C.5 8 3 13 12 21c9-8 11.5-13 8.5-16.2Z"/></symbol>
  <symbol id="i-stethoscope" viewBox="0 0 24 24"><path d="M5 3H3v5a5 5 0 0 0 10 0V3h-2M8 13v3a5 5 0 0 0 10 0v-3"/><circle cx="18" cy="10" r="3"/></symbol>
  <symbol id="i-compare" viewBox="0 0 24 24"><rect x="3" y="5" width="7" height="15" rx="1"/><rect x="14" y="3" width="7" height="17" rx="1"/><path d="M5 9h3M5 12h3m8-5h3m-3 3h3m-3 3h3"/></symbol>
  <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 3H5v18h14V8l-5-5ZM14 3v5h5M8 12h8m-8 4h5"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 4v16M4 12h16"/></symbol>
  <symbol id="i-quote" viewBox="0 0 24 24"><path d="M10 5C5 6 3 9 3 13v6h7v-7H6c0-2 1-4 4-4V5Zm11 0c-5 1-7 4-7 8v6h7v-7h-4c0-2 1-4 4-4V5Z"/></symbol>
  <symbol id="i-message" viewBox="0 0 24 24"><path d="M4 3h16v14H9l-5 4V3Z"/><path d="M8 7h8M8 11h5"/></symbol>
  <symbol id="i-brand" viewBox="0 0 48 56"><path fill="#4b91ff" d="M24 1C10 1 1 11 1 24c0 12 23 31 23 31s23-19 23-31C47 11 38 1 24 1Z"/><path fill="#fff" d="M13 19c3-3 7-2 11 2 4-4 8-5 11-2 6 6-1 12-11 20-10-8-17-14-11-20Z"/><circle cx="39" cy="10" r="9" fill="#2563ff" stroke="#102847" stroke-width="3"/><path d="M39 6v8m-4-4h8" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></symbol>
</defs></svg>

  <section class="hero" aria-labelledby="home-hero-title">
    <div class="container hero-grid">
      <div class="hero-copy">
        <p class="eyebrow"><span class="dot" aria-hidden="true"></span><?= htmlspecialchars($isEnglish ? 'Alongside your health journey' : 'Đồng hành cùng sức khỏe của bạn', ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($isEnglish): ?>
          <h1 id="home-hero-title">Find healthcare<br>you can <span>trust</span><br>in Vietnam</h1>
        <?php else: ?>
          <h1 id="home-hero-title">Cộng đồng đánh giá<br>Y tế <span>đáng tin cậy</span><br>tại Việt Nam</h1>
        <?php endif; ?>
        <p class="hero-description">
          <span class="desktop-copy"><?= htmlspecialchars($labels['heroCopy'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="mobile-copy"><?= htmlspecialchars($isEnglish ? 'Find the right care and doctors. Choose with confidence.' : 'Tìm cơ sở, bác sĩ phù hợp. An tâm chăm sóc sức khỏe.', ENT_QUOTES, 'UTF-8') ?></span>
        </p>
        <div class="medical-search-shell hero-search-shell search-panel" data-medical-search>
          <form class="search-form" action="<?= htmlspecialchars($facilitiesPath, ENT_QUOTES, 'UTF-8') ?>" method="get" role="search" aria-label="<?= htmlspecialchars($isEnglish ? 'Search providers, doctors and services' : 'Tìm cơ sở y tế, bác sĩ, dịch vụ và Toplist', ENT_QUOTES, 'UTF-8') ?>">
            <label class="search-field">
              <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
              <span class="sr-only"><?= htmlspecialchars($labels['placeholder'], ENT_QUOTES, 'UTF-8') ?></span>
              <input type="search" name="q" data-medical-search-input autocomplete="off" placeholder="<?= htmlspecialchars($isEnglish ? 'Facilities, doctors, services, cities…' : 'Cơ sở, bác sĩ, dịch vụ, thành phố…', ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <button class="send-button" type="submit" aria-label="<?= htmlspecialchars($labels['search'], ENT_QUOTES, 'UTF-8') ?>"><svg class="icon" aria-hidden="true"><use href="#i-send"/></svg></button>
          </form>
          <div class="medical-search-results suggestions" data-medical-search-results hidden></div>
        </div>
        <div class="quick-search"><span><?= htmlspecialchars($isEnglish ? 'Try:' : 'Thử tìm:', ENT_QUOTES, 'UTF-8') ?></span><?php foreach ($popularTerms as $term): ?><button type="button" data-home-query="<?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8') ?></button><?php endforeach; ?></div>
        <div class="hero-proof">
          <span><svg class="icon" aria-hidden="true"><use href="#i-building"/></svg><strong><?= number_format($stats['facilities'], 0, ',', '.') ?></strong> <?= htmlspecialchars($labels['heroProof'], ENT_QUOTES, 'UTF-8') ?></span>
          <span><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg><?= htmlspecialchars($labels['heroProofVerified'], ENT_QUOTES, 'UTF-8') ?></span>
          <span><svg class="icon" aria-hidden="true"><use href="#i-heart"/></svg><?= htmlspecialchars($isEnglish ? 'For the community' : 'Vì cộng đồng', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
      <div class="hero-visual">
        <span class="hero-cross" aria-hidden="true"><svg class="icon"><use href="#i-plus"/></svg></span>
        <figure class="hero-photo">
          <img src="<?= htmlspecialchars($homeHeroImage, ENT_QUOTES, 'UTF-8') ?>" style="object-position:right center" alt="<?= htmlspecialchars($labels['heroPhotoAlt'], ENT_QUOTES, 'UTF-8') ?>" width="1800" height="720" fetchpriority="high" decoding="async">
          <figcaption><span><?= htmlspecialchars($labels['heroPhotoCaption'], ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars($isEnglish ? 'Illustration' : 'Ảnh minh họa', ENT_QUOTES, 'UTF-8') ?></span></figcaption>
        </figure>
        <div class="floating-note note-top"><span class="note-icon"><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg></span><span><strong><?= htmlspecialchars($labels['heroNoteTitle'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($labels['heroNoteMeta'], ENT_QUOTES, 'UTF-8') ?></small></span></div>
        <div class="floating-note note-bottom"><span class="note-icon"><svg class="icon" aria-hidden="true"><use href="#i-heart"/></svg></span><span><strong><?= htmlspecialchars($labels['heroNoteBottomTitle'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($labels['heroNoteBottomMeta'], ENT_QUOTES, 'UTF-8') ?></small></span></div>
        <span class="hero-orbit" aria-hidden="true"></span>
      </div>
    </div>
  </section>

  <section class="specialty-section" aria-labelledby="home-specialty-title">
    <div class="container">
      <div class="specialty-heading"><h2 id="home-specialty-title"><span class="desktop-copy"><?= htmlspecialchars($isEnglish ? 'What matters to your health today?' : 'Bạn đang quan tâm đến điều gì?', ENT_QUOTES, 'UTF-8') ?></span><span class="mobile-copy"><?= htmlspecialchars($isEnglish ? 'What matters to you?' : 'Bạn quan tâm điều gì?', ENT_QUOTES, 'UTF-8') ?></span></h2><a class="text-link specialty-more" href="<?= htmlspecialchars(site_localized_path($facilitiesBasePath, $locale), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($isEnglish ? 'See more' : 'Xem thêm', ENT_QUOTES, 'UTF-8') ?><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div>
      <div class="specialty-grid mobile-rail" aria-label="<?= htmlspecialchars($isEnglish ? 'Healthcare topics' : 'Các chuyên khoa và nhu cầu chăm sóc sức khỏe', ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($specialties as [$specialtyName, $specialtyCopy, $specialtyQuery, $specialtyIcon]): ?>
          <a class="specialty" href="<?= htmlspecialchars(site_localized_path($facilitiesBasePath, $locale, ['q' => $specialtyQuery]), ENT_QUOTES, 'UTF-8') ?>"><span class="specialty-icon"><svg class="icon" aria-hidden="true"><use href="#i-<?= htmlspecialchars($specialtyIcon, ENT_QUOTES, 'UTF-8') ?>"/></svg></span><span><strong><?= htmlspecialchars($specialtyName, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($specialtyCopy, ENT_QUOTES, 'UTF-8') ?></small></span></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section" id="facilities" aria-labelledby="home-facilities-title">
    <div class="container">
      <div class="section-heading reveal"><div><p class="eyebrow"><?= htmlspecialchars($isEnglish ? 'Discover healthcare facilities' : 'Khám phá cơ sở y tế', ENT_QUOTES, 'UTF-8') ?></p><h2 id="home-facilities-title"><span class="desktop-copy"><?= htmlspecialchars($isEnglish ? 'More information. More peace of mind.' : 'Thêm thông tin. Thêm an tâm.', ENT_QUOTES, 'UTF-8') ?></span><span class="mobile-copy"><?= htmlspecialchars($isEnglish ? 'Featured facilities' : 'Cơ sở nổi bật', ENT_QUOTES, 'UTF-8') ?></span></h2><p><?= htmlspecialchars($isEnglish ? 'Explore and compare places that may fit your needs.' : 'Những địa chỉ để bạn tìm hiểu, đối chiếu và lựa chọn phù hợp.', ENT_QUOTES, 'UTF-8') ?></p></div><a class="text-link" href="<?= htmlspecialchars($facilitiesPath, ENT_QUOTES, 'UTF-8') ?>"><span class="desktop-copy"><?= htmlspecialchars($isEnglish ? 'View all facilities' : 'Xem tất cả cơ sở', ENT_QUOTES, 'UTF-8') ?></span><span class="mobile-copy"><?= htmlspecialchars($labels['viewAll'], ENT_QUOTES, 'UTF-8') ?></span><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div>
      <div class="facility-toolbar"><div class="city-filters" role="group" aria-label="<?= htmlspecialchars($isEnglish ? 'Filter by city' : 'Lọc cơ sở theo thành phố', ENT_QUOTES, 'UTF-8') ?>"><button type="button" data-city="all" aria-pressed="true"><?= htmlspecialchars($isEnglish ? 'All' : 'Tất cả', ENT_QUOTES, 'UTF-8') ?></button><button type="button" data-city="hcm" aria-pressed="false">TP. Hồ Chí Minh</button><button type="button" data-city="hanoi" aria-pressed="false">Hà Nội</button><button type="button" data-city="danang" aria-pressed="false">Đà Nẵng</button></div><span class="toolbar-note"><svg class="icon" aria-hidden="true"><use href="#i-shield"/></svg><?= htmlspecialchars($isEnglish ? 'Profiles on MedReview' : 'Hồ sơ từ MedReview', ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="facility-grid mobile-rail" id="home-facility-grid" data-rail="<?= htmlspecialchars($isEnglish ? 'Facilities' : 'Cơ sở y tế', ENT_QUOTES, 'UTF-8') ?>" role="region" aria-label="<?= htmlspecialchars($isEnglish ? 'Featured healthcare facilities' : 'Cơ sở y tế nổi bật — vuốt ngang để khám phá', ENT_QUOTES, 'UTF-8') ?>" aria-live="polite">
        <?php foreach ($featuredFacilities as $index => $facility): $image = medical_home_image($facility); $description = medical_home_description($facility, $isEnglish); $facilityUrl = medical_public_entity_path('facility', (string) $facility['slug'], $locale); ?>
          <article class="facility-card reveal" data-region="<?= htmlspecialchars(medical_home_region((string) $facility['city']), ENT_QUOTES, 'UTF-8') ?>" data-facility-id="<?= (int) $facility['id'] ?>"<?= $index >= 3 ? ' hidden' : '' ?>>
            <div class="facility-media"><a href="<?= htmlspecialchars($facilityUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars((string) $facility['name'], ENT_QUOTES, 'UTF-8') ?>"><?php if ($image !== ''): ?><img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $facility['name'], ENT_QUOTES, 'UTF-8') ?>" width="640" height="356" loading="lazy" decoding="async"><?php else: ?><span class="facility-photo-placeholder"><svg class="icon" aria-hidden="true"><use href="#i-building"/></svg></span><?php endif; ?></a><span class="facility-label"><?= htmlspecialchars((string) $facility['category'], ENT_QUOTES, 'UTF-8') ?></span><button class="save-button" type="button" aria-pressed="false" aria-label="<?= htmlspecialchars($isEnglish ? 'Save this facility on this device' : 'Lưu cơ sở trên thiết bị này', ENT_QUOTES, 'UTF-8') ?>"><svg class="icon" aria-hidden="true"><use href="#i-bookmark"/></svg></button></div>
            <div class="facility-body"><div class="facility-title"><h3><a href="<?= htmlspecialchars($facilityUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $facility['name'], ENT_QUOTES, 'UTF-8') ?></a></h3><?php if ((int) $facility['verified'] === 1): ?><span class="verified" title="<?= htmlspecialchars($isEnglish ? 'Verified profile' : 'Hồ sơ xác thực', ENT_QUOTES, 'UTF-8') ?>"><svg class="icon" aria-hidden="true"><use href="#i-verified"/></svg></span><?php endif; ?></div><p class="facility-location"><svg class="icon" aria-hidden="true"><use href="#i-pin"/></svg><?= htmlspecialchars((string) ($facility['address_text'] ?: $facility['city']), ENT_QUOTES, 'UTF-8') ?></p><p class="facility-description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p><div class="facility-bottom"><span class="rating"><svg class="icon" aria-hidden="true"><use href="#i-star"/></svg><strong><?= (float) $facility['rating'] > 0 ? number_format((float) $facility['rating'], 1) : '—' ?></strong><small>(<?= number_format((int) $facility['reviews_count'], 0, ',', '.') ?> <?= htmlspecialchars($labels['reviewsCount'], ENT_QUOTES, 'UTF-8') ?>)</small></span><a href="<?= htmlspecialchars($facilityUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($isEnglish ? 'View profile' : 'Xem hồ sơ', ENT_QUOTES, 'UTF-8') ?><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div></div>
          </article>
        <?php endforeach; ?>
        <?php if ($featuredFacilities === []): ?><div class="facility-empty"><?= htmlspecialchars($isEnglish ? 'Profiles will appear here soon.' : 'Hồ sơ cơ sở y tế sẽ sớm được cập nhật.', ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      </div>
      <p class="snapshot-note"><?= htmlspecialchars($isEnglish ? 'Facility information and ratings come from published MedReview profiles.' : 'Thông tin và điểm đánh giá được lấy từ hồ sơ đang xuất bản trên MedReview.', ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </section>

  <section class="section toplist-section" aria-labelledby="home-toplist-title">
    <div class="container">
      <div class="section-heading reveal"><div><p class="eyebrow"><?= htmlspecialchars($isEnglish ? 'Curated Toplists' : 'Toplist chọn lọc', ENT_QUOTES, 'UTF-8') ?></p><h2 id="home-toplist-title"><span class="desktop-copy"><?= $isEnglish ? 'A shorter list.<br>A clearer place to start.' : 'Một danh sách ngắn.<br>Khởi đầu cho lựa chọn tốt.' ?></span><span class="mobile-copy"><?= htmlspecialchars($isEnglish ? 'Recommended for you' : 'Gợi ý cho bạn', ENT_QUOTES, 'UTF-8') ?></span></h2><p><?= htmlspecialchars($isEnglish ? 'Explore facilities by your needs and city.' : 'Cùng khám phá các cơ sở theo nhu cầu và thành phố của bạn.', ENT_QUOTES, 'UTF-8') ?></p></div><a class="text-link" href="<?= htmlspecialchars($toplistsPath, ENT_QUOTES, 'UTF-8') ?>"><span class="desktop-copy"><?= htmlspecialchars($isEnglish ? 'Explore Toplists' : 'Khám phá Toplist', ENT_QUOTES, 'UTF-8') ?></span><span class="mobile-copy"><?= htmlspecialchars($labels['viewAll'], ENT_QUOTES, 'UTF-8') ?></span><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div>
      <div class="toplist-grid mobile-rail" id="home-toplist-grid" data-rail="Toplist" role="region" aria-label="<?= htmlspecialchars($isEnglish ? 'Featured Toplists' : 'Toplist — vuốt ngang để khám phá', ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($toplists as $index => $toplist): $toplistImage = medical_home_image($toplist); $toplistTitle = (string) $toplist['title']; $toplistRegion = medical_home_region($toplistTitle); $toplistLocation = ['hcm' => 'TP. Hồ Chí Minh', 'hanoi' => 'Hà Nội', 'danang' => 'Đà Nẵng'][$toplistRegion] ?? 'Việt Nam'; ?>
          <a class="toplist-card reveal" href="<?= htmlspecialchars(medical_public_entity_path('toplist', (string) $toplist['slug'], $locale), ENT_QUOTES, 'UTF-8') ?>"><?php if ($toplistImage !== ''): ?><img src="<?= htmlspecialchars($toplistImage, ENT_QUOTES, 'UTF-8') ?>" alt="" width="600" height="550" loading="lazy" decoding="async"><?php endif; ?><span class="editorial-tag"><?= htmlspecialchars($isEnglish ? 'CURATED LIST' : 'DANH SÁCH THAM KHẢO', ENT_QUOTES, 'UTF-8') ?></span><span class="toplist-number" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><span class="toplist-location"><svg class="icon" aria-hidden="true"><use href="#i-pin"/></svg><?= htmlspecialchars($toplistLocation, ENT_QUOTES, 'UTF-8') ?></span><h3><?= htmlspecialchars($toplistTitle, ENT_QUOTES, 'UTF-8') ?></h3><span class="toplist-meta"><span><?= number_format((int) $toplist['facility_count'], 0, ',', '.') ?> <?= htmlspecialchars($labels['facilitiesCount'], ENT_QUOTES, 'UTF-8') ?></span><span class="round-arrow"><svg class="icon" aria-hidden="true"><use href="#i-up-right"/></svg></span></span></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section approach-section" aria-labelledby="home-approach-title">
    <div class="container approach-grid">
      <div class="approach-copy reveal"><p class="eyebrow"><?= htmlspecialchars($isEnglish ? 'Why MedReview?' : 'Vì sao có MedReview?', ENT_QUOTES, 'UTF-8') ?></p><h2 id="home-approach-title"><span class="desktop-copy"><?= $isEnglish ? 'A health choice needs<br>more than an advertisement.' : 'Lựa chọn sức khỏe,<br>cần nhiều hơn một lời quảng cáo.' ?></span><span class="mobile-copy"><?= htmlspecialchars($isEnglish ? 'Confidence, in 3 steps.' : 'An tâm, trong 3 bước.', ENT_QUOTES, 'UTF-8') ?></span></h2><p><?= htmlspecialchars($isEnglish ? 'We help you see the information that matters, from provider profiles to services and shared experiences.' : 'Chúng tôi giúp bạn nhìn rõ thông tin cần thiết, từ hồ sơ cơ sở đến dịch vụ và những trải nghiệm được chia sẻ.', ENT_QUOTES, 'UTF-8') ?></p><a class="text-link" href="<?= htmlspecialchars(site_localized_path('/ve-chung-toi.php', $locale), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($isEnglish ? 'About MedReview' : 'Tìm hiểu về MedReview', ENT_QUOTES, 'UTF-8') ?><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div>
      <div class="approach-steps" role="list">
        <article class="step reveal" role="listitem"><span class="step-icon"><svg class="icon" aria-hidden="true"><use href="#i-search"/></svg><span class="step-number">01</span></span><h3><?= htmlspecialchars($labels['stepOne'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($labels['stepOneCopy'], ENT_QUOTES, 'UTF-8') ?></p></article>
        <article class="step reveal" role="listitem"><span class="step-icon"><svg class="icon" aria-hidden="true"><use href="#i-compare"/></svg><span class="step-number">02</span></span><h3><?= htmlspecialchars($labels['stepTwo'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($isEnglish ? 'Compare profiles, prices and reviews.' : 'Xem hồ sơ, chi phí và đánh giá.', ENT_QUOTES, 'UTF-8') ?></p></article>
        <article class="step reveal" role="listitem"><span class="step-icon"><svg class="icon" aria-hidden="true"><use href="#i-heart"/></svg><span class="step-number">03</span></span><h3><?= htmlspecialchars($labels['stepThree'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($isEnglish ? 'Connect with a provider you choose.' : 'Kết nối với cơ sở bạn chọn.', ENT_QUOTES, 'UTF-8') ?></p></article>
      </div>
    </div>
  </section>

  <section class="community-section" aria-labelledby="home-community-title">
    <div class="container"><div class="community reveal"><div class="community-copy"><p class="eyebrow"><?= htmlspecialchars($isEnglish ? 'A caring community' : 'Một cộng đồng, nhiều sự sẻ chia', ENT_QUOTES, 'UTF-8') ?></p><h2 id="home-community-title"><span class="desktop-copy"><?= $isEnglish ? 'Your experience.<br>More peace of mind for others.' : 'Trải nghiệm của bạn.<br>An tâm cho nhiều người.' ?></span><span class="mobile-copy"><?= $isEnglish ? 'A small story.<br>A lasting difference.' : 'Chia sẻ nhỏ.<br>An tâm lớn.' ?></span></h2><p><?= htmlspecialchars($isEnglish ? 'Every honest story can help someone else feel better informed about a health decision.' : 'Mỗi chia sẻ chân thực đều giúp một người khác có thêm thông tin trước khi đưa ra quyết định về sức khỏe.', ENT_QUOTES, 'UTF-8') ?></p><a class="button button-primary" href="<?= htmlspecialchars($reviewsPath, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($isEnglish ? 'Explore reviews' : 'Khám phá những trải nghiệm', ENT_QUOTES, 'UTF-8') ?><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div><div class="community-art" aria-hidden="true"><div class="question-card"><svg class="icon"><use href="#i-quote"/></svg><p><?= $isEnglish ? 'What helped you<br>feel more confident?' : 'Điều gì đã giúp bạn<br>cảm thấy an tâm hơn?' ?></p><div class="experience-chips"><span><?= htmlspecialchars($isEnglish ? 'Thoughtful care' : 'Sự tận tâm', ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars($isEnglish ? 'Clear information' : 'Thông tin rõ ràng', ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars($isEnglish ? 'Transparent costs' : 'Chi phí minh bạch', ENT_QUOTES, 'UTF-8') ?></span></div><p class="question-label"><?= htmlspecialchars($isEnglish ? 'A small review can make a big difference.' : 'Một chia sẻ nhỏ, một giá trị lớn.', ENT_QUOTES, 'UTF-8') ?></p></div><span class="community-badge"><svg class="icon"><use href="#i-message"/></svg></span><span class="heart-stamp"><svg class="icon"><use href="#i-heart"/></svg></span></div></div></div>
  </section>
</main>
<script>
(() => {
  const home = document.querySelector('.med-home.hc-home');
  if (!home) return;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const input = home.querySelector('.search-field input[name="q"]');
  const searchPanel = home.querySelector('.search-panel');

  if (!reduced && 'IntersectionObserver' in window) {
    home.classList.add('motion-ready');
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      });
    }, {threshold: .06, rootMargin: '0px 0px -5% 0px'});
    home.querySelectorAll('.reveal').forEach(item => observer.observe(item));
  }

  home.querySelectorAll('[data-home-query]').forEach(button => button.addEventListener('click', () => {
    if (!input) return;
    input.value = button.dataset.homeQuery || '';
    input.focus();
    input.dispatchEvent(new Event('input', {bubbles:true}));
  }));

  if (input && !reduced) {
    const phrases = <?= json_encode($popularTerms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const placeholder = input.placeholder;
    let phrase = 0, length = 0, reverse = false, timer;
    const tick = () => {
      if (document.hidden || document.activeElement === input || input.value) return;
      const sample = phrases[phrase] || placeholder;
      length += reverse ? -1 : 1;
      input.placeholder = sample.slice(0, Math.max(0, length));
      if (length >= sample.length) { reverse = true; timer = setTimeout(tick, 1600); return; }
      if (length <= 0) { reverse = false; phrase = (phrase + 1) % phrases.length; }
      timer = setTimeout(tick, reverse ? 32 : 72);
    };
    const start = () => { clearTimeout(timer); if (!input.value && document.activeElement !== input) timer = setTimeout(tick, 1100); };
    input.addEventListener('focus', () => {clearTimeout(timer); input.placeholder = placeholder;});
    input.addEventListener('blur', () => {length = 0; reverse = false; input.placeholder = placeholder; start();});
    input.addEventListener('input', () => clearTimeout(timer));
    document.addEventListener('visibilitychange', () => {if (document.hidden) clearTimeout(timer); else start();});
    start();
  }

  if (searchPanel) {
    const backdrop = document.createElement('div');
    backdrop.className = 'home-search-backdrop';
    backdrop.hidden = true;
    document.body.append(backdrop);
    let overlayTimer;
    const sync = () => {backdrop.hidden = !searchPanel.classList.contains('is-open');};
    new MutationObserver(sync).observe(searchPanel, {attributes:true, attributeFilter:['class']});
    input?.addEventListener('focus', () => {
      clearTimeout(overlayTimer);
      overlayTimer = setTimeout(() => {
        if (document.activeElement === input) searchPanel.classList.add('is-open');
      }, 100);
    });
    input?.addEventListener('blur', () => {
      clearTimeout(overlayTimer);
      overlayTimer = setTimeout(() => {
        if (!searchPanel.contains(document.activeElement)) searchPanel.classList.remove('is-open');
      }, 100);
    });
    backdrop.addEventListener('click', () => {input?.blur(); sync();});
  }

  const facilityGrid = home.querySelector('#home-facility-grid');
  const cards = facilityGrid ? [...facilityGrid.querySelectorAll('.facility-card')] : [];
  const filters = [...home.querySelectorAll('.city-filters button')];
  const empty = document.createElement('p');
  empty.className = 'facility-empty';
  empty.textContent = <?= json_encode($isEnglish ? 'No facilities in this city are featured right now. Explore the full directory.' : 'Chưa có cơ sở nổi bật tại thành phố này. Bạn có thể xem toàn bộ danh bạ.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  empty.hidden = true;
  facilityGrid?.append(empty);
  const applyCity = region => {
    let shown = 0;
    cards.forEach(card => {
      const match = region === 'all' || card.dataset.region === region;
      card.hidden = !match || shown >= 3;
      if (match) shown++;
    });
    empty.hidden = shown > 0;
    filters.forEach(button => button.setAttribute('aria-pressed', button.dataset.city === region ? 'true' : 'false'));
    if (facilityGrid) facilityGrid.scrollLeft = 0;
    facilityGrid?.dispatchEvent(new Event('rail-change'));
  };
  filters.forEach(button => button.addEventListener('click', () => applyCity(button.dataset.city || 'all')));
  applyCity('all');

  cards.forEach(card => {
    const button = card.querySelector('.save-button');
    if (!button) return;
    const key = 'medreview:saved-facility:' + card.dataset.facilityId;
    try {button.setAttribute('aria-pressed', localStorage.getItem(key) === '1' ? 'true' : 'false');} catch (_) {}
    button.addEventListener('click', () => {
      const saved = button.getAttribute('aria-pressed') !== 'true';
      button.setAttribute('aria-pressed', saved ? 'true' : 'false');
      try {if (saved) localStorage.setItem(key, '1'); else localStorage.removeItem(key);} catch (_) {}
    });
  });

  const mobile = matchMedia('(max-width:760px)');
  [facilityGrid, home.querySelector('#home-toplist-grid')].filter(Boolean).forEach(rail => {
    const nav = document.createElement('div');
    nav.className = 'rail-navigation';
    nav.innerHTML = '<div class="rail-progress" aria-hidden="true"></div><div class="rail-actions"><button type="button" class="rail-prev" aria-label="Trước"><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></button><span class="rail-count" aria-hidden="true"></span><button type="button" class="rail-next" aria-label="Tiếp"><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></button></div>';
    rail.after(nav);
    const prev = nav.querySelector('.rail-prev'), next = nav.querySelector('.rail-next');
    const progress = nav.querySelector('.rail-progress'), count = nav.querySelector('.rail-count');
    const visible = () => [...rail.children].filter(card => !card.hidden);
    const update = () => {
      const items = visible();
      const scrollable = mobile.matches && items.length > 1 && rail.scrollWidth > rail.clientWidth + 2;
      nav.hidden = !scrollable;
      if (!scrollable) return;
      const edge = rail.getBoundingClientRect().left + parseFloat(getComputedStyle(rail).paddingLeft || '0');
      let active = items.reduce((best, card, index) => Math.abs(card.getBoundingClientRect().left-edge) < Math.abs(items[best].getBoundingClientRect().left-edge) ? index : best, 0);
      if (rail.scrollLeft >= rail.scrollWidth - rail.clientWidth - 3) active = items.length - 1;
      progress.innerHTML = items.map((_, i) => '<span class="' + (i === active ? 'active' : '') + '"></span>').join('');
      count.textContent = (active + 1) + ' / ' + items.length;
      prev.disabled = active === 0;
      next.disabled = active === items.length - 1;
    };
    const move = direction => {
      const items = visible();
      if (!items.length) return;
      const step = items[0].getBoundingClientRect().width + parseFloat(getComputedStyle(rail).columnGap || '0');
      rail.scrollBy({left: direction * step, behavior: reduced ? 'instant' : 'smooth'});
    };
    prev.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    rail.addEventListener('scroll', () => requestAnimationFrame(update), {passive:true});
    rail.addEventListener('rail-change', () => requestAnimationFrame(update));
    window.addEventListener('resize', update, {passive:true});
    mobile.addEventListener('change', update);
    update();
  });
})();
</script>
