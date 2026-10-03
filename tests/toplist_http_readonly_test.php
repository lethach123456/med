<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/db.php';
$base = (string) (getenv('TOPLIST_TEST_BASE_URL') ?: 'http://127.0.0.1:8768');
$key = (string) (getenv('MEDICAL_CONTENT_API_KEY') ?: site_setting('medical_content_api_key', 'them'));
$request = static function (string $endpoint, string $method = 'GET', ?string $body = null, bool $auth = true) use ($base, $key): array {
    $curl = curl_init($base . '/api/medical/' . $endpoint);
    $headers = ['Content-Type: application/json'];
    if ($auth) $headers[] = 'X-Medical-Api-Key: ' . $key;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    return [$status, json_decode(is_string($raw) ? $raw : '', true)];
};
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++; echo "PASS: {$label}\n";
};
[$status] = $request('toplists-needing-facilities.php', 'GET', null, false);
$assert($status === 401, 'queue requires key');
[$status, $legacyQueue] = $request('toplists-needing-facilities.php?limit=1');
$assert($status === 200 && ($legacyQueue['entity_type'] ?? '') === 'facility', 'legacy queue remains facility-only');
[$status, $genericQueue] = $request('toplists-needing-members.php?limit=1');
$assert($status === 200 && ($genericQueue['entity_type'] ?? '') === 'all', 'generic queue supports both types');
[$status, $queue] = $request('toplists-needing-facilities.php?entity_type=doctor&limit=1');
$assert($status === 200 && is_array($queue['items'] ?? null), 'typed doctor queue');
$assert(($queue['entity_type'] ?? '') === 'doctor', 'queue reports entity filter');
foreach ($queue['items'] as $item) $assert(($item['entity_type'] ?? '') === 'doctor' && str_contains($item['prompt'] ?? '', '"doctors"'), 'doctor queue item prompt');
[$status] = $request('toplists-needing-facilities.php?entity_type=bad');
$assert($status === 422, 'queue rejects unknown type');
[$status, $prompt] = $request('prompt.php?type=toplist&entity_type=doctor&title=Doctor%20Test');
$assert($status === 200 && ($prompt['entity_type'] ?? '') === 'doctor' && str_contains($prompt['prompt'] ?? '', '"doctors"') && str_contains($prompt['prompt'] ?? '', '```json'), 'doctor manual prompt contract');
[$status, $prompt] = $request('prompt.php?type=toplist&entity_type=facility&title=Facility%20Test');
$assert($status === 200 && str_contains($prompt['prompt'] ?? '', '"facilities"'), 'facility prompt retained');
[$status, $queue] = $request('toplists-needing-members.php?entity_type=mixed&limit=1');
$assert($status === 200 && ($queue['entity_type'] ?? '') === 'mixed' && is_array($queue['items'] ?? null), 'mixed queue filter');
foreach ($queue['items'] as $item) $assert(($item['entity_type'] ?? '') === 'mixed' && str_contains($item['prompt'] ?? '', '"members"'), 'mixed queue item prompt');
[$status, $prompt] = $request('prompt.php?type=toplist&entity_type=mixed&title=Mixed%20Test');
$assert($status === 200 && ($prompt['entity_type'] ?? '') === 'mixed' && str_contains($prompt['prompt'] ?? '', '"members"') && str_contains($prompt['prompt'] ?? '', '```json'), 'mixed manual prompt contract');
if (in_array('--queue-only', $argv, true)) { echo "Toplist queue/prompt HTTP: {$checks} checks passed.\n"; exit; }
foreach (['toplist-members-update.php', 'toplist-doctors-update.php', 'toplist-facilities-update.php'] as $endpoint) {
    [$status] = $request($endpoint, 'POST', '{}', false);
    $assert($status === 401, $endpoint . ' requires key');
    [$status] = $request($endpoint);
    $assert($status === 405, $endpoint . ' rejects GET');
    [$status] = $request($endpoint, 'POST', '{bad');
    $assert($status === 400, $endpoint . ' rejects malformed JSON');
    [$status] = $request($endpoint, 'POST', '{"items":[]}');
    $assert($status === 422, $endpoint . ' rejects empty batch');
    // Deliberately invalid ID: never enters a record update or inserts any profile.
    [$status, $result] = $request($endpoint, 'POST', '{"toplist_id":0,"entity_type":"doctor","doctors":[{"doctor_id":1}]}');
    $assert($status === 422 && ($result['updated_count'] ?? -1) === 0, $endpoint . ' refuses invalid record');
    [$status, $result] = $request($endpoint, 'POST', '{"toplist_id":0,"entity_type":"mixed","members":[{"type":"facility","facility_id":1},{"type":"doctor","doctor_id":1}]}');
    $assert($status === 422 && ($result['updated_count'] ?? -1) === 0, $endpoint . ' refuses invalid mixed record');
}
echo "Toplist HTTP: {$checks} checks passed. No real article/profile updated.\n";
