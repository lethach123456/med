<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_admin();

$adminPageTitle = 'Admin • Gắn logo ảnh y tế';
$adminHeaderTitle = 'Gắn logo ảnh y tế';
$adminHeaderSubtitle = 'Đồng bộ dấu ấn MedReview cho ảnh cơ sở y tế và bác sĩ';
$adminActive = 'medical-image-branding';
$csrfToken = admin_csrf_token();
$brandingEscape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$brandingCssVersion = (string) (@filemtime(__DIR__ . '/../assets/css/pages/admin-image-branding.css') ?: '1');
$brandingJsVersion = (string) (@filemtime(__DIR__ . '/../assets/js/admin-image-branding.js') ?: '1');

require __DIR__ . '/_layout_start.php';
?>

<link rel="stylesheet" href="<?php echo $brandingEscape(site_url('assets/css/pages/admin-image-branding.css') . '?v=' . $brandingCssVersion); ?>">

<section class="image-branding" id="medicalImageBranding"
         data-endpoint="<?php echo $brandingEscape(admin_url('api/medical/image_branding.php')); ?>"
         data-csrf-token="<?php echo $brandingEscape($csrfToken); ?>">
  <div class="branding-hero">
    <div class="branding-hero-copy">
      <span class="branding-eyebrow"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> CÔNG CỤ ẢNH HÀNG LOẠT</span>
      <h1>Dấu ấn MedReview,<br>đồng bộ trên từng ảnh.</h1>
      <p>Gắn logo chữ <strong>MedReview</strong> vào góc trên bên phải của ảnh cơ sở y tế và bác sĩ. Quét danh sách trước, kiểm tra số lượng rồi bắt đầu xử lý.</p>
      <div class="branding-safety"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Giữ nguyên tệp ảnh gốc · Lưu bản có logo riêng</div>
    </div>
    <figure class="branding-preview">
      <div class="branding-preview-art" role="img" aria-label="Minh họa vị trí logo chữ MedReview ở góc trên bên phải của ảnh">
        <div class="branding-preview-building" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>
        <div class="branding-preview-tree branding-preview-tree-left" aria-hidden="true"></div>
        <div class="branding-preview-tree branding-preview-tree-right" aria-hidden="true"></div>
        <img class="branding-preview-wordmark" src="<?php echo $brandingEscape(site_url('assets/brand/medreview-wordmark.png')); ?>" alt="" width="2850" height="412">
        <span class="branding-preview-tag" aria-hidden="true"><i class="fa-solid fa-check" aria-hidden="true"></i> GÓC TRÊN BÊN PHẢI</span>
      </div>
      <figcaption>Minh họa vị trí logo · Tự điều chỉnh theo kích thước ảnh</figcaption>
    </figure>
  </div>

  <div class="branding-stats" aria-label="Thống kê ảnh trong phạm vi đã chọn">
    <article class="branding-stat">
      <span class="branding-stat-icon"><i class="fa-regular fa-images" aria-hidden="true"></i></span>
      <div><div class="branding-stat-number" id="brandingTotal">—</div><h2>Tổng ảnh đã thống kê</h2></div>
    </article>
    <article class="branding-stat branding-stat-done">
      <span class="branding-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
      <div><div class="branding-stat-number" id="brandingDone">—</div><h2>Đã gắn logo</h2></div>
    </article>
    <article class="branding-stat branding-stat-pending">
      <span class="branding-stat-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
      <div><div class="branding-stat-number" id="brandingPending">—</div><h2>Chưa gắn logo</h2></div>
    </article>
    <article class="branding-stat branding-stat-failed">
      <span class="branding-stat-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
      <div><div class="branding-stat-number" id="brandingFailed">—</div><h2>Ảnh cần thử lại</h2></div>
    </article>
  </div>

  <section class="branding-panel" aria-labelledby="brandingWorkflowTitle">
    <div class="branding-panel-heading">
      <div><h2 id="brandingWorkflowTitle">Xử lý ảnh</h2><p>Chỉ quét khi bạn yêu cầu. Trạng thái và kết quả được lưu để tiếp tục sau.</p></div>
      <span class="branding-state" id="brandingState"><span class="branding-state-dot" aria-hidden="true"></span><span id="brandingStateLabel">Đang tải</span></span>
    </div>

    <div class="branding-toolbar">
      <div class="branding-scope-field">
        <label for="brandingType">Phạm vi ảnh</label>
        <select class="form-select" id="brandingType" aria-describedby="brandingScopeNote" disabled>
          <option value="all">Tất cả ảnh y tế</option>
          <option value="facility">Cơ sở y tế</option>
          <option value="doctor">Bác sĩ</option>
        </select>
      </div>
      <div class="branding-action-group">
        <button type="button" class="btn btn-outline-primary" id="brandingScan" disabled><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> <span>1. Quét danh sách ảnh</span></button>
        <button type="button" class="btn btn-primary" id="brandingRun" disabled><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> <span>2. Gắn logo hàng loạt</span><span class="branding-button-count" id="brandingRunCount">0</span></button>
        <button type="button" class="btn btn-outline-secondary" id="brandingPause" hidden><i class="fa-solid fa-pause" aria-hidden="true"></i> <span>Tạm dừng</span></button>
      </div>
    </div>

    <p class="branding-scope-note" id="brandingScopeNote"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Nguồn: ảnh được tham chiếu trong cơ sở y tế / bác sĩ và thư viện ảnh y tế tương ứng. Bộ lọc trạng thái bên dưới chỉ đổi danh sách hiển thị.</span></p>

    <div class="branding-capability-warning" id="brandingCapabilities" hidden></div>
    <div class="branding-feedback" id="brandingFeedback" role="status" aria-live="polite" aria-atomic="true">Đang tải thống kê đã lưu…</div>

    <div class="branding-progress-meta"><span id="brandingProgressLabel">Tiến độ gắn logo</span><strong id="brandingProgressText">—</strong></div>
    <div class="branding-progress-track"><div class="branding-progress-fill" id="brandingProgress" role="progressbar" aria-label="Tiến độ gắn logo" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div></div>
    <div class="branding-progress-footer"><span id="brandingScanInfo">Chưa tải thông tin quét</span><span id="brandingProcessingInfo">Không có tác vụ đang chạy</span></div>
  </section>

  <section class="branding-panel branding-results" aria-labelledby="brandingResultsTitle">
    <div class="branding-panel-heading branding-results-heading">
      <div><h2 id="brandingResultsTitle">Danh sách ảnh</h2><p id="brandingListCaption">Ảnh trùng URL được thống kê một lần, kèm các hồ sơ liên quan.</p></div>
      <div class="branding-list-tools">
        <div class="branding-filter-field"><label for="brandingStatus" class="visually-hidden">Lọc theo trạng thái</label><select class="form-select" id="brandingStatus" disabled><option value="all">Tất cả trạng thái</option><option value="pending">Chưa gắn logo</option><option value="done">Đã gắn logo</option><option value="failed">Cần thử lại</option></select></div>
        <button type="button" class="btn btn-outline-secondary" id="brandingRefresh" disabled><i class="fa-solid fa-rotate" aria-hidden="true"></i> <span>Làm mới</span></button>
        <button type="button" class="btn btn-outline-danger" id="brandingRetry" disabled><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> <span>Thử lại ảnh lỗi</span></button>
      </div>
    </div>

    <div class="branding-table-wrap" id="brandingList" aria-busy="true">
      <table class="branding-table">
        <caption class="visually-hidden">Ảnh và tình trạng gắn logo trong phạm vi đã chọn</caption>
        <thead><tr><th scope="col">Ảnh</th><th scope="col">Nguồn sử dụng</th><th scope="col">Trạng thái</th><th scope="col">Xem ảnh</th></tr></thead>
        <tbody id="brandingRows"><tr class="branding-empty-row"><td colspan="4"><div class="branding-empty"><i class="fa-regular fa-images" aria-hidden="true"></i><strong>Đang tải danh sách ảnh…</strong><span>Chỉ đọc trạng thái đã lưu, không tự động quét.</span></div></td></tr></tbody>
      </table>
    </div>
    <div class="branding-pagination" id="brandingPager" aria-label="Phân trang danh sách ảnh"></div>
  </section>

  <div class="branding-notes">
    <p><i class="fa-solid fa-folder-open" aria-hidden="true"></i><span><strong>Ảnh gốc được giữ lại.</strong> Bản có logo lưu tại <code>/uploads/library/medreview-branded</code>; chỉ cập nhật các tham chiếu ảnh trong phạm vi y tế.</span></p>
    <p><i class="fa-solid fa-eye" aria-hidden="true"></i><span><strong>Số “đã gắn logo” dựa trên lịch sử của công cụ.</strong> Không dùng nhận diện hình ảnh để phát hiện logo có sẵn trong ảnh gốc.</span></p>
    <p><i class="fa-solid fa-pause" aria-hidden="true"></i><span><strong>Có thể tạm dừng an toàn.</strong> Công cụ hoàn tất yêu cầu hiện tại rồi dừng. Giữ trang này mở trong lúc quét hoặc gắn logo.</span></p>
  </div>
  <noscript><div class="alert alert-warning">Vui lòng bật JavaScript để quét danh sách và gắn logo hàng loạt.</div></noscript>
</section>

<script src="<?php echo $brandingEscape(site_url('assets/js/admin-image-branding.js') . '?v=' . $brandingJsVersion); ?>" defer></script>

<?php require __DIR__ . '/_layout_end.php'; ?>
