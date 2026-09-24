<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_directory.php';
require_once __DIR__ . '/front_admin.php';

if (function_exists('admin_front_session_boot')) {
    admin_front_session_boot();
}
$locale = site_page_locale('about');
$isEnglish = $locale === 'en';
$copy = $isEnglish ? [
    'home' => 'Home', 'kicker' => 'Healthcare discovery platform',
    'title' => 'Clearer healthcare information so you can <span>choose with confidence.</span>',
    'intro' => 'MedReview helps you explore healthcare facilities, doctors, reviews and useful lists in one place — before making a choice that fits your needs.',
    'exploreFacilities' => 'Explore healthcare facilities', 'curatedLists' => 'Browse curated lists',
    'dataLabel' => 'MedReview data', 'visualLabel' => 'Healthcare facilities on MedReview', 'fallbackFacility' => 'Healthcare facility on MedReview',
    'aboutEyebrow' => 'About MedReview', 'aboutHeading' => 'A clearer starting point for your healthcare journey.',
    'storyOne' => 'MedReview was built to make information about clinics, hospitals and doctors easier to find. Instead of switching between multiple sources, you can review profiles, services, reference information, reviews and comparison lists on one platform.',
    'storyTwo' => 'MedReview content is for reference and can help you ask better questions before making a choice. Diagnosis and treatment decisions should always be discussed directly with an appropriate healthcare professional.',
    'principles' => 'Our content principles', 'structured' => 'Structured information', 'structuredCopy' => 'Profiles are organized into clear sections for easier comparison.',
    'contextual' => 'Reviews with context', 'contextualCopy' => 'Where available, reviews are shown alongside ratings and relevant information.',
    'updated' => 'Information can be updated', 'updatedCopy' => 'Profiles and lists can be expanded as information changes.',
    'direction' => 'What we aim for', 'valuesHeading' => 'Support decisions with transparent experiences and useful information.',
    'easyExplore' => 'Easy to explore', 'easyExploreCopy' => 'Search by location, service category or need to narrow your options.',
    'easyCompare' => 'Easy to compare', 'easyCompareCopy' => 'Review important information side by side before learning more.',
    'realExperience' => 'Real experiences first', 'realExperienceCopy' => 'Reviews and source information, when available, appear with the relevant content.',
    'byNeed' => 'Explore by need', 'byNeedHeading' => 'Start with the type of care you are looking for.',
    'categoryEmpty' => 'Categories will appear here as more profiles are published.',
    'methodHeading' => 'How should you use this information?', 'disclaimer' => 'MedReview does not replace professional advice, diagnosis or treatment.',
    'readProfiles' => 'Review profiles and related experiences carefully.', 'confirmDetails' => 'Contact providers directly to confirm the latest information.', 'consultProfessionals' => 'Speak with a healthcare professional before treatment.',
    'howTo' => 'How to use MedReview', 'stepsHeading' => 'Three simple steps to get started.',
    'search' => 'Search', 'searchCopy' => 'Enter a need, service or location you want to explore.',
    'compareProfiles' => 'Compare profiles', 'compareCopy' => 'Review the available information for each facility or doctor.',
    'confirm' => 'Contact and confirm', 'confirmCopy' => 'Ask the provider to confirm details before making a decision.',
    'ctaHeading' => 'Help us build a more useful healthcare community.', 'ctaCopy' => 'Have feedback about content or data, or want to update a profile? MedReview is ready to listen.',
    'contact' => 'Contact MedReview',
] : [
    'home' => 'Trang chủ', 'kicker' => 'Nền tảng khám phá y tế',
    'title' => 'Thông tin y tế rõ ràng hơn để bạn <span>tự tin lựa chọn.</span>',
    'intro' => 'MedReview giúp bạn tìm hiểu cơ sở y tế, bác sĩ, đánh giá và các danh sách hữu ích tại một nơi — trước khi đưa ra quyết định phù hợp với nhu cầu của mình.',
    'exploreFacilities' => 'Khám phá cơ sở y tế', 'curatedLists' => 'Xem danh sách chọn lọc',
    'dataLabel' => 'Dữ liệu MedReview', 'visualLabel' => 'Một số cơ sở y tế trên MedReview', 'fallbackFacility' => 'Cơ sở y tế trên MedReview',
    'aboutEyebrow' => 'Về MedReview', 'aboutHeading' => 'Một điểm bắt đầu dễ hiểu cho hành trình chăm sóc sức khỏe.',
    'storyOne' => 'MedReview được xây dựng để việc tìm kiếm thông tin về phòng khám, bệnh viện và bác sĩ bớt rời rạc. Thay vì phải mở nhiều nguồn khác nhau, bạn có thể xem hồ sơ, dịch vụ, thông tin tham khảo, đánh giá và danh sách so sánh ngay trên cùng một nền tảng.',
    'storyTwo' => 'Nội dung trên MedReview mang tính tham khảo, giúp bạn đặt câu hỏi đúng hơn trước khi lựa chọn. Việc chẩn đoán và điều trị luôn cần được trao đổi trực tiếp với chuyên môn y tế phù hợp.',
    'principles' => 'Nguyên tắc nội dung', 'structured' => 'Thông tin có cấu trúc', 'structuredCopy' => 'Hồ sơ được trình bày theo các nhóm thông tin dễ đối chiếu.',
    'contextual' => 'Đánh giá có ngữ cảnh', 'contextualCopy' => 'Nơi có dữ liệu, đánh giá được gắn với điểm số và thông tin liên quan.',
    'updated' => 'Luôn có thể cập nhật', 'updatedCopy' => 'Hồ sơ và danh sách có thể được bổ sung khi dữ liệu thay đổi.',
    'direction' => 'Điều chúng tôi hướng tới', 'valuesHeading' => 'Hỗ trợ quyết định bằng trải nghiệm minh bạch và thông tin dễ dùng.',
    'easyExplore' => 'Dễ khám phá', 'easyExploreCopy' => 'Tìm theo khu vực, nhóm dịch vụ hoặc nhu cầu để thu hẹp lựa chọn phù hợp.',
    'easyCompare' => 'Dễ so sánh', 'easyCompareCopy' => 'Đặt những thông tin quan trọng cạnh nhau trước khi bạn tìm hiểu sâu hơn.',
    'realExperience' => 'Ưu tiên trải nghiệm thật', 'realExperienceCopy' => 'Đánh giá và thông tin nguồn, khi có, được thể hiện ngay trong phần nội dung liên quan.',
    'byNeed' => 'Khám phá theo nhu cầu', 'byNeedHeading' => 'Bắt đầu từ nhóm dịch vụ bạn đang quan tâm.',
    'categoryEmpty' => 'Danh mục sẽ xuất hiện tại đây khi có hồ sơ được công bố.',
    'methodHeading' => 'Thông tin được dùng như thế nào?', 'disclaimer' => 'MedReview không thay thế tư vấn, chẩn đoán hoặc điều trị từ bác sĩ.',
    'readProfiles' => 'Đọc kỹ hồ sơ và đánh giá liên quan.', 'confirmDetails' => 'Liên hệ trực tiếp để xác nhận thông tin mới nhất.', 'consultProfessionals' => 'Trao đổi với chuyên môn y tế trước khi điều trị.',
    'howTo' => 'Cách sử dụng MedReview', 'stepsHeading' => 'Ba bước ngắn để bắt đầu tìm hiểu.',
    'search' => 'Tìm kiếm', 'searchCopy' => 'Nhập nhu cầu, dịch vụ hoặc địa điểm bạn muốn tìm.',
    'compareProfiles' => 'Đối chiếu hồ sơ', 'compareCopy' => 'Xem các thông tin có trên từng cơ sở hoặc bác sĩ.',
    'confirm' => 'Liên hệ và xác nhận', 'confirmCopy' => 'Chủ động hỏi lại đơn vị cung cấp dịch vụ trước khi quyết định.',
    'ctaHeading' => 'Cùng xây dựng một cộng đồng y tế hữu ích hơn.', 'ctaCopy' => 'Nếu bạn có góp ý về nội dung, dữ liệu hoặc muốn cập nhật hồ sơ, MedReview luôn sẵn sàng lắng nghe.',
    'contact' => 'Liên hệ MedReview',
];

$pdo = db();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$aboutCount = static function (PDO $pdo, string $table, string $where = ''): int {
    if (!medical_directory_table_exists($pdo, $table)) {
        return 0;
    }
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` {$where}")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
};

$stats = [
    ['icon' => 'ph-hospital', 'value' => $aboutCount($pdo, 'medical_facilities', "WHERE status = 'published'"), 'label' => $isEnglish ? 'facilities' : 'cơ sở y tế'],
    ['icon' => 'ph-stethoscope', 'value' => $aboutCount($pdo, 'medical_doctors', "WHERE status = 'published'"), 'label' => $isEnglish ? 'doctors' : 'bác sĩ'],
    ['icon' => 'ph-star', 'value' => $aboutCount($pdo, 'medical_reviews', "WHERE status = 'published'"), 'label' => $isEnglish ? 'reviews' : 'đánh giá'],
    ['icon' => 'ph-list-numbers', 'value' => $aboutCount($pdo, 'medical_toplists', "WHERE status = 'published'"), 'label' => $isEnglish ? 'curated lists' : 'danh sách chọn lọc'],
];

$categories = [];
$featuredFacilities = [];
if (medical_directory_table_exists($pdo, 'medical_facilities')) {
    try {
        $categoryStmt = $pdo->query(
            "SELECT TRIM(category) AS name, COUNT(*) AS total
             FROM medical_facilities
             WHERE status = 'published' AND TRIM(COALESCE(category, '')) <> ''
             GROUP BY TRIM(category)
             ORDER BY total DESC, name ASC
             LIMIT 6"
        );
        $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $facilityStmt = $pdo->query(
            "SELECT name, category, city, image_url, slug
             FROM medical_facilities
             WHERE status = 'published'
               AND TRIM(COALESCE(image_url, '')) <> ''
             ORDER BY reviews_count DESC, id DESC
             LIMIT 4"
        );
        $featuredFacilities = $facilityStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $categories = [];
        $featuredFacilities = [];
    }
}

$contactPath = front_editor_page_public_path($isEnglish ? 'contact-en' : 'contact');
$seo = front_editor_page_seo('about', [
    'title' => 'Về MedReview',
    'description' => 'Tìm hiểu MedReview — nền tảng giúp người dùng khám phá cơ sở y tế, bác sĩ, đánh giá và danh sách y tế hữu ích.',
    'canonical_path' => '/ve-chung-toi.php',
]);
$canonicalPath = '/ve-chung-toi.php';
$canonicalPath = site_localized_path($canonicalPath, $locale);
$seoTitle = trim((string) ($seo['title'] ?? ''));
$seoDescription = trim((string) ($seo['description'] ?? ''));
if ($isEnglish) {
    $seoTitle = 'About MedReview | Trusted Healthcare Discovery';
    $seoDescription = 'Learn how MedReview helps people discover healthcare facilities, doctors, reviews and useful medical information in Vietnam.';
} elseif ($seoTitle === '' || stripos($seoTitle, 'top dental') !== false) {
    $seoTitle = 'Về MedReview | Nền tảng review và tìm kiếm y tế';
}
if ($seoDescription === '' || stripos($seoDescription, 'top dental') !== false) {
    $seoDescription = 'Tìm hiểu MedReview — nền tảng giúp người dùng khám phá cơ sở y tế, bác sĩ, đánh giá và danh sách y tế hữu ích.';
}
?>
<!doctype html>
<html lang="<?php echo $isEnglish ? 'en' : 'vi'; ?>">
<head>
    <?php echo site_favicon_tags(); ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $escape($seoTitle); ?></title>
  <meta name="description" content="<?php echo $escape($seoDescription); ?>">
  <link rel="canonical" href="<?php echo $escape(site_absolute_url($canonicalPath)); ?>">
  <link rel="alternate" hreflang="vi" href="<?php echo $escape(site_absolute_url('/ve-chung-toi.php')); ?>">
  <link rel="alternate" hreflang="en" href="<?php echo $escape(site_absolute_url(site_localized_path('/ve-chung-toi.php', 'en'))); ?>">
  <link rel="alternate" hreflang="x-default" href="<?php echo $escape(site_absolute_url('/ve-chung-toi.php')); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--about-ink:#12203b;--about-muted:#64748b;--about-blue:#2563eb;--about-line:#dbe7fb;--about-surface:#fff}
    *{box-sizing:border-box} html{scroll-behavior:smooth} body{margin:0;background:#f6f9ff;color:var(--about-ink);font-family:var(--ui-font,"Inter",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif)}
    .about-page{overflow:hidden}.about-shell{width:min(1180px,calc(100% - 48px));margin:0 auto}.about-page a{text-decoration:none}.about-page button{font:inherit}
    .about-breadcrumb{display:flex;align-items:center;gap:8px;padding:24px 0 17px;color:#7183a3;font-size:13px;font-weight:700}.about-breadcrumb a{color:#7183a3}.about-breadcrumb i{font-size:14px;color:#9aaccc}.about-breadcrumb strong{color:#2563eb}
    .about-hero{position:relative;display:grid;grid-template-columns:minmax(0,1.1fr) minmax(380px,.9fr);gap:44px;align-items:center;min-height:520px;padding:58px clamp(30px,5vw,66px);overflow:hidden;border:1px solid #d7e6ff;border-radius:32px;background:linear-gradient(135deg,#fff 0%,#f4f8ff 53%,#e9f2ff 100%);box-shadow:0 22px 62px rgba(37,99,235,.1)}
    .about-hero:before{content:"";position:absolute;width:480px;height:480px;right:-190px;top:-235px;border-radius:50%;background:radial-gradient(circle,rgba(99,154,255,.2),rgba(99,154,255,0) 69%);pointer-events:none}.about-hero-copy,.about-visual{position:relative;z-index:1}.about-kicker{display:inline-flex;align-items:center;gap:8px;padding:9px 13px;border:1px solid #c8f2df;border-radius:999px;background:#f0fcf6;color:#087c51;font-size:12px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}.about-kicker i{font-size:17px}.about-hero h1{max-width:650px;margin:20px 0 18px;font-size:clamp(34px,4vw,58px);line-height:1.12;letter-spacing:-.055em}.about-hero h1 span{color:var(--about-blue)}.about-hero p{max-width:625px;margin:0;color:#576b8b;font-size:17px;line-height:1.8}.about-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.about-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:48px;padding:0 18px;border:1px solid transparent;border-radius:14px;font-size:14px;font-weight:800;transition:transform .18s ease,box-shadow .18s ease}.about-btn:hover{transform:translateY(-2px)}.about-btn-primary{background:linear-gradient(135deg,#3b82f6,#1d4ed8);box-shadow:0 15px 26px rgba(37,99,235,.25);color:#fff}.about-btn-secondary{border-color:#cbdcf8;background:rgba(255,255,255,.78);color:#2853a5}
    .about-stat-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:31px}.about-stat-chip{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid rgba(204,220,246,.9);border-radius:12px;background:rgba(255,255,255,.74);color:#567092;font-size:12px;font-weight:700}.about-stat-chip strong{color:#1f55bf;font-size:15px}.about-stat-chip i{color:#3978ef;font-size:17px}
    .about-visual{min-height:365px}.about-visual-main{position:absolute;inset:17px 20px 10px 10px;overflow:hidden;border:1px solid rgba(255,255,255,.86);border-radius:25px;background:linear-gradient(140deg,#d7e9ff,#edf5ff);box-shadow:0 25px 48px rgba(38,83,159,.14)}.about-visual-main img{width:100%;height:100%;object-fit:cover}.about-visual-main:after{content:"";position:absolute;inset:0;background:linear-gradient(145deg,rgba(21,77,176,.05),rgba(255,255,255,.35))}.about-visual-empty{display:flex;height:100%;align-items:center;justify-content:center;color:#4a7cd5;font-size:76px}.about-photo-stack{position:absolute;right:-2px;bottom:-5px;display:grid;grid-template-columns:repeat(2,74px);gap:8px;padding:10px;border:1px solid rgba(255,255,255,.95);border-radius:19px;background:rgba(255,255,255,.87);box-shadow:0 18px 36px rgba(37,99,235,.17);backdrop-filter:blur(12px)}.about-photo-stack div{width:74px;height:58px;overflow:hidden;border-radius:10px;background:#e7f0ff}.about-photo-stack img{width:100%;height:100%;object-fit:cover}.about-photo-stack i{display:grid;height:100%;place-items:center;color:#5f89d5;font-size:24px}
    .about-section{padding:88px 0 0}.about-section-heading{max-width:680px}.about-eyebrow{margin:0 0 10px;color:#2563eb;font-size:12px;font-weight:800;letter-spacing:.09em;text-transform:uppercase}.about-section h2{margin:0;font-size:clamp(27px,3vw,40px);letter-spacing:-.045em;line-height:1.2}.about-section-heading>p:not(.about-eyebrow){margin:15px 0 0;color:#63748f;font-size:15px;line-height:1.75}
    .about-purpose{display:grid;grid-template-columns:1.02fr .98fr;gap:24px;margin-top:28px}.about-story,.about-trust{border:1px solid var(--about-line);border-radius:22px;background:var(--about-surface);box-shadow:0 12px 35px rgba(26,67,135,.05)}.about-story{padding:31px}.about-story p{margin:0;color:#536884;font-size:16px;line-height:1.85}.about-story p+p{margin-top:15px}.about-trust{display:grid;gap:0;overflow:hidden}.about-trust-item{display:flex;gap:14px;padding:19px 22px;border-bottom:1px solid #e6eefb}.about-trust-item:last-child{border-bottom:0}.about-trust-item>i{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;border-radius:13px;background:#eef5ff;color:#2563eb;font-size:21px}.about-trust-item strong{display:block;margin:1px 0 4px;font-size:14px}.about-trust-item p{margin:0;color:#70819b;font-size:13px;line-height:1.55}
    .about-values{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:29px}.about-value{min-height:218px;padding:27px;border:1px solid var(--about-line);border-radius:21px;background:linear-gradient(155deg,#fff,#f8fbff);transition:transform .2s ease,box-shadow .2s ease}.about-value:hover{transform:translateY(-4px);box-shadow:0 19px 35px rgba(37,99,235,.1)}.about-value-icon{display:grid;width:46px;height:46px;place-items:center;border-radius:14px;background:#e9f2ff;color:#2563eb;font-size:23px}.about-value h3{margin:19px 0 8px;font-size:17px;letter-spacing:-.025em}.about-value p{margin:0;color:#6a7d99;font-size:13px;line-height:1.7}
    .about-directory{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:24px;margin-top:29px}.about-category-card,.about-method-card{border:1px solid var(--about-line);border-radius:22px;background:#fff;box-shadow:0 12px 35px rgba(26,67,135,.05)}.about-category-card{padding:26px}.about-category-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.about-category{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:53px;padding:11px 12px;border:1px solid #e0ebfc;border-radius:13px;background:#fbfdff;color:#315178;font-size:13px;font-weight:800;transition:border-color .18s ease,background .18s ease}.about-category:hover{border-color:#9cc1fa;background:#f2f7ff}.about-category span{display:grid;min-width:25px;height:25px;place-items:center;border-radius:8px;background:#edf4ff;color:#2f6de3;font-size:11px}.about-empty{margin:0;color:#7788a1;font-size:14px;line-height:1.7}.about-method-card{padding:26px;background:linear-gradient(145deg,#1e55c1,#3677e8);color:#fff}.about-method-card h3{margin:0;font-size:19px;letter-spacing:-.03em}.about-method-card>p{margin:10px 0 0;color:rgba(255,255,255,.76);font-size:13px;line-height:1.65}.about-method-list{display:grid;gap:13px;margin-top:22px}.about-method-list div{display:flex;align-items:flex-start;gap:10px;font-size:13px;font-weight:700;line-height:1.45}.about-method-list i{margin-top:1px;color:#c7dcff;font-size:17px}
    .about-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:25px;margin-top:30px;counter-reset:steps}.about-step{position:relative;padding:27px 4px 2px 24px;border-left:2px solid #bad5ff}.about-step:before{counter-increment:steps;content:"0" counter(steps);position:absolute;left:-16px;top:-3px;display:grid;width:30px;height:30px;place-items:center;border:2px solid #8db9fd;border-radius:50%;background:#fff;color:#1d61cc;font-size:11px;font-weight:800}.about-step h3{margin:0 0 8px;font-size:17px}.about-step p{margin:0;color:#71829b;font-size:13px;line-height:1.72}
    .about-cta{position:relative;display:flex;align-items:center;justify-content:space-between;gap:26px;margin:84px 0 18px;padding:38px clamp(25px,4vw,50px);overflow:hidden;border-radius:25px;background:linear-gradient(122deg,#0d3e94,#2566d8 52%,#528eff);box-shadow:0 22px 45px rgba(34,91,205,.24);color:#fff}.about-cta:after{content:"";position:absolute;right:-80px;bottom:-165px;width:370px;height:370px;border:1px solid rgba(255,255,255,.18);border-radius:50%;box-shadow:0 0 0 38px rgba(255,255,255,.055),0 0 0 76px rgba(255,255,255,.04)}.about-cta-copy{position:relative;z-index:1}.about-cta h2{max-width:670px;font-size:clamp(26px,3vw,36px)}.about-cta p{max-width:640px;margin:12px 0 0;color:rgba(255,255,255,.76);font-size:14px;line-height:1.7}.about-cta .about-btn{position:relative;z-index:1;flex:0 0 auto;background:#fff;color:#2156be;box-shadow:0 15px 28px rgba(9,42,112,.2)}
    .about-reveal{opacity:0;transform:translateY(14px);transition:opacity .48s ease,transform .48s ease}.about-reveal.is-visible{opacity:1;transform:none}
    @media(max-width:980px){.about-hero{grid-template-columns:1fr;gap:20px;min-height:0}.about-visual{max-width:520px;width:100%;height:330px;margin:0 auto}.about-purpose,.about-directory{grid-template-columns:1fr}.about-values{grid-template-columns:repeat(3,minmax(0,1fr))}.about-directory{gap:18px}.about-method-card{display:grid;grid-template-columns:.85fr 1.15fr;column-gap:25px}.about-method-list{margin-top:0}.about-method-card>p{grid-column:1}.about-steps{gap:13px}}
    @media(max-width:720px){.about-shell{width:min(100% - 28px,600px)}.about-breadcrumb{padding:17px 0 13px;font-size:11px}.about-hero{padding:32px 22px 23px;border-radius:24px}.about-hero h1{margin-top:17px;font-size:36px;line-height:1.15}.about-hero p{font-size:14px;line-height:1.72}.about-actions{display:grid;grid-template-columns:1fr 1fr;margin-top:22px}.about-btn{min-height:46px;padding:0 12px;font-size:13px}.about-stat-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:22px}.about-stat-chip{padding:9px;font-size:10px}.about-stat-chip strong{font-size:14px}.about-visual{height:240px;min-height:0}.about-visual-main{inset:7px 13px 10px 2px;border-radius:19px}.about-photo-stack{right:-4px;bottom:-8px;grid-template-columns:repeat(2,55px);gap:6px;padding:8px;border-radius:15px}.about-photo-stack div{width:55px;height:43px}.about-section{padding-top:58px}.about-section-heading>p:not(.about-eyebrow){font-size:13px}.about-section h2{font-size:28px}.about-purpose{gap:15px;margin-top:22px}.about-story{padding:22px}.about-story p{font-size:14px;line-height:1.75}.about-trust-item{padding:15px}.about-values{grid-template-columns:1fr;gap:11px;margin-top:22px}.about-value{min-height:0;padding:20px}.about-value h3{margin-top:14px}.about-category-card,.about-method-card{padding:21px}.about-category-grid{grid-template-columns:1fr}.about-method-card{display:block}.about-method-list{margin-top:18px}.about-steps{grid-template-columns:1fr;gap:5px;margin:23px 0 0 10px}.about-step{padding:21px 0 18px 21px}.about-cta{display:block;margin-top:56px;padding:28px 23px;border-radius:22px}.about-cta p{font-size:13px}.about-cta .about-btn{width:100%;margin-top:21px}.about-reveal{opacity:1;transform:none}}
    @media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;transition-duration:.01ms!important;animation-duration:.01ms!important}.about-reveal{opacity:1;transform:none}}
  </style>
</head>
<body>
<?php require __DIR__ . '/Tem/header.php'; ?>
<main class="about-page">
  <div class="about-shell">
    <nav class="about-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo $escape(site_localized_path('/', $locale)); ?>"><?php echo $escape($copy['home']); ?></a><i class="ph ph-caret-right" aria-hidden="true"></i><strong><?php echo $isEnglish ? 'About MedReview' : 'Về MedReview'; ?></strong></nav>
    <section class="about-hero about-reveal">
      <div class="about-hero-copy">
        <span class="about-kicker"><i class="ph-fill ph-seal-check"></i> <?php echo $escape($copy['kicker']); ?></span>
        <h1><?php echo $copy['title']; ?></h1>
        <p><?php echo $escape($copy['intro']); ?></p>
        <div class="about-actions">
          <a class="about-btn about-btn-primary" href="<?php echo $escape(site_localized_path(medical_public_facility_path(), $locale)); ?>"><i class="ph ph-magnifying-glass"></i> <?php echo $escape($copy['exploreFacilities']); ?></a>
          <a class="about-btn about-btn-secondary" href="<?php echo $escape(site_localized_path(medical_public_toplist_path(), $locale)); ?>"><i class="ph ph-list-numbers"></i> <?php echo $escape($copy['curatedLists']); ?></a>
        </div>
        <div class="about-stat-row" aria-label="<?php echo $escape($copy['dataLabel']); ?>">
          <?php foreach ($stats as $stat): ?>
            <span class="about-stat-chip"><i class="ph <?php echo $escape($stat['icon']); ?>"></i><strong><?php echo medical_directory_format_int((int) $stat['value']); ?></strong> <?php echo $escape($stat['label']); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="about-visual" aria-label="<?php echo $escape($copy['visualLabel']); ?>">
        <div class="about-visual-main">
          <?php $mainImage = $featuredFacilities[0]['image_url'] ?? ''; ?>
          <?php if (trim((string) $mainImage) !== ''): ?>
            <img src="<?php echo $escape($mainImage); ?>" alt="<?php echo $escape($featuredFacilities[0]['name'] ?? $copy['fallbackFacility']); ?>" loading="eager" fetchpriority="high" decoding="async">
          <?php else: ?>
            <span class="about-visual-empty"><i class="ph-fill ph-heartbeat"></i></span>
          <?php endif; ?>
        </div>
        <?php if (count($featuredFacilities) > 1): ?>
          <div class="about-photo-stack" aria-hidden="true">
            <?php foreach (array_slice($featuredFacilities, 1, 4) as $facility): ?>
              <div><?php if (trim((string) ($facility['image_url'] ?? '')) !== ''): ?><img src="<?php echo $escape($facility['image_url']); ?>" alt="" loading="lazy" decoding="async"><?php else: ?><i class="ph ph-image"></i><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="about-section about-reveal">
      <header class="about-section-heading"><p class="about-eyebrow"><?php echo $escape($copy['aboutEyebrow']); ?></p><h2><?php echo $escape($copy['aboutHeading']); ?></h2></header>
      <div class="about-purpose">
        <article class="about-story">
          <p><?php echo $escape($copy['storyOne']); ?></p>
          <p><?php echo $escape($copy['storyTwo']); ?></p>
        </article>
        <aside class="about-trust" aria-label="<?php echo $escape($copy['principles']); ?>">
          <div class="about-trust-item"><i class="ph ph-squares-four"></i><div><strong><?php echo $escape($copy['structured']); ?></strong><p><?php echo $escape($copy['structuredCopy']); ?></p></div></div>
          <div class="about-trust-item"><i class="ph ph-chat-text"></i><div><strong><?php echo $escape($copy['contextual']); ?></strong><p><?php echo $escape($copy['contextualCopy']); ?></p></div></div>
          <div class="about-trust-item"><i class="ph ph-arrows-clockwise"></i><div><strong><?php echo $escape($copy['updated']); ?></strong><p><?php echo $escape($copy['updatedCopy']); ?></p></div></div>
        </aside>
      </div>
    </section>

    <section class="about-section about-reveal">
      <header class="about-section-heading"><p class="about-eyebrow"><?php echo $escape($copy['direction']); ?></p><h2><?php echo $escape($copy['valuesHeading']); ?></h2></header>
      <div class="about-values">
        <article class="about-value"><span class="about-value-icon"><i class="ph ph-compass-tool"></i></span><h3><?php echo $escape($copy['easyExplore']); ?></h3><p><?php echo $escape($copy['easyExploreCopy']); ?></p></article>
        <article class="about-value"><span class="about-value-icon"><i class="ph ph-scales"></i></span><h3><?php echo $escape($copy['easyCompare']); ?></h3><p><?php echo $escape($copy['easyCompareCopy']); ?></p></article>
        <article class="about-value"><span class="about-value-icon"><i class="ph ph-heart"></i></span><h3><?php echo $escape($copy['realExperience']); ?></h3><p><?php echo $escape($copy['realExperienceCopy']); ?></p></article>
      </div>
    </section>

    <section class="about-section about-reveal">
      <header class="about-section-heading"><p class="about-eyebrow"><?php echo $escape($copy['byNeed']); ?></p><h2><?php echo $escape($copy['byNeedHeading']); ?></h2></header>
      <div class="about-directory">
        <div class="about-category-card">
          <?php if ($categories): ?>
            <div class="about-category-grid">
              <?php foreach ($categories as $category): ?>
                <?php $categoryName = trim((string) ($category['name'] ?? '')); ?>
                <a class="about-category" href="<?php echo $escape(site_localized_path(medical_public_facility_path(), $locale, ['category' => $categoryName])); ?>"><span><i class="ph ph-plus"></i></span><?php echo $escape($categoryName); ?><i class="ph ph-arrow-up-right"></i></a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="about-empty"><?php echo $escape($copy['categoryEmpty']); ?></p>
          <?php endif; ?>
        </div>
        <aside class="about-method-card"><div><h3><?php echo $escape($copy['methodHeading']); ?></h3><p><?php echo $escape($copy['disclaimer']); ?></p></div><div class="about-method-list"><div><i class="ph-fill ph-check-circle"></i><span><?php echo $escape($copy['readProfiles']); ?></span></div><div><i class="ph-fill ph-check-circle"></i><span><?php echo $escape($copy['confirmDetails']); ?></span></div><div><i class="ph-fill ph-check-circle"></i><span><?php echo $escape($copy['consultProfessionals']); ?></span></div></div></aside>
      </div>
    </section>

    <section class="about-section about-reveal">
      <header class="about-section-heading"><p class="about-eyebrow"><?php echo $escape($copy['howTo']); ?></p><h2><?php echo $escape($copy['stepsHeading']); ?></h2></header>
      <div class="about-steps"><article class="about-step"><h3><?php echo $escape($copy['search']); ?></h3><p><?php echo $escape($copy['searchCopy']); ?></p></article><article class="about-step"><h3><?php echo $escape($copy['compareProfiles']); ?></h3><p><?php echo $escape($copy['compareCopy']); ?></p></article><article class="about-step"><h3><?php echo $escape($copy['confirm']); ?></h3><p><?php echo $escape($copy['confirmCopy']); ?></p></article></div>
    </section>

    <section class="about-cta about-reveal"><div class="about-cta-copy"><h2><?php echo $escape($copy['ctaHeading']); ?></h2><p><?php echo $escape($copy['ctaCopy']); ?></p></div><a class="about-btn" href="<?php echo $escape($contactPath); ?>"><?php echo $escape($copy['contact']); ?> <i class="ph ph-arrow-right"></i></a></section>
  </div>
</main>
<?php require __DIR__ . '/Tem/footer.php'; ?>
<script>
  (() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), {threshold:.08});
    document.querySelectorAll('.about-reveal').forEach((item) => observer.observe(item));
  })();
</script>
<?php front_editor_render('about'); ?>
</body>
</html>
