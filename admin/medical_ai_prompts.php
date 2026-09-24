<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../medical_directory.php';

admin_require_login();
$pdo = db();
medical_directory_ensure_tables($pdo);

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageUrl = admin_url('medical_ai_prompts.php');
$normalizeCategory = static function (string $value): string {
    if (function_exists('medical_directory_ai_prompt_normalize_category')) {
        return medical_directory_ai_prompt_normalize_category($value);
    }
    $value = trim((string) preg_replace('/\s+/u', ' ', $value));
    return mb_strtolower($value, 'UTF-8');
};

if (empty($_SESSION['medical_ai_prompts_csrf']) || !is_string($_SESSION['medical_ai_prompts_csrf'])) {
    $_SESSION['medical_ai_prompts_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['medical_ai_prompts_csrf'];

$redirect = static function (string $type, string $message, ?string $icon = null) use ($pageUrl): void {
    flash_toast_set($type, $message, $icon);
    header('Location: ' . $pageUrl);
    exit;
};

$basePrompts = $pdo->query(
    'SELECT prompt_key, label, template, updated_at FROM medical_ai_prompts ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$categoryOptions = [];
try {
    $categoryRows = $pdo->query(
        "SELECT DISTINCT TRIM(category) AS category
         FROM medical_facilities
         WHERE TRIM(COALESCE(category, '')) <> ''
         ORDER BY category ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categoryRows as $row) {
        $label = trim((string) ($row['category'] ?? ''));
        $key = $normalizeCategory($label);
        if ($key !== '' && !isset($categoryOptions[$key])) {
            $categoryOptions[$key] = $label;
        }
    }
} catch (Throwable $e) {
    error_log('AI prompt category options failed: ' . $e->getMessage());
}

$parseCategories = static function (array $selected, string $custom, array $known) use ($normalizeCategory): array {
    $items = [];
    foreach ($selected as $item) {
        if (is_scalar($item)) {
            $items[] = (string) $item;
        }
    }
    foreach (preg_split('/[,;\r\n]+/u', $custom) ?: [] as $item) {
        $items[] = (string) $item;
    }

    $result = [];
    foreach ($items as $item) {
        $label = trim((string) preg_replace('/\s+/u', ' ', $item));
        $key = $normalizeCategory($label);
        if ($label !== '' && $key !== '') {
            $result[$key] = $known[$key] ?? $label;
        }
    }
    return $result;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $redirect('danger', 'Phiên làm việc đã hết hạn. Hãy tải lại trang và thử lại.', 'fa-solid fa-triangle-exclamation');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'save_base_prompts') {
        $templates = is_array($_POST['template'] ?? null) ? $_POST['template'] : [];
        $updates = [];
        foreach ($basePrompts as $prompt) {
            $key = (string) $prompt['prompt_key'];
            if (!array_key_exists($key, $templates)) {
                continue;
            }
            $template = trim((string) $templates[$key]);
            if ($template === '') {
                $redirect('danger', 'Prompt “' . (string) $prompt['label'] . '” không được để trống.');
            }
            if (mb_strlen($template, 'UTF-8') > 500000) {
                $redirect('danger', 'Một prompt chung vượt giới hạn 500.000 ký tự.');
            }
            $updates[$key] = $template;
        }
        if ($updates === []) {
            $redirect('warning', 'Không có prompt chung nào để lưu.', 'fa-solid fa-circle-info');
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE medical_ai_prompts SET template = :template WHERE prompt_key = :key');
            foreach ($updates as $key => $template) {
                $stmt->execute([':template' => $template, ':key' => $key]);
            }
            $pdo->commit();
            $redirect('success', 'Đã lưu các prompt chung. Prompt cơ sở y tế vẫn là fallback khi không có quy tắc theo ngành.', 'fa-solid fa-circle-check');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('AI base prompts save failed: ' . $e->getMessage());
            $redirect('danger', 'Không thể lưu prompt chung. Hãy thử lại.');
        }
    }

    if ($action === 'save_variant') {
        $id = max(0, (int) ($_POST['variant_id'] ?? 0));
        $label = trim((string) ($_POST['label'] ?? ''));
        $template = trim((string) ($_POST['variant_template'] ?? ''));
        $priorityInput = trim((string) ($_POST['priority'] ?? '100'));
        $priority = filter_var($priorityInput, FILTER_VALIDATE_INT);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $selected = is_array($_POST['categories'] ?? null) ? $_POST['categories'] : [];
        $categories = $parseCategories($selected, (string) ($_POST['custom_categories'] ?? ''), $categoryOptions);

        if ($label === '' || mb_strlen($label, 'UTF-8') > 120) {
            $redirect('danger', 'Tên prompt là bắt buộc và tối đa 120 ký tự.');
        }
        if ($template === '' || mb_strlen($template, 'UTF-8') > 500000) {
            $redirect('danger', $template === '' ? 'Nội dung prompt không được để trống.' : 'Nội dung prompt vượt giới hạn 500.000 ký tự.');
        }
        if ($priority === false || $priority < 0 || $priority > 65535) {
            $redirect('danger', 'Độ ưu tiên phải là số nguyên từ 0 đến 65.535.');
        }
        if ($categories === []) {
            $redirect('danger', 'Hãy chọn hoặc nhập ít nhất một ngành áp dụng.');
        }
        if (count($categories) > 100) {
            $redirect('danger', 'Mỗi prompt chỉ hỗ trợ tối đa 100 ngành.');
        }

        try {
            $pdo->beginTransaction();
            if ($id > 0) {
                $exists = $pdo->prepare("SELECT id FROM medical_ai_prompt_variants WHERE id = :id AND prompt_type = 'facility' LIMIT 1");
                $exists->execute([':id' => $id]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Prompt theo ngành không tồn tại.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE medical_ai_prompt_variants
                     SET label = :label, template = :template, priority = :priority, is_active = :active
                     WHERE id = :id AND prompt_type = 'facility'"
                );
                $stmt->execute([
                    ':label' => $label,
                    ':template' => $template,
                    ':priority' => $priority,
                    ':active' => $active,
                    ':id' => $id,
                ]);
                $pdo->prepare('DELETE FROM medical_ai_prompt_variant_categories WHERE variant_id = :id')->execute([':id' => $id]);
                $successMessage = 'Đã cập nhật prompt theo ngành.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO medical_ai_prompt_variants (prompt_type, label, template, priority, is_active)
                     VALUES ('facility', :label, :template, :priority, :active)"
                );
                $stmt->execute([
                    ':label' => $label,
                    ':template' => $template,
                    ':priority' => $priority,
                    ':active' => $active,
                ]);
                $id = (int) $pdo->lastInsertId();
                $successMessage = 'Đã tạo prompt theo ngành.';
            }

            $insert = $pdo->prepare(
                'INSERT INTO medical_ai_prompt_variant_categories (variant_id, category_key, category_label)
                 VALUES (:variant_id, :category_key, :category_label)'
            );
            foreach ($categories as $key => $categoryLabel) {
                $insert->execute([
                    ':variant_id' => $id,
                    ':category_key' => $key,
                    ':category_label' => $categoryLabel,
                ]);
            }
            $pdo->commit();
            $redirect('success', $successMessage, 'fa-solid fa-circle-check');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('AI prompt variant save failed: ' . $e->getMessage());
            $redirect('danger', 'Không thể lưu prompt theo ngành. Hãy kiểm tra lại dữ liệu và thử lại.');
        }
    }

    if ($action === 'delete_variant') {
        $id = max(0, (int) ($_POST['variant_id'] ?? 0));
        if ($id <= 0) {
            $redirect('danger', 'Không xác định được prompt cần xoá.');
        }

        try {
            $pdo->beginTransaction();
            $exists = $pdo->prepare("SELECT id FROM medical_ai_prompt_variants WHERE id = :id AND prompt_type = 'facility' LIMIT 1");
            $exists->execute([':id' => $id]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Prompt theo ngành không tồn tại.');
            }
            $pdo->prepare('DELETE FROM medical_ai_prompt_variant_categories WHERE variant_id = :id')->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM medical_ai_prompt_variants WHERE id = :id AND prompt_type = 'facility'")->execute([':id' => $id]);
            $pdo->commit();
            $redirect('success', 'Đã xoá prompt theo ngành. Các cơ sở phù hợp sẽ dùng prompt fallback.', 'fa-solid fa-trash-can');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('AI prompt variant delete failed: ' . $e->getMessage());
            $redirect('danger', 'Không thể xoá prompt theo ngành. Hãy thử lại.');
        }
    }

    $redirect('warning', 'Yêu cầu không hợp lệ.', 'fa-solid fa-circle-info');
}

$variants = [];
$variantCategories = [];
try {
    $variants = $pdo->query(
        "SELECT id, prompt_type, label, template, priority, is_active, created_at, updated_at
         FROM medical_ai_prompt_variants
         WHERE prompt_type = 'facility'
         ORDER BY priority ASC, id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $ids = array_map(static fn(array $variant): int => (int) $variant['id'], $variants);
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT variant_id, category_key, category_label
             FROM medical_ai_prompt_variant_categories
             WHERE variant_id IN ({$placeholders})
             ORDER BY category_label ASC, id ASC"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $variantCategories[(int) $row['variant_id']][] = [
                'key' => (string) ($row['category_key'] ?? ''),
                'label' => (string) ($row['category_label'] ?? ''),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('AI prompt variant load failed: ' . $e->getMessage());
}

$editor = [
    'id' => 0,
    'label' => '',
    'template' => '',
    'priority' => 100,
    'is_active' => 1,
    'categories' => [],
];
$editMessage = '';
$editId = max(0, (int) ($_GET['edit_variant'] ?? 0));
if ($editId > 0) {
    foreach ($variants as $variant) {
        if ((int) $variant['id'] !== $editId) {
            continue;
        }
        $editor = [
            'id' => (int) $variant['id'],
            'label' => (string) $variant['label'],
            'template' => (string) $variant['template'],
            'priority' => (int) $variant['priority'],
            'is_active' => (int) $variant['is_active'],
            'categories' => $variantCategories[(int) $variant['id']] ?? [],
        ];
        break;
    }
    if ($editor['id'] === 0) {
        $editMessage = 'Không tìm thấy prompt theo ngành cần chỉnh sửa.';
    }
}

$selectedEditorCategories = [];
foreach ($editor['categories'] as $category) {
    $key = (string) ($category['key'] ?? '');
    $label = trim((string) ($category['label'] ?? ''));
    if ($key !== '' && $label !== '') {
        $selectedEditorCategories[$key] = $label;
        if (!isset($categoryOptions[$key])) {
            $categoryOptions[$key] = $label;
        }
    }
}
natcasesort($categoryOptions);

$promptNotes = [
    'facility' => 'Prompt fallback. Khi cơ sở không khớp prompt theo ngành nào, tiện ích sẽ dùng mẫu này.',
    'facility_image_prompt' => 'Dùng để AI tạo ảnh đại diện cho cơ sở y tế. Chỉ mô tả khung cảnh, không chèn chữ, logo, số điện thoại hoặc thông tin chưa được cung cấp.',
    'toplist' => 'Dùng để AI lập danh sách cơ sở cho bài Toplist chưa có cơ sở. Giữ nguyên {{id}} / {{toplist_id}} và trả về mảng facilities theo rank_order.',
    'doctor' => 'Dùng cho bài giới thiệu bác sĩ. {{name}} là tên bác sĩ; chỉ viết từ dữ liệu được cung cấp và trả về JSON hợp lệ.',
    'review' => 'Dùng cho review y tế. Giữ giọng văn khách quan, không khẳng định tuyệt đối hoặc tự bịa đánh giá.',
    'translation' => 'Dùng chung cho API dịch hồ sơ cơ sở y tế, bác sĩ và Toplist từ tiếng Việt sang tiếng Anh. Giữ nguyên source_id, dữ liệu thực tế, cấu trúc HTML/JSON và chỉ dịch các trường được cho phép.',
];

$basePromptByKey = [];
foreach ($basePrompts as $basePrompt) {
    $basePromptByKey[(string) $basePrompt['prompt_key']] = $basePrompt;
}
$basePromptTabs = [];
foreach (['facility', 'facility_image_prompt', 'toplist', 'doctor', 'review', 'translation'] as $promptKey) {
    if (isset($basePromptByKey[$promptKey])) {
        $basePromptTabs[] = $basePromptByKey[$promptKey];
        unset($basePromptByKey[$promptKey]);
    }
}
foreach ($basePromptByKey as $basePrompt) {
    $basePromptTabs[] = $basePrompt;
}
$basePromptDefaultKey = isset($basePrompts[0]) ? (string) $basePrompts[0]['prompt_key'] : '';
foreach ($basePromptTabs as $basePrompt) {
    if ((string) $basePrompt['prompt_key'] === 'facility') {
        $basePromptDefaultKey = 'facility';
        break;
    }
}

$adminPageTitle = 'Admin • Prompt AI';
$adminHeaderTitle = 'Prompt AI';
$adminHeaderSubtitle = 'Quản lý prompt chung và prompt tuỳ biến theo ngành cơ sở y tế';
$adminActive = 'medical-ai-prompts';
require __DIR__ . '/_layout_start.php';
?>
<style>
  .ai-prompt-hero { border:1px solid rgba(127,169,235,.3); background:linear-gradient(125deg,#f8fbff 0%,#eef5ff 62%,#f3fffb 100%); }
  .ai-prompt-hero .card-body { padding:1.1rem 1.35rem; }
  .ai-prompt-source { display:inline-grid; width:36px; height:36px; place-items:center; border-radius:11px; color:#2563eb; background:#eaf2ff; }
  .ai-prompt-tabs { display:flex; gap:.25rem; overflow-x:auto; padding:.3rem; border:1px solid #e0e9f6; border-radius:.85rem; background:#f6f9fd; scrollbar-width:none; }
  .ai-prompt-tabs::-webkit-scrollbar { display:none; }
  .ai-prompt-tab { flex:0 0 auto; border:0; border-radius:.62rem; padding:.56rem .78rem; color:#617089; background:transparent; font-size:.82rem; font-weight:700; transition:background .15s ease,color .15s ease,box-shadow .15s ease; }
  .ai-prompt-tab:hover { color:#1d4fbe; background:#edf4ff; }
  .ai-prompt-tab.is-active { color:#1552c7; background:#fff; box-shadow:0 4px 12px rgba(37,99,235,.11); }
  .ai-prompt-panel { padding:1.15rem; border:1px solid #e3ebf7; border-radius:.9rem; background:linear-gradient(135deg,#fff,#fbfdff); }
  .ai-prompt-panel[hidden] { display:none!important; }
  .ai-prompt-template { min-height:235px; resize:vertical; font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:.82rem; line-height:1.58; }
  .ai-prompt-fallback { border-color:#bdd8ff!important; background:linear-gradient(135deg,#fff 0%,#f4f8ff 100%); }
  .ai-prompt-chip { display:inline-flex; max-width:100%; align-items:center; gap:.32rem; padding:.28rem .54rem; border:1px solid #d9e7fb; border-radius:999px; color:#285aaf; background:#f3f8ff; font-size:.75rem; font-weight:700; line-height:1.2; }
  .ai-prompt-chip i { color:#16886e; font-size:.65rem; }
  .ai-prompt-variant-list { overflow:hidden; border:1px solid #e2eaf4; border-radius:1rem; background:#fff; }
  .ai-prompt-variant-row { display:grid; grid-template-columns:54px minmax(0,1fr) auto; gap:1rem; align-items:start; padding:1rem 1.15rem; border-bottom:1px solid #edf1f7; transition:background .15s ease; }
  .ai-prompt-variant-row:last-child { border-bottom:0; }
  .ai-prompt-variant-row:hover { background:#fbfdff; }
  .ai-prompt-variant-row.is-inactive { background:#fbfcfe; opacity:.75; }
  .ai-prompt-priority { display:grid; width:42px; height:42px; place-items:center; border-radius:.8rem; color:#1f5fd3; background:#edf4ff; font-size:.85rem; font-weight:800; }
  .ai-prompt-row-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.45rem; }
  .ai-prompt-modal .modal-content { overflow:hidden; border-radius:1.15rem; background:#fbfdff; }
  /* The form is the modal-content flex child, so it must constrain modal-body for scrollable dialogs. */
  .ai-prompt-modal form { display:flex; flex:1 1 auto; flex-direction:column; min-height:0; overflow:hidden; }
  .ai-prompt-modal .modal-body { flex:1 1 auto; min-height:0; overflow-y:auto; overscroll-behavior:contain; -webkit-overflow-scrolling:touch; }
  .ai-prompt-modal .modal-header { min-height:64px; background:linear-gradient(135deg,#f7fbff,#eff7ff); }
  .ai-prompt-modal .modal-footer { min-height:64px; background:#fff; }
  .ai-prompt-modal-layout { display:grid; grid-template-columns:minmax(300px,340px) minmax(0,1fr); min-height:min(620px,74vh); }
  .ai-prompt-config { padding:1.25rem; border-right:1px solid #e4ebf5; background:linear-gradient(180deg,#f7faff,#fdfefe); }
  .ai-prompt-editor { display:flex; min-width:0; flex-direction:column; padding:1.25rem 1.4rem; background:#fff; }
  .ai-prompt-editor .ai-prompt-template { flex:1; min-height:420px; }
  .ai-prompt-category-filter { position:relative; }
  .ai-prompt-category-filter i { position:absolute; top:50%; left:.72rem; color:#8090a8; transform:translateY(-50%); pointer-events:none; }
  .ai-prompt-category-filter input { padding-left:2.1rem; }
  .ai-category-picker { max-height:230px; overflow:auto; padding:.4rem; border:1px solid #dce6f4; border-radius:.75rem; background:#fff; }
  .ai-category-option { display:flex; align-items:center; gap:.58rem; margin:0; padding:.48rem .55rem; border-radius:.55rem; color:#41516a; cursor:pointer; font-size:.82rem; font-weight:600; }
  .ai-category-option:hover { background:#f1f6ff; }
  .ai-category-option input { width:1rem; height:1rem; margin:0; accent-color:#2563eb; }
  .ai-category-option.is-hidden { display:none; }
  .ai-prompt-variable { display:inline-flex; align-items:center; padding:.24rem .43rem; border:1px solid #dce7f7; border-radius:.4rem; color:#47617e; background:#f8fbff; font:700 .7rem/1 ui-monospace,SFMono-Regular,Menlo,monospace; }
  @media (max-width: 991.98px) { .ai-prompt-modal-layout { grid-template-columns:1fr; min-height:0; } .ai-prompt-config { border-right:0; border-bottom:1px solid #e4ebf5; } .ai-prompt-editor .ai-prompt-template { min-height:320px; } }
  @media (max-width: 767.98px) { .ai-prompt-hero .card-body { padding:1rem; } .ai-prompt-template { min-height:190px; } .ai-prompt-variant-row { grid-template-columns:42px minmax(0,1fr); gap:.8rem; padding:.9rem; } .ai-prompt-row-actions { grid-column:1 / -1; justify-content:flex-start; } .ai-prompt-priority { width:36px; height:36px; border-radius:.65rem; } .ai-prompt-config,.ai-prompt-editor { padding:1rem; } }
</style>

<div class="card border-0 shadow-soft ai-prompt-hero mb-4">
  <div class="card-body p-4 p-lg-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex gap-3 align-items-start">
        <span class="ai-prompt-source flex-shrink-0"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
        <div>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h2 class="h4 mb-0">Prompt nội dung &amp; Ảnh AI</h2><span class="badge text-bg-light border">Cơ sở · Ảnh AI · Toplist · Bác sĩ · Review · Dịch VI→EN</span></div>
          <p class="text-secondary mb-0">Prompt theo ngành được ưu tiên cho cơ sở phù hợp; nếu không có, tiện ích Chrome tự dùng prompt chung của cơ sở y tế. Prompt Ảnh AI dùng riêng cho luồng tạo ảnh cơ sở.</p>
        </div>
      </div>
      <button class="btn btn-primary flex-shrink-0" type="button" data-bs-toggle="modal" data-bs-target="#facilityVariantModal"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Tạo prompt theo ngành</button>
    </div>
  </div>
</div>

<?php if ($editMessage !== ''): ?>
  <div class="alert alert-warning border-0 shadow-sm mb-4"><i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i><?php echo $escape($editMessage); ?></div>
<?php endif; ?>

<section class="card border-0 shadow-soft mb-4" aria-labelledby="base-prompts-heading">
  <div class="card-body p-3 p-lg-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-3 px-1">
      <div>
        <div class="eyebrow text-primary mb-1">MẶC ĐỊNH &amp; FALLBACK</div>
        <h2 id="base-prompts-heading" class="h5 mb-1">Prompt chung theo loại nội dung</h2>
        <p class="small text-secondary mb-0">Prompt cơ sở y tế là fallback khi không có mẫu khớp ngành. Các tab còn lại vẫn được lưu trong cùng một lần gửi.</p>
      </div>
      <span class="badge text-bg-light border flex-shrink-0"><i class="fa-solid fa-shield-halved me-1 text-success" aria-hidden="true"></i>Prompt chung</span>
    </div>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
      <input type="hidden" name="action" value="save_base_prompts">
      <div class="ai-prompt-tabs mb-3" role="tablist" aria-label="Loại prompt chung">
        <?php foreach ($basePromptTabs as $prompt): ?>
          <?php $promptKey = (string) $prompt['prompt_key']; $isActive = $promptKey === $basePromptDefaultKey; ?>
          <button class="ai-prompt-tab <?php echo $isActive ? 'is-active' : ''; ?>" type="button" role="tab" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>" aria-controls="basePromptPanel<?php echo $escape($promptKey); ?>" data-base-prompt-tab="<?php echo $escape($promptKey); ?>">
            <?php if ($promptKey === 'facility'): ?><i class="fa-solid fa-building-medical me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'facility_image_prompt'): ?><i class="fa-solid fa-image me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'toplist'): ?><i class="fa-solid fa-list-ol me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'doctor'): ?><i class="fa-solid fa-user-doctor me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'review'): ?><i class="fa-solid fa-star me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'translation'): ?><i class="fa-solid fa-language me-1" aria-hidden="true"></i><?php endif; ?>
            <?php echo $escape($prompt['label']); ?>
          </button>
        <?php endforeach; ?>
      </div>
      <?php foreach ($basePromptTabs as $prompt): ?>
        <?php $promptKey = (string) $prompt['prompt_key']; $isActive = $promptKey === $basePromptDefaultKey; $isFacilityFallback = $promptKey === 'facility'; ?>
        <section id="basePromptPanel<?php echo $escape($promptKey); ?>" class="ai-prompt-panel <?php echo $isFacilityFallback ? 'ai-prompt-fallback' : ''; ?>" role="tabpanel" data-base-prompt-panel="<?php echo $escape($promptKey); ?>" <?php echo $isActive ? '' : 'hidden'; ?>>
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
              <label class="form-label fw-bold mb-1" for="basePrompt<?php echo $escape($promptKey); ?>"><?php echo $escape($prompt['label']); ?></label>
              <?php if ($isFacilityFallback): ?><span class="badge text-bg-primary ms-2">Fallback theo ngành</span><?php endif; ?>
              <div class="form-text mt-1"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?php echo $escape($promptNotes[$promptKey] ?? 'Chỉ trả về JSON hợp lệ.'); ?></div>
            </div>
            <?php if (!empty($prompt['updated_at'])): ?><span class="small text-secondary">Cập nhật: <?php echo $escape($prompt['updated_at']); ?></span><?php endif; ?>
          </div>
          <textarea id="basePrompt<?php echo $escape($promptKey); ?>" name="template[<?php echo $escape($promptKey); ?>]" class="form-control ai-prompt-template" rows="9" required><?php echo $escape($prompt['template']); ?></textarea>
        </section>
      <?php endforeach; ?>
      <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu prompt chung</button></div>
    </form>
  </div>
</section>

<section id="facility-custom-prompts" class="card border-0 shadow-soft mb-4" aria-labelledby="facility-variants-heading">
  <div class="card-body p-4 p-lg-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
      <div>
        <div class="eyebrow text-success mb-1">ƯU TIÊN THEO NGÀNH</div>
        <h2 id="facility-variants-heading" class="h4 mb-1">Prompt tuỳ biến cho cơ sở y tế</h2>
        <p class="text-secondary mb-0">Một prompt có thể áp dụng cho nhiều ngành. Khi nhiều prompt cùng khớp, hệ thống lấy <strong>độ ưu tiên nhỏ hơn</strong> trước, rồi mới quay về prompt fallback.</p>
      </div>
      <span class="small text-secondary"><i class="fa-solid fa-layer-group text-primary me-1" aria-hidden="true"></i><?php echo count($variants); ?> prompt tuỳ biến</span>
    </div>

    <div class="row g-4">
      <div class="col-12">
        <div class="ai-prompt-variant-list">
          <?php if ($variants === []): ?>
            <div class="p-4 text-center text-secondary">
              <i class="fa-solid fa-sparkles d-block text-primary fs-4 mb-2" aria-hidden="true"></i>
              <div class="fw-semibold text-dark">Chưa có prompt theo ngành</div>
              <div class="small mt-1">Dùng nút “Tạo prompt theo ngành” phía trên để thêm mẫu đầu tiên.</div>
            </div>
          <?php else: ?>
            <?php foreach ($variants as $variant): ?>
              <?php $variantId = (int) $variant['id']; $categories = $variantCategories[$variantId] ?? []; ?>
              <article class="ai-prompt-variant-row <?php echo (int) $variant['is_active'] === 1 ? '' : 'is-inactive'; ?>">
                <div class="ai-prompt-priority" title="Độ ưu tiên"><?php echo (int) $variant['priority']; ?></div>
                <div class="min-w-0">
                  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h3 class="h6 mb-0 text-break"><?php echo $escape($variant['label']); ?></h3>
                    <?php if ((int) $variant['is_active'] === 1): ?>
                      <span class="badge rounded-pill text-bg-success"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>Đang dùng</span>
                    <?php else: ?>
                      <span class="badge rounded-pill text-bg-secondary"><i class="fa-solid fa-pause me-1" aria-hidden="true"></i>Tạm tắt</span>
                    <?php endif; ?>
                  </div>
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    <?php foreach ($categories as $category): ?>
                      <span class="ai-prompt-chip"><i class="fa-solid fa-tag" aria-hidden="true"></i><span class="text-truncate"><?php echo $escape($category['label']); ?></span></span>
                    <?php endforeach; ?>
                    <?php if ($categories === []): ?><span class="small text-warning"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>Chưa gán ngành</span><?php endif; ?>
                  </div>
                  <p class="small text-secondary mb-0 text-break"><?php echo $escape(mb_strimwidth(trim((string) $variant['template']), 0, 180, '…', 'UTF-8')); ?></p>
                </div>
                <div class="ai-prompt-row-actions">
                  <a class="btn btn-sm btn-outline-primary" href="<?php echo $escape($pageUrl); ?>?edit_variant=<?php echo $variantId; ?>#facility-custom-prompts"><i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>Sửa</a>
                  <form method="post" class="d-inline" onsubmit="return window.confirm('Xoá prompt theo ngành này? Các cơ sở đang khớp sẽ dùng prompt fallback.');">
                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="delete_variant">
                    <input type="hidden" name="variant_id" value="<?php echo $variantId; ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Xoá prompt"><i class="fa-solid fa-trash-can" aria-hidden="true"></i><span class="visually-hidden">Xoá</span></button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade ai-prompt-modal" id="facilityVariantModal" tabindex="-1" aria-labelledby="facilityVariantModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered modal-fullscreen-lg-down">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom px-3 px-lg-4 py-2">
        <div class="d-flex align-items-center gap-2 min-w-0">
          <span class="ai-prompt-source flex-shrink-0"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
          <div class="min-w-0">
            <div class="eyebrow text-success mb-0">PROMPT THEO NGÀNH</div>
            <h2 id="facilityVariantModalTitle" class="modal-title h6 mb-0 text-truncate"><?php echo $editor['id'] > 0 ? 'Chỉnh prompt theo ngành' : 'Tạo prompt theo ngành'; ?></h2>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <?php if ($editor['id'] > 0): ?><a class="btn btn-sm btn-light border" href="<?php echo $escape($pageUrl); ?>#facility-custom-prompts">Tạo mới</a><?php endif; ?>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
      </div>
      <form method="post" id="facilityVariantForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
        <input type="hidden" name="action" value="save_variant">
        <input type="hidden" name="variant_id" value="<?php echo (int) $editor['id']; ?>">
        <div class="modal-body p-0">
          <div class="ai-prompt-modal-layout">
            <aside class="ai-prompt-config" aria-label="Cấu hình prompt theo ngành">
              <div class="small fw-bold text-uppercase text-secondary mb-3" style="letter-spacing:.07em">Cấu hình</div>
              <div class="mb-3">
                <label class="form-label fw-semibold" for="variantLabel">Tên nội bộ <span class="text-danger">*</span></label>
                <input id="variantLabel" name="label" class="form-control" maxlength="120" required value="<?php echo $escape($editor['label']); ?>" placeholder="Ví dụ: Nha khoa chuyên sâu">
                <div class="form-text">Chỉ hiện trong Admin và metadata API.</div>
              </div>
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label class="form-label fw-semibold" for="variantPriority">Ưu tiên</label>
                  <input id="variantPriority" name="priority" class="form-control" type="number" min="0" max="65535" step="1" required value="<?php echo (int) $editor['priority']; ?>">
                  <div class="form-text">0 cao nhất.</div>
                </div>
                <div class="col-6 d-flex align-items-end">
                  <div class="form-check form-switch mb-2">
                    <input class="form-check-input" id="variantActive" name="is_active" type="checkbox" value="1" <?php echo (int) $editor['is_active'] === 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-semibold" for="variantActive">Bật dùng</label>
                    <div class="form-text">Tắt = fallback.</div>
                  </div>
                </div>
              </div>
              <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                  <label class="form-label fw-semibold mb-0" for="variantCategoryFilter">Ngành áp dụng <span class="text-danger">*</span></label>
                  <span class="small text-secondary" id="variantCategoryCount" aria-live="polite"></span>
                </div>
                <div class="ai-prompt-category-filter mb-2"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="form-control form-control-sm" id="variantCategoryFilter" type="search" placeholder="Tìm ngành…" autocomplete="off"></div>
                <div id="variantCategories" class="ai-category-picker" role="group" aria-describedby="variantCategoriesHelp">
                  <?php foreach ($categoryOptions as $categoryKey => $categoryLabel): ?>
                    <label class="ai-category-option" data-category-option data-category-search="<?php echo $escape($categoryLabel); ?>">
                      <input type="checkbox" name="categories[]" value="<?php echo $escape($categoryLabel); ?>" <?php echo isset($selectedEditorCategories[$categoryKey]) ? 'checked' : ''; ?>>
                      <span><?php echo $escape($categoryLabel); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div id="variantCategoriesHelp" class="form-text">Chọn một hoặc nhiều ngành có sẵn.</div>
              </div>
              <div class="mb-0">
                <label class="form-label fw-semibold" for="variantCustomCategories">Ngành thủ công <span class="text-secondary fw-normal">(tuỳ chọn)</span></label>
                <textarea id="variantCustomCategories" name="custom_categories" class="form-control" rows="3" placeholder="Da liễu, Xét nghiệm&#10;Mỗi dòng/dấu phẩy là một ngành"></textarea>
              </div>
            </aside>
            <section class="ai-prompt-editor" aria-labelledby="variantTemplateLabel">
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                <div>
                  <label id="variantTemplateLabel" class="form-label fw-bold mb-1" for="variantTemplate">Nội dung prompt <span class="text-danger">*</span></label>
                  <div class="small text-secondary">Đây là mẫu AI nhận được khi cơ sở khớp ngành đã chọn.</div>
                </div>
                <span class="badge text-bg-light border align-self-start">JSON hợp lệ</span>
              </div>
              <div class="d-flex flex-wrap gap-1 mb-2" aria-label="Biến có thể dùng">
                <?php foreach (['{{id}}', '{{name}}', '{{address}}', '{{phone}}', '{{website}}', '{{hours}}', '{{gallery_json}}'] as $variable): ?><code class="ai-prompt-variable"><?php echo $escape($variable); ?></code><?php endforeach; ?>
              </div>
              <textarea id="variantTemplate" name="variant_template" class="form-control ai-prompt-template" rows="18" required placeholder="Viết prompt riêng cho ngành đã chọn…"><?php echo $escape($editor['template']); ?></textarea>
              <div class="form-text mt-2"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Giữ nguyên các biến cần thiết để API chèn đúng dữ liệu cơ sở.</div>
            </section>
          </div>
        </div>
        <div class="modal-footer border-top px-3 px-lg-4 py-2">
          <span class="small text-secondary me-auto d-none d-sm-inline"><?php echo $editor['id'] > 0 ? 'Thay đổi có hiệu lực cho lần tiện ích lấy dữ liệu tiếp theo.' : 'Prompt chung sẽ được dùng nếu không có ngành khớp.'; ?></span>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">Huỷ</button>
          <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i><?php echo $editor['id'] > 0 ? 'Lưu thay đổi' : 'Tạo prompt'; ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var picker = document.getElementById('variantCategories');
  var filter = document.getElementById('variantCategoryFilter');
  var count = document.getElementById('variantCategoryCount');
  if (picker && filter && count) {
    var categoryInputs = picker.querySelectorAll('input[name="categories[]"]');
    var categoryRows = picker.querySelectorAll('[data-category-option]');
    function updateCount() {
      var selected = Array.prototype.filter.call(categoryInputs, function (input) { return input.checked; }).length;
      count.textContent = selected ? selected + ' ngành chọn' : 'Chưa chọn';
    }
    function applyFilter() {
      var query = String(filter.value || '').trim().toLocaleLowerCase('vi-VN');
      Array.prototype.forEach.call(categoryRows, function (row) {
        var label = String(row.getAttribute('data-category-search') || '').toLocaleLowerCase('vi-VN');
        row.classList.toggle('is-hidden', query !== '' && label.indexOf(query) === -1);
      });
    }
    Array.prototype.forEach.call(categoryInputs, function (input) { input.addEventListener('change', updateCount); });
    filter.addEventListener('input', applyFilter);
    updateCount();
  }

  var tabs = document.querySelectorAll('[data-base-prompt-tab]');
  var panels = document.querySelectorAll('[data-base-prompt-panel]');
  function activateBasePrompt(key) {
    Array.prototype.forEach.call(tabs, function (tab) {
      var active = tab.getAttribute('data-base-prompt-tab') === key;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    Array.prototype.forEach.call(panels, function (panel) {
      panel.hidden = panel.getAttribute('data-base-prompt-panel') !== key;
    });
  }
  Array.prototype.forEach.call(tabs, function (tab) {
    tab.addEventListener('click', function () { activateBasePrompt(tab.getAttribute('data-base-prompt-tab')); });
  });

  var modalNode = document.getElementById('facilityVariantModal');
  if (modalNode && window.bootstrap && <?php echo $editor['id'] > 0 ? 'true' : 'false'; ?>) {
    window.bootstrap.Modal.getOrCreateInstance(modalNode).show();
  }
});
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
