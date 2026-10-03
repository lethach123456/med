<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php'; medical_api_auth();
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['ok'=>false,'message'=>'Method not allowed.'],405);
$type = trim((string)($_GET['type'] ?? 'facility')); $name = trim((string)($_GET['name'] ?? '')); $title = trim((string)($_GET['title'] ?? '')); $category = trim((string)($_GET['category'] ?? ''));
if ($type === 'toplist_doctor') { $type = 'toplist'; $_GET['entity_type'] = 'doctor'; }
if ($type === 'toplist' && $name === '') $name = $title;
$allowed=['facility','facility_image_prompt','toplist','doctor','review','translation']; if (!in_array($type,$allowed,true)) json_response(['ok'=>false,'message'=>'Loại nội dung không hợp lệ.'],422);
if ($name === '' && $type !== 'translation') json_response(['ok'=>false,'message'=>'Thiếu tên đối tượng.'],422);
$pdo=db();
$resolved = medical_directory_resolve_ai_prompt($pdo, $type, $type === 'facility' ? $category : '');
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
