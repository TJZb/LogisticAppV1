<?php
/**
 * ไฟล์ทดสอบ auth function
 */

echo "<h1>🔍 ทดสอบฟังก์ชัน auth()</h1>";
echo "<hr>";

try {
    require_once 'includes/auth.php';
    echo "✅ โหลดไฟล์ auth.php สำเร็จ<br>";
    
    // ตรวจสอบฟังก์ชันที่มี
    $functions = get_defined_functions()['user'];
    $auth_functions = array_filter($functions, function($func) {
        return strpos($func, 'auth') !== false || 
               strpos($func, 'session') !== false ||
               strpos($func, 'authenticate') !== false;
    });
    
    echo "<h3>🔧 ฟังก์ชันที่เกี่ยวข้องกับ auth:</h3>";
    foreach ($auth_functions as $func) {
        if (function_exists($func)) {
            echo "✅ $func()<br>";
        }
    }
    
    // ตรวจสอบว่าฟังก์ชัน auth มีอยู่หรือไม่
    if (function_exists('auth')) {
        echo "<br>✅ ฟังก์ชัน auth() พร้อมใช้งาน<br>";
    } else {
        echo "<br>❌ ฟังก์ชัน auth() ไม่พบ<br>";
    }
    
    if (function_exists('check_session')) {
        echo "✅ ฟังก์ชัน check_session() พร้อมใช้งาน<br>";
    } else {
        echo "❌ ฟังก์ชัน check_session() ไม่พบ<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><a href='AdminPage/user_manage.php'>🔗 ทดสอบ AdminPage</a></p>";
echo "<p><a href='login.php'>🔑 ไปหน้า Login</a></p>";
?>

<style>
body { font-family: 'Sarabun', sans-serif; margin: 20px; background: #f5f5f5; }
h1, h3 { color: #333; }
hr { margin: 20px 0; border: 1px solid #ddd; }
a { color: #007bff; text-decoration: none; margin-right: 10px; }
a:hover { text-decoration: underline; }
</style>
