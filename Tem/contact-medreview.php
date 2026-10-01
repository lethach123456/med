<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/contact_form.php';
$contactLocale = ($contactLocale ?? 'vi') === 'en' ? 'en' : 'vi';
$contactEnglish = $contactLocale === 'en';
$contactPageKey = $contactEnglish ? 'contact-en' : 'contact';
$GLOBALS['site_page_key'] = $contactPageKey;
front_editor_page_maybe_redirect($contactPageKey);
$contactEscape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$contactPath = front_editor_page_public_path($contactPageKey);
$contactAboutPath = site_localized_path('/ve-chung-toi.php', $contactLocale);
$contactEmail = trim(site_email(''));
$contactPhone = trim(site_hotline(''));
$contactAddress = trim(site_address(''));
$contactPhoneHref = preg_replace('/[^+0-9]/', '', $contactPhone);
$contactCopy = $contactEnglish ? [
    'home' => 'Home', 'name' => 'Contact & feedback', 'breadcrumb' => 'Breadcrumb',
    'eyebrow' => 'Contact MedReview', 'titleOne' => 'A little conversation.', 'titleTwo' => 'A better connection.',
    'intro' => 'An idea to share, a detail to correct, or a provider profile to update? We are here to listen and help you take the next step.',
    'heroAction' => 'Leave us a message', 'aboutAction' => 'Get to know MedReview',
    'artTitle' => 'We are listening.', 'artCopy' => 'Your perspective helps us improve.', 'artTag' => 'Every helpful note matters',
    'email' => 'Email', 'emailHint' => 'Write to us directly', 'phone' => 'Phone', 'phoneHint' => 'Let’s start a conversation', 'address' => 'Contact address', 'addressHint' => 'Contact details from MedReview', 'missing' => 'Details are being updated',
    'formEyebrow' => 'Start the conversation', 'formTitle' => 'What would you like to share?', 'formIntro' => 'Choose a topic and tell us a little more. Clear details help us understand your request.',
    'topicLabel' => 'Your topic', 'feedback' => 'Feedback & support', 'provider' => 'Provider updates', 'partnership' => 'Partnerships',
    'feedbackHint' => 'Share an idea or an issue you encountered on MedReview.', 'providerHint' => 'Include the provider’s name, profile link and the information you want to update.', 'partnershipHint' => 'Tell us about your organization and the connection you have in mind.',
    'fullName' => 'Full name', 'fullNamePlaceholder' => 'How should we address you?', 'emailPlaceholder' => 'you@example.com', 'phoneField' => 'Phone number', 'optional' => 'optional', 'phonePlaceholder' => 'A number we can contact',
    'urlField' => 'Relevant page link', 'urlPlaceholder' => 'https://medreview.vn/...', 'message' => 'Your message', 'messagePlaceholder' => 'Share your question, suggestion or the details that need updating…', 'required' => 'Required', 'submit' => 'Send message', 'submitting' => 'Sending…',
    'privacy' => 'Please do not include medical records, identity documents or someone else’s private information.',
    'sendError' => 'Your message could not be sent. Your text is still here; please try again later or email us directly.', 'tokenError' => 'This form has expired. Please review your message and send it again.', 'rateError' => 'Please wait a minute before sending another message.', 'invalid' => 'Please check the highlighted fields.',
    'successTitle' => 'Your message is on its way.', 'successCopy' => 'The mail service has accepted your message. Thank you for sharing with MedReview.',
    'errorName' => 'Enter your name (up to 100 characters).', 'errorEmail' => 'Enter a valid email address.', 'errorPhone' => 'Check the phone number you entered.', 'errorTopic' => 'Choose one of the available topics.', 'errorUrl' => 'Enter a full http:// or https:// link.', 'errorMessage' => 'Enter your message (up to 4,000 characters).',
    'sideEyebrow' => 'Make your message useful', 'sideTitle' => 'A little context goes a long way.', 'sideIntro' => 'You can help us understand your request by sharing:', 'sideOne' => 'The page or provider you are referring to', 'sideTwo' => 'What needs correcting or improving', 'sideThree' => 'The best way to get back to you',
    'providerTitle' => 'Representing a healthcare provider?', 'providerCopy' => 'Choose “Provider updates” to suggest corrections to a profile, services or contact details.', 'providerAction' => 'Update a provider profile',
    'bookingTitle' => 'Looking to book an appointment?', 'bookingCopy' => 'Please contact the healthcare provider directly to confirm services, fees and availability. This form does not book appointments.', 'bookingLink' => 'Find a healthcare provider',
    'faqEyebrow' => 'A few helpful answers', 'faqTitle' => 'Before you send a message.',
    'faqOne' => 'Can I correct information on a profile?', 'faqOneCopy' => 'Yes. Include the profile link, the detail that needs updating and a relevant public source, where available, so we can review the request.',
    'faqTwo' => 'How can a healthcare provider get in touch?', 'faqTwoCopy' => 'Choose “Provider updates” and provide your facility’s name, your role, contact information and the details you would like to provide or correct.',
    'faqThree' => 'Can I book an appointment through this form?', 'faqThreeCopy' => 'No. This form is for feedback and contact with MedReview. Use the provider’s contact details to confirm a consultation or appointment.',
    'faqFour' => 'Should I send medical records?', 'faqFourCopy' => 'No. Please keep your message focused on website feedback or public profile information. Do not send medical records, identity documents or other sensitive personal details.',
    'closing' => 'More information. More understanding. Better choices.', 'closingLink' => 'Discover our story',
] : [
    'home' => 'Trang chủ', 'name' => 'Liên hệ & góp ý', 'breadcrumb' => 'Đường dẫn trang',
    'eyebrow' => 'Liên hệ MedReview', 'titleOne' => 'Một lời nhắn.', 'titleTwo' => 'Thêm một kết nối.',
    'intro' => 'Một ý tưởng muốn sẻ chia, một thông tin cần sửa, hay một hồ sơ cần cập nhật? MedReview luôn sẵn sàng lắng nghe và cùng bạn bắt đầu.',
    'heroAction' => 'Gửi lời nhắn', 'aboutAction' => 'Tìm hiểu về MedReview',
    'artTitle' => 'Chúng tôi luôn lắng nghe.', 'artCopy' => 'Góc nhìn của bạn giúp MedReview tốt hơn.', 'artTag' => 'Mỗi góp ý, một bước tốt hơn',
    'email' => 'Email', 'emailHint' => 'Gửi lời nhắn trực tiếp', 'phone' => 'Điện thoại', 'phoneHint' => 'Bắt đầu một cuộc trò chuyện', 'address' => 'Địa chỉ liên hệ', 'addressHint' => 'Thông tin liên hệ của MedReview', 'missing' => 'Thông tin đang cập nhật',
    'formEyebrow' => 'Bắt đầu cuộc trò chuyện', 'formTitle' => 'Bạn muốn chia sẻ điều gì?', 'formIntro' => 'Chọn chủ đề và kể thêm một chút. Thông tin rõ ràng giúp chúng tôi hiểu đúng điều bạn cần.',
    'topicLabel' => 'Chủ đề liên hệ', 'feedback' => 'Góp ý & hỗ trợ', 'provider' => 'Cập nhật hồ sơ', 'partnership' => 'Hợp tác',
    'feedbackHint' => 'Chia sẻ ý tưởng hoặc vấn đề bạn gặp khi sử dụng MedReview.', 'providerHint' => 'Cho chúng tôi biết tên cơ sở, đường dẫn hồ sơ và thông tin bạn muốn cập nhật.', 'partnershipHint' => 'Giới thiệu đơn vị của bạn và ý tưởng kết nối cùng MedReview.',
    'fullName' => 'Họ và tên', 'fullNamePlaceholder' => 'Chúng tôi nên gọi bạn là gì?', 'emailPlaceholder' => 'ban@example.com', 'phoneField' => 'Số điện thoại', 'optional' => 'tuỳ chọn', 'phonePlaceholder' => 'Số điện thoại để trao đổi',
    'urlField' => 'Đường dẫn trang liên quan', 'urlPlaceholder' => 'https://medreview.vn/...', 'message' => 'Lời nhắn của bạn', 'messagePlaceholder' => 'Chia sẻ câu hỏi, góp ý hoặc thông tin cần cập nhật…', 'required' => 'Bắt buộc', 'submit' => 'Gửi lời nhắn', 'submitting' => 'Đang gửi…',
    'privacy' => 'Vui lòng không gửi hồ sơ bệnh án, giấy tờ định danh hoặc thông tin riêng tư của người khác.',
    'sendError' => 'Chưa gửi được lời nhắn. Nội dung của bạn vẫn được giữ lại; hãy thử lại sau hoặc liên hệ trực tiếp qua email.', 'tokenError' => 'Phiên gửi đã hết hạn. Vui lòng kiểm tra nội dung và gửi lại.', 'rateError' => 'Vui lòng chờ một phút trước khi gửi lời nhắn tiếp theo.', 'invalid' => 'Vui lòng kiểm tra các trường được đánh dấu.',
    'successTitle' => 'Lời nhắn đang được chuyển đi.', 'successCopy' => 'Dịch vụ email đã chấp nhận gửi lời nhắn. Cảm ơn bạn đã chia sẻ cùng MedReview.',
    'errorName' => 'Nhập họ tên, tối đa 100 ký tự.', 'errorEmail' => 'Nhập địa chỉ email hợp lệ.', 'errorPhone' => 'Kiểm tra lại số điện thoại.', 'errorTopic' => 'Chọn một chủ đề có sẵn.', 'errorUrl' => 'Nhập đường dẫn đầy đủ bắt đầu bằng http:// hoặc https://.', 'errorMessage' => 'Nhập lời nhắn, tối đa 4.000 ký tự.',
    'sideEyebrow' => 'Để lời nhắn hữu ích hơn', 'sideTitle' => 'Thêm một chút thông tin. Hiểu nhau rõ hơn.', 'sideIntro' => 'Bạn có thể giúp chúng tôi hiểu đúng yêu cầu bằng cách chia sẻ:', 'sideOne' => 'Trang hoặc cơ sở bạn đang đề cập', 'sideTwo' => 'Điều cần sửa hoặc mong muốn cải thiện', 'sideThree' => 'Cách phù hợp để phản hồi cho bạn',
    'providerTitle' => 'Bạn đại diện cơ sở y tế?', 'providerCopy' => 'Chọn “Cập nhật hồ sơ” để bổ sung thông tin về cơ sở, dịch vụ hoặc cách liên hệ.', 'providerAction' => 'Cập nhật hồ sơ cơ sở',
    'bookingTitle' => 'Bạn muốn đặt lịch khám?', 'bookingCopy' => 'Hãy liên hệ trực tiếp với cơ sở để xác nhận dịch vụ, chi phí và thời gian. Form này không tiếp nhận đặt lịch khám.', 'bookingLink' => 'Tìm cơ sở y tế',
    'faqEyebrow' => 'Giải đáp nhanh', 'faqTitle' => 'Trước khi gửi lời nhắn.',
    'faqOne' => 'Tôi có thể góp ý sửa thông tin trên hồ sơ không?', 'faqOneCopy' => 'Có. Hãy gửi đường dẫn hồ sơ, thông tin cần cập nhật và nguồn công khai liên quan nếu có, để chúng tôi có cơ sở xem xét.',
    'faqTwo' => 'Cơ sở y tế muốn cập nhật hồ sơ thì làm thế nào?', 'faqTwoCopy' => 'Chọn “Cập nhật hồ sơ”, cung cấp tên cơ sở, vai trò của bạn, thông tin liên hệ và những nội dung muốn bổ sung hoặc điều chỉnh.',
    'faqThree' => 'Tôi có thể đặt lịch khám qua form này không?', 'faqThreeCopy' => 'Không. Đây là form liên hệ và góp ý dành cho MedReview. Để tư vấn hoặc đặt lịch khám, vui lòng dùng thông tin liên hệ trên hồ sơ của cơ sở.',
    'faqFour' => 'Có cần gửi hồ sơ bệnh án không?', 'faqFourCopy' => 'Không. Chỉ chia sẻ thông tin liên quan đến trải nghiệm website hoặc hồ sơ công khai. Không gửi bệnh án, giấy tờ định danh và những thông tin cá nhân nhạy cảm khác.',
    'closing' => 'Thêm thông tin. Thêm thấu hiểu. Thêm an tâm.', 'closingLink' => 'Câu chuyện của MedReview',
];

// A personalized anti-CSRF form must never be cached as public HTML.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
}
header('Cache-Control: private, no-store, max-age=0');
if (empty($_SESSION['medreview_contact_csrf'])) $_SESSION['medreview_contact_csrf'] = bin2hex(random_bytes(32));
$contactToken = (string) $_SESSION['medreview_contact_csrf'];
$contactValues = ['name' => '', 'email' => '', 'phone' => '', 'topic' => 'feedback', 'url' => '', 'message' => ''];
if (isset($_GET['topic']) && is_string($_GET['topic']) && in_array($_GET['topic'], ['feedback', 'provider', 'partnership'], true)) $contactValues['topic'] = $_GET['topic'];
$contactErrors = [];
$contactError = '';
$contactSent = !empty($_SESSION['medreview_contact_sent'][$contactLocale]);
unset($_SESSION['medreview_contact_sent'][$contactLocale]);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $validated = medical_contact_validate($_POST);
    $contactValues = $validated['values'];
    $contactErrors = $validated['errors'];
    $postedToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals($contactToken, $postedToken) || !empty($_POST['website'])) {
        $contactError = $contactCopy['tokenError'];
    } elseif ($contactErrors) {
        $contactError = $contactCopy['invalid'];
    } elseif (time() - (int) ($_SESSION['medreview_contact_last_attempt'] ?? 0) < 60) {
        $contactError = $contactCopy['rateError'];
    } else {
        $_SESSION['medreview_contact_last_attempt'] = time();
        if (medical_contact_send($contactValues, $contactEmail)) {
            $_SESSION['medreview_contact_sent'][$contactLocale] = true;
            $_SESSION['medreview_contact_csrf'] = bin2hex(random_bytes(32));
            header('Location: ' . $contactPath . '#gui-lien-he', true, 303);
            exit;
        }
        $contactError = $contactCopy['sendError'];
    }
    http_response_code(422);
}
// Release the session lock before the shared layout performs additional work.
session_write_close();
$contactErrorText = static function (string $field) use ($contactErrors, $contactCopy): string {
    return isset($contactErrors[$field]) ? $contactCopy['error' . ucfirst($field)] : '';
};
$contactSeo = front_editor_page_seo($contactPageKey, [
    'title' => $contactEnglish ? 'Contact MedReview | Feedback & provider updates' : 'Liên hệ MedReview | Góp ý & cập nhật hồ sơ',
    'description' => $contactEnglish ? 'Contact MedReview to share feedback, suggest corrections and update healthcare provider profiles.' : 'Kết nối với MedReview để góp ý trải nghiệm, đề nghị cập nhật thông tin và bổ sung hồ sơ cơ sở y tế.',
]);
$contactTitle = trim((string) ($contactSeo['title'] ?? ''));
$contactDescription = trim((string) ($contactSeo['description'] ?? ''));
if ($contactTitle === '' || preg_match('/(?:dental|dentaelite)/i', $contactTitle)) $contactTitle = $contactEnglish ? 'Contact MedReview | Feedback & provider updates' : 'Liên hệ MedReview | Góp ý & cập nhật hồ sơ';
if ($contactDescription === '' || preg_match('/(?:dental|dentaelite)/i', $contactDescription)) $contactDescription = $contactEnglish ? 'Contact MedReview to share feedback and update healthcare provider information.' : 'Liên hệ, góp ý và cùng MedReview xây dựng thông tin y tế rõ ràng, hữu ích hơn.';
$contactStyleVersion = (string) filemtime(dirname(__DIR__) . '/assets/css/pages/contact-medreview.css');
$contactScriptVersion = (string) filemtime(dirname(__DIR__) . '/assets/js/contact-medreview.js');
?>
<!doctype html>
<html lang="<?php echo $contactEscape($contactLocale); ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo site_favicon_tags(); ?>
    <title><?php echo $contactEscape($contactTitle); ?></title>
    <meta name="description" content="<?php echo $contactEscape($contactDescription); ?>">
    <link rel="canonical" href="<?php echo $contactEscape(site_absolute_url($contactPath)); ?>">
    <link rel="alternate" hreflang="vi" href="<?php echo $contactEscape(site_absolute_url(front_editor_page_public_path('contact'))); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo $contactEscape(site_absolute_url(front_editor_page_public_path('contact-en'))); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo $contactEscape(site_absolute_url(front_editor_page_public_path('contact'))); ?>">
    <?php echo site_json_ld(['@context' => 'https://schema.org', '@type' => 'ContactPage', 'name' => $contactTitle, 'description' => $contactDescription, 'url' => site_absolute_url($contactPath), 'inLanguage' => $contactEnglish ? 'en' : 'vi-VN', 'about' => ['@type' => 'Organization', 'name' => 'MedReview', 'url' => site_absolute_url('/')]]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700;750;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/pages/contact-medreview.css?v=<?php echo $contactEscape($contactStyleVersion); ?>">
    <script defer src="/assets/js/contact-medreview.js?v=<?php echo $contactEscape($contactScriptVersion); ?>"></script>
</head>
<body class="site-contact">
<?php require __DIR__ . '/header.php'; ?>
<main class="mr-contact">
    <section class="mr-contact__hero" aria-labelledby="contact-title">
        <div class="mr-contact__shell">
            <nav class="mr-contact__breadcrumb" aria-label="<?php echo $contactEscape($contactCopy['breadcrumb']); ?>"><a href="<?php echo $contactEscape(site_localized_path('/', $contactLocale)); ?>"><?php echo $contactEscape($contactCopy['home']); ?></a><i class="ph ph-caret-right" aria-hidden="true"></i><span aria-current="page"><?php echo $contactEscape($contactCopy['name']); ?></span></nav>
            <div class="mr-contact__hero-grid">
                <div class="mr-contact__hero-copy"><p class="mr-contact__eyebrow"><span class="mr-contact__dot" aria-hidden="true"></span><?php echo $contactEscape($contactCopy['eyebrow']); ?></p><h1 id="contact-title"><?php echo $contactEscape($contactCopy['titleOne']); ?><br><span><?php echo $contactEscape($contactCopy['titleTwo']); ?></span></h1><p class="mr-contact__intro"><?php echo $contactEscape($contactCopy['intro']); ?></p><div class="mr-contact__actions"><a class="mr-contact__button" href="#gui-lien-he"><?php echo $contactEscape($contactCopy['heroAction']); ?><i class="ph ph-arrow-down-right" aria-hidden="true"></i></a><a class="mr-contact__text-link" href="<?php echo $contactEscape($contactAboutPath); ?>"><?php echo $contactEscape($contactCopy['aboutAction']); ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a></div></div>
                <div class="mr-contact__art" aria-hidden="true"><span class="mr-contact__orbit"></span><span class="mr-contact__art-dot"></span><div class="mr-contact__letter"><span class="mr-contact__letter-icon"><i class="ph ph-chat-centered-text"></i></span><span class="mr-contact__letter-lines"><i></i><i></i><i></i></span><strong><?php echo $contactEscape($contactCopy['artTitle']); ?></strong><p><?php echo $contactEscape($contactCopy['artCopy']); ?></p><span class="mr-contact__letter-footer"><i class="ph ph-heart"></i> MedReview</span></div><span class="mr-contact__heart"><i class="ph ph-heart"></i></span><span class="mr-contact__art-tag"><i class="ph ph-sparkle"></i><?php echo $contactEscape($contactCopy['artTag']); ?></span></div>
            </div>
        </div>
    </section>
    <div class="mr-contact__shell mr-contact__channels" aria-label="<?php echo $contactEscape($contactCopy['name']); ?>">
        <?php foreach ([['icon' => 'ph-envelope-simple', 'label' => 'email', 'hint' => 'emailHint', 'value' => $contactEmail, 'href' => filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $contactEmail : '', 'tone' => 'blue'], ['icon' => 'ph-phone', 'label' => 'phone', 'hint' => 'phoneHint', 'value' => $contactPhone, 'href' => $contactPhoneHref ? 'tel:' . $contactPhoneHref : '', 'tone' => 'green'], ['icon' => 'ph-map-pin', 'label' => 'address', 'hint' => 'addressHint', 'value' => $contactAddress, 'href' => '', 'tone' => 'sand']] as $channel): ?>
            <div class="mr-contact__channel mr-contact__channel--<?php echo $channel['tone']; ?>"><span class="mr-contact__channel-icon"><i class="ph <?php echo $channel['icon']; ?>" aria-hidden="true"></i></span><div><p><?php echo $contactEscape($contactCopy[$channel['label']]); ?></p><?php if ($channel['href']): ?><a href="<?php echo $contactEscape($channel['href']); ?>"><?php echo $contactEscape($channel['value']); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a><?php else: ?><strong><?php echo $contactEscape($channel['value'] ?: $contactCopy['missing']); ?></strong><?php endif; ?><small><?php echo $contactEscape($contactCopy[$channel['hint']]); ?></small></div></div>
        <?php endforeach; ?>
    </div>
    <section class="mr-contact__section mr-contact__shell" id="gui-lien-he" aria-labelledby="form-title">
        <header class="mr-contact__section-heading"><p class="mr-contact__eyebrow"><?php echo $contactEscape($contactCopy['formEyebrow']); ?></p><h2 id="form-title"><?php echo $contactEscape($contactCopy['formTitle']); ?></h2><p><?php echo $contactEscape($contactCopy['formIntro']); ?></p></header>
        <div class="mr-contact__compose">
            <div class="mr-contact__form-panel">
                <?php if ($contactSent): ?><div class="mr-contact__notice mr-contact__notice--success" role="status"><i class="ph ph-check-circle" aria-hidden="true"></i><div><strong><?php echo $contactEscape($contactCopy['successTitle']); ?></strong><p><?php echo $contactEscape($contactCopy['successCopy']); ?></p></div></div><?php endif; ?>
                <?php if ($contactError !== ''): ?><div class="mr-contact__notice mr-contact__notice--error" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><div><p><?php echo $contactEscape($contactError); ?></p><?php if ($contactError === $contactCopy['sendError'] && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?php echo $contactEscape($contactEmail); ?>"><?php echo $contactEscape($contactEmail); ?></a><?php endif; ?></div></div><?php endif; ?>
                <form class="mr-contact__form" method="post" action="<?php echo $contactEscape($contactPath . '#gui-lien-he'); ?>" data-contact-form data-submitting="<?php echo $contactEscape($contactCopy['submitting']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $contactEscape($contactToken); ?>">
                    <div class="mr-contact__honey" aria-hidden="true"><label for="contact-website">Website</label><input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                    <fieldset class="mr-contact__topics"><legend><?php echo $contactEscape($contactCopy['topicLabel']); ?></legend><div class="mr-contact__topic-options"><?php foreach (['feedback' => 'ph-chat-circle-dots', 'provider' => 'ph-hospital', 'partnership' => 'ph-handshake'] as $topic => $icon): ?><label><input type="radio" name="topic" value="<?php echo $topic; ?>" <?php echo $contactValues['topic'] === $topic ? 'checked' : ''; ?> required><span><i class="ph <?php echo $icon; ?>" aria-hidden="true"></i><?php echo $contactEscape($contactCopy[$topic]); ?></span></label><?php endforeach; ?></div><?php if ($contactErrorText('topic')): ?><p class="mr-contact__field-error"><?php echo $contactEscape($contactErrorText('topic')); ?></p><?php endif; ?></fieldset>
                    <div class="mr-contact__topic-hint" aria-live="polite"><?php foreach (['feedback', 'provider', 'partnership'] as $topic): ?><p data-contact-hint="<?php echo $topic; ?>" <?php echo $contactValues['topic'] !== $topic ? 'hidden' : ''; ?>><?php echo $contactEscape($contactCopy[$topic . 'Hint']); ?></p><?php endforeach; ?></div>
                    <div class="mr-contact__form-row">
                        <?php foreach (['name' => ['label' => 'fullName', 'placeholder' => 'fullNamePlaceholder', 'type' => 'text', 'autocomplete' => 'name', 'maxlength' => 100], 'email' => ['label' => 'email', 'placeholder' => 'emailPlaceholder', 'type' => 'email', 'autocomplete' => 'email', 'maxlength' => 254]] as $field => $meta): ?><div class="mr-contact__field"><label for="contact-<?php echo $field; ?>"><?php echo $contactEscape($contactCopy[$meta['label']]); ?> <span class="mr-contact__required" aria-label="<?php echo $contactEscape($contactCopy['required']); ?>">*</span></label><input id="contact-<?php echo $field; ?>" name="<?php echo $field; ?>" type="<?php echo $meta['type']; ?>" autocomplete="<?php echo $meta['autocomplete']; ?>" maxlength="<?php echo $meta['maxlength']; ?>" placeholder="<?php echo $contactEscape($contactCopy[$meta['placeholder']]); ?>" value="<?php echo $contactEscape($contactValues[$field]); ?>" required <?php echo $contactErrorText($field) ? 'aria-invalid="true" aria-describedby="contact-' . $field . '-error"' : ''; ?>><?php if ($contactErrorText($field)): ?><p class="mr-contact__field-error" id="contact-<?php echo $field; ?>-error"><?php echo $contactEscape($contactErrorText($field)); ?></p><?php endif; ?></div><?php endforeach; ?>
                    </div>
                    <div class="mr-contact__form-row">
                        <?php foreach (['phone' => ['label' => 'phoneField', 'placeholder' => 'phonePlaceholder', 'type' => 'tel', 'maxlength' => 30], 'url' => ['label' => 'urlField', 'placeholder' => 'urlPlaceholder', 'type' => 'url', 'maxlength' => 1000]] as $field => $meta): ?><div class="mr-contact__field"><label for="contact-<?php echo $field; ?>"><?php echo $contactEscape($contactCopy[$meta['label']]); ?> <small>(<?php echo $contactEscape($contactCopy['optional']); ?>)</small></label><input id="contact-<?php echo $field; ?>" name="<?php echo $field; ?>" type="<?php echo $meta['type']; ?>" maxlength="<?php echo $meta['maxlength']; ?>" <?php echo $field === 'phone' ? 'autocomplete="tel"' : ''; ?> placeholder="<?php echo $contactEscape($contactCopy[$meta['placeholder']]); ?>" value="<?php echo $contactEscape($contactValues[$field]); ?>" <?php echo $contactErrorText($field) ? 'aria-invalid="true" aria-describedby="contact-' . $field . '-error"' : ''; ?>><?php if ($contactErrorText($field)): ?><p class="mr-contact__field-error" id="contact-<?php echo $field; ?>-error"><?php echo $contactEscape($contactErrorText($field)); ?></p><?php endif; ?></div><?php endforeach; ?>
                    </div>
                    <div class="mr-contact__field"><label for="contact-message"><?php echo $contactEscape($contactCopy['message']); ?> <span class="mr-contact__required" aria-label="<?php echo $contactEscape($contactCopy['required']); ?>">*</span></label><textarea id="contact-message" name="message" rows="5" maxlength="4000" placeholder="<?php echo $contactEscape($contactCopy['messagePlaceholder']); ?>" required <?php echo $contactErrorText('message') ? 'aria-invalid="true" aria-describedby="contact-message-error"' : ''; ?>><?php echo $contactEscape($contactValues['message']); ?></textarea><div class="mr-contact__message-bottom"><?php if ($contactErrorText('message')): ?><p class="mr-contact__field-error" id="contact-message-error"><?php echo $contactEscape($contactErrorText('message')); ?></p><?php endif; ?><span data-contact-count aria-hidden="true"><?php echo mb_strlen($contactValues['message']); ?> / 4.000</span></div></div>
                    <p class="mr-contact__privacy"><i class="ph ph-lock-simple" aria-hidden="true"></i><?php echo $contactEscape($contactCopy['privacy']); ?></p>
                    <button type="submit" class="mr-contact__button mr-contact__submit"><span><?php echo $contactEscape($contactCopy['submit']); ?></span><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i></button>
                </form>
            </div>
            <aside class="mr-contact__sidebar">
                <div class="mr-contact__context"><span class="mr-contact__context-icon"><i class="ph ph-note-pencil" aria-hidden="true"></i></span><p class="mr-contact__eyebrow"><?php echo $contactEscape($contactCopy['sideEyebrow']); ?></p><h3><?php echo $contactEscape($contactCopy['sideTitle']); ?></h3><p><?php echo $contactEscape($contactCopy['sideIntro']); ?></p><ul><?php foreach (['sideOne', 'sideTwo', 'sideThree'] as $key): ?><li><i class="ph ph-check-circle" aria-hidden="true"></i><?php echo $contactEscape($contactCopy[$key]); ?></li><?php endforeach; ?></ul></div>
                <div class="mr-contact__provider"><i class="ph ph-hospital" aria-hidden="true"></i><h3><?php echo $contactEscape($contactCopy['providerTitle']); ?></h3><p><?php echo $contactEscape($contactCopy['providerCopy']); ?></p><a class="mr-contact__text-link" href="<?php echo $contactEscape($contactPath . '?topic=provider#gui-lien-he'); ?>" data-contact-provider><?php echo $contactEscape($contactCopy['providerAction']); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a></div>
                <div class="mr-contact__booking"><i class="ph ph-info" aria-hidden="true"></i><div><h3><?php echo $contactEscape($contactCopy['bookingTitle']); ?></h3><p><?php echo $contactEscape($contactCopy['bookingCopy']); ?></p><a href="<?php echo $contactEscape(site_localized_path(medical_public_facility_path(), $contactLocale)); ?>"><?php echo $contactEscape($contactCopy['bookingLink']); ?> <i class="ph ph-arrow-right" aria-hidden="true"></i></a></div></div>
            </aside>
        </div>
    </section>
    <section class="mr-contact__faq-band" aria-labelledby="faq-title"><div class="mr-contact__shell mr-contact__faq-grid"><header><p class="mr-contact__eyebrow"><?php echo $contactEscape($contactCopy['faqEyebrow']); ?></p><h2 id="faq-title"><?php echo $contactEscape($contactCopy['faqTitle']); ?></h2></header><div class="mr-contact__faqs"><?php foreach (['One', 'Two', 'Three', 'Four'] as $index => $suffix): ?><details <?php echo $index === 0 ? 'open' : ''; ?>><summary><?php echo $contactEscape($contactCopy['faq' . $suffix]); ?><span aria-hidden="true"><i class="ph ph-plus"></i></span></summary><div class="mr-contact__faq-answer"><p><?php echo $contactEscape($contactCopy['faq' . $suffix . 'Copy']); ?></p></div></details><?php endforeach; ?></div></div></section>
    <div class="mr-contact__closing"><div class="mr-contact__shell"><p><?php echo $contactEscape($contactCopy['closing']); ?></p><a class="mr-contact__text-link" href="<?php echo $contactEscape($contactAboutPath); ?>"><?php echo $contactEscape($contactCopy['closingLink']); ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a></div></div>
</main>
<?php require __DIR__ . '/footer.php'; ?>
<?php front_editor_render($contactPageKey); ?>
</body>
</html>
