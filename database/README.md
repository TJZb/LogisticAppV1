# 📊 ฐานข้อมูลระบบจัดการขนส่ง (Logistic App)

## 🎯 ภาพรวมระบบ

ระบบฐานข้อมูลนี้ออกแบบมาเพื่อจัดการข้อมูลการขนส่งและยานพาหนะของบริษัท โดยใช้ **Microsoft SQL Server** เป็นฐานข้อมูลหลัก ครอบคลุมการจัดการ:

- ✅ ข้อมูลรถและยานพาหนะ
- ✅ การบันทึกการเติมน้ำมัน
- ✅ การจัดการผู้ใช้และพนักงาน
- ✅ รายงานและสถิติต่าง ๆ

## 🚀 วิธีการติดตั้ง

### ขั้นตอนที่ 1: เตรียมระบบ
```bash
# ตรวจสอบว่ามี SQL Server พร้อมใช้งาน
# ตรวจสอบสิทธิ์การเข้าถึงฐานข้อมูล
```

### ขั้นตอนที่ 2: รันไฟล์ SQL
```sql
-- เปิด SQL Server Management Studio หรือ Azure Data Studio
-- เชื่อมต่อกับเซิร์ฟเวอร์: 192.168.50.123
-- รันไฟล์: complete_database_setup.sql
```

### ขั้นตอนที่ 3: ตรวจสอบการติดตั้ง
```sql
-- ตรวจสอบว่าฐานข้อมูลถูกสร้างแล้ว
USE logistic_app_db;
SELECT COUNT(*) AS table_count FROM INFORMATION_SCHEMA.TABLES;

-- ตรวจสอบข้อมูลผู้ใช้
SELECT username, role FROM users WHERE active = 1;
```

## 🗄️ โครงสร้างฐานข้อมูล

### ตารางหลัก (Core Tables)

| ตาราง | วัตถุประสงค์ | ข้อมูลสำคัญ |
|-------|-------------|-------------|
| **vehicles** | ข้อมูลรถและยานพาหนะ | ทะเบียน, ยี่ห้อ, รุ่น, สถานะ |
| **fuel_records** | บันทึกการเติมน้ำมัน | ปริมาณ, ราคา, วันที่, สถานี |
| **employees** | ข้อมูลพนักงาน | ชื่อ, แผนก, ตำแหน่ง, ใบขับขี่ |
| **users** | ผู้ใช้ระบบ | Username, Password, สิทธิ์ |

### ตารางอ้างอิง (Reference Tables)

| ตาราง | วัตถุประสงค์ | ตัวอย่างข้อมูล |
|-------|-------------|----------------|
| **fuel_types** | ประเภทเชื้อเพลิง | ดีเซล, เบนซิน 95, แก๊ส NGV |
| **vehicle_brands** | ยี่ห้อรถ | โตโยต้า, อีซูซุ, ฮีโน่ |
| **vehicle_categories** | ประเภทรถ | รถบรรทุก 6 ล้อ, รถหัวลาก |
| **departments** | แผนกงาน | ฝ่ายขนส่ง, ฝ่ายซ่อมบำรุง |

### ตารางไฟล์ (File Tables)

| ตาราง | วัตถุประสงค์ | ไฟล์ที่เก็บ |
|-------|-------------|-------------|
| **fuel_receipt_attachments** | ไฟล์แนบใบเสร็จ | รูปเกจ, ใบเสร็จ |

## 📈 Views สำหรับรายงาน

### 1. VehicleDetailsView
```sql
-- รายละเอียดรถทั้งหมดพร้อมข้อมูลครบถ้วน
SELECT * FROM VehicleDetailsView WHERE status = 'active';
```

### 2. FuelRecordsView
```sql
-- บันทึกการเติมน้ำมันพร้อมรายละเอียด
SELECT * FROM FuelRecordsView WHERE fuel_date >= '2025-01-01';
```

### 3. MonthlyFuelSummary
```sql
-- สรุปการเติมน้ำมันรายเดือน
SELECT * FROM MonthlyFuelSummary WHERE fuel_year = 2025;
```

### 4. VehicleUsageSummary
```sql
-- สรุปการใช้งานรถแต่ละคัน
SELECT * FROM VehicleUsageSummary ORDER BY total_fuel_cost DESC;
```

### 5. UserDetailsView
```sql
-- ข้อมูลผู้ใช้ระบบพร้อมรายละเอียด
SELECT * FROM UserDetailsView WHERE role = 'employee';
```

## 👥 ข้อมูลผู้ใช้เริ่มต้น

| Username | Password | บทบาท | คำอธิบาย |
|----------|----------|--------|----------|
| `admin` | `123456` | ผู้ดูแลระบบ | สิทธิ์เต็ม จัดการผู้ใช้ |
| `manager` | `123456` | ผู้จัดการ | อนุมัติ ดูรายงาน |
| `employee1` | `123456` | พนักงาน | บันทึกการเติมน้ำมัน |
| `employee2` | `123456` | พนักงาน | บันทึกการเติมน้ำมัน |
| `mechanic` | `123456` | พนักงาน | ช่างซ่อมบำรุง |

## 🔧 การใช้งานพื้นฐาน

### เพิ่มรถใหม่
```sql
INSERT INTO vehicles (license_plate, province, brand_id, model_name, year_manufactured, fuel_type_id, category_id)
VALUES (N'กก-1111', N'กรุงเทพมหานคร', 2, N'D-MAX', 2023, 3, 2);
```

### บันทึกการเติมน้ำมัน
```sql
INSERT INTO fuel_records (vehicle_id, fuel_type, volume_liters, price_per_liter, total_cost, recorded_by_employee_id)
VALUES (1, N'ดีเซล', 50.00, 33.50, 1675.00, 3);
```

### ดูรายงานการเติมน้ำมันรายเดือน
```sql
SELECT 
    month_name,
    total_records,
    total_volume,
    total_cost,
    avg_price_per_liter
FROM MonthlyFuelSummary 
WHERE fuel_year = 2025 
ORDER BY fuel_month;
```

## 🛡️ ความปลอดภัย

### การเข้ารหัสรหัสผ่าน
- ใช้ PHP `password_hash()` และ `password_verify()`
- Hash Algorithm: `PASSWORD_DEFAULT` (bcrypt)

### การจัดการสิทธิ์
- **admin**: จัดการผู้ใช้, ดูรายงานทั้งหมด
- **manager**: อนุมัติการเติมน้ำมัน, ดูรายงาน
- **employee**: บันทึกการเติมน้ำมัน, ดูข้อมูลของตัวเอง

### การป้องกัน SQL Injection
- ใช้ PDO Prepared Statements
- Parameterized Queries
- Input Validation

## 📱 การเชื่อมต่อกับ PHP

### ตัวอย่างการเชื่อมต่อ
```php
// ไฟล์: service/connect.php
function connect_db() {
    $dsn = "sqlsrv:Server=192.168.50.123;Database=logistic_app_db;TrustServerCertificate=true";
    $pdo = new PDO($dsn, "sa", "P@ssw0rd", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
    ]);
    return $pdo;
}
```

### ตัวอย่างการใช้งาน
```php
// ดึงข้อมูลรถ
$conn = connect_db();
$stmt = $conn->prepare("SELECT * FROM VehicleDetailsView WHERE license_plate = ?");
$stmt->execute(['กข-1234']);
$vehicle = $stmt->fetch();
```

## 🔄 การบำรุงรักษา

### สำรองข้อมูล
```sql
-- สร้าง backup ฐานข้อมูล
BACKUP DATABASE logistic_app_db 
TO DISK = 'C:\\Backup\\logistic_app_db.bak';
```

### ล้างข้อมูลเก่า
```sql
-- ลบบันทึกการเติมน้ำมันที่ปฏิเสธแล้วเก่ากว่า 6 เดือน
DELETE FROM fuel_records 
WHERE status = 'rejected' 
AND created_at < DATEADD(month, -6, GETDATE());
```

### ตรวจสอบประสิทธิภาพ
```sql
-- ดู Index ที่มีการใช้งาน
SELECT 
    OBJECT_NAME(object_id) AS table_name,
    name AS index_name,
    type_desc
FROM sys.indexes 
WHERE object_id = OBJECT_ID('fuel_records');
```

## 🚨 การแก้ไขปัญหา

### ปัญหาการเชื่อมต่อ
1. ตรวจสอบ SQL Server Service
2. ตรวจสอบ Firewall Settings
3. ตรวจสอบ Connection String

### ปัญหา Encoding
```sql
-- ตรวจสอบ Collation
SELECT DATABASEPROPERTYEX('logistic_app_db', 'Collation');

-- ควรได้ผลลัพธ์: Thai_CI_AS
```

### ปัญหาประสิทธิภาพ
```sql
-- ดูการใช้งาน Index
SELECT 
    OBJECT_NAME(s.object_id) AS table_name,
    i.name AS index_name,
    s.user_seeks,
    s.user_scans,
    s.user_lookups
FROM sys.dm_db_index_usage_stats s
INNER JOIN sys.indexes i ON s.object_id = i.object_id AND s.index_id = i.index_id;
```

## 📞 การติดต่อและสนับสนุน

- **เอกสารเพิ่มเติม**: ดูไฟล์ `PROJECT_STRUCTURE.md`
- **การใช้งาน PHP**: ดูโฟล์เดอร์ `includes/` และ `service/`
- **การจัดการไฟล์**: ดูไฟล์ `includes/file_upload.php`

---

**หมายเหตุ**: ไฟล์นี้ควรอัปเดตเมื่อมีการเปลี่ยนแปลงโครงสร้างฐานข้อมูล
