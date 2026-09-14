<?php
declare(strict_types=1);

$mediaGalleryId = isset($mediaGalleryId) ? (string) $mediaGalleryId : 'gallery';
$mediaGalleryLabel = isset($mediaGalleryLabel) ? (string) $mediaGalleryLabel : 'Gallery';
$mediaGalleryName = isset($mediaGalleryName) ? (string) $mediaGalleryName : 'gallery_lines';
$mediaGalleryValue = isset($mediaGalleryValue) ? (string) $mediaGalleryValue : '';

$idPrefix = preg_replace('/[^a-zA-Z0-9_]+/', '_', $mediaGalleryId) ?? 'gallery';
$textareaId = $idPrefix . '_lines';
$uploadId = $idPrefix . '_upload';
?>

<div class="col-12" data-media-gallery-field="<?php echo htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8'); ?>">
  <label class="form-label" for="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mediaGalleryLabel, ENT_QUOTES, 'UTF-8'); ?></label>
  <textarea id="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($mediaGalleryName, ENT_QUOTES, 'UTF-8'); ?>" class="form-control mono d-none" rows="4"><?php echo htmlspecialchars($mediaGalleryValue, ENT_QUOTES, 'UTF-8'); ?></textarea>

  <div class="border rounded-4 bg-white p-3">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <label class="btn btn-outline-secondary mb-0">
        <i class="fa-solid fa-upload me-2" aria-hidden="true"></i>Upload
        <input id="<?php echo htmlspecialchars($uploadId, ENT_QUOTES, 'UTF-8'); ?>" type="file" accept="image/*" multiple hidden>
      </label>
      <button class="btn btn-outline-primary" type="button" data-open-media-library-multi="1">
        <i class="fa-solid fa-images me-2" aria-hidden="true"></i>Thư viện
      </button>
      <button class="btn btn-outline-danger" type="button" data-clear-gallery="1">
        <i class="fa-solid fa-xmark me-2" aria-hidden="true"></i>Xoá
      </button>
    </div>

    <div class="row g-2" data-gallery-grid="1"></div>
    <div class="text-secondary small mt-2" data-gallery-empty="1">Chưa có ảnh trong gallery.</div>
  </div>
</div>

