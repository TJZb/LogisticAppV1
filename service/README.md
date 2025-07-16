# Export Services Directory

โฟลเดอร์นี้เก็บไฟล์ service สำหรับการ export รายงานต่างๆ ในระบบ

## โครงสร้างไฟล์

### ไฟล์หลัก
- `connect.php` - การเชื่อมต่อฐานข้อมูล
- `base_export_service.php` - คลาสพื้นฐานสำหรับการ export (ใช้ร่วมกัน)

### Export Services
- `fuel_export_service.php` - ✅ คลาสสำหรับ export รายงานการเติมน้ำมัน (เสร็จแล้ว)
- `fuel_export_api.php` - ✅ API endpoint สำหรับรายงานน้ำมัน (เสร็จแล้ว)
- `vehicle_export_service.php` - 🚧 คลาสสำหรับ export รายงานรถ (โครงสร้างพร้อม)
- `employee_export_service.php` - 🚧 คลาสสำหรับ export รายงานพนักงาน (โครงสร้างพร้อม)

## โครงสร้างคลาส

### BaseExportService (คลาสพื้นฐาน)
```php
abstract class BaseExportService {
    public static function exportToExcel($data, $headers, $title, $subtitle, $filename, $filterText)
    public static function exportToODS($data, $headers, $title, $subtitle, $filename, $filterText)
    public static function exportToCSV($data, $headers, $title, $subtitle, $filename, $filterText)
    public static function exportToPDF($data, $headers, $title, $subtitle, $filename, $filterText)
    public static function generateFilename($prefix, $suffix)
}
```

### FuelExportService (รายงานการเติมน้ำมัน)
```php
class FuelExportService extends BaseExportService {
    public static function prepareFuelExportData($records)
    public static function generateFuelFilename($factoryCode)
    // + methods จาก BaseExportService
}
```

## การใช้งาน

### การ Export รายงานการเติมน้ำมัน

```php
// ใน PHP
require_once 'service/fuel_export_service.php';

$records = [/* ข้อมูลจากฐานข้อมูล */];
$data = FuelExportService::prepareFuelExportData($records);
$headers = ['วันที่', 'เวลา', 'ทะเบียนรถ', 'ลิตร'];

FuelExportService::exportToExcel($data, $headers, 'รายงานการเติมน้ำมัน', 'รายละเอียด', 'filename');
```

```javascript
// ใน JavaScript
function exportFuelData() {
    const params = new URLSearchParams();
    params.append('format', 'xlsx');
    params.append('factory', '1');
    params.append('start_date', '2025-01-01');
    params.append('end_date', '2025-12-31');
    
    const exportUrl = '../service/fuel_export_api.php?' + params.toString();
    window.open(exportUrl, '_blank');
}
```

### การสร้าง Export Service ใหม่

```php
// ตัวอย่างการสร้าง service ใหม่
require_once __DIR__ . '/base_export_service.php';

class CustomExportService extends BaseExportService {
    
    public static function prepareCustomExportData($records) {
        // เตรียมข้อมูลตามความต้องการ
        return $exportData;
    }
    
    // ใช้ methods จาก BaseExportService ได้ทันที
    // exportToExcel(), exportToCSV(), exportToPDF(), exportToODS()
}
```

## รูปแบบที่รองรับ

- **Excel (.xlsx)** - Microsoft Excel
- **LibreOffice (.ods)** - OpenDocument Spreadsheet  
- **CSV (.csv)** - Comma Separated Values
- **PDF (.pdf)** - Portable Document Format

## ข้อดีของโครงสร้างใหม่

1. **ไม่มีโค้ดซ้ำ**: logic การ export อยู่ใน BaseExportService
2. **ขยายได้ง่าย**: สร้าง service ใหม่แค่ extend BaseExportService
3. **บำรุงรักษาง่าย**: แก้ไข export logic ที่เดียวครอบคลุมทั้งระบบ
4. **โค้ดสะอาด**: แต่ละ service มีหน้าที่ชัดเจน

## แนวทางการพัฒนาต่อ

1. **รายงานรถ** (VehicleExportService)
   - รายงานสถานะรถ
   - รายงานการบำรุงรักษา
   - รายงานค่าใช้จ่าย

2. **รายงานพนักงาน** (EmployeeExportService)
   - รายงานการปฏิบัติงาน
   - รายงานการเติมน้ำมันของพนักงาน

3. **รายงานสรุป**
   - รายงานรายเดือน
   - รายงานรายปี
   - รายงานเปรียบเทียบ

## หมายเหตุ

- ทุก service ต้อง extend จาก `BaseExportService`
- ไฟล์ API endpoint ต้องมีการ validate input และ error handling
- ควรใช้ชื่อไฟล์ที่สื่อความหมาย เช่น `{module}_export_service.php`
