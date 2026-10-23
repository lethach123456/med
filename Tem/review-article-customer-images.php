<?php
declare(strict_types=1);
if ($reviewArticleCustomerImages === []) return;
?>
<section class="ra-reading-section ra-customer-gallery" id="review-customer-images" aria-labelledby="review-customer-images-title">
  <h2 id="review-customer-images-title"><?= $raText('Hình ảnh khách hàng', 'Customer photos') ?></h2>
  <p class="ra-gallery-note"><?= $raText('Hình ảnh tham khảo, không đại diện cho kết quả điều trị của mọi người.', 'Photos are for reference and do not represent treatment outcomes for everyone.') ?></p>
  <div class="ra-customer-grid">
    <?php foreach ($reviewArticleCustomerImages as $photo): $photoAlt = $photo['alt'] ?: ($photo['caption'] ?: $raText('Hình ảnh khách hàng tại cơ sở', 'Customer photo at the facility')); ?>
      <figure>
        <a href="<?= $e($photo['url']) ?>" data-review-photo data-caption="<?= $e($photo['caption']) ?>" aria-label="<?= $e($raText('Xem ảnh: ', 'View photo: ') . $photoAlt) ?>">
          <img src="<?= $e($photo['url']) ?>" alt="<?= $e($photoAlt) ?>" width="480" height="360" loading="lazy" decoding="async" referrerpolicy="no-referrer">
          <span aria-hidden="true"><i class="ph ph-magnifying-glass-plus"></i></span>
        </a>
        <?php if ($photo['caption'] !== ''): ?><figcaption><?= $e($photo['caption']) ?></figcaption><?php endif; ?>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<dialog class="ra-photo-dialog" aria-label="<?= $raText('Xem hình ảnh khách hàng', 'Customer photo viewer') ?>" data-review-photo-dialog>
  <div class="ra-photo-toolbar">
    <span data-review-photo-counter aria-live="polite"></span>
    <button type="button" data-review-photo-close aria-label="<?= $raText('Đóng ảnh', 'Close photo') ?>"><i class="ph ph-x" aria-hidden="true"></i></button>
  </div>
  <figure><img alt="" data-review-photo-image referrerpolicy="no-referrer"><figcaption data-review-photo-caption></figcaption></figure>
  <div class="ra-photo-controls">
    <button type="button" data-review-photo-prev aria-label="<?= $raText('Ảnh trước', 'Previous photo') ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i></button>
    <button type="button" data-review-photo-next aria-label="<?= $raText('Ảnh tiếp theo', 'Next photo') ?>"><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
  </div>
</dialog>
