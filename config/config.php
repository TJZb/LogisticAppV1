<?php
/**
 * ไฟล์การตั้งค่าหลักของระบบ
 * สำหรับการกำหนดค่าต่าง ๆ ที่ใช้ทั่วทั้งระบบ
 */

// ตั้งค่า PHP สำหรับการอัปโหลดไฟล์ขนาดใหญ่
ini_set('post_max_size', '50M');          // ขนาดสูงสุดของ POST request
ini_set('upload_max_filesize', '50M');    // ขนาดสูงสุดของไฟล์แต่ละไฟล์
ini_set('max_file_uploads', 20);          // จำนวนไฟล์สูงสุดที่อัปโหลดได้พร้อมกัน
ini_set('memory_limit', '256M');          // เพิ่ม memory limit สำหรับประมวลผลไฟล์
ini_set('max_execution_time', 300);       // เพิ่มเวลาในการประมวลผลเป็น 5 นาที

// การตั้งค่าฐานข้อมูล
define('DB_HOST', '192.168.50.123');
define('DB_NAME', 'logistic_app_db');
define('DB_USER', 'bcf_it_dev');
define('DB_PASS', 'bcf@625_information');
define('DB_TYPE', 'sqlsrv'); // ประเภทฐานข้อมูล

// การตั้งค่าเซสชัน
define('SESSION_TIMEOUT', 3600); // 1 ชั่วโมง

// การตั้งค่าการอัปโหลดไฟล์
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 52428800); // เพิ่มเป็น 50MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']); // ลบ pdf ออก

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
