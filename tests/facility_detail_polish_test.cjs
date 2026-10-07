'use strict';

// Read-only source tests: no PHP bootstrap, network request or DB connection.
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'co-so-y-te-chi-tiet.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/pages/facility-detail-polish.css'), 'utf8');
const layout = fs.readFileSync(path.join(root, 'assets/css/pages/facility-detail-layout.css'), 'utf8');
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };
const digest = source => crypto.createHash('sha256').update(source).digest('hex');

// Retain the original backend/layout contract. The user-requested profile move
// and readable disclosure deliberately update the body contract; data fields and
// all gallery/review behavior checks below remain protected.
const documentStart = page.indexOf('\n<!doctype html>\n');
const bodyStart = page.indexOf('  <body>');
check(documentStart > 0 && bodyStart > documentStart, 'Original document boundaries remain intact');
check(digest(page.slice(0, documentStart)) === '5f10fd8a3e34588a0e4a6646c2f1b6c8fd20cba653116b9cee77389708390d63', 'All backend data, normalization, query, locale and schema logic matches 278e4e7');
check(digest(page.slice(bodyStart)) === 'db3a69585d008d50234307f95e1ede6a2f7f65e1f9f51407fd4fa4bfb504c8f7', 'Body matches the reviewed preview/mobile-nav hooks, preserving gallery/review scripts');
check(digest(layout) === '45c14e8635da61b6c1a7ac63d6e47136bf0c68e427cb72143f78b587ab99edb2', 'The original layout stylesheet is unchanged');
const polishHook = "    <link rel=\"stylesheet\" href=\"/assets/css/pages/facility-detail-polish.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/facility-detail-polish.css'); ?>\">";
check(page.split(polishHook).length === 2, 'Exactly one filemtime-versioned polish hook exists');
check(page.includes('/assets/css/pages/facility-profile-disclosure.css?v=') && page.includes('/assets/js/facility-profile-disclosure.js?v='), 'The isolated disclosure assets are filemtime-versioned');
check(!page.includes('<details class="facility-info-disclosure">'), 'Profile is readable by default, not hidden in a closed details element');
check(page.indexOf('data-facility-profile-content') < page.indexOf('data-facility-read-more'), 'Read-more control follows the readable content');
const stylesheetLinks = [...page.matchAll(/<link\b[^>]*rel="stylesheet"[^>]*>/g)].map(match => match[0]);
check(stylesheetLinks.at(-2)?.includes('/assets/css/pages/facility-detail-polish.css?v='), 'Polish follows the original layout and profile disclosure stylesheet');
check(stylesheetLinks.at(-1)?.includes('/assets/css/pages/facility-detail-nav.css?v='), 'Mobile reading tabs load last without changing desktop styles');
const motionStart = page.indexOf('<style id="facility-detail-motion-polish">');
const motionEnd = page.indexOf('</style>', motionStart);
const polishStart = page.indexOf(polishHook);
check(motionStart > 0 && polishStart > motionEnd && polishStart < page.indexOf('  </head>'), 'Polish loads after retained layout/motion rules in the head');
check(page.indexOf('/assets/css/pages/facility-detail-layout.css?v=') < polishStart, 'Original layout loads before polish');
check(!page.includes('/facility-detail-refresh.css'), 'The reverted redesign is not loaded');

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
]) check(page.includes(hook), `Retained structure/interaction hook: ${hook}`);

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
  "event.key === 'Escape'", "event.key === 'Tab'", "event.key === 'ArrowLeft'", "event.key === 'ArrowRight'",
]) check(page.includes(binding), `Retained data/schema/behavior binding: ${binding}`);

const scripts = [...page.matchAll(/<script\b(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/g)]
  .map(match => match[1]).filter(script => script.trim());
check(scripts.length === 2, 'The original two inline behavior scripts remain');
for (const script of scripts) {
  const emitted = script.replace(/<\?(?:php|=)[\s\S]*?\?>/g, expression =>
    expression.includes("$facility['gallery']") ? '[]' : expression.includes("$facility['name']") ? '"Fixture facility"' : '{}');
  check(!emitted.includes('<?'), 'PHP expressions are replaced for JavaScript syntax checking');
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
  else if ('})]'.includes(char)) assert.equal(stack.pop(), pairs[char], `Matched CSS delimiter at offset ${index}`);
}
check(!quote && stack.length === 0, 'CSS quotes and delimiters remain balanced');
check(!/@(?!media\b)/i.test(cleanCss), 'Polish adds no imports, fonts, animations or other new at-rule behavior');
const rules = [...cleanCss.matchAll(/([^{}]+)\{([^{}]*)\}/g)]
  .filter(match => !match[1].trim().startsWith('@'));
const narrowReviewMedia = [...cleanCss.matchAll(/@media\s*\(\s*max-width\s*:\s*380px\s*\)\s*\{/g)];
check(narrowReviewMedia.length === 1, 'Exactly one <=380px review readability adjustment exists');
const narrowReviewStart = narrowReviewMedia[0].index + narrowReviewMedia[0][0].length;
let narrowReviewEnd = narrowReviewStart;
let narrowDepth = 1;
while (narrowReviewEnd < cleanCss.length && narrowDepth) {
  if (cleanCss[narrowReviewEnd] === '{') narrowDepth++;
  else if (cleanCss[narrowReviewEnd] === '}') narrowDepth--;
  narrowReviewEnd++;
}
check(narrowDepth === 0, 'The <=380px adjustment has a complete media block');
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
check(rules.length > 50, 'The complete typography/spacing polish is present');
check(rules.every(rule => selectors(rule[1]).every(selector => /^\.facility-detail\.site-typo(?:[\s.:#>+~\[]|$)/.test(selector))), 'Every rule is isolated to this existing facility-detail main');

// Strict allow-list excludes colors, backgrounds, borders, radii, shadows,
// fonts/weights, display, templates, positioning, transforms and motion.
const typography = new Set(['font-size', 'line-height', 'letter-spacing']);
const spacing = new Set([
  'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'margin-block', 'margin-block-start', 'margin-block-end',
  'margin-inline', 'margin-inline-start', 'margin-inline-end',
  'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'padding-block', 'padding-block-start', 'padding-block-end',
  'padding-inline', 'padding-inline-start', 'padding-inline-end',
  'gap', 'row-gap', 'column-gap',
]);
const targetSizes = new Set(['width', 'min-width', 'height', 'min-height']);
const targetSelectors = new Set([
  '.facility-detail.site-typo .hero-actions .icon-btn',
  '.facility-detail.site-typo .contact-actions a',
  '.facility-detail.site-typo .detail-nav a',
  '.facility-detail.site-typo #danh-gia .review-more a',
  '.facility-detail.site-typo :is(.facility-info-action,.facility-social-link,.facility-video-links a)',
]);
let columnRepairs = 0;
let narrowMaxWidthRepairs = 0;
let narrowWrapRepairs = 0;
for (const rule of rules) {
  const ruleSelectors = selectors(rule[1]);
  const inNarrowReviewMedia = rule.index >= narrowReviewStart && rule.index < narrowReviewEnd;
  if (inNarrowReviewMedia) check(ruleSelectors.every(selector => [
    '.facility-detail.site-typo #danh-gia .review-content-top',
    '.facility-detail.site-typo #danh-gia .rating-row',
  ].includes(selector)), 'The <=380px adjustment changes only review rating/date wrapping and bar labels');
  for (const declaration of rule[2].split(';').map(value => value.trim()).filter(Boolean)) {
    const separator = declaration.indexOf(':');
    check(separator > 0, `A valid property declaration exists in ${rule[1].trim()}`);
    const property = declaration.slice(0, separator).trim();
    const value = declaration.slice(separator + 1).replace(/\s*!important\s*$/, '').trim();
    if (property === 'grid-column') {
      columnRepairs++;
      check(ruleSelectors.length === 1 && ruleSelectors[0] === '.facility-detail.site-typo .facility-contact-sidebar>.side-card' && /^1\s*\/\s*-1$/.test(value), 'Only the exact side-card full-rail span repair may change grid placement');
    } else if (property === 'max-width' || property === 'flex-wrap') {
      check(inNarrowReviewMedia && ruleSelectors.length === 1 && ruleSelectors[0] === '.facility-detail.site-typo #danh-gia .review-content-top', 'Review rating/date reflow is restricted to its exact selector inside <=380px');
      if (property === 'max-width') {
        narrowMaxWidthRepairs++;
        check(value === '112px', 'Only the 112px rating/date wrapping limit is permitted');
      } else {
        narrowWrapRepairs++;
        check(value === 'wrap', 'Only wrapping of the existing rating/date flex line is permitted');
      }
    } else if (targetSizes.has(property)) {
      const eyebrowClearance = property === 'min-height' && value === '44px' && ruleSelectors.length === 1 && ruleSelectors[0] === '.facility-detail.site-typo .facility-eyebrow';
      check(eyebrowClearance || ruleSelectors.every(selector => targetSelectors.has(selector)), `Sizing is limited to existing control targets or their label clearance: ${property}`);
      check(/^(?:40|44)px$/.test(value), `Control sizing retains compact 40/44px targets: ${value}`);
    } else check(typography.has(property) || spacing.has(property), `Only font-size/line-height/letter-spacing and spacing may change: ${property}`);
  }
}
check(columnRepairs === 1, 'Exactly one narrowly scoped side-card span repair is present');
check(narrowMaxWidthRepairs === 1 && narrowWrapRepairs === 1, 'Exactly one <=380px max-width/flex-wrap review repair is present');
check(/\.rating-row\{\s*font-size\s*:\s*11px\s*;?\s*\}/.test(cleanCss.slice(narrowReviewStart, narrowReviewEnd)), 'Narrow review bar labels remain readable on one line at 11px');
check(!/url\s*\(|gallery-lightbox/i.test(cleanCss), 'No external assets or body-mounted gallery styles are added');
const phoneCss = cleanCss.slice(cleanCss.indexOf('@media(max-width:767px)'));
check(phoneCss.includes('@media(max-width:767px)'), 'Existing phone breakpoint is retained');
check(/\.hero-actions \.icon-btn\{[^}]*font-size:0(?:;|\s*\})/.test(phoneCss), 'Phone hero controls retain their existing icon-only presentation');
check(/\.hero-actions \.icon-btn\{[^}]*width:44px;[^}]*height:44px/.test(phoneCss), 'Phone hero controls provide 44px targets');
check(/\.detail-nav a\{[^}]*min-height:44px/.test(phoneCss), 'Phone section links provide 44px targets');
check(/\.section#gioi-thieu \.section-copy\{font-size:16px;line-height:1\.75/.test(cleanCss), 'Editorial copy receives readable sizing and line spacing');

console.log(`Facility detail polish: ${checks} source checks passed. No DB connection or records modified.`);
