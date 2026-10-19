<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../medical_public_assets.php';
require_once __DIR__ . '/../medical_media_variants.php';

$forcedLocale = trim((string) ($GLOBALS['site_forced_locale'] ?? ''));
$locale = in_array($forcedLocale, ['vi', 'en'], true) ? $forcedLocale : site_page_locale();
$isEnglish = $locale === 'en';
$hotline = site_hotline('0901 234 567');
$email = site_email('hello@medreview.vn');
$address = site_address($isEnglish ? '123 Nguyen Van Linh, Da Nang' : '123 Nguyen Van Linh, Da Nang');
$facebook = site_facebook('#');
$instagram = site_instagram('#');
$zalo = site_zalo('#');
$brandName = site_title('MedReview');
$brandIconUrl = site_icon_href('');
if ($brandIconUrl === '') {
    $brandIconUrl = 'https://medreview.vn/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg';
}
if (preg_match('~^https?://(?:www\.)?medreview\.vn(/uploads/[a-zA-Z0-9/_-]+\.(?:jpe?g|png|webp|svg))(?:\?.*)?$~i', $brandIconUrl, $brandIconMatch)
    && is_file(dirname(__DIR__) . $brandIconMatch[1])) {
    $brandIconUrl = $brandIconMatch[1];
}
$isMedReviewWordmark = strcasecmp($brandName, 'MedReview') === 0;
$displayBrandName = $isMedReviewWordmark ? 'MedReview' : $brandName;
$aboutPath = site_localized_path('/ve-chung-toi.php', $locale);
$contactPath = front_editor_page_public_path($isEnglish ? 'contact-en' : 'contact');
$servicesPath = site_localized_path(medical_public_facility_path(), $locale);

$footerIntroLines = $isEnglish
    ? ['Connecting information and sharing experiences.', 'Building a community more engaged in healthcare.']
    : ['Kết nối thông tin, chia sẻ trải nghiệm.', 'Cùng xây dựng một cộng đồng chủ động hơn trong chăm sóc sức khỏe.'];
$footerIntroMobileLines = $isEnglish
    ? ['Connect with information.', 'Share experiences.']
    : ['Kết nối thông tin.', 'Chia sẻ trải nghiệm.'];
$footerListenCopy = $isEnglish
    ? 'Have an update or an experience to share? MedReview is here to listen.'
    : 'Một thông tin cần cập nhật? Một trải nghiệm muốn sẻ chia? MedReview luôn sẵn sàng lắng nghe.';
$footerBrandTagline = $isEnglish ? 'A trusted healthcare review community' : 'Cộng đồng review y tế đáng tin cậy';
$footerConnectLabel = $isEnglish ? 'Get in touch with us' : 'Kết nối với chúng tôi';
$featuredTitle = $isEnglish ? 'Explore' : 'Khám phá';
$quickTitle = $isEnglish ? 'About MedReview' : 'Về MedReview';
$contactTitle = $isEnglish ? 'We are listening' : 'Luôn lắng nghe bạn';
$hoursText = $isEnglish ? 'Mon – Sat, 08:00 – 18:00' : 'Thứ 2 – Thứ 7, 08:00 – 18:00';

$featuredLinks = $isEnglish ? [
    ['label' => 'Healthcare facilities', 'href' => $servicesPath],
    ['label' => 'Doctors & specialists', 'href' => site_localized_path('/bac-si.php', $locale)],
    ['label' => 'Community reviews', 'href' => site_localized_path('/review.php', $locale)],
    ['label' => 'Health guides', 'href' => site_localized_path('/cam-nang', $locale)],
    ['label' => 'Curated toplists', 'href' => site_localized_path(medical_public_toplist_path(), $locale)],
] : [
    ['label' => 'Cơ sở y tế', 'href' => $servicesPath],
    ['label' => 'Bác sĩ & chuyên gia', 'href' => site_localized_path('/bac-si.php', $locale)],
    ['label' => 'Review từ cộng đồng', 'href' => site_localized_path('/review.php', $locale)],
    ['label' => 'Cẩm nang sức khỏe', 'href' => site_localized_path('/cam-nang', $locale)],
    ['label' => 'Toplist chọn lọc', 'href' => site_localized_path(medical_public_toplist_path(), $locale)],
];
$quickLinks = $isEnglish ? [
    ['label' => 'Our story', 'href' => $aboutPath . '#cau-chuyen'],
    ['label' => 'Values & how it works', 'href' => $aboutPath . '#gia-tri'],
    ['label' => 'Verified profiles explained', 'href' => site_localized_path('/ho-so-da-xac-thuc', $locale)],
    ['label' => 'Contact & feedback', 'href' => $contactPath],
    ['label' => 'For healthcare providers', 'href' => $contactPath . '?topic=provider#gui-lien-he'],
] : [
    ['label' => 'Câu chuyện của chúng tôi', 'href' => $aboutPath . '#cau-chuyen'],
    ['label' => 'Giá trị & cách hoạt động', 'href' => $aboutPath . '#gia-tri'],
    ['label' => 'Hồ sơ cơ sở đã xác thực', 'href' => site_localized_path('/ho-so-da-xac-thuc', $locale)],
    ['label' => 'Liên hệ & góp ý', 'href' => $contactPath],
    ['label' => 'Dành cho cơ sở y tế', 'href' => $contactPath . '?topic=provider#gui-lien-he'],
];

$escape = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$footerMatchStylesheetPath = __DIR__ . '/../assets/css/pages/brand-footer-match.css';
$footerMatchStylesheetVersion = is_file($footerMatchStylesheetPath) ? (string) filemtime($footerMatchStylesheetPath) : '1';
?>


<?php if (empty($GLOBALS['medical_public_home_assets_emitted'])): ?>
<link rel="stylesheet" href="<?php echo $escape(medical_public_asset_url('/assets/css/core/footer-base.css')); ?>">
<link rel="stylesheet" href="/assets/css/pages/brand-footer-match.css?v=<?php echo $escape($footerMatchStylesheetVersion); ?>">
<?php endif; ?>

<footer class="medical-footer" role="contentinfo">
  <div class="container footer-main">
    <div class="footer-grid">
      <section class="footer-brand" aria-label="<?php echo $escape($displayBrandName); ?>">
        <a class="footer-brand-link" href="<?php echo $escape(site_localized_path('/', $locale)); ?>">
          <span class="footer-brand-icon<?php echo $brandIconUrl !== '' ? ' has-image' : ''; ?>"><?php if ($isMedReviewWordmark): ?><svg viewBox="0 0 48 56" width="39" height="44" aria-hidden="true" focusable="false"><path fill="#4b91ff" d="M24 1C10 1 1 11 1 24c0 12 23 31 23 31s23-19 23-31C47 11 38 1 24 1Z"/><path fill="#fff" d="M13 19c3-3 7-2 11 2 4-4 8-5 11-2 6 6-1 12-11 20-10-8-17-14-11-20Z"/><circle cx="39" cy="10" r="9" fill="#2563ff" stroke="#102847" stroke-width="3"/><path d="M39 6v8m-4-4h8" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg><?php elseif ($brandIconUrl !== ''): ?><img <?php echo medical_media_image_attributes($brandIconUrl, '48px', [64, 128], 64); ?> alt="" width="43" height="48" loading="lazy" decoding="async" onload="this.parentElement.classList.add('brand-image-loaded');" onerror="var mark=this.parentElement;this.remove();if(mark)mark.classList.remove('has-image','brand-image-loaded');"><?php endif; ?><i class="ph ph-heart" aria-hidden="true"></i></span>
          <span>
            <strong class="footer-brand-name"><?php if ($isMedReviewWordmark): ?><span class="footer-wordmark-med">Med</span><span class="footer-wordmark-review">Review</span><?php else: ?><?php echo $escape($brandName); ?><?php endif; ?></strong>
            <small class="footer-brand-label"><?php echo $escape($footerBrandTagline); ?></small>
          </span>
        </a>
        <p class="footer-intro"><span class="footer-intro-desktop"><?php echo $escape($footerIntroLines[0]); ?><br><?php echo $escape($footerIntroLines[1]); ?></span><span class="footer-intro-mobile"><?php echo $escape($footerIntroMobileLines[0]); ?><br><?php echo $escape($footerIntroMobileLines[1]); ?></span></p>
        <div class="social-row" aria-label="<?php echo $escape($isEnglish ? 'Social media' : 'Mạng xã hội'); ?>">
          <a href="<?php echo $escape($facebook); ?>" aria-label="Facebook"><i class="ph ph-facebook-logo" aria-hidden="true"></i></a>
          <a href="<?php echo $escape($instagram); ?>" aria-label="Instagram"><i class="ph ph-instagram-logo" aria-hidden="true"></i></a>
          <a href="<?php echo $escape($zalo); ?>" aria-label="Zalo"><i class="ph ph-chat-circle-text" aria-hidden="true"></i></a>
        </div>
      </section>

      <section class="footer-section" aria-labelledby="footer-specialties">
        <h3 id="footer-specialties"><?php echo $escape($featuredTitle); ?></h3>
        <ul class="footer-links">
          <?php foreach ($featuredLinks as $link): ?>
            <li><a href="<?php echo $escape($link['href']); ?>"><?php echo $escape($link['label']); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="footer-section" aria-labelledby="footer-explore">
        <h3 id="footer-explore"><?php echo $escape($quickTitle); ?></h3>
        <ul class="footer-links">
          <?php foreach ($quickLinks as $link): ?>
            <li><a href="<?php echo $escape($link['href']); ?>"><?php echo $escape($link['label']); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="footer-section footer-section-contact" aria-labelledby="footer-contact">
        <h3 id="footer-contact"><?php echo $escape($contactTitle); ?></h3>
        <p class="footer-listen-copy"><?php echo $escape($footerListenCopy); ?></p>
        <a class="footer-contact-link" href="<?php echo $escape($contactPath); ?>"><?php echo $escape($footerConnectLabel); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
        <div class="footer-contact-compact">
          <a href="tel:<?php echo $escape(preg_replace('/[^+0-9]/', '', $hotline)); ?>"><i class="ph ph-phone" aria-hidden="true"></i><?php echo $escape($hotline); ?></a>
          <a href="mailto:<?php echo $escape($email); ?>"><i class="ph ph-envelope-simple" aria-hidden="true"></i><?php echo $escape($email); ?></a>
          <span><i class="ph ph-map-pin" aria-hidden="true"></i><?php echo $escape($address); ?></span>
          <span><i class="ph ph-clock" aria-hidden="true"></i><?php echo $escape($hoursText); ?></span>
        </div>
      </section>
    </div>
  </div>

  <div class="container footer-bottom">
    <span>&copy; <?php echo date('Y'); ?> <?php echo $escape($displayBrandName); ?>. <?php echo $escape($isEnglish ? 'A healthier community, built together.' : 'Vì một Việt Nam khỏe mạnh hơn.'); ?></span>
    <span class="footer-disclaimer"><?php echo $escape($isEnglish ? 'Information is for reference only and does not replace diagnosis or advice from a healthcare professional.' : 'Thông tin trên nền tảng mang tính tham khảo, không thay thế chẩn đoán hoặc tư vấn của người hành nghề y tế.'); ?></span>
  </div>
</footer>
