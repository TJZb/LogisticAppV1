# 🚛 ระบบจัดการขนส่ง (LogisticAppV1)

ระบบจัดการขนส่งและการเติมน้ำมันสำหรับบริษัทขนส่ง พัฒนาด้วย PHP และ SQL Server

## 🎯 ฟีเจอร์หลัก

### 👔 สำหรับผู้จัดการ (Manager)
- 📊 จัดการข้อมูลรถและยานพาหนะ (เพิ่ม/แก้ไข/ลบ)
- 📈 รายงานการเติมน้ำมันและสถิติ
- ✅ อนุมัติ/ปฏิเสธรายการเติมน้ำมัน
- 👥 จัดการข้อมูลพนักงาน
- 📋 ติดตามเลขไมล์ของรถ

### 👷 สำหรับพนักงาน (Employee)
- 🚗 เลือกรถและบันทึกการเติมน้ำมัน
- 📷 อัปโหลดรูปภาพหลักฐาน (เกจ, ใบเสร็จ)
- 📱 ระบบลดขนาดภาพอัตโนมัติ
- 📜 ดูประวัติการเติมน้ำมัน
- ⚙️ จัดการโปรไฟล์ส่วนตัว

### 🛡️ สำหรับผู้ดูแลระบบ (Admin)
- 👤 จัดการผู้ใช้งานทั้งหมด
- 🔧 ตั้งค่าระบบ
- 📊 ดูสถิติการใช้งานระบบ

## 🚀 ฟีเจอร์ใหม่ล่าสุด

### 📸 ระบบอัปโหลดไฟล์ขั้นสูง
- ✨ **ลดขนาดรูปภาพอัตโนมัติ** ก่อนส่งข้อมูล (Client-side)
- 🎯 ลดขนาดไฟล์ได้ **60-80%** โดยไม่สูญเสียคุณภาพมาก
- ⚡ ประหยัดเวลาและ Internet ในการอัปโหลด
- 📊 แสดงความคืบหน้าการประมวลผลแบบเรียลไทม์
- 📏 รองรับไฟล์ขนาดใหญ่ถึง **10MB** ต่อไฟล์
- 🖼️ รองรับ: JPG, PNG, GIF, WebP, PDF

### 🛣️ ระบบจัดการเลขไมล์
- 📊 แสดงเลขไมล์ปัจจุบันของรถ
- 🔄 อัปเดตเลขไมล์อัตโนมัติจากประวัติการเติมน้ำมัน
- 🎯 ติดตามการใช้งานรถแต่ละคัน

## 📁 โครงสร้างโปรเจค

```
LogisticAppV1/
├── 🔧 config/                  # การตั้งค่าระบบ
├── 📚 includes/                # ไฟล์ที่ใช้งานร่วมกัน
├── 🌐 service/                 # บริการและการเชื่อมต่อฐานข้อมูล
├── 🗄️ database/               # ไฟล์ฐานข้อมูล
├── 👔 ManagerPage/             # หน้าสำหรับผู้จัดการ
├── 👷 EmployeePage/            # หน้าสำหรับพนักงาน
├── 🛡️ AdminPage/              # หน้าสำหรับผู้ดูแลระบบ
├── 🎨 asset/css/               # ไฟล์ CSS และทรัพยากร
├── 📤 uploads/                 # ไฟล์ที่อัปโหลด
└── 🏠 index.php               # หน้าแรกของระบบ
```

## ⚙️ ข้อกำหนดระบบ

### 🖥️ Server Requirements
- **PHP** 7.4+ (แนะนำ 8.0+)
- **SQL Server** 2017+ หรือ SQL Server Express
- **PHP SQL Server Driver** (sqlsrv, pdo_sqlsrv)
- **Web Server** Apache/Nginx
- **Memory** อย่างน้อย 256MB RAM

### 🗄️ ฐานข้อมูล
- **Database:** `logistic_app_db`
- **Collation:** `Thai_CI_AS`
- **Authentication:** SQL Server Authentication

## 🛠️ การติดตั้ง

### 1️⃣ ตั้งค่าฐานข้อมูล
```sql
-- รันไฟล์ SQL ใน SQL Server Management Studio
sqlcmd -S [SERVER] -U [USER] -P [PASSWORD] -i database/complete_database_setup.sql
```

### 2️⃣ ตั้งค่าเว็บแอปพลิเคชัน
แก้ไข `config/config.php`:
```php
define('DB_HOST', 'your_sql_server_ip');
define('DB_NAME', 'logistic_app_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3️⃣ ตั้งค่า Web Server
- ชี้ Document Root ไปที่โฟลเดอร์ root ของโปรเจค
- ตรวจสอบว่า PHP Extensions ครบถ้วน:
  - `php_sqlsrv.dll`
  - `php_pdo_sqlsrv.dll`
  - `php_gd2.dll` (สำหรับการประมวลผลรูปภาพ)

## 🔐 การเข้าสู่ระบบ

### 👤 บัญชีทดสอบ
| Role | Username | Password | สิทธิ์ |
|------|----------|----------|--------|
| Admin | `admin` | `123456` | จัดการระบบทั้งหมด |
| Manager | `manager` | `123456` | จัดการรถและอนุมัติ |
| Employee | `employee1` | `123456` | บันทึกการเติมน้ำมัน |

## 🧪 การทดสอบ

### ✅ ฟีเจอร์ที่ทดสอบแล้ว
- 🚗 การจัดการรถ (CRUD + เลขไมล์)
- ⛽ การบันทึกการเติมน้ำมัน
- 📷 การอัปโหลดรูปภาพ + การลดขนาด
- ✅ การอนุมัติรายการ
- 📊 รายงานและสถิติ
- 🔐 ระบบ Authentication และ Authorization

### 🔗 URL ทดสอบ
```
http://your_server/index.php          # หน้าหลัก
http://your_server/test.php           # ทดสอบการเชื่อมต่อ
```

## 🔧 การปรับแต่งระบบ

### 📊 การตั้งค่าการอัปโหลดไฟล์
```php
// ใน config/config.php
define('MAX_FILE_SIZE', 52428800);     // 50MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// ใน .htaccess
php_value upload_max_filesize 50M
php_value post_max_size 50M
```

### 🎨 การปรับแต่ง UI
- ใช้ **Tailwind CSS** สำหรับ styling
- รองรับ **Responsive Design**
- **Dark Theme** เป็นหลัก

## 📝 บันทึกการเปลี่ยนแปลง

### v1.2.0 (ล่าสุด)
- ✨ เพิ่มระบบลดขนาดรูปภาพ Client-side
- 🎯 ปรับปรุงประสิทธิภาพการอัปโหลด
- 📊 เพิ่ม Progress Bar สำหรับการประมวลผล
- 🔧 แก้ไขปัญหา POST Content-Length exceeded

### v1.1.0
- 📊 เพิ่มระบบจัดการเลขไมล์
- 🚗 ปรับปรุงการจัดการรถพ่วง
- 📈 เพิ่มรายงานและสถิติใหม่

### v1.0.0
- 🎉 เปิดตัวระบบครั้งแรก
- 🔐 ระบบ Authentication
- ⛽ การบันทึกการเติมน้ำมัน
- 🚗 การจัดการรถพื้นฐาน

## 🤝 การสนับสนุน

สำหรับคำถามหรือปัญหาในการใช้งาน:
1. ตรวจสอบ Error Log ใน browser console
2. ตรวจสอบ PHP Error Log
3. ตรวจสอบ SQL Server Connection

## 📄 License

โปรเจคนี้เป็น Internal System สำหรับองค์กร