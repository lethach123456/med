<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/medical_directory.php';
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function (callable $run, string $label, string $class = InvalidArgumentException::class) use ($assert): void {
    try { $run(); } catch (Throwable $e) { $assert($e instanceof $class, $label . ' exception type'); return; }
    $assert(false, $label . ' should reject');
};
$token = str_repeat('a', 64);
$payload = medical_doctor_output_template(1, 'BS. Test');
$payload['writer_claim_token'] = $token;
$payload['insufficient_data'] = false;
$payload['content'] = '<h2>Giới thiệu</h2><p>Bác sĩ Test.</p>';
$payload['sources_json'] = [['id' => 's1', 'url' => 'https://example.org/doctor', 'publisher' => 'Test hospital']];
$payload['evidence_json'] = ['identity_status' => 'matched', 'missing_fields' => [], 'conflicts' => []];
$payload['education_json'] = [['institution' => 'Test university', 'degree' => 'BS', 'source_ids' => ['s1']]];
$payload['services_json'] = [['name' => 'Consultation', 'description' => 'Test service', 'source_ids' => ['s1']]];
$payload['locations_json'] = [['facility_id' => null, 'facility_name' => 'Test hospital', 'is_primary' => true, 'source_ids' => ['s1']]];
$normalized = medical_doctor_normalize_payload($payload);
$assert(str_contains($normalized['content'], '<h2>'), 'HTML article retained');
$assert(json_decode($normalized['education_json'], true)[0]['source_ids'] === ['s1'], 'source references retained');
$assert(count(medical_doctor_column_definitions()) === 48, '48 additive columns including public profile/legal data');
$assert(!isset($normalized['name'], $normalized['id'], $normalized['writer_claim_token']), 'identity and lease cannot be editorial fields');
$tainted = $payload + ['verified' => 1, 'rating' => 5, 'reviews_count' => 123, 'language_code' => 'en', 'translation_of_id' => 22];
$clean = medical_doctor_normalize_payload($tainted);
foreach (['verified', 'rating', 'reviews_count', 'language_code', 'translation_of_id'] as $field) $assert(!array_key_exists($field, $clean), 'protect ' . $field);
$html = medical_doctor_sanitize_html('<script>alert(1)</script><p onclick="evil()" style="color:red">Safe <a href="javascript:evil()">link</a><iframe src="https://evil.test"></iframe></p>');
$assert(!str_contains($html, 'script') && !str_contains($html, 'alert') && !str_contains($html, 'onclick') && !str_contains($html, 'iframe') && !str_contains($html, 'javascript'), 'active HTML removed');
$assert(str_contains($html, '<p>Safe'), 'safe HTML retained');
$assert(str_contains(medical_doctor_sanitize_html('<a href="https://example.org">x</a>'), 'nofollow'), 'links hardened');
foreach (['javascript:alert(1)', '[site](https://example.org)', 'https://user:password@example.org', 'data:text/html,x'] as $url) $reject(fn() => medical_doctor_http_url($url), 'unsafe URL');
$assert(medical_doctor_http_url(null) === null, 'unknown URL remains null');
foreach ([['education_json' => '{bad'], ['education_json' => 5], ['bio_json' => [123]], ['sources_json' => [['id' => 's1', 'url' => 'javascript:x']]],
    ['experience_start_year' => '2020'], ['insufficient_data' => 'false'], ['evidence_json' => ['identity_status' => 'insufficient']],
    ['sources_json' => []], ['content' => '<script>x</script>'], ['education_json' => [['source_ids' => ['missing']]]],
    ['education_json' => [true]], ['education_json' => [['degree' => 'Invented']]],
    ['fees_json' => [['amount_min' => -2, 'source_ids' => ['s1']]]], ['fees_json' => [['amount_min' => 500, 'amount_max' => 100, 'source_ids' => ['s1']]]],
    ['gallery_json' => ['javascript:alert(1)']], ['video_urls_json' => ['data:text/html,x']],
    ['social_links_json' => ['facebook' => '[site](https://example.org)']], ['practice_license_json' => ['number' => 'Invented']]] as $override) {
    $reject(fn() => medical_doctor_normalize_payload(array_replace($payload, $override)), 'invalid payload');
}
$insufficient = array_replace($payload, ['insufficient_data' => true, 'content' => null, 'sources_json' => [], 'education_json' => [], 'services_json' => [], 'locations_json' => [],
    'notes_for_editor' => 'Không đủ thông tin nhận diện bác sĩ.', 'evidence_json' => ['identity_status' => 'insufficient']]);
$assert(medical_doctor_normalize_payload($insufficient)['insufficient_data'] === 1, 'insufficient evidence can finish research');
$reject(fn() => medical_doctor_normalize_payload(array_replace($insufficient, ['notes_for_editor' => ''])), 'missing research explanation');
$assert(medical_doctor_needs_content(['status' => 'published', 'language_code' => 'vi']), 'initial record eligible');
foreach ([['status' => 'draft'], ['language_code' => 'en'], ['content' => 'Existing'], ['last_researched_at' => '2026-01-01']] as $override) {
    $assert(!medical_doctor_needs_content(array_replace(['status' => 'published', 'language_code' => 'vi'], $override)), 'queue exclusions');
}
$prompt = medical_doctor_research_prompt('Custom prompt {{name}}', ['id' => 1, 'name' => 'BS. Test', 'ai_writer_claim_json' => json_encode(['claim_token' => 'SECRET']), 'reviewed_by' => 99]);
$assert(!str_contains($prompt, 'SECRET'), 'prompt never leaks claim');
$assert(str_contains($prompt, 'MEDREVIEW_DOCTOR_RESEARCH_CONTRACT_V2') && str_contains($prompt, 'education_json') && str_contains($prompt, '```json'), 'custom prompts get full contract');
$defaultPrompt = medical_doctor_default_prompt();
$assert(str_contains($defaultPrompt, 'MEDREVIEW_DOCTOR_EDITORIAL_PROMPT_V3'), 'expanded doctor editorial prompt version');
foreach (medical_doctor_json_fields() as $field) $assert(str_contains($defaultPrompt, $field), 'doctor prompt documents ' . $field);
$assert(str_contains($defaultPrompt, '{{source_json}}') && str_contains($defaultPrompt, '{{output_template}}'), 'doctor prompt carries source and dynamic output contract');
$renderedDefault = medical_doctor_research_prompt($defaultPrompt, ['id' => 1, 'name' => 'BS. Test', 'city' => 'Test city', 'ai_writer_claim_json' => '{"claim_token":"SECRET"}']);
$assert(!str_contains($renderedDefault, 'SECRET') && !preg_match('/\{\{[a-z_]+\}\}/', $renderedDefault), 'doctor default fully rendered without private lease');
$mapped = medical_directory_doctor_from_row(['name' => 'Test', 'content' => '<p>Real</p>', 'gallery_json' => '[{"url":"https://example.org/a.jpg"}]', 'education_json' => '[{"degree":"BS"}]']);
$assert($mapped['gallery'] === ['https://example.org/a.jpg'], 'rich gallery compatible with frontend');
$assert($mapped['education_json'][0]['degree'] === 'BS' && $mapped['content'] === '<p>Real</p>', 'new fields mapped');

if (in_array('--mysql-temporary', $argv, true)) {
    $pdo = db();
    // Each CREATE TEMPORARY TABLE shadows ONLY this connection. No real doctor/facility is inserted/updated/deleted.
    foreach (['medical_doctors', 'medical_facilities', 'medical_doctor_facilities'] as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
        $create = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create, 1);
        $pdo->exec($create);
    }
    $pdo->exec("INSERT INTO medical_doctors (id,slug,name,status,language_code,rating,reviews_count,verified) VALUES (1,'test-doctor','BS. Test','published','vi',4.3,8,1)");
    $lease = ['claim_token' => $token, 'instance_id' => 'worker-a', 'request_id' => 'attempt-a', 'expires_at' => time() + 180];
    $setLease = static function (array $claim) use ($pdo): void {
        $pdo->prepare('UPDATE medical_doctors SET ai_writer_claim_json=:claim WHERE id=1')->execute([':claim' => json_encode($claim)]);
    };
    $setLease($lease);
    $reject(fn() => medical_doctor_save_research($pdo, array_replace($payload, ['writer_claim_token' => str_repeat('b', 64)])), 'foreign worker rejected', MedicalDoctorConflict::class);
    $assert((int) $pdo->query('SELECT COUNT(*) FROM medical_doctor_facilities')->fetchColumn() === 0, 'foreign worker changes nothing');
    $setLease(array_replace($lease, ['expires_at' => time() - 1]));
    $reject(fn() => medical_doctor_save_research($pdo, $payload), 'expired lease rejected', MedicalDoctorConflict::class);
    $setLease($lease);
    $reject(fn() => medical_doctor_save_research($pdo, array_replace($payload, ['locations_json' => [['facility_id' => 999, 'facility_name' => 'Unknown', 'source_ids' => ['s1']]]])), 'unknown facility rolls back');
    $assert($pdo->query('SELECT last_researched_at FROM medical_doctors WHERE id=1')->fetchColumn() === null, 'failed transaction preserves eligibility');
    $reject(fn() => medical_doctor_save_research($pdo, array_replace($payload, ['locations_json' => [['facility_id' => null, 'facility_name' => ['Bad'], 'source_ids' => ['s1']]]])), 'invalid location name rolls back');
    $reject(fn() => medical_doctor_save_research($pdo, array_replace($payload, ['locations_json' => [['facility_id' => null, 'facility_name' => 'Test', 'source_ids' => ['s1'], 'fees_json' => [['amount_min' => -1]]]]])), 'negative location fees roll back');
    $result = medical_doctor_save_research($pdo, $tainted);
    $saved = $pdo->query('SELECT * FROM medical_doctors WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    $assert($result['writer_released'] && $saved['ai_writer_claim_json'] === null, 'successful save releases lease');
    $assert((int) $saved['verified'] === 0 && $saved['verification_status'] === 'unreviewed', 'AI never grants verification');
    $assert((float) $saved['rating'] === 4.3 && (int) $saved['reviews_count'] === 8, 'AI cannot change ratings');
    $assert($saved['language_code'] === 'vi' && $saved['translation_of_id'] === null, 'language links preserved');
    $assert(!str_contains($saved['full_json'], $token), 'raw JSON excludes lease token');
    $assert(json_decode($saved['full_json'], true)['writer_request_id'] === 'attempt-a', 'lost response recovery receipt');
    $cacheDoctors = medical_search_cache_doctors($pdo);
    $assert(in_array('Consultation', $cacheDoctors[0]['services'], true), 'new doctor services searchable');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM medical_doctor_facilities WHERE doctor_id=1')->fetchColumn() === 1, 'locations mirrored');
    $reject(fn() => medical_doctor_save_research($pdo, $payload), 'second submission rejected', MedicalDoctorConflict::class);
    $assert(!medical_doctor_needs_content($saved), 'completed article excluded from queue');
    $pdo->exec("INSERT INTO medical_doctors (id,slug,name,status,language_code) VALUES (2,'test-insufficient','BS. Test','published','vi')");
    $pdo->prepare('UPDATE medical_doctors SET ai_writer_claim_json=:claim WHERE id=2')->execute([':claim' => json_encode($lease)]);
    medical_doctor_save_research($pdo, array_replace($insufficient, ['id' => 2]));
    $assert(!medical_doctor_needs_content($pdo->query('SELECT * FROM medical_doctors WHERE id=2')->fetch(PDO::FETCH_ASSOC)), 'insufficient research excluded from endless queue');
    echo "MySQL transactions verified using connection-local temporary tables only.\n";
}
echo "Doctor content: {$checks} checks passed.\n";
