<?php
/**
 * ไฟล์การตั้งค่าหลักของระบบ
 * สำหรับการกำหนดค่าต่าง ๆ ที่ใช้ทั่วทั้งระบบ
 */

// การตั้งค่าฐานข้อมูล
define('DB_HOST', '192.168.50.123');
define('DB_NAME', 'logistic_app_db');
define('DB_USER', 'sa');
define('DB_PASS', 'P@ssw0rd');

// การตั้งค่าเซสชัน
define('SESSION_TIMEOUT', 3600); // 1 ชั่วโมง

// การตั้งค่าการอัปโหลดไฟล์
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// การตั้งค่าระบบ
define('SYSTEM_NAME', 'ระบบจัดการขนส่ง');
define('SYSTEM_VERSION', '1.0');
define('DEFAULT_TIMEZONE', 'Asia/Bangkok');

// ตั้งค่า timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// การตั้งค่าการแสดงผลข้อผิดพลาด (ปิดในโปรดักชั่น)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// การตั้งค่า encoding
ini_set('default_charset', 'UTF-8');
?>
