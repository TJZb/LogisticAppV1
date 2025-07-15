<?php
/**
 * Quick Test Script สำหรับทดสอบระบบ
 * ใช้สำหรับตรวจสอบการทำงานของระบบหลังจาก Deploy
 */

echo "<h1>🚛 Logistic App - System Test</h1>";
echo "<p>วันที่ทดสอบ: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// ทดสอบการเชื่อมต่อฐานข้อมูล
echo "<h2>1. ทดสอบการเชื่อมต่อฐานข้อมูล</h2>";
try {
    require_once 'service/connect.php';
    $conn = connect_db();
    echo "✅ เชื่อมต่อฐานข้อมูลสำเร็จ<br>";
    
    // ทดสอบการ query
    $stmt = $conn->query("SELECT COUNT(*) as count FROM vehicles");
    $result = $stmt->fetch();
    echo "✅ จำนวนรถในระบบ: " . $result['count'] . " คัน<br>";
    
} catch (Exception $e) {
    echo "❌ ไม่สามารถเชื่อมต่อฐานข้อมูล: " . $e->getMessage() . "<br>";
}

// ทดสอบการ Authentication
echo "<h2>2. ทดสอบระบบ Authentication</h2>";
try {
    require_once 'includes/auth.php';
    echo "✅ โหลด Authentication system สำเร็จ<br>";
} catch (Exception $e) {
    echo "❌ ปัญหาระบบ Authentication: " . $e->getMessage() . "<br>";
}

// ทดสอบการมีอยู่ของไฟล์สำคัญ
echo "<h2>3. ทดสอบไฟล์สำคัญ</h2>";
$important_files = [
    'config/config.php' => 'การตั้งค่าระบบ',
    'EmployeePage/index.php' => 'หน้าเลือกรถ (Employee)',
    'EmployeePage/Addfeal_form.php' => 'ฟอร์มเติมน้ำมัน',
    'ManagerPage/index.php' => 'จัดการรถ (Manager)',
    'ManagerPage/orderlist.php' => 'อนุมัติรายการ',
    'AdminPage/user_manage.php' => 'จัดการผู้ใช้ (Admin)'
];

foreach ($important_files as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $description ($file)<br>";
    } else {
        echo "❌ ไม่พบไฟล์: $description ($file)<br>";
    }
}

// ทดสอบโฟลเดอร์ uploads
echo "<h2>4. ทดสอบการอัพโหลดไฟล์</h2>";
if (is_dir('uploads') && is_writable('uploads')) {
    echo "✅ โฟลเดอร์ uploads พร้อมใช้งาน<br>";
} else {
    echo "❌ โฟลเดอร์ uploads ไม่พร้อมใช้งาน<br>";
}

// ทดสอบ PHP Extensions
echo "<h2>5. ทดสอบ PHP Extensions</h2>";
$required_extensions = ['pdo', 'pdo_sqlsrv', 'mbstring', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ PHP Extension: $ext<br>";
    } else {
        echo "❌ ไม่พบ PHP Extension: $ext<br>";
    }
}

// ข้อมูลระบบ
echo "<h2>6. ข้อมูลระบบ</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

echo "<hr>";
echo "<h2>🔗 ลิงก์ทดสอบ</h2>";
echo "<a href='login.php' target='_blank'>🔐 หน้าเข้าสู่ระบบ</a><br>";
echo "<a href='EmployeePage/' target='_blank'>👷 หน้าพนักงาน</a><br>";
echo "<a href='ManagerPage/' target='_blank'>👔 หน้าผู้จัดการ</a><br>";
echo "<a href='AdminPage/' target='_blank'>⚙️ หน้าผู้ดูแลระบบ</a><br>";

echo "<hr>";
echo "<p><strong>ข้อมูลการเข้าสู่ระบบ:</strong></p>";
echo "<ul>";
echo "<li>Admin: admin / 123456</li>";
echo "<li>Manager: manager / 123456</li>";
echo "<li>Employee: employee1 / 123456</li>";
echo "</ul>";
?>
