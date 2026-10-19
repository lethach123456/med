'use strict';

// Pure VM fixtures: no real browser, DB, network, navigation or storage writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/medical-webmcp.js'), 'utf8');
let checks = 0;
const check = (value, label) => { checks++; assert.ok(value, label); };
const plain = value => JSON.parse(JSON.stringify(value));
const same = (value, expected, label) => { checks++; assert.deepEqual(plain(value), expected, label); };
const settle = async () => { for (let index = 0; index < 16; index++) await Promise.resolve(); };

function harness({ secure = true, primary = true, legacy = true, lang = 'vi', registration } = {}) {
  const calls = [], requests = [], mutations = [], timers = new Map();
  let clock = 0, nextTimer = 1;
  const setTimeout = (callback, delay = 0) => { const id = nextTimer++; timers.set(id, { callback, due: clock + delay }); return id; };
  const clearTimeout = id => timers.delete(id);
  const register = channel => ({ registerTool(tool) {
    calls.push({ channel, tool });
    return registration ? registration(tool, calls.length) : undefined;
  } });
  const document = { documentElement: { lang }, readyState: 'complete' };
  if (primary) document.modelContext = register('document');
  const navigator = {};
  if (legacy) navigator.modelContext = register('navigator');
  const deniedStorage = new Proxy({}, { get() { throw new Error('WebMCP must not persist searches or access browser storage'); } });
  const location = { origin: 'https://medreview.test', protocol: 'https:', href: 'https://medreview.test/',
    assign: value => { mutations.push(['navigate', value]); throw new Error('WebMCP search must not navigate'); },
    replace: value => { mutations.push(['replace', value]); throw new Error('WebMCP search must not navigate'); } };
  const fetch = (url, options = {}) => new Promise((resolve, reject) => {
    const request = { url: new URL(url, location.origin), options, resolve, reject };
    requests.push(request);
    if (options.signal) {
      const abort = () => reject(new DOMException('Fixture request aborted', 'AbortError'));
      if (options.signal.aborted) abort();
      else options.signal.addEventListener('abort', abort, { once: true });
    }
  });
  const context = { document, navigator, location, isSecureContext: secure, fetch, URL, URLSearchParams,
    AbortController, DOMException, Symbol, setTimeout, clearTimeout, localStorage: deniedStorage,
    sessionStorage: deniedStorage, console: { warn() {}, error() {}, info() {}, log() {} } };
  context.window = context;
  const sandbox = vm.createContext(context);
  const boot = () => vm.runInContext(source, sandbox, { filename: 'medical-webmcp.js' });
  boot();
  const tick = async milliseconds => {
    const end = clock + milliseconds;
    for (;;) {
      const next = [...timers].filter(([, timer]) => timer.due <= end).sort((a, b) => a[1].due - b[1].due)[0];
      if (!next) break;
      clock = next[1].due; timers.delete(next[0]); next[1].callback(); await settle();
    }
    clock = end; await settle();
  };
  const respond = async (index, payload = sample(), extra = {}) => {
    requests[index].resolve({ ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => payload, ...extra });
    await settle();
  };
  return { calls, requests, mutations, timers, context, boot, tick, respond };
}

const sample = () => ({ ok: true, query: 'nha khoa', min_length: 2, groups: {
  facilities: [{ id: 11, name: 'Nha khoa mẫu', url: '/co-so-y-te/nha-khoa-mau', city: 'Đà Nẵng', admin_note: 'secret' }],
  doctors: [{ id: 12, name: 'Bác sĩ mẫu', url: '/bac-si/bac-si-mau', city: 'Đà Nẵng' }],
  toplists: [{ id: 13, title: 'Toplist mẫu', url: '/toplist/toplist-mau' }]
}, meta: { totals: { facilities: 3, doctors: 2, toplists: 1 }, group_order: ['doctors', 'facilities', 'toplists'], city: 'Đà Nẵng', internal_path: '/private/index' },
  cache: { source: 'json', generated_at: 123, internal_path: '/private/index' } });

function result(output) {
  check(output && Array.isArray(output.content) && output.content.length === 1, 'Tool returns one MCP content item');
  same(output.content[0].type, 'text', 'MCP result is text');
  check(typeof output.isError === 'boolean', 'MCP result always declares success/error');
  return JSON.parse(output.content[0].text);
}
async function failed(app, args, code, options) {
  const before = app.requests.length;
  const output = await app.calls[0].tool.execute(args, options);
  const payload = result(output);
  check(output.isError && payload.ok === false, 'Failure is an explicit structured tool error');
  same(payload.error.code, code, 'Error code: ' + code);
  check(typeof payload.error.message === 'string' && payload.error.message.length > 0, 'Failure has a user-readable message');
  same(app.requests.length, before, 'Rejected input does not call the API');
  return payload;
}

(async () => {
  for (const options of [{ secure: false }, { primary: false, legacy: false }, { secure: false, primary: false }]) {
    const app = harness(options);
    await settle();
    same(app.calls.length, 0, 'Unsupported/insecure page does not register a tool');
    same(app.requests.length, 0, 'Unsupported/insecure page makes no request');
    same(app.timers.size, 0, 'Unsupported/insecure page schedules no work');
  }
  const app = harness();
  await settle();
  same(app.calls.length, 1, 'Exactly one public search tool is registered');
  same(app.calls[0].channel, 'document', 'Current document.modelContext takes precedence over legacy navigator.modelContext');
  same(app.requests.length, 0, 'Registration does not fetch or send health queries');
  app.boot(); await settle();
  same(app.calls.length, 1, 'Reexecuting the integration is idempotent');
  const fallback = harness({ primary: false });
  await settle();
  same(fallback.calls[0].channel, 'navigator', 'Guarded legacy registration works when primary API is absent');
  const tool = app.calls[0].tool;
  same(tool.name, 'search_medreview_directory', 'Stable registered tool name');
  check(typeof tool.description === 'string' && tool.description.length > 30, 'Tool describes its real search capability');
  check(tool.annotations.readOnlyHint === true && tool.annotations.consequentialHint === false && tool.annotations.untrustedContentHint === true, 'Tool declares read-only, non-consequential and untrusted-result semantics');
  const headerSource = fs.readFileSync(path.join(__dirname, '../Tem/header.php'), 'utf8');
  same((headerSource.match(/medical-webmcp\.js/g) || []).length, 1, 'Shared header loads integration exactly once');
  check(/<script[^\n]*medical_public_asset_url\('\/assets\/js\/medical-webmcp\.js'\)[^\n]*defer><\/script>/.test(headerSource), 'Integration is cache-versioned and deferred without blocking page rendering');
  const schema = plain(tool.inputSchema);
  same(schema.type, 'object', 'Input is an object');
  same(schema.required, ['q'], 'Query is required');
  same(schema.additionalProperties, false, 'Undeclared tool arguments are forbidden');
  same(Object.keys(schema.properties).sort(), ['category', 'limit', 'locale', 'q'], 'Every declared parameter has executable coverage');
  same(schema.properties.q.type, 'string', 'Query is text');
  same([schema.properties.q.minLength, schema.properties.q.maxLength], [2, 120], 'Schema bounds query length');
  same(schema.properties.category.enum, ['all', 'facilities', 'doctors', 'toplists'], 'Categories match actual API groups');
  same(schema.properties.category.default, 'all', 'All groups are the declared default');
  same(schema.properties.locale.enum, ['vi', 'en'], 'Only supported languages are declared');
  same(schema.properties.locale.default, 'vi', 'Schema default locale follows the Vietnamese page');
  same([schema.properties.limit.type, schema.properties.limit.minimum, schema.properties.limit.maximum, schema.properties.limit.default], ['integer', 1, 6, 4], 'Schema bounds result limits');

  const pending = tool.execute({ q: '  nha \n khoa  ' });
  await settle();
  same(app.requests.length, 1, 'Executing valid search makes one request');
  const request = app.requests[0];
  same(request.url.origin + request.url.pathname, 'https://medreview.test/api/medical/search.php', 'Only the fixed same-origin public search endpoint is called');
  same(Object.fromEntries(request.url.searchParams), { q: 'nha khoa', locale: 'vi', limit: '4' }, 'Whitespace and runtime defaults match API parameters');
  same(request.options.method, 'GET', 'Tool request is explicitly read-only GET');
  same(request.options.credentials, 'omit', 'No authenticated admin/session credentials are sent');
  same(request.options.cache, 'no-store', 'Health searches do not persist in browser fetch cache');
  same(request.options.redirect, 'error', 'Search cannot redirect queries to another endpoint');
  const headers = new Headers(request.options.headers);
  same(headers.get('Accept'), 'application/json', 'Tool requests JSON');
  check(!headers.has('Authorization') && !headers.has('X-Medical-Api-Key') && request.options.body === undefined, 'No auth secrets or mutation payload');
  await app.respond(0);
  const output = await pending, payload = result(output);
  check(!output.isError && payload.ok, 'Valid API response becomes a successful tool result');
  same([payload.query, payload.locale, payload.category], ['nha khoa', 'vi', 'all'], 'Tool describes the executed query/language/category');
  same(Object.keys(payload.groups).sort(), ['doctors', 'facilities', 'toplists'], 'All public entity groups are returned');
  same(payload.meta.group_order, ['doctors', 'facilities', 'toplists'], 'Server relevance order is preserved');
  check(!JSON.stringify(payload).includes('internal_path') && !JSON.stringify(payload).includes('secret') && !Object.hasOwn(payload, 'cache'), 'Only safe public result fields are exposed');
  check(typeof payload.notice === 'string' && payload.notice.length > 0, 'Result includes a medical-information notice');
  same(app.timers.size, 0, 'Successful fetch clears its timeout');

  for (const [category, locale, limit] of [['facilities', 'en', 1], ['doctors', 'vi', 6], ['toplists', 'en', 4], ['all', 'vi', 2]]) {
    const next = tool.execute({ q: 'mắt', category, locale, limit }); await settle();
    const index = app.requests.length - 1;
    same(Object.fromEntries(app.requests[index].url.searchParams), { q: 'mắt', locale, limit: String(limit) }, 'Explicit schema parameters reach API');
    await app.respond(index);
    const data = result(await next);
    same(Object.keys(data.groups).sort(), (category === 'all' ? ['doctors', 'facilities', 'toplists'] : [category]), 'Category filters returned groups without fake endpoint parameters');
    same(data.category, category, 'Result category follows caller');
  }
  for (const lang of ['en', 'en-US', 'EN-gb']) {
    const english = harness({ lang }); await settle();
    same(english.calls[0].tool.inputSchema.properties.locale.default, 'en', 'English page variants select English schema locale');
    const next = english.calls[0].tool.execute({ q: 'doctor' }); await settle();
    same(english.requests[0].url.searchParams.get('locale'), 'en', 'English page locale is used at execution');
    await english.respond(0); await next;
  }

  const invalid = [null, [], 'mắt', {}, { q: 'a' }, { q: ' \n ' }, { q: 12 }, { q: 'x'.repeat(121) },
    { q: 'mắt', category: 'admin' }, { q: 'mắt', locale: 'fr' }, { q: 'mắt', limit: 0 }, { q: 'mắt', limit: 7 },
    { q: 'mắt', limit: 1.5 }, { q: 'mắt', limit: '2' }, { q: 'mắt', limit: NaN }, { q: 'mắt', limit: Infinity },
    { q: 'mắt', url: 'https://evil.test/admin' }, { q: '😀'.repeat(121) }];
  for (const args of invalid) await failed(app, args, 'INVALID_INPUT');
  for (const q of ['😀😀', '😀'.repeat(120), 'https://evil.test/admin?action=delete&token=x']) {
    const next = tool.execute({ q }); await settle();
    const index = app.requests.length - 1;
    same(app.requests[index].url.origin + app.requests[index].url.pathname, 'https://medreview.test/api/medical/search.php', 'URL-shaped input remains an encoded query, never a destination');
    same(app.requests[index].url.searchParams.get('q'), q, 'Unicode codepoints and URL-shaped queries are preserved');
    await app.respond(index); await next;
  }

  const preAborted = new AbortController(); preAborted.abort();
  await failed(app, { q: 'mắt' }, 'ABORTED', { signal: preAborted.signal });
  const controller = new AbortController();
  const abortPending = tool.execute({ q: 'mắt' }, { signal: controller.signal }); await settle();
  const abortedRequest = app.requests.at(-1); controller.abort();
  const aborted = result(await abortPending);
  check(abortedRequest.options.signal.aborted, 'Caller abort propagates to the active fetch');
  same(aborted.error.code, 'ABORTED', 'Active cancellation has a stable error code');
  same(app.timers.size, 0, 'Cancellation clears its deadline');
  const timeoutPending = tool.execute({ q: 'mắt' }); await settle();
  const timeoutRequest = app.requests.at(-1); await app.tick(9_999);
  check(!timeoutRequest.options.signal.aborted, 'Request is allowed to finish before deadline');
  await app.tick(1);
  same(result(await timeoutPending).error.code, 'TIMEOUT', '10-second timeout is actionable');
  check(timeoutRequest.options.signal.aborted && app.timers.size === 0, 'Timeout aborts request and removes pending timer');

  for (const kind of ['http', 'network', 'json', 'payload', 'mime', 'api-error', 'missing-group']) {
    const next = tool.execute({ q: 'mắt' }); await settle();
    const index = app.requests.length - 1;
    if (kind === 'network') app.requests[index].reject(new Error('fixture network down'));
    else if (kind === 'http') await app.respond(index, null, { ok: false, status: 500 });
    else if (kind === 'json') await app.respond(index, null, { json: async () => { throw new SyntaxError('fixture malformed JSON'); } });
    else if (kind === 'mime') await app.respond(index, sample(), { headers: { get: () => 'text/html' } });
    else if (kind === 'api-error') await app.respond(index, { ok: false, message: 'No search right now.' });
    else if (kind === 'missing-group') await app.respond(index, { ok: true, groups: { facilities: [], doctors: [] } });
    else await app.respond(index, { ok: true, groups: 'not-an-object' });
    const failure = result(await next);
    same(failure.error.code, kind === 'http' || kind === 'network' ? 'UNAVAILABLE' : 'INVALID_RESPONSE', 'API failure is classified: ' + kind);
    same(app.timers.size, 0, 'API failure removes deadline: ' + kind);
  }
  const unsafePending = tool.execute({ q: 'mắt', limit: 6 }); await settle();
  const unsafe = sample();
  unsafe.groups.facilities.push(...['https://evil.test/co-so-y-te/a', '/admin/medical_facilities.php', 'javascript:alert(1)', '//evil.test/a', '/co-so-y-te/a/../../admin/delete'].map((url, index) => ({ id: 100 + index, name: 'Unsafe', url })));
  await app.respond(app.requests.length - 1, unsafe);
  const safe = result(await unsafePending);
  same(safe.groups.facilities.length, 1, 'External/admin/script/traversal links are not exposed as public records');
  same(safe.groups.facilities[0].url, 'https://medreview.test/co-so-y-te/nha-khoa-mau', 'Safe public profile link is made absolute on the current origin');
  for (const url of ['/co-so-y-te/a?token=private', '/co-so-y-te/a#private', '/co-so-y-te/a%2fb', '/co-so-y-te/a%5cb', '/co-so-y-te/a%00b', 'https://person:password@medreview.test/co-so-y-te/a']) {
    const next = tool.execute({ q: 'mắt', category: 'facilities' }); await settle();
    const data = sample(); data.groups.facilities = [{ name: 'Unsafe', url }];
    await app.respond(app.requests.length - 1, data);
    same(result(await next).groups.facilities, [], 'Query/hash/encoded-path/credential URL is omitted: ' + url);
  }
  const capPending = tool.execute({ q: 'doctor', category: 'doctors', locale: 'en', limit: 1 }); await settle();
  const cap = sample(); cap.groups.doctors = [
    { name: 'Doctor one', url: '/en/bac-si/doctor-one', rating: 5, reviews_count: 2, verified: true },
    { name: 'Doctor two', url: '/en/bac-si/doctor-two' }
  ];
  await app.respond(app.requests.length - 1, cap);
  const capped = result(await capPending);
  same(capped.groups.doctors.length, 1, 'Returned group respects caller limit even if server sends more');
  same(capped.groups.doctors[0], { label: 'Doctor one', url: 'https://medreview.test/en/bac-si/doctor-one', rating: 5, reviews_count: 2, verified: true }, 'English route and validated public rating fields are retained');
  same(capped.meta.group_order, ['doctors'], 'Category-specific output does not expose unrelated relevance groups');
  same(Object.keys(capped.meta.totals), ['doctors'], 'Category-specific totals contain only selected category');
  same(app.mutations.length, 0, 'All searches are non-navigating');

  let rejectRegistration;
  const deferredRegistration = new Promise((resolve, reject) => { rejectRegistration = reject; });
  const recovering = harness({ registration: (_, count) => count === 1 ? deferredRegistration : undefined });
  recovering.boot(); await settle();
  same(recovering.calls.length, 1, 'Pending async registration is also idempotent');
  rejectRegistration(new Error('temporary registration error')); await settle();
  recovering.boot(); await settle();
  same(recovering.calls.length, 2, 'Rejected registration clears its marker and can retry');
  same(recovering.requests.length, 0, 'Registration failures never trigger network fallback');
  const syncRecovering = harness({ registration: (_, count) => { if (count === 1) throw new Error('fixture sync registration failure'); } });
  syncRecovering.boot(); await settle();
  same(syncRecovering.calls.length, 2, 'Synchronous registration failure is contained and retryable');
  console.log(`Medical WebMCP: ${checks} checks passed. VM fixtures only; no DB/browser/network.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
