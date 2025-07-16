<?php
/**
 * Test Export Service - ไฟล์ทดสอบระบบ export
 * 
 * วิธีใช้: เรียกไฟล์นี้ใน browser เพื่อทดสอบการ export
 * เช่น: http://localhost/service/test_export.php?format=csv
 */

// ป้องกันการเรียกใช้ใน production
if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
    die('ไฟล์ทดสอบนี้ใช้ได้เฉพาะใน localhost เท่านั้น');
}

require_once 'base_export_service.php';
require_once 'fuel_export_service.php';

// ข้อมูลทดสอบ
$testData = [
    ['2025-01-15', '08:30', 'กข-1234', '125,000', 'ดีเซล', '32.50', '45.50', '1,478.75', '150', '9.86', '3.30', '0.30'],
    ['2025-01-16', '09:15', 'กค-5678', '98,500', 'เบนซิน', '35.20', '40.00', '1,408.00', '120', '11.73', '3.00', '0.33'],
    ['2025-01-17', '10:45', 'กง-9012', '87,200', 'ดีเซล', '32.80', '38.75', '1,270.60', '95', '13.38', '2.45', '0.41']
];

$headers = [
    'วันที่', 'เวลา', 'ทะเบียนรถ', 'เลขไมล์', 'ชนิดน้ำมัน', 'ราคาต่อลิตร', 'ลิตร', 'ราคารวม',
    'ระยะที่วิ่ง(กม.)', 'เฉลี่ยบาทต่อกม.', 'เฉลี่ยกม.ต่อลิตร', 'เฉลี่ยลิตรต่อกม.'
];

$title = 'รายการเติมน้ำมัน (ทดสอบ)';
$subtitle = 'รายการจากสำเนาสลิปน้ำมัน - สังกัด ทดสอบ';
$filterText = 'เงื่อนไขการกรอง: ช่วงวันที่ 2025-01-15 ถึง 2025-01-17';
$filename = 'test_fuel_report_' . date('Y-m-d_H-i-s');

// รับพารามิเตอร์ format
$format = $_GET['format'] ?? 'xlsx';

try {
    switch ($format) {
        case 'xlsx':
            FuelExportService::exportToExcel($testData, $headers, $title, $subtitle, $filename, $filterText);
            break;
        case 'ods':
            FuelExportService::exportToODS($testData, $headers, $title, $subtitle, $filename, $filterText);
            break;
        case 'csv':
            FuelExportService::exportToCSV($testData, $headers, $title, $subtitle, $filename, $filterText);
            break;
        case 'pdf':
            FuelExportService::exportToPDF($testData, $headers, $title, $subtitle, $filename, $filterText);
            break;
        default:
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
            echo "<h2>ทดสอบ Export Service</h2>";
            echo "<p>เลือกรูปแบบที่ต้องการทดสอบ:</p>";
            echo "<ul>";
            echo "<li><a href='?format=xlsx'>Excel (.xlsx)</a></li>";
            echo "<li><a href='?format=ods'>LibreOffice (.ods)</a></li>";
            echo "<li><a href='?format=csv'>CSV (.csv)</a></li>";
            echo "<li><a href='?format=pdf'>PDF (.pdf)</a></li>";
            echo "</ul>";
            echo "<p><strong>หมายเหตุ:</strong> ไฟล์นี้ใช้สำหรับทดสอบเท่านั้น กรุณาลบออกจาก production</p>";
            echo "</body></html>";
    }
} catch (Exception $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>
