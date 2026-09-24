<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

$locale = site_page_locale();
$isEnglish = $locale === 'en';
$hotline = site_hotline('0901 234 567');
$email = site_email('hello@medreview.vn');
$address = site_address($isEnglish ? '123 Nguyen Van Linh, Da Nang' : '123 Nguyen Van Linh, Da Nang');
$facebook = site_facebook('#');
$instagram = site_instagram('#');
$zalo = site_zalo('#');
$brandName = site_title('MedReview');
$blogPath = front_editor_page_public_path($isEnglish ? 'blog-en' : 'blog');
$aboutPath = site_localized_path('/ve-chung-toi.php', $locale);
$contactPath = front_editor_page_public_path($isEnglish ? 'contact-en' : 'contact');
$servicesPath = site_localized_path(medical_public_facility_path(), $locale);
$categoriesPath = '/danh-muc-y-te.php';

$footerIntro = $isEnglish
    ? 'MedReview helps users discover trusted clinics, doctors and useful healthcare content before they make important treatment decisions.'
    : 'MedReview giúp người dùng tìm thấy cơ sở y tế, bác sĩ và nội dung review hữu ích trước khi đưa ra quyết định khám chữa bệnh.';
$ctaTitle = $isEnglish
    ? 'Are you a clinic, hospital or doctor who wants to reach the right patients?'
    : 'Bạn là phòng khám, bệnh viện hoặc bác sĩ muốn tiếp cận đúng người bệnh?';
$ctaText = $isEnglish ? 'Create your profile' : 'Tạo hồ sơ hiển thị';
$ctaKicker = $isEnglish ? 'Connect with MedReview' : 'Kết nối cùng MedReview';
$platformLabel = $isEnglish ? 'Healthcare discovery & review platform' : 'Nền tảng review và tìm kiếm y tế';
$featuredTitle = $isEnglish ? 'Popular specialties' : 'Chuyên khoa phổ biến';
$quickTitle = $isEnglish ? 'Explore MedReview' : 'Khám phá MedReview';
$contactTitle = $isEnglish ? 'Contact' : 'Liên hệ';
$copyright = $isEnglish ? 'All rights reserved.' : 'Bảo lưu mọi quyền.';
$addressLabel = $isEnglish ? 'Address' : 'Địa chỉ';
$hoursLabel = $isEnglish ? 'Support hours' : 'Hỗ trợ khách hàng';
$hoursText = $isEnglish ? 'Mon – Sat, 08:00 – 18:00' : 'Thứ 2 – Thứ 7, 08:00 – 18:00';

$featuredLinks = $isEnglish ? [
    ['label' => 'Dental care', 'href' => $servicesPath],
    ['label' => 'ENT', 'href' => $servicesPath],
    ['label' => 'Dermatology', 'href' => $servicesPath],
    ['label' => 'General practice', 'href' => $servicesPath],
] : [
    ['label' => 'Nha khoa', 'href' => $servicesPath],
    ['label' => 'Tai mũi họng', 'href' => $servicesPath],
    ['label' => 'Da liễu', 'href' => $servicesPath],
    ['label' => 'Khám tổng quát', 'href' => $servicesPath],
];
$quickLinks = $isEnglish ? [
    ['label' => 'About us', 'href' => $aboutPath],
    ['label' => 'Healthcare blog', 'href' => $blogPath],
    ['label' => 'Contact', 'href' => $contactPath],
    ['label' => 'Medical categories', 'href' => $categoriesPath],
] : [
    ['label' => 'Về chúng tôi', 'href' => $aboutPath],
    ['label' => 'Blog y tế', 'href' => $blogPath],
    ['label' => 'Liên hệ', 'href' => $contactPath],
    ['label' => 'Danh mục y tế', 'href' => $categoriesPath],
];

$escape = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<style>
  .medical-footer{
    position:relative;
    isolation:isolate;
    overflow:hidden;
    margin-top:72px!important;
    color:#dbe7fb;
    background:
      radial-gradient(circle at 86% 5%,rgba(53,112,255,.32),transparent 27rem),
      radial-gradient(circle at 5% 106%,rgba(13,182,152,.16),transparent 27rem),
      linear-gradient(138deg,#0a1730 0%,#0d2244 49%,#10294f 100%);
  }
  .medical-footer::before,.medical-footer::after{
    position:absolute;
    z-index:-1;
    content:"";
    pointer-events:none;
    border-radius:999px;
    filter:blur(1px);
  }
  .medical-footer::before{
    top:-210px;
    right:10%;
    width:420px;
    height:420px;
    background:rgba(63,116,255,.18);
  }
  .medical-footer::after{
    bottom:-260px;
    left:20%;
    width:520px;
    height:420px;
    background:rgba(16,185,129,.11);
  }
  .medical-footer a{color:inherit;text-decoration:none}
  .medical-footer .footer-cta-wrap{position:relative;z-index:1;padding-top:34px}
  .medical-footer .footer-cta{
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    align-items:center;
    gap:28px;
    padding:25px 28px;
    border:1px solid rgba(161,193,255,.25);
    border-radius:24px;
    background:linear-gradient(112deg,rgba(51,99,209,.35),rgba(31,65,132,.38));
    box-shadow:0 24px 56px rgba(3,11,30,.2),inset 0 1px rgba(255,255,255,.11);
  }
  .medical-footer .footer-cta-copy{max-width:780px}
  .medical-footer .footer-kicker{
    display:inline-flex;
    align-items:center;
    gap:7px;
    margin-bottom:8px;
    color:#9deed6;
    font-size:11px;
    font-weight:800;
    letter-spacing:.07em;
    text-transform:uppercase;
  }
  .medical-footer .footer-kicker i{font-size:16px}
  .medical-footer .footer-cta-copy strong{
    display:block;
    color:#fff;
    font-size:22px;
    line-height:1.34;
    letter-spacing:-.035em;
  }
  .medical-footer .footer-cta-copy p{
    margin:7px 0 0;
    color:#bfd0ec;
    font-size:13px;
    line-height:1.7;
  }
  .medical-footer .footer-cta-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:46px;
    padding:0 17px;
    border:1px solid rgba(255,255,255,.5);
    border-radius:13px;
    color:#103162;
    background:#fff;
    font-size:13px;
    font-weight:800;
    white-space:nowrap;
    box-shadow:0 10px 24px rgba(1,11,32,.16);
  }
  .medical-footer .footer-cta-btn i{font-size:18px;transition:transform .2s var(--ui-ease)}
  .medical-footer .footer-cta-btn:hover i{transform:translateX(3px)}
  .medical-footer .footer-main{position:relative;z-index:1;padding:44px 0 34px!important}
  .medical-footer .footer-grid{
    display:grid;
    grid-template-columns:minmax(245px,1.35fr) minmax(145px,.72fr) minmax(155px,.8fr) minmax(230px,1.05fr);
    gap:40px;
  }
  .medical-footer .footer-brand{max-width:340px}
  .medical-footer .footer-brand-link{display:inline-flex;align-items:center;gap:11px}
  .medical-footer .footer-brand-icon{
    display:grid;
    width:42px;
    height:42px;
    place-items:center;
    border:1px solid rgba(179,205,255,.36);
    border-radius:14px;
    color:#fff;
    background:linear-gradient(145deg,#3979f4,#2256ca);
    box-shadow:0 10px 22px rgba(0,0,0,.18),inset 0 1px rgba(255,255,255,.24);
  }
  .medical-footer .footer-brand-icon i{font-size:23px}
  .medical-footer .footer-brand-name{display:block;color:#fff;font-size:20px;font-weight:850;letter-spacing:-.055em}
  .medical-footer .footer-brand-label{display:block;margin-top:2px;color:#9eb5d8;font-size:10px;font-weight:700;letter-spacing:.012em}
  .medical-footer .footer-intro{margin:16px 0 0;color:#b6c7e1;font-size:13px;line-height:1.8}
  .medical-footer .social-row{display:flex;gap:9px;margin-top:19px}
  .medical-footer .social-row a{
    display:grid;
    width:34px;
    height:34px;
    place-items:center;
    border:1px solid rgba(181,207,255,.2);
    border-radius:11px;
    color:#dceaff;
    background:rgba(255,255,255,.065);
  }
  .medical-footer .social-row a:hover{color:#fff;background:rgba(89,139,255,.3);border-color:rgba(178,205,255,.43)}
  .medical-footer .social-row i{font-size:18px}
  .medical-footer .footer-section h3{
    position:relative;
    margin:7px 0 16px;
    padding-bottom:10px;
    color:#fff;
    font-size:12px;
    font-weight:850;
    letter-spacing:.035em;
    text-transform:uppercase;
  }
  .medical-footer .footer-section h3::after{
    position:absolute;
    bottom:0;
    left:0;
    width:28px;
    height:2px;
    content:"";
    border-radius:999px;
    background:linear-gradient(90deg,#5f9cff,#8fc5ff);
  }
  .medical-footer .footer-links{display:grid;gap:9px;margin:0;padding:0;list-style:none}
  .medical-footer .footer-links a{
    display:inline-flex;
    align-items:center;
    gap:6px;
    width:max-content;
    max-width:100%;
    color:#b6c7e1;
    font-size:13px;
    line-height:1.45;
    transition:color .18s var(--ui-ease),transform .18s var(--ui-ease);
  }
  .medical-footer .footer-links a::before{content:"›";color:#79a9ff;font-size:18px;line-height:.7}
  .medical-footer .footer-links a:hover{color:#fff;transform:translateX(3px)}
  .medical-footer .footer-contact{display:grid;gap:11px}
  .medical-footer .footer-contact-item{display:grid;grid-template-columns:29px minmax(0,1fr);gap:9px;align-items:start}
  .medical-footer .footer-contact-icon{
    display:grid;
    width:29px;
    height:29px;
    place-items:center;
    border:1px solid rgba(166,198,255,.18);
    border-radius:9px;
    color:#95bcff;
    background:rgba(255,255,255,.065);
  }
  .medical-footer .footer-contact-icon i{font-size:15px}
  .medical-footer .footer-contact-copy{min-width:0;color:#b6c7e1;font-size:12px;line-height:1.55;overflow-wrap:anywhere}
  .medical-footer .footer-contact-copy small{display:block;margin-bottom:1px;color:#7f99bf;font-size:9px;font-weight:800;letter-spacing:.055em;text-transform:uppercase}
  .medical-footer .footer-contact-copy a{color:#dbeafe;font-weight:700;transition:color .18s ease}
  .medical-footer .footer-contact-copy a:hover{color:#fff}
  .medical-footer .footer-bottom{
    position:relative;
    z-index:1;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    min-height:64px;
    border-top:1px solid rgba(186,209,245,.14);
    color:#91a7c6;
    font-size:11px;
  }
  .medical-footer .footer-bottom-note{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
  .medical-footer .footer-bottom-note i{color:#75a9ff;font-size:15px}
  @media (min-width:1181px){
    .medical-footer .footer-cta-copy p{font-size:14px;line-height:1.7}
    .medical-footer .footer-cta-btn{font-size:14px}
    .medical-footer .footer-brand-label{font-size:11px}
    .medical-footer .footer-intro{font-size:14px;line-height:1.75}
    .medical-footer .footer-section h3{font-size:13px}
    .medical-footer .footer-links a{font-size:14px}
    .medical-footer .footer-contact-copy{font-size:13px}
    .medical-footer .footer-contact-copy small{font-size:10px}
    .medical-footer .footer-bottom{font-size:12px}
  }
  @media (max-width:1040px){
    .medical-footer .footer-grid{grid-template-columns:minmax(260px,1.35fr) minmax(150px,.8fr) minmax(200px,1fr);gap:31px}
    .medical-footer .footer-brand{grid-column:span 3;max-width:570px}
  }
  @media (max-width:720px){
    .medical-footer{margin-top:46px!important}
    .medical-footer .footer-cta-wrap{padding-top:20px}
    .medical-footer .footer-cta{grid-template-columns:1fr;gap:16px;padding:21px 20px 19px;border-radius:20px}
    .medical-footer .footer-kicker{margin-bottom:7px;font-size:10px;letter-spacing:.055em}
    .medical-footer .footer-cta-copy strong{font-size:20px!important;line-height:1.35}
    .medical-footer .footer-cta-copy p{font-size:13px;line-height:1.65}
    .medical-footer .footer-cta-btn{width:100%;min-height:46px;font-size:13px}
    .medical-footer .footer-main{padding:32px 0 27px!important}
    .medical-footer .footer-grid{grid-template-columns:1fr 1fr;gap:28px 18px}
    .medical-footer .footer-brand{grid-column:1 / -1;max-width:none}
    .medical-footer .footer-brand-icon{width:40px;height:40px;border-radius:13px}
    .medical-footer .footer-brand-name{font-size:20px}
    .medical-footer .footer-brand-label{font-size:11px}
    .medical-footer .footer-intro{max-width:35rem;margin-top:14px;font-size:13px;line-height:1.65}
    .medical-footer .social-row{margin-top:16px}
    .medical-footer .social-row a{width:38px;height:38px;border-radius:12px}
    .medical-footer .footer-section h3{margin:0 0 10px;padding-bottom:9px;font-size:11px}
    .medical-footer .footer-links{gap:3px}
    .medical-footer .footer-links a{min-height:36px;font-size:13px;line-height:1.45}
    .medical-footer .footer-section-contact{grid-column:1 / -1}
    .medical-footer .footer-contact{grid-template-columns:1fr 1fr;gap:12px 14px;margin-top:2px;padding-top:22px;border-top:1px solid rgba(186,209,245,.14)}
    .medical-footer .footer-contact-item{grid-template-columns:30px minmax(0,1fr);gap:8px}
    .medical-footer .footer-contact-icon{width:30px;height:30px}
    .medical-footer .footer-contact-copy{font-size:12px;line-height:1.55}
    .medical-footer .footer-bottom{align-items:flex-start;flex-direction:column;justify-content:center;gap:5px;min-height:70px;padding:13px 0;font-size:11px;line-height:1.5}
    .medical-footer .footer-bottom-note{white-space:normal}
  }
  @media (max-width:430px){
    .medical-footer .footer-grid{gap:27px 16px}
    .medical-footer .footer-contact{grid-template-columns:1fr;gap:12px}
    .medical-footer .footer-brand-name{font-size:19px}
  }
  @media (prefers-reduced-motion:reduce){
    .medical-footer *{scroll-behavior:auto!important;transition:none!important}
  }
</style>

<footer class="medical-footer" role="contentinfo">
  <div class="container footer-cta-wrap">
    <section class="footer-cta" aria-label="<?php echo $escape($ctaKicker); ?>">
      <div class="footer-cta-copy">
        <span class="footer-kicker"><i class="ph-fill ph-heartbeat" aria-hidden="true"></i><?php echo $escape($ctaKicker); ?></span>
        <strong><?php echo $escape($ctaTitle); ?></strong>
        <p><?php echo $escape($footerIntro); ?></p>
      </div>
      <a class="footer-cta-btn" href="<?php echo $escape($contactPath); ?>">
        <?php echo $escape($ctaText); ?>
        <i class="ph ph-arrow-up-right" aria-hidden="true"></i>
      </a>
    </section>
  </div>

  <div class="container footer-main">
    <div class="footer-grid">
      <section class="footer-brand" aria-label="<?php echo $escape($brandName); ?>">
        <a class="footer-brand-link" href="<?php echo $escape(site_localized_path('/', $locale)); ?>" aria-label="<?php echo $escape($brandName); ?>">
          <span class="footer-brand-icon"><i class="ph-fill ph-heartbeat" aria-hidden="true"></i></span>
          <span>
            <strong class="footer-brand-name"><?php echo $escape($brandName); ?></strong>
            <small class="footer-brand-label"><?php echo $escape($platformLabel); ?></small>
          </span>
        </a>
        <p class="footer-intro"><?php echo $escape($footerIntro); ?></p>
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
        <div class="footer-contact">
          <div class="footer-contact-item">
            <span class="footer-contact-icon"><i class="ph ph-phone" aria-hidden="true"></i></span>
            <div class="footer-contact-copy"><small>Hotline</small><a href="tel:<?php echo $escape(preg_replace('/[^+0-9]/', '', $hotline)); ?>"><?php echo $escape($hotline); ?></a></div>
          </div>
          <div class="footer-contact-item">
            <span class="footer-contact-icon"><i class="ph ph-envelope-simple" aria-hidden="true"></i></span>
            <div class="footer-contact-copy"><small>Email</small><a href="mailto:<?php echo $escape($email); ?>"><?php echo $escape($email); ?></a></div>
          </div>
          <div class="footer-contact-item">
            <span class="footer-contact-icon"><i class="ph ph-map-pin" aria-hidden="true"></i></span>
            <div class="footer-contact-copy"><small><?php echo $escape($addressLabel); ?></small><?php echo $escape($address); ?></div>
          </div>
          <div class="footer-contact-item">
            <span class="footer-contact-icon"><i class="ph ph-clock" aria-hidden="true"></i></span>
            <div class="footer-contact-copy"><small><?php echo $escape($hoursLabel); ?></small><?php echo $escape($hoursText); ?></div>
          </div>
        </div>
      </section>
    </div>
  </div>

  <div class="container footer-bottom">
    <span>&copy; <?php echo date('Y'); ?> <?php echo $escape($brandName); ?>. <?php echo $escape($copyright); ?></span>
    <span class="footer-bottom-note"><i class="ph-fill ph-shield-check" aria-hidden="true"></i><?php echo $escape($platformLabel); ?></span>
  </div>
</footer>
