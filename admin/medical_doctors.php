<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../medical_directory.php';

admin_require_login();

$pdo = db();
medical_directory_ensure_tables($pdo);
medical_directory_seed_defaults($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM medical_doctors WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        flash_toast_set('success', 'Đã xoá bác sĩ.', 'fa-solid fa-circle-check');
        header('Location: ' . admin_url('medical_doctors.php'));
        exit;
    }
}

$rows = $pdo->query('SELECT id, slug, name, title_text, facility_name, city, rating, reviews_count, status, display_order, updated_at FROM medical_doctors ORDER BY display_order ASC, id DESC')->fetchAll();

$adminPageTitle = 'Admin • Quản lý bác sĩ';
$adminHeaderTitle = 'Quản lý bác sĩ';
$adminHeaderSubtitle = 'Danh sách hồ sơ bác sĩ dùng cho frontend MedReview';
$adminActive = 'medical-doctors';
require __DIR__ . '/_layout_start.php';
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <div class="h5 mb-1">Bác sĩ</div>
            <div class="text-secondary">Quản lý hồ sơ bác sĩ, chuyên môn nổi bật và liên kết cơ sở y tế.</div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars(admin_url('medical_doctor_edit.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-plus me-2"></i>Thêm bác sĩ</a>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th style="width:72px;">#</th>
                <th>Bác sĩ</th>
                <th>Chức danh</th>
                <th>Cơ sở</th>
                <th>Khu vực</th>
                <th>Điểm</th>
                <th>Review</th>
                <th>Trạng thái</th>
                <th>Cập nhật</th>
                <th class="text-end">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?php echo (int) ($row['display_order'] ?? 0); ?></td>
                  <td>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-secondary mono"><?php echo htmlspecialchars((string) ($row['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  </td>
                  <td><?php echo htmlspecialchars((string) ($row['title_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($row['facility_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($row['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(number_format((float) ($row['rating'] ?? 0), 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo number_format((int) ($row['reviews_count'] ?? 0), 0, ',', '.'); ?></td>
                  <td>
                    <span class="badge <?php echo ((string) ($row['status'] ?? '') === 'published') ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                      <?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </td>
                  <td class="small text-secondary"><?php echo htmlspecialchars((string) ($row['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <div class="d-flex gap-2 justify-content-end">
                      <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(site_url('bac-si-chi-tiet.php') . '?slug=' . rawurlencode((string) ($row['slug'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Xem</a>
                      <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(admin_url('medical_doctor_edit.php') . '?id=' . (int) ($row['id'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">Sửa</a>
                      <form method="post" onsubmit="return confirm('Xoá bác sĩ này?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Xoá</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr>
                  <td colspan="10" class="text-center text-secondary py-4">Chưa có bác sĩ nào.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
