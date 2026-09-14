<?php
declare(strict_types=1);

$mediaFieldId = isset($mediaFieldId) ? (string) $mediaFieldId : 'featured_image';
$mediaFieldLabel = isset($mediaFieldLabel) ? (string) $mediaFieldLabel : 'Hình ảnh';
$mediaFieldName = isset($mediaFieldName) ? (string) $mediaFieldName : 'featured_image_url';
$mediaFieldValue = isset($mediaFieldValue) ? (string) $mediaFieldValue : '';

$idPrefix = preg_replace('/[^a-zA-Z0-9_]+/', '_', $mediaFieldId) ?? 'featured_image';
$inputId = $idPrefix . '_url';
$uploadId = $idPrefix . '_upload';
$previewId = $idPrefix . '_preview';
?>

<div class="col-12" data-media-image-field="<?php echo htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8'); ?>">
  <label class="form-label" for="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mediaFieldLabel, ENT_QUOTES, 'UTF-8'); ?></label>
  <div class="d-flex flex-column flex-md-row align-items-start gap-3">
    <div class="border rounded-4 bg-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 110px; height: 110px; overflow:hidden;">
      <img id="<?php echo htmlspecialchars($previewId, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;" src="<?php echo htmlspecialchars($mediaFieldValue, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="flex-grow-1">
      <input id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($mediaFieldName, ENT_QUOTES, 'UTF-8'); ?>" class="form-control mono" value="<?php echo htmlspecialchars($mediaFieldValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="/uploads/library/... hoặc URL ảnh">
      <div class="d-flex flex-wrap gap-2 mt-2">
        <label class="btn btn-outline-secondary mb-0">
          <i class="fa-solid fa-upload me-2" aria-hidden="true"></i>Upload
          <input id="<?php echo htmlspecialchars($uploadId, ENT_QUOTES, 'UTF-8'); ?>" type="file" accept="image/*" hidden>
        </label>
        <button class="btn btn-outline-primary" type="button" data-open-media-library="1">
          <i class="fa-solid fa-images me-2" aria-hidden="true"></i>Thư viện
        </button>
        <button class="btn btn-outline-danger" type="button" data-clear-image="1">
          <i class="fa-solid fa-xmark me-2" aria-hidden="true"></i>Xoá
        </button>
      </div>
      <div class="form-text">Chọn ảnh từ thư viện hoặc upload để dùng lại sau.</div>
    </div>
  </div>
</div>
