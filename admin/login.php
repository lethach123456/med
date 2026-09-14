<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (admin_is_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MedReview Admin • Đăng nhập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root {
        --ink: #10203f;
        --ink-soft: #506481;
        --brand: #2864e8;
        --brand-deep: #144cc5;
        --teal: #19b394;
        --line: #dce6f7;
        --surface: #ffffff;
      }
      * { box-sizing: border-box; }
      body.auth-bg {
        min-height: 100vh;
        margin: 0;
        color: var(--ink);
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background:
          radial-gradient(900px 550px at -10% -10%, rgba(69, 127, 255, .22), transparent 68%),
          radial-gradient(760px 540px at 110% 105%, rgba(22, 184, 150, .16), transparent 65%),
          #f5f8fe;
      }
      .auth-shell {
        width: min(1120px, calc(100% - 40px));
        min-height: 100vh;
        margin: 0 auto;
        padding: 34px 0;
        display: grid;
        place-items: center;
      }
      .auth-layout {
        width: 100%;
        min-height: 620px;
        display: grid;
        grid-template-columns: minmax(0, 1.14fr) minmax(390px, .86fr);
        overflow: hidden;
        border: 1px solid rgba(196, 211, 238, .9);
        border-radius: 28px;
        background: rgba(255,255,255,.82);
        box-shadow: 0 28px 76px rgba(24, 52, 105, .14);
      }
      .auth-story {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        padding: 48px;
        color: #fff;
        background:
          radial-gradient(circle at 83% 17%, rgba(89, 148, 255, .52) 0 5%, transparent 5.3%),
          radial-gradient(circle at 91% 34%, rgba(47, 199, 175, .28) 0 12%, transparent 12.4%),
          linear-gradient(145deg, #061d4b 0%, #102f71 55%, #1c62d6 140%);
      }
      .auth-story::before,
      .auth-story::after {
        content: "";
        position: absolute;
        pointer-events: none;
        z-index: -1;
      }
      .auth-story::before {
        width: 480px;
        height: 480px;
        right: -220px;
        bottom: -230px;
        border: 62px solid rgba(255,255,255,.08);
        border-radius: 50%;
      }
      .auth-story::after {
        inset: 0;
        opacity: .15;
        background-image: linear-gradient(rgba(255,255,255,.24) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.24) 1px, transparent 1px);
        background-size: 36px 36px;
        mask-image: linear-gradient(90deg, #000 0%, transparent 92%);
      }
      .brand-lockup {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        color: inherit;
        text-decoration: none;
      }
      .brand-mark {
        position: relative;
        flex: 0 0 auto;
        width: 42px;
        height: 42px;
        display: inline-grid;
        place-items: center;
        border: 1px solid rgba(255,255,255,.48);
        border-radius: 14px;
        color: #fff;
        background: rgba(255,255,255,.12);
        box-shadow: inset 0 1px rgba(255,255,255,.17);
      }
      .brand-mark::after {
        content: "+";
        position: absolute;
        top: -7px;
        right: -7px;
        width: 18px;
        height: 18px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        color: #2864e8;
        font-size: 14px;
        font-weight: 800;
        background: #fff;
      }
      .brand-name { font-size: 20px; font-weight: 800; letter-spacing: -.045em; }
      .brand-caption { margin-top: 1px; color: rgba(255,255,255,.72); font-size: 12px; font-weight: 600; }
      .story-content { position: relative; z-index: 1; max-width: 505px; margin-top: 88px; }
      .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 11px;
        border: 1px solid rgba(167, 249, 226, .3);
        border-radius: 999px;
        color: #c6fff0;
        font-size: 12px;
        font-weight: 750;
        letter-spacing: .035em;
        text-transform: uppercase;
        background: rgba(16, 182, 145, .15);
      }
      .story-title {
        max-width: 470px;
        margin: 20px 0 14px;
        font-size: clamp(34px, 4vw, 48px);
        font-weight: 780;
        line-height: 1.08;
        letter-spacing: -.055em;
      }
      .story-copy { max-width: 430px; margin: 0; color: rgba(237,244,255,.79); font-size: 16px; line-height: 1.65; }
      .story-points {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        max-width: 480px;
        margin: 44px 0 0;
      }
      .story-point {
        min-height: 88px;
        padding: 16px;
        border: 1px solid rgba(255,255,255,.13);
        border-radius: 16px;
        background: rgba(2, 19, 54, .18);
        backdrop-filter: blur(8px);
      }
      .story-point i { color: #7eead2; }
      .story-point strong { display: block; margin-top: 10px; font-size: 14px; }
      .story-point span { display: block; margin-top: 3px; color: rgba(255,255,255,.67); font-size: 12px; line-height: 1.4; }
      .auth-main {
        display: flex;
        align-items: center;
        padding: 44px clamp(30px, 5vw, 60px);
        background: var(--surface);
      }
      .login-wrap { width: 100%; max-width: 390px; margin: 0 auto; }
      .login-brand { display: none; }
      .login-kicker { margin: 0 0 9px; color: var(--brand); font-size: 12px; font-weight: 800; letter-spacing: .095em; text-transform: uppercase; }
      .login-title { margin: 0; color: var(--ink); font-size: 31px; font-weight: 790; letter-spacing: -.045em; }
      .login-subtitle { margin: 10px 0 30px; color: var(--ink-soft); font-size: 15px; line-height: 1.55; }
      .setup-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-left: auto;
        padding: 7px 10px;
        border: 1px solid var(--line);
        border-radius: 10px;
        color: #5d6f8a;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: .18s ease;
      }
      .setup-link:hover { border-color: #b8cdf6; color: var(--brand); background: #f5f8ff; }
      .alert { border-radius: 12px; border-width: 1px; font-size: 14px; }
      .form-label { margin-bottom: 8px; color: #31435f; font-size: 13px; font-weight: 750; }
      .field-wrap { position: relative; }
      .field-icon {
        position: absolute;
        z-index: 3;
        top: 50%;
        left: 15px;
        color: #89a0c2;
        transform: translateY(-50%);
        pointer-events: none;
      }
      .form-control {
        min-height: 52px;
        padding: 12px 15px 12px 43px;
        border: 1px solid #d8e3f3;
        border-radius: 13px !important;
        color: var(--ink);
        font-size: 15px;
        box-shadow: none;
        transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
      }
      .form-control::placeholder { color: #a6b4c9; }
      .form-control:focus {
        border-color: #80a6f4;
        box-shadow: 0 0 0 4px rgba(40,100,232,.11);
      }
      .password-wrap .form-control { padding-right: 62px; }
      .password-toggle {
        position: absolute;
        z-index: 4;
        top: 50%;
        right: 8px;
        min-width: 42px;
        padding: 7px 9px;
        border: 0;
        color: #5b7195;
        font-size: 12px;
        font-weight: 750;
        transform: translateY(-50%);
        background: transparent;
      }
      .password-toggle:hover { color: var(--brand); background: #f1f6ff; }
      .form-check-input { width: 17px; height: 17px; margin-top: .15em; border-color: #b4c6e1; }
      .form-check-input:checked { border-color: var(--brand); background-color: var(--brand); }
      .form-check-label, .home-link { color: #62748e; font-size: 13px; }
      .home-link { font-weight: 700; text-decoration: none; }
      .home-link:hover { color: var(--brand); text-decoration: underline; text-underline-offset: 3px; }
      .btn-login {
        min-height: 53px;
        border: 0;
        border-radius: 13px;
        color: #fff;
        font-size: 15px;
        font-weight: 760;
        background: linear-gradient(135deg, #3174f1, #1d58d8);
        box-shadow: 0 12px 22px rgba(37, 98, 228, .24);
        transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
      }
      .btn-login:hover { color: #fff; filter: brightness(1.025); transform: translateY(-1px); box-shadow: 0 15px 28px rgba(37, 98, 228, .3); }
      .btn-login:active { transform: translateY(0); }
      .login-footer {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #edf1f7;
        color: #8090a8;
        font-size: 12px;
        line-height: 1.52;
      }
      .login-footer i { margin-top: 2px; color: var(--teal); }
      @media (max-width: 991.98px) {
        .auth-shell { width: min(580px, calc(100% - 32px)); padding: 22px 0; }
        .auth-layout { display: block; min-height: 0; border-radius: 24px; }
        .auth-story { display: none; }
        .auth-main { min-height: min(620px, calc(100vh - 44px)); padding: 42px clamp(24px, 8vw, 60px); }
        .login-brand { display: inline-flex; margin-bottom: 42px; color: var(--ink); }
        .login-brand .brand-mark { border-color: #bcd2fc; color: var(--brand); background: #f0f5ff; }
        .login-brand .brand-mark::after { color: #fff; background: var(--brand); }
        .login-brand .brand-caption { color: #7a8da9; }
      }
      @media (max-width: 480px) {
        .auth-shell { width: 100%; padding: 0; }
        .auth-layout { min-height: 100vh; border: 0; border-radius: 0; box-shadow: none; }
        .auth-main { min-height: 100vh; padding: 30px 24px; }
        .login-brand { margin-bottom: 35px; }
        .login-title { font-size: 28px; }
        .login-subtitle { margin-bottom: 26px; }
        .form-control { min-height: 50px; }
      }
    </style>
  </head>
  <body class="auth-bg">
    <main class="auth-shell">
      <section class="auth-layout" aria-label="Đăng nhập quản trị MedReview">
        <aside class="auth-story">
          <a class="brand-lockup" href="/" aria-label="Về trang chủ MedReview">
            <span class="brand-mark" aria-hidden="true"><i class="fa-regular fa-heart"></i></span>
            <span>
              <span class="brand-name d-block">MedReview</span>
              <span class="brand-caption d-block">Cộng đồng review y tế đáng tin cậy</span>
            </span>
          </a>
          <div class="story-content">
            <div class="eyebrow"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i> Không gian quản trị</div>
            <h1 class="story-title">Dữ liệu y tế rõ ràng, quản trị tập trung.</h1>
            <p class="story-copy">Cập nhật hồ sơ cơ sở, nội dung, đánh giá và thư viện ảnh của MedReview trong một không gian làm việc thống nhất.</p>
            <div class="story-points" aria-label="Tính năng khu vực quản trị">
              <div class="story-point">
                <i class="fa-solid fa-hospital-user" aria-hidden="true"></i>
                <strong>Hồ sơ y tế</strong>
                <span>Quản lý dữ liệu cơ sở và bác sĩ.</span>
              </div>
              <div class="story-point">
                <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
                <strong>Thư viện tập trung</strong>
                <span>Kiểm soát ảnh và nội dung xuất bản.</span>
              </div>
            </div>
          </div>
        </aside>

        <section class="auth-main">
          <div class="login-wrap">
            <a class="brand-lockup login-brand" href="/" aria-label="Về trang chủ MedReview">
              <span class="brand-mark" aria-hidden="true"><i class="fa-regular fa-heart"></i></span>
              <span>
                <span class="brand-name d-block">MedReview</span>
                <span class="brand-caption d-block">Cổng quản trị</span>
              </span>
            </a>

            <div class="d-flex align-items-start gap-3">
              <div>
                <p class="login-kicker">MedReview Admin</p>
                <h2 class="login-title">Chào mừng trở lại</h2>
              </div>
              <a class="setup-link" href="/admin/setup.php" title="Thiết lập hệ thống">
                <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><span>Setup</span>
              </a>
            </div>
            <p class="login-subtitle">Đăng nhập bằng tài khoản quản trị để tiếp tục làm việc.</p>

            <div id="alert" class="alert d-none mb-4" role="alert" aria-live="polite"></div>

            <form id="loginForm" class="vstack gap-3" novalidate>
              <div>
                <label for="username" class="form-label">Tài khoản</label>
                <div class="field-wrap">
                  <i class="field-icon fa-regular fa-user" aria-hidden="true"></i>
                  <input id="username" name="username" class="form-control" autocomplete="username" placeholder="Nhập tài khoản quản trị" required>
                  <div class="invalid-feedback">Vui lòng nhập tài khoản.</div>
                </div>
              </div>

              <div>
                <label for="password" class="form-label">Mật khẩu</label>
                <div class="field-wrap password-wrap">
                  <i class="field-icon fa-solid fa-lock" aria-hidden="true"></i>
                  <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" placeholder="Nhập mật khẩu" required>
                  <button id="togglePassword" class="password-toggle" type="button" aria-label="Hiện mật khẩu"><i class="fa-regular fa-eye me-1" aria-hidden="true"></i><span id="togglePasswordText">Hiện</span></button>
                  <div class="invalid-feedback">Vui lòng nhập mật khẩu.</div>
                </div>
              </div>

              <div class="d-flex align-items-center justify-content-between gap-3 pt-1">
                <div class="form-check mb-0">
                  <input id="remember" class="form-check-input" type="checkbox">
                  <label class="form-check-label" for="remember">Ghi nhớ phiên</label>
                </div>
                <a class="home-link" href="/">Về trang chủ <i class="fa-solid fa-arrow-up-right-from-square ms-1" aria-hidden="true"></i></a>
              </div>

              <button id="btnSubmit" class="btn btn-login w-100 mt-2" type="submit">
                <i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i><span id="btnText">Đăng nhập</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
              </button>
            </form>

            <div class="login-footer">
              <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              <span>Chưa có dữ liệu người dùng? Mở <a href="/admin/setup.php">Setup</a> để hoàn tất thiết lập tài khoản quản trị.</span>
            </div>
          </div>
        </section>
      </section>
    </main>

    <script>
      const form = document.getElementById("loginForm");
      const alertBox = document.getElementById("alert");
      const btnSubmit = document.getElementById("btnSubmit");
      const btnText = document.getElementById("btnText");
      const btnSpinner = document.getElementById("btnSpinner");
      const togglePassword = document.getElementById("togglePassword");
      const togglePasswordText = document.getElementById("togglePasswordText");

      function showAlert(type, message) {
        alertBox.className = "alert alert-" + type;
        alertBox.textContent = message;
        alertBox.classList.remove("d-none");
      }

      function setLoading(isLoading) {
        btnSubmit.disabled = isLoading;
        if (isLoading) {
          btnSpinner.classList.remove("d-none");
          btnText.textContent = "Đang đăng nhập...";
        } else {
          btnSpinner.classList.add("d-none");
          btnText.textContent = "Đăng nhập";
        }
      }

      togglePassword.addEventListener("click", () => {
        const input = document.getElementById("password");
        const isHidden = input.type === "password";
        input.type = isHidden ? "text" : "password";
        togglePasswordText.textContent = isHidden ? "Ẩn" : "Hiện";
        togglePassword.querySelector("i").className = isHidden ? "fa-regular fa-eye-slash me-2" : "fa-regular fa-eye me-2";
      });

      form.addEventListener("submit", async (e) => {
        e.preventDefault();

        alertBox.classList.add("d-none");
        form.classList.add("was-validated");

        const username = document.getElementById("username").value.trim();
        const password = document.getElementById("password").value;
        if (!username || !password) {
          return;
        }

        const payload = {
          username,
          password
        };

        try {
          setLoading(true);
          const res = await fetch("/admin/api/login.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
          });
          const data = await res.json().catch(() => ({}));

          if (!res.ok || !data.ok) {
            showAlert("danger", data.message || "Đăng nhập thất bại.");
            return;
          }

          window.location.href = "/admin/dashboard.php";
        } catch (err) {
          showAlert("danger", "Không thể kết nối tới server.");
        } finally {
          setLoading(false);
        }
      });
    </script>
  </body>
</html>
