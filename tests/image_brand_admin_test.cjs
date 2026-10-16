const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/admin-image-branding.js'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));
const payload = (overrides = {}) => ({ ok: true, summary: { total: 4, done: 1, pending: 2, failed: 1, processing: 0 }, scan: { phase: 'complete', scanned: 6, steps: 3, updated_at: '2026-10-17' }, items: [], page: 1, pages: 1, capabilities: { gd: true, logo: true, writable: true }, ...overrides });

class Element {
  constructor() { this.dataset = {}; this.listeners = {}; this.attributes = {}; this.children = {}; this.style = {}; this.className = ''; this.textContent = ''; this.innerHTML = ''; this.disabled = false; this.hidden = false; this.value = ''; this.classList = { toggle() {} }; }
  addEventListener(name, callback) { this.listeners[name] = callback; }
  querySelector(selector) { return this.children[selector] ||= new Element(); }
  querySelectorAll() { return []; }
  setAttribute(name, value) { this.attributes[name] = value; }
  removeAttribute(name) { delete this.attributes[name]; }
  fire(name, event = {}) { return this.listeners[name]?.(event); }
}

async function setup(handler, confirmResult = true) {
  const elements = {};
  const get = (name) => elements[name] ||= new Element();
  const root = get('medicalImageBranding');
  root.dataset = { endpoint: '/admin/api/medical/image_branding.php', csrfToken: 'test-token' };
  const calls = [];
  const confirmations = [];
  const context = {
    document: { getElementById: get },
    window: { location: { href: 'https://medreview.test/admin/medical_image_branding.php' }, addEventListener() {}, confirm(message) { confirmations.push(message); return confirmResult; } },
    fetch: async (url, options) => { const call = { url: new URL(url), action: options.body ? JSON.parse(options.body).action : 'GET', options }; calls.push(call); const data = await handler(call, calls); return { ok: true, json: async () => data }; },
    URL, URLSearchParams, Intl, console,
  };
  vm.runInNewContext(source, context);
  await tick();
  return { get, calls, confirmations };
}

(async () => {
  {
    const { get, calls, confirmations } = await setup(() => payload());
    assert.equal(calls.length, 1);
    assert.equal(calls[0].action, 'GET', 'opening page must not mutate');
    assert.equal(get('brandingPending').textContent, '3', 'remaining includes failed, not only queue');
    assert.equal(get('brandingRunCount').textContent, '2');
    assert.equal(get('brandingRun').disabled, false);
    await tick();
    assert.equal(calls.length, 1, 'no idle polling');
    get('brandingType').value = 'doctor';
    get('brandingType').fire('change');
    await tick();
    assert.equal(calls[1].url.searchParams.get('type'), 'doctor');
    assert.equal(confirmations.length, 0, 'read-only loading and filtering never prompt for an overwrite');
  }
  {
    let steps = 0;
    const { get, calls } = await setup(call => {
      if (call.action === 'scan_start') return payload({ scan: { phase: 'facility', scanned: 0, steps: 0 } });
      if (call.action === 'scan_step') return payload({ scan: { phase: ++steps === 2 ? 'complete' : 'doctor', scanned: steps, steps } });
      return payload();
    });
    await get('brandingScan').fire('click');
    assert.deepEqual(calls.map(call => call.action), ['GET', 'scan_start', 'scan_step', 'scan_step']);
    assert.equal(calls[1].options.headers['X-CSRF-Token'], 'test-token');
    assert.ok(calls.filter(call => call.action.startsWith('scan_')).every(call => !Object.hasOwn(JSON.parse(call.options.body), 'overwrite_confirmed')), 'read-only scans do not claim an overwrite confirmation');
    assert.equal(get('brandingScan').disabled, false);
    assert.match(get('brandingFeedback').textContent, /Quét xong/);
  }
  {
    let steps = 0;
    const { get, calls } = await setup(call => {
      if (call.action === 'scan_start') return payload({ scan: { phase: 'library', scanned: 4, steps: 0 } });
      if (call.action === 'scan_step') return payload({ scan: { phase: ++steps === 12 ? 'complete' : 'library', scanned: 4, steps } });
      return payload();
    });
    await get('brandingScan').fire('click');
    assert.equal(calls.filter(call => call.action === 'scan_step').length, 12, 'skipped library originals must not trigger a false stall when steps advances');
    assert.match(get('brandingFeedback').textContent, /Quét xong/);
  }
  {
    const { get, calls } = await setup(call => payload(call.action.startsWith('scan_') ? { scan: { phase: 'library', scanned: 4, steps: 1 } } : {}));
    await get('brandingScan').fire('click');
    assert.equal(calls.filter(call => call.action === 'scan_step').length, 9, 'a genuinely unchanged cursor still stops safely');
    assert.match(get('brandingFeedback').textContent, /chưa có tiến triển/);
    assert.equal(get('brandingScan').disabled, true, 'a stalled job requires a refresh before another mutation');
  }
  {
    let runs = 0;
    const { get, calls, confirmations } = await setup(call => payload(call.action === 'run' ? { summary: { total: 4, done: 1 + ++runs, pending: 2 - runs, failed: 1, processing: 0 } } : {}));
    await get('brandingRun').fire('click');
    assert.deepEqual(calls.map(call => call.action), ['GET', 'run', 'run']);
    assert.equal(get('brandingRun').disabled, true);
    assert.equal(get('brandingPending').textContent, '1');
    assert.match(get('brandingFeedback').textContent, /1 ảnh cần/);
    assert.equal(confirmations.length, 1, 'one confirmation for the entire run, not one per image');
    assert.match(confirmations[0], /GHI ĐÈ 2 ảnh/);
    assert.match(confirmations[0], /tất cả ảnh y tế/);
    assert.match(confirmations[0], /URL trong DB được giữ nguyên/);
    assert.match(confirmations[0], /KHÔNG giữ bản gốc/);
    assert.match(confirmations[0], /không thể hoàn tác/);
    assert.ok(calls.filter(call => call.action === 'run').every(call => JSON.parse(call.options.body).overwrite_confirmed === true), 'each run POST must carry the accepted overwrite confirmation');
  }
  {
    const { get, calls, confirmations } = await setup(() => payload(), false);
    get('brandingType').value = 'doctor';
    get('brandingType').fire('change');
    await tick();
    await get('brandingRun').fire('click');
    assert.match(confirmations[0], /phạm vi ảnh bác sĩ/);
    assert.deepEqual(calls.map(call => call.action), ['GET', 'GET'], 'cancel must never send a destructive POST');
    assert.equal(get('brandingRun').disabled, false);
    assert.equal(get('brandingPause').hidden, true);
  }
  {
    const { get, calls, confirmations } = await setup(() => payload());
    await get('brandingRetry').fire('click');
    const retryCall = calls.find(call => call.action === 'retry_failed');
    assert.ok(retryCall && !Object.hasOwn(JSON.parse(retryCall.options.body), 'overwrite_confirmed'), 'retry only queues jobs; never sends an overwrite confirmation');
    assert.equal(confirmations.length, 0);
  }
  {
    const { get, calls, confirmations } = await setup(() => payload({ summary: { total: 1, done: 1, pending: 0, failed: 0, processing: 0 } }));
    await get('brandingRun').fire('click');
    assert.equal(confirmations.length, 0, 'empty queue does not prompt or mutate');
    assert.equal(calls.length, 1);
  }
  {
    let resolveRequest;
    const { get, calls } = await setup(call => call.action === 'run' ? new Promise(resolve => { resolveRequest = resolve; }) : payload());
    const running = get('brandingRun').fire('click');
    await tick();
    assert.equal(get('brandingType').disabled, true);
    assert.equal(get('brandingScan').disabled, true);
    assert.equal(get('brandingPause').hidden, false);
    get('brandingPause').fire('click');
    assert.equal(get('brandingPause').disabled, true);
    resolveRequest(payload({ summary: { total: 4, done: 2, pending: 1, failed: 1, processing: 0 } }));
    await running;
    assert.equal(calls.filter(call => call.action === 'run').length, 1, 'pause stops after current POST');
    assert.equal(calls[1].options.signal, undefined, 'must never abort POST');
    assert.match(get('brandingFeedback').textContent, /Đã tạm dừng/);
    assert.equal(get('brandingRun').disabled, false);
  }
  {
    let fail = true;
    const { get } = await setup(call => { if (call.action === 'run' && fail) throw new Error('network error'); return payload(); });
    await get('brandingRun').fire('click');
    assert.equal(get('brandingRun').disabled, true, 'unknown POST result must be refreshed before rerun');
    assert.equal(get('brandingRefresh').disabled, false);
    assert.match(get('brandingFeedback').textContent, /Mất kết nối/);
    fail = false;
    await get('brandingRefresh').fire('click');
    assert.equal(get('brandingRun').disabled, false);
  }
  {
    const { get } = await setup(() => payload({ items: [{ status: 'failed', url: 'javascript:alert(1)', output_url: 'data:text/html,evil', error: '<script>bad()</script>', refs: [{ type: 'doctor', name: '<img onerror=bad()>' }] }] }));
    const html = get('brandingRows').innerHTML;
    assert.ok(!html.includes('href="javascript:'));
    assert.ok(!html.includes('src="data:'));
    assert.ok(!html.includes('<script>'));
    assert.ok(html.includes('&lt;script&gt;'));
    assert.ok(html.includes('&lt;img onerror=bad()&gt;'));
  }
  {
    const { get } = await setup(() => payload({ items: [{ status: 'done', url: '/uploads/library/bac-si/test.webp', output_url: '/uploads/library/bac-si/test.webp', cache_revision: 'a'.repeat(64), refs: [] }] }));
    const html = get('brandingRows').innerHTML;
    assert.equal((html.match(/<a href=/g) || []).length, 1, 'same URL must show only one image link');
    assert.ok(html.includes('>Xem ảnh '));
    assert.ok(!html.includes('Ảnh gốc') && !html.includes('Bản có logo cũ'), 'overwrite UI never claims an original is recoverable');
    assert.ok(html.includes('test.webp?branding_v=' + 'a'.repeat(64)), 'thumbnail and view link invalidate browser cache without changing DB URL');
  }
  {
    const { get } = await setup(() => payload({ items: [{ status: 'done', url: '/uploads/library/bac-si/test.webp', output_url: '/uploads/library/medreview-branded/legacy.webp', cache_revision: 'b'.repeat(64), refs: [] }] }));
    const html = get('brandingRows').innerHTML;
    assert.equal((html.match(/<a href=/g) || []).length, 2);
    assert.ok(html.includes('>Ảnh hiện tại ') && html.includes('>Bản có logo cũ '), 'legacy copies must have honest labels');
    assert.ok(!html.includes('Ảnh gốc'));
  }
  {
    const { get } = await setup(() => payload({ capabilities: { gd: false, logo: true, writable: true } }));
    assert.equal(get('brandingRun').disabled, true);
    assert.equal(get('brandingScan').disabled, false, 'inventory does not require GD');
    assert.match(get('brandingCapabilities').textContent, /GD/);
  }
  {
    const { get, calls } = await setup(() => payload({ summary: { total: 4, done: 3, pending: 0, failed: 0, processing: 1 } }));
    assert.match(get('brandingProcessingInfo').textContent, /Quét danh sách ảnh.*khôi phục/);
    assert.equal(calls.length, 1, 'interrupted jobs must not be recovered by an idle GET');
    assert.equal(get('brandingScan').disabled, false);
    assert.equal(get('brandingRun').disabled, true);
  }
  console.log('PASS: initial GET/no idle polling, remaining counter, scope, sequential scan/run, overwrite confirmation/cancel, same-URL cache busting, honest legacy links, skipped-page scan progress, real stall detection, CSRF, safe pause, error recovery, URL/XSS handling, GD gating, interrupted-job recovery guidance.');
})().catch(error => { console.error(error); process.exitCode = 1; });
