# Social sharing metadata

Public directory, profile, review, Toplist, handbook and information pages render
one `site_social_meta_tags()` set in the initial HTML `<head>`. Crawlers do not
need JavaScript. Existing canonical, hreflang and `site_language_metadata_tags()`
remain authoritative; the helper does not duplicate them.

## Page fields

Pass the page's final SEO title, description and canonical path/URL. A profile or
article supplies its own cover/photo and meaningful `image_alt`. `type` is
`website`, `profile` or `article`. Optional article dates/section must come from
real stored content; no publication date, author handle or Facebook App ID is
fabricated. No DB columns or migrations are required.

The shared renderer outputs Open Graph title/description/type/site name/URL,
image and image alt, plus explicit Twitter card/title/description/image/alt.
Text is plain, whitespace-normalized, Unicode-bounded and HTML-attribute escaped.
Page URLs use `SITE_CANONICAL_ORIGIN` (default `https://medreview.vn`), never a
request Host header. Pagination/filter queries supplied by the canonical path
are retained; fragments are omitted.

## Images and performance

- Prefer each page's real image. Local images must exist under the public media
  folders, remain inside the project after symlink resolution, be raster images
  and at least 200×200. Invalid/missing-local/private-address/data URLs fall back to
  `/assets/social/medreview-share.jpg`, a committed 1200×630 JPEG.
- Local image MIME/width/height come from the actual file header. Remote public
  HTTP(S) URLs are retained without fetching them or guessing dimensions/MIME.
  `og:image:secure_url` is only emitted for HTTPS images.
- Known portraits/square images use an X summary card; landscape/default images
  use a large-image card. This does not alter or crop stored images.
- Page rendering performs no HTTP requests, raster decoding/transforms, file
  writes, DB queries or cache rebuilds for these metadata. The only extra I/O is
  a local image header check. External image availability, expiry or DNS targets
  are not probed; a syntactically public URL may still be expired/unavailable.

Rebuild the default artwork explicitly with
`php scripts/build_social_image.php`. It reuses the bundled Inter wordmark,
requires GD only for this build step and publishes a small JPEG atomically.
Deploy the JPEG together with the PHP helper and modified page templates.

## Verify after deployment

Run `php tests/social_metadata_test.php` locally. After deployment check raw HTML
for both VI/EN URLs and use [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/)
to request a fresh scrape for previously shared URLs. Social platforms may keep
their own old previews; deploying tags does not purge those caches. No claim is
made that a crawler will bypass a site's existing WAF/reCAPTCHA rules.

Reference: [Open Graph specification](https://ogp.me/) (basic and structured image
properties). X continues to use the `twitter:` meta names; do not invent a
`twitter:site` account or `fb:app_id` without a real configured identifier.
