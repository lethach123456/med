<?php
declare(strict_types=1);
$raText = static fn(string $vi, string $en): string => review_articles_view_text($vi, $en, $locale);
$reviewArticleUpdated = review_articles_view_date($reviewArticle['updated_at'] ?? $reviewArticle['published_at'] ?? '', $locale);
$reviewArticleStrengths = review_articles_view_list($reviewArticle['strengths_json'] ?? []);
$reviewArticleConsiderations = review_articles_view_list($reviewArticle['considerations_json'] ?? []);
$reviewArticleSuitable = review_articles_view_list($reviewArticle['suitable_for_json'] ?? []);
$reviewArticleVerdict = review_articles_view_string($reviewArticle['verdict'] ?? '');
$reviewArticleFacilityUrl = review_articles_view_safe_url($reviewArticleFacility['url'] ?? '');
$reviewArticleCustomerImages = $reviewArticleCustomerImages ?? [];
?>
<main class="ra-page ra-article site-typo" id="main-content" data-review-articles data-locale="<?= $e($locale) ?>">
  <div class="ra-container">
    <nav class="ra-breadcrumb" aria-label="<?= $raText('Đường dẫn', 'Breadcrumb') ?>"><a href="<?= $isEnglish ? '/en' : '/' ?>"><?= $raText('Trang chủ', 'Home') ?></a><i class="ph ph-caret-right" aria-hidden="true"></i><a href="<?= $e(review_articles_view_path($locale)) ?>">Review</a><i class="ph ph-caret-right" aria-hidden="true"></i><span aria-current="page"><?= $raText('Bài review', 'Review article') ?></span></nav>
    <article>
      <header class="ra-article-header">
        <div class="ra-article-topline">
          <a class="ra-eyebrow" href="<?= $e(review_articles_view_path($locale)) ?>"><i class="ph ph-notebook" aria-hidden="true"></i><?= $raText('Review & đánh giá', 'Reviews & assessments') ?></a>
          <?php if (review_articles_view_string($reviewArticleFacility['category'] ?? '') !== '' || review_articles_view_string($reviewArticleFacility['city'] ?? '') !== ''): ?>
            <div class="ra-article-context">
              <?php if (review_articles_view_string($reviewArticleFacility['category'] ?? '') !== ''): ?><span><?= $e($reviewArticleFacility['category']) ?></span><?php endif; ?>
              <?php if (review_articles_view_string($reviewArticleFacility['city'] ?? '') !== ''): ?><span><i class="ph ph-map-pin" aria-hidden="true"></i><?= $e($reviewArticleFacility['city']) ?></span><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <h1><?= $e($reviewArticle['title']) ?></h1>
        <?php if (review_articles_view_string($reviewArticle['excerpt'] ?? '') !== ''): ?><p class="ra-article-lead"><?= $e($reviewArticle['excerpt']) ?></p><?php endif; ?>
        <div class="ra-article-meta">
          <span class="ra-article-author"><i class="ph ph-pen-nib" aria-hidden="true"></i>MedReview</span>
          <?php if ($reviewArticleUpdated !== []): ?><time datetime="<?= $e($reviewArticleUpdated['datetime']) ?>"><i class="ph ph-calendar-blank" aria-hidden="true"></i><?= $raText('Cập nhật', 'Updated') ?> <?= $e($reviewArticleUpdated['label']) ?></time><?php endif; ?>
          <span><i class="ph ph-clock" aria-hidden="true"></i><?= max(1, (int) ($reviewArticle['reading_minutes'] ?? 1)) ?> <?= $raText('phút đọc', 'min read') ?></span>
        </div>
      </header>
      <div class="ra-article-layout">
        <div class="ra-reading">
          <?php if ($reviewArticleImage !== ''): ?><figure class="ra-cover"><img <?= medical_media_image_attributes($reviewArticleImage, '(max-width:620px) calc(100vw - 70px), (max-width:900px) calc(100vw - 116px), 760px', [480, 768, 960, 1280], 960) ?> width="960" height="600" alt="<?= $e(review_articles_view_string($reviewArticleFacility['name'] ?? '') ?: $reviewArticle['title']) ?>" decoding="async" fetchpriority="high"></figure><?php endif; ?>
          <details class="ra-toc-mobile"><summary><?= $raText('Nội dung bài viết', 'In this article') ?><i class="ph ph-caret-down" aria-hidden="true"></i></summary><nav aria-label="<?= $raText('Mục lục bài viết', 'Article contents') ?>" data-review-article-toc><?= review_articles_view_toc_html($reviewArticleToc) ?></nav></details>
          <?php if ($reviewArticleVerdict !== ''): ?><section class="ra-reading-section ra-takeaways" id="review-overview" aria-labelledby="review-overview-title"><h2 id="review-overview-title"><i class="ph ph-note-pencil" aria-hidden="true"></i><?= $raText('Góc nhìn tổng quan', 'At a glance') ?></h2><p class="ra-verdict"><?= $e($reviewArticleVerdict) ?></p></section><?php endif; ?>
          <?php foreach ($reviewArticleSections as $reviewArticleSection): ?><section class="ra-reading-section" id="<?= $e($reviewArticleSection['id']) ?>" aria-labelledby="<?= $e($reviewArticleSection['id']) ?>-title"><h2 id="<?= $e($reviewArticleSection['id']) ?>-title"><?= $e($reviewArticleSection['title']) ?></h2><div class="ra-prose"><?= $reviewArticleSection['content_html'] ?></div></section><?php endforeach; ?>
          <?php if ($reviewArticleStrengths !== [] || $reviewArticleConsiderations !== []): ?><div class="ra-reading-section ra-observations" id="review-observations"><?php foreach ([['items' => $reviewArticleStrengths, 'class' => '', 'icon' => 'check-circle', 'title' => $raText('Điểm nổi bật', 'Notable strengths')], ['items' => $reviewArticleConsiderations, 'class' => ' ra-observation--considerations', 'icon' => 'info', 'title' => $raText('Điều cần cân nhắc', 'Things to consider')]] as $reviewArticleObservation): if ($reviewArticleObservation['items'] === []) continue; ?><section class="ra-observation<?= $reviewArticleObservation['class'] ?>"><h2><i class="ph ph-<?= $reviewArticleObservation['icon'] ?>" aria-hidden="true"></i><?= $e($reviewArticleObservation['title']) ?></h2><ul><?php foreach ($reviewArticleObservation['items'] as $reviewArticleObservationText): ?><li><?= $e($reviewArticleObservationText) ?></li><?php endforeach; ?></ul></section><?php endforeach; ?></div><?php endif; ?>
          <?php if ($reviewArticleSuitable !== []): ?><section class="ra-reading-section" id="review-suitability" aria-labelledby="review-suitability-title"><h2 id="review-suitability-title"><?= $raText('Phù hợp với nhu cầu nào?', 'Who may find it useful?') ?></h2><ul class="ra-suitable-list"><?php foreach ($reviewArticleSuitable as $reviewArticleSuitableText): ?><li><?= $e($reviewArticleSuitableText) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
          <?php require __DIR__ . "/review-article-customer-images.php"; ?>
          <p class="ra-editorial-note"><i class="ph ph-info" aria-hidden="true"></i><?= $raText('Bài viết giúp bạn tìm hiểu trước khi lựa chọn. Hãy xác nhận dịch vụ, chi phí và lịch hẹn với cơ sở; nội dung không thay thế tư vấn y khoa.', 'Use this article to learn more before choosing. Confirm services, costs and appointments with the facility; this content does not replace medical advice.') ?></p>
          <div class="ra-article-bottom"><a href="<?= $e(review_articles_view_path($locale)) ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><?= $raText('Tất cả bài review', 'All review articles') ?></a><button class="ra-button ra-button-secondary" type="button" data-review-article-share hidden><i class="ph ph-share-network" aria-hidden="true"></i><?= $raText('Chia sẻ', 'Share') ?></button></div><p class="ra-share-status" role="status" aria-live="polite" data-review-article-share-status></p>
        </div>
        <aside class="ra-article-side" aria-label="<?= $raText('Hồ sơ liên quan & mục lục', 'Related profile & contents') ?>">
          <?php if ($reviewArticleFacility !== []): ?><section class="ra-side-section"><div class="ra-facility-heading"><i class="ph ph-hospital" aria-hidden="true"></i><?= $raText('Review & Đánh giá cơ sở', 'Facility review & assessment') ?></div><strong class="ra-facility-name"><?= $e($reviewArticleFacility['name'] ?? '') ?></strong><?php if (review_articles_view_string($reviewArticleFacility['address_text'] ?? '') !== ''): ?><p class="ra-facility-location"><i class="ph ph-map-pin" aria-hidden="true"></i><span><?= $e($reviewArticleFacility['address_text']) ?></span></p><?php endif; ?>
            <?php if ($reviewArticleFacilityUrl !== ''): ?><a class="ra-button" href="<?= $e($reviewArticleFacilityUrl) ?>"><?= $raText('Xem hồ sơ cơ sở', 'View facility profile') ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a><?php endif; ?>
          </section><?php endif; ?>
          <section class="ra-side-section ra-side-toc"><h2 class="ra-toc-heading"><?= $raText('Trong bài viết này', 'In this article') ?></h2><nav aria-label="<?= $raText('Mục lục bài viết', 'Article contents') ?>" data-review-article-toc><?= review_articles_view_toc_html($reviewArticleToc) ?></nav></section>
        </aside>
      </div>
    </article>
    <?php if ($reviewArticleRelated !== []): ?><section class="ra-related" aria-labelledby="review-related-title"><h2 id="review-related-title"><?= $raText('Đọc thêm góc nhìn khác', 'Explore more perspectives') ?></h2><div class="ra-related-grid"><?php foreach ($reviewArticleRelated as $reviewArticleRelatedItem) echo review_articles_view_card($reviewArticleRelatedItem, $locale); ?></div></section><?php endif; ?>
  </div>
</main>
