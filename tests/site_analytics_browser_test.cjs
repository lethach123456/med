'use strict';

// Both Google endpoints are intercepted: this test sends no analytics traffic.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '..');
const fixture = host => execFileSync('php', ['-r', `
  require 'site_analytics.php';
  $_SERVER = ['HTTP_HOST' => '${host}', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php'];
  $_GET = [];
  echo '<!doctype html><html><head><meta charset="utf-8">', site_tag_manager_head(), site_tag_manager_head(),
    '</head><body>', site_tag_manager_body(), site_tag_manager_body(), '<main>MedReview</main></body></html>';
`], {cwd: root, encoding: 'utf8'});

(async () => {
  const browser = await chromium.launch({headless: true});
  let checks = 0;
  const check = (ok, label) => { checks++; assert.ok(ok, label); };
  try {
    for (const javaScriptEnabled of [true, false]) {
      const context = await browser.newContext({javaScriptEnabled});
      const requests = [];
      await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (url.hostname === 'www.googletagmanager.com') {
          requests.push(url);
          return route.fulfill({contentType: url.pathname === '/gtm.js' ? 'application/javascript' : 'text/html', body: url.pathname === '/gtm.js' ? 'window.__gtmFixtureLoaded=true;' : '<!doctype html><html></html>'});
        }
        return route.fulfill({contentType: 'text/html', body: fixture(url.host)});
      });
      const page = await context.newPage();
      await page.goto('https://medreview.vn/');
      check(requests.length === 1, `${javaScriptEnabled}: exactly one GTM request, including repeated PHP helper calls`);
      check(requests[0].searchParams.get('id') === 'GTM-WQKB3F22', `${javaScriptEnabled}: exact user container ID`);
      check(requests[0].pathname === (javaScriptEnabled ? '/gtm.js' : '/ns.html'), `${javaScriptEnabled}: correct script/noscript behavior`);
      if (javaScriptEnabled) {
        check(await page.evaluate(() => window.__gtmFixtureLoaded === true), 'Async container script loads');
        check(await page.evaluate(() => window.dataLayer.filter(event => event.event === 'gtm.js').length === 1), 'One bootstrap event, not duplicate page views');
        check(await page.locator('head script[src*="googletagmanager.com/gtm.js"]').count() === 1, 'Container loader is in head');
      } else {
        check(await page.locator('body > noscript > iframe').count() === 1, 'Noscript fallback is immediately inside body');
        check(await page.locator('body > noscript > iframe').isVisible() === false, 'Fallback iframe cannot affect page layout');
      }
      requests.length = 0;
      await page.goto('https://localhost/');
      check(requests.length === 0, `${javaScriptEnabled}: local previews send no GTM request`);
      await context.close();
    }
    console.log(`Site analytics: ${checks} browser checks passed; Google requests intercepted, no live tracking.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
