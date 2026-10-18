# Facility ratings

`medical_facility_ratings.php` separates the public MedReview score from externally reported totals. All calculators are read-only.

## Public score and JSON snapshot

The directory, search, homepages and detail header use only published, rated reviews actually saved in `medical_reviews`. The score is their average; the count is the number of the same records. Draft, zero and out-of-range ratings do not count. An explicit facility ID wins; slug matching is only for legacy records with a NULL facility ID.

`medical_facility_stored_rating_sql()` is joined into the bulk facility SELECT. `medical_search_cache_facilities()` writes the resulting score and count into each facility in `storage/cache/medical-search-index-v1.json` with `rating_basis=stored_reviews`. There is no per-card rating query or second client request. Initial HTML, AJAX pagination, sorting and rating filters all read that same snapshot. No stored rated reviews means score/count zero and “Chưa có đánh giá”; external totals are never a fallback.

`facility_ratings_version=2` forces the next access to rebuild the previous snapshot. While another process rebuilds or the DB is unavailable, older snapshots remain usable for directory content but their obsolete facility rating/count fields are cleared on read. Writes are atomic under the existing single rebuild lock. Review saves, deletes and content imports invalidate the snapshot; the normal TTL also refreshes it. No schema migration is required.

The homepage queries use the same grouped SQL and public calculator. Detail calculates from its already-loaded review records and displays external source information separately; its structured data uses the public score and matching local count.

## External totals retained in storage

- Valid `aggregate_ratings_json` entries take priority. Deduplicate a canonical source URL (or source name for old records without a URL), then compute a count-weighted average. Do not add imported review samples to these counts.
- Scores must be finite and greater than 0, at most 5. Counts are positive integers. A value such as 10/10 is not automatically converted into 5/5.
- If no valid aggregate exists, preserve a valid legacy `rating`/`reviews_count` pair. Its label is “Điểm hồ sơ”, since its original provenance may be missing.
- Only profiles without usable totals fall back to their published, rated review samples. Store `basis=imported_reviews` in the existing `review_summary_json` so subsequent review edits can recompute that fallback, including deletion of all samples.

`review_summary` describes the stored reviews, including the star distribution and verified-label percentage using that same count. Reading a public score does not overwrite external evidence in the DB. Source URLs record provenance; they are not independent verification of an AI claim.

## Receiving content

`facility-content-update.php` accepts arrays or JSON-encoded arrays for `aggregate_ratings`/`aggregate_ratings_json` and review aliases. It normalizes and validates them before any batch writes. Invalid aggregate data returns HTTP 422. Empty template arrays preserve prior external evidence. Review-only updates preserve external/legacy totals; sample-based totals are recalculated after saved-review deduplication.

New AI-imported reviews receive `verified=0`; an external source does not establish a verified MedReview patient. Existing verification labels are not rewritten automatically.

Existing aggregate JSON can repair previously overwritten external scalar totals on subsequent saves. These scalars are not used as the public MedReview review count. Missing original external evidence cannot be reconstructed from a small sample.
