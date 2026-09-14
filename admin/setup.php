<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
?>
<?php
$adminPageTitle = 'MedReview Admin • Thiết lập hệ thống';
$adminHeaderTitle = 'Thiết lập hệ thống';
$adminHeaderSubtitle = 'Khởi tạo dữ liệu nền tảng và kiểm tra kết nối cho MedReview';
$adminActive = 'setup';
$isLoggedIn = function_exists('admin_is_logged_in') && admin_is_logged_in();
require __DIR__ . '/_layout_start.php';
?>
          <div class="row g-3">
            <div class="col-12 col-xl-7">
              <div class="card border-0 shadow-soft">
                <div class="card-body p-4">
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                      <div class="h5 mb-1">Khởi tạo hệ thống</div>
                      <div class="text-secondary">
                        Tạo bảng, kiểm tra kết nối. Upload ảnh trong Thư viện sẽ tự nén để tối ưu dung lượng.
                      </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                      <button id="btnSetup" class="btn btn-primary" type="button"><i class="fa-solid fa-table me-2" aria-hidden="true"></i>Tạo bảng</button>
                      <button id="btnTest" class="btn btn-outline-primary" type="button"><i class="fa-solid fa-plug-circle-check me-2" aria-hidden="true"></i>Test kết nối</button>
                      <?php if ($isLoggedIn): ?>
                        <a class="btn btn-outline-secondary" href="/admin/dashboard.php"><i class="fa-solid fa-chart-line me-2" aria-hidden="true"></i>Tổng quan</a>
                      <?php else: ?>
                        <a class="btn btn-outline-secondary" href="/admin/login.php"><i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>Đăng nhập</a>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="mt-3">
                    <div class="small text-secondary mb-2">Kết quả</div>
                    <pre id="output" class="pre-box bg-body-tertiary border rounded-4 p-3 mb-0 mono"></pre>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-xl-5">
              <div class="card border-0 shadow-soft">
                <div class="card-body p-4">
                  <div class="h5 mb-1">User</div>
                  <div class="text-secondary mb-3">Tạo tài khoản để vào trang quản trị và thao tác nội dung.</div>
                  <?php if ($isLoggedIn): ?>
                    <form id="createUserForm" class="row g-3" novalidate>
                      <div class="col-12">
                        <label class="form-label" for="newUsername">Username</label>
                        <input id="newUsername" class="form-control" autocomplete="off" required>
                      </div>
                      <div class="col-12">
                        <label class="form-label" for="newPassword">Password</label>
                        <input id="newPassword" class="form-control" type="password" required>
                      </div>
                      <div class="col-12">
                        <label class="form-label" for="newRole">Role</label>
                        <select id="newRole" class="form-select" required>
                          <option value="admin" selected>admin</option>
                          <option value="staff">staff</option>
                        </select>
                      </div>
                      <div class="col-12 d-grid">
                        <button class="btn btn-success" type="submit"><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Tạo user</button>
                      </div>
                    </form>
                  <?php else: ?>
                    <div class="alert alert-secondary mb-0">
                      Đăng nhập để tạo user và xem danh sách user.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="card border-0 shadow-soft">
                <div class="card-body p-4">
                  <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="tabTablesBtn" data-bs-toggle="tab" data-bs-target="#tabTables" type="button" role="tab">Bảng</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="tabUsersBtn" data-bs-toggle="tab" data-bs-target="#tabUsers" type="button" role="tab">Users</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="tabUiHistoryBtn" data-bs-toggle="tab" data-bs-target="#tabUiHistory" type="button" role="tab">Lịch sử UI</button>
                    </li>
                  </ul>

                  <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tabTables" role="tabpanel" aria-labelledby="tabTablesBtn" tabindex="0">
                      <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <div>
                          <div class="h6 mb-0">Bảng đã có</div>
                          <div class="text-secondary small">Danh sách bảng trong DB hiện tại, gồm cả visits/front_editor_history.</div>
                        </div>
                        <button id="btnReloadTables" class="btn btn-sm btn-outline-secondary" type="button"><i class="fa-solid fa-rotate me-2" aria-hidden="true"></i>Reload</button>
                      </div>
                      <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                          <thead class="table-light"><tr><th>Bảng</th><th style="width:140px;">Trạng thái</th></tr></thead>
                          <tbody id="tablesTbody"><tr><td colspan="2" class="text-secondary">Đang tải...</td></tr></tbody>
                        </table>
                      </div>
                    </div>

                    <div class="tab-pane fade" id="tabUsers" role="tabpanel" aria-labelledby="tabUsersBtn" tabindex="0">
                      <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <div>
                          <div class="h6 mb-0">Danh sách user</div>
                          <div class="text-secondary small">Hiển thị từ bảng users.</div>
                        </div>
                        <button id="btnReloadUsers" class="btn btn-sm btn-outline-secondary" type="button"><i class="fa-solid fa-rotate me-2" aria-hidden="true"></i>Reload</button>
                      </div>
                      <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                          <thead class="table-light">
                            <tr>
                              <th style="width: 90px;">ID</th>
                              <th>Username</th>
                              <th style="width: 140px;">Role</th>
                              <th style="width: 240px;">Created</th>
                            </tr>
                          </thead>
                          <tbody id="usersTbody">
                            <tr><td colspan="4" class="text-secondary">Đang tải...</td></tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div class="tab-pane fade" id="tabUiHistory" role="tabpanel" aria-labelledby="tabUiHistoryBtn" tabindex="0">
                      <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <div>
                          <div class="h6 mb-0">Lịch sử thay đổi giao diện</div>
                          <div class="text-secondary small">Ghi lại các lần chỉnh text tĩnh bằng editor và cho phép khôi phục.</div>
                        </div>
                        <button id="btnReloadUiHistory" class="btn btn-sm btn-outline-secondary" type="button"><i class="fa-solid fa-rotate me-2" aria-hidden="true"></i>Reload</button>
                      </div>
                      <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                          <thead class="table-light">
                            <tr>
                              <th style="width: 170px;">Thời gian</th>
                              <th style="width: 120px;">Trang</th>
                              <th style="width: 90px;">User</th>
                              <th>Nội dung</th>
                              <th style="width: 160px;"></th>
                            </tr>
                          </thead>
                          <tbody id="uiHistoryTbody">
                            <tr><td colspan="5" class="text-secondary">Đang tải...</td></tr>
                          </tbody>
                        </table>
                      </div>
                      <div id="uiHistoryPager" class="d-flex align-items-center justify-content-between gap-2 mt-3"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

    <script>
      const output = document.getElementById("output");
      const btnSetup = document.getElementById("btnSetup");
      const btnTest = document.getElementById("btnTest");

      function show(type, message) {
        window.adminToast(type, message, {
          icon: type === "success" ? "fa-solid fa-circle-check" : (type === "danger" ? "fa-solid fa-triangle-exclamation" : "fa-solid fa-circle-info")
        });
      }

      async function callApi(url) {
        output.textContent = "";

        const res = await fetch(url, { method: "POST" });
        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data.ok) {
          show("danger", data.message || "Thao tác thất bại.");
          output.textContent = JSON.stringify(data, null, 2);
          return;
        }

        show("success", data.message || "OK");
        output.textContent = JSON.stringify(data, null, 2);
      }

      btnSetup.addEventListener("click", () => callApi("/admin/api/setup.php"));
      btnTest.addEventListener("click", () => callApi("/admin/api/test-db.php"));

      async function postJson(url, payload) {
        const res = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: payload ? JSON.stringify(payload) : null
        });
        const data = await res.json().catch(() => ({}));
        return { res, data };
      }

      const usersTbody = document.getElementById("usersTbody");
      const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
      async function loadUsers() {
        const { res, data } = await postJson("/admin/api/users/list.php");
        if (!res.ok || !data.ok) {
          if (usersTbody) usersTbody.innerHTML = `<tr><td colspan="4" class="text-danger">${(data && data.message) ? data.message : "Không tải được danh sách user."}</td></tr>`;
          return;
        }
        const rows = Array.isArray(data.users) ? data.users : [];
        if (rows.length === 0) {
          if (usersTbody) usersTbody.innerHTML = `<tr><td colspan="4" class="text-secondary">Chưa có user.</td></tr>`;
          return;
        }
        if (usersTbody) {
          usersTbody.innerHTML = rows.map(u => `
            <tr>
              <td>${u.id}</td>
              <td>${escapeHtml(u.username)}</td>
              <td><span class="badge text-bg-secondary">${escapeHtml(u.role)}</span></td>
              <td class="text-secondary">${escapeHtml(u.created_at)}</td>
            </tr>
          `).join("");
        }
      }
      function escapeHtml(s) {
        return String(s ?? "").replace(/[&<>"']/g, (c) => ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "\"": "&quot;",
          "'": "&#039;"
        }[c]));
      }
      const createUserForm = document.getElementById("createUserForm");
      if (createUserForm) {
        createUserForm.addEventListener("submit", async (e) => {
          e.preventDefault();
          const payload = {
            username: document.getElementById("newUsername").value.trim(),
            password: document.getElementById("newPassword").value,
            role: document.getElementById("newRole").value
          };
          const { res, data } = await postJson("/admin/api/users/create.php", payload);
          if (!res.ok || !data.ok) {
            window.adminToast("danger", data.message || "Tạo user thất bại.");
            return;
          }
          window.adminToast("success", data.message || "Đã tạo user.");
          document.getElementById("newPassword").value = "";
          await loadUsers();
        });
      }
      const reloadBtn = document.getElementById("btnReloadUsers");
      if (reloadBtn) reloadBtn.addEventListener("click", loadUsers);
      if (isLoggedIn) {
        loadUsers();
      } else if (usersTbody) {
        usersTbody.innerHTML = '<tr><td colspan="4" class="text-secondary">Đăng nhập để xem danh sách user.</td></tr>';
      }

      async function loadTables(){
        const tbody = document.getElementById('tablesTbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="2" class="text-secondary">Đang tải...</td></tr>';
        try {
          const res = await fetch('/admin/api/tables/list.php', { method: 'POST' });
          const data = await res.json().catch(()=> ({}));
          if (!res.ok || !data.ok) throw new Error(data.message || 'Không tải được');
          const rows = Array.isArray(data.status) ? data.status : [];
          if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-secondary">Không có dữ liệu.</td></tr>';
            return;
          }
          tbody.innerHTML = rows.map(r => `
            <tr>
              <td class="mono">${escapeHtml(r.table)}</td>
              <td>${r.exists ? '<span class="badge text-bg-success">Tồn tại</span>' : '<span class="badge text-bg-secondary">Chưa có</span>'}</td>
            </tr>
          `).join('');
        } catch (e) {
          tbody.innerHTML = '<tr><td colspan="2" class="text-danger">Không tải được danh sách bảng.</td></tr>';
        }
      }
      document.getElementById('btnReloadTables')?.addEventListener('click', loadTables);
      loadTables();

      const uiHistoryTbody = document.getElementById('uiHistoryTbody');
      let uiHistoryPage = 1;
      const uiHistoryLimit = 10;
      function shortText(s){
        const t = String(s ?? '').replace(/\s+/g, ' ').trim();
        return t.length > 90 ? (t.slice(0, 90) + '…') : t;
      }
      function renderUiHistoryPager(paging){
        const totalPages = Number(paging?.total_pages || 1);
        const page = Number(paging?.page || 1);
        const wrap = document.getElementById('uiHistoryPager');
        if (!wrap) return;
        wrap.innerHTML = '';
        if (totalPages <= 1) return;
        const prevDisabled = page <= 1;
        const nextDisabled = page >= totalPages;
        wrap.innerHTML = `
          <button class="btn btn-sm btn-outline-secondary" type="button" data-page="${page - 1}" ${prevDisabled ? 'disabled' : ''}>
            <i class="fa-solid fa-chevron-left me-2" aria-hidden="true"></i>Trước
          </button>
          <span class="text-secondary small">Trang <strong>${page}</strong> / ${totalPages}</span>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-page="${page + 1}" ${nextDisabled ? 'disabled' : ''}>
            Sau<i class="fa-solid fa-chevron-right ms-2" aria-hidden="true"></i>
          </button>
        `;
        wrap.querySelectorAll('button[data-page]').forEach(b => {
          b.addEventListener('click', () => {
            const p = Number(b.getAttribute('data-page') || 1);
            if (!p || p < 1 || p > totalPages) return;
            uiHistoryPage = p;
            loadUiHistory();
          });
        });
      }
      async function loadUiHistory(){
        if (!uiHistoryTbody) return;
        uiHistoryTbody.innerHTML = '<tr><td colspan="5" class="text-secondary">Đang tải...</td></tr>';
        const { res, data } = await postJson('/admin/api/front_editor/history_list.php', { page: uiHistoryPage, limit: uiHistoryLimit });
        if (!res.ok || !data.ok) {
          uiHistoryTbody.innerHTML = `<tr><td colspan="5" class="text-danger">${escapeHtml(data.message || 'Không tải được')}</td></tr>`;
          renderUiHistoryPager(null);
          return;
        }
        renderUiHistoryPager(data.paging);
        const rows = Array.isArray(data.items) ? data.items : [];
        if (rows.length === 0) {
          uiHistoryTbody.innerHTML = '<tr><td colspan="5" class="text-secondary">Chưa có lịch sử.</td></tr>';
          renderUiHistoryPager(null);
          return;
        }
        uiHistoryTbody.innerHTML = rows.map(r => {
          const oldT = shortText(r.old_text);
          const newT = shortText(r.new_text);
          const change = oldT && newT ? `${escapeHtml(oldT)} → ${escapeHtml(newT)}` : escapeHtml(r.action || '');
          return `
            <tr>
              <td class="text-secondary mono">${escapeHtml(r.created_at)}</td>
              <td><span class="badge text-bg-secondary">${escapeHtml(r.page_key)}</span></td>
              <td class="text-secondary mono">${r.admin_user_id === null ? '-' : r.admin_user_id}</td>
              <td class="small">${change}</td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-danger" type="button" data-restore-id="${r.id}"><i class="fa-solid fa-rotate-left me-2" aria-hidden="true"></i>Khôi phục</button>
              </td>
            </tr>
          `;
        }).join('');
        uiHistoryTbody.querySelectorAll('[data-restore-id]').forEach(btn => {
          btn.addEventListener('click', async () => {
            const id = Number(btn.getAttribute('data-restore-id') || 0);
            if (!id) return;
            if (!confirm('Khôi phục file về trạng thái trước thay đổi này? Các chỉnh sửa sau đó có thể bị mất.')) return;
            const { res: r2, data: d2 } = await postJson('/admin/api/front_editor/restore.php', { id });
            if (!r2.ok || !d2.ok) {
              show('danger', d2.message || 'Khôi phục thất bại.');
              return;
            }
            show('success', d2.message || 'Đã khôi phục.');
            loadUiHistory();
          });
        });
      }
      document.getElementById('btnReloadUiHistory')?.addEventListener('click', loadUiHistory);
      if (isLoggedIn) loadUiHistory();
    </script>
<?php require __DIR__ . '/_layout_end.php'; ?>
