<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    flash_toast_set('danger', 'Thiếu chuyên mục.', 'fa-solid fa-triangle-exclamation');
    header('Location: /admin/content.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, slug FROM categories WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$category = $stmt->fetch();
if (!$category) {
    flash_toast_set('danger', 'Chuyên mục không tồn tại.', 'fa-solid fa-triangle-exclamation');
    header('Location: /admin/content.php');
    exit;
}

$posts = $pdo->prepare(
    "SELECT id, title, slug, status, updated_at
     FROM posts
     WHERE category_id = :cid
     ORDER BY updated_at DESC, id DESC"
);
$posts->execute([':cid' => $id]);
$items = $posts->fetchAll();

$adminPageTitle = 'Admin • Chuyên mục';
$adminHeaderTitle = 'Chuyên mục';
$adminHeaderSubtitle = (string) $category['name'];
$adminActive = 'content';
require __DIR__ . '/_layout_start.php';
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <div class="h5 mb-1"><?php echo htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="text-secondary small"><?php echo htmlspecialchars((string) $category['slug'], ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="/admin/content.php"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Quay lại</a>
            <a class="btn btn-outline-primary" href="/admin/content_category_edit.php?id=<?php echo $id; ?>"><i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>Sửa chuyên mục</a>
            <a class="btn btn-primary" href="/admin/content_post_edit.php?category_id=<?php echo $id; ?>"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Thêm trang</a>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Tiêu đề</th>
                <th style="width:120px;">Trạng thái</th>
                <th style="width:140px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!is_array($items) || count($items) === 0): ?>
                <tr><td colspan="3" class="text-secondary">Chưa có trang trong chuyên mục này.</td></tr>
              <?php else: ?>
                <?php foreach ($items as $p): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo htmlspecialchars((string) $p['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="small text-secondary"><?php echo htmlspecialchars((string) $p['slug'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td>
                      <?php if (($p['status'] ?? '') === 'published'): ?>
                        <span class="badge text-bg-success">published</span>
                      <?php else: ?>
                        <span class="badge text-bg-secondary">draft</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-primary" href="/admin/content_post_edit.php?id=<?php echo (int) $p['id']; ?>">Quản lý</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>

