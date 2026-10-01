<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_directory.php';
require_once __DIR__ . '/front_admin.php';
if (function_exists('admin_front_session_boot')) admin_front_session_boot();
$locale = site_page_locale('about');
$isEnglish = $locale === 'en';
$aboutEscape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$aboutCopy = $isEnglish ? [
    'home' => 'Home', 'name' => 'About MedReview', 'breadcrumb' => 'Breadcrumb',
    'titleOne' => 'Understand more.', 'titleTwo' => 'Choose with confidence.',
    'intro' => 'A healthcare choice deserves a little more understanding. We bring information and shared experiences together, so you can find care that fits you.',
    'explore' => 'Explore MedReview', 'readStory' => 'Our story',
    'photoAlt' => 'A family in a bright healthcare setting — illustrative image', 'photoCaption' => 'More understanding. A little more peace of mind.', 'illustration' => 'Illustrative image',
    'noteOne' => 'Information that makes sense', 'noteOneSub' => 'Profiles · Services · Experiences',
    'noteTwo' => 'Your choice comes first', 'noteTwoSub' => 'Find the care that fits your needs',
    'dataLabel' => 'Published on MedReview', 'facilities' => 'healthcare facilities', 'doctors' => 'doctor profiles', 'reviews' => 'community reviews', 'lists' => 'curated lists',
    'pageNavigation' => 'Explore this page', 'storyNav' => 'Our story', 'valuesNav' => 'Values & how it works', 'contactNav' => 'Contact & feedback', 'providerNav' => 'For healthcare providers',
    'storyEyebrow' => 'Our story', 'storyHeading' => 'Healthcare starts with a question. And a need to feel reassured.',
    'storyLead' => 'Where should I go? What do I need to know? How have other people experienced it?',
    'storyOne' => 'These questions matter, whether you are looking after yourself or someone you love. Yet information about clinics, hospitals and doctors is often scattered across different places and can be difficult to compare.',
    'storyTwo' => 'MedReview was built to make that first step clearer. We bring provider profiles, services, reference information, reviews and curated lists into one place — giving you more context before you get in touch.',
    'storyThree' => 'We believe useful information is not just something to read. It helps you ask better questions, understand your options and make a choice that fits your needs.',
    'quote' => 'Behind every search is a person who wants to feel a little more reassured.', 'quoteLabel' => 'The idea behind MedReview',
    'promiseTitle' => 'More information. More understanding.', 'promiseCopy' => 'For you, and for the people you care about.',
    'valuesEyebrow' => 'Our values', 'valuesHeading' => 'Useful information. Thoughtful principles.',
    'valuesIntro' => 'We focus on how information is presented, how experiences are understood and how profiles can grow over time.',
    'valueOne' => 'Clarity', 'valueOneCopy' => 'Organize profiles into familiar sections, making services, locations and contact details easier to find.',
    'valueTwo' => 'Context', 'valueTwoCopy' => 'Present ratings alongside review counts and available details, rather than leaving a number to tell the whole story.',
    'valueThree' => 'Respect for experiences', 'valueThreeCopy' => 'Shared experiences add a personal perspective. They help you explore, while leaving the final choice with you.',
    'valueFour' => 'Room to improve', 'valueFourCopy' => 'Information changes. We welcome corrections and updates from the community and healthcare providers.',
    'methodEyebrow' => 'How it works', 'methodHeading' => 'A clearer path to your next step.',
    'methodOne' => 'Find what you need', 'methodOneCopy' => 'Search by service, specialty or location to discover relevant facilities and doctors.',
    'methodTwo' => 'Understand your options', 'methodTwoCopy' => 'Read profiles, available reviews and curated lists to build a fuller picture.',
    'methodThree' => 'Connect and confirm', 'methodThreeCopy' => 'Contact the provider to confirm services, fees and availability before your visit.',
    'methodLink' => 'Start exploring',
    'referenceTitle' => 'A starting point, with the right context.',
    'referenceCopy' => 'MedReview provides information for reference. Profiles and reviews do not replace medical advice, diagnosis or treatment. Confirm current details with the provider and discuss treatment with an appropriate healthcare professional.',
    'contactEyebrow' => 'Contact & feedback', 'contactHeading' => 'Every helpful note makes MedReview better.',
    'contactIntro' => 'A detail to correct, an idea to share, or a profile to update? Here is where we can start the conversation.',
    'communityLabel' => 'For our community', 'communityHeading' => 'We would love to hear from you.',
    'communityCopy' => 'Help us improve an experience, point out a detail that needs updating, or suggest what you would like to see next.', 'communityLink' => 'Send feedback',
    'providerLabel' => 'For healthcare providers', 'providerHeading' => 'Help people understand your care.',
    'providerCopy' => 'Get in touch to provide or correct profile details, services, contact information and other useful updates about your facility.',
    'providerPointOne' => 'Clearer provider profiles', 'providerPointTwo' => 'Current service & contact details', 'providerPointThree' => 'A conversation about better information', 'providerLink' => 'Contact MedReview',
    'closing' => 'A little more understanding today. A more confident choice tomorrow.', 'closingLink' => 'Find healthcare facilities',
] : [
    'home' => 'Trang chủ', 'name' => 'Về MedReview', 'breadcrumb' => 'Đường dẫn trang',
    'titleOne' => 'Hiểu rõ hơn.', 'titleTwo' => 'Lựa chọn an tâm hơn.',
    'intro' => 'Một lựa chọn sức khỏe xứng đáng được tìm hiểu kỹ hơn. Chúng tôi kết nối thông tin và trải nghiệm, để bạn tìm được nơi chăm sóc phù hợp với mình.',
    'explore' => 'Khám phá MedReview', 'readStory' => 'Câu chuyện của chúng tôi',
    'photoAlt' => 'Gia đình trong không gian y tế sáng, thân thiện — ảnh minh họa', 'photoCaption' => 'Thêm thấu hiểu. Thêm một chút an tâm.', 'illustration' => 'Ảnh minh họa',
    'noteOne' => 'Thông tin dễ hiểu hơn', 'noteOneSub' => 'Hồ sơ · Dịch vụ · Trải nghiệm',
    'noteTwo' => 'Lựa chọn nằm ở bạn', 'noteTwoSub' => 'Tìm nơi chăm sóc phù hợp với mình',
    'dataLabel' => 'Đã được công bố trên MedReview', 'facilities' => 'cơ sở y tế', 'doctors' => 'hồ sơ bác sĩ', 'reviews' => 'đánh giá cộng đồng', 'lists' => 'danh sách chọn lọc',
    'pageNavigation' => 'Khám phá nội dung trang', 'storyNav' => 'Câu chuyện của chúng tôi', 'valuesNav' => 'Giá trị & cách hoạt động', 'contactNav' => 'Liên hệ & góp ý', 'providerNav' => 'Dành cho cơ sở y tế',
    'storyEyebrow' => 'Câu chuyện của chúng tôi', 'storyHeading' => 'Chăm sóc sức khỏe bắt đầu từ một câu hỏi. Và mong muốn được an tâm.',
    'storyLead' => 'Nên khám ở đâu? Cần biết những gì? Trải nghiệm của người khác ra sao?',
    'storyOne' => 'Đó là những câu hỏi quen thuộc khi bạn chăm sóc bản thân hay một người mình yêu thương. Nhưng thông tin về phòng khám, bệnh viện và bác sĩ thường nằm ở nhiều nơi, khó tìm và khó đối chiếu.',
    'storyTwo' => 'MedReview được xây dựng để bước đầu tiên ấy trở nên rõ ràng hơn. Chúng tôi tập hợp hồ sơ cơ sở, dịch vụ, thông tin tham khảo, đánh giá và danh sách chọn lọc tại một nơi — giúp bạn có thêm góc nhìn trước khi liên hệ.',
    'storyThree' => 'Chúng tôi tin rằng thông tin hữu ích không chỉ để đọc. Nó giúp bạn đặt câu hỏi đúng hơn, hiểu các lựa chọn và tìm được nơi phù hợp với nhu cầu của mình.',
    'quote' => 'Đằng sau mỗi lượt tìm kiếm là một người đang mong được an tâm hơn.', 'quoteLabel' => 'Điều MedReview luôn hướng tới',
    'promiseTitle' => 'Thêm thông tin. Thêm thấu hiểu.', 'promiseCopy' => 'Cho bạn, và cho những người bạn quan tâm.',
    'valuesEyebrow' => 'Giá trị của chúng tôi', 'valuesHeading' => 'Thông tin hữu ích. Từ những nguyên tắc rõ ràng.',
    'valuesIntro' => 'Chúng tôi chú trọng cách trình bày thông tin, cách nhìn nhận trải nghiệm và khả năng bổ sung hồ sơ theo thời gian.',
    'valueOne' => 'Rõ ràng', 'valueOneCopy' => 'Sắp xếp hồ sơ theo từng nhóm dễ hiểu, để bạn dễ tìm dịch vụ, địa chỉ và thông tin liên hệ cần thiết.',
    'valueTwo' => 'Có ngữ cảnh', 'valueTwoCopy' => 'Đặt điểm số bên cạnh số lượt đánh giá và thông tin liên quan, giúp bạn nhìn nhận đầy đủ hơn một con số.',
    'valueThree' => 'Tôn trọng trải nghiệm', 'valueThreeCopy' => 'Mỗi chia sẻ mang thêm một góc nhìn. Trải nghiệm giúp bạn tham khảo, còn lựa chọn phù hợp vẫn nằm ở bạn.',
    'valueFour' => 'Sẵn sàng cập nhật', 'valueFourCopy' => 'Thông tin có thể thay đổi. Chúng tôi đón nhận góp ý và thông tin bổ sung từ cộng đồng, cơ sở y tế.',
    'methodEyebrow' => 'Cách MedReview hoạt động', 'methodHeading' => 'Một hành trình rõ ràng hơn, từng bước.',
    'methodOne' => 'Tìm đúng nhu cầu', 'methodOneCopy' => 'Tìm theo dịch vụ, chuyên khoa hoặc địa điểm để khám phá các cơ sở và bác sĩ phù hợp.',
    'methodTwo' => 'Hiểu các lựa chọn', 'methodTwoCopy' => 'Đọc hồ sơ, đánh giá hiện có và danh sách chọn lọc để có thêm góc nhìn trước khi lựa chọn.',
    'methodThree' => 'Kết nối & xác nhận', 'methodThreeCopy' => 'Liên hệ trực tiếp với cơ sở để xác nhận dịch vụ, chi phí và thời gian phù hợp trước khi đến.',
    'methodLink' => 'Bắt đầu tìm hiểu',
    'referenceTitle' => 'Một điểm bắt đầu, với thông tin đúng ngữ cảnh.',
    'referenceCopy' => 'MedReview cung cấp thông tin tham khảo. Hồ sơ và đánh giá không thay thế tư vấn, chẩn đoán hay điều trị y tế. Hãy xác nhận thông tin mới nhất với cơ sở và trao đổi cùng chuyên môn y tế phù hợp trước khi điều trị.',
    'contactEyebrow' => 'Liên hệ & góp ý', 'contactHeading' => 'Mỗi góp ý, một bước tốt hơn.',
    'contactIntro' => 'Một thông tin cần sửa, một ý tưởng muốn chia sẻ, hay một hồ sơ cần cập nhật? Hãy bắt đầu cuộc trò chuyện cùng MedReview.',
    'communityLabel' => 'Dành cho cộng đồng', 'communityHeading' => 'Chúng tôi luôn sẵn sàng lắng nghe.',
    'communityCopy' => 'Góp ý về trải nghiệm sử dụng, báo thông tin cần cập nhật hoặc chia sẻ điều bạn mong muốn MedReview làm tốt hơn.', 'communityLink' => 'Gửi góp ý',
    'providerLabel' => 'Dành cho cơ sở y tế', 'providerHeading' => 'Để người dùng hiểu hơn về nơi chăm sóc của bạn.',
    'providerCopy' => 'Kết nối với MedReview để cung cấp hoặc điều chỉnh hồ sơ, dịch vụ, thông tin liên hệ và những cập nhật hữu ích về cơ sở.',
    'providerPointOne' => 'Hồ sơ được trình bày rõ ràng', 'providerPointTwo' => 'Cập nhật dịch vụ & thông tin liên hệ', 'providerPointThree' => 'Cùng xây dựng thông tin hữu ích', 'providerLink' => 'Kết nối với MedReview',
    'closing' => 'Thêm thấu hiểu hôm nay. Thêm an tâm khi lựa chọn.', 'closingLink' => 'Tìm cơ sở y tế',
];

$aboutCount = static function (string $table): int {
    try {
        $pdo = db();
        if (medical_directory_table_exists($pdo, $table)) {
            return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'published'")->fetchColumn();
        }
    } catch (Throwable $e) {
        // Counts are optional; never substitute invented statistics.
    }
    return 0;
};
$aboutStats = [];
foreach (['medical_facilities' => 'facilities', 'medical_doctors' => 'doctors', 'medical_reviews' => 'reviews', 'medical_toplists' => 'lists'] as $table => $label) {
    $count = $aboutCount($table);
    if ($count > 0) $aboutStats[] = ['value' => $count, 'label' => $aboutCopy[$label]];
}
$aboutValues = [
    ['icon' => 'ph-text-align-left', 'title' => 'valueOne', 'copy' => 'valueOneCopy', 'tone' => 'blue'],
    ['icon' => 'ph-scales', 'title' => 'valueTwo', 'copy' => 'valueTwoCopy', 'tone' => 'green'],
    ['icon' => 'ph-heart', 'title' => 'valueThree', 'copy' => 'valueThreeCopy', 'tone' => 'rose'],
    ['icon' => 'ph-arrows-clockwise', 'title' => 'valueFour', 'copy' => 'valueFourCopy', 'tone' => 'sand'],
];
$aboutSteps = [
    ['icon' => 'ph-magnifying-glass', 'title' => 'methodOne', 'copy' => 'methodOneCopy'],
    ['icon' => 'ph-book-open-text', 'title' => 'methodTwo', 'copy' => 'methodTwoCopy'],
    ['icon' => 'ph-chat-circle-dots', 'title' => 'methodThree', 'copy' => 'methodThreeCopy'],
];
$aboutFacilitiesPath = site_localized_path(medical_public_facility_path(), $locale);
$aboutContactPath = front_editor_page_public_path($isEnglish ? 'contact-en' : 'contact');
$aboutCanonicalPath = site_localized_path('/ve-chung-toi.php', $locale);
$seo = front_editor_page_seo('about', [
    'title' => 'Về MedReview | Câu chuyện, giá trị & cách hoạt động',
    'description' => 'Tìm hiểu câu chuyện, giá trị và cách MedReview kết nối thông tin cơ sở y tế, bác sĩ và trải nghiệm cộng đồng. Liên hệ, góp ý và cập nhật hồ sơ cơ sở.',
    'canonical_path' => '/ve-chung-toi.php',
]);
$aboutTitle = $isEnglish ? 'About MedReview | Our story, values & how it works' : trim((string) ($seo['title'] ?? ''));
$aboutDescription = $isEnglish ? 'Discover the story, values and approach behind MedReview. Explore healthcare information, share feedback and connect with us to update provider profiles.' : trim((string) ($seo['description'] ?? ''));
if ($aboutTitle === '' || stripos($aboutTitle, 'top dental') !== false) $aboutTitle = 'Về MedReview | Câu chuyện, giá trị & cách hoạt động';
if ($aboutDescription === '' || stripos($aboutDescription, 'top dental') !== false) $aboutDescription = 'Tìm hiểu câu chuyện, giá trị và cách MedReview giúp bạn khám phá thông tin y tế. Kết nối, góp ý và cập nhật hồ sơ cơ sở y tế.';
$aboutStyleVersion = (string) filemtime(__DIR__ . '/assets/css/pages/about-medreview.css');
$aboutScriptVersion = (string) filemtime(__DIR__ . '/assets/js/about-medreview.js');
?>
<!doctype html>
<html lang="<?php echo $aboutEscape($locale); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo site_favicon_tags(); ?>
    <title><?php echo $aboutEscape($aboutTitle); ?></title>
    <meta name="description" content="<?php echo $aboutEscape($aboutDescription); ?>">
    <link rel="canonical" href="<?php echo $aboutEscape(site_absolute_url($aboutCanonicalPath)); ?>">
    <link rel="alternate" hreflang="vi" href="<?php echo $aboutEscape(site_absolute_url('/ve-chung-toi.php')); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo $aboutEscape(site_absolute_url(site_localized_path('/ve-chung-toi.php', 'en'))); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo $aboutEscape(site_absolute_url('/ve-chung-toi.php')); ?>">
    <?php echo site_json_ld([
        '@context' => 'https://schema.org', '@type' => 'AboutPage', 'name' => $aboutTitle,
        'description' => $aboutDescription, 'url' => site_absolute_url($aboutCanonicalPath),
        'inLanguage' => $locale === 'en' ? 'en' : 'vi-VN',
        'about' => ['@type' => 'Organization', 'name' => 'MedReview', 'url' => site_absolute_url('/')],
    ]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700;750;800&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg">
    <link rel="stylesheet" href="/assets/css/pages/about-medreview.css?v=<?php echo $aboutEscape($aboutStyleVersion); ?>">
    <script defer src="/assets/js/about-medreview.js?v=<?php echo $aboutEscape($aboutScriptVersion); ?>"></script>
</head>
<body class="site-about">
<?php require __DIR__ . '/Tem/header.php'; ?>
<main class="mr-about">
    <section class="mr-about__hero" aria-labelledby="about-title">
        <div class="mr-about__shell">
            <nav class="mr-about__breadcrumb" aria-label="<?php echo $aboutEscape($aboutCopy['breadcrumb']); ?>">
                <a href="<?php echo $aboutEscape(site_localized_path('/', $locale)); ?>"><?php echo $aboutEscape($aboutCopy['home']); ?></a>
                <i class="ph ph-caret-right" aria-hidden="true"></i><span aria-current="page"><?php echo $aboutEscape($aboutCopy['name']); ?></span>
            </nav>
            <div class="mr-about__hero-grid">
                <div class="mr-about__hero-copy" data-about-reveal>
                    <p class="mr-about__eyebrow"><span class="mr-about__dot" aria-hidden="true"></span><?php echo $aboutEscape($aboutCopy['name']); ?></p>
                    <h1 id="about-title"><?php echo $aboutEscape($aboutCopy['titleOne']); ?><br><span><?php echo $aboutEscape($aboutCopy['titleTwo']); ?></span></h1>
                    <p class="mr-about__intro"><?php echo $aboutEscape($aboutCopy['intro']); ?></p>
                    <div class="mr-about__actions">
                        <a class="mr-about__button" href="<?php echo $aboutEscape($aboutFacilitiesPath); ?>"><?php echo $aboutEscape($aboutCopy['explore']); ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                        <a class="mr-about__text-link" href="#cau-chuyen"><?php echo $aboutEscape($aboutCopy['readStory']); ?><i class="ph ph-arrow-down" aria-hidden="true"></i></a>
                    </div>
                </div>
                <div class="mr-about__hero-art" data-about-reveal>
                    <span class="mr-about__orbit" aria-hidden="true"></span>
                    <figure class="mr-about__photo">
                        <img src="/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg" alt="<?php echo $aboutEscape($aboutCopy['photoAlt']); ?>" width="1800" height="720" fetchpriority="high" decoding="async">
                        <figcaption><span><?php echo $aboutEscape($aboutCopy['photoCaption']); ?></span><small><?php echo $aboutEscape($aboutCopy['illustration']); ?></small></figcaption>
                    </figure>
                    <div class="mr-about__note mr-about__note--top"><span class="mr-about__note-icon"><i class="ph ph-book-open-text" aria-hidden="true"></i></span><div><strong><?php echo $aboutEscape($aboutCopy['noteOne']); ?></strong><span><?php echo $aboutEscape($aboutCopy['noteOneSub']); ?></span></div></div>
                    <div class="mr-about__note mr-about__note--bottom"><span class="mr-about__note-icon"><i class="ph ph-heart" aria-hidden="true"></i></span><div><strong><?php echo $aboutEscape($aboutCopy['noteTwo']); ?></strong><span><?php echo $aboutEscape($aboutCopy['noteTwoSub']); ?></span></div></div>
                </div>
            </div>
            <?php if ($aboutStats): ?>
                <div class="mr-about__stats" aria-label="<?php echo $aboutEscape($aboutCopy['dataLabel']); ?>">
                    <p class="mr-about__stats-label"><i class="ph ph-circles-four" aria-hidden="true"></i><?php echo $aboutEscape($aboutCopy['dataLabel']); ?></p>
                    <dl><?php foreach ($aboutStats as $stat): ?><div><dt><?php echo $aboutEscape($stat['label']); ?></dt><dd><?php echo $aboutEscape(medical_directory_format_int($stat['value'])); ?></dd></div><?php endforeach; ?></dl>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <nav class="mr-about__navigation" aria-label="<?php echo $aboutEscape($aboutCopy['pageNavigation']); ?>">
        <div class="mr-about__shell mr-about__nav-items">
            <a href="#cau-chuyen" aria-current="location"><span>01</span><?php echo $aboutEscape($aboutCopy['storyNav']); ?></a>
            <a href="#gia-tri"><span>02</span><?php echo $aboutEscape($aboutCopy['valuesNav']); ?></a>
            <a href="#lien-he"><span>03</span><?php echo $aboutEscape($aboutCopy['contactNav']); ?></a>
            <a href="#co-so-y-te"><span>04</span><?php echo $aboutEscape($aboutCopy['providerNav']); ?></a>
        </div>
    </nav>
    <section class="mr-about__section mr-about__story mr-about__shell" id="cau-chuyen" aria-labelledby="story-title">
        <div class="mr-about__story-grid">
            <header data-about-reveal>
                <p class="mr-about__eyebrow"><?php echo $aboutEscape($aboutCopy['storyEyebrow']); ?></p>
                <h2 id="story-title"><?php echo $aboutEscape($aboutCopy['storyHeading']); ?></h2>
                <div class="mr-about__story-sign"><span class="mr-about__sign-icon"><i class="ph ph-heartbeat" aria-hidden="true"></i></span><div><strong><?php echo $aboutEscape($aboutCopy['promiseTitle']); ?></strong><p><?php echo $aboutEscape($aboutCopy['promiseCopy']); ?></p></div></div>
            </header>
            <div class="mr-about__story-text" data-about-reveal>
                <p class="mr-about__lead"><?php echo $aboutEscape($aboutCopy['storyLead']); ?></p>
                <p><?php echo $aboutEscape($aboutCopy['storyOne']); ?></p><p><?php echo $aboutEscape($aboutCopy['storyTwo']); ?></p><p><?php echo $aboutEscape($aboutCopy['storyThree']); ?></p>
            </div>
        </div>
        <figure class="mr-about__quote" data-about-reveal>
            <span class="mr-about__quote-icon" aria-hidden="true"><i class="ph-fill ph-quotes"></i></span>
            <div><blockquote><?php echo $aboutEscape($aboutCopy['quote']); ?></blockquote><figcaption><?php echo $aboutEscape($aboutCopy['quoteLabel']); ?></figcaption></div>
            <span class="mr-about__quote-heart" aria-hidden="true"><i class="ph ph-heart"></i></span>
        </figure>
    </section>
    <section class="mr-about__values-band" id="gia-tri" aria-labelledby="values-title">
        <div class="mr-about__shell mr-about__section">
            <header class="mr-about__section-heading" data-about-reveal>
                <div><p class="mr-about__eyebrow"><?php echo $aboutEscape($aboutCopy['valuesEyebrow']); ?></p><h2 id="values-title"><?php echo $aboutEscape($aboutCopy['valuesHeading']); ?></h2></div>
                <p><?php echo $aboutEscape($aboutCopy['valuesIntro']); ?></p>
            </header>
            <div class="mr-about__values">
                <?php foreach ($aboutValues as $index => $value): ?>
                    <article class="mr-about__value mr-about__value--<?php echo $aboutEscape($value['tone']); ?>" data-about-reveal>
                        <div class="mr-about__value-top"><span class="mr-about__value-icon"><i class="ph <?php echo $aboutEscape($value['icon']); ?>" aria-hidden="true"></i></span><span class="mr-about__value-number" aria-hidden="true">0<?php echo $index + 1; ?></span></div>
                        <h3><?php echo $aboutEscape($aboutCopy[$value['title']]); ?></h3><p><?php echo $aboutEscape($aboutCopy[$value['copy']]); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="mr-about__method" aria-labelledby="method-title">
                <header class="mr-about__method-heading" data-about-reveal><div><p class="mr-about__eyebrow"><?php echo $aboutEscape($aboutCopy['methodEyebrow']); ?></p><h2 id="method-title"><?php echo $aboutEscape($aboutCopy['methodHeading']); ?></h2></div><a class="mr-about__text-link" href="<?php echo $aboutEscape($aboutFacilitiesPath); ?>"><?php echo $aboutEscape($aboutCopy['methodLink']); ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a></header>
                <ol class="mr-about__steps">
                    <?php foreach ($aboutSteps as $index => $step): ?>
                        <li data-about-reveal><div class="mr-about__step-top"><span class="mr-about__step-icon"><i class="ph <?php echo $aboutEscape($step['icon']); ?>" aria-hidden="true"></i><small aria-hidden="true">0<?php echo $index + 1; ?></small></span></div><h3><?php echo $aboutEscape($aboutCopy[$step['title']]); ?></h3><p><?php echo $aboutEscape($aboutCopy[$step['copy']]); ?></p></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <aside class="mr-about__reference" data-about-reveal><i class="ph ph-info" aria-hidden="true"></i><div><h3><?php echo $aboutEscape($aboutCopy['referenceTitle']); ?></h3><p><?php echo $aboutEscape($aboutCopy['referenceCopy']); ?></p></div></aside>
        </div>
    </section>
    <section class="mr-about__section mr-about__connect mr-about__shell" id="lien-he" aria-labelledby="contact-title">
        <header class="mr-about__section-heading" data-about-reveal><div><p class="mr-about__eyebrow"><?php echo $aboutEscape($aboutCopy['contactEyebrow']); ?></p><h2 id="contact-title"><?php echo $aboutEscape($aboutCopy['contactHeading']); ?></h2></div><p><?php echo $aboutEscape($aboutCopy['contactIntro']); ?></p></header>
        <div class="mr-about__connect-grid">
            <article class="mr-about__community" data-about-reveal>
                <div class="mr-about__conversation" aria-hidden="true"><span><i class="ph ph-chat-centered-text"></i></span><span><i class="ph ph-heart"></i></span><span><i class="ph ph-sparkle"></i></span></div>
                <p class="mr-about__eyebrow"><?php echo $aboutEscape($aboutCopy['communityLabel']); ?></p><h3><?php echo $aboutEscape($aboutCopy['communityHeading']); ?></h3><p><?php echo $aboutEscape($aboutCopy['communityCopy']); ?></p>
                <a class="mr-about__button mr-about__button--white" href="<?php echo $aboutEscape($aboutContactPath); ?>"><?php echo $aboutEscape($aboutCopy['communityLink']); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
            </article>
            <article class="mr-about__provider" id="co-so-y-te" data-about-reveal>
                <span class="mr-about__provider-icon"><i class="ph ph-hospital" aria-hidden="true"></i></span>
                <p class="mr-about__eyebrow"><?php echo $aboutEscape($aboutCopy['providerLabel']); ?></p><h3><?php echo $aboutEscape($aboutCopy['providerHeading']); ?></h3><p><?php echo $aboutEscape($aboutCopy['providerCopy']); ?></p>
                <ul><?php foreach (['providerPointOne', 'providerPointTwo', 'providerPointThree'] as $key): ?><li><i class="ph ph-check-circle" aria-hidden="true"></i><?php echo $aboutEscape($aboutCopy[$key]); ?></li><?php endforeach; ?></ul>
                <a class="mr-about__text-link" href="<?php echo $aboutEscape($aboutContactPath); ?>"><?php echo $aboutEscape($aboutCopy['providerLink']); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
            </article>
        </div>
    </section>
    <div class="mr-about__closing"><div class="mr-about__shell"><p><?php echo $aboutEscape($aboutCopy['closing']); ?></p><a class="mr-about__text-link" href="<?php echo $aboutEscape($aboutFacilitiesPath); ?>"><?php echo $aboutEscape($aboutCopy['closingLink']); ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a></div></div>
</main>
<?php require __DIR__ . '/Tem/footer.php'; ?>
<?php front_editor_render('about'); ?>
</body>
</html>
