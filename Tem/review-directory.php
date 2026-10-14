<?php
$rvText = static fn(string $vi, string $en): string => review_view_text($vi, $en, $locale);
$rvNumber = static fn(int $number): string => number_format($number, 0, '.', $isEnglish ? ',' : '.');
$rvFilterCount = (int) ($reviewFilters['rating'] !== '') + (int) ($reviewFilters['media'] !== '');
?>
<main class="rv-directory site-typo" id="main-content" data-locale="<?= review_view_escape($locale) ?>">
  <div class="rv-container">
    <nav class="rv-breadcrumb" aria-label="<?= review_view_escape($rvText('Đường dẫn', 'Breadcrumb')) ?>">
      <a href="<?= $isEnglish ? '/en' : '/' ?>"><?= $rvText('Trang chủ', 'Home') ?></a><?= review_view_icon('chevron-right') ?><span aria-current="page">Review</span>
    </nav>
    <section class="rv-dir-hero" aria-labelledby="reviews-title">
      <div class="rv-dir-hero-copy">
        <span class="rv-eyebrow"><?= $rvText('Trải nghiệm & góc nhìn', 'Experiences & perspectives') ?></span>
        <h1 id="reviews-title"><?= $rvText('Một trải nghiệm.<br>Thêm một góc nhìn.', 'One experience.<br>A new perspective.') ?></h1>
        <p><?= $rvText('Tham khảo chia sẻ về dịch vụ, chi phí và hành trình điều trị. Thêm một góc nhìn trước khi lựa chọn.', 'Explore shared experiences of care, costs and treatment journeys. Get another perspective before making your own choice.') ?></p>
        <div class="rv-dir-hero-foot"><?= review_view_icon('quote') ?><span><?= $rvText('Đọc để tham khảo. Lựa chọn cùng bác sĩ.', 'Read for perspective. Decide with your clinician.') ?></span></div>
      </div>
      <div class="rv-dir-hero-art" aria-hidden="true">
        <span class="rv-dir-art-quote"><?= review_view_icon('quote') ?></span>
        <div class="rv-dir-art-note"><span class="rv-dir-art-line"></span><strong><?= $rvText('Điều gì đã giúp bạn<br>an tâm hơn?', 'What helped you<br>feel more confident?') ?></strong><div><span><?= $rvText('Sự tận tâm', 'Thoughtful care') ?></span><span><?= $rvText('Thông tin rõ ràng', 'Clear information') ?></span></div><p><?= $rvText('Một chia sẻ nhỏ, một giá trị lớn.', 'A small story. A meaningful perspective.') ?></p></div>
        <span class="rv-dir-art-heart"><?= review_view_icon('heart') ?></span>
      </div>
    </section>

    <form class="rv-dir-search" id="reviewDirectoryFilter" action="<?= review_view_escape($reviewBasePath) ?>" method="get" role="search">
      <label class="rv-dir-search-label" for="review-search"><?= review_view_icon('search') ?><span class="rv-sr-only"><?= $rvText('Tìm trải nghiệm bạn quan tâm', 'Find an experience that matters to you') ?></span></label>
      <input id="review-search" name="q" type="search" maxlength="120" autocomplete="off" enterkeyhint="search" value="<?= review_view_escape($reviewFilters['q']) ?>" placeholder="<?= review_view_escape($rvText('Dịch vụ, cơ sở, thành phố…', 'Service, facility, city…')) ?>">
      <button class="rv-dir-search-submit" type="submit" aria-label="<?= $rvText('Tìm kiếm', 'Search') ?>"><?= review_view_icon('send') ?></button>
    </form>

    <div class="rv-dir-layout">
      <section class="rv-dir-results" aria-labelledby="reviews-results-title">
        <div class="rv-dir-results-heading" id="reviewResultsHeading" tabindex="-1">
          <div class="rv-dir-results-copy"><h2 id="reviews-results-title"><?= $rvText('Review dành cho bạn', 'Reviews for you') ?></h2><p><span id="reviewResultCount"><?= $rvNumber($reviewPagination['total']) ?> <?= $rvText('bài viết phù hợp', 'matching articles') ?></span><span class="rv-dir-loading" data-review-loading hidden aria-hidden="true"></span></p></div>
          <div class="rv-dir-tools" data-review-tools>
            <details class="rv-dir-filter-disclosure" data-review-filters>
              <summary class="rv-dir-filter-toggle" id="reviewFilterToggle" aria-controls="reviewFilterOptions"><?= review_view_icon('filter') ?><span class="rv-dir-filter-desktop"><?= $rvText('Bộ lọc', 'Filters') ?></span><span class="rv-dir-filter-mobile"><?= $rvText('Lọc & xếp', 'Filter & sort') ?></span><b data-review-filter-count<?= $rvFilterCount === 0 ? ' hidden' : '' ?>><?= $rvFilterCount ?></b><?= review_view_icon('chevron-down') ?></summary>
              <section class="rv-dir-filter-panel" id="reviewFilterOptions" aria-labelledby="reviewFilterHeading">
                <div class="rv-dir-filter-heading"><h3 id="reviewFilterHeading"><?= review_view_icon('filter') ?><?= $rvText('Lọc theo nhu cầu', 'Refine your search') ?></h3><div><a href="<?= review_view_escape($reviewBasePath) ?>" data-review-clear><?= $rvText('Đặt lại', 'Reset') ?></a><button type="button" data-review-filter-close hidden aria-label="<?= $rvText('Đóng bộ lọc', 'Close filters') ?>"><?= review_view_icon('x') ?></button></div></div>
                <div class="rv-dir-sort-slot" data-review-sort-slot><div class="rv-dir-sort" data-review-sort-group><label for="review-sort"><?= $rvText('Sắp xếp', 'Sort by') ?></label><select name="sort" id="review-sort" form="reviewDirectoryFilter" data-review-control><option value="newest"><?= $rvText('Mới đăng gần đây', 'Recently published') ?></option><option value="rating"<?= $reviewFilters['sort'] === 'rating' ? ' selected' : '' ?>><?= $rvText('Điểm cao nhất', 'Highest rating') ?></option></select></div></div>
                <div class="rv-dir-filter-field"><label for="review-rating"><?= $rvText('Điểm đánh giá', 'Rating') ?></label><div class="rv-dir-select-wrap"><?= review_view_icon('star') ?><select name="rating" id="review-rating" form="reviewDirectoryFilter" data-review-control><option value=""><?= $rvText('Tất cả điểm', 'All ratings') ?></option><option value="4"<?= $reviewFilters['rating'] === '4' ? ' selected' : '' ?>><?= $rvText('Từ 4 sao', '4 stars and above') ?></option><option value="5"<?= $reviewFilters['rating'] === '5' ? ' selected' : '' ?>>5 <?= $rvText('sao', 'stars') ?></option></select><?= review_view_icon('chevron-down') ?></div></div>
                <div class="rv-dir-filter-field"><label for="review-media"><?= $rvText('Hình ảnh', 'Photos') ?></label><div class="rv-dir-select-wrap"><?= review_view_icon('images') ?><select name="media" id="review-media" form="reviewDirectoryFilter" data-review-control><option value=""><?= $rvText('Tất cả review', 'All reviews') ?></option><option value="photos"<?= $reviewFilters['media'] === 'photos' ? ' selected' : '' ?>><?= $rvText('Có ảnh trước / sau', 'With before / after photos') ?></option></select><?= review_view_icon('chevron-down') ?></div></div>
                <button class="rv-button rv-button-primary rv-dir-filter-apply" type="submit" form="reviewDirectoryFilter"><?= $rvText('Xem kết quả', 'Show results') ?><?= review_view_icon('arrow-right') ?></button>
              </section>
            </details>
          </div>
        </div>
        <div class="rv-dir-active-filters" id="reviewActiveFilters" hidden></div>
        <p class="rv-dir-status rv-sr-only" id="reviewDirectoryStatus" role="status" aria-live="polite" aria-atomic="true"></p>
        <div class="rv-dir-result-content" id="reviewResults"><?php require __DIR__ . '/review-results.php'; ?></div>
      </section>
      <aside class="rv-dir-aside" aria-label="<?= review_view_escape($rvText('Tổng quan & hướng dẫn', 'Overview & reading guide')) ?>">
        <?php if ($reviewSummary['total'] > 0): ?><section class="rv-dir-summary"><span class="rv-eyebrow"><?= $rvText('Góc nhìn tổng quan', 'At a glance') ?></span><h2><?= $rvText('Từ những chia sẻ<br>đã công bố', 'From published<br>experiences') ?></h2><?php if ($reviewSummary['rated_count'] > 0): ?><div class="rv-dir-summary-score"><strong><?= number_format($reviewSummary['average'], 1) ?></strong><span>/ 5</span><?= review_view_icon('star') ?></div><p><?= $rvNumber($reviewSummary['rated_count']) ?> <?= $rvText('review có điểm đánh giá', 'reviews with a rating') ?></p><div class="rv-dir-bars" aria-label="<?= $rvText('Phân bố điểm đánh giá', 'Rating distribution') ?>"><?php foreach ($reviewSummary['breakdown'] as $rvStars => $rvCount): ?><div><span><?= $rvStars ?> <?= review_view_icon('star') ?></span><span class="rv-dir-bar" aria-hidden="true"><span style="width:<?= (int) round($rvCount / $reviewSummary['rated_count'] * 100) ?>%"></span></span><span><?= $rvNumber($rvCount) ?></span></div><?php endforeach; ?></div><?php else: ?><p><?= $rvNumber($reviewSummary['total']) ?> <?= $rvText('review đã được công bố', 'published reviews') ?></p><?php endif; ?><p class="rv-dir-summary-note"><?= $rvText('Tổng hợp các review trên trang này, không phải điểm chung của tất cả cơ sở y tế.', 'An overview of reviews in this directory, not a combined rating of healthcare facilities.') ?></p></section><?php endif; ?>
        <section class="rv-dir-guide"><span class="rv-dir-guide-symbol"><?= review_view_icon('info') ?></span><h2><?= $rvText('Đọc review thế nào<br>cho hữu ích?', 'Make the most<br>of each review') ?></h2><ol><li><strong><?= $rvText('Xem đúng bối cảnh', 'Consider the context') ?></strong><p><?= $rvText('Dịch vụ, thời điểm và tình trạng của mỗi người có thể khác nhau.', 'Services, timing and individual circumstances can differ.') ?></p></li><li><strong><?= $rvText('Đối chiếu thông tin', 'Compare information') ?></strong><p><?= $rvText('Tham khảo hồ sơ cơ sở và nguồn được ghi nhận trong bài.', 'Explore the facility profile and any recorded sources.') ?></p></li><li><strong><?= $rvText('Trao đổi với bác sĩ', 'Speak to your clinician') ?></strong><p><?= $rvText('Trải nghiệm cá nhân không thay thế tư vấn y khoa.', 'Personal experiences do not replace medical advice.') ?></p></li></ol></section>
        <section class="rv-dir-contribute"><?= review_view_icon('heart') ?><h2><?= $rvText('Bạn có một trải nghiệm<br>muốn chia sẻ?', 'Have an experience<br>to share?') ?></h2><p><?= $rvText('Gửi câu chuyện hoặc góp ý để giúp cộng đồng có thêm góc nhìn.', 'Share a story or feedback to offer the community another perspective.') ?></p><a class="rv-button rv-button-secondary" href="<?= review_view_escape($reviewContactPath) ?>"><?= $rvText('Gửi chia sẻ', 'Share feedback') ?><?= review_view_icon('arrow-right') ?></a></section>
      </aside>
    </div>
  </div>
</main>
