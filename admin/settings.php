<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();
?>
<?php
$adminPageTitle = 'Admin • Cài đặt trang';
$adminHeaderTitle = 'Cài đặt trang';
$adminHeaderSubtitle = 'Tiêu đề, mô tả, icon';
$adminActive = 'settings';
require __DIR__ . '/_layout_start.php';
?>
          <div class="row g-3">
            <div class="col-12 col-xl-7">
              <div class="card border-0 shadow-soft">
                <div class="card-body p-0">
                  <div class="px-4 pt-4">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                      <div>
                        <div class="h5 mb-1">Cài đặt</div>
                        <div class="text-secondary">Quản lý thông tin trang và liên hệ.</div>
                      </div>
                      <div class="d-flex gap-2">
                        <button id="btnReload" class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-rotate me-2" aria-hidden="true"></i>Reload</button>
                        <button id="btnSave" class="btn btn-primary" type="button"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu</button>
                      </div>
                    </div>
                    <ul class="nav nav-tabs mt-3" role="tablist">
                      <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-general" data-bs-toggle="tab" data-bs-target="#pane-general" type="button" role="tab" aria-controls="pane-general" aria-selected="true">
                          <i class="fa-solid fa-globe me-2"></i>Thông tin trang
                        </button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-gallery" data-bs-toggle="tab" data-bs-target="#pane-gallery" type="button" role="tab" aria-controls="pane-gallery" aria-selected="false">
                          <i class="fa-solid fa-images me-2"></i>Gallery
                        </button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-contact" data-bs-toggle="tab" data-bs-target="#pane-contact" type="button" role="tab" aria-controls="pane-contact" aria-selected="false">
                          <i class="fa-solid fa-address-card me-2"></i>Liên hệ & Mạng xã hội
                        </button>
                      </li>
                    </ul>
                  </div>
                  <div class="tab-content p-4">
                    <div class="tab-pane fade show active" id="pane-general" role="tabpanel" aria-labelledby="tab-general" tabindex="0">
                      <form id="settingsForm" class="row g-3" novalidate>
                        <div class="col-12">
                          <label class="form-label" for="siteTitle">Tiêu đề</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-heading"></i></span>
                            <input id="siteTitle" class="form-control" required>
                          </div>
                          <div class="invalid-feedback">Vui lòng nhập tiêu đề.</div>
                          <div class="form-text">Dùng làm tên thương hiệu trên header/footer và tiêu đề mặc định của trang chủ. SEO riêng của từng trang vẫn được ưu tiên.</div>
                        </div>
                        <div class="col-12">
                          <label class="form-label" for="siteDescription">Mô tả</label>
                          <textarea id="siteDescription" class="form-control" rows="3"></textarea>
                          <div class="form-text">Dùng làm mô tả mặc định của trang chủ khi chưa có SEO Description riêng.</div>
                        </div>
                        <div class="col-12">
                          <label class="form-label" for="siteIconHref">Icon</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-image"></i></span>
                            <input id="siteIconHref" class="form-control mono" placeholder="https://... hoặc data:image/svg+xml,...">
                          </div>
                          <div class="form-text">Favicon dùng chung trên toàn bộ trang. Hỗ trợ URL HTTPS, đường dẫn nội bộ hoặc data URI ảnh.</div>
                        </div>
                        <div class="col-12">
                          <div class="d-flex flex-wrap gap-2 align-items-center">
                            <label class="btn btn-outline-secondary">
                              <i class="fa-solid fa-upload me-2" aria-hidden="true"></i>Upload icon
                              <input id="iconUploadInput" type="file" accept="image/*" hidden>
                            </label>
                            <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#mediaModal">
                              <i class="fa-solid fa-images me-2" aria-hidden="true"></i>Chọn từ thư viện
                            </button>
                            <label class="btn btn-outline-secondary">
                              <i class="fa-solid fa-cloud-arrow-up me-2" aria-hidden="true"></i>Upload media (ảnh/mp4)
                              <input id="mediaUploadInput" type="file" accept="image/*,video/mp4" hidden>
                            </label>
                          </div>
                          <div class="form-text">File lưu vào /uploads/library.</div>
                        </div>
                      </form>
                    </div>
                    <div class="tab-pane fade" id="pane-gallery" role="tabpanel" aria-labelledby="tab-gallery" tabindex="0">
                      <form id="galleryForm" class="row g-3" novalidate>
                        <?php
                          $mediaGalleryId = 'site_home_gallery';
                          $mediaGalleryLabel = 'Gallery trang chủ';
                          $mediaGalleryName = 'site_home_gallery_lines';
                          $mediaGalleryValue = '';
                          require __DIR__ . '/_media_gallery_field.php';
                        ?>
                      </form>
                    </div>
                    <div class="tab-pane fade" id="pane-contact" role="tabpanel" aria-labelledby="tab-contact" tabindex="0">
                      <div class="d-flex justify-content-end mb-3">
                        <div class="d-flex gap-2">
                          <button id="btnReloadContact" class="btn btn-outline-secondary" type="button"><i class="fa-solid fa-rotate me-2" aria-hidden="true"></i>Reload</button>
                          <button id="btnSaveContact" class="btn btn-primary" type="button"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu</button>
                        </div>
                      </div>
                      <form id="contactForm" class="row g-3" novalidate>
                        <div class="col-md-6">
                          <label class="form-label" for="siteHotline">Hotline</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                            <input id="siteHotline" class="form-control" required>
                          </div>
                          <div class="invalid-feedback">Vui lòng nhập hotline.</div>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label" for="siteEmail">Email</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                            <input id="siteEmail" type="email" class="form-control" required>
                          </div>
                          <div class="invalid-feedback">Vui lòng nhập email hợp lệ.</div>
                        </div>
                        <div class="col-12">
                          <label class="form-label" for="siteAddress">Địa chỉ</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                            <input id="siteAddress" class="form-control" required>
                          </div>
                          <div class="invalid-feedback">Vui lòng nhập địa chỉ.</div>
                        </div>
                        <div class="col-md-4">
                          <label class="form-label" for="siteFacebook">Facebook URL</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="fa-brands fa-facebook-f"></i></span>
                            <input id="siteFacebook" type="url" class="form-control" placeholder="https://facebook.com/...">
                          </div>
                        </div>
                        <div class="col-md-4">
                          <label class="form-label" for="siteZalo">Zalo URL</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-comment-dots"></i></span>
                            <input id="siteZalo" type="url" class="form-control" placeholder="https://zalo.me/...">
                          </div>
                        </div>
                        <div class="col-md-4">
                          <label class="form-label" for="siteInstagram">Instagram URL</label>
                          <div class="input-group">
                            <span class="input-group-text"><i class="fa-brands fa-instagram"></i></span>
                            <input id="siteInstagram" type="url" class="form-control" placeholder="https://instagram.com/...">
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-xl-5">
              <div class="card border-0 shadow-soft">
                <div class="card-body p-4">
                  <div class="h5 mb-2">Preview</div>
                  <div class="text-secondary mb-3">Xem nhanh trước khi lưu.</div>

                  <div class="d-flex align-items-start gap-3">
                    <div class="border rounded-4 bg-white d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
                      <img id="iconPreview" alt="Icon" style="max-width:48px;max-height:48px;">
                    </div>
                    <div class="flex-grow-1">
                      <div id="titlePreview" class="fw-semibold"></div>
                      <div id="descPreview" class="text-secondary small"></div>
                    </div>
                  </div>

                  <hr class="my-4">

                  <div class="mb-2">
                    <div class="small text-secondary mb-2">Liên hệ</div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <i class="fa-solid fa-phone text-secondary"></i>
                      <span id="previewHotline" class="small"></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <i class="fa-solid fa-envelope text-secondary"></i>
                      <a id="previewEmail" class="small text-decoration-none" href="#"></a>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <i class="fa-solid fa-location-dot text-secondary"></i>
                      <span id="previewAddress" class="small"></span>
                    </div>
                  </div>
                  <div class="mb-3">
                    <div class="small text-secondary mb-2">Mạng xã hội</div>
                    <div class="d-flex flex-wrap gap-2">
                      <a id="previewFacebook" class="btn btn-light border btn-sm" target="_blank" href="#"><i class="fa-brands fa-facebook-f me-2"></i>Facebook</a>
                      <a id="previewZalo" class="btn btn-light border btn-sm" target="_blank" href="#"><i class="fa-solid fa-comment-dots me-2"></i>Zalo</a>
                      <a id="previewInstagram" class="btn btn-light border btn-sm" target="_blank" href="#"><i class="fa-brands fa-instagram me-2"></i>Instagram</a>
                    </div>
                  </div>
                  <div class="text-secondary small">Lưu để áp dụng nội dung ra giao diện công khai.</div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal fade" id="mediaModal" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header">
                  <div class="modal-title fw-semibold" id="mediaModalLabel">Thư viện</div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="btn-group" role="group" aria-label="Filter">
                      <input type="radio" class="btn-check" name="mediaFilter" id="filterImages" autocomplete="off" checked>
                      <label class="btn btn-outline-secondary" for="filterImages">Ảnh</label>
                      <input type="radio" class="btn-check" name="mediaFilter" id="filterAll" autocomplete="off">
                      <label class="btn btn-outline-secondary" for="filterAll">Tất cả</label>
                    </div>
                    <button id="btnReloadMedia" type="button" class="btn btn-outline-secondary">
                      <i class="fa-solid fa-rotate me-2" aria-hidden="true"></i>Reload
                    </button>
                  </div>

                  <div id="mediaGrid" class="row g-3"></div>
                  <div id="mediaEmpty" class="text-secondary d-none">Chưa có file trong thư viện.</div>
                </div>
                <div class="modal-footer">
                  <div class="text-secondary small me-auto">Click vào ảnh để chọn làm icon.</div>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
              </div>
            </div>
          </div>

    <script>
      const form = document.getElementById("settingsForm");
      const btnSave = document.getElementById("btnSave");
      const btnReload = document.getElementById("btnReload");
      const contactForm = document.getElementById("contactForm");
      const btnSaveContact = document.getElementById("btnSaveContact");
      const btnReloadContact = document.getElementById("btnReloadContact");
      const iconUploadInput = document.getElementById("iconUploadInput");
      const mediaUploadInput = document.getElementById("mediaUploadInput");

      const mediaModal = document.getElementById("mediaModal");
      const mediaGrid = document.getElementById("mediaGrid");
      const mediaEmpty = document.getElementById("mediaEmpty");
      const btnReloadMedia = document.getElementById("btnReloadMedia");
      const filterImages = document.getElementById("filterImages");
      const filterAll = document.getElementById("filterAll");

      const siteTitle = document.getElementById("siteTitle");
      const siteDescription = document.getElementById("siteDescription");
      const siteIconHref = document.getElementById("siteIconHref");
      const siteHotline = document.getElementById("siteHotline");
      const siteEmail = document.getElementById("siteEmail");
      const siteAddress = document.getElementById("siteAddress");
      const siteFacebook = document.getElementById("siteFacebook");
      const siteZalo = document.getElementById("siteZalo");
      const siteInstagram = document.getElementById("siteInstagram");
      const siteHomeGalleryLines = document.getElementById("site_home_gallery_lines");

      const iconPreview = document.getElementById("iconPreview");
      const titlePreview = document.getElementById("titlePreview");
      const descPreview = document.getElementById("descPreview");
      const previewHotline = document.getElementById("previewHotline");
      const previewEmail = document.getElementById("previewEmail");
      const previewAddress = document.getElementById("previewAddress");
      const previewFacebook = document.getElementById("previewFacebook");
      const previewZalo = document.getElementById("previewZalo");
      const previewInstagram = document.getElementById("previewInstagram");

      function showAlert(type, message) {
        window.adminToast(type, message, {
          icon: type === "success" ? "fa-solid fa-circle-check" : (type === "danger" ? "fa-solid fa-triangle-exclamation" : "fa-solid fa-circle-info")
        });
      }

      function hideAlert() {
      }

      function renderPreview() {
        titlePreview.textContent = siteTitle.value.trim() || "(Chưa có tiêu đề)";
        descPreview.textContent = siteDescription.value.trim() || "(Chưa có mô tả)";
        iconPreview.src = siteIconHref.value.trim() || "";
        previewHotline.textContent = siteHotline ? (siteHotline.value.trim() || "") : "";
        const em = siteEmail ? siteEmail.value.trim() : "";
        previewEmail.textContent = em || "";
        previewEmail.href = em ? ("mailto:" + em) : "#";
        previewAddress.textContent = siteAddress ? (siteAddress.value.trim() || "") : "";
        const fb = siteFacebook ? siteFacebook.value.trim() : "";
        const zl = siteZalo ? siteZalo.value.trim() : "";
        const ig = siteInstagram ? siteInstagram.value.trim() : "";
        previewFacebook.href = fb || "#";
        previewZalo.href = zl || "#";
        previewInstagram.href = ig || "#";
        previewFacebook.classList.toggle("disabled", !fb);
        previewZalo.classList.toggle("disabled", !zl);
        previewInstagram.classList.toggle("disabled", !ig);
      }

      async function postJson(url, payload) {
        const res = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: payload ? JSON.stringify(payload) : null
        });
        const data = await res.json().catch(() => ({}));
        return { res, data };
      }

      async function uploadFile(file) {
        hideAlert();
        if (!file) return null;

        const fd = new FormData();
        fd.append("file", file);

        const res = await fetch("/admin/api/media/upload.php", { method: "POST", body: fd });
        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data.ok) {
          showAlert("danger", data.message || "Upload thất bại.");
          return null;
        }
        showAlert("success", data.message || "Upload OK.");
        return data.file || null;
      }

      function formatBytes(n) {
        const num = Number(n || 0);
        if (num < 1024) return num + " B";
        const kb = num / 1024;
        if (kb < 1024) return kb.toFixed(1) + " KB";
        const mb = kb / 1024;
        return mb.toFixed(1) + " MB";
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

      async function loadMedia() {
        mediaGrid.innerHTML = "";
        mediaEmpty.classList.add("d-none");

        const { res, data } = await postJson("/admin/api/media/list.php");
        if (!res.ok || !data.ok) {
          mediaGrid.innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${escapeHtml(data.message || "Không tải được thư viện.")}</div></div>`;
          return;
        }

        const items = Array.isArray(data.files) ? data.files : [];
        const onlyImages = filterImages.checked && !filterAll.checked;
        const filtered = onlyImages ? items.filter(i => i.type === "image") : items;

        if (filtered.length === 0) {
          mediaEmpty.classList.remove("d-none");
          return;
        }

        mediaGrid.innerHTML = filtered.map((f) => {
          const meta = `${escapeHtml(f.type)} • ${formatBytes(f.size)}`;
          const url = escapeHtml(f.url);
          if (f.type === "image") {
            return `
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <button type="button" class="btn p-0 w-100 text-start" data-url="${url}" data-type="image" style="border:0;background:transparent;">
                  <div class="border rounded-4 overflow-hidden bg-white" style="aspect-ratio: 1/1;">
                    <img src="${url}" alt="" style="width:100%;height:100%;object-fit:cover;">
                  </div>
                  <div class="small text-secondary mt-1">${meta}</div>
                </button>
              </div>
            `;
          }
          return `
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
              <div class="border rounded-4 overflow-hidden bg-white d-flex align-items-center justify-content-center" style="aspect-ratio: 1/1;">
                <div class="text-center">
                  <i class="fa-solid fa-film fa-2x text-secondary" aria-hidden="true"></i>
                  <div class="small text-secondary mt-2">MP4</div>
                </div>
              </div>
              <div class="small text-secondary mt-1">${meta}</div>
            </div>
          `;
        }).join("");
      }

      async function loadSettings() {
        hideAlert();
        const { res, data } = await postJson("/admin/api/settings/get.php");
        if (!res.ok || !data.ok) {
          showAlert("danger", data.message || "Không tải được settings.");
          return;
        }
        const s = data.settings || {};
        siteTitle.value = s.site_title || "";
        siteDescription.value = s.site_description || "";
        siteIconHref.value = s.site_icon_href || "";
        if (siteHomeGalleryLines) {
          siteHomeGalleryLines.value = s.site_home_gallery_lines || "";
          const galleryField = document.querySelector("[data-media-gallery-field='site_home_gallery']");
          if (galleryField) {
            delete galleryField.dataset.inited;
          }
          if (window.adminInitPostForms) {
            window.adminInitPostForms(document);
          }
        }
        siteHotline.value = s.site_hotline || "";
        siteEmail.value = s.site_email || "";
        siteAddress.value = s.site_address || "";
        siteFacebook.value = s.site_facebook || "";
        siteZalo.value = s.site_zalo || "";
        siteInstagram.value = s.site_instagram || "";
        renderPreview();
      }

      async function saveSettings() {
        hideAlert();
        form.classList.add("was-validated");
        const payload = {
          site_title: siteTitle.value.trim(),
          site_description: siteDescription.value.trim(),
          site_icon_href: siteIconHref.value.trim(),
          site_home_gallery_lines: siteHomeGalleryLines ? siteHomeGalleryLines.value : "",
          site_hotline: siteHotline.value.trim(),
          site_email: siteEmail.value.trim(),
          site_address: siteAddress.value.trim(),
          site_facebook: siteFacebook.value.trim(),
          site_zalo: siteZalo.value.trim(),
          site_instagram: siteInstagram.value.trim()
        };
        if (!payload.site_title) {
          return;
        }
        btnSave.disabled = true;
        btnSave.classList.add("disabled");
        btnSave.setAttribute("aria-busy", "true");
        try {
          const { res, data } = await postJson("/admin/api/settings/update.php", payload);
          if (!res.ok || !data.ok) {
            showAlert("danger", data.message || "Lưu thất bại.");
            return;
          }
          showAlert("success", data.message || "Đã lưu.");
        } finally {
          btnSave.disabled = false;
          btnSave.classList.remove("disabled");
          btnSave.removeAttribute("aria-busy");
        }
      }

      async function saveContactSettings() {
        hideAlert();
        contactForm.classList.add("was-validated");
        const payload = {
          site_hotline: siteHotline.value.trim(),
          site_email: siteEmail.value.trim(),
          site_address: siteAddress.value.trim(),
          site_facebook: siteFacebook.value.trim(),
          site_zalo: siteZalo.value.trim(),
          site_instagram: siteInstagram.value.trim()
        };
        if (!siteHotline.value.trim() || !siteEmail.value.trim() || !siteAddress.value.trim()) {
          return;
        }
        btnSaveContact.disabled = true;
        btnSaveContact.classList.add("disabled");
        btnSaveContact.setAttribute("aria-busy", "true");
        try {
          const { res, data } = await postJson("/admin/api/settings/update.php", payload);
          if (!res.ok || !data.ok) {
            showAlert("danger", data.message || "Lưu thất bại.");
            return;
          }
          showAlert("success", data.message || "Đã lưu.");
        } finally {
          btnSaveContact.disabled = false;
          btnSaveContact.classList.remove("disabled");
          btnSaveContact.removeAttribute("aria-busy");
        }
      }

      siteTitle.addEventListener("input", renderPreview);
      siteDescription.addEventListener("input", renderPreview);
      siteIconHref.addEventListener("input", renderPreview);

      btnReload.addEventListener("click", loadSettings);
      btnSave.addEventListener("click", saveSettings);
      btnReloadContact.addEventListener("click", loadSettings);
      btnSaveContact.addEventListener("click", saveContactSettings);

      iconUploadInput.addEventListener("change", async () => {
        const file = iconUploadInput.files && iconUploadInput.files[0] ? iconUploadInput.files[0] : null;
        iconUploadInput.value = "";
        const uploaded = await uploadFile(file);
        if (!uploaded) return;
        if (uploaded.type !== "image") {
          showAlert("warning", "Icon chỉ dùng ảnh. File đã được lưu vào thư viện.");
          return;
        }
        siteIconHref.value = uploaded.url || "";
        renderPreview();
      });

      mediaUploadInput.addEventListener("change", async () => {
        const file = mediaUploadInput.files && mediaUploadInput.files[0] ? mediaUploadInput.files[0] : null;
        mediaUploadInput.value = "";
        const uploaded = await uploadFile(file);
        if (uploaded && mediaModal.classList.contains("show")) {
          loadMedia();
        }
      });

      btnReloadMedia.addEventListener("click", loadMedia);
      filterImages.addEventListener("change", loadMedia);
      filterAll.addEventListener("change", loadMedia);

      mediaModal.addEventListener("show.bs.modal", () => {
        loadMedia();
      });

      mediaGrid.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-url][data-type]");
        if (!btn) return;
        const url = btn.getAttribute("data-url") || "";
        const type = btn.getAttribute("data-type") || "";
        if (type !== "image") {
          showAlert("warning", "Chỉ chọn ảnh để làm icon.");
          return;
        }
        siteIconHref.value = url;
        renderPreview();
        const closeBtn = mediaModal.querySelector("[data-bs-dismiss='modal']");
        if (closeBtn) closeBtn.click();
      });

      loadSettings();
    </script>
<?php require __DIR__ . '/_layout_end.php'; ?>
