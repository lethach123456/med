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
$values = ['title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'featured_image_url' => '', 'status' => 'draft', 'entity_type' => 'mixed'];
$selectedMembers = [];
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
    $selectedMembers = array_map(static fn(array $member): array => ['type' => $member['member_type'], 'id' => (int) $member['id']], toplist_directory_linked_rows($pdo, $row));
}
$toplistLanguage = strtolower((string) ($toplistTranslationRow['language_code'] ?? 'vi')) === 'en' ? 'en' : 'vi';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(admin_csrf_token(), (string) ($_POST['_csrf'] ?? ''))) $errors[] = 'Phiên biểu mẫu hết hạn. Tải lại trang rồi thử lại.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === [] && ($_POST['_translation_action'] ?? '') === 'create_en') {
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
    try { $values['entity_type'] = toplist_directory_entity_type(['entity_type' => $values['entity_type']]); }
    catch (InvalidArgumentException $e) { $errors[] = $e->getMessage(); $values['entity_type'] = 'facility'; }
    try {
        $rawOrder = trim((string) ($_POST['member_order'] ?? $_POST['facility_order'] ?? ''));
        if ($rawOrder === '') $selectedMembers = [];
        elseif (str_starts_with($rawOrder, '[')) {
            $selectedMembers = json_decode($rawOrder, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($selectedMembers) || !array_is_list($selectedMembers) || count($selectedMembers) > 1000) throw new InvalidArgumentException('Danh sách liên kết không hợp lệ.');
            foreach ($selectedMembers as $member) {
                if (!is_array($member) || !in_array($member['type'] ?? null, ['facility', 'doctor'], true) || !is_int($member['id'] ?? null) || $member['id'] <= 0) throw new InvalidArgumentException('Liên kết cần type và ID nguyên dương.');
            }
        } elseif ($values['entity_type'] !== 'mixed' && preg_match('/^\d+(,\d+)*$/D', $rawOrder)) {
            $selectedMembers = array_map(static fn(string $memberId): array => ['type' => $values['entity_type'], 'id' => (int) $memberId], explode(',', $rawOrder));
        } else throw new InvalidArgumentException('Danh sách hỗn hợp cần ID kèm loại hồ sơ.');
    } catch (Throwable $e) { $errors[] = 'Thứ tự liên kết không hợp lệ. Tải lại hoặc chọn lại hồ sơ.'; $selectedMembers = []; }
    if ($values['title'] === '' || mb_strlen($values['title']) > 220) {
        $errors[] = 'Tiêu đề bài Toplist là bắt buộc và tối đa 220 ký tự.';
    }
    $values['status'] = in_array($values['status'], ['draft', 'published'], true) ? $values['status'] : 'draft';
    $values['slug'] = unique_slug($pdo, 'medical_toplists', $values['slug'] !== '' ? $values['slug'] : $values['title'], $isEdit ? $id : null);

    if ($errors === []) {
        try {
        $pdo->beginTransaction();
        $savedId = $id;
        if ($isEdit) {
            $stmt = $pdo->prepare('UPDATE medical_toplists SET title=:title, slug=:slug, excerpt=:excerpt, content=:content, featured_image_url=:image, status=:status WHERE id=:id');
            $stmt->execute([':title' => $values['title'], ':slug' => $values['slug'], ':excerpt' => $values['excerpt'], ':content' => $values['content'], ':image' => $values['featured_image_url'], ':status' => $values['status'], ':id' => $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO medical_toplists (title,slug,excerpt,content,featured_image_url,status) VALUES (:title,:slug,:excerpt,:content,:image,:status)');
            $stmt->execute([':title' => $values['title'], ':slug' => $values['slug'], ':excerpt' => $values['excerpt'], ':content' => $values['content'], ':image' => $values['featured_image_url'], ':status' => $values['status']]);
            $savedId = (int) $pdo->lastInsertId();
        }
        toplist_directory_sync_ranked_members($pdo, $savedId, $values['entity_type'], $selectedMembers);
        $pdo->commit();
        medical_search_cache_invalidate();
        flash_toast_set('success', 'Đã lưu bài Toplist và thứ hạng các hồ sơ.', 'fa-solid fa-circle-check');
        header('Location: /admin/medical_toplist_edit.php?id=' . $savedId);
        exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Không thể lưu Toplist; nội dung và liên kết chưa bị thay đổi.';
            error_log('Toplist editor save: ' . $e->getMessage());
        }
    }
}

$selectedFacilities = [];
$memberLabel = $values['entity_type'] === 'mixed' ? 'Cơ sở & bác sĩ' : ($values['entity_type'] === 'doctor' ? 'Bác sĩ' : 'Cơ sở');
if ($selectedMembers !== []) {
    $byId = [];
    foreach (['facility', 'doctor'] as $memberType) {
        $ids = array_column(array_filter($selectedMembers, static fn(array $member): bool => $member['type'] === $memberType), 'id');
        if ($ids === []) continue;
        $memberConfig = toplist_directory_member_config($memberType);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $doctorSelect = $memberType === 'doctor' ? ', specialty_text, facility_name' : '';
        $stmt = $pdo->prepare("SELECT id, name, city, address_text, image_url, rating, reviews_count{$doctorSelect} FROM {$memberConfig['table']} WHERE id IN ({$placeholders}) AND status='published' AND language_code=?");
        $stmt->execute(array_merge($ids, [$toplistLanguage]));
        foreach ($stmt->fetchAll() as $facility) $byId[$memberType . ':' . $facility['id']] = ['member_type' => $memberType] + $facility;
    }
    foreach ($selectedMembers as $member) if (isset($byId[$member['type'] . ':' . $member['id']])) $selectedFacilities[] = $byId[$member['type'] . ':' . $member['id']];
}

$toplistTranslationCounterpart = is_array($toplistTranslationRow)
    ? medical_directory_translation_counterpart($pdo, 'toplist', $toplistTranslationRow, false)
    : null;
$adminPageTitle = $isEdit ? 'Admin • Sửa Toplist' : 'Admin • Tạo Toplist';
$adminHeaderTitle = $isEdit ? 'Sửa bài Toplist' : 'Tạo bài Toplist';
$adminHeaderSubtitle = 'Soạn bài và kéo thả cơ sở y tế, bác sĩ trong cùng danh sách xếp hạng';
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
      <div class="small text-secondary mt-1">Bản dịch Toplist được lưu riêng; danh sách cơ sở hoặc bác sĩ chỉ liên kết hồ sơ cùng ngôn ngữ đã xuất bản.</div>
    </div>
    <?php if ($toplistLanguage === 'en' && is_array($toplistTranslationCounterpart)): ?>
      <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(admin_url('medical_toplist_edit.php') . '?id=' . (int) $toplistTranslationCounterpart['id'], ENT_QUOTES, 'UTF-8'); ?>">Mở Toplist tiếng Việt</a>
    <?php elseif ($toplistLanguage === 'vi' && is_array($toplistTranslationCounterpart)): ?>
      <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(admin_url('medical_toplist_edit.php') . '?id=' . (int) $toplistTranslationCounterpart['id'], ENT_QUOTES, 'UTF-8'); ?>">Mở bản tiếng Anh · <?php echo htmlspecialchars((string) $toplistTranslationCounterpart['status'], ENT_QUOTES, 'UTF-8'); ?></a>
    <?php elseif ($toplistLanguage === 'vi'): ?>
      <form method="post" class="m-0" onsubmit="return confirm('Tạo bản tiếng Anh nháp từ Toplist hiện tại?');">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
        <input type="hidden" name="_translation_action" value="create_en">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-language me-2" aria-hidden="true"></i>Tạo bản tiếng Anh</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" class="row g-3" id="toplistForm" data-post-form>
  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
  <input type="hidden" name="member_order" id="facilityOrder" value="<?php echo htmlspecialchars(json_encode($selectedMembers), ENT_QUOTES, 'UTF-8'); ?>">

  <div class="col-12 col-xl-7">
    <div class="card border-0 shadow-soft"><div class="card-body p-4">
      <div class="h5 mb-3">Thông tin bài viết</div>
      <div class="row g-3">
        <div class="col-12"><label class="form-label" for="entityType">Đối tượng xếp hạng</label><select class="form-select" name="entity_type" id="entityType"><option value="mixed" <?php echo $values['entity_type'] === 'mixed' ? 'selected' : ''; ?>>Cơ sở y tế & bác sĩ</option><option value="facility" <?php echo $values['entity_type'] === 'facility' ? 'selected' : ''; ?>>Chỉ cơ sở y tế</option><option value="doctor" <?php echo $values['entity_type'] === 'doctor' ? 'selected' : ''; ?>>Chỉ bác sĩ</option></select><div class="form-text">Có thể thêm cả hai loại và kéo thả chung thứ hạng. Chuyển bộ lọc tìm kiếm không xoá hồ sơ đã chọn.</div></div>
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
      <div class="d-flex justify-content-between align-items-center mb-2"><div><div class="h5 mb-1" id="memberListTitle"><?php echo $memberLabel; ?> trong Toplist</div><div class="small text-secondary">Kéo thả để đổi thứ hạng hiển thị.</div></div><span class="badge text-bg-primary" id="selectedCount"><?php echo count($selectedFacilities); ?></span></div>
      <div class="list-group toplist-sortable mb-4" id="selectedFacilities">
        <?php foreach ($selectedFacilities as $index => $facility): $selectedMeta = implode(' · ', array_filter([$facility['specialty_text'] ?? '', $facility['city'] ?? '', $facility['facility_name'] ?? $facility['address_text'] ?? ''])); ?>
          <div class="list-group-item d-flex align-items-center gap-2 toplist-facility" draggable="true" data-type="<?= $facility['member_type'] ?>" data-id="<?php echo (int) $facility['id']; ?>">
            <i class="fa-solid fa-grip-vertical text-secondary" aria-hidden="true"></i>
            <?php if ((string) $facility['image_url'] !== ''): ?><img class="facility-thumb" src="<?php echo htmlspecialchars((string) $facility['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt=""><?php else: ?><span class="facility-thumb-placeholder"><i class="fa-solid <?= $facility['member_type'] === 'doctor' ? 'fa-user-doctor' : 'fa-hospital' ?>"></i></span><?php endif; ?>
            <div class="toplist-facility-content"><div class="fw-semibold text-truncate"><span class="me-1 text-primary rank-number"><?php echo $index + 1; ?>.</span><?php echo htmlspecialchars((string) $facility['name'], ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-primary"><?= $facility['member_type'] === 'doctor' ? 'Bác sĩ' : 'Cơ sở y tế' ?></div><div class="small text-secondary toplist-facility-address" title="<?= htmlspecialchars($selectedMeta, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($selectedMeta, ENT_QUOTES, 'UTF-8') ?></div></div>
            <button type="button" class="btn btn-sm btn-outline-danger remove-facility" aria-label="Xoá <?= htmlspecialchars(mb_strtolower($memberLabel), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-xmark"></i></button>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="border-top pt-3">
        <label class="form-label fw-semibold" for="memberSearchType">Tìm hồ sơ để thêm</label>
        <select class="form-select mb-2" id="memberSearchType"><option value="facility">Cơ sở y tế</option><option value="doctor" <?= $values['entity_type'] === 'doctor' ? 'selected' : '' ?>>Bác sĩ</option></select>
        <label class="form-label visually-hidden" for="facilitySearch" id="memberSearchLabel">Tìm hồ sơ</label>
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
  const typeSelect = document.querySelector('#entityType');
  const searchType = document.querySelector('#memberSearchType');
  const csrf = <?php echo json_encode(admin_csrf_token()); ?>;
  let activeType = searchType.value;
  const memberLabel = () => activeType === 'doctor' ? 'bác sĩ' : 'cơ sở';
  const contentLocale = <?php echo json_encode($toplistLanguage, JSON_UNESCAPED_SLASHES); ?>;
  let dragged = null;
  let searchTimer = null;
  let requestId = 0;

  const makeThumb = (url, memberType = activeType) => {
    if (url) { const img = document.createElement('img'); img.className = 'facility-thumb'; img.src = url; img.alt = ''; return img; }
    const span = document.createElement('span'); span.className = 'facility-thumb-placeholder'; span.innerHTML = memberType === 'doctor' ? '<i class="fa-solid fa-user-doctor" aria-hidden="true"></i>' : '<i class="fa-solid fa-hospital" aria-hidden="true"></i>'; return span;
  };
  const sync = () => {
    const cards = [...selected.querySelectorAll('.toplist-facility')];
    order.value = JSON.stringify(cards.map((card) => ({type: card.dataset.type, id: Number(card.dataset.id)}))); count.textContent = String(cards.length);
    if (typeSelect.value !== 'mixed' && cards.some(card => card.dataset.type !== typeSelect.value)) typeSelect.value = 'mixed';
    document.querySelector('#memberListTitle').textContent = (typeSelect.value === 'mixed' ? 'Cơ sở & bác sĩ' : typeSelect.value === 'doctor' ? 'Bác sĩ' : 'Cơ sở') + ' trong Toplist';
    cards.forEach((card, index) => { card.querySelector('.rank-number').textContent = (index + 1) + '.'; });
  };
  const isSelected = (id, memberType) => !!selected.querySelector('.toplist-facility[data-type="' + memberType + '"][data-id="' + CSS.escape(String(id)) + '"]');
  const bindCard = (card) => {
    card.addEventListener('dragstart', () => { dragged = card; card.classList.add('dragging'); });
    card.addEventListener('dragend', () => { dragged = null; card.classList.remove('dragging'); sync(); });
    card.querySelector('.remove-facility').addEventListener('click', () => { card.remove(); sync(); if (search.value.trim().length >= 2) loadResults(search.value.trim()); });
  };
  const addFacility = (facility, memberType = activeType) => {
    const id = String(facility.id || ''); if (!id || isSelected(id, memberType)) return;
    const card = document.createElement('div'); card.className = 'list-group-item d-flex align-items-center gap-2 toplist-facility'; card.draggable = true; card.dataset.id = id; card.dataset.type = memberType;
    const grip = document.createElement('i'); grip.className = 'fa-solid fa-grip-vertical text-secondary'; grip.setAttribute('aria-hidden', 'true');
    const body = document.createElement('div'); body.className = 'flex-grow-1 min-w-0';
    const name = document.createElement('div'); name.className = 'fw-semibold text-truncate'; const rank = document.createElement('span'); rank.className = 'me-1 text-primary rank-number'; name.append(rank, document.createTextNode(String(facility.name || 'Cơ sở y tế')));
    const kind = document.createElement('div'); kind.className = 'small text-primary'; kind.textContent = memberType === 'doctor' ? 'Bác sĩ' : 'Cơ sở y tế';
    const city = document.createElement('div'); city.className = 'small text-secondary toplist-facility-address'; city.title = [facility.specialty_text, facility.city, facility.facility_name || facility.address_text].filter(Boolean).join(' · '); city.textContent = city.title; body.className = 'toplist-facility-content'; body.append(name, kind, city);
    const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger remove-facility'; remove.setAttribute('aria-label', 'Xoá hồ sơ'); remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
    card.append(grip, makeThumb(String(facility.image_url || ''), memberType), body, remove); selected.appendChild(card); bindCard(card); sync(); loadResults(search.value.trim());
  };
  const renderResults = (items, resultType) => {
    results.innerHTML = '';
    if (!items.length) {
      if (contentLocale === 'en') {
        results.innerHTML = '<div class="list-group-item text-secondary small">Chưa có hồ sơ ' + memberLabel() + ' tiếng Anh phù hợp đã xuất bản. Hãy tạo bản dịch từ hồ sơ tiếng Việt trước.</div>';
        return;
      }
      const quickType = resultType;
      results.innerHTML = '<div class="list-group-item quick-facility-form"><div class="small fw-semibold mb-2">Chưa có cơ sở phù hợp? Thêm nhanh</div><input class="form-control form-control-sm mb-2" name="quick_name" placeholder="Tên cơ sở *"><input class="form-control form-control-sm mb-2" name="quick_address" placeholder="Địa chỉ *"><input class="form-control form-control-sm mb-2" name="quick_category" value="Cơ sở y tế" placeholder="Nhóm / chuyên khoa"><button type="button" class="btn btn-sm btn-primary w-100" data-quick-add>Lưu và thêm vào Toplist</button><div class="small text-danger mt-2 d-none" data-quick-error></div></div>';
      const form = results.firstElementChild;
      if (quickType === 'doctor') {
        form.innerHTML = '<div class="small fw-semibold mb-2">Chưa có bác sĩ phù hợp? Thêm hồ sơ cơ bản</div><input class="form-control form-control-sm mb-2" name="quick_name" placeholder="Tên bác sĩ *"><input class="form-control form-control-sm mb-2" name="quick_specialty" placeholder="Chuyên khoa *"><input class="form-control form-control-sm mb-2" name="quick_city" placeholder="Thành phố"><input class="form-control form-control-sm mb-2" name="quick_facility" placeholder="Nơi công tác"><div class="small text-secondary mb-2">Cần thành phố hoặc nơi công tác. Hồ sơ mới chưa được xác minh.</div><button type="button" class="btn btn-sm btn-primary w-100" data-quick-add>Lưu và thêm vào Toplist</button><div class="small text-danger mt-2 d-none" data-quick-error></div>';
      }
      form.querySelector('[data-quick-add]').addEventListener('click', async () => {
        const read = name => form.querySelector('[name="' + name + '"]')?.value.trim() || '';
        const name = read('quick_name'); const address = read('quick_address'); const category = read('quick_category') || 'Cơ sở y tế'; const error = form.querySelector('[data-quick-error]');
        const specialty = read('quick_specialty'); const city = read('quick_city'); const facilityName = read('quick_facility');
        if (!name || (quickType === 'facility' ? !address : !specialty || (!city && !facilityName))) { error.textContent = quickType === 'doctor' ? 'Nhập tên, chuyên khoa và thành phố hoặc nơi công tác.' : 'Vui lòng nhập tên và địa chỉ.'; error.classList.remove('d-none'); return; }
        const button = form.querySelector('[data-quick-add]'); button.disabled = true; button.textContent = 'Đang lưu...';
        try { const response = await fetch('/admin/api/medical/' + quickType + '_search.php', { method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json'}, body: JSON.stringify({_csrf: csrf, name, address_text: address, category, specialty_text: specialty, city, facility_name: facilityName}) }); const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tạo hồ sơ.'); addFacility(data.item, quickType); }
        catch (e) { error.textContent = e.message; error.classList.remove('d-none'); button.disabled = false; button.textContent = 'Lưu và thêm vào Toplist'; }
      });
      return;
    }
    items.forEach((facility) => {
      const item = document.createElement('div'); item.className = 'list-group-item d-flex align-items-center gap-2';
      const body = document.createElement('div'); body.className = 'flex-grow-1 facility-result-body'; const name = document.createElement('div'); name.className = 'fw-semibold text-truncate'; name.textContent = String(facility.name || ''); const meta = document.createElement('small'); meta.className = 'text-secondary d-block text-truncate'; meta.textContent = [facility.specialty_text, facility.city, Number(facility.reviews_count) > 0 ? String(facility.rating) + '/5' : ''].filter(Boolean).join(' · '); const address = document.createElement('small'); address.className = 'text-secondary facility-result-address'; address.title = String(facility.facility_name || facility.address_text || ''); address.textContent = address.title; if (address.textContent) body.append(name, meta, address); else body.append(name, meta);
      const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary facility-result-add'; button.textContent = 'Thêm'; button.addEventListener('click', () => addFacility(facility, resultType));
      item.append(makeThumb(String(facility.image_url || ''), resultType), body, button); results.appendChild(item);
    });
  };
  const loadResults = async (term) => {
    const q = String(term || '').trim();
    const currentRequest = ++requestId;
    const resultType = activeType;
    if (q.length < 2) { results.innerHTML = '<div class="list-group-item text-secondary small">Nhập ít nhất 2 ký tự để tìm ' + memberLabel() + '.</div>'; return; }
    results.innerHTML = '<div class="list-group-item text-secondary small">Đang tìm...</div>';
    try {
      const excluded = [...selected.querySelectorAll('.toplist-facility')].filter(card => card.dataset.type === resultType).map((card) => card.dataset.id).join(',');
      const response = await fetch('/admin/api/medical/' + activeType + '_search.php?q=' + encodeURIComponent(q) + '&locale=' + encodeURIComponent(contentLocale) + '&exclude=' + encodeURIComponent(excluded), { headers: { Accept: 'application/json' } });
      const data = await response.json(); if (currentRequest !== requestId) return;
      if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tìm cơ sở.'); renderResults(Array.isArray(data.items) ? data.items : [], resultType);
    } catch (error) { if (currentRequest === requestId) results.innerHTML = '<div class="list-group-item text-danger small">Không thể tải kết quả. Vui lòng thử lại.</div>'; }
  };
  selected.querySelectorAll('.toplist-facility').forEach(bindCard);
  selected.addEventListener('dragover', (event) => { event.preventDefault(); const after = [...selected.querySelectorAll('.toplist-facility:not(.dragging)')].find((card) => event.clientY < card.getBoundingClientRect().top + card.offsetHeight / 2); if (dragged) selected.insertBefore(dragged, after || null); });
  search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadResults(search.value), 250); });
  const updateSearchType = () => {
    clearTimeout(searchTimer); ++requestId; dragged = null;
    selected.querySelectorAll('.dragging').forEach(card => card.classList.remove('dragging'));
    activeType = searchType.value;
    document.querySelector('#memberSearchLabel').textContent = 'Thêm ' + memberLabel();
    search.placeholder = activeType === 'doctor' ? 'Tìm tên, chuyên khoa, nơi công tác hoặc thành phố...' : 'Tìm theo tên cơ sở hoặc tỉnh/thành...';
    sync(); loadResults(search.value);
  };
  searchType.addEventListener('change', updateSearchType);
  typeSelect.addEventListener('change', () => { if (typeSelect.value !== 'mixed') searchType.value = typeSelect.value; updateSearchType(); });
  search.placeholder = activeType === 'doctor' ? 'Tìm tên, chuyên khoa, nơi công tác hoặc thành phố...' : 'Tìm theo tên cơ sở hoặc tỉnh/thành...';
  sync();
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
