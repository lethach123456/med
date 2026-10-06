'use strict';

// Source-only regressions: no PHP bootstrap, DB connection, browser or writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const home = fs.readFileSync(path.join(root, 'Tem/home.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/pages/home-polish.css'), 'utf8');
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };

const scripts = [...home.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)]
  .map(match => match[1]).filter(script => script.trim());
check(scripts.length === 1, 'Current homepage has one inline behavior script');
for (const script of scripts) {
  const emitted = script.replace(/<\?(?:php|=)[\s\S]*?\?>/g, expression =>
    expression.includes('$popularTerms') ? '["Nha khoa Hà Nội","Invisalign"]' : '"fixture"');
  check(!emitted.includes('<?'), 'All server expressions are replaced for syntax checking');
  new vm.Script(emitted, {filename: 'Tem/home.php (emitted inline JavaScript)'});
  checks++;
}
const behavior = scripts.join('\n');
check(home.indexOf('/home-concept-live.css?v=') < home.indexOf('/home-polish.css?v='), 'Polish loads after the existing homepage styles');
check(home.includes('filemtime($homePolishStylesheet)'), 'Polish uses file-based cache versioning');
for (const hook of ['class="med-home hc-home site-typo"', 'data-medical-search-input', 'data-medical-search-results', 'id="home-facility-grid"', 'id="home-toplist-grid"']) {
  check(home.includes(hook), `Existing homepage hook is retained: ${hook}`);
}
check(!home.includes('home-next.css') && !home.includes('Tem/home-next.php'), 'Current home does not load the ignored preview');
check(/const motionQuery = matchMedia\('\(prefers-reduced-motion: reduce\)'\)/.test(behavior), 'Motion preference is a live media query');
check(behavior.includes("motionQuery.addEventListener('change', syncMotion)"), 'Runtime preference changes synchronize motion');
check(behavior.includes('document.hidden || motionQuery.matches') && behavior.includes('revealObserver?.disconnect()'), 'Hidden/reduced pages pause decoration and finish reveals');
check(behavior.includes("home.addEventListener('focusin'") && css.includes('.reveal:focus-within{opacity:1!important;transform:none!important}'), 'Keyboard focus makes reveal content immediately visible');
check(behavior.includes('performance.now() + 4800') && behavior.includes('performance.now() >= deadline'), 'Typewriter has a finite demonstration budget');
check(behavior.includes('Math.min(delay, deadline - performance.now())'), 'Typewriter hold cannot exceed its motion deadline');
check(behavior.includes('!inputVisible') && behavior.includes('document.activeElement === input') && behavior.includes("input.addEventListener('input', stop)"), 'Typewriter pauses offscreen, on focus and during typing');
check(behavior.includes("home.classList.add('motion-managed')") && behavior.includes("'.hero-visual,.community-art'"), 'Decoration visibility is observed without changing markup');
check(behavior.includes("searchPanel.contains(document.activeElement)") && behavior.includes("key:'Escape', bubbles:true") && behavior.includes("searchPanel.dataset.homeSearchAutoScrolled = 'false'"), 'Search close uses shared Escape handling and resets mobile autoscroll');
check(behavior.includes("searchPanel.addEventListener('focusout'") && behavior.includes('if (searchPanel.classList.contains(\'is-open\') && !searchPanel.contains(document.activeElement)) closeSearch()'), 'Search cannot leave or reopen the blur layer behind lost focus');
check(behavior.includes('if (frame) return; frame = requestAnimationFrame') && behavior.includes("rail.addEventListener('scroll', scheduleUpdate, {passive:true})"), 'Rail scroll work is passive and coalesced per frame');
check(behavior.includes('const positions = items.map(') && behavior.includes('if (lastActive === active && lastLength === items.length) return'), 'Rail geometry is batched and unchanged UI avoids repeated writes');
check(behavior.includes("motionQuery.matches ? 'instant' : 'smooth'"), 'Rail movement reads the current motion preference');
check(home.includes("$isEnglish ? 'Previous' : 'Trước'") && home.includes("$isEnglish ? 'Next' : 'Tiếp'"), 'Rail button names remain bilingual');

const cleanCss = css.replace(/\/\*[\s\S]*?\*\//g, '');
const rules = [...cleanCss.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
  .filter(match => !match[1].trim().startsWith('@'));
check(rules.length > 20 && rules.every(match => /^(?:body\.site-home:not\(\.site-home-next\)|html:has\(body\.site-home:not\(\.site-home-next\)\))/.test(match[1].trim())), 'Every polish selector is isolated to the current homepage');
check(css.includes(':is(.facility-card,.toplist-card){animation:none}'), 'Reveal cards do not also run the legacy entry animation');
check(css.includes('.toplist-card{display:flex;flex-direction:column;justify-content:flex-end;') && css.includes('.toplist-card h3{color:#fff;margin:0;'), 'Toplist cover titles stay legible and anchored below legacy layout overrides');
check(css.includes('animation-iteration-count:1') && css.includes(':is(.community-badge,.heart-stamp){animation:none;translate:0 0}'), 'Decorations are finite and nested badges no longer compete');
check(css.includes('animation-play-state:paused') && css.includes(':not(.motion-in-view)'), 'Offscreen/hidden decorative motion pauses');
check(css.includes('.rail-navigation button{width:44px;height:44px}') && css.includes('.city-filters button{min-height:44px}'), 'Mobile rail and filter controls preserve touch targets');
check(/@media\(prefers-reduced-motion:reduce\)[\s\S]*\.home-search-backdrop\{animation:none!important;transition:none!important\}/.test(css), 'Body-mounted search backdrop explicitly honors reduced motion');

console.log(`Home polish: ${checks} source checks passed. No DB connection or records modified.`);
