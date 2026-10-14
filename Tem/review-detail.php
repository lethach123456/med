<?php
/** Public review article. All content is supplied by the read-only view model. */
$detailText = static fn(string $vi, string $en): string => review_view_text($vi, $en, $locale);
$detailEscape = static fn($value): string => review_view_escape((string) $value);
$detailStory = array_values(array_filter($review['story'] ?? [], static fn($value): bool => is_string($value) && trim($value) !== ''));
$detailTimeline = array_values($review['timeline'] ?? []);
$detailMedia = array_values($review['media'] ?? []);
$detailFacts = [];
foreach ([
    'service' => $detailText('Dịch vụ', 'Service'),
    'method' => $detailText('Phương pháp', 'Method'),
    'duration' => $detailText('Thời gian điều trị', 'Treatment duration'),
    'start_date' => $detailText('Bắt đầu', 'Started'),
    'end_date' => $detailText('Kết thúc', 'Completed'),
    'condition' => $detailText('Tình trạng chia sẻ', 'Reported condition'),
] as $detailKey => $detailLabel) {
    if (trim((string) ($review[$detailKey] ?? '')) !== '') {
        $detailFacts[] = ['label' => $detailLabel, 'value' => $review[$detailKey]];
    }
}
$detailAuthor = trim((string) ($review['author'] ?? ''));
$detailRating = (float) ($review['rating'] ?? 0);
$detailEditorial = !empty($review['is_editorial']);
?>
<main class="rv-detail site-typo" id="main-content">
  <div class="rv-container">
    <nav class="rv-breadcrumb" aria-label="<?= $detailEscape($detailText('Đường dẫn', 'Breadcrumb')) ?>">
      <a href="<?= $isEnglish ? '/en/' : '/' ?>"><?= $detailEscape($detailText('Trang chủ', 'Home')) ?></a>
      <?= review_view_icon('chevron-right') ?>
      <a href="<?= $detailEscape($reviewBasePath) ?>">Review</a>
      <?= review_view_icon('chevron-right') ?>
      <span aria-current="page"><?= $detailEscape($detailText('Trải nghiệm được chia sẻ', 'Shared experience')) ?></span>
    </nav>

    <header class="rv-detail-heading">
      <div class="rv-detail-heading-labels">
        <span class="rv-eyebrow"><?= $detailEscape($detailEditorial ? $detailText('Thông tin biên tập', 'Editorial information') : $detailText('Góc nhìn từ trải nghiệm', 'A perspective from experience')) ?></span>
        <?php if (trim((string) ($review['service'] ?? '')) !== ''): ?>
          <span class="rv-detail-service"><?= $detailEscape($review['service']) ?></span>
        <?php endif; ?>
      </div>
      <h1><?= $detailEscape($review['title']) ?></h1>
      <?php if ($detailStory !== [] && trim((string) ($review['excerpt'] ?? '')) !== ''): ?>
        <p class="rv-detail-deck"><?= $detailEscape($review['excerpt']) ?></p>
      <?php endif; ?>
      <div class="rv-detail-byline">
        <div class="rv-detail-author">
          <span class="rv-detail-avatar" aria-hidden="true"><?= review_view_icon('user') ?></span>
          <div>
            <strong><?= $detailEscape($detailAuthor !== '' ? $detailAuthor : $detailText('Người chia sẻ', 'Contributor')) ?></strong>
            <div class="rv-detail-author-meta">
              <?php if (trim((string) ($review['location'] ?? '')) !== ''): ?>
                <span><?= review_view_icon('map-pin') ?><?= $detailEscape($review['location']) ?></span>
              <?php endif; ?>
              <?php if (trim((string) ($review['date'] ?? '')) !== ''): ?>
                <span><?= review_view_icon('calendar') ?><?= $detailEscape($review['date']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="rv-detail-rating-status">
          <?php if ($detailRating > 0): ?>
            <span class="rv-detail-score" aria-label="<?= $detailEscape($detailText('Điểm người chia sẻ: ', 'Contributor rating: ') . number_format($detailRating, 1) . '/5') ?>">
              <?= review_view_icon('star') ?><strong><?= number_format($detailRating, 1) ?></strong><span>/ 5</span>
            </span>
          <?php endif; ?>
          <?php if (!empty($review['is_verified'])): ?>
            <a class="rv-detail-status" href="#review-context"><?= review_view_icon('shield') ?><?= $detailEscape($detailText('Được đánh dấu xác minh', 'Marked as verified')) ?></a>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <div class="rv-detail-layout">
      <article class="rv-detail-article" aria-label="<?= $detailEscape($detailText('Nội dung review', 'Review content')) ?>">
        <?php if ($detailMedia !== []): ?>
          <section class="rv-detail-gallery" id="review-images" aria-labelledby="review-images-title">
            <div class="rv-detail-section-heading rv-detail-gallery-heading">
              <h2 id="review-images-title"><?= $detailEscape($detailText('Hình ảnh được chia sẻ', 'Shared photos')) ?></h2>
              <span><?= review_view_icon('images') ?><?= count($detailMedia) ?> <?= $detailEscape($detailText('ảnh', 'photos')) ?></span>
            </div>
            <div class="rv-detail-gallery-main<?= count($detailMedia) === 1 ? ' rv-detail-gallery-single' : '' ?>">
              <?php foreach (array_slice($detailMedia, 0, 2) as $detailImageIndex => $detailImage): ?>
                <button class="rv-detail-image" type="button" data-review-image data-image-src="<?= $detailEscape($detailImage['url']) ?>" data-image-label="<?= $detailEscape($detailImage['label']) ?>" data-image-alt="<?= $detailEscape($detailImage['alt']) ?>" aria-label="<?= $detailEscape($detailText('Xem ảnh: ', 'View photo: ') . $detailImage['label']) ?>">
                  <img src="<?= $detailEscape($detailImage['url']) ?>" alt="<?= $detailEscape($detailImage['alt']) ?>" loading="<?= $detailImageIndex === 0 ? 'eager' : 'lazy' ?>" decoding="async"<?= $detailImageIndex === 0 ? ' fetchpriority="high"' : '' ?>>
                  <span class="rv-detail-image-caption"><?= $detailEscape($detailImage['label']) ?></span>
                  <span class="rv-detail-image-expand" aria-hidden="true"><?= review_view_icon('expand') ?></span>
                </button>
              <?php endforeach; ?>
            </div>
            <?php if (count($detailMedia) > 2): ?>
              <div class="rv-detail-gallery-thumbs">
              <?php foreach (array_slice($detailMedia, 2, 3) as $detailImage): ?>
                  <button class="rv-detail-image rv-detail-image-thumb" type="button" data-review-image data-image-src="<?= $detailEscape($detailImage['url']) ?>" data-image-label="<?= $detailEscape($detailImage['label']) ?>" data-image-alt="<?= $detailEscape($detailImage['alt']) ?>" aria-label="<?= $detailEscape($detailText('Xem ảnh: ', 'View photo: ') . $detailImage['label']) ?>">
                    <img src="<?= $detailEscape($detailImage['url']) ?>" alt="<?= $detailEscape($detailImage['alt']) ?>" loading="lazy" decoding="async">
                    <span class="rv-detail-image-caption"><?= $detailEscape($detailImage['label']) ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (count($detailMedia) > 5): ?>
              <details class="rv-detail-more-photos">
                <summary><?= review_view_icon('images') ?><?= $detailEscape($detailText('Xem thêm ', 'Show ') . (count($detailMedia) - 5) . $detailText(' ảnh', ' more photos')) ?><?= review_view_icon('chevron-down') ?></summary>
                <div class="rv-detail-gallery-thumbs">
                  <?php foreach (array_slice($detailMedia, 5) as $detailImage): ?>
                    <button class="rv-detail-image rv-detail-image-thumb" type="button" data-review-image data-image-src="<?= $detailEscape($detailImage['url']) ?>" data-image-label="<?= $detailEscape($detailImage['label']) ?>" data-image-alt="<?= $detailEscape($detailImage['alt']) ?>" aria-label="<?= $detailEscape($detailText('Xem ảnh: ', 'View photo: ') . $detailImage['label']) ?>">
                      <img src="<?= $detailEscape($detailImage['url']) ?>" alt="<?= $detailEscape($detailImage['alt']) ?>" loading="lazy" decoding="async"><span class="rv-detail-image-caption"><?= $detailEscape($detailImage['label']) ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endif; ?>
            <p class="rv-detail-photo-note"><?= $detailEscape($detailText('Hình ảnh được ghi nhận trong review. Kết quả điều trị có thể khác nhau ở mỗi người.', 'Images recorded in this review. Treatment outcomes can vary from person to person.')) ?></p>
          </section>
        <?php endif; ?>

        <?php if ($detailStory !== [] || trim((string) ($review['excerpt'] ?? '')) !== ''): ?>
          <section class="rv-detail-section" id="review-story" aria-labelledby="review-story-title">
            <div class="rv-detail-section-heading">
              <span class="rv-detail-section-symbol" aria-hidden="true"><?= review_view_icon('quote') ?></span>
              <h2 id="review-story-title"><?= $detailEscape($detailEditorial ? $detailText('Thông tin được tổng hợp', 'Compiled information') : $detailText('Câu chuyện được chia sẻ', 'The shared experience')) ?></h2>
            </div>
            <div class="rv-detail-story">
              <?php if ($detailStory === []): ?><p><?= nl2br($detailEscape($review['excerpt'])) ?></p><?php endif; ?>
              <?php foreach ($detailStory as $detailParagraph): ?>
                <p><?= nl2br($detailEscape($detailParagraph)) ?></p>
              <?php endforeach; ?>
            </div>
          </section>
        <?php elseif (trim((string) ($review['excerpt'] ?? '')) === ''): ?>
          <section class="rv-detail-section rv-detail-story-placeholder">
            <h2><?= $detailEscape($detailText('Nội dung chia sẻ', 'Shared content')) ?></h2>
            <p><?= $detailEscape($detailText('Review này hiện chỉ có thông tin tóm tắt. Bạn có thể tham khảo các thông tin đã được công bố bên dưới.', 'This review currently contains summary information only. The published details are available below.')) ?></p>
          </section>
        <?php endif; ?>

        <?php if ($detailTimeline !== []): ?>
          <section class="rv-detail-section" id="review-timeline" aria-labelledby="review-timeline-title">
            <div class="rv-detail-section-heading">
              <span class="rv-detail-section-symbol" aria-hidden="true"><?= review_view_icon('clock') ?></span>
              <h2 id="review-timeline-title"><?= $detailEscape($detailEditorial ? $detailText('Mốc thông tin', 'Information timeline') : $detailText('Hành trình điều trị', 'Treatment journey')) ?></h2>
            </div>
            <ol class="rv-detail-timeline">
              <?php foreach ($detailTimeline as $detailPointIndex => $detailPoint): ?>
                <li>
                  <span class="rv-detail-timeline-step" aria-hidden="true"><?= $detailPointIndex + 1 ?></span>
                  <div>
                    <?php if (trim((string) ($detailPoint['time'] ?? '')) !== ''): ?><span class="rv-detail-timeline-time"><?= $detailEscape($detailPoint['time']) ?></span><?php endif; ?>
                    <?php $detailPointTitle = trim((string) ($detailPoint['title'] ?? '')) ?: trim((string) ($detailPoint['label'] ?? '')); ?>
                    <?php if ($detailPointTitle !== ''): ?><h3><?= $detailEscape($detailPointTitle) ?></h3><?php endif; ?>
                    <?php if (trim((string) ($detailPoint['text'] ?? '')) !== ''): ?><p><?= nl2br($detailEscape($detailPoint['text'])) ?></p><?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endif; ?>

        <section class="rv-detail-context" id="review-context" aria-labelledby="review-context-title">
          <div class="rv-detail-section-heading">
            <?= review_view_icon('info') ?><h2 id="review-context-title"><?= $detailEscape($detailText('Đọc review với đầy đủ bối cảnh', 'Read this review in context')) ?></h2>
          </div>
          <p><?= $detailEscape($detailEditorial ? $detailText('Đây là nội dung biên tập tổng hợp, không phải đánh giá của khách hàng. Thông tin mang tính tham khảo, không thay thế tư vấn y khoa.', 'This is compiled editorial information, not a customer review. It is for reference and does not replace medical advice.') : $detailText('Đây là trải nghiệm được chia sẻ, không phải cam kết về kết quả hay tư vấn y khoa. Hãy trao đổi trực tiếp với bác sĩ về tình trạng và phương án phù hợp với bạn.', 'This is a shared experience, not a promise of results or medical advice. Discuss your condition and suitable treatment options directly with a qualified clinician.')) ?></p>
          <?php if (!empty($review['is_verified'])): ?>
            <p><?= $detailEscape($detailText('Review này được hệ thống đánh dấu đã xác minh. Nhãn này không đồng nghĩa với việc MedReview bảo chứng hiệu quả điều trị.', 'This review is marked as verified in the system. That label does not mean MedReview guarantees treatment effectiveness.')) ?></p>
          <?php endif; ?>
          <?php if (trim((string) ($review['source'] ?? '')) !== '' || trim((string) ($review['source_url'] ?? '')) !== ''): ?>
            <div class="rv-detail-source">
              <strong><?= $detailEscape($detailText('Nguồn được ghi nhận', 'Recorded source')) ?></strong>
              <?php if (!empty($review['source_url'])): ?>
                <a href="<?= $detailEscape($review['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= $detailEscape(trim((string) ($review['source'] ?? '')) ?: $detailText('Xem nguồn công khai', 'View public source')) ?><?= review_view_icon('arrow-right') ?><span class="rv-sr-only"> <?= $detailEscape($detailText('(mở trong tab mới)', '(opens in a new tab)')) ?></span></a>
              <?php else: ?>
                <span><?= $detailEscape($review['source']) ?></span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="rv-detail-feedback" aria-labelledby="review-feedback-title">
          <span class="rv-detail-feedback-symbol" aria-hidden="true"><?= review_view_icon('heart') ?></span>
          <div>
            <h2 id="review-feedback-title"><?= $detailEscape($detailText('Mỗi góc nhìn đều có giá trị', 'Every perspective matters')) ?></h2>
            <p><?= $detailEscape($detailText('Muốn chia sẻ trải nghiệm hoặc góp ý về nội dung này? Hãy gửi thông tin đến MedReview.', 'Have an experience to share or feedback about this review? Get in touch with MedReview.')) ?></p>
          </div>
          <a class="rv-button rv-button-primary" href="<?= $detailEscape($reviewContactPath) ?>"><?= $detailEscape($detailText('Gửi chia sẻ', 'Share feedback')) ?><?= review_view_icon('arrow-right') ?></a>
        </section>
      </article>

      <aside class="rv-detail-aside" aria-label="<?= $detailEscape($detailText('Thông tin liên quan', 'Related information')) ?>">
        <?php if ($reviewFacility): ?>
          <section class="rv-detail-side-card rv-detail-facility">
            <span class="rv-eyebrow"><?= $detailEscape($detailText('Cơ sở trong review', 'Facility in this review')) ?></span>
            <div class="rv-detail-facility-heading">
              <?php if (!empty($reviewFacility['image'])): ?>
                <img class="rv-detail-facility-image" src="<?= $detailEscape($reviewFacility['image']) ?>" alt="<?= $detailEscape($reviewFacility['name']) ?>" width="56" height="56" loading="lazy" decoding="async">
              <?php else: ?>
                <span class="rv-detail-facility-symbol" aria-hidden="true"><?= review_view_icon('building') ?></span>
              <?php endif; ?>
              <h2><?= $detailEscape($reviewFacility['name']) ?></h2>
            </div>
            <?php if (trim((string) ($reviewFacility['location'] ?? '')) !== ''): ?>
              <p class="rv-detail-facility-location"><?= review_view_icon('map-pin') ?><span><?= $detailEscape($reviewFacility['location']) ?></span></p>
            <?php endif; ?>
            <?php if ((float) ($reviewFacility['rating'] ?? 0) > 0): ?>
              <div class="rv-detail-facility-rating"><span class="rv-rating"><?= review_view_icon('star') ?><strong><?= number_format((float) $reviewFacility['rating'], 1) ?></strong><span>/ 5</span></span><?php if ((int) ($reviewFacility['review_count'] ?? 0) > 0): ?><span><?= number_format((int) $reviewFacility['review_count'], 0, '.', $isEnglish ? ',' : '.') ?> <?= $detailEscape($detailText('đánh giá', 'ratings')) ?></span><?php endif; ?></div>
            <?php endif; ?>
            <?php if ($reviewFacility['url'] !== ''): ?><a class="rv-button rv-button-primary rv-detail-facility-link" href="<?= $detailEscape($reviewFacility['url']) ?>"><?= $detailEscape($detailText('Xem hồ sơ cơ sở', 'View facility profile')) ?><?= review_view_icon('arrow-right') ?></a><?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if (trim((string) ($review['price'] ?? '')) !== ''): ?>
          <section class="rv-detail-side-card rv-detail-cost">
            <h2><?= review_view_icon('wallet') ?><?= $detailEscape($detailText('Chi phí được chia sẻ', 'Reported cost')) ?></h2>
            <p class="rv-detail-cost-value"><?= $detailEscape($review['price']) ?></p>
            <p class="rv-detail-small-note"><?= $detailEscape($detailText('Chỉ để tham khảo, không phải báo giá hiện tại của cơ sở.', 'For reference only, not the facility’s current quote.')) ?></p>
          </section>
        <?php endif; ?>

        <?php if ($detailFacts !== []): ?>
          <section class="rv-detail-side-card rv-detail-facts">
            <h2><?= $detailEscape($detailEditorial ? $detailText('Thông tin bài viết', 'Article details') : $detailText('Thông tin trải nghiệm', 'Experience details')) ?></h2>
            <dl>
              <?php foreach ($detailFacts as $detailFact): ?>
                <div><dt><?= $detailEscape($detailFact['label']) ?></dt><dd><?= $detailEscape($detailFact['value']) ?></dd></div>
              <?php endforeach; ?>
            </dl>
          </section>
        <?php endif; ?>

        <nav class="rv-detail-side-card rv-detail-contents" aria-label="<?= $detailEscape($detailText('Trong bài review này', 'In this review')) ?>">
          <h2><?= $detailEscape($detailText('Trong bài review này', 'In this review')) ?></h2>
          <?php if ($detailMedia !== []): ?><a href="#review-images"><?= review_view_icon('images') ?><?= $detailEscape($detailText('Hình ảnh được chia sẻ', 'Shared photos')) ?><?= review_view_icon('chevron-right') ?></a><?php endif; ?>
          <?php if ($detailStory !== [] || trim((string) ($review['excerpt'] ?? '')) !== ''): ?><a href="#review-story"><?= review_view_icon('quote') ?><?= $detailEscape($detailEditorial ? $detailText('Thông tin tổng hợp', 'Compiled information') : $detailText('Câu chuyện trải nghiệm', 'The experience')) ?><?= review_view_icon('chevron-right') ?></a><?php endif; ?>
          <?php if ($detailTimeline !== []): ?><a href="#review-timeline"><?= review_view_icon('clock') ?><?= $detailEscape($detailEditorial ? $detailText('Mốc thông tin', 'Information timeline') : $detailText('Hành trình điều trị', 'Treatment journey')) ?><?= review_view_icon('chevron-right') ?></a><?php endif; ?>
          <a href="#review-context"><?= review_view_icon('info') ?><?= $detailEscape($detailText('Bối cảnh & nguồn', 'Context & source')) ?><?= review_view_icon('chevron-right') ?></a>
        </nav>
      </aside>
    </div>

    <?php if ($relatedReviews !== []): ?>
      <section class="rv-detail-related" aria-labelledby="review-related-title">
        <div class="rv-detail-related-heading">
          <div><span class="rv-eyebrow"><?= $detailEscape($detailText('Thêm góc nhìn cho bạn', 'More perspectives')) ?></span><h2 id="review-related-title"><?= $detailEscape($detailText('Khám phá những chia sẻ khác', 'Explore more shared experiences')) ?></h2></div>
          <a class="rv-button rv-button-secondary" href="<?= $detailEscape($reviewBasePath) ?>"><?= $detailEscape($detailText('Tất cả review', 'All reviews')) ?><?= review_view_icon('arrow-right') ?></a>
        </div>
        <div class="rv-detail-related-grid">
          <?php foreach ($relatedReviews as $detailRelated): ?>
            <article class="rv-detail-related-card">
              <?php $detailRelatedImage = $detailRelated['media'][0] ?? null; ?>
              <?php if ($detailRelatedImage): ?>
                <a class="rv-detail-related-image" href="<?= $detailEscape(review_view_detail_url($detailRelated['slug'], $locale)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= $detailEscape($detailRelatedImage['url']) ?>" alt="" loading="lazy" decoding="async"></a>
              <?php endif; ?>
              <div class="rv-detail-related-copy">
                <span class="rv-detail-related-service"><?= $detailEscape(trim((string) ($detailRelated['service'] ?? '')) ?: $detailText('Trải nghiệm được chia sẻ', 'Shared experience')) ?></span>
                <h3><a href="<?= $detailEscape(review_view_detail_url($detailRelated['slug'], $locale)) ?>"><?= $detailEscape($detailRelated['title']) ?></a></h3>
                <?php if (trim((string) ($detailRelated['excerpt'] ?? '')) !== ''): ?><p><?= $detailEscape($detailRelated['excerpt']) ?></p><?php endif; ?>
                <div class="rv-detail-related-meta">
                  <span><?= review_view_icon('user') ?><?= $detailEscape(trim((string) ($detailRelated['author'] ?? '')) ?: $detailText('Người chia sẻ', 'Contributor')) ?></span>
                  <?php if ((float) ($detailRelated['rating'] ?? 0) > 0): ?><span class="rv-rating"><?= review_view_icon('star') ?><strong><?= number_format((float) $detailRelated['rating'], 1) ?></strong></span><?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <?php if ($detailMedia !== []): ?>
    <dialog class="rv-lightbox" data-review-lightbox aria-label="<?= $detailEscape($detailText('Xem hình ảnh review', 'Review photo viewer')) ?>">
      <button class="rv-lightbox-close" type="button" data-lightbox-close aria-label="<?= $detailEscape($detailText('Đóng xem ảnh', 'Close photo viewer')) ?>"><?= review_view_icon('x') ?></button>
      <button class="rv-lightbox-prev" type="button" data-lightbox-prev aria-label="<?= $detailEscape($detailText('Ảnh trước', 'Previous photo')) ?>"><?= review_view_icon('arrow-left') ?></button>
      <figure><img data-lightbox-image alt=""><figcaption data-lightbox-caption></figcaption></figure>
      <button class="rv-lightbox-next" type="button" data-lightbox-next aria-label="<?= $detailEscape($detailText('Ảnh tiếp theo', 'Next photo')) ?>"><?= review_view_icon('arrow-right') ?></button>
      <span class="rv-lightbox-counter" data-lightbox-counter></span>
    </dialog>
  <?php endif; ?>
</main>
