<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$adminPageTitle = 'Admin • Trạng thái quét ảnh';
$adminHeaderTitle = 'Quét & đánh dấu trạng thái ảnh';
$adminHeaderSubtitle = 'Quét tự động toàn bộ Cơ sở y tế & Bác sĩ và đánh dấu Đã quét / Chưa quét trong Database';
$adminActive = 'medical-image-audit';
$csrfToken = admin_csrf_token();

require __DIR__ . '/_layout_start.php';
?>

<style>
  .scan-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.85rem; }
  .scan-stat-card { padding:1rem 1.15rem; border:1px solid #e2eaf4; border-radius:.9rem; background:#fff; }
  .scan-stat-card .num { font-size:1.45rem; font-weight:800; color:#16233a; line-height:1.1; }
  .scan-stat-card .lbl { font-size:.78rem; font-weight:700; color:#697b96; margin-top:.25rem; }
  .scan-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.28rem .65rem; border-radius:999px; font-size:.75rem; font-weight:750; }
  .scan-badge.scanned { color:#15803d; background:#dcfce7; }
  .scan-badge.unscanned { color:#52627a; background:#eef2f8; }
  @media (max-width:767.98px) { .scan-stats { grid-template-columns:1fr; } }
</style>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
          <div>
            <div class="h5 mb-1">Quét tự động &amp; đánh dấu Database</div>
            <div class="text-secondary small" id="scanStatusText">
              Bấm <strong>“Quét toàn bộ chưa quét”</strong> một lần để hệ thống tự động duyệt hết danh sách và lưu trạng thái vào DB.
            </div>
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

        <div class="progress mb-3" style="height:10px;border-radius:999px;background:#e9eff8;">
          <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="scanProgressBar" role="progressbar" style="width:0%"></div>
        </div>

        <div class="scan-stats mb-4">
          <div class="scan-stat-card">
            <div class="num" id="statTotal">0</div>
            <div class="lbl">Tổng số hồ sơ</div>
          </div>
          <div class="scan-stat-card">
            <div class="num text-success" id="statScanned">0</div>
            <div class="lbl">Đã quét (Lưu trong DB)</div>
          </div>
          <div class="scan-stat-card">
            <div class="num text-secondary" id="statUnscanned">0</div>
            <div class="lbl">Chưa quét</div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th style="width:80px;">ID</th>
                <th>Tên hồ sơ</th>
                <th style="width:150px;">Khu vực</th>
                <th style="width:120px;">Số ảnh</th>
                <th style="width:170px;">Trạng thái DB</th>
                <th style="width:180px;">Thời gian quét</th>
                <th style="width:190px;" class="text-end">Thao tác</th>
              </tr>
            </thead>
            <tbody id="scanTableBody">
              <tr>
                <td colspan="7" class="text-center text-secondary py-4">Đang tải danh sách...</td>
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
    progressBar: document.getElementById('scanProgressBar'),
    statTotal: document.getElementById('statTotal'),
    statScanned: document.getElementById('statScanned'),
    statUnscanned: document.getElementById('statUnscanned'),
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
    const pct = total > 0 ? Math.min(100, Math.round((scanned / total) * 100)) : 0;

    el.statTotal.textContent = fmt(total);
    el.statScanned.textContent = `${fmt(scanned)} (${pct}%)`;
    el.statUnscanned.textContent = fmt(unscanned);
    el.btnUnscannedCount.textContent = fmt(unscanned);
    el.progressBar.style.width = `${pct}%`;
  }

  function renderRows(items) {
    if (!Array.isArray(items) || items.length === 0) {
      el.tbody.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-4">Chưa có dữ liệu hồ sơ.</td></tr>';
      return;
    }

    el.tbody.innerHTML = items.map((row) => {
      const isScanned = Boolean(row.is_scanned);
      const badgeHtml = isScanned
        ? '<span class="scan-badge scanned"><i class="fa-solid fa-circle-check"></i>Đã quét</span>'
        : '<span class="scan-badge unscanned"><i class="fa-regular fa-clock"></i>Chưa quét</span>';

      return `
        <tr>
          <td class="mono fw-semibold">#${Number(row.id || 0)}</td>
          <td>
            <div class="fw-semibold">${escapeHtml(row.name)}</div>
            ${row.subtitle ? `<div class="small text-secondary">${escapeHtml(row.subtitle)}</div>` : ''}
          </td>
          <td>${escapeHtml(row.city || '—')}</td>
          <td><span class="badge text-bg-light border">${fmt(row.images_count)} ảnh</span></td>
          <td>${badgeHtml}</td>
          <td class="small text-secondary mono">${escapeHtml(row.image_scanned_at || '—')}</td>
          <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
              <button type="button" class="btn btn-sm btn-light" data-toggle-id="${Number(row.id || 0)}" data-next-scanned="${isScanned ? '0' : '1'}" title="Đổi trạng thái Đã quét / Chưa quét">
                ${isScanned ? 'Đặt Chưa quét' : 'Đánh dấu Đã quét'}
              </button>
              <a class="btn btn-sm btn-outline-secondary" href="${escapeHtml(row.public_url)}" target="_blank">Xem</a>
              <a class="btn btn-sm btn-outline-primary" href="${escapeHtml(row.edit_url)}">Sửa</a>
            </div>
          </td>
        </tr>
      `;
    }).join('');
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
      <span class="small text-secondary">Hiển thị <strong>${fmt(from)}–${fmt(to)}</strong> / <strong>${fmt(total)}</strong></span>
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
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.message || 'Không thể tải danh sách.');
      updateSummary(data.summary || {});
      renderRows(data.items || []);
      renderPager(data.paging || {});
    } catch (err) {
      el.tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(err.message || 'Lỗi tải dữ liệu.')}</td></tr>`;
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
            batch_size: 10,
          }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.message || 'Lỗi khi quét.');

        updateSummary(data.summary || {});
        const processed = Array.isArray(data.processed) ? data.processed : [];
        if (processed.length > 0) {
          const last = processed[processed.length - 1];
          el.statusText.innerHTML = `Đang quét tự động... Vừa đánh dấu <strong>#${escapeHtml(last.id)} ${escapeHtml(last.name)}</strong> (${fmt(last.images_count)} ảnh).`;
        }

        step++;
        if (step % 3 === 0) {
          loadPage(state.page);
        }

        if (data.done || Number(data.summary?.unscanned || 0) === 0) {
          el.statusText.innerHTML = '<strong class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Đã quét và đánh dấu hoàn tất 100% hồ sơ!</strong>';
          if (window.adminToast) window.adminToast('success', 'Đã quét xong toàn bộ hồ sơ!');
          break;
        }

        await new Promise((r) => window.setTimeout(r, 300));
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
    if (!window.confirm('Đặt lại toàn bộ hồ sơ về trạng thái "Chưa quét"?')) return;
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
    const btn = e.target.closest('button[data-toggle-id]');
    if (!btn) return;
    const id = Number(btn.dataset.toggleId || 0);
    const scanned = btn.dataset.nextScanned === '1';
    btn.disabled = true;
    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({
          action: 'toggle_row_scan',
          entity_type: state.entityType,
          id,
          scanned,
        }),
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        loadPage(state.page);
      }
    } finally {
      btn.disabled = false;
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
