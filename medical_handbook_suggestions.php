<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_directory.php';

/** Public source references are required before an AI suggestion can seed a profile. */
function handbook_suggestion_public_url(mixed $value): ?string
{
    try { $url = medical_doctor_http_url($value, 'evidence_url'); }
    catch (InvalidArgumentException $e) { return null; }
    if ($url === null) return null;
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) return null;
    if (filter_var($host, FILTER_VALIDATE_IP)
        && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
    return $url;
}

function handbook_suggestion_text(array $item, string $key, int $limit): string
{
    $value = $item[$key] ?? '';
    if (!is_string($value) || mb_strlen($value, 'UTF-8') > $limit || str_contains($value, "\0")) {
        throw new InvalidArgumentException("{$key} của hồ sơ gợi ý không hợp lệ.");
    }
    return trim($value);
}

function handbook_suggestion_identity_name(string $name): string
{
    $name = medical_doctor_facility_match_text($name);
    return trim(preg_replace('/^(?:(?:bac si|bs|dr|doctor|ths|ts|pgs|gs)\s+)+/', '', $name) ?? $name);
}

/** Short given names must agree exactly; a one-letter difference can be another person. */
function handbook_suggestion_doctor_given_name_compatible(string $left, string $right): bool
{
    $a = explode(' ', handbook_suggestion_identity_name($left));
    $b = explode(' ', handbook_suggestion_identity_name($right));
    $givenA = (string) end($a);
    $givenB = (string) end($b);
    if ($givenA === '' || $givenB === '') return false;
    if ($givenA === $givenB) return true;
    return min(strlen($givenA), strlen($givenB)) >= 5
        && medical_doctor_facility_text_similarity($givenA, $givenB) >= 0.9;
}

function handbook_suggestion_score(string $left, string $right): float
{
    if ($left === '' || $right === '') return 0.0;
    return medical_doctor_facility_token_similarity($left, $right);
}

function handbook_suggestion_same_city(string $left, string $right): bool
{
    $a = medical_doctor_facility_city_key($left);
    $b = medical_doctor_facility_city_key($right);
    return $a !== '' && $b !== '' && $a === $b;
}

/** Require a complete street address and preserve house/locality boundaries. */
function handbook_suggestion_address_score(string $left, string $right, string $leftCity = '', string $rightCity = ''): float
{
    $a = medical_doctor_facility_address_identity($left, $leftCity);
    $b = medical_doctor_facility_address_identity($right, $rightCity);
    if (!$a['complete'] || !$b['complete'] || $a['house'] !== $b['house'] || $a['locality'] !== $b['locality']) return 0.0;
    preg_match_all('/\b\d+[a-z]?\b/', $a['key'], $aNumbers);
    preg_match_all('/\b\d+[a-z]?\b/', $b['key'], $bNumbers);
    if ($aNumbers[0] !== $bNumbers[0]) return 0.0;
    return medical_doctor_facility_text_similarity($a['key'], $b['key']);
}

function handbook_suggestion_resolution(array $item, string $key, ?array $row, string $resolution, float $score = 0.0, string $reason = ''): array
{
    $item[$key] = $row === null ? null : (int) $row['id'];
    $item['slug'] = $row === null ? null : (string) $row['slug'];
    $item['resolution'] = $resolution;
    $item['match_score'] = round($score, 3);
    if ($reason !== '') $item['resolution_reason'] = $reason;
    return $item;
}

function handbook_suggestion_slug(string $name, string $identity): string
{
    $base = substr(slugify($name), 0, 165) ?: 'ho-so';
    return $base . '-' . substr(hash('sha256', $identity), 0, 16);
}

/**
 * Match suggestions inside the caller's transaction. No runtime migrations or
 * implicit commits. New profiles stay unverified and content-empty so they
 * enter the existing AI-writing queues; article links can wait for content.
 */
function handbook_suggested_entities_resolve(PDO $pdo, array $facilities, array $doctors, string $locale = 'vi'): array
{
    if ($facilities === [] && $doctors === []) return ['facilities' => [], 'doctors' => []];
    if (!$pdo->inTransaction()) throw new LogicException('Gợi ý hồ sơ và lưu bài Cẩm nang cần cùng transaction.');
    $locale = site_normalize_locale($locale);
    if (!in_array($locale, ['vi', 'en'], true)) throw new InvalidArgumentException('Ngôn ngữ hồ sơ gợi ý không hợp lệ.');
    if (!array_is_list($facilities) || !array_is_list($doctors) || count($facilities) > 8 || count($doctors) > 8) {
        throw new InvalidArgumentException('Mỗi loại hồ sơ gợi ý tối đa 8 mục.');
    }

    $facilityRows = [];
    if ($facilities !== []) {
        $stmt = $pdo->prepare('SELECT id, slug, name, address_text, city, category FROM medical_facilities WHERE language_code=:locale');
        $stmt->execute([':locale' => $locale]);
        $facilityRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $resolvedFacilities = [];
    foreach ($facilities as $item) {
        if (!is_array($item) || array_is_list($item)) throw new InvalidArgumentException('Gợi ý cơ sở phải là object.');
        $name = handbook_suggestion_text($item, 'name', 160);
        $address = handbook_suggestion_text($item, 'address', 255);
        $city = handbook_suggestion_text($item, 'city', 120);
        $category = handbook_suggestion_text($item, 'category', 120);
        $phone = handbook_suggestion_text($item, 'phone', 80);
        $website = handbook_suggestion_public_url($item['website'] ?? null);
        $evidence = handbook_suggestion_public_url($item['evidence_url'] ?? null);
        $id = $item['facility_id'] ?? null;
        if ($id !== null && (!is_int($id) || $id <= 0)) throw new InvalidArgumentException('facility_id phải là số nguyên dương hoặc null.');
        $location = ['facility_name' => $name, 'address_text' => $address, 'city' => $city];
        if ($id !== null) {
            $row = null;
            foreach ($facilityRows as $candidate) if ((int) $candidate['id'] === $id) { $row = $candidate; break; }
            $verified = $row !== null && $name !== '' && $address !== '' && medical_doctor_match_facility($location, [$row]) !== null;
            $resolvedFacilities[] = handbook_suggestion_resolution($item, 'facility_id', $verified ? $row : null,
                $verified ? 'matched' : 'unresolved', $verified ? 1.0 : 0.0, $verified ? '' : 'id_mismatch');
            continue;
        }
        $ambiguous = false;
        $match = $name !== '' && $address !== '' ? medical_doctor_match_facility($location, $facilityRows, $ambiguous) : null;
        if ($match !== null) {
            $nameScore = handbook_suggestion_score(medical_doctor_facility_match_text($name), medical_doctor_facility_match_text((string) $match['name']));
            $addressScore = medical_doctor_facility_text_similarity(
                medical_doctor_facility_match_text($address, true), medical_doctor_facility_match_text((string) $match['address_text'], true));
            $resolvedFacilities[] = handbook_suggestion_resolution($item, 'facility_id', $match, 'matched', min($nameScore, $addressScore));
            continue;
        }
        if (($ambiguous ?? false)) {
            $resolvedFacilities[] = handbook_suggestion_resolution($item, 'facility_id', null, 'unresolved', 0, 'ambiguous_match');
            continue;
        }
        $addressIdentity = medical_doctor_facility_address_identity($address, $city);
        if ($name === '' || !$addressIdentity['complete'] || $evidence === null) {
            $resolvedFacilities[] = handbook_suggestion_resolution($item, 'facility_id', null, 'unresolved', 0, 'insufficient_identity_or_evidence');
            continue;
        }
        $city = $city !== '' ? $city : preg_replace('/^(?:Thành phố|TP\.?|Tỉnh)\s+/iu', '', (string) $addressIdentity['city_label']);
        $identity = medical_doctor_facility_match_text($name) . '|' . $addressIdentity['house'] . '|' . $addressIdentity['key'];
        $slug = handbook_suggestion_slug($name . '-' . $city, $identity);
        $provenance = ['origin' => 'handbook_suggestion', 'name' => $name, 'address' => $address,
            'evidence_url' => $evidence, 'website' => $website];
        $insert = $pdo->prepare("INSERT INTO medical_facilities
            (slug,language_code,name,address_text,city,category,phone_text,website_url,full_json,status,verified,content)
            VALUES (:slug,:locale,:name,:address,:city,:category,:phone,:website,:provenance,'published',0,NULL)");
        $insert->execute([':slug' => $slug, ':locale' => $locale, ':name' => $name, ':address' => $address,
            ':city' => $city, ':category' => $category !== '' ? $category : 'Cơ sở y tế',
            ':phone' => $phone !== '' ? $phone : null, ':website' => $website !== null && strlen($website) <= 255 ? $website : null,
            ':provenance' => medical_directory_json_encode($provenance)]);
        $row = ['id' => (int) $pdo->lastInsertId(), 'slug' => $slug, 'name' => $name, 'address_text' => $address, 'city' => $city, 'category' => $category];
        $facilityRows[] = $row;
        $resolvedFacilities[] = handbook_suggestion_resolution($item, 'facility_id', $row, 'created', 1.0);
    }

    $doctorRows = [];
    if ($doctors !== []) {
        $stmt = $pdo->prepare('SELECT id, slug, name, specialty_text, city, facility_name, address_text FROM medical_doctors WHERE language_code=:locale');
        $stmt->execute([':locale' => $locale]);
        $doctorRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $resolvedDoctors = [];
    foreach ($doctors as $item) {
        if (!is_array($item) || array_is_list($item)) throw new InvalidArgumentException('Gợi ý bác sĩ phải là object.');
        $name = handbook_suggestion_text($item, 'name', 160);
        $specialty = handbook_suggestion_text($item, 'specialty', 160);
        $facility = handbook_suggestion_text($item, 'facility_name', 160);
        $address = handbook_suggestion_text($item, 'address', 10000);
        $city = handbook_suggestion_text($item, 'city', 120);
        $website = handbook_suggestion_public_url($item['website'] ?? null);
        $evidence = handbook_suggestion_public_url($item['evidence_url'] ?? null);
        $id = $item['doctor_id'] ?? null;
        if ($id !== null && (!is_int($id) || $id <= 0)) throw new InvalidArgumentException('doctor_id phải là số nguyên dương hoặc null.');
        $ranked = []; $near = false;
        foreach ($doctorRows as $candidate) {
            if ($id !== null && (int) $candidate['id'] !== $id) continue;
            $nameScore = handbook_suggestion_score(handbook_suggestion_identity_name($name), handbook_suggestion_identity_name((string) $candidate['name']));
            if ($nameScore < 0.8) continue;
            $candidateCity = (string) ($candidate['city'] ?? '');
            $sameCity = handbook_suggestion_same_city($city, $candidateCity);
            if ($city !== '' && $candidateCity !== '' && !$sameCity) continue;
            $candidateSpecialty = medical_doctor_facility_match_text((string) ($candidate['specialty_text'] ?? ''));
            $specialtyScore = $specialty !== '' && $candidateSpecialty !== ''
                ? handbook_suggestion_score(medical_doctor_facility_match_text($specialty), $candidateSpecialty) : 0.0;
            $candidateFacility = medical_doctor_facility_match_text((string) ($candidate['facility_name'] ?? ''));
            $facilityScore = $facility !== '' && $candidateFacility !== ''
                ? handbook_suggestion_score(medical_doctor_facility_match_text($facility), $candidateFacility) : 0.0;
            if ($facility !== '' && $candidateFacility !== '' && $facilityScore < 0.8) continue;
            $addressScore = $address !== '' && trim((string) ($candidate['address_text'] ?? '')) !== ''
                ? handbook_suggestion_address_score($address, (string) $candidate['address_text'], $city, $candidateCity) : 0.0;
            if ($address !== '' && trim((string) ($candidate['address_text'] ?? '')) !== '' && $addressScore === 0.0) continue;
            if (!handbook_suggestion_doctor_given_name_compatible($name, (string) $candidate['name'])) {
                if ($sameCity && ($facilityScore >= 0.9 || $addressScore >= 0.9)) $near = true;
                continue;
            }
            $strongContext = $specialtyScore >= 0.9 && ($sameCity || $addressScore >= 0.9)
                && ($facility === '' || $facilityScore >= 0.9 || $addressScore >= 0.95);
            if ($id !== null && $nameScore >= 0.95
                && ($specialty === '' || $candidateSpecialty === '' || $specialtyScore >= 0.9)
                && ($facility === '' || $candidateFacility === '' || $facilityScore >= 0.9)) {
                $ranked[(int) $candidate['id']] = ['row' => $candidate, 'score' => $nameScore];
                continue;
            }
            if ($nameScore >= 0.95 && $strongContext && ($facilityScore >= 0.9 || $addressScore >= 0.9 || $sameCity)) {
                $ranked[(int) $candidate['id']] = ['row' => $candidate, 'score' => min($nameScore, $specialtyScore)];
            } elseif ($nameScore >= 0.9 && $addressScore >= 0.95 && $specialtyScore >= 0.9 && $strongContext) {
                $ranked[(int) $candidate['id']] = ['row' => $candidate, 'score' => min($nameScore, $addressScore, $specialtyScore)];
            } elseif ($nameScore >= 0.8 && ($specialtyScore >= 0.8 || $addressScore >= 0.8 || $facilityScore >= 0.8)) {
                $near = true;
            }
        }
        uasort($ranked, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $matches = array_values($ranked);
        if ($id !== null && $matches === []) {
            $resolvedDoctors[] = handbook_suggestion_resolution($item, 'doctor_id', null, 'unresolved', 0.0, 'id_mismatch');
            continue;
        }
        if (count($matches) > 1 && $matches[0]['score'] - $matches[1]['score'] < 0.03) {
            $resolvedDoctors[] = handbook_suggestion_resolution($item, 'doctor_id', null, 'unresolved', 0.0, 'ambiguous_match');
            continue;
        }
        if ($matches !== [] && !$near) {
            $resolvedDoctors[] = handbook_suggestion_resolution($item, 'doctor_id', $matches[0]['row'], 'matched', $matches[0]['score']);
            continue;
        }
        if ($near) {
            $resolvedDoctors[] = handbook_suggestion_resolution($item, 'doctor_id', null, 'unresolved', 0.0, 'ambiguous_match');
            continue;
        }
        $addressIdentity = medical_doctor_facility_address_identity($address, $city);
        if ($name === '' || $specialty === '' || $evidence === null || ($city === '' && !$addressIdentity['complete'])
            || ($facility === '' && !$addressIdentity['complete'])) {
            $resolvedDoctors[] = handbook_suggestion_resolution($item, 'doctor_id', null, 'unresolved', 0.0, 'insufficient_identity_or_evidence');
            continue;
        }
        $identity = handbook_suggestion_identity_name($name) . '|'
            . medical_doctor_facility_match_text($specialty) . '|'
            . medical_doctor_facility_city_key($city) . '|'
            . medical_doctor_facility_match_text($facility) . '|'
            . $addressIdentity['key'];
        $slug = handbook_suggestion_slug($name . '-' . $specialty . '-' . $city, $identity);
        $provenance = ['origin' => 'handbook_suggestion', 'name' => $name, 'specialty' => $specialty,
            'facility_name' => $facility, 'address' => $address, 'city' => $city, 'evidence_url' => $evidence, 'website' => $website];
        $insert = $pdo->prepare("INSERT INTO medical_doctors
            (slug,language_code,name,specialty_text,facility_name,address_text,city,website_url,full_json,status,verified,content)
            VALUES (:slug,:locale,:name,:specialty,:facility,:address,:city,:website,:provenance,'published',0,NULL)");
        $insert->execute([':slug' => $slug, ':locale' => $locale, ':name' => $name, ':specialty' => $specialty,
            ':facility' => $facility, ':address' => $address !== '' ? $address : null, ':city' => $city,
            ':website' => $website, ':provenance' => medical_directory_json_encode($provenance)]);
        $row = ['id' => (int) $pdo->lastInsertId(), 'slug' => $slug, 'name' => $name, 'specialty_text' => $specialty,
            'city' => $city, 'facility_name' => $facility, 'address_text' => $address];
        $doctorRows[] = $row;
        $resolvedDoctors[] = handbook_suggestion_resolution($item, 'doctor_id', $row, 'created', 1.0);
    }
    return ['facilities' => $resolvedFacilities, 'doctors' => $resolvedDoctors];
}
