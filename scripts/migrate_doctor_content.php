<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
try {
    $pdo = db();
    medreview_with_schema_migration(static fn() => medical_directory_ensure_doctor_content_columns($pdo));
    // Normal migrations preserve custom prompts. Explicit replacement is opt-in only.
    $stmt = $pdo->prepare("SELECT id, template FROM medical_ai_prompts WHERE prompt_key='doctor'");
    $stmt->execute(); $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $legacy = 'Hãy viết bài giới thiệu chuyên môn về bác sĩ "{{name}}". Trình bày chuyên khoa, kinh nghiệm, dịch vụ và điểm nổi bật bằng giọng văn đáng tin cậy. Chỉ dùng thông tin được cung cấp, không bịa chứng chỉ hoặc thành tích. Chỉ trả về JSON hợp lệ theo mẫu: {"name":"{{name}}","title_text":"...","specialty_text":"...","bio":"HTML 300-500 từ","services":["..."],"address":"...","phone":"...","website":"..."}';
    $replacePrompt = in_array('--replace-doctor-prompt', $argv, true);
    if ($row && ($row['template'] === $legacy || $replacePrompt)) {
        $pdo->prepare('UPDATE medical_ai_prompts SET template=:template WHERE id=:id AND template=:old')
            ->execute([':template' => medical_doctor_default_prompt(), ':id' => $row['id'], ':old' => $row['template']]);
        $check = $pdo->prepare('SELECT template FROM medical_ai_prompts WHERE id=:id');
        $check->execute([':id' => $row['id']]);
        if ($check->fetchColumn() !== medical_doctor_default_prompt()) throw new RuntimeException('Prompt vừa thay đổi bởi người khác; không ghi đè.');
        echo $replacePrompt ? "Saved doctor prompt replaced on explicit request.\n" : "Built-in doctor prompt upgraded.\n";
    } elseif (!$row) {
        $pdo->prepare("INSERT INTO medical_ai_prompts (prompt_key,label,template) VALUES ('doctor','Bác sĩ',:template)")
            ->execute([':template' => medical_doctor_default_prompt()]);
    } else echo "Existing custom/current doctor prompt preserved.\n";
    $count = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='medical_doctors'")->fetchColumn();
    echo "Doctor schema ready: {$count} columns; medical_doctor_facilities ready. Existing records preserved.\n";
} catch (Throwable $e) { fwrite(STDERR, 'Doctor migration failed: ' . $e->getMessage() . "\n"); exit(1); }
