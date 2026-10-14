<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/medical_toplist_readiness.php';
$checks = 0;
$assert = static function(bool $condition,string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException($label); $checks++;
};
$article = ['title' => 'Top 2 cơ sở và bác sĩ', 'slug' => 'test', 'entity_type' => 'mixed', 'language_code' => 'vi'];
$facility = ['member_type' => 'facility', 'member_id' => 1, 'id' => 1, 'status' => 'published', 'language_code' => 'vi', 'content' => '<p>Hồ sơ cơ sở thực tế.</p>'];
$doctor = ['member_type' => 'doctor', 'member_id' => 1, 'id' => 1, 'status' => 'published', 'language_code' => 'vi', 'content' => '<p>Hồ sơ bác sĩ thực tế.</p>'];
$assert(medical_toplist_readiness($article, [$facility,$doctor])['ready'], 'Mixed ready; same numeric IDs across types allowed; Toplist article may be empty');
$assert(!medical_toplist_readiness($article, [])['ready'], 'Empty list never ready');
$assert(!medical_toplist_readiness($article, [$facility])['ready'], 'Incomplete Top 2 never ready');
foreach (['', '<p>&nbsp; &#8203;</p>', '<img src="photo.jpg">'] as $empty) {
    $assert(!medical_toplist_readiness($article, [$facility,array_replace($doctor,['content' => $empty])])['ready'], 'Empty HTML not written');
}
foreach (['id' => null, 'status' => 'draft', 'language_code' => 'en'] as $field => $value) {
    $assert(!medical_toplist_readiness($article, [$facility,array_replace($doctor,[$field => $value])])['ready'], 'Missing/hidden/wrong locale raw member blocks publication');
}
$assert(!medical_toplist_readiness(array_replace($article,['entity_type' => 'facility']), [$facility,$doctor])['ready'], 'Wrong entity type blocks publication');
$assert(!medical_toplist_readiness($article, [$facility,$facility])['ready'], 'Duplicate relation blocked');
$assert(!medical_toplist_readiness(array_replace($article,['title' => '']), [$facility,$doctor])['ready'], 'Title required');
$assert(medical_toplist_readiness(array_replace($article,['title' => 'Danh sách cơ sở']), [$facility,$doctor])['ready'], 'Untyped count title does not impose guessed length');
$assert(!medical_toplist_readiness(array_replace($article,['title' => '3 cơ sở đáng tham khảo']), [$facility,$doctor])['ready'], 'Numeric-leading count protected');
echo "Toplist readiness: {$checks} checks passed. No DB or network used.\n";
