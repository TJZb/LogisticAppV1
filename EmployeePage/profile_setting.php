<?php
session_start();
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['employee', 'manager', 'admin']);

$conn = connect_db();
$user_id = $_SESSION['user_id'] ?? null;

// ดึงข้อมูล user และ employee
$stmt = $conn->prepare("SELECT u.username, e.first_name, e.last_name, e.email, e.phone_number
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_field'])) {
    $field = $_POST['edit_field'];
    if ($field === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id=?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!password_verify($old, $row['password_hash'])) {
            $msg = "รหัสผ่านเดิมไม่ถูกต้อง";
        } elseif ($new !== $confirm) {
            $msg = "รหัสผ่านใหม่ไม่ตรงกัน";
        } elseif (strlen($new) < 6) {
            $msg = "รหัสผ่านใหม่ควรมีอย่างน้อย 6 ตัวอักษร";
        } else {
            $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
            $msg = "เปลี่ยนรหัสผ่านสำเร็จ";
        }
    } else {
        $value = $_POST['value'] ?? '';
        if (in_array($field, ['first_name', 'last_name', 'email', 'phone_number'])) {
            // สำหรับ phone ต้องใช้ phone_number ในฐานข้อมูล
            $db_field = ($field === 'phone') ? 'phone_number' : $field;
            $stmt = $conn->prepare("UPDATE employees SET $db_field=? WHERE employee_id=(SELECT employee_id FROM users WHERE user_id=?)");
            $stmt->execute([$value, $user_id]);
            $msg = "บันทึกข้อมูลสำเร็จ";
        }
    }
    // อัปเดตข้อมูลใหม่
    $stmt = $conn->prepare("SELECT u.username, e.first_name, e.last_name, e.email, e.phone_number
        FROM users u
        LEFT JOIN employees e ON u.employee_id = e.employee_id
        WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
?>
<?php include '../container/header.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าโปรไฟล์</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
      @media (max-width: 700px) {
        .profile-card { padding: 1.25rem !important; }
        .profile-grid { flex-direction: column !important; }
        .profile-avatar { margin-bottom: 1.5rem !important; }
        .profile-info-list { width: 100% !important; }
      }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
<div class="container mx-auto py-10 max-w-xl">
    <h1 class="text-3xl font-bold mb-8 text-center text-[#f9fafb]">โปรไฟล์ของฉัน</h1>
    <?php if ($msg): ?>
        <div id="alertBox" class="mb-4 p-3 rounded-lg bg-[#bbf7d0] text-[#166534] text-center font-semibold shadow transition-all duration-300">
            <?=htmlspecialchars($msg)?>
        </div>
        <script>
        setTimeout(() => {
            const alertBox = document.getElementById('alertBox');
            if(alertBox) alertBox.style.display = 'none';
        }, 3000);
        </script>
    <?php endif; ?>
    <div class="bg-[#1f2937] rounded-2xl shadow-xl p-8 profile-card border-2 border-transparent flex flex-col items-center">
        <!-- Avatar -->
        <div class="profile-avatar mb-6">
            <div class="w-24 h-24 rounded-full bg-[#374151] flex items-center justify-center text-4xl font-bold text-[#60a5fa] shadow-lg">
                <?= mb_substr($user['first_name'] ?? 'U', 0, 1) . mb_substr($user['last_name'] ?? '', 0, 1) ?>
            </div>
        </div>
        <!-- ข้อมูลโปรไฟล์ -->
        <div class="w-full profile-info-list">
            <?php
            $fields = [
                ['label' => 'ชื่อผู้ใช้', 'key' => 'username', 'readonly' => true, 'icon' => 'fa-user'],
                ['label' => 'ชื่อ', 'key' => 'first_name', 'icon' => 'fa-id-card'],
                ['label' => 'นามสกุล', 'key' => 'last_name', 'icon' => 'fa-id-card'],
                ['label' => 'อีเมล', 'key' => 'email', 'icon' => 'fa-envelope'],
                ['label' => 'เบอร์โทร', 'key' => 'phone', 'icon' => 'fa-phone'],
                ['label' => 'รหัสผ่าน', 'key' => 'password', 'icon' => 'fa-lock'],
            ];
            foreach ($fields as $f):
            ?>
            <div class="flex items-center gap-3 border-b border-[#374151] py-3">
                <div class="w-8 flex justify-center">
                    <?php if ($f['icon']): ?>
                        <i class="fa <?= $f['icon'] ?> text-[#60a5fa]"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <div class="font-semibold"><?= $f['label'] ?></div>
                    <?php if ($f['key'] === 'password'): ?>
                        <div class="bg-[#111827] rounded-lg px-3 py-2 tracking-widest mt-1">
                            <?php $pwlen = 6; echo str_repeat('*', $pwlen); ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-[#111827] rounded-lg px-3 py-2 mt-1">
                            <?php 
                            // สำหรับ phone ต้องใช้ phone_number จากฐานข้อมูล
                            $display_key = ($f['key'] === 'phone') ? 'phone_number' : $f['key'];
                            echo htmlspecialchars($user[$display_key] ?? '');
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ml-2">
                    <?php if (empty($f['readonly'])): ?>
                    <?php 
                    // สำหรับ phone ต้องใช้ phone_number จากฐานข้อมูล
                    $edit_value = ($f['key'] === 'phone') ? ($user['phone_number'] ?? '') : ($user[$f['key']] ?? '');
                    ?>
                    <button onclick="showEdit('<?= $f['key'] ?>', '<?= htmlspecialchars($edit_value, ENT_QUOTES) ?>')" class="bg-[#374151] hover:bg-[#60a5fa] text-[#e0e0e0] px-4 py-1 rounded-lg transition-all duration-150">แก้ไข</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Popup Modal แก้ไข -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-8 max-w-md w-full relative text-[#e0e0e0]">
            <button onclick="closeEdit()" class="absolute top-2 right-2 text-2xl text-[#f87171] hover:text-[#ef4444] font-bold">&times;</button>
            <h2 id="editTitle" class="text-xl font-bold mb-4 text-[#60a5fa]">แก้ไข</h2>
            <form id="editForm" method="post" class="space-y-5">
                <input type="hidden" name="edit_field" id="edit_field">
                <div id="editFields"></div>
                <div class="mt-6 text-center flex flex-col md:flex-row gap-4 justify-center">
                    <button type="submit" class="transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-semibold px-6 py-2 rounded-lg shadow hover:scale-105">บันทึก</button>
                    <button type="button" onclick="closeEdit()" class="transition-all duration-150 bg-[#f87171] hover:bg-[#ef4444] text-white font-semibold px-6 py-2 rounded-lg shadow hover:scale-105">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- FontAwesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<script>
function showEdit(field, value) {
    let title = '';
    let html = '';
    document.getElementById('edit_field').value = field;
    if (field === 'first_name') {
        title = 'แก้ไขชื่อ';
        html = `<input type="text" name="value" value="${value}" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>`;
    } else if (field === 'last_name') {
        title = 'แก้ไขนามสกุล';
        html = `<input type="text" name="value" value="${value}" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>`;
    } else if (field === 'email') {
        title = 'แก้ไขอีเมล';
        html = `<input type="email" name="value" value="${value}" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">`;
    } else if (field === 'phone') {
        title = 'แก้ไขเบอร์โทร';
        html = `<input type="text" name="value" value="${value}" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">`;
    } else if (field === 'password') {
        title = 'เปลี่ยนรหัสผ่าน';
        html = `
            <input type="hidden" name="edit_field" value="password">
            <div>
                <label class="block font-semibold mb-1">รหัสผ่านเดิม</label>
                <input type="password" name="old_password" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
            </div>
            <div>
                <label class="block font-semibold mb-1">รหัสผ่านใหม่</label>
                <input type="password" name="new_password" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
            </div>
            <div>
                <label class="block font-semibold mb-1">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
            </div>
        `;
    }
    document.getElementById('editTitle').innerText = title;
    document.getElementById('editFields').innerHTML = html;
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEdit() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>
</body>
</html>