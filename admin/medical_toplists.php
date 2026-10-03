<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../medical_directory.php';
require_once __DIR__ . '/../toplist_directory.php';

admin_require_login();
$pdo = db();
medical_directory_ensure_tables($pdo);
toplist_directory_ensure_tables($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(admin_csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
    flash_toast_set('danger', 'Phiên biểu mẫu hết hạn. Tải lại trang rồi thử lại.');
    header('Location: /admin/medical_toplists.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM medical_toplists WHERE id = :id LIMIT 1')->execute([':id' => $id]);
        medical_search_cache_invalidate();
        flash_toast_set('success', 'Đã xoá bài Toplist.', 'fa-solid fa-circle-check');
    }
    header('Location: /admin/medical_toplists.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_json') {
    $rawJson = trim((string) ($_POST['toplist_json'] ?? ''));
    $rawJson = preg_replace('/\A```(?:json)?\s*\R([\s\S]*?)\R```\s*\z/i', '$1', $rawJson) ?? $rawJson;
    $payload = strlen($rawJson) <= 8 * 1024 * 1024 ? json_decode($rawJson, true) : null;
    $importMode = (string) ($_POST['json_mode'] ?? 'full');
    $fallbackType = in_array($_POST['json_entity_type'] ?? '', ['facility', 'doctor', 'mixed'], true) ? $_POST['json_entity_type'] : 'facility';

    if (!is_array($payload)) {
        flash_toast_set('danger', 'JSON không hợp lệ. Hãy kiểm tra lại cú pháp JSON.');
        header('Location: /admin/medical_toplists.php'); exit;
    }

    if ($importMode === 'title_only') {
        // Accept a single object, an array of objects, or {"toplists": [...]} for bulk drafts.
        $draftItems = [];
        if (isset($payload['toplists']) && is_array($payload['toplists'])) {
            $draftItems = $payload['toplists'];
        } elseif (isset($payload['items']) && is_array($payload['items'])) {
            $draftItems = $payload['items'];
        } elseif ($payload !== [] && array_keys($payload) === range(0, count($payload) - 1)) {
            $draftItems = $payload;
        } else {
            $draftItems = [$payload];
        }

        $validDrafts = [];
        foreach ($draftItems as $draftItem) {
            if (!is_array($draftItem)) continue;
            $title = trim((string) ($draftItem['title'] ?? $draftItem['tieu_de'] ?? ''));
            if ($title !== '') $validDrafts[] = $draftItem;
        }
        if ($validDrafts === []) {
            flash_toast_set('danger', 'JSON nhập nhanh cần có ít nhất một trường title.');
            header('Location: /admin/medical_toplists.php'); exit;
        }

        try {
            $pdo->beginTransaction();
            if (count($validDrafts) > 1000) throw new InvalidArgumentException('Tối đa 1.000 bài nháp mỗi lần.');
            foreach ($validDrafts as $draft) toplist_directory_import_article($pdo, $draft, $fallbackType, true);
            $pdo->commit();
            medical_search_cache_invalidate();
            flash_toast_set('success', 'Đã tạo ' . count($validDrafts) . ' bài Toplist nháp. Bạn có thể bổ sung nội dung và cơ sở hoặc bác sĩ sau.', 'fa-solid fa-circle-check');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash_toast_set('danger', 'Không thể nhập JSON: ' . $e->getMessage());
        }
    } else {
        try {
            $imported = toplist_directory_import_article($pdo, $payload, $fallbackType);
            flash_toast_set('success', 'Đã nhập Toplist với ' . count($imported['ids']) . ' hồ sơ, tạo mới ' . count($imported['created_ids']) . ' hồ sơ.', 'fa-solid fa-circle-check');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash_toast_set('danger', 'Không thể nhập JSON: ' . $e->getMessage());
        }
    }
    header('Location: /admin/medical_toplists.php'); exit;
}

$adminPageTitle = 'Admin • Toplist y tế';
$adminHeaderTitle = 'Toplist y tế';
$adminHeaderSubtitle = 'Tạo bài xếp hạng và quản lý cơ sở hoặc bác sĩ xuất hiện trong từng bài';
$adminActive = 'medical-toplists';
require __DIR__ . '/_layout_start.php';
?>
<div class="card border-0 shadow-soft"><div class="card-body p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><div class="h5 mb-1">Danh sách Toplist</div><div class="text-secondary">Ví dụ: Top 5 nha khoa uy tín tại Đà Nẵng.</div></div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="/admin/medical_toplist_edit.php"><i class="fa-solid fa-plus me-2"></i>Tạo bài Toplist</a><a class="btn btn-outline-secondary" href="/admin/medical_ai_prompts.php"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Prompt AI</a><button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#importToplistJson"><i class="fa-solid fa-file-import me-2"></i>Nhập JSON</button></div>
  </div>
  <div class="collapse mb-4" id="importToplistJson">
    <div class="border rounded-4 bg-light p-3 p-md-4">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="import_json">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <div class="fw-semibold">Chọn dạng JSON cần nhập</div>
            <div class="small text-secondary">Bạn có thể tạo bài hoàn chỉnh hoặc tạo trước danh sách bài nháp.</div>
          </div>
          <span class="badge text-bg-light border">Slug được tạo tự động</span>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <input class="btn-check" type="radio" name="json_mode" id="jsonModeFull" value="full" checked>
            <label class="btn btn-outline-primary w-100 text-start h-100 p-3" for="jsonModeFull">
              <span class="d-block fw-semibold"><i class="fa-solid fa-list-check me-2"></i>Nhập đầy đủ</span>
              <span class="small text-secondary">Bài viết, nội dung, ảnh và danh sách cơ sở hoặc bác sĩ.</span>
            </label>
          </div>
          <div class="col-md-6">
            <input class="btn-check" type="radio" name="json_mode" id="jsonModeTitleOnly" value="title_only">
            <label class="btn btn-outline-primary w-100 text-start h-100 p-3" for="jsonModeTitleOnly">
              <span class="d-block fw-semibold"><i class="fa-solid fa-pen-to-square me-2"></i>Chỉ nhập tiêu đề</span>
              <span class="small text-secondary">Tạo một hoặc nhiều bài nháp để bổ sung sau.</span>
            </label>
          </div>
        </div>
        <label class="form-label fw-semibold" id="toplistJsonLabel" for="toplistJson">JSON bài Toplist và danh sách cơ sở</label>
        <div class="mb-3"><label class="form-label" for="jsonEntityType">Loại Toplist / mẫu JSON</label><select class="form-select" id="jsonEntityType" name="json_entity_type"><option value="mixed">Cơ sở y tế & bác sĩ</option><option value="facility">Chỉ cơ sở y tế</option><option value="doctor">Chỉ bác sĩ</option></select><div class="form-text">Dùng members để xếp hạng chung hai loại; cũng nhận cả facilities và doctors trong cùng JSON. Bài chỉ có tiêu đề dùng lựa chọn tại đây.</div></div>
        <textarea class="form-control mono" id="toplistJson" name="toplist_json" rows="9" placeholder="Dán JSON bài viết đầy đủ theo mẫu bên dưới..." required></textarea>
        <div class="small text-secondary mt-2" id="toplistJsonHelp">Dạng đầy đủ cần có title và danh sách facilities hoặc doctors. Dùng ID có sẵn để liên kết; hồ sơ chưa có sẽ được tạo mới.</div>
        <details class="mt-3">
          <summary class="small text-primary" style="cursor:pointer">Xem mẫu JSON theo dạng đã chọn</summary>
          <pre class="small bg-white border rounded-3 p-3 mt-2 mb-0" id="fullJsonSample" style="max-height:280px;overflow:auto;white-space:pre-wrap">{
  "title": "Top 5 cơ sở y tế uy tín tại Đà Nẵng",
  "excerpt": "Danh sách các cơ sở y tế được đánh giá tốt tại Đà Nẵng.",
  "content": "&lt;h2&gt;Top 5 cơ sở y tế uy tín tại Đà Nẵng&lt;/h2&gt;&lt;p&gt;Viết nội dung bài khoảng 300-500 từ, giới thiệu tiêu chí lựa chọn và thông tin hữu ích về các cơ sở trong danh sách.&lt;/p&gt;",
  "featured_image_url": "",
  "status": "published",
  "facilities": [{
    "name": "Phòng khám ABC Đà Nẵng",
    "category": "Cơ sở y tế",
    "city": "Đà Nẵng",
    "address": "123 Nguyễn Văn Linh, Hải Châu, Đà Nẵng",
    "phone": "0905123456",
    "website": "https://example.com"
  }]
}</pre>
          <pre class="small bg-white border rounded-3 p-3 mt-2 mb-0 d-none" id="titleOnlyJsonSample" style="max-height:280px;overflow:auto;white-space:pre-wrap">[
  { "title": "Top 5 cơ sở y tế uy tín tại Đà Nẵng" },
  { "title": "Top 10 phòng khám đáng tham khảo tại Huế" }
]</pre>
          <pre class="small bg-white border rounded-3 p-3 mt-2 mb-0 d-none" id="doctorJsonSample" style="max-height:280px;overflow:auto;white-space:pre-wrap">{
  "title": "Top bác sĩ chuyên khoa mắt tại Hà Nội đáng tham khảo",
  "entity_type": "doctor",
  "excerpt": "Danh sách bác sĩ có thông tin chuyên môn và nơi công tác công khai.",
  "content": "&lt;h2&gt;Tiêu chí tham khảo&lt;/h2&gt;&lt;p&gt;Đối chiếu chuyên môn và nơi khám từ nguồn chính thức, không cam kết kết quả điều trị.&lt;/p&gt;",
  "status": "draft",
  "doctors": [{
    "doctor_id": 0,
    "name": "Tên bác sĩ đã đối chiếu nguồn",
    "title_text": "Chức danh theo nguồn",
    "specialty_text": "Nhãn khoa",
    "city": "Hà Nội",
    "facility_name": "Tên cơ sở công tác theo nguồn",
    "rank_order": 1
  }]
}</pre>
        </details>
        <pre class="small bg-white border rounded-3 p-3 mt-2 mb-0 d-none" id="mixedJsonSample" style="max-height:280px;overflow:auto;white-space:pre-wrap">{
  "title": "Cơ sở và bác sĩ nha khoa tại Đà Nẵng đáng tham khảo",
  "entity_type": "mixed",
  "excerpt": "Đối chiếu hồ sơ cơ sở và chuyên môn bác sĩ trước khi lựa chọn.",
  "status": "draft",
  "members": [
    {"type": "facility", "facility_id": 0, "name": "Tên cơ sở theo nguồn", "city": "Đà Nẵng", "address": "Địa chỉ theo nguồn", "rank_order": 1},
    {"type": "doctor", "doctor_id": 0, "name": "Tên bác sĩ theo nguồn", "specialty_text": "Răng Hàm Mặt", "city": "Đà Nẵng", "facility_name": "Tên nơi công tác", "rank_order": 2}
  ]
}</pre>
        <details class="mt-3"><summary class="small text-primary">Prompt AI tạo JSON</summary><textarea readonly class="form-control mono mt-2" id="toplistImportPrompt" rows="6"></textarea><button class="btn btn-sm btn-outline-primary mt-2" type="button" id="copyToplistImportPrompt">Sao chép prompt</button></details>
        <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-cloud-arrow-up me-2"></i><span id="toplistJsonSubmitText">Nhập và tạo mới</span></button></div>
      </form>
    </div>
  </div>
  <form class="row g-2 mb-3" id="medicalToplistsSearchForm" role="search">
    <div class="col-md-7 col-lg-6">
      <label class="visually-hidden" for="medicalToplistsSearch">Tìm kiếm Toplist</label>
      <input class="form-control" id="medicalToplistsSearch" type="search" placeholder="Tìm tiêu đề hoặc slug bài Toplist..." autocomplete="off">
    </div>
    <div class="col-auto"><button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-2"></i>Tìm kiếm</button></div>
  </form>
  <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tiêu đề</th><th>Slug</th><th>Hồ sơ liên kết</th><th>Trạng thái</th><th>Cập nhật</th><th></th></tr></thead><tbody id="medicalToplistsTbody"><tr><td colspan="6" class="text-center text-secondary py-5">Đang tải danh sách Toplist...</td></tr></tbody></table></div>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3" id="medicalToplistsPager" aria-live="polite"></div>
</div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var fullMode = document.getElementById('jsonModeFull');
  var titleOnlyMode = document.getElementById('jsonModeTitleOnly');
  var label = document.getElementById('toplistJsonLabel');
  var textarea = document.getElementById('toplistJson');
  var help = document.getElementById('toplistJsonHelp');
  var submitText = document.getElementById('toplistJsonSubmitText');
  var fullSample = document.getElementById('fullJsonSample');
  var titleSample = document.getElementById('titleOnlyJsonSample');
  var doctorSample = document.getElementById('doctorJsonSample');
  var mixedSample = document.getElementById('mixedJsonSample');
  var entitySelect = document.getElementById('jsonEntityType');
  var importPrompt = document.getElementById('toplistImportPrompt');
  if (!fullMode || !titleOnlyMode || !label || !textarea || !help || !submitText || !fullSample || !titleSample) return;

  function updateJsonMode() {
    var isTitleOnly = titleOnlyMode.checked;
    var isDoctor = entitySelect.value === 'doctor';
    var isMixed = entitySelect.value === 'mixed';
    var subject = isMixed ? 'cơ sở và bác sĩ' : (isDoctor ? 'bác sĩ' : 'cơ sở');
    var arrayKey = isMixed ? 'members' : (isDoctor ? 'doctors' : 'facilities');
    label.textContent = isTitleOnly ? 'JSON danh sách bài Toplist (chỉ tiêu đề)' : 'JSON bài Toplist và danh sách ' + subject;
    textarea.placeholder = isTitleOnly
      ? 'Dán một bài {"title":"..."} hoặc danh sách [{"title":"..."}, ...]'
      : 'Dán JSON bài viết đầy đủ theo mẫu bên dưới...';
    help.innerHTML = isTitleOnly
      ? 'Mỗi bài chỉ cần <code>title</code>. Bài nháp sẽ dùng loại ' + subject + ' đã chọn hoặc <code>entity_type</code> của từng bài; bổ sung liên kết sau.'
      : 'Dạng đầy đủ cần <code>title</code> và <code>' + arrayKey + '</code>. ' + (isMixed ? 'Mỗi member có <code>type: facility/doctor</code> cùng <code>facility_id/doctor_id</code> tương ứng. Thứ hạng dùng chung cho cả hai loại. ' : '') + 'Dùng ID có sẵn để liên kết, không ghi đè hồ sơ. ID bằng 0 hoặc thiếu tạo hồ sơ cơ bản chưa xác minh; bác sĩ mới cần chuyên khoa và thành phố hoặc nơi công tác.';
    submitText.textContent = isTitleOnly ? 'Tạo bài nháp' : 'Nhập và tạo mới';
    fullSample.classList.toggle('d-none', isTitleOnly || isDoctor || isMixed);
    doctorSample.classList.toggle('d-none', isTitleOnly || !isDoctor);
    mixedSample.classList.toggle('d-none', isTitleOnly || !isMixed);
    titleSample.classList.toggle('d-none', !isTitleOnly);
    titleSample.textContent = JSON.stringify([{title: isDoctor ? 'Top bác sĩ chuyên khoa mắt tại Hà Nội' : 'Top cơ sở y tế tại Đà Nẵng', entity_type: entitySelect.value}], null, 2);
    importPrompt.value = 'Tạo ' + (isTitleOnly ? 'danh sách tiêu đề bài Toplist' : 'bài Toplist và danh sách') + ' về ' + subject + ' theo yêu cầu tôi cung cấp. ' +
      'Chỉ dùng dữ liệu công khai đã đối chiếu nguồn chính thức; không bịa danh tính, chuyên khoa, chức danh, nơi công tác, liên hệ, giá hoặc đánh giá. ' +
      'Không tự tạo ID: dùng ID tôi cung cấp; chưa có ID thì 0. Bài hỗn hợp có cả cơ sở và bác sĩ; mỗi member phải có type và ID tương ứng, không nhầm ID giữa hai loại. ' +
      'Giữ entity_type=' + entitySelect.value + ', rank_order bắt đầu từ 1 và xếp chung cả danh sách; trường không rõ để rỗng. ' +
      'Chỉ trả JSON hợp lệ trong một block code json, không lời dẫn bên ngoài. Cấu trúc mẫu (thay dữ liệu mẫu bằng thông tin thật):\n' +
      (isTitleOnly ? titleSample.textContent : (isMixed ? mixedSample.textContent : isDoctor ? doctorSample.textContent : fullSample.textContent));
  }

  fullMode.addEventListener('change', updateJsonMode);
  titleOnlyMode.addEventListener('change', updateJsonMode);
  entitySelect.addEventListener('change', updateJsonMode);
  document.getElementById('copyToplistImportPrompt').addEventListener('click', async function () {
    try { await navigator.clipboard.writeText(importPrompt.value); this.textContent = 'Đã sao chép'; }
    catch (_) { importPrompt.focus(); importPrompt.select(); this.textContent = 'Chọn prompt rồi sao chép'; }
  });
  updateJsonMode();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var tbody = document.getElementById('medicalToplistsTbody');
  var pager = document.getElementById('medicalToplistsPager');
  var searchForm = document.getElementById('medicalToplistsSearchForm');
  var searchInput = document.getElementById('medicalToplistsSearch');
  if (!tbody || !pager || !searchForm || !searchInput) return;

  var initialParams = new URLSearchParams(window.location.search);
  var page = Math.max(1, Number(initialParams.get('page') || 1));
  var query = initialParams.get('q') || '';
  var limit = 20;
  var endpoint = '/admin/api/medical/toplists_list.php';
  var editUrl = <?= json_encode(admin_url('medical_toplist_edit.php?id='), JSON_UNESCAPED_SLASHES) ?>;
  var csrf = <?= json_encode(admin_csrf_token()) ?>;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char];
    });
  }
  function number(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)); }
  function renderPager(data) {
    var totalPages = Number(data.total_pages || 1);
    var current = Number(data.page || 1);
    var total = Number(data.total || 0);
    if (total === 0) { pager.innerHTML = ''; return; }
    pager.innerHTML = '<span class="small text-secondary">Hiển thị ' + number((current - 1) * limit + 1) + '–' + number(Math.min(current * limit, total)) + ' / ' + number(total) + ' bài Toplist</span>' +
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
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-5">' + (query ? 'Không tìm thấy bài Toplist phù hợp.' : 'Chưa có bài Toplist nào.') + '</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(function (row) {
      var status = String(row.status || '');
      return '<tr>' +
        '<td class="fw-semibold">' + escapeHtml(row.title) + '</td>' +
        '<td class="text-secondary small mono">' + escapeHtml(row.slug) + '</td>' +
        '<td><span class="badge text-bg-light">' + (row.entity_type === 'mixed' ? number(row.facility_count) + ' cơ sở · ' + number(row.doctor_count) + ' bác sĩ' : number(row.entity_type === 'doctor' ? row.doctor_count : row.facility_count) + (row.entity_type === 'doctor' ? ' bác sĩ' : ' cơ sở')) + '</span></td>' +
        '<td><span class="badge ' + (status === 'published' ? 'text-bg-success' : 'text-bg-secondary') + '">' + escapeHtml(status) + '</span></td>' +
        '<td class="small text-secondary">' + escapeHtml(row.updated_at) + '</td>' +
        '<td><div class="d-flex gap-2 justify-content-end"><a class="btn btn-sm btn-primary" href="' + editUrl + encodeURIComponent(row.id || 0) + '">Sửa</a>' +
          '<form method="post" onsubmit="return confirm(\'Xoá bài Toplist này?\');"><input type="hidden" name="_csrf" value="' + escapeHtml(csrf) + '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + escapeHtml(row.id) + '"><button class="btn btn-sm btn-outline-danger" type="submit">Xoá</button></form></div></td>' +
        '</tr>';
    }).join('');
  }
  async function load(nextPage) {
    if (!Number.isFinite(nextPage) || nextPage < 1) return;
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Đang tải...</td></tr>';
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
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-5">Không thể tải danh sách. Vui lòng thử lại.</td></tr>';
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
