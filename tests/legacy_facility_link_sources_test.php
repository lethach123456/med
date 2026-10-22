<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

$checks = 0;
function legacy_facility_links_expect(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

$sources = [];
foreach (['post.php', 'toplist-chi-tiet-mau.php', 'admin/medical_facilities.php'] as $file) {
    $sources[$file] = (string) file_get_contents(__DIR__ . '/../' . $file);
    legacy_facility_links_expect(!str_contains($sources[$file], 'co-so-y-te-chi-tiet.php'), $file . ' must not generate the redirected facility detail URL.');
    legacy_facility_links_expect(str_contains($sources[$file], "medical_public_entity_path('facility'"), $file . ' must use the canonical route helper.');
}

// Render only the href expression, never the controllers or their DB queries.
$postSource = $sources['post.php'];
legacy_facility_links_expect(str_contains($postSource, '$post[\'content\'] = medical_normalize_facility_links_html((string) ($post[\'content\'] ?? \'\'));'), 'All article types must normalize stored facility anchors before rendering.');
legacy_facility_links_expect(preg_match('~href="(<\?php echo htmlspecialchars\(medical_public_entity_path\(\'facility\'.+?\?>)"~', $postSource, $postLink) === 1, 'Post recommendation href must use the shared facility helper.');
$toplistSource = $sources['toplist-chi-tiet-mau.php'];
legacy_facility_links_expect(str_contains($toplistSource, 'return medical_normalize_facility_links_html(str_replace(\'</body>\', $script . \'</body>\', $html));'), 'Facility Toplist must normalize stored intro/member anchors after HTML transformations.');
legacy_facility_links_expect(preg_match('~href="(<\?=htmlspecialchars\(medical_public_entity_path\(\'facility\'.+?\?>)"~', $toplistSource, $toplistLink) === 1, 'Toplist facility href must use the shared facility helper.');
$renderHref = static function (string $expression, array $variables): string {
    extract($variables, EXTR_SKIP);
    ob_start();
    try { eval('?>' . $expression); return (string) ob_get_clean(); }
    catch (Throwable $error) { ob_end_clean(); throw $error; }
};

foreach (['vi', 'en'] as $locale) {
    $row = ['slug' => 'phong-kham-a&b', 'language_code' => $locale];
    $expected = ($locale === 'en' ? '/en' : '') . '/co-so-y-te/phong-kham-a%26b';
    legacy_facility_links_expect($renderHref($postLink[1], ['item' => $row, 'htmlLang' => $locale === 'en' ? 'vi' : 'en']) === $expected, 'Post href must follow the facility record locale, not the article locale.');
    legacy_facility_links_expect($renderHref($toplistLink[1], ['facility' => $row, 'toplistLanguage' => $locale === 'en' ? 'vi' : 'en']) === $expected, 'Toplist href must follow the facility record locale.');
}

legacy_facility_links_expect(str_contains($toplistSource, 'data-facility-slug="<?=htmlspecialchars((string)$facility[\'slug\'],ENT_QUOTES,\'UTF-8\')?>"'), 'Facility cards must expose an escaped stable identity for galleries/reviews.');
legacy_facility_links_expect(substr_count($toplistSource, 'dataset.facilitySlug') === 6, 'All six gallery/review slug consumers must use the stable card identity.');
legacy_facility_links_expect(!str_contains($toplistSource, 'searchParams.get("slug")'), 'Gallery/review code must no longer depend on a legacy query-string slug.');
$adminSource = $sources['admin/medical_facilities.php'];
legacy_facility_links_expect(str_contains($adminSource, "medical_public_entity_path('facility', '', 'en')"), 'Admin must provide a canonical English facility base.');
legacy_facility_links_expect(str_contains($adminSource, "String(row.language_code || 'vi').toLowerCase()"), 'Admin must select a detail base from the record locale.');

echo "OK: {$checks} legacy facility link-source checks.\n";
