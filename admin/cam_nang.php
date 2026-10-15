<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_cam_nang.php';
admin_require_login();

$filters = admin_handbook_filters($_GET);
$categories = admin_handbook_categories(db());
$paging = admin_handbook_page(db(), $filters);
$adminPageTitle = 'Admin • Cẩm nang';
$adminHeaderTitle = 'Cẩm nang';
$adminHeaderSubtitle = 'Quản lý bài viết, ngôn ngữ và trạng thái xuất bản';
$adminActive = 'cam-nang';
require __DIR__ . '/_layout_start.php';
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<div class="card border-0 shadow-soft">
  <div class="card-body p-3 p-md-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <h1 class="h5 mb-1">Bài viết Cẩm nang</h1>
        <p class="text-secondary mb-0">Bài đã xuất bản sẽ hiển thị tại mục Cẩm nang trên website.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="/cam-nang" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-2" aria-hidden="true"></i>Xem Cẩm nang</a>
        <?php foreach (['vi' => 'Thêm bài tiếng Việt', 'en' => 'Thêm bài tiếng Anh'] as $language => $label): ?>
          <?php $categoryId = admin_handbook_category_id($categories, $language); ?>
          <?php if ($categoryId > 0): ?>
            <a class="btn <?php echo $language === 'vi' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/admin/content_post_edit.php?handbook=1&amp;language=<?php echo $language; ?>&amp;category_id=<?php echo $categoryId; ?>"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i><?php echo $label; ?></a>
          <?php else: ?>
            <button class="btn btn-outline-secondary" type="button" disabled aria-describedby="handbook-category-<?php echo $language; ?>"><?php echo $label; ?></button>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <?php foreach (['vi' => 'blog', 'en' => 'blog-en'] as $language => $slug): ?>
      <?php if (admin_handbook_category_id($categories, $language) === 0): ?>
        <div class="alert alert-warning" id="handbook-category-<?php echo $language; ?>">
          Chưa có chuyên mục cho bài <?php echo $language === 'vi' ? 'tiếng Việt' : 'tiếng Anh'; ?>.
          <a href="/admin/content_category_edit.php?language=<?php echo $language; ?>">Tạo chuyên mục</a> với slug <code><?php echo $slug; ?></code>, rồi quay lại để thêm bài. Trang này không tự tạo chuyên mục.
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <form method="get" action="/admin/cam_nang.php" class="row g-3 align-items-end mb-4" role="search">
      <div class="col-12 col-lg-5">
        <label class="form-label" for="handbook-search">Tìm bài viết</label>
        <input class="form-control" type="search" id="handbook-search" name="q" value="<?php echo $escape($filters['q']); ?>" placeholder="Tiêu đề, slug hoặc mô tả" maxlength="160">
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label" for="handbook-status">Trạng thái</label>
        <select class="form-select" id="handbook-status" name="status">
          <option value="">Tất cả trạng thái</option>
          <option value="published" <?php echo $filters['status'] === 'published' ? 'selected' : ''; ?>>Đã xuất bản</option>
          <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Bản nháp</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label" for="handbook-language">Ngôn ngữ</label>
        <select class="form-select" id="handbook-language" name="language">
          <option value="">Tất cả ngôn ngữ</option>
          <option value="vi" <?php echo $filters['language'] === 'vi' ? 'selected' : ''; ?>>Tiếng Việt</option>
          <option value="en" <?php echo $filters['language'] === 'en' ? 'selected' : ''; ?>>English</option>
        </select>
      </div>
      <div class="col-12 col-lg-3 d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-2" aria-hidden="true"></i>Lọc bài viết</button>
        <a class="btn btn-outline-secondary" href="/admin/cam_nang.php">Đặt lại</a>
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
