<?php
declare(strict_types=1);

/** Read-only presentation helpers. Never seed, migrate or modify a profile. */
function medical_doctor_profile_text(mixed $value): string
{
    return is_scalar($value) ? trim((string) $value) : '';
}

function medical_doctor_profile_entries(mixed $value, array $required): array
{
    if (is_string($value)) $value = json_decode($value, true);
    if (!is_array($value)) return [];
    return array_values(array_filter($value, static function ($entry) use ($required): bool {
        if (!is_array($entry)) return false;
        foreach ($required as $key) {
            if (medical_doctor_profile_text($entry[$key] ?? '') !== '') return true;
        }
        return false;
    }));
}

function medical_doctor_profile_url(mixed $value, bool $media = false): string
{
    $value = medical_doctor_profile_text($value);
    // Existing uploaded photos use root-relative paths; links remain HTTP(S)-only.
    if ($media && str_starts_with($value, '/') && !str_starts_with($value, '//')
        && !preg_match('/[\x00-\x20\\\\]/', $value)) return $value;
    try { return medical_doctor_http_url($value) ?? ''; }
    catch (InvalidArgumentException) { return ''; }
}

function medical_doctor_profile_phone(mixed $value): string
{
    $phone = medical_doctor_profile_text($value);
    // A text field can contain several numbers; never concatenate them into one dial target.
    if (!preg_match('/\+?\d[\d\s().-]{5,}\d/', $phone, $match)) return '';
    $number = preg_replace('/[^\d+]/', '', $match[0]) ?? '';
    return strlen(ltrim($number, '+')) >= 7 && strlen(ltrim($number, '+')) <= 15 ? 'tel:' . $number : '';
}

function medical_doctor_profile_date(mixed $value): string
{
    $value = medical_doctor_profile_text($value);
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) return '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
    return $date && $date->format('Y-m-d') === substr($value, 0, 10) ? $date->format('d/m/Y') : '';
}

function medical_doctor_profile_period(array $entry, bool $english): string
{
    $start = medical_doctor_profile_text($entry['start_year'] ?? '');
    $end = medical_doctor_profile_text($entry['end_year'] ?? '');
    if (!empty($entry['is_current'])) $end = $english ? 'Present' : 'Hiện tại';
    return implode(' — ', array_filter([$start, $end], static fn($part) => $part !== ''));
}

function medical_doctor_profile_fee(array $entry, bool $english): string
{
    $min = $entry['amount_min'] ?? null;
    $max = $entry['amount_max'] ?? null;
    $valid = static fn($value): bool => is_numeric($value) && (float) $value >= 0;
    $format = static fn($value): string => number_format((float) $value, (float) $value == (int) $value ? 0 : 2, $english ? '.' : ',', $english ? ',' : '.');
    $amount = '';
    if ($valid($min) && $valid($max) && (float) $max >= (float) $min) {
        $amount = $format($min) . ((float) $max !== (float) $min ? ' – ' . $format($max) : '');
    } elseif ($valid($min)) $amount = ($english ? 'From ' : 'Từ ') . $format($min);
    elseif ($valid($max)) $amount = ($english ? 'Up to ' : 'Đến ') . $format($max);
    if ($amount === '') return $english ? 'Contact for pricing' : 'Liên hệ xác nhận giá';
    $currency = medical_doctor_profile_text($entry['currency'] ?? '');
    $unit = medical_doctor_profile_text($entry['unit'] ?? '');
    return $amount . ($currency !== '' ? ' ' . $currency : '') . ($unit !== '' ? ' / ' . $unit : '');
}

/** Build a display model from the mapped DB row, preserving source/location associations. */
function medical_doctor_profile_model(array $doctor, ?array $legacyFacility = null): array
{
    $lists = [
        'education_json' => ['institution', 'degree'], 'experience_json' => ['facility_name', 'role'],
        'certifications_json' => ['name'], 'services_json' => ['name'], 'conditions_treated_json' => ['name'],
        'memberships_json' => ['name'], 'publications_json' => ['title'], 'awards_json' => ['name'],
        'languages_supported_json' => ['name'], 'patient_groups_json' => ['name'],
        'schedule_json' => ['day', 'time_text'], 'fees_json' => ['service'],
    ];
    $model = $doctor;
    foreach ($lists as $field => $required) $model[$field] = medical_doctor_profile_entries($doctor[$field] ?? [], $required);
    $model['content'] = medical_doctor_sanitize_html(medical_doctor_profile_text($doctor['content'] ?? ''));
    $model['bio'] = array_values(array_filter(array_map('medical_doctor_profile_text', (array) ($doctor['bio'] ?? []))));
    $model['specialties'] = array_values(array_unique(array_filter(array_map('medical_doctor_profile_text', (array) ($doctor['specialties'] ?? [])))));
    $model['image_url'] = medical_doctor_profile_url($doctor['image_url'] ?? '', true);
    $nameParts = preg_split('/\s+/u', medical_doctor_profile_text($doctor['name'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $model['initials'] = implode('', array_map(static fn($part) => mb_substr($part, 0, 1, 'UTF-8'), array_slice($nameParts, -2)));
    $model['has_rating'] = (int) ($doctor['reviews_count'] ?? 0) > 0 && (float) ($doctor['rating'] ?? 0) > 0;
    $model['rating'] = max(0, min(5, (float) ($doctor['rating'] ?? 0)));
    $model['sources'] = [];
    $model['source_index'] = [];
    foreach (medical_doctor_profile_entries($doctor['sources_json'] ?? [], ['url']) as $source) {
        $source['url'] = medical_doctor_profile_url($source['url']);
        if ($source['url'] === '') continue;
        $source['number'] = count($model['sources']) + 1;
        $source['anchor'] = 'doctor-source-' . $source['number'];
        $source['title'] = medical_doctor_profile_text($source['title'] ?? '') ?: (string) parse_url($source['url'], PHP_URL_HOST);
        $id = medical_doctor_profile_text($source['id'] ?? '');
        if ($id !== '' && !isset($model['source_index'][$id])) $model['source_index'][$id] = $source;
        $model['sources'][] = $source;
    }
    $model['locations'] = medical_doctor_profile_entries($doctor['locations_json'] ?? [], ['facility_name']);
    // Legacy profiles retain their known workplace, without inventing a clinic or timetable.
    if ($model['locations'] === [] && (medical_doctor_profile_text($doctor['facility_name'] ?? '') !== '' || medical_doctor_profile_text($legacyFacility['name'] ?? '') !== '')) {
        $model['locations'][] = [
            'facility_name' => medical_doctor_profile_text($doctor['facility_name'] ?? '') ?: medical_doctor_profile_text($legacyFacility['name'] ?? ''),
            'address_text' => medical_doctor_profile_text($doctor['address_text'] ?? '') ?: medical_doctor_profile_text($legacyFacility['address_text'] ?? ''),
            'phone_text' => medical_doctor_profile_text($doctor['phone_text'] ?? '') ?: medical_doctor_profile_text($legacyFacility['phone_text'] ?? ''),
            'website_url' => medical_doctor_profile_text($doctor['website_url'] ?? '') ?: medical_doctor_profile_text($legacyFacility['website_url'] ?? ''),
            'booking_url' => $doctor['booking_url'] ?? '', 'is_primary' => true,
            'profile_url' => $legacyFacility && !empty($legacyFacility['slug']) ? medical_public_entity_path('facility', $legacyFacility['slug'], $legacyFacility['language_code'] ?? 'vi') : '',
        ];
    }
    // Stable primary-first ordering; never relocate a schedule or fee to a different facility.
    $model['locations'] = array_merge(array_filter($model['locations'], static fn($item) => !empty($item['is_primary'])), array_filter($model['locations'], static fn($item) => empty($item['is_primary'])));
    foreach ($model['locations'] as &$location) {
        $location['phone_href'] = medical_doctor_profile_phone($location['phone_text'] ?? '');
        foreach (['website_url', 'booking_url'] as $field) $location[$field] = medical_doctor_profile_url($location[$field] ?? '');
        $location['schedule_json'] = medical_doctor_profile_entries($location['schedule_json'] ?? [], ['day', 'time_text']);
        $location['fees_json'] = medical_doctor_profile_entries($location['fees_json'] ?? [], ['service']);
        $location['map_url'] = !empty($location['address_text']) ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(medical_doctor_profile_text($location['address_text'])) : '';
    }
    unset($location);
    $primary = $model['locations'][0] ?? [];
    $model['phone_text'] = medical_doctor_profile_text($doctor['phone_text'] ?? '') ?: medical_doctor_profile_text($primary['phone_text'] ?? '');
    $model['phone_href'] = medical_doctor_profile_phone($model['phone_text']);
    $model['booking_url'] = medical_doctor_profile_url($doctor['booking_url'] ?? '') ?: ($primary['booking_url'] ?? '');
    $model['website_url'] = medical_doctor_profile_url($doctor['website_url'] ?? '');
    $model['email_text'] = filter_var($doctor['email_text'] ?? '', FILTER_VALIDATE_EMAIL) ? $doctor['email_text'] : '';
    $model['social_links'] = [];
    foreach ((array) ($doctor['social_links_json'] ?? []) as $platform => $url) {
        if (is_string($platform) && ($safe = medical_doctor_profile_url($url)) !== '') $model['social_links'][$platform] = $safe;
    }
    $model['video_urls'] = array_values(array_filter(array_map('medical_doctor_profile_url', (array) ($doctor['video_urls_json'] ?? []))));
    $model['gallery'] = array_values(array_unique(array_filter(array_map(static fn($url) => medical_doctor_profile_url($url, true), (array) ($doctor['gallery'] ?? [])))));
    $model['gallery_meta'] = [];
    foreach (medical_doctor_profile_entries($doctor['gallery_items'] ?? [], ['url']) as $entry) {
        $url = medical_doctor_profile_url($entry['url'], true);
        if ($url !== '') $model['gallery_meta'][$url] = $entry;
    }
    $model['updated_label'] = medical_doctor_profile_date($doctor['last_researched_at'] ?? '');
    return $model;
}
