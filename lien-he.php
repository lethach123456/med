<?php
declare(strict_types=1);
$sent = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim((string)($_POST['name'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $message = trim((string)($_POST['message'] ?? ''));
  if ($name === '' || $phone === '' || $message === '') {
    $error = 'Vui lòng nhập Họ tên, Số điện thoại và Nội dung.';
  } else {
    $sent = true;
  }
}
require_once __DIR__ . '/db.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
front_editor_page_maybe_redirect('contact');
$hotline = site_hotline('0988 123 456');
$emailInfo = site_email('info@dentaelite.vn');
$address = site_address('123 Đường ABC, Quận 1, TP. Hồ Chí Minh');
// Hero slider de mau cung 1 anh. Ban co the tu thay URL anh ngay tai file nay.
$heroSlides = [
  '/uploads/library/2026/06/cce25ee27ab1105ffcea7ec9f0efac3f.png',
];
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
      $seo = front_editor_page_seo('contact', [
        'title' => 'Top Dental Clinic • Liên hệ',
        'description' => 'Liên hệ Top Dental Clinic để đặt lịch tư vấn, nhận thông tin dịch vụ và kết nối với đội ngũ chăm sóc khách hàng nhanh chóng.',
      ]);
      $seoKeywords = (string) ($seo['keywords'] ?? '');
    ?>
    <title><?php echo htmlspecialchars((string) ($seo['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars((string) ($seo['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars((string) ($seo['canonical_path'] ?? '/lien-he'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root{
        --bg: #f0f9ff;
        --surface: rgba(255,255,255,0.96);
        --surface-2: rgba(255,255,255,0.98);
        --border: rgba(37,99,235,0.12);
        --text: rgba(15,23,42,0.95);
        --muted: rgba(15,23,42,0.68);
        --brand: #1e40af;
        --brand-light: #dbeafe;
        --brand-2: #3b82f6;
        --shadow: 0 18px 50px rgba(37,99,235,0.18);
        --radius: 10px;
        --max: 1320px;
        --img-1: url("https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=1200&q=80");
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
      .icon-gap{ margin-right: 8px; }
      .topbar{ position: sticky; top: 0; z-index: 50; backdrop-filter: blur(12px); background: linear-gradient(180deg, rgba(255,255,255,0.78), rgba(255,255,255,0.58)); border-bottom: 1px solid var(--border); }
      .nav{ position: relative; display:flex; align-items:center; justify-content:space-between; gap: 16px; padding: 14px 0; }
      .brand{ display:flex; align-items:center; gap: 10px; min-width: max-content; }
      .brand-mark{ width: 44px; height: 44px; border-radius: 14px; background: radial-gradient(18px 18px at 30% 25%, rgba(255,255,255,0.8), transparent 60%), linear-gradient(135deg, var(--brand-2), var(--brand)); box-shadow: 0 18px 50px rgba(37,99,235,0.25); display: flex; align-items: center; justify-content: center; }
      .brand-mark i{ color: #fff; font-size: 20px; }
      .brand-title{ line-height: 1.1; }
      .brand-title strong{ font-family: "Playfair Display", serif; letter-spacing: -0.02em; font-weight: 700; color: var(--brand); }
      .brand-title span{ display:block; margin-top: 2px; font-size: 12px; color: var(--muted); }
      .navlinks{ display:flex; align-items:center; gap: 16px; }
      .navlinks a{ font-size: 14px; color: var(--muted); padding: 10px 10px; border-radius: var(--radius); transition: background 160ms ease, color 160ms ease; }
      .navlinks a:hover{ background: var(--brand-light); color: var(--brand); }
      .nav-cta{ display:flex; align-items:center; gap: 10px; min-width: max-content; }
      .pill{ display:inline-flex; align-items:center; gap: 8px; padding: 10px 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--brand-light); color: var(--brand); font-size: 13px; }
      .nav-toggle{ display:none; width: 42px; height: 42px; border-radius: 999px; border: 1px solid var(--border); background: rgba(255,255,255,0.9); align-items:center; justify-content:center; cursor: pointer; }
      .btn{ appearance:none; border:0; cursor:pointer; border-radius:var(--radius); padding: 11px 14px; font-weight: 600; font-size:14px; display:inline-flex; align-items:center; gap:10px; transition: transform 160ms ease, filter 160ms ease, background 160ms ease, border-color 160ms ease; }
      .btn:active{ transform: translateY(1px); }
      .btn-primary{ background: var(--brand); color: #fff; box-shadow: 0 18px 60px rgba(37,99,235,0.35); }
      .btn-primary:hover{ background: #1e3a8a; }
      .btn-ghost{ background: #fff; color: var(--brand); border: 1px solid var(--border); }
      .btn-ghost:hover{ background: var(--brand-light); }
      .hero{ position: relative; overflow: hidden; }
      .hero-slider{ position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94); }
      .hero-slide{ min-width: 100%; height: 100%; background-size: cover; background-position: center; }
      .hero-slider-controls{ position: absolute; top: 50%; left: 0; right: 0; transform: translateY(-50%); display: flex; justify-content: space-between; padding: 0 20px; z-index: 10; }
      .hero-slider-btn{ width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.9); border:1px solid rgba(37,99,235,0.2); color:var(--brand); font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
      .hero-slider-btn:hover{ background: var(--brand); color: white; border-color: var(--brand); transform: scale(1.05); }
      .hero-slider-dots{ position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%); display: flex; gap:10px; z-index:10; }
      .hero-slider-dot{ width:12px; height:12px; border-radius:50%; background: rgba(255,255,255,0.5); border:0; cursor:pointer; transition: all 0.3s ease; }
      .hero-slider-dot.active{ background: white; transform: scale(1.3); box-shadow: 0 0 10px rgba(255,255,255,0.8); }
      .hero-inner{ position: relative; padding: 110px 0 72px; min-height: 78vh; display:flex; align-items:center; }
      .hero-copy{ padding: 22px 20px; border-radius: var(--radius); background: rgba(0,0,0,0.26); border: 1px solid rgba(255,255,255,0.14); box-shadow: 0 28px 80px rgba(0,0,0,0.22); color: rgba(255,255,255,0.96); backdrop-filter: blur(12px); }
      .hero-copy h1{ margin: 0 0 10px; font-family: "Playfair Display", serif; font-weight: 700; letter-spacing: -0.03em; line-height: 1.1; font-size: clamp(30px, 3.0vw, 44px); }
      .hero-copy p{ margin:0; color: rgba(255,255,255,0.82); line-height: 1.7; max-width: 62ch; }
      .hero-actions{ margin-top:18px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
      .hero-actions .btn-ghost{ border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.10); color: rgba(255,255,255,0.92); }
      .section{ padding: 34px 0; }
      .section h2{ margin:0 0 14px; text-align:center; font-family:"Playfair Display",serif; font-size:22px; letter-spacing:-0.02em; }
      .section-lead{ text-align:center; color:var(--muted); max-width:70ch; margin:0 auto 16px; line-height:1.7; font-size:14px; }
      .grid{ display:grid; grid-template-columns: 1fr 1fr; gap:14px; align-items:start; }
      .card{ border-radius: var(--radius); border:1px solid var(--border); background: var(--surface-2); box-shadow: var(--shadow); overflow:hidden; }
      .card .hd{ padding: 12px 14px; font-weight:800; font-size:13px; letter-spacing:0.08em; text-transform: uppercase; color: rgba(15,23,42,0.72); background: var(--brand-light); border-bottom:1px solid var(--border); }
      .card .bd{ padding:14px 14px; }
      .contact-item{ display:flex; gap:12px; align-items:flex-start; padding:12px 0; border-bottom:1px dashed var(--border); }
      .contact-item:last-child{ border-bottom:0; }
      .contact-icon{ width:44px; height:44px; border-radius: var(--radius); background: var(--brand-light); display:flex; align-items:center; justify-content:center; color:var(--brand); font-size:18px; flex-shrink:0; }
      .contact-text{ display:grid; gap:4px; }
      .contact-text b{ font-size:14px; }
      .contact-text span{ color:var(--muted); font-size:13px; line-height:1.6; }
      .form{ display:grid; gap:10px; }
      .row{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
      .field{ display:grid; gap:6px; }
      label{ font-size:13px; color: rgba(15,23,42,0.72); font-weight:700; }
      input, textarea{ width:100%; padding:12px 12px; border-radius: var(--radius); border:1px solid var(--border); background: #fff; color: var(--text); font:inherit; outline:none; }
      textarea{ min-height:140px; resize:vertical; }
      input:focus, textarea:focus{ border-color: var(--brand-2); box-shadow: 0 0 0 4px rgba(59,130,246,0.14); }
      .alert{ border-radius: var(--radius); padding:12px 12px; border:1px solid var(--border); background: #fff; color: var(--text); font-size:14px; line-height:1.6; }
      .alert.ok{ border-color: rgba(34,197,94,0.22); background: rgba(34,197,94,0.08); }
      .alert.err{ border-color: rgba(239,68,68,0.22); background: rgba(239,68,68,0.08); }
      .map{ margin-top:14px; border-radius: var(--radius); overflow:hidden; border:1px solid var(--border); box-shadow: var(--shadow); height:360px; }
      .map iframe{ width:100%; height:100%; border:0; display:block; }
      .site-footer{ margin-top:34px; position:relative; overflow:hidden; color: rgba(255,255,255,0.95); background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3b82f6 100%); }
      .site-footer::before{ content:""; position:absolute; inset:0; background: var(--img-1) center/cover no-repeat; filter: saturate(0.7) contrast(1.10); opacity:0.15; z-index:0; pointer-events:none; }
      .footer-layer{ position:relative; z-index:1; }
      .footer-cta{ border-bottom:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.34); }
      .footer-cta-inner{ padding:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
      .footer-cta-inner b{ font-family:"Playfair Display",serif; font-weight:600; color: rgba(255,255,255,0.92); }
      .footer-cta .btn-ghost{ border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.10); color: rgba(255,255,255,0.92); }
      .footer-cta .btn-ghost:hover{ background: rgba(255,255,255,0.16); }
      .footer-main{ padding:18px 0 10px; }
      .footer-cols{ display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:18px; }
      .footer-col h4{ margin:0 0 10px; font-size:14px; font-weight:700; color: rgba(255,255,255,0.92); padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.10); }
      .footer-link{ display:block; color: rgba(255,255,255,0.78); font-size:13px; padding:6px 0; }
      .footer-link:hover{ color: rgba(255,255,255,0.92); }
      .footer-contact{ display:grid; gap:8px; color: rgba(255,255,255,0.78); font-size:13px; }
      .footer-contact .item{ display:flex; gap:10px; align-items:flex-start; line-height:1.5; }
      .footer-contact i{ width:18px; margin-top:2px; text-align:center; color: rgba(255,255,255,0.90); }
      .footer-bottom{ border-top:1px solid rgba(255,255,255,0.10); padding:12px 0 14px; text-align:center; color: rgba(255,255,255,0.70); font-size:13px; }
      @media (max-width: 1024px){
        .grid{ grid-template-columns: 1fr; }
        .footer-cols{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      }
      @media (max-width:680px){
        .nav-toggle{ display:inline-flex; }
        .navlinks{ display:none; position:absolute; left:16px; right:16px; top:calc(100% + 10px); padding:10px; border-radius:16px; border:1px solid var(--border); background: rgba(255,255,255,0.96); box-shadow:0 26px 80px rgba(17,24,39,0.18); flex-direction:column; align-items:stretch; gap:6px; }
        .navlinks a{ padding:12px 12px; }
        .navlinks.is-open{ display:flex; }
        .pill{ display:none; }
        .row{ grid-template-columns: 1fr; }
        .map{ height:300px; }
        .footer-cols{ grid-template-columns:1fr; gap:12px; }
        .footer-col h4{ margin:0 0 8px; }
        .footer-link{ padding:8px 0; font-size:14px; }
        .footer-cta-inner{ padding:12px 0; flex-direction:column; align-items:flex-start; gap:10px; }
        .footer-cta-inner .btn{ width:100%; justify-content:center; }
        .footer-contact{ gap:10px; }
        .footer-bottom{ font-size:12px; padding:10px 0 12px; }
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <section class="hero">
      <div class="hero-slider" id="heroSlider">
        <?php foreach ($heroSlides as $slideUrl): ?>
          <div class="hero-slide" style="background: linear-gradient(90deg, rgba(30,64,175,0.75) 0%, rgba(30,64,175,0.55) 42%, rgba(30,64,175,0.35) 72%, rgba(30,64,175,0.5) 100%), url('<?php echo htmlspecialchars($slideUrl, ENT_QUOTES, 'UTF-8'); ?>') center/cover no-repeat;"></div>
        <?php endforeach; ?>
        <?php if (empty($heroSlides)): ?>
          <div class="hero-slide" style="background: linear-gradient(90deg, rgba(30,64,175,0.75) 0%, rgba(30,64,175,0.55) 42%, rgba(30,64,175,0.35) 72%, rgba(30,64,175,0.5) 100%), var(--img-1) center/cover no-repeat;"></div>
        <?php endif; ?>
      </div>
      <div class="container hero-inner">
        <div class="hero-copy">
          <h1>Liên hệ Top Dental Clinic</h1>
          <p>Đăng ký tư vấn miễn phí về dịch vụ nha khoa, đặc biệt là răng sứ thẩm mỹ. Đội ngũ bác sĩ chuyên gia sẽ hỗ trợ bạn.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="tel:<?php echo htmlspecialchars(str_replace(' ', '', $hotline), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> Gọi ngay</a>
          </div>
        </div>
      </div>
      <div class="hero-slider-controls">
        <button class="hero-slider-btn hero-slider-prev" id="heroSliderPrev" aria-label="Previous slide"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="hero-slider-btn hero-slider-next" id="heroSliderNext" aria-label="Next slide"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
      <div class="hero-slider-dots" id="heroSliderDots"></div>
    </section>
    <section class="section">
      <div class="container">
        <div class="grid">
          <div class="card">
            <div class="hd">Thông tin liên hệ</div>
            <div class="bd">
              <div class="contact-item">
                <div class="contact-icon"><i class="fa-solid fa-phone"></i></div>
                <div class="contact-text">
                  <b>Hotline</b>
                  <span><a href="tel:<?php echo htmlspecialchars(str_replace(' ', '', $hotline), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($hotline, ENT_QUOTES, 'UTF-8'); ?></a></span>
                </div>
              </div>
              <div class="contact-item">
                <div class="contact-icon"><i class="fa-solid fa-envelope"></i></div>
                <div class="contact-text">
                  <b>Email</b>
                  <span><a href="mailto:<?php echo htmlspecialchars($emailInfo, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($emailInfo, ENT_QUOTES, 'UTF-8'); ?></a></span>
                </div>
              </div>
              <div class="contact-item">
                <div class="contact-icon"><i class="fa-solid fa-location-dot"></i></div>
                <div class="contact-text">
                  <b>Địa chỉ</b>
                  <span><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
              </div>
              <div class="contact-item">
                <div class="contact-icon"><i class="fa-regular fa-clock"></i></div>
                <div class="contact-text">
                  <b>Giờ làm việc</b>
                  <span>08:00–18:00 (Thứ 2–Thứ 7)</span>
                </div>
              </div>
              <div class="map">
                <iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=Ho%20Chi%20Minh%20City&output=embed"></iframe>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="hd">Gửi yêu cầu tư vấn</div>
            <div class="bd">
              <?php if ($sent): ?>
                <div class="alert ok">Đã nhận thông tin. DentaElite sẽ liên hệ lại sớm nhất.</div>
              <?php elseif ($error !== ''): ?>
                <div class="alert err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
              <form class="form" method="post" action="/lien-he.php">
                <div class="row">
                  <div class="field">
                    <label>Họ tên</label>
                    <input name="name" value="<?php echo htmlspecialchars((string)($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nhập họ tên">
                  </div>
                  <div class="field">
                    <label>Số điện thoại</label>
                    <input name="phone" value="<?php echo htmlspecialchars((string)($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nhập số điện thoại">
                  </div>
                </div>
                <div class="field">
                  <label>Email (tuỳ chọn)</label>
                  <input name="email" value="<?php echo htmlspecialchars((string)($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nhập email">
                </div>
                <div class="field">
                  <label>Nội dung</label>
                  <textarea name="message" placeholder="Mô tả nhu cầu, thắc mắc về dịch vụ nha khoa..."><?php echo htmlspecialchars((string)($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Gửi yêu cầu</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php if (function_exists('front_editor_render')) { front_editor_render('contact'); } ?>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
    <script>
      (function(){
        function boot(){
          var hero = document.querySelector(".hero");
          if (!hero) return;
          var slider = document.getElementById("heroSlider");
          var slides = slider ? slider.querySelectorAll(".hero-slide") : [];
          if (!slider || slides.length === 0) return;
          var prevBtn = document.getElementById("heroSliderPrev");
          var nextBtn = document.getElementById("heroSliderNext");
          var dotsContainer = document.getElementById("heroSliderDots");
          var currentSlide = 0;
          var autoSlideInterval;

          // Create dots
          for (var i = 0; i < slides.length; i++) {
            var dot = document.createElement("button");
            dot.className = "hero-slider-dot" + (i === 0 ? " active" : "");
            dot.setAttribute("aria-label", "Slide " + (i + 1));
            (function(index){
              dot.addEventListener("click", function(){
                goToSlide(index);
              });
            })(i);
            dotsContainer.appendChild(dot);
          }
          var dots = dotsContainer.querySelectorAll(".hero-slider-dot");

          function updateSlider(){
            slider.style.transform = "translateX(" + (-currentSlide * 100) + "%)";
            dots.forEach(function(dot, index){ dot.classList.toggle("active", index === currentSlide); });
          }
          function goToSlide(index){
            currentSlide = index;
            if (currentSlide < 0) currentSlide = slides.length - 1;
            if (currentSlide >= slides.length) currentSlide = 0;
            updateSlider();
            resetAutoSlide();
          }
          function nextSlide(){ goToSlide(currentSlide + 1); }
          function prevSlide(){ goToSlide(currentSlide - 1); }
          function startAutoSlide(){ autoSlideInterval = setInterval(nextSlide, 5000); }
          function resetAutoSlide(){ clearInterval(autoSlideInterval); startAutoSlide(); }
          if (prevBtn) prevBtn.addEventListener("click", prevSlide);
          if (nextBtn) nextBtn.addEventListener("click", nextSlide);

          // Touch Swipe Support
          var touchStartX = 0;
          var touchEndX = 0;
          slider.addEventListener("touchstart", function(e){ touchStartX = e.changedTouches[0].screenX; }, { passive: true });
          slider.addEventListener("touchend", function(e){
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
          }, { passive: true });
          function handleSwipe(){
            var swipeThreshold = 50;
            var diff = touchStartX - touchEndX;
            if (Math.abs(diff) > swipeThreshold){
              if (diff > 0) nextSlide();
              else prevSlide();
            }
          }
          startAutoSlide();
        }
        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
        else boot();
      })();
    </script>
    <script>
      (function(){
        var toggle = document.querySelector('[data-nav-toggle="1"]');
        var nav = document.querySelector('.navlinks');
        if (!toggle || !nav) return;
        toggle.addEventListener('click', function(){ nav.classList.toggle('is-open'); });
        document.addEventListener('click', function(e){
          if (!nav.classList.contains('is-open')) return;
          if (e.target === toggle || toggle.contains(e.target)) return;
          if (e.target === nav || nav.contains(e.target)) return;
          nav.classList.remove('is-open');
        });
      })();
    </script>
  </body>
</html>
