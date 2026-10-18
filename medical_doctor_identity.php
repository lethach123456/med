<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_doctor_content.php';

/** Pure identity matching; never opens a connection or edits an existing profile. */
function medical_doctor_identity_name(string $name): string
{
    $name = medical_doctor_facility_match_text($name);
    $credential = '(?:bac si(?: chuyen khoa (?:ii?|[12])| noi tru)?|bs(?:\s*ck\s*(?:ii?|[12]))?|bsnt|dr|doctor|ths|thac si|ts|tien si|pgs|pho giao su|gs|giao su|ck\s*(?:ii?|[12])|md|ph\s*d|mbbs|msc)';
    $name = preg_replace('/^(?:' . $credential . '\s+)+/', '', $name) ?? $name;
    return trim(preg_replace('/(?:\s+' . $credential . ')+$/', '', $name) ?? $name);
}

function medical_doctor_identity_given_name(string $left, string $right): bool
{
    $a = explode(' ', medical_doctor_identity_name($left));
    $b = explode(' ', medical_doctor_identity_name($right));
    $a = (string) end($a);
    $b = (string) end($b);
    return $a !== '' && $b !== '' && ($a === $b
        || (min(strlen($a), strlen($b)) >= 5 && medical_doctor_facility_text_similarity($a, $b) >= 0.9));
}

function medical_doctor_identity_specialty_score(string $left, string $right): float
{
    $a = medical_doctor_facility_match_text($left);
    $b = medical_doctor_facility_match_text($right);
    if ($a === '' || $b === '') return 0.0;
    $family = static function (string $key): string {
        if (preg_match('/\b(?:rang ham mat|nha khoa|chinh nha|nieng rang|dentistry|orthodontics)\b/', $key)) return 'dental';
        if (preg_match('/\b(?:nhan khoa|ophthalmology|chuyen khoa mat)\b/', $key)
            || $key === 'mat' || str_starts_with($key, 'mat tre em')) return 'eye';
        return '';
    };
    if ($family($a) !== '' && $family($a) === $family($b)) return 0.95;
    return medical_doctor_facility_token_similarity($a, $b);
}

function medical_doctor_identity_workplace_score(string $left, string $right, string $city = ''): float
{
    $normalize = static function (string $name) use ($city): string {
        $name = medical_doctor_facility_match_text($name);
        $name = preg_replace('/^bv\s+/', 'benh vien ', $name) ?? $name;
        $name = preg_replace('/^pk\s+/', 'phong kham ', $name) ?? $name;
        $cityKey = medical_doctor_facility_city_key($city);
        if ($cityKey !== '' && str_ends_with($name, ' ' . $cityKey)) $name = trim(substr($name, 0, -strlen($cityKey)));
        return $name;
    };
    $a = $normalize($left);
    $b = $normalize($right);
    if ($a === '' || $b === '') return 0.0;
    return medical_doctor_facility_name_similarity($a, $b);
}

/** Same house/street/city while allowing optional administrative ward wording. */
function medical_doctor_identity_address_score(string $left, string $right, string $leftCity = '', string $rightCity = ''): float
{
    $a = medical_doctor_facility_address_identity($left, $leftCity);
    $b = medical_doctor_facility_address_identity($right, $rightCity);
    if (!$a['complete'] || !$b['complete'] || $a['house'] !== $b['house'] || $a['locality'] !== $b['locality']) return 0.0;
    $street = static function (string $raw): string {
        $first = (preg_split('/[,;]/u', trim($raw)) ?: [''])[0];
        $first = preg_replace('/^(?:số\s+|so\s+)?\d+[a-z]?(?:\s*[\/\-]\s*\d+[a-z]?)*(?=\s|,|$)/iu', '', $first) ?? '';
        return trim(preg_replace('/^d\s+/', '', medical_doctor_facility_match_text($first, true)) ?? '');
    };
    $sa = $street($left);
    $sb = $street($right);
    preg_match_all('/\b\d+[a-z]?\b/', $sa, $na);
    preg_match_all('/\b\d+[a-z]?\b/', $sb, $nb);
    if ($na[0] !== $nb[0]) return 0.0;
    $score = medical_doctor_facility_text_similarity($sa, $sb);
    return $score >= 0.95 ? $score : 0.0;
}

/**
 * A probable existing person with insufficient/conflicting context is NOT a
 * new doctor. Return ambiguity so the caller can request an explicit checked ID.
 * @return array{status:string,id:?int,candidate_ids:list<int>,reason:string}
 */
function medical_doctor_identity_match(array $item, array $rows): array
{
    $name = medical_doctor_identity_name((string) ($item['name'] ?? ''));
    $specialty = (string) ($item['specialty_text'] ?? $item['specialty'] ?? '');
    $facility = (string) ($item['facility_name'] ?? '');
    $address = (string) ($item['address_text'] ?? $item['address'] ?? '');
    $city = (string) ($item['city'] ?? '');
    $identity = medical_doctor_facility_address_identity($address, $city);
    $matches = []; $near = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0 || $name === '') continue;
        $candidateName = medical_doctor_identity_name((string) ($row['name'] ?? ''));
        $nameScore = medical_doctor_facility_text_similarity($name, $candidateName);
        $tokens = explode(' ', $name); $candidateTokens = explode(' ', $candidateName);
        $sortedTokens = $tokens; $sortedCandidateTokens = $candidateTokens;
        sort($sortedTokens, SORT_STRING); sort($sortedCandidateTokens, SORT_STRING);
        $reorderedName = $name !== $candidateName && $sortedTokens === $sortedCandidateTokens;
        // A missing middle name is not enough to link, but must not seed another person.
        $shortenedName = count($tokens) >= 2 && count($candidateTokens) >= 2
            && $tokens[0] === $candidateTokens[0] && end($tokens) === end($candidateTokens)
            && count($tokens) !== count($candidateTokens)
            && (array_diff($tokens, $candidateTokens) === [] || array_diff($candidateTokens, $tokens) === []);
        if ($nameScore < 0.8 && !$shortenedName && !$reorderedName) continue;
        $sameName = $name === $candidateName;
        $candidateCity = (string) ($row['city'] ?? '');
        $candidateAddress = (string) ($row['address_text'] ?? '');
        $candidate = medical_doctor_facility_address_identity($candidateAddress, $candidateCity);
        $cityA = medical_doctor_facility_city_key($city) ?: $identity['locality'];
        $cityB = medical_doctor_facility_city_key($candidateCity) ?: $candidate['locality'];
        $sameCity = $cityA !== '' && $cityA === $cityB;
        $cityConflict = $cityA !== '' && $cityB !== '' && $cityA !== $cityB;
        $candidateFacility = (string) ($row['facility_name'] ?? '');
        $workplaceScore = medical_doctor_identity_workplace_score($facility, $candidateFacility, $city ?: $candidateCity);
        $specialtyScore = medical_doctor_identity_specialty_score($specialty, (string) ($row['specialty_text'] ?? ''));
        $addressScore = medical_doctor_identity_address_score($address, $candidateAddress, $city, $candidateCity);
        $addressConflict = $identity['city_conflict'] || $candidate['city_conflict']
            || ($identity['house'] !== '' && $candidate['house'] !== '' && $identity['house'] !== $candidate['house'])
            || ($identity['complete'] && $candidate['complete'] && $addressScore === 0.0);
        $workplaceConflict = $facility !== '' && $candidateFacility !== '' && $workplaceScore < 0.9;
        $givenName = medical_doctor_identity_given_name($name, $candidateName);
        $strongContext = ($specialtyScore >= 0.9 && ($sameCity || $workplaceScore >= 0.95 || $addressScore >= 0.95))
            || ($sameName && $workplaceScore >= 0.95 && ($sameCity || $addressScore >= 0.95)
                && ($specialty === '' || trim((string) ($row['specialty_text'] ?? '')) === '' || $specialtyScore >= 0.9));
        // Similar names in the same large city/specialty may be different people.
        // A real spelling difference needs corroborating workplace AND address.
        $strongName = $sameName || (!$shortenedName && !$reorderedName && $nameScore >= 0.9
            && $addressScore >= 0.95 && $workplaceScore >= 0.95 && $specialtyScore >= 0.9);
        if ($givenName && $strongName && $strongContext && !$cityConflict && !$addressConflict && !$workplaceConflict) {
            $matches[$id] = ($nameScore + max($specialtyScore, $workplaceScore, $addressScore)) / 2;
        } elseif ($sameName || $shortenedName || $reorderedName
            || (($sameCity || $workplaceScore >= 0.8 || $addressScore >= 0.95) && $nameScore >= 0.8)) {
            $near[$id] = true;
        }
    }
    arsort($matches, SORT_NUMERIC);
    $ids = array_map('intval', array_keys($matches));
    $candidates = array_values(array_unique(array_merge($ids, array_map('intval', array_keys($near)))));
    if ($near !== [] || count($matches) > 1) return ['status' => 'ambiguous', 'id' => null, 'candidate_ids' => $candidates, 'reason' => 'identity_needs_review'];
    if ($ids !== []) return ['status' => 'matched', 'id' => $ids[0], 'candidate_ids' => $ids, 'reason' => 'name_and_context_match'];
    return ['status' => 'none', 'id' => null, 'candidate_ids' => [], 'reason' => 'no_existing_identity'];
}
