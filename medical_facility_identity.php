<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_handbook_suggestions.php';

/** Shared brand/SEO normalization, without discarding a branch marker. */
function medical_facility_identity_name(string $name, string $city = ''): string
{
    $name = preg_replace('/^\s*bv\.?\s+/iu', 'Bệnh viện ', $name) ?? $name;
    $name = preg_replace('/^\s*pk\.?\s+/iu', 'Phòng khám ', $name) ?? $name;
    return handbook_suggestion_facility_name_key($name, $city);
}

/** A separately supplied city may complete a street-only address. */
function medical_facility_identity_address(string $address, string $city = ''): array
{
    $identity = medical_doctor_facility_address_identity($address, $city);
    if (!$identity['city_conflict'] && $identity['locality'] === '' && trim($city) !== '' && $identity['house'] !== '') {
        $address = rtrim(trim($address), ',;') . ', ' . trim($city);
        $identity = medical_doctor_facility_address_identity($address, $city);
    }
    return ['address' => $address] + $identity;
}

/**
 * Match a physical facility, not every branch sharing a brand or website.
 * This helper never connects to the DB, changes a profile or merges old IDs.
 * @return array{status:string,id:?int,candidate_ids:list<int>,reason:string}
 */
function medical_facility_identity_match(array $item, array $rows): array
{
    $name = (string) ($item['name'] ?? '');
    $city = (string) ($item['city'] ?? '');
    $address = medical_facility_identity_address((string) ($item['address_text'] ?? $item['address'] ?? ''), $city);
    if ($address['city_conflict']) return ['status' => 'ambiguous', 'id' => null, 'candidate_ids' => [], 'reason' => 'city_address_conflict'];
    $nameKey = medical_facility_identity_name($name, $city ?: $address['city_label']);
    $matches = []; $near = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0 || $nameKey === '') continue;
        $candidateCity = (string) ($row['city'] ?? '');
        $candidate = medical_facility_identity_address((string) ($row['address_text'] ?? ''), $candidateCity);
        $candidateName = medical_facility_identity_name((string) ($row['name'] ?? ''), $candidateCity ?: $candidate['city_label']);
        $nameScore = medical_doctor_facility_name_similarity($nameKey, $candidateName);
        // A branch/address suffix is not safe to discard for auto-linking, but
        // must not let the same physical clinic seed a second row either.
        $nameNear = medical_doctor_facility_token_similarity($nameKey, $candidateName) >= 0.8
            || (min(strlen($nameKey), strlen($candidateName)) >= 4
                && (str_starts_with($nameKey, $candidateName . ' ') || str_starts_with($candidateName, $nameKey . ' ')));
        if ($nameScore < 0.8 && !$nameNear) continue;
        $cityA = medical_doctor_facility_city_key($city) ?: $address['locality'];
        $cityB = medical_doctor_facility_city_key($candidateCity) ?: $candidate['locality'];
        if ($candidate['city_conflict']) {
            $houseDiffers = $address['house'] !== '' && $candidate['house'] !== '' && $address['house'] !== $candidate['house'];
            if (!$houseDiffers && ($cityA === '' || in_array($cityA, [$cityB, $candidate['locality']], true))) $near[$id] = true;
            continue;
        }
        // Explicit geography and house numbers distinguish real branches.
        if (($cityA !== '' && $cityB !== '' && $cityA !== $cityB)
            || ($address['house'] !== '' && $candidate['house'] !== '' && $address['house'] !== $candidate['house'])) continue;
        $streetA = handbook_suggestion_street_key($address['address']);
        $streetB = handbook_suggestion_street_key($candidate['address']);
        preg_match_all('/\b\d+[a-z]?\b/', $streetA, $numbersA);
        preg_match_all('/\b\d+[a-z]?\b/', $streetB, $numbersB);
        if ($address['complete'] && $candidate['complete']) {
            // Shared city/ward text must not conceal a different numbered road.
            if ($numbersA[0] !== $numbersB[0]) continue;
            $streetScore = medical_doctor_facility_text_similarity($streetA, $streetB);
            if ($streetScore < 0.8) continue;
            if ($nameScore >= 0.9 && $streetScore >= 0.9) {
                $matches[$id] = ($nameScore + $streetScore) / 2;
            } else {
                $near[$id] = true;
            }
        } else {
            // No address is not proof of a new branch, even for an exact name.
            $near[$id] = true;
        }
    }
    arsort($matches, SORT_NUMERIC);
    $ids = array_map('intval', array_keys($matches));
    $candidates = array_values(array_unique(array_merge($ids, array_map('intval', array_keys($near)))));
    if ($near !== [] || count($ids) > 1) return ['status' => 'ambiguous', 'id' => null, 'candidate_ids' => $candidates, 'reason' => 'branch_needs_review'];
    if ($ids !== []) return ['status' => 'matched', 'id' => $ids[0], 'candidate_ids' => $ids, 'reason' => 'brand_and_physical_address_match'];
    return ['status' => 'none', 'id' => null, 'candidate_ids' => [], 'reason' => 'no_existing_branch'];
}
