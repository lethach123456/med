'use strict';

// Isolated browser fixture: no website DB, remote requests or content mutations.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const script = read('assets/js/content-links.js');
const styles = [
  'assets/css/core/shared-typography.css',
  'assets/css/core/public-components.css',
  'assets/css/pages/facility-detail-polish.css',
  'assets/css/pages/doctor-profile.css',
  'assets/css/pages/toplist-doctors.css',
  'assets/css/pages/toplist-detail.css',
  'assets/css/pages/handbook-article.css',
  'assets/css/pages/review-articles.css',
  'assets/css/core/content-links.css',
].map(read).join('\n');
const title = 'Top 10 phòng khám nha khoa uy tín tại TP. Rạch Giá, Kiên Giang với đội ngũ bác sĩ chuyên môn cao';
const roots = [
  ['facility-detail site-typo', 'section-copy'],
  ['facility-detail site-typo', 'facility-price-table'],
  ['doctor-profile', 'dp-article'],
  ['doctor-toplist', 'td-prose'],
  ['doctor-toplist', 'td-article-content'],
  ['page site-typo', 'intro'],
  ['page site-typo', 'facility-content'],
  ['page site-typo', 'price-box'],
  ['fixture-handbook', 'hb-article-content'],
  ['fixture-review', 'ra-prose'],
];
const content = index => `
  <p>Nội dung có <a class="inline" href="https://example.org/about" target="_blank" rel="noopener noreferrer">liên kết tham khảo</a> nằm trong câu.</p>
  <ul class="links"><li><!-- safe whitespace --> <a class="internal" href="https://medreview.vn/toplist/nha-khoa-rach-gia"><strong>Top 10</strong> ${title.slice(7)}</a> </li><li><p><a class="external" href="https://example.org/about?x=1&amp;y=2" target="_blank" rel="noopener noreferrer">Website chính thức về cơ sở y tế</a></p></li></ul>
  <p><a class="standalone" href="/co-so-y-te/vi-du">Xem thông tin về cơ sở</a></p>
  <ul class="mixed"><li>Mô tả với <a href="https://example.org/a">link nằm trong câu</a> và nội dung tiếp.</li><li><a href="/cam-nang">Link trong danh sách hỗn hợp</a></li></ul>
  <ol class="steps"><li><a href="/step-1">Bước đầu tiên</a></li><li><a href="/step-2">Bước thứ hai</a></li></ol>
  <p><a class="long" href="https://example.org/${'x'.repeat(140)}">https://example.org/${'x'.repeat(140)}</a></p>
  <p><a class="hash" href="#section-${index}">Xem nội dung</a> <a class="phone" href="tel:0901234567">Gọi điện</a> <a class="email" href="mailto:test@example.org">Email</a></p>
  <p><a class="btn btn-primary" href="https://example.org/book">Đặt lịch</a> <a role="button" href="/action">Thao tác</a></p>
  <p><a class="photo" href="https://example.org/photo"><img alt="Ảnh cơ sở" width="80" height="60"></a></p>
  <pre><a class="code" href="https://example.org/code">Literal code</a></pre>
  <nav class="hb-toc"><a href="/toc">Mục lục</a></nav>
  <p><a class="download" href="/download.pdf" download>Tải tài liệu</a></p>
  <p><a class="production-alias" href="https://www.medreview.vn/co-so-y-te">MedReview</a> <a class="lookalike" href="https://medreview.vn.example.org/">Nguồn ngoài</a></p>
`;
const fixture = `<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}</style><style>body{margin:0;background:#f5f8fc;font:16px/1.75 Arial,sans-serif}.test-section{max-width:860px;margin:24px auto;padding:24px;background:#fff;border:1px solid #e1e8f0;border-radius:16px;box-sizing:border-box}.test-title{margin:0 0 16px;color:#142d50;font-size:20px}@media(max-width:760px){.test-section{margin:12px;padding:18px}}</style></head><body><nav class="outside"><a href="https://example.org/menu">Menu không đổi</a></nav>${roots.map(([outer, inner], index) => `<section class="test-section ${outer}"><h2 class="test-title">${index === 0 ? 'Liên kết tham khảo' : inner}</h2><div class="${inner}" data-test-root="${index}">${content(index)}</div></section>`).join('')}<div class="article"><div class="body"><div class="content" data-test-root="10">${content(10)}</div></div></div><script>${script}</script></body></html>`;

(async () => {
  let checks = 0;
  const check = (condition, message) => { checks++; assert.ok(condition, message); };
  const browser = await chromium.launch({headless: true});
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => route.request().isNavigationRequest()
      ? route.fulfill({contentType: 'text/html', body: fixture}) : route.abort());
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({width, height: 960});
      await page.goto('https://medreview.test/link-fixture');
      await page.waitForFunction(() => document.querySelector('.med-content-link'));
      for (let index = 0; index <= 10; index++) {
        const data = await page.locator(`[data-test-root="${index}"]`).evaluate(el => {
          const link = selector => el.querySelector(selector);
          const styles = getComputedStyle(link('.internal'));
          return {
            block: link('.internal').classList.contains('med-link-block'),
            external: link('.external').classList.contains('is-external-link'),
            internal: !link('.internal').classList.contains('is-external-link'),
            alias: !link('.production-alias').classList.contains('is-external-link'),
            lookalike: link('.lookalike').classList.contains('is-external-link'),
            display: styles.display,
            decoration: styles.textDecorationLine,
            font: parseFloat(styles.fontSize),
            height: link('.internal').getBoundingClientRect().height,
            list: getComputedStyle(link('.links')).listStyleType,
            indent: parseFloat(getComputedStyle(link('.links')).paddingInlineStart),
            mixed: !link('.mixed').classList.contains('med-link-list') && !link('.mixed a').classList.contains('med-link-block'),
            ordered: !link('.steps').classList.contains('med-link-list'),
            inline: !link('.inline').classList.contains('med-link-block') && getComputedStyle(link('.inline')).display === 'inline',
            untouched: ['.hash','.phone','.email','.btn','[role="button"]','.photo','.code','.hb-toc a','.download'].every(selector => !link(selector).classList.contains('med-content-link')),
            href: link('.external').getAttribute('href'),
            rel: link('.external').getAttribute('rel'),
            target: link('.external').getAttribute('target'),
            overflow: link('.long').getBoundingClientRect().right > el.getBoundingClientRect().right + 1,
            label: link('.internal .med-link-label strong')?.textContent,
          };
        });
        check(data.block && data.display === 'grid' && data.decoration === 'none', `${width}/${index}: standalone links are reading cards`);
        check(data.external && data.internal && data.alias && data.lookalike, `${width}/${index}: real outbound and same-site URLs classified correctly`);
        check(data.font >= 14 && data.height >= 44 && data.list === 'none' && data.indent === 0, `${width}/${index}: compact readable aligned touch targets`);
        check(data.mixed && data.ordered && data.inline && data.untouched, `${width}/${index}: inline prose, list semantics, navigation and actions preserved`);
        check(data.href === 'https://example.org/about?x=1&y=2' && data.rel === 'noopener noreferrer' && data.target === '_blank', `${width}/${index}: URLs, followability and new-tab safety unchanged`);
        check(!data.overflow && data.label === 'Top 10', `${width}/${index}: long labels wrap, inline emphasis survives`);
      }
      check(await page.locator('.outside .med-content-link').count() === 0, `${width}: header navigation untouched`);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
      check(!overflow, `${width}: no horizontal page overflow`);
      if (process.env.CONTENT_LINKS_SCREENSHOT && [1440,390].includes(width)) {
        await page.locator('.test-section').first().screenshot({path: `${process.env.CONTENT_LINKS_SCREENSHOT}-${width}.png`});
      }
    }
    const before = await page.locator('[data-test-root="0"]').innerHTML();
    await page.addScriptTag({content: script});
    check(await page.locator('[data-test-root="0"]').innerHTML() === before, 'Running enhancement again does not nest labels or duplicate decoration');
    await page.emulateMedia({reducedMotion: 'reduce'});
    check(await page.locator('.internal').first().evaluate(el => getComputedStyle(el).transitionDuration === '0s'), 'Reduced motion disables link transitions');
    await page.locator('.internal').first().focus();
    check(await page.locator('.internal').first().evaluate(el => getComputedStyle(el).outlineStyle !== 'none'), 'Keyboard focus stays clearly visible');
    console.log(`Content links: ${checks} browser checks passed; no DB or external requests.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
