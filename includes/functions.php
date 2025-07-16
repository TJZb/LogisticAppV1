<?php
/**
 * ไฟล์ Helper Functions สำหรับการใช้งานทั่วไป
 * รวบรวมฟังก์ชันที่ใช้งานร่วมกันทั่วทั้งระบบ
 */

/**
 * ฟังก์ชันสำหรับการ sanitize input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * ฟังก์ชันสำหรับการตรวจสอบไฟล์อัปโหลด
 */
function validate_upload($file) {
    $errors = [];
    
    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)';
    }
    
    // ตรวจสอบนามสกุลไฟล์
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = 'นามสกุลไฟล์ไม่ถูกต้อง (อนุญาต: ' . implode(', ', ALLOWED_EXTENSIONS) . ')';
    }
    
    return $errors;
}

/**
 * ฟังก์ชันสำหรับการสร้างชื่อไฟล์เฉพาะ
 */
function generate_unique_filename($original_name, $prefix = '') {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $timestamp = date('Y-m-d_H-i-s');
    $random = substr(md5(uniqid()), 0, 6);
    
    return ($prefix ? $prefix . '_' : '') . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * ฟังก์ชันสำหรับการแปลงวันที่เป็นรูปแบบไทย
 */
function format_thai_date($date) {
    $thai_months = [
        '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
        '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
        '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
        '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
    ];
    
    $date_parts = explode('-', date('Y-m-d', strtotime($date)));
    $year = $date_parts[0] + 543; // แปลงเป็น พ.ศ.
    $month = $thai_months[$date_parts[1]];
    $day = intval($date_parts[2]);
    
    return $day . ' ' . $month . ' ' . $year;
}

/**
 * ฟังก์ชันสำหรับการแสดงข้อความแจ้งเตือน
 */
function show_alert($message, $type = 'info') {
    $alert_class = [
        'success' => 'alert-success',
        'error' => 'alert-danger', 
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = $alert_class[$type] ?? 'alert-info';
    
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * ฟังก์ชันสำหรับการคำนวณระยะทาง
 */
function calculate_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // รัศมีของโลกในหน่วยกิโลเมตร
    
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lon = $lon2_rad - $lon1_rad;
    
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) + 
         cos($lat1_rad) * cos($lat2_rad) * 
         sin($delta_lon / 2) * sin($delta_lon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

/**
 * ฟังก์ชันสำหรับการตรวจสอบสิทธิ์การเข้าถึง
 */
function check_permission($required_role) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit();
    }
    
    $role_hierarchy = [
        'employee' => 1,
        'manager' => 2,
        'admin' => 3
    ];
    
    $user_level = $role_hierarchy[$_SESSION['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 99;
    
    if ($user_level < $required_level) {
        header('Location: /index.php?error=permission_denied');
        exit();
    }
    
    return true;
}

/**
 * ฟังก์ชันสำหรับการล็อกระบบ
 */
function log_activity($action, $details = '') {
    $log_file = __DIR__ . '/../logs/activity_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'] ?? 'anonymous';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $log_entry = "[$timestamp] User: $user_id | IP: $ip | Action: $action | Details: $details" . PHP_EOL;
    
    // สร้างโฟล์เดอร์ logs หากไม่มี
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>
