<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php'; medical_api_auth();
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['ok'=>false,'message'=>'Method not allowed.'],405);
$type = trim((string)($_GET['type'] ?? 'facility')); $name = trim((string)($_GET['name'] ?? '')); $title = trim((string)($_GET['title'] ?? '')); $category = trim((string)($_GET['category'] ?? ''));
if ($type === 'toplist_doctor') { $type = 'toplist'; $_GET['entity_type'] = 'doctor'; }
if ($type === 'toplist' && $name === '') $name = $title;
$allowed=['facility','facility_image_prompt','facility_image_fix','toplist','doctor','review','translation']; if (!in_array($type,$allowed,true)) json_response(['ok'=>false,'message'=>'Loại nội dung không hợp lệ.'],422);
if ($name === '' && !in_array($type, ['translation', 'facility_image_fix'], true)) json_response(['ok'=>false,'message'=>'Thiếu tên đối tượng.'],422);
$pdo=db();
$resolved = medical_directory_resolve_ai_prompt($pdo, $type, $type === 'facility' ? $category : '');
if ($type === 'facility_image_fix') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) json_response(['ok' => false, 'message' => 'Fix ảnh cần id cơ sở để lấy toàn bộ ảnh thực tế trong DB.'], 422);
    try { medical_facility_image_fix_require_schema($pdo); }
    catch (Throwable $e) { json_response(['ok' => false, 'message' => 'Chạy php scripts/migrate_facility_image_fix.php trước.'], 503); }
    $lookup = $pdo->prepare('SELECT * FROM medical_facilities WHERE id=:id');
    $lookup->execute([':id' => $id]); $source = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!is_array($source)) json_response(['ok' => false, 'message' => 'Không tìm thấy cơ sở.'], 404);
    if (trim((string) $resolved['template']) === '') json_response(['ok' => false, 'message' => 'Chưa có Prompt AI Fix ảnh. Chạy migration Fix ảnh trước.'], 503);
    try {
        json_response(['ok' => true, 'type' => $type, 'id' => (int) $id, 'name' => $source['name'],
            'label' => $resolved['label'], 'prompt_source' => $resolved['source'], 'prompt_key_used' => $resolved['prompt_key_used'],
            'images_revision' => medical_facility_image_fix_revision($source),
            'output_template' => medical_facility_image_fix_output_template($source),
            'prompt' => medical_facility_image_fix_prompt($resolved['template'], $source, min(12, max(1, (int) ($_GET['target_images'] ?? 6))))]);
    } catch (InvalidArgumentException $e) { json_response(['ok' => false, 'message' => $e->getMessage()], 422); }
}
if ($type === 'toplist') {
    require_once __DIR__ . '/../../toplist_directory.php';
    $toplist = ['id' => (int) ($_GET['id'] ?? 0), 'title' => $name, 'excerpt' => $_GET['excerpt'] ?? '', 'content' => $_GET['content'] ?? '', 'entity_type' => $_GET['entity_type'] ?? 'facility'];
    if ($toplist['id'] > 0) {
        $lookup = $pdo->prepare('SELECT id,title,excerpt,content,entity_type FROM medical_toplists WHERE id=:id');
        $lookup->execute([':id' => $toplist['id']]); $toplist = $lookup->fetch(PDO::FETCH_ASSOC) ?: $toplist;
    }
    try {
        $resolved = toplist_directory_resolve_prompt($pdo, $toplist);
        $prompt = toplist_directory_research_prompt($resolved['template'], $toplist, $toplist['entity_type'] === 'doctor');
    }
    catch (InvalidArgumentException $e) { json_response(['ok' => false, 'message' => $e->getMessage()], 422); }
    json_response(['ok' => true, 'type' => 'toplist', 'entity_type' => $toplist['entity_type'], 'label' => $resolved['label'], 'prompt_key_used' => $resolved['prompt_key_used'], 'prompt_source' => $resolved['source'], 'name' => $toplist['title'], 'output_template' => toplist_directory_output_template($toplist), 'prompt' => $prompt]);
}
if ($type === 'doctor') {
    $source = ['id' => (int) ($_GET['id'] ?? 0), 'name' => $name,
        'city' => (string) ($_GET['city'] ?? ''), 'specialty_text' => (string) ($_GET['specialty'] ?? ''),
        'facility_name' => (string) ($_GET['facility_name'] ?? '')];
    if ($source['id'] > 0) {
        $stmt = $pdo->prepare('SELECT * FROM medical_doctors WHERE id=:id');
        $stmt->execute([':id' => $source['id']]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC) ?: $source;
    }
    json_response(['ok' => true, 'type' => 'doctor', 'label' => $resolved['label'],
        'prompt_source' => $resolved['source'], 'name' => $source['name'],
        'output_template' => medical_doctor_output_template((int) $source['id'], (string) $source['name']),
        'prompt' => medical_doctor_research_prompt($resolved['template'] ?: medical_doctor_default_prompt(), $source)]);
}
if ((string) $resolved['template'] === '') json_response(['ok'=>false,'message'=>'Chưa có prompt cho loại này.'],404);
$promptTemplate = $type === 'facility'
    ? medical_directory_facility_json_transport_rules((string) $resolved['template'])
    : (string) $resolved['template'];
$prompt = medical_directory_ai_prompt_render_template($promptTemplate, [
    'id' => (string) ($_GET['id'] ?? 0),
    'source_id' => (string) ($_GET['source_id'] ?? $_GET['id'] ?? 0),
    'toplist_id' => (string) ($_GET['id'] ?? 0),
    'type' => $type,
    'name' => $name,
    'title' => ($title !== '' ? $title : $name),
    'excerpt' => (string) ($_GET['excerpt'] ?? ''),
    'content' => (string) ($_GET['content'] ?? ''),
    'address' => (string) ($_GET['address'] ?? ''),
    'phone' => (string) ($_GET['phone'] ?? ''),
    'website' => (string) ($_GET['website'] ?? ''),
    'hours' => (string) ($_GET['hours'] ?? ''),
    'hours_text' => (string) ($_GET['hours'] ?? ''),
    'gallery_json' => (string) ($_GET['gallery_json'] ?? ''),
    'reference_images' => (string) ($_GET['reference_images'] ?? $_GET['gallery_json'] ?? ''),
    'gallery_count' => (string) ($_GET['gallery_count'] ?? ''),
    'category' => $category,
    'city' => (string) ($_GET['city'] ?? ''),
    'fields' => (string) ($_GET['fields'] ?? ''),
    'source_json' => (string) ($_GET['source_json'] ?? '{}'),
    'output_template' => (string) ($_GET['output_template'] ?? '{}'),
]);
json_response(['ok'=>true,'type'=>$type,'label'=>$resolved['label'],'name'=>$name,'category'=>$category,'prompt_source'=>$resolved['source'],'prompt_variant_id'=>$resolved['variant_id'],'prompt_categories'=>$resolved['categories'],'prompt_key_used'=>$resolved['prompt_key_used'],'prompt'=>$prompt]);
