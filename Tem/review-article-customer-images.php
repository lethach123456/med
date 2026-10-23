<?php
declare(strict_types=1);
if ($reviewArticleCustomerImages === []) return;
$photoCount = count($reviewArticleCustomerImages);
?>
<section class="ra-reading-section ra-customer-gallery" id="review-customer-images" aria-labelledby="review-customer-images-title" aria-roledescription="<?= $raText('thư viện ảnh trượt', 'carousel') ?>" data-review-photo-slider>
  <div class="ra-gallery-heading">
    <div><span class="ra-gallery-kicker"><i class="ph ph-images" aria-hidden="true"></i><?= $raText('Góc nhìn qua hình ảnh', 'A closer look') ?></span><h2 id="review-customer-images-title"><?= $raText('Hình ảnh khách hàng', 'Customer photos') ?></h2></div>
    <span class="ra-gallery-count"><?= $photoCount ?> <?= $raText('ảnh', 'photos') ?></span>
  </div>
  <div class="ra-customer-grid ra-customer-track<?= $photoCount === 1 ? ' ra-customer-grid--single' : '' ?>" id="review-customer-track" data-review-photo-track tabindex="0" role="group" aria-label="<?= $raText('Ảnh khách hàng, lướt ngang để xem tiếp', 'Customer photos, scroll horizontally for more') ?>">
    <?php foreach ($reviewArticleCustomerImages as $photoIndex => $photo) echo review_articles_view_customer_photo_card($photo, $photoIndex, $locale); ?>
  </div>
  <?php if ($photoCount > 2): ?>
    <div class="ra-slider-controls" data-review-slider-controls hidden>
      <button type="button" data-review-slider-prev aria-controls="review-customer-track" aria-label="<?= $raText('Hai ảnh trước', 'Previous two photos') ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i></button>
      <span data-review-slider-position aria-live="polite" aria-atomic="true">1–2 / <?= $photoCount ?></span>
      <button type="button" data-review-slider-next aria-controls="review-customer-track" aria-label="<?= $raText('Hai ảnh tiếp theo', 'Next two photos') ?>"><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
    </div>
  <?php endif; ?>
  <p class="ra-gallery-note"><i class="ph ph-info" aria-hidden="true"></i><span><?= $raText('Hình ảnh tham khảo, không đại diện cho kết quả điều trị của mọi người.', 'Photos are for reference and do not represent treatment outcomes for everyone.') ?></span></p>
</section>
<dialog class="ra-photo-dialog" aria-label="<?= $raText('Xem hình ảnh khách hàng', 'Customer photo viewer') ?>" data-review-photo-dialog>
  <div class="ra-photo-toolbar">
    <span data-review-photo-counter aria-live="polite"></span>
    <button type="button" data-review-photo-close aria-label="<?= $raText('Đóng ảnh', 'Close photo') ?>"><i class="ph ph-x" aria-hidden="true"></i></button>
  </div>
  <figure><div class="ra-photo-stage" data-review-photo-stage><img alt="" data-review-photo-image referrerpolicy="no-referrer"><div class="ra-photo-feedback"><span role="status" aria-live="polite" data-review-photo-status></span><button type="button" data-review-photo-retry hidden><?= $raText('Thử lại', 'Try again') ?></button></div></div><figcaption data-review-photo-caption></figcaption></figure>
  <div class="ra-photo-controls">
    <button type="button" data-review-photo-prev aria-label="<?= $raText('Ảnh trước', 'Previous photo') ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i></button>
    <button type="button" data-review-photo-next aria-label="<?= $raText('Ảnh tiếp theo', 'Next photo') ?>"><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
  </div>
</dialog>
