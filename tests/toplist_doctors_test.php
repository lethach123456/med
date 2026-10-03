<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/toplist_directory.php';
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$reject = static function (callable $run, string $label) use ($assert): void {
    try { $run(); } catch (InvalidArgumentException $e) { $assert(true, $label); return; }
    $assert(false, $label . ' should reject');
};
$assert(toplist_directory_entity_type([]) === 'facility', 'legacy defaults');
$assert(toplist_directory_entity_type(['doctors' => []]) === 'doctor', 'infer doctor key');
$assert(toplist_directory_entity_type(['bac_si' => []]) === 'doctor', 'doctor alias');
$assert(toplist_directory_entity_type(['facilities' => []], 'doctor') === 'facility', 'explicit list overrides selection');
$assert(toplist_directory_entity_type(['doctors' => [], 'facilities' => []]) === 'mixed', 'infer mixed separate arrays');
$assert(toplist_directory_entity_type(['members' => []]) === 'mixed', 'infer combined members');
foreach ([['entity_type' => 'bad'], ['entity_type' => 'doctor', 'members' => []], ['entity_type' => 'facility', 'doctors' => [['id' => 1]]]] as $input) $reject(fn() => toplist_directory_entity_type($input), 'invalid/contradictory type');
$combined = toplist_directory_payload_members(['facilities' => [['facility_id' => 1, 'rank_order' => 2]], 'doctors' => [['doctor_id' => 1, 'rank_order' => 1]]], 'mixed');
$assert(array_column($combined, 'type') === ['facility', 'doctor'], 'separate arrays retain typed identities');
foreach ([['members' => [['type' => 'bad']]], ['members' => [['type' => []]]], ['members' => [], 'doctors' => [['id' => 1]]]] as $input) $reject(fn() => toplist_directory_payload_members($input, 'mixed'), 'invalid mixed structure');
$mixedPrompt = toplist_directory_research_prompt('Old facilities only', ['id' => 9, 'title' => 'Providers', 'entity_type' => 'mixed']);
$assert(str_contains($mixedPrompt, '"members"') && preg_match('/"type"\s*:\s*"facility"/', $mixedPrompt) && preg_match('/"type"\s*:\s*"doctor"/', $mixedPrompt) && !str_contains($mixedPrompt, 'Old facilities only'), 'mixed prompt requires both typed examples');
$prompt = toplist_directory_research_prompt('Old prompt asks for facilities only {{title}}', ['id' => 7, 'title' => 'Top bác sĩ', 'entity_type' => 'doctor']);
$assert(str_contains($prompt, '"doctors"') && !str_contains($prompt, 'Old prompt'), 'doctor prompt replaces incompatible facility template');
$assert((bool) preg_match('/"toplist_id"\s*:\s*7/', $prompt) && str_contains($prompt, '```json') && str_contains($prompt, 'specialty_text'), 'typed output and code block');
$assert(!preg_match('/\{\{\w+\}\}/', $prompt), 'all placeholders rendered');
$facilityPrompt = toplist_directory_research_prompt('Custom facility prompt {{title}}', ['id' => 8, 'title' => 'Clinics']);
$assert(str_contains($facilityPrompt, 'Custom facility prompt Clinics') && str_contains($facilityPrompt, '"facilities"'), 'custom facility template retained');

if (in_array('--mysql-temporary', $argv, true)) {
    $pdo = db();
    // Connection-scoped shadow tables only: no real profile/article/relation is written by these tests.
    foreach (['medical_doctors', 'medical_facilities', 'medical_toplists', 'medical_toplist_facilities', 'medical_toplist_doctors'] as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
        $create = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create, 1);
        $create = preg_replace('/^\s*CONSTRAINT[^\n]*\n/m', '', $create);
        $create = preg_replace('/,\n\)/', "\n)", $create);
        $pdo->exec($create);
    }
    $pdo->exec("INSERT INTO medical_facilities (id,slug,name,city,address_text,status,language_code,translation_of_id) VALUES (1,'clinic','Test Clinic','Huế','10 Test Road','published','vi',NULL),(2,'clinic-en','Test Clinic','Hue','10 Test Road','published','en',1)");
    $pdo->exec("INSERT INTO medical_doctors (id,slug,name,specialty_text,city,facility_name,status,language_code,translation_of_id,rating,reviews_count,verified) VALUES
        (1,'doctor-a','Doctor A','Mắt','Hà Nội','Clinic A','published','vi',NULL,4.5,5,1),
        (2,'doctor-b','Doctor B','Răng','Huế','Clinic B','published','vi',NULL,0,0,0),
        (3,'doctor-a-en','Doctor A','Eyes','Hanoi','Clinic A','published','en',1,0,0,0),
        (4,'doctor-b-en','Doctor B','Teeth','Hue','Clinic B','draft','en',2,0,0,0)");
    $pdo->exec("INSERT INTO medical_toplists (id,title,slug,status,entity_type) VALUES (1,'Facility list','facility-list','published','facility'),(2,'Doctor list','doctor-list','published','doctor')");
    toplist_directory_sync_facilities($pdo, 1, [1]);
    toplist_directory_sync_members($pdo, 2, 'doctor', [2, 1, 2]);
    $rows = toplist_directory_linked_rows($pdo, ['id' => 2, 'entity_type' => 'doctor']);
    $assert(array_map('intval', array_column($rows, 'id')) === [2, 1], 'doctor ordered and deduplicated');
    $assert(array_map('intval', array_column($rows, 'rank_order')) === [1, 2], 'ranks contiguous');
    foreach ([[999], [3], [0], ['bad']] as $ids) $reject(fn() => toplist_directory_sync_members($pdo, 2, 'doctor', $ids), 'unknown/wrong-language ID rejected');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM medical_toplist_doctors WHERE toplist_id=2')->fetchColumn() === 2, 'invalid replacement keeps prior ranking');
    $reject(fn() => toplist_directory_sync_members($pdo, 999, 'doctor', [1]), 'missing toplist rejected');
    $article = toplist_directory_import_article($pdo, ['title' => 'Imported doctor list', 'entity_type' => 'doctor', 'status' => 'published', 'doctors' => [
        ['doctor_id' => 1, 'rank_order' => 2, 'rating' => 1, 'verified' => 0],
        ['doctor_id' => 2, 'rank_order' => 1],
        ['name' => 'New Doctor', 'specialty_text' => 'Tim mạch', 'city' => 'Đà Nẵng', 'rank_order' => 3, 'image_url' => '/uploads/test.jpg', 'verified' => 1, 'rating' => 5],
    ]]);
    $assert(count($article['ids']) === 3 && count($article['created_ids']) === 1 && $article['ids'][0] === 2, 'import links existing and creates only missing doctor');
    $newDoctorId = $article['created_ids'][0];
    $newDoctor = $pdo->query('SELECT * FROM medical_doctors WHERE id=' . $newDoctorId)->fetch(PDO::FETCH_ASSOC);
    $assert((int) $newDoctor['verified'] === 0 && (float) $newDoctor['rating'] === 0.0, 'AI cannot verify or invent ratings');
    $assert($newDoctor['image_url'] === '/uploads/test.jpg', 'local media path supported');
    $savedDoctor = $pdo->query('SELECT * FROM medical_doctors WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    $assert((float) $savedDoctor['rating'] === 4.5 && (int) $savedDoctor['verified'] === 1, 'ID association does not overwrite profile');
    $existing = toplist_directory_resolve_member($pdo, 'doctor', ['name' => 'New Doctor', 'specialty_text' => 'Tim mạch', 'city' => 'Đà Nẵng']);
    $assert($existing['id'] === $newDoctorId && !$existing['created'], 'precise identity reused');
    $reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', ['name' => 'No Identity Clues']), 'requires doctor specialty and location');
    $reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', ['doctor_id' => 1, 'name' => 'Someone Else']), 'ID/name mismatch rejected');
    $reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', ['facility_id' => 1, 'name' => 'Clinic mistaken for doctor']), 'wrong typed ID rejected');
    $reject(fn() => toplist_directory_resolve_member($pdo, 'doctor', ['name' => 'Fake English'], 'en'), 'no fabricated translated doctor');
    $beforeArticles = (int) $pdo->query('SELECT COUNT(*) FROM medical_toplists')->fetchColumn();
    $beforeDoctors = (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn();
    $reject(fn() => toplist_directory_import_article($pdo, ['title' => 'Bad atomic import', 'doctors' => [
        ['name' => 'Rollback Doctor', 'specialty_text' => 'Mắt', 'city' => 'Hà Nội'], ['doctor_id' => 999]
    ]]), 'invalid member rolls back entire import');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM medical_toplists')->fetchColumn() === $beforeArticles
        && (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === $beforeDoctors, 'no partial article or profile');
    $legacy = toplist_directory_import_article($pdo, ['title' => 'Legacy Facility', 'facilities' => [['name' => 'Test Clinic', 'city' => 'Huế', 'address' => '10 Test Road']]]);
    $assert($legacy['entity_type'] === 'facility' && $legacy['ids'] === [1] && $legacy['created_ids'] === [], 'legacy facility JSON remains functional');
    $draft = toplist_directory_import_article($pdo, ['title' => 'Future Doctors'], 'doctor', true);
    $assert($pdo->query('SELECT entity_type FROM medical_toplists WHERE id=' . $draft['id'])->fetchColumn() === 'doctor', 'title-only doctor draft keeps type');
    toplist_directory_sync_members($pdo, 1, 'doctor', [1]);
    $assert((int) $pdo->query('SELECT COUNT(*) FROM medical_toplist_facilities WHERE toplist_id=1')->fetchColumn() === 0, 'type switch clears old-type relations');
    $englishId = medical_directory_create_translation_copy($pdo, 'toplist', 2);
    $translated = $pdo->query('SELECT * FROM medical_toplists WHERE id=' . $englishId)->fetch(PDO::FETCH_ASSOC);
    $assert($translated['entity_type'] === 'doctor' && (int) $translated['translation_of_id'] === 2, 'translation retains entity and parent');
    $enRows = toplist_directory_linked_rows($pdo, $translated);
    $assert(array_map('intval', array_column($enRows, 'id')) === [3], 'only published EN doctors linked');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM medical_toplist_facilities WHERE toplist_id=' . $englishId)->fetchColumn() === 0, 'doctor translation has no facility ranks');
    $mixed = toplist_directory_import_article($pdo, ['title' => 'Mixed providers', 'entity_type' => 'mixed', 'status' => 'published', 'members' => [
        ['type' => 'facility', 'facility_id' => 1, 'rank_order' => 2],
        ['type' => 'doctor', 'doctor_id' => 1, 'rank_order' => 1],
        ['type' => 'facility', 'facility_id' => 1, 'rank_order' => 3],
        ['type' => 'doctor', 'doctor_id' => 2, 'rank_order' => 4],
    ]]);
    $assert($mixed['members'] === [['type' => 'doctor', 'id' => 1], ['type' => 'facility', 'id' => 1], ['type' => 'doctor', 'id' => 2]], 'same numeric ID across types preserved, duplicate same-type removed');
    $mixedRows = toplist_directory_linked_rows($pdo, ['id' => $mixed['id'], 'entity_type' => 'mixed']);
    $assert(array_column($mixedRows, 'member_type') === ['doctor', 'facility', 'doctor'] && array_map('intval', array_column($mixedRows, 'rank_order')) === [1, 2, 3], 'mixed ranking globally ordered');
    $assert(count(toplist_directory_linked_rows($pdo, ['id' => $mixed['id'], 'entity_type' => 'mixed'], 2)) === 2, 'combined limit applies after merge');
    foreach ([['type' => 'doctor', 'id' => 999], ['type' => 'facility', 'id' => 2], ['type' => 'bad', 'id' => 1]] as $badMember) {
        $reject(fn() => toplist_directory_sync_ranked_members($pdo, $mixed['id'], 'mixed', [['type' => 'facility', 'id' => 1], $badMember]), 'mixed invalid replacement rejected atomically');
    }
    $assert(count(toplist_directory_linked_rows($pdo, ['id' => $mixed['id'], 'entity_type' => 'mixed'])) === 3, 'invalid mixed replacement preserves both tables');
    $reject(fn() => toplist_directory_sync_ranked_members($pdo, $mixed['id'], 'doctor', [['type' => 'facility', 'id' => 1]]), 'pure mode cannot silently discard opposite type');
    $mixedEnId = medical_directory_create_translation_copy($pdo, 'toplist', $mixed['id']);
    $mixedEn = $pdo->query('SELECT * FROM medical_toplists WHERE id=' . $mixedEnId)->fetch(PDO::FETCH_ASSOC);
    $mixedEnRows = toplist_directory_linked_rows($pdo, $mixedEn);
    $assert($mixedEn['entity_type'] === 'mixed' && array_column($mixedEnRows, 'member_type') === ['doctor', 'facility'], 'mixed translation copies both published counterpart types');
    $assert(array_map('intval', array_column($mixedEnRows, 'id')) === [3, 2] && array_map('intval', array_column($mixedEnRows, 'rank_order')) === [1, 2], 'translated mixed IDs preserve global order and omit draft');
    $separate = toplist_directory_import_article($pdo, ['title' => 'Separate arrays mixed', 'facilities' => [['facility_id' => 1, 'rank_order' => 2]], 'doctors' => [['doctor_id' => 1, 'rank_order' => 1]]]);
    $assert($separate['entity_type'] === 'mixed' && array_column($separate['members'], 'type') === ['doctor', 'facility'], 'both-array import ranks cross-type');
    $newMixed = toplist_directory_import_article($pdo, ['title' => 'New mixed profiles', 'members' => [
        ['type' => 'facility', 'name' => 'New Mixed Clinic', 'city' => 'Huế', 'address' => '20 Test Road'],
        ['type' => 'doctor', 'name' => 'New Mixed Doctor', 'city' => 'Huế', 'specialty_text' => 'Mắt'],
    ]]);
    $assert(count($newMixed['created_facility_ids']) === 1 && count($newMixed['created_doctor_ids']) === 1 && count($newMixed['created_ids']) === 2, 'new mixed creation reports each type separately');
    $beforeCounts = array_map(static fn(string $table): int => (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(), ['medical_toplists', 'medical_facilities', 'medical_doctors']);
    $reject(fn() => toplist_directory_import_article($pdo, ['title' => 'Rollback mixed', 'members' => [
        ['type' => 'facility', 'name' => 'Rollback Mixed Clinic', 'city' => 'Huế'],
        ['type' => 'doctor', 'name' => 'Rollback Mixed Doctor', 'city' => 'Huế', 'specialty_text' => 'Mắt'],
        ['type' => 'doctor', 'doctor_id' => 999],
    ]]), 'mixed failed import rolls back article and both profile types');
    $afterCounts = array_map(static fn(string $table): int => (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(), ['medical_toplists', 'medical_facilities', 'medical_doctors']);
    $assert($beforeCounts === $afterCounts, 'no partial mixed data');
    $cache = medical_search_cache_toplists($pdo);
    $indexed = array_values(array_filter($cache, static fn(array $row): bool => $row['id'] === $article['id']))[0] ?? [];
    $assert(($indexed['doctor_count'] ?? 0) === 3 && ($indexed['facility_count'] ?? -1) === 0, 'search counts use correct entity');
    $assert(str_contains($indexed['search_text'] ?? '', medical_search_cache_normalize('New Doctor')), 'linked doctor identity searchable');
    $public = medical_search_cache_public_item('toplists', $indexed);
    $assert($public['entity_type'] === 'doctor' && $public['doctor_count'] === 3, 'public search preserves typed counts');
    $mixedIndexed = array_values(array_filter($cache, static fn(array $row): bool => $row['id'] === $mixed['id']))[0] ?? [];
    $assert(($mixedIndexed['doctor_count'] ?? 0) === 2 && ($mixedIndexed['facility_count'] ?? 0) === 1, 'mixed search counts avoid join multiplication');
    $assert(str_contains($mixedIndexed['search_text'] ?? '', medical_search_cache_normalize('Test Clinic')) && str_contains($mixedIndexed['search_text'] ?? '', medical_search_cache_normalize('Doctor A')), 'mixed search includes both entity identities');
    $renderArgument = array_values(array_filter($argv, static fn(string $value): bool => str_starts_with($value, '--render=')))[0] ?? '';
    if ($renderArgument !== '') {
        $toplist = $pdo->query('SELECT * FROM medical_toplists WHERE id=' . $article['id'])->fetch(PDO::FETCH_ASSOC);
        $title = 'Bác sĩ chuyên khoa — bản xem thử'; $description = 'Dữ liệu mẫu trong bảng tạm, không xuất bản lên website.';
        $toplistLanguage = 'vi'; $toplistLanguageLinks = ['has_counterpart' => false];
        $toplistEntityType = 'doctor';
        $canonicalUrl = 'https://medreview.vn/toplist/test-only'; $heroImage = '';
        $linkedRows = toplist_directory_linked_rows($pdo, $toplist);
        $toplistSchema = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $title];
        ob_start(); require dirname(__DIR__) . '/Tem/toplist-doctor-detail.php'; $html = ob_get_clean();
        $assert(str_contains($html, '/bac-si/doctor-a') && str_contains($html, 'Nơi công tác'), 'public doctor profiles and labels rendered');
        $toplist = $pdo->query('SELECT * FROM medical_toplists WHERE id=' . $mixed['id'])->fetch(PDO::FETCH_ASSOC);
        $toplistEntityType = 'mixed'; $linkedRows = toplist_directory_linked_rows($pdo, $toplist);
        ob_start(); require dirname(__DIR__) . '/Tem/toplist-doctor-detail.php'; $html = ob_get_clean();
        $assert(str_contains($html, '/bac-si/doctor-a') && str_contains($html, '/co-so-y-te/clinic') && str_contains($html, 'Cơ sở y tế &amp; bác sĩ'), 'mixed public cards link to proper profile type');
        $assert(str_contains($html, '"MedicalClinic"') && str_contains($html, '"Physician"'), 'mixed schema has correct typed items');
        $html = preg_replace('~((?:src|href)=[\x22\x27])(/(?:assets|uploads)/)~', '$1http://127.0.0.1:8768$2', $html);
        file_put_contents(substr($renderArgument, strlen('--render=')), $html);
    }
}
echo "Toplist doctors: {$checks} checks passed. Database tests use connection-scoped temporary tables only.\n";
