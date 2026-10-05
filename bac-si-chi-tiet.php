<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
require_once __DIR__ . '/medical_doctor_profile.php';
if (function_exists('admin_front_session_boot')) admin_front_session_boot();

$slug = trim((string) ($_GET['slug'] ?? ''));
$doctor = medical_directory_doctor_row_by_slug($slug, true);
if (!$doctor) {
    http_response_code(404);
    $notFoundTitle = 'Không tìm thấy hồ sơ bác sĩ';
    $notFoundDescription = 'Hồ sơ bác sĩ không tồn tại, đã bị ẩn hoặc không còn được xuất bản.';
    require __DIR__ . '/Tem/public-404.php';
    exit;
}
$doctorLanguage = ($doctor['language_code'] ?? 'vi') === 'en' ? 'en' : 'vi';
$doctorLanguageLinks = medical_directory_translation_switch_links(db(), 'doctor', $doctor);
$GLOBALS['site_forced_locale'] = $doctorLanguage;
$GLOBALS['site_language_links'] = $doctorLanguageLinks;
$legacyFacility = !empty($doctor['facility_slug']) ? medical_directory_facility_row_by_slug($doctor['facility_slug'], true) : null;
$profile = medical_doctor_profile_model($doctor, $legacyFacility);

// Resolve only published linked workplaces, with the matching language counterpart where available.
$facilityIds = array_values(array_unique(array_filter(array_map(static fn($item) => (int) ($item['facility_id'] ?? 0), $profile['locations']))));
if ($facilityIds !== []) {
    $placeholders = implode(',', array_fill(0, count($facilityIds), '?'));
    $stmt = db()->prepare("SELECT id,slug,language_code,translation_of_id FROM medical_facilities WHERE status='published' AND (id IN ({$placeholders}) OR translation_of_id IN ({$placeholders}))");
    $stmt->execute(array_merge($facilityIds, $facilityIds));
    $linkedFacilities = $stmt->fetchAll();
    foreach ($profile['locations'] as &$location) {
        $id = (int) ($location['facility_id'] ?? 0);
        $match = null;
        foreach ($linkedFacilities as $candidate) {
            if ((int) $candidate['id'] === $id) $match ??= $candidate;
            if ($candidate['language_code'] === $doctorLanguage && ((int) $candidate['id'] === $id || (int) $candidate['translation_of_id'] === $id)) { $match = $candidate; break; }
        }
        if ($match) $location['profile_url'] = medical_public_entity_path('facility', $match['slug'], $match['language_code']);
    }
    unset($location);
}

// Small related-profile query; do not load every full research article into memory.
$stmt = db()->prepare("SELECT id,slug,name,title_text,specialty_text,city,image_url,language_code FROM medical_doctors
    WHERE status='published' AND language_code=:locale AND id<>:id
    ORDER BY (specialty_text=:specialty) DESC,(city=:city) DESC,display_order ASC,id DESC LIMIT 3");
$stmt->execute([':locale' => $doctorLanguage, ':id' => $doctor['id'], ':specialty' => $doctor['specialty_text'], ':city' => $doctor['city']]);
$relatedDoctors = $stmt->fetchAll();

$english = $doctorLanguage === 'en';
$title = trim($doctor['seo_title']) ?: $doctor['name'] . ' | MedReview';
$description = '';
foreach (['seo_description', 'subtitle', 'title_text'] as $field) {
    $description = trim($doctor[$field] ?? '');
    if ($description !== '') break;
}
if ($description === '') $description = $doctor['name'] . ($english ? ' — professional profile, expertise and practice locations on MedReview.' : ' — hồ sơ chuyên môn, quá trình công tác và nơi khám trên MedReview.');
$description = site_meta_description($description);
$canonical = site_absolute_url(medical_public_entity_path('doctor', $doctor['slug'], $doctorLanguage));
$imageAbsolute = site_absolute_media_url($profile['image_url']);
$schema = ['@context' => 'https://schema.org', '@type' => 'Physician', '@id' => $canonical . '#physician',
    'name' => $doctor['name'], 'url' => $canonical, 'description' => $description];
if ($doctor['specialty_text'] !== '') $schema['medicalSpecialty'] = $doctor['specialty_text'];
if ($imageAbsolute !== '') $schema['image'] = $imageAbsolute;
if ($profile['phone_href'] !== '') $schema['telephone'] = $profile['phone_text'];
if ($profile['locations'] !== []) $schema['worksFor'] = array_map(static fn($location) => ['@type' => 'MedicalOrganization', 'name' => $location['facility_name']], $profile['locations']);
$breadcrumbSchema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => $english ? 'Home' : 'Trang chủ', 'item' => site_absolute_url(site_localized_path('/', $doctorLanguage))],
    ['@type' => 'ListItem', 'position' => 2, 'name' => $english ? 'Doctors' : 'Bác sĩ', 'item' => site_absolute_url(site_localized_path('/bac-si.php', $doctorLanguage))],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $doctor['name'], 'item' => $canonical],
]];
$escape = static fn(mixed $value): string => htmlspecialchars(medical_doctor_profile_text($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="<?= $escape($doctorLanguage) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $escape($title) ?></title>
  <meta name="description" content="<?= $escape($description) ?>">
  <link rel="canonical" href="<?= $escape($canonical) ?>">
  <?php if (!empty($doctorLanguageLinks['has_counterpart'])): ?>
  <link rel="alternate" hreflang="vi" href="<?= $escape(site_absolute_url($doctorLanguageLinks['vi'])) ?>">
  <link rel="alternate" hreflang="en" href="<?= $escape(site_absolute_url($doctorLanguageLinks['en'])) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= $escape(site_absolute_url($doctorLanguageLinks['vi'])) ?>">
  <?php endif; ?>
  <meta property="og:type" content="profile">
  <meta property="og:title" content="<?= $escape($title) ?>">
  <meta property="og:description" content="<?= $escape($description) ?>">
  <meta property="og:url" content="<?= $escape($canonical) ?>">
  <?php if ($imageAbsolute !== ''): ?><meta property="og:image" content="<?= $escape($imageAbsolute) ?>"><?php endif; ?>
  <meta name="twitter:card" content="<?= $imageAbsolute !== '' ? 'summary_large_image' : 'summary' ?>">
  <?= site_favicon_tags() ?><?= site_json_ld($schema) ?><?= site_json_ld($breadcrumbSchema) ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <link rel="stylesheet" href="/assets/css/pages/doctor-profile.css?v=<?= filemtime(__DIR__ . '/assets/css/pages/doctor-profile.css') ?>">
  <script src="/assets/js/doctor-profile.js?v=<?= filemtime(__DIR__ . '/assets/js/doctor-profile.js') ?>" defer></script>
</head>
<body class="doctor-profile-page">
<?php include __DIR__ . '/Tem/header.php'; ?>
<?php require __DIR__ . '/Tem/doctor-profile.php'; ?>
<?php include __DIR__ . '/Tem/footer.php'; ?>
</body>
</html>
