<?php
require_once __DIR__ . '/../service/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$vehicle_id = $_POST['vehicle_id'] ?? null;
$fuel_record_id = $_POST['fuel_record_id'] ?? null;
$fuel_date = $_POST['fuel_date'] ?? null;

if (!$vehicle_id || !$fuel_record_id || !$fuel_date) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

try {
    $conn = connect_db();
    
    // ดึงเลขไมล์ล่าสุดก่อนวันที่ที่เลือก (ย้อนหลัง)
    $stmt_before = $conn->prepare("SELECT TOP 1 mileage_at_fuel FROM FuelRecords WHERE vehicle_id = ? AND fuel_record_id <> ? AND mileage_at_fuel IS NOT NULL AND fuel_date < ? ORDER BY fuel_date DESC");
    $stmt_before->execute([$vehicle_id, $fuel_record_id, $fuel_date]);
    $min_mileage = $stmt_before->fetchColumn();
    
    // ดึงเลขไมล์แรกหลังวันที่ที่เลือก (ถัดไป)
    $stmt_after = $conn->prepare("SELECT TOP 1 mileage_at_fuel FROM FuelRecords WHERE vehicle_id = ? AND fuel_record_id <> ? AND mileage_at_fuel IS NOT NULL AND fuel_date > ? ORDER BY fuel_date ASC");
    $stmt_after->execute([$vehicle_id, $fuel_record_id, $fuel_date]);
    $max_mileage = $stmt_after->fetchColumn();
    
    // ถ้าไม่มีเลขไมล์ก่อนหน้า ให้ใช้ 0 เป็นค่าเริ่มต้น
    if ($min_mileage === false) {
        $min_mileage = 0;
    }
    
    echo json_encode([
        'success' => true,
        'min_mileage' => $min_mileage,
        'max_mileage' => $max_mileage !== false ? $max_mileage : null
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
