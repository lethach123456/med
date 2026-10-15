# Local fonts

The shared public shell can load `assets/css/core/local-fonts.css` for Inter and `assets/vendor/@phosphor-icons/web/2.1.1/phosphor.css` for icons. Font URLs inside these stylesheets are absolute, so Inter CSS can also be included in a generated shared CSS bundle without breaking URLs.

## Sources and licenses

- Inter normal variable weight 100–900: [`@fontsource-variable/inter` 5.3.0](https://fontsource.org/fonts/inter), Google Fonts upstream v20, SIL OFL 1.1. Fonts are unchanged official Latin and Vietnamese WOFF2 subsets. License: `assets/fonts/inter/5.3.0/LICENSE.txt`.
- Phosphor regular/fill: [`@phosphor-icons/web` 2.1.1](https://github.com/phosphor-icons/web), matching the previous CDN version, MIT. Fonts are unchanged official WOFF2 files. License: `assets/vendor/@phosphor-icons/web/2.1.1/LICENSE.txt`.
- Package archive URLs/integrity and local SHA-256 hashes/byte sizes are recorded in `assets/fonts/local-fonts-manifest.json`.

Inter uses `font-display:swap`: fallback text remains visible during font loading. It retains family `Inter`, so existing styles and intermediate weights 450/550/650/750 continue to work. English typically requests only the 48,256-byte Latin file; Vietnamese requests that plus the 10,252-byte Vietnamese file. Cyrillic, Greek, Latin Extended, italics and unused static weight files are not shipped.

Phosphor retains upstream `font-display:block`: its private-use characters do not have meaningful system-font fallbacks. It does not block normal text. Full regular/fill glyphs are intentionally retained because `db.php` lets the front editor choose arbitrary icons, and several public templates construct icon names dynamically. Subsetting to visible literal classes could silently break edited content. WOFF/TTF/SVG fallback files are omitted for current evergreen browsers. Both CSS sets are minified and combined into one local stylesheet; font files are only fetched when a matching icon weight is used.

The local Phosphor CSS path deliberately contains `@phosphor-icons/web`, and its selectors retain `.ph.ph-name:before` / `.ph-fill.ph-name:before`. The existing editor discovers and parses its full icon library using those patterns. Keep this CSS as a separate `<link rel="stylesheet">` even if Inter is bundled.

Suggested critical preload: `/assets/fonts/inter/5.3.0/inter-latin-wght-normal.woff2` with `as="font" type="font/woff2" crossorigin`. Vietnamese pages can additionally preload `/assets/fonts/inter/5.3.0/inter-vietnamese-wght-normal.woff2`. Do not preload all icon fonts; they are not the text LCP resource.

## Rebuild and verify

Download the pinned public npm package archives named in the manifest, verify their SHA-512 integrity, and extract them to a temporary directory. No package installation is required. Then run:

```sh
node scripts/build_local_fonts.cjs /path/to/inter/package /path/to/phosphor/package
node tests/local_fonts_test.cjs
```

The import checks package names and versions, regenerates CSS/assets/manifest deterministically, and never loads PHP or connects to the database. Tests validate hashes, WOFF2 headers, local URLs, weight/display policies, Vietnamese coverage, full icon mappings and licenses. Deploy the versioned font/vendor directories together with the generated CSS and manifest.
