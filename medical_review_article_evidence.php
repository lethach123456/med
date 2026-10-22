<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_review_articles.php';

/**
 * Private editorial reference ledger, not a truth checker or patient-consensus score.
 * Including this module performs no DB/network/file operations. Catalogs contain
 * only stable references into the already allowlisted source DTO, never values.
 */
const MEDICAL_REVIEW_ARTICLE_EVIDENCE_MAX_BYTES = 65536;
const MEDICAL_REVIEW_ARTICLE_EVIDENCE_MAX_CLAIMS = 30;
const MEDICAL_REVIEW_ARTICLE_EVIDENCE_MAX_REFS = 20;
const MEDICAL_REVIEW_ARTICLE_EVIDENCE_TOPICS = [
    'services', 'staff', 'reception', 'waiting_time', 'costs', 'facilities', 'access', 'suitability', 'other',
];

/** Explicit public fact fields: ratings, identity IDs, claims, media and patient data are excluded. */
function medical_review_article_evidence_profile_fields(): array
{
    return [
        'name' => 'p-name', 'category' => 'p-category', 'city' => 'p-city', 'subtitle' => 'p-subtitle',
        'address_text' => 'p-address', 'phone_text' => 'p-phone', 'website_url' => 'p-website',
        'google_maps_url' => 'p-maps', 'hours_text' => 'p-hours', 'price_text' => 'p-price',
        'price_table_html' => 'p-price-table', 'price_source_scope' => 'p-price-scope',
        'featured_services_json' => 'p-featured-services', 'services_json' => 'p-services',
        'equipment_mentioned_json' => 'p-equipment', 'doctors_json' => 'p-staff',
        'business_license' => 'p-business-license', 'medical_operation_license' => 'p-operation-license',
        'established_year' => 'p-established', 'insurance_accepted_json' => 'p-insurance',
        'payment_methods_json' => 'p-payment', 'languages_supported_json' => 'p-languages',
        'parking_info' => 'p-parking', 'nearby_landmarks' => 'p-landmarks', 'booking_url' => 'p-booking',
        'latitude' => 'p-latitude', 'longitude' => 'p-longitude', 'branch_count' => 'p-branches',
        'utilities_json' => 'p-utilities', 'warranty_policy' => 'p-warranty',
        'intro_json' => 'p-intro', 'highlights_json' => 'p-highlights', 'content' => 'p-content',
    ];
}

function medical_review_article_evidence_has_text(mixed $value): bool
{
    if (!is_string($value) || preg_match('//u', $value) !== 1) return false;
    $plain = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return (preg_replace('/[\s\p{Z}\p{Cf}]+/u', '', $plain) ?? '') !== '';
}

/** Known legacy scan sections, not arbitrary keys/paths supplied by the writer. */
function medical_review_article_evidence_legacy_insight_fields(): array
{
    return ['services' => 'i-services', 'equipment_seen' => 'i-equipment-seen', 'premises' => 'i-premises',
        'staff_signals' => 'i-staff-signals', 'price_signals' => 'i-price-signals', 'activity' => 'i-activity',
        'comment_signals' => 'i-comment-signals', 'data_conflicts' => 'i-data-conflicts', 'dominant_content' => 'i-dominant-content'];
}

/** Presence only, not semantic verification: skip counters, links, IDs and operational metadata. */
function medical_review_article_evidence_legacy_node_has_facts(mixed $value, int $depth, int &$remaining): bool
{
    if ($depth > 5 || --$remaining < 0) return false;
    if (is_string($value)) {
        if (preg_match('//u', $value) !== 1) return false;
        $value = preg_replace('~<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>~isu', '', $value) ?? '';
        $plain = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plain = trim(preg_replace('/[\p{Cf}]+/u', '', $plain) ?? '');
        if ($plain === '' || !preg_match('/\p{L}/u', $plain)
            || preg_match('~^(?:https?://|ftp:|data:|blob:|//)\S*$~iu', $plain)
            || preg_match('~^(?:[a-z0-9-]+\.)+[a-z]{2,}(?:/\S*)?$~iu', $plain)
            || preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ].*)?$/D', $plain)
            || preg_match('/^(?:[a-f0-9]{32,128}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})$/Di', $plain)
            || preg_match('/^(?:id|uuid|ref|asset|image|photo)[_:\-][a-z0-9_-]+$/Di', $plain)
            || in_array(strtolower($plain), ['null','none','n/a','unknown','true','false','active','inactive','pending',
                'completed','done','published','draft','verified','unverified'], true)) return false;
        return medical_review_article_evidence_has_text($plain);
    }
    if ($value instanceof stdClass) $value = get_object_vars($value);
    if (!is_array($value)) return false;
    foreach (array_slice($value, 0, 50, true) as $key => $entry) {
        if (is_string($key)) {
            $name = strtolower($key);
            if (in_array($name, ['id','ids','url','urls','href','link','links','source','sources','ref','refs','references',
                'date','dates','timestamp','confidence','status','evidence_type','schema_version','version',
                'score','scores','rating','ratings','count','counts','total','coverage','image','images','photo','photos',
                'video','videos','media','asset','assets','thumbnail','base64','data_uri','bytes','mime_type','mime','filename','file','files',
                'patient_images_json','patient_images_notes','full_json','notes_for_editor'], true)
                || preg_match('/(?:_ids?|_urls?|_at|_dates?|_counts?|_scores?|_ratings?)$/D', $name)
                || preg_match('/^ai_|token|secret|password|^patient_(?:images|notes|name|email|phone|id)/', $name)) continue;
        }
        if (medical_review_article_evidence_legacy_node_has_facts($entry, $depth + 1, $remaining)) return true;
        if ($remaining <= 0) return false;
    }
    return false;
}

/** @return array{schema_version:int,items:list<array{id:string,kind:string,path:string,evidence_type:string}>} */
function medical_review_article_evidence_catalog(array $source): array
{
    $items = [];
    $facility = $source['facility'] ?? [];
    if ($facility instanceof stdClass) $facility = get_object_vars($facility);
    if (!is_array($facility)) $facility = [];
    foreach (medical_review_article_evidence_profile_fields() as $field => $id) {
        $value = $facility[$field] ?? null;
        if (str_ends_with($field, '_json')) {
            // Keep references only when the public nested allowlist has facts,
            // rather than making an arbitrary private key an evidence source.
            $present = medical_review_articles_nested_facts(medical_review_articles_private_redact($value), $field) !== [];
        } elseif (in_array($field, ['latitude', 'longitude'], true)) {
            $limit = $field === 'latitude' ? 90 : 180;
            $present = (is_int($value) || is_float($value) || is_string($value)) && is_numeric($value)
                && is_finite((float) $value) && abs((float) $value) <= $limit;
        } elseif ($field === 'branch_count') {
            $present = (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{0,2}$/D', (string) $value) === 1;
        } elseif ($field === 'established_year') {
            $present = (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{3}$/D', (string) $value) === 1;
        } else $present = medical_review_article_evidence_has_text($value);
        if (!$present) continue;
        $items[] = ['id' => $id, 'kind' => 'profile', 'path' => 'facility.' . $field, 'evidence_type' => 'official_statement'];
    }
    $samples = $source['review_samples'] ?? [];
    if (is_array($samples) && array_is_list($samples)) {
        foreach ($samples as $index => $sample) {
            if ($sample instanceof stdClass) $sample = get_object_vars($sample);
            if (!is_array($sample) || !medical_review_article_evidence_has_text($sample['excerpt'] ?? null)) continue;
            $items[] = ['id' => 'r-' . ($index + 1), 'kind' => 'feedback',
                'path' => 'review_samples.' . $index . '.excerpt', 'evidence_type' => 'public_feedback'];
        }
    }
    $insights = null;
    if (($source['stored_insights_valid'] ?? true) === true) {
        try { $insights = medical_facility_insights_normalize($source['facility_insights_json'] ?? null); }
        catch (InvalidArgumentException) { /* Invalid historic analysis must not become a reference. */ }
    }
    if ($insights instanceof stdClass) {
        if (medical_review_article_evidence_has_text($insights->summary ?? null)) {
            $items[] = ['id' => 'i-summary', 'kind' => 'insight',
                'path' => 'facility_insights_json.summary', 'evidence_type' => 'unverified'];
        }
        foreach (($insights->findings ?? []) as $index => $finding) {
            if (!$finding instanceof stdClass || !medical_review_article_evidence_has_text($finding->summary ?? null)) continue;
            $items[] = ['id' => 'i-' . ($index + 1), 'kind' => 'insight',
                'path' => 'facility_insights_json.findings.' . $index . '.summary',
                'evidence_type' => $finding->evidence_type ?? 'unverified'];
        }
        foreach (medical_review_article_evidence_legacy_insight_fields() as $field => $id) {
            $remaining = 256;
            if (!medical_review_article_evidence_legacy_node_has_facts($insights->{$field} ?? null, 0, $remaining)) continue;
            $items[] = ['id' => $id, 'kind' => 'insight', 'path' => 'facility_insights_json.' . $field,
                'evidence_type' => 'unverified'];
        }
    }
    return ['schema_version' => 1, 'items' => $items];
}

function medical_review_article_evidence_object(mixed $value, string $field, array $keys): array
{
    if ($value instanceof stdClass) $value = get_object_vars($value);
    if (!is_array($value) || array_is_list($value) || count($value) !== count($keys)
        || array_diff(array_keys($value), $keys) !== [] || array_diff($keys, array_keys($value)) !== []) {
        throw new InvalidArgumentException($field . ' cần object đúng các trường: ' . implode(', ', $keys) . '.');
    }
    return $value;
}

function medical_review_article_evidence_id(mixed $value, string $field): string
{
    $value = medical_facility_insights_text($value, $field, 80, true);
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/D', $value)) throw new InvalidArgumentException($field . ' chỉ nhận mã bắt đầu bằng chữ, gồm chữ/số/gạch nối/gạch dưới.');
    return $value;
}

function medical_review_article_evidence_refs(mixed $value, string $field, bool $required): array
{
    if (!is_array($value) || !array_is_list($value) || count($value) > MEDICAL_REVIEW_ARTICLE_EVIDENCE_MAX_REFS || ($required && $value === [])) {
        throw new InvalidArgumentException($field . ' cần danh sách mã tham chiếu, tối đa 20 và không trống khi bắt buộc.');
    }
    $refs = []; $seen = [];
    foreach ($value as $entry) {
        $entry = medical_review_article_evidence_id($entry, $field);
        if (isset($seen[$entry])) throw new InvalidArgumentException($field . ' có tham chiếu trùng.');
        $seen[$entry] = true; $refs[] = $entry;
    }
    return $refs;
}

/** Pure, idempotent canonicalization: no implicit dates, evidence changes, or semantic decisions. */
function medical_review_article_evidence_normalize(mixed $raw): array
{
    if (is_string($raw)) {
        if (strlen($raw) > MEDICAL_REVIEW_ARTICLE_EVIDENCE_MAX_BYTES) throw new InvalidArgumentException('editorial_evidence_json tối đa 64 KiB.');
        try { $raw = json_decode($raw, false, 8, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new InvalidArgumentException('editorial_evidence_json phải là object JSON UTF-8 hợp lệ.'); }
    }
    $raw = medical_review_article_evidence_object($raw, 'editorial_evidence_json', ['schema_version', 'claims']);
    if ($raw['schema_version'] !== 1) throw new InvalidArgumentException('editorial_evidence_json.schema_version chỉ nhận số nguyên 1.');
    if (!is_array($raw['claims']) || !array_is_list($raw['claims']) || count($raw['claims']) > MEDICAL_REVIEW_ARTICLE_EVIDENCE_MAX_CLAIMS) {
        throw new InvalidArgumentException('editorial_evidence_json.claims cần danh sách tối đa 30 nhận định.');
    }
    $claims = []; $seen = [];
    foreach ($raw['claims'] as $index => $entry) {
        $path = 'editorial_evidence_json.claims[' . $index . ']';
        $entry = medical_review_article_evidence_object($entry, $path,
            ['id', 'topic', 'statement', 'evidence_refs', 'feedback_scope', 'opposing_refs', 'disposition']);
        $id = medical_review_article_evidence_id($entry['id'], $path . '.id');
        if (isset($seen[$id])) throw new InvalidArgumentException('editorial_evidence_json.claims có id trùng.');
        $seen[$id] = true;
        if (!is_string($entry['topic']) || !in_array($entry['topic'], MEDICAL_REVIEW_ARTICLE_EVIDENCE_TOPICS, true)) throw new InvalidArgumentException($path . '.topic không hợp lệ.');
        $statement = medical_facility_insights_text($entry['statement'], $path . '.statement', 800, true);
        if (strip_tags($statement) !== $statement) throw new InvalidArgumentException($path . '.statement chỉ nhận văn bản thuần, không HTML.');
        if (!medical_review_article_evidence_has_text($statement)) throw new InvalidArgumentException($path . '.statement cần nhận định đọc được.');
        $refs = medical_review_article_evidence_refs($entry['evidence_refs'], $path . '.evidence_refs', true);
        $opposing = medical_review_article_evidence_refs($entry['opposing_refs'], $path . '.opposing_refs', false);
        if (count($refs) + count($opposing) > MEDICAL_REVIEW_ARTICLE_EVIDENCE_MAX_REFS) throw new InvalidArgumentException($path . ' tối đa 20 tham chiếu tổng cộng.');
        if (!is_string($entry['feedback_scope']) || !in_array($entry['feedback_scope'], ['none', 'single', 'recurring'], true)) throw new InvalidArgumentException($path . '.feedback_scope không hợp lệ.');
        if (!is_string($entry['disposition']) || !in_array($entry['disposition'], ['use', 'qualify', 'omit'], true)) throw new InvalidArgumentException($path . '.disposition không hợp lệ.');
        $claims[] = ['id' => $id, 'topic' => $entry['topic'], 'statement' => $statement,
            'evidence_refs' => $refs, 'feedback_scope' => $entry['feedback_scope'],
            'opposing_refs' => $opposing, 'disposition' => $entry['disposition']];
    }
    $result = ['schema_version' => 1, 'claims' => $claims];
    if (strlen(medical_review_articles_json_encode($result)) > MEDICAL_REVIEW_ARTICLE_EVIDENCE_MAX_BYTES) throw new InvalidArgumentException('editorial_evidence_json sau chuẩn hóa tối đa 64 KiB.');
    return $result;
}

/** Text deduplication is conservative reference checking, not author independence or agreement detection. */
function medical_review_article_evidence_feedback_key(string $excerpt): string
{
    $excerpt = html_entity_decode(strip_tags($excerpt), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (class_exists('Normalizer')) $excerpt = Normalizer::normalize($excerpt, Normalizer::FORM_C) ?: $excerpt;
    $excerpt = mb_strtolower($excerpt, 'UTF-8');
    $excerpt = preg_replace('/[\p{Cf}]+/u', '', $excerpt) ?? '';
    $excerpt = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $excerpt) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $excerpt) ?? '');
}

/**
 * Validate references only. A known reference need not actually support a claim;
 * neither this check nor two different excerpts proves truth, representativeness,
 * independent authors, recurring meaning, or customer consensus. The writer and
 * editorial review remain responsible for that semantic assessment.
 */
function medical_review_article_evidence_validate(array $ledger, array $source): array
{
    $ledger = medical_review_article_evidence_normalize($ledger);
    $catalog = [];
    foreach (medical_review_article_evidence_catalog($source)['items'] as $item) $catalog[$item['id']] = $item;
    foreach ($ledger['claims'] as $claim) {
        $support = $claim['evidence_refs']; $opposing = $claim['opposing_refs'];
        foreach (array_merge($support, $opposing) as $ref) {
            if (!isset($catalog[$ref])) throw new InvalidArgumentException('editorial_evidence_json dẫn tham chiếu không có trong danh mục: ' . $ref . '.');
        }
        if (array_intersect($support, $opposing) !== []) throw new InvalidArgumentException('Tham chiếu ủng hộ và đối lập phải tách biệt.');
        if ($opposing !== [] && $claim['disposition'] === 'use') throw new InvalidArgumentException('Nhận định có bằng chứng đối lập phải qualify hoặc omit.');
        $directFeedback = []; $uniqueText = [];
        foreach ($support as $ref) {
            $item = $catalog[$ref];
            if ($item['kind'] === 'insight' && $item['evidence_type'] === 'unverified' && $claim['disposition'] === 'use') {
                throw new InvalidArgumentException('Insight chưa xác minh chỉ được qualify hoặc omit, không use trực tiếp.');
            }
            if ($item['kind'] !== 'feedback') continue;
            $directFeedback[] = $ref;
            $index = (int) substr($ref, 2) - 1;
            $sample = $source['review_samples'][$index];
            if ($sample instanceof stdClass) $sample = get_object_vars($sample);
            $key = medical_review_article_evidence_feedback_key($sample['excerpt']);
            if ($key !== '') $uniqueText[$key] = true;
        }
        if ($claim['feedback_scope'] === 'single' && $directFeedback === []) throw new InvalidArgumentException('feedback_scope single cần phản hồi trực tiếp trong evidence_refs.');
        if ($claim['feedback_scope'] === 'recurring' && count($uniqueText) < 2) throw new InvalidArgumentException('feedback_scope recurring cần ít nhất hai đoạn phản hồi trực tiếp khác nhau sau chuẩn hóa.');
        // scope=none may cite feedback cautiously for a non-recurring observation;
        // this is not a count or a declaration of a recurring customer theme.
    }
    return $ledger;
}
