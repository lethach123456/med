<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('DOCTOR_PROFILE_FIXTURE_ONLY', true);
define('DOCTOR_LEGAL_PROFILE_FIXTURE_ONLY', true);
require __DIR__ . '/doctor_profile_test.php';
require __DIR__ . '/doctor_legal_profile_test.php';

/** Synthetic view fixtures only. Neither the model nor this partial opens a database. */
function dp_view_complete_doctor(string $locale): array
{
    $doctor = doctor_profile_test_fixture($locale);
    $legal = doctor_legal_profile_test_fixture($locale);
    foreach (['professional_profile_url', 'practice_license_type', 'practice_license_number', 'practice_license_issuer',
        'practice_license_issued_date', 'practice_license_scope', 'practice_registry_url', 'practice_registration_json',
        'legal_documents_json', 'legal_notes', 'legal_source_ids_json', 'sources_json'] as $field) {
        $doctor[$field] = $legal[$field];
    }
    $doctor['name'] = $locale === 'en' ? 'Doctor Complete Profile Fixture' : 'Bác sĩ Hồ Sơ Đầy Đủ';
    $doctor['specialties'] = ['Ophthalmology', 'Paediatric ophthalmology', 'Strabismus surgery'];
    $doctor['content'] = '<h2>Professional profile</h2>';
    for ($index = 1; $index <= 12; $index++) {
        $doctor['content'] .= '<p>COMPLETE_ARTICLE_PARAGRAPH_' . $index . ' — Full published professional information remains available without JavaScript.</p>';
    }
    $doctor['image_url'] = 'https://example.org/media/doctor-portrait.webp';
    $doctor['gallery_items'] = [
        ['url' => $doctor['image_url'], 'caption' => 'Published portrait caption', 'alt' => 'Actual portrait alternative text', 'source_ids' => ['s1']],
        ['url' => 'https://example.org/media/consultation-room.jpg', 'caption' => 'Consultation room caption', 'alt' => 'Actual consultation room alternative text', 'source_ids' => ['s2']],
        ['url' => 'https://example.org/media/equipment.png', 'caption' => 'Published clinical equipment', 'source_ids' => ['s1']],
    ];
    $doctor['gallery'] = array_column($doctor['gallery_items'], 'url');
    $doctor['notes_for_editor'] = 'PRIVATE_VIEW_EDITOR_MARKER';
    $doctor['ai_writer_claim_json'] = ['token' => 'PRIVATE_VIEW_WORKER_MARKER'];
    return $doctor;
}

function dp_view_render(array $doctor, string $doctorLanguage, array $facilityPaths = [], array $relatedDoctors = [], ?array $legacyFacility = null): array
{
    $profile = medical_doctor_profile_model($doctor, $legacyFacility);
    // Simulate authoritative page-level facility resolution, never trust a path from AI JSON.
    foreach ($profile['locations'] as &$location) {
        if (isset($facilityPaths[$location['facility_name']])) $location['profile_url'] = $facilityPaths[$location['facility_name']];
    }
    unset($location);
    $escape = static fn(mixed $value): string => htmlspecialchars(medical_doctor_profile_text($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ob_start();
    try {
        require dirname(__DIR__) . '/Tem/doctor-profile.php';
        return ['html' => (string) ob_get_clean(), 'model' => $profile];
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
}

function dp_view_dom(string $html): array
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return [$dom, new DOMXPath($dom)];
}

function dp_view_class(string $class): string
{
    return 'contains(concat(" ",normalize-space(@class)," ")," ' . $class . ' ")';
}

$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($label);
};
$nodes = static fn(DOMXPath $xpath, string $query, ?DOMNode $context = null): DOMNodeList => $xpath->query($query, $context);
$one = static fn(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode => $xpath->query($query, $context)->item(0);
$hrefs = static function (DOMNodeList $links): array {
    $values = [];
    foreach ($links as $link) $values[] = $link->getAttribute('href');
    return $values;
};
$heroMetadata = static function (DOMXPath $xpath, array $profile, string $context) use ($assert, $nodes, $one): void {
    $metadata = $nodes($xpath, '//*[' . dp_view_class('dp-hero') . ']//*[' . dp_view_class('dp-hero-meta') . ']');
    $hasMetadata = $profile['updated_label'] !== '' || $profile['sources'] !== [];
    $assert($metadata->length === ($hasMetadata ? 1 : 0), $context . 'hero metadata only exists for a real research date or valid sources');
    if (!$hasMetadata) return;
    $wrapper = $metadata->item(0);
    $dates = $nodes($xpath, './span', $wrapper);
    $assert($dates->length === ($profile['updated_label'] !== '' ? 1 : 0), $context . 'hero update date is not invented when absent or invalid');
    if ($profile['updated_label'] !== '') $assert(str_contains($dates->item(0)->textContent, $profile['updated_label']), $context . 'hero shows the actual researched date');
    $sourceLink = $one($xpath, './a[@href="#nguon-tham-khao"]', $wrapper);
    $assert($profile['sources'] !== [] ? $sourceLink !== null : $sourceLink === null, $context . 'hero source link exists only for a rendered source section');
    if ($sourceLink !== null) $assert(preg_match('/^\s*' . count($profile['sources']) . '\s+\S/u', $sourceLink->textContent) === 1, $context . 'hero counts valid model sources, not raw or fabricated references');
};
$structure = static function (DOMXPath $xpath, array $expectedSections, string $context) use ($assert, $nodes, $one, $hrefs): void {
    $assert($nodes($xpath, '//main[@id="doctor-main"]')->length === 1, $context . 'one skip-link target wraps the profile');
    $assert($nodes($xpath, '//h1[@id="doctor-name"]')->length === 1, $context . 'one primary doctor heading');
    $assert($nodes($xpath, '//*[' . dp_view_class('dp-medical-art') . ' or ' . dp_view_class('dp-visual-symbol') . ']')->length === 0, $context . 'oversized decorative SVG scene is removed');
    $assert($nodes($xpath, '//img[@src=""] | //a[@href="" or @href="#"]')->length === 0, $context . 'no empty or fake image/link targets');
    $assert($nodes($xpath, '//a//button | //button//a | //summary//a | //summary//button | //a//summary')->length === 0, $context . 'interactive controls are not nested');
    $ids = [];
    foreach ($nodes($xpath, '//*[@id]') as $node) {
        $id = $node->getAttribute('id');
        $assert(!isset($ids[$id]), $context . 'DOM id is unique: ' . $id);
        $ids[$id] = true;
    }
    foreach ($nodes($xpath, '//a[starts-with(@href,"#")]') as $anchor) {
        $assert(isset($ids[substr($anchor->getAttribute('href'), 1)]), $context . 'in-page link resolves: ' . $anchor->getAttribute('href'));
    }
    foreach ($nodes($xpath, '//*[@aria-labelledby]') as $element) {
        foreach (preg_split('/\s+/', trim($element->getAttribute('aria-labelledby'))) as $id) {
            $assert(isset($ids[$id]), $context . 'accessible heading exists: ' . $id);
        }
    }
    foreach ($expectedSections as $id) {
        $section = $one($xpath, '//section[@id="' . $id . '"]');
        $assert($section instanceof DOMElement, $context . 'data-backed section exists: ' . $id);
        $headingId = $section->getAttribute('aria-labelledby');
        $assert($headingId !== '' && $nodes($xpath, './/h2[@id="' . $headingId . '"]', $section)->length === 1, $context . 'section has its own named heading: ' . $id);
    }
    $mobile = $one($xpath, '//nav[' . dp_view_class('dp-nav') . ']');
    $desktop = $one($xpath, '//details[' . dp_view_class('dp-toc-card') . ']/nav[' . dp_view_class('dp-toc') . ']');
    $assert($mobile !== null && $desktop !== null, $context . 'mobile rail and native desktop contents both exist');
    $assert($nodes($xpath, '//details[' . dp_view_class('dp-toc-card') . ']/summary')->length === 1, $context . 'desktop contents has a native summary control');
    $mobileLinks = $hrefs($nodes($xpath, './a', $mobile));
    $desktopLinks = $hrefs($nodes($xpath, './a', $desktop));
    $assert($mobileLinks === array_map(static fn(string $id): string => '#' . $id, $expectedSections), $context . 'mobile rail contains only real sections in article order');
    $assert($desktopLinks === $mobileLinks, $context . 'desktop contents has exactly the same actual section targets');
    foreach ([$mobile, $desktop] as $nav) {
        $assert($nav instanceof DOMElement && $nav->getAttribute('aria-label') !== '', $context . 'section navigation has an accessible name');
        foreach ($nodes($xpath, './a', $nav) as $link) $assert(trim($link->textContent) !== '', $context . 'section link is named');
    }
    foreach ($nodes($xpath, '//a[@target="_blank"]') as $link) {
        $rel = preg_split('/\s+/', $link->getAttribute('rel'));
        $assert(in_array('noopener', $rel, true) && in_array('noreferrer', $rel, true), $context . 'new-tab external link is isolated');
    }
};

$allSections = ['gioi-thieu', 'chuyen-mon', 'dao-tao', 'ho-so-phap-ly', 'cong-tac', 'noi-kham', 'lich-chi-phi', 'nghien-cuu', 'tu-lieu', 'nguon-tham-khao'];
foreach (['vi', 'en'] as $locale) {
    $prefix = $locale === 'en' ? '/en' : '';
    $doctor = dp_view_complete_doctor($locale);
    $paths = ['Hospital A' => $prefix . '/co-so-y-te/hospital-a', 'Clinic B' => $prefix . '/co-so-y-te/clinic-b'];
    $related = [
        ['name' => 'Bác sĩ liên quan Việt', 'slug' => 'related-vi', 'language_code' => 'vi', 'specialty_text' => 'Nhãn khoa', 'city' => 'Huế', 'image_url' => 'https://example.org/related-vi.webp'],
        ['name' => 'Related English doctor', 'slug' => 'related-en', 'language_code' => 'en', 'specialty_text' => 'Ophthalmology', 'city' => 'Hanoi', 'image_url' => ''],
    ];
    $rendered = dp_view_render($doctor, $locale, $paths, $related);
    [$dom, $xpath] = dp_view_dom($rendered['html']);
    $profile = $rendered['model'];
    $context = $locale . ' complete: ';
    $structure($xpath, $allSections, $context);
    $heroMetadata($xpath, $profile, $context);
    $assert($one($xpath, '//h1')->textContent === $profile['name'], $context . 'actual name remains complete');
    $assert($nodes($xpath, '//*[' . dp_view_class('dp-reviewed') . ']')->length === 1, $context . 'reviewed badge follows both stored verification fields');
    $rating = $one($xpath, '//*[' . dp_view_class('dp-rating') . ']');
    $assert($rating !== null && str_contains($rating->textContent, '4.3/5') && str_contains($rating->textContent, '8'), $context . 'actual doctor-specific rating and count are preserved');
    $article = $one($xpath, '//*[@id="doctor-article" and @data-dp-article]');
    $assert($article !== null && $nodes($xpath, './p', $article)->length === 12, $context . 'complete article is in the initial HTML without JavaScript');
    $assert($nodes($xpath, 'ancestor::details | ancestor::*[@hidden or @aria-hidden="true"]', $article)->length === 0, $context . 'article is not initially hidden behind JavaScript or details');
    for ($index = 1; $index <= 12; $index++) $assert(substr_count($article->textContent, 'COMPLETE_ARTICLE_PARAGRAPH_' . $index . ' —') === 1, $context . 'full paragraph retained once: ' . $index);
    foreach ($profile['specialties'] as $specialty) $assert(str_contains($one($xpath, '//section[@id="chuyen-mon"]')->textContent, $specialty), $context . 'specialty preserved: ' . $specialty);
    foreach (['Eye consultation', 'Clinical assessment.', 'Myopia', 'Adults', 'Medical university', 'Training certificate', 'University', 'Consultant',
        'Research paper', '10.0000/example', 'Professional association', 'Research award'] as $value) {
        $assert(str_contains($dom->textContent, $value), $context . 'existing public field retained: ' . $value);
    }
    foreach (['LEGAL-TEST/123', 'Synthetic licence authority', 'Synthetic scope of clinical practice', 'Synthetic registered hospital',
        'Synthetic eye department', 'Synthetic registered clinical scope', 'Published registration: Monday 09:00–11:00',
        'Synthetic public legal document', 'PUBLIC-DOC/456', 'Synthetic document issuer', 'Published legal details should be confirmed with the provider.'] as $value) {
        $assert(str_contains($one($xpath, '//section[@id="ho-so-phap-ly"]')->textContent, $value), $context . 'public legal field retained: ' . $value);
    }
    $assert(str_contains($dom->textContent, '29/02/2024') && str_contains($dom->textContent, '01/03/2025'), $context . 'real legal document dates stay intact');
    foreach ([$profile['legal']['professional_profile_url'], $profile['legal']['practice_registry_url'], $profile['legal']['documents'][0]['url'],
        $profile['booking_url'], $profile['website_url'], $profile['phone_href'], 'mailto:' . $profile['email_text'], 'https://example.org/research'] as $url) {
        $assert(in_array($url, $hrefs($nodes($xpath, '//a[@href]')), true), $context . 'actual public link retained: ' . $url);
    }
    $assert($nodes($xpath, '//a[' . dp_view_class('dp-facility-link') . ' and @href="' . $paths['Hospital A'] . '"]')->length === 3, $context . 'primary facility name is linked in hero facts, practice and contact sidebar');
    $assert($nodes($xpath, '//a[' . dp_view_class('dp-facility-link') . ' and @href="' . $paths['Clinic B'] . '"]')->length === 1, $context . 'secondary practice has its own real facility link');
    foreach ($nodes($xpath, '//a[' . dp_view_class('dp-facility-link') . ']') as $link) {
        $assert(!$link->hasAttribute('target') && (str_contains($link->getAttribute('aria-label'), 'Hospital') || str_contains($link->getAttribute('aria-label'), 'Clinic B')), $context . 'facility name links remain named and local');
    }
    $places = $nodes($xpath, '//article[' . dp_view_class('dp-location') . ']');
    $assert($places->length === 2 && str_contains($places->item(0)->textContent, 'Hospital A') && str_contains($places->item(1)->textContent, 'Clinic B'), $context . 'primary-first workplace order remains stable');
    $assert(str_contains($places->item(0)->textContent, 'Monday') && str_contains($places->item(1)->textContent, 'Friday'), $context . 'each timetable stays at its own workplace');
    $assert($nodes($xpath, './/*[' . dp_view_class('dp-fee-row') . ']', $places->item(0))->length === 1 && $nodes($xpath, './/*[' . dp_view_class('dp-fee-row') . ']', $places->item(1))->length === 0, $context . 'fees are never copied to a second practice');
    $formattedFee = medical_doctor_profile_fee($profile['fees_json'][0], $locale === 'en');
    $assert(str_contains($one($xpath, '//section[@id="lich-chi-phi"]')->textContent, $formattedFee), $context . 'global consultation range retains its locale formatting');
    foreach ($profile['sources'] as $source) {
        $assert($nodes($xpath, '//li[@id="' . $source['anchor'] . '"]')->length === 1, $context . 'one real source anchor: ' . $source['anchor']);
        $assert($nodes($xpath, '//a[@href="#' . $source['anchor'] . '"]')->length > 0, $context . 'source citations remain usable: ' . $source['anchor']);
        $assert(in_array($source['url'], $hrefs($nodes($xpath, '//section[@id="nguon-tham-khao"]//a')), true), $context . 'source URL remains public and inspectable');
    }
    $gallery = $nodes($xpath, '//*[' . dp_view_class('dp-gallery') . ']/figure');
    $assert($gallery->length === count($profile['gallery']), $context . 'all actual gallery images remain');
    foreach ($gallery as $index => $figure) {
        $url = $profile['gallery'][$index];
        $metadata = $profile['gallery_meta'][$url];
        $image = $one($xpath, './/img', $figure);
        $caption = $one($xpath, './figcaption', $figure);
        $assert($image instanceof DOMElement && $image->getAttribute('src') === $url && $image->getAttribute('alt') === ($metadata['alt'] ?? $metadata['caption']), $context . 'gallery retains actual URL and explicit alt, with caption fallback');
        $assert($caption !== null && str_contains($caption->textContent, $metadata['caption']), $context . 'gallery caption stays visible');
        $button = $one($xpath, './/button[@data-dp-gallery]', $figure);
        $assert($button->getAttribute('data-dp-gallery') === (string) $index, $context . 'lightbox index matches the real gallery order');
        $assert($button->getAttribute('data-dp-gallery-caption') === $metadata['caption'] && $button->getAttribute('data-dp-gallery-alt') === ($metadata['alt'] ?? $metadata['caption']), $context . 'lightbox receives the same real caption and alt as the visible gallery');
    }
    $relatedLinks = $hrefs($nodes($xpath, '//a[' . dp_view_class('dp-related-card') . ']'));
    $assert($relatedLinks === ['/bac-si/related-vi', '/en/bac-si/related-en'], $context . 'related profiles retain their own typed language-specific doctor routes');
    $assert($nodes($xpath, '//a[' . dp_view_class('dp-related-card') . ']//img')->length === 1, $context . 'related profile without a photo has no invented image');
    foreach (['PRIVATE_VIEW_EDITOR_MARKER', 'PRIVATE_VIEW_WORKER_MARKER', 'PRIVATE_DOCUMENT_MARKER', 'PRIVATE_REGISTRATION_MARKER', 'notes_for_editor', 'ai_writer_claim_json'] as $private) {
        $assert(!str_contains($rendered['html'], $private), $context . 'private workflow field excluded: ' . $private);
    }
    $assert(str_contains($dom->textContent, $locale === 'en' ? 'Education & qualifications' : 'Đào tạo & bằng cấp'), $context . 'section labels use the correct interface language');
}

foreach (['vi', 'en'] as $locale) {
    $doctor = medical_directory_doctor_from_row(['name' => $locale === 'en' ? 'Doctor Sparse Fixture' : 'Bác sĩ Thiếu Dữ Liệu', 'slug' => 'sparse-fixture', 'language_code' => $locale,
        'verified' => 1, 'verification_status' => 'unreviewed', 'rating' => 5, 'reviews_count' => 0, 'image_url' => '', 'last_researched_at' => '2026-02-31']);
    $rendered = dp_view_render($doctor, $locale);
    [$dom, $xpath] = dp_view_dom($rendered['html']);
    $context = $locale . ' sparse: ';
    $structure($xpath, ['gioi-thieu'], $context);
    $heroMetadata($xpath, $rendered['model'], $context);
    $assert($nodes($xpath, '//*[' . dp_view_class('dp-reviewed') . ' or ' . dp_view_class('dp-rating') . ']')->length === 0, $context . 'reviewed/score badges are not fabricated from an unreviewed or zero-review row');
    $assert(str_contains($dom->textContent, $locale === 'en' ? 'No ratings yet' : 'Chưa có đánh giá') && !str_contains($dom->textContent, '5.0/5'), $context . 'unknown review state is honest');
    $assert($nodes($xpath, '//*[' . dp_view_class('dp-portrait') . ']//img')->length === 0, $context . 'missing portrait is not replaced by an invented photograph');
    $initials = $one($xpath, '//*[' . dp_view_class('dp-avatar') . ' and @aria-hidden="true"]');
    $assert($initials !== null && $initials->textContent === $rendered['model']['initials'] && $initials->textContent !== '', $context . 'decorative placeholder uses actual name initials');
    $assert($nodes($xpath, '//section[@id="lich-chi-phi" or @id="noi-kham" or @id="nguon-tham-khao" or @id="ho-so-phap-ly"]')->length === 0, $context . 'unknown fees, workplaces, sources and legal details create no empty sections');
    $assert($nodes($xpath, '//a[starts-with(@href,"tel:") or starts-with(@href,"mailto:") or contains(@href,"example.org/book")]')->length === 0, $context . 'unknown contacts and booking URLs create no fake actions');
    $assert($nodes($xpath, '//time')->length === 0 && !str_contains($dom->textContent, '31/02/2026'), $context . 'invalid research dates are omitted rather than replaced with today');
}

foreach (['vi', 'en'] as $locale) {
    foreach (['date only', 'sources only', 'neither'] as $variant) {
        $doctor = dp_view_complete_doctor($locale);
        if ($variant !== 'sources only') $doctor['sources_json'] = [];
        if ($variant !== 'date only') $doctor['last_researched_at'] = '2026-02-31';
        $rendered = dp_view_render($doctor, $locale);
        [$dom, $xpath] = dp_view_dom($rendered['html']);
        $heroMetadata($xpath, $rendered['model'], $locale . ' ' . $variant . ': ');
    }
}

// Legacy aliases remain real data, not an invitation to guess schedules, fees, or facility links.
foreach (['vi', 'en'] as $locale) {
    $legacy = medical_directory_doctor_from_row(['name' => 'Legacy Profile', 'slug' => 'legacy-profile', 'language_code' => $locale,
        'facility_name' => 'Legacy actual hospital', 'address_text' => 'Known old address', 'hours_text' => 'LEGACY_HOURS_TEXT', 'price_text' => 'LEGACY_PRICE_TEXT',
        'bio_json' => '["LEGACY_BIO_ONE","LEGACY_BIO_TWO"]']);
    $rendered = dp_view_render($legacy, $locale);
    [$dom, $xpath] = dp_view_dom($rendered['html']);
    $structure($xpath, ['gioi-thieu', 'noi-kham', 'lich-chi-phi'], $locale . ' legacy: ');
    foreach (['LEGACY_HOURS_TEXT', 'LEGACY_PRICE_TEXT', 'LEGACY_BIO_ONE', 'LEGACY_BIO_TWO', 'Legacy actual hospital', 'Known old address'] as $value) {
        $assert(str_contains($dom->textContent, $value), $locale . ': legacy alias remains visible: ' . $value);
    }
    $assert($nodes($xpath, '//a[' . dp_view_class('dp-facility-link') . ']')->length === 0, $locale . ': unresolved legacy workplace remains text, not a guessed facility link');
}

// Unsafe imported prose/contact/media is sanitized before this read-only presentation.
$unsafe = dp_view_complete_doctor('vi');
$unsafe['name'] = str_repeat('Bác sĩ tên dài & <script>IDENTITY_XSS</script> ', 6);
$unsafe['content'] = '<h2 id="doctor-main">SAFE_IMPORTED_HEADING</h2><p onclick="bad()">SAFE_IMPORTED_PARAGRAPH</p><script>ARTICLE_XSS</script><iframe src="https://evil.example"></iframe>';
$unsafe['booking_url'] = 'javascript:alert(1)';
$unsafe['website_url'] = 'data:text/html,evil';
$unsafe['email_text'] = 'not-an-email';
$unsafe['phone_text'] = 'unknown';
$unsafe['image_url'] = 'javascript:alert(2)';
$unsafe['gallery'] = ['javascript:alert(3)', 'https://example.org/safe-gallery.jpg'];
$unsafe['gallery_items'] = [['url' => 'https://example.org/safe-gallery.jpg', 'caption' => '<script>CAPTION_XSS</script>', 'alt' => 'Actual safe alt']];
$unsafe['legal_notes'] = '<script>LEGAL_XSS</script>';
$unsafe['sources_json'][] = ['id' => 'unsafe', 'url' => 'javascript:alert(4)', 'title' => 'Invalid source'];
$unsafe['services_json'][0]['source_ids'] = ['s1', 'unknown', 's1'];
foreach ($unsafe['locations_json'] as &$location) {
    $location['phone_text'] = 'unknown';
    $location['booking_url'] = 'javascript:alert(5)';
    $location['website_url'] = 'javascript:alert(6)';
    $location['profile_url'] = '/co-so-y-te/ai-invented-facility';
}
unset($location);
$rendered = dp_view_render($unsafe, 'vi');
[$dom, $xpath] = dp_view_dom($rendered['html']);
$structure($xpath, $allSections, 'Unsafe imported profile: ');
$heroMetadata($xpath, $rendered['model'], 'Unsafe imported profile: ');
$assert(!str_contains($rendered['html'], '<script>IDENTITY_XSS') && !str_contains($rendered['html'], '<script>CAPTION_XSS') && !str_contains($rendered['html'], '<script>LEGAL_XSS') && !str_contains($rendered['html'], 'ARTICLE_XSS'), 'Imported identity/caption/legal text stays escaped and active article payload is removed');
$assert($nodes($xpath, '//script | //iframe | //*[@onclick or @onerror or @onload]')->length === 0, 'No active imported markup reaches the profile');
$assert($one($xpath, '//h1')->textContent === medical_doctor_profile_text($unsafe['name']), 'Long imported name remains complete plain text');
$assert(str_contains($dom->textContent, 'SAFE_IMPORTED_HEADING') && str_contains($dom->textContent, 'SAFE_IMPORTED_PARAGRAPH'), 'Safe parts of imported article are not lost during sanitization');
$assert($nodes($xpath, '//a[starts-with(@href,"javascript:") or starts-with(@href,"data:")] | //img[starts-with(@src,"javascript:")]')->length === 0, 'Unsafe contact and image schemes never become actions');
$assert($nodes($xpath, '//a[@href="/co-so-y-te/ai-invented-facility" or @href="#unknown"]')->length === 0, 'Untrusted facility paths and source IDs do not create public links');
$assert(count($rendered['model']['sources']) === 2 && count($rendered['model']['gallery']) === 1, 'Invalid source and photo URLs are omitted without losing valid entries');

echo "Doctor profile view: {$checks} checks passed. No database or network used.\n";
