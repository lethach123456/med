<?php
declare(strict_types=1);
require_once __DIR__ . '/_doctor.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, OPTIONS'); json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ GET.'], 405);
}
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) json_response(['ok' => false, 'message' => 'Cần id nguyên dương.'], 422);
$stmt = $pdo->prepare('SELECT * FROM medical_doctors WHERE id=:id'); $stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) json_response(['ok' => false, 'message' => 'Không tìm thấy bác sĩ.'], 404);
$locations = $pdo->prepare('SELECT * FROM medical_doctor_facilities WHERE doctor_id=:id ORDER BY display_order');
$locations->execute([':id' => $id]);
json_response(['ok' => true, 'type' => 'doctor', 'contract_version' => 1,
    'item' => medical_doctor_api_source($row), 'facility_links' => $locations->fetchAll(PDO::FETCH_ASSOC)]);
