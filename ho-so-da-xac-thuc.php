<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$GLOBALS['site_page_key'] = 'verification';
$locale = site_page_locale('verification');
$en = $locale === 'en';
$t = static fn (string $vi, string $english): string => $en ? $english : $vi;
$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$path = site_localized_path('/ho-so-da-xac-thuc', $locale);
$contact = front_editor_page_public_path($en ? 'contact-en' : 'contact');
$title = $t('Hồ sơ cơ sở đã xác thực', 'Verified facility profiles');
$description = $t('Hiểu ý nghĩa nhãn xác thực trên MedReview, các yếu tố cần đối chiếu và giới hạn của thông tin hồ sơ cơ sở y tế.', 'Understand the verified label on MedReview, the information to cross-check and the limits of healthcare facility profiles.');
$criteria = [
    ['ph-buildings', $t('Đúng cơ sở, đúng địa điểm', 'The right facility and location'), $t('Tên cơ sở, địa chỉ và chi nhánh cần nhất quán. Cơ sở cùng tên hoặc cùng hệ thống không đồng nghĩa là cùng một địa điểm.', 'The name, address and branch should be consistent. Facilities with the same name or brand are not necessarily the same location.'), $t('Đối chiếu: website cơ sở, Google Maps, thông tin liên hệ.', 'Cross-check: the facility website, Google Maps and contact details.')],
    ['ph-phone', $t('Kênh liên hệ rõ ràng', 'Clear contact channels'), $t('Số điện thoại, website và kênh công khai giúp người dùng xác nhận thông tin trực tiếp. Giờ làm việc và lịch khám có thể thay đổi.', 'Phone numbers, websites and public channels help you confirm details directly. Opening hours and appointment schedules may change.'), $t('Đối chiếu: các kênh chính thức và phản hồi từ cơ sở.', 'Cross-check: official channels and information from the facility.')],
    ['ph-stethoscope', $t('Dịch vụ & đội ngũ có căn cứ', 'Services and team information'), $t('Chuyên khoa, dịch vụ và tên bác sĩ cần có nguồn phù hợp với cơ sở. Không suy ra năng lực, bằng cấp hay phạm vi hành nghề chỉ từ quảng cáo.', 'Specialties, services and doctor names need sources relevant to the facility. Advertising alone does not establish qualifications or scope of practice.'), $t('Đối chiếu: hồ sơ chuyên môn và thông tin được công bố.', 'Cross-check: professional profiles and published information.')],
    ['ph-image', $t('Ảnh đúng ngữ cảnh', 'Images in the right context'), $t('Ảnh mặt tiền, khu tiếp đón, phòng khám, thiết bị và đội ngũ cần thể hiện đúng cơ sở. Ảnh minh họa, logo hoặc nội dung quảng cáo không phải bằng chứng về cơ sở vật chất.', 'Exterior, reception, clinical space, equipment and team photos should match the facility. Illustrations, logos and advertising do not prove its physical facilities.'), $t('Đối chiếu: địa điểm, biển hiệu và nguồn ảnh thực tế.', 'Cross-check: the location, signage and actual photo source.')],
    ['ph-file-text', $t('Giấy phép khi có bằng chứng', 'Licensing when evidence is available'), $t('Thông tin giấy phép chỉ nên trình bày khi có tài liệu hoặc nguồn công khai có thể đối chiếu. Không có thông tin trong hồ sơ không có nghĩa là cơ sở không được cấp phép.', 'Licensing details should be presented only when supported by documents or identifiable public sources. Missing details in a profile do not mean that a facility is unlicensed.'), $t('Đối chiếu: tài liệu được cung cấp hoặc nguồn cơ quan có thẩm quyền.', 'Cross-check: supplied documents or relevant authority sources.')],
    ['ph-arrows-clockwise', $t('Nguồn & khả năng cập nhật', 'Sources and updates'), $t('Cần xem nguồn, thời điểm công bố và các chi tiết còn chưa rõ. Thông tin mới từ cơ sở hoặc người dùng là căn cứ để rà soát và điều chỉnh hồ sơ.', 'Consider sources, publication dates and unresolved details. New information from providers or users can help a profile be reviewed and corrected.'), $t('Đối chiếu: bằng chứng cập nhật, không chỉ độ đầy đủ của bài viết.', 'Cross-check: current evidence, not just how complete an article looks.')],
];
$faq = [
    [$t('Có nhãn xác thực nghĩa là cơ sở đã được kiểm tra mọi thông tin?', 'Does the label mean every detail has been checked?'), $t('Không. Nhãn trên MedReview là trạng thái hồ sơ do quản trị đánh dấu. Hiện nhãn không kèm bảng bằng chứng cho từng tiêu chí, nên không nên hiểu là mọi dịch vụ, bác sĩ, giấy phép và ảnh đều đã được xác nhận riêng.', 'No. The label is a profile status set by an administrator. It does not currently include an evidence checklist for each criterion, so it should not be taken as individual confirmation of every service, doctor, licence or photo.')],
    [$t('Xác thực hồ sơ có đồng nghĩa với chất lượng điều trị tốt?', 'Does profile verification guarantee good treatment?'), $t('Không. Thông tin hồ sơ và chất lượng điều trị là hai vấn đề khác nhau. Nhãn không phải xếp hạng chuyên môn, chứng nhận an toàn hay cam kết kết quả điều trị.', 'No. Profile information and treatment quality are different matters. The label is not a clinical ranking, safety certification or promise of a treatment outcome.')],
    [$t('Điểm đánh giá cao có phải là điều kiện xác thực?', 'Is a high rating a verification requirement?'), $t('Điểm số và số lượt đánh giá cung cấp thêm ngữ cảnh tham khảo, không thay thế việc đối chiếu danh tính, nguồn thông tin hoặc tài liệu. Nhãn hồ sơ cũng không có nghĩa là mọi đánh giá của người dùng đã được xác thực.', 'Ratings and review counts provide context, but do not replace identity, source or document checks. The profile label also does not mean every user review has been verified.')],
    [$t('Nếu thấy thông tin sai hoặc cũ, tôi cần làm gì?', 'What should I do if information is wrong or outdated?'), $t('Gửi link hồ sơ, chi tiết cần sửa và nguồn đối chiếu qua trang liên hệ. Nếu gửi ảnh hoặc tài liệu, hãy che thông tin cá nhân của bệnh nhân và các dữ liệu không cần thiết.', 'Send the profile link, the detail to correct and supporting sources through our contact page. Remove patient identifiers and unnecessary personal information from any images or documents.')],
];
?>
<!doctype html>
<html lang="<?php echo $en ? 'en' : 'vi'; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $esc($title . ' | MedReview'); ?></title>
    <meta name="description" content="<?php echo $esc($description); ?>">
    <link rel="canonical" href="<?php echo $esc(site_absolute_url($path)); ?>">
    <link rel="alternate" hreflang="vi" href="<?php echo $esc(site_absolute_url('/ho-so-da-xac-thuc')); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo $esc(site_absolute_url('/en/ho-so-da-xac-thuc')); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo $esc(site_absolute_url('/ho-so-da-xac-thuc')); ?>">
    <?php echo site_favicon_tags(); ?>
    <?php echo site_json_ld(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $title, 'description' => $description, 'url' => site_absolute_url($path), 'inLanguage' => $locale]); ?>
    <link rel="stylesheet" href="/assets/css/pages/verified-profile.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/pages/verified-profile.css'); ?>">
</head>
<body class="site-verification">
<?php require __DIR__ . '/Tem/header.php'; ?>
<main class="verification">
    <section class="vf-hero" aria-labelledby="verification-title">
        <div class="vf-shell">
            <nav class="vf-breadcrumb" aria-label="<?php echo $esc($t('Đường dẫn trang', 'Breadcrumb')); ?>"><a href="<?php echo $esc(site_localized_path('/', $locale)); ?>"><?php echo $t('Trang chủ', 'Home'); ?></a><i class="ph ph-caret-right" aria-hidden="true"></i><span aria-current="page"><?php echo $t('Minh bạch hồ sơ', 'Profile transparency'); ?></span></nav>
            <div class="vf-hero-grid">
                <div>
                    <p class="vf-eyebrow"><?php echo $t('Hiểu đúng. Lựa chọn rõ ràng hơn.', 'Understand the label. Make an informed choice.'); ?></p>
                    <h1 id="verification-title"><?php echo $t('Hồ sơ cơ sở<br><span>đã xác thực.</span>', 'Verified<br><span>facility profiles.</span>'); ?></h1>
                    <p class="vf-lead"><?php echo $t('Một nhãn nhỏ, cần được hiểu đúng. Tìm hiểu ý nghĩa, những yếu tố cần đối chiếu và giới hạn của nhãn trên MedReview.', 'A small label deserves a clear explanation. Understand its meaning, the information to cross-check and its limits on MedReview.'); ?></p>
                    <a class="vf-button" href="#y-nghia"><?php echo $t('Tìm hiểu về nhãn', 'Understand the label'); ?><i class="ph ph-arrow-down" aria-hidden="true"></i></a>
                </div>
                <aside class="vf-profile-preview" aria-label="<?php echo $esc($t('Minh họa ý nghĩa nhãn hồ sơ', 'Illustration of the profile label')); ?>">
                    <div class="vf-seal"><i class="ph ph-seal-check" aria-hidden="true"></i></div>
                    <p class="vf-preview-label"><?php echo $t('Nhãn trên hồ sơ', 'The profile label'); ?></p>
                    <h2><?php echo $t('Thông tin rõ hơn.<br>Không phải lời bảo chứng.', 'Clearer information.<br>Not a guarantee.'); ?></h2>
                    <div class="vf-preview-row"><i class="ph ph-check-circle" aria-hidden="true"></i><?php echo $t('Trạng thái thông tin hồ sơ', 'A profile information status'); ?></div>
                    <div class="vf-preview-row"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><?php echo $t('Luôn cần đối chiếu chi tiết', 'Details still need cross-checking'); ?></div>
                    <span class="vf-preview-foot"><?php echo $t('Minh bạch về phạm vi xác thực', 'Transparency about verification scope'); ?></span>
                </aside>
            </div>
        </div>
    </section>
    <nav class="vf-section-nav vf-shell" aria-label="<?php echo $esc($t('Nội dung trang', 'On this page')); ?>">
        <a href="#y-nghia"><?php echo $t('Ý nghĩa của nhãn', 'What the label means'); ?></a><a href="#tieu-chi"><?php echo $t('Yếu tố đối chiếu', 'What to cross-check'); ?></a><a href="#gioi-han"><?php echo $t('Phạm vi & giới hạn', 'Scope and limits'); ?></a><a href="#cau-hoi"><?php echo $t('Câu hỏi thường gặp', 'Common questions'); ?></a>
    </nav>
    <section class="vf-section vf-shell vf-meaning" id="y-nghia" aria-labelledby="meaning-title">
        <div><p class="vf-eyebrow"><?php echo $t('01 / Ý nghĩa', '01 / Meaning'); ?></p><h2 id="meaning-title"><?php echo $t('Xác thực thông tin.<br>Không thay bạn quyết định.', 'Information context.<br>Your choice remains yours.'); ?></h2></div>
        <div class="vf-prose"><p><?php echo $t('“Hồ sơ cơ sở đã xác thực” là nhãn trạng thái hiển thị trên hồ sơ cơ sở y tế tại MedReview. Nhãn giúp phân biệt trạng thái thông tin của hồ sơ; không phải giấy chứng nhận của cơ quan quản lý hay kết luận về chất lượng chuyên môn.', '“Verified facility profile” is a status label displayed on a healthcare facility profile on MedReview. It describes the profile’s information status; it is not a certificate from a regulatory authority or a conclusion about clinical quality.'); ?></p>
            <div class="vf-note"><i class="ph ph-info" aria-hidden="true"></i><div><strong><?php echo $t('Phạm vi hiện tại của nhãn', 'The current scope of the label'); ?></strong><p><?php echo $t('Nhãn do quản trị đánh dấu. Hiện chưa có bảng bằng chứng theo từng tiêu chí đi kèm nhãn. Đừng xem dấu xác thực là xác nhận rằng mọi thông tin trong hồ sơ đã được kiểm tra riêng.', 'An administrator sets the label. It does not currently include an evidence checklist for each criterion. Do not take the badge as confirmation that every profile detail has been individually checked.'); ?></p></div></div>
        </div>
    </section>
    <section class="vf-criteria" id="tieu-chi" aria-labelledby="criteria-title"><div class="vf-shell vf-section">
        <div class="vf-section-heading"><div><p class="vf-eyebrow"><?php echo $t('02 / Yếu tố đối chiếu', '02 / Information checks'); ?></p><h2 id="criteria-title"><?php echo $t('Một hồ sơ đáng tin,<br>cần những thông tin có căn cứ.', 'A useful profile starts<br>with supported information.'); ?></h2></div><p><?php echo $t('Đây là các nhóm thông tin nên được đối chiếu khi rà soát hồ sơ, không phải danh sách đã hoàn tất cho mọi cơ sở có nhãn.', 'These are areas to cross-check when reviewing a profile, not a checklist completed for every labelled facility.'); ?></p></div>
        <div class="vf-criteria-grid"><?php foreach ($criteria as $index => [$icon, $heading, $copy, $source]): ?>
            <article class="vf-criterion"><div class="vf-card-top"><i class="ph <?php echo $esc($icon); ?>" aria-hidden="true"></i><span><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span></div><h3><?php echo $esc($heading); ?></h3><p><?php echo $esc($copy); ?></p><div class="vf-source"><?php echo $esc($source); ?></div></article>
        <?php endforeach; ?></div>
    </div></section>
    <section class="vf-section vf-shell" id="gioi-han" aria-labelledby="limits-title">
        <p class="vf-eyebrow"><?php echo $t('03 / Phạm vi & giới hạn', '03 / Scope and limits'); ?></p><h2 id="limits-title"><?php echo $t('Biết điều gì có thể hiểu.<br>Và điều gì không nên suy ra.', 'What you can understand.<br>What you should not infer.'); ?></h2>
        <div class="vf-limits-grid">
            <article class="vf-limit vf-limit--positive"><i class="ph ph-check-circle" aria-hidden="true"></i><h3><?php echo $t('Dùng hồ sơ để tìm hiểu', 'Use the profile to learn'); ?></h3><ul><li><?php echo $t('Tìm địa chỉ, kênh liên hệ và dịch vụ được giới thiệu.', 'Find addresses, contact channels and described services.'); ?></li><li><?php echo $t('So sánh thông tin cùng nguồn và ngữ cảnh của nó.', 'Compare information alongside its source and context.'); ?></li><li><?php echo $t('Chuẩn bị câu hỏi trước khi liên hệ trực tiếp với cơ sở.', 'Prepare questions before contacting the provider.'); ?></li></ul></article>
            <article class="vf-limit"><i class="ph ph-shield-warning" aria-hidden="true"></i><h3><?php echo $t('Không xem nhãn là bảo đảm', 'Do not treat it as a guarantee'); ?></h3><ul><li><?php echo $t('Không chứng nhận chất lượng hoặc kết quả điều trị.', 'It does not certify quality or treatment outcomes.'); ?></li><li><?php echo $t('Không thay thế giấy phép, tư vấn hay đánh giá chuyên môn.', 'It does not replace licences, advice or clinical assessment.'); ?></li><li><?php echo $t('Không bảo đảm giá, lịch khám và thông tin luôn còn hiệu lực.', 'It does not guarantee current fees, availability or details.'); ?></li></ul></article>
        </div>
        <div class="vf-before"><i class="ph ph-chat-circle-dots" aria-hidden="true"></i><div><strong><?php echo $t('Trước khi đến khám', 'Before your visit'); ?></strong><p><?php echo $t('Hãy xác nhận trực tiếp với cơ sở về dịch vụ, bác sĩ phụ trách, lịch hẹn, chi phí và những thông tin quan trọng với bạn.', 'Confirm the service, treating doctor, appointment, fees and any details important to you directly with the facility.'); ?></p></div></div>
    </section>
    <section class="vf-faq vf-shell vf-section" id="cau-hoi" aria-labelledby="faq-title"><div><p class="vf-eyebrow"><?php echo $t('04 / Giải đáp', '04 / Questions'); ?></p><h2 id="faq-title"><?php echo $t('Bạn có thể đang thắc mắc.', 'You may be wondering.'); ?></h2></div><div><?php foreach ($faq as [$question, $answer]): ?><details><summary><?php echo $esc($question); ?><i class="ph ph-plus" aria-hidden="true"></i></summary><p><?php echo $esc($answer); ?></p></details><?php endforeach; ?></div></section>
    <section class="vf-shell vf-contact"><div><p class="vf-eyebrow"><?php echo $t('Cùng làm thông tin rõ ràng hơn', 'Help make information clearer'); ?></p><h2><?php echo $t('Một thông tin cần sửa?<br>Hãy cho MedReview biết.', 'Something needs correcting?<br>Let MedReview know.'); ?></h2><p><?php echo $t('Gửi link hồ sơ và nguồn đối chiếu. Đừng gửi dữ liệu nhạy cảm của bệnh nhân.', 'Send the profile link and supporting sources. Please do not send sensitive patient data.'); ?></p></div><a class="vf-button" href="<?php echo $esc($contact . '?topic=provider#gui-lien-he'); ?>"><?php echo $t('Gửi thông tin đối chiếu', 'Send supporting information'); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a></section>
</main>
<?php require __DIR__ . '/Tem/footer.php'; ?>
</body>
</html>
