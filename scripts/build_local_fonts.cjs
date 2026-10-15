'use strict';

// Offline vendor import. Download/extract the pinned public npm packages first;
// this script does not fetch, install packages, connect to the DB, or change PHP.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '..');
const [interSource, phosphorSource] = process.argv.slice(2);
if (!interSource || !phosphorSource || process.argv.length !== 4) {
  console.error('Usage: node scripts/build_local_fonts.cjs <extracted Inter package> <extracted Phosphor package>');
  process.exit(1);
}

const interVersion = '5.3.0';
const phosphorVersion = '2.1.1';
const sources = {
  inter: {
    package: '@fontsource-variable/inter', version: interVersion,
    source: 'https://fontsource.org/fonts/inter',
    archive: `https://registry.npmjs.org/@fontsource-variable/inter/-/inter-${interVersion}.tgz`,
    archiveIntegrity: 'sha512-OupL48va4JNofb97w6NYeF9S7W/kHNKM0Er8Dem5nqi4jeOLrVJDoE8tZEpnMJmtkvNbB1EIPPwHcdkF6b1oUA==',
    license: 'OFL-1.1', upstreamFontVersion: 'v20',
  },
  phosphor: {
    package: '@phosphor-icons/web', version: phosphorVersion,
    source: 'https://github.com/phosphor-icons/web',
    archive: `https://registry.npmjs.org/@phosphor-icons/web/-/web-${phosphorVersion}.tgz`,
    archiveIntegrity: 'sha512-QjrfbItu5Rb2i37GzsKxmrRHfZPTVk3oXSPBnQ2+oACDbQRWGAeB0AsvZw263n1nFouQuff+khOCtRbrc6+k+A==',
    license: 'MIT',
  },
};

function read(source, name) { return fs.readFileSync(path.join(source, name), 'utf8'); }
for (const [source, expected] of [[interSource, sources.inter], [phosphorSource, sources.phosphor]]) {
  const actual = JSON.parse(read(source, 'package.json'));
  if (actual.name !== expected.package || actual.version !== expected.version) {
    throw new Error(`Expected ${expected.package}@${expected.version}; received ${actual.name}@${actual.version}`);
  }
}

const assets = [];
function save(relative, value, role) {
  const target = path.join(root, relative);
  fs.mkdirSync(path.dirname(target), {recursive: true});
  fs.writeFileSync(target, value);
  const bytes = fs.readFileSync(target);
  assets.push({path: relative, bytes: bytes.length, sha256: crypto.createHash('sha256').update(bytes).digest('hex'), role});
}
function copy(source, name, relative, role) {
  save(relative, fs.readFileSync(path.join(source, name)), role);
}
function minify(css) {
  return css.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, ' ').replace(/\s*([{}:;,])\s*/g, '$1').trim();
}

const interBase = `assets/fonts/inter/${interVersion}`;
const interCss = read(interSource, 'wght.css');
const unicode = JSON.parse(read(interSource, 'unicode.json'));
const interFaces = [];
for (const subset of ['vietnamese', 'latin']) {
  const filename = `inter-${subset}-wght-normal.woff2`;
  copy(interSource, `files/${filename}`, `${interBase}/${filename}`, `inter-${subset}`);
  const match = interCss.match(new RegExp(`/\\* inter-${subset}-wght-normal \\*/\\s*(@font-face\\s*\\{[^}]+\\})`));
  if (!match) throw new Error(`Missing upstream ${subset} @font-face`);
  interFaces.push(match[1].replace(/'Inter Variable'/g, "'Inter'")
    .replace(`./files/${filename}`, `/${interBase}/${filename}`)
    .replace("format('woff2-variations')", "format('woff2')"));
}
save('assets/css/core/local-fonts.css', `/*! Inter variable normal, Fontsource ${interVersion}, SIL OFL-1.1. License: /${interBase}/LICENSE.txt */\n${interFaces.map(minify).join('\n')}\n`, 'inter-css');
copy(interSource, 'LICENSE', `${interBase}/LICENSE.txt`, 'inter-license');

const phosphorBase = `assets/vendor/@phosphor-icons/web/${phosphorVersion}`;
const phosphorFontBase = `assets/fonts/phosphor/${phosphorVersion}`;
const iconRuleCounts = {};
const phosphorFaces = [];
for (const [weight, filename, baseClass] of [['regular', 'Phosphor.woff2', 'ph'], ['fill', 'Phosphor-Fill.woff2', 'ph-fill']]) {
  const upstream = read(phosphorSource, `src/${weight}/style.css`);
  iconRuleCounts[weight] = [...upstream.matchAll(new RegExp(`\\.${baseClass}\\.ph-[a-z0-9-]+:before\\s*\\{`, 'g'))].length;
  if (iconRuleCounts[weight] < 1000) throw new Error(`Incomplete ${weight} icon stylesheet`);
  // WOFF2-only is enough for the evergreen browsers served by this website.
  // Preserve upstream font-display:block: PUA fallback characters are not icons.
  phosphorFaces.push(minify(upstream.replace(/src:\s*[\s\S]*?;/, `src:url("/${phosphorFontBase}/${filename}") format("woff2");`)));
  copy(phosphorSource, `src/${weight}/${filename}`, `${phosphorFontBase}/${filename}`, `phosphor-${weight}`);
}
save(`${phosphorBase}/phosphor.css`, `/*! Phosphor ${phosphorVersion}, MIT. License: /${phosphorBase}/LICENSE.txt. Full regular/fill glyphs retained for dynamic front-editor icons. */\n${phosphorFaces.join('\n')}\n`, 'phosphor-css');
copy(phosphorSource, 'LICENSE', `${phosphorBase}/LICENSE.txt`, 'phosphor-license');

const manifest = {
  schema: 1, sources,
  inter: {family: 'Inter', style: 'normal', weight: [100, 900], display: 'swap', unicodeRanges: {latin: unicode.latin, vietnamese: unicode.vietnamese}},
  phosphor: {
    weights: ['regular', 'fill'], display: 'block', iconRuleCounts,
    subsetPolicy: 'Full upstream glyphs: db.php front editor accepts arbitrary ph-* icons; public templates also build icon names dynamically.',
    editorCompatibility: 'The local CSS URL retains @phosphor-icons/web and .ph/.ph-fill selector syntax for db.php iconStylesheetUrls/extractPhosphorIcons.',
  },
  assets,
};
fs.mkdirSync(path.join(root, 'assets/fonts'), {recursive: true});
fs.writeFileSync(path.join(root, 'assets/fonts/local-fonts-manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
for (const asset of assets) console.log(`${asset.bytes.toString().padStart(7)} bytes  ${asset.path}`);
console.log(`Imported ${assets.length} local assets; no database access.`);
