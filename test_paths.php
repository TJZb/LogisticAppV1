<?php
/**
 * ไฟล์ทดสอบการทำงานของ Path ต่างๆ
 * สำหรับตรวจสอบว่าระบบทำงานได้ถูกต้อง
 */

echo "<h1>🔍 การทดสอบ Path และการเรียกใช้ไฟล์</h1>";
echo "<hr>";

// ทดสอบ 1: ตรวจสอบไฟล์สำคัญ
echo "<h2>📁 ไฟล์สำคัญ</h2>";
$important_files = [
    'config/config.php' => 'การตั้งค่าระบบ',
    'includes/auth.php' => 'ระบบ Authentication',
    'includes/functions.php' => 'ฟังก์ชันทั่วไป',
    'service/connect.php' => 'การเชื่อมต่อฐานข้อมูล',
    'container/header.php' => 'Header ของระบบ',
    'container/footer.php' => 'Footer ของระบบ',
    'asset/css/app-theme.css' => 'CSS Theme',
];

foreach ($important_files as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $file - $description<br>";
    } else {
        echo "❌ $file - $description (ไม่พบไฟล์)<br>";
    }
}

echo "<hr>";

// ทดสอบ 2: ตรวจสอบไฟล์ PHP ในแต่ละโฟลเดอร์
echo "<h2>📂 ไฟล์ PHP ในแต่ละโฟลเดอร์</h2>";

$directories = ['AdminPage', 'ManagerPage', 'EmployeePage'];
foreach ($directories as $dir) {
    echo "<h3>$dir/</h3>";
    if (is_dir($dir)) {
        $files = glob("$dir/*.php");
        foreach ($files as $file) {
            echo "📄 " . basename($file) . "<br>";
        }
    } else {
        echo "❌ โฟลเดอร์ไม่พบ<br>";
    }
}

echo "<hr>";

// ทดสอบ 3: ตรวจสอบการ include ไฟล์
echo "<h2>🔗 การทดสอบ Include ไฟล์</h2>";

try {
    require_once 'config/config.php';
    echo "✅ config/config.php โหลดสำเร็จ<br>";
} catch (Exception $e) {
    echo "❌ config/config.php: " . $e->getMessage() . "<br>";
}

try {
    require_once 'includes/functions.php';
    echo "✅ includes/functions.php โหลดสำเร็จ<br>";
} catch (Exception $e) {
    echo "❌ includes/functions.php: " . $e->getMessage() . "<br>";
}

try {
    require_once 'service/connect.php';
    echo "✅ service/connect.php โหลดสำเร็จ<br>";
} catch (Exception $e) {
    echo "❌ service/connect.php: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// ทดสอบ 4: ตรวจสอบค่าคงที่จาก config
echo "<h2>⚙️ การตั้งค่าจาก Config</h2>";
if (defined('DB_HOST')) {
    echo "✅ DB_HOST: " . DB_HOST . "<br>";
    echo "✅ DB_NAME: " . DB_NAME . "<br>";
    echo "✅ UPLOAD_PATH: " . UPLOAD_PATH . "<br>";
} else {
    echo "❌ ไม่สามารถโหลดค่าคงที่จาก config ได้<br>";
}

echo "<hr>";

// ทดสอบ 5: ตรวจสอบโฟลเดอร์ uploads
echo "<h2>📤 โฟลเดอร์ Uploads</h2>";
if (is_dir('uploads') && is_writable('uploads')) {
    echo "✅ โฟลเดอร์ uploads พร้อมใช้งาน<br>";
    $upload_files = glob('uploads/*');
    echo "📁 ไฟล์ในโฟลเดอร์: " . count($upload_files) . " ไฟล์<br>";
} else {
    echo "❌ โฟลเดอร์ uploads ไม่พร้อมใช้งาน<br>";
}

echo "<hr>";
echo "<p><strong>✨ การทดสอบเสร็จสิ้น</strong></p>";
echo "<p><a href='login.php'>🔑 ไปหน้า Login</a> | <a href='index.php'>🏠 ไปหน้าหลัก</a></p>";
?>

<style>
body { font-family: 'Sarabun', sans-serif; margin: 20px; background: #f5f5f5; }
h1, h2, h3 { color: #333; }
hr { margin: 20px 0; border: 1px solid #ddd; }
a { color: #007bff; text-decoration: none; margin-right: 10px; }
a:hover { text-decoration: underline; }
</style>
