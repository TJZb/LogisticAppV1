<?php
/**
 * ไฟล์สำหรับจัดการการรับรองตัวตนและเซสชัน
 * รวมฟังก์ชันที่เกี่ยวข้องกับการ login/logout และการตรวจสอบสิทธิ์
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * ฟังก์ชันสำหรับการตรวจสอบการเข้าสู่ระบบ
 */
function authenticate_user($username, $password) {
    $conn = connect_db();
    
    try {
        $stmt = $conn->prepare("SELECT u.*, e.first_name, e.last_name, e.employee_code 
                               FROM users u 
                               LEFT JOIN employees e ON u.employee_id = e.employee_id 
                               WHERE u.username = ? AND u.active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // อัปเดตเวลาเข้าสู่ระบบล่าสุด
            $update_stmt = $conn->prepare("UPDATE users SET last_login = GETDATE() WHERE user_id = ?");
            $update_stmt->execute([$user['user_id']]);
            
            log_activity('LOGIN_SUCCESS', "User: {$user['username']}");
            return $user;
        }
        
        log_activity('LOGIN_FAILED', "Username: $username");
        return false;
        
    } catch (PDOException $e) {
        log_activity('LOGIN_ERROR', $e->getMessage());
        return false;
    }
}

/**
 * ฟังก์ชันสำหรับการสร้างเซสชัน
 */
function create_session($user) {
    session_start();
    session_regenerate_id(true); // ป้องกัน session hijacking
    
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['employee_code'] = $user['employee_code'];
    $_SESSION['login_time'] = time();
    
    // ตั้งค่า session timeout
    $_SESSION['expires'] = time() + SESSION_TIMEOUT;
}

/**
 * ฟังก์ชันสำหรับการตรวจสอบเซสชัน
 */
function check_session() {
    session_start();
    
    // ตรวจสอบว่ามีเซสชันหรือไม่
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // ตรวจสอบ session timeout
    if (isset($_SESSION['expires']) && $_SESSION['expires'] < time()) {
        destroy_session();
        return false;
    }
    
    // ต่ออายุเซสชัน
    $_SESSION['expires'] = time() + SESSION_TIMEOUT;
    
    return true;
}

/**
 * ฟังก์ชันสำหรับการทำลายเซสชัน
 */
function destroy_session() {
    session_start();
    
    if (isset($_SESSION['username'])) {
        log_activity('LOGOUT', "User: {$_SESSION['username']}");
    }
    
    // ทำลายตัวแปรเซสชันทั้งหมด
    $_SESSION = array();
    
    // ทำลาย session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // ทำลายเซสชัน
    session_destroy();
}

/**
 * ฟังก์ชันสำหรับการเปลี่ยนรหัสผ่าน
 */
function change_password($user_id, $old_password, $new_password) {
    $conn = connect_db();
    
    try {
        // ตรวจสอบรหัสผ่านเก่า
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($old_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'รหัสผ่านเก่าไม่ถูกต้อง'];
        }
        
        // อัปเดตรหัสผ่านใหม่
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ?, password_changed = GETDATE() WHERE user_id = ?");
        $result = $update_stmt->execute([$new_hash, $user_id]);
        
        if ($result) {
            log_activity('PASSWORD_CHANGED', "User ID: $user_id");
            return ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'];
        } else {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'];
        }
        
    } catch (PDOException $e) {
        log_activity('PASSWORD_CHANGE_ERROR', $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ'];
    }
}

/**
 * ฟังก์ชันสำหรับการตรวจสอบสิทธิ์การเข้าถึงหน้า
 */
function require_login() {
    if (!check_session()) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

/**
 * ฟังก์ชันสำหรับการตรวจสอบสิทธิ์ตามบทบาท
 */
function require_role($required_roles) {
    require_login();
    
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    if (!in_array($_SESSION['role'], $required_roles)) {
        header('Location: /index.php?error=access_denied');
        exit();
    }
}

/**
 * ฟังก์ชันสำหรับการดึงข้อมูลผู้ใช้ปัจจุบัน
 */
function get_current_user_info() {
    if (!check_session()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'employee_id' => $_SESSION['employee_id'],
        'full_name' => $_SESSION['full_name'],
        'employee_code' => $_SESSION['employee_code']
    ];
}
?>
