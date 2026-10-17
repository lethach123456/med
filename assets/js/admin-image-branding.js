(() => {
  'use strict';

  const root = document.getElementById('medicalImageBranding');
  if (!root) return;

  const byId = (suffix) => document.getElementById(`branding${suffix}`);
  const el = {
    total: byId('Total'), done: byId('Done'), pending: byId('Pending'), failed: byId('Failed'),
    type: byId('Type'), status: byId('Status'), scan: byId('Scan'), run: byId('Run'),
    runCount: byId('RunCount'), pause: byId('Pause'), refresh: byId('Refresh'), retry: byId('Retry'),
    state: byId('State'), stateLabel: byId('StateLabel'), feedback: byId('Feedback'),
    progress: byId('Progress'), progressLabel: byId('ProgressLabel'), progressText: byId('ProgressText'),
    scanInfo: byId('ScanInfo'), processingInfo: byId('ProcessingInfo'),
    capabilities: byId('Capabilities'), rows: byId('Rows'), list: byId('List'), pager: byId('Pager'),
  };
  const state = { type: 'all', status: 'all', page: 1, pages: 1, busy: '', pauseRequested: false, data: null, hasError: false };
  const fmt = (value) => new Intl.NumberFormat('vi-VN').format(Number(value) || 0);
  const count = (value) => Math.max(0, Number(value) || 0);
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));

  function safeUrl(value) {
    if (typeof value !== 'string' || !value.trim()) return '';
    try {
      const url = new URL(value, window.location.href);
      return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : '';
    } catch (_) {
      return '';
    }
  }

  function cacheBustedUrl(value, revision) {
    const safe = safeUrl(value);
    if (!safe || typeof revision !== 'string' || !/^[a-f0-9]{8,64}$/i.test(revision)) return safe;
    const url = new URL(safe);
    url.searchParams.set('branding_v', revision);
    return url.href;
  }

  function setFeedback(message, tone = '') {
    el.feedback.textContent = message;
    el.feedback.className = `branding-feedback${tone ? ` is-${tone}` : ''}`;
  }

  function updateControls() {
    const busy = Boolean(state.busy);
    const ready = Boolean(state.data) && !state.hasError;
    const summary = state.data?.summary || {};
    const caps = state.data?.capabilities || {};
    const canRun = caps.gd === true && caps.logo === true && caps.writable === true;
    [el.type, el.status, el.refresh].forEach((control) => { control.disabled = busy; });
    el.scan.disabled = busy || !ready || caps.writable === false;
    el.run.disabled = busy || !ready || !canRun || state.data?.scan?.ready === false || count(summary.pending) === 0;
    el.retry.disabled = busy || !ready || caps.writable === false || count(summary.failed) === 0;
    el.pause.hidden = !['scan', 'run'].includes(state.busy);
    el.pause.disabled = state.pauseRequested;
    el.pause.querySelector('span').textContent = state.pauseRequested ? 'Đang chờ dừng…' : 'Tạm dừng';
    el.pager.querySelectorAll('button[data-page]').forEach((button) => {
      button.disabled = busy || button.dataset.unavailable === 'true';
    });
    el.list.setAttribute('aria-busy', busy ? 'true' : 'false');
    el.state.className = `branding-state${['scan', 'run', 'retry'].includes(state.busy) ? ' is-running' : state.hasError ? ' is-error' : ''}`;
    el.stateLabel.textContent = state.pauseRequested ? 'Đang dừng' : ({ load: 'Đang tải', scan: 'Đang quét', run: 'Đang gắn logo', retry: 'Đang đặt lại' }[state.busy] || (state.hasError ? 'Cần kiểm tra' : 'Sẵn sàng'));
  }

  function updateProgress() {
    const summary = state.data?.summary || {};
    const total = count(summary.total);
    const done = count(summary.done);
    const percent = total ? Math.min(100, Math.round(done / total * 100)) : 0;
    const scan = state.data?.scan || {};
    const scanning = state.busy === 'scan';
    el.progress.classList.toggle('is-scanning', scanning);
    el.progress.style.width = `${percent}%`;
    el.progress.setAttribute('aria-label', scanning ? 'Tiến độ quét danh sách ảnh' : 'Tiến độ gắn logo');
    if (scanning) {
      el.progress.removeAttribute('aria-valuenow');
      el.progress.setAttribute('aria-valuetext', `Đã duyệt ${fmt(scan.scanned)} mục nguồn. Chưa xác định tổng số.`);
      el.progressLabel.textContent = 'Đang lập danh sách ảnh';
      el.progressText.textContent = `${fmt(scan.scanned)} mục nguồn đã duyệt`;
    } else {
      el.progress.setAttribute('aria-valuenow', String(percent));
      el.progress.setAttribute('aria-valuetext', `${fmt(done)} trên ${fmt(total)} ảnh đã gắn logo`);
      el.progressLabel.textContent = 'Tiến độ gắn logo';
      el.progressText.textContent = `${percent}% · ${fmt(done)} / ${fmt(total)} ảnh`;
    }
    const phaseLabels = { idle: 'Chưa quét danh sách', facility: 'Đang duyệt cơ sở y tế', doctor: 'Đang duyệt bác sĩ', library: 'Đang duyệt thư viện y tế', complete: 'Đã quét xong danh sách' };
    el.scanInfo.textContent = `${phaseLabels[scan.phase] || 'Chưa có thông tin quét'}${scan.updated_at ? ` · ${scan.updated_at}` : ''}`;
    el.processingInfo.textContent = count(summary.processing)
      ? (state.busy ? `${fmt(summary.processing)} ảnh đang xử lý` : `${fmt(summary.processing)} ảnh chưa hoàn tất · Quét danh sách ảnh để khôi phục tác vụ gián đoạn`)
      : (state.busy === 'run' ? 'Xử lý lần lượt từng ảnh' : 'Không có ảnh đang xử lý');
  }

  function renderCapabilities(caps) {
    const errors = [];
    if (caps?.gd === false) errors.push('Máy chủ chưa bật GD để xử lý ảnh.');
    if (caps?.logo === false) errors.push('Chưa tìm thấy tệp logo chữ MedReview hợp lệ.');
    if (caps?.writable === false) errors.push('Chưa thể ghi trạng thái hoặc ảnh kết quả vào thư mục lưu trữ.');
    el.capabilities.hidden = errors.length === 0;
    el.capabilities.textContent = errors.length ? `${errors.join(' ')} Vui lòng kiểm tra cấu hình trước khi gắn logo.` : '';
  }

  function emptyRows(title, detail) {
    el.rows.innerHTML = `<tr class="branding-empty-row"><td colspan="4"><div class="branding-empty"><i class="fa-regular fa-images" aria-hidden="true"></i><strong>${escapeHtml(title)}</strong><span>${escapeHtml(detail)}</span></div></td></tr>`;
  }

  function renderRows(items) {
    if (!Array.isArray(items) || items.length === 0) {
      const hasInventory = count(state.data?.summary?.total) > 0;
      emptyRows(hasInventory ? 'Không có ảnh trong bộ lọc này' : 'Chưa có ảnh trong danh sách', hasInventory ? 'Chọn trạng thái khác để xem các ảnh đã thống kê.' : 'Bấm “1. Quét danh sách ảnh” để tìm ảnh từ hồ sơ và thư viện y tế.');
      return;
    }
    const statusInfo = {
      done: ['Đã gắn logo', 'circle-check'], pending: ['Chưa gắn logo', 'clock'],
      failed: ['Cần thử lại', 'triangle-exclamation'], processing: ['Đang xử lý', 'circle-notch'],
    };
    el.rows.innerHTML = items.map((item) => {
      const status = Object.hasOwn(statusInfo, item.status) ? item.status : 'pending';
      const [label, icon] = statusInfo[status];
      const originalUrl = safeUrl(item.url);
      const outputUrl = safeUrl(item.output_url);
      const legacyOutput = Boolean(outputUrl && originalUrl && outputUrl !== originalUrl);
      const currentUrl = status === 'done' && outputUrl && !legacyOutput
        ? cacheBustedUrl(outputUrl, item.cache_revision) : originalUrl;
      const previewUrl = status === 'done' && outputUrl
        ? cacheBustedUrl(outputUrl, item.cache_revision) : currentUrl;
      let filename = 'Ảnh y tế';
      if (originalUrl) {
        try { filename = decodeURIComponent(new URL(originalUrl).pathname.split('/').filter(Boolean).pop() || filename); } catch (_) { /* Keep the safe fallback label. */ }
      }
      const refs = Array.isArray(item.refs) ? item.refs : [];
      const refsHtml = refs.map((ref) => {
        const typeLabel = ref.type === 'doctor' ? 'Bác sĩ' : 'Cơ sở y tế';
        return `<div class="branding-source"><i class="fa-solid fa-${ref.type === 'doctor' ? 'user-doctor' : 'hospital'}" aria-hidden="true"></i><span>${escapeHtml(ref.name || `${typeLabel} #${ref.id || ''}`)}</span></div>`;
      }).join('');
      const libraryHtml = item.library ? '<span class="branding-library-label"><i class="fa-regular fa-folder-open" aria-hidden="true"></i> Thư viện ảnh y tế</span>' : '';
      return `<tr>
        <td><div class="branding-image-cell"><div class="branding-thumb"><i class="fa-regular fa-image" aria-hidden="true"></i>${previewUrl ? `<img src="${escapeHtml(previewUrl)}" alt="" width="64" height="56" loading="lazy" decoding="async" referrerpolicy="no-referrer">` : ''}</div><div class="branding-image-copy"><span class="branding-image-name">${escapeHtml(filename)}</span><span class="branding-image-path">${escapeHtml(item.url || 'Không có URL ảnh hợp lệ')}</span></div></div></td>
        <td data-label="Sử dụng tại"><div class="branding-source-list">${refsHtml}${libraryHtml}${!refsHtml && !libraryHtml ? '<span>Chưa có nguồn tham chiếu</span>' : ''}</div></td>
        <td data-label="Trạng thái"><span class="branding-badge is-${status}"><i class="fa-solid fa-${icon}" aria-hidden="true"></i>${label}</span>${item.error ? `<p class="branding-row-error">${escapeHtml(item.error)}</p>` : ''}${item.updated_at ? `<div class="branding-row-date">${escapeHtml(item.updated_at)}</div>` : ''}</td>
        <td data-label="Xem ảnh"><div class="branding-row-links">${currentUrl ? `<a href="${escapeHtml(currentUrl)}" target="_blank" rel="noopener noreferrer">${legacyOutput ? 'Ảnh hiện tại' : 'Xem ảnh'} <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="visually-hidden"> (mở tab mới)</span></a>` : ''}${legacyOutput ? `<a href="${escapeHtml(cacheBustedUrl(outputUrl, item.cache_revision))}" target="_blank" rel="noopener noreferrer">Bản có logo cũ <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="visually-hidden"> (mở tab mới)</span></a>` : ''}${!originalUrl && !outputUrl ? '<span>URL không hợp lệ</span>' : ''}</div></td>
      </tr>`;
    }).join('');
    el.rows.querySelectorAll('img').forEach((img) => img.addEventListener('error', () => { img.hidden = true; }, { once: true }));
  }

  function renderPager() {
    el.pager.innerHTML = `<span>Trang ${fmt(state.page)} / ${fmt(state.pages)}</span><div class="branding-pager-buttons"><button type="button" class="btn btn-outline-secondary" data-page="${state.page - 1}" data-unavailable="${state.page <= 1}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Trước</button><button type="button" class="btn btn-outline-secondary" data-page="${state.page + 1}" data-unavailable="${state.page >= state.pages}">Sau <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button></div>`;
  }

  function applyPayload(data) {
    state.data = data;
    state.pages = Math.max(1, count(data.pages));
    state.page = Math.max(1, Math.min(state.pages, count(data.page) || state.page));
    const summary = data.summary || {};
    el.total.textContent = fmt(summary.total);
    el.done.textContent = fmt(summary.done);
    el.pending.textContent = fmt(Math.max(0, count(summary.total) - count(summary.done)));
    el.failed.textContent = fmt(summary.failed);
    el.runCount.textContent = fmt(summary.pending);
    renderCapabilities(data.capabilities);
    renderRows(data.items);
    renderPager();
    updateProgress();
    updateControls();
  }

  async function request(action) {
    const endpoint = new URL(root.dataset.endpoint, window.location.href);
    endpoint.search = new URLSearchParams({ type: state.type, status: state.status, page: String(state.page) }).toString();
    const options = { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } };
    if (action) {
      options.method = 'POST';
      options.headers['Content-Type'] = 'application/json';
      options.headers['X-CSRF-Token'] = root.dataset.csrfToken;
      options.body = JSON.stringify({ action, type: state.type, status: state.status, page: state.page, ...(action === 'run' ? { overwrite_confirmed: true } : {}) });
    }
    let response;
    try { response = await fetch(endpoint.href, options); } catch (_) {
      throw new Error('Mất kết nối với máy chủ. Yêu cầu trước có thể đã hoàn tất; bấm “Làm mới” để kiểm tra trước khi chạy tiếp.');
    }
    let data;
    try { data = await response.json(); } catch (_) {
      throw new Error('Máy chủ không trả về dữ liệu hợp lệ. Phiên đăng nhập có thể đã hết hạn; hãy tải lại trang để kiểm tra.');
    }
    if (!response.ok || data?.ok !== true) throw new Error(data?.message || 'Không thể hoàn tất yêu cầu. Vui lòng làm mới để kiểm tra trạng thái.');
    applyPayload(data);
    return data;
  }

  function begin(action) {
    if (state.busy) return false;
    state.busy = action;
    state.pauseRequested = false;
    state.hasError = false;
    updateControls();
    updateProgress();
    return true;
  }

  function end() {
    state.busy = '';
    state.pauseRequested = false;
    updateControls();
    updateProgress();
  }

  function reportError(error) {
    state.hasError = true;
    setFeedback(error instanceof Error ? error.message : 'Có lỗi xảy ra. Vui lòng kiểm tra rồi thử lại.', 'error');
  }

  async function load() {
    if (!begin('load')) return;
    state.data = null;
    [el.total, el.done, el.pending, el.failed, el.progressText].forEach((node) => { node.textContent = '—'; });
    el.runCount.textContent = '0';
    el.progress.style.width = '0%';
    el.scanInfo.textContent = 'Đang tải thông tin quét…';
    el.processingInfo.textContent = 'Đang kiểm tra trạng thái';
    emptyRows('Đang tải danh sách ảnh…', 'Chỉ đọc trạng thái đã lưu, không tự động quét.');
    updateControls();
    setFeedback('Đang tải trạng thái đã lưu; không quét hay thay đổi ảnh.');
    try {
      const data = await request();
      setFeedback(data.scan?.ready === false && count(data.summary?.total)
        ? 'Bấm “Quét danh sách ảnh” để cập nhật danh sách nguồn trước khi gắn logo. Lịch sử ảnh đã xử lý vẫn được giữ lại.'
        : count(data.summary?.total) ? `Đã tải ${fmt(data.summary.total)} ảnh trong phạm vi đã chọn. ${fmt(data.summary.done)} ảnh có logo, ${fmt(data.summary.pending)} ảnh đang chờ, ${fmt(data.summary.failed)} ảnh cần thử lại.` : 'Chưa có ảnh được thống kê trong phạm vi này. Bắt đầu bằng bước “Quét danh sách ảnh”.');
    } catch (error) {
      reportError(error);
      if (!state.data) emptyRows('Không thể tải danh sách', 'Kiểm tra kết nối hoặc phiên đăng nhập, sau đó bấm “Làm mới”.');
    } finally { end(); }
  }

  async function scan() {
    if (!begin('scan')) return;
    setFeedback('Đang bắt đầu quét nguồn ảnh. Các kết quả đã gắn logo vẫn được giữ lại.');
    try {
      let data = await request('scan_start');
      let previous = '';
      let unchanged = 0;
      while (!state.pauseRequested && data.scan?.phase !== 'complete') {
        data = await request('scan_step');
        const fingerprint = JSON.stringify([data.scan?.phase, data.scan?.steps, data.scan?.scanned, data.summary?.total]);
        unchanged = fingerprint === previous ? unchanged + 1 : 0;
        previous = fingerprint;
        if (unchanged >= 8) throw new Error('Quét chưa có tiến triển sau nhiều yêu cầu. Đã dừng an toàn; vui lòng làm mới và kiểm tra trạng thái trước khi thử tiếp.');
        setFeedback(`Đang quét: ${fmt(data.scan?.scanned)} mục nguồn đã duyệt, tìm thấy ${fmt(data.summary?.total)} ảnh. Có thể tạm dừng sau yêu cầu hiện tại.`);
      }
      setFeedback(data.scan?.phase === 'complete' ? `Quét xong danh sách. Có ${fmt(data.summary?.total)} ảnh, ${fmt(data.summary?.pending)} ảnh đang chờ gắn logo.` : 'Đã tạm dừng quét an toàn. Danh sách đã tìm thấy được giữ lại; bấm quét để rà soát lại nguồn ảnh.', data.scan?.phase === 'complete' ? 'success' : '');
    } catch (error) { reportError(error); } finally { end(); }
  }

  async function run() {
    if (state.busy || el.run.disabled) return;
    const scope = { all: 'tất cả ảnh y tế', facility: 'ảnh cơ sở y tế', doctor: 'ảnh bác sĩ' }[state.type] || 'ảnh y tế';
    const pending = fmt(state.data?.summary?.pending);
    if (!window.confirm(`Gắn logo và GHI ĐÈ ${pending} ảnh đang chờ trong phạm vi ${scope}?\n\nẢnh trên hosting sẽ bị thay thế tại cùng thư mục và tên; URL trong DB được giữ nguyên. KHÔNG giữ bản gốc hoặc bản sao dự phòng, không thể hoàn tác bằng công cụ. Ảnh nguồn bên ngoài sẽ báo lỗi, không được ghi đè.\n\nBấm OK để bắt đầu.`)) return;
    if (!begin('run')) return;
    setFeedback('Đang gắn logo và ghi đè lần lượt từng ảnh trên hosting. Giữ nguyên thư mục, tên và URL; không lưu bản gốc.');
    try {
      let data = state.data;
      let previous = '';
      let unchanged = 0;
      while (!state.pauseRequested && count(data?.summary?.pending) > 0) {
        data = await request('run');
        const summary = data.summary || {};
        const fingerprint = JSON.stringify([summary.pending, summary.done, summary.failed, summary.processing]);
        unchanged = fingerprint === previous ? unchanged + 1 : 0;
        previous = fingerprint;
        if (unchanged >= 5) throw new Error('Hàng chờ chưa có tiến triển sau nhiều yêu cầu. Đã dừng an toàn; hãy làm mới và kiểm tra ảnh đang xử lý trước khi chạy tiếp.');
        setFeedback(`Đã gắn logo ${fmt(summary.done)} / ${fmt(summary.total)} ảnh. Còn ${fmt(summary.pending)} ảnh chờ${count(summary.failed) ? `, ${fmt(summary.failed)} ảnh cần thử lại` : ''}.`);
      }
      const summary = data?.summary || {};
      if (state.pauseRequested && count(summary.pending) > 0) {
        setFeedback(`Đã tạm dừng an toàn. ${fmt(summary.done)} ảnh có logo; ${fmt(summary.pending)} ảnh còn chờ. Bấm “Gắn logo & ghi đè” để tiếp tục.`);
      } else if (count(summary.processing) > 0) {
        setFeedback(`Không còn ảnh đang chờ nhưng có ${fmt(summary.processing)} ảnh chưa hoàn tất. Nếu tác vụ trước bị gián đoạn, bấm “Quét danh sách ảnh” để khôi phục hàng chờ an toàn.`);
      } else {
        setFeedback(count(summary.failed) ? `Đã xử lý hết ảnh đang chờ. ${fmt(summary.done)} ảnh có logo, ${fmt(summary.failed)} ảnh cần kiểm tra và thử lại.` : `Hoàn tất hàng chờ. ${fmt(summary.done)} ảnh đã được gắn logo MedReview.`, count(summary.failed) ? '' : 'success');
      }
    } catch (error) { reportError(error); } finally { end(); }
  }

  async function retry() {
    if (!begin('retry')) return;
    setFeedback('Đang đưa các ảnh lỗi trong phạm vi đã chọn về hàng chờ…');
    try {
      const data = await request('retry_failed');
      setFeedback(`Đã đưa ảnh lỗi về hàng chờ. Có ${fmt(data.summary?.pending)} ảnh đang chờ; bấm “Gắn logo & ghi đè” để xử lý.`, 'success');
    } catch (error) { reportError(error); } finally { end(); }
  }

  el.type.addEventListener('change', () => { state.type = el.type.value; state.page = 1; load(); });
  el.status.addEventListener('change', () => { state.status = el.status.value; state.page = 1; load(); });
  el.refresh.addEventListener('click', load);
  el.scan.addEventListener('click', scan);
  el.run.addEventListener('click', run);
  el.retry.addEventListener('click', retry);
  el.pause.addEventListener('click', () => {
    state.pauseRequested = true;
    setFeedback('Đã yêu cầu tạm dừng. Đang chờ yêu cầu hiện tại hoàn tất; không hủy thao tác đang ghi dữ liệu.');
    updateControls();
  });
  el.pager.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-page]');
    if (!button || button.disabled || state.busy) return;
    state.page = Math.max(1, Math.min(state.pages, Number(button.dataset.page) || 1));
    load();
  });
  window.addEventListener('beforeunload', (event) => {
    if (!['scan', 'run', 'retry'].includes(state.busy)) return;
    event.preventDefault();
    event.returnValue = '';
  });

  load();
})();
