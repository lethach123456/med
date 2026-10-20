<?php
declare(strict_types=1);
$tdEscape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tdIcon = 'medical_toplist_detail_icon';
$tdIntro = medical_toplist_detail_html((string) ($toplist['content'] ?? ''));
$tdHasIntro = trim(strip_tags($tdIntro)) !== '';
$tdDate = medical_doctor_profile_date($toplist['updated_at'] ?? '');
// Only an explicitly selected article image is a banner, not the first doctor's portrait.
$tdHero = medical_doctor_profile_url($toplist['featured_image_url'] ?? '', true);
$tdGalleryData = [];
foreach ($tdMembers as $member) {
    $tdGalleryData[(string) $member['rank']] = ['name' => $member['name'], 'images' => $member['gallery']];
}
$tdSummaryMembers = array_slice(array_values(array_filter($tdMembers, static fn(array $member): bool => $member['image'] !== '')), 0, 3);
$tdFactIcons = ['specialty' => 'specialty', 'workplace' => 'building', 'address' => 'pin', 'hours' => 'calendar', 'phone' => 'phone', 'website' => 'globe'];
?>
<main id="td-main" class="doctor-toplist site-typo" data-locale="<?= $tdEscape($toplistLanguage) ?>">
  <div class="td-container">
    <nav class="td-breadcrumb" aria-label="<?= $toplistLanguage === 'en' ? 'Breadcrumb' : 'Đường dẫn' ?>">
      <a href="<?= $tdEscape(site_localized_path('/', $toplistLanguage)) ?>"><?= $tdEscape($tdLabels['home']) ?></a>
      <span aria-hidden="true">›</span>
      <a href="<?= $tdEscape(medical_public_entity_path('toplist', '', $toplistLanguage)) ?>">Toplist</a>
      <span aria-hidden="true">›</span><span aria-current="page"><?= $tdEscape($title) ?></span>
    </nav>

    <header class="td-hero<?= $tdHero === '' ? ' td-no-image' : '' ?>">
      <div class="td-hero-copy">
        <span class="td-eyebrow"><?= $tdIcon('list') ?><?= $tdEscape($tdLabels['article']) ?></span>
        <h1><?= $tdEscape($title) ?></h1>
        <?php if ($description !== ''): ?><p><?= $tdEscape($description) ?></p><?php endif; ?>
        <div class="td-meta">
          <?php if ($tdDate !== ''): ?>
          <span><?= $tdIcon('calendar') ?><?= $tdEscape($tdLabels['updated']) ?> <time datetime="<?= $tdEscape(substr((string) $toplist['updated_at'], 0, 10)) ?>"><?= $tdEscape($tdDate) ?></time></span>
          <?php endif; ?>
          <span><?= $tdIcon('person') ?><?= count($tdMembers) ?> <?= $tdEscape($tdLabels['count']) ?></span>
        </div>
        <?php if ($tdMembers !== []): ?>
        <div class="td-hero-actions">
          <a class="td-jump-primary" href="#toplist-members"><?= $tdEscape($tdLabels['jumpList']) ?><?= $tdIcon('arrow') ?></a>
          <a class="td-jump-secondary" href="#toplist-comparison"><?= $tdIcon('list') ?><?= $tdEscape($tdLabels['compare']) ?></a>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($tdHero !== ''): ?>
      <div class="td-hero-image"><img src="<?= $tdEscape($tdHero) ?>" alt="<?= $tdEscape($title) ?>" width="640" height="480" fetchpriority="high" decoding="async" data-td-image></div>
      <?php elseif ($tdMembers !== []): ?>
      <div class="td-hero-summary">
        <span class="td-summary-label"><?= $tdEscape($tdLabels['overview']) ?></span>
        <strong><?= count($tdMembers) ?><span><?= $tdEscape($tdLabels['count']) ?></span></strong>
        <?php if ($tdSummaryMembers !== []): ?>
        <div class="td-summary-avatars" aria-hidden="true">
          <?php foreach ($tdSummaryMembers as $summaryMember): ?>
          <img src="<?= $tdEscape($summaryMember['image']) ?>" alt="" width="48" height="48" decoding="async" data-td-image>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <p><?= $tdEscape($tdLabels['overviewCopy']) ?></p>
      </div>
      <?php endif; ?>
    </header>

    <?php if ($tdMembers !== []): ?>
    <details class="td-mobile-toc">
      <summary><?= $tdIcon('list') ?><?= $tdEscape($tdLabels['guide']) ?><?= $tdIcon('chevron') ?></summary>
      <nav class="td-toc" aria-label="<?= $tdEscape($tdLabels['toc']) ?>">
        <?php if ($tdHasIntro): ?><a href="#toplist-intro"><?= $tdEscape($tdLabels['intro']) ?></a><?php endif; ?>
        <a href="#toplist-comparison"><?= $tdEscape($tdLabels['compare']) ?></a>
        <?php foreach ($tdMembers as $member): ?>
        <a href="#rank-<?= $member['rank'] ?>"><span class="td-mini-rank"><?= $member['rank'] ?></span><?= $tdEscape($member['name']) ?></a>
        <?php endforeach; ?>
      </nav>
    </details>
    <?php endif; ?>

    <div class="td-layout">
      <div class="td-main">
        <?php if ($tdHasIntro): ?>
        <article class="td-intro" id="toplist-intro" aria-labelledby="tdIntroHeading">
          <h2 class="td-block-title" id="tdIntroHeading"><?= $tdEscape($tdLabels['intro']) ?></h2>
          <div class="td-prose"><?= $tdIntro ?></div>
        </article>
        <?php endif; ?>

        <?php if ($tdMembers !== []): ?>
        <section class="td-comparison" id="toplist-comparison" aria-labelledby="tdComparisonHeading">
          <h2 id="tdComparisonHeading"><?= $tdEscape($tdLabels['compare']) ?></h2>
          <p><?= $tdEscape($tdLabels['compareCopy']) ?></p>
          <details class="td-comparison-disclosure">
            <summary class="td-comparison-toggle"><span><?= $tdEscape($tdLabels['comparisonToggle']) ?></span><span><?= count($tdMembers) ?> <?= $tdEscape($tdLabels['count']) ?></span><?= $tdIcon('chevron') ?></summary>
            <div class="td-comparison-desktop">
              <div class="td-table-scroll" tabindex="0" role="region" aria-label="<?= $tdEscape($tdLabels['compare']) ?>">
                <table>
                  <caption class="td-sr-only"><?= $tdEscape($tdLabels['compareCopy']) ?></caption>
                  <thead><tr><?php foreach (['name', 'specialty', 'workplace', 'price', 'rating'] as $label): ?><th scope="col"><?= $tdEscape($tdLabels[$label]) ?></th><?php endforeach; ?></tr></thead>
                  <tbody>
                    <?php foreach ($tdMembers as $member): ?>
                    <tr>
                      <th scope="row"><a href="#rank-<?= $member['rank'] ?>"><span class="td-mini-rank"><?= $member['rank'] ?></span><?= $tdEscape($member['name']) ?></a></th>
                      <td><?= $tdEscape($member['specialty'] ?: '—') ?></td>
                      <td><?= $tdEscape(($member['workplace'] ?: $member['city']) ?: '—') ?></td>
                      <td><?= $tdEscape($member['price'] ?: '—') ?></td>
                      <td><?php if ($member['has_rating']): ?><strong class="td-score"><?= number_format($member['rating'], 1) ?>/5</strong><small><?= $member['reviews'] ?> <?= $tdEscape($tdLabels['reviews']) ?></small><?php else: ?><?= $tdEscape($tdLabels['noReviews']) ?><?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <ul class="td-comparison-mobile">
              <?php foreach ($tdMembers as $member): ?>
              <li>
                <h3><a href="#rank-<?= $member['rank'] ?>"><span class="td-mini-rank"><?= $member['rank'] ?></span><?= $tdEscape($member['name']) ?><?= $tdIcon('arrow') ?></a></h3>
                <dl>
                  <div><dt><?= $tdEscape($tdLabels[$member['type'] === 'doctor' ? 'specialty' : 'category']) ?></dt><dd><?= $tdEscape($member['specialty'] ?: '—') ?></dd></div>
                  <div><dt><?= $tdEscape($tdLabels['workplace']) ?></dt><dd><?= $tdEscape(($member['workplace'] ?: $member['city']) ?: '—') ?></dd></div>
                  <div><dt><?= $tdEscape($tdLabels['price']) ?></dt><dd><?= $tdEscape($member['price'] ?: '—') ?></dd></div>
                  <div><dt><?= $tdEscape($tdLabels['rating']) ?></dt><dd><?php if ($member['has_rating']): ?><strong class="td-score"><?= number_format($member['rating'], 1) ?>/5</strong> · <?= $member['reviews'] ?> <?= $tdEscape($tdLabels['reviews']) ?><?php else: ?><?= $tdEscape($tdLabels['noReviews']) ?><?php endif; ?></dd></div>
                </dl>
              </li>
              <?php endforeach; ?>
            </ul>
          </details>
        </section>
        <?php endif; ?>

        <div class="td-section-heading" id="toplist-members"><h2><?= $tdEscape($tdLabels['list']) ?></h2><span><?= count($tdMembers) ?> <?= $tdEscape($tdLabels['count']) ?></span></div>
        <?php if ($tdMembers === []): ?><p class="td-empty"><?= $tdEscape($tdLabels['empty']) ?></p><?php endif; ?>

        <?php foreach ($tdMembers as $member): ?>
        <article class="td-doctor" id="rank-<?= $member['rank'] ?>" data-td-member="<?= $member['rank'] ?>" aria-labelledby="tdMember<?= $member['rank'] ?>">
          <header class="td-doctor-header">
            <div class="td-cover<?= $member['type'] === 'doctor' ? ' td-cover-portrait' : '' ?>">
              <?php if ($member['image'] !== ''): ?>
              <a href="<?= $tdEscape($member['image']) ?>" data-td-photo="0" aria-label="<?= $tdEscape($tdLabels['photos'] . ' · ' . $member['name']) ?>">
                <img src="<?= $tdEscape($member['image']) ?>" alt="<?= $tdEscape($member['gallery'][0]['alt'] ?: $member['name']) ?>" width="296" height="352" loading="lazy" decoding="async" data-td-image>
                <span class="td-photo-hint"><?= $tdIcon('photo') ?><?= count($member['gallery']) ?></span>
              </a>
              <?php else: ?>
              <div class="td-cover-placeholder" aria-hidden="true"><?= $tdIcon($member['type'] === 'doctor' ? 'person' : 'building') ?></div>
              <?php endif; ?>
            </div>
            <div class="td-doctor-identity">
              <div class="td-kind"><span class="td-inline-rank"><?= str_pad((string) $member['rank'], 2, '0', STR_PAD_LEFT) ?></span><?= $tdEscape($tdLabels[$member['type']]) ?><?php if ($member['city'] !== ''): ?><span>· <?= $tdEscape($member['city']) ?></span><?php endif; ?></div>
              <h2 id="tdMember<?= $member['rank'] ?>"><a href="<?= $tdEscape($member['profile_url']) ?>"><?= $tdEscape($member['name']) ?></a></h2>
              <?php if ($member['title'] !== ''): ?><p class="td-subtitle"><?= $tdEscape($member['title']) ?></p><?php endif; ?>
              <?php if ($member['verified']): ?>
                <?php if ($member['type'] === 'facility'): ?><a class="td-verified" href="<?= $tdEscape(site_localized_path('/ho-so-da-xac-thuc', $toplistLanguage)) ?>"><?= $tdIcon('check') ?><?= $tdEscape($tdLabels['verified']) ?></a>
                <?php else: ?><span class="td-verified"><?= $tdIcon('check') ?><?= $tdEscape($tdLabels['verified']) ?></span><?php endif; ?>
              <?php endif; ?>
              <p class="td-rating"><?php if ($member['has_rating']): ?><span class="td-star" aria-hidden="true">★</span><strong><?= number_format($member['rating'], 1) ?>/5</strong><span>· <?= $member['reviews'] ?> <?= $tdEscape($tdLabels['reviews']) ?></span><?php else: ?><?= $tdEscape($tdLabels['noReviews']) ?><?php endif; ?></p>
            </div>
          </header>

          <div class="td-doctor-body">
            <?php if ($member['tags'] !== []): ?>
            <div class="td-tags"><?php foreach ($member['tags'] as $tag): ?><span><?= $tdIcon('specialty') ?><?= $tdEscape($tag) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
            <?php
            $tdFacts = array_filter(
                ['specialty' => $member['type'] === 'doctor' ? 'specialty' : 'category', 'workplace' => 'workplace', 'address' => 'address', 'hours' => 'hours', 'phone' => 'phone', 'website' => 'website'],
                static fn(string $label, string $field): bool => $member[$field] !== '',
                ARRAY_FILTER_USE_BOTH
            );
            if ($tdFacts !== []): ?>
            <dl class="td-facts">
              <?php foreach ($tdFacts as $field => $label): ?>
              <div><dt><?= $tdIcon($tdFactIcons[$field]) ?><?= $tdEscape($tdLabels[$label]) ?></dt><dd>
                <?php if ($field === 'website'): ?><a href="<?= $tdEscape($member['website']) ?>" target="_blank" rel="noopener noreferrer"><?= $tdEscape(parse_url($member['website'], PHP_URL_HOST)) ?></a>
                <?php elseif ($field === 'phone' && $member['phone_url'] !== ''): ?><a href="<?= $tdEscape($member['phone_url']) ?>"><?= $tdEscape($member['phone']) ?></a>
                <?php else: ?><?= $tdEscape($member[$field]) ?><?php endif; ?>
              </dd></div>
              <?php endforeach; ?>
            </dl>
            <?php endif; ?>
            <?php if (trim(strip_tags($member['content'])) !== ''): ?><div class="td-article-content"><?= $member['content'] ?></div><?php endif; ?>

            <?php if ($member['fees'] !== []): ?>
            <section class="td-fees">
              <h3><?= $tdEscape($tdLabels['fees']) ?></h3>
              <div class="td-fee-table"><table><thead><tr><th scope="col"><?= $tdEscape($tdLabels['service']) ?></th><th scope="col"><?= $tdEscape($tdLabels['fee']) ?></th></tr></thead><tbody>
                <?php foreach ($member['fees'] as $fee): ?><tr><th scope="row"><?= $tdEscape($fee['service']) ?></th><td><?= $tdEscape(medical_doctor_profile_fee($fee, $toplistLanguage === 'en')) ?></td></tr><?php endforeach; ?>
              </tbody></table></div>
              <p><?= $tdEscape($tdLabels['feeNote']) ?></p>
            </section>
            <?php endif; ?>
            <?php if ($member['sources'] !== []): ?>
            <details class="td-sources"><summary><?= $tdEscape($tdLabels['sources']) ?> <span><?= count($member['sources']) ?></span><?= $tdIcon('chevron') ?></summary><ul>
              <?php foreach ($member['sources'] as $source): ?><li><a href="<?= $tdEscape($source['url']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $tdEscape($source['title']) ?></a></li><?php endforeach; ?>
            </ul></details>
            <?php endif; ?>

            <?php if (count($member['gallery']) > 1): ?>
            <details class="td-photo-disclosure">
              <summary class="td-photo-toggle"><?= $tdIcon('photo') ?><span><?= $tdEscape($tdLabels['gallery']) ?></span><span><?= count($member['gallery']) ?></span><?= $tdIcon('chevron') ?></summary>
              <div class="td-gallery" aria-label="<?= $tdEscape($tdLabels['gallery'] . ' · ' . $member['name']) ?>">
                <?php foreach (array_slice($member['gallery'], 1, 4, true) as $index => $photo): ?>
                <a href="<?= $tdEscape($photo['url']) ?>" data-td-photo="<?= $index ?>" aria-label="<?= $tdEscape($tdLabels['photo'] . ' ' . ($index + 1) . ' · ' . $member['name']) ?>">
                  <img src="<?= $tdEscape($photo['url']) ?>" alt="<?= $tdEscape($photo['alt'] ?: $photo['caption'] ?: $member['name']) ?>" width="320" height="220" loading="lazy" decoding="async" data-td-image>
                  <?php if ($index === 4 && count($member['gallery']) > 5): ?><span class="td-gallery-more">+<?= count($member['gallery']) - 5 ?> <?= $tdEscape($tdLabels['morePhotos']) ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
              </div>
            </details>
            <?php endif; ?>
            <div class="td-doctor-bottom">
              <?php if ($member['price'] !== '' && $member['fees'] === []): ?><span><?= $tdEscape($tdLabels['price']) ?>: <?= $tdEscape($member['price']) ?></span><?php endif; ?>
              <a class="td-profile-link" href="<?= $tdEscape($member['profile_url']) ?>"><?= $tdEscape($member['type'] === 'doctor' ? $tdLabels['profile'] : $tdLabels['facilityProfile']) ?><?= $tdIcon('arrow') ?></a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
        <p class="td-disclaimer"><?= $tdIcon('info') ?><?= $tdEscape($tdLabels['note']) ?></p>
      </div>

      <?php if ($tdMembers !== []): ?>
      <aside class="td-sidebar">
        <section class="td-side">
          <h2><?= $tdEscape($tdLabels['guide']) ?></h2>
          <nav class="td-toc" aria-label="<?= $tdEscape($tdLabels['toc']) ?>">
            <?php if ($tdHasIntro): ?><a href="#toplist-intro"><?= $tdEscape($tdLabels['intro']) ?></a><?php endif; ?>
            <a href="#toplist-comparison"><?= $tdEscape($tdLabels['compare']) ?></a>
            <?php foreach ($tdMembers as $member): ?><a href="#rank-<?= $member['rank'] ?>"><span class="td-mini-rank"><?= $member['rank'] ?></span><?= $tdEscape($member['name']) ?></a><?php endforeach; ?>
          </nav>
        </section>
        <div class="td-reading-note"><?= $tdIcon('info') ?><h2><?= $tdEscape($tdLabels['sideNoteTitle']) ?></h2><p><?= $tdEscape($tdLabels['sideNote']) ?></p></div>
      </aside>
      <?php endif; ?>
    </div>
  </div>

  <script type="application/json" id="tdGalleryData"><?= json_encode(['galleries' => $tdGalleryData, 'labels' => $tdLabels], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <dialog class="td-lightbox" aria-label="<?= $tdEscape($tdLabels['gallery']) ?>">
    <span class="td-viewer-count"></span>
    <button type="button" class="td-viewer-close" aria-label="<?= $tdEscape($tdLabels['close']) ?>"><?= $tdIcon('close') ?></button>
    <button type="button" class="td-viewer-prev" aria-label="<?= $tdEscape($tdLabels['previous']) ?>"><?= $tdIcon('left') ?></button>
    <div class="td-viewer-stage"><img class="td-viewer-image" alt=""><p class="td-viewer-error" role="status" hidden><?= $tdEscape($tdLabels['photoError']) ?></p></div>
    <button type="button" class="td-viewer-next" aria-label="<?= $tdEscape($tdLabels['next']) ?>"><?= $tdIcon('right') ?></button>
    <p class="td-viewer-caption"></p>
  </dialog>
</main>
