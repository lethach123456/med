<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../medical_directory.php';
require_once __DIR__ . '/../medical_handbook_ai.php';

admin_require_login();
$pdo = db();

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageUrl = admin_url('medical_ai_prompts.php');
$promptTypeLabels = [
    'facility' => 'Cơ sở y tế',
    'doctor' => 'Bác sĩ',
    'handbook' => 'Cẩm nang',
];
$requestedType = (string) ($_POST['prompt_type'] ?? $_GET['type'] ?? 'facility');
$selectedType = array_key_exists($requestedType, $promptTypeLabels) ? $requestedType : 'facility';
$scopeLabels = [
    'facility' => ['name' => 'Ngành áp dụng', 'item' => 'ngành', 'hint' => 'Chọn ngành có sẵn hoặc nhập thêm ngành riêng.'],
    'doctor' => ['name' => 'Chuyên môn áp dụng', 'item' => 'chuyên môn', 'hint' => 'Chọn chuyên môn có sẵn hoặc nhập một cụm từ xuất hiện trong chuyên môn bác sĩ.'],
    'handbook' => ['name' => 'Ngôn ngữ hoặc chủ đề áp dụng', 'item' => 'phạm vi', 'hint' => 'Chọn Tiếng Việt/Tiếng Anh hoặc nhập cụm từ trong tiêu đề bài, ví dụ “niềng răng”.'],
];
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

$redirect = static function (string $type, string $message, ?string $icon = null) use ($pageUrl, $selectedType): void {
    flash_toast_set($type, $message, $icon);
    header('Location: ' . $pageUrl . '?type=' . rawurlencode($selectedType));
    exit;
};

$basePrompts = $pdo->query(
    'SELECT prompt_key, label, template, updated_at FROM medical_ai_prompts ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);
$basePrompts = handbook_ai_admin_prompts($basePrompts);

$categoryOptions = [];
try {
    if ($selectedType === 'facility') {
        $categoryRows = $pdo->query(
            "SELECT DISTINCT TRIM(category) AS label FROM medical_facilities
             WHERE TRIM(COALESCE(category, '')) <> '' ORDER BY label ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($selectedType === 'doctor') {
        $categoryRows = $pdo->query(
            "SELECT DISTINCT TRIM(specialty_text) AS label FROM medical_doctors
             WHERE TRIM(COALESCE(specialty_text, '')) <> '' ORDER BY label ASC LIMIT 120"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $categoryRows = [['label' => 'vi'], ['label' => 'en']];
    }
    foreach ($categoryRows as $row) {
        $label = trim((string) ($row['label'] ?? ''));
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
                if ($key === 'handbook') {
                    // Explicit CSRF-valid save also supports the built-in row
                    // before the optional handbook migration has seeded it.
                    $pdo->prepare("INSERT INTO medical_ai_prompts(prompt_key,label,template) VALUES('handbook','Viết bài Cẩm nang',:template) ON DUPLICATE KEY UPDATE template=VALUES(template)")
                        ->execute([':template'=>$template]);
                } else {
                    $stmt->execute([':template' => $template, ':key' => $key]);
                }
            }
            $pdo->commit();
            $redirect('success', 'Đã lưu prompt chung. Mẫu này được dùng khi không có prompt tuỳ biến phù hợp.', 'fa-solid fa-circle-check');
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
        if (count($categories) > 100) {
            $redirect('danger', 'Mỗi prompt chỉ hỗ trợ tối đa 100 phạm vi.');
        }
        foreach ($categories as $key => $categoryLabel) {
            if (mb_strlen((string) $categoryLabel, 'UTF-8') > 120 || mb_strlen((string) $key, 'UTF-8') > 120) {
                $redirect('danger', 'Mỗi phạm vi áp dụng tối đa 120 ký tự.');
            }
        }

        try {
            $pdo->beginTransaction();
            if ($id > 0) {
                $exists = $pdo->prepare('SELECT id FROM medical_ai_prompt_variants WHERE id = :id AND prompt_type = :type LIMIT 1');
                $exists->execute([':id' => $id, ':type' => $selectedType]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Prompt tuỳ biến không tồn tại trong nhóm đã chọn.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE medical_ai_prompt_variants
                     SET label = :label, template = :template, priority = :priority, is_active = :active
                     WHERE id = :id AND prompt_type = :type"
                );
                $stmt->execute([
                    ':label' => $label,
                    ':template' => $template,
                    ':priority' => $priority,
                    ':active' => $active,
                    ':id' => $id,
                    ':type' => $selectedType,
                ]);
                $pdo->prepare('DELETE FROM medical_ai_prompt_variant_categories WHERE variant_id = :id')->execute([':id' => $id]);
                $successMessage = 'Đã cập nhật prompt tuỳ biến.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO medical_ai_prompt_variants (prompt_type, label, template, priority, is_active)
                     VALUES (:type, :label, :template, :priority, :active)"
                );
                $stmt->execute([
                    ':label' => $label,
                    ':template' => $template,
                    ':priority' => $priority,
                    ':active' => $active,
                    ':type' => $selectedType,
                ]);
                $id = (int) $pdo->lastInsertId();
                $successMessage = 'Đã tạo prompt tuỳ biến.';
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
            $redirect('danger', 'Không thể lưu prompt tuỳ biến. Hãy kiểm tra lại dữ liệu và thử lại.');
        }
    }

    if ($action === 'delete_variant') {
        $id = max(0, (int) ($_POST['variant_id'] ?? 0));
        if ($id <= 0) {
            $redirect('danger', 'Không xác định được prompt cần xoá.');
        }

        try {
            $pdo->beginTransaction();
            $exists = $pdo->prepare('SELECT id FROM medical_ai_prompt_variants WHERE id = :id AND prompt_type = :type LIMIT 1');
            $exists->execute([':id' => $id, ':type' => $selectedType]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Prompt tuỳ biến không tồn tại trong nhóm đã chọn.');
            }
            $pdo->prepare('DELETE FROM medical_ai_prompt_variant_categories WHERE variant_id = :id')->execute([':id' => $id]);
            $pdo->prepare('DELETE FROM medical_ai_prompt_variants WHERE id = :id AND prompt_type = :type')->execute([':id' => $id, ':type' => $selectedType]);
            $pdo->commit();
            $redirect('success', 'Đã xoá prompt tuỳ biến. Hồ sơ phù hợp sẽ dùng prompt chung.', 'fa-solid fa-trash-can');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('AI prompt variant delete failed: ' . $e->getMessage());
            $redirect('danger', 'Không thể xoá prompt tuỳ biến. Hãy thử lại.');
        }
    }

    $redirect('warning', 'Yêu cầu không hợp lệ.', 'fa-solid fa-circle-info');
}

$variants = [];
$variantCategories = [];
$variantCounts = array_fill_keys(array_keys($promptTypeLabels), 0);
try {
    $counts = $pdo->query(
        "SELECT prompt_type, COUNT(*) AS total FROM medical_ai_prompt_variants
         WHERE prompt_type IN ('facility','doctor','handbook') GROUP BY prompt_type"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($counts as $countRow) {
        $variantCounts[(string) $countRow['prompt_type']] = (int) $countRow['total'];
    }
    $variantsStmt = $pdo->prepare(
        "SELECT id, prompt_type, label, template, priority, is_active, created_at, updated_at
         FROM medical_ai_prompt_variants
         WHERE prompt_type = :type
         ORDER BY priority ASC, id DESC"
    );
    $variantsStmt->execute([':type' => $selectedType]);
    $variants = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);

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
    'type' => $selectedType,
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
            'type' => $selectedType,
            'label' => (string) $variant['label'],
            'template' => (string) $variant['template'],
            'priority' => (int) $variant['priority'],
            'is_active' => (int) $variant['is_active'],
            'categories' => $variantCategories[(int) $variant['id']] ?? [],
        ];
        break;
    }
    if ($editor['id'] === 0) {
        $editMessage = 'Không tìm thấy prompt tuỳ biến trong nhóm này.';
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
    'facility' => 'Prompt chung. Khi cơ sở không khớp prompt tuỳ biến nào, tiện ích sẽ dùng mẫu này.',
    'facility_image_prompt' => 'Dùng để AI tạo ảnh đại diện cho cơ sở y tế. Chỉ mô tả khung cảnh, không chèn chữ, logo, số điện thoại hoặc thông tin chưa được cung cấp.',
    'facility_image_fix' => 'Kiểm tra ảnh bìa, ảnh AI và gallery của đúng cơ sở/chi nhánh; chấp nhận ảnh thực tế không gian, thiết bị hoặc đội ngũ bác sĩ đã xác minh. Giữ ảnh hợp lệ, tìm thay thế mọi ảnh không phù hợp; nếu cả bộ ảnh sai thì tìm lại toàn bộ, tối thiểu 5 ảnh thật. Ưu tiên Google Maps → website chính thức → Facebook → nguồn công khai khác; thiếu ảnh phải báo trung thực, không bịa URL. Không xem 403/timeout/CAPTCHA là ảnh hỏng. URL phải là chuỗi thuần, không [URL](URL); inspected_images[].url giữ nguyên source.images[].url, không thay bằng inspection_url. ai_image_url luôn là chuỗi: nguồn null/rỗng trả chuỗi rỗng, không trả null; URL đang có chỉ xóa khi được remove có bằng chứng, không đưa ảnh mới vào trường này. Hỗ trợ {{id}}, {{name}}, {{address}}, {{city}}, {{website}}, {{google_maps_url}}, {{target_images}}, {{source_json}}, {{images_revision}}, {{output_template}}. API tự bổ sung các quy tắc hiện hành kể cả mẫu đã lưu; JSON nằm trong block code json, tiện ích tự gắn writer_claim_token khi gửi về.',
    'toplist' => 'Lập danh sách cơ sở, bác sĩ hoặc cả hai (entity_type=mixed). Hỗ trợ {{id}}, {{toplist_id}}, {{title}}, {{entity_type}}, {{entity_label}}, {{member_key}}, {{output_template}}. Mixed dùng members có type=facility/doctor cùng ID tương ứng; rank_order xếp chung cả hai loại. Prompt cơ sở cũ tự thay bằng prompt phù hợp cho bài bác sĩ/hỗn hợp; JSON bắt buộc trong block code json.',
    'toplist_doctor' => 'Prompt riêng cho danh sách bác sĩ trong bài Toplist (không phải nghiên cứu hồ sơ bác sĩ riêng lẻ). API tự chọn mẫu này khi entity_type=doctor. Hỗ trợ {{id}}, {{toplist_id}}, {{title}}, {{excerpt}}, {{content}}, {{entity_type}}, {{member_key}}, {{output_template}}. Đầu ra doctors với doctor_id/name/specialty_text/city/facility_name/rank_order, bắt buộc trong block code json; không tự tạo điểm đánh giá hay xác minh.',
    'doctor' => 'Prompt nghiên cứu hồ sơ bác sĩ: nhận diện đúng người, đào tạo/công tác, giấy phép, chuyên môn, nơi khám, lịch/giá, nguồn chứng minh, SEO và đánh giá công khai có thật. Nhận aggregate_ratings_json (score/count) và reviews_json có URL/bằng chứng, entity_type=doctor, doctor_id/name đúng hồ sơ; không dùng điểm cơ sở. Nguồn đánh giá tách khỏi nguồn chuyên môn/pháp lý; không dùng trang booking/review y tế bên thứ ba, không bịa review hoặc tự chấm verified. Hỗ trợ {{id}}, {{name}}, {{specialty}}, {{facility_name}}, {{city}}, {{address}}, {{phone}}, {{website}}, {{source_json}}, {{output_template}}. API tự bổ sung contract cho cả mẫu đã lưu, tự tính điểm; AI không gửi rating/reviews_count gốc. JSON bắt buộc trong block code json.',
    'handbook' => 'Viết bài Cẩm nang theo chủ đề và ngôn ngữ được giao. BẮT BUỘC có suggested_facilities_json và suggested_doctors_json (mảng riêng, có thể []), tối đa 8 hồ sơ mỗi loại; chỉ ghi tên/địa chỉ/chuyên khoa, nơi công tác, URL bằng chứng công khai đã kiểm tra; không bịa ID, đánh giá hoặc quan hệ công tác. API so khớp tên + địa chỉ/chuyên môn, ghép hồ sơ đủ gần (>=90–95%), tạo hồ sơ published nhưng chưa xác minh và nội dung trống để hàng đợi viết hồ sơ xử lý; trường hợp mơ hồ hoặc thiếu bằng chứng trả unresolved, không tự tạo. Ảnh cần là file thật theo contract JSON + linkN_base64.txt. Bài Cẩm nang chỉ xuất bản khi nội dung, ảnh và gợi ý đã lưu thành công. Hỗ trợ {{id}}, {{title}}, {{topic}}, {{language}}, {{brief}}, {{source_json}}, {{output_template}}; API luôn thêm quy tắc mới nhất.',
    'review' => 'Dùng cho review y tế. Giữ giọng văn khách quan, không khẳng định tuyệt đối hoặc tự bịa đánh giá.',
    'translation' => 'Dùng chung cho API dịch hồ sơ cơ sở y tế, bác sĩ và Toplist từ tiếng Việt sang tiếng Anh. Giữ nguyên source_id, dữ liệu thực tế, cấu trúc HTML/JSON; dịch đúng các trường được cho phép. API sẽ yêu cầu AI trả kết quả trong một block code ```json.',
];

$basePromptByKey = [];
foreach ($basePrompts as $basePrompt) {
    $basePromptByKey[(string) $basePrompt['prompt_key']] = $basePrompt;
}
$basePromptTabs = [];
foreach (['facility', 'facility_image_prompt', 'facility_image_fix', 'toplist', 'toplist_doctor', 'doctor', 'handbook', 'review', 'translation'] as $promptKey) {
    if (isset($basePromptByKey[$promptKey])) {
        $basePromptTabs[] = $basePromptByKey[$promptKey];
        unset($basePromptByKey[$promptKey]);
    }
}
foreach ($basePromptByKey as $basePrompt) {
    $basePromptTabs[] = $basePrompt;
}
$basePromptDefaultKey = $selectedType;
if (!in_array($selectedType, array_column($basePromptTabs, 'prompt_key'), true)) {
    $basePromptDefaultKey = isset($basePromptTabs[0]) ? (string) $basePromptTabs[0]['prompt_key'] : '';
}
if (is_string($_GET['prompt'] ?? null)) {
    foreach ($basePromptTabs as $basePrompt) {
        if ($basePrompt['prompt_key'] === $_GET['prompt']) $basePromptDefaultKey = $_GET['prompt'];
    }
}

$adminPageTitle = 'Admin • Prompt AI';
$adminHeaderTitle = 'Prompt AI';
$adminHeaderSubtitle = 'Mẫu chung và mẫu tuỳ biến cho cơ sở y tế, bác sĩ, Cẩm nang';
$adminActive = 'medical-ai-prompts';
require __DIR__ . '/_layout_start.php';
?>
<style>
  .ai-prompt-hero { border:1px solid rgba(127,169,235,.3); background:linear-gradient(125deg,#f8fbff 0%,#eef5ff 62%,#f3fffb 100%); }
  .ai-prompt-hero .card-body { padding:1.1rem 1.35rem; }
  .ai-prompt-source { display:inline-grid; width:36px; height:36px; place-items:center; border-radius:11px; color:#2563eb; background:#eaf2ff; }
  .ai-prompt-types { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.65rem; }
  .ai-prompt-type { display:flex; min-width:0; align-items:center; gap:.75rem; padding:.85rem 1rem; border:1px solid #dce6f4; border-radius:.8rem; color:#364e70; background:#fff; text-decoration:none; transition:border-color .15s ease,background .15s ease,box-shadow .15s ease; }
  .ai-prompt-type:hover,.ai-prompt-type:focus-visible { color:#1958c5; border-color:#8bb4ff; background:#f8fbff; }
  .ai-prompt-type.is-active { color:#174ba8; border-color:#93b8fa; background:#eff6ff; box-shadow:0 2px 10px rgba(36,95,200,.07); }
  .ai-prompt-type strong { display:block; font-size:.91rem; }
  .ai-prompt-type small { display:block; color:#65748b; }
  .ai-prompt-type .ai-prompt-type-count { margin-left:auto; border-radius:999px; padding:.12rem .5rem; color:#3563a4; background:#e7f0ff; font-size:.76rem; font-weight:700; }
  .ai-prompt-type:focus-visible,.ai-prompt-tab:focus-visible,.ai-prompt-row-actions .btn:focus-visible { outline:3px solid #74a5ff; outline-offset:2px; }
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
  @media (max-width: 767.98px) { .ai-prompt-hero .card-body { padding:1rem; } .ai-prompt-types { grid-template-columns:1fr; gap:.45rem; } .ai-prompt-type { padding:.62rem .8rem; } .ai-prompt-type small { display:none; } .ai-prompt-template { min-height:190px; } .ai-prompt-variant-row { grid-template-columns:42px minmax(0,1fr); gap:.8rem; padding:.9rem; } .ai-prompt-row-actions { grid-column:1 / -1; justify-content:flex-start; } .ai-prompt-priority { width:36px; height:36px; border-radius:.65rem; } .ai-prompt-config,.ai-prompt-editor { padding:1rem; } }
</style>

<div class="card border-0 shadow-soft ai-prompt-hero mb-4">
  <div class="card-body p-4 p-lg-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
      <div class="d-flex gap-3 align-items-start">
        <span class="ai-prompt-source flex-shrink-0"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
        <div>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h2 class="h4 mb-0">Quản lý prompt nội dung</h2><span class="badge text-bg-light border">3 luồng viết bài</span></div>
          <p class="text-secondary mb-0">Chọn loại nội dung, chỉnh mẫu mặc định hoặc thêm nhiều mẫu theo ngành, chuyên môn, ngôn ngữ và chủ đề. Khi không có mẫu phù hợp, hệ thống dùng prompt chung.</p>
        </div>
      </div>
      <button class="btn btn-primary flex-shrink-0" type="button" data-bs-toggle="modal" data-bs-target="#promptVariantModal"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Thêm mẫu <?php echo $escape($promptTypeLabels[$selectedType]); ?></button>
    </div>
    <nav class="ai-prompt-types mt-4" aria-label="Chọn loại nội dung cần chỉnh">
      <?php foreach ($promptTypeLabels as $typeKey => $typeLabel): ?>
        <?php $typeActive = $typeKey === $selectedType; ?>
        <a class="ai-prompt-type <?php echo $typeActive ? 'is-active' : ''; ?>" href="<?php echo $escape($pageUrl); ?>?type=<?php echo $escape($typeKey); ?>" <?php echo $typeActive ? 'aria-current="page"' : ''; ?>>
          <i class="fa-solid <?php echo $typeKey === 'facility' ? 'fa-building-medical' : ($typeKey === 'doctor' ? 'fa-user-doctor' : 'fa-book-open'); ?>" aria-hidden="true"></i>
          <span class="min-w-0"><strong><?php echo $escape($typeLabel); ?></strong><small><?php echo $typeKey === 'facility' ? 'Theo ngành' : ($typeKey === 'doctor' ? 'Theo chuyên môn' : 'Theo ngôn ngữ / chủ đề'); ?></small></span>
          <span class="ai-prompt-type-count" aria-label="<?php echo (int) ($variantCounts[$typeKey] ?? 0); ?> mẫu tuỳ biến"><?php echo (int) ($variantCounts[$typeKey] ?? 0); ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
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
        <p class="small text-secondary mb-0">Đang chỉnh <?php echo $escape($promptTypeLabels[$selectedType]); ?>. Có thể mở các tab khác để sửa prompt bổ trợ; mỗi lần lưu chỉ gửi mẫu đang mở.</p>
      </div>
      <span class="badge text-bg-light border flex-shrink-0"><i class="fa-solid fa-shield-halved me-1 text-success" aria-hidden="true"></i>Prompt chung</span>
    </div>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
      <input type="hidden" name="action" value="save_base_prompts">
      <input type="hidden" name="prompt_type" value="<?php echo $escape($selectedType); ?>">
      <div class="ai-prompt-tabs mb-3" role="tablist" aria-label="Loại prompt chung">
        <?php foreach ($basePromptTabs as $prompt): ?>
          <?php $promptKey = (string) $prompt['prompt_key']; $isActive = $promptKey === $basePromptDefaultKey; ?>
          <button class="ai-prompt-tab <?php echo $isActive ? 'is-active' : ''; ?>" type="button" role="tab" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>" aria-controls="basePromptPanel<?php echo $escape($promptKey); ?>" data-base-prompt-tab="<?php echo $escape($promptKey); ?>">
            <?php if ($promptKey === 'facility'): ?><i class="fa-solid fa-building-medical me-1" aria-hidden="true"></i><?php elseif (in_array($promptKey, ['facility_image_prompt', 'facility_image_fix'], true)): ?><i class="fa-solid fa-image me-1" aria-hidden="true"></i><?php elseif (in_array($promptKey, ['toplist', 'toplist_doctor'], true)): ?><i class="fa-solid fa-list-ol me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'doctor'): ?><i class="fa-solid fa-user-doctor me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'handbook'): ?><i class="fa-solid fa-book-open me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'review'): ?><i class="fa-solid fa-star me-1" aria-hidden="true"></i><?php elseif ($promptKey === 'translation'): ?><i class="fa-solid fa-language me-1" aria-hidden="true"></i><?php endif; ?>
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
              <?php if (in_array($promptKey, ['facility', 'doctor', 'handbook'], true)): ?><span class="badge text-bg-primary ms-2">Mẫu dự phòng</span><?php endif; ?>
              <?php if (!empty($prompt['is_builtin'])): ?><div class="small text-secondary mt-1">Mẫu mặc định có sẵn. Bấm Lưu prompt chung để lưu vào DB. API nhận bài vẫn cần chạy migration Cẩm nang.</div><?php endif; ?>
              <div class="form-text mt-1"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?php echo $escape($promptNotes[$promptKey] ?? 'Chỉ trả về JSON hợp lệ.'); ?></div>
            </div>
            <?php if (!empty($prompt['updated_at'])): ?><span class="small text-secondary">Cập nhật: <?php echo $escape($prompt['updated_at']); ?></span><?php endif; ?>
          </div>
          <textarea id="basePrompt<?php echo $escape($promptKey); ?>" name="template[<?php echo $escape($promptKey); ?>]" class="form-control ai-prompt-template" rows="9" required><?php echo $escape($prompt['template']); ?></textarea>
        </section>
      <?php endforeach; ?>
      <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu prompt đang mở</button></div>
    </form>
  </div>
</section>

<section id="custom-prompts" class="card border-0 shadow-soft mb-4" aria-labelledby="variants-heading">
  <div class="card-body p-4 p-lg-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
      <div>
        <div class="eyebrow text-success mb-1">MẪU TUỲ BIẾN</div>
        <h2 id="variants-heading" class="h4 mb-1">Prompt <?php echo $escape($promptTypeLabels[$selectedType]); ?></h2>
        <p class="text-secondary mb-0">Có thể tạo nhiều mẫu cho cùng loại nội dung. Gắn <?php echo $escape($scopeLabels[$selectedType]['item']); ?> để chọn theo hồ sơ; bỏ trống phạm vi để mẫu áp dụng toàn bộ. Ưu tiên <strong>số nhỏ hơn</strong> trước, sau cùng là prompt chung.</p>
      </div>
      <span class="small text-secondary"><i class="fa-solid fa-layer-group text-primary me-1" aria-hidden="true"></i><?php echo count($variants); ?> prompt tuỳ biến</span>
    </div>

    <div class="row g-4">
      <div class="col-12">
        <div class="ai-prompt-variant-list">
          <?php if ($variants === []): ?>
            <div class="p-4 text-center text-secondary">
              <i class="fa-solid fa-sparkles d-block text-primary fs-4 mb-2" aria-hidden="true"></i>
              <div class="fw-semibold text-dark">Chưa có prompt tuỳ biến</div>
              <div class="small mt-1">Dùng nút “Thêm mẫu <?php echo $escape($promptTypeLabels[$selectedType]); ?>” để tạo mẫu đầu tiên.</div>
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
                    <?php if ($categories === []): ?><span class="ai-prompt-chip"><i class="fa-solid fa-globe" aria-hidden="true"></i>Áp dụng toàn bộ</span><?php endif; ?>
                  </div>
                  <p class="small text-secondary mb-0 text-break"><?php echo $escape(mb_strimwidth(trim((string) $variant['template']), 0, 180, '…', 'UTF-8')); ?></p>
                </div>
                <div class="ai-prompt-row-actions">
                  <a class="btn btn-sm btn-outline-primary" href="<?php echo $escape($pageUrl); ?>?type=<?php echo $escape($selectedType); ?>&amp;edit_variant=<?php echo $variantId; ?>#custom-prompts"><i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>Sửa</a>
                  <form method="post" class="d-inline" onsubmit="return window.confirm('Xoá prompt này? Hồ sơ phù hợp sẽ dùng mẫu dự phòng.');">
                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="delete_variant">
                    <input type="hidden" name="prompt_type" value="<?php echo $escape($selectedType); ?>">
                    <input type="hidden" name="variant_id" value="<?php echo $variantId; ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Xoá prompt <?php echo $escape($variant['label']); ?>"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
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

<div class="modal fade ai-prompt-modal" id="promptVariantModal" tabindex="-1" aria-labelledby="promptVariantModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered modal-fullscreen-lg-down">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom px-3 px-lg-4 py-2">
        <div class="d-flex align-items-center gap-2 min-w-0">
          <span class="ai-prompt-source flex-shrink-0"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>
          <div class="min-w-0">
            <div class="eyebrow text-success mb-0"><?php echo $escape(mb_strtoupper($promptTypeLabels[$selectedType], 'UTF-8')); ?></div>
            <h2 id="promptVariantModalTitle" class="modal-title h6 mb-0 text-truncate"><?php echo $editor['id'] > 0 ? 'Chỉnh prompt tuỳ biến' : 'Tạo prompt tuỳ biến'; ?></h2>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <?php if ($editor['id'] > 0): ?><a class="btn btn-sm btn-light border" href="<?php echo $escape($pageUrl); ?>?type=<?php echo $escape($selectedType); ?>#custom-prompts">Tạo mới</a><?php endif; ?>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
      </div>
      <form method="post" id="promptVariantForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
        <input type="hidden" name="action" value="save_variant">
        <input type="hidden" name="prompt_type" value="<?php echo $escape($selectedType); ?>">
        <input type="hidden" name="variant_id" value="<?php echo (int) $editor['id']; ?>">
        <div class="modal-body p-0">
          <div class="ai-prompt-modal-layout">
            <aside class="ai-prompt-config" aria-label="Cấu hình prompt theo ngành">
              <div class="small fw-bold text-uppercase text-secondary mb-3" style="letter-spacing:.07em">Cấu hình</div>
              <div class="mb-3">
                <label class="form-label fw-semibold" for="variantLabel">Tên nội bộ <span class="text-danger">*</span></label>
                <input id="variantLabel" name="label" class="form-control" maxlength="120" required value="<?php echo $escape($editor['label']); ?>" placeholder="<?php echo $selectedType === 'handbook' ? 'Ví dụ: Cẩm nang nha khoa' : ($selectedType === 'doctor' ? 'Ví dụ: Chuyên khoa mắt' : 'Ví dụ: Nha khoa chuyên sâu'); ?>">
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
                  <label class="form-label fw-semibold mb-0" for="variantCategoryFilter"><?php echo $escape($scopeLabels[$selectedType]['name']); ?> <span class="text-secondary fw-normal">(tuỳ chọn)</span></label>
                  <span class="small text-secondary" id="variantCategoryCount" aria-live="polite"></span>
                </div>
                <div class="ai-prompt-category-filter mb-2"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="form-control form-control-sm" id="variantCategoryFilter" type="search" aria-label="Tìm <?php echo $escape($scopeLabels[$selectedType]['item']); ?>" placeholder="Tìm <?php echo $escape($scopeLabels[$selectedType]['item']); ?>…" autocomplete="off"></div>
                <div id="variantCategories" class="ai-category-picker" role="group" aria-describedby="variantCategoriesHelp">
                  <?php foreach ($categoryOptions as $categoryKey => $categoryLabel): ?>
                    <label class="ai-category-option" data-category-option data-category-search="<?php echo $escape($categoryLabel); ?>">
                      <input type="checkbox" name="categories[]" value="<?php echo $escape($categoryLabel); ?>" <?php echo isset($selectedEditorCategories[$categoryKey]) ? 'checked' : ''; ?>>
                      <span><?php echo $escape($categoryLabel); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div id="variantCategoriesHelp" class="form-text"><?php echo $escape($scopeLabels[$selectedType]['hint']); ?> Bỏ trống cả hai ô phạm vi nếu muốn áp dụng mẫu cho tất cả.</div>
              </div>
              <div class="mb-0">
                <label class="form-label fw-semibold" for="variantCustomCategories">Thêm <?php echo $escape($scopeLabels[$selectedType]['item']); ?> thủ công</label>
                <textarea id="variantCustomCategories" name="custom_categories" class="form-control" rows="3" placeholder="<?php echo $selectedType === 'handbook' ? 'Ví dụ: niềng răng, implant' : ($selectedType === 'doctor' ? 'Ví dụ: Mắt trẻ em, Răng Hàm Mặt' : 'Ví dụ: Da liễu, Xét nghiệm'); ?>&#10;Mỗi dòng hoặc dấu phẩy là một phạm vi"></textarea>
              </div>
            </aside>
            <section class="ai-prompt-editor" aria-labelledby="variantTemplateLabel">
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                <div>
                  <label id="variantTemplateLabel" class="form-label fw-bold mb-1" for="variantTemplate">Nội dung prompt <span class="text-danger">*</span></label>
                  <div class="small text-secondary">Mẫu này áp dụng cho <?php echo $escape($promptTypeLabels[$selectedType]); ?> khi khớp phạm vi đã chọn.</div>
                </div>
                <span class="badge text-bg-light border align-self-start">JSON hợp lệ</span>
              </div>
              <div class="d-flex flex-wrap gap-1 mb-2" aria-label="Biến có thể dùng">
                <?php $variables = $selectedType === 'handbook' ? ['{{id}}', '{{title}}', '{{topic}}', '{{language}}', '{{brief}}', '{{source_json}}', '{{output_template}}'] : ($selectedType === 'doctor' ? ['{{id}}', '{{name}}', '{{specialty}}', '{{facility_name}}', '{{city}}', '{{address}}', '{{source_json}}', '{{output_template}}'] : ['{{id}}', '{{name}}', '{{address}}', '{{phone}}', '{{website}}', '{{hours}}', '{{gallery_json}}']); ?>
                <?php foreach ($variables as $variable): ?><code class="ai-prompt-variable"><?php echo $escape($variable); ?></code><?php endforeach; ?>
              </div>
              <textarea id="variantTemplate" name="variant_template" class="form-control ai-prompt-template" rows="18" required placeholder="Viết hướng dẫn AI cho mẫu này…"><?php echo $escape($editor['template']); ?></textarea>
              <div class="form-text mt-2"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>API sẽ chèn dữ liệu hồ sơ/chủ đề và bổ sung các quy tắc JSON bắt buộc trước khi gửi cho AI.</div>
            </section>
          </div>
        </div>
        <div class="modal-footer border-top px-3 px-lg-4 py-2">
          <span class="small text-secondary me-auto d-none d-sm-inline"><?php echo $editor['id'] > 0 ? 'Thay đổi có hiệu lực từ lượt lấy dữ liệu tiếp theo.' : 'Không khớp mẫu nào sẽ dùng prompt chung.'; ?></span>
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
      count.textContent = selected ? selected + ' phạm vi đã chọn' : 'Toàn bộ nếu để trống';
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
      var active = panel.getAttribute('data-base-prompt-panel') === key;
      panel.hidden = !active;
      var input = panel.querySelector('textarea[name^="template["]');
      if (input) input.disabled = !active;
    });
  }
  Array.prototype.forEach.call(tabs, function (tab) {
    tab.addEventListener('click', function () { activateBasePrompt(tab.getAttribute('data-base-prompt-tab')); });
  });
  if (tabs.length) activateBasePrompt(<?php echo json_encode($basePromptDefaultKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);

  var modalNode = document.getElementById('promptVariantModal');
  if (modalNode && window.bootstrap && <?php echo $editor['id'] > 0 ? 'true' : 'false'; ?>) {
    window.bootstrap.Modal.getOrCreateInstance(modalNode).show();
  }
});
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
