<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php'; medical_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['ok'=>false,'message'=>'Method not allowed.'],405);
$type = trim((string)($_GET['type'] ?? 'facility')); $name = trim((string)($_GET['name'] ?? '')); $title = trim((string)($_GET['title'] ?? '')); $category = trim((string)($_GET['category'] ?? ''));
if ($type === 'toplist' && $name === '') $name = $title;
if ($name === '') json_response(['ok'=>false,'message'=>'Thiếu tên đối tượng.'],422);
$allowed=['facility','facility_image_prompt','toplist','doctor','review']; if (!in_array($type,$allowed,true)) json_response(['ok'=>false,'message'=>'Loại nội dung không hợp lệ.'],422);
$pdo=db(); medical_directory_ensure_tables($pdo);
$resolved = medical_directory_resolve_ai_prompt($pdo, $type, $type === 'facility' ? $category : '');
if ((string) $resolved['template'] === '') json_response(['ok'=>false,'message'=>'Chưa có prompt cho loại này.'],404);
$promptTemplate = $type === 'facility'
    ? medical_directory_facility_json_transport_rules((string) $resolved['template'])
    : (string) $resolved['template'];
$prompt = medical_directory_ai_prompt_render_template($promptTemplate, [
    'id' => (string) ($_GET['id'] ?? 0),
    'toplist_id' => (string) ($_GET['id'] ?? 0),
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
]);
json_response(['ok'=>true,'type'=>$type,'label'=>$resolved['label'],'name'=>$name,'category'=>$category,'prompt_source'=>$resolved['source'],'prompt_variant_id'=>$resolved['variant_id'],'prompt_categories'=>$resolved['categories'],'prompt_key_used'=>$resolved['prompt_key_used'],'prompt'=>$prompt]);
