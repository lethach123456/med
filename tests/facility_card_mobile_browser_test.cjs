'use strict';

// Actual SSR + AJAX renderers, real CSS cascade; isolated fixtures and local assets only.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '..');
const fixtures = [
  {name: 'Nha Khoa Hoàn Mỹ Hải Phòng', slug: 'hoan-my-fixture', category: 'Nha khoa', city: 'Hải Phòng', address: '120 Lý Thường Kiệt, Hồng Bàng, Hải Phòng', subtitle: 'Hệ thống nha khoa kỹ thuật cao uy tín lâu năm tại Hải Phòng', image: '/fixture/clinic.webp', verified: true, rating: 5, reviews_count: 55, rating_basis: 'stored_reviews', services: ['Chỉnh nha - Niềng răng kỹ thuật số không nhổ răng', 'Cấy ghép Implant phục hình mất răng chịu lực tức thì', 'Thẩm mỹ răng sứ và mặt dán sứ Veneer bảo tồn men răng', 'Nhổ răng khôn bằng công nghệ siêu âm']},
  {name: 'Trung tâm chăm sóc răng hàm mặt chuyên sâu & phục hình thẩm mỹ quốc tế', slug: 'long-fixture', category: 'Nha khoa chuyên sâu', city: 'Thành phố Hồ Chí Minh', address: 'Tầng 2, số 123 đường Nguyễn Thị Minh Khai, phường Bến Thành, Thành phố Hồ Chí Minh', subtitle: 'Thông tin dài để kiểm tra khả năng xuống dòng mà không làm tràn ngang thẻ hoặc cắt mất địa chỉ của cơ sở.', image: '/fixture/broken.webp', verified: true, rating: 4.5, reviews_count: 128, rating_basis: 'external_aggregates', services: ['Khám chuyên sâu', 'Niềng răng', 'Bọc răng sứ']},
  {name: 'Phòng khám', slug: 'sparse-fixture', category: 'Cơ sở y tế', services: [], rating: 0, reviews_count: 0},
];
const php = `
  require 'medical_facility_directory_view.php';
  require 'medical_public_assets.php';
  function site_localized_path(string $path, string $locale): string { return ($locale === 'en' ? '/en' : '') . $path; }
  function medical_public_facility_path(): string { return '/co-so-y-te'; }
  $locale=$argv[2]; $isEnglish=$locale==='en';
  $items=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);
  $filters=['q'=>'','city'=>'','category'=>'','service'=>'','min_rating'=>'','sort'=>'recommended'];
  $cities=['Hải Phòng'];$categories=['Nha khoa'];$services=[];$cityCounts=['Hải Phòng'=>3];
  $directoryStats=['facilities'=>3,'reviews'=>183];
  $initial=['items'=>$items,'total'=>3,'page'=>1,'total_pages'=>1];
  echo '<!doctype html><html lang="'.$locale.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
  foreach (['/assets/css/core/shared-typography.css','/assets/css/pages/facility-directory.css','/assets/css/core/directory-service-labels.css','/assets/css/pages/facility-directory-mobile.css','/assets/vendor/@phosphor-icons/web/2.1.1/phosphor.css', ...medical_public_shell_sources()] as $css) echo '<link rel="stylesheet" href="'.$css.'">';
  echo '</head><body class="fd-page">';
  require 'Tem/facility-directory.php';
  echo '<script src="/assets/js/facility-directory.js" defer></script></body></html>';
`;
const html = locale => execFileSync('php', ['-r', php, JSON.stringify(fixtures), locale], {cwd: root, encoding: 'utf8'});
const photo = fs.readFileSync(path.join(root, 'uploads/library/co-so-y-te/facility-74-benh-vien-rang-ham-mat-worldwide-244a-cong-quynh-phuong-ben-thanh-tp-ho-chi-minh/source-a3a61b832f53e3ee8f34.webp'));
const types = {'.css':'text/css','.js':'application/javascript','.woff2':'font/woff2','.woff':'font/woff','.ttf':'font/ttf'};

(async () => {
  let checks = 0;
  const check = (ok, label) => { checks++; assert.ok(ok, label); };
  const browser = await chromium.launch({headless:true});
  try {
    const page = await browser.newPage({reducedMotion:'reduce'});
    let locale = 'vi';
    const serve = route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/api/medical/facilities.php') return route.fulfill({json:{ok:true,items:fixtures,paging:{page:1,total:3,total_pages:1}}});
      if (route.request().isNavigationRequest()) return route.fulfill({contentType:'text/html',body:html(locale)});
      if (url.pathname === '/fixture/clinic.webp') return route.fulfill({contentType:'image/webp',body:photo});
      const file = path.resolve(root, '.' + url.pathname);
      if (file.startsWith(root + '/assets/') && fs.existsSync(file) && fs.statSync(file).isFile()) return route.fulfill({contentType:types[path.extname(file)] || 'application/octet-stream',body:fs.readFileSync(file)});
      return route.fulfill({status:404,body:''});
    };
    await page.route('**/*', serve);
    const geometry = card => card.evaluate(el => {
      const measure = selector => {const node=el.querySelector(selector);if(!node)return null;const r=node.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,right:r.right,bottom:r.bottom};};
      return {media:measure('.fd-media'),main:measure('.fd-card-main'),title:measure('h2'),address:measure('.fd-address'),category:measure('.fd-card-category'),summary:measure('.fd-summary'),footer:measure('.fd-card-footer'),card:{w:el.clientWidth,scroll:el.scrollWidth}};
    });
    for (locale of process.env.FACILITY_CARD_PREVIEW ? ['vi'] : ['vi','en']) {
      for (const width of process.env.FACILITY_CARD_PREVIEW ? [390] : [320,360,375,390,430,600]) {
        await page.setViewportSize({width,height:1000});
        await page.goto('https://medreview.test/' + (locale==='en'?'en/':'') + 'co-so-y-te');
        await page.evaluate(() => document.fonts.ready);
        for (const phase of ['SSR','AJAX']) {
          if (phase==='AJAX') {
            await page.locator('#facilityDirectoryFilter').evaluate(form => form.requestSubmit());
            await page.waitForFunction(() => document.getElementById('facilityDirectoryStatus').textContent.includes('3'));
          }
          const cards = page.locator('.fd-card');
          check(await cards.count()===3, `${locale}/${width}/${phase}: real renderer displays all fixture cards`);
          for (let index=0;index<3;index++) {
            const card = cards.nth(index);
            const g = await geometry(card);
            check(Math.abs(g.media.y-g.main.y)<1 && Math.abs(g.media.h-g.main.h)<1, `${locale}/${width}/${phase}/${index}: image and identity have matching top/bottom`);
            check(g.media.h>=128 && g.media.w<=104.5, `${locale}/${width}/${phase}/${index}: taller, compact portrait`);
            if (g.address) check(g.address.x>g.media.right && g.address.bottom<=g.media.bottom+1 && g.address.y>=g.title.bottom-1, `${locale}/${width}/${phase}/${index}: full address stays to the right below name`);
            if (g.summary) check(g.summary.y>=g.media.bottom && g.summary.x<g.main.x, `${locale}/${width}/${phase}/${index}: summary uses full row below identity`);
            check(g.card.scroll<=g.card.w+1, `${locale}/${width}/${phase}/${index}: no card overflow`);
            check(await card.locator('.fd-profile-link').evaluate(el => el.getBoundingClientRect().height>=44), `${locale}/${width}/${phase}/${index}: profile action is touch friendly`);
          }
          const first=cards.first();
          check(await first.locator('.fd-service:visible').count()===3, `${locale}/${width}/${phase}: only three services before expansion`);
          const more=first.locator('[data-service-tags-more]');
          await more.click();
          check(await more.getAttribute('aria-expanded')==='true' && await first.locator('.fd-service:visible').count()===4, `${locale}/${width}/${phase}: +N reveals all services`);
          await more.click();
          check(await more.getAttribute('aria-expanded')==='false' && await first.locator('.fd-service:visible').count()===3, `${locale}/${width}/${phase}: collapse hides extras again`);
          check(await page.evaluate(() => document.documentElement.scrollWidth<=innerWidth+1), `${locale}/${width}/${phase}: no viewport overflow`);
          if (process.env.FACILITY_CARD_SCREENSHOT && locale==='vi' && phase==='SSR' && [375,390,600].includes(width)) await first.screenshot({path:`${process.env.FACILITY_CARD_SCREENSHOT}-${width}.png`});
        }
      }
    }
    locale='vi';
    for (const width of [900,1280]) {
      await page.setViewportSize({width,height:1100});
      await page.goto('https://medreview.test/co-so-y-te');
      await page.evaluate(() => document.fonts.ready);
      const card=page.locator('.fd-card').first();
      const current=await geometry(card);
      check(await card.locator('.fd-card-main').evaluate(el => getComputedStyle(el).display==='contents'), `${width}: desktop identity wrapper stays layout-transparent`);
      await card.evaluate(el => {const main=el.querySelector('.fd-card-main');main.after(main.querySelector('.fd-address'));});
      const previous=await geometry(card);
      for (const key of ['media','title','address','category','summary','footer']) check(['x','y','w','h'].every(axis=>Math.abs(current[key][axis]-previous[key][axis])<1), `${width}/${key}: desktop geometry identical to previous DOM`);
    }
    const touch = await browser.newPage({viewport:{width:390,height:1000},isMobile:true,hasTouch:true,reducedMotion:'reduce'});
    await touch.route('**/*',serve);
    await touch.goto('https://medreview.test/co-so-y-te');
    await touch.evaluate(() => document.fonts.ready);
    const touchCard=touch.locator('.fd-card').first();
    const touchGeometry=await geometry(touchCard);
    check(Math.abs(touchGeometry.media.h-touchGeometry.main.h)<1 && touchGeometry.address.x>touchGeometry.media.right, 'Touch phone: image and full identity remain side by side at equal height');
    check(await touchCard.locator('.fd-verified').evaluate(el => {const r=el.getBoundingClientRect();return r.width>=44&&r.height>=44;}), 'Touch phone: verification link retains a 44px hit target');
    check(await touch.evaluate(() => document.documentElement.scrollWidth<=innerWidth+1), 'Touch phone: no overflow with coarse-pointer styles');
    await touch.close();
    console.log(`Facility mobile cards: ${checks} browser checks passed; SSR/AJAX, ${process.env.FACILITY_CARD_PREVIEW ? '390/900/1280px, VI preview' : '320–1280px, VI/EN'}, touch phone, no DB/network.`);
  } finally {await browser.close();}
})().catch(error => {console.error(error);process.exitCode=1;});
