# PageSpeed: public homepage assets

Baseline read from the user's [mobile report](https://pagespeed.web.dev/analysis/https-medreview-vn/ndhnu8npeg?form_factor=mobile), generated 14 October 2026 at 21:58 GMT+7: performance 73, FCP 4.1s, LCP 4.7s, TBT 0ms, CLS 0. The largest reported issues were render-blocking CSS/font CDNs and oversized images. These are baseline values, not post-change results.

## Implementation

- Self-host Inter variable Latin/Vietnamese and Phosphor 2.1.1 with their licenses. Preserve all dynamic editor icon names and 450/550/650/750 font weights. Remove unused homepage Font Awesome CSS. See `local-fonts.md`.
- Extract existing homepage/header/footer inline CSS unchanged and bundle it in the original cascade order. The homepage discovers all its CSS in the head through two same-origin stylesheet links, without duplicate header/home/footer links. Other public pages use the shared shell bundle.
- Content hashes invalidate cached assets across deployments even when Git resets file modification times. A stale/missing CSS bundle falls back to the source CSS in the same order, with no request-time build or writes.
- Serve prebuilt WebP variants with `srcset`/`sizes`; preload only the matching mobile or desktop hero. The mobile picture and hero background use the same URL. Local/custom logos without prebuilt variants retain their original URLs.
- The visually checked DAHAN SPA source has prebuilt responsive variants. Other remote URLs remain unchanged. The Bcare source returned a generic illustrated doctor during the CLI fetch, so it was deliberately excluded rather than caching an incorrect profile image.
- `assets/.htaccess` enables static compression and long immutable caching only for fingerprinted assets/versioned fonts. No HTML, API, session, admin or search response gets a public cache rule. HTTP behavior must be verified on the deployed hosting; PHP's development server does not apply `.htaccess`.

## Generated image sizes

| Image | Original | Selected prebuilt variants |
| --- | ---: | ---: |
| Brand logo | 47,350 B | 64px: 624 B; 128px: 1,360 B |
| Homepage hero | 69,133 B | 960px/mobile: 13,398 B; 1280px: 18,890 B |
| DAHAN SPA | 442,794 B | 320px: 21,490 B; 640px: 79,664 B; 960px: 161,646 B |

Images are resized/compressed without changing original files, gallery URLs, database rows, captions or alt text. No image worker, migration or seed is needed. Remote originals are temporary build inputs only, not committed source files.

## Build and verification

```sh
php scripts/build_public_assets.php
php scripts/build_media_variants.php
php scripts/build_home_image_variants.php
php tests/public_assets_test.php
php tests/media_variants_test.php
php tests/home_image_variants_test.php
node tests/local_fonts_test.cjs
php tests/public_components_test.php
node tests/home_polish_test.cjs
```

The generated bundles/media, manifests and local fonts must be deployed together with their PHP integrations. Runtime never invokes these build scripts. Rebuild CSS after changing any bundle source. Refresh the allowlisted remote thumbnail explicitly if its source image changes.

Preview checks at 375px and desktop confirmed no horizontal overflow, working local font/icon rendering, working mobile navigation and search with the header excluded from background blur. Existing CSS declarations were compared with the previous Git version and retained unchanged except the intentional mobile hero URL variable. No production deploy or new PageSpeed score is implied by these local checks; rerun PageSpeed after deployment.

Known pre-existing test issue: `facility_verification_links_test.php` stops at its old mixed-Toplist-card fixture (`td-doctor`), before its header asset check. The unchanged Git-baseline test reproduces the same failure, and the Toplist template was not changed in this task. The focused asset/font/image/home behavior tests pass independently.
