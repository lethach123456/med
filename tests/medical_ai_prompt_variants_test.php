<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE medical_ai_prompts (prompt_key TEXT PRIMARY KEY, label TEXT, template TEXT)');
$pdo->exec('CREATE TABLE medical_ai_prompt_variants (id INTEGER PRIMARY KEY AUTOINCREMENT, prompt_type TEXT, label TEXT, template TEXT, priority INTEGER, is_active INTEGER, updated_at TEXT)');
$pdo->exec('CREATE TABLE medical_ai_prompt_variant_categories (variant_id INTEGER, category_key TEXT, category_label TEXT)');
foreach (['facility', 'doctor', 'handbook', 'review'] as $type) {
    $stmt = $pdo->prepare('INSERT INTO medical_ai_prompts VALUES (?,?,?)');
    $stmt->execute([$type, 'Default ' . $type, 'Base ' . $type]);
}
$add = static function (string $type, string $label, array $scopes = [], int $priority = 100, int $active = 1) use ($pdo): int {
    $stmt = $pdo->prepare('INSERT INTO medical_ai_prompt_variants (prompt_type,label,template,priority,is_active,updated_at) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$type, $label, 'Template ' . $label, $priority, $active, '2026-10-16 10:00:00']);
    $id = (int) $pdo->lastInsertId();
    foreach ($scopes as $scope) {
        $stmt = $pdo->prepare('INSERT INTO medical_ai_prompt_variant_categories VALUES (?,?,?)');
        $stmt->execute([$id, medical_directory_ai_prompt_normalize_category($scope), $scope]);
    }
    return $id;
};

$facilityGlobal = $add('facility', 'Facility shared', [], 1);
$facilityBroad = $add('facility', 'Dental', ['Nha khoa'], 1);
$facilitySpecific = $add('facility', 'Dental cosmetic', ['Nha khoa thẩm mỹ'], 50);
$assert(medical_directory_resolve_ai_prompt($pdo, 'facility', 'NHA KHOA THẨM MỸ')['variant_id'] === $facilitySpecific,
    'longest facility scope wins before priority');
$assert(medical_directory_resolve_ai_prompt($pdo, 'facility', 'Nha khoa tổng quát')['variant_id'] === $facilityBroad,
    'facility phrase matches a broader category');
$assert(medical_directory_resolve_ai_prompt($pdo, 'facility', 'Da liễu')['variant_id'] === $facilityGlobal,
    'categoryless facility variant catches otherwise unmatched categories');

$doctorGlobal = $add('doctor', 'Doctor shared');
$doctorSpecialty = $add('doctor', 'Eye specialist', ['Nhãn khoa']);
$add('doctor', 'Inactive eyes', ['Nhãn khoa'], 0, 0);
$doctor = medical_directory_resolve_ai_prompt($pdo, 'doctor', 'Nhãn khoa - Phẫu thuật Phaco');
$assert($doctor['variant_id'] === $doctorSpecialty && $doctor['source'] === 'category'
    && $doctor['prompt_key_used'] === 'doctor-variant-' . $doctorSpecialty,
    'doctor specialty chooses active scope variant with stable metadata');
$assert(medical_directory_resolve_ai_prompt($pdo, 'doctor', 'Tim mạch')['variant_id'] === $doctorGlobal,
    'doctor unmatched specialty selects categoryless variant');

$handbookGlobal = $add('handbook', 'Handbook shared', [], 1);
$handbookVi = $add('handbook', 'Vietnamese handbook', ['vi'], 1);
$handbookTopic = $add('handbook', 'Orthodontics', ['niềng răng'], 200);
$assert(medical_directory_resolve_ai_prompt($pdo, 'handbook', 'Giá niềng răng Đà Nẵng', 'vi')['variant_id'] === $handbookTopic,
    'handbook topic phrase takes precedence over locale and global priority');
$assert(medical_directory_resolve_ai_prompt($pdo, 'handbook', 'Cách chăm sóc tóc', 'vi')['variant_id'] === $handbookVi,
    'handbook language selects matching locale variant');
$assert(medical_directory_resolve_ai_prompt($pdo, 'handbook', 'Haircare', 'en')['variant_id'] === $handbookGlobal,
    'handbook categoryless variant catches missing locale');
$assert(medical_directory_resolve_ai_prompt($pdo, 'review', 'Nha khoa')['source'] === 'default',
    'unrelated prompt types continue to use base prompt');

$pdo->exec('UPDATE medical_ai_prompt_variants SET is_active=0');
$assert(medical_directory_resolve_ai_prompt($pdo, 'facility', 'Nha khoa')['template'] === 'Base facility',
    'disabled variants fall back to saved base prompt');
$legacy = new PDO('sqlite::memory:');
$legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$legacy->exec("CREATE TABLE medical_ai_prompts (prompt_key TEXT PRIMARY KEY, label TEXT, template TEXT)");
$legacy->exec("INSERT INTO medical_ai_prompts VALUES ('doctor','Doctor base','Legacy base')");
$assert(medical_directory_resolve_ai_prompt($legacy, 'doctor', 'Mắt')['template'] === 'Legacy base',
    'deployment without variant tables retains base prompt');

echo "Medical AI prompt variants: {$checks} checks passed.\n";
