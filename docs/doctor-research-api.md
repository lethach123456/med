# Doctor research API — contract v1

This is the doctor-writing workflow, separate from facility writing and VI→EN translation.
Base URL in production: `https://medreview.vn`. Every request requires the existing
`X-Medical-Api-Key` header. Never send that key or writer tokens to an AI chat.

## Replace a doctor's portrait

`POST /api/medical/doctor-image-replace.php` accepts
`{"replacements":[{"doctor_id":12,"image_url":"https://example.org/portrait.jpg"}]}`
(1–50 replacements), or one object with `doctor_id` and `image_url`.
Each image is downloaded, raster-validated, branded with the MedReview wordmark,
then compressed synchronously to JPEG **strictly below 150,000 bytes**, with a
maximum long edge of 1280px and the original aspect ratio retained. No worker is
required. PNG/JPEG/WebP inputs must be at least 200×200, at most 12 MiB and 12 MP.
Only the final verified image is published in `uploads/library/doctors/{id}/`.
On processing failure the doctor's previous image URL stays unchanged; temporary
source/branded files are removed. Existing portrait files are not deleted.

Successful entries keep `public_url`, `width`, `height` and additionally return
`size_bytes` (actual final file size) and `original_size_bytes`. The doctor search
cache is invalidated after successful updates. This does not change the separate
`doctor-content-update.php` image queue or its worker's existing <100 KiB policy.

## Database

`medical_doctors` has 50 additive research columns:

* Text/content: `subtitle`, `content`, `full_json`, `degree_text`, `experience_start_year`,
  `address_text`, `phone_text`, `email_text`, `website_url`, `booking_url`,
  `seo_title`, `seo_description`, `seo_keywords`, `notes_for_editor`.
* Structured JSON: `education_json`, `experience_json`, `certifications_json`,
  `practice_license_json`, `services_json`, `conditions_treated_json`, `memberships_json`,
  `publications_json`, `awards_json`, `languages_supported_json`, `patient_groups_json`,
  `schedule_json`, `fees_json`, `sources_json`, `social_links_json`, `video_urls_json`,
  `evidence_json`, `locations_json`.
* Research/review tracking: `insufficient_data`, `last_researched_at`, `reviewed_at`,
  `reviewed_by`, `verification_status`.
* Professional profile/legal research: `professional_profile_url`, `practice_license_type`,
  `practice_license_number`, `practice_license_issuer`, `practice_license_issued_date`,
  `practice_license_scope`, `practice_registry_url`, `practice_registration_json`,
  `legal_documents_json`, `legal_notes`, `legal_source_ids_json`.
* Public external feedback: `aggregate_ratings_json`, `reviews_json` (MEDIUMTEXT,
  empty/absent means no new evidence, not an instruction to erase existing ratings).

The 11 professional/legal columns are an additive extension of contract v1, not a new
endpoint or queue type. Existing extension clients and submissions without these keys
remain supported. `practice_license_json` is retained for older clients; the server
synchronizes its evidenced license details with the new individual license columns.
Conflicting nonempty values are rejected rather than silently choosing one version.
Migration does not invent legal data, approve doctors, or backfill existing rows.

Existing `id`, `slug`, `language_code`, `translation_of_id`, `ai_writer_claim_json`,
ratings and counters remain. New doctors default to `verified=0`; existing records
are not retroactively changed by migration. AI submissions always become unreviewed.

`medical_doctor_facilities` mirrors `locations_json` relationally: `doctor_id`, optional
existing `facility_id`, `facility_name`, role/department, public address/contact,
booking, schedule, fees, source references, primary flag and order. Unknown facilities
may have `facility_id=null`; clients never guess facility IDs. During Vietnamese
doctor saves, the server creates a basic facility if no match or potentially
duplicate candidate exists, and a full name plus house number/street/locality
address (at most 255 characters) are supplied. The seed is published with empty
content, unverified and all rating/review/follower counters zero, so it can enter the
research queue but does not appear in the written-facility directory.
On doctor research save and admin create/edit, a null ID is resolved only when
both normalized full facility name and full address each score at least 90%
Levenshtein similarity against an existing facility in the doctor's locale.
Token-order similarity additionally recognizes reordered brand words; distinctive
brand text must also meet the 90% threshold independently of generic clinic prefixes.
Case/accents/punctuation and common Vietnamese address abbreviations are normalized;
House identifiers retain slash/dash structure, and address numeric identifiers,
locality names and short name identifiers must agree exactly. A competing
match within 3 percentage points is ambiguous and is not auto-linked. Substring,
name-only and incomplete-address matches are not used. Ambiguous workplaces stay
unlinked and never trigger creation; below-threshold near candidates are also
reserved for review, not interpreted as evidence that a new facility is needed.
Missing/placeholder addresses, insufficient research and English translations do
not create facilities. A DB-scoped advisory mutex is acquired before the doctor
transaction and released after commit/rollback (10-second acquisition timeout),
serializing concurrent doctor seed writers without row-locking every facility.
Creation and relational links commit or roll back together. Resolved IDs are saved in `locations_json` and
`medical_doctor_facilities`; a sole/primary linked workplace also updates legacy
`facility_slug`/`facility_name`. Explicit admin changes/unlinking take precedence,
and unlinked replacements clear stale slugs. English names remain translated even
when a linked facility has no published English counterpart. Workplace-specific
phone, website/booking URL, city and referenced sources are retained on a new seed;
provenance is stored in `full_json` with the originating doctor ID, without tokens.
The JSON field is the translation/transport source; the relation is regenerated atomically
when an API/admin saves it. EN records get their own links and prefer published EN clinics.

Deployment migration (CLI only; idempotent; no deletion/seeding):

```sh
php scripts/migrate_doctor_content.php
```

Admin > Doctor edit has an expandable research editor. CSRF protection covers saves
and translation creation. Only administrators can approve a profile; editing by a
non-admin clears approval. Custom saved doctor prompt wording is preserved.

Doctor detail pages now display these public fields in a separate **Hồ sơ & pháp lý**
section (**Profile & licensing** in English), with source references, safe external
profile/registry links, practice registrations and expandable public documents.
Licence values prefer the new nonempty columns and fall back to older
`practice_license_json` values; partial legacy dates are not completed by guessing.
The section and navigation item are omitted when there is no public legal content.
This read-only presentation does not change approval, licensing validity or database
records, and never exposes internal editor notes or writer tokens.

The built-in editorial prompt is `MEDREVIEW_DOCTOR_EDITORIAL_PROMPT_V3`: identity
matching, official-source research, all research JSON shapes, fact-checking, neutral
Vietnamese writing/SEO and mandatory fenced JSON output. Queue/manual prompt APIs
read the saved **Doctor** prompt from `medical_ai_prompts`; the transport contract is
appended regardless of customization (`MEDREVIEW_DOCTOR_RESEARCH_CONTRACT_V2`). The
appended version includes the new legal keys even when a customized older prompt is
saved. Editing its admin textarea affects future requests.
The migration upgrades only the exact older built-in V2/legacy prompt to V3. For a
custom saved Doctor prompt, it preserves the wording and appends missing
professional/legal, primary-source and public-review addenda once
(`MEDREVIEW_DOCTOR_PROFILE_LEGAL_V1`, `MEDREVIEW_DOCTOR_PRIMARY_SOURCES_V1`,
`MEDREVIEW_DOCTOR_PUBLIC_REVIEWS_V1`). Rerunning the migration does
not append them again. Saved-prompt updates use a compare-and-swap condition against the
previous text; a concurrent edit is never silently overwritten. Prompt/schema changes
run only through this CLI migration, not from visitor/API requests.
To explicitly replace a saved Doctor prompt with this built-in version, first back up its
current text, then run `php scripts/migrate_doctor_content.php --replace-doctor-prompt`.
Without that flag, a custom prompt is not replaced wholesale; only missing mandatory
addenda are appended.

## Public doctor reviews (additive contract)

The AI may collect real, public feedback for the **doctor**, never manufacture
patient stories or copy a workplace's rating to that doctor. The default prompt
and every saved custom prompt receive `MEDREVIEW_DOCTOR_PUBLIC_REVIEWS_V1` at request
time. Third-party medical booking/directory/review websites remain excluded;
professional/legal facts still come only from primary sources. Google Maps must
rate a separate doctor profile for aggregate scores. Individual comments on a
clinic page are eligible only when they explicitly refer to the identified doctor.
An officially operated Facebook/profile feedback page can also be used.

Review evidence lives in its own `url`, `source`/`source_text`, `evidence_text`
fields, **not** in `sources_json` or clinical/legal `source_ids`. No public feedback
is a legitimate outcome: return both arrays `[]`, do not mark an otherwise valid
profile insufficient solely because it has no reviews. Neither the prompt nor
URL/shape validation can prove a web claim true; the doctor remains unreviewed.

Add these two fields inside the existing doctor's JSON object:

```json
{
  "aggregate_ratings_json": [{
    "entity_type": "doctor", "doctor_id": 12, "doctor_name": "Exact source name",
    "source": "Google Maps", "score": 4.6, "count": 10,
    "url": "https://example.org/public-doctor-feedback",
    "checked_at": "2026-10-14", "evidence_text": "Observed score and count belong to this doctor's own profile."
  }],
  "reviews_json": [{
    "entity_type": "doctor", "doctor_id": 12, "doctor_name": "Exact source name",
    "source_text": "Google Maps", "url": "https://example.org/public-review",
    "rating": 5, "author_text": null, "review_date_text": null,
    "excerpt": "Short faithful summary of a real public comment.",
    "evidence_text": "The public comment explicitly identifies this doctor."
  }]
}
```

The URLs/numbers above illustrate structure only. Each record requires
`entity_type=doctor`, an integer `doctor_id` equal to the parent `id`, a real
HTTP(S) evidence URL, a source label and nonempty evidence text. Supplied
`doctor_name` must match the parent's exact name; omission inherits that name.
Scores must be numeric 1..5. Aggregate counts are positive integers ≤1,000,000,000;
total counts must fit the DB counter. `checked_at` is YYYY-MM-DD or null. A review
requires a nonempty plain-text excerpt; author and displayed date can be null.
Do not include sensitive patient information. Maximums: 20 aggregates, 50 reviews.
HTML is removed from public feedback text; arbitrary extra fields are not mapped
into the structured review columns, and imported reviews always have `verified=false`.

Facility-style aliases `aggregate_ratings`, `reviews`, `reviews_list` and
`reviews_list_json` are accepted. Arrays may be native or JSON-encoded, like older
doctor JSON fields. An empty canonical template list can be populated by an alias;
conflicting nonempty versions cause a field-specific HTTP 422 instead of silent loss.
Exact duplicate aggregates/review samples are deduplicated; inconsistent scores
for the same sample/source are rejected. Several distinct comments may share a
source page. The same author/text/date sample repeated at another URL counts once.

Clients still **must not send root `rating` or `reviews_count`**. The server derives
those columns from the valid aggregates using count-weighted means, rounded to
one decimal. If no aggregate exists, it uses the imported review sample; that count
is only the sample size, not an invented full total. Samples are not added to an
aggregate's count. Empty/no evidence preserves existing scores and stored feedback
arrays; an empty template is not a deletion request. A nonempty feedback submission
is a new snapshot of the supplied lists, not an incremental merge. The receipt adds
`reviews_imported`, `aggregate_sources`, `rating_summary` with basis
`external_aggregates` or `imported_review_sample` (or null); the server-generated
summary is also recorded in `full_json`. Raw AI JSON is retained without the lease.

The current extension already forwards new fields from `output_template`; it must
keep these arrays verbatim and never move nested scores to protected root scalars.
The API stores feedback with the doctor in the same transaction as the article,
workplaces and lease release. It does **not** insert into `medical_reviews`, which
belongs to facilities. Invalid feedback or a failed update rolls back the save.
The existing row mapper/API exposes the new arrays; no new gallery or review UI
is added by this transport change.

The VI→EN doctor translation map excludes both feedback arrays: they are copied
as source evidence, not rewritten by AI. IDs, names, scores, counts, URLs and text
remain exactly from the VI record. When editing/publishing an EN copy, the admin
validates these copied arrays against its DB-linked original VI doctor's identity,
not the EN row's different ID. Existing VI feedback likewise retains its original
evidence name during an admin display-name correction. This trusted identity comes
from DB, never from a client-supplied `review_identity`; strict research API requests
cannot override the parent ID/name. Translation does not invent new review samples.

Deploy the code **and run** `php scripts/migrate_doctor_content.php` on the site's
configured DB via CLI before reloading the doctor queue. The two columns are
additive; existing rows, ratings and leases are preserved. No migration runs on
visitor/API requests. Without the columns, the doctor API correctly reports schema
unavailable (503). Old submissions omitting these fields remain supported after
migration. Do not rerun old researched doctors or overwrite their articles merely
to backfill feedback; that requires a separately authorized update workflow.

Local verification: `php tests/doctor_reviews_test.php` uses pure validation and
in-memory PDO doubles; it does not connect to DB, fetch source URLs, or alter data.

## Endpoints

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `/api/medical/doctors-needing-content.php?page=1&limit=25` | GET | Work queue with record, saved admin prompt and full output template |
| `/api/medical/doctors-needing-content.php?ids=12,34` | GET | Recheck eligibility of remembered IDs |
| `/api/medical/doctor-content.php?id=12` | GET | Retrieve stored record, research JSON and facility links |
| `/api/medical/writer-claim.php` | POST | Atomic claim / heartbeat / release / status with `type=doctor` |
| `/api/medical/doctor-content-update.php` | POST | Receive completed doctor JSON; writer lease required |
| `/api/medical/prompt.php?type=doctor&id=12&name=...` | GET | Saved doctor prompt plus mandatory research contract |
| `/api/medical/translate-content.php?type=doctor` | GET/POST | Existing VI→EN workflow, including the new translatable fields |

Compatibility: `/api/medical/facilities-needing-content.php?type=doctor` routes to the
doctor queue now. Do NOT use `/api/medical/facility-content-update.php` to save a doctor.
Public `/api/medical/doctors.php` is still the visitor directory, NOT the writing queue.

Queue eligibility is identical to claim/save eligibility: published Vietnamese source,
empty `content`, and `last_researched_at IS NULL`. Existing short bio does not count as
a completed deep-research article; include it in the AI's source context. Completed
research, including `insufficient_data=true`, leaves the queue. English copies and drafts
are never automatically researched. Pagination may contain busy items: queue browsing
does not reserve them. `writer_claimed`/`writer_claim` are hints without a secret token.

## Extension sequence (must implement)

1. Add a separate source type **Doctor research** (`sourceType=doctor`). Keep queues,
   configuration, results and start/stop isolated per tab, as existing extension logic does.
2. Fetch the doctor queue and use each item's `prompt` verbatim. It comes from
   `medical_ai_prompts.prompt_key=doctor` plus the full server-side contract. Do not use
   a facility prompt, translation prompt, or a locally cached old doctor prompt.
3. Immediately BEFORE sending a prompt to the AI, claim the record:

```json
{
  "action": "claim",
  "type": "doctor",
  "id": 12,
  "request_id": "unique-stable-id-for-this-attempt",
  "client": {
    "instance_id": "stable-machine-install-id",
    "instance_label": "Machine label",
    "account_label": "Optional account label, not credentials",
    "worker_id": "unique-worker-or-tab-id",
    "provider": "gemini",
    "model": "model label",
    "task": "doctor_article"
  }
}
```

4. Only `claimed=true` may begin AI work. On 409 (`already_claimed`, `content_exists`,
   `not_published`), skip/recheck that record; never start AI anyway. A lost claim response
   can be retried with the SAME instance/worker/request ID to recover the same lease.
5. Store `claim_token` privately; heartbeat every 45 seconds during generation,
   parsing and network retries. Lease lifetime is 180 seconds:

```json
{"action":"heartbeat","type":"doctor","id":12,"claim_token":"token-returned-by-server"}
```

6. Extract the one `json` code block, parse its object, verify numeric `id` and exact
   `name` match the queue item. Preserve arrays, objects, nulls and all output fields.
   Inject `writer_claim_token` YOURSELF; the AI must never receive or generate it.
7. POST `/api/medical/doctor-content-update.php`:

```json
{"items":[{"id":12,"name":"exact source name","writer_claim_token":"private lease token","content":null,"sources_json":[],"evidence_json":{"identity_status":"insufficient","missing_fields":[],"conflicts":[]},"insufficient_data":true,"notes_for_editor":"Explain why this person could not be identified reliably."}]}
```

This example represents an insufficient-data result, not a successful article.
For a full article, send the AI's complete output template: `insufficient_data=false`,
nonempty sanitized `content`, actual `sources_json`, and `evidence_json.identity_status=matched`.
Single-object bodies are also accepted. The old `{content:"serialized JSON",writer_claim_token:"..."}`
envelope is supported. Each batch item needs its OWN token; max 25 items / 8MB body.
`id` must be a positive JSON integer, not a string.

8. Interpret each result, not just HTTP success:
   * 200: `ok=true`, `updated_count`, `updated` / `results`. Lease is cleared after save.
   * 422: invalid JSON data; show exact indexed validation error; never declare saved.
   * 409: stale/lost lease or no longer eligible. Stop work on this record; do not overwrite.
   * 207: mixed batch; inspect per-item `results` and `errors`.
   * 401: bad API key; stop and ask user to correct configuration.
   * 503: schema/database not ready; no AI work should start.
9. On POST timeout/network ambiguity, GET `doctor-content.php?id=...`. A completed
   result has `item.full_json.writer_request_id` matching the stable claim `request_id`
   and `last_researched_at`. Confirm that before treating it as saved or retrying.
   Another worker's receipt is NOT your successful submission.
10. If canceled/failed before save, release the token when still owned. Heartbeat failure
    means lease lost: do not submit that old output under a new/different record.
    Never auto-rewrite completed research. Insufficient data is a completed audit result,
    displayed separately in UI, not a failure that retries forever.

## Research JSON shapes

The API returns `output_template` dynamically. Field names/types below are fixed:

* `sources_json`: list of `{id,url,title,publisher,accessed_at}`. Unique source IDs,
  raw HTTP(S) URLs. Collect only publicly available professional information.
* `education_json`: `{institution,degree,specialty,start_year,end_year,source_ids}`.
* `experience_json`: `{facility_name,role,department,start_year,end_year,is_current,source_ids}`.
* `certifications_json`: `{name,issuer,year,source_ids}`.
* `practice_license_json`: `{document_type,number,issuer,issued_date,scope,source_ids}` or null.
  Failure to find a license online does not mean a doctor has no license.
* `professional_profile_url`: raw HTTP(S) URL of the matching official public doctor
  profile; `practice_registry_url`: raw HTTP(S) URL of a regulator's public register or
  doctor-specific registration record. Unknown URLs are null; do not substitute a
  fabricated detail-page URL or a site's homepage as proof of a specific license.
* Individual license columns: `practice_license_type`, `practice_license_number`,
  `practice_license_issuer`, `practice_license_issued_date`, `practice_license_scope`.
  Type/number/issuer/scope are strings or null. The issue date is exactly `YYYY-MM-DD`
  or null and must be a real calendar date. If a source only gives a month/year, keep
  the date null and describe that incomplete date in `legal_notes`; never guess day 01.
  For backward compatibility, incomplete legacy `practice_license_json.issued_date`
  text can remain in the old object while the new database DATE column stays null.
* `legal_source_ids_json`: list of source-ID strings referencing `sources_json`.
  Nonempty legal scalar fields/URLs require these references in research submissions.
  Compatibility normalization aggregates declared `source_ids` from the license,
  registration and document objects. A profile/register URL matching a source URL
  exactly also supplies that source ID (no host-only or guessed URL matching).
  This permits an omitted/null/empty aggregate without losing existing citations.
  Inferred link citations never become license `source_ids`; unknown/malformed IDs
  remain errors. No fallback attaches every article source to legal facts or notes.
* `practice_registration_json`: list of `{facility_name,department,scope,schedule_text,source_ids}`.
  This represents publicly evidenced practice registration, not an assumed copy of
  `locations_json` or the opening hours of a hospital.
* `legal_documents_json`: list of `{document_type,title,number,issuer,issued_date,url,source_ids}`.
  Each entry must contain public professional-document details and references to real
  sources. `issued_date` obeys the same full-date/null rule and `url` is raw HTTP(S) or
  null. Do not collect private identity documents or patient information.
* `legal_notes`: short professional/legal data caveats, string or null, at most 10,000
  characters. Missing online evidence is not proof that a doctor is unlicensed. Neither
  these columns nor AI research can declare a license valid, expired, revoked, or grant
  MedReview verification. Approval remains an administrator-controlled workflow.

Legal fields are evidence, not localization targets: VI→EN translation copies the
source legal columns/JSON unchanged and does not ask the translation AI to rewrite
document numbers, issuer names, dates, URLs or evidence source IDs.

* `services_json`: `{name,description,source_ids}`; `conditions_treated_json`: `{name,source_ids}`.
* `memberships_json`: `{name,role,source_ids}`; `publications_json`: `{title,year,url,doi,source_ids}`;
  `awards_json`: `{name,issuer,year,source_ids}`.
* `languages_supported_json`: `{code,name,source_ids}`; `patient_groups_json`: `{name,source_ids}`.
* `schedule_json`: `{day,time_text,location_name,source_ids}`. No inferred daily schedules.
* `fees_json`: `{service,amount_min,amount_max,currency,unit,notes,source_ids}`. Unknown amounts null.
* `locations_json`: `{facility_id,facility_name,role_text,department_text,address_text,phone_text,
  website_url,booking_url,is_primary,schedule_json,fees_json,source_ids}`. Max 30, at most one primary.
* `gallery_json`: list of `{url,caption,source_ids}` (legacy URL strings also accepted).
  No stock/AI portraits presented as real doctors. Downloads are asynchronous media jobs.
* `social_links_json`: object mapping platform names to raw HTTP(S) URLs;
  `video_urls_json`: list of raw HTTP(S) URLs. Never include private accounts or credentials.
* `bio_json`, `specialties_json`, `tags_json`: string lists, NOT HTML.
* `content`: article HTML; `subtitle`: short intro. Scripts/styles/embeds/active attributes removed.
* `experience_start_year`: evidenced clinical career start year or null; do not infer from graduation.
* `evidence_json`: `{identity_status:"matched|insufficient|conflicting",missing_fields:[],conflicts:[]}`.
* `insufficient_data`: mandatory boolean. Missing info is null/[]; never manufacture it.

Operational fields (`verified`, ratings, counts, IDs/slugs, translations, review approval)
cannot be changed by AI. AI never auto-approves a profile. Same record and locale are
preserved; saving research does not create a new doctor. `full_json` retains the original
payload without private claim tokens. The server stores research timestamps, not the AI.

## Verification

```sh
php tests/doctor_content_test.php
php tests/doctor_content_test.php --mysql-temporary
php tests/doctor_legal_content_test.php
php tests/doctor_legal_content_test.php --mysql-temporary
```

Commands with `--mysql-temporary` use connection-local TEMPORARY tables shadowing the production
names; no real doctors/clinics are inserted, edited or deleted. Covers foreign/expired
tokens, second submissions, rollback, protected fields, source references, HTML XSS,
gallery compatibility and insufficient-research queue exclusion.
The legal regression also checks the 11-column additive schema, old/new license
compatibility, real dates, legal evidence references, list/object types and limits,
protected approval fields, current prompt/output templates, mapping and transactional
persistence of the new legal fields.

With a local PHP server on port 8768, `php tests/doctor_http_readonly_test.php` checks
authentication, CORS, queue/read/prompt routes and invalid POST handling. It does not
claim a real doctor or submit a real article. It reads the configured API key locally,
without printing it.
