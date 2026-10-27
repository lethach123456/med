'use strict';

// All requests are intercepted: this test sends no analytics traffic.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '..');
const fixture = host => execFileSync('php', ['-r', `
  require 'site_analytics.php';
  $_SERVER = ['HTTP_HOST' => '${host}', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php'];
  $_GET = [];
  echo '<!doctype html><html><head><meta charset="utf-8"><script>window.dataLayer=[{event:"fixture-existing"}];</script>', site_tag_manager_head(), site_tag_manager_head(),
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
          return route.fulfill({contentType: 'application/javascript', body: 'window.__googleTagFixtureLoaded=true;'});
        }
        return route.fulfill({contentType: 'text/html', body: fixture(url.host)});
      });
      const page = await context.newPage();
      await page.goto('https://medreview.vn/');
      check(await page.locator('iframe').count() === 0, `${javaScriptEnabled}: no retired GTM noscript iframe`);
      check(await page.locator('script[src*="/gtm.js"]').count() === 0, `${javaScriptEnabled}: no retired GTM loader`);
      if (javaScriptEnabled) {
        check(requests.length === 1, 'Exactly one Google tag request, including repeated PHP helper calls');
        check(requests[0].searchParams.get('id') === 'G-RS82TMHNPT' && requests[0].pathname === '/gtag/js', 'Exact user GA4 measurement ID and Google tag endpoint');
        check(await page.evaluate(() => window.__googleTagFixtureLoaded === true), 'Async Google tag script loads');
        const dataLayer = await page.evaluate(() => window.dataLayer.map(entry => entry.event ? {event: entry.event} : Array.from(entry)));
        check(dataLayer.filter(entry => entry.event === 'fixture-existing').length === 1, 'Pre-existing dataLayer event survives bootstrap');
        check(dataLayer.filter(entry => entry[0] === 'js').length === 1, 'One Google tag bootstrap command');
        const configs = dataLayer.filter(entry => entry[0] === 'config');
        check(configs.length === 1 && configs[0][1] === 'G-RS82TMHNPT', 'Exactly one GA4 configuration with the correct measurement ID');
        check(await page.locator('head script[src*="googletagmanager.com/gtag/js"]').count() === 1, 'Google tag loader is in head');
      } else {
        check(requests.length === 0, 'JavaScript-disabled visitors send no Google tag or noscript request');
      }
      requests.length = 0;
      await page.goto('https://localhost/');
      check(requests.length === 0, `${javaScriptEnabled}: local previews send no Google tag request`);
      await context.close();
    }
    console.log(`Site analytics: ${checks} browser checks passed; Google requests intercepted, no live tracking.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
