'use strict';

// Offline, read-only checks: no HTTP bootstrap or database connection.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '..');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'assets/fonts/local-fonts-manifest.json'), 'utf8'));
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const inter = read('assets/css/core/local-fonts.css');
const icons = read('assets/vendor/@phosphor-icons/web/2.1.1/phosphor.css');

check(manifest.sources.inter.version === '5.3.0' && manifest.sources.phosphor.version === '2.1.1', 'Vendor sources remain pinned');
for (const asset of manifest.assets) {
  const bytes = fs.readFileSync(path.join(root, asset.path));
  check(bytes.length === asset.bytes, `Asset size agrees with manifest: ${asset.path}`);
  check(crypto.createHash('sha256').update(bytes).digest('hex') === asset.sha256, `Asset checksum agrees with manifest: ${asset.path}`);
  if (asset.path.endsWith('.woff2')) {
    check(bytes.toString('ascii', 0, 4) === 'wOF2' && bytes.readUInt32BE(8) === bytes.length, `Valid WOFF2 signature and length: ${asset.path}`);
  }
}
for (const css of [inter, icons]) {
  check(!/@import|https?:\/\//.test(css), 'Local font CSS has no external dependencies');
  for (const [, url] of css.matchAll(/url\(["']?(\/[^)"']+)["']?\)/g)) {
    check(fs.existsSync(path.join(root, url.slice(1))), `Every local font URL exists: ${url}`);
  }
}
const faces = [...inter.matchAll(/@font-face\{([^}]+)\}/g)];
check(faces.length === 2, 'Inter ships only Latin and Vietnamese subsets');
check(faces.every(([, face]) => /font-family:'Inter'/.test(face) && /font-weight:100 900/.test(face) && /font-style:normal/.test(face) && /font-display:swap/.test(face)), 'Inter preserves existing family, every requested weight, and immediate fallback text');
const ranges = Object.values(manifest.inter.unicodeRanges).flatMap(value => value.split(',').map(item => {
  const [start, end] = item.replace('U+', '').split('-').map(value => parseInt(value, 16));
  return [start, end === undefined ? start : end];
}));
const vietnamese = 'ĂăÂâĐđÊêÔôƠơƯưÀÁẢÃẠàáảãạẰẮẲẴẶằắẳẵặẦẤẨẪẬầấẩẫậÈÉẺẼẸèéẻẽẹỀẾỂỄỆềếểễệÌÍỈĨỊìíỉĩịÒÓỎÕỌòóỏõọỒỐỔỖỘồốổỗộỜỚỞỠỢờớởỡợÙÚỦŨỤùúủũụỪỨỬỮỰừứửữựỲÝỶỸỴỳýỷỹỵ₫';
for (const character of vietnamese) {
  const code = character.codePointAt(0);
  check(ranges.some(([start, end]) => code >= start && code <= end), `Vietnamese character has a declared font subset: ${character}`);
}
for (const [weight, baseClass] of [['regular', 'ph'], ['fill', 'ph-fill']]) {
  const names = [...icons.matchAll(new RegExp(`\\.${baseClass}\\.ph-([a-z0-9-]+):before\\{`, 'g'))].map(match => match[1]);
  check(names.length === manifest.phosphor.iconRuleCounts[weight] && names.length > 1000, `Full ${weight} icon set retained`);
  for (const name of ['heart', 'seal-check', 'magnifying-glass', 'hospital', 'arrow-right', 'tooth', 'stethoscope', 'sparkle', 'check', 'x']) {
    check(names.includes(name), `Shared/dynamic ${weight} icon is available: ${name}`);
  }
}
check((icons.match(/font-display:block/g) || []).length === 2, 'PUA icon fonts retain upstream block display, avoiding meaningless fallback glyphs');
check(!/format\("(?:woff|truetype|svg)"\)/.test(icons), 'Unused legacy icon font formats are not requested');
check(read('assets/fonts/inter/5.3.0/LICENSE.txt').includes('SIL OPEN FONT LICENSE Version 1.1'), 'Inter license accompanies font redistribution');
check(read('assets/vendor/@phosphor-icons/web/2.1.1/LICENSE.txt').includes('MIT License'), 'Phosphor license accompanies font redistribution');
console.log(`Local fonts: ${checks} checks passed. No DB connection or records modified.`);
