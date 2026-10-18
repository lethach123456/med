# Public language declarations

Public pages declare one actual content language, `vi` or `en`. List/static pages use their existing route-owned locale; entity and article pages use the stored `language_code`/`language` rather than browser preferences. Legacy articles without a supported language keep the previous category/route fallback. No extra database query or migration is needed.

- HTML `lang` remains the document-language declaration.
- `site_language_response_header()` sends `Content-Language: vi` or `en` before output.
- `site_language_metadata_tags()` emits one `og:locale` (`vi_VN`/`en_US`). The English Open Graph territory is a formatting convention, not US-only targeting; HTML/HTTP still use general `en`.
- `og:locale:alternate` is opt-in. Detail templates supply it only for a real published counterpart; language-list routes may supply their existing corresponding lists. No guessed translation slug or draft is advertised.
- Shared header/footer follow the record's forced locale, including when an old URL differs from that record's language. Error pages localize their labels and declaration together.

The deprecated `<meta http-equiv="content-language">` is intentionally not added. These declarations describe content; they do not translate it or guarantee Google's language classification/ranking.

References: [MDN language declarations](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Language), [Open Graph optional metadata](https://ogp.me/), [Google localized pages](https://developers.google.com/search/docs/specialty/international/localized-versions).

Tests: `php tests/language_metadata_test.php`; optional real loopback HTTP/header checks: `php tests/language_metadata_test.php --http`. Both avoid application DB connections and writes.
