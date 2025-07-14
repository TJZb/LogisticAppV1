<?php
/**
 * ไฟล์ทดสอบการทำงานของ session management
 */

echo "<h1>🔍 การทดสอบ Session Management</h1>";
echo "<hr>";

// ตรวจสอบสถานะ session ปัจจุบัน
echo "<h3>📊 สถานะ Session:</h3>";
echo "Session Status: " . session_status() . "<br>";
switch (session_status()) {
    case PHP_SESSION_DISABLED:
        echo "❌ Session ถูกปิดใช้งาน<br>";
        break;
    case PHP_SESSION_NONE:
        echo "⚪ ยังไม่เริ่ม Session<br>";
        break;
    case PHP_SESSION_ACTIVE:
        echo "✅ Session ทำงานอยู่<br>";
        break;
}

// ทดสอบโหลด auth.php
try {
    require_once 'includes/auth.php';
    echo "✅ โหลด auth.php สำเร็จ<br>";
    
    // ตรวจสอบสถานะ session หลังโหลด auth
    echo "<br><h3>📊 สถานะ Session หลังโหลด auth.php:</h3>";
    echo "Session Status: " . session_status() . "<br>";
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "✅ Session เริ่มทำงานแล้ว<br>";
        
        // ตรวจสอบข้อมูล session
        if (isset($_SESSION['user_id'])) {
            echo "🔐 มีการ login อยู่<br>";
            echo "User: " . ($_SESSION['username'] ?? 'ไม่ระบุ') . "<br>";
            echo "Role: " . ($_SESSION['role'] ?? 'ไม่ระบุ') . "<br>";
        } else {
            echo "🔓 ยังไม่ได้ login<br>";
        }
    }
    
    // ทดสอบฟังก์ชัน
    if (function_exists('auth')) {
        echo "<br>✅ ฟังก์ชัน auth() พร้อมใช้งาน<br>";
    }
    
    if (function_exists('check_session')) {
        echo "✅ ฟังก์ชัน check_session() พร้อมใช้งาน<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>✨ การทดสอบเสร็จสิ้น</strong></p>";
echo "<p><a href='login.php'>🔑 ไปหน้า Login</a> | <a href='AdminPage/user_manage.php'>👤 ทดสอบ Admin Page</a></p>";
?>

<style>
body { font-family: 'Sarabun', sans-serif; margin: 20px; background: #f5f5f5; }
h1, h3 { color: #333; }
hr { margin: 20px 0; border: 1px solid #ddd; }
a { color: #007bff; text-decoration: none; margin-right: 10px; }
a:hover { text-decoration: underline; }
</style>
