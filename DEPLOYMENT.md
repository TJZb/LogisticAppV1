# การ Deploy ระบบ Logistic App

## ข้อกำหนดระบบ

### Server Requirements
- PHP 7.4 หรือสูงกว่า
- SQL Server 2017 หรือสูงกว่า
- PHP SQL Server Driver (sqlsrv)
- Web Server (Apache/Nginx)

### ฐานข้อมูล
- SQL Server Instance
- Database: `logistic_app_db`
- Collation: `Thai_CI_AS`

## ขั้นตอนการติดตั้ง

### 1. การตั้งค่าฐานข้อมูล

รันสคริปต์สร้างฐานข้อมูลและโครงสร้างทั้งหมด:
```sql
sqlcmd -S [SERVER] -U [USER] -P [PASSWORD] -i database/complete_database_setup.sql
```

**หมายเหตุ**: สคริปต์นี้จะสร้างโครงสร้างฐานข้อมูลทั้งหมด รวมถึงฟิลด์ `current_mileage` และอัปเดตข้อมูลเลขไมล์จากประวัติการเติมน้ำมันอัตโนมัติ

### 2. การตั้งค่าเว็บแอปพลิเคชัน

1. แก้ไขไฟล์ `config/config.php`:
   ```php
   define('DB_HOST', 'your_sql_server_ip');
   define('DB_NAME', 'logistic_app_db');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

2. ตั้งค่า Web Server ให้ชี้ไปที่โฟลเดอร์ root

### 3. การทดสอบระบบ

1. เข้าสู่ระบบด้วย:
   - Username: `admin` / Password: `123456` (Admin)
   - Username: `manager` / Password: `123456` (Manager)
   - Username: `employee1` / Password: `123456` (Employee)

2. ทดสอบฟีเจอร์หลัก:
   - ✅ การจัดการรถ (เพิ่ม/แก้ไข/ลบ พร้อมเลขไมล์)
   - ✅ การเติมน้ำมัน
   - ✅ การอนุมัติรายการ
   - ✅ รายงานและสถิติ

## โครงสร้างไฟล์

```
LogisticAppV1/
├── index.php                 # หน้าแรก
├── login.php                 # หน้าเข้าสู่ระบบ
├── logout.php                # ออกจากระบบ
├── config/
│   └── config.php            # การตั้งค่าระบบ
├── database/
│   ├── complete_database_setup.sql    # สคริปต์ติดตั้งฐานข้อมูลทั้งหมด
│   └── README.md                      # คู่มือการใช้งานฐานข้อมูล
├── EmployeePage/
│   ├── index.php             # เลือกรถเติมน้ำมัน
│   ├── Addfeal_form.php      # ฟอร์มเติมน้ำมัน
│   └── fuel_history.php      # ประวัติการเติม
├── ManagerPage/
│   ├── index.php             # จัดการรถ
│   ├── orderlist.php         # อนุมัติรายการ
│   └── report.php            # รายงาน
└── AdminPage/
    └── user_manage.php       # จัดการผู้ใช้
```

## ฟีเจอร์ใหม่: การจัดการเลขไมล์

### สำหรับ Manager
- ✅ แสดงเลขไมล์ปัจจุบันในตารางรถ
- ✅ แก้ไขเลขไมล์ในฟอร์มจัดการรถ
- ✅ อัปเดตอัตโนมัติเมื่ออนุมัติการเติมน้ำมัน

### สำหรับ Employee
- ✅ แสดงเลขไมล์ปัจจุบันก่อนเลือกรถ
- ✅ ป้อนเลขไมล์ขณะเติมน้ำมัน

### Backend
- ✅ เก็บ `current_mileage` ในตาราง vehicles
- ✅ Fallback ไปใช้ `odometer_reading` ล่าสุด
- ✅ การตรวจสอบความถูกต้องของเลขไมล์

## Security
- ✅ Authentication & Authorization
- ✅ SQL Injection Protection (PDO)
- ✅ XSS Protection
- ✅ CSRF Protection

## สำหรับการทดสอบ
URL: http://192.168.50.123/
