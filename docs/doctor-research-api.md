# Doctor research API — contract v1

This is the doctor-writing workflow, separate from facility writing and VI→EN translation.
Base URL in production: `https://medreview.vn`. Every request requires the existing
`X-Medical-Api-Key` header. Never send that key or writer tokens to an AI chat.

## Database

`medical_doctors` has 37 additive research columns (63 columns in total):

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

Existing `id`, `slug`, `language_code`, `translation_of_id`, `ai_writer_claim_json`,
ratings and counters remain. New doctors default to `verified=0`; existing records
are not retroactively changed by migration. AI submissions always become unreviewed.

`medical_doctor_facilities` mirrors `locations_json` relationally: `doctor_id`, optional
existing `facility_id`, `facility_name`, role/department, public address/contact,
booking, schedule, fees, source references, primary flag and order. Unknown facilities
may have `facility_id=null`; this workflow never creates clinics or guesses their IDs.
The JSON field is the translation/transport source; the relation is regenerated atomically
when an API/admin saves it. EN records get their own links and prefer published EN clinics.

Deployment migration (CLI only; idempotent; no deletion/seeding):

```sh
php scripts/migrate_doctor_content.php
```

Admin > Doctor edit has an expandable research editor. CSRF protection covers saves
and translation creation. Only administrators can approve a profile; editing by a
non-admin clears approval. Custom saved doctor prompts are preserved.

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
```

The second command uses connection-local TEMPORARY tables shadowing the production
names; no real doctors/clinics are inserted, edited or deleted. Covers foreign/expired
tokens, second submissions, rollback, protected fields, source references, HTML XSS,
gallery compatibility and insufficient-research queue exclusion.

With a local PHP server on port 8768, `php tests/doctor_http_readonly_test.php` checks
authentication, CORS, queue/read/prompt routes and invalid POST handling. It does not
claim a real doctor or submit a real article. It reads the configured API key locally,
without printing it.
