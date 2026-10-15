<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/medical_handbook_ai.php';
$checks = 0;
$assert = static function(bool $ok, string $label) use (&$checks): void {
    $checks++; if (!$ok) throw new RuntimeException($label);
};
$reject = static function(callable $fn) use ($assert): void {
    try { $fn(); $assert(false, 'Invalid payload accepted'); }
    catch (InvalidArgumentException) { $assert(true, 'Invalid payload rejected'); }
};
$adminDefaults=handbook_ai_admin_prompts([]);
$assert(count($adminDefaults)===1&&$adminDefaults[0]['prompt_key']==='handbook'&&$adminDefaults[0]['template']===handbook_ai_default_prompt(),'Admin exposes built-in handbook prompt before DB seed');
$savedPrompts=[['prompt_key'=>'doctor','template'=>'Doctor custom'],['prompt_key'=>'handbook','template'=>'Handbook custom']];
$assert(handbook_ai_admin_prompts($savedPrompts)===$savedPrompts,'Admin fallback never replaces or duplicates a saved custom prompt');
$assert(handbook_ai_admin_prompts($adminDefaults)===$adminDefaults,'Applying admin fallback twice keeps a single handbook tab');
$inlineRules=handbook_ai_inline_output_rules();
$realImageRule='Ảnh Cẩm nang phải là file PNG/JPEG/WebP thật đã mã hoá BASE64 đầy đủ, không URL tạm/placeholder.';
$assert(str_starts_with(handbook_ai_default_prompt(),$inlineRules)&&str_ends_with(handbook_ai_default_prompt(),$inlineRules),'Default editor prompt repeats inline output contract at start and end');
$assert(substr_count(handbook_ai_default_prompt(),$realImageRule)>=2,'Default editor prompt repeats the exact real-file complete BASE64 requirement');
$assert(str_contains($inlineRules,'Yêu cầu đối với payload cuối gửi API: '.$realImageRule),'Real-image requirement explicitly applies to the final API payload');
$assert(str_contains(handbook_ai_transport_rules(),$realImageRule),'Current transport contract includes the exact real-image requirement');
foreach (['```json','file .json','Tải JSON hoàn chỉnh','sandbox:/mnt/data','Canvas','artifact','File ẢNH','handbook-image:N','không cắt nội dung hay BASE64'] as $text) {
    $assert(str_contains($inlineRules,$text),'Inline output contract covers: '.$text);
}
$row = ['id'=>1,'title'=>'Cách chăm sóc mắt','slug'=>'cham-soc-mat','language'=>'vi','category_slug'=>'blog',
    'category_id'=>1,'excerpt'=>'Giải thích cho người làm việc văn phòng','status'=>'draft','content'=>'',
    'updated_at'=>'2026-10-15 00:00:00'];
$assert(handbook_ai_needs_content($row), 'Draft topic is eligible');
foreach ([['status'=>'published'],['content'=>'<p>Written</p>'],['title'=>' '],['slug'=>''],['category_slug'=>'services'],['language'=>'fr']] as $change) {
    $assert(!handbook_ai_needs_content(array_replace($row,$change)), 'Out-of-scope row excluded');
}
$assert(handbook_ai_needs_content(array_replace($row,['language'=>null,'category_slug'=>'blog-en'])), 'Legacy authoritative language normalized');
$revision = handbook_ai_revision($row);
$assert($revision === handbook_ai_revision($row+['ai_writer_claim_json'=>'private','ai_content_json'=>'metadata']), 'Operational metadata does not change revision');
foreach (['title','slug','language','category_id','excerpt','content','status','updated_at'] as $key) {
    $assert($revision !== handbook_ai_revision(array_replace($row,[$key=>'Changed'])), 'Editorial field changes revision');
}
$payload = ['id'=>1,'title'=>'Cách chăm sóc mắt','language'=>'vi','excerpt'=>'Nội dung dễ hiểu',
    'content_html'=>'<h2>Tìm hiểu</h2><p>'.str_repeat('Thông tin tham khảo, hãy hỏi người có chuyên môn khi cần. ',10).'</p>',
    'seo_title'=>'Cẩm nang chăm sóc mắt','seo_description'=>'Thông tin tham khảo',
    'sources_json'=>[['url'=>'https://www.who.int/health-topics','title'=>'WHO']]];
$normalized = handbook_ai_validate_payload($payload,$row);
$hash = handbook_ai_payload_hash($normalized);
$assert(strlen($hash)===64 && $hash===handbook_ai_payload_hash($normalized), 'Stable bounded payload hash');
$assert($hash!==handbook_ai_payload_hash(array_replace($normalized,['title'=>'Changed'])), 'Retry hash detects changes');
$assert(!array_key_exists('claim_token', handbook_ai_validate_payload($payload+['claim_token'=>'private'],$row)), 'Transport secrets stripped from normalized content');
foreach ([['id'=>true],['id'=>2],['language'=>'en'],['title'=>[]],['title'=>str_repeat('x',161)],['title'=>'<b>Title</b>'],
    ['excerpt'=>'data:image/png;base64,secret'],['seo_title'=>str_repeat('x',161)],['seo_description'=>str_repeat('x',301)],
    ['sources_json'=>[]],['sources_json'=>[null]],['sources_json'=>'invalid'],['sources_json'=>['url'=>'https://www.who.int']],
    ['content_html'=>[]],['content_html'=>'<p>Too short</p>'],['content_html'=>"bad\0"],['status'=>'published'],['category_id'=>[]],['slug'=>'other']] as $change) {
    $reject(static fn()=>handbook_ai_validate_payload(array_replace($payload,$change),$row));
}
foreach (['javascript:alert(1)','data:image/png;base64,abc','https://user:secret@example.org/a','https://localhost/a',
    'https://127.0.0.1/a','https://192.168.1.2/a','http://0.0.0.0/a','https://example.local/a','https://example.org/a?api_key=private',
    "https://example.org/a\nb",'[WHO](https://www.who.int)'] as $url) $reject(static fn()=>handbook_ai_source_url($url));
$sources = $payload; $sources['sources_json'] = json_encode(array_merge($payload['sources_json'],$payload['sources_json']));
$assert(count(handbook_ai_validate_payload($sources,$row)['sources_json'])===1, 'Legacy stringified source list normalized and deduplicated');

$pdo = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$prompt = handbook_ai_prompt($pdo,$row+['ai_writer_claim_json'=>'secret-do-not-leak']);
$assert($prompt['prompt_source']==='built_in', 'Default prompt works without GET seed');
$assert(substr_count($prompt['prompt'],$realImageRule)>=2,'Rendered default prompt repeats the exact real-image requirement');
$assert(!str_contains($prompt['prompt'],'secret-do-not-leak') && !str_contains($prompt['prompt'],'{{'), 'Rendered prompt exposes no operational secrets/unresolved placeholders');
foreach (['Cách chăm sóc mắt','BASE64','data:image/png;base64','công cụ tạo ảnh','sources_json','nháp','[MEDREVIEW_HANDBOOK_CONTRACT_V1]'] as $text) {
    $assert(str_contains($prompt['prompt'],$text), 'Prompt contains current workflow: '.$text);
}
$assert(!array_key_exists('claim_token',$prompt['output_template']) && $prompt['output_template']['id']===1, 'Output template only asks for content fields');
$assert(str_starts_with($prompt['prompt'],$inlineRules)&&str_ends_with($prompt['prompt'],$inlineRules),'Rendered prompt repeats current inline contract at start and end');
$assert(substr_count($prompt['prompt'],'[MEDREVIEW_HANDBOOK_INLINE_JSON_V1]')===2,'Default rendering does not accumulate repeated managed inline blocks');
$assert(substr_count($prompt['prompt'],'[MEDREVIEW_HANDBOOK_CONTRACT_V1]')===1,'Default rendering has one current transport contract');
$assert(str_contains($prompt['prompt'],"```json\n".json_encode($prompt['output_template'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n```"),'Authoritative schema demonstrates actual fenced JSON output');
$pdo->exec('CREATE TABLE medical_ai_prompts(prompt_key TEXT,label TEXT,template TEXT)');
$pdo->prepare('INSERT INTO medical_ai_prompts VALUES (?,?,?)')->execute(['handbook','Custom handbook','Custom instructions without variables']);
$custom = handbook_ai_prompt($pdo,$row);
$assert($custom['prompt_source']==='saved' && $custom['label']==='Custom handbook', 'Admin custom prompt preserved');
$assert(substr_count($custom['prompt'],$realImageRule)>=2,'Saved custom prompt receives the exact real-image requirement repeatedly');
$assert(str_contains($custom['prompt'],'Custom instructions') && str_contains($custom['prompt'],'Cách chăm sóc mắt') && str_contains($custom['prompt'],'[MEDREVIEW_HANDBOOK_CONTRACT_V1]'), 'Current contract and authoritative topic appended to legacy custom prompt');
$assert(str_starts_with($custom['prompt'],$inlineRules)&&str_ends_with($custom['prompt'],$inlineRules),'Saved custom prompts also receive repeated inline output rule');
$oldTemplate="Keep my custom editorial instructions.\n[MEDREVIEW_HANDBOOK_CONTRACT_V1]OLD_TRANSPORT_ONLY_DOWNLOAD[/MEDREVIEW_HANDBOOK_CONTRACT_V1]\n".$inlineRules;
$pdo->prepare('UPDATE medical_ai_prompts SET template=? WHERE prompt_key=?')->execute([$oldTemplate,'handbook']);
$refreshed=handbook_ai_prompt($pdo,$row);
$assert(str_contains($refreshed['prompt'],'Keep my custom editorial instructions.')&&!str_contains($refreshed['prompt'],'OLD_TRANSPORT_ONLY_DOWNLOAD'),'Managed transport rules refreshed without replacing custom editorial instructions');
$assert(substr_count($refreshed['prompt'],'[MEDREVIEW_HANDBOOK_INLINE_JSON_V1]')===2&&substr_count($refreshed['prompt'],'[MEDREVIEW_HANDBOOK_CONTRACT_V1]')===1,'Saved managed blocks do not multiply when rules are refreshed');
$assert($pdo->query("SELECT template FROM medical_ai_prompts WHERE prompt_key='handbook'")->fetchColumn()===$oldTemplate,'Rendering a prompt never writes the saved template');
$topicWithMarker='Topic [MEDREVIEW_HANDBOOK_CONTRACT_V1]literal topic text[/MEDREVIEW_HANDBOOK_CONTRACT_V1]';
$assert(str_contains(handbook_ai_prompt($pdo,array_replace($row,['title'=>$topicWithMarker]))['prompt'],$topicWithMarker),'Managed template refresh does not strip interpolated topic data');
$englishPrompt=handbook_ai_prompt($pdo,array_replace($row,['language'=>'en','category_slug'=>'blog-en']));
$assert($englishPrompt['output_template']['language']==='en'&&str_contains($englishPrompt['prompt'],'"language":"en"'),'Inline contract preserves authoritative article language');
try { handbook_ai_migrate($pdo); $assert(false,'Runtime migration must fail'); }
catch (LogicException) { $assert(true,'No migration outside explicit maintenance'); }
$readySource=(string)file_get_contents(dirname(__DIR__).'/medical_handbook_ai.php');
$ready=substr($readySource,strpos($readySource,'function handbook_ai_require_schema'),strpos($readySource,'function handbook_ai_needs_content')-strpos($readySource,'function handbook_ai_require_schema'));
$assert(!preg_match('/ALTER|CREATE|INSERT|UPDATE/',$ready),'Readiness never changes schema or content');
$assert(str_contains((string)file_get_contents(dirname(__DIR__).'/api/medical/prompt.php'),"if (\$type === 'handbook')"),'Shared prompt endpoint exposes handbook type');
echo "Handbook AI: {$checks} checks passed; SQLite only, no runtime DB or network writes.\n";
