<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$adminPageTitle = 'Admin • Rà soát ảnh hỏng';
$adminHeaderTitle = 'Rà soát ảnh hỏng';
$adminHeaderSubtitle = 'Quét tự động toàn bộ Cơ sở y tế & Bác sĩ với lưu vết trạng thái trong Database';
$adminActive = 'medical-image-audit';
$csrfToken = admin_csrf_token();

require __DIR__ . '/_layout_start.php';
?>

<style>
  .audit-shell { --audit-blue:#2563eb; --audit-ink:#16233a; --audit-line:#dfe8f6; --audit-soft:#f5f8ff; }
  .audit-hero { position:relative; overflow:hidden; padding:1.35rem 1.5rem; border:1px solid #d7e5ff; border-radius:1.25rem; background:linear-gradient(118deg,#f7fbff 0%,#fff 55%,#eef5ff 100%); box-shadow:0 12px 32px rgba(36,76,140,.08); }
  .audit-eyebrow { display:inline-flex; align-items:center; gap:.45rem; padding:.34rem .62rem; border-radius:999px; color:#156847; background:#eaf8f0; font-size:.73rem; font-weight:800; letter-spacing:.02em; text-transform:uppercase; }
  .audit-hero h1 { margin:.55rem 0 .35rem; color:var(--audit-ink); font-size:clamp(1.2rem,2.2vw,1.6rem); font-weight:800; letter-spacing:-.03em; }
  .audit-hero p { max-width:780px; margin:0; color:#5e708a; font-size:.88rem; line-height:1.55; }
  .audit-throttle-badge { display:inline-flex; align-items:center; gap:.45rem; padding:.45rem .72rem; border:1px solid #d7e5fb; border-radius:999px; color:#355079; background:#fff; font-size:.78rem; font-weight:700; white-space:nowrap; }
  .audit-throttle-badge.is-scanning { color:#1650c2; border-color:#b7d1ff; background:#eff5ff; }
  .audit-scanner-panel { border:1px solid #d5e4ff; border-radius:1.1rem; background:linear-gradient(180deg,#ffffff,#f7faff); padding:1rem 1.15rem; }
  .audit-stat-grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.65rem; }
  .audit-stat-box { padding:.68rem .85rem; border:1px solid #e1eaf6; border-radius:.85rem; background:#fff; }
  .audit-stat-box .num { font-size:1.22rem; font-weight:800; color:#16233a; line-height:1.1; }
  .audit-stat-box .lbl { font-size:.73rem; font-weight:700; color:#697b96; margin-top:.2rem; }
  .audit-pills { display:flex; flex-wrap:wrap; gap:.42rem; }
  .audit-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .68rem; border:1px solid #d9e4f4; border-radius:999px; color:#425573; background:#fff; font-size:.77rem; font-weight:700; cursor:pointer; transition:all .14s ease; }
  .audit-pill:hover { border-color:#9ec0fb; color:#1c4ed8; background:#f4f8ff; }
  .audit-pill.active { border-color:#2563eb; color:#fff; background:linear-gradient(135deg,#3478f6,#2461dc); box-shadow:0 6px 14px rgba(37,99,235,.18); }
  .audit-pill .count { display:inline-block; padding:.08rem .4rem; border-radius:999px; background:rgba(15,23,42,.08); font-size:.7rem; font-weight:800; }
  .audit-pill.active .count { color:#fff; background:rgba(255,255,255,.22); }
  .audit-row.is-browser-broken { background:#fff8f9 !important; }
  .audit-thumb-grid { display:flex; flex-wrap:wrap; gap:.58rem; }
  .audit-thumb-card { position:relative; width:136px; border:1px solid #dce6f4; border-radius:.75rem; background:#fff; overflow:hidden; transition:border-color .15s ease, box-shadow .15s ease; }
  .audit-thumb-card.is-cover { border-color:#93bbfb; box-shadow:0 0 0 2px rgba(37,99,235,.12); }
  .audit-thumb-card.state-broken { border-color:#f18b96; background:#fff5f6; }
  .audit-thumb-card.state-warning { border-color:#f3c66f; background:#fffdf5; }
  .audit-thumb-card.state-ok { border-color:#a5dfb8; }
  .audit-thumb-frame { position:relative; width:100%; height:84px; background:#f1f5fb; display:grid; place-items:center; overflow:hidden; }
  .audit-thumb-frame img { width:100%; height:100%; object-fit:cover; display:block; }
  .audit-thumb-frame .thumb-placeholder { color:#8898b0; font-size:.72rem; text-align:center; padding:.35rem; line-height:1.3; }
  .audit-thumb-badge { position:absolute; top:.32rem; left:.32rem; padding:.14rem .4rem; border-radius:.4rem; font-size:.64rem; font-weight:800; color:#fff; background:rgba(15,23,42,.72); backdrop-filter:blur(4px); }
  .audit-thumb-badge.cover { background:#1d58d8; }
  .audit-thumb-meta { padding:.42rem .48rem; font-size:.69rem; line-height:1.35; }
  .audit-thumb-status { font-weight:750; color:#4b5d78; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .audit-thumb-card.state-broken .audit-thumb-status { color:#b91c1c; }
  .audit-thumb-card.state-warning .audit-thumb-status { color:#a16207; }
  .audit-thumb-card.state-ok .audit-thumb-status { color:#15803d; }
  .audit-thumb-actions { display:flex; gap:.25rem; margin-top:.32rem; }
  .audit-thumb-actions .btn { min-height:24px !important; padding:.12rem .36rem !important; font-size:.66rem !important; border-radius:.4rem !important; flex:1 1 auto; }
  .audit-issue-list { display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.38rem; }
  .audit-issue-tag { display:inline-flex; align-items:center; gap:.28rem; padding:.18rem .48rem; border-radius:.45rem; font-size:.7rem; font-weight:700; }
  .audit-issue-tag.danger { color:#b42318; background:#fee4e2; border:1px solid #fecdca; }
  .audit-issue-tag.warning { color:#9a5b00; background:#fef3c7; border:1px solid #fde68a; }
  .audit-issue-tag.info { color:#1d4ed8; background:#dbeafe; border:1px solid #bfdbfe; }
  .db-scan-badge { display:inline-flex; align-items:center; gap:.32rem; padding:.22rem .55rem; border-radius:999px; font-size:.71rem; font-weight:800; }
  .db-scan-badge.unscanned { color:#52627a; background:#eef2f8; }
  .db-scan-badge.broken { color:#b42318; background:#fee4e2; }
  .db-scan-badge.warning { color:#9a5b00; background:#fef3c7; }
  .db-scan-badge.ok { color:#15803d; background:#dcfce7; }
  @media (max-width:991.98px) { .audit-stat-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>

<section class="audit-shell" id="medicalImageAuditApp">
  <div class="audit-hero mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div>
        <div class="audit-eyebrow"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Quét toàn hệ thống &amp; Lưu vết DB</div>
        <h1>Rà soát ảnh hỏng tự động (Đánh dấu Đã quét / Chưa quét trong DB)</h1>
        <p>Bấm <strong>“Quét toàn bộ chưa quét”</strong> một lần để hệ thống tự động duyệt hết toàn bộ Cơ sở y tế / Bác sĩ. Mỗi nhịp chỉ tải thử tối đa <strong>64 KB đầu của ảnh</strong> (nhẹ hơn 30 lần tải nguyên tấm, không giải nén GD) và lưu trực tiếp trạng thái <code>image_scan_status</code>, <code>image_scanned_at</code> vào Database.</p>
      </div>
      <div class="d-flex flex-column align-items-end gap-2">
        <span class="audit-throttle-badge" id="auditQueueBadge" role="status">
          <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
          <span id="auditQueueText">Trạng thái: Sẵn sàng</span>
        </span>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(admin_url('medical_media_worker.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa-solid fa-cloud-arrow-down me-1"></i>Worker tải/nén ảnh
          </a>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(admin_url('medical_facilities.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa-solid fa-hospital me-1"></i>DS Cơ sở
          </a>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(admin_url('medical_doctors.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa-solid fa-user-doctor me-1"></i>DS Bác sĩ
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-warning d-none align-items-center justify-content-between gap-3 mb-3" id="schemaWarningBox">
    <div>
      <i class="fa-solid fa-database me-2"></i>
      <strong>Chưa khởi tạo cột đánh dấu quét trong DB:</strong> Bảng dữ liệu cần thêm 3 cột <code>image_scan_status</code>, <code>image_scanned_at</code>, <code>image_scan_json</code> để lưu trạng thái trang nào đã quét / chưa quét.
    </div>
    <button type="button" class="btn btn-sm btn-warning fw-bold" id="btnMigrateSchema">
      <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Tạo cột DB ngay
    </button>
  </div>

  <!-- Bảng điều khiển Quét Toàn Bộ Hệ Thống 1-Click -->
  <div class="audit-scanner-panel shadow-soft mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
      <div class="d-flex align-items-center gap-2">
        <span class="icon-pill" style="width:34px;height:34px;"><i class="fa-solid fa-satellite-dish"></i></span>
        <div>
          <div class="fw-bold" style="font-size:.94rem;">Quét tự động toàn bộ danh sách (Không cần chuyển từng trang)</div>
          <div class="small text-secondary" id="fullScanStatusLine">Hệ thống sẽ tự động lấy lần lượt các hồ sơ <strong>Chưa quét</strong> trong DB, kiểm tra nhẹ 64 KB/ảnh rồi đánh dấu hoàn tất.</div>
        </div>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-primary btn-sm" id="btnStartFullScan">
          <i class="fa-solid fa-play me-1"></i>Quét toàn bộ chưa quét (<span id="btnUnscannedCount">0</span>)
        </button>
        <button type="button" class="btn btn-outline-warning btn-sm d-none" id="btnPauseFullScan">
          <i class="fa-solid fa-pause me-1"></i>Tạm dừng quét
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetAllScans" title="Đặt lại tất cả hồ sơ về trạng thái Chưa quét để quét lại từ đầu">
          <i class="fa-solid fa-rotate-left me-1"></i>Đặt lại về Chưa quét
        </button>
      </div>
    </div>

    <div class="progress mb-3" style="height:10px;border-radius:999px;background:#e5edf9;">
      <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" id="fullScanProgressBar" role="progressbar" style="width:0%"></div>
    </div>

    <div class="audit-stat-grid">
      <div class="audit-stat-box">
        <div class="num" id="statScannedTotal">0 / 0</div>
        <div class="lbl">Tiến độ đã quét trong DB</div>
      </div>
      <div class="audit-stat-box">
        <div class="num text-secondary" id="statUnscanned">0</div>
        <div class="lbl">Hồ sơ chưa quét</div>
      </div>
      <div class="audit-stat-box">
        <div class="num text-danger" id="statScannedBroken">0</div>
        <div class="lbl">Đã quét · Lỗi ảnh</div>
      </div>
      <div class="audit-stat-box">
        <div class="num text-warning" id="statScannedWarning">0</div>
        <div class="lbl">Đã quét · Cảnh báo</div>
      </div>
      <div class="audit-stat-box">
        <div class="num text-success" id="statScannedOk">0</div>
        <div class="lbl">Đã quét · Ảnh tốt</div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-soft mb-3">
    <div class="card-body p-3 p-md-4">
      <form id="auditFilterForm" class="row g-2 align-items-end mb-3">
        <div class="col-6 col-md-2">
          <label class="form-label" for="auditEntityType">Đối tượng</label>
          <select class="form-select" id="auditEntityType">
            <option value="facility">Cơ sở y tế</option>
            <option value="doctor">Bác sĩ</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label" for="auditLanguage">Ngôn ngữ</label>
          <select class="form-select" id="auditLanguage">
            <option value="vi">Tiếng Việt (vi)</option>
            <option value="en">English (en)</option>
            <option value="all">Tất cả</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label" for="auditStatus">Trạng thái</label>
          <select class="form-select" id="auditStatus">
            <option value="published">published</option>
            <option value="draft">draft</option>
            <option value="all">Tất cả</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label" for="auditLimit">Hiển thị / trang</label>
          <select class="form-select" id="auditLimit">
            <option value="8">8 hồ sơ / trang</option>
            <option value="12" selected>12 hồ sơ / trang</option>
            <option value="20">20 hồ sơ / trang</option>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="auditSearch">Tìm kiếm tên, slug, tỉnh/thành hoặc ID</label>
          <div class="input-group">
            <input type="search" class="form-control" id="auditSearch" placeholder="VD: Gia Bảo, Đà Nẵng, 83...">
            <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Lọc</button>
          </div>
        </div>
      </form>

      <div class="audit-pills mb-3" id="auditIssuePills" role="tablist" aria-label="Bộ lọc nhóm lỗi ảnh">
        <button type="button" class="audit-pill active" data-issue="any_issue">
          <i class="fa-solid fa-triangle-exclamation"></i><span>Tất cả có lỗi</span><span class="count" data-count="any_issue">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="unscanned">
          <i class="fa-regular fa-clock"></i><span>Chưa quét DB</span><span class="count" data-count="unscanned">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="scanned_broken">
          <i class="fa-solid fa-circle-xmark"></i><span>Đã quét · Lỗi</span><span class="count" data-count="scanned_broken">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="scanned_warning">
          <i class="fa-solid fa-circle-exclamation"></i><span>Đã quét · Cảnh báo</span><span class="count" data-count="scanned_warning">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="scanned_ok">
          <i class="fa-solid fa-circle-check"></i><span>Đã quét · Tốt</span><span class="count" data-count="scanned_ok">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="empty">
          <i class="fa-regular fa-image"></i><span>Trống ảnh</span><span class="count" data-count="empty">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="fbsbx">
          <i class="fa-brands fa-facebook"></i><span>Facebook fbsbx</span><span class="count" data-count="fbsbx">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="google_temp">
          <i class="fa-solid fa-map-location-dot"></i><span>Link Maps tạm</span><span class="count" data-count="google_temp">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="logo_banner">
          <i class="fa-solid fa-bullhorn"></i><span>Logo / Banner</span><span class="count" data-count="logo_banner">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="suspicious_2026">
          <i class="fa-solid fa-link-slash"></i><span>Link /2026/</span><span class="count" data-count="suspicious_2026">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="missing_local">
          <i class="fa-solid fa-file-circle-xmark"></i><span>Mất file nội bộ</span><span class="count" data-count="missing_local">0</span>
        </button>
        <button type="button" class="audit-pill" data-issue="all">
          <i class="fa-solid fa-layer-group"></i><span>Tất cả hồ sơ</span><span class="count" data-count="all">0</span>
        </button>
      </div>

      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopyFilteredIds">
            <i class="fa-regular fa-copy me-1"></i>Copy danh sách ID đang lọc (<span id="copyFilteredCount">0</span>)
          </button>
          <button type="button" class="btn btn-outline-danger btn-sm" id="btnCopyBrokenPageIds">
            <i class="fa-solid fa-bug me-1"></i>Copy ID ảnh vỡ trên trang (<span id="copyBrokenPageCount">0</span>)
          </button>
        </div>
        <div class="small text-secondary">
          Mỗi hồ sơ hiển thị trạng thái <strong>Đã quét / Chưa quét</strong> lưu trực tiếp từ Database.
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-soft">
    <div class="card-body p-0">
      <div class="table-responsive border-0">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th style="width:76px;">ID</th>
              <th style="width:260px;">Hồ sơ</th>
              <th style="width:255px;">Trạng thái Quét DB &amp; Nhãn lỗi</th>
              <th>Xem trước Ảnh Bìa &amp; Gallery (Nạp điều tiết 2 ảnh/lượt)</th>
              <th style="width:170px;" class="text-end">Thao tác</th>
            </tr>
          </thead>
          <tbody id="auditTableBody">
            <tr>
              <td colspan="5" class="text-center text-secondary py-5">Đang tải danh sách rà soát ảnh...</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top" id="auditPager"></div>
    </div>
  </div>
</section>

<script>
(() => {
  const endpoint = <?php echo json_encode(admin_url('api/medical/image_audit.php'), JSON_UNESCAPED_SLASHES); ?>;
  const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;

  const MAX_CONCURRENT_THUMBS = 2;
  const THUMB_DELAY_MS = 120;
  const FULL_SCAN_BATCH_SIZE = 2;
  const FULL_SCAN_DELAY_MS = 550;

  const state = {
    entityType: 'facility',
    language: 'vi',
    status: 'published',
    issue: 'any_issue',
    q: '',
    page: 1,
    limit: 12,
    matchedIds: [],
    brokenIdsOnPage: new Set(),
    thumbQueue: [],
    activeThumbs: 0,
    generation: 0,
    fullScanning: false,
    stopFullScanRequested: false,
  };

  const el = {
    form: document.getElementById('auditFilterForm'),
    entityType: document.getElementById('auditEntityType'),
    language: document.getElementById('auditLanguage'),
    status: document.getElementById('auditStatus'),
    limit: document.getElementById('auditLimit'),
    search: document.getElementById('auditSearch'),
    pills: document.getElementById('auditIssuePills'),
    tbody: document.getElementById('auditTableBody'),
    pager: document.getElementById('auditPager'),
    queueBadge: document.getElementById('auditQueueBadge'),
    queueText: document.getElementById('auditQueueText'),
    schemaWarningBox: document.getElementById('schemaWarningBox'),
    btnMigrateSchema: document.getElementById('btnMigrateSchema'),
    btnStartFullScan: document.getElementById('btnStartFullScan'),
    btnUnscannedCount: document.getElementById('btnUnscannedCount'),
    btnPauseFullScan: document.getElementById('btnPauseFullScan'),
    btnResetAllScans: document.getElementById('btnResetAllScans'),
    fullScanStatusLine: document.getElementById('fullScanStatusLine'),
    fullScanProgressBar: document.getElementById('fullScanProgressBar'),
    statScannedTotal: document.getElementById('statScannedTotal'),
    statUnscanned: document.getElementById('statUnscanned'),
    statScannedBroken: document.getElementById('statScannedBroken'),
    statScannedWarning: document.getElementById('statScannedWarning'),
    statScannedOk: document.getElementById('statScannedOk'),
    btnCopyFiltered: document.getElementById('btnCopyFilteredIds'),
    copyFilteredCount: document.getElementById('copyFilteredCount'),
    btnCopyBrokenPage: document.getElementById('btnCopyBrokenPageIds'),
    copyBrokenPageCount: document.getElementById('copyBrokenPageCount'),
  };

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[ch]));
  }

  function formatNumber(n) {
    return new Intl.NumberFormat('vi-VN').format(Number(n || 0));
  }

  function updateQueueStatus(text, isBusy = false) {
    el.queueText.textContent = text;
    el.queueBadge.classList.toggle('is-scanning', isBusy);
  }

  function updateBrokenCounter() {
    el.copyBrokenPageCount.textContent = String(state.brokenIdsOnPage.size);
  }

  function updateProgressWidgets(progress) {
    const total = Number(progress?.total ?? 0);
    const scanned = Number(progress?.scanned ?? 0);
    const unscanned = Number(progress?.unscanned ?? Math.max(0, total - scanned));
    const broken = Number(progress?.broken ?? 0);
    const warning = Number(progress?.warning ?? 0);
    const ok = Number(progress?.ok ?? 0);
    const pct = total > 0 ? Math.min(100, Math.round((scanned / total) * 100)) : 0;

    el.statScannedTotal.textContent = `${formatNumber(scanned)} / ${formatNumber(total)} (${pct}%)`;
    el.statUnscanned.textContent = formatNumber(unscanned);
    el.statScannedBroken.textContent = formatNumber(broken);
    el.statScannedWarning.textContent = formatNumber(warning);
    el.statScannedOk.textContent = formatNumber(ok);
    el.btnUnscannedCount.textContent = formatNumber(unscanned);
    el.fullScanProgressBar.style.width = `${pct}%`;
  }

  function markRowBrokenOnPage(entityId, reasonText) {
    state.brokenIdsOnPage.add(Number(entityId));
    updateBrokenCounter();
    const row = el.tbody.querySelector(`tr[data-entity-id="${entityId}"]`);
    if (!row) return;
    row.classList.add('is-browser-broken');
    const issueBox = row.querySelector('.audit-issue-list');
    if (issueBox && !issueBox.querySelector('[data-browser-error="1"]')) {
      const tag = document.createElement('span');
      tag.className = 'audit-issue-tag danger';
      tag.dataset.browserError = '1';
      tag.innerHTML = `<i class="fa-solid fa-bug"></i><span>${escapeHtml(reasonText || 'Trình duyệt báo lỗi tải ảnh')}</span>`;
      issueBox.prepend(tag);
    }
  }

  function pumpThumbQueue(gen) {
    if (gen !== state.generation) return;
    while (state.activeThumbs < MAX_CONCURRENT_THUMBS && state.thumbQueue.length > 0) {
      const task = state.thumbQueue.shift();
      state.activeThumbs++;
      if (!state.fullScanning) {
        updateQueueStatus(`Đang nạp ảnh trang này (${state.thumbQueue.length} chờ)...`, true);
      }

      const { card, imgUrl, entityId } = task;
      const frame = card.querySelector('.audit-thumb-frame');
      const statusNode = card.querySelector('.audit-thumb-status');

      const img = new Image();
      img.decoding = 'async';
      img.referrerPolicy = 'no-referrer';

      const finish = () => {
        window.setTimeout(() => {
          if (gen !== state.generation) return;
          state.activeThumbs = Math.max(0, state.activeThumbs - 1);
          if (state.thumbQueue.length === 0 && state.activeThumbs === 0 && !state.fullScanning) {
            updateQueueStatus('Trạng thái: Sẵn sàng', false);
          } else {
            pumpThumbQueue(gen);
          }
        }, THUMB_DELAY_MS);
      };

      img.onload = () => {
        if (gen !== state.generation) return;
        const w = img.naturalWidth || 0;
        const h = img.naturalHeight || 0;
        const placeholder = frame.querySelector('.thumb-placeholder');
        if (placeholder) placeholder.remove();
        frame.prepend(img);

        if (w < 120 || h < 90) {
          card.classList.remove('state-ok');
          card.classList.add('state-warning');
          statusNode.textContent = `Ảnh nhỏ (${w}×${h}px)`;
        } else if (!card.classList.contains('state-broken') && !card.classList.contains('state-warning')) {
          card.classList.add('state-ok');
          statusNode.textContent = `Hiển thị OK (${w}×${h}px)`;
        } else {
          statusNode.textContent = `${statusNode.textContent} · (${w}×${h}px)`;
        }
        finish();
      };

      img.onerror = () => {
        if (gen !== state.generation) return;
        card.classList.remove('state-ok', 'state-warning');
        card.classList.add('state-broken');
        statusNode.textContent = 'Vỡ ảnh trên trình duyệt (404/403/HTML)';
        const placeholder = frame.querySelector('.thumb-placeholder');
        if (placeholder) {
          placeholder.innerHTML = '<i class="fa-solid fa-image-slash text-danger d-block mb-1 fs-5"></i>Lỗi hiển thị';
        }
        markRowBrokenOnPage(entityId, 'Ảnh vỡ khi hiển thị');
        finish();
      };

      img.src = imgUrl;
    }
  }

  function renderSummaryPills(summary) {
    if (!summary || typeof summary !== 'object') return;
    el.pills.querySelectorAll('.audit-pill').forEach((btn) => {
      const key = btn.dataset.issue;
      btn.classList.toggle('active', key === state.issue);
      const badge = btn.querySelector('[data-count]');
      if (badge && summary[key] !== undefined) {
        badge.textContent = formatNumber(summary[key]);
      }
    });
    const total = Number(summary.all || 0);
    const unscanned = Number(summary.unscanned || 0);
    updateProgressWidgets({
      total,
      scanned: Math.max(0, total - unscanned),
      unscanned,
      broken: Number(summary.scanned_broken || 0),
      warning: Number(summary.scanned_warning || 0),
      ok: Number(summary.scanned_ok || 0),
    });
  }

  function renderDbScanBadge(item) {
    const st = String(item.image_scan_status || 'unscanned');
    const at = item.image_scanned_at ? ` · ${escapeHtml(item.image_scanned_at)}` : '';
    if (st === 'broken') {
      return `<span class="db-scan-badge broken"><i class="fa-solid fa-circle-xmark"></i>Đã quét: Lỗi${at}</span>`;
    }
    if (st === 'warning') {
      return `<span class="db-scan-badge warning"><i class="fa-solid fa-circle-exclamation"></i>Đã quét: Cảnh báo${at}</span>`;
    }
    if (st === 'ok') {
      return `<span class="db-scan-badge ok"><i class="fa-solid fa-circle-check"></i>Đã quét: Tốt${at}</span>`;
    }
    return `<span class="db-scan-badge unscanned"><i class="fa-regular fa-clock"></i>Chưa quét DB</span>`;
  }

  function renderPager(paging) {
    const total = Number(paging?.total || 0);
    const current = Number(paging?.page || 1);
    const totalPages = Number(paging?.total_pages || 1);
    const limit = Number(paging?.limit || state.limit);

    if (total === 0) {
      el.pager.innerHTML = '<span class="small text-secondary">Không có hồ sơ nào trong bộ lọc này.</span>';
      return;
    }

    const from = (current - 1) * limit + 1;
    const to = Math.min(current * limit, total);
    el.pager.innerHTML = `
      <span class="small text-secondary">Hiển thị <strong>${formatNumber(from)}–${formatNumber(to)}</strong> / <strong>${formatNumber(total)}</strong> hồ sơ</span>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>
          <i class="fa-solid fa-chevron-left me-1"></i>Trước
        </button>
        <span class="small text-secondary">Trang <strong>${current}</strong> / ${formatNumber(totalPages)}</span>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-page="${current + 1}" ${current >= totalPages ? 'disabled' : ''}>
          Sau<i class="fa-solid fa-chevron-right ms-1"></i>
        </button>
      </div>
    `;
    el.pager.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => loadPage(Number(btn.dataset.page || 1)));
    });
  }

  function renderRows(items, gen) {
    state.thumbQueue = [];
    state.activeThumbs = 0;
    state.brokenIdsOnPage = new Set();
    updateBrokenCounter();

    if (!Array.isArray(items) || items.length === 0) {
      el.tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-5">Không tìm thấy hồ sơ nào khớp điều kiện lọc.</td></tr>';
      return;
    }

    el.tbody.innerHTML = items.map((item) => {
      const entityId = Number(item.id || 0);
      const rowIssues = Array.isArray(item.row_issues) ? item.row_issues : [];
      const imageIssuesFlat = [];
      (item.images || []).forEach((img) => {
        (img.issues || []).forEach((iss) => {
          if (!imageIssuesFlat.some((x) => x.label === iss.label)) {
            imageIssuesFlat.push(iss);
          }
          if (iss.severity === 'danger') {
            state.brokenIdsOnPage.add(entityId);
          }
        });
      });
      if (rowIssues.some((r) => r.severity === 'danger') || item.image_scan_status === 'broken') {
        state.brokenIdsOnPage.add(entityId);
      }

      const allTags = [...rowIssues, ...imageIssuesFlat];
      const tagsHtml = allTags.length
        ? allTags.map((t) => `<span class="audit-issue-tag ${escapeHtml(t.severity || 'warning')}"><i class="fa-solid fa-circle-exclamation"></i><span>${escapeHtml(t.label)}</span></span>`).join('')
        : '<span class="audit-issue-tag info"><i class="fa-solid fa-check"></i><span>Không có cảnh báo tĩnh</span></span>';

      const images = Array.isArray(item.images) ? item.images : [];
      const thumbsHtml = images.length === 0
        ? '<div class="text-danger small fw-semibold"><i class="fa-regular fa-image me-1"></i>Hồ sơ này hiện có 0 ảnh.</div>'
        : `<div class="audit-thumb-grid">${images.map((img, idx) => {
            const isCover = img.url === item.image_url;
            const hasDanger = (img.issues || []).some((i) => i.severity === 'danger');
            const hasWarn = !hasDanger && (img.issues || []).some((i) => i.severity === 'warning');
            const stateClass = hasDanger ? 'state-broken' : (hasWarn ? 'state-warning' : '');
            const firstIssue = (img.issues && img.issues[0]) ? img.issues[0].label : 'Đang chờ nạp...';
            return `
              <div class="audit-thumb-card ${isCover ? 'is-cover' : ''} ${stateClass}"
                   data-entity-id="${entityId}"
                   data-image-url="${escapeHtml(img.url)}">
                <div class="audit-thumb-frame">
                  <span class="audit-thumb-badge ${isCover ? 'cover' : ''}">${isCover ? 'Ảnh bìa' : ('#' + (idx + 1))}</span>
                  <div class="thumb-placeholder">Đang xếp hàng...</div>
                </div>
                <div class="audit-thumb-meta">
                  <div class="audit-thumb-status" title="${escapeHtml(firstIssue)}">${escapeHtml(firstIssue)}</div>
                  <div class="text-secondary text-truncate" title="${escapeHtml(img.url)}">${escapeHtml(img.is_local ? 'Nội bộ (/uploads)' : img.url)}</div>
                  <div class="audit-thumb-actions">
                    <a class="btn btn-light" href="${escapeHtml(img.url)}" target="_blank" rel="noopener" title="Mở URL ảnh gốc"><i class="fa-solid fa-up-right-from-square"></i></a>
                    ${!isCover ? `<button type="button" class="btn btn-outline-primary" data-action="set-cover" data-id="${entityId}" data-url="${escapeHtml(img.url)}" title="Đặt làm ảnh đại diện">Đặt bìa</button>` : ''}
                    <button type="button" class="btn btn-outline-danger" data-action="remove-image" data-id="${entityId}" data-url="${escapeHtml(img.url)}" title="Gỡ URL ảnh hỏng này khỏi hồ sơ"><i class="fa-solid fa-trash-can"></i></button>
                  </div>
                </div>
              </div>
            `;
          }).join('')}</div>`;

      return `
        <tr class="audit-row ${state.brokenIdsOnPage.has(entityId) ? 'is-browser-broken' : ''}" data-entity-id="${entityId}">
          <td class="mono fw-bold">#${entityId}</td>
          <td>
            <div class="fw-semibold">${escapeHtml(item.name)}</div>
            <div class="small text-secondary">${escapeHtml(item.subtitle || '')}${item.city ? ' · ' + escapeHtml(item.city) : ''}</div>
            <div class="small text-secondary mono text-truncate" style="max-width:240px;" title="${escapeHtml(item.slug)}">${escapeHtml(item.slug)}</div>
          </td>
          <td>
            <div class="mb-1">${renderDbScanBadge(item)}</div>
            <div class="small fw-semibold">${formatNumber(item.images_count)} ảnh (${formatNumber(item.gallery_count)} gallery)</div>
            <div class="audit-issue-list">${tagsHtml}</div>
          </td>
          <td>${thumbsHtml}</td>
          <td class="text-end">
            <div class="d-flex flex-column gap-1 align-items-end">
              <div class="d-flex gap-1">
                <a class="btn btn-sm btn-outline-secondary" href="${escapeHtml(item.public_url)}" target="_blank">Xem</a>
                <a class="btn btn-sm btn-outline-primary" href="${escapeHtml(item.edit_url)}">Sửa</a>
              </div>
              ${state.entityType === 'facility' ? `
                <button type="button" class="btn btn-sm btn-light text-primary" data-action="reset-fix-queue" data-id="${entityId}" title="Đưa cơ sở này lên đầu hàng đợi API Fix ảnh">
                  <i class="fa-solid fa-rotate-left me-1"></i>Đẩy vào Queue Fix
                </button>
              ` : ''}
            </div>
          </td>
        </tr>
      `;
    }).join('');

    updateBrokenCounter();

    el.tbody.querySelectorAll('.audit-thumb-card').forEach((card) => {
      const imgUrl = card.dataset.imageUrl || '';
      const entityId = Number(card.dataset.entityId || 0);
      if (imgUrl) {
        state.thumbQueue.push({ card, imgUrl, entityId });
      }
    });

    if (state.thumbQueue.length > 0) {
      pumpThumbQueue(gen);
    }
  }

  async function loadPage(nextPage = 1) {
    state.generation++;
    const gen = state.generation;

    state.page = Math.max(1, Number(nextPage || 1));
    state.entityType = el.entityType.value;
    state.language = el.language.value;
    state.status = el.status.value;
    state.limit = Number(el.limit.value || 12);
    state.q = el.search.value.trim();

    try {
      const params = new URLSearchParams({
        entity_type: state.entityType,
        language: state.language,
        status: state.status,
        issue: state.issue,
        limit: String(state.limit),
        page: String(state.page),
      });
      if (state.q) params.set('q', state.q);

      const response = await fetch(`${endpoint}?${params.toString()}`, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Không thể tải dữ liệu rà soát ảnh.');
      }

      if (gen !== state.generation) return;
      el.schemaWarningBox.classList.toggle('d-none', Boolean(data.schema_ready));
      el.schemaWarningBox.classList.toggle('d-flex', !data.schema_ready);

      state.matchedIds = Array.isArray(data.matched_ids) ? data.matched_ids : [];
      el.copyFilteredCount.textContent = formatNumber(state.matchedIds.length);
      renderSummaryPills(data.summary || {});
      renderRows(data.items || [], gen);
      renderPager(data.paging || {});
    } catch (err) {
      if (gen !== state.generation) return;
      el.tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-5">${escapeHtml(err.message || 'Lỗi tải dữ liệu.')}</td></tr>`;
    }
  }

  // Continuous Full-System Auto-Scanner: scans unscanned records in small batches across the entire DB.
  async function startFullSystemScan() {
    if (state.fullScanning) return;
    state.fullScanning = true;
    state.stopFullScanRequested = false;
    el.btnStartFullScan.disabled = true;
    el.btnPauseFullScan.classList.remove('d-none');

    let batchesRun = 0;
    try {
      while (!state.stopFullScanRequested) {
        updateQueueStatus('Đang quét tự động toàn hệ thống (2 hồ sơ/nhịp)...', true);
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrfToken,
          },
          body: JSON.stringify({
            action: 'scan_next_batch',
            entity_type: el.entityType.value,
            language: el.language.value,
            status: el.status.value,
            batch_size: FULL_SCAN_BATCH_SIZE,
            only_unscanned: true,
          }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
          throw new Error(data.message || 'Lỗi khi quét lô tiếp theo.');
        }

        el.schemaWarningBox.classList.add('d-none');
        el.schemaWarningBox.classList.remove('d-flex');

        if (data.progress) {
          updateProgressWidgets(data.progress);
        }
        const items = Array.isArray(data.processed) ? data.processed : [];
        if (items.length > 0) {
          const last = items[items.length - 1];
          el.fullScanStatusLine.innerHTML = `Vừa quét xong <strong>#${escapeHtml(last.id)} ${escapeHtml(last.name)}</strong> → Trạng thái: <code>${escapeHtml(last.status)}</code> (${formatNumber(last.broken_count)} lỗi, ${formatNumber(last.warning_count)} cảnh báo, ${formatNumber(last.ok_count)} tốt).`;
        }

        batchesRun++;
        // Refresh table every 4 batches so user sees live DB badges updating.
        if (batchesRun % 4 === 0) {
          loadPage(state.page);
        }

        if (data.done || Number(data.progress?.unscanned || 0) === 0) {
          el.fullScanStatusLine.innerHTML = `<strong class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Đã quét hoàn tất 100% hồ sơ trong phạm vi đã chọn!</strong>`;
          if (window.adminToast) window.adminToast('success', 'Đã quét xong toàn bộ hồ sơ!');
          break;
        }

        await new Promise((r) => window.setTimeout(r, FULL_SCAN_DELAY_MS));
      }
      if (state.stopFullScanRequested) {
        el.fullScanStatusLine.innerHTML = '<span class="text-warning fw-semibold">Đã tạm dừng quét. Tiến độ đã được lưu trong DB, bạn có thể bấm Tiếp tục bất cứ lúc nào.</span>';
      }
    } catch (err) {
      el.fullScanStatusLine.innerHTML = `<span class="text-danger fw-semibold">${escapeHtml(err.message || 'Quét bị gián đoạn.')}</span>`;
      if (window.adminToast) window.adminToast('danger', err.message || 'Lỗi khi quét.');
    } finally {
      state.fullScanning = false;
      el.btnStartFullScan.disabled = false;
      el.btnPauseFullScan.classList.add('d-none');
      updateQueueStatus('Trạng thái: Sẵn sàng', false);
      loadPage(state.page);
    }
  }

  async function migrateSchemaNow() {
    el.btnMigrateSchema.disabled = true;
    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ action: 'migrate_schema' }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.message || 'Không thể tạo cột DB.');
      if (window.adminToast) window.adminToast('success', data.message);
      loadPage(state.page);
    } catch (err) {
      if (window.adminToast) window.adminToast('danger', err.message || 'Lỗi migrate DB.');
    } finally {
      el.btnMigrateSchema.disabled = false;
    }
  }

  async function resetAllScansNow() {
    if (!window.confirm('Đặt lại toàn bộ hồ sơ về trạng thái "Chưa quét" để quét lại từ đầu?')) return;
    el.btnResetAllScans.disabled = true;
    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ action: 'reset_all_scans', entity_type: el.entityType.value }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.message || 'Không thể đặt lại trạng thái.');
      if (window.adminToast) window.adminToast('success', data.message);
      loadPage(1);
    } catch (err) {
      if (window.adminToast) window.adminToast('danger', err.message || 'Lỗi khi đặt lại.');
    } finally {
      el.btnResetAllScans.disabled = false;
    }
  }

  async function handleCardAction(button) {
    const action = button.dataset.action;
    const id = Number(button.dataset.id || 0);
    const url = button.dataset.url || '';
    if (!id) return;

    if (action === 'remove-image') {
      if (!window.confirm(`Gỡ URL ảnh này khỏi hồ sơ #${id}?\n${url}`)) return;
      button.disabled = true;
      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ action: 'remove_image', entity_type: state.entityType, id, url }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.message || 'Không thể gỡ ảnh.');
        if (window.adminToast) window.adminToast('success', data.message || 'Đã gỡ ảnh lỗi.');
        loadPage(state.page);
      } catch (err) {
        button.disabled = false;
        if (window.adminToast) window.adminToast('danger', err.message || 'Lỗi khi gỡ ảnh.');
      }
      return;
    }

    if (action === 'set-cover') {
      button.disabled = true;
      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ action: 'set_cover', entity_type: state.entityType, id, url }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.message || 'Không thể đặt ảnh bìa.');
        if (window.adminToast) window.adminToast('success', data.message || 'Đã cập nhật ảnh bìa.');
        loadPage(state.page);
      } catch (err) {
        button.disabled = false;
        if (window.adminToast) window.adminToast('danger', err.message || 'Lỗi cập nhật ảnh bìa.');
      }
      return;
    }

    if (action === 'reset-fix-queue') {
      button.disabled = true;
      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ action: 'reset_fix_queue', entity_type: state.entityType, id }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.message || 'Không thể cập nhật hàng đợi.');
        if (window.adminToast) window.adminToast('success', data.message || 'Đã đưa vào hàng đợi Fix ảnh.');
      } catch (err) {
        if (window.adminToast) window.adminToast('danger', err.message || 'Lỗi cập nhật hàng đợi.');
      } finally {
        button.disabled = false;
      }
    }
  }

  async function copyText(text, label) {
    if (!text) {
      if (window.adminToast) window.adminToast('warning', 'Danh sách ID đang trống.');
      return;
    }
    try {
      await navigator.clipboard.writeText(text);
    } catch (_) {
      const area = document.createElement('textarea');
      area.value = text;
      area.style.cssText = 'position:fixed;left:-9999px';
      document.body.appendChild(area);
      area.select();
      document.execCommand('copy');
      area.remove();
    }
    if (window.adminToast) window.adminToast('success', `Đã copy ${label}: ${text.slice(0, 60)}${text.length > 60 ? '...' : ''}`);
  }

  el.form.addEventListener('submit', (e) => {
    e.preventDefault();
    loadPage(1);
  });
  [el.entityType, el.language, el.status, el.limit].forEach((select) => {
    select.addEventListener('change', () => loadPage(1));
  });
  el.pills.querySelectorAll('.audit-pill').forEach((btn) => {
    btn.addEventListener('click', () => {
      state.issue = btn.dataset.issue || 'any_issue';
      loadPage(1);
    });
  });
  el.btnStartFullScan.addEventListener('click', startFullSystemScan);
  el.btnPauseFullScan.addEventListener('click', () => {
    state.stopFullScanRequested = true;
  });
  el.btnResetAllScans.addEventListener('click', resetAllScansNow);
  if (el.btnMigrateSchema) {
    el.btnMigrateSchema.addEventListener('click', migrateSchemaNow);
  }
  el.btnCopyFiltered.addEventListener('click', () => {
    copyText(state.matchedIds.join(','), `${state.matchedIds.length} ID đang lọc`);
  });
  el.btnCopyBrokenPage.addEventListener('click', () => {
    const ids = Array.from(state.brokenIdsOnPage);
    copyText(ids.join(','), `${ids.length} ID lỗi trên trang`);
  });
  el.tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (btn) handleCardAction(btn);
  });

  const initialUrlParams = new URLSearchParams(window.location.search);
  if (['facility', 'doctor'].includes(initialUrlParams.get('entity_type') || '')) {
    el.entityType.value = initialUrlParams.get('entity_type');
  }
  if (['vi', 'en', 'all'].includes(initialUrlParams.get('language') || '')) {
    el.language.value = initialUrlParams.get('language');
  }
  if (['published', 'draft', 'all'].includes(initialUrlParams.get('status') || '')) {
    el.status.value = initialUrlParams.get('status');
  }
  if (initialUrlParams.get('issue')) {
    state.issue = initialUrlParams.get('issue');
  }
  if (initialUrlParams.get('q')) {
    el.search.value = initialUrlParams.get('q');
  }
  const initialPage = Math.max(1, Number(initialUrlParams.get('page') || 1));

  loadPage(initialPage);
})();
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
