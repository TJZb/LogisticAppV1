<?php
// filepath: /workspaces/logisticApp/AdminPage/user_manage.php
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['admin']);

$conn = connect_db();

// เพิ่ม/แก้ไข/ลบ user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;
        $active = isset($_POST['active']) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, employee_id, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password_hash, $role, $employee_id, $active]);
        $msg = "เพิ่มผู้ใช้สำเร็จ";
    }
    if (isset($_POST['edit_user']) && isset($_POST['user_id'])) {
        $user_id = $_POST['user_id'];
        $role = $_POST['role'];
        $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;
        $active = isset($_POST['active']) ? 1 : 0;
        $sql = "UPDATE users SET role=?, employee_id=?, active=?";
        $params = [$role, $employee_id, $active];
        if (!empty($_POST['password'])) {
            $sql .= ", password_hash=?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        $sql .= " WHERE user_id=?";
        $params[] = $user_id;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $msg = "แก้ไขข้อมูลผู้ใช้สำเร็จ";
    }
    if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
        $stmt->execute([$_POST['user_id']]);
        $msg = "ลบผู้ใช้สำเร็จ";
    }
}

// ดึงข้อมูล user ทั้งหมด
$stmt = $conn->query("SELECT u.*, e.first_name, e.last_name FROM users u LEFT JOIN employees e ON u.employee_id = e.employee_id ORDER BY u.user_id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงรายชื่อพนักงานสำหรับเลือกผูก user
$emps = $conn->query("SELECT employee_id, first_name, last_name FROM employees ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../container/header.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
<div class="container mx-auto py-10 max-w-4xl">
    <h1 class="text-3xl font-bold mb-8 text-center text-[#f9fafb]">จัดการผู้ใช้</h1>
    <?php if (!empty($msg)): ?>
        <div class="mb-4 p-3 rounded-lg bg-[#bbf7d0] text-[#166534] text-center font-semibold shadow"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>

    <!-- ปุ่มเพิ่มผู้ใช้ -->
    <div class="flex justify-end mb-6">
        <button id="showAddUserFormBtn"
            class="transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#4ade80]">
            + เพิ่มผู้ใช้ใหม่
        </button>
    </div>

    <!-- ฟอร์มเพิ่มผู้ใช้ (ซ่อนเริ่มต้น) -->
    <div id="addUserFormContainer" class="mb-8 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-semibold text-[#60a5fa] text-lg">เพิ่มผู้ใช้ใหม่</h2>
            <button id="closeAddUserFormBtn" class="text-[#f87171] hover:text-[#ef4444] text-2xl font-bold px-2">&times;</button>
        </div>
        <form method="post" class="flex flex-wrap gap-4 items-end">
            <input type="text" name="username" placeholder="Username" required class="rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
            <input type="password" name="password" placeholder="Password" required class="rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
            <select name="role" required class="rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                <option value="employee">employee</option>
                <option value="manager">manager</option>
                <option value="admin">admin</option>
            </select>
            <select name="employee_id" class="rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                <option value="">-- ไม่ผูกกับพนักงาน --</option>
                <?php foreach($emps as $emp): ?>
                    <option value="<?=$emp['employee_id']?>"><?=htmlspecialchars($emp['first_name'].' '.$emp['last_name'])?></option>
                <?php endforeach; ?>
            </select>
            <label class="flex items-center gap-2 text-[#e0e0e0]"><input type="checkbox" name="active" checked> ใช้งาน</label>
            <button type="submit" name="add_user" class="transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#4ade80]">เพิ่ม</button>
        </form>
    </div>

    <div class="bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <h2 class="font-semibold mb-4 text-[#60a5fa] text-lg">รายชื่อผู้ใช้</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full bg-[#111827] rounded-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#60a5fa] to-[#4ade80] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">Username</th>
                    <th class="px-4 py-3 text-left font-bold">Role</th>
                    <th class="px-4 py-3 text-left font-bold">ชื่อพนักงาน</th>
                    <th class="px-4 py-3 text-left font-bold">สถานะ</th>
                    <th class="px-4 py-3 text-left font-bold">แก้ไข</th>
                    <th class="px-4 py-3 text-left font-bold">ลบ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($users as $u): ?>
                <tr class="even:bg-[#1f2937] odd:bg-[#111827] hover:bg-[#374151] transition-colors">
                    <form method="post">
                        <td class="px-4 py-2"><?=htmlspecialchars($u['username'])?></td>
                        <td class="px-4 py-2">
                            <select name="role" class="rounded-lg px-2 py-1 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                                <option value="employee" <?=$u['role']=='employee'?'selected':''?>>employee</option>
                                <option value="manager" <?=$u['role']=='manager'?'selected':''?>>manager</option>
                                <option value="admin" <?=$u['role']=='admin'?'selected':''?>>admin</option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <select name="employee_id" class="rounded-lg px-2 py-1 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                                <option value="">-- ไม่ผูก --</option>
                                <?php foreach($emps as $emp): ?>
                                    <option value="<?=$emp['employee_id']?>" <?=$u['employee_id']==$emp['employee_id']?'selected':''?>><?=htmlspecialchars($emp['first_name'].' '.$emp['last_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <label class="flex items-center gap-2"><input type="checkbox" name="active" <?=$u['active']?'checked':''?>> <span class="text-[#e0e0e0]">ใช้งาน</span></label>
                        </td>
                        <td class="px-4 py-2">
                            <input type="hidden" name="user_id" value="<?=$u['user_id']?>">
                            <input type="password" name="password" placeholder="เปลี่ยนรหัสผ่าน" class="rounded-lg px-2 py-1 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                            <button type="submit" name="edit_user" class="transition-all duration-150 bg-[#facc15] hover:bg-[#fbbf24] text-[#111827] font-semibold px-4 py-1 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#facc15] ml-2">บันทึก</button>
                        </td>
                        <td class="px-4 py-2">
                            <input type="hidden" name="user_id" value="<?=$u['user_id']?>">
                            <button type="submit" name="delete_user" class="transition-all duration-150 bg-[#f87171] hover:bg-[#ef4444] text-white font-semibold px-4 py-1 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#f87171]" onclick="return confirm('ยืนยันลบผู้ใช้นี้?')">ลบ</button>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<script>
    // Toggle add user form
    const showBtn = document.getElementById('showAddUserFormBtn');
    const closeBtn = document.getElementById('closeAddUserFormBtn');
    const formContainer = document.getElementById('addUserFormContainer');
    showBtn.addEventListener('click', () => {
        formContainer.classList.remove('hidden');
        showBtn.classList.add('hidden');
    });
    closeBtn.addEventListener('click', () => {
        formContainer.classList.add('hidden');
        showBtn.classList.remove('hidden');
    });
</script>
</body>
</html>