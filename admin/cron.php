<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../medical_directory.php';
require_once __DIR__ . '/../medical_search_cache.php';

admin_require_admin();

$adminPageTitle = 'Admin • Cron & Cache';
$adminHeaderTitle = 'Cron & Cache';
$adminHeaderSubtitle = 'Thiết lập lịch làm mới dữ liệu tìm kiếm và Worker ảnh nền';
$adminActive = 'cron';

$projectRoot = dirname(__DIR__);
$searchCronPath = realpath($projectRoot . '/cron/medical-search-cache.php') ?: $projectRoot . '/cron/medical-search-cache.php';
$mediaCronPath = realpath($projectRoot . '/cron/medical-media-worker.php') ?: $projectRoot . '/cron/medical-media-worker.php';
$phpBinary = '/usr/bin/php';
$shellSearchPath = escapeshellarg($searchCronPath);
$shellMediaPath = escapeshellarg($mediaCronPath);

$cache = medical_search_cache_read();
$cacheExists = is_array($cache);
$cacheFresh = $cacheExists && medical_search_cache_is_fresh($cache);
$cacheFile = medical_search_cache_path();
$cacheSize = is_file($cacheFile) ? (int) (@filesize($cacheFile) ?: 0) : 0;
$cacheGeneratedAt = $cacheExists ? (int) ($cache['generated_at'] ?? 0) : 0;
$cacheExpiresAt = $cacheExists ? (int) ($cache['expires_at'] ?? 0) : 0;
$cacheCounts = $cacheExists && is_array($cache['counts'] ?? null) ? $cache['counts'] : [];
$mediaQueue = ['pending' => 0, 'processing' => 0, 'downloaded' => 0, 'failed' => 0];

try {
    $pdo = db();
    if (medical_directory_table_exists($pdo, 'medical_media_jobs')) {
        $rows = $pdo->query("SELECT status, COUNT(*) AS total FROM medical_media_jobs GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $mediaQueue)) {
                $mediaQueue[$status] = (int) ($row['total'] ?? 0);
            }
        }
    }
} catch (Throwable) {
    // The page still provides cron commands when the database is temporarily unavailable.
}

$searchCron = '*/10 * * * * ' . $phpBinary . ' ' . $shellSearchPath . ' >/dev/null 2>&1';
$mediaDownloadCron = '* * * * * ' . $phpBinary . ' ' . $shellMediaPath . ' --type=all --mode=download --limit=10 --parallel=3 >/dev/null 2>&1';
$mediaCompressCron = '*/5 * * * * ' . $phpBinary . ' ' . $shellMediaPath . ' --type=all --mode=compress --limit=3 --no-scan >/dev/null 2>&1';

require __DIR__ . '/_layout_start.php';
?>
<style>
  .cron-page { --cron-blue:#2466e8; --cron-ink:#17253f; --cron-muted:#6f8099; --cron-line:#dfe8f5; }
  .cron-hero { position:relative; overflow:hidden; padding:1.45rem; border:1px solid #d6e5ff; border-radius:1.35rem; background:linear-gradient(118deg,#f9fcff 0%,#fff 52%,#edf5ff 100%); box-shadow:0 14px 36px rgba(32,83,170,.07); }
  .cron-hero::after { position:absolute; right:-6rem; bottom:-9rem; width:21rem; height:21rem; border-radius:50%; content:""; background:radial-gradient(circle,rgba(42,111,235,.15),rgba(42,111,235,0) 67%); pointer-events:none; }
  .cron-eyebrow { display:inline-flex; align-items:center; gap:.42rem; padding:.36rem .62rem; border:1px solid #c9f0dd; border-radius:999px; color:#14714d; background:#edfcf4; font-size:.73rem; font-weight:800; letter-spacing:.025em; text-transform:uppercase; }
  .cron-hero h1 { position:relative; margin:.66rem 0 .35rem; color:var(--cron-ink); font-size:clamp(1.35rem,2.5vw,1.85rem); font-weight:850; letter-spacing:-.04em; }
  .cron-hero p { position:relative; max-width:720px; margin:0; color:var(--cron-muted); font-size:.91rem; line-height:1.6; }
  .cron-status { position:relative; z-index:1; display:inline-flex; align-items:center; gap:.45rem; padding:.47rem .7rem; border:1px solid #dce8fa; border-radius:999px; color:#52647f; background:rgba(255,255,255,.86); font-size:.79rem; font-weight:750; white-space:nowrap; }
  .cron-status.is-good { border-color:#bfeacc; color:#14714d; background:#f1fcf5; }
  .cron-status.is-stale { border-color:#ffdc9e; color:#936000; background:#fff9ea; }
  .cron-stat { min-width:0; height:100%; padding:1rem; border:1px solid var(--cron-line); border-radius:1rem; background:#fff; box-shadow:0 8px 23px rgba(24,62,116,.045); }
  .cron-stat-icon { display:grid; width:2.2rem; height:2.2rem; place-items:center; margin-bottom:.72rem; border-radius:.72rem; color:var(--cron-blue); background:#edf4ff; }
  .cron-stat-value { color:#172a49; font-size:1.42rem; font-weight:850; letter-spacing:-.045em; line-height:1; }
  .cron-stat-label { margin-top:.35rem; color:#73829a; font-size:.76rem; font-weight:700; line-height:1.35; }
  .cron-card { height:100%; border:1px solid var(--cron-line); border-radius:1.15rem; background:#fff; box-shadow:0 10px 27px rgba(24,58,105,.055); }
  .cron-card .card-body { padding:1.2rem; }
  .cron-title { display:flex; align-items:center; gap:.58rem; margin:0; color:#1e2d46; font-size:1rem; font-weight:850; }
  .cron-title i { display:grid; width:2rem; height:2rem; place-items:center; border-radius:.67rem; color:var(--cron-blue); background:#edf4ff; font-size:.84rem; }
  .cron-caption { margin:.36rem 0 0; color:var(--cron-muted); font-size:.82rem; line-height:1.55; }
  .cron-command { overflow:hidden; border:1px solid #dce8f8; border-radius:.9rem; background:#f8fbff; }
  .cron-command code { display:block; overflow:auto; padding:.82rem .92rem; color:#30415f; font-size:.74rem; line-height:1.55; white-space:pre-wrap; word-break:break-word; }
  .cron-command button { width:100%; min-height:36px; border:0; border-top:1px solid #dce8f8; color:#2a63ca; background:#fff; font-size:.77rem; font-weight:750; }
  .cron-command button:hover { background:#eef5ff; }
  .cron-list { display:grid; gap:.65rem; margin:1rem 0 0; padding:0; list-style:none; }
  .cron-list li { display:flex; align-items:flex-start; gap:.65rem; color:#5e708a; font-size:.81rem; line-height:1.5; }
  .cron-list i { margin-top:.13rem; color:#2d72e6; }
  .cron-meta { display:grid; grid-template-columns:150px minmax(0,1fr); gap:.5rem 1rem; margin:1rem 0 0; font-size:.82rem; }
  .cron-meta dt { color:#7a899e; font-weight:750; }
  .cron-meta dd { min-width:0; margin:0; overflow-wrap:anywhere; color:#334662; }
  .cron-note { display:flex; align-items:flex-start; gap:.65rem; margin-top:1rem; padding:.82rem .9rem; border:1px solid #dce8fa; border-radius:.9rem; color:#61738e; background:#f7faff; font-size:.8rem; line-height:1.55; }
  .cron-note i { margin-top:.12rem; color:#3478ed; }
  @media (max-width:575.98px) { .cron-hero,.cron-card .card-body { padding:1rem; }.cron-meta { grid-template-columns:1fr; gap:.15rem; }.cron-stat { padding:.85rem; } }
</style>

<section class="cron-page">
  <div class="cron-hero mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div>
        <div class="cron-eyebrow"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Tác vụ nền</div>
        <h1>Cron cho dữ liệu luôn sẵn sàng</h1>
        <p>Cron chỉ chạy các tác vụ nền theo lịch: làm mới index tìm kiếm JSON và tải/nén ảnh. Website vẫn hoạt động khi chưa cài Cron, nhưng request đầu tiên sau khi cache cũ có thể chậm hơn.</p>
      </div>
      <span class="cron-status <?php echo $cacheFresh ? 'is-good' : 'is-stale'; ?>">
        <i class="fa-solid <?php echo $cacheFresh ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" aria-hidden="true"></i>
        <?php echo $cacheFresh ? 'JSON search đang mới' : ($cacheExists ? 'JSON search cần làm mới' : 'Chưa có JSON search'); ?>
      </span>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><article class="cron-stat"><div class="cron-stat-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></div><div class="cron-stat-value"><?php echo number_format((int) ($cacheCounts['facilities'] ?? 0), 0, ',', '.'); ?></div><div class="cron-stat-label">Cơ sở trong index</div></article></div>
    <div class="col-6 col-xl-3"><article class="cron-stat"><div class="cron-stat-icon"><i class="fa-solid fa-user-doctor" aria-hidden="true"></i></div><div class="cron-stat-value"><?php echo number_format((int) ($cacheCounts['doctors'] ?? 0), 0, ',', '.'); ?></div><div class="cron-stat-label">Bác sĩ trong index</div></article></div>
    <div class="col-6 col-xl-3"><article class="cron-stat"><div class="cron-stat-icon"><i class="fa-solid fa-database" aria-hidden="true"></i></div><div class="cron-stat-value"><?php echo $cacheSize > 0 ? number_format($cacheSize / 1048576, 1, ',', '.') . ' MB' : '—'; ?></div><div class="cron-stat-label">Dung lượng file JSON</div></article></div>
    <div class="col-6 col-xl-3"><article class="cron-stat"><div class="cron-stat-icon"><i class="fa-solid fa-images" aria-hidden="true"></i></div><div class="cron-stat-value"><?php echo number_format($mediaQueue['pending'] + $mediaQueue['downloaded'] + $mediaQueue['processing'], 0, ',', '.'); ?></div><div class="cron-stat-label">Ảnh đang chờ Worker</div></article></div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-7">
      <article class="cron-card">
        <div class="card-body">
          <h2 class="cron-title"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Làm mới JSON tìm kiếm</h2>
          <p class="cron-caption">Chạy mỗi 10 phút để index được chuẩn bị trước. Mã nguồn cũng tự làm mới index khi có cập nhật dữ liệu hoặc khi file hết TTL.</p>
          <div class="cron-command mt-3">
            <code id="searchCronCommand"><?php echo htmlspecialchars($searchCron, ENT_QUOTES, 'UTF-8'); ?></code>
            <button type="button" data-copy-target="searchCronCommand"><i class="fa-regular fa-copy me-1" aria-hidden="true"></i>Sao chép lệnh</button>
          </div>
          <ul class="cron-list">
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Không chạy query tìm kiếm cho từng lần người dùng gõ; Cron chỉ tạo lại một file snapshot dùng chung.</span></li>
            <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Nếu DB tạm lỗi, hệ thống vẫn trả JSON cache cũ thay vì lỗi tìm kiếm.</span></li>
          </ul>
        </div>
      </article>
    </div>
    <div class="col-12 col-xl-5">
      <article class="cron-card">
        <div class="card-body">
          <h2 class="cron-title"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Trạng thái search cache</h2>
          <dl class="cron-meta">
            <dt>TTL cấu hình</dt><dd><?php echo (int) (medical_search_cache_ttl() / 60); ?> phút</dd>
            <dt>Tạo gần nhất</dt><dd><?php echo $cacheGeneratedAt > 0 ? htmlspecialchars(date('d/m/Y H:i:s', $cacheGeneratedAt), ENT_QUOTES, 'UTF-8') : 'Chưa có'; ?></dd>
            <dt>Hết hạn</dt><dd><?php echo $cacheExpiresAt > 0 ? htmlspecialchars(date('d/m/Y H:i:s', $cacheExpiresAt), ENT_QUOTES, 'UTF-8') : 'Chưa có'; ?></dd>
            <dt>File runtime</dt><dd class="mono"><?php echo htmlspecialchars($cacheFile, ENT_QUOTES, 'UTF-8'); ?></dd>
          </dl>
        </div>
      </article>
    </div>

    <div class="col-12">
      <article class="cron-card">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
              <h2 class="cron-title"><i class="fa-solid fa-images" aria-hidden="true"></i> Worker ảnh y tế</h2>
              <p class="cron-caption">Tải ảnh về thường xuyên, còn nén ảnh chạy thưa hơn để không chiếm CPU khi website có khách.</p>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(admin_url('medical_media_worker.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>Mở Worker</a>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-12 col-lg-6"><div class="cron-command"><code id="mediaDownloadCronCommand"><?php echo htmlspecialchars($mediaDownloadCron, ENT_QUOTES, 'UTF-8'); ?></code><button type="button" data-copy-target="mediaDownloadCronCommand"><i class="fa-regular fa-copy me-1" aria-hidden="true"></i>Sao chép lịch tải ảnh</button></div></div>
            <div class="col-12 col-lg-6"><div class="cron-command"><code id="mediaCompressCronCommand"><?php echo htmlspecialchars($mediaCompressCron, ENT_QUOTES, 'UTF-8'); ?></code><button type="button" data-copy-target="mediaCompressCronCommand"><i class="fa-regular fa-copy me-1" aria-hidden="true"></i>Sao chép lịch nén ảnh</button></div></div>
          </div>
          <div class="cron-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Dán từng dòng vào mục <strong>Cron Jobs</strong> của Hostinger. Đường dẫn trên được lấy từ server hiện tại; nếu Hostinger dùng PHP CLI ở vị trí khác, thay phần <code>/usr/bin/php</code> theo hướng dẫn của hPanel.</span></div>
        </div>
      </article>
    </div>
  </div>
</section>

<script>
(() => {
  function fallbackCopy(text) {
    const area = document.createElement('textarea');
    area.value = text; area.setAttribute('readonly', '');
    area.style.position = 'fixed'; area.style.opacity = '0';
    document.body.appendChild(area); area.select();
    const copied = document.execCommand('copy'); area.remove();
    return copied;
  }
  document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async () => {
      const target = document.getElementById(button.dataset.copyTarget || '');
      const text = target ? target.textContent.trim() : '';
      if (!text) return;
      try {
        if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(text);
        else if (!fallbackCopy(text)) throw new Error('copy failed');
        window.adminToast('success', 'Đã sao chép lệnh Cron.', {icon:'fa-solid fa-copy'});
      } catch (error) {
        window.adminToast('danger', 'Không thể sao chép tự động. Hãy chọn và copy thủ công.', {icon:'fa-solid fa-triangle-exclamation'});
      }
    });
  });
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
