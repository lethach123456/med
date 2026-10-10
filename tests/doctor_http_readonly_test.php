<?php
declare(strict_types=1);
// CLI smoke test: reads real queue only; POST bodies are deliberately invalid and cannot update records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/db.php';
$key = (string) (getenv('MEDICAL_CONTENT_API_KEY') ?: site_setting('medical_content_api_key', 'them'));
if ($key === '') throw new RuntimeException('API key unavailable.');
$request = static function (string $endpoint, string $method = 'GET', ?string $body = null, bool $auth = true) use ($key): array {
    $curl = curl_init('http://127.0.0.1:8768/api/medical/' . $endpoint);
    $headers = ['Content-Type: application/json', 'Origin: https://gemini.google.com'];
    if ($auth) $headers[] = 'X-Medical-Api-Key: ' . $key;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    return [$status, json_decode(is_string($raw) ? $raw : '', true)];
};
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++; echo "PASS: {$label}\n";
};
[$status] = $request('doctors-needing-content.php', 'GET', null, false);
$assert($status === 401, 'missing API key blocked');
[$status] = $request('doctors-needing-content.php', 'OPTIONS', null, false);
$assert($status === 204, 'Gemini CORS preflight');
[$status, $queue] = $request('doctors-needing-content.php?limit=1');
$assert($status === 200 && ($queue['type'] ?? '') === 'doctor', 'authenticated doctor queue');
$assert(($queue['contract_version'] ?? 0) === 1 && ($queue['claim_required'] ?? false), 'version and lease contract');
$assert(isset($queue['items']) && is_array($queue['items']), 'queue shape');
if ($queue['items'] !== []) {
    $item = $queue['items'][0];
    $assert(!isset($item['ai_writer_claim_json']) && !isset($item['writer_claim']['claim_token']), 'queue has no private lease');
    $assert(str_contains($item['prompt'], 'MEDREVIEW_DOCTOR_RESEARCH_CONTRACT_V2') && isset($item['output_template']['education_json']), 'full doctor prompt from API');
    [$status, $record] = $request('doctor-content.php?id=' . (int) $item['id']);
    $assert($status === 200 && (int) ($record['item']['id'] ?? 0) === (int) $item['id'], 'record read endpoint');
    [$status, $prompt] = $request('prompt.php?type=doctor&id=' . (int) $item['id'] . '&name=' . rawurlencode($item['name']));
    $assert($status === 200 && ($prompt['output_template']['id'] ?? 0) === (int) $item['id']
        && str_contains($prompt['prompt'] ?? '', 'MEDREVIEW_DOCTOR_RESEARCH_CONTRACT_V2'), 'manual doctor prompt uses database source');
}
// Check the legal contract even when the real queue is empty; this prompt GET
// does not create a doctor, acquire a claim or save any editorial data.
[$status, $legalPrompt] = $request('prompt.php?type=doctor&name=Doctor%20HTTP%20Test');
$assert($status === 200 && str_contains($legalPrompt['prompt'] ?? '', 'MEDREVIEW_DOCTOR_RESEARCH_CONTRACT_V2'), 'manual doctor prompt includes current transport contract');
foreach (['professional_profile_url', 'practice_license_type', 'practice_license_number',
    'practice_license_issuer', 'practice_license_issued_date', 'practice_license_scope',
    'practice_registry_url', 'practice_registration_json', 'legal_documents_json',
    'legal_notes', 'legal_source_ids_json'] as $field) {
    $assert(array_key_exists($field, $legalPrompt['output_template'] ?? [])
        && str_contains($legalPrompt['prompt'] ?? '', $field), 'HTTP doctor prompt contains legal field: ' . $field);
}
[$status, $legacy] = $request('facilities-needing-content.php?type=doctor&limit=1');
$assert($status === 200 && ($legacy['type'] ?? '') === 'doctor', 'legacy type=doctor routes to doctors');
[$status] = $request('doctor-content-update.php', 'GET');
$assert($status === 405, 'save rejects GET');
[$status] = $request('doctor-content-update.php', 'POST', '{bad');
$assert($status === 400, 'malformed JSON rejected');
[$status] = $request('doctor-content-update.php', 'POST', '{"items":[]}');
$assert($status === 422, 'empty batch rejected');
[$status] = $request('doctor-content-update.php', 'POST', '{"id":0,"insufficient_data":true}');
$assert($status === 422, 'invalid record rejected before transaction');
echo "Doctor HTTP: {$checks} checks passed. No real profile updated or claimed.\n";
