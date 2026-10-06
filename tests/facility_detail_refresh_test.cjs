'use strict';

// Read-only source regressions. No PHP bootstrap, network access or DB writes.
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'co-so-y-te-chi-tiet.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/pages/facility-detail-refresh.css'), 'utf8');
const layout = fs.readFileSync(path.join(root, 'assets/css/pages/facility-detail-layout.css'), 'utf8');
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };
const digest = source => crypto.createHash('sha256').update(source).digest('hex');

// The refresh is intentionally visual-only. These snapshots cover the original
// data normalization/queries/schema and the complete original markup/behavior.
// Update them only alongside an intentional, separately reviewed functional edit.
const documentStart = page.indexOf('\n<!doctype html>\n');
const bodyStart = page.indexOf('  <body>');
check(documentStart > 0 && bodyStart > documentStart, 'The original document boundaries remain intact');
check(digest(page.slice(0, documentStart)) === '5f10fd8a3e34588a0e4a6646c2f1b6c8fd20cba653116b9cee77389708390d63', 'All pre-existing PHP data, query, locale and schema logic is unchanged');
check(digest(page.slice(bodyStart)) === '06c764199a1d4fc6f38f488997c355f397b8bbac4849ff839c77cde9643cd09a', 'All existing page markup, content bindings and interaction scripts are unchanged');
check(digest(layout) === '45c14e8635da61b6c1a7ac63d6e47136bf0c68e427cb72143f78b587ab99edb2', 'The existing desktop and mobile layout stylesheet is unchanged');

const stylesheetLinks = [...page.matchAll(/<link\b[^>]*rel="stylesheet"[^>]*>/g)].map(match => match[0]);
check(stylesheetLinks.at(-1)?.includes('/assets/css/pages/facility-detail-refresh.css?v='), 'The refresh is the final linked stylesheet');
const motionStart = page.indexOf('<style id="facility-detail-motion-polish">');
const motionEnd = page.indexOf('</style>', motionStart);
const refreshStart = page.indexOf('/assets/css/pages/facility-detail-refresh.css?v=');
check(motionStart > 0 && refreshStart > motionEnd && refreshStart < page.indexOf('  </head>'), 'The refresh loads after the legacy layout and motion rules in the head');
check(page.indexOf('/assets/css/pages/facility-detail-layout.css?v=') < refreshStart, 'The retained layout loads before the visual refresh');
check(page.includes("filemtime(__DIR__ . '/assets/css/pages/facility-detail-refresh.css')"), 'Refresh caching uses the local file modification time');

for (const hook of [
  'class="facility-detail site-typo"', 'class="hero-grid"', 'class="hero-gallery"',
  'class="facility-reading-layout"', 'class="sidebar facility-contact-sidebar"',
  'class="facility-reading-main"', 'class="detail-nav"', 'class="facility-info-grid"',
  'id="gioi-thieu"', 'id="dich-vu"', 'id="bang-gia"', 'id="danh-gia"', 'id="dat-lich"',
  'href="#gioi-thieu"', 'href="#dich-vu"', 'href="#bang-gia"', 'href="#danh-gia"', 'href="#dat-lich"',
  'data-hero-gallery-carousel', 'data-hero-gallery-counter', 'data-gallery-index=',
  'id="facilityGalleryLightbox"', 'id="facilityGalleryImage"', 'id="facilityGalleryCounter"',
  'id="facilityGalleryStatus"', 'data-gallery-lightbox-prev', 'data-gallery-lightbox-next',
  'data-gallery-lightbox-close', 'role="dialog" aria-modal="true"',
  'id="reviewServiceFilter"', 'id="reviewSort"', 'id="reviewImageOnly"',
  'id="facilityReviewList"', 'data-facility-id=', 'data-facility-slug=',
  'id="loadMoreReviews" data-page="2"',
]) check(page.includes(hook), `Retained structure or behavior hook: ${hook}`);

for (const binding of [
  'medical_directory_facility_row_by_slug($slug, true)',
  'medical_directory_facility_rows(true, $facilityLanguage)',
  '$facility = facility_detail_normalize($facility);',
  '$facilityPriceTableHtml = facility_detail_compact_price_html(',
  "'@type' => 'MedicalClinic'", "'@type' => 'PostalAddress'", "'@type' => 'AggregateRating'",
  'site_json_ld($facilitySchema)', 'site_localized_path(',
  '/api/medical/facility-reviews.php?', 'facility_id: list.dataset.facilityId',
  'facility_slug: list.dataset.facilitySlug', 'has_images: imageOnly?.checked',
  'href="tel:', 'https://www.google.com/maps/search/?api=1',
  'serviceFilter?.addEventListener(\'change\', refreshReviews)',
  'sortFilter?.addEventListener(\'change\', refreshReviews)',
  'imageOnly?.addEventListener(\'change\', refreshReviews)',
  "event.key === 'Escape'", "event.key === 'Tab'", "event.key === 'ArrowLeft'", "event.key === 'ArrowRight'",
]) check(page.includes(binding), `Retained data, schema or behavior binding: ${binding}`);

const scripts = [...page.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)]
  .map(match => match[1]).filter(script => script.trim());
check(scripts.length === 2, 'The original two inline behavior scripts remain');
for (const script of scripts) {
  const emitted = script.replace(/<\?(?:php|=)[\s\S]*?\?>/g, expression =>
    expression.includes("$facility['gallery']") ? '[]' : expression.includes("$facility['name']") ? '"Fixture facility"' : '{}');
  check(!emitted.includes('<?'), 'All server expressions are replaced for JavaScript syntax checking');
  new vm.Script(emitted, {filename: 'co-so-y-te-chi-tiet.php (emitted JavaScript)'});
  checks++;
}

const cleanCss = css.replace(/\/\*[\s\S]*?\*\//g, '');
const stack = [];
let quote = null;
const pairs = {'}': '{', ')': '(', ']': '['};
for (let index = 0; index < cleanCss.length; index++) {
  const char = cleanCss[index];
  if (char === '\\') { index++; continue; }
  if (quote) { if (char === quote) quote = null; continue; }
  if (char === '"' || char === "'") { quote = char; continue; }
  if ('{(['.includes(char)) stack.push(char);
  else if ('})]'.includes(char)) assert.equal(stack.pop(), pairs[char], `CSS delimiter is matched at offset ${index}`);
}
check(!quote && stack.length === 0, 'CSS quotes and delimiters remain balanced');

const rules = [...cleanCss.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
  .filter(match => !match[1].trim().startsWith('@'));
const selectors = selectorList => {
  const result = [];
  let start = 0;
  let depth = 0;
  for (let index = 0; index < selectorList.length; index++) {
    if ('(['.includes(selectorList[index])) depth++;
    else if (')]'.includes(selectorList[index])) depth--;
    else if (selectorList[index] === ',' && depth === 0) {
      result.push(selectorList.slice(start, index).trim());
      start = index + 1;
    }
  }
  result.push(selectorList.slice(start).trim());
  return result;
};
check(rules.length > 100, 'The complete visual refresh has CSS rules');
check(rules.every(rule => selectors(rule[1]).every(selector => /^\.facility-detail\.site-typo(?:[\s.:#>+~\[]|$)/.test(selector))), 'Every refresh selector is isolated to this facility-detail main');
check(!/@import\b|url\s*\(/i.test(cleanCss), 'The refresh introduces no external assets or dependencies');
// A retained generic .sidebar rule makes its only contact card one-third width
// at intermediate desktop widths. Allow only the exact internal sizing repair;
// the page-level columns, gallery grid, order and visibility remain untouched.
const sidebarSizingRepair = /\.facility-detail\.site-typo\s+\.facility-contact-sidebar\s*\{\s*grid-template-columns\s*:\s*minmax\(\s*0\s*,\s*1fr\s*\)\s*;?\s*\}/g;
check([...cleanCss.matchAll(sidebarSizingRepair)].length === 1, 'The contact sidebar has only its narrowly scoped full-width card sizing repair');
const layoutPreservingCss = cleanCss.replace(sidebarSizingRepair, '');
check(!/\b(?:display|position|order|grid-template(?:-columns|-rows|-areas)?)\s*:/.test(layoutPreservingCss), 'Apart from the sidebar sizing repair, the refresh does not replace layout, reading order or visibility');
check(!/gallery-lightbox/.test(cleanCss), 'The body-mounted gallery viewer retains its original state and layout rules');
check(!/\b(?:visibility|pointer-events)\s*:/.test(cleanCss), 'The refresh does not disable or hide existing controls');
check(/\.hero-actions \.icon-btn\{[^}]*width:44px;[^}]*min-height:44px/.test(cleanCss), 'Phone hero controls provide 44px targets');
check(/\.detail-nav a\{[^}]*min-height:44px/.test(cleanCss), 'Phone section navigation provides 44px targets');
check(/:focus-visible\{[^}]*outline:3px solid[^}]*outline-offset:3px/.test(cleanCss), 'Interactive controls retain visible keyboard focus');
check(/\.motion-reveal:focus-within\{[^}]*opacity:1!important;[^}]*transform:none!important/.test(cleanCss), 'Keyboard focus immediately reveals motion-managed content');
check(/@media\s*\(prefers-reduced-motion:reduce\)[\s\S]*\.motion-reveal\{[^}]*opacity:1!important;[^}]*animation:none!important/.test(cleanCss), 'Reduced-motion users receive fully visible content without reveal animation');

console.log(`Facility detail refresh: ${checks} source checks passed. No DB connection or records modified.`);
