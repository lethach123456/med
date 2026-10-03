<?php
declare(strict_types=1);
// Typed cards keep facility data separate from doctor data within a shared ranking.
$tdEnglish = $toplistLanguage === 'en';
$tdMixed = $toplistEntityType === 'mixed';
$tdLabels = $tdEnglish ? [
    'home' => 'Home', 'article' => 'Doctor Toplist', 'updated' => 'Updated', 'count' => 'doctors',
    'list' => 'Doctors in this list', 'profile' => 'View doctor profile', 'workplace' => 'Practice location',
    'city' => 'City', 'specialty' => 'Specialty', 'verified' => 'Verified', 'reviews' => 'reviews',
    'toc' => 'Quick comparison', 'empty' => 'Doctor profiles are being added to this list.',
    'note' => 'This list is a starting point for research, not a guarantee of treatment quality. Please confirm credentials and availability directly with the provider.',
] : [
    'home' => 'Trang chủ', 'article' => 'Toplist bác sĩ', 'updated' => 'Cập nhật', 'count' => 'bác sĩ',
    'list' => 'Bác sĩ trong danh sách', 'profile' => 'Xem hồ sơ bác sĩ', 'workplace' => 'Nơi công tác',
    'city' => 'Thành phố', 'specialty' => 'Chuyên khoa', 'verified' => 'Đã xác thực', 'reviews' => 'đánh giá',
    'toc' => 'So sánh nhanh', 'empty' => 'Danh sách đang được bổ sung hồ sơ bác sĩ.',
    'note' => 'Danh sách là thông tin tham khảo, không phải cam kết chất lượng điều trị. Hãy đối chiếu bằng cấp và lịch khám trực tiếp với nơi công tác.',
];
if ($tdMixed) {
    $tdLabels['article'] = $tdEnglish ? 'Healthcare Toplist' : 'Toplist y tế';
    $tdLabels['count'] = $tdEnglish ? 'providers' : 'hồ sơ';
    $tdLabels['list'] = $tdEnglish ? 'Facilities & doctors in this list' : 'Cơ sở y tế & bác sĩ trong danh sách';
    $tdLabels['empty'] = $tdEnglish ? 'Provider profiles are being added to this list.' : 'Danh sách đang được bổ sung cơ sở y tế và bác sĩ.';
}
$tdLabels['category'] = $tdEnglish ? 'Provider type' : 'Nhóm cơ sở';
$tdLabels['address'] = $tdEnglish ? 'Address' : 'Địa chỉ';
$tdEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$tdSchema = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $title,
    'numberOfItems' => count($linkedRows), 'itemListElement' => []];
foreach ($linkedRows as $tdRow) {
    $tdSchema['itemListElement'][] = ['@type' => 'ListItem', 'position' => (int) $tdRow['rank_order'],
        'item' => ['@type' => $tdRow['member_type'] === 'doctor' ? 'Physician' : 'MedicalClinic', 'name' => $tdRow['name'],
            'url' => site_absolute_url(medical_public_entity_path($tdRow['member_type'], (string) $tdRow['slug'], $toplistLanguage))]];
}
?>
<!doctype html>
<html lang="<?= $tdEscape($toplistLanguage) ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $tdEscape($title) ?> | MedReview</title>
  <meta name="description" content="<?= $tdEscape($description) ?>">
  <link rel="canonical" href="<?= $tdEscape($canonicalUrl) ?>">
  <?php if (!empty($toplistLanguageLinks['has_counterpart'])): foreach (['vi', 'en'] as $tdLanguage): ?>
    <link rel="alternate" hreflang="<?= $tdLanguage ?>" href="<?= $tdEscape(site_absolute_url($toplistLanguageLinks[$tdLanguage])) ?>">
  <?php endforeach; endif; ?>
  <meta property="og:type" content="article"><meta property="og:title" content="<?= $tdEscape($title) ?>">
  <meta property="og:description" content="<?= $tdEscape($description) ?>"><meta property="og:url" content="<?= $tdEscape($canonicalUrl) ?>">
  <?php if ($heroImage !== ''): ?><meta property="og:image" content="<?= $tdEscape($heroImage) ?>"><?php endif; ?>
  <?= site_favicon_tags() ?><?= site_json_ld($toplistSchema) ?><?= site_json_ld($tdSchema) ?>
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <link rel="stylesheet" href="/assets/css/pages/toplist-doctors.css?v=<?= filemtime(__DIR__ . '/../assets/css/pages/toplist-doctors.css') ?>">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="doctor-toplist site-typo">
  <div class="td-container">
    <nav class="td-breadcrumb" aria-label="Breadcrumb"><a href="<?= $tdEscape(site_localized_path('/', $toplistLanguage)) ?>"><?= $tdEscape($tdLabels['home']) ?></a><span>›</span><a href="<?= $tdEscape(medical_public_entity_path('toplist', '', $toplistLanguage)) ?>">Toplist</a></nav>
    <section class="td-hero<?= $heroImage === '' ? ' td-no-image' : '' ?>">
      <div><span class="td-eyebrow"><?= $tdEscape($tdLabels['article']) ?></span><h1><?= $tdEscape($title) ?></h1><p><?= $tdEscape($description) ?></p>
        <div class="td-meta"><span><?= $tdEscape($tdLabels['updated']) ?> <?= $tdEscape(date('d/m/Y', strtotime((string) $toplist['updated_at']))) ?></span><span><?= count($linkedRows) ?> <?= $tdEscape($tdLabels['count']) ?></span></div>
      </div>
      <?php if ($heroImage !== ''): ?><img class="td-hero-image" src="<?= $tdEscape($heroImage) ?>" alt="<?= $tdEscape($title) ?>" width="640" height="480" fetchpriority="high" decoding="async"><?php endif; ?>
    </section>
    <div class="td-layout">
      <div class="td-main">
        <?php if (trim((string) ($toplist['content'] ?? '')) !== ''): ?><article class="td-intro"><?= $toplist['content'] ?></article><?php endif; ?>
        <h2 class="td-list-title"><?= $tdEscape($tdLabels['list']) ?></h2>
        <?php if ($linkedRows === []): ?><p><?= $tdEscape($tdLabels['empty']) ?></p><?php endif; ?>
        <?php foreach ($linkedRows as $tdDoctor):
            $tdIsDoctor = $tdDoctor['member_type'] === 'doctor';
            $tdImage = site_absolute_media_url((string) ($tdDoctor['image_url'] ?? ''));
            $tdProfile = medical_public_entity_path($tdDoctor['member_type'], (string) $tdDoctor['slug'], $toplistLanguage);
            $tdKind = $tdIsDoctor ? ($tdEnglish ? 'Doctor' : 'Bác sĩ') : ($tdEnglish ? 'Medical facility' : 'Cơ sở y tế');
            $tdProfileLabel = $tdIsDoctor ? $tdLabels['profile'] : ($tdEnglish ? 'View facility profile' : 'Xem hồ sơ cơ sở');
            $tdBio = trim((string) ($tdDoctor['subtitle'] ?? ''));
            if ($tdBio === '') $tdBio = trim(strip_tags((string) ($tdDoctor['content'] ?? '')));
            $tdBio = site_meta_description($tdBio, 260);
        ?>
          <article class="td-doctor" id="rank-<?= (int) $tdDoctor['rank_order'] ?>">
            <div class="td-doctor-heading">
              <span class="td-rank"><?= str_pad((string) $tdDoctor['rank_order'], 2, '0', STR_PAD_LEFT) ?></span>
              <?php if ($tdImage !== ''): ?><img class="td-portrait" src="<?= $tdEscape($tdImage) ?>" alt="<?= $tdEscape($tdDoctor['name']) ?>" width="120" height="120" loading="lazy" decoding="async"><?php endif; ?>
              <div><span class="td-kind"><?= $tdEscape($tdKind) ?></span><h2><a href="<?= $tdEscape($tdProfile) ?>"><?= $tdEscape($tdDoctor['name']) ?></a></h2>
                <?php if (trim((string) ($tdDoctor['title_text'] ?? '')) !== ''): ?><p><?= $tdEscape($tdDoctor['title_text']) ?></p><?php endif; ?>
                <?php if ((int) ($tdDoctor['verified'] ?? 0) === 1): ?><span class="td-verified"><?= $tdEscape($tdLabels['verified']) ?></span><?php endif; ?>
              </div>
            </div>
            <dl class="td-facts">
              <?php foreach ($tdIsDoctor ? ['specialty_text' => 'specialty', 'facility_name' => 'workplace', 'city' => 'city'] : ['category' => 'category', 'city' => 'city', 'address_text' => 'address'] as $tdField => $tdLabel): if (trim((string) ($tdDoctor[$tdField] ?? '')) !== ''): ?>
                <div><dt><?= $tdEscape($tdLabels[$tdLabel]) ?></dt><dd><?= $tdEscape($tdDoctor[$tdField]) ?></dd></div>
              <?php endif; endforeach; ?>
            </dl>
            <?php if ($tdBio !== ''): ?><p class="td-bio"><?= $tdEscape($tdBio) ?></p><?php endif; ?>
            <div class="td-doctor-bottom">
              <?php if ((int) ($tdDoctor['reviews_count'] ?? 0) > 0 && (float) ($tdDoctor['rating'] ?? 0) > 0): ?><span>★ <?= number_format((float) $tdDoctor['rating'], 1) ?>/5 · <?= (int) $tdDoctor['reviews_count'] ?> <?= $tdEscape($tdLabels['reviews']) ?></span><?php endif; ?>
              <a class="td-profile-link" href="<?= $tdEscape($tdProfile) ?>"><?= $tdEscape($tdProfileLabel) ?> <span aria-hidden="true">→</span></a>
            </div>
          </article>
        <?php endforeach; ?>
        <p class="td-disclaimer"><?= $tdEscape($tdLabels['note']) ?></p>
      </div>
      <?php if ($linkedRows !== []): ?><aside class="td-sidebar"><h2><?= $tdEscape($tdLabels['toc']) ?></h2><nav>
        <?php foreach ($linkedRows as $tdDoctor): ?><a href="#rank-<?= (int) $tdDoctor['rank_order'] ?>"><span class="td-mini-rank"><?= (int) $tdDoctor['rank_order'] ?></span><span><strong><?= $tdEscape($tdDoctor['name']) ?></strong><small><?= $tdEscape($tdDoctor['member_type'] === 'doctor' ? ($tdDoctor['specialty_text'] ?? 'Bác sĩ') : ($tdDoctor['category'] ?? 'Cơ sở y tế')) ?></small></span></a><?php endforeach; ?>
      </nav></aside><?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body></html>
