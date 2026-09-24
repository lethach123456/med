<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../medical_directory.php';
require_once __DIR__ . '/../toplist_directory.php';

admin_require_login();
$pdo = db();
medical_directory_ensure_tables($pdo);
toplist_directory_ensure_tables($pdo);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$values = ['title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'featured_image_url' => '', 'status' => 'draft'];
$selectedIds = [];
$toplistTranslationRow = null;

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM medical_toplists WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_toast_set('danger', 'Không tìm thấy bài Toplist.');
        header('Location: /admin/medical_toplists.php');
        exit;
    }
    $toplistTranslationRow = $row;
    foreach (array_keys($values) as $key) {
        $values[$key] = (string) ($row[$key] ?? '');
    }
    $stmt = $pdo->prepare('SELECT facility_id FROM medical_toplist_facilities WHERE toplist_id = :id ORDER BY rank_order ASC');
    $stmt->execute([':id' => $id]);
    $selectedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
$toplistLanguage = strtolower((string) ($toplistTranslationRow['language_code'] ?? 'vi')) === 'en' ? 'en' : 'vi';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_translation_action'] ?? '') === 'create_en') {
    try {
        $translationId = medical_directory_create_translation_copy($pdo, 'toplist', $id);
        flash_toast_set('success', 'Đã tạo bản tiếng Anh ở trạng thái nháp. Hãy dịch tiêu đề/nội dung rồi xuất bản.', 'fa-solid fa-language');
        header('Location: ' . admin_url('medical_toplist_edit.php') . '?id=' . $translationId);
        exit;
    } catch (Throwable $e) {
        flash_toast_set('danger', $e->getMessage(), 'fa-solid fa-triangle-exclamation');
        header('Location: ' . admin_url('medical_toplist_edit.php') . '?id=' . $id);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $selectedIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_POST['facility_order'] ?? ''))))));
    if ($toplistLanguage === 'en' && $selectedIds !== []) {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $languageStmt = $pdo->prepare("SELECT COUNT(*) FROM medical_facilities WHERE status = 'published' AND language_code = 'en' AND id IN ({$placeholders})");
        $languageStmt->execute($selectedIds);
        if ((int) $languageStmt->fetchColumn() !== count($selectedIds)) {
            $errors[] = 'Toplist tiếng Anh chỉ liên kết được với hồ sơ cơ sở tiếng Anh đã xuất bản.';
        }
    }
    if ($values['title'] === '') {
        $errors[] = 'Vui lòng nhập tiêu đề bài Toplist.';
    }
    $values['status'] = in_array($values['status'], ['draft', 'published'], true) ? $values['status'] : 'draft';
    $values['slug'] = unique_slug($pdo, 'medical_toplists', $values['slug'] !== '' ? $values['slug'] : $values['title'], $isEdit ? $id : null);

    if ($errors === []) {
        if ($isEdit) {
            $stmt = $pdo->prepare('UPDATE medical_toplists SET title=:title, slug=:slug, excerpt=:excerpt, content=:content, featured_image_url=:image, status=:status WHERE id=:id');
            $stmt->execute([':title' => $values['title'], ':slug' => $values['slug'], ':excerpt' => $values['excerpt'], ':content' => $values['content'], ':image' => $values['featured_image_url'], ':status' => $values['status'], ':id' => $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO medical_toplists (title,slug,excerpt,content,featured_image_url,status) VALUES (:title,:slug,:excerpt,:content,:image,:status)');
            $stmt->execute([':title' => $values['title'], ':slug' => $values['slug'], ':excerpt' => $values['excerpt'], ':content' => $values['content'], ':image' => $values['featured_image_url'], ':status' => $values['status']]);
            $id = (int) $pdo->lastInsertId();
            $isEdit = true;
        }
        // The article itself has been written even if synchronizing the
        // selected facilities subsequently fails.
        medical_search_cache_invalidate();
        toplist_directory_sync_facilities($pdo, $id, $selectedIds);
        flash_toast_set('success', 'Đã lưu bài Toplist và thứ hạng cơ sở.', 'fa-solid fa-circle-check');
        header('Location: /admin/medical_toplist_edit.php?id=' . $id);
        exit;
    }
}

$selectedFacilities = [];
if ($selectedIds !== []) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $selectedLanguage = medreview_ensure_translation_columns($pdo, 'medical_facilities') ? ' AND language_code = ?' : '';
    $selectedLanguageParams = $selectedLanguage !== '' ? array_merge($selectedIds, [$toplistLanguage]) : $selectedIds;
    $stmt = $pdo->prepare("SELECT id, name, city, address_text, image_url, rating, reviews_count FROM medical_facilities WHERE id IN ({$placeholders}) AND status = 'published'{$selectedLanguage}");
    $stmt->execute($selectedLanguageParams);
    $byId = [];
    foreach ($stmt->fetchAll() as $facility) {
        $byId[(int) $facility['id']] = $facility;
    }
    foreach ($selectedIds as $facilityId) {
        if (isset($byId[$facilityId])) {
            $selectedFacilities[] = $byId[$facilityId];
        }
    }
    // Keep the initial drag/drop state aligned with the Toplist language too.
    $selectedIds = array_map(static fn(array $facility): int => (int) $facility['id'], $selectedFacilities);
}

$toplistTranslationCounterpart = is_array($toplistTranslationRow)
    ? medical_directory_translation_counterpart($pdo, 'toplist', $toplistTranslationRow, false)
    : null;
$adminPageTitle = $isEdit ? 'Admin • Sửa Toplist' : 'Admin • Tạo Toplist';
$adminHeaderTitle = $isEdit ? 'Sửa bài Toplist' : 'Tạo bài Toplist';
$adminHeaderSubtitle = 'Soạn bài bằng CKEditor, chọn ảnh từ thư viện và kéo thả để xếp hạng cơ sở';
$adminActive = 'medical-toplists';
require __DIR__ . '/_layout_start.php';

$mediaFieldId = 'medical_toplist_featured_image';
$mediaFieldLabel = 'Ảnh đại diện Toplist';
$mediaFieldName = 'featured_image_url';
$mediaFieldValue = $values['featured_image_url'];
?>
<style>
  .toplist-sortable { min-height: 80px; }
  .toplist-facility { cursor: grab; }
  .toplist-facility.dragging { opacity: .45; }
  .facility-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: .75rem; background: #eef4ff; }
  .facility-thumb-placeholder { width: 44px; height: 44px; border-radius: .75rem; background: #eef4ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; }
  .toplist-search-results { min-height: 68px; max-height: 370px; overflow: auto; }
  .toplist-search-results .list-group-item { min-width: 0; overflow: hidden; }
  .toplist-search-results .facility-thumb, .toplist-search-results .facility-thumb-placeholder { flex: 0 0 44px; }
  .toplist-search-results .facility-result-body { min-width: 0; overflow: hidden; }
  .toplist-search-results .facility-result-address { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .toplist-search-results .facility-result-add { flex: 0 0 auto; white-space: nowrap; }
  .quick-facility-form { background: #f8fbff; border: 1px dashed #b9d3f7; border-radius: .85rem; }
  #selectedFacilities .list-group-item { min-width: 0; overflow: hidden; }
  #selectedFacilities .toplist-facility-content { min-width: 0; flex: 1 1 auto; overflow: hidden; }
  #selectedFacilities .toplist-facility-address { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  #selectedFacilities .remove-facility { flex: 0 0 auto; }
</style>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($isEdit): ?>
  <div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="fw-semibold"><i class="fa-solid fa-language text-primary me-2" aria-hidden="true"></i>Bản nội dung: <?php echo $toplistLanguage === 'en' ? 'English' : 'Tiếng Việt'; ?></div>
      <div class="small text-secondary mt-1">Bản dịch Toplist được lưu riêng; danh sách cơ sở được sao chép và ưu tiên hồ sơ tiếng Anh nếu đã có.</div>
    </div>
    <?php if ($toplistLanguage === 'en' && is_array($toplistTranslationCounterpart)): ?>
      <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(admin_url('medical_toplist_edit.php') . '?id=' . (int) $toplistTranslationCounterpart['id'], ENT_QUOTES, 'UTF-8'); ?>">Mở Toplist tiếng Việt</a>
    <?php elseif ($toplistLanguage === 'vi' && is_array($toplistTranslationCounterpart)): ?>
      <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(admin_url('medical_toplist_edit.php') . '?id=' . (int) $toplistTranslationCounterpart['id'], ENT_QUOTES, 'UTF-8'); ?>">Mở bản tiếng Anh · <?php echo htmlspecialchars((string) $toplistTranslationCounterpart['status'], ENT_QUOTES, 'UTF-8'); ?></a>
    <?php elseif ($toplistLanguage === 'vi'): ?>
      <form method="post" class="m-0" onsubmit="return confirm('Tạo bản tiếng Anh nháp từ Toplist hiện tại?');">
        <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
        <input type="hidden" name="_translation_action" value="create_en">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-language me-2" aria-hidden="true"></i>Tạo bản tiếng Anh</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" class="row g-3" id="toplistForm" data-post-form>
  <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
  <input type="hidden" name="facility_order" id="facilityOrder" value="<?php echo htmlspecialchars(implode(',', $selectedIds), ENT_QUOTES, 'UTF-8'); ?>">

  <div class="col-12 col-xl-7">
    <div class="card border-0 shadow-soft"><div class="card-body p-4">
      <div class="h5 mb-3">Thông tin bài viết</div>
      <div class="row g-3">
        <div class="col-12"><label class="form-label" for="title">Tiêu đề</label><input id="title" required name="title" class="form-control" value="<?php echo htmlspecialchars($values['title'], ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-8"><label class="form-label" for="slug">Slug</label><input id="slug" name="slug" class="form-control mono" value="<?php echo htmlspecialchars($values['slug'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tự tạo từ tiêu đề nếu để trống"></div>
        <div class="col-md-4"><label class="form-label" for="status">Trạng thái</label><select id="status" name="status" class="form-select"><option value="draft" <?php echo $values['status'] === 'draft' ? 'selected' : ''; ?>>Nháp</option><option value="published" <?php echo $values['status'] === 'published' ? 'selected' : ''; ?>>Xuất bản</option></select></div>
        <div class="col-12"><label class="form-label" for="excerpt">Mô tả ngắn</label><textarea id="excerpt" name="excerpt" class="form-control" rows="3"><?php echo htmlspecialchars($values['excerpt'], ENT_QUOTES, 'UTF-8'); ?></textarea></div>
        <?php require __DIR__ . '/_media_image_field.php'; ?>
        <div class="col-12" data-ckeditor-wrap>
          <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <label class="form-label mb-0">Nội dung bài viết</label>
            <button type="button" class="btn btn-outline-primary btn-sm" data-ckeditor-insert-image="1"><i class="fa-solid fa-images me-2" aria-hidden="true"></i>Thêm ảnh từ thư viện</button>
          </div>
          <div class="border rounded-4 bg-white p-2" style="min-height: 420px;" data-ckeditor-target></div>
          <textarea name="content" class="d-none" data-ckeditor-source><?php echo htmlspecialchars($values['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
      </div>
    </div></div>
  </div>

  <div class="col-12 col-xl-5">
    <div class="card border-0 shadow-soft"><div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-2"><div><div class="h5 mb-1">Cơ sở trong Toplist</div><div class="small text-secondary">Kéo thả để đổi thứ hạng hiển thị.</div></div><span class="badge text-bg-primary" id="selectedCount"><?php echo count($selectedFacilities); ?></span></div>
      <div class="list-group toplist-sortable mb-4" id="selectedFacilities">
        <?php foreach ($selectedFacilities as $index => $facility): ?>
          <div class="list-group-item d-flex align-items-center gap-2 toplist-facility" draggable="true" data-id="<?php echo (int) $facility['id']; ?>">
            <i class="fa-solid fa-grip-vertical text-secondary" aria-hidden="true"></i>
            <?php if ((string) $facility['image_url'] !== ''): ?><img class="facility-thumb" src="<?php echo htmlspecialchars((string) $facility['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt=""><?php else: ?><span class="facility-thumb-placeholder"><i class="fa-solid fa-hospital"></i></span><?php endif; ?>
            <div class="toplist-facility-content"><div class="fw-semibold text-truncate"><span class="me-1 text-primary rank-number"><?php echo $index + 1; ?>.</span><?php echo htmlspecialchars((string) $facility['name'], ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-secondary toplist-facility-address" title="<?php echo htmlspecialchars(trim((string) $facility['city'] . ((string) $facility['address_text'] !== '' ? ' · ' . (string) $facility['address_text'] : '')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(trim((string) $facility['city'] . ((string) $facility['address_text'] !== '' ? ' · ' . (string) $facility['address_text'] : '')), ENT_QUOTES, 'UTF-8'); ?></div></div>
            <button type="button" class="btn btn-sm btn-outline-danger remove-facility" aria-label="Xoá cơ sở"><i class="fa-solid fa-xmark"></i></button>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="border-top pt-3">
        <label class="form-label fw-semibold" for="facilitySearch">Thêm cơ sở y tế</label>
        <div class="input-group mb-2"><span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary" aria-hidden="true"></i></span><input id="facilitySearch" class="form-control" autocomplete="off" placeholder="Tìm theo tên cơ sở hoặc tỉnh/thành..."></div>
        <div class="small text-secondary mb-2">Nhập ít nhất 2 ký tự để tìm. Kết quả được tải theo yêu cầu.</div>
        <div class="list-group toplist-search-results" id="facilitySearchResults"><div class="list-group-item text-secondary small">Chưa có kết quả tìm kiếm.</div></div>
      </div>
    </div></div>
  </div>

  <div class="col-12"><div class="d-flex gap-2 justify-content-end"><a class="btn btn-outline-secondary" href="/admin/medical_toplists.php">Quay lại</a><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu Toplist</button></div></div>
</form>

<script>
(() => {
  const selected = document.querySelector('#selectedFacilities');
  const order = document.querySelector('#facilityOrder');
  const count = document.querySelector('#selectedCount');
  const search = document.querySelector('#facilitySearch');
  const results = document.querySelector('#facilitySearchResults');
  const contentLocale = <?php echo json_encode($toplistLanguage, JSON_UNESCAPED_SLASHES); ?>;
  let dragged = null;
  let searchTimer = null;
  let requestId = 0;

  const makeThumb = (url) => {
    if (url) { const img = document.createElement('img'); img.className = 'facility-thumb'; img.src = url; img.alt = ''; return img; }
    const span = document.createElement('span'); span.className = 'facility-thumb-placeholder'; span.innerHTML = '<i class="fa-solid fa-hospital" aria-hidden="true"></i>'; return span;
  };
  const sync = () => {
    const cards = [...selected.querySelectorAll('.toplist-facility')];
    order.value = cards.map((card) => card.dataset.id).join(','); count.textContent = String(cards.length);
    cards.forEach((card, index) => { card.querySelector('.rank-number').textContent = (index + 1) + '.'; });
  };
  const isSelected = (id) => !!selected.querySelector('.toplist-facility[data-id="' + CSS.escape(String(id)) + '"]');
  const bindCard = (card) => {
    card.addEventListener('dragstart', () => { dragged = card; card.classList.add('dragging'); });
    card.addEventListener('dragend', () => { dragged = null; card.classList.remove('dragging'); sync(); });
    card.querySelector('.remove-facility').addEventListener('click', () => { card.remove(); sync(); if (search.value.trim().length >= 2) loadResults(search.value.trim()); });
  };
  const addFacility = (facility) => {
    const id = String(facility.id || ''); if (!id || isSelected(id)) return;
    const card = document.createElement('div'); card.className = 'list-group-item d-flex align-items-center gap-2 toplist-facility'; card.draggable = true; card.dataset.id = id;
    const grip = document.createElement('i'); grip.className = 'fa-solid fa-grip-vertical text-secondary'; grip.setAttribute('aria-hidden', 'true');
    const body = document.createElement('div'); body.className = 'flex-grow-1 min-w-0';
    const name = document.createElement('div'); name.className = 'fw-semibold text-truncate'; const rank = document.createElement('span'); rank.className = 'me-1 text-primary rank-number'; name.append(rank, document.createTextNode(String(facility.name || 'Cơ sở y tế')));
    const city = document.createElement('div'); city.className = 'small text-secondary toplist-facility-address'; city.title = [facility.city, facility.address_text].filter(Boolean).join(' · '); city.textContent = [facility.city, facility.address_text].filter(Boolean).join(' · '); body.className = 'toplist-facility-content'; body.append(name, city);
    const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger remove-facility'; remove.setAttribute('aria-label', 'Xoá cơ sở'); remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
    card.append(grip, makeThumb(String(facility.image_url || '')), body, remove); selected.appendChild(card); bindCard(card); sync(); loadResults(search.value.trim());
  };
  const renderResults = (items) => {
    results.innerHTML = '';
    if (!items.length) {
      if (contentLocale === 'en') {
        results.innerHTML = '<div class="list-group-item text-secondary small">Chưa có hồ sơ cơ sở tiếng Anh đã xuất bản. Hãy tạo bản dịch từ hồ sơ tiếng Việt trong trang quản trị cơ sở y tế trước.</div>';
        return;
      }
      results.innerHTML = '<div class="list-group-item quick-facility-form"><div class="small fw-semibold mb-2">Chưa có cơ sở phù hợp? Thêm nhanh</div><input class="form-control form-control-sm mb-2" name="quick_name" placeholder="Tên cơ sở *"><input class="form-control form-control-sm mb-2" name="quick_address" placeholder="Địa chỉ *"><input class="form-control form-control-sm mb-2" name="quick_category" value="Cơ sở y tế" placeholder="Nhóm / chuyên khoa"><button type="button" class="btn btn-sm btn-primary w-100" data-quick-add>Lưu và thêm vào Toplist</button><div class="small text-danger mt-2 d-none" data-quick-error></div></div>';
      const form = results.firstElementChild;
      form.querySelector('[data-quick-add]').addEventListener('click', async () => {
        const name = form.querySelector('[name="quick_name"]').value.trim(); const address = form.querySelector('[name="quick_address"]').value.trim(); const category = form.querySelector('[name="quick_category"]').value.trim() || 'Cơ sở y tế'; const error = form.querySelector('[data-quick-error]');
        if (!name || !address) { error.textContent = 'Vui lòng nhập tên và địa chỉ.'; error.classList.remove('d-none'); return; }
        const button = form.querySelector('[data-quick-add]'); button.disabled = true; button.textContent = 'Đang lưu...';
        try { const response = await fetch('/admin/api/medical/facility_search.php', { method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json'}, body: JSON.stringify({name, address_text: address, category}) }); const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tạo cơ sở.'); addFacility(data.item); }
        catch (e) { error.textContent = e.message; error.classList.remove('d-none'); button.disabled = false; button.textContent = 'Lưu và thêm vào Toplist'; }
      });
      return;
    }
    items.forEach((facility) => {
      const item = document.createElement('div'); item.className = 'list-group-item d-flex align-items-center gap-2';
      const body = document.createElement('div'); body.className = 'flex-grow-1 facility-result-body'; const name = document.createElement('div'); name.className = 'fw-semibold text-truncate'; name.textContent = String(facility.name || ''); const meta = document.createElement('small'); meta.className = 'text-secondary d-block text-truncate'; meta.textContent = [facility.city, facility.rating ? String(facility.rating) + '/5' : ''].filter(Boolean).join(' · '); const address = document.createElement('small'); address.className = 'text-secondary facility-result-address'; address.title = String(facility.address_text || ''); address.textContent = String(facility.address_text || ''); if (address.textContent) body.append(name, meta, address); else body.append(name, meta);
      const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary facility-result-add'; button.textContent = 'Thêm'; button.addEventListener('click', () => addFacility(facility));
      item.append(makeThumb(String(facility.image_url || '')), body, button); results.appendChild(item);
    });
  };
  const loadResults = async (term) => {
    const q = String(term || '').trim();
    if (q.length < 2) { results.innerHTML = '<div class="list-group-item text-secondary small">Nhập ít nhất 2 ký tự để tìm cơ sở.</div>'; return; }
    const currentRequest = ++requestId; results.innerHTML = '<div class="list-group-item text-secondary small">Đang tìm...</div>';
    try {
      const excluded = [...selected.querySelectorAll('.toplist-facility')].map((card) => card.dataset.id).join(',');
      const response = await fetch('/admin/api/medical/facility_search.php?q=' + encodeURIComponent(q) + '&locale=' + encodeURIComponent(contentLocale) + '&exclude=' + encodeURIComponent(excluded), { headers: { Accept: 'application/json' } });
      const data = await response.json(); if (currentRequest !== requestId) return;
      if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tìm cơ sở.'); renderResults(Array.isArray(data.items) ? data.items : []);
    } catch (error) { if (currentRequest === requestId) results.innerHTML = '<div class="list-group-item text-danger small">Không thể tải kết quả. Vui lòng thử lại.</div>'; }
  };
  selected.querySelectorAll('.toplist-facility').forEach(bindCard);
  selected.addEventListener('dragover', (event) => { event.preventDefault(); const after = [...selected.querySelectorAll('.toplist-facility:not(.dragging)')].find((card) => event.clientY < card.getBoundingClientRect().top + card.offsetHeight / 2); if (dragged) selected.insertBefore(dragged, after || null); });
  search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadResults(search.value), 250); });
  sync();
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
