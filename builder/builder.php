<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/_bootstrap.php';
require_once __DIR__ . '/../Tem/tem.php';

admin_require_login();

$postId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$postSlug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
if ($postId <= 0 && $postSlug === '') {
    http_response_code(400);
    echo 'Thiếu id hoặc slug bài viết.';
    exit;
}

$pdo = db();

if ($postId <= 0 && $postSlug !== '') {
    $find = $pdo->prepare('SELECT id FROM posts WHERE slug = :slug LIMIT 1');
    $find->execute([':slug' => $postSlug]);
    $postId = (int) ($find->fetchColumn() ?: 0);
}
if ($postId <= 0) {
    http_response_code(404);
    echo 'Bài viết không tồn tại.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = read_json_body();
    $content = '';
    if (isset($payload['content'])) {
        $content = (string) $payload['content'];
    } elseif (isset($_POST['content'])) {
        $content = (string) $_POST['content'];
    }
    $stmt = $pdo->prepare('UPDATE posts SET content = :content WHERE id = :id');
    $stmt->execute([
        ':content' => $content !== '' ? $content : null,
        ':id' => $postId,
    ]);
    json_response(['ok' => true]);
}

$stmt = $pdo->prepare('SELECT id, title, slug, content, template FROM posts WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $postId]);
$post = $stmt->fetch();
if (!$post) {
    http_response_code(404);
    echo 'Bài viết không tồn tại.';
    exit;
}

$postTitle = (string) ($post['title'] ?? '');
$postSlug = (string) ($post['slug'] ?? '');
$postContent = (string) ($post['content'] ?? '');
$postTemplateMode = (int) ($post['template'] ?? 0) === 1;

if (!$postTemplateMode) {
    try {
        $pdo->prepare('UPDATE posts SET template = 1 WHERE id = :id')->execute([':id' => $postId]);
        $postTemplateMode = true;
    } catch (Throwable $e) {
    }
}

$header = isset($Header) && is_string($Header) ? $Header : '';
$footer = isset($Footer) && is_string($Footer) ? $Footer : '';

$frameHtml = $header . "\n" . '<div data-builder-content="1">' . "\n" . $postContent . "\n" . '</div>' . "\n" . $footer;
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Top Dental Builder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
    <style>
      :root{
        --bg: #0b1220;
        --panel: rgba(255,255,255,0.06);
        --panel2: rgba(255,255,255,0.08);
        --border: rgba(255,255,255,0.12);
        --text: rgba(255,255,255,0.92);
        --muted: rgba(255,255,255,0.70);
        --brand: #3dd6d0;
        --brand2: #5b8cff;
        --danger: #ff5b7a;
        --shadow: 0 18px 60px rgba(0,0,0,0.40);
      }

      *{ box-sizing: border-box; }
      html, body{ height: 100%; }
      body{
        margin: 0;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        color: var(--text);
        background:
          radial-gradient(900px 500px at 10% 0%, rgba(91,140,255,0.28), transparent 60%),
          radial-gradient(900px 500px at 90% 10%, rgba(61,214,208,0.22), transparent 60%),
          linear-gradient(180deg, #060913 0%, var(--bg) 65%, #060913 100%);
        overflow: hidden;
      }

      .app{
        height: 100%;
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 0;
      }

      .sidebar{
        border-right: 1px solid rgba(255,255,255,0.10);
        background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.03));
        padding: 14px 14px;
        overflow: auto;
      }
      .brand{
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 10px;
        border-radius: 16px;
        border: 1px solid rgba(255,255,255,0.10);
        background: rgba(255,255,255,0.04);
      }
      .logo{
        width: 38px;
        height: 38px;
        border-radius: 14px;
        background:
          radial-gradient(16px 16px at 30% 25%, rgba(255,255,255,0.7), transparent 60%),
          linear-gradient(135deg, rgba(61,214,208,1), rgba(91,140,255,1));
        box-shadow: 0 18px 50px rgba(91,140,255,0.18);
      }
      .brand strong{ font-weight: 800; letter-spacing: -0.02em; }
      .brand span{ display:block; color: var(--muted); font-size: 12px; margin-top: 3px; }

      .section{
        margin-top: 12px;
        border: 1px solid rgba(255,255,255,0.10);
        background: rgba(255,255,255,0.04);
        border-radius: 18px;
        overflow: hidden;
      }
      .section > header{
        padding: 12px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        background: rgba(255,255,255,0.03);
        border-bottom: 1px solid rgba(255,255,255,0.08);
      }
      .section > header b{ font-size: 13px; }
      .section > header small{ color: var(--muted); font-size: 12px; }
      .section .body{ padding: 12px; display: grid; gap: 10px; }

      .row{
        display: grid;
        gap: 6px;
      }
      label{
        font-size: 12px;
        color: var(--muted);
      }
      input, textarea, select{
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.12);
        background: rgba(0,0,0,0.20);
        color: var(--text);
        padding: 10px 12px;
        outline: none;
      }
      textarea{ min-height: 84px; resize: vertical; }
      input:focus, textarea:focus, select:focus{
        border-color: rgba(61,214,208,0.55);
        box-shadow: 0 0 0 4px rgba(61,214,208,0.18);
      }

      .btnrow{
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
      }
      .btn{
        appearance: none;
        border: 0;
        cursor: pointer;
        border-radius: 14px;
        padding: 10px 12px;
        font-weight: 700;
        font-size: 13px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.12);
        color: var(--text);
        transition: transform 160ms ease, filter 160ms ease, background 160ms ease, border-color 160ms ease;
      }
      .btn:active{ transform: translateY(1px); }
      .btn:hover{ background: rgba(255,255,255,0.09); border-color: rgba(255,255,255,0.16); }
      .btn.primary{
        background: linear-gradient(135deg, rgba(61,214,208,1), rgba(91,140,255,1));
        color: #04101b;
        border: 0;
      }
      .btn.primary:hover{ filter: brightness(1.03); }
      .btn.danger{
        background: rgba(255,91,122,0.10);
        border-color: rgba(255,91,122,0.22);
        color: rgba(255,255,255,0.92);
      }

      .toolbar{
        border-bottom: 1px solid rgba(255,255,255,0.10);
        background: rgba(255,255,255,0.04);
        padding: 12px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
      }
      .toolbar .left{
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
      }
      .pill{
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.10);
        background: rgba(255,255,255,0.04);
        color: var(--muted);
        font-size: 12px;
      }
      .pill strong{ color: var(--text); font-weight: 700; }
      .toolbar .right{
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
      }
      .toggle{
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--muted);
        font-size: 12px;
        user-select: none;
      }
      .toggle input{ width: 18px; height: 18px; }

      .saveDock{
        position: fixed;
        left: 14px;
        bottom: 14px;
        z-index: 10000;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border-radius: 18px;
        border: 1px solid rgba(255,255,255,0.12);
        background: rgba(0,0,0,0.22);
        backdrop-filter: blur(10px);
        box-shadow: 0 18px 60px rgba(0,0,0,0.35);
      }
      .saveDock .state{
        font-size: 12px;
        color: var(--muted);
      }
      .toast{
        position: fixed;
        left: 50%;
        bottom: 16px;
        transform: translateX(-50%) translateY(20px);
        opacity: 0;
        pointer-events: none;
        transition: opacity 180ms ease, transform 180ms ease;
        background: rgba(255,255,255,0.92);
        color: #0b1220;
        border: 1px solid rgba(255,255,255,0.28);
        backdrop-filter: blur(10px);
        padding: 10px 12px;
        border-radius: 14px;
        box-shadow: 0 18px 60px rgba(0,0,0,0.35);
        font-size: 12px;
        z-index: 10001;
        display: inline-flex;
        gap: 8px;
        align-items: center;
        min-width: 220px;
      }
      .toast.show{
        opacity: 1;
        transform: translateX(-50%) translateY(0);
      }
      .toast .mark{
        width: 26px;
        height: 26px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        background: rgba(22,163,74,0.12);
        border: 1px solid rgba(22,163,74,0.22);
        color: rgba(22,163,74,0.95);
        flex: 0 0 auto;
      }
      .toast.danger .mark{
        background: rgba(225,29,72,0.10);
        border-color: rgba(225,29,72,0.22);
        color: rgba(225,29,72,0.95);
      }
      .mediaPicker{
        position: fixed;
        inset: 0;
        z-index: 10002;
        display: none;
        background: rgba(0,0,0,0.45);
        backdrop-filter: blur(2px);
      }
      .mediaPicker .panel{
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        width: min(960px, 92vw);
        max-height: 78vh;
        display: grid;
        grid-template-rows: auto 1fr;
        gap: 0;
        border-radius: 18px;
        border: 1px solid rgba(255,255,255,0.12);
        background: rgba(255,255,255,0.06);
        box-shadow: var(--shadow);
        overflow: hidden;
      }
      .mediaPicker header{
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border-bottom: 1px solid rgba(255,255,255,0.10);
        background: rgba(255,255,255,0.04);
        color: var(--text);
      }
      .mediaPicker .grid{
        padding: 12px;
        overflow: auto;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 10px;
        align-content: start;
      }
      .mediaPicker .item{
        appearance: none;
        width: 100%;
        text-align: left;
        border: 1px solid rgba(255,255,255,0.10);
        background: rgba(255,255,255,0.04);
        border-radius: 14px;
        padding: 8px;
        cursor: pointer;
        color: var(--text);
        display: grid;
        gap: 8px;
      }
      .mediaPicker .item:active{ transform: translateY(1px); }
      .mediaPicker .item img{
        width: 100%;
        height: 110px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.10);
        background: rgba(0,0,0,0.18);
      }
      .mediaPicker .url{
        font-size: 11px;
        color: var(--muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .stage{
        display: grid;
        grid-template-rows: auto 1fr;
        min-width: 0;
      }

      .canvas{
        padding: 16px;
        overflow: auto;
      }
      .framewrap{
        width: 100%;
        display: grid;
        place-items: start center;
      }
      .framebox{
        width: min(1220px, 100%);
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.12);
        background: rgba(255,255,255,0.03);
        box-shadow: var(--shadow);
        overflow: hidden;
      }
      iframe{
        width: 100%;
        height: 78vh;
        border: 0;
        background: white;
      }
      .framebox.mobile iframe{ width: 390px; margin: 0 auto; display:block; }
      .framebox.tablet iframe{ width: 820px; margin: 0 auto; display:block; }

      .hint{
        color: var(--muted);
        font-size: 12px;
        line-height: 1.55;
      }
      .mono{
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 12px;
      }
      .smallbtns{
        display: inline-flex;
        gap: 8px;
        align-items: center;
      }

      @media (max-width: 980px){
        body{ overflow: auto; }
        .app{ grid-template-columns: 1fr; height: auto; min-height: 100%; }
        .sidebar{ border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.10); }
        iframe{ height: 72vh; }
      }
    </style>
  </head>
  <body>
    <div class="app">
      <aside class="sidebar">
        <div class="brand">
          <div class="logo" aria-hidden="true"></div>
          <div>
            <strong>Top Dental Builder</strong>
            <span>Click vào trang để edit, lưu, export</span>
          </div>
        </div>

        <section class="section">
          <header>
            <div>
              <b>Phần tử đang chọn</b>
              <div><small id="selectedMeta">Chưa chọn</small></div>
            </div>
          </header>
          <div class="body" id="editorBody">
            <div class="hint">Click vào bất kỳ chỗ nào trong preview để chọn phần tử.</div>
          </div>
        </section>
      </aside>

      <section class="stage">
        <div class="toolbar">
          <div class="left">
            <span class="pill">Trang: <strong id="pageName">Chưa mở</strong></span>
            <span class="pill">Tip: <strong>Click</strong> để chọn, <strong>Esc</strong> để bỏ chọn</span>
          </div>
          <div class="right">
            <div class="smallbtns">
              <button class="btn" type="button" data-view="desktop">Desktop</button>
              <button class="btn" type="button" data-view="tablet">Tablet</button>
              <button class="btn" type="button" data-view="mobile">Mobile</button>
            </div>
          </div>
        </div>

        <div class="canvas">
          <div class="framewrap">
            <div class="framebox" id="frameBox">
              <iframe id="frame" title="Preview"></iframe>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="saveDock" aria-label="Lưu">
      <button class="btn primary" id="saveBtn" type="button">Lưu</button>
      <div class="state" id="saveState">Chưa lưu</div>
    </div>
    <div class="toast" id="toast">
      <div class="mark" aria-hidden="true">
        <i class="fa-solid fa-circle-check"></i>
      </div>
      <div>
        <strong id="toastTitle">Đã lưu</strong>
        <div id="toastMsg">Cập nhật thành công.</div>
      </div>
    </div>
    <div class="mediaPicker" id="mediaPicker" aria-label="Thư viện">
      <div class="panel">
        <header>
          <div>Chọn ảnh từ thư viện</div>
          <button class="btn" type="button" id="closeMediaPickerBtn">Đóng</button>
        </header>
        <div class="grid" id="mediaGrid"></div>
      </div>
    </div>

    <script>
      const POST_ID = <?php echo json_encode($postId, JSON_UNESCAPED_UNICODE); ?>;
      const POST_TITLE = <?php echo json_encode($postTitle, JSON_UNESCAPED_UNICODE); ?>;
      const POST_SLUG = <?php echo json_encode($postSlug, JSON_UNESCAPED_UNICODE); ?>;
      const BUILDER_URL = <?php echo json_encode('/builder/' . $postId, JSON_UNESCAPED_UNICODE); ?>;
      const INITIAL_FRAME_HTML = <?php echo json_encode($frameHtml, JSON_UNESCAPED_UNICODE); ?>;

      const FONT_AWESOME_CSS = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css";

      const qs = (s, el = document) => el.querySelector(s);
      const qsa = (s, el = document) => Array.from(el.querySelectorAll(s));

      const frame = qs("#frame");
      const frameBox = qs("#frameBox");
      const editorBody = qs("#editorBody");
      const selectedMeta = qs("#selectedMeta");
      const saveState = qs("#saveState");
      const pageNameEl = qs("#pageName");

      const saveBtn = qs("#saveBtn");
      const toastEl = qs("#toast");
      const toastTitleEl = qs("#toastTitle");
      const toastMsgEl = qs("#toastMsg");
      const mediaPickerEl = qs("#mediaPicker");
      const mediaGridEl = qs("#mediaGrid");
      const closeMediaPickerBtn = qs("#closeMediaPickerBtn");
      let mediaApplyMode = "";

      let selected = null;
      let lastName = POST_SLUG ? `${POST_SLUG}.html` : "";
      let dirty = false;

      const history = {
        stack: [],
        index: -1,
        push(html) {
          const normalized = String(html || "");
          if (!normalized.trim()) return;
          const current = this.stack[this.index];
          if (current === normalized) return;
          this.stack = this.stack.slice(0, this.index + 1);
          this.stack.push(normalized);
          this.index = this.stack.length - 1;
          if (this.stack.length > 40) {
            this.stack.shift();
            this.index -= 1;
          }
          updateUndoRedo();
        },
        canUndo() { return this.index > 0; },
        canRedo() { return this.index >= 0 && this.index < this.stack.length - 1; },
        undo() {
          if (!this.canUndo()) return null;
          this.index -= 1;
          updateUndoRedo();
          return this.stack[this.index] || null;
        },
        redo() {
          if (!this.canRedo()) return null;
          this.index += 1;
          updateUndoRedo();
          return this.stack[this.index] || null;
        }
      };

      function setDirty(v) {
        dirty = Boolean(v);
        saveState.textContent = dirty ? "Chưa lưu" : "Đã lưu";
      }

      function setPageName(name) {
        lastName = String(name || "");
        pageNameEl.textContent = lastName ? lastName : "Chưa mở";
      }

      function updateUndoRedo() {
        return;
      }

      function createBuilderCss() {
        return `
          html{ scroll-behavior: smooth; }
          section{
            padding-top: 10px !important;
            padding-bottom: 10px !important;
          }
          section > .container{
            margin-top: -10px !important;
            margin-bottom: -10px !important;
          }
          .__builder-selected{
            outline: 3px solid rgba(59,130,246,0.95) !important;
            outline-offset: 2px !important;
          }
          .__builder-hover{
            outline: 2px dashed rgba(16,182,176,0.95) !important;
            outline-offset: 2px !important;
          }
          .__builder-editing{
            outline: 3px solid rgba(16,182,176,0.95) !important;
            outline-offset: 2px !important;
            caret-color: rgba(15,23,42,0.92);
          }
          .__builder-editing:focus{
            outline-color: rgba(59,130,246,0.95) !important;
          }
          .__builder-popover{
            position: fixed;
            z-index: 2147483000;
            display: none;
            padding: 6px;
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.14);
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(10px);
            box-shadow: 0 16px 60px rgba(15,23,42,0.22);
            color: rgba(15,23,42,0.92);
            max-width: min(760px, calc(100vw - 24px));
          }
          .__builder-popover *{ box-sizing: border-box; }
          .__builder-popover .row{
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
          }
          .__builder-popover .grp{
            display: inline-flex;
            gap: 4px;
            align-items: center;
            padding: 4px 4px;
            border-radius: 11px;
            border: 1px solid rgba(15,23,42,0.10);
            background: rgba(255,255,255,0.70);
          }
          .__builder-popover .btn{
            appearance: none;
            border: 1px solid rgba(15,23,42,0.12);
            background: rgba(255,255,255,0.90);
            color: rgba(15,23,42,0.92);
            border-radius: 10px;
            padding: 6px 10px;
            font: 600 12px/1 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            cursor: pointer;
            user-select: none;
          }
          .__builder-popover .btn i{ font-size: 13px; }
          .__builder-popover .btn:active{ transform: translateY(1px); }
          .__builder-popover .btn.active{
            border-color: rgba(59,130,246,0.34);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.14);
          }
          .__builder-popover .btn.danger{
            border-color: rgba(225,29,72,0.24);
            background: rgba(225,29,72,0.08);
          }
          .__builder-popover .iconbtn{
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
          }
          .__builder-popover .btn:disabled{
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(0.2);
          }
          .__builder-popover input[type="text"],
          .__builder-popover input[type="url"],
          .__builder-popover input[type="number"],
          .__builder-popover select{
            height: 30px;
            border-radius: 10px;
            border: 1px solid rgba(15,23,42,0.12);
            background: rgba(255,255,255,0.92);
            padding: 0 10px;
            font: 500 12px/1 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            color: rgba(15,23,42,0.92);
            outline: none;
          }
          .__builder-popover input[type="color"]{
            width: 32px;
            height: 30px;
            border: 1px solid rgba(15,23,42,0.12);
            border-radius: 10px;
            padding: 0;
            background: transparent;
          }
          .__builder-popover .txt{
            width: min(280px, 42vw);
          }
          .__builder-popover .url{
            width: min(340px, 46vw);
          }
          .__builder-popover .num{
            width: 72px;
            text-align: center;
          }
          .__builder-popover .tag{
            font: 600 12px/1 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            color: rgba(15,23,42,0.60);
            padding: 0 6px;
          }
        `.trim();
      }

      function injectBuilderRuntime(doc) {
        const style = doc.createElement("style");
        style.setAttribute("data-topdental-builder", "1");
        style.textContent = createBuilderCss();
        doc.head.appendChild(style);

        ensureFontAwesome(doc);
        ensurePopover(doc);

        doc.addEventListener("click", (e) => {
          const t = e.target;
          if (!t || !(t instanceof doc.defaultView.HTMLElement)) return;
          if (t.closest("[data-topdental-builder-ui]")) return;
          if (t.tagName === "HTML" || t.tagName === "BODY") return;
          if (editingEl && (t === editingEl || editingEl.contains(t))) return;
          const wrap = doc.querySelector('[data-builder-content="1"]');
          if (wrap && !wrap.contains(t)) return;
          e.preventDefault();
          e.stopPropagation();
          setSelected(t, { x: e.clientX, y: e.clientY });
        }, true);

        doc.addEventListener("mouseover", (e) => {
          const t = e.target;
          if (!t || !(t instanceof doc.defaultView.HTMLElement)) return;
          if (t.tagName === "HTML" || t.tagName === "BODY") return;
          if (selected && t === selected) return;
          const wrap = doc.querySelector('[data-builder-content="1"]');
          if (wrap && !wrap.contains(t)) return;
          clearHover(doc);
          t.classList.add("__builder-hover");
        }, true);

        doc.addEventListener("mouseout", () => {
          clearHover(doc);
        }, true);

        doc.addEventListener("keydown", (e) => {
          if (e.key === "Escape") {
            if (editingEl) {
              e.preventDefault();
              endInlineTextEdit();
              return;
            }
            clearSelected();
          }
        }, true);

        doc.defaultView.addEventListener("scroll", () => schedulePopoverPosition(), true);
        doc.defaultView.addEventListener("resize", () => schedulePopoverPosition(), true);
      }

      function clearHover(doc) {
        qsa(".__builder-hover", doc).forEach((el) => el.classList.remove("__builder-hover"));
      }

      function clearSelected() {
        const doc = frame.contentDocument;
        if (!doc) return;
        endInlineTextEdit();
        if (selected) selected.classList.remove("__builder-selected");
        selected = null;
        selectedMeta.textContent = "Chưa chọn";
        renderEditor(null);
        hidePopover();
      }

      function shortSelector(el) {
        if (!el) return "";
        const id = el.id ? `#${el.id}` : "";
        const cls = el.classList && el.classList.length ? "." + Array.from(el.classList).slice(0, 3).join(".") : "";
        return `${el.tagName.toLowerCase()}${id}${cls}`;
      }

      function setSelected(el, clickPoint = null) {
        const doc = frame.contentDocument;
        if (!doc) return;
        endInlineTextEdit();
        if (selected && selected !== el) selected.classList.remove("__builder-selected");
        selected = el;
        selected.classList.add("__builder-selected");
        selectedMeta.textContent = shortSelector(el);
        renderEditor(el);
        showPopover(el);
        const target = getInlineTextTarget(el);
        if (target && isInlineTextEditable(target)) startInlineTextEdit(target, clickPoint);
      }

      let popoverEl = null;
      let popoverFor = null;
      let popoverRaf = 0;
      let editingEl = null;
      let editingInitialText = "";
      let editingOnInput = null;
      let editingOnBlur = null;
      let editingOnKeyDown = null;

      function isInlineTextEditable(el) {
        if (!el) return false;
        const tag = el.tagName ? el.tagName.toLowerCase() : "";
        const isImg = tag === "img";
        if (isImg) return false;
        if (["html","head","body","script","style","svg","path"].includes(tag)) return false;
        if (tag === "a") return el.children.length === 0;
        const textTags = ["p","span","h1","h2","h3","h4","h5","h6","button","label","li","small","strong","em"];
        if (textTags.includes(tag)) return true;
        return el.children.length === 0;
      }

      function getInlineTextTarget(el) {
        if (!el) return null;
        const tag = el.tagName ? el.tagName.toLowerCase() : "";
        if (tag === "a" && el.children.length > 0) {
          const existing = el.querySelector('[data-topdental-inline-text="1"]');
          if (existing) return existing;

          const meaningfulTextNodes = Array.from(el.childNodes).filter(
            (n) => n.nodeType === 3 && String(n.textContent || "").trim().length > 0
          );
          if (meaningfulTextNodes.length === 0) return null;

          const text = meaningfulTextNodes.map((n) => String(n.textContent || "")).join(" ").replace(/\s+/g, " ").trim();
          meaningfulTextNodes.forEach((n) => n.remove());

          const span = el.ownerDocument.createElement("span");
          span.setAttribute("data-topdental-inline-text", "1");
          span.textContent = text;
          el.appendChild(span);
          return span;
        }
        return el;
      }

      function startInlineTextEdit(el, clickPoint) {
        const doc = frame.contentDocument;
        const win = frame.contentWindow;
        if (!doc || !win) return;
        if (!isInlineTextEditable(el)) return;
        if (el.closest("[data-topdental-builder-ui]")) return;

        editingEl = el;
        editingInitialText = el.textContent || "";

        snapshot();

        el.classList.add("__builder-editing");
        try {
          el.setAttribute("contenteditable", "plaintext-only");
        } catch {
          el.setAttribute("contenteditable", "true");
        }
        el.setAttribute("spellcheck", "false");
        el.focus({ preventScroll: true });

        if (clickPoint && Number.isFinite(clickPoint.x) && Number.isFinite(clickPoint.y)) {
          const r = caretRangeFromPoint(doc, clickPoint.x, clickPoint.y);
          if (r) {
            const sel = win.getSelection();
            sel.removeAllRanges();
            sel.addRange(r);
          } else {
            placeCaretAtEnd(doc, el);
          }
        } else {
          placeCaretAtEnd(doc, el);
        }

        editingOnInput = () => {
          setDirty(true);
          schedulePopoverPosition();
        };
        editingOnBlur = () => {
          win.setTimeout(() => {
            const doc2 = frame.contentDocument;
            if (!doc2 || !editingEl) return;
            const p = popoverEl || doc2.getElementById("__builderPopover");
            const active = doc2.activeElement;
            if (p && active && p.contains(active)) return;
            endInlineTextEdit();
          }, 0);
        };
        editingOnKeyDown = (e) => {
          if (e.key === "Escape") {
            e.preventDefault();
            endInlineTextEdit();
          }
          const isMac = doc.defaultView.navigator.platform.toLowerCase().includes("mac");
          const mod = isMac ? e.metaKey : e.ctrlKey;
          if (mod && e.key.toLowerCase() === "s") {
            e.preventDefault();
            saveToServer();
          }
        };

        el.addEventListener("input", editingOnInput, true);
        el.addEventListener("blur", editingOnBlur, true);
        el.addEventListener("keydown", editingOnKeyDown, true);
      }

      function endInlineTextEdit() {
        const el = editingEl;
        if (!el) return;
        const doc = frame.contentDocument;
        if (!doc) return;

        el.removeEventListener("input", editingOnInput, true);
        el.removeEventListener("blur", editingOnBlur, true);
        el.removeEventListener("keydown", editingOnKeyDown, true);
        editingOnInput = null;
        editingOnBlur = null;
        editingOnKeyDown = null;

        el.removeAttribute("contenteditable");
        el.classList.remove("__builder-editing");

        const after = el.textContent || "";
        const changed = after !== (editingInitialText || "");
        editingEl = null;
        editingInitialText = "";

        if (changed) {
          markChanged();
        } else {
          schedulePopoverPosition();
        }
      }

      function placeCaretAtEnd(doc, el) {
        const win = doc.defaultView;
        const range = doc.createRange();
        range.selectNodeContents(el);
        range.collapse(false);
        const sel = win.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
      }

      function caretRangeFromPoint(doc, x, y) {
        const win = doc.defaultView;
        if (typeof doc.caretRangeFromPoint === "function") {
          return doc.caretRangeFromPoint(x, y);
        }
        if (typeof doc.caretPositionFromPoint === "function") {
          const pos = doc.caretPositionFromPoint(x, y);
          if (!pos) return null;
          const r = doc.createRange();
          r.setStart(pos.offsetNode, pos.offset);
          r.collapse(true);
          return r;
        }
        const sel = win.getSelection();
        if (sel && sel.rangeCount) return sel.getRangeAt(0);
        return null;
      }

      function ensurePopover(doc) {
        const existing = doc.getElementById("__builderPopover");
        if (existing) {
          popoverEl = existing;
          return existing;
        }
        const el = doc.createElement("div");
        el.id = "__builderPopover";
        el.className = "__builder-popover";
        el.setAttribute("data-topdental-builder-ui", "1");
        el.addEventListener("click", (e) => {
          e.stopPropagation();
        });
        doc.body.appendChild(el);
        popoverEl = el;
        return el;
      }

      function ensureFontAwesome(doc) {
        const existing = doc.querySelector('link[data-topdental-fa="1"]');
        if (existing) return;
        const link = doc.createElement("link");
        link.rel = "stylesheet";
        link.href = FONT_AWESOME_CSS;
        link.setAttribute("data-topdental-fa", "1");
        doc.head.appendChild(link);
      }

      function hidePopover() {
        const doc = frame.contentDocument;
        if (!doc) return;
        const p = popoverEl || doc.getElementById("__builderPopover");
        if (!p) return;
        p.style.display = "none";
        p.innerHTML = "";
        popoverFor = null;
      }

      function showPopover(el) {
        const doc = frame.contentDocument;
        if (!doc || !el) return;
        const p = ensurePopover(doc);
        popoverFor = el;
        renderPopover(el, p);
        p.style.display = "block";
        schedulePopoverPosition();
      }

      function schedulePopoverPosition() {
        const win = frame.contentWindow;
        if (!win) return;
        if (popoverRaf) win.cancelAnimationFrame(popoverRaf);
        popoverRaf = win.requestAnimationFrame(() => {
          popoverRaf = 0;
          positionPopover();
        });
      }

      function positionPopover() {
        const doc = frame.contentDocument;
        const win = frame.contentWindow;
        if (!doc || !win) return;
        const p = popoverEl || doc.getElementById("__builderPopover");
        if (!p || p.style.display === "none") return;
        const el = popoverFor;
        if (!el || !doc.contains(el)) {
          hidePopover();
          return;
        }

        const r = el.getBoundingClientRect();
        const margin = 10;
        const pw = p.offsetWidth || 280;
        const ph = p.offsetHeight || 44;

        let left = r.left + (r.width / 2) - (pw / 2);
        left = Math.max(margin, Math.min(left, win.innerWidth - pw - margin));

        const aboveTop = r.top - ph - 10;
        const belowTop = r.bottom + 10;
        let top = aboveTop >= margin ? aboveTop : belowTop;
        top = Math.max(margin, Math.min(top, win.innerHeight - ph - margin));

        p.style.left = `${Math.round(left)}px`;
        p.style.top = `${Math.round(top)}px`;
      }

      function prevSectionSibling(sectionEl) {
        let cur = sectionEl ? sectionEl.previousElementSibling : null;
        while (cur && cur.tagName !== "SECTION") cur = cur.previousElementSibling;
        return cur;
      }

      function nextSectionSibling(sectionEl) {
        let cur = sectionEl ? sectionEl.nextElementSibling : null;
        while (cur && cur.tagName !== "SECTION") cur = cur.nextElementSibling;
        return cur;
      }

      function moveSection(sectionEl, direction) {
        if (!sectionEl || sectionEl.tagName !== "SECTION") return;
        const parent = sectionEl.parentElement;
        if (!parent) return;

        const prev = prevSectionSibling(sectionEl);
        const next = nextSectionSibling(sectionEl);

        if (direction === "up" && !prev) return;
        if (direction === "down" && !next) return;

        snapshot();
        if (direction === "up") {
          parent.insertBefore(sectionEl, prev);
        } else {
          parent.insertBefore(sectionEl, next.nextSibling);
        }
        markChanged();
        setSelected(sectionEl);
        schedulePopoverPosition();
      }

      function renderPopover(el, pop) {
        const doc = el.ownerDocument;
        pop.innerHTML = "";

        const tag = el.tagName.toLowerCase();
        const isImg = tag === "img";
        const isLink = tag === "a";
        const isTextish = !isImg && !["html", "head", "body", "script", "style", "svg", "path"].includes(tag);
        const canBg = ["div", "section", "header", "aside", "main", "article"].includes(tag);
        const textTags = ["p","span","h1","h2","h3","h4","h5","h6","button","a","label","li","small","strong","em"];
        const canEditText = isTextish && (textTags.includes(tag) || el.children.length === 0);

        const row = doc.createElement("div");
        row.className = "row";

        const meta = doc.createElement("span");
        meta.className = "tag";
        meta.textContent = tag.toUpperCase();
        row.appendChild(meta);

        if (canEditText) {
          const fontSelect = doc.createElement("select");
          fontSelect.innerHTML = `
            <option value="">Font</option>
            <option value="Inter">Inter</option>
            <option value="Noto Sans">Noto Sans</option>
            <option value="Roboto">Roboto</option>
            <option value="Arial">Arial</option>
            <option value="Georgia">Georgia</option>
          `;
          const currentFamily = (getInlineOrComputed(el, "fontFamily") || "").split(",")[0].replace(/["']/g, "").trim();
          fontSelect.value = ["Inter","Noto Sans","Roboto","Arial","Georgia"].includes(currentFamily) ? currentFamily : "";
          fontSelect.addEventListener("change", () => {
            if (!fontSelect.value) {
              el.style.fontFamily = "";
            } else {
              el.style.fontFamily = `"${fontSelect.value}", Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial`;
            }
            markChanged();
          });
          const decGrp = doc.createElement("div");
          decGrp.className = "grp";
          decGrp.appendChild(fontSelect);

          const sizeMinus = doc.createElement("button");
          sizeMinus.type = "button";
          sizeMinus.className = "btn iconbtn";
          sizeMinus.title = "Giảm cỡ chữ";
          sizeMinus.innerHTML = '<i class="fa-solid fa-minus"></i>';

          const sizeInput = doc.createElement("input");
          sizeInput.type = "number";
          sizeInput.className = "num";
          sizeInput.min = "8";
          sizeInput.max = "120";
          sizeInput.value = toPxNumber(getInlineOrComputed(el, "fontSize")) || "14";

          const sizePlus = doc.createElement("button");
          sizePlus.type = "button";
          sizePlus.className = "btn iconbtn";
          sizePlus.title = "Tăng cỡ chữ";
          sizePlus.innerHTML = '<i class="fa-solid fa-plus"></i>';

          function applySize(v) {
            const n = clampNum(v, 8, 120);
            if (n === null) return;
            sizeInput.value = String(n);
            el.style.fontSize = `${n}px`;
            markChanged();
            schedulePopoverPosition();
          }

          sizeMinus.addEventListener("click", () => applySize(Number(sizeInput.value || 14) - 1));
          sizePlus.addEventListener("click", () => applySize(Number(sizeInput.value || 14) + 1));
          sizeInput.addEventListener("input", () => applySize(sizeInput.value));

          decGrp.appendChild(sizeMinus);
          decGrp.appendChild(sizeInput);
          decGrp.appendChild(sizePlus);

          const boldBtn = doc.createElement("button");
          boldBtn.type = "button";
          boldBtn.className = "btn iconbtn";
          boldBtn.title = "Đậm";
          boldBtn.innerHTML = '<i class="fa-solid fa-bold"></i>';
          const italicBtn = doc.createElement("button");
          italicBtn.type = "button";
          italicBtn.className = "btn iconbtn";
          italicBtn.title = "Nghiêng";
          italicBtn.innerHTML = '<i class="fa-solid fa-italic"></i>';

          function syncTextBtns() {
            const w = normalizeWeight(getInlineOrComputed(el, "fontWeight"));
            const isBold = Number(w || 400) >= 700;
            const isItalic = (getInlineOrComputed(el, "fontStyle") || "").trim().toLowerCase() === "italic";
            boldBtn.classList.toggle("active", isBold);
            italicBtn.classList.toggle("active", isItalic);
          }

          boldBtn.addEventListener("click", () => {
            const w = normalizeWeight(getInlineOrComputed(el, "fontWeight"));
            const isBold = Number(w || 400) >= 700;
            el.style.fontWeight = isBold ? "400" : "700";
            markChanged();
            syncTextBtns();
          });
          italicBtn.addEventListener("click", () => {
            const isItalic = (getInlineOrComputed(el, "fontStyle") || "").trim().toLowerCase() === "italic";
            el.style.fontStyle = isItalic ? "normal" : "italic";
            markChanged();
            syncTextBtns();
          });
          syncTextBtns();

          const color = doc.createElement("input");
          color.type = "color";
          color.title = "Màu chữ";
          color.value = toHex(getInlineOrComputed(el, "color"));
          color.addEventListener("input", () => {
            el.style.color = color.value;
            markChanged();
          });

          const alignLeft = doc.createElement("button");
          alignLeft.type = "button";
          alignLeft.className = "btn iconbtn";
          alignLeft.title = "Căn trái";
          alignLeft.innerHTML = '<i class="fa-solid fa-align-left"></i>';

          const alignCenter = doc.createElement("button");
          alignCenter.type = "button";
          alignCenter.className = "btn iconbtn";
          alignCenter.title = "Căn giữa";
          alignCenter.innerHTML = '<i class="fa-solid fa-align-center"></i>';

          const alignRight = doc.createElement("button");
          alignRight.type = "button";
          alignRight.className = "btn iconbtn";
          alignRight.title = "Căn phải";
          alignRight.innerHTML = '<i class="fa-solid fa-align-right"></i>';

          function getAlignValue() {
            return (getInlineOrComputed(el, "textAlign") || "").trim().toLowerCase();
          }
          function syncAlignBtns() {
            const a = getAlignValue();
            alignLeft.classList.toggle("active", a === "left");
            alignCenter.classList.toggle("active", a === "center");
            alignRight.classList.toggle("active", a === "right");
          }
          function setAlign(v) {
            el.style.textAlign = v;
            markChanged();
            syncAlignBtns();
          }
          alignLeft.addEventListener("click", () => setAlign("left"));
          alignCenter.addEventListener("click", () => setAlign("center"));
          alignRight.addEventListener("click", () => setAlign("right"));
          syncAlignBtns();

          decGrp.appendChild(boldBtn);
          decGrp.appendChild(italicBtn);
          decGrp.appendChild(color);
          decGrp.appendChild(alignLeft);
          decGrp.appendChild(alignCenter);
          decGrp.appendChild(alignRight);

          row.appendChild(decGrp);
        }

        if (isLink) {
          const grp = doc.createElement("div");
          grp.className = "grp";
          const href = doc.createElement("input");
          href.type = "url";
          href.className = "url";
          href.placeholder = "https://...";
          href.value = el.getAttribute("href") || "";
          href.addEventListener("input", () => {
            el.setAttribute("href", href.value);
            markChanged();
          });
          grp.appendChild(href);
          row.appendChild(grp);
        }

        if (isImg) {
          const grp = doc.createElement("div");
          grp.className = "grp";

          const src = doc.createElement("input");
          src.type = "url";
          src.className = "url";
          src.placeholder = "Image URL";
          src.value = el.getAttribute("src") || "";
          src.addEventListener("input", () => {
            el.setAttribute("src", src.value);
            markChanged();
          });

          const pick = doc.createElement("button");
          pick.type = "button";
          pick.className = "btn iconbtn";
          pick.title = "Chọn từ thư viện";
          pick.innerHTML = '<i class="fa-regular fa-images"></i>';
          pick.addEventListener("click", () => openMediaPicker("img"));

          const alt = doc.createElement("input");
          alt.type = "text";
          alt.className = "txt";
          alt.placeholder = "Alt";
          alt.value = el.getAttribute("alt") || "";
          alt.addEventListener("input", () => {
            el.setAttribute("alt", alt.value);
            markChanged();
          });

          const radius = doc.createElement("input");
          radius.type = "number";
          radius.className = "num";
          radius.min = "0";
          radius.max = "80";
          radius.value = toPxNumber(getInlineOrComputed(el, "borderRadius")) || "";
          radius.addEventListener("input", () => {
            const v = clampNum(radius.value, 0, 80);
            el.style.borderRadius = v === null ? "" : `${v}px`;
            markChanged();
          });

          const fit = doc.createElement("select");
          fit.innerHTML = `
            <option value="">Fit</option>
            <option value="cover">Cover</option>
            <option value="contain">Contain</option>
          `;
          fit.value = (getInlineOrComputed(el, "objectFit") || "").trim().toLowerCase();
          fit.addEventListener("change", () => {
            el.style.objectFit = fit.value;
            markChanged();
          });

          grp.appendChild(src);
          grp.appendChild(pick);
          grp.appendChild(alt);
          grp.appendChild(fit);
          grp.appendChild(radius);
          row.appendChild(grp);
        }

        if (!isImg && (canBg || parseBgImageUrl(getInlineOrComputed(el, "backgroundImage")))) {
          const grp = doc.createElement("div");
          grp.className = "grp";

          const bg = doc.createElement("input");
          bg.type = "url";
          bg.className = "url";
          bg.placeholder = "Background image URL";
          bg.value = parseBgImageUrl(getInlineOrComputed(el, "backgroundImage")) || "";
          bg.addEventListener("input", () => {
            setBgImage(el, bg.value);
            markChanged();
          });

          const pickBg = doc.createElement("button");
          pickBg.type = "button";
          pickBg.className = "btn iconbtn";
          pickBg.title = "Chọn từ thư viện";
          pickBg.innerHTML = '<i class="fa-regular fa-images"></i>';
          pickBg.addEventListener("click", () => openMediaPicker("bg"));

          const bgc = doc.createElement("input");
          bgc.type = "color";
          bgc.value = toHex(getInlineOrComputed(el, "backgroundColor"));
          bgc.addEventListener("input", () => {
            el.style.backgroundColor = bgc.value;
            markChanged();
          });

          const pad = doc.createElement("input");
          pad.type = "number";
          pad.className = "num";
          pad.min = "0";
          pad.max = "120";
          pad.value = toPxNumber(getInlineOrComputed(el, "paddingTop")) || "";
          pad.addEventListener("input", () => {
            const v = clampNum(pad.value, 0, 120);
            el.style.padding = v === null ? "" : `${v}px`;
            markChanged();
            schedulePopoverPosition();
          });

          const radius = doc.createElement("input");
          radius.type = "number";
          radius.className = "num";
          radius.min = "0";
          radius.max = "80";
          radius.value = toPxNumber(getInlineOrComputed(el, "borderRadius")) || "";
          radius.addEventListener("input", () => {
            const v = clampNum(radius.value, 0, 80);
            el.style.borderRadius = v === null ? "" : `${v}px`;
            markChanged();
          });

          grp.appendChild(bgc);
          grp.appendChild(bg);
          grp.appendChild(pickBg);
          grp.appendChild(pad);
          grp.appendChild(radius);
          row.appendChild(grp);
        }

        const actions = doc.createElement("div");
        actions.className = "grp";

        if (tag === "section") {
          const up = doc.createElement("button");
          up.type = "button";
          up.className = "btn iconbtn";
          up.title = "Di chuyển lên";
          up.innerHTML = '<i class="fa-solid fa-chevron-up"></i>';
          up.disabled = !prevSectionSibling(el);
          up.addEventListener("click", () => moveSection(el, "up"));

          const down = doc.createElement("button");
          down.type = "button";
          down.className = "btn iconbtn";
          down.title = "Di chuyển xuống";
          down.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';
          down.disabled = !nextSectionSibling(el);
          down.addEventListener("click", () => moveSection(el, "down"));

          actions.appendChild(up);
          actions.appendChild(down);
        }

        const dup = doc.createElement("button");
        dup.type = "button";
        dup.className = "btn iconbtn";
        dup.title = "Nhân bản";
        dup.innerHTML = '<i class="fa-regular fa-clone"></i>';
        dup.addEventListener("click", () => {
          endInlineTextEdit();
          const clone = el.cloneNode(true);
          el.insertAdjacentElement("afterend", clone);
          setSelected(clone);
          markChanged();
        });

        const del = doc.createElement("button");
        del.type = "button";
        del.className = "btn danger iconbtn";
        del.title = "Xóa";
        del.innerHTML = '<i class="fa-solid fa-trash"></i>';
        del.addEventListener("click", () => {
          const parent = el.parentElement;
          el.remove();
          clearSelected();
          markChanged();
          if (parent) setSelected(parent);
        });

        actions.appendChild(dup);
        actions.appendChild(del);
        row.appendChild(actions);

        pop.appendChild(row);
      }

      function getInlineOrComputed(el, prop) {
        const v = el.style[prop] || "";
        if (v) return v;
        try {
          const cs = el.ownerDocument.defaultView.getComputedStyle(el);
          return cs.getPropertyValue(prop) || "";
        } catch {
          return "";
        }
      }

      function parseBgImageUrl(bg) {
        const s = String(bg || "").trim();
        const m = s.match(/url\((['"]?)(.*?)\1\)/i);
        return m && m[2] ? m[2] : "";
      }

      function setBgImage(el, url) {
        const u = String(url || "").trim();
        if (!u) {
          el.style.backgroundImage = "";
          return;
        }
        el.style.backgroundImage = `url("${u}")`;
        if (!el.style.backgroundSize) el.style.backgroundSize = "cover";
        if (!el.style.backgroundPosition) el.style.backgroundPosition = "center";
        if (!el.style.backgroundRepeat) el.style.backgroundRepeat = "no-repeat";
      }

      function setText(el, value) {
        el.textContent = String(value ?? "");
      }

      function markChanged() {
        setDirty(true);
        snapshot();
      }

      function snapshot() {
        const doc = frame.contentDocument;
        if (!doc) return;
        const html = serializeDoc(doc);
        history.push(html);
      }

      function serializeDoc(doc) {
        const doctype = doc.doctype ? "<!doctype html>" : "<!doctype html>";
        const html = doc.documentElement ? doc.documentElement.outerHTML : "";
        return `${doctype}\n${html}`;
      }

      function serializeCleanDoc(doc) {
        const doctype = "<!doctype html>";
        const root = doc.documentElement ? doc.documentElement.cloneNode(true) : null;
        if (!root) return `${doctype}\n<html></html>`;

        const removeAll = (selector) => {
          root.querySelectorAll(selector).forEach((n) => n.remove());
        };

        removeAll('style[data-topdental-builder="1"]');
        removeAll('link[data-topdental-fa="1"]');
        removeAll("#__builderPopover");
        removeAll("[data-topdental-builder-ui]");

        root.querySelectorAll("*").forEach((el) => {
          el.classList?.remove("__builder-selected", "__builder-hover", "__builder-editing");
          el.removeAttribute?.("contenteditable");
          el.removeAttribute?.("spellcheck");
          el.removeAttribute?.("data-topdental-builder-ui");
        });

        root.querySelectorAll('[data-topdental-inline-text="1"]').forEach((span) => {
          span.replaceWith(String(span.textContent || ""));
        });

        return `${doctype}\n${root.outerHTML}`;
      }

      function getContentForSave(doc) {
        const wrap = doc.querySelector('[data-builder-content="1"]');
        return wrap ? String(wrap.innerHTML || "") : "";
      }

      async function saveToServer() {
        endInlineTextEdit();
        const doc = frame.contentDocument;
        if (!doc) return;
        const content = getContentForSave(doc);
        try {
          const res = await fetch(BUILDER_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ content })
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok || !data.ok) {
            const prev = saveState.textContent;
            saveState.textContent = "Lỗi lưu";
            window.setTimeout(() => {
              if (saveState.textContent === "Lỗi lưu") saveState.textContent = prev;
            }, 1600);
            return;
          }
          setDirty(false);
          showToast("Đã lưu", "Cập nhật thành công.");
        } catch {
          const prev = saveState.textContent;
          saveState.textContent = "Lỗi lưu";
          window.setTimeout(() => {
            if (saveState.textContent === "Lỗi lưu") saveState.textContent = prev;
          }, 1600);
          showToast("Lỗi lưu", "Không thể lưu nội dung.", "danger");
        }
      }

      

      function downloadText(filename, text) {
        const blob = new Blob([text], { type: "text/html;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
      }

      function renderEditor(el) {
        editorBody.innerHTML = "";
        if (!el) {
          const d = document.createElement("div");
          d.className = "hint";
          d.textContent = "Click vào bất kỳ chỗ nào trong preview để chọn phần tử.";
          editorBody.appendChild(d);
          return;
        }

        const doc = el.ownerDocument;
        const tag = el.tagName.toLowerCase();
        const meta = document.createElement("div");
        meta.className = "hint";
        meta.innerHTML = `Tag: <span class="mono">${tag}</span> · ID: <span class="mono">${el.id || "-"}</span> · Class: <span class="mono">${el.className || "-"}</span>`;
        editorBody.appendChild(meta);

        const fields = [];

        const isImg = tag === "img";
        const isLink = tag === "a";
        const hasText = !isImg && !["html","head","body","script","style","svg","path"].includes(tag);
        const nonTextContainers = new Set(["div","section","header","aside","main","article"]);
        const textTags = new Set(["p","span","h1","h2","h3","h4","h5","h6","button","label","li","small","strong","em"]);
        const canEditText = hasText && !nonTextContainers.has(tag) && (textTags.has(tag) || el.children.length === 0);
        const canEditLinkText = isLink && el.children.length === 0;

        if (canEditText || canEditLinkText) {
          const row = makeRow("Text", "textarea", { value: el.textContent || "" });
          row.input.addEventListener("input", () => {
            setText(el, row.input.value);
            markChanged();
          });
          fields.push(row.el);
        }

        if (isLink) {
          const row = makeRow("Href", "input", { value: el.getAttribute("href") || "" });
          row.input.addEventListener("input", () => {
            el.setAttribute("href", row.input.value);
            markChanged();
          });
          fields.push(row.el);
        }

        if (isImg) {
          const srcRow = makeRow("Image src", "input", { value: el.getAttribute("src") || "" });
          srcRow.input.addEventListener("input", () => {
            el.setAttribute("src", srcRow.input.value);
            markChanged();
          });
          fields.push(srcRow.el);

          const pickRow = document.createElement("div");
          pickRow.className = "row";
          const pickBtn = document.createElement("button");
          pickBtn.className = "btn";
          pickBtn.type = "button";
          pickBtn.textContent = "Chọn từ thư viện";
          pickBtn.addEventListener("click", () => openMediaPicker("img"));
          pickRow.appendChild(pickBtn);
          fields.push(pickRow);

          const altRow = makeRow("Alt", "input", { value: el.getAttribute("alt") || "" });
          altRow.input.addEventListener("input", () => {
            el.setAttribute("alt", altRow.input.value);
            markChanged();
          });
          fields.push(altRow.el);
        }

        const bgRaw = getInlineOrComputed(el, "backgroundImage");
        const bgUrl = parseBgImageUrl(bgRaw);
        if (bgUrl || ["div","section","header","aside"].includes(tag)) {
          const row = makeRow("Background image URL", "input", { value: bgUrl || "" });
          row.input.addEventListener("input", () => {
            setBgImage(el, row.input.value);
            markChanged();
          });
          fields.push(row.el);

          const pickBgRow = document.createElement("div");
          pickBgRow.className = "row";
          const pickBgBtn = document.createElement("button");
          pickBgBtn.className = "btn";
          pickBgBtn.type = "button";
          pickBgBtn.textContent = "Chọn từ thư viện";
          pickBgBtn.addEventListener("click", () => openMediaPicker("bg"));
          pickBgRow.appendChild(pickBgBtn);
          fields.push(pickBgRow);
        }

        const colorRow = makeRow("Text color", "input", { value: toHex(getInlineOrComputed(el, "color")) });
        colorRow.input.type = "color";
        colorRow.input.addEventListener("input", () => {
          el.style.color = colorRow.input.value;
          markChanged();
        });
        fields.push(colorRow.el);

        const bgColorRow = makeRow("Background color", "input", { value: toHex(getInlineOrComputed(el, "backgroundColor")) });
        bgColorRow.input.type = "color";
        bgColorRow.input.addEventListener("input", () => {
          el.style.backgroundColor = bgColorRow.input.value;
          markChanged();
        });
        fields.push(bgColorRow.el);

        const fontSizeRow = makeRow("Font size (px)", "input", { value: toPxNumber(getInlineOrComputed(el, "fontSize")) });
        fontSizeRow.input.type = "number";
        fontSizeRow.input.min = "8";
        fontSizeRow.input.max = "120";
        fontSizeRow.input.addEventListener("input", () => {
          const v = clampNum(fontSizeRow.input.value, 8, 120);
          el.style.fontSize = v ? `${v}px` : "";
          markChanged();
        });
        fields.push(fontSizeRow.el);

        const fontWeightRow = makeRow("Font weight", "select", { value: getInlineOrComputed(el, "fontWeight").trim() });
        fontWeightRow.input.innerHTML = `
          <option value="">Auto</option>
          <option value="400">400</option>
          <option value="500">500</option>
          <option value="600">600</option>
          <option value="700">700</option>
          <option value="800">800</option>
        `;
        fontWeightRow.input.value = normalizeWeight(fontWeightRow.value);
        fontWeightRow.input.addEventListener("change", () => {
          el.style.fontWeight = fontWeightRow.input.value;
          markChanged();
        });
        fields.push(fontWeightRow.el);

        const radiusRow = makeRow("Border radius (px)", "input", { value: toPxNumber(getInlineOrComputed(el, "borderRadius")) });
        radiusRow.input.type = "number";
        radiusRow.input.min = "0";
        radiusRow.input.max = "80";
        radiusRow.input.addEventListener("input", () => {
          const v = clampNum(radiusRow.input.value, 0, 80);
          el.style.borderRadius = v === null ? "" : `${v}px`;
          markChanged();
        });
        fields.push(radiusRow.el);

        const padRow = makeRow("Padding (px)", "input", { value: toPxNumber(getInlineOrComputed(el, "paddingTop")) });
        padRow.input.type = "number";
        padRow.input.min = "0";
        padRow.input.max = "120";
        padRow.input.addEventListener("input", () => {
          const v = clampNum(padRow.input.value, 0, 120);
          el.style.padding = v === null ? "" : `${v}px`;
          markChanged();
        });
        fields.push(padRow.el);

        const marRow = makeRow("Margin (px)", "input", { value: toPxNumber(getInlineOrComputed(el, "marginTop")) });
        marRow.input.type = "number";
        marRow.input.min = "-60";
        marRow.input.max = "160";
        marRow.input.addEventListener("input", () => {
          const v = clampNumAllowNegative(marRow.input.value, -60, 160);
          el.style.margin = v === null ? "" : `${v}px`;
          markChanged();
        });
        fields.push(marRow.el);

        const alignRow = makeRow("Text align", "select", { value: getInlineOrComputed(el, "textAlign").trim() });
        alignRow.input.innerHTML = `
          <option value="">Auto</option>
          <option value="left">Left</option>
          <option value="center">Center</option>
          <option value="right">Right</option>
          <option value="justify">Justify</option>
        `;
        alignRow.input.value = (alignRow.value || "").toLowerCase();
        alignRow.input.addEventListener("change", () => {
          el.style.textAlign = alignRow.input.value;
          markChanged();
        });
        fields.push(alignRow.el);

        const delRow = document.createElement("div");
        delRow.className = "btnrow";
        const delBtn = document.createElement("button");
        delBtn.className = "btn danger";
        delBtn.type = "button";
        delBtn.textContent = "Xoá phần tử";
        delBtn.addEventListener("click", () => {
          const parent = el.parentElement;
          el.remove();
          clearSelected();
          markChanged();
          if (parent) setSelected(parent);
        });
        delRow.appendChild(delBtn);

        fields.forEach((f) => editorBody.appendChild(f));
        editorBody.appendChild(delRow);
      }

      function makeRow(labelText, type, { value }) {
        const el = document.createElement("div");
        el.className = "row";
        const label = document.createElement("label");
        label.textContent = labelText;
        const input = type === "textarea"
          ? document.createElement("textarea")
          : type === "select"
            ? document.createElement("select")
            : document.createElement("input");
        if (type !== "select") input.value = String(value ?? "");
        if (type === "select") input.value = String(value ?? "");
        el.appendChild(label);
        el.appendChild(input);
        return { el, input, value: String(value ?? "") };
      }

      function clampNum(v, min, max) {
        const n = Number(v);
        if (!Number.isFinite(n)) return null;
        return Math.min(max, Math.max(min, Math.round(n)));
      }

      function clampNumAllowNegative(v, min, max) {
        const n = Number(v);
        if (!Number.isFinite(n)) return null;
        return Math.min(max, Math.max(min, Math.round(n)));
      }

      function toPxNumber(v) {
        const s = String(v || "").trim();
        const m = s.match(/-?\d+(\.\d+)?/);
        if (!m) return "";
        const n = Number(m[0]);
        return Number.isFinite(n) ? String(Math.round(n)) : "";
      }

      function normalizeWeight(w) {
        const s = String(w || "").trim();
        if (!s) return "";
        const n = Number(s);
        if (!Number.isFinite(n)) return "";
        const allowed = [400, 500, 600, 700, 800];
        const closest = allowed.reduce((best, cur) => Math.abs(cur - n) < Math.abs(best - n) ? cur : best, allowed[0]);
        return String(closest);
      }

      function toHex(color) {
        const c = String(color || "").trim();
        if (!c || c === "transparent") return "#000000";
        const m = c.match(/rgba?\(([^)]+)\)/i);
        if (!m) return "#000000";
        const parts = m[1].split(",").map((p) => Number(String(p).trim()));
        const r = clamp255(parts[0]);
        const g = clamp255(parts[1]);
        const b = clamp255(parts[2]);
        return `#${toHex2(r)}${toHex2(g)}${toHex2(b)}`;
      }

      function clamp255(n) {
        const x = Number(n);
        if (!Number.isFinite(x)) return 0;
        return Math.min(255, Math.max(0, Math.round(x)));
      }
      function toHex2(n) {
        return n.toString(16).padStart(2, "0");
      }

      function showToast(title, message, type = "ok") {
        if (!toastEl) return;
        toastTitleEl.textContent = String(title || "Thông báo");
        toastMsgEl.textContent = String(message || "");
        toastEl.classList.toggle("danger", type === "danger");
        toastEl.classList.add("show");
        window.setTimeout(() => toastEl.classList.remove("show"), 2600);
      }
      function loadHtml(html, name) {
        clearSelected();
        setPageName(name);
        frame.srcdoc = String(html || "");
        setDirty(false);
      }

      frame.addEventListener("load", () => {
        const doc = frame.contentDocument;
        if (!doc) return;
        injectBuilderRuntime(doc);
        const html = serializeDoc(doc);
        history.push(html);
        updateUndoRedo();
      });

      qsa("[data-view]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const v = btn.getAttribute("data-view");
          frameBox.classList.remove("mobile", "tablet");
          if (v === "mobile") frameBox.classList.add("mobile");
          if (v === "tablet") frameBox.classList.add("tablet");
        });
      });

      saveBtn.addEventListener("click", () => saveToServer());
      async function fetchMediaList() {
        try {
          const res = await fetch("/admin/api/media/list.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: "{}" });
          const data = await res.json().catch(() => ({}));
          if (!res.ok || !data.ok) {
            showToast("Lỗi tải thư viện", data.message || "Không tải được thư viện.", "danger");
            return [];
          }
          return Array.isArray(data.files) ? data.files : [];
        } catch {
          showToast("Lỗi mạng", "Không kết nối được API.", "danger");
          return [];
        }
      }
      function openMediaPicker(mode = "") {
        if (!mediaPickerEl || !mediaGridEl) return;
        mediaApplyMode = String(mode || "");
        mediaPickerEl.style.display = "block";
        mediaGridEl.innerHTML = "<div class='hint'>Đang tải...</div>";
        fetchMediaList().then((files) => {
          if (!files.length) {
            mediaGridEl.innerHTML = "<div class='hint'>Chưa có file trong thư viện.</div>";
            return;
          }
          mediaGridEl.innerHTML = "";
          files.forEach((f) => {
            const item = document.createElement("button");
            item.type = "button";
            item.className = "item";
            const img = document.createElement("img");
            img.alt = f.url || "image";
            img.src = f.url;
            const name = document.createElement("div");
            name.className = "url";
            name.textContent = f.url || "";
            item.appendChild(img);
            item.appendChild(name);
            item.addEventListener("click", () => {
              const doc = frame.contentDocument;
              if (!doc || !selected) return;
              const tag = selected.tagName ? selected.tagName.toLowerCase() : "";
              if (mediaApplyMode === "img" && tag !== "img") {
                showToast("Không thể đặt ảnh", "Hãy chọn thẻ img.", "danger");
                return;
              }
              if (mediaApplyMode === "bg") {
                setBgImage(selected, f.url);
                markChanged();
                showToast("Đã đặt ảnh nền", "Ảnh đã áp dụng vào nền.");
                mediaPickerEl.style.display = "none";
                return;
              }
              if (tag === "img") {
                selected.setAttribute("src", f.url);
                markChanged();
                showToast("Đã đặt ảnh", "Ảnh đã áp dụng vào img.");
              } else {
                const canBg = ["div","section","header","aside","main","article"].includes(tag) || Boolean(parseBgImageUrl(getInlineOrComputed(selected, "backgroundImage")));
                if (canBg) {
                  setBgImage(selected, f.url);
                  markChanged();
                  showToast("Đã đặt ảnh nền", "Ảnh đã áp dụng vào nền.");
                } else {
                  showToast("Không thể đặt ảnh", "Chọn img hoặc div/section.", "danger");
                }
              }
              mediaPickerEl.style.display = "none";
            });
            mediaGridEl.appendChild(item);
          });
        });
      }
      mediaPickerEl?.addEventListener("click", (e) => {
        if (e.target === mediaPickerEl) mediaPickerEl.style.display = "none";
      });


      document.addEventListener("keydown", (e) => {
        const isMac = navigator.platform.toLowerCase().includes("mac");
        const mod = isMac ? e.metaKey : e.ctrlKey;
        if (!mod) return;
        if (e.key.toLowerCase() === "s") {
          e.preventDefault();
          saveToServer();
        }
        if (e.key.toLowerCase() === "z" && !e.shiftKey) {
          e.preventDefault();
          const html = history.undo();
          if (!html) return;
          loadHtml(html, lastName || "Đang chỉnh");
          setDirty(true);
        }
        if ((e.key.toLowerCase() === "z" && e.shiftKey) || e.key.toLowerCase() === "y") {
          e.preventDefault();
          const html = history.redo();
          if (!html) return;
          loadHtml(html, lastName || "Đang chỉnh");
          setDirty(true);
        }
      });

      const name = POST_TITLE ? `${POST_TITLE} (#${POST_ID})` : (POST_SLUG ? `${POST_SLUG} (#${POST_ID})` : `#${POST_ID}`);
      loadHtml(INITIAL_FRAME_HTML, name);
    </script>
  </body>
</html>
