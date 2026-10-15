<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_cam_nang.php';
require_once __DIR__ . '/_cam_nang_import.php';
admin_require_login();

$importError = '';
$importJson = '';
$importLanguage = 'vi';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $importJson = is_string($_POST['titles_json'] ?? null) ? $_POST['titles_json'] : '';
    $importLanguage = ($_POST['default_language'] ?? null) === 'en' ? 'en' : 'vi';
    try {
        $_SESSION['handbook_import_result'] = admin_handbook_import_request(db(), $_POST, admin_csrf_token(), ['has_template' => db_has_column(db(), 'posts', 'template')]);
        header('Location: /admin/cam_nang.php');
        exit;
    } catch (InvalidArgumentException $error) {
        $importError = $error->getMessage();
    } catch (Throwable $error) {
        error_log('Handbook title import failed (' . get_class($error) . ').');
        $importError = 'Không xác nhận được kết quả nhập. Tải lại danh sách để kiểm tra trước khi thử lại.';
    }
}
$importResult = $_SESSION['handbook_import_result'] ?? null;
unset($_SESSION['handbook_import_result']);
$filters = admin_handbook_filters($_GET);
$paging = admin_handbook_page(db(), $filters);
$adminPageTitle = 'Admin • Cẩm nang';
$adminHeaderTitle = 'Cẩm nang';
$adminHeaderSubtitle = 'Bài viết sức khỏe';
$adminActive = 'cam-nang';
require __DIR__ . '/_layout_start.php';
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<div class="card border-0 shadow-soft">
  <div class="card-body p-3 p-md-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <h1 class="h5 mb-1">Cẩm nang</h1>
        <p class="small text-secondary mb-0"><?php echo (int)$paging['total']; ?> bài viết</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#handbook-import" aria-controls="handbook-import" aria-expanded="<?php echo $importError!==''?'true':'false'; ?>"><i class="fa-solid fa-file-import me-2" aria-hidden="true"></i>Nhập JSON</button>
        <a class="btn btn-primary" href="/admin/content_post_edit.php?handbook=1"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Thêm bài</a>
      </div>
    </div>

    <?php if(is_array($importResult)): ?>
      <div class="alert alert-success" role="status">
        Đã tạo <?php echo count($importResult['created']); ?> bài nháp. Bỏ qua <?php echo count($importResult['skipped']); ?> tiêu đề trùng cùng ngôn ngữ. Không ghi đè bài có sẵn.
        <?php if($importResult['skipped']!==[]): ?><details class="mt-2"><summary>Xem tiêu đề đã bỏ qua</summary><ul class="mb-0 mt-2"><?php foreach($importResult['skipped']as$item): ?><li><?php echo $escape($item['title']); ?> (<?php echo $escape($item['language']); ?>) — <a href="/admin/content_post_edit.php?handbook=1&amp;id=<?php echo(int)$item['id']; ?>">Mở bài</a></li><?php endforeach; ?></ul></details><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="collapse<?php echo $importError!==''?' show':''; ?> mb-4" id="handbook-import">
      <form method="post" action="/admin/cam_nang.php" class="border rounded-4 p-3" data-handbook-import>
        <input type="hidden" name="action" value="import_titles">
        <input type="hidden" name="csrf_token" value="<?php echo $escape(admin_csrf_token()); ?>">
        <h2 class="h6">Nhập tiêu đề hàng loạt</h2>
        <p class="small text-secondary">Tạo tối đa 100 bài nháp/lần, nội dung rỗng để AI viết tiếp. Tiêu đề trùng cùng ngôn ngữ sẽ được bỏ qua.</p>
        <div class="mb-3">
          <label class="form-label" for="handbook-import-language">Ngôn ngữ mặc định</label>
          <select class="form-select" id="handbook-import-language" name="default_language"><option value="vi" <?php echo $importLanguage==='vi'?'selected':''; ?>>Tiếng Việt</option><option value="en" <?php echo $importLanguage==='en'?'selected':''; ?>>English</option></select>
        </div>
        <label class="form-label" for="handbook-import-json">Danh sách JSON</label>
        <textarea class="form-control font-monospace<?php echo $importError!==''?' is-invalid':''; ?>" id="handbook-import-json" name="titles_json" rows="7" maxlength="131072" required spellcheck="false" aria-describedby="handbook-import-help<?php echo $importError!==''?' handbook-import-error':''; ?>" <?php echo $importError!==''?'aria-invalid="true"':''; ?>><?php echo $escape(mb_substr($importJson,0,131072)); ?></textarea>
        <?php if($importError!==''): ?><div class="invalid-feedback d-block" id="handbook-import-error" role="alert" tabindex="-1" data-handbook-import-error><?php echo $escape($importError); ?></div><?php endif; ?>
        <div class="form-text" id="handbook-import-help">Dán mảng chuỗi tiêu đề, hoặc mảng đối tượng có title và language (vi/en). Nếu có mục sai, toàn bộ lần nhập chưa được lưu.</div>
        <details class="mt-3"><summary class="small text-primary">Xem mẫu JSON</summary><pre class="small bg-light rounded-3 p-3 mt-2 mb-0" style="white-space:pre-wrap">[
  "Cách chăm sóc răng miệng hằng ngày",
  {"title": "Khi nào nên đi khám mắt?", "language": "vi"},
  {"title": "Daily dental care tips", "language": "en"}
]</pre></details>
        <button class="btn btn-primary mt-3" type="submit" data-handbook-import-submit><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Tạo bài nháp</button>
      </form>
    </div>

    <form method="get" action="/admin/cam_nang.php" class="row g-2 align-items-end mb-4" role="search">
      <div class="col-12 col-sm">
        <label class="form-label" for="handbook-search">Tìm bài viết</label>
        <input class="form-control" type="search" id="handbook-search" name="q" value="<?php echo $escape($filters['q']); ?>" placeholder="Tiêu đề, slug hoặc mô tả" maxlength="160">
      </div>
      <div class="col-12 col-sm-auto d-flex gap-2">
        <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass me-2" aria-hidden="true"></i>Tìm</button>
        <?php if($filters['q']!==''||$filters['language']!==''||$filters['status']!==''):?><a class="btn btn-outline-secondary" href="/admin/cam_nang.php">Tất cả</a><?php endif;?>
      </div>
    </form>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <h2 class="h6 mb-0">Danh sách bài viết</h2>
      <div class="small text-secondary">Hiển thị <?php echo $paging['start']; ?>–<?php echo $paging['end']; ?> / <?php echo $paging['total']; ?> bài viết</div>
    </div>
    <div class="table-responsive border rounded-4">
      <table class="table table-hover align-middle mb-0">
        <caption class="visually-hidden">Bài viết Cẩm nang theo bộ lọc đã chọn</caption>
        <thead class="table-light">
          <tr><th scope="col">Tiêu đề</th><th scope="col">Ngôn ngữ</th><th scope="col">Trạng thái</th><th scope="col">Cập nhật</th><th scope="col"><span class="visually-hidden">Thao tác</span></th></tr>
        </thead>
        <tbody>
          <?php if ($paging['items'] === []): ?>
            <tr><td colspan="5" class="text-center py-5"><i class="fa-solid fa-book-open fa-2x text-secondary mb-3" aria-hidden="true"></i><div class="fw-semibold">Chưa có bài viết phù hợp.</div><div class="small text-secondary mt-1">Thêm bài viết mới hoặc thay đổi bộ lọc tìm kiếm.</div></td></tr>
          <?php else: ?>
            <?php foreach ($paging['items'] as $post): ?>
              <tr>
                <td style="min-width:220px;max-width:560px;overflow-wrap:anywhere;"><a class="fw-semibold text-decoration-none" href="/admin/content_post_edit.php?handbook=1&amp;id=<?php echo (int) $post['id']; ?>"><?php echo $escape($post['title']); ?></a><div class="small text-secondary mt-1"><?php echo $escape($post['slug']); ?></div></td>
                <td><?php echo $post['language'] === 'en' ? 'English' : 'Tiếng Việt'; ?></td>
                <td><span class="badge <?php echo $post['status'] === 'published' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $post['status'] === 'published' ? 'Đã xuất bản' : 'Bản nháp'; ?></span></td>
                <td class="small text-nowrap"><?php $updated = strtotime((string) $post['updated_at']); echo $updated !== false ? date('d/m/Y H:i', $updated) : $escape($post['updated_at']); ?></td>
                <td><div class="d-flex justify-content-end gap-2 text-nowrap">
                  <a class="btn btn-sm btn-primary" href="/admin/content_post_edit.php?handbook=1&amp;id=<?php echo (int) $post['id']; ?>" aria-label="<?php echo $escape('Sửa bài ' . $post['title']); ?>"><i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>Sửa</a>
                  <?php if ($post['status'] === 'published' && trim((string) $post['slug']) !== '' && (int) $post['has_public_content'] === 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo $escape(admin_handbook_public_url($post)); ?>" target="_blank" rel="noopener" aria-label="<?php echo $escape('Xem bài ' . $post['title']); ?>">Xem</a><?php endif; ?>
                </div></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($paging['pages'] > 1): ?>
      <nav class="mt-4" aria-label="Phân trang bài viết Cẩm nang">
        <ul class="pagination flex-wrap gap-1 mb-0">
          <?php if ($paging['page'] > 1): ?><li class="page-item"><a class="page-link" href="<?php echo $escape(admin_handbook_page_url($filters, $paging['page'] - 1)); ?>" aria-label="Trang trước">Trước</a></li><?php endif; ?>
          <?php $lastNumber = 0; foreach (array_unique(array_merge([1], range(max(1, $paging['page'] - 2), min($paging['pages'], $paging['page'] + 2)), [$paging['pages']])) as $number): ?>
            <?php if ($lastNumber > 0 && $number > $lastNumber + 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item <?php echo $number === $paging['page'] ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $escape(admin_handbook_page_url($filters, $number)); ?>" aria-label="Trang <?php echo $number; ?>" <?php echo $number === $paging['page'] ? 'aria-current="page"' : ''; ?>><?php echo $number; ?></a></li>
          <?php $lastNumber = $number; endforeach; ?>
          <?php if ($paging['page'] < $paging['pages']): ?><li class="page-item"><a class="page-link" href="<?php echo $escape(admin_handbook_page_url($filters, $paging['page'] + 1)); ?>" aria-label="Trang sau">Sau</a></li><?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
