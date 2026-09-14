<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$adminPageTitle = 'Admin • Worker ảnh y tế';
$adminHeaderTitle = 'Worker ảnh y tế';
$adminHeaderSubtitle = 'Tải nền, nén ảnh và cập nhật thư viện cho cơ sở y tế và bác sĩ';
$adminActive = 'medical-media-worker';
$workerCronPath = realpath(__DIR__ . '/../cron/medical-media-worker.php') ?: dirname(__DIR__) . '/cron/medical-media-worker.php';
$workerPhpBinary = 'php';

require __DIR__ . '/_layout_start.php';
?>

<style>
  .media-worker { --worker-blue:#2365e8; --worker-ink:#18243a; --worker-line:#dfe8f7; --worker-soft:#f4f8ff; }
  .worker-hero { position:relative; overflow:hidden; padding:1.55rem; border:1px solid #d7e5ff; border-radius:1.35rem; background:linear-gradient(118deg,#f7fbff 0%,#fff 52%,#eef5ff 100%); box-shadow:0 14px 36px rgba(36,76,140,.09); }
  .worker-hero::after { position:absolute; top:-8rem; right:-5rem; width:20rem; height:20rem; border-radius:50%; background:radial-gradient(circle,rgba(45,110,242,.15),rgba(45,110,242,0) 68%); content:""; pointer-events:none; }
  .worker-eyebrow { display:inline-flex; align-items:center; gap:.45rem; padding:.37rem .62rem; border-radius:999px; color:#176a48; background:#eaf8f0; font-size:.74rem; font-weight:800; letter-spacing:.02em; text-transform:uppercase; }
  .worker-hero h1 { max-width:680px; margin:.65rem 0 .4rem; color:var(--worker-ink); font-size:clamp(1.25rem,2.5vw,1.75rem); font-weight:800; letter-spacing:-.035em; }
  .worker-hero p { max-width:700px; margin:0; color:#65758e; font-size:.91rem; line-height:1.6; }
  .worker-status { position:relative; z-index:1; display:inline-flex; align-items:center; gap:.45rem; padding:.46rem .68rem; border:1px solid #dce8fb; border-radius:999px; color:#53647f; background:rgba(255,255,255,.82); font-size:.79rem; font-weight:700; white-space:nowrap; }
  .worker-status.is-running { color:#1452c1; border-color:#bcd3ff; background:#eff5ff; }
  .worker-status.is-error { color:#a53a43; border-color:#ffd1d6; background:#fff4f5; }
  .worker-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.8rem; }
  .worker-stat { min-width:0; padding:1rem; border:1px solid var(--worker-line); border-radius:1rem; background:#fff; box-shadow:0 8px 24px rgba(37,71,124,.055); }
  .worker-stat .icon { display:grid; place-items:center; width:2.2rem; height:2.2rem; margin-bottom:.7rem; border-radius:.75rem; color:var(--worker-blue); background:#edf4ff; }
  .worker-stat .number { color:#192743; font-size:1.5rem; font-weight:800; line-height:1; letter-spacing:-.04em; }
  .worker-stat .label { margin-top:.34rem; color:#71819a; font-size:.77rem; font-weight:700; }
  .worker-panel { border:1px solid var(--worker-line); border-radius:1.2rem; background:#fff; box-shadow:0 12px 32px rgba(29,61,112,.065); }
  .worker-panel .card-body { padding:1.25rem; }
  .worker-title { display:flex; align-items:center; gap:.6rem; margin:0; color:#1c2940; font-size:1.03rem; font-weight:800; }
  .worker-title i { display:grid; place-items:center; width:2rem; height:2rem; border-radius:.68rem; color:var(--worker-blue); background:#edf4ff; font-size:.86rem; }
  .worker-caption { margin:.35rem 0 0; color:#71809a; font-size:.82rem; line-height:1.55; }
  .worker-panel label { color:#56657d; font-size:.78rem; font-weight:750; }
  .worker-panel .form-select { min-height:42px; border-color:#d8e3f4; border-radius:.75rem; color:#263852; font-size:.88rem; box-shadow:none; }
  .worker-panel .form-select:focus { border-color:#8eb7ff; box-shadow:0 0 0 .2rem rgba(41,105,233,.1); }
  .worker-run-actions { display:flex; flex-wrap:wrap; align-items:center; gap:.55rem; }
  .worker-run-actions .btn { min-height:42px; padding-inline:1rem; font-size:.86rem; font-weight:750; }
  .worker-run-actions .btn-primary { border-color:#2260df; background:linear-gradient(135deg,#367bf6,#225bdd); box-shadow:0 8px 17px rgba(36,100,230,.18); }
  .worker-run-actions .btn-primary:hover { border-color:#1550cc; background:linear-gradient(135deg,#2d70ed,#1a52cf); }
  .worker-queue-note { display:flex; gap:.55rem; margin-top:1rem; padding:.72rem .8rem; border-radius:.8rem; color:#5a6b85; background:var(--worker-soft); font-size:.78rem; line-height:1.5; }
  .worker-queue-note i { margin-top:.1rem; color:#3d73dd; }
  .worker-log { min-height:325px; max-height:530px; overflow:auto; padding:.35rem; border:1px solid #e3eaf6; border-radius:1rem; background:linear-gradient(180deg,#fbfcff,#f6f9fe); }
  .worker-log-empty { display:grid; min-height:270px; place-items:center; padding:1.5rem; color:#8290a7; text-align:center; font-size:.83rem; }
  .worker-log-empty i { display:block; margin-bottom:.65rem; color:#90b2f4; font-size:1.55rem; }
  .worker-log-item { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:start; gap:.7rem; padding:.78rem .75rem; border-radius:.78rem; }
  .worker-log-item + .worker-log-item { margin-top:.18rem; }
  .worker-log-item:hover { background:#fff; }
  .worker-log-item .state { display:grid; place-items:center; width:1.65rem; height:1.65rem; border-radius:.55rem; color:#3772dc; background:#edf4ff; font-size:.72rem; }
  .worker-log-item.success .state { color:#16804d; background:#e7f7ee; }
  .worker-log-item.warning .state { color:#ad7400; background:#fff6dd; }
  .worker-log-item.error .state { color:#bd4450; background:#fff0f2; }
  .worker-log-title { overflow:hidden; color:#263550; font-size:.83rem; font-weight:800; text-overflow:ellipsis; white-space:nowrap; }
  .worker-log-detail { margin-top:.15rem; color:#76859b; font-size:.74rem; line-height:1.5; overflow-wrap:anywhere; white-space:pre-line; }
  .worker-log-time { color:#98a5b8; font-size:.7rem; white-space:nowrap; }
  .worker-cron { border:1px solid #dce8fa; border-radius:.95rem; background:#f8fbff; }
  .worker-cron pre { overflow:auto; margin:0; padding:.82rem 1rem; color:#30405c; font-size:.74rem; line-height:1.5; white-space:pre-wrap; word-break:break-word; }
  .worker-cron-copy { border:0; border-left:1px solid #dce8fa; border-radius:0 .9rem .9rem 0; color:#396dd2; background:transparent; font-size:.79rem; font-weight:750; }
  .worker-cron-copy:hover { color:#174fab; background:#eef5ff; }
  @media (max-width:991.98px) { .worker-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:575.98px) { .worker-hero,.worker-panel .card-body { padding:1rem; }.worker-summary { gap:.55rem; }.worker-stat { padding:.78rem; }.worker-stat .number { font-size:1.24rem; }.worker-stat .label { font-size:.69rem; }.worker-run-actions .btn { flex:1 1 auto; }.worker-log { min-height:245px; }.worker-log-item { grid-template-columns:auto minmax(0,1fr); }.worker-log-time { grid-column:2; }.worker-cron { flex-direction:column; }.worker-cron-copy { min-height:38px; border-top:1px solid #dce8fa; border-left:0; border-radius:0 0 .9rem .9rem; } }
</style>

<section class="media-worker" id="medicalMediaWorker">
  <div class="worker-hero mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div>
        <div class="worker-eyebrow"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Xử lý nền</div>
        <h1>Tải ảnh trước, nén ảnh sau</h1>
        <p>API nhận bài chỉ lưu URL ảnh để phản hồi nhanh. Worker tách thành hai bước: tải ảnh gốc về thư viện trước, sau đó nén nền khi server rảnh hơn.</p>
      </div>
      <span class="worker-status" id="workerStatus" role="status"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i><span>Đang kiểm tra hàng chờ…</span></span>
    </div>
  </div>

  <div class="worker-summary mb-3" aria-live="polite">
    <article class="worker-stat">
      <div class="icon"><i class="fa-solid fa-hospital" aria-hidden="true"></i></div>
      <div class="number" id="pendingFacilities">—</div>
      <div class="label">Cơ sở còn ảnh cần xử lý</div>
    </article>
    <article class="worker-stat">
      <div class="icon"><i class="fa-solid fa-user-doctor" aria-hidden="true"></i></div>
      <div class="number" id="pendingDoctors">—</div>
      <div class="label">Bác sĩ còn ảnh cần xử lý</div>
    </article>
    <article class="worker-stat">
      <div class="icon"><i class="fa-solid fa-images" aria-hidden="true"></i></div>
      <div class="number" id="pendingRemoteImages">—</div>
      <div class="label">URL ảnh đang chờ tải về</div>
    </article>
    <article class="worker-stat">
      <div class="icon"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i></div>
      <div class="number" id="pendingDownloadedImages">—</div>
      <div class="label">Ảnh đã tải, chờ nén</div>
    </article>
  </div>

  <div class="row g-3">
    <div class="col-xl-5">
      <div class="worker-panel h-100">
        <div class="card-body">
          <h2 class="worker-title"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Xử lý ảnh theo hai bước</h2>
          <p class="worker-caption">Tải ảnh là bước nhanh hơn. Nén ảnh chạy riêng để không làm chậm việc đưa gallery về máy chủ của bạn.</p>

          <div class="row g-2 mt-2">
            <div class="col-12">
              <label for="workerType" class="form-label mb-1">Dữ liệu cần quét</label>
              <select class="form-select" id="workerType">
                <option value="all">Cả cơ sở y tế và bác sĩ</option>
                <option value="facility">Chỉ cơ sở y tế</option>
                <option value="doctor">Chỉ bác sĩ</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label for="workerLimit" class="form-label mb-1">Số ảnh/URL mỗi lượt</label>
              <select class="form-select" id="workerLimit">
                <option value="1">1 ảnh</option>
                <option value="3" selected>3 ảnh</option>
                <option value="10">10 ảnh</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label for="workerParallel" class="form-label mb-1">Ảnh tải song song</label>
              <select class="form-select" id="workerParallel">
                <option value="1">1 ảnh</option>
                <option value="2">2 ảnh</option>
                <option value="3" selected>3 ảnh</option>
              </select>
            </div>
          </div>

          <div class="worker-run-actions mt-3">
            <button id="workerDownload" type="button" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-down me-2" aria-hidden="true"></i>Tải về trước</button>
            <button id="workerCompress" type="button" class="btn btn-outline-primary"><i class="fa-solid fa-file-zipper me-2" aria-hidden="true"></i>Nén ảnh đã tải</button>
            <button id="workerScan" type="button" class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Quét URL hiện có</button>
            <button id="workerRetryFailed" type="button" class="btn btn-outline-warning"><i class="fa-solid fa-rotate-left me-2" aria-hidden="true"></i>Thử lại lỗi</button>
            <button id="workerRefresh" type="button" class="btn btn-light ms-sm-auto" title="Làm mới số liệu"><i class="fa-solid fa-rotate" aria-hidden="true"></i><span class="visually-hidden">Làm mới</span></button>
          </div>

          <div class="worker-queue-note">
            <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
            <span>Mỗi ảnh có nhật ký riêng: tên nguồn, dung lượng tải về, dung lượng sau nén và URL nội bộ. Ảnh lỗi vẫn giữ URL cũ để bạn có thể thử lại.</span>
          </div>

          <div class="mt-4">
            <h3 class="worker-title fs-6"><i class="fa-regular fa-clock" aria-hidden="true"></i> Chạy tự động bằng Cron</h3>
            <p class="worker-caption">Dùng Cron tải URL thường xuyên; nén chạy thưa hơn để không chiếm CPU khi website đang có khách.</p>
            <div class="worker-cron d-flex mt-2">
              <pre id="workerCronCommand"><?php echo htmlspecialchars('* * * * * ' . $workerPhpBinary . ' ' . $workerCronPath . ' --type=all --mode=download --limit=10 --parallel=3 >/dev/null 2>&1' . "\n" . '*/5 * * * * ' . $workerPhpBinary . ' ' . $workerCronPath . ' --type=all --mode=compress --limit=3 --no-scan >/dev/null 2>&1', ENT_QUOTES, 'UTF-8'); ?></pre>
              <button id="workerCopyCron" class="worker-cron-copy px-3" type="button"><i class="fa-regular fa-copy me-1" aria-hidden="true"></i>Copy</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-7">
      <div class="worker-panel h-100">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
            <div>
              <h2 class="worker-title"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Nhật ký lượt chạy</h2>
              <p class="worker-caption">Kết quả gần nhất trong phiên đang mở. Refresh trang sẽ xoá danh sách này.</p>
            </div>
            <button id="workerClearLog" type="button" class="btn btn-sm btn-light text-secondary">Xoá nhật ký</button>
          </div>
          <div class="worker-log" id="workerLog" aria-live="polite">
            <div class="worker-log-empty" id="workerLogEmpty"><div><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>Chưa có lượt xử lý nào.<br>Chọn cấu hình bên trái rồi bấm <strong>“Tải về trước”</strong>.</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
(() => {
  const statusEndpoint = <?php echo json_encode(admin_url('api/medical-media-worker-status.php'), JSON_UNESCAPED_SLASHES); ?>;
  const runEndpoint = <?php echo json_encode(admin_url('api/medical-media-worker-run.php'), JSON_UNESCAPED_SLASHES); ?>;
  const el = {
    type: document.getElementById('workerType'),
    limit: document.getElementById('workerLimit'),
    parallel: document.getElementById('workerParallel'),
    download: document.getElementById('workerDownload'),
    compress: document.getElementById('workerCompress'),
    scan: document.getElementById('workerScan'),
    retryFailed: document.getElementById('workerRetryFailed'),
    refresh: document.getElementById('workerRefresh'),
    status: document.getElementById('workerStatus'),
    facilities: document.getElementById('pendingFacilities'),
    doctors: document.getElementById('pendingDoctors'),
    remoteImages: document.getElementById('pendingRemoteImages'),
    downloadedImages: document.getElementById('pendingDownloadedImages'),
    log: document.getElementById('workerLog'),
    empty: document.getElementById('workerLogEmpty'),
    clearLog: document.getElementById('workerClearLog'),
    copyCron: document.getElementById('workerCopyCron'),
    cron: document.getElementById('workerCronCommand')
  };
  let running = false;
  let lastPending = null;
  let progressPollTimer = null;
  let announcedActiveJobs = new Set();

  const toNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  };
  const firstValue = (sources, keys, fallback = 0) => {
    for (const source of sources) {
      if (!source || typeof source !== 'object') continue;
      for (const key of keys) {
        if (source[key] !== undefined && source[key] !== null && source[key] !== '') return toNumber(source[key], fallback);
      }
    }
    return fallback;
  };
  const asPayload = (data) => (data && typeof data.data === 'object' && data.data !== null) ? {...data, ...data.data} : (data || {});
  function queueCounts(input) {
    const data = asPayload(input);
    const sources = [data.pending, data.queue, data.counts, data.summary, data];
    const facilities = firstValue(sources, ['facilities', 'facility', 'facility_count', 'pending_facilities']);
    const doctors = firstValue(sources, ['doctors', 'doctor', 'doctor_count', 'pending_doctors']);
    const images = firstValue(sources, ['images', 'image', 'image_count', 'pending_images', 'urls']);
    const downloaded = firstValue(sources, ['downloaded', 'waiting_compress', 'pending_compress', 'downloaded_pending_compress']);
    const total = firstValue(sources, ['total', 'pending_total'], images + downloaded);
    return {facilities, doctors, images, downloaded, total};
  }
  function replaceStatus(iconClass, message) {
    const icon = document.createElement('i');
    icon.className = 'fa-solid ' + iconClass;
    icon.setAttribute('aria-hidden', 'true');
    const label = document.createElement('span');
    label.textContent = String(message || 'Sẵn sàng xử lý');
    el.status.replaceChildren(icon, label);
  }
  function setBusy(state, message = '') {
    running = state;
    const controlsLocked = state;
    [el.download, el.compress, el.scan, el.retryFailed, el.refresh, el.type, el.limit, el.parallel].forEach((node) => { if (node) node.disabled = controlsLocked; });
    el.status.classList.toggle('is-running', state);
    el.status.classList.remove('is-error');
    replaceStatus(state ? 'fa-circle-notch fa-spin' : 'fa-circle-check', message || (state ? 'Worker đang xử lý…' : 'Sẵn sàng xử lý'));
  }
  function setStatusError(message) {
    el.status.classList.remove('is-running');
    el.status.classList.add('is-error');
    replaceStatus('fa-triangle-exclamation', message || 'Không tải được trạng thái');
  }
  function updateCounts(data) {
    const counts = queueCounts(data);
    el.facilities.textContent = new Intl.NumberFormat('vi-VN').format(counts.facilities);
    el.doctors.textContent = new Intl.NumberFormat('vi-VN').format(counts.doctors);
    el.remoteImages.textContent = new Intl.NumberFormat('vi-VN').format(counts.images);
    el.downloadedImages.textContent = new Intl.NumberFormat('vi-VN').format(counts.downloaded);
    lastPending = counts;
    return counts;
  }
  function logEntry(type, title, detail = '') {
    el.empty?.remove();
    const row = document.createElement('article');
    row.className = 'worker-log-item ' + (type || 'info');
    const icon = type === 'success' ? 'fa-check' : type === 'warning' ? 'fa-triangle-exclamation' : type === 'error' ? 'fa-xmark' : 'fa-info';
    const state = document.createElement('span'); state.className = 'state';
    const stateIcon = document.createElement('i'); stateIcon.className = 'fa-solid ' + icon; stateIcon.setAttribute('aria-hidden', 'true'); state.appendChild(stateIcon);
    const body = document.createElement('div');
    const titleNode = document.createElement('div'); titleNode.className = 'worker-log-title'; titleNode.textContent = String(title || 'Đã cập nhật');
    body.appendChild(titleNode);
    if (detail) { const detailNode = document.createElement('div'); detailNode.className = 'worker-log-detail'; detailNode.textContent = String(detail); body.appendChild(detailNode); }
    const time = document.createElement('time'); time.className = 'worker-log-time'; time.textContent = new Date().toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    row.append(state, body, time);
    el.log.prepend(row);
    while (el.log.children.length > 80) el.log.lastElementChild?.remove();
  }
  function resultItems(data) {
    const payload = asPayload(data);
    for (const key of ['results', 'items', 'processed_items', 'records']) {
      if (Array.isArray(payload[key])) return payload[key];
    }
    return [];
  }
  function formatBytes(value) {
    const bytes = toNumber(value, 0);
    if (bytes <= 0) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    const size = bytes / Math.pow(1024, index);
    return (index === 0 ? String(Math.round(size)) : size.toLocaleString('vi-VN', {maximumFractionDigits: 1})) + ' ' + units[index];
  }
  function formatDuration(value) {
    const milliseconds = toNumber(value, 0);
    if (milliseconds <= 0) return '';
    if (milliseconds < 1000) return Math.round(milliseconds) + ' ms';
    return (milliseconds / 1000).toLocaleString('vi-VN', {maximumFractionDigits: 2}) + ' giây';
  }
  function timingDetails(value, status) {
    const timing = value && typeof value === 'object' ? value : {};
    const summary = [];
    const httpStatus = toNumber(status ?? timing.http_status, 0);
    const total = formatDuration(timing.total_ms);
    if (httpStatus) summary.push('HTTP ' + httpStatus);
    if (total) summary.push('Tổng: ' + total);
    if (summary.length) summary[0] = 'Kết nối: ' + summary[0];

    const phases = [];
    const dns = formatDuration(timing.dns_ms);
    const tcp = formatDuration(timing.tcp_ms);
    const tls = formatDuration(timing.tls_ms);
    const ttfb = formatDuration(timing.ttfb_ms);
    const wait = formatDuration(timing.server_wait_ms);
    const transfer = formatDuration(timing.download_ms);
    if (dns) phases.push('DNS ' + dns);
    if (tcp) phases.push('TCP ' + tcp);
    if (tls) phases.push('TLS ' + tls);
    if (ttfb) phases.push('Byte đầu ' + ttfb);
    if (wait) phases.push('Chờ server ' + wait);
    if (transfer) phases.push('Tải dữ liệu ' + transfer);
    if (phases.length) summary.push(phases.join(' · '));
    const redirects = toNumber(timing.redirects, 0);
    if (redirects) summary.push('Redirect: ' + redirects + ' lần');
    return summary;
  }
  function urlHost(value) {
    const source = String(value || '').trim();
    if (!source) return '';
    try { return new URL(source).hostname.replace(/^www\./i, '') || source; } catch (_) { return source; }
  }
  function phaseLabel(phase, fallbackAction = '') {
    const value = String(phase || fallbackAction || '').toLowerCase();
    if (value === 'downloaded' || value === 'download') return 'Đã tải về';
    if (value === 'compressed' || value === 'compress') return 'Đã nén';
    if (value === 'optimized') return 'Đã tối ưu';
    return 'Đã xử lý';
  }
  function renderItemResult(item, fallbackAction) {
    const name = String(item?.name || item?.title || item?.entity_name || item?.id || 'Ảnh chưa đặt tên');
    const phase = phaseLabel(item?.phase, fallbackAction);
    const source = String(item?.source_url || item?.source || item?.url || '').trim();
    const local = String(item?.local_url || item?.stored_url || item?.url_local || '').trim();
    const downloaded = formatBytes(item?.download_bytes ?? item?.bytes_downloaded ?? item?.source_bytes);
    const stored = formatBytes(item?.stored_bytes ?? item?.compressed_bytes ?? item?.output_bytes);
    const error = String(item?.error || item?.message_error || '').trim();
    const ok = item?.ok !== false && !error;
    const details = [];
    const host = urlHost(source);
    if (host) details.push('Nguồn: ' + host);
    if (source) details.push('URL: ' + source);
    if (downloaded) details.push('Tải về: ' + downloaded);
    if (stored) details.push('Lưu nội bộ: ' + stored);
    details.push(...timingDetails(item?.timing, item?.http_status));
    if (local) details.push('URL nội bộ: ' + local);
    if (error) details.push('Lỗi: ' + error);
    logEntry(ok ? 'success' : 'error', phase + ' · ' + name, details.join('\n'));
  }
  function renderActiveJobs(data) {
    const payload = asPayload(data);
    const jobs = Array.isArray(payload.active) ? payload.active : [];
    jobs.forEach((job) => {
      const id = String(job?.id || job?.job_id || '');
      if (!id || announcedActiveJobs.has(id)) return;
      announcedActiveJobs.add(id);
      const isCompress = String(job?.phase || '').toLowerCase() === 'compress';
      const name = String(job?.name || job?.entity_id || 'Ảnh chưa đặt tên');
      const source = String(job?.source_url || '').trim();
      const details = [];
      const host = urlHost(source);
      if (host) details.push('Nguồn: ' + host);
      if (source) details.push('URL: ' + source);
      if (job?.locked_at) details.push('Bắt đầu: ' + String(job.locked_at));
      logEntry('info', (isCompress ? 'Đang nén' : 'Đang tải') + ' · ' + name, details.join('\n'));
    });
  }
  function startProgressPolling() {
    announcedActiveJobs = new Set();
    window.clearInterval(progressPollTimer);
    // A status request is deliberately lightweight. On hosts with only one
    // PHP worker it simply returns after this batch finishes; on normal FPM it
    // gives the person in admin a live list of the claimed URLs.
    progressPollTimer = window.setInterval(() => { refreshStatus(false, true); }, 1400);
    window.setTimeout(() => { refreshStatus(false, true); }, 240);
  }
  function stopProgressPolling() {
    window.clearInterval(progressPollTimer);
    progressPollTimer = null;
  }
  function renderRun(data, fallbackAction = '') {
    const payload = asPayload(data);
    const items = resultItems(payload);
    if (items.length) {
      items.forEach((item) => renderItemResult(item, fallbackAction));
    } else {
      const processed = firstValue([payload], ['processed', 'processed_count', 'records_processed'], 0);
      const imported = firstValue([payload], ['imported', 'images_imported', 'success', 'downloaded', 'compressed'], 0);
      const errors = firstValue([payload], ['errors_count', 'error_count', 'failed'], 0);
      const counts = queueCounts(payload);
      const parts = [];
      if (processed) parts.push(processed + ' hồ sơ');
      if (imported) parts.push(imported + ' ảnh ' + (fallbackAction === 'compress' ? 'đã nén' : 'đã tải'));
      if (counts.images) parts.push('chờ tải ' + counts.images + ' URL');
      if (counts.downloaded) parts.push('chờ nén ' + counts.downloaded + ' ảnh');
      if (errors) parts.push(errors + ' lỗi');
      logEntry(errors ? 'warning' : 'success', payload.message || (processed ? 'Đã chạy Worker' : 'Không còn ảnh cần xử lý'), parts.join(' · '));
    }
    updateCounts(payload);
  }
  async function readJson(response) {
    const text = await response.text();
    try { return text ? JSON.parse(text) : {}; } catch (_) { throw new Error('Phản hồi Worker không phải JSON hợp lệ.'); }
  }
  async function refreshStatus(showToast = false, showActive = false) {
    try {
      const response = await fetch(statusEndpoint, {headers: {Accept:'application/json'}, cache:'no-store'});
      const data = await readJson(response);
      if (!response.ok || data.ok === false) throw new Error(data.message || 'Không thể tải hàng chờ.');
      const counts = updateCounts(data);
      if (showActive) renderActiveJobs(data);
      if (!running) {
        const statusParts = [];
        if (counts.images) statusParts.push('chờ tải ' + counts.images + ' URL');
        if (counts.downloaded) statusParts.push('chờ nén ' + counts.downloaded + ' ảnh');
        setBusy(false, statusParts.length ? 'Sẵn sàng · ' + statusParts.join(' · ') : 'Hàng chờ ảnh đang trống');
      }
      if (showToast && window.adminToast) window.adminToast('success', 'Đã cập nhật trạng thái Worker.', {icon:'fa-solid fa-rotate'});
      return counts;
    } catch (error) {
      setStatusError(error.message || 'Không thể tải trạng thái');
      if (showToast && window.adminToast) window.adminToast('danger', error.message || 'Không thể tải trạng thái Worker.', {icon:'fa-solid fa-triangle-exclamation'});
      return null;
    }
  }
  async function runImageAction(action) {
    if (running) return false;
    const isDownload = action === 'download';
    const actionTitle = isDownload ? 'Đang tải ảnh gốc về thư viện…' : 'Đang nén ảnh đã tải…';
    setBusy(true, actionTitle);
    startProgressPolling();
    try {
      const response = await fetch(runEndpoint, {
        method: 'POST',
        headers: {'Content-Type':'application/json', Accept:'application/json'},
        body: JSON.stringify({action, type: el.type.value, limit: Number(el.limit.value), parallel: Number(el.parallel.value)})
      });
      const data = await readJson(response);
      if (!response.ok || data.ok === false) throw new Error(data.message || 'Worker không thể hoàn tất lượt chạy.');
      renderRun(data, action);
      const counts = queueCounts(data);
      const nextSteps = [];
      if (counts.images) nextSteps.push('chờ tải ' + counts.images + ' URL');
      if (counts.downloaded) nextSteps.push('chờ nén ' + counts.downloaded + ' ảnh');
      setBusy(false, nextSteps.length ? 'Đã hoàn tất · ' + nextSteps.join(' · ') : 'Hàng chờ ảnh đang trống');
      if (window.adminToast) window.adminToast('success', isDownload ? 'Đã hoàn tất lượt tải ảnh.' : 'Đã hoàn tất lượt nén ảnh.', {icon:isDownload ? 'fa-solid fa-cloud-arrow-down' : 'fa-solid fa-file-zipper'});
      return counts;
    } catch (error) {
      logEntry('error', isDownload ? 'Lượt tải ảnh không thành công' : 'Lượt nén ảnh không thành công', error.message || 'Lỗi không xác định');
      setBusy(false, 'Worker cần kiểm tra lại');
      setStatusError(error.message || 'Worker gặp lỗi');
      if (window.adminToast) window.adminToast('danger', error.message || 'Worker không thể hoàn tất lượt chạy.', {icon:'fa-solid fa-triangle-exclamation'});
      return null;
    } finally {
      stopProgressPolling();
    }
  }
  async function runWorkerAction(action, progressText) {
    if (running) return;
    setBusy(true, progressText);
    try {
      const response = await fetch(runEndpoint, {
        method: 'POST',
        headers: {'Content-Type':'application/json', Accept:'application/json'},
        body: JSON.stringify({action, type: el.type.value, scan_limit: 250})
      });
      const data = await readJson(response);
      if (!response.ok || data.ok === false) throw new Error(data.message || 'Không thể hoàn tất thao tác Worker.');
      updateCounts(data);
      const detail = action === 'scan'
        ? `Đã quét ${Number(data.scan?.scanned || 0)} hồ sơ, thêm ${Number(data.scan?.queued || 0)} URL.`
        : `Đã đưa ${Number(data.retried || 0)} ảnh lỗi trở lại hàng chờ.`;
      logEntry('success', data.message || 'Đã cập nhật hàng chờ', detail);
      setBusy(false, 'Sẵn sàng xử lý');
      if (window.adminToast) window.adminToast('success', data.message || 'Đã cập nhật hàng chờ.', {icon:'fa-solid fa-check'});
    } catch (error) {
      logEntry('error', 'Không thể cập nhật hàng chờ', error.message || 'Lỗi không xác định');
      setBusy(false, 'Worker cần kiểm tra lại');
      setStatusError(error.message || 'Worker gặp lỗi');
      if (window.adminToast) window.adminToast('danger', error.message || 'Không thể cập nhật hàng chờ.', {icon:'fa-solid fa-triangle-exclamation'});
    }
  }
  async function copyCron() {
    const value = el.cron.textContent.trim();
    try {
      await navigator.clipboard.writeText(value);
    } catch (_) {
      const area = document.createElement('textarea'); area.value = value; area.style.cssText = 'position:fixed;left:-9999px'; document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove();
    }
    if (window.adminToast) window.adminToast('success', 'Đã copy lệnh Cron.', {icon:'fa-regular fa-copy'});
  }
  el.download.addEventListener('click', () => runImageAction('download'));
  el.compress.addEventListener('click', () => runImageAction('compress'));
  el.scan.addEventListener('click', () => runWorkerAction('scan', 'Đang quét URL ảnh hiện có…'));
  el.retryFailed.addEventListener('click', () => runWorkerAction('retry_failed', 'Đang đưa ảnh lỗi trở lại hàng chờ…'));
  el.refresh.addEventListener('click', () => refreshStatus(true));
  el.clearLog.addEventListener('click', () => {
    el.log.innerHTML = '<div class="worker-log-empty" id="workerLogEmpty"><div><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i>Nhật ký đã được xoá.</div></div>';
    el.empty = document.getElementById('workerLogEmpty');
  });
  el.copyCron.addEventListener('click', copyCron);
  refreshStatus(false);
})();
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
