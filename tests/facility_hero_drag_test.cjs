'use strict';

// Real browser gesture checks against local CSS/controller assets, without DB,
// network requests, the full profile page, or timing-dependent AI/image services.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '..');
const controllerPath = path.join(root, 'assets/js/facility-hero-drag.js');
const cssPath = path.join(root, 'assets/css/pages/facility-hero-gallery.css');
const layoutPath = path.join(root, 'assets/css/pages/facility-detail-layout.css');

function fixture(count = 3) {
  const sources = Array.from({length:count}, (_, index) => `/fixture/photo-${index + 1}.svg`);
  return `<!doctype html><html lang="vi"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
      *{box-sizing:border-box}body{margin:0;padding:16px;background:#f6f8fc;font-family:Arial,sans-serif}
      .facility-detail{max-width:1000px;margin:auto}.hero-grid{width:100%}
      .hero-image{position:relative;display:block;width:100%;padding:0;border:0;overflow:hidden;cursor:pointer;background:#e7edf5}
      /* Actual older profile rules that must not lag, scale, or dim the drag. */
      .gallery-trigger{transition:transform .3s cubic-bezier(.2,.9,.25,1),filter .3s ease}
      .gallery-trigger:active{transform:scale(.982);transition-duration:.12s}
      .gallery-trigger:hover{filter:brightness(1.035)}
      .facility-detail :is(.hero-image img,.thumb img,.service-image img,.mini-gallery img){transition:transform .42s cubic-bezier(.16,1,.3,1),filter .28s ease}
      @media(prefers-reduced-motion:reduce){.gallery-trigger{transition:none!important}}
      .view-all{position:absolute;left:12px;bottom:12px;z-index:4;color:white;background:#142d50;padding:6px 10px;border-radius:20px}
      .after-gallery{height:2000px;margin-top:24px;background:linear-gradient(#e8eef9,#fff)}
    </style>
    <link rel="stylesheet" href="/assets/css/pages/facility-detail-layout.css">
    <link rel="stylesheet" href="/assets/css/pages/facility-hero-gallery.css">
    </head><body><main class="facility-detail"><div class="hero-grid">
      <section class="hero-gallery" data-gallery-layout="${count === 1 ? 'single' : count === 2 ? 'duo' : 'trio'}">
        <div class="hero-gallery-main"><button type="button" class="hero-image gallery-trigger" data-hero-gallery-carousel data-gallery-index="0">
          <img src="${sources[0]}" alt="Ảnh cơ sở 1" loading="eager" fetchpriority="high" draggable="false">
          <span class="view-all"><span data-hero-gallery-counter>1 / ${count}</span></span>
        </button></div>
      </section></div><div class="after-gallery">Nội dung trang để kiểm tra cuộn dọc tự nhiên.</div></main>
    <script src="/assets/js/facility-hero-drag.js"></script><script>
      const hero = document.querySelector('[data-hero-gallery-carousel]');
      const image = hero.querySelector('img');
      const counter = hero.querySelector('[data-hero-gallery-counter]');
      const sources = ${JSON.stringify(sources)};
      const state = {index:0, calls:[], opens:0, disabled:false, fail:false, hold:false, generation:0, releases:[]};
      let controller;
      controller = window.MedReviewHeroDrag.create({
        element:hero, sources,
        getIndex:() => state.index,
        isDisabled:() => state.disabled,
        cancelPending:() => {state.generation++;},
        selectIndex:async (index, beforeCommit) => {
          const generation = state.generation;
          state.calls.push(index);
          await beforeCommit;
          if (state.hold) await new Promise(resolve => state.releases.push(resolve));
          if (generation !== state.generation || state.fail) return false;
          state.index = ((index % sources.length) + sources.length) % sources.length;
          image.src = sources[state.index];
          image.alt = 'Ảnh cơ sở ' + (state.index + 1);
          hero.dataset.galleryIndex = String(state.index);
          counter.textContent = (state.index + 1) + ' / ' + sources.length;
          controller.sync();
          return true;
        }
      });
      hero.addEventListener('click', event => {
        if (controller?.consumeClick()) {event.preventDefault(); return;}
        state.opens++;
      });
      window.heroTest = {hero,image,counter,state,controller};
    </script></body></html>`;
}

(async () => {
  assert.ok(fs.existsSync(controllerPath), 'Hero drag controller exists');
  assert.ok(fs.existsSync(cssPath), 'Actual gallery stylesheet exists');
  let checks = 0;
  const check = (ok, label) => {checks++; assert.ok(ok, label);};
  const browser = await chromium.launch({headless:true});
  try {
    const context = await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,reducedMotion:'no-preference'});
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      if (route.request().isNavigationRequest()) return route.fulfill({contentType:'text/html',body:fixture(Number(url.searchParams.get('count')) || 3)});
      if (/^\/fixture\/photo-\d+\.svg$/.test(url.pathname)) {
        const number = Number(url.pathname.match(/(\d+)\.svg$/)[1]);
        const color = ['#c6daf6','#9ec9ba','#f3c99b'][number % 3];
        return route.fulfill({contentType:'image/svg+xml',body:`<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect width="900" height="600" fill="${color}"/><text x="450" y="320" text-anchor="middle" font-size="100">${number}</text></svg>`});
      }
      if (url.pathname === '/assets/js/facility-hero-drag.js') return route.fulfill({contentType:'application/javascript',body:fs.readFileSync(controllerPath)});
      if (url.pathname === '/assets/css/pages/facility-detail-layout.css') return route.fulfill({contentType:'text/css',body:fs.readFileSync(layoutPath)});
      if (url.pathname === '/assets/css/pages/facility-hero-gallery.css') return route.fulfill({contentType:'text/css',body:fs.readFileSync(cssPath)});
      return route.fulfill({status:404,body:''});
    });
    const read = () => page.evaluate(() => {
      const {hero,image,counter,state} = window.heroTest;
      const rect = image.getBoundingClientRect();
      return {index:state.index,calls:[...state.calls],opens:state.opens,counter:counter.textContent,
        x:rect.x,width:rect.width,dragX:parseFloat(getComputedStyle(hero).getPropertyValue('--hero-drag-x')) || 0,
        dragging:hero.classList.contains('is-dragging'),settling:hero.classList.contains('is-settling'),
        ready:hero.hasAttribute('data-hero-drag-ready'),panels:hero.querySelectorAll('[data-hero-drag-panel]').length,
        scrollY:window.scrollY};
    });
    const load = async (count = 3) => {
      await page.goto(`https://medreview.test/hero-drag-fixture?count=${count}`);
      await page.waitForFunction(() => window.heroTest && window.heroTest.image.complete && window.heroTest.image.naturalWidth > 0);
      check(errors.length === 0, 'Fixture/controller initialize without browser errors');
    };
    const touch = (type, points = [], changed = points) => page.evaluate(({type,points,changed}) => {
      const hero = window.heroTest.hero;
      const make = ([x,y,id = 1]) => new Touch({identifier:id,target:hero,clientX:x,clientY:y,pageX:x + scrollX,pageY:y + scrollY,radiusX:1,radiusY:1,force:1});
      const event = new TouchEvent(type,{bubbles:true,cancelable:true,touches:points.map(make),targetTouches:points.map(make),changedTouches:changed.map(make)});
      hero.dispatchEvent(event);
      return {prevented:event.defaultPrevented};
    },{type,points,changed});
    const start = (x = 220, y = 140) => touch('touchstart',[[x,y]]);
    const move = (x, y = 140) => touch('touchmove',[[x,y]]);
    const end = (x, y = 140) => touch('touchend',[],[[x,y]]);
    const idle = () => page.waitForFunction(() => {
      const hero = window.heroTest.hero;
      return !hero.classList.contains('is-dragging') && !hero.classList.contains('is-settling')
        && Math.abs(parseFloat(getComputedStyle(hero).getPropertyValue('--hero-drag-x')) || 0) < .5;
    });
    const waitIndex = index => page.waitForFunction(index => window.heroTest.state.index === index,index);

    await load();
    const before = await read();
    check(before.ready && before.panels === 2, 'Multiple photos mount two decorative neighboring panels');
    check(await page.locator('[data-hero-gallery-carousel] > img:not([data-hero-drag-panel])').count() === 1,
      'Original hero image remains a direct child for initial loading/lightbox contracts');
    await start(); await page.waitForTimeout(80); await move(120);
    const held = await read();
    check(held.dragging && Math.abs(held.dragX + 100) < 1, 'Held horizontal gesture writes the exact -100px displacement');
    check(Math.abs(held.x - (before.x - 100)) < 2, 'Actual visible photo follows the finger before release, not only a CSS variable');
    check(held.index === 0 && held.counter === '1 / 3', 'Finger movement does not prematurely commit metadata/counter');
    check(await page.locator('.view-all').evaluate(el => Math.abs(el.getBoundingClientRect().x - 26) < 1),
      'Counter overlay stays stationary while image panels move');
    if (process.env.FACILITY_HERO_DRAG_SCREENSHOT) await page.locator('.hero-gallery').screenshot({path:process.env.FACILITY_HERO_DRAG_SCREENSHOT});
    await end(120);
    await page.locator('[data-hero-gallery-carousel]').dispatchEvent('click');
    check((await read()).opens === 0, 'Synthetic click immediately following swipe does not open lightbox');
    await waitIndex(1); await idle();
    check((await read()).counter === '2 / 3', 'Successful snap commits the next photo and matching counter');
    await page.waitForTimeout(650);
    await page.locator('[data-hero-gallery-carousel]').dispatchEvent('click');
    check((await read()).opens === 1, 'A later deliberate tap still opens the lightbox');

    await load();
    await start(); await page.waitForTimeout(240); await move(196); await page.waitForTimeout(120); await end(196); await idle();
    check((await read()).index === 0 && (await read()).calls.length === 0, 'Small slow drag snaps back without selecting another photo');

    await load();
    await start(); await page.waitForTimeout(16); await move(188); await end(188); await waitIndex(1); await idle();
    check((await read()).index === 1, 'Short quick flick advances even when below the distance threshold');

    await load();
    await start(); await move(125); await page.waitForTimeout(60); await move(310); await end(310);
    await waitIndex(2); await idle();
    check((await read()).counter === '3 / 3', 'Reversing direction during one drag chooses the final rightward/previous direction');

    await load();
    await page.evaluate(() => {window.heroTest.state.fail = true;});
    await start(); await move(100); await end(100); await idle();
    check((await read()).calls.length === 1 && (await read()).index === 0 && (await read()).counter === '1 / 3',
      'Failed selection animates back to the committed image without advancing its counter');

    await load();
    await page.evaluate(() => {window.heroTest.state.hold = true;});
    await start(); await move(100); await end(100);
    await page.waitForFunction(() => window.heroTest.state.releases.length === 1);
    check((await read()).index === 0 && (await read()).counter === '1 / 3', 'Slow target cannot commit before its bitmap is ready');
    check(await page.locator('[data-hero-gallery-carousel]').evaluate(el => el.classList.contains('is-awaiting-image')
      && getComputedStyle(el, '::after').content.includes('Loading photo')),
      'A slow target shows an explicit localized loading placeholder, not an unexplained blank cover');
    await start(); await touch('touchcancel',[],[[220,140]]); await idle();
    await page.evaluate(() => {window.heroTest.state.hold = false;window.heroTest.state.releases.splice(0).forEach(resolve => resolve());});
    await page.waitForTimeout(40);
    check((await read()).index === 0 && (await read()).dragX === 0 && (await read()).counter === '1 / 3',
      'Regrabbing and cancelling a slow pending selection invalidates its eventual late commit');
    check(!await page.locator('[data-hero-gallery-carousel]').evaluate(el => el.classList.contains('is-awaiting-image')),
      'Cancelling an unready target also clears its loading placeholder');

    await load();
    await start(); const vertical = await move(230,230); const verticalState = await read(); await end(230,230); await idle();
    check(!vertical.prevented && !verticalState.dragging && verticalState.dragX === 0 && verticalState.calls.length === 0,
      'Vertical gesture is never prevented, translated, or interpreted as photo navigation');

    await load();
    await start(); await move(120); await touch('touchcancel',[],[[120,140]]); await idle();
    check((await read()).index === 0 && (await read()).calls.length === 0, 'Touch cancellation restores the committed image without navigation');
    await end(120); check((await read()).calls.length === 0, 'Late touchend after cancellation cannot revive the old gesture');

    await load();
    await start(); await move(120);
    await touch('touchstart',[[120,140,1],[180,180,2]],[[180,180,2]]);
    await touch('touchmove',[[80,140,1],[200,180,2]]); await touch('touchend',[],[[80,140,1],[200,180,2]]); await idle();
    check((await read()).index === 0 && (await read()).calls.length === 0, 'A second touch aborts horizontal dragging and leaves pinch gestures alone');

    await load();
    await start(); await move(120); await page.setViewportSize({width:900,height:844}); await idle();
    check((await read()).dragX === 0 && (await read()).calls.length === 0, 'Resize resets an in-flight drag without selecting a photo');
    await start(); await move(100); await end(100); await idle();
    check((await read()).calls.length === 0, 'Desktop gestures do not activate mobile drag behavior');
    await page.setViewportSize({width:390,height:844});

    await load(2);
    await start(); await move(100); await end(100); await waitIndex(1); await idle();
    await start(); await move(100); await end(100); await waitIndex(0); await idle();
    check((await read()).counter === '1 / 2', 'Two-image gallery wraps cleanly despite both neighboring panels sharing a source');

    await load(1);
    await start(); await move(100); await end(100); await idle();
    check((await read()).calls.length === 0 && (await read()).dragX === 0, 'Single-photo gallery never starts an empty carousel gesture');

    await page.emulateMedia({reducedMotion:'reduce'}); await load();
    await start(); await move(100); await end(100); await waitIndex(1); await idle();
    check((await read()).counter === '2 / 3', 'Reduced-motion mode still commits drag navigation without a lingering animation');

    // A real browser touch stream verifies that the passive vertical path works
    // with touch-action, beyond synthetic event.defaultPrevented assertions.
    await page.emulateMedia({reducedMotion:'no-preference'}); await load();
    const session = await context.newCDPSession(page);
    const position = await page.locator('[data-hero-gallery-carousel]').boundingBox();
    const centerX = Math.round(position.x + position.width / 2);
    const startY = Math.round(position.y + position.height * .8);
    const dispatch = (type, y) => session.send('Input.dispatchTouchEvent',{type,touchPoints:type === 'touchEnd' ? [] : [{x:centerX,y,id:1,radiusX:1,radiusY:1,force:1}]});
    await dispatch('touchStart',startY);
    for (let step=1;step<=6;step++) {await dispatch('touchMove',startY-step*18);await page.waitForTimeout(24);}
    await dispatch('touchEnd',0); await page.waitForTimeout(250);
    check((await read()).scrollY > 40 && (await read()).index === 0, 'Real touch vertical swipe scrolls the page without changing photo');
    await session.detach();
    check(errors.length === 0, 'All gesture paths complete without uncaught browser errors');
    console.log(`Facility hero drag: ${checks} browser checks passed; real CSS, mobile touch/desktop, reduced motion, no DB/network.`);
    await context.close();
  } finally {await browser.close();}
})().catch(error => {console.error(error);process.exitCode=1;});
