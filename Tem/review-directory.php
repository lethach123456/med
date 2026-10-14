<?php
$rvText = static fn(string $vi, string $en): string => review_view_text($vi, $en, $locale);
$rvNumber = static fn(int $number): string => number_format($number, 0, '.', $isEnglish ? ',' : '.');
?>
<main class="rv-directory site-typo" id="main-content">
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

    <form class="rv-dir-search" action="<?= review_view_escape($reviewBasePath) ?>" method="get" role="search">
      <label for="review-search"><?= $rvText('Tìm trải nghiệm bạn quan tâm', 'Find an experience that matters to you') ?></label>
      <div class="rv-dir-search-row"><div class="rv-dir-search-field"><?= review_view_icon('search') ?><input id="review-search" name="q" type="search" maxlength="120" value="<?= review_view_escape($reviewFilters['q']) ?>" placeholder="<?= review_view_escape($rvText('Dịch vụ, cơ sở, khu vực…', 'Service, facility, location…')) ?>"></div><button class="rv-button rv-button-primary" type="submit"><?= $rvText('Tìm kiếm', 'Search') ?><?= review_view_icon('arrow-right') ?></button></div>
      <details class="rv-dir-filter" open data-review-filters data-active="<?= $reviewFilters['rating'] !== '' || $reviewFilters['media'] !== '' || $reviewFilters['sort'] !== 'newest' ? 'true' : 'false' ?>">
        <summary><?= review_view_icon('filter') ?><?= $rvText('Lọc & sắp xếp', 'Filter & sort') ?><?= review_view_icon('chevron-down') ?></summary>
        <div class="rv-dir-filter-fields">
          <div><label for="review-rating"><?= $rvText('Điểm đánh giá', 'Rating') ?></label><select name="rating" id="review-rating"><option value=""><?= $rvText('Tất cả điểm', 'All ratings') ?></option><option value="4"<?= $reviewFilters['rating'] === '4' ? ' selected' : '' ?>><?= $rvText('Từ 4 sao', '4 stars and above') ?></option><option value="5"<?= $reviewFilters['rating'] === '5' ? ' selected' : '' ?>>5 <?= $rvText('sao', 'stars') ?></option></select></div>
          <div><label for="review-media"><?= $rvText('Hình ảnh', 'Photos') ?></label><select name="media" id="review-media"><option value=""><?= $rvText('Tất cả review', 'All reviews') ?></option><option value="photos"<?= $reviewFilters['media'] === 'photos' ? ' selected' : '' ?>><?= $rvText('Có ảnh trước / sau', 'With before / after photos') ?></option></select></div>
          <div><label for="review-sort"><?= $rvText('Sắp xếp', 'Sort by') ?></label><select name="sort" id="review-sort"><option value="newest"><?= $rvText('Mới đăng gần đây', 'Recently published') ?></option><option value="rating"<?= $reviewFilters['sort'] === 'rating' ? ' selected' : '' ?>><?= $rvText('Điểm cao nhất', 'Highest rating') ?></option></select></div>
          <button class="rv-button rv-button-secondary" type="submit"><?= $rvText('Áp dụng', 'Apply filters') ?></button>
        </div>
      </details>
    </form>

    <div class="rv-dir-layout">
      <section class="rv-dir-results" aria-labelledby="reviews-results-title">
        <div class="rv-dir-results-heading"><div><h2 id="reviews-results-title"><?= $rvText('Những chia sẻ dành cho bạn', 'Shared perspectives for you') ?></h2><p><?= $rvNumber($reviewPagination['total']) ?> <?= $rvText('bài viết phù hợp', 'matching articles') ?></p></div><?php if ($reviewFilters['q'] !== '' || $reviewFilters['rating'] !== '' || $reviewFilters['media'] !== ''): ?><a class="rv-dir-reset" href="<?= review_view_escape($reviewBasePath) ?>"><?= review_view_icon('x') ?><?= $rvText('Xóa bộ lọc', 'Clear filters') ?></a><?php endif; ?></div>
        <?php if (!empty($reviewUnavailable)): ?>
          <div class="rv-dir-empty"><?= review_view_icon('info') ?><h3><?= $rvText('Chưa tải được danh sách review', 'Reviews could not be loaded') ?></h3><p><?= $rvText('Vui lòng thử lại sau ít phút. Các thông tin trên website vẫn được giữ nguyên.', 'Please try again shortly. The website’s information has not been changed.') ?></p><a class="rv-button rv-button-secondary" href="<?= review_view_escape(review_view_url($reviewBasePath, $reviewFilters)) ?>"><?= $rvText('Thử lại', 'Try again') ?></a></div>
        <?php elseif ($reviewRows === []): ?>
          <div class="rv-dir-empty"><?= review_view_icon('search') ?><h3><?= $rvText('Chưa có review phù hợp', 'No matching reviews yet') ?></h3><p><?= $rvText('Thử một từ khóa khác hoặc bỏ bớt bộ lọc để khám phá thêm trải nghiệm.', 'Try another keyword or fewer filters to explore more experiences.') ?></p><a class="rv-button rv-button-secondary" href="<?= review_view_escape($reviewBasePath) ?>"><?= $rvText('Xem tất cả review', 'View all reviews') ?></a></div>
        <?php else: ?>
          <div class="rv-dir-list">
            <?php foreach ($reviewRows as $rvItem): $rvHref = review_view_detail_url($rvItem['slug'], $locale); ?>
              <article class="rv-dir-card<?= $rvItem['media'] === [] ? ' rv-dir-card-text' : '' ?>">
                <?php if ($rvItem['media'] !== []): ?><a class="rv-dir-card-media" href="<?= review_view_escape($rvHref) ?>" tabindex="-1" aria-hidden="true" data-rv-image-wrap><div class="rv-dir-card-shots<?= count($rvItem['media']) === 1 ? ' rv-dir-single' : '' ?>"><?php foreach (array_slice($rvItem['media'], 0, 2) as $rvImage): ?><div class="rv-dir-shot"><img data-rv-image src="<?= review_view_escape($rvImage['url']) ?>" alt="" loading="lazy" decoding="async"><span><?= review_view_escape($rvImage['label']) ?></span></div><?php endforeach; ?></div></a><?php endif; ?>
                <div class="rv-dir-card-copy">
                  <div class="rv-dir-card-top"><span class="rv-dir-service"><?= review_view_escape($rvItem['is_editorial'] ? $rvText('Nội dung biên tập', 'Editorial information') : ($rvItem['service'] ?: $rvText('Trải nghiệm y tế', 'Healthcare experience'))) ?></span><?php if ($rvItem['rating'] !== null): ?><span class="rv-rating" aria-label="<?= review_view_escape($rvText('Điểm đánh giá ', 'Rating ') . $rvItem['rating'] . '/5') ?>"><?= review_view_icon('star') ?><strong><?= number_format($rvItem['rating'], 1) ?></strong><span>/5</span></span><?php endif; ?></div>
                  <h3><a href="<?= review_view_escape($rvHref) ?>"><?= review_view_escape($rvItem['title']) ?></a></h3>
                  <?php if ($rvItem['facility_name'] !== ''): ?><p class="rv-dir-facility"><?= review_view_icon('building') ?><span><?= review_view_escape($rvItem['facility_name']) ?></span></p><?php endif; ?>
                  <?php if ($rvItem['excerpt'] !== ''): ?><p class="rv-dir-excerpt"><?= review_view_escape($rvItem['excerpt']) ?></p><?php endif; ?>
                  <div class="rv-dir-card-meta"><?php if ($rvItem['author'] !== ''): ?><span><?= review_view_icon('user') ?><?= review_view_escape($rvItem['author']) ?></span><?php endif; ?><?php if ($rvItem['location'] !== ''): ?><span><?= review_view_icon('map-pin') ?><?= review_view_escape($rvItem['location']) ?></span><?php endif; ?><?php if ($rvItem['date'] !== ''): ?><span><?= review_view_icon('calendar') ?><?= review_view_escape($rvItem['date']) ?></span><?php endif; ?></div>
                  <div class="rv-dir-card-foot"><?php if ($rvItem['price'] !== ''): ?><span class="rv-dir-cost"><?= review_view_icon('wallet') ?><?= review_view_escape($rvItem['price']) ?></span><?php elseif ($rvItem['is_verified']): ?><span class="rv-dir-verified"><?= review_view_icon('shield') ?><?= $rvText('Được đánh dấu xác minh', 'Marked as verified') ?></span><?php endif; ?><a class="rv-dir-read" href="<?= review_view_escape($rvHref) ?>"><?= $rvText('Đọc chia sẻ', 'Read experience') ?><?= review_view_icon('arrow-right') ?></a></div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <div class="rv-dir-pagination"><span><?= $rvText('Hiển thị', 'Showing') ?> <?= $reviewPagination['start'] ?>–<?= $reviewPagination['end'] ?> / <?= $rvNumber($reviewPagination['total']) ?></span><?php if ($reviewPagination['pages'] > 1): ?><nav aria-label="<?= review_view_escape($rvText('Phân trang review', 'Review pagination')) ?>"><?php if ($reviewPagination['page'] > 1): ?><a class="rv-button" rel="prev" href="<?= review_view_escape(review_view_url($reviewBasePath, $reviewFilters, $reviewPagination['page'] - 1)) ?>" aria-label="<?= $rvText('Trang trước', 'Previous page') ?>"><?= review_view_icon('arrow-left') ?></a><?php endif; ?><span><?= $reviewPagination['page'] ?> / <?= $reviewPagination['pages'] ?></span><?php if ($reviewPagination['page'] < $reviewPagination['pages']): ?><a class="rv-button" rel="next" href="<?= review_view_escape(review_view_url($reviewBasePath, $reviewFilters, $reviewPagination['page'] + 1)) ?>" aria-label="<?= $rvText('Trang sau', 'Next page') ?>"><?= review_view_icon('arrow-right') ?></a><?php endif; ?></nav><?php endif; ?></div>
        <?php endif; ?>
      </section>
      <aside class="rv-dir-aside" aria-label="<?= review_view_escape($rvText('Tổng quan & hướng dẫn', 'Overview & reading guide')) ?>">
        <?php if ($reviewSummary['total'] > 0): ?><section class="rv-dir-summary"><span class="rv-eyebrow"><?= $rvText('Góc nhìn tổng quan', 'At a glance') ?></span><h2><?= $rvText('Từ những chia sẻ<br>đã công bố', 'From published<br>experiences') ?></h2><?php if ($reviewSummary['rated_count'] > 0): ?><div class="rv-dir-summary-score"><strong><?= number_format($reviewSummary['average'], 1) ?></strong><span>/ 5</span><?= review_view_icon('star') ?></div><p><?= $rvNumber($reviewSummary['rated_count']) ?> <?= $rvText('review có điểm đánh giá', 'reviews with a rating') ?></p><div class="rv-dir-bars" aria-label="<?= $rvText('Phân bố điểm đánh giá', 'Rating distribution') ?>"><?php foreach ($reviewSummary['breakdown'] as $rvStars => $rvCount): ?><div><span><?= $rvStars ?> <?= review_view_icon('star') ?></span><span class="rv-dir-bar" aria-hidden="true"><span style="width:<?= (int) round($rvCount / $reviewSummary['rated_count'] * 100) ?>%"></span></span><span><?= $rvNumber($rvCount) ?></span></div><?php endforeach; ?></div><?php else: ?><p><?= $rvNumber($reviewSummary['total']) ?> <?= $rvText('review đã được công bố', 'published reviews') ?></p><?php endif; ?><p class="rv-dir-summary-note"><?= $rvText('Tổng hợp các review trên trang này, không phải điểm chung của tất cả cơ sở y tế.', 'An overview of reviews in this directory, not a combined rating of healthcare facilities.') ?></p></section><?php endif; ?>
        <section class="rv-dir-guide"><span class="rv-dir-guide-symbol"><?= review_view_icon('info') ?></span><h2><?= $rvText('Đọc review thế nào<br>cho hữu ích?', 'Make the most<br>of each review') ?></h2><ol><li><strong><?= $rvText('Xem đúng bối cảnh', 'Consider the context') ?></strong><p><?= $rvText('Dịch vụ, thời điểm và tình trạng của mỗi người có thể khác nhau.', 'Services, timing and individual circumstances can differ.') ?></p></li><li><strong><?= $rvText('Đối chiếu thông tin', 'Compare information') ?></strong><p><?= $rvText('Tham khảo hồ sơ cơ sở và nguồn được ghi nhận trong bài.', 'Explore the facility profile and any recorded sources.') ?></p></li><li><strong><?= $rvText('Trao đổi với bác sĩ', 'Speak to your clinician') ?></strong><p><?= $rvText('Trải nghiệm cá nhân không thay thế tư vấn y khoa.', 'Personal experiences do not replace medical advice.') ?></p></li></ol></section>
        <section class="rv-dir-contribute"><?= review_view_icon('heart') ?><h2><?= $rvText('Bạn có một trải nghiệm<br>muốn chia sẻ?', 'Have an experience<br>to share?') ?></h2><p><?= $rvText('Gửi câu chuyện hoặc góp ý để giúp cộng đồng có thêm góc nhìn.', 'Share a story or feedback to offer the community another perspective.') ?></p><a class="rv-button rv-button-secondary" href="<?= review_view_escape($reviewContactPath) ?>"><?= $rvText('Gửi chia sẻ', 'Share feedback') ?><?= review_view_icon('arrow-right') ?></a></section>
      </aside>
    </div>
  </div>
</main>
