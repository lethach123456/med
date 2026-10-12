<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Source-level guards deliberately do not load the authenticated endpoint or
// open a database connection. Sync's field-preservation behavior is covered
// separately by doctor_facility_link_test.php.
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$root = dirname(__DIR__);
$copyFile = (string) file_get_contents($root . '/medical_directory.php');
$apiFile = (string) file_get_contents($root . '/api/medical/translate-content.php');
$copyStart = strpos($copyFile, 'function medical_directory_create_translation_copy(');
$assert($copyStart !== false, 'Translation-copy function exists');
$copy = substr($copyFile, $copyStart);
$apiStart = strpos($apiFile, '$sets = [];');
$assert($apiStart !== false, 'Translation-save block begins with field preparation');
$apiEnd = strpos($apiFile, 'medical_search_cache_invalidate();', $apiStart);
$assert($apiEnd !== false, 'Translation-save block finishes before cache invalidation');
$api = substr($apiFile, $apiStart, $apiEnd - $apiStart);

foreach (['copy' => $copy, 'receive' => $api] as $name => $code) {
    $matched = preg_match('/\$(\w+)\s*=\s*medical_doctor_sync_locations\([^;]+,\s*\x27en\x27\);/', $code, $sync, PREG_OFFSET_CAPTURE);
    $assert($matched === 1, $name . ': capture the resolved English locations');
    $resolvedName = $sync[1][0];
    $syncPosition = $sync[0][1];
    $jsonPosition = strpos($code, 'medical_directory_json_encode($' . $resolvedName . ')', $syncPosition);
    $beginPosition = strpos($code, '$pdo->beginTransaction()');
    $commitPosition = strpos($code, '$pdo->commit()');
    $assert($beginPosition !== false && $commitPosition !== false, $name . ': transaction remains present');
    $assert($beginPosition < $syncPosition && $syncPosition < $commitPosition, $name . ': relation sync stays in the transaction');
    $assert($jsonPosition !== false && $syncPosition < $jsonPosition && $jsonPosition < $commitPosition,
        $name . ': serialize the returned locations before commit');
    $matched = preg_match('/UPDATE medical_doctors SET locations_json = :locations_json WHERE ([^"\n]+)/', $code, $update, PREG_OFFSET_CAPTURE);
    $assert($matched === 1, $name . ': update only locations_json, preserving all other doctor columns');
    $assert($syncPosition < $update[0][1] && $update[0][1] < $jsonPosition, $name . ': persist after resolving the locations');
    $assert(str_contains($update[1][0], "language_code = 'en'"), $name . ': persistence is English-only');
}
$assert(str_contains($copy, "if (\$entity === 'doctor' && !empty(\$source['locations_json']))"), 'Copy persistence is doctor-only');
$assert(str_contains($copy, "':id' => \$newId"), 'Copy persistence targets the newly inserted doctor');
$assert(str_contains($api, "if (\$type === 'doctor')") && str_contains($api, "if (isset(\$normalized['locations_json']))"),
    'Receive persistence is limited to doctor payloads containing locations');
$assert(str_contains($api, 'WHERE id = :target_id AND translation_of_id = :source_id'), 'Receive persistence keeps the source/target guard');
$assert(str_contains($api, "':target_id' => \$targetId, ':source_id' => \$sourceId"), 'Receive persistence binds both linked doctor identities');
echo "Doctor translation locations: {$checks} source checks passed; no DB/network.\n";
