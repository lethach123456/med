<?php
declare(strict_types=1);

$adminPageTitle = isset($adminPageTitle) ? (string) $adminPageTitle : 'Admin';
if (strpos($adminPageTitle, 'Admin • ') === 0) {
    $adminPageTitle = 'MedReview Admin • ' . substr($adminPageTitle, strlen('Admin • '));
}
$adminHeaderTitle = isset($adminHeaderTitle) ? (string) $adminHeaderTitle : $adminPageTitle;
$adminHeaderSubtitle = isset($adminHeaderSubtitle) ? (string) $adminHeaderSubtitle : '';
$adminActive = isset($adminActive) ? (string) $adminActive : '';
$adminSettingsActive = in_array($adminActive, ['settings', 'medical-ai-prompts', 'medical-media-worker', 'cron', 'users'], true);
$adminCanManageSystem = function_exists('admin_is_admin') && admin_is_admin();
?>
<!DOCTYPE html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($adminPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
      :root {
        --bs-body-font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        --admin-sidebar-w: 268px;
        --sidebar-w: var(--admin-sidebar-w);
        --admin-ink: #16233a;
        --admin-ink-soft: #4e607a;
        --admin-muted: #7e8da3;
        --admin-line: #e2eaf4;
        --admin-soft: #f6f9fe;
        --admin-blue: #2563eb;
        --admin-blue-dark: #1647b9;
        --admin-blue-soft: #edf4ff;
        --admin-teal: #11a385;
        --admin-sidebar: #0b1730;
      }

      html { scroll-padding-top: 92px; background: #f4f7fc; }
      body {
        min-width: 320px;
        color: var(--admin-ink);
        font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        text-rendering: optimizeLegibility;
        background:
          radial-gradient(1100px 520px at 88% -100px, rgba(66, 137, 255, .13), transparent 62%),
          radial-gradient(800px 440px at -10% 10%, rgba(39, 191, 161, .065), transparent 62%),
          #f4f7fc;
      }

      .app-shell { min-height: 100vh; }
      .content { min-width: 0; }
      .min-w-0 { min-width: 0; }
      .admin-content { display: flex; min-height: 100vh; flex-direction: column; }
      .admin-content > main { flex: 1 0 auto; }

      /* Sidebar */
      .sidebar {
        box-sizing: border-box;
        width: var(--admin-sidebar-w) !important;
        min-width: var(--admin-sidebar-w) !important;
        max-width: var(--admin-sidebar-w) !important;
        flex: 0 0 var(--admin-sidebar-w) !important;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-x: hidden;
        overflow-y: auto;
        color: rgba(238, 245, 255, .82);
        background:
          radial-gradient(330px 330px at 15% -5%, rgba(72, 130, 255, .28), transparent 65%),
          linear-gradient(180deg, #101f3d 0%, #09152b 60%, #071124 100%);
        box-shadow: 16px 0 38px rgba(11, 26, 53, .10);
        scrollbar-color: rgba(255,255,255,.25) transparent;
        scrollbar-width: thin;
      }
      .sidebar::-webkit-scrollbar { width: 7px; }
      .sidebar::-webkit-scrollbar-thumb { border-radius: 99px; background: rgba(255,255,255,.22); }
      .sidebar .brand,
      .sidebar .nav-link span { white-space: nowrap; }
      .sidebar .brand { color: #fff; text-decoration: none; }
      .admin-brand-mark {
        display: inline-grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border: 1px solid rgba(168, 203, 255, .34);
        border-radius: 13px;
        color: #fff;
        background: linear-gradient(145deg, #3d82ff, #205cda);
        box-shadow: 0 10px 22px rgba(22, 81, 202, .30);
      }
      .admin-brand-copy { display: grid; line-height: 1.08; }
      .admin-brand-name { font-size: 1rem; font-weight: 800; letter-spacing: -.02em; }
      .admin-brand-role { margin-top: .24rem; color: rgba(217, 232, 255, .58); font-size: .66rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
      .sidebar .nav-heading {
        margin: 0 .68rem .42rem;
        color: rgba(208, 224, 250, .48);
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
      }
      .sidebar .nav-link {
        position: relative;
        min-height: 42px;
        color: rgba(233, 241, 255, .73);
        border: 1px solid transparent;
        border-radius: 11px;
        padding: .62rem .72rem;
        display: flex;
        align-items: center;
        gap: .72rem;
        font-size: .875rem;
        font-weight: 600;
        transition: color .16s ease, background-color .16s ease, border-color .16s ease, transform .16s ease;
      }
      .sidebar .nav-link i { width: 18px; text-align: center; color: rgba(173, 199, 244, .78); }
      .sidebar .nav-link:hover {
        color: #fff;
        border-color: rgba(171, 202, 255, .10);
        background: rgba(255,255,255,.075);
        transform: translateX(2px);
      }
      .sidebar .nav-link:hover i { color: #bcd7ff; }
      .sidebar .nav-link.active {
        color: #fff;
        border-color: rgba(142, 186, 255, .30);
        background: linear-gradient(90deg, rgba(47, 111, 240, .75), rgba(41, 99, 221, .46));
        box-shadow: inset 3px 0 0 #8cb9ff, 0 8px 18px rgba(4, 37, 103, .18);
      }
      .sidebar .nav-link.active i { color: #fff; }
      .admin-nav-group { margin: 0; padding: 0; }
      .admin-nav-group > summary { list-style: none; cursor: pointer; }
      .admin-nav-group > summary::-webkit-details-marker { display: none; }
      .admin-nav-summary-main { display: inline-flex; min-width: 0; align-items: center; gap: .72rem; }
      .admin-nav-summary-main > i { width: 18px; color: rgba(173, 199, 244, .78); text-align: center; }
      .admin-nav-caret { width: auto !important; margin-left: auto; color: rgba(192, 213, 248, .66) !important; font-size: .67rem; transition: transform .18s ease; }
      .admin-nav-group[open] > summary .admin-nav-caret { transform: rotate(180deg); }
      .admin-nav-group[open] > summary .admin-nav-summary-main > i { color: #fff; }
      .sidebar .admin-nav-submenu { display: grid; gap: .14rem; margin: .22rem .12rem .38rem 1.52rem; padding: .14rem 0 .14rem .48rem; border-left: 1px solid rgba(175, 204, 251, .18); }
      .sidebar .admin-nav-submenu .nav-link { min-height: 34px; padding: .46rem .56rem; border-radius: 8px; font-size: .78rem; font-weight: 650; }
      .sidebar .admin-nav-submenu .nav-link i { width: 15px; font-size: .72rem; }
      .sidebar .muted { color: rgba(216, 229, 249, .52); }
      .admin-account {
        padding: .82rem;
        border: 1px solid rgba(190, 213, 255, .12);
        border-radius: 13px;
        background: rgba(255,255,255,.045);
      }
      .admin-account .btn { border-color: rgba(221, 232, 255, .26); color: #eef5ff; }
      .admin-account .btn:hover { border-color: transparent; color: #fff; background: rgba(255,255,255,.14); }

      /* Header and mobile navigation */
      .page-header {
        z-index: 1020;
        min-height: 72px;
        border-color: rgba(215, 225, 240, .92) !important;
        background: rgba(252, 253, 255, .88);
        box-shadow: 0 1px 0 rgba(21, 44, 84, .02), 0 8px 28px rgba(32, 60, 109, .035);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
      }
      .admin-header-title { color: var(--admin-ink); font-size: 1rem; font-weight: 800; letter-spacing: -.015em; }
      .admin-header-subtitle { max-width: min(58vw, 700px); overflow: hidden; color: var(--admin-muted); font-size: .78rem; text-overflow: ellipsis; white-space: nowrap; }
      .admin-menu-toggle {
        min-width: 42px;
        min-height: 42px;
        padding: 0 .72rem;
        border-color: #d8e4f5;
        color: var(--admin-blue);
        background: #fff;
      }
      .admin-menu-toggle:hover,
      .admin-menu-toggle:focus { border-color: #a9c8fb; color: var(--admin-blue-dark); background: var(--admin-blue-soft); box-shadow: 0 0 0 .2rem rgba(37,99,235,.10); }
      .admin-home-link { white-space: nowrap; }
      .admin-offcanvas { width: min(290px, 88vw) !important; border: 0; color: var(--admin-ink); background: #fbfdff; }
      .admin-offcanvas .offcanvas-header { padding: 1.08rem 1.1rem; border-bottom: 1px solid var(--admin-line); }
      .admin-offcanvas .offcanvas-title { color: var(--admin-ink); font-size: .98rem; font-weight: 800; }
      .admin-offcanvas .offcanvas-body { padding: 1rem; }
      .admin-offcanvas .nav-link {
        min-height: 42px;
        color: #40516a;
        border: 1px solid transparent;
        border-radius: 10px;
        padding: .62rem .68rem;
        font-size: .88rem;
        font-weight: 650;
      }
      .admin-offcanvas .nav-link i { width: 20px; color: #6d82a5; text-align: center; }
      .admin-offcanvas .nav-link:hover { color: var(--admin-blue-dark); background: #f0f6ff; }
      .admin-offcanvas .nav-link.active { color: var(--admin-blue-dark); border-color: #cee0ff; background: #eaf3ff; }
      .admin-offcanvas .nav-link.active i { color: var(--admin-blue); }
      .admin-offcanvas .admin-nav-group > summary { display:flex; align-items:center; list-style: none; cursor: pointer; }
      .admin-offcanvas .admin-nav-group > summary::-webkit-details-marker { display: none; }
      .admin-offcanvas .admin-nav-summary-main { display: inline-flex; align-items: center; gap: .5rem; }
      .admin-offcanvas .admin-nav-summary-main > i { width: 20px; color: #6d82a5; text-align: center; }
      .admin-offcanvas .admin-nav-caret { margin-left: auto; color: #8191a8 !important; }
      .admin-offcanvas .admin-nav-group[open] > summary .admin-nav-caret { transform: rotate(180deg); }
      .admin-offcanvas .admin-nav-submenu { display: grid; gap: .16rem; margin: .25rem 0 .38rem 1.44rem; padding: .15rem 0 .15rem .48rem; border-left: 1px solid #dbe6f5; }
      .admin-offcanvas .admin-nav-submenu .nav-link { display:flex; align-items:center; gap:.5rem; min-height: 36px; padding: .47rem .58rem; font-size: .8rem; }
      .admin-offcanvas .admin-nav-submenu .nav-link i { width: 17px; font-size: .73rem; }

      /* Common page primitives. Page-specific rules still override these safely. */
      .admin-content main.container-fluid { max-width: 1760px; }
      .admin-content .card,
      .admin-content .modal-content {
        border-color: var(--admin-line);
        border-radius: 15px;
        background: rgba(255,255,255,.96);
        box-shadow: 0 8px 26px rgba(24, 55, 103, .045);
      }
      .admin-content .card-header,
      .admin-content .modal-header,
      .admin-content .modal-footer { border-color: var(--admin-line); background: transparent; }
      .admin-content .card-header { padding: 1rem 1.15rem; }
      .admin-content .card-body { color: var(--admin-ink); }
      .admin-content .text-secondary { color: var(--admin-muted) !important; }
      .shadow-soft { box-shadow: 0 12px 32px rgba(20, 49, 94, .075) !important; }
      .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
      .pre-box { white-space: pre-wrap; }
      .icon-pill {
        width: 40px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border: 1px solid #cae0ff;
        border-radius: 12px;
        color: var(--admin-blue);
        background: linear-gradient(145deg, #f2f7ff, #e5efff);
      }

      .admin-content .btn {
        min-height: 38px;
        border-radius: 10px;
        padding: .48rem .82rem;
        font-size: .84rem;
        font-weight: 700;
        line-height: 1.35;
        transition: transform .14s ease, box-shadow .14s ease, background-color .14s ease, border-color .14s ease;
      }
      .admin-content .btn:hover { transform: translateY(-1px); }
      .admin-content .btn:active { transform: translateY(0); }
      .admin-content .btn.btn-sm { min-height: 33px; border-radius: 8px; padding: .35rem .62rem; font-size: .76rem; }
      .admin-content .btn-primary {
        border-color: var(--admin-blue);
        background: linear-gradient(135deg, #3478f6, #2461dc);
        box-shadow: 0 7px 16px rgba(36, 97, 220, .18);
      }
      .admin-content .btn-primary:hover,
      .admin-content .btn-primary:focus { border-color: var(--admin-blue-dark); background: linear-gradient(135deg, #2b70ef, #1e52c5); box-shadow: 0 9px 20px rgba(36, 97, 220, .24); }
      .admin-content .btn-outline-secondary { border-color: #d4dfef; color: #52627a; background: #fff; }
      .admin-content .btn-outline-secondary:hover { border-color: #b6c8e2; color: #263d60; background: #f5f8fd; }
      .admin-content .btn-light { border-color: #e5ecf5; color: #52627a; background: #f8faff; }
      .admin-content .btn-light:hover { background: #f0f5fc; }

      .admin-content .form-label { margin-bottom: .42rem; color: #3f5069; font-size: .8rem; font-weight: 750; }
      .admin-content .form-control,
      .admin-content .form-select,
      .admin-content .input-group-text {
        min-height: 40px;
        border-color: #d8e3f1;
        color: #273a57;
        background: #fff;
        font-size: .87rem;
      }
      .admin-content .form-control,
      .admin-content .form-select { border-radius: 9px; }
      .admin-content .input-group > .form-control:not(:first-child),
      .admin-content .input-group > .form-select:not(:first-child) { border-top-left-radius: 0; border-bottom-left-radius: 0; }
      .admin-content .input-group > .form-control:not(:last-child),
      .admin-content .input-group > .form-select:not(:last-child) { border-top-right-radius: 0; border-bottom-right-radius: 0; }
      .admin-content .input-group-text { color: #7184a1; background: #f7faff; }
      .admin-content .form-control:focus,
      .admin-content .form-select:focus { border-color: #8bb4fb; box-shadow: 0 0 0 .22rem rgba(37,99,235,.11); }
      .admin-content textarea.form-control { min-height: 104px; }
      .admin-content .form-text { color: var(--admin-muted); font-size: .76rem; }
      .admin-content .form-check-input { border-color: #bfcfe6; }
      .admin-content .form-check-input:checked { border-color: var(--admin-blue); background-color: var(--admin-blue); }

      .admin-content .table { --bs-table-bg: transparent; --bs-table-striped-bg: #f8fbff; margin-bottom: 0; color: #30425e; }
      .admin-content .table > :not(caption) > * > * { padding: .78rem .9rem; border-color: #e8eef6; vertical-align: middle; }
      .admin-content .table > thead > tr > * { color: #5e718d; background: #f6f9fd; font-size: .7rem; font-weight: 800; letter-spacing: .045em; text-transform: uppercase; }
      .admin-content .table > tbody > tr { transition: background-color .14s ease; }
      .admin-content .table-hover > tbody > tr:hover > * { --bs-table-accent-bg: #f4f8ff; color: var(--admin-ink); }
      .admin-content .table-responsive { border: 1px solid var(--admin-line); border-radius: 12px; }
      .admin-content .table-responsive > .table { margin-bottom: 0; }
      .admin-content .badge { padding: .42em .66em; border-radius: 999px; font-weight: 750; letter-spacing: .01em; }
      .admin-content .alert { border-radius: 12px; border-color: transparent; font-size: .88rem; }
      .admin-content .pagination { --bs-pagination-border-color: #dce6f3; --bs-pagination-color: #456083; --bs-pagination-hover-color: #1d55bf; --bs-pagination-hover-bg: #eff5ff; --bs-pagination-hover-border-color: #c4d9fb; --bs-pagination-active-bg: var(--admin-blue); --bs-pagination-active-border-color: var(--admin-blue); }
      .admin-content .page-link { border-radius: 8px; margin: 0 .12rem; font-size: .82rem; font-weight: 700; }
      .admin-content .list-group-item { border-color: #e8eef6; color: #40526d; }

      .toast { border-radius: 13px; box-shadow: 0 16px 42px rgba(16, 40, 80, .18); }
      .toast-container { z-index: 1100; }
      .admin-footer { flex: 0 0 auto; border-color: rgba(219,228,240,.85) !important; background: rgba(255,255,255,.64); }
      .admin-footer-copy { color: #8795aa; font-size: .75rem; }

      @media (max-width: 1199.98px) {
        .admin-content main.container-fluid { max-width: none; }
      }
      @media (max-width: 767.98px) {
        html { scroll-padding-top: 70px; }
        .page-header { min-height: 62px; }
        .page-header .container-fluid { min-height: 62px; }
        .admin-header-title { font-size: .92rem; }
        .admin-header-subtitle { max-width: 52vw; font-size: .7rem; }
        .admin-menu-toggle { min-width: 38px; min-height: 38px; padding: 0 .62rem; }
        .admin-home-link .admin-header-link-label { display: none; }
        .admin-home-link i { margin-right: 0 !important; }
        .admin-content main.container-fluid { padding: 1rem !important; }
        .admin-content .card-header { padding: .88rem 1rem; }
        .admin-content .card-body { padding: 1rem; }
        .admin-content .table > :not(caption) > * > * { padding: .68rem .72rem; }
      }
      @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
      }
    </style>
  </head>
  <body class="bg-body-tertiary">
    <div class="app-shell d-flex">
      <aside class="sidebar d-none d-xl-flex flex-column p-3">
        <a class="brand d-flex align-items-center gap-2 mb-4" href="<?php echo htmlspecialchars(admin_url('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">
          <span class="admin-brand-mark" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
          <span class="admin-brand-copy">
            <span class="admin-brand-name">MedReview</span>
            <span class="admin-brand-role">Quản trị hệ thống</span>
          </span>
        </a>

        <div class="nav-heading">Điều hướng</div>
        <nav class="nav nav-pills flex-column gap-1 mb-3">
          <a class="nav-link <?php echo ($adminActive === 'dashboard') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-grid-2" aria-hidden="true"></i><span>Tổng quan</span></a>
          <a class="nav-link <?php echo ($adminActive === 'content') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('content.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Quản lý nội dung</span></a>
          <a class="nav-link <?php echo ($adminActive === 'medical-facilities') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_facilities.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-hospital" aria-hidden="true"></i><span>Cơ sở y tế</span></a>
          <a class="nav-link <?php echo ($adminActive === 'medical-toplists') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_toplists.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i><span>Toplist y tế</span></a>
          <a class="nav-link <?php echo ($adminActive === 'medical-doctors') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_doctors.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-user-doctor" aria-hidden="true"></i><span>Bác sĩ</span></a>
          <a class="nav-link <?php echo ($adminActive === 'medical-reviews') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_reviews.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i><span>Review y tế</span></a>
          <a class="nav-link <?php echo ($adminActive === 'library') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('library.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-photo-film" aria-hidden="true"></i><span>Thư viện</span></a>
          <details class="admin-nav-group"<?php echo $adminSettingsActive ? ' open' : ''; ?>>
            <summary class="nav-link <?php echo $adminSettingsActive ? 'active' : ''; ?>">
              <span class="admin-nav-summary-main"><i class="fa-solid fa-gear" aria-hidden="true"></i><span>Cài đặt</span></span>
              <i class="fa-solid fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
            </summary>
            <div class="admin-nav-submenu">
              <a class="nav-link <?php echo ($adminActive === 'settings') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('settings.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-sliders" aria-hidden="true"></i><span>Trang web</span></a>
              <a class="nav-link <?php echo ($adminActive === 'medical-ai-prompts') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_ai_prompts.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>Prompt AI y tế</span></a>
              <a class="nav-link <?php echo ($adminActive === 'medical-media-worker') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_media_worker.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i><span>Worker ảnh y tế</span></a>
              <?php if ($adminCanManageSystem): ?>
                <a class="nav-link <?php echo ($adminActive === 'cron') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('cron.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>Cron &amp; Cache</span></a>
                <a class="nav-link <?php echo ($adminActive === 'users') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('users.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-users-gear" aria-hidden="true"></i><span>Quản lý người dùng</span></a>
              <?php endif; ?>
            </div>
          </details>
        </nav>

        <div class="mt-auto pt-3 border-top border-light border-opacity-10">
          <div class="small muted mb-2">Phiên quản trị</div>
          <div class="admin-account d-flex align-items-center justify-content-between gap-2">
            <div class="small">
              <div class="fw-semibold text-white"><i class="fa-solid fa-user-shield me-2" aria-hidden="true"></i>#<?php echo isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : 0; ?></div>
              <div class="muted">role: <?php echo htmlspecialchars((string) ($_SESSION['admin_role'] ?? 'guest'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php if (function_exists('admin_is_logged_in') && admin_is_logged_in()): ?>
              <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(admin_url('logout.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i>Đăng xuất</a>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(admin_url('login.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>Đăng nhập</a>
            <?php endif; ?>
          </div>
        </div>
      </aside>

      <div class="content admin-content flex-grow-1">
        <nav class="navbar page-header border-bottom sticky-top">
          <div class="container-fluid py-2 py-md-0">
            <div class="d-flex align-items-center min-w-0">
              <button class="btn btn-outline-secondary admin-menu-toggle d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Mở menu quản trị">
                <i class="fa-solid fa-bars" aria-hidden="true"></i><span class="d-none d-sm-inline ms-2">Menu</span>
              </button>
              <div class="ms-2 ms-xl-0 min-w-0">
                <div class="admin-header-title text-truncate"><?php echo htmlspecialchars($adminHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($adminHeaderSubtitle !== ''): ?>
                  <div class="admin-header-subtitle"><?php echo htmlspecialchars($adminHeaderSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
              <a class="btn btn-sm btn-outline-secondary admin-home-link" href="<?php echo htmlspecialchars(site_url('/'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-up-right-from-square me-2" aria-hidden="true"></i><span class="admin-header-link-label">Xem website</span></a>
              <?php if (function_exists('admin_is_logged_in') && admin_is_logged_in()): ?>
                <a class="btn btn-sm btn-outline-secondary d-xl-none" href="<?php echo htmlspecialchars(admin_url('logout.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Đăng xuất"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span class="d-none d-sm-inline ms-2">Đăng xuất</span></a>
              <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary d-xl-none" href="<?php echo htmlspecialchars(admin_url('login.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Đăng nhập"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span class="d-none d-sm-inline ms-2">Đăng nhập</span></a>
              <?php endif; ?>
            </div>
          </div>
        </nav>

        <div class="offcanvas offcanvas-start d-xl-none admin-offcanvas" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
          <div class="offcanvas-header">
            <div class="offcanvas-title d-flex align-items-center gap-2" id="sidebarOffcanvasLabel">
              <span class="admin-brand-mark" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
              <span>MedReview Admin</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng menu"></button>
          </div>
          <div class="offcanvas-body">
            <div class="small text-uppercase fw-bold text-secondary px-2 mb-2" style="font-size:.66rem;letter-spacing:.1em">Điều hướng</div>
            <div class="nav nav-pills flex-column gap-1">
              <a class="nav-link <?php echo ($adminActive === 'dashboard') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-grid-2 me-2" aria-hidden="true"></i>Tổng quan</a>
              <a class="nav-link <?php echo ($adminActive === 'content') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('content.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>Quản lý nội dung</a>
              <a class="nav-link <?php echo ($adminActive === 'medical-facilities') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_facilities.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-hospital me-2" aria-hidden="true"></i>Cơ sở y tế</a>
              <a class="nav-link <?php echo ($adminActive === 'medical-toplists') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_toplists.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-ranking-star me-2" aria-hidden="true"></i>Toplist y tế</a>
              <a class="nav-link <?php echo ($adminActive === 'medical-doctors') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_doctors.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-user-doctor me-2" aria-hidden="true"></i>Bác sĩ</a>
              <a class="nav-link <?php echo ($adminActive === 'medical-reviews') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_reviews.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-star-half-stroke me-2" aria-hidden="true"></i>Review y tế</a>
              <a class="nav-link <?php echo ($adminActive === 'library') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('library.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-photo-film me-2" aria-hidden="true"></i>Thư viện</a>
              <details class="admin-nav-group"<?php echo $adminSettingsActive ? ' open' : ''; ?>>
                <summary class="nav-link <?php echo $adminSettingsActive ? 'active' : ''; ?>">
                  <span class="admin-nav-summary-main"><i class="fa-solid fa-gear" aria-hidden="true"></i><span>Cài đặt</span></span>
                  <i class="fa-solid fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
                </summary>
                <div class="admin-nav-submenu">
                  <a class="nav-link <?php echo ($adminActive === 'settings') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('settings.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-sliders" aria-hidden="true"></i>Trang web</a>
                  <a class="nav-link <?php echo ($adminActive === 'medical-ai-prompts') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_ai_prompts.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>Prompt AI y tế</a>
                  <a class="nav-link <?php echo ($adminActive === 'medical-media-worker') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('medical_media_worker.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>Worker ảnh y tế</a>
                  <?php if ($adminCanManageSystem): ?>
                    <a class="nav-link <?php echo ($adminActive === 'cron') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('cron.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>Cron &amp; Cache</a>
                    <a class="nav-link <?php echo ($adminActive === 'users') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('users.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-users-gear" aria-hidden="true"></i>Quản lý người dùng</a>
                  <?php endif; ?>
                </div>
              </details>
              <div class="border-top my-2"></div>
              <?php if (function_exists('admin_is_logged_in') && admin_is_logged_in()): ?>
                <a class="nav-link" href="<?php echo htmlspecialchars(admin_url('logout.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i>Đăng xuất</a>
              <?php else: ?>
                <a class="nav-link" href="<?php echo htmlspecialchars(admin_url('login.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>Đăng nhập</a>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div id="adminToastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>

        <div class="modal fade" id="adminMediaLibraryModal" tabindex="-1" aria-labelledby="adminMediaLibraryLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div class="modal-title fw-semibold" id="adminMediaLibraryLabel">Thư viện</div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                  <div class="btn-group" role="group" aria-label="Filter">
                    <input type="radio" class="btn-check" name="adminMediaFilter" id="adminMediaFilterImages" autocomplete="off" checked>
                    <label class="btn btn-outline-secondary" for="adminMediaFilterImages">Ảnh</label>
                    <input type="radio" class="btn-check" name="adminMediaFilter" id="adminMediaFilterAll" autocomplete="off">
                    <label class="btn btn-outline-secondary" for="adminMediaFilterAll">Tất cả</label>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <label class="btn btn-outline-primary mb-0">
                      <i class="fa-solid fa-cloud-arrow-up me-2" aria-hidden="true"></i>Upload ảnh
                      <input id="adminMediaUploadInput" type="file" accept="image/*" hidden>
                    </label>
                    <button id="adminMediaReload" type="button" class="btn btn-outline-secondary">
                      <i class="fa-solid fa-rotate me-2" aria-hidden="true"></i>Reload
                    </button>
                  </div>
                </div>
                <div id="adminMediaUploadProgressWrap" class="d-none mb-3">
                  <div class="d-flex align-items-center justify-content-between gap-2 small mb-1">
                    <span id="adminMediaUploadProgressLabel" class="text-secondary">Đang tải lên...</span>
                    <span id="adminMediaUploadProgressText" class="fw-semibold">0%</span>
                  </div>
                  <div class="progress" role="progressbar" aria-label="Tiến trình upload thư viện" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div id="adminMediaUploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%;"></div>
                  </div>
                </div>
                <div id="adminMediaGrid" class="row g-3"></div>
                <div id="adminMediaEmpty" class="text-secondary d-none">Chưa có file trong thư viện.</div>
              </div>
              <div class="modal-footer">
                <div class="text-secondary small me-auto">
                  <span id="adminMediaHintSingle">Click vào ảnh để chọn.</span>
                  <span id="adminMediaHintMulti" class="d-none">Chọn nhiều ảnh rồi bấm “Chọn”.</span>
                </div>
                <button id="adminMediaPickConfirm" type="button" class="btn btn-primary d-none">
                  <i class="fa-solid fa-check me-2" aria-hidden="true"></i>Chọn
                  <span id="adminMediaPickCount" class="badge text-bg-light text-dark ms-2">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
              </div>
            </div>
          </div>
        </div>

        <main class="container-fluid p-3 p-lg-4">
