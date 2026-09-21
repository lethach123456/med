<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_admin();

$adminPageTitle = 'Admin • Quản lý người dùng';
$adminHeaderTitle = 'Quản lý người dùng';
$adminHeaderSubtitle = 'Tạo tài khoản, phân quyền và đặt lại mật khẩu quản trị';
$adminActive = 'users';
$currentAdminId = (int) ($_SESSION['admin_user_id'] ?? 0);
$currentAdminUsername = (string) ($_SESSION['admin_username'] ?? '');
$csrfToken = admin_csrf_token();

require __DIR__ . '/_layout_start.php';
?>
<style>
  .users-page { --users-blue:#2466e8; --users-ink:#192842; --users-muted:#71819a; --users-line:#dfe8f5; }
  .users-hero { position:relative; overflow:hidden; padding:1.45rem; border:1px solid #d7e5ff; border-radius:1.35rem; background:linear-gradient(118deg,#f8fbff 0%,#fff 54%,#eef6ff 100%); box-shadow:0 14px 36px rgba(33,78,153,.065); }
  .users-hero::after { position:absolute; top:-8rem; right:-5rem; width:20rem; height:20rem; border-radius:50%; content:""; background:radial-gradient(circle,rgba(54,121,243,.15),rgba(54,121,243,0) 67%); pointer-events:none; }
  .users-eyebrow { display:inline-flex; align-items:center; gap:.4rem; padding:.36rem .62rem; border:1px solid #c8e4ff; border-radius:999px; color:#215bbd; background:#eef6ff; font-size:.73rem; font-weight:800; letter-spacing:.025em; text-transform:uppercase; }
  .users-hero h1 { position:relative; margin:.65rem 0 .34rem; color:var(--users-ink); font-size:clamp(1.35rem,2.5vw,1.85rem); font-weight:850; letter-spacing:-.04em; }
  .users-hero p { position:relative; max-width:720px; margin:0; color:var(--users-muted); font-size:.91rem; line-height:1.6; }
  .users-current { position:relative; z-index:1; display:inline-flex; align-items:center; gap:.45rem; padding:.46rem .68rem; border:1px solid #dce8fa; border-radius:999px; color:#50627e; background:rgba(255,255,255,.85); font-size:.78rem; font-weight:750; white-space:nowrap; }
  .users-stat { min-width:0; height:100%; padding:1rem; border:1px solid var(--users-line); border-radius:1rem; background:#fff; box-shadow:0 8px 23px rgba(24,62,116,.045); }
  .users-stat-icon { display:grid; width:2.15rem; height:2.15rem; place-items:center; margin-bottom:.7rem; border-radius:.72rem; color:var(--users-blue); background:#edf4ff; }
  .users-stat-value { color:#172a48; font-size:1.42rem; font-weight:850; letter-spacing:-.045em; line-height:1; }
  .users-stat-label { margin-top:.36rem; color:#73829a; font-size:.76rem; font-weight:700; }
  .users-card { border:1px solid var(--users-line); border-radius:1.15rem; background:#fff; box-shadow:0 10px 28px rgba(25,61,109,.055); }
  .users-card .card-body { padding:1.2rem; }
  .users-title { display:flex; align-items:center; gap:.58rem; margin:0; color:#1e2e48; font-size:1rem; font-weight:850; }
  .users-title i { display:grid; width:2rem; height:2rem; place-items:center; border-radius:.67rem; color:var(--users-blue); background:#edf4ff; font-size:.84rem; }
  .users-caption { margin:.35rem 0 0; color:var(--users-muted); font-size:.82rem; line-height:1.55; }
  .user-row-current { --bs-table-accent-bg:#f4f8ff; }
  .user-name-wrap { min-width:185px; }
  .user-name-wrap .form-control { min-width:160px; }
  .user-role { min-width:116px; }
  .user-actions { display:flex; justify-content:flex-end; flex-wrap:wrap; gap:.35rem; min-width:250px; }
  .user-self-badge { display:inline-flex; align-items:center; gap:.25rem; margin-top:.32rem; color:#2364cf; font-size:.7rem; font-weight:750; }
  .user-empty { padding:2.5rem 1rem; color:#8090a7; text-align:center; }
  .user-empty i { display:block; margin-bottom:.5rem; color:#9ab9ef; font-size:1.55rem; }
  @media (max-width:767.98px) {
    .users-hero,.users-card .card-body { padding:1rem; }
    .users-stat { padding:.8rem; }
    .users-stat-icon { width:1.9rem; height:1.9rem; margin-bottom:.58rem; }
    .users-stat-value { font-size:1.2rem; }
    .users-stat-label { font-size:.7rem; }
    .users-card .table-responsive { overflow:visible; border:0; }
    .users-card .table { display:block; }
    .users-card .table thead { display:none; }
    .users-card .table tbody { display:grid; gap:.7rem; }
    .users-card .table tbody tr { display:grid; grid-template-columns:minmax(0,1fr) minmax(102px,.56fr); gap:.55rem .7rem; padding:.82rem; border:1px solid var(--users-line); border-radius:.95rem; background:#fff; box-shadow:0 6px 18px rgba(24,62,116,.04); }
    .users-card .table tbody tr.user-row-current { border-color:#c8dcff; background:#f7faff; }
    .users-card .table tbody td { display:block; min-width:0; padding:0; border:0; }
    .users-card .table tbody td[colspan] { grid-column:1 / -1; }
    .users-card .table tbody td:first-child { display:none; }
    .users-card .table tbody td:nth-child(2) { grid-column:1 / -1; }
    .users-card .table tbody td:nth-child(3)::before,
    .users-card .table tbody td:nth-child(4)::before { display:block; margin-bottom:.24rem; color:#7a899e; content:attr(data-label); font-size:.67rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }
    .users-card .table tbody td:last-child { grid-column:1 / -1; }
    .user-name-wrap,.user-name-wrap .form-control { min-width:0; }
    .user-role { min-width:0; }
    .user-actions { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.35rem; min-width:0; }
    .user-actions .btn { padding:.42rem .3rem; font-size:.72rem; white-space:nowrap; }
    .user-actions .btn i { margin-right:.15rem !important; }
  }
  @media (max-width:380px) { .user-actions { grid-template-columns:1fr; }.user-actions .btn { width:100%; } }
</style>

<section class="users-page">
  <div class="users-hero mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div>
        <div class="users-eyebrow"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Kiểm soát truy cập</div>
        <h1>Tài khoản quản trị</h1>
        <p>Chỉ quản trị viên có thể tạo người dùng, phân quyền, đặt lại mật khẩu hoặc xoá tài khoản. Hệ thống luôn bảo vệ tối thiểu một tài khoản quản trị.</p>
      </div>
      <span class="users-current"><i class="fa-solid fa-user-check" aria-hidden="true"></i><?php echo htmlspecialchars($currentAdminUsername !== '' ? $currentAdminUsername : ('#' . $currentAdminId), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </div>

  <div class="row g-3 mb-3" aria-live="polite">
    <div class="col-4"><article class="users-stat"><div class="users-stat-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></div><div class="users-stat-value" id="userTotal">—</div><div class="users-stat-label">Tổng tài khoản</div></article></div>
    <div class="col-4"><article class="users-stat"><div class="users-stat-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div><div class="users-stat-value" id="userAdmins">—</div><div class="users-stat-label">Quản trị viên</div></article></div>
    <div class="col-4"><article class="users-stat"><div class="users-stat-icon"><i class="fa-solid fa-user-gear" aria-hidden="true"></i></div><div class="users-stat-value" id="userStaff">—</div><div class="users-stat-label">Nhân sự</div></article></div>
  </div>

  <div class="row g-3">
    <div class="col-12">
      <article class="users-card">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div><h2 class="users-title"><i class="fa-solid fa-users-gear" aria-hidden="true"></i> Danh sách người dùng</h2><p class="users-caption">Lưu username/quyền trực tiếp trên từng dòng. Đặt lại mật khẩu không hiển thị mật khẩu cũ.</p></div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-primary" type="button" id="showCreateUser"><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Tạo tài khoản</button>
              <button class="btn btn-outline-secondary" type="button" id="reloadUsers"><i class="fa-solid fa-rotate me-1" aria-hidden="true"></i>Làm mới</button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><th>#</th><th>Tài khoản</th><th>Quyền</th><th>Tạo lúc</th><th class="text-end">Thao tác</th></tr></thead>
              <tbody id="usersTbody"><tr><td colspan="5" class="text-center text-secondary py-4">Đang tải tài khoản…</td></tr></tbody>
            </table>
          </div>
        </div>
      </article>
    </div>
  </div>
</section>

<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:1.1rem;">
      <form id="createUserForm" novalidate>
        <div class="modal-header px-4 pt-4 pb-3 border-0">
          <div><h2 class="modal-title fs-5 fw-bold" id="createUserModalLabel">Tạo tài khoản</h2><p class="small text-secondary mb-0 mt-1">Tạo thông tin đăng nhập và chọn quyền phù hợp.</p></div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
        <div class="modal-body px-4 pt-1 pb-2">
          <div class="mb-3"><label class="form-label" for="newUsername">Username</label><input class="form-control" id="newUsername" maxlength="50" autocomplete="username" required placeholder="vd: editor-medreview"></div>
          <div class="mb-3"><label class="form-label" for="newPassword">Password</label><input class="form-control" id="newPassword" type="password" minlength="6" autocomplete="new-password" required placeholder="Tối thiểu 6 ký tự"></div>
          <div><label class="form-label" for="newRole">Quyền truy cập</label><select class="form-select" id="newRole"><option value="staff" selected>Nhân sự — staff</option><option value="admin">Quản trị viên — admin</option></select><div class="form-text">Chỉ cấp quyền quản trị viên khi thực sự cần thiết.</div></div>
        </div>
        <div class="modal-footer px-4 pt-3 pb-4 border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Huỷ</button>
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Tạo tài khoản</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="passwordForm">
        <div class="modal-header"><h2 class="modal-title fs-5" id="passwordModalLabel">Đặt lại mật khẩu</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
        <div class="modal-body"><p class="text-secondary small mb-3">Đặt mật khẩu mới cho <strong id="passwordTarget"></strong>. Mật khẩu cũ không được hiển thị.</p><label class="form-label" for="resetPassword">Mật khẩu mới</label><input class="form-control" id="resetPassword" type="password" minlength="6" autocomplete="new-password" required placeholder="Tối thiểu 6 ký tự"></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Huỷ</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-key me-2" aria-hidden="true"></i>Cập nhật mật khẩu</button></div>
      </form>
    </div>
  </div>
</div>

<script>
(() => {
  const endpoints = {
    list: <?php echo json_encode(admin_url('api/users/list.php'), JSON_UNESCAPED_SLASHES); ?>,
    create: <?php echo json_encode(admin_url('api/users/create.php'), JSON_UNESCAPED_SLASHES); ?>,
    update: <?php echo json_encode(admin_url('api/users/update.php'), JSON_UNESCAPED_SLASHES); ?>,
    remove: <?php echo json_encode(admin_url('api/users/delete.php'), JSON_UNESCAPED_SLASHES); ?>
  };
  const currentUserId = <?php echo $currentAdminId; ?>;
  const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
  const tbody = document.getElementById('usersTbody');
  const stats = { total:document.getElementById('userTotal'), admins:document.getElementById('userAdmins'), staff:document.getElementById('userStaff') };
  const createModalElement = document.getElementById('createUserModal');
  let createModal = null;
  const getCreateModal = () => {
    if (!createModal && createModalElement && window.bootstrap?.Modal) createModal = window.bootstrap.Modal.getOrCreateInstance(createModalElement);
    return createModal;
  };
  const passwordModalElement = document.getElementById('passwordModal');
  let passwordModal = null;
  const getPasswordModal = () => {
    if (!passwordModal && passwordModalElement && window.bootstrap?.Modal) passwordModal = window.bootstrap.Modal.getOrCreateInstance(passwordModalElement);
    return passwordModal;
  };
  const passwordTarget = document.getElementById('passwordTarget');
  const resetPassword = document.getElementById('resetPassword');
  let users = [];
  let passwordUserId = 0;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  async function postJson(url, payload) {
    const response = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken}, body:JSON.stringify(payload || {}) });
    const data = await response.json().catch(() => ({}));
    return {response, data};
  }
  function render() {
    const adminCount = users.filter((user) => user.role === 'admin').length;
    stats.total.textContent = String(users.length); stats.admins.textContent = String(adminCount); stats.staff.textContent = String(users.length - adminCount);
    if (!users.length) { tbody.innerHTML = '<tr><td colspan="5"><div class="user-empty"><i class="fa-solid fa-users"></i>Chưa có tài khoản nào.</div></td></tr>'; return; }
    tbody.innerHTML = users.map((user) => {
      const id = Number(user.id || 0); const current = id === currentUserId;
      const canDelete = !current && !(user.role === 'admin' && adminCount <= 1);
      return `<tr class="${current ? 'user-row-current' : ''}" data-user-id="${id}">
        <td class="text-secondary mono">#${id}</td>
        <td data-label="Tài khoản"><div class="user-name-wrap"><input class="form-control form-control-sm" data-field="username" maxlength="50" value="${escapeHtml(user.username)}">${current ? '<span class="user-self-badge"><i class="fa-solid fa-circle-check"></i>Tài khoản hiện tại</span>' : ''}</div></td>
        <td data-label="Quyền"><select class="form-select form-select-sm user-role" data-field="role"><option value="admin" ${user.role === 'admin' ? 'selected' : ''}>admin</option><option value="staff" ${user.role === 'staff' ? 'selected' : ''}>staff</option></select></td>
        <td class="text-secondary small text-nowrap" data-label="Ngày tạo">${escapeHtml(user.created_at || '—')}</td>
        <td><div class="user-actions"><button class="btn btn-sm btn-outline-primary" type="button" data-action="save"><i class="fa-solid fa-floppy-disk me-1"></i>Lưu</button><button class="btn btn-sm btn-outline-secondary" type="button" data-action="password"><i class="fa-solid fa-key me-1"></i>Mật khẩu</button><button class="btn btn-sm btn-outline-danger" type="button" data-action="delete" ${canDelete ? '' : 'disabled title="Không thể xoá tài khoản này"'}><i class="fa-solid fa-trash-can me-1"></i>Xoá</button></div></td>
      </tr>`;
    }).join('');
  }
  async function loadUsers() {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-4"><i class="fa-solid fa-circle-notch fa-spin me-2"></i>Đang tải tài khoản…</td></tr>';
    try {
      const {response, data} = await postJson(endpoints.list);
      if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tải danh sách người dùng.');
      users = Array.isArray(data.users) ? data.users : [];
      render();
    } catch (error) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(error.message || 'Không thể tải danh sách người dùng.')}</td></tr>`;
    }
  }
  document.getElementById('showCreateUser').addEventListener('click', () => getCreateModal()?.show());
  createModalElement?.addEventListener('shown.bs.modal', () => document.getElementById('newUsername')?.focus());
  document.getElementById('reloadUsers').addEventListener('click', loadUsers);
  document.getElementById('createUserForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!event.currentTarget.reportValidity()) return;
    const username = document.getElementById('newUsername').value.trim(); const password = document.getElementById('newPassword').value; const role = document.getElementById('newRole').value;
    try {
      const {response, data} = await postJson(endpoints.create, {username, password, role});
      if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tạo tài khoản.');
      event.currentTarget.reset(); document.getElementById('newRole').value = 'staff';
      getCreateModal()?.hide(); window.adminToast('success', data.message || 'Đã tạo tài khoản.', {icon:'fa-solid fa-user-check'}); await loadUsers();
    } catch (error) { window.adminToast('danger', error.message || 'Không thể tạo tài khoản.', {icon:'fa-solid fa-triangle-exclamation'}); }
  });
  tbody.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]'); if (!button || button.disabled) return;
    const row = button.closest('[data-user-id]'); const id = Number(row?.dataset.userId || 0); const user = users.find((item) => Number(item.id) === id); if (!id || !user) return;
    const action = button.dataset.action;
    if (action === 'save') {
      const username = row.querySelector('[data-field="username"]').value.trim(); const role = row.querySelector('[data-field="role"]').value;
      button.disabled = true;
      try {
        const {response, data} = await postJson(endpoints.update, {id, username, role});
        if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể cập nhật tài khoản.');
        window.adminToast('success', data.message || 'Đã cập nhật tài khoản.', {icon:'fa-solid fa-circle-check'}); await loadUsers();
      } catch (error) { window.adminToast('danger', error.message || 'Không thể cập nhật tài khoản.', {icon:'fa-solid fa-triangle-exclamation'}); button.disabled = false; }
      return;
    }
    if (action === 'password') {
      passwordUserId = id; passwordTarget.textContent = user.username || ('#' + id); resetPassword.value = ''; getPasswordModal()?.show(); setTimeout(() => resetPassword.focus(), 180); return;
    }
    if (action === 'delete') {
      if (!window.confirm(`Xoá tài khoản “${user.username}”? Thao tác này không thể hoàn tác.`)) return;
      button.disabled = true;
      try {
        const {response, data} = await postJson(endpoints.remove, {id});
        if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể xoá tài khoản.');
        window.adminToast('success', data.message || 'Đã xoá tài khoản.', {icon:'fa-solid fa-trash-can'}); await loadUsers();
      } catch (error) { window.adminToast('danger', error.message || 'Không thể xoá tài khoản.', {icon:'fa-solid fa-triangle-exclamation'}); button.disabled = false; }
    }
  });
  document.getElementById('passwordForm').addEventListener('submit', async (event) => {
    event.preventDefault(); const user = users.find((item) => Number(item.id) === passwordUserId); const password = resetPassword.value;
    if (!user || password.length < 6) { window.adminToast('warning', 'Mật khẩu cần ít nhất 6 ký tự.', {icon:'fa-solid fa-key'}); return; }
    const submit = event.currentTarget.querySelector('[type="submit"]'); submit.disabled = true;
    try {
      const {response, data} = await postJson(endpoints.update, {id:passwordUserId, username:user.username, role:user.role, password});
      if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể cập nhật mật khẩu.');
      getPasswordModal()?.hide(); window.adminToast('success', data.message || 'Đã cập nhật mật khẩu.', {icon:'fa-solid fa-key'}); await loadUsers();
    } catch (error) { window.adminToast('danger', error.message || 'Không thể cập nhật mật khẩu.', {icon:'fa-solid fa-triangle-exclamation'}); }
    finally { submit.disabled = false; }
  });
  loadUsers();
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
