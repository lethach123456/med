<?php
$e = 'handbook_view_escape';
$handbookPath = handbook_view_path($locale);
$handbookSearchActive = $handbookFilters['q'] !== '';
?>
<main class="hb-directory site-typo" id="main-content">
  <div class="hb-container">
    <nav class="hb-breadcrumb" aria-label="<?= $locale === 'en' ? 'Breadcrumb' : 'Đường dẫn' ?>"><a href="<?= $e(site_localized_path('/', $locale)) ?>"><?= $e($handbookCopy['home']) ?></a><i class="ph ph-caret-right" aria-hidden="true"></i><span aria-current="page"><?= $e($handbookCopy['name']) ?></span></nav>
    <header class="hb-hero">
      <div class="hb-hero-copy"><p class="hb-kicker"><span aria-hidden="true"></span><?= $e($handbookCopy['kicker']) ?></p><h1><?= $e($handbookCopy['title']) ?><br><em><?= $e($handbookCopy['accent']) ?></em></h1><p class="hb-intro"><?= $e($handbookCopy['intro']) ?></p></div>
      <div class="hb-hero-mark" aria-hidden="true"><i class="ph ph-book-open-text"></i><span class="hb-mark-plus">+</span><span class="hb-mark-dot"></span></div>
    </header>
    <section class="hb-search-area" aria-labelledby="handbookSearchLabel">
      <form class="hb-search" action="<?= $e($handbookPath) ?>" method="get" role="search">
        <label id="handbookSearchLabel" for="handbookSearch"><?= $e($handbookCopy['search']) ?></label>
        <div class="hb-search-control"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><input id="handbookSearch" type="search" name="q" value="<?= $e($handbookFilters['q']) ?>" placeholder="<?= $e($handbookCopy['placeholder']) ?>" maxlength="120" enterkeyhint="search"><button type="submit"><?= $e($handbookCopy['searchButton']) ?><i class="ph ph-arrow-right" aria-hidden="true"></i></button></div>
      </form>
      <?php if ($handbookSearchActive): ?><p class="hb-active-search"><?= $e($handbookCopy['query']) ?> <strong><?= $e($handbookFilters['q']) ?></strong><a href="<?= $e($handbookPath) ?>"><?= $e($handbookCopy['clear']) ?><i class="ph ph-x" aria-hidden="true"></i></a></p><?php endif; ?>
    </section>
    <section class="hb-results" aria-labelledby="handbookResultsHeading">
      <div class="hb-results-header"><div><h2 id="handbookResultsHeading"><?= $e($handbookSearchActive ? $handbookCopy['searchResults'] : $handbookCopy['results']) ?></h2><?php if (!$handbookFailed): ?><p><strong><?= (int) $handbookPaging['total'] ?></strong> <?= $e($handbookSearchActive ? $handbookCopy['matches'] : $handbookCopy['published']) ?></p><?php endif; ?></div><?php if (!$handbookFailed && $handbookPaging['total'] > 0): ?><span class="hb-order"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i><?= $e($handbookCopy['order']) ?></span><?php endif; ?></div>
      <?php if ($handbookFailed): ?>
      <div class="hb-empty hb-error" role="status"><span class="hb-empty-icon" aria-hidden="true"><i class="ph ph-warning-circle"></i></span><h3><?= $e($handbookCopy['errorTitle']) ?></h3><p><?= $e($handbookCopy['errorCopy']) ?></p><a class="hb-button" href="<?= $e(handbook_view_page_url($locale, $handbookFilters)) ?>"><?= $e($handbookCopy['retry']) ?><i class="ph ph-arrow-clockwise" aria-hidden="true"></i></a></div>
      <?php elseif ($handbookPaging['items'] === []): ?>
      <div class="hb-empty"><span class="hb-empty-icon" aria-hidden="true"><i class="ph ph-<?= $handbookSearchActive ? 'magnifying-glass' : 'book-open-text' ?>"></i></span><h3><?= $e($handbookCopy[$handbookSearchActive ? 'noMatchTitle' : 'emptyTitle']) ?></h3><p><?= $e($handbookCopy[$handbookSearchActive ? 'noMatchCopy' : 'emptyCopy']) ?></p><?php if ($handbookSearchActive): ?><a class="hb-button" href="<?= $e($handbookPath) ?>"><?= $e($handbookCopy['clear']) ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a><?php endif; ?></div>
      <?php else: ?>
      <div class="hb-grid"><?php foreach ($handbookPaging['items'] as $row) echo handbook_view_card($row, $locale); ?></div>
      <?= handbook_view_pagination($locale, $handbookFilters, $handbookPaging) ?>
      <?php endif; ?>
    </section>
    <aside class="hb-note"><span aria-hidden="true"><i class="ph ph-info"></i></span><div><h2><?= $e($handbookCopy['noteTitle']) ?></h2><p><?= $e($handbookCopy['noteCopy']) ?></p></div></aside>
  </div>
</main>
