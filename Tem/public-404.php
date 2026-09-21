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
    .public-404{min-height:44vh;display:grid;place-items:center;padding:36px 16px}
    .public-404__card{width:min(100%,620px);padding:clamp(22px,5vw,42px);border:1px solid #dce7f6;border-radius:24px;background:linear-gradient(145deg,#fff,#f5f9ff);box-shadow:0 18px 50px rgba(15,35,66,.08);text-align:center}
    .public-404__code{display:inline-flex;padding:6px 11px;border-radius:999px;background:#eaf2ff;color:#2563eb;font-size:12px;font-weight:800;letter-spacing:.08em}
    .public-404 h1{margin:14px 0 8px;color:#10213d;font-size:clamp(24px,5vw,34px);line-height:1.2}
    .public-404 p{margin:0 auto;max-width:48ch;color:#64748b;font-size:15px;line-height:1.7}
    .public-404__links{display:flex;justify-content:center;flex-wrap:wrap;gap:10px;margin-top:22px}
    .public-404__links a{min-height:42px;display:inline-flex;align-items:center;justify-content:center;padding:0 15px;border:1px solid #cfe0fc;border-radius:12px;background:#fff;color:#2563eb;font-weight:750;text-decoration:none}
    .public-404__links a:first-child{border-color:#2563eb;background:#2563eb;color:#fff}
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>
  <main class="public-404 site-typo">
    <section class="public-404__card" aria-labelledby="public-404-title">
      <span class="public-404__code">404</span>
      <h1 id="public-404-title"><?php echo $escapeNotFound($notFoundTitle); ?></h1>
      <p><?php echo $escapeNotFound($notFoundDescription); ?></p>
      <nav class="public-404__links" aria-label="Liên kết gợi ý">
        <a href="/">Về trang chủ</a>
        <a href="/co-so-y-te">Tìm cơ sở y tế</a>
      </nav>
    </section>
  </main>
  <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
