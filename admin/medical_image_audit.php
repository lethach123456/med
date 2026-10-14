<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$adminPageTitle = 'Admin • Rà soát ảnh hỏng tự động';
$adminHeaderTitle = 'Rà soát ảnh hỏng tự động';
$adminHeaderSubtitle = 'Quét 64 KB đầu của ảnh cho các Cơ sở y tế & Bác sĩ đã có bài viết và lưu vết vào Database';
$adminActive = 'medical-image-audit';
$csrfToken = admin_csrf_token();

require __DIR__ . '/_layout_start.php';
?>

<style>
  .scan-hero { padding:1.25rem 1.4rem; border:1px solid #d7e5ff; border-radius:1.15rem; background:linear-gradient(118deg,#f7fbff 0%,#fff 55%,#eef5ff 100%); }
  .scan-hero h1 { margin:0 0 .35rem; color:#16233a; font-size:1.3rem; font-weight:800; letter-spacing:-.02em; }
  .scan-hero p { margin:0; color:#5a6b85; font-size:.87rem; line-height:1.55; max-width:820px; }
  .scan-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.8rem; }
  .scan-stat-card { padding:.9rem 1.05rem; border:1px solid #e2eaf4; border-radius:.85rem; background:#fff; }
  .scan-stat-card .num { font-size:1.38rem; font-weight:800; color:#16233a; line-height:1.1; }
  .scan-stat-card .lbl { font-size:.76rem; font-weight:700; color:#697b96; margin-top:.22rem; }
  .scan-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.26rem .62rem; border-radius:999px; font-size:.73rem; font-weight:750; }
  .scan-badge.ok { color:#15803d; background:#dcfce7; }
  .scan-badge.broken { color:#b42318; background:#fee4e2; }
  .scan-badge.warning { color:#9a5b00; background:#fef3c7; }
  .scan-badge.unscanned { color:#52627a; background:#eef2f8; }
  .error-report-list { display:flex; flex-direction:column; gap:.32rem; }
  .error-report-item { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.28rem .52rem; border-radius:.45rem; font-size:.75rem; }
  .error-report-item.danger { color:#b42318; background:#fff1f0; border:1px solid #fecdca; }
  .error-report-item.warning { color:#9a5b00; background:#fffbeb; border:1px solid #fde68a; }
  .error-report-url { max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#52627a; font-size:.7rem; }
  @media (max-width:991.98px) { .scan-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:575.98px) { .scan-stats { grid-template-columns:1fr; } }
</style>

<div class="row g-3">
  <div class="col-12">
    <div class="scan-hero shadow-soft">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <h1>Rà soát ảnh hỏng tự động (Đánh dấu Đã quét / Chưa quét trong DB)</h1>
          <p>Bấm <strong>“Quét toàn bộ chưa quét”</strong> một lần để hệ thống tự động duyệt hết toàn bộ Cơ sở y tế / Bác sĩ đã có nội dung bài viết. Mỗi nhịp chỉ tải thử tối đa <strong>64 KB đầu của ảnh</strong> (nhẹ hơn 30 lần tải nguyên tấm, không giải nén GD) và lưu trực tiếp trạng thái <code>image_scan_status</code>, <code>image_scanned_at</code> vào Database.</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
          <div class="btn-group" role="group" aria-label="Chọn đối tượng">
            <button type="button" class="btn btn-outline-primary active" data-entity="facility">
              <i class="fa-solid fa-hospital me-1"></i>Cơ sở y tế
            </button>
            <button type="button" class="btn btn-outline-primary" data-entity="doctor">
              <i class="fa-solid fa-user-doctor me-1"></i>Bác sĩ
            </button>
          </div>

          <button type="button" class="btn btn-primary" id="btnStartScan">
            <i class="fa-solid fa-play me-1"></i>Quét toàn bộ chưa quét (<span id="btnUnscannedCount">0</span>)
          </button>
          <button type="button" class="btn btn-outline-warning d-none" id="btnPauseScan">
            <i class="fa-solid fa-pause me-1"></i>Tạm dừng
          </button>
          <button type="button" class="btn btn-outline-secondary" id="btnResetScan">
            <i class="fa-solid fa-rotate-left me-1"></i>Đặt lại Chưa quét
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <div class="small text-secondary" id="scanStatusText">
            Chỉ tính và quét các hồ sơ đã có nội dung bài viết.
          </div>
          <div class="small fw-semibold text-primary" id="scanPctText">0% hoàn tất</div>
        </div>

        <div class="progress mb-3" style="height:10px;border-radius:999px;background:#e9eff8;">
          <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" id="scanProgressBar" role="progressbar" style="width:0%"></div>
        </div>

        <div class="scan-stats mb-4">
          <div class="scan-stat-card">
            <div class="num" id="statTotal">0</div>
            <div class="lbl">Hồ sơ đã có bài viết</div>
          </div>
          <div class="scan-stat-card">
            <div class="num text-primary" id="statScanned">0</div>
            <div class="lbl">Đã quét (Lưu trong DB)</div>
          </div>
          <div class="scan-stat-card">
            <div class="num text-secondary" id="statUnscanned">0</div>
            <div class="lbl">Chưa quét</div>
          </div>
          <div class="scan-stat-card">
            <div class="num text-danger" id="statBroken">0</div>
            <div class="lbl">Đã quét · Phát hiện lỗi ảnh</div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th style="width:72px;">ID</th>
                <th style="width:260px;">Hồ sơ</th>
                <th style="width:95px;">Số ảnh</th>
                <th style="width:180px;">Trạng thái DB</th>
                <th>Báo cáo lỗi ảnh (Chi tiết quét 64 KB &amp; đường dẫn)</th>
                <th style="width:155px;" class="text-end">Thao tác</th>
              </tr>
            </thead>
            <tbody id="scanTableBody">
              <tr>
                <td colspan="6" class="text-center text-secondary py-4">Đang tải danh sách...</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3" id="scanPager"></div>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const endpoint = <?php echo json_encode(admin_url('api/medical/image_audit.php'), JSON_UNESCAPED_SLASHES); ?>;
  const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;

  const state = {
    entityType: 'facility',
    page: 1,
    scanning: false,
    stopRequested: false,
  };

  const el = {
    entityBtns: document.querySelectorAll('button[data-entity]'),
    btnStart: document.getElementById('btnStartScan'),
    btnUnscannedCount: document.getElementById('btnUnscannedCount'),
    btnPause: document.getElementById('btnPauseScan'),
    btnReset: document.getElementById('btnResetScan'),
    statusText: document.getElementById('scanStatusText'),
    pctText: document.getElementById('scanPctText'),
    progressBar: document.getElementById('scanProgressBar'),
    statTotal: document.getElementById('statTotal'),
    statScanned: document.getElementById('statScanned'),
    statUnscanned: document.getElementById('statUnscanned'),
    statBroken: document.getElementById('statBroken'),
    tbody: document.getElementById('scanTableBody'),
    pager: document.getElementById('scanPager'),
  };

  function escapeHtml(val) {
    return String(val ?? '').replace(/[&<>'"]/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[c]));
  }

  function fmt(n) {
    return new Intl.NumberFormat('vi-VN').format(Number(n || 0));
  }

  function updateSummary(summary) {
    const total = Number(summary?.total || 0);
    const scanned = Number(summary?.scanned || 0);
    const unscanned = Number(summary?.unscanned || 0);
    const broken = Number(summary?.broken || 0);
    const pct = total > 0 ? Math.min(100, Math.round((scanned / total) * 100)) : 0;

    el.statTotal.textContent = fmt(total);
    el.statScanned.textContent = fmt(scanned);
    el.statUnscanned.textContent = fmt(unscanned);
    el.statBroken.textContent = fmt(broken);
    el.btnUnscannedCount.textContent = fmt(unscanned);
    el.pctText.textContent = `${pct}% hoàn tất (${fmt(scanned)}/${fmt(total)})`;
    el.progressBar.style.width = `${pct}%`;
  }

  function renderDbBadge(row) {
    const st = String(row.image_scan_status || 'unscanned');
    const timeHtml = row.image_scanned_at
      ? `<div class="small text-secondary mono mt-1">${escapeHtml(row.image_scanned_at)}</div>`
      : '';
    if (!row.is_scanned || st === 'unscanned') {
      return `<span class="scan-badge unscanned"><i class="fa-regular fa-clock"></i>Chưa quét</span>`;
    }
    if (st === 'broken') {
      return `<span class="scan-badge broken"><i class="fa-solid fa-circle-xmark"></i>Đã quét · Lỗi</span>${timeHtml}`;
    }
    if (st === 'warning') {
      return `<span class="scan-badge warning"><i class="fa-solid fa-triangle-exclamation"></i>Đã quét · Cảnh báo</span>${timeHtml}`;
    }
    return `<span class="scan-badge ok"><i class="fa-solid fa-circle-check"></i>Đã quét · Tốt</span>${timeHtml}`;
  }

  function renderErrorReport(row) {
    const errors = Array.isArray(row.errors) ? row.errors : [];
    if (errors.length === 0) {
      if (row.is_scanned) {
        return `<span class="text-success small fw-semibold"><i class="fa-solid fa-check me-1"></i>Tất cả ${fmt(row.images_count)} ảnh hợp lệ (64KB OK)</span>`;
      }
      return `<span class="text-secondary small">Chưa phát hiện lỗi tĩnh · Chờ quét 64 KB</span>`;
    }

    return `<div class="error-report-list">${errors.map((err) => {
      const sev = err.severity === 'danger' ? 'danger' : 'warning';
      const url = String(err.url || '');
      return `
        <div class="error-report-item ${sev}">
          <div class="d-flex align-items-center gap-2 min-w-0">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span class="fw-semibold">${escapeHtml(err.reason)}</span>
            ${url ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="error-report-url mono" title="${escapeHtml(url)}">${escapeHtml(url)}</a>` : ''}
          </div>
          ${url ? `
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" style="min-height:22px;font-size:.68rem;"
                    data-remove-id="${Number(row.id || 0)}" data-remove-url="${escapeHtml(url)}" title="Gỡ URL ảnh lỗi này khỏi hồ sơ">
              Gỡ ảnh
            </button>
          ` : ''}
        </div>
      `;
    }).join('')}</div>`;
  }

  function renderRows(items) {
    if (!Array.isArray(items) || items.length === 0) {
      el.tbody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-4">Chưa có hồ sơ nào đã viết bài.</td></tr>';
      return;
    }

    el.tbody.innerHTML = items.map((row) => `
      <tr>
        <td class="mono fw-semibold">#${Number(row.id || 0)}</td>
        <td>
          <div class="fw-semibold">${escapeHtml(row.name)}</div>
          <div class="small text-secondary">${escapeHtml(row.subtitle || '')}${row.city ? ' · ' + escapeHtml(row.city) : ''}</div>
        </td>
        <td><span class="badge text-bg-light border">${fmt(row.images_count)} ảnh</span></td>
        <td>${renderDbBadge(row)}</td>
        <td>${renderErrorReport(row)}</td>
        <td class="text-end">
          <div class="d-flex justify-content-end gap-1">
            ${row.is_scanned ? `
              <button type="button" class="btn btn-sm btn-light" data-rescan-id="${Number(row.id || 0)}" title="Đặt lại Chưa quét cho dòng này">
                <i class="fa-solid fa-rotate-left"></i>
              </button>
            ` : ''}
            <a class="btn btn-sm btn-outline-secondary" href="${escapeHtml(row.public_url)}" target="_blank">Xem</a>
            <a class="btn btn-sm btn-outline-primary" href="${escapeHtml(row.edit_url)}">Sửa</a>
          </div>
        </td>
      </tr>
    `).join('');
  }

  function renderPager(paging) {
    const total = Number(paging?.total || 0);
    const current = Number(paging?.page || 1);
    const totalPages = Number(paging?.total_pages || 1);
    const limit = Number(paging?.limit || 20);

    if (total === 0) {
      el.pager.innerHTML = '';
      return;
    }

    const from = (current - 1) * limit + 1;
    const to = Math.min(current * limit, total);
    el.pager.innerHTML = `
      <span class="small text-secondary">Hiển thị <strong>${fmt(from)}–${fmt(to)}</strong> / <strong>${fmt(total)}</strong> hồ sơ có bài viết</span>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>Trước</button>
        <span class="small text-secondary">Trang <strong>${current}</strong> / ${fmt(totalPages)}</span>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-page="${current + 1}" ${current >= totalPages ? 'disabled' : ''}>Sau</button>
      </div>
    `;
    el.pager.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => loadPage(Number(btn.dataset.page || 1)));
    });
  }

  async function loadPage(nextPage = 1) {
    state.page = Math.max(1, Number(nextPage || 1));
    try {
      const res = await fetch(`${endpoint}?entity_type=${encodeURIComponent(state.entityType)}&page=${state.page}`, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      const text = await res.text();
      let data = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch (_) {
        throw new Error('Phản hồi máy chủ không đúng định dạng JSON.');
      }
      if (!res.ok || !data.ok) throw new Error(data.message || 'Không thể tải danh sách.');
      updateSummary(data.summary || {});
      renderRows(data.items || []);
      renderPager(data.paging || {});
    } catch (err) {
      el.tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(err.message || 'Lỗi tải dữ liệu.')}</td></tr>`;
    }
  }

  async function startFullScan() {
    if (state.scanning) return;
    state.scanning = true;
    state.stopRequested = false;
    el.btnStart.disabled = true;
    el.btnPause.classList.remove('d-none');

    let step = 0;
    try {
      while (!state.stopRequested) {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrfToken,
          },
          body: JSON.stringify({
            action: 'scan_next_batch',
            entity_type: state.entityType,
            batch_size: 2,
          }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.message || 'Lỗi khi quét.');

        updateSummary(data.summary || {});
        const processed = Array.isArray(data.processed) ? data.processed : [];
        if (processed.length > 0) {
          const last = processed[processed.length - 1];
          el.statusText.innerHTML = `Đang quét 64 KB... Vừa kiểm tra <strong>#${escapeHtml(last.id)} ${escapeHtml(last.name)}</strong> → <code>${escapeHtml(last.status)}</code> (${fmt(last.broken_count)} lỗi / ${fmt(last.images_count)} ảnh).`;
        }

        step++;
        if (step % 3 === 0) {
          loadPage(state.page);
        }

        if (data.done || Number(data.summary?.unscanned || 0) === 0) {
          el.statusText.innerHTML = '<strong class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Đã quét 64 KB và đánh dấu hoàn tất 100% hồ sơ có bài viết!</strong>';
          if (window.adminToast) window.adminToast('success', 'Đã quét xong toàn bộ hồ sơ!');
          break;
        }

        await new Promise((r) => window.setTimeout(r, 500));
      }
      if (state.stopRequested) {
        el.statusText.innerHTML = '<span class="text-warning fw-semibold">Đã tạm dừng. Các hồ sơ đã quét đều được lưu trong DB.</span>';
      }
    } catch (err) {
      el.statusText.innerHTML = `<span class="text-danger fw-semibold">${escapeHtml(err.message || 'Quét bị gián đoạn.')}</span>`;
    } finally {
      state.scanning = false;
      el.btnStart.disabled = false;
      el.btnPause.classList.add('d-none');
      loadPage(state.page);
    }
  }

  async function resetAllScans() {
    if (!window.confirm('Đặt lại toàn bộ hồ sơ có bài viết về trạng thái "Chưa quét"?')) return;
    el.btnReset.disabled = true;
    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({
          action: 'reset_all_scans',
          entity_type: state.entityType,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.message || 'Lỗi khi đặt lại.');
      if (window.adminToast) window.adminToast('success', data.message);
      loadPage(1);
    } catch (err) {
      if (window.adminToast) window.adminToast('danger', err.message || 'Lỗi khi đặt lại.');
    } finally {
      el.btnReset.disabled = false;
    }
  }

  el.entityBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      state.entityType = btn.dataset.entity || 'facility';
      el.entityBtns.forEach((b) => b.classList.toggle('active', b === btn));
      loadPage(1);
    });
  });

  el.btnStart.addEventListener('click', startFullScan);
  el.btnPause.addEventListener('click', () => { state.stopRequested = true; });
  el.btnReset.addEventListener('click', resetAllScans);

  el.tbody.addEventListener('click', async (e) => {
    const removeBtn = e.target.closest('button[data-remove-id]');
    if (removeBtn) {
      const id = Number(removeBtn.dataset.removeId || 0);
      const url = removeBtn.dataset.removeUrl || '';
      if (!id || !url || !window.confirm(`Gỡ URL ảnh lỗi này khỏi hồ sơ #${id}?\n${url}`)) return;
      removeBtn.disabled = true;
      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ action: 'remove_image', entity_type: state.entityType, id, url }),
        });
        const data = await res.json();
        if (res.ok && data.ok) {
          if (window.adminToast) window.adminToast('success', data.message);
          loadPage(state.page);
        }
      } finally {
        removeBtn.disabled = false;
      }
      return;
    }

    const rescanBtn = e.target.closest('button[data-rescan-id]');
    if (rescanBtn) {
      const id = Number(rescanBtn.dataset.rescanId || 0);
      rescanBtn.disabled = true;
      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ action: 'toggle_row_scan', entity_type: state.entityType, id }),
        });
        const data = await res.json();
        if (res.ok && data.ok) loadPage(state.page);
      } finally {
        rescanBtn.disabled = false;
      }
    }
  });

  const params = new URLSearchParams(window.location.search);
  if (params.get('entity_type') === 'doctor') {
    state.entityType = 'doctor';
    el.entityBtns.forEach((b) => b.classList.toggle('active', b.dataset.entity === 'doctor'));
  }

  loadPage(1);
})();
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
