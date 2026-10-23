<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once dirname(__DIR__) . '/medical_facility_content_images.php';
function site_absolute_url(string $path): string { return 'https://medreview.example.test' . $path; }
function medical_api_json(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($label); };
$reject = static function (callable $action) use ($assert): void {
    try { $action(); } catch (InvalidArgumentException|RuntimeException) { $assert(true, 'Rejected safely'); return; }
    throw new RuntimeException('Expected image rejection');
};
if (!extension_loaded('gd')) throw new RuntimeException('GD required for synthetic image tests.');
$facilityId = random_int(1100000000, 1900000000);
$directory = medical_media_library_dir() . '/facilities/' . $facilityId;
if (file_exists($directory)) throw new RuntimeException('Random test folder already exists.');
$files = [];
try {
    $image = imagecreatetruecolor(800, 600);
    imagefill($image, 0, 0, 0xe3eef6);
    ob_start(); imagepng($image); $bytes = (string) ob_get_clean(); imagedestroy($image);
    $calls = 0;
    $download = static function (string $url, int $seconds) use (&$calls, $bytes, $assert): array {
        ++$calls; $assert($seconds >= 1 && $seconds <= 8, 'Download timeout stays bounded');
        return ['ok' => true, 'bytes' => $bytes];
    };
    $source = 'https://images.example.test/clinic.png';
    $map = medical_facility_content_import_images($facilityId, [$source, $source], microtime(true) + 20, $download);
    $assert($calls === 1 && count($map) === 1, 'Repeated cover/gallery URL imports only once');
    $url = $map[$source]; $path = medical_media_local_library_path($url); $files[] = $path;
    $assert(is_string($path) && str_starts_with($path, $directory . '/content-branded-v1/'), 'Only branded output is published in the facility folder');
    $info = getimagesize($path); $decoded = imagecreatefromjpeg($path);
    $assert($decoded instanceof GdImage && $info[2] === IMAGETYPE_JPEG && filesize($path) < 150000 && max($info[0], $info[1]) <= 1280,
        'Stored photo is decodable JPEG below 150 KB and 1280px');
    $bluePixels = 0;
    for ($y = 0; $y < (int) ($info[1] * .25); $y++) for ($x = (int) ($info[0] * .65); $x < $info[0]; $x++) {
        $pixel = imagecolorat($decoded, $x, $y);
        if ((($pixel >> 16) & 255) < 110 && (($pixel >> 8) & 255) < 165 && ($pixel & 255) > 65) ++$bluePixels;
    }
    imagedestroy($decoded);
    $assert($bluePixels > 100, 'MedReview blue/navy wordmark exists in the upper right of an originally flat photo');
    $hash = hash_file('sha256', $path);
    $noWork = static function (): never { throw new RuntimeException('Owned branded output must not be downloaded or stamped again'); };
    $retry = medical_facility_content_import_images($facilityId, [$url], microtime(true) + 20, $noWork, $noWork);
    $assert($retry[$url] === $url && hash_file('sha256', $path) === $hash, 'Resubmitted owned output is reused byte-for-byte without double branding');
    $absolute = site_absolute_url($url);
    $assert(medical_facility_content_import_images($facilityId, [$absolute], microtime(true) + 20, $noWork, $noWork)[$absolute] === $url,
        'Same-origin absolute owned URLs resolve to canonical relative paths');
    $reject(static fn() => medical_facility_content_import_images($facilityId, ['https://foreign.example.test' . $url], microtime(true) + 20, $noWork));
    $reject(static fn() => medical_facility_content_import_images($facilityId, [$source], microtime(true) - 1, $noWork));
    $reject(static fn() => medical_facility_content_import_images($facilityId, array_map(static fn(int $i): string => $source . '?i=' . $i, range(1, 13)), microtime(true) + 20, $noWork));
    foreach (['data:image/png;base64,AAAA', 'file:///etc/passwd', '/uploads/library/../private.jpg', '/uploads/library/missing.jpg'] as $invalid) {
        $reject(static fn() => medical_facility_content_import_images($facilityId, [$invalid], microtime(true) + 20, $noWork));
    }
    $assert(medical_facility_content_import_images($facilityId, [], microtime(true) - 1, $noWork) === [], 'Patches without images need no download, GD work or available time budget');
    $reject(static fn() => medical_facility_content_import_images($facilityId, [$source], microtime(true) + 20, static fn(): array => ['ok' => false, 'error' => 'Synthetic timeout']));
    $reject(static fn() => medical_facility_content_import_images($facilityId, [$source], microtime(true) + 20, static fn(): array => ['ok' => true, 'bytes' => 'not a raster']));

    $endpoint = file_get_contents(dirname(__DIR__) . '/api/medical/facility-content-update.php');
    $extract = static function (string $start, string $end) use ($endpoint): string {
        $from = strpos($endpoint, $start); $to = $from === false ? false : strpos($endpoint, $end, $from);
        if ($from === false || $to === false) throw new RuntimeException('Missing endpoint test boundary');
        return substr($endpoint, $from, $to - $from);
    };
    eval($extract('function medical_api_content_url(', 'function medical_api_content_rating_list('));
    $imageCode = $extract('    // Normalize metadata first;', "    foreach (['services'=>'services_json'");
    $runImageBlock = static function (array $input) use ($imageCode, $facilityId): array {
        $params = []; $set = []; $errors = []; $imageErrors = []; $imagesRequested = 0; $galleryImagesRequested = 0;
        $imageDeadline = microtime(true) + 20; $result = [];
        eval('foreach ([$input] as $item) { $normalizedItem = $item; ' . $imageCode . '$result = ["params" => $params, "item" => $normalizedItem]; }');
        return ['saved' => $result, 'errors' => $errors, 'image_errors' => $imageErrors];
    };
    $result = $runImageBlock(['image_url' => $absolute, 'gallery_json' => [['url' => $absolute, 'caption' => 'Mặt tiền', 'angle' => 'front']], 'images' => [$absolute]]);
    $gallery = json_decode($result['saved']['params'][':gallery_json'], true);
    $assert($result['errors'] === [] && $result['saved']['params'][':image_url'] === $url, 'Endpoint SQL receives only the canonical branded cover');
    $assert($gallery === [['url' => $url, 'angle' => 'front', 'caption' => 'Mặt tiền']], 'Gallery aliases deduplicate while retaining captions and angle');
    $assert($result['saved']['item']['image_url'] === $url && $result['saved']['item']['gallery_json'] === $gallery
        && $result['saved']['item']['images'] === $gallery, 'full_json and processed_items use the same local gallery URLs as dedicated columns');
    $failed = $runImageBlock(['gallery_json' => [['url' => '/uploads/library/missing.jpg']]]);
    $assert($failed['saved'] === [] && count($failed['errors']) === 1 && count($failed['image_errors']) === 1,
        'An image failure skips the item before any SQL image or content publication');
    $assert($runImageBlock(['gallery_json' => []])['saved']['params'][':gallery_json'] === '[]', 'An explicit empty gallery can still clear the gallery');
    $assert(!str_contains($endpoint, 'medical_media_jobs_enqueue_entity_urls') && str_contains($endpoint, "'image_processing' => 'synchronous'")
        && str_contains($endpoint, "'images_imported' => \$imagesImported"), 'Response reports real synchronous imports without enqueuing worker jobs');
} finally {
    foreach (array_unique(array_filter($files, 'is_string')) as $file) if (str_starts_with($file, $directory . '/') && is_file($file)) unlink($file);
    if (is_dir($directory . '/content-branded-v1')) rmdir($directory . '/content-branded-v1');
    if (is_dir($directory)) rmdir($directory);
}
echo 'Facility content images: ' . $checks . " checks passed.\n";
