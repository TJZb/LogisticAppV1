<?php
/**
 * หน้าเข้าสู่ระบบ
 */

require_once 'includes/auth.php';

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบแล้วหรือไม่
if (check_session()) {
    // ส่งผู้ใช้ไปยังหน้าหลักตามบทบาท
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: AdminPage/user_manage.php');
            break;
        case 'manager':
            header('Location: ManagerPage/index.php');
            break;
        case 'employee':
            header('Location: EmployeePage/index.php');
            break;
        default:
            header('Location: index.php');
            break;
    }
    exit();
}

$error_message = '';

// ประมวลผลการเข้าสู่ระบบ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $user = authenticate_user($username, $password);
        
        if ($user) {
            create_session($user);
            
            // ตรวจสอบ redirect URL
            $redirect = $_GET['redirect'] ?? '';
            if (!empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL) === false) {
                // ถ้าไม่ใช่ URL ที่ถูกต้อง ให้ส่งไปหน้าหลัก
                switch ($user['role']) {
                    case 'admin':
                        $redirect = 'AdminPage/user_manage.php';
                        break;
                    case 'manager':
                        $redirect = 'ManagerPage/index.php';
                        break;
                    case 'employee':
                        $redirect = 'EmployeePage/index.php';
                        break;
                    default:
                        $redirect = 'index.php';
                        break;
                }
            } elseif (empty($redirect)) {
                // ถ้าไม่มี redirect ให้ส่งไปหน้าหลัก
                switch ($user['role']) {
                    case 'admin':
                        $redirect = 'AdminPage/user_manage.php';
                        break;
                    case 'manager':
                        $redirect = 'ManagerPage/index.php';
                        break;
                    case 'employee':
                        $redirect = 'EmployeePage/index.php';
                        break;
                    default:
                        $redirect = 'index.php';
                        break;
                }
            }
            
            header("Location: $redirect");
            exit();
        } else {
            $error_message = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ | LogisticApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
    <div class="w-full max-w-sm mx-auto bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <div class="text-center mb-8">
            <span class="font-bold text-3xl text-blue-400 select-none">LogisticApp</span>
            <h2 class="text-xl font-semibold mt-2 text-[#f9fafb]">เข้าสู่ระบบ</h2>
        </div>
        <form method="post" class="space-y-5">
            <div>
                <input type="text" name="username" placeholder="ชื่อผู้ใช้" required
                    class="w-full rounded-lg px-4 py-3 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#60a5fa] focus:ring-2 focus:ring-[#60a5fa] text-lg" autocomplete="username">
            </div>
            <div>
                <input type="password" name="password" placeholder="รหัสผ่าน" required
                    class="w-full rounded-lg px-4 py-3 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#60a5fa] focus:ring-2 focus:ring-[#60a5fa] text-lg" autocomplete="current-password">
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="bg-[#fee2e2] text-[#b91c1c] rounded-lg px-4 py-2 text-center font-semibold"><?=htmlspecialchars($error_message)?></div>
            <?php endif; ?>
            <button type="submit"
                class="w-full transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-bold px-6 py-3 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#4ade80] text-lg">
                เข้าสู่ระบบ
            </button>
        </form>
    </div>
</body>
</html>