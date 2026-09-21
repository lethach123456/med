        </main>

        <footer class="admin-footer py-3 border-top">
          <div class="container-fluid d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2">
            <div class="admin-footer-copy"><i class="fa-solid fa-shield-heart me-2" aria-hidden="true"></i>MedReview · Khu vực quản trị</div>
            <div class="admin-footer-copy">© <?php echo date('Y'); ?> · Quản lý dữ liệu an toàn</div>
          </div>
        </footer>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
    <script>
      (function () {
        function titleFor(type) {
          if (type === "success") return "Thành công";
          if (type === "warning") return "Lưu ý";
          if (type === "danger") return "Lỗi";
          return "Thông báo";
        }

        window.adminToast = function (type, message, options) {
          const opts = options || {};
          const container = document.getElementById("adminToastContainer");
          if (!container) return;

          const t = document.createElement("div");
          t.className = "toast align-items-center text-bg-" + (type || "secondary") + " border-0";
          t.setAttribute("role", "alert");
          t.setAttribute("aria-live", "assertive");
          t.setAttribute("aria-atomic", "true");

          const headerTitle = (opts.title || titleFor(type || ""));
          const headerIcon = opts.icon ? `<i class="${opts.icon} me-2" aria-hidden="true"></i>` : "";

          t.innerHTML = `
            <div class="d-flex">
              <div class="toast-body">
                <div class="fw-semibold mb-1">${headerIcon}${escapeHtml(headerTitle)}</div>
                <div>${escapeHtml(message || "")}</div>
              </div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
          `;

          container.appendChild(t);
          const toast = bootstrap.Toast.getOrCreateInstance(t, { delay: opts.delay || 3500 });
          t.addEventListener("hidden.bs.toast", () => t.remove());
          toast.show();
        };

        function escapeHtml(s) {
          return String(s ?? "").replace(/[&<>"']/g, (c) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "\"": "&quot;",
            "'": "&#039;"
          }[c]));
        }
      })();
    </script>
    <script>
      (function () {
        function slugifyVi(s) {
          const str = String(s ?? "").trim().toLowerCase();
          const from =
            "àáạảãâầấậẩẫăằắặẳẵ" +
            "èéẹẻẽêềếệểễ" +
            "ìíịỉĩ" +
            "òóọỏõôồốộổỗơờớợởỡ" +
            "ùúụủũưừứựửữ" +
            "ỳýỵỷỹ" +
            "đ";
          const to =
            "aaaaaaaaaaaaaaaaa" +
            "eeeeeeeeeee" +
            "iiiii" +
            "ooooooooooooooooooo" +
            "uuuuuuuuuuu" +
            "yyyyy" +
            "d";
          let out = "";
          for (const ch of str) {
            const idx = from.indexOf(ch);
            out += (idx >= 0) ? to[idx] : ch;
          }
          out = out.normalize("NFKD").replace(/[\u0300-\u036f]/g, "");
          out = out.replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").replace(/-+/g, "-");
          return out;
        }

        window.adminSlugify = slugifyVi;

        async function postJson(url, payload) {
          const res = await fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: payload ? JSON.stringify(payload) : null
          });
          const data = await res.json().catch(() => ({}));
          return { res, data };
        }

        function uploadFileWithProgress(file, options) {
          return new Promise((resolve) => {
            if (!file) {
              resolve({ res: new Response(null, { status: 400, statusText: "No file" }), data: { ok: false, message: "Thiếu file upload." } });
              return;
            }
            const opts = options || {};
            const fd = new FormData();
            fd.append("file", file);
            if (opts.folder) {
              fd.append("folder", String(opts.folder));
            }
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "/admin/api/media/upload.php", true);
            xhr.responseType = "json";

            if (xhr.upload && typeof opts.onProgress === "function") {
              xhr.upload.addEventListener("progress", (event) => {
                if (!event.lengthComputable) return;
                const percent = Math.round((event.loaded / event.total) * 100);
                opts.onProgress(percent, event);
              });
            }

            xhr.addEventListener("load", () => {
              let data = xhr.response;
              if (!data || typeof data !== "object") {
                try {
                  data = JSON.parse(xhr.responseText || "{}");
                } catch (_) {
                  data = {};
                }
              }
              const res = {
                ok: xhr.status >= 200 && xhr.status < 300,
                status: xhr.status
              };
              resolve({ res, data });
            });

            xhr.addEventListener("error", () => {
              resolve({ res: { ok: false, status: 0 }, data: { ok: false, message: "Không kết nối được đến máy chủ upload." } });
            });

            xhr.send(fd);
          });
        }

        window.adminUploadFile = uploadFileWithProgress;

        async function uploadImage(file, options) {
          if (!file) return null;
          const { res, data } = await uploadFileWithProgress(file, options);
          if (!res.ok || !data.ok) {
            window.adminToast("danger", data.message || "Upload thất bại.", { icon: "fa-solid fa-triangle-exclamation" });
            return null;
          }
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

        function esc(s) {
          return String(s ?? "").replace(/[&<>"']/g, (c) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "\"": "&quot;",
            "'": "&#039;"
          }[c]));
        }

        function initMediaImageFields(root) {
          const scope = root || document;
          const fields = scope.querySelectorAll("[data-media-image-field]");
          fields.forEach((field) => {
            if (field.dataset.inited === "1") return;
            field.dataset.inited = "1";

            const idPrefix = field.getAttribute("data-media-image-field") || "";
            const cssEsc = (window.CSS && typeof CSS.escape === "function") ? CSS.escape : (v) => String(v || "");
            const input = field.querySelector(`#${cssEsc(idPrefix)}_url`);
            const upload = field.querySelector(`#${cssEsc(idPrefix)}_upload`);
            const preview = field.querySelector(`#${cssEsc(idPrefix)}_preview`);
            const openBtn = field.querySelector("button[data-open-media-library='1']");

            if (!input || !upload || !preview) return;

            function renderPreview() {
              const v = (input.value || "").trim();
              preview.src = v || "";
            }

            function setValue(url) {
              input.value = url || "";
              renderPreview();
              input.dispatchEvent(new Event("input", { bubbles: true }));
            }

            input.addEventListener("input", renderPreview);

            upload.addEventListener("change", async () => {
              const file = upload.files && upload.files[0] ? upload.files[0] : null;
              upload.value = "";
              const uploaded = await uploadImage(file);
              if (!uploaded) return;
              if (uploaded.type !== "image") {
                window.adminToast("warning", "Chỉ chọn ảnh để làm hình đại diện. File đã được lưu vào thư viện.", { icon: "fa-solid fa-triangle-exclamation" });
                return;
              }
              setValue(uploaded.url || "");
              window.adminToast("success", "Đã chọn ảnh.", { icon: "fa-solid fa-circle-check" });
            });

            field.addEventListener("click", (e) => {
              const clearBtn = e.target.closest("button[data-clear-image='1']");
              if (clearBtn) {
                setValue("");
              }
            });

            if (openBtn) {
              openBtn.addEventListener("click", () => {
                openMediaLibraryModal({
                  onPick: (url) => setValue(url)
                });
              });
            }

            renderPreview();
          });
        }

        function initMediaGalleryFields(root) {
          const scope = root || document;
          const fields = scope.querySelectorAll("[data-media-gallery-field]");
          const cssEsc = (window.CSS && typeof CSS.escape === "function") ? CSS.escape : (v) => String(v || "");

          function parseLines(val) {
            const lines = (String(val || "").split(/\r\n|\r|\n/)).map(s => s.trim()).filter(Boolean);
            return lines;
          }
          function linesToValue(arr) {
            return (arr || []).filter(Boolean).join("\n");
          }

          fields.forEach((field) => {
            if (field.dataset.inited === "1") return;
            field.dataset.inited = "1";

            const idPrefix = field.getAttribute("data-media-gallery-field") || "";
            const ta = field.querySelector(`#${cssEsc(idPrefix)}_lines`);
            const grid = field.querySelector("[data-gallery-grid='1']");
            const empty = field.querySelector("[data-gallery-empty='1']");
            const upload = field.querySelector(`#${cssEsc(idPrefix)}_upload`);
            const btnOpen = field.querySelector("button[data-open-media-library-multi='1']");

            if (!ta || !grid || !empty) return;

            function getList() { return parseLines(ta.value); }
            function setList(list) {
              ta.value = linesToValue(list);
              render();
              ta.dispatchEvent(new Event("input", { bubbles: true }));
            }
            function render() {
              const list = getList();
              empty.classList.toggle("d-none", list.length > 0);
              grid.innerHTML = list.map((url, idx) => {
                const u = String(url || "");
                return `
                  <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <div class="position-relative">
                      <div class="border rounded-4 overflow-hidden bg-white" style="aspect-ratio:1/1;">
                        <img src="${u}" alt="" style="width:100%;height:100%;object-fit:cover;">
                      </div>
                      <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" data-remove-index="${idx}" title="Xoá">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                      </button>
                    </div>
                  </div>
                `;
              }).join("");
            }

            field.addEventListener("click", (e) => {
              const btn = e.target.closest("button[data-remove-index]");
              if (!btn) return;
              const idx = parseInt(btn.getAttribute("data-remove-index") || "-1", 10);
              if (idx < 0) return;
              const list = getList();
              list.splice(idx, 1);
              setList(list);
            });

            if (upload) {
              upload.addEventListener("change", async () => {
                const files = upload.files ? Array.from(upload.files) : [];
                upload.value = "";
                if (!files.length) return;
                const list = getList();
                for (const f of files) {
                  const uploaded = await uploadImage(f);
                  if (uploaded && uploaded.type === "image" && uploaded.url) {
                    list.push(uploaded.url);
                  }
                }
                setList(list);
                window.adminToast("success", "Đã thêm ảnh vào gallery.", { icon: "fa-solid fa-circle-check" });
              });
            }

            if (btnOpen) {
              btnOpen.addEventListener("click", () => {
                const current = getList();
                openMediaLibraryModal({
                  multiple: true,
                  initialSelected: current,
                  onPick: (urls) => {
                    const selected = Array.isArray(urls) ? urls : [];
                    setList(selected);
                  }
                });
              });
            }

            render();
          });
        }

        function openMediaLibraryModal(options) {
          const modalEl = document.getElementById("adminMediaLibraryModal");
          if (!modalEl) return;

          const grid = document.getElementById("adminMediaGrid");
          const empty = document.getElementById("adminMediaEmpty");
          const btnReload = document.getElementById("adminMediaReload");
          const uploadInput = document.getElementById("adminMediaUploadInput");
          const filterImages = document.getElementById("adminMediaFilterImages");
          const filterAll = document.getElementById("adminMediaFilterAll");
          const btnPick = document.getElementById("adminMediaPickConfirm");
          const elPickCount = document.getElementById("adminMediaPickCount");
          const hintSingle = document.getElementById("adminMediaHintSingle");
          const hintMulti = document.getElementById("adminMediaHintMulti");
          const uploadProgressWrap = document.getElementById("adminMediaUploadProgressWrap");
          const uploadProgressBar = document.getElementById("adminMediaUploadProgressBar");
          const uploadProgressText = document.getElementById("adminMediaUploadProgressText");
          const uploadProgressLabel = document.getElementById("adminMediaUploadProgressLabel");

          if (!grid || !empty || !btnReload || !uploadInput || !filterImages || !filterAll || !btnPick || !elPickCount || !hintSingle || !hintMulti || !uploadProgressWrap || !uploadProgressBar || !uploadProgressText || !uploadProgressLabel) return;

          const isMulti = !!(options && options.multiple);
          const state = {
            multiple: isMulti,
            onPick: options && typeof options.onPick === "function" ? options.onPick : null,
            selected: new Set()
          };
          const initial = options && Array.isArray(options.initialSelected) ? options.initialSelected : [];
          initial.forEach((u) => {
            const v = String(u || "").trim();
            if (v) state.selected.add(v);
          });
          modalEl.__mediaPickerState = state;

          function renderItems(items) {
            grid.innerHTML = "";
            empty.classList.toggle("d-none", items.length !== 0);
            if (items.length === 0) return;

            grid.innerHTML = items.map((f) => {
              const url = esc(f.url);
              const meta = `${esc(f.type)} • ${formatBytes(f.size)}`;
              if (f.type === "image") {
                const active = state.multiple && state.selected.has(f.url) ? "outline:3px solid rgba(47,42,36,0.35);" : "";
                return `
                  <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <button type="button" class="btn p-0 w-100 text-start" data-pick-url="${url}" style="border:0;background:transparent;">
                      <div class="border rounded-4 overflow-hidden bg-white" style="aspect-ratio: 1/1;${active}">
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

          async function loadLibrary() {
            grid.innerHTML = `<div class="col-12"><div class="text-secondary">Đang tải...</div></div>`;
            empty.classList.add("d-none");
            const { res, data } = await postJson("/admin/api/media/list.php");
            if (!res.ok || !data.ok) {
              grid.innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${esc(data.message || "Không tải được thư viện.")}</div></div>`;
              return;
            }
            const items = Array.isArray(data.files) ? data.files : [];
            const onlyImages = filterImages.checked && !filterAll.checked;
            const filtered = onlyImages ? items.filter(i => i.type === "image") : items;
            renderItems(filtered);
          }

          function setModalUploadProgress(percent, label) {
            const value = Math.max(0, Math.min(100, Math.round(Number(percent || 0))));
            uploadProgressWrap.classList.remove("d-none");
            uploadProgressBar.style.width = value + "%";
            uploadProgressBar.setAttribute("aria-valuenow", String(value));
            uploadProgressText.textContent = value + "%";
            if (label) {
              uploadProgressLabel.textContent = label;
            }
          }

          function resetModalUploadProgress() {
            uploadProgressBar.style.width = "0%";
            uploadProgressBar.setAttribute("aria-valuenow", "0");
            uploadProgressText.textContent = "0%";
            uploadProgressLabel.textContent = "Đang tải lên...";
            uploadProgressWrap.classList.add("d-none");
          }

          function bindOnce() {
            if (modalEl.dataset.bound === "1") return;
            modalEl.dataset.bound = "1";

            btnReload.addEventListener("click", loadLibrary);
            filterImages.addEventListener("change", loadLibrary);
            filterAll.addEventListener("change", loadLibrary);

            uploadInput.addEventListener("change", async () => {
              const file = uploadInput.files && uploadInput.files[0] ? uploadInput.files[0] : null;
              uploadInput.value = "";
              if (!file) return;
              btnReload.disabled = true;
              setModalUploadProgress(0, `Đang tải lên ${file.name}...`);
              let uploaded = null;
              try {
                uploaded = await uploadImage(file, {
                  onProgress(percent) {
                    setModalUploadProgress(percent, `Đang tải lên ${file.name}...`);
                  }
                });
              } finally {
                btnReload.disabled = false;
              }
              if (!uploaded) {
                resetModalUploadProgress();
                return;
              }
              if (uploaded.type !== "image") {
                resetModalUploadProgress();
                window.adminToast("warning", "Chỉ cho phép upload ảnh trong popup.", { icon: "fa-solid fa-triangle-exclamation" });
                return;
              }
              setModalUploadProgress(100, `Đã tải lên ${file.name}`);
              window.adminToast("success", "Upload thành công.", { icon: "fa-solid fa-circle-check" });
              await loadLibrary();
              setTimeout(resetModalUploadProgress, 600);
            });

            grid.addEventListener("click", (e) => {
              const btn = e.target.closest("button[data-pick-url]");
              if (!btn) return;
              const url = btn.getAttribute("data-pick-url") || "";
              const st = modalEl.__mediaPickerState;
              if (!st) return;
              if (st.multiple) {
                if (!url) return;
                if (st.selected.has(url)) st.selected.delete(url);
                else st.selected.add(url);
                elPickCount.textContent = String(st.selected.size);
                loadLibrary();
                return;
              }
              if (typeof st.onPick === "function") {
                st.onPick(url);
              }
              bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            });

            btnPick.addEventListener("click", () => {
              const st = modalEl.__mediaPickerState;
              if (!st || !st.multiple) return;
              const urls = Array.from(st.selected);
              if (typeof st.onPick === "function") {
                st.onPick(urls);
              }
              bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            });

            modalEl.addEventListener("shown.bs.modal", loadLibrary);
            modalEl.addEventListener("hidden.bs.modal", () => {
              btnReload.disabled = false;
              resetModalUploadProgress();
            });
          }

          hintSingle.classList.toggle("d-none", state.multiple);
          hintMulti.classList.toggle("d-none", !state.multiple);
          btnPick.classList.toggle("d-none", !state.multiple);
          elPickCount.textContent = String(state.selected.size);

          bindOnce();
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        function initRichTextEditors(root) {
          const scope = root || document;
          const nodes = scope.querySelectorAll("[data-rich-editor]");
          nodes.forEach((wrap) => {
            const textarea = wrap.querySelector("textarea[data-rich-source]");
            const editor = wrap.querySelector("[data-rich-editable]");
            if (!textarea || !editor) return;

            if (!editor.getAttribute("contenteditable")) {
              editor.setAttribute("contenteditable", "true");
            }

            if (!editor.dataset.inited) {
              editor.innerHTML = textarea.value || "";
              editor.dataset.inited = "1";
            }

            const form = wrap.closest("form");
            if (form && !form.dataset.richBound) {
              form.addEventListener("submit", () => {
                textarea.value = editor.innerHTML;
              });
              form.dataset.richBound = "1";
            }

            wrap.addEventListener("click", (e) => {
              const btn = e.target.closest("button[data-cmd]");
              if (!btn) return;
              e.preventDefault();
              const cmd = btn.getAttribute("data-cmd");
              if (!cmd) return;

              if (cmd === "createLink") {
                const url = prompt("Link URL:");
                if (!url) return;
                document.execCommand("createLink", false, url);
                return;
              }
              if (cmd === "insertImage") {
                const url = prompt("Image URL:");
                if (!url) return;
                document.execCommand("insertImage", false, url);
                return;
              }
              document.execCommand(cmd, false, null);
            });
          });
        }

        function initCkEditors(root) {
          const scope = root || document;
          const wraps = scope.querySelectorAll("[data-ckeditor-wrap]");
          if (!wraps.length) return;
          if (!window.ClassicEditor) return;

          if (!window.__adminCkEditors) {
            window.__adminCkEditors = new Map();
          }

          function insertImageIntoCkEditor(editor, url) {
            const imageUrl = String(url || "").trim();
            if (!editor || !imageUrl) return false;

            try {
              editor.editing.view.focus();
            } catch (_) {
            }

            try {
              if (editor.commands && editor.commands.get("insertImage")) {
                editor.execute("insertImage", { source: [imageUrl] });
                return true;
              }
            } catch (_) {
            }

            try {
              if (editor.commands && editor.commands.get("imageInsert")) {
                editor.execute("imageInsert", { source: imageUrl });
                return true;
              }
            } catch (_) {
            }

            try {
              const html = `<p><img src="${esc(imageUrl)}" alt=""></p>`;
              const viewFragment = editor.data.processor.toView(html);
              const modelFragment = editor.data.toModel(viewFragment);
              editor.model.change(() => {
                editor.model.insertContent(modelFragment, editor.model.document.selection);
              });
              return true;
            } catch (_) {
            }

            try {
              editor.setData((editor.getData() || "") + `<p><img src="${esc(imageUrl)}" alt=""></p>`);
              return true;
            } catch (_) {
            }

            return false;
          }

          wraps.forEach((wrap) => {
            const textarea = wrap.querySelector("textarea[data-ckeditor-source]");
            const target = wrap.querySelector("[data-ckeditor-target]");
            const insertImageBtn = wrap.querySelector("[data-ckeditor-insert-image='1']");
            if (!textarea || !target) return;
            if (target.dataset.ckeditorInited === "1") return;

            target.dataset.ckeditorInited = "1";
            const initialData = textarea.value || "";

            ClassicEditor.create(target, {
              toolbar: [
                "heading",
                "|",
                "bold",
                "italic",
                "link",
                "bulletedList",
                "numberedList",
                "|",
                "blockQuote",
                "insertTable",
                "|",
                "undo",
                "redo"
              ]
            }).then((editor) => {
              editor.setData(initialData);
              window.__adminCkEditors.set(textarea, editor);

              if (insertImageBtn && insertImageBtn.dataset.bound !== "1") {
                insertImageBtn.dataset.bound = "1";
                insertImageBtn.addEventListener("click", () => {
                  openMediaLibraryModal({
                    onPick: (url) => {
                      const ok = insertImageIntoCkEditor(editor, url);
                      if (!ok) {
                        window.adminToast("danger", "Không chèn được ảnh vào nội dung.", { icon: "fa-solid fa-triangle-exclamation" });
                        return;
                      }
                      window.adminToast("success", "Đã chèn ảnh vào nội dung.", { icon: "fa-solid fa-circle-check" });
                    }
                  });
                });
              }

              const form = wrap.closest("form");
              if (form && !form.dataset.ckBound) {
                form.addEventListener("submit", () => {
                  form.querySelectorAll("textarea[data-ckeditor-source]").forEach((ta) => {
                    const ed = window.__adminCkEditors.get(ta);
                    if (ed) ta.value = ed.getData();
                  });
                });
                form.dataset.ckBound = "1";
              }
            }).catch(() => {
              target.dataset.ckeditorInited = "0";
            });
          });
        }

        function initPostForms(root) {
          const scope = root || document;
          scope.querySelectorAll("form[data-post-form]").forEach((form) => {
            const title = form.querySelector("input[name='title']") || form.querySelector("input[name='name']");
            const slug = form.querySelector("input[name='slug']");
            if (title && slug) {
              let touched = slug.value.trim() !== "";
              slug.addEventListener("input", () => {
                touched = slug.value.trim() !== "";
              });
              title.addEventListener("input", () => {
                if (touched) return;
                slug.value = window.adminSlugify(title.value);
              });
            }
          });

          initRichTextEditors(scope);
          initCkEditors(scope);
          initMediaImageFields(scope);
          initMediaGalleryFields(scope);
          initYoastPanels(scope);
        }

        window.adminInitPostForms = initPostForms;

        function bootInit() {
          try {
            initPostForms(document);
          } catch (_) {
          }
        }
        if (document.readyState === "loading") {
          document.addEventListener("DOMContentLoaded", bootInit);
        } else {
          bootInit();
        }

        function initYoastPanels(root) {
          const scope = root || document;
          const panels = scope.querySelectorAll("[data-yoast-panel]");
          panels.forEach((panel) => {
            if (panel.dataset.inited === "1") return;
            panel.dataset.inited = "1";

            const form = panel.closest("form");
            if (!form) return;

            const titleInput = form.querySelector("input[name='title']") || form.querySelector("input[name='name']");
            const slugInput = form.querySelector("input[name='slug']");
            const excerptInput = form.querySelector("textarea[name='excerpt']") || form.querySelector("textarea[name='short_description']") || form.querySelector("textarea[name='subtitle']") || form.querySelector("input[name='subtitle']");
            const seoTitleInput = form.querySelector("input[name='seo_title']");
            const seoDescInput = form.querySelector("textarea[name='seo_description']");
            const seoKeywordsInput = form.querySelector("input[name='seo_keywords']");
            const featuredInput = form.querySelector("input[name='featured_image_url']") || form.querySelector("input[name='image_url']");
            const contentTextarea = form.querySelector("textarea[data-ckeditor-source][name='content']") || form.querySelector("textarea[data-ckeditor-source][name='intro_lines']");

            const elPreviewTitle = panel.querySelector("[data-yoast-preview-title]");
            const elPreviewUrl = panel.querySelector("[data-yoast-preview-url]");
            const elPreviewDesc = panel.querySelector("[data-yoast-preview-desc]");
            const elTitleCount = panel.querySelector("[data-yoast-title-count]");
            const elDescCount = panel.querySelector("[data-yoast-desc-count]");
            const elChecklist = panel.querySelector("[data-yoast-checklist]");

            const defaultDomain = panel.getAttribute("data-yoast-domain") || window.location.host || "example.com";
            const urlPrefix = panel.getAttribute("data-yoast-url-prefix") || "/";
            const isFacilityProfile = panel.getAttribute("data-yoast-profile") === "facility";
            const minimumWords = isFacilityProfile ? 120 : 300;

            function stripHtml(html) {
              const tmp = document.createElement("div");
              tmp.innerHTML = String(html || "");
              return (tmp.textContent || tmp.innerText || "").replace(/\s+/g, " ").trim();
            }

            function wordCount(text) {
              const t = String(text || "").trim();
              if (!t) return 0;
              return t.split(/\s+/).filter(Boolean).length;
            }

            function parseFocusKeyword(raw) {
              const s = String(raw || "").trim();
              if (!s) return "";
              return s.split(",")[0].trim();
            }

            function scoreBadge(ok) {
              return ok ? `<span class="badge text-bg-success">OK</span>` : `<span class="badge text-bg-secondary">Chưa</span>`;
            }

            function compute() {
              const pageTitle = (titleInput && titleInput.value) ? titleInput.value.trim() : "";
              const pageSlug = (slugInput && slugInput.value) ? slugInput.value.trim() : "";
              const seoTitle = (seoTitleInput && seoTitleInput.value) ? seoTitleInput.value.trim() : "";
              const seoDesc = (seoDescInput && seoDescInput.value) ? seoDescInput.value.trim() : "";
              const excerpt = (excerptInput && excerptInput.value) ? excerptInput.value.trim() : "";
              const keywordsRaw = (seoKeywordsInput && seoKeywordsInput.value) ? seoKeywordsInput.value : "";
              const focusKeyword = parseFocusKeyword(keywordsRaw);
              const featured = (featuredInput && featuredInput.value) ? featuredInput.value.trim() : "";

              let contentHtml = "";
              if (window.__adminCkEditors && contentTextarea) {
                const ed = window.__adminCkEditors.get(contentTextarea);
                if (ed) contentHtml = ed.getData();
              }
              if (!contentHtml && contentTextarea) {
                contentHtml = contentTextarea.value || "";
              }
              const contentText = stripHtml(contentHtml);
              const wc = wordCount(contentText);

              const usedTitle = seoTitle || pageTitle;
              const usedDesc = seoDesc || excerpt || contentText.slice(0, 160);
              const urlLine = `${defaultDomain}${urlPrefix}${pageSlug || "slug"}`;

              if (elPreviewTitle) elPreviewTitle.textContent = usedTitle || "(Chưa có tiêu đề)";
              if (elPreviewUrl) elPreviewUrl.textContent = urlLine;
              if (elPreviewDesc) elPreviewDesc.textContent = usedDesc || "(Chưa có mô tả)";

              if (elTitleCount) {
                const len = usedTitle.length;
                elTitleCount.textContent = `${len}/60`;
                elTitleCount.className = "small " + (len >= 50 && len <= 60 ? "text-success" : (len > 60 ? "text-danger" : "text-secondary"));
              }
              if (elDescCount) {
                const len = usedDesc.length;
                elDescCount.textContent = `${len}/160`;
                elDescCount.className = "small " + (len >= 120 && len <= 160 ? "text-success" : (len > 160 ? "text-danger" : "text-secondary"));
              }

              if (elChecklist) {
                const kw = focusKeyword.toLowerCase();
                const hasKw = kw !== "";
                const okTitle = hasKw && usedTitle.toLowerCase().includes(kw);
                const okSlug = hasKw && pageSlug.toLowerCase().includes(window.adminSlugify ? window.adminSlugify(kw) : kw);
                const okDesc = hasKw && usedDesc.toLowerCase().includes(kw);
                const okContent = hasKw && contentText.toLowerCase().includes(kw);
                const okWords = wc >= minimumWords;
                const okFeatured = featured !== "";

                elChecklist.innerHTML = `
                  <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
                    <div class="text-secondary">Từ khóa chính</div>
                    <div class="fw-semibold">${escapeHtml(focusKeyword || "—")}</div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
                    <div>Keyword trong tiêu đề</div>
                    ${scoreBadge(okTitle)}
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
                    <div>Keyword trong slug</div>
                    ${scoreBadge(okSlug)}
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
                    <div>Keyword trong mô tả</div>
                    ${scoreBadge(okDesc)}
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
                    <div>Keyword trong nội dung</div>
                    ${scoreBadge(okContent)}
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
                    <div>Độ dài nội dung (≥ ${minimumWords} từ)</div>
                    <div class="d-flex align-items-center gap-2">${scoreBadge(okWords)}<span class="small text-secondary">${wc} từ</span></div>
                  </div>
                  <div class="d-flex align-items-center justify-content-between gap-2 py-2">
                    <div>Ảnh đại diện</div>
                    ${scoreBadge(okFeatured)}
                  </div>
                `;
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

            const inputs = [titleInput, slugInput, excerptInput, seoTitleInput, seoDescInput, seoKeywordsInput, featuredInput].filter(Boolean);
            inputs.forEach((el) => el.addEventListener("input", () => compute()));

            if (window.__adminCkEditors && contentTextarea) {
              const ed = window.__adminCkEditors.get(contentTextarea);
              if (ed) {
                ed.model.document.on("change:data", () => compute());
              }
            }

            compute();
          });
        }
      })();
    </script>
    <?php
    $toast = function_exists('flash_toast_pop') ? flash_toast_pop() : null;
    if (is_array($toast) && isset($toast['type'], $toast['message'])):
        $type = (string) $toast['type'];
        $message = (string) $toast['message'];
        $icon = isset($toast['icon']) ? (string) $toast['icon'] : '';
    ?>
    <script>
      window.adminToast(<?php echo json_encode($type); ?>, <?php echo json_encode($message); ?>, {
        icon: <?php echo json_encode($icon); ?>
      });
    </script>
    <?php endif; ?>
  </body>
</html>
