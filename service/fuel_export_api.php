<?php
/**
 * Fuel Export API Endpoint - จุดเชื่อมต่อสำหรับการ export รายงานการเติมน้ำมัน
 * 
 * รับพารามิเตอร์:
 * - format: xlsx, ods, csv, pdf
 * - factory: factory_id (optional)
 * - start_date: วันที่เริ่มต้น (optional)
 * - end_date: วันที่สิ้นสุด (optional)
 */

require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../service/fuel_export_service.php';
require_once __DIR__ . '/../includes/auth.php';

// ตรวจสอบสิทธิ์การเข้าถึง
auth(['admin', 'manager']);

$conn = connect_db();

// รับพารามิเตอร์
$format = $_GET['format'] ?? 'xlsx';
$factory_filter = $_GET['factory'] ?? null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// ตรวจสอบรูปแบบที่รองรับ
$allowedFormats = ['xlsx', 'ods', 'csv', 'pdf'];
if (!in_array($format, $allowedFormats)) {
    http_response_code(400);
    echo json_encode(['error' => 'รูปแบบไฟล์ไม่ถูกต้อง']);
    exit;
}

try {
    // ดึงข้อมูลจากฐานข้อมูล (ใช้ function เดียวกับใน fuel_history.php)
    function getFuelRecordsForExport($conn, $factory_filter = null, $start_date = null, $end_date = null) {
        // ตรวจสอบว่าตาราง vehicles_factory มีอยู่หรือไม่
        $factoryTableExists = false;
        $factoryColumnExists = false;
        
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'vehicles_factory'");
            $factoryTableExists = $stmt->fetchColumn() > 0;
            
            if ($factoryTableExists) {
                $stmt = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'factory_id'");
                $factoryColumnExists = $stmt->fetchColumn() > 0;
            }
        } catch (Exception $e) {
            $factoryTableExists = false;
            $factoryColumnExists = false;
        }
        
        if ($factoryTableExists && $factoryColumnExists) {
            $baseSelect = "SELECT f.*, v.license_plate,
                vf.factory_name, vf.factory_code
                FROM fuel_records f
                JOIN vehicles v ON f.vehicle_id = v.vehicle_id
                LEFT JOIN vehicles_factory vf ON v.factory_id = vf.factory_id
                WHERE f.status IN ('pending', 'approved')";
            
            $params = [];
            
            // เพิ่มการกรองสังกัด
            if ($factory_filter) {
                $baseSelect .= " AND v.factory_id = ?";
                $params[] = intval($factory_filter);
            }
            
            // เพิ่มการกรองวันที่
            if ($start_date) {
                $baseSelect .= " AND f.fuel_date >= ?";
                $params[] = $start_date . ' 00:00:00';
            }
            
            if ($end_date) {
                $baseSelect .= " AND f.fuel_date <= ?";
                $params[] = $end_date . ' 23:59:59';
            }
        } else {
            // ใช้ query แบบเก่าถ้าไม่มีตาราง vehicles_factory
            $baseSelect = "SELECT f.*, v.license_plate,
                NULL as factory_name, NULL as factory_code
                FROM fuel_records f
                JOIN vehicles v ON f.vehicle_id = v.vehicle_id
                WHERE f.status IN ('pending', 'approved')";
            
            $params = [];
            
            // เพิ่มการกรองวันที่
            if ($start_date) {
                $baseSelect .= " AND f.fuel_date >= ?";
                $params[] = $start_date . ' 00:00:00';
            }
            
            if ($end_date) {
                $baseSelect .= " AND f.fuel_date <= ?";
                $params[] = $end_date . ' 23:59:59';
            }
        }
        
        $sql = $baseSelect . " ORDER BY f.fuel_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ดึงข้อมูล
    $records = getFuelRecordsForExport($conn, $factory_filter, $start_date, $end_date);
    
    // ดึงข้อมูลสังกัดสำหรับหัวข้อ
    $factoryInfo = null;
    if ($factory_filter) {
        try {
            $stmt = $conn->prepare("SELECT factory_name, factory_code FROM vehicles_factory WHERE factory_id = ?");
            $stmt->execute([$factory_filter]);
            $factoryInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // ไม่มีตาราง factory
        }
    }
    
    // เตรียมข้อมูลสำหรับ export
    $exportData = FuelExportService::prepareExportData($records);
    
    // สร้างหัวข้อ
    $factoryCode = $factoryInfo ? $factoryInfo['factory_code'] : null;
    $orgName = $factoryInfo ? $factoryInfo['factory_name'] : null;
    
    $title = $factoryCode ? "รายการเติมน้ำมัน{$factoryCode}" : 'รายการเติมน้ำมันรวมทุกสังกัด';
    $subtitle = $factoryCode ? "รายการจากสำเนาสลิปน้ำมัน - สังกัด {$orgName}" : 'รายการจากสำเนาสลิปน้ำมัน - รวมทุกสังกัด';
    
    // สร้างข้อความตัวกรอง
    $filterParts = [];
    if ($start_date || $end_date) {
        if ($start_date && $end_date) {
            $filterParts[] = "ช่วงวันที่: {$start_date} ถึง {$end_date}";
        } elseif ($start_date) {
            $filterParts[] = "ตั้งแต่วันที่: {$start_date}";
        } elseif ($end_date) {
            $filterParts[] = "จนถึงวันที่: {$end_date}";
        }
    }
    if ($factoryInfo) {
        $filterParts[] = "สังกัด: {$orgName}";
    }
    $filterText = !empty($filterParts) ? 'เงื่อนไขการกรอง: ' . implode(', ', $filterParts) : '';
    
    // หัวตาราง
    $headers = [
        'วันที่', 'เวลา', 'ทะเบียนรถ', 'เลขไมล์', 'ชนิดน้ำมัน', 'ราคาต่อลิตร', 'ลิตร', 'ราคารวม',
        'ระยะที่วิ่ง(กม.)', 'เฉลี่ยบาทต่อกม.', 'เฉลี่ยกม.ต่อลิตร', 'เฉลี่ยลิตรต่อกม.'
    ];
    
    // สร้างชื่อไฟล์
    $filename = FuelExportService::generateFilename('fuel_report', $factoryCode);
    
    // ส่งออกตามรูปแบบที่ระบุ
    switch ($format) {
        case 'xlsx':
            FuelExportService::exportToExcel($exportData, $headers, $title, $subtitle, $filename, $filterText);
            break;
        case 'ods':
            FuelExportService::exportToODS($exportData, $headers, $title, $subtitle, $filename, $filterText);
            break;
        case 'csv':
            FuelExportService::exportToCSV($exportData, $headers, $title, $subtitle, $filename, $filterText);
            break;
        case 'pdf':
            FuelExportService::exportToPDF($exportData, $headers, $title, $subtitle, $filename, $filterText);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'เกิดข้อผิดพลาดในการส่งออกข้อมูล: ' . $e->getMessage()]);
}
?>
