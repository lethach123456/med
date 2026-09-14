<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    flash_toast_set('danger', 'Thiếu chuyên mục sản phẩm.', 'fa-solid fa-triangle-exclamation');
    header('Location: /admin/content.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, slug FROM product_categories WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$category = $stmt->fetch();
if (!$category) {
    flash_toast_set('danger', 'Chuyên mục sản phẩm không tồn tại.', 'fa-solid fa-triangle-exclamation');
    header('Location: /admin/content.php');
    exit;
}

$itemsStmt = $pdo->prepare(
    "SELECT id, name, slug, featured_image_url, status, price_int, price_text, created_at, updated_at
     FROM products
     WHERE category_id = :cid
     ORDER BY updated_at DESC, id DESC"
);
$itemsStmt->execute([':cid' => $id]);
$items = $itemsStmt->fetchAll();

function admin_price_text($priceInt, $priceText): string {
    if ($priceInt !== null && $priceInt !== '') {
        return number_format((int) $priceInt, 0, ',', '.') . ' đ';
    }
    $t = (string) ($priceText ?? '');
    return $t !== '' ? $t : 'Liên hệ';
}

$adminPageTitle = 'Admin • Chuyên mục sản phẩm';
$adminHeaderTitle = 'Chuyên mục sản phẩm';
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
            <a class="btn btn-outline-primary" href="/admin/content_product_category_edit.php?id=<?php echo $id; ?>"><i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>Sửa chuyên mục</a>
            <a class="btn btn-primary" href="/admin/content_product_edit.php?category_id=<?php echo $id; ?>"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Thêm sản phẩm</a>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 90px;">ID</th>
                <th>Sản phẩm</th>
                <th style="width:160px;">Giá</th>
                <th style="width:140px;">Trạng thái</th>
                <th style="width:180px;">Cập nhật</th>
                <th style="width:200px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!is_array($items) || count($items) === 0): ?>
                <tr><td colspan="6" class="text-secondary">Chưa có sản phẩm trong chuyên mục này.</td></tr>
              <?php else: ?>
                <?php foreach ($items as $p): ?>
                  <tr>
                    <td class="text-secondary mono"><?php echo (int) $p['id']; ?></td>
                    <td>
                      <div class="d-flex align-items-center gap-3">
                        <?php $img = (string) ($p['featured_image_url'] ?? ''); ?>
                        <?php if ($img !== ''): ?>
                          <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:10px;border:1px solid rgba(0,0,0,.08);" loading="lazy">
                        <?php else: ?>
                          <div style="width:44px;height:44px;border-radius:10px;border:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.04);"></div>
                        <?php endif; ?>
                        <div>
                          <div class="fw-semibold"><?php echo htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                          <div class="small text-secondary mono"><?php echo htmlspecialchars((string) $p['slug'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="fw-semibold"><?php echo htmlspecialchars(admin_price_text($p['price_int'] ?? null, $p['price_text'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td>
                      <?php if (($p['status'] ?? '') === 'published'): ?>
                        <span class="badge text-bg-success">published</span>
                      <?php else: ?>
                        <span class="badge text-bg-secondary">draft</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-secondary small mono">
                      <?php echo htmlspecialchars((string) ($p['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-2">
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/san-pham/<?php echo htmlspecialchars(rawurlencode((string) ($p['slug'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-up-right-from-square me-2" aria-hidden="true"></i>Xem</a>
                        <a class="btn btn-sm btn-outline-primary" href="/admin/content_product_edit.php?id=<?php echo (int) $p['id']; ?>"><i class="fa-solid fa-pen-to-square me-2" aria-hidden="true"></i>Sửa</a>
                        <button class="btn btn-sm btn-outline-danger" type="button" data-delete-type="product" data-delete-id="<?php echo (int) $p['id']; ?>"><i class="fa-solid fa-trash me-2" aria-hidden="true"></i>Xoá</button>
                      </div>
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

<script>
  (function(){
    async function postJson(url, payload) {
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload || {})
      });
      const data = await res.json().catch(() => ({}));
      return { res, data };
    }
    document.querySelectorAll('[data-delete-type][data-delete-id]').forEach(function(btn){
      btn.addEventListener('click', async function(){
        const type = btn.getAttribute('data-delete-type');
        const id = Number(btn.getAttribute('data-delete-id') || 0);
        if (!type || !id) return;
        if (!confirm('Xoá mục này?')) return;
        const { res, data } = await postJson('/admin/api/content/delete.php', { type, id });
        if (!res.ok || !data.ok) {
          if (window.adminToast) window.adminToast('danger', data.message || 'Xoá thất bại.');
          else alert(data.message || 'Xoá thất bại.');
          return;
        }
        if (window.adminToast) window.adminToast('success', data.message || 'Đã xoá.');
        location.reload();
      });
    });
  })();
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
