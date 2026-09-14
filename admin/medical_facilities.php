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
        $stmt = $pdo->prepare('DELETE FROM medical_facilities WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        flash_toast_set('success', 'Đã xoá cơ sở y tế.', 'fa-solid fa-circle-check');
        header('Location: /admin/medical_facilities.php');
        exit;
    }
}

$adminPageTitle = 'Admin • Quản lý cơ sở y tế';
$adminHeaderTitle = 'Quản lý cơ sở y tế';
$adminHeaderSubtitle = 'Danh sách dữ liệu đang dùng cho frontend cơ sở y tế';
$adminActive = 'medical-facilities';
require __DIR__ . '/_layout_start.php';
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <div class="h5 mb-1">Cơ sở y tế</div>
            <div class="text-secondary">Quản lý danh sách, nội dung chi tiết và dữ liệu hiển thị frontend.</div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars(admin_url('medical_facility_edit.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-plus me-2"></i>Thêm cơ sở y tế</a>
          </div>
        </div>

        <form class="row g-2 mb-3" id="medicalFacilitiesSearchForm" role="search">
          <div class="col-md-7 col-lg-6">
            <label class="visually-hidden" for="medicalFacilitiesSearch">Tìm kiếm cơ sở y tế</label>
            <input class="form-control" id="medicalFacilitiesSearch" type="search" placeholder="Tìm tên, slug, nhóm, khu vực hoặc địa chỉ..." autocomplete="off">
          </div>
          <div class="col-auto"><button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-2"></i>Tìm kiếm</button></div>
        </form>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th style="width:72px;">#</th>
                <th>Tên</th>
                <th>Nhóm</th>
                <th>Khu vực</th>
                <th>Điểm</th>
                <th>Review</th>
                <th>Trạng thái</th>
                <th>Cập nhật</th>
                <th class="text-end">Thao tác</th>
              </tr>
            </thead>
            <tbody id="medicalFacilitiesTbody">
              <tr><td colspan="9" class="text-center text-secondary py-4">Đang tải danh sách cơ sở y tế...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2" id="medicalFacilitiesPager" aria-live="polite"></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var tbody = document.getElementById('medicalFacilitiesTbody');
  var pager = document.getElementById('medicalFacilitiesPager');
  var searchForm = document.getElementById('medicalFacilitiesSearchForm');
  var searchInput = document.getElementById('medicalFacilitiesSearch');
  if (!tbody || !pager || !searchForm || !searchInput) return;

  var initialParams = new URLSearchParams(window.location.search);
  var page = Math.max(1, Number(initialParams.get('page') || 1));
  var query = initialParams.get('q') || '';
  var limit = 20;
  var endpoint = '/admin/api/medical/facilities_list.php';
  var detailUrl = <?= json_encode(site_url('co-so-y-te-chi-tiet.php?slug='), JSON_UNESCAPED_SLASHES) ?>;
  var editUrl = <?= json_encode(admin_url('medical_facility_edit.php?id='), JSON_UNESCAPED_SLASHES) ?>;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char];
    });
  }
  function number(value, decimals) {
    return new Intl.NumberFormat('vi-VN', {minimumFractionDigits: decimals || 0, maximumFractionDigits: decimals || 0}).format(Number(value || 0));
  }
  function renderPager(data) {
    var totalPages = Number(data.total_pages || 1);
    var current = Number(data.page || 1);
    var total = Number(data.total || 0);
    if (total === 0) { pager.innerHTML = ''; return; }
    pager.innerHTML = '<span class="small text-secondary">Hiển thị ' + number((current - 1) * limit + 1) + '–' + number(Math.min(current * limit, total)) + ' / ' + number(total) + ' cơ sở</span>' +
      '<div class="d-flex align-items-center gap-2">' +
      '<button class="btn btn-sm btn-outline-secondary" type="button" data-page="' + (current - 1) + '" ' + (current <= 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left me-1"></i>Trước</button>' +
      '<span class="small text-secondary">Trang <strong>' + current + '</strong> / ' + totalPages + '</span>' +
      '<button class="btn btn-sm btn-outline-secondary" type="button" data-page="' + (current + 1) + '" ' + (current >= totalPages ? 'disabled' : '') + '>Sau<i class="fa-solid fa-chevron-right ms-1"></i></button>' +
      '</div>';
    pager.querySelectorAll('button[data-page]').forEach(function (button) {
      button.addEventListener('click', function () { load(Number(button.dataset.page || 1)); });
    });
  }
  function renderRows(items) {
    if (!Array.isArray(items) || items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">' + (query ? 'Không tìm thấy cơ sở phù hợp.' : 'Chưa có cơ sở y tế nào.') + '</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(function (row) {
      var status = String(row.status || '');
      return '<tr>' +
        '<td>' + number(row.display_order) + '</td>' +
        '<td><div class="fw-semibold">' + escapeHtml(row.name) + '</div><div class="small text-secondary mono">' + escapeHtml(row.slug) + '</div></td>' +
        '<td>' + escapeHtml(row.category) + '</td><td>' + escapeHtml(row.city) + '</td>' +
        '<td>' + number(row.rating, 1) + '</td><td>' + number(row.reviews_count) + '</td>' +
        '<td><span class="badge ' + (status === 'published' ? 'text-bg-success' : 'text-bg-secondary') + '">' + escapeHtml(status) + '</span></td>' +
        '<td class="small text-secondary">' + escapeHtml(row.updated_at) + '</td>' +
        '<td><div class="d-flex gap-2 justify-content-end">' +
          '<a class="btn btn-sm btn-outline-secondary" href="' + detailUrl + encodeURIComponent(row.slug || '') + '" target="_blank">Xem</a>' +
          '<a class="btn btn-sm btn-outline-primary" href="' + editUrl + encodeURIComponent(row.id || 0) + '">Sửa</a>' +
          '<form method="post" onsubmit="return confirm(\'Xoá cơ sở y tế này?\');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + escapeHtml(row.id) + '"><button class="btn btn-sm btn-outline-danger" type="submit">Xoá</button></form>' +
        '</div></td></tr>';
    }).join('');
  }
  async function load(nextPage) {
    if (!Number.isFinite(nextPage) || nextPage < 1) return;
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Đang tải...</td></tr>';
    try {
      var requestUrl = endpoint + '?page=' + encodeURIComponent(nextPage) + '&limit=' + limit;
      if (query) requestUrl += '&q=' + encodeURIComponent(query);
      var response = await fetch(requestUrl, {headers: {Accept: 'application/json'}});
      var data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tải danh sách.');
      page = Number(data.paging.page || 1);
      var url = new URL(window.location.href); url.searchParams.set('page', page); if (query) url.searchParams.set('q', query); else url.searchParams.delete('q'); window.history.replaceState({}, '', url);
      renderRows(data.items); renderPager(data.paging || {});
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Không thể tải danh sách. Vui lòng thử lại.</td></tr>';
      pager.innerHTML = '';
    }
  }
  searchInput.value = query;
  searchForm.addEventListener('submit', function (event) {
    event.preventDefault();
    query = searchInput.value.trim();
    load(1);
  });
  load(page);
});
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
