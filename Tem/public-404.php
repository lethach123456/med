<?php
declare(strict_types=1);

$notFoundTitle = trim((string) ($notFoundTitle ?? 'Không tìm thấy trang'));
$notFoundDescription = trim((string) ($notFoundDescription ?? 'Trang bạn tìm hiện không tồn tại hoặc đã được chuyển đi.'));
$escapeNotFound = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="vi">
<head>
  <?php echo site_favicon_tags(); ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,follow">
  <title><?php echo $escapeNotFound($notFoundTitle); ?> | MedReview</title>
  <meta name="description" content="<?php echo $escapeNotFound($notFoundDescription); ?>">
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <style>
    .public-404{
      --error-ink:#10284b;
      --error-muted:#61748f;
      --error-blue:#2563ff;
      --error-line:#dce8fa;
      position:relative;
      isolation:isolate;
      display:grid;
      place-items:center;
      min-height:clamp(490px,64vh,720px);
      overflow:hidden;
      padding:clamp(28px,6vw,76px) 20px;
      background:
        radial-gradient(ellipse at 9% 10%,rgba(76,145,255,.11),transparent 32%),
        radial-gradient(ellipse at 92% 88%,rgba(47,109,255,.09),transparent 34%),
        linear-gradient(180deg,#f9fbff 0%,#f2f7ff 100%);
      color:var(--error-ink);
    }
    .public-404::before,.public-404::after{
      position:absolute;
      z-index:-1;
      width:280px;
      aspect-ratio:1;
      border:1px solid rgba(98,151,231,.12);
      border-radius:50%;
      content:"";
      pointer-events:none;
    }
    .public-404::before{top:-195px;left:-125px;box-shadow:0 0 0 32px rgba(255,255,255,.23),0 0 0 64px rgba(255,255,255,.16)}
    .public-404::after{right:-175px;bottom:-200px;width:350px;box-shadow:0 0 0 36px rgba(255,255,255,.23),0 0 0 72px rgba(255,255,255,.16)}
    .public-404__layout{
      display:grid;
      grid-template-columns:minmax(0,1fr) minmax(220px,310px);
      align-items:center;
      gap:clamp(26px,6vw,80px);
      width:min(100%,1040px);
      padding:clamp(26px,5vw,58px);
      border:1px solid rgba(205,221,245,.92);
      border-radius:30px;
      background:rgba(255,255,255,.88);
      box-shadow:0 30px 80px rgba(23,62,117,.09),inset 0 1px 0 #fff;
      backdrop-filter:blur(16px);
    }
    .public-404__copy{min-width:0}
    .public-404__eyebrow{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border:1px solid #d5e5ff;
      border-radius:999px;
      background:#f3f8ff;
      color:#2862c7;
      font-size:12px;
      font-weight:800;
      letter-spacing:.055em;
      text-transform:uppercase;
    }
    .public-404__eyebrow svg{width:15px;height:15px;flex:none}
    .public-404__number{
      margin:20px 0 2px;
      color:var(--error-blue);
      font-size:clamp(62px,10vw,108px);
      font-weight:850;
      letter-spacing:-.09em;
      line-height:.95;
    }
    .public-404 h1{
      margin:15px 0 10px;
      color:var(--error-ink);
      font-size:clamp(25px,3.2vw,36px);
      font-weight:780;
      letter-spacing:-.045em;
      line-height:1.2;
    }
    .public-404__description{
      max-width:54ch;
      margin:0;
      color:var(--error-muted);
      font-size:16px;
      line-height:1.75;
    }
    .public-404__actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:26px}
    .public-404__actions a{
      display:inline-flex;
      min-height:48px;
      align-items:center;
      justify-content:center;
      gap:9px;
      padding:0 18px;
      border:1px solid var(--error-line);
      border-radius:14px;
      background:#fff;
      color:#315278;
      font-size:14px;
      font-weight:750;
      text-decoration:none;
      transition:transform .18s ease,border-color .18s ease,background .18s ease,box-shadow .18s ease;
    }
    .public-404__actions a:hover{transform:translateY(-2px);border-color:#a9c8fb;box-shadow:0 8px 20px rgba(38,99,255,.1)}
    .public-404__actions a:focus-visible{outline:3px solid rgba(37,99,255,.3);outline-offset:3px}
    .public-404__actions a:first-child{border-color:#2563eb;background:linear-gradient(135deg,#397dff,#205be9);color:#fff;box-shadow:0 12px 24px rgba(37,99,255,.2)}
    .public-404__actions svg{width:17px;height:17px;flex:none}
    .public-404__visual{
      display:grid;
      place-items:center;
      min-height:270px;
      border:1px solid #e1ecfb;
      border-radius:28px;
      background:linear-gradient(145deg,#f8fbff,#edf5ff);
    }
    .public-404__visual svg{display:block;width:min(76%,240px);height:auto;overflow:visible}
    @media(max-width:700px){
      .public-404{min-height:0;padding:28px 14px 38px}
      .public-404__layout{grid-template-columns:1fr;gap:22px;padding:24px 20px;border-radius:24px}
      .public-404__number{margin-top:17px;font-size:76px}
      .public-404 h1{margin-top:11px;font-size:26px}
      .public-404__description{font-size:15px;line-height:1.7}
      .public-404__actions{display:grid;grid-template-columns:1fr;margin-top:22px}
      .public-404__actions a{width:100%;min-height:48px}
      .public-404__visual{min-height:190px;border-radius:20px}
      .public-404__visual svg{width:154px}
    }
    @media(max-width:360px){.public-404__layout{padding:21px 16px}.public-404__number{font-size:68px}}
    @media(prefers-reduced-motion:reduce){.public-404__actions a{transition:none}.public-404__actions a:hover{transform:none}}
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>
  <main class="public-404 site-typo">
    <section class="public-404__layout" aria-labelledby="public-404-title">
      <div class="public-404__copy">
        <span class="public-404__eyebrow">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.25 14.4 5l3-.15.9 2.85 2.45 1.75-.9 2.85.9 2.85-2.45 1.75-.9 2.85-3-.15L12 21.5l-2.4-1.9-3 .15-.9-2.85-2.45-1.75.9-2.85-.9-2.85L5.7 7.9l.9-2.85 3 .15L12 3.25Z" fill="currentColor"/><path d="m9 12.1 2 2 4.25-4.3" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          MedReview · Trang không khả dụng
        </span>
        <p class="public-404__number" aria-hidden="true">404</p>
        <h1 id="public-404-title"><?php echo $escapeNotFound($notFoundTitle); ?></h1>
        <p class="public-404__description"><?php echo $escapeNotFound($notFoundDescription); ?></p>
        <nav class="public-404__actions" aria-label="Liên kết gợi ý">
          <a href="/">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3.5 10.6 8.5-7 8.5 7v9.1a1.3 1.3 0 0 1-1.3 1.3h-14a1.3 1.3 0 0 1-1.3-1.3v-9.1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 21v-7h6v7" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            Về trang chủ
          </a>
          <a href="/co-so-y-te">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.5 10.1c0 5.15-8.5 11-8.5 11s-8.5-5.85-8.5-11a8.5 8.5 0 1 1 17 0Z" stroke="currentColor" stroke-width="1.8"/><path d="M12 6.7v6.8m-3.4-3.4h6.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            Tìm cơ sở y tế
          </a>
        </nav>
      </div>
      <div class="public-404__visual" aria-hidden="true">
        <svg viewBox="0 0 240 240" fill="none">
          <defs>
            <linearGradient id="pin-gradient" x1="55" y1="30" x2="187" y2="193" gradientUnits="userSpaceOnUse"><stop stop-color="#52A5FF"/><stop offset="1" stop-color="#1557E8"/></linearGradient>
            <linearGradient id="plus-gradient" x1="157" y1="26" x2="195" y2="65" gradientUnits="userSpaceOnUse"><stop stop-color="#58A9FF"/><stop offset="1" stop-color="#2764F4"/></linearGradient>
            <filter id="pin-shadow" x="28" y="9" width="190" height="220" color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse"><feGaussianBlur stdDeviation="12"/></filter>
          </defs>
          <ellipse cx="120" cy="206" rx="66" ry="15" fill="#B9D8FF" opacity=".45"/>
          <ellipse cx="120" cy="206" rx="44" ry="8" fill="#8DBEFF" opacity=".48"/>
          <path d="M120 28c-39.2 0-71 31.8-71 71 0 53.25 71 115 71 115s71-61.75 71-115c0-39.2-31.8-71-71-71Z" fill="#2563FF" opacity=".22" filter="url(#pin-shadow)" transform="translate(0 2)"/>
          <path d="M120 23c-38.1 0-69 30.9-69 69 0 51.75 69 112 69 112s69-60.25 69-112c0-38.1-30.9-69-69-69Z" fill="url(#pin-gradient)"/>
          <path d="M120 76.5c-6.75-10.05-22.2-11.25-30.45-2.25-8.1 8.85-6.15 21.6 1.05 30.15 6.3 7.5 24.45 23.1 27.6 25.8a2.75 2.75 0 0 0 3.6 0c3.15-2.7 21.3-18.3 27.6-25.8 7.2-8.55 9.15-21.3 1.05-30.15-8.25-9-23.7-7.8-30.45 2.25Z" fill="#fff"/>
          <circle cx="177" cy="49" r="25" fill="#fff"/>
          <circle cx="177" cy="49" r="19" fill="url(#plus-gradient)"/>
          <path d="M174 38.5h6v7.5h7.5v6H180v7.5h-6V52h-7.5v-6h7.5v-7.5Z" fill="#fff"/>
        </svg>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
