# Facility ratings

`medical_facility_ratings.php` is the shared, read-only calculator used by the public directory/search, homepages, detail mapper and content receiver.

## Main profile score

- Valid `aggregate_ratings_json` entries take priority. Deduplicate a canonical source URL (or source name for old records without a URL), then compute a count-weighted average. Do not add imported review samples to these counts.
- Scores must be finite and greater than 0, at most 5. Counts are positive integers. A value such as 10/10 is not automatically converted into 5/5.
- If no valid aggregate exists, preserve a valid legacy `rating`/`reviews_count` pair. Its label is “Điểm hồ sơ”, since its original provenance may be missing.
- Only profiles without usable totals fall back to their published, rated review samples. Store `basis=imported_reviews` in the existing `review_summary_json` so subsequent review edits can recompute that fallback, including deletion of all samples.

## Stored reviews

`review_summary` describes only published reviews saved in `medical_reviews`. Its average, distribution and verified-label percentage use the same sample count. It never overwrites external profile totals. Records with rating 0 or outside the 0–5 range do not enter that average. Facility ID links take priority; the slug fallback applies only to legacy reviews without an ID.

Detail structured data uses the main score and its matching count. Source links/dates and the stored review section are shown separately. Source URLs record provenance; they are not independent verification of an AI claim.

## Receiving content

`facility-content-update.php` accepts arrays or JSON-encoded arrays for `aggregate_ratings`/`aggregate_ratings_json` and review aliases. It normalizes and validates them before any batch writes. Invalid aggregate data returns HTTP 422. Empty template arrays preserve prior external evidence. Review-only updates preserve external/legacy totals; sample-based totals are recalculated after saved-review deduplication.

New AI-imported reviews receive `verified=0`; an external source does not establish a verified MedReview patient. Existing verification labels are not rewritten automatically.

No schema migration is required. `facility_ratings_version=1` makes the next ordinary cache access refresh a pre-fix search snapshot. Existing aggregate JSON can repair previously overwritten totals on reads and subsequent saves. Missing original evidence cannot be reconstructed from a small sample.
