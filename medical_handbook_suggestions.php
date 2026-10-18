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
    return trim(preg_replace('/^(?:(?:bac si(?: chuyen khoa (?:ii?|[12]))?|bs|dr|doctor|ths|thac si|ts|tien si|pgs|pho giao su|gs|giao su|ckii?)\s+)+/', '', $name) ?? $name);
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
    $streetA = handbook_suggestion_street_key($left);
    $streetB = handbook_suggestion_street_key($right);
    preg_match_all('/\b\d+[a-z]?\b/', $streetA, $aNumbers);
    preg_match_all('/\b\d+[a-z]?\b/', $streetB, $bNumbers);
    // Preserve numbered roads, but not ward/district numbers that can change.
    if ($aNumbers[0] !== $bNumbers[0]) return 0.0;
    $wholeScore = medical_doctor_facility_text_similarity($a['key'], $b['key']);
    $streetScore = medical_doctor_facility_text_similarity($streetA, $streetB);
    // A long shared district/city must not hide a different street.
    if ($streetScore < 0.9) return 0.0;
    return $streetScore >= 0.95 ? max($wholeScore, $streetScore) : $wholeScore;
}

/** Compare the street independently of optional/renamed administrative wards. */
function handbook_suggestion_street_key(string $address): string
{
    $street = (preg_split('/[,;]/u', trim($address)) ?: [''])[0];
    $street = preg_replace('/^(?:số\s+|so\s+)?\d+[a-z]?(?:\s*[\/\-]\s*\d+[a-z]?)*(?=\s|,|$)/iu', '', $street) ?? '';
    $street = medical_doctor_facility_match_text($street, true);
    return trim(preg_replace('/^d\s+/', '', $street) ?? $street);
}

/** Remove only a generic SEO suffix/city; never discard a branch marker. */
function handbook_suggestion_facility_name_key(string $name, string $city): string
{
    $city = medical_doctor_facility_city_key($city);
    $parts = preg_split('/\s+[-–—|]\s+/u', trim($name)) ?: [$name];
    if (count($parts) > 1 && $city !== '') {
        $tail = medical_doctor_facility_match_text(implode(' ', array_slice($parts, 1)));
        $allowed = array_merge(explode(' ', $city), ['nha', 'khoa', 'tham', 'my', 'quoc', 'te', 'uy', 'tin', 'tai', 'o', 'tp', 'thanh', 'pho', 'dental', 'clinic']);
        if (array_diff(explode(' ', $tail), $allowed) === []) $name = $parts[0];
    }
    $key = medical_doctor_facility_brand_key($name);
    if ($city !== '' && str_ends_with($key, ' ' . $city)) {
        $base = trim(substr($key, 0, -strlen($city)));
        if (strlen($base) >= 3) $key = $base;
    }
    return $key;
}

function handbook_suggestion_facility_name_score(string $left, string $right, string $leftCity = '', string $rightCity = ''): float
{
    return medical_doctor_facility_name_similarity(
        handbook_suggestion_facility_name_key($left, $leftCity), handbook_suggestion_facility_name_key($right, $rightCity));
}

/** Contact is corroboration, never a replacement for a branch's street/house. */
function handbook_suggestion_shared_contact(array $left, array $right): bool
{
    $contactKey = static function (array $row): string {
        $url = handbook_suggestion_public_url($row['website'] ?? $row['website_url'] ?? null);
        if ($url === null) return '';
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST))) ?? '';
        // A social/directory host is shared by unrelated businesses. Do not
        // mistake two different Facebook pages (or shortlinks) for one site.
        foreach (['facebook.com', 'fb.com', 'fb.me', 'instagram.com', 'tiktok.com', 'youtube.com',
            'youtu.be', 'google.com', 'google.com.vn', 'maps.app.goo.gl', 'goo.gl', 'medreview.vn',
            'bookingcare.vn', 'youmed.vn', 'nhakhoahub.vn', 'zalo.me', 'linktr.ee'] as $sharedHost) {
            if ($host === $sharedHost || str_ends_with($host, '.' . $sharedHost)) {
                $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
                if ($path === '' || $path === '/profile.php' || $path === '/watch') return '';
                return $host . '/' . ltrim($path, '/') . '?' . (string) parse_url($url, PHP_URL_QUERY);
            }
        }
        return $host;
    };
    $a = $contactKey($left); $b = $contactKey($right);
    if ($a !== '' && $a === $b) return true;
    $phone = static function (array $row): array {
        $phones = [];
        foreach (preg_split('/[,;\/|]/', (string) ($row['phone'] ?? $row['phone_text'] ?? '')) ?: [] as $part) {
            $number = preg_replace('/\D/', '', $part) ?? '';
            if (str_starts_with($number, '84') && strlen($number) >= 11) $number = '0' . substr($number, 2);
            if (strlen($number) >= 9 && strlen($number) <= 11) $phones[] = $number;
        }
        return $phones;
    };
    return array_intersect($phone($left), $phone($right)) !== [];
}

/**
 * Match the physical branch, not the entire presentation string. Already
 * duplicated records may use one canonical ID only when the same branch AND
 * official contact agree. Otherwise keep ambiguity; never seed a third copy.
 */
function handbook_suggestion_match_facility(array $item, array $rows, ?bool &$ambiguous = null): ?array
{
    $ambiguous = false; $matches = []; $near = false;
    $name = (string) ($item['name'] ?? '');
    $address = (string) ($item['address'] ?? '');
    $city = (string) ($item['city'] ?? '');
    $identity = medical_doctor_facility_address_identity($address, $city);
    if ($identity['city_conflict']) { $ambiguous = true; return null; }
    foreach ($rows as $row) {
        $candidateCity = (string) ($row['city'] ?? '');
        $candidateAddress = (string) ($row['address_text'] ?? '');
        $candidate = medical_doctor_facility_address_identity($candidateAddress, $candidateCity);
        $sameCity = handbook_suggestion_same_city($city, $candidateCity)
            || ($identity['locality'] !== '' && $identity['locality'] === $candidate['locality']);
        if (($city !== '' && $candidateCity !== '' && !handbook_suggestion_same_city($city, $candidateCity))
            || ($identity['locality'] !== '' && $candidate['locality'] !== '' && $identity['locality'] !== $candidate['locality'])) continue;
        $nameScore = handbook_suggestion_facility_name_score($name, (string) $row['name'], $city, $candidateCity);
        // Preserve truly distinct house/branch numbers, even on one website.
        if ($identity['house'] !== '' && $candidate['house'] !== '' && $identity['house'] !== $candidate['house']) continue;
        $addressScore = handbook_suggestion_address_score($address, $candidateAddress, $city, $candidateCity);
        if ($nameScore >= 0.9 && $addressScore >= 0.9) {
            $matches[(int) $row['id']] = ['row' => $row, 'score' => ($nameScore + $addressScore) / 2];
        } elseif ($sameCity && (($nameScore >= 0.8 && $addressScore >= 0.8)
            || ($nameScore >= 0.9 && (handbook_suggestion_shared_contact($item, $row)
                || !$identity['complete'] || !$candidate['complete'])))) {
            $near = true;
        }
    }
    usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    if ($matches === [] || $near) { $ambiguous = $near; return null; }
    if (isset($matches[1]) && $matches[0]['score'] - $matches[1]['score'] < 0.03) {
        $best = $matches[0]['row'];
        foreach ($matches as $match) {
            $other = $match['row'];
            if ((int) $best['id'] === (int) $other['id']) continue;
            if (handbook_suggestion_facility_name_score((string) $best['name'], (string) $other['name'], (string) ($best['city'] ?? ''), (string) ($other['city'] ?? '')) < 0.98
                || handbook_suggestion_address_score((string) $best['address_text'], (string) $other['address_text'], (string) ($best['city'] ?? ''), (string) ($other['city'] ?? '')) < 0.98
                || !handbook_suggestion_shared_contact($best, $other)) { $ambiguous = true; return null; }
        }
        // Stable representative of confirmed duplicate rows: written, public,
        // then oldest ID. No deletion or implicit DB merge is performed.
        usort($matches, static function (array $a, array $b): int {
            return ((int) ($b['row']['has_content'] ?? 0) <=> (int) ($a['row']['has_content'] ?? 0))
                ?: ((int) (($b['row']['status'] ?? '') === 'published') <=> (int) (($a['row']['status'] ?? '') === 'published'))
                ?: ((int) $a['row']['id'] <=> (int) $b['row']['id']);
        });
    }
    return $matches[0]['row'];
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
        $stmt = $pdo->prepare("SELECT id, slug, name, address_text, city, category, website_url, phone_text, status,
            CASE WHEN TRIM(COALESCE(content,'')) <> '' THEN 1 ELSE 0 END AS has_content
            FROM medical_facilities WHERE language_code=:locale");
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
        if ($id !== null) {
            $row = null;
            foreach ($facilityRows as $candidate) if ((int) $candidate['id'] === $id) { $row = $candidate; break; }
            $verified = $row !== null && $name !== '' && $address !== '' && handbook_suggestion_match_facility($item, [$row]) !== null;
            $resolvedFacilities[] = handbook_suggestion_resolution($item, 'facility_id', $verified ? $row : null,
                $verified ? 'matched' : 'unresolved', $verified ? 1.0 : 0.0, $verified ? '' : 'id_mismatch');
            continue;
        }
        $ambiguous = false;
        $match = $name !== '' ? handbook_suggestion_match_facility($item, $facilityRows, $ambiguous) : null;
        if ($match !== null) {
            $nameScore = handbook_suggestion_facility_name_score($name, (string) $match['name'], $city, (string) $match['city']);
            $addressScore = handbook_suggestion_address_score($address, (string) $match['address_text'], $city, (string) $match['city']);
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
        $row = ['id' => (int) $pdo->lastInsertId(), 'slug' => $slug, 'name' => $name, 'address_text' => $address, 'city' => $city, 'category' => $category,
            'website_url' => $website, 'phone_text' => $phone, 'has_content' => 0, 'status' => 'published'];
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
            // Same doctor in this city with different specialty/workplace text
            // is not proof of a new person. Stop duplicate seeding on a near
            // identity even if the strict match below cannot establish a link.
            $possibleDuplicate = $id === null && $nameScore >= 0.95 && $sameCity
                && handbook_suggestion_doctor_given_name_compatible($name, (string) $candidate['name']);
            $candidateSpecialty = medical_doctor_facility_match_text((string) ($candidate['specialty_text'] ?? ''));
            $specialtyScore = $specialty !== '' && $candidateSpecialty !== ''
                ? handbook_suggestion_score(medical_doctor_facility_match_text($specialty), $candidateSpecialty) : 0.0;
            $candidateFacility = medical_doctor_facility_match_text((string) ($candidate['facility_name'] ?? ''));
            $facilityScore = $facility !== '' && $candidateFacility !== ''
                ? handbook_suggestion_facility_name_score($facility, (string) $candidate['facility_name'], $city, $candidateCity) : 0.0;
            if ($facility !== '' && $candidateFacility !== '' && $facilityScore < 0.8) { if ($possibleDuplicate) $near = true; continue; }
            $addressScore = $address !== '' && trim((string) ($candidate['address_text'] ?? '')) !== ''
                ? handbook_suggestion_address_score($address, (string) $candidate['address_text'], $city, $candidateCity) : 0.0;
            if ($address !== '' && trim((string) ($candidate['address_text'] ?? '')) !== '' && $addressScore === 0.0) { if ($possibleDuplicate) $near = true; continue; }
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
            } elseif ($possibleDuplicate) {
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
