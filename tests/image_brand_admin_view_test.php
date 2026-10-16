<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// This isolated renderer stubs authentication only in this CLI test process.
// It does not include the production bootstrap, open a session, or load a DB.
$GLOBALS['brandingViewAdminChecks'] = 0;
$GLOBALS['brandingViewAdminRole'] = true;
function admin_require_admin(): void { $GLOBALS['brandingViewAdminChecks']++; }
function admin_is_admin(): bool { return (bool) $GLOBALS['brandingViewAdminRole']; }
function admin_is_logged_in(): bool { return true; }
function admin_csrf_token(): string { return str_repeat('fixture-not-a-real-token-', 3); }
function admin_url(string $path = ''): string { return '/admin/' . ltrim($path, '/'); }
function site_url(string $path = ''): string { return '/' . ltrim($path, '/'); }

function image_brand_admin_render_real_page(): string
{
    $directory = dirname(__DIR__) . '/admin';
    $source = (string) file_get_contents($directory . '/medical_image_branding.php');
    $bootstrap = "require_once __DIR__ . '/_bootstrap.php';";
    if (substr_count($source, $bootstrap) !== 1) {
        throw new RuntimeException('Expected exactly one production bootstrap require');
    }
    // Keep the real page and both real layout includes; remove only bootstrap.
    $source = str_replace($bootstrap, '', $source);
    $source = str_replace('__DIR__', var_export($directory, true), $source);
    $originalSession = $_SESSION ?? null;
    $_SESSION = ['admin_user_id' => 0, 'admin_role' => 'admin (CLI fixture)'];
    ob_start();
    try {
        eval(substr($source, 5));
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
        if ($originalSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $originalSession;
        }
    }
}

function image_brand_admin_synthetic_preview(string $html): string
{
    // SYNTHETIC DATA ONLY. Override both the DOM endpoint and fetch; no fallback
    // to the native fetch exists, so preview controls can never reach a real API.
    $html = str_replace('data-endpoint="/admin/api/medical/image_branding.php"', 'data-endpoint="/__branding-fixture-api"', $html, $endpointChanges);
    if ($endpointChanges !== 1) {
        throw new RuntimeException('Could not isolate the fixture API endpoint');
    }
    $stub = <<<'HTML'
<!-- SYNTHETIC BRANDING PREVIEW: fake records; no real auth, API, DB, or image writes. -->
<script id="brandingSyntheticFixture" data-synthetic-fixture="true">
(() => {
  'use strict';
  const root = document.getElementById('medicalImageBranding');
  if (!root) throw new Error('Synthetic fixture root is missing');
  root.dataset.endpoint = '/__branding-fixture-api';
  const notice = document.createElement('div');
  notice.className = 'alert alert-info mb-0 small';
  notice.setAttribute('role', 'note');
  notice.dataset.syntheticFixture = 'true';
  notice.textContent = 'BẢN XEM THỬ · Dữ liệu giả lập. Không kết nối dữ liệu thật; mọi thao tác chỉ thay đổi bản xem thử này.';
  root.prepend(notice);
  const imageA = '/assets/media/variants/c4b9450843808925f063940a-640.webp';
  const imageB = '/assets/media/variants/remote-a07371772354b457ac6afaea-640.webp';
  const imageC = '/assets/media/variants/remote-bb88d31bf48df3430da69e35-320.webp';
  const date = '2026-10-17 09:30:00';
  const records = [
    { key: 'synthetic-facility-1', url: imageA, output_url: imageA, cache_revision: 'a'.repeat(64), status: 'done', error: null, refs: [{ type: 'facility', id: 1, name: 'Phòng khám Minh An (giả lập)' }, { type: 'doctor', id: 2, name: 'BS. Nguyễn Minh (giả lập)' }], library: true, updated_at: date },
    { key: 'synthetic-doctor-2', url: imageB, output_url: null, status: 'pending', error: null, refs: [{ type: 'doctor', id: 3, name: 'BS. Trần Thanh Hà (giả lập)' }], library: true, updated_at: date },
    { key: 'synthetic-facility-3', url: imageC, output_url: null, status: 'failed', error: 'Ví dụ lỗi giả lập: tệp ảnh chưa có quyền ghi. Kiểm tra quyền trên hosting rồi thử lại.', refs: [{ type: 'facility', id: 4, name: 'Trung tâm Chăm sóc Sức khỏe Thành Phố (giả lập)' }], library: false, updated_at: date },
    { key: 'synthetic-library-4', url: imageB + '?synthetic=library', output_url: imageB + '?synthetic=library-branded', status: 'done', error: null, refs: [], library: true, updated_at: date },
    { key: 'synthetic-facility-5', url: imageA + '?synthetic=facility', output_url: null, status: 'pending', error: null, refs: [{ type: 'facility', id: 5, name: 'Phòng khám Đa khoa & Chuyên khoa Y học Gia đình (giả lập)' }], library: true, updated_at: date },
    { key: 'synthetic-doctor-6', url: '/assets/brand/medreview-wordmark.png', output_url: null, status: 'pending', error: null, refs: [{ type: 'doctor', id: 6, name: 'BS. Lê Anh Thư (giả lập)' }], library: false, updated_at: date },
  ];
  let scan = { phase: 'complete', scanned: 48, steps: 3, updated_at: date };
  const fixture = window.__brandingSyntheticFixture = { synthetic: true, endpoint: '/__branding-fixture-api', requests: [] };
  const copy = (value) => JSON.parse(JSON.stringify(value));
  window.fetch = async (input, options = {}) => {
    const url = new URL(String(input?.url ?? input), window.location.href);
    if (url.origin !== window.location.origin || url.pathname !== fixture.endpoint) {
      throw new Error('Synthetic preview blocked a non-fixture fetch. No request was sent.');
    }
    const body = options.body ? JSON.parse(options.body) : {};
    const action = body.action || 'GET';
    fixture.requests.push({ action, type: url.searchParams.get('type'), status: url.searchParams.get('status') });
    const scope = url.searchParams.get('type') || 'all';
    const scoped = records.filter(item => scope === 'all' || item.refs.some(ref => ref.type === scope));
    if (action === 'scan_start') scan = { phase: 'facility', scanned: 0, steps: 0, updated_at: date };
    if (action === 'scan_step') {
      scan.steps += 1;
      scan.scanned = scan.steps * 16;
      scan.phase = ['facility', 'doctor', 'library', 'complete'][Math.min(scan.steps, 3)];
    }
    if (action === 'run') {
      const item = scoped.find(record => record.status === 'pending');
      if (item) { item.status = 'done'; item.output_url = item.url; item.cache_revision = 'c'.repeat(64); item.error = null; }
    }
    if (action === 'retry_failed') scoped.filter(item => item.status === 'failed').forEach(item => { item.status = 'pending'; item.error = null; });
    const summary = { total: scoped.length, done: 0, pending: 0, failed: 0, processing: 0 };
    scoped.forEach(item => { summary[item.status] += 1; });
    const status = url.searchParams.get('status') || 'all';
    const items = scoped.filter(item => status === 'all' || item.status === status);
    const result = { ok: true, synthetic: true, summary, scan, items, page: 1, pages: 1, capabilities: { gd: true, logo: true, writable: true } };
    return { ok: true, status: 200, json: async () => copy(result) };
  };
})();
</script>
HTML;
    $needle = '<script src="/assets/js/admin-image-branding.js';
    if (substr_count($html, $needle) !== 1) {
        throw new RuntimeException('Expected exactly one real branding controller script');
    }
    return str_replace($needle, $stub . "\n" . $needle, $html);
}

function image_brand_admin_fixture_destination(string $destination): string
{
    $parent = realpath(dirname($destination));
    if ($destination === '' || $parent === false || is_link($destination) || is_dir($destination)) {
        throw new RuntimeException('Choose a regular HTML path inside an existing temporary directory');
    }
    $roots = array_filter([realpath(sys_get_temp_dir()), realpath('/private/tmp')]);
    $allowed = false;
    foreach ($roots as $root) {
        if ($parent === $root || str_starts_with($parent, $root . DIRECTORY_SEPARATOR)) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed || strtolower(pathinfo($destination, PATHINFO_EXTENSION)) !== 'html') {
        throw new RuntimeException('Preview must be an .html artifact inside a temporary directory');
    }
    return $parent . DIRECTORY_SEPARATOR . basename($destination);
}

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};

$html = image_brand_admin_render_real_page();
$document = new DOMDocument();
$previousErrors = libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
libxml_use_internal_errors($previousErrors);
$xpath = new DOMXPath($document);
$nodes = static fn(string $query): int => (int) $xpath->evaluate('count(' . $query . ')');

$assert($GLOBALS['brandingViewAdminChecks'] === 1, 'The real page invokes the administrator guard');
$assert(!function_exists('db') && session_status() === PHP_SESSION_NONE, 'Rendering opens no database connection or authenticated session');
$assert($nodes('//html[@lang="vi"]') === 1 && $nodes('//main') === 1, 'The real shared admin layout is rendered');
$assert($nodes('//a[@href="/admin/medical_image_branding.php" and contains(concat(" ", normalize-space(@class), " "), " active ")]') === 2, 'Desktop and mobile navigation mark image branding active');
$assert($nodes('//section[@id="medicalImageBranding" and @data-endpoint="/admin/api/medical/image_branding.php"]') === 1, 'The real page points to the production API before fixture isolation');
$assert($nodes('//section[@id="medicalImageBranding" and @data-csrf-token="' . admin_csrf_token() . '"]') === 1, 'CSRF token is passed to the real controller');
$assert($nodes('//link[starts-with(@href,"/assets/css/pages/admin-image-branding.css?v=")]') === 1, 'Page stylesheet is local and cache-versioned');
$assert($nodes('//script[starts-with(@src,"/assets/js/admin-image-branding.js?v=") and @defer]') === 1, 'Real page loads its deferred local controller once');
foreach (['Total', 'Done', 'Pending', 'Failed'] as $counter) {
    $assert($nodes('//*[@id="branding' . $counter . '"]') === 1, 'One counter is rendered for ' . $counter);
}
foreach (['Scan', 'Run', 'Refresh', 'Retry'] as $action) {
    $assert($nodes('//button[@id="branding' . $action . '" and @type="button" and @disabled]') === 1, 'Action starts disabled until status loads: ' . $action);
}
$assert($nodes('//button[@id="brandingPause" and @type="button" and @hidden]') === 1, 'Pause is available only for an active task');
$assert($nodes('//label[@for="brandingType"]') === 1 && $nodes('//select[@id="brandingType"]/option') === 3, 'Scope selector has a visible label and three choices');
$assert($nodes('//label[@for="brandingStatus"]') === 1 && $nodes('//select[@id="brandingStatus"]/option') === 4, 'Status selector has an accessible label and four choices');
$assert($nodes('//*[@id="brandingFeedback" and @role="status" and @aria-live="polite" and @aria-atomic="true"]') === 1, 'Feedback is a contextual atomic polite live region');
$assert($nodes('//*[@id="brandingProgress" and @role="progressbar" and @aria-valuemin="0" and @aria-valuemax="100" and @aria-valuenow="0" and @aria-label]') === 1, 'Progress has a name and initial numeric bounds');
$assert($nodes('//*[@id="brandingList" and @aria-busy="true"]') === 1 && $nodes('//table/caption') === 1 && $nodes('//th[@scope="col"]') === 4, 'Image list exposes loading and table semantics');
$assert($nodes('//*[@id="brandingPager" and @aria-label]') === 1, 'Pagination has an accessible name');
$assert($nodes('//figure[contains(@class,"branding-preview")]//img[@src="/assets/brand/medreview-wordmark.png" and @alt="" and @width and @height]') === 1, 'Hero uses the local wordmark with stable dimensions');
$assert($nodes('//figure[contains(@class,"branding-preview")]//img[starts-with(@src,"http")]') === 0, 'Hero never requests external photos');
$assert(str_contains($html, 'Ghi đè ảnh tại chỗ') && str_contains($html, 'Không lưu bản gốc hoặc bản sao dự phòng') && str_contains($html, 'không thể hoàn tác'), 'Irreversible overwrite without an original backup is clearly explained');
$assert($nodes('//button[@id="brandingRun" and @aria-describedby="brandingOverwriteNote"]') === 1 && $nodes('//*[@id="brandingOverwriteNote"]') === 1, 'Run action is connected to the destructive overwrite warning');
$assert(str_contains($html, 'cùng thư mục, tên và URL') && str_contains($html, 'không thể ghi đè máy chủ nguồn'), 'Same-name storage and remote-origin restriction are explained');
$assert(!str_contains($html, 'Ảnh gốc được giữ lại.') && !str_contains($html, 'Lưu bản có logo riêng'), 'Page must not promise recoverable originals');
$assert(str_contains($html, 'dựa trên lịch sử của công cụ') && str_contains($html, 'Không dùng nhận diện hình ảnh'), 'Counters are explicitly ledger-based, not visual logo recognition');
$assert(str_contains($html, 'hoàn tất yêu cầu hiện tại rồi dừng') && $nodes('//noscript') === 1, 'Safe pause and JavaScript requirement are explained');
$assert($nodes('//*[@id="brandingSyntheticFixture"]') === 0, 'Production rendering contains no synthetic preview code');

$preview = image_brand_admin_synthetic_preview($html);
$assert(str_contains($preview, 'data-endpoint="/__branding-fixture-api"') && !str_contains($preview, 'data-endpoint="/admin/api/medical/image_branding.php"'), 'Exported preview cannot target the real API');
$assert(str_contains($preview, 'data-synthetic-fixture="true"') && str_contains($preview, 'Dữ liệu giả lập'), 'Synthetic data and no-write preview are clearly marked');
$assert(strpos($preview, 'id="brandingSyntheticFixture"') < strpos($preview, '<script src="/assets/js/admin-image-branding.js'), 'Fake fetch is installed before the actual deferred controller');
$assert(str_contains($preview, 'window.fetch = async') && !str_contains($preview, 'nativeFetch'), 'Fixture fetch has no path back to a real network request');
foreach (['c4b9450843808925f063940a-640.webp', 'remote-a07371772354b457ac6afaea-640.webp', 'remote-bb88d31bf48df3430da69e35-320.webp'] as $image) {
    $assert(is_file(dirname(__DIR__) . '/assets/media/variants/' . $image), 'Fixture thumbnail is a local repository asset: ' . $image);
}
$assert(image_brand_admin_fixture_destination('/private/tmp/branding-view-fixture.html') === realpath('/private/tmp') . '/branding-view-fixture.html', 'Preview accepts an HTML artifact inside the temporary directory');
try {
    image_brand_admin_fixture_destination(dirname(__DIR__) . '/branding-view-fixture.html');
    $assert(false, 'Preview export must reject a repository destination');
} catch (RuntimeException $error) {
    $assert(str_contains($error->getMessage(), 'temporary directory'), 'Preview export rejects writes outside temporary artifacts');
}

if (($argv[1] ?? '') === '--render-fixture') {
    $destination = image_brand_admin_fixture_destination((string) ($argv[2] ?? ''));
    if (file_put_contents($destination, $preview, LOCK_EX) === false) {
        throw new RuntimeException('Could not write the isolated preview artifact');
    }
    echo 'Rendered synthetic branding fixture: ' . $destination . "\n";
}

echo "Image branding admin view: {$checks} checks passed (real page and layouts; no session, database, API, or image writes).\n";
