<?php
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['manager', 'admin']);
$conn = connect_db();

// เพิ่ม/แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $job_title = $_POST['job_title'];
    $driver_license_number = $_POST['driver_license_number'];
    $license_expiry_date = $_POST['license_expiry_date'];
    $employee_code = $_POST['employee_code'];

    if (isset($_POST['edit_id']) && $_POST['edit_id'] !== '') {
        // UPDATE Employees
        $sql = "UPDATE Employees SET first_name=?, last_name=?, email=?, phone_number=?, job_title=?, driver_license_number=?, license_expiry_date=?, employee_code=?
                WHERE employee_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $phone_number,
            $job_title,
            $driver_license_number,
            $license_expiry_date,
            $employee_code,
            $_POST['edit_id']
        ]);
        // อัปเดต username ใน Users ให้ตรงกับ employee_code ใหม่
        $stmt = $conn->prepare("UPDATE Users SET username=? WHERE employee_id=?");
        $stmt->execute([
            $employee_code,
            $_POST['edit_id']
        ]);
    } else {
        // INSERT
        $sql = "INSERT INTO Employees (first_name, last_name, email, phone_number, job_title, driver_license_number, license_expiry_date, employee_code)
                OUTPUT INSERTED.employee_id
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $phone_number,
            $job_title,
            $driver_license_number,
            $license_expiry_date,
            $employee_code
        ]);
        // เพิ่ม user อัตโนมัติ
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $employee_id = $result['employee_id'];
        $default_password = '123456'; // หรือกำหนดเอง
        $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
        $role = 'employee';
        $stmt = $conn->prepare("INSERT INTO Users (username, password_hash, role, employee_id, active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([
            $employee_code,
            $password_hash,
            $role,
            $employee_id
        ]);
    }
    header("Location: employee.php");
    exit;
}

// ลบข้อมูล
if (isset($_GET['delete'])) {
    // ลบ Users ที่อ้างถึง employee_id นี้ก่อน
    $stmt = $conn->prepare("DELETE FROM Users WHERE employee_id=?");
    $stmt->execute([$_GET['delete']]);
    // ลบ Employees
    $stmt = $conn->prepare("DELETE FROM Employees WHERE employee_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: employee.php");
    exit;
}

// ปิด/เปิดใช้งานผู้ใช้
if (isset($_GET['deactivate'])) {
    $stmt = $conn->prepare("UPDATE Users SET active=0 WHERE employee_id=?");
    $stmt->execute([$_GET['deactivate']]);
    header("Location: employee.php");
    exit;
}
if (isset($_GET['activate'])) {
    $stmt = $conn->prepare("UPDATE Users SET active=1 WHERE employee_id=?");
    $stmt->execute([$_GET['activate']]);
    header("Location: employee.php");
    exit;
}

// ดึงข้อมูลพนักงานทั้งหมด พร้อมสถานะจาก Users
$stmt = $conn->query("
    SELECT e.*, u.active 
    FROM Employees e 
    LEFT JOIN Users u ON e.employee_id = u.employee_id 
    ORDER BY e.employee_id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลพนักงานที่จะแก้ไข (ถ้ามี)
$edit_employee = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM Employees WHERE employee_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_employee = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลคนขับ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
        body {
            font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif;
        }
    </style>
</head>
<?php include '../container/header.php'; ?>

<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
    <div class="container mx-auto py-10">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-[#f9fafb]">จัดการข้อมูลคนขับ</h1>
            <button onclick="openEmployeeModal()" class="flex items-center gap-2 bg-[#4ade80] hover:bg-[#22d3ee] text-[#111827] rounded-lg px-4 py-2 shadow-lg font-semibold transition-all duration-150" title="เพิ่มคนขับ">
                <h2 class="เปลี่ยน + เป็น   <h2 class="font-semibold text-[#60a5fa] text-lg">เพิ่มผู้ใช้ใหม่</h2>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-[#1f2937] rounded-2xl shadow-xl overflow-hidden">
                <thead>
                    <tr class="bg-gradient-to-r from-[#6366f1] to-[#4ade80] text-[#111827]">
                        <th class="px-4 py-3 text-left font-bold">ชื่อ</th>
                        <th class="px-4 py-3 text-left font-bold">นามสกุล</th>
                        <th class="px-4 py-3 text-left font-bold">อีเมล</th>
                        <th class="px-4 py-3 text-left font-bold">เบอร์โทร</th>
                        <th class="px-4 py-3 text-left font-bold">เลขใบขับขี่</th>
                        <th class="px-4 py-3 text-left font-bold">หมดอายุ</th>
                        <th class="px-4 py-3 text-left font-bold">รหัสพนักงาน</th>
                        <th class="px-4 py-3 text-left font-bold">สถานะ</th>
                        <th class="px-4 py-3 text-left font-bold">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $e): ?>
                        <tr class="even:bg-[#111827] odd:bg-[#1f2937] hover:bg-[#374151] transition-colors">
                            <td class="px-4 py-2"><?= htmlspecialchars($e['first_name']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['last_name']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['email']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['phone_number']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['driver_license_number']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['license_expiry_date']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['employee_code']) ?></td>
                            <td class="px-4 py-2">
                                <?php if ($e['active'] == 1): ?>
                                    <span class="text-green-400 font-semibold">ใช้งาน</span>
                                <?php else: ?>
                                    <span class="text-red-400 font-semibold">ลาออก/ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2">
                                <button onclick="editEmployee(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>)" class="text-[#60a5fa] underline mr-2 hover:text-[#4ade80] transition">แก้ไข</button>
                                <a href="?delete=<?= $e['employee_id'] ?>" class="text-[#f87171] underline hover:text-[#ef4444] transition" onclick="return confirm('ยืนยันการลบ?')">ลบ</a>
                                <?php if ($e['active'] == 1): ?>
                                    <a href="?deactivate=<?= $e['employee_id'] ?>" class="text-yellow-400 underline ml-2 hover:text-yellow-500 transition" onclick="return confirm('ปิดใช้งานผู้ใช้นี้?')">ปิดใช้งาน</a>
                                <?php else: ?>
                                    <a href="?activate=<?= $e['employee_id'] ?>" class="text-blue-400 underline ml-2 hover:text-blue-500 transition" onclick="return confirm('เปิดใช้งานผู้ใช้นี้?')">เปิดใช้งาน</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Popup Modal สำหรับเพิ่ม/แก้ไข -->
        <div id="employeeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-[#1f2937] rounded-2xl shadow-xl p-8 max-w-xl w-full relative text-[#e0e0e0]">
                <button onclick="closeEmployeeModal()" class="absolute top-2 right-2 text-2xl text-[#f87171] hover:text-[#ef4444] font-bold">&times;</button>
                <h2 id="modalTitle" class="text-xl font-bold mb-4 text-[#60a5fa]">เพิ่มคนขับ</h2>
                <form id="employeeForm" method="post" class="space-y-5">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold mb-1">ชื่อ</label>
                            <input type="text" name="first_name" id="first_name" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#4ade80] focus:ring-2 focus:ring-[#4ade80]" required>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">นามสกุล</label>
                            <input type="text" name="last_name" id="last_name" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#4ade80] focus:ring-2 focus:ring-[#4ade80]" required>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Email</label>
                            <input type="email" name="email" id="email" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#4ade80] focus:ring-2 focus:ring-[#4ade80]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">เบอร์โทร</label>
                            <input type="text" name="phone_number" id="phone_number" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#4ade80] focus:ring-2 focus:ring-[#4ade80]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">ตำแหน่ง</label>
                            <input type="text" name="job_title" id="job_title" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#4ade80] focus:ring-2 focus:ring-[#4ade80]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">เลขใบขับขี่</label>
                            <input type="text" name="driver_license_number" id="driver_license_number" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#4ade80] focus:ring-2 focus:ring-[#4ade80]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">วันหมดอายุใบขับขี่</label>
                            <input type="date" name="license_expiry_date" id="license_expiry_date" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#4ade80] focus:ring-2 focus:ring-[#4ade80]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">รหัสพนักงาน</label>
                            <input type="text" name="employee_code" id="employee_code" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:border-[#4ade80] focus:ring-2 focus:ring-[#4ade80]" required>
                        </div>
                    </div>
                    <div class="mt-6 text-center flex flex-col md:flex-row gap-4 justify-center">
                        <button type="submit" class="transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#4ade80]">
                            บันทึก
                        </button>
                        <button type="button" onclick="closeEmployeeModal()" class="transition-all duration-150 bg-[#f87171] hover:bg-[#ef4444] text-white font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#f87171] text-center">
                            ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
function openEmployeeModal() {
    document.getElementById('modalTitle').innerText = 'เพิ่มคนขับ';
    document.getElementById('employeeForm').reset();
    document.getElementById('edit_id').value = '';
    document.getElementById('employeeModal').classList.remove('hidden');
}
function closeEmployeeModal() {
    document.getElementById('employeeModal').classList.add('hidden');
}
function editEmployee(data) {
    document.getElementById('modalTitle').innerText = 'แก้ไขข้อมูลคนขับ';
    document.getElementById('edit_id').value = data.employee_id || '';
    document.getElementById('first_name').value = data.first_name || '';
    document.getElementById('last_name').value = data.last_name || '';
    document.getElementById('email').value = data.email || '';
    document.getElementById('phone_number').value = data.phone_number || '';
    document.getElementById('job_title').value = data.job_title || '';
    document.getElementById('driver_license_number').value = data.driver_license_number || '';
    document.getElementById('license_expiry_date').value = data.license_expiry_date || '';
    document.getElementById('employee_code').value = data.employee_code || '';
    document.getElementById('employeeModal').classList.remove('hidden');
}
</script>
</body>

</html>