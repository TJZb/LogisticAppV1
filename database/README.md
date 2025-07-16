# 🗄️ ฐานข้อมูลระบบ LogisticApp

## 📋 สารบัญ
- [การติดตั้ง## 📁 ไฟล์ SQL และการใช้งาน

### ไฟล์หลัก

#### 1. `complete_database_setup.sql` - การติดตั้งครบถ้วน
- **จุดประสงค์**: สร้างฐานข้อมูลใหม่ทั้งหมด รวมถึงฟีเจอร์สังกัดรถ
- **เนื้อหา**: 
  - สร้างฐานข้อมูล `logistic_app_db`
  - ตารางข้อมูลพื้นฐาน (fuel_types, vehicle_brands, vehicle_categories)
  - ตารางหลัก (vehicles, employees, users, fuel_records)
  - ตาราง vehicles_factory (สังกัดรถ) พร้อมคอลัมน์ครบถ้วน
  - Views สำหรับรายงาน
  - ข้อมูลเริ่มต้น (users, sample data, สังกัดรถ)
  - Indexes และ Constraints
- **การใช้งาน**: สำหรับติดตั้งระบบใหม่ หรืออัปเกรดระบบเดิม
- **ขนาด**: ~626 บรรทัด

#### 2. Documentation Files
- `README.md` - คู่มือนี้
- `README_trailer_mileage_update.md` - คู่มืออัปเดตระบบรถพ่วง

### วิธีเลือกไฟล์ที่เหมาะสม

| สถานการณ์ | ไฟล์ที่ใช้ | คำอธิบาย |
|-----------|-----------|----------|
| 🆕 **ติดตั้งใหม่** | `complete_database_setup.sql` | ระบบใหม่ทั้งหมด รวมฟีเจอร์สังกัดรถ |
| 🔄 **อัปเกรดระบบเดิม** | `complete_database_setup.sql` | ตรวจสอบการมีอยู่ก่อนสร้าง |
| 🚛 **อัปเดตรถพ่วง** | ดู `README_trailer_mileage_update.md` | แก้ไขระบบรถพ่วง |

**หมายเหตุ**: ไฟล์ `complete_database_setup.sql` สามารถใช้ได้ทั้งการติดตั้งใหม่และการอัปเกรด เพราะมีการตรวจสอบการมีอยู่ของตารางก่อนสร้าง

## 🗂️ โครงสร้างฐานข้อมูล

### ตารางหลัก (Core Tables)

| ตาราง | จุดประสงค์ | จำนวนคอลัมน์ | ความสัมพันธ์ |
|-------|------------|---------------|--------------|
| `vehicles` | ข้อมูลรถและยานพาหนะ | 15+ | → brands, categories, factory |
| `fuel_records` | บันทึกการเติมน้ำมัน | 12+ | → vehicles, employees |
| `employees` | ข้อมูลพนักงาน | 10+ | → users |
| `users` | ผู้ใช้งานระบบ | 8+ | → employees |
| `fuel_receipt_attachments` | ไฟล์หลักฐานการเติมน้ำมัน | 8+ | → fuel_records |

### ตารางอ้างอิง (Reference Tables)

| ตาราง | จุดประสงค์ | ตัวอย่างข้อมูล |
|-------|------------|----------------|
| **fuel_types** | ประเภทเชื้อเพลิง | ดีเซล, เบนซิน 95, แก๊ส NGV |
| **vehicle_brands** | ยี่ห้อรถ | โตโยต้า, อีซูซุ, ฮีโน่ |
| **vehicle_categories** | ประเภทรถ | รถบรรทุก 6 ล้อ, รถหัวลาก, รถพ่วง |
| **vehicles_factory** | สังกัดรถ | โรงงานกรุงเทพ, โรงงานระยอง |

### ไฟล์และสื่อ (Media Tables)

| ตาราง | วัตถุประสงค์ | ไฟล์ที่เก็บ |
|-------|-------------|-------------|
| **fuel_receipt_attachments** | ไฟล์แนบใบเสร็จ | รูปเกจ, ใบเสร็จ, รูปหลักฐาน |

## 🏭 ตารางสังกัดรถ (Vehicles Factory)

### โครงสร้าง vehicles_factory
```sql
CREATE TABLE vehicles_factory (
    factory_id INT IDENTITY(1,1) PRIMARY KEY,
    factory_name NVARCHAR(100) NOT NULL,           -- ชื่อสังกัด
    factory_code NVARCHAR(20) NOT NULL UNIQUE,     -- รหัสสังกัด
    factory_address NVARCHAR(500),                 -- ที่อยู่
    factory_phone NVARCHAR(20),                    -- เบอร์โทร
    factory_manager NVARCHAR(100),                 -- ผู้จัดการ
    is_active BIT NOT NULL DEFAULT 1,              -- สถานะใช้งาน
    created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2 NOT NULL DEFAULT GETDATE()
);
```

### ข้อมูลสังกัดเริ่มต้น
| factory_id | factory_name | factory_code | สถานะ |
|------------|-------------|--------------|--------|
| 1 | โรงงานกรุงเทพ | BKK01 | ✅ ใช้งาน |
| 2 | โรงงานระยอง | RYG01 | ✅ ใช้งาน |
| 3 | โรงงานชลบุรี | CBR01 | ✅ ใช้งาน |
| 4 | สำนักงานใหญ่ | HQ001 | ✅ ใช้งาน |

### การเชื่อมโยงกับตาราง vehicles
```sql
-- เพิ่มคอลัมน์ factory_id ในตาราง vehicles
ALTER TABLE vehicles ADD factory_id INT NULL;

-- เพิ่ม Foreign Key
ALTER TABLE vehicles 
ADD CONSTRAINT FK_vehicles_factory 
FOREIGN KEY (factory_id) REFERENCES vehicles_factory(factory_id);
```

### การใช้งานในระบบ
- **ระบบกรองรายงาน**: กรองข้อมูลตามสังกัด
- **การจัดการสิทธิ์**: แยกสิทธิ์ดูข้อมูลตามสังกัด
- **รายงาน Export**: แสดงชื่อสังกัดในหัวรายงาน
- **Dashboard**: แสดงสถิติแยกตามสังกัดดตั้งฐานข้อมูล)
- [โครงสร้างฐานข้อมูล](#โครงสร้างฐานข้อมูล)
- [ไฟล์ SQL และการใช้งาน](#ไฟล์-sql-และการใช้งาน)
- [ตารางสังกัดรถ (Vehicles Factory)](#ตารางสังกัดรถ-vehicles-factory)
- [Views และ Functions](#views-และ-functions)
- [ข้อมูลผู้ใช้เริ่มต้น](#ข้อมูลผู้ใช้เริ่มต้น)
- [การบำรุงรักษาและแก้ไขปัญหา](#การบำรุงรักษาและแก้ไขปัญหา)

## 🚀 วิธีการติดตั้งฐานข้อมูล

### ขั้นตอนการติดตั้งระบบใหม่ (Complete Setup)

#### 1. เตรียมสภาพแวดล้อม
```bash
# ตรวจสอบ SQL Server
sqlcmd -S localhost -U sa -P YourPassword123 -Q "SELECT @@VERSION"

# ตรวจสอบ PHP PDO SQL Server
php -m | grep pdo_sqlsrv
```

#### 2. สร้างฐานข้อมูลด้วย complete_database_setup.sql
```sql
-- Method 1: ใช้ sqlcmd
sqlcmd -S localhost -U sa -P YourPassword123 -i complete_database_setup.sql

-- Method 2: ใช้ SQL Server Management Studio
-- 1. เปิด SSMS เชื่อมต่อ SQL Server
-- 2. File > Open > File > เลือก complete_database_setup.sql
-- 3. กด Execute (F5)

-- Method 3: ใช้ Azure Data Studio
-- 1. เปิด Azure Data Studio
-- 2. File > Open File > เลือก complete_database_setup.sql
-- 3. กด Run
```

#### 3. ตรวจสอบการติดตั้ง
```sql
-- เชิ ้อมต่อฐานข้อมูล
USE logistic_app_db;

-- ตรวจสอบตารางทั้งหมด
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE';

-- ตรวจสอบข้อมูลเริ่มต้น
SELECT COUNT(*) as user_count FROM users;
SELECT COUNT(*) as vehicle_count FROM vehicles;
SELECT COUNT(*) as factory_count FROM vehicles_factory;
```

### ขั้นตอนการอัปเกรด (Existing System)

#### 1. สำรองข้อมูลเดิม
```sql
-- สำรองฐานข้อมูล
BACKUP DATABASE logistic_app_db 
TO DISK = 'C:\backup\logistic_app_db_backup.bak'
WITH FORMAT, COMPRESSION;
```

#### 2. รันไฟล์อัปเกรด
```sql
-- ใช้ไฟล์ complete_database_setup.sql (มีการตรวจสอบการมีอยู่)
sqlcmd -S localhost -U sa -P YourPassword123 -d logistic_app_db -i complete_database_setup.sql
```

#### 3. ตรวจสอบการอัปเกรด
```sql
-- ตรวจสอบตาราง vehicles_factory
SELECT * FROM vehicles_factory;

-- ตรวจสอบคอลัมน์ factory_id ในตาราง vehicles
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'factory_id';
```

**หมายเหตุ**: ไฟล์ `complete_database_setup.sql` ใช้ `IF NOT EXISTS` จึงปลอดภัยสำหรับการอัปเกรดระบบเดิม

### ข้อกำหนดระบบ
- **SQL Server** 2017+ หรือ SQL Server Express
- **Collation:** `Thai_CI_AS`
- **Authentication:** SQL Server Authentication
- **Memory:** อย่างน้อย 2GB RAM
- **Storage:** อย่างน้อย 1GB ว่าง

### การเชื่อมต่อจาก PHP
```php
// ไฟล์: service/connect.php
$dsn = "sqlsrv:Server=192.168.50.123;Database=logistic_app_db;TrustServerCertificate=true";
$pdo = new PDO($dsn, "sa", "P@ssw0rd", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
]);
### การแก้ไขปัญหาทั่วไป

#### ปัญหา: ไม่สามารถเชื่อมต่อฐานข้อมูล
```bash
# ตรวจสอบ SQL Server Service
sudo systemctl status mssql-server

# เริ่ม SQL Server (ถ้าหยุดทำงาน)
sudo systemctl start mssql-server

# ตรวจสอบ Port
netstat -tuln | grep 1433
```

#### ปัญหา: PHP ไม่สามารถเชื่อมต่อ
```bash
# ติดตั้ง PHP SQL Server Driver
sudo apt-get install php-sqlsrv php-pdo-sqlsrv

# ตรวจสอบ extension ใน php.ini
grep -i sqlsrv /etc/php/*/apache2/php.ini
```

#### ปัญหา: Permission Denied
```sql
-- เพิ่มสิทธิ์ให้ user
USE logistic_app_db;
ALTER ROLE db_owner ADD MEMBER your_username;
```

#### ปัญหา: ตาราง vehicles_factory ไม่มี
```sql
-- รันไฟล์สำหรับเพิ่มตาราง (รันไฟล์เต็ม)
sqlcmd -S localhost -U sa -P password -d logistic_app_db -i complete_database_setup.sql

-- ตรวจสอบว่าตารางถูกสร้างแล้ว
SELECT * FROM vehicles_factory;
```

#### ปัญหา: ข้อมูลภาษาไทยแสดงผิด
```sql
-- ตรวจสอบ Collation ของฐานข้อมูล
SELECT DATABASEPROPERTYEX('logistic_app_db', 'Collation');

-- ควรเป็น Thai_CI_AS
```

### การทดสอบระบบ

#### 1. ทดสอบการเชื่อมต่อพื้นฐาน
```php
<?php
// test_connection.php
try {
    $pdo = new PDO("sqlsrv:server=localhost;Database=logistic_app_db", "sa", "YourPassword123");
    echo "✅ การเชื่อมต่อสำเร็จ!\n";
    
    // ทดสอบ query
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM vehicles");
    $result = $stmt->fetch();
    echo "🚗 จำนวนรถ: " . $result['total'] . "\n";
    
} catch(PDOException $e) {
    echo "❌ เชื่อมต่อไม่สำเร็จ: " . $e->getMessage() . "\n";
}
?>
```

#### 2. ทดสอบ Vehicles Factory
```sql
-- ทดสอบข้อมูลสังกัด
SELECT v.license_plate, vf.factory_name 
FROM vehicles v 
LEFT JOIN vehicles_factory vf ON v.factory_id = vf.factory_id;

-- ทดสอบสถิติตามสังกัด
SELECT 
    vf.factory_name,
    COUNT(v.vehicle_id) as vehicle_count
FROM vehicles_factory vf
LEFT JOIN vehicles v ON vf.factory_id = v.factory_id
GROUP BY vf.factory_id, vf.factory_name;
```

#### 3. ทดสอบการ Export
```sql
-- ตรวจสอบข้อมูลสำหรับ Export
SELECT 
    v.license_plate,
    vf.factory_name,
    fr.fuel_date,
    fr.volume_liters,
    fr.total_cost
FROM fuel_records fr
JOIN vehicles v ON fr.vehicle_id = v.vehicle_id
LEFT JOIN vehicles_factory vf ON v.factory_id = vf.factory_id
WHERE fr.fuel_date >= '2025-01-01'
ORDER BY fr.fuel_date DESC;
```

## 📊 ข้อมูลเริ่มต้นในระบบ

### ผู้ใช้เริ่มต้น
| Username | Password | บทบาท | สิทธิ์ | หมายเหตุ |
|----------|----------|--------|---------|----------|
| `admin` | `123456` | ผู้ดูแลระบบ | เต็ม | จัดการทุกอย่าง |
| `manager` | `123456` | ผู้จัดการ | อ่าน/อนุมัติ | ดูรายงาน อนุมัติ |
| `employee1` | `123456` | พนักงาน | บันทึก | เติมน้ำมัน |
| `employee2` | `123456` | พนักงาน | บันทึก | เติมน้ำมัน |
| `mechanic` | `123456` | ช่าง | บันทึก/แก้ไข | ซ่อมบำรุง |

⚠️ **คำเตือน**: เปลี่ยนรหัสผ่านทันทีหลังติดตั้ง!

### ประเภทเชื้อเพลิง
| ID | ชื่อ | หน่วย | ราคาโดยประมาณ |
|----|-----|-------|----------------|
| 1 | เบนซิน 91 | ลิตร | 35.00 บาท |
| 2 | เบนซิน 95 | ลิตร | 36.50 บาท |
| 3 | ดีเซล | ลิตร | 33.50 บาท |
| 4 | แก๊ส NGV | กิโลกรัม | 16.50 บาท |
| 5 | แก๊ส LPG | ลิตร | 18.00 บาท |

### ยี่ห้อรถ
| ID | ยี่ห้อ | ประเทศ | หมายเหตุ |
|----|-------|--------|----------|
| 1 | โตโยต้า | ญี่ปุ่น | รถยนต์ทั่วไป |
| 2 | อีซูซุ | ญี่ปุ่น | รถบรรทุก |
| 3 | ฮีโน่ | ญี่ปุ่น | รถบรรทุกหนัก |
| 4 | มิตซูบิชิ | ญี่ปุ่น | รถทุกประเภท |
| 5 | ฟอร์ด | อเมริกา | รถกระบะ |

### ประเภทรถ
| ID | ประเภท | คำอธิบาย | น้ำหนักบรรทุก |
|----|--------|----------|----------------|
| 1 | รถกระบะ | รถกระบะขนาดเล็ก | 500-1,000 กก. |
| 2 | รถบรรทุก 4 ล้อ | รถบรรทุกเล็ก | 1-3 ตัน |
| 3 | รถบรรทุก 6 ล้อ | รถบรรทุกกลาง | 3-8 ตัน |
| 4 | รถบรรทุก 10 ล้อ | รถบรรทุกหนัก | 8-15 ตัน |
| 5 | รถหัวลาก | รถลากพ่วง | 15+ ตัน |
| 6 | รถพ่วง | รถพ่วงบรรทุก | ไม่มีเครื่องยนต์ |

### สังกัดรถ (Vehicles Factory)
| ID | ชื่อสังกัด | รหัส | คำอธิบาย |
|----|-----------|------|----------|
| 1 | โรงงานกรุงเทพ | BKK01 | โรงงานกรุงเทพมหานคร |
| 2 | โรงงานระยอง | RYG01 | โรงงานจังหวัดระยอง |
| 3 | โรงงานชลบุรี | CBR01 | โรงงานจังหวัดชลบุรี |
| 4 | สำนักงานใหญ่ | HQ001 | สำนักงานใหญ่ กรุงเทพมหานคร |
| 5 | Golden World | GW | โรงงาน Golden World |
| 6 | Industrial | IND | โรงงานอุตสาหกรรม |
| 7 | Logistics | LOG | ศูนย์กระจายสินค้า |
| 8 | Transport | TRP | แผนกขนส่ง |
| 9 | Maintenance | MNT | แผนกซ่อมบำรุง |

### ตารางหลัก (Core Tables)

| ตาราง | จุดประสงค์ | จำนวนคอลัมน์ | ความสัมพันธ์ |
|-------|------------|---------------|--------------|
| `vehicles` | ข้อมูลรถและยานพาหนะ | 15+ | → brands, categories, factory |
| `fuel_records` | บันทึกการเติมน้ำมัน | 12+ | → vehicles, employees |
| `employees` | ข้อมูลพนักงาน | 10+ | → users |
| `users` | ผู้ใช้งานระบบ | 8+ | → employees |
| `fuel_receipt_attachments` | ไฟล์หลักฐานการเติมน้ำมัน | 8+ | → fuel_records |

### ตารางอ้างอิง (Reference Tables)

| ตาราง | จุดประสงค์ | ตัวอย่างข้อมูล |
|-------|------------|----------------|
| **fuel_types** | ประเภทเชื้อเพลิง | ดีเซล, เบนซิน 95, แก๊ส NGV |
| **vehicle_brands** | ยี่ห้อรถ | โตโยต้า, อีซูซุ, ฮีโน่ |
| **vehicle_categories** | ประเภทรถ | รถบรรทุก 6 ล้อ, รถหัวลาก, รถพ่วง |
| **vehicles_factory** | สังกัดรถ | โรงงานกรุงเทพ, โรงงานระยอง |

### ไฟล์และสื่อ (Media Tables)

| ตาราง | วัตถุประสงค์ | ไฟล์ที่เก็บ |
|-------|-------------|-------------|
| **fuel_receipt_attachments** | ไฟล์แนบใบเสร็จ | รูปเกจ, ใบเสร็จ, รูปหลักฐาน |

## ✅ การทดสอบระบบ

### การทดสอบพื้นฐาน
```sql
USE logistic_app_db;

-- ตรวจสอบจำนวนตารางทั้งหมด
SELECT COUNT(*) AS table_count FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_TYPE = 'BASE TABLE';

-- ตรวจสอบผู้ใช้ระบบ
SELECT username, role FROM users WHERE active = 1;

-- ตรวจสอบข้อมูลรถ
SELECT 
    v.license_plate, 
    vb.brand_name, 
    v.model_name,
    vf.factory_name
FROM vehicles v
LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
LEFT JOIN vehicles_factory vf ON v.factory_id = vf.factory_id
WHERE v.status = 'active';

-- ตรวจสอบข้อมูลสังกัด
SELECT factory_name, factory_code FROM vehicles_factory WHERE is_active = 1;
```

### การทดสอบ Relationships
```sql
-- ทดสอบ Foreign Keys
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME IS NOT NULL;
```

สำหรับข้อมูลเพิ่มเติม ดูที่ `../README.md`
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

## � การบำรุงรักษาและแก้ไขปัญหา

### การสำรองข้อมูล (Backup)
```sql
-- สำรองข้อมูลแบบเต็ม
BACKUP DATABASE logistic_app_db 
TO DISK = 'C:\backup\logistic_app_db_full.bak'
WITH FORMAT, COMPRESSION, CHECKSUM;

-- สำรองข้อมูลแบบ Differential (ส่วนที่เปลี่ยนแปลง)
BACKUP DATABASE logistic_app_db 
TO DISK = 'C:\backup\logistic_app_db_diff.bak'
WITH DIFFERENTIAL, COMPRESSION;
```

### การกู้คืนข้อมูล (Restore)
```sql
-- กู้คืนจาก backup
RESTORE DATABASE logistic_app_db 
FROM DISK = 'C:\backup\logistic_app_db_full.bak'
WITH REPLACE;
```

### การล้างข้อมูลเก่า
```sql
-- ลบบันทึกการเติมน้ำมันที่ปฏิเสธแล้วเก่ากว่า 6 เดือน
DELETE FROM fuel_records 
WHERE status = 'rejected' 
AND created_at < DATEADD(month, -6, GETDATE());

-- ลบไฟล์แนบเก่าที่ไม่ได้ใช้งาน
DELETE FROM fuel_receipt_attachments 
WHERE fuel_record_id NOT IN (SELECT fuel_record_id FROM fuel_records);
```

### การตรวจสอบประสิทธิภาพ
```sql
-- ดู Index ที่มีการใช้งาน
SELECT 
    OBJECT_NAME(object_id) AS table_name,
    name AS index_name,
    type_desc,
    is_unique
FROM sys.indexes 
WHERE object_id IN (
    OBJECT_ID('vehicles'),
    OBJECT_ID('fuel_records'),
    OBJECT_ID('employees')
);

-- ตรวจสอบขนาดตาราง
SELECT 
    TABLE_NAME,
    TABLE_ROWS as 'จำนวนแถว'
FROM INFORMATION_SCHEMA.TABLES t
LEFT JOIN INFORMATION_SCHEMA.PARTITIONS p ON t.TABLE_NAME = p.TABLE_NAME
WHERE TABLE_TYPE = 'BASE TABLE';
```

### การตรวจสอบความสมบูรณ์ของข้อมูล
```sql
-- ตรวจสอบ Foreign Key ที่อาจเสียหาย
SELECT 
    'vehicles without factory' as issue_type,
    COUNT(*) as count
FROM vehicles v 
LEFT JOIN vehicles_factory vf ON v.factory_id = vf.factory_id
WHERE v.factory_id IS NOT NULL AND vf.factory_id IS NULL

UNION ALL

SELECT 
    'fuel_records without vehicle' as issue_type,
    COUNT(*) as count
FROM fuel_records fr
LEFT JOIN vehicles v ON fr.vehicle_id = v.vehicle_id
WHERE v.vehicle_id IS NULL;
```

## � Changelog และการอัปเดต

### Version 2.1.0 (2025-01-12)
**🆕 ฟีเจอร์ใหม่**
- เพิ่มตาราง `vehicles_factory` สำหรับจัดการสังกัดรถ
- เพิ่มคอลัมน์ `factory_id` ในตาราง `vehicles`
- ระบบกรองข้อมูลตามสังกัดในหน้ารายงาน
- Export รายงานแสดงชื่อสังกัดในหัวกระดาษ

**🔧 การปรับปรุง**
- ปรับปรุงประสิทธิภาพการเชื่อมต่อฐานข้อมูล
- เพิ่ม Indexes สำหรับ factory_id
- ปรับปรุงความปลอดภัยของ SQL Queries
- **รวมไฟล์ SQL**: รวม `add_vehicles_factory.sql` เข้ากับ `complete_database_setup.sql`

**📁 การจัดการไฟล์**
- ✅ รวม `add_vehicles_factory.sql` → `complete_database_setup.sql`
- ✅ ลบไฟล์ที่ซ้ำซ้อน เหลือไฟล์เดียวครบถ้วน
- ✅ ไฟล์เดียวใช้ได้ทั้งติดตั้งใหม่และอัปเกรด
- ✅ เพิ่มข้อมูลสังกัดรถครบถ้วน (9 สังกัด)

### Version 2.0.0 (2024-12-01)
**🆕 ฟีเจอร์ใหม่**
- ระบบจัดการไฟล์แนบใบเสร็จ
- Views สำหรับรายงานข้อมูล
- ระบบ Authentication แบบ Role-based

### Version 1.0.0 (2024-10-01)
**🎉 เวอร์ชันแรก**
- ระบบจัดการข้อมูลรถ
- ระบบบันทึกการเติมน้ำมัน
- ระบบผู้ใช้งานพื้นฐาน

## 🚀 แผนการพัฒนาต่อไป

### Version 2.2.0 (กำลังพัฒนา)
- [ ] ระบบแจ้งเตือนการบำรุงรักษา
- [ ] Mobile API สำหรับแอปมือถือ
- [ ] Dashboard แบบ Real-time
- [ ] การ Export ข้อมูลเป็น PDF

### Version 2.3.0 (วางแผน)
- [ ] ระบบ GPS Tracking
- [ ] การวิเคราะห์ต้นทุนเชื้อเพลิง
- [ ] ระบบอนุมัติแบบหลายขั้นตอน
- [ ] Integration กับระบบบัญชี

## 📞 การติดต่อและสนับสนุน

### การรายงานปัญหา
1. ตรวจสอบ [Troubleshooting](#การแก้ไขปัญหาทั่วไป) ก่อน
2. เก็บ Log ไฟล์ที่เกี่ยวข้อง
3. ระบุขั้นตอนการทำงานที่เกิดปัญหา
4. แนบหน้าจอที่แสดงข้อผิดพลาด

### ไฟล์ Log ที่สำคัญ
- **SQL Server Error Log**: `C:\Program Files\Microsoft SQL Server\MSSQL15.MSSQLSERVER\MSSQL\Log\ERRORLOG`
- **PHP Error Log**: `/var/log/apache2/error.log`
- **Application Log**: `logs/app.log` (ถ้ามี)

### การขอฟีเจอร์ใหม่
1. อธิบายฟีเจอร์ที่ต้องการ
2. ระบุเหตุผลและผลประโยชน์
3. ให้ตัวอย่างการใช้งาน

---

📝 **เอกสารนี้อัปเดตล่าสุด**: 2025-01-12  
🔄 **เวอร์ชันฐานข้อมูล**: 2.1.0  
👨‍💻 **ผู้จัดทำ**: LogisticApp Development Team

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
