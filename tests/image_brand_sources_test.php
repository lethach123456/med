<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_image_brand_sources.php';
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    $checks++;
};
$url = 'https://photos.example.org/doctor.jpg?x=1&y=2';
$identity = medical_image_brand_source_identity($url);
$assert($identity !== null && $identity['key'] === hash('sha256', $url) && $identity['local_path'] === null, 'Remote identity is deterministic without DNS.');
$local = '/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg';
$a = medical_image_brand_source_identity($local);
$assert($a !== null && medical_image_brand_source_identity('https://medreview.vn' . $local . '?v=1#fragment')['key'] === $a['key'], 'Own absolute and root-relative URLs deduplicate.');
$foreign = medical_image_brand_source_identity('https://photos.example.org' . $local);
$assert($foreign !== null && $foreign['local_path'] === null && $foreign['key'] !== $a['key'], 'Foreign matching path never becomes a local file.');
$assert(medical_image_brand_source_identity('/uploads/library/missing-photo.png')['local_path'] === null, 'Missing local images remain reportable.');
foreach (['data:image/png;base64,abc', 'blob:https://medreview.vn/abc', 'sandbox:/tmp/a.jpg', '//photos.example.org/x.jpg', 'http://127.0.0.1/a.jpg', 'http://[::1]/a.jpg', 'http://10.0.0.1/a.jpg', 'http://100.64.0.1/a.jpg', 'https://foo.local/a.jpg', 'https://foo.home/a.jpg', 'http://2130706433/a.jpg', 'https://user:pass@photos.example.org/a.jpg', 'https://photos.example.org/a.jpg?token=private', 'https://photos.example.org:80/a.jpg', 'https://photos.example.org:8443/a.jpg', '/uploads/library/../secret.jpg', '/uploads/library/%2e%2e/secret.jpg', '/uploads/library/x.svg', '/assets/photo.jpg', '/uploads/library/medreview-branded/a.jpg', '/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg'] as $bad) {
    $assert(medical_image_brand_source_identity($bad) === null, 'Reject unsafe or excluded source: ' . $bad);
}
$assert(medical_image_brand_library_prefixes() === ['co-so-y-te/facility-', 'bac-si/doctor-'], 'Worker folder scopes match import convention.');
$row = [
    'image_url' => $url, 'ai_image_url' => 'https://photos.example.org/ai.jpg',
    'gallery_json' => json_encode([$url, ['url' => $url, 'src' => $url, 'caption' => $url, 'alt' => 'Portrait', 'source' => $url, 'evidence' => ['image' => $url]]]),
    'services_json' => json_encode([['image' => $url, 'url' => 'https://photos.example.org/not-a-photo.jpg', 'source' => ['image' => $url]]]),
    'doctors_json' => json_encode([['photo' => $url, 'professional_profile_url' => $url]]),
    'reviews_list_json' => json_encode([['photos' => [$url], 'images' => [['url' => $url]], 'evidence_url' => $url]]),
    'full_json' => json_encode(['image_url' => $url]), 'image_fix_json' => json_encode(['image_url' => $url]),
    'content' => '<p data-url="' . $url . '"><a href="' . $url . '">Link</a><img alt="A > B" data-src="' . $url . '" src="https://photos.example.org/doctor.jpg?x=1&amp;y=2"></p><!-- <img src="' . $url . '"> --><script>let x = \'<img src="' . $url . '">\';</script>',
];
$sources = medical_image_brand_sources_from_row($row, 'facility');
$assert(count($sources) === 2, 'Only explicit photo references are inventoried and deduplicated.');
$output = '/uploads/library/medreview-branded/fixture.jpg';
$patch = medical_image_brand_source_patch($row, 'facility', static function (string $candidate) use ($identity, $output): string {
    $source = medical_image_brand_source_identity($candidate);
    return $source !== null && $source['key'] === $identity['key'] ? $output : $candidate;
});
$gallery = json_decode($patch['gallery_json'], true);
$assert($gallery[0] === $output && $gallery[1]['url'] === $output && $gallery[1]['src'] === $output, 'String and rich gallery locations are replaced.');
$assert($gallery[1]['caption'] === $url && $gallery[1]['source'] === $url && $gallery[1]['evidence']['image'] === $url && $gallery[1]['alt'] === 'Portrait', 'Gallery prose and provenance survive unchanged.');
$assert(!isset($patch['full_json'], $patch['image_fix_json'], $patch['ai_image_url']), 'Historical JSON and unrelated sources remain untouched.');
$assert(str_contains($patch['content'], 'src="' . $output . '"') && str_contains($patch['content'], 'data-src="' . $url . '"') && str_contains($patch['content'], 'href="' . $url . '"'), 'Only img src is rewritten in HTML.');
$assert(str_contains($patch['content'], '<!-- <img src="' . $url . '"> -->') && str_contains($patch['content'], '<script>let x ='), 'Comments and script blocks survive unchanged.');
$assert(medical_image_brand_source_patch(['gallery_json' => '[broken'], 'doctor', static fn($v) => $output) === [], 'Malformed JSON is never erased.');
$assert(medical_image_brand_source_html("<IMG SRC='$url' alt='x'><img src=$url>", static fn($v) => $v === $url ? $output : $v) === "<IMG SRC='$output' alt='x'><img src=\"$output\">", 'Single-quoted and unquoted src are supported.');
$altHtml = "<img alt='example src=\"$url\"' src='$url' data-caption='$url'>";
$assert(medical_image_brand_source_html($altHtml, static fn($v) => $v === $url ? $output : $v) === "<img alt='example src=\"$url\"' src='$output' data-caption='$url'>", 'Attribute-looking text inside quoted alt values is preserved.');
$assert(medical_image_brand_source_identity('https://photos.example.org/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg') !== null, 'Foreign path matching own logo is still a remote URL.');

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE medical_facilities (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT, ai_image_url TEXT, gallery_json TEXT, services_json TEXT, doctors_json TEXT, reviews_list_json TEXT, content TEXT, full_json TEXT, image_fix_json TEXT)');
$pdo->exec('CREATE TABLE medical_doctors (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT, gallery_json TEXT)');
$pdo->exec('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
$fields = array_keys($row);
$insert = $pdo->prepare('INSERT INTO medical_facilities (id, name, ' . implode(', ', $fields) . ') VALUES (1, :name, :' . implode(', :', $fields) . ')');
$insert->execute(['name' => 'Clinic'] + $row);
$pdo->prepare('INSERT INTO medical_facilities (id,name,image_url,gallery_json) VALUES (?,?,?,?)')->execute([3, 'Second', $url, '[]']);
$pdo->prepare('INSERT INTO medical_doctors (id,name,image_url,gallery_json) VALUES (?,?,?,?)')->execute([1, 'Doctor', $url, '[]']);
$page = medical_image_brand_source_page($pdo, 'facility', 0, 1);
$assert($page['scanned'] === 1 && $page['next_id'] === 1 && $page['has_more'] && count($page['rows'][0]['sources']) === 2, 'Keyset inventory includes installed optional columns.');
$page2 = medical_image_brand_source_page($pdo, 'facility', $page['next_id'], 1);
$assert($page2['next_id'] === 3 && !$page2['has_more'], 'Keyset cursor crosses gaps and ends correctly.');
$assert(medical_image_brand_source_page($pdo, 'doctor')['scanned'] === 1, 'Minimal installed schema works without migrations.');
$requiredTransaction = false;
try { medical_image_brand_apply($pdo, [['type' => 'facility', 'id' => 1]], $identity['key'], $output); } catch (LogicException) { $requiredTransaction = true; }
$assert($requiredTransaction, 'Apply requires caller transaction.');
$pdo->beginTransaction();
$result = medical_image_brand_apply($pdo, [['type' => 'facility', 'id' => 1], ['type' => 'doctor', 'id' => 1], ['type' => 'facility', 'id' => 99]], $identity['key'], $output);
$assert($result === ['updated' => 2, 'skipped' => 1] && $pdo->inTransaction(), 'Apply locks/re-reads refs and leaves transaction owned by caller.');
$saved = $pdo->query('SELECT * FROM medical_facilities WHERE id=1')->fetch();
$assert($saved['image_url'] === $output && $saved['full_json'] === $row['full_json'] && $saved['image_fix_json'] === $row['image_fix_json'], 'Current photo refs change, trace JSON stays byte-identical.');
$assert(medical_image_brand_apply($pdo, [['type' => 'facility', 'id' => 1]], $identity['key'], $output) === ['updated' => 0, 'skipped' => 1], 'Repeated apply is idempotent.');
$pdo->rollBack();
$assert($pdo->query('SELECT image_url FROM medical_facilities WHERE id=1')->fetchColumn() === $url, 'Caller rollback restores all reference changes.');
$pdo->prepare('UPDATE medical_facilities SET image_url=?, gallery_json=? WHERE id=3')->execute(['https://photos.example.org/new.jpg', '[]']);
$pdo->beginTransaction();
$assert(medical_image_brand_apply($pdo, [['type' => 'facility', 'id' => 3]], $identity['key'], $output) === ['updated' => 0, 'skipped' => 1], 'Editor-removed sources are never resurrected.');
$pdo->rollBack();
$previous = '/uploads/library/medreview-branded/' . str_repeat('a', 24) . '-' . str_repeat('b', 16) . '.jpg';
$unrelated = '/uploads/library/medreview-branded/' . str_repeat('c', 24) . '-' . str_repeat('d', 16) . '.jpg';
$foreignPrevious = 'https://photos.example.org' . $previous;
$recoveryGallery = [['url' => 'https://medreview.vn' . $previous . '?v=1', 'caption' => $previous], ['url' => $unrelated], ['url' => $foreignPrevious]];
$pdo->prepare('UPDATE medical_facilities SET image_url=?, gallery_json=? WHERE id=3')->execute([$previous, json_encode($recoveryGallery)]);
$pdo->beginTransaction();
$assert(medical_image_brand_apply($pdo, [['type' => 'facility', 'id' => 3]], $identity['key'], $output, $previous) === ['updated' => 1, 'skipped' => 0], 'Exact missing previous output is repaired inside caller transaction.');
$recovered = $pdo->query('SELECT image_url,gallery_json FROM medical_facilities WHERE id=3')->fetch();
$recoveredGallery = json_decode($recovered['gallery_json'], true);
$assert($recovered['image_url'] === $output && $recoveredGallery[0]['url'] === $output && $recoveredGallery[0]['caption'] === $previous, 'Recovery updates current old-output locations but preserves captions.');
$assert($recoveredGallery[1]['url'] === $unrelated && $recoveredGallery[2]['url'] === $foreignPrevious, 'Recovery leaves unrelated branded images and foreign matching paths untouched.');
$pdo->rollBack();
$pdo->prepare('UPDATE medical_facilities SET image_url=?, gallery_json=? WHERE id=3')->execute(['https://photos.example.org/editor-new.jpg', '[]']);
$pdo->beginTransaction();
$assert(medical_image_brand_apply($pdo, [['type' => 'facility', 'id' => 3]], $identity['key'], $output, $previous) === ['updated' => 0, 'skipped' => 1], 'Recovery does not overwrite a newer editor-selected photo.');
foreach (['/uploads/library/unbranded.jpg', '/uploads/library/medreview-branded/not-generated.jpg', 'https://photos.example.org' . $previous] as $badPrevious) {
    $rejected = false;
    try { medical_image_brand_apply($pdo, [], $identity['key'], $output, $badPrevious); } catch (InvalidArgumentException) { $rejected = true; }
    $assert($rejected, 'Recovery rejects unowned previous output: ' . $badPrevious);
}
$pdo->rollBack();
$icon = 'https://photos.example.org/custom-hashed-icon.jpg';
$pdo->prepare('INSERT INTO site_settings VALUES (?,?)')->execute(['site_icon_href', $icon]);
$pdo->prepare('UPDATE medical_doctors SET image_url=? WHERE id=1')->execute([$icon]);
$assert(medical_image_brand_source_page($pdo, 'doctor')['rows'][0]['sources'] === [], 'Configured hash-named site icon is excluded.');
echo "Image brand sources: {$checks} checks passed; SQLite fixtures only, no network or production writes.\n";
