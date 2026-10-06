<?php
$e = 'toplist_view_escape';
$toplistCopy = toplist_view_copy($locale);
$toplistFilters = toplist_view_filters($_GET);
$toplistPath = site_localized_path(medical_public_toplist_path(), $locale);
$typeCounts = ['facility'=>0,'doctor'=>0,'mixed'=>0];
$totalMembers = 0;
$visibleCount = 0;
foreach ($rows as $row) {
    $type = $row['entity_type'] ?? 'facility';
    if (isset($typeCounts[$type])) $typeCounts[$type]++;
    $totalMembers += max(0, (int) ($row['member_count'] ?? 0));
    if (toplist_view_matches($row, $toplistFilters)) $visibleCount++;
}
?>
<main class="tl-directory site-typo" data-locale="<?= $e($locale) ?>">
  <div class="tl-container">
    <nav class="tl-breadcrumb" aria-label="<?= $locale === 'en' ? 'Breadcrumb' : 'Đường dẫn' ?>"><a href="<?= $e(site_localized_path('/', $locale)) ?>"><?= $e($toplistCopy['home']) ?></a><i class="ph ph-caret-right" aria-hidden="true"></i><span aria-current="page">Toplist</span></nav>
    <header class="tl-hero">
      <div class="tl-hero-copy"><span class="tl-kicker"><span aria-hidden="true"></span><?= $e($toplistCopy['kicker']) ?></span><h1><?= $e($toplistCopy['title']) ?><br><em><?= $e($toplistCopy['titleAccent']) ?></em></h1><p><?= $e($toplistCopy['intro']) ?></p></div>
      <div class="tl-hero-note"><span class="tl-note-float" aria-hidden="true"><i class="ph ph-sparkle"></i></span><span class="tl-note-icon" aria-hidden="true"><i class="ph ph-list-checks"></i></span><p><?= $toplistCopy['note'] ?></p><div class="tl-stats"><div><strong><?= count($rows) ?></strong><span><?= $e($toplistCopy['articles']) ?></span></div><div><strong><?= $totalMembers ?></strong><span><?= $e($toplistCopy['profiles']) ?></span></div></div></div>
      <span class="tl-hero-decoration" aria-hidden="true"><i class="ph ph-stack"></i></span>
    </header>
    <section class="tl-search-area" aria-label="<?= $e($toplistCopy['search']) ?>">
      <form class="tl-search" id="toplistSearchForm" action="<?= $e($toplistPath) ?>" method="get" role="search"><label for="toplistSearch"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><span class="tl-sr-only"><?= $e($toplistCopy['search']) ?></span></label><input type="search" id="toplistSearch" name="q" value="<?= $e($toplistFilters['q']) ?>" placeholder="<?= $e($toplistCopy['placeholder']) ?>" maxlength="120" autocomplete="off" enterkeyhint="search"><input type="hidden" id="toplistType" name="type" value="<?= $e($toplistFilters['type']) ?>"><button type="submit" aria-label="<?= $e($toplistCopy['search']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-6.8 18-3.5-7.7L3 9.8 21 3Z"/><path d="m10.7 13.3 5.5-5.5"/></svg></button></form>
      <nav class="tl-types" aria-label="<?= $locale === 'en' ? 'Browse lists by profile type' : 'Khám phá theo loại danh sách' ?>"><?php foreach ([''=>'squares-four','facility'=>'hospital','doctor'=>'stethoscope','mixed'=>'intersect'] as $type=>$icon): ?><a href="<?= $e(toplist_view_page_url($toplistPath, array_replace($toplistFilters,['type'=>$type]))) ?>" data-toplist-type="<?= $type ?>"<?= $toplistFilters['type'] === $type ? ' aria-current="true"' : '' ?>><i class="ph ph-<?= $icon ?>" aria-hidden="true"></i><?= $e($toplistCopy[$type ?: 'all']) ?><span><?= $type === '' ? count($rows) : $typeCounts[$type] ?></span></a><?php endforeach; ?></nav>
    </section>
    <div class="tl-layout">
      <aside class="tl-sidebar"><section class="tl-explore"><div class="tl-explore-art" aria-hidden="true"><span class="tl-art-orbit"></span><span class="tl-art-stack"><i class="ph ph-cards"></i></span><span class="tl-art-heart"><i class="ph-fill ph-heart"></i></span><span class="tl-art-check"><i class="ph ph-check"></i></span></div><span class="tl-side-kicker"><?= $e($toplistCopy['sideKicker']) ?></span><h2><?= $toplistCopy['sideTitle'] ?></h2><p><?= $e($toplistCopy['sideCopy']) ?></p><a href="<?= $e(site_localized_path(medical_public_facility_path(),$locale)) ?>"><i class="ph ph-hospital" aria-hidden="true"></i><?= $e($toplistCopy['browseFacilities']) ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a><a href="<?= $e(site_localized_path('/bac-si',$locale)) ?>"><i class="ph ph-stethoscope" aria-hidden="true"></i><?= $e($toplistCopy['browseDoctors']) ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a></section>
        <section class="tl-guide"><span class="tl-guide-icon" aria-hidden="true"><i class="ph ph-lightbulb"></i></span><h2><?= $toplistCopy['guideTitle'] ?></h2><ul><?php foreach (['guideOne'=>'identification-card','guideTwo'=>'chat-circle-text','guideThree'=>'phone'] as $key=>$icon): ?><li><i class="ph ph-<?= $icon ?>" aria-hidden="true"></i><?= $e($toplistCopy[$key]) ?></li><?php endforeach; ?></ul><a href="<?= $e(site_localized_path('/ve-chung-toi.php',$locale)) ?>"><?= $e($toplistCopy['about']) ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a></section>
      </aside>
      <section class="tl-results" aria-labelledby="toplistResultsHeading"><div class="tl-results-top"><div><h2 id="toplistResultsHeading"><?= $e($toplistCopy['resultTitle']) ?></h2><p id="toplistResultCount"><strong><?= $visibleCount ?></strong> <?= $e($toplistCopy['matches']) ?></p></div><div class="tl-sort"><label for="toplistSort"><?= $e($toplistCopy['sort']) ?></label><select id="toplistSort" name="sort" form="toplistSearchForm"><?php foreach (['updated'=>'updated','members'=>'members','title'=>'titleSort'] as $value=>$key): ?><option value="<?= $value ?>"<?= $toplistFilters['sort'] === $value ? ' selected' : '' ?>><?= $e($toplistCopy[$key]) ?></option><?php endforeach; ?></select></div></div>
        <p class="tl-sr-only" id="toplistStatus" role="status" aria-live="polite" aria-atomic="true"></p>
        <div class="tl-grid" id="toplistList"><?php foreach (toplist_view_sort($rows,$toplistFilters['sort']) as $row) echo toplist_view_card($row,$locale,toplist_view_matches($row,$toplistFilters)); ?></div>
        <div class="tl-empty" id="toplistEmpty"<?= $visibleCount > 0 ? ' hidden' : '' ?>><i class="ph ph-magnifying-glass" aria-hidden="true"></i><h3><?= $e($toplistCopy['emptyTitle']) ?></h3><p><?= $e($toplistCopy['emptyCopy']) ?></p><a class="tl-button" href="<?= $e($toplistPath) ?>" data-toplist-clear><?= $e($toplistCopy['clear']) ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
        <p class="tl-disclaimer"><i class="ph ph-info" aria-hidden="true"></i><?= $e($toplistCopy['disclaimer']) ?></p>
      </section>
    </div>
  </div>
</main>
