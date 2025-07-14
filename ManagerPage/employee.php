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
        // UPDATE employees
        $sql = "UPDATE employees SET first_name=?, last_name=?, email=?, phone_number=?, job_title=?, driver_license_number=?, license_expiry_date=?, employee_code=?
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
        $stmt = $conn->prepare("UPDATE users SET username=? WHERE employee_id=?");
        $stmt->execute([
            $employee_code,
            $_POST['edit_id']
        ]);
    } else {
        // INSERT
        $sql = "INSERT INTO employees (first_name, last_name, email, phone_number, job_title, driver_license_number, license_expiry_date, employee_code)
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
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, employee_id, active) VALUES (?, ?, ?, ?, 1)");
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
    // ลบ users ที่อ้างถึง employee_id นี้ก่อน
    $stmt = $conn->prepare("DELETE FROM users WHERE employee_id=?");
    $stmt->execute([$_GET['delete']]);
    // ลบ employees
    $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: employee.php");
    exit;
}

// ปิด/เปิดใช้งานผู้ใช้
if (isset($_GET['deactivate'])) {
    $stmt = $conn->prepare("UPDATE users SET active=0 WHERE employee_id=?");
    $stmt->execute([$_GET['deactivate']]);
    header("Location: employee.php");
    exit;
}
if (isset($_GET['activate'])) {
    $stmt = $conn->prepare("UPDATE users SET active=1 WHERE employee_id=?");
    $stmt->execute([$_GET['activate']]);
    header("Location: employee.php");
    exit;
}

// ดึงข้อมูลพนักงานทั้งหมด พร้อมสถานะจาก users
$stmt = $conn->query("
    SELECT e.*, u.active 
    FROM employees e 
    LEFT JOIN users u ON e.employee_id = u.employee_id 
    ORDER BY e.employee_id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลพนักงานที่จะแก้ไข (ถ้ามี)
$edit_employee = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id=?");
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
        .animate-slide-down {
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
    <?php include '../container/header.php'; ?>
    <div class="container mx-auto py-10">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-[#f9fafb]">จัดการข้อมูลพนักงาน</h1>
            <button onclick="openEmployeeModal()" class="flex items-center gap-2 bg-[#4ade80] hover:bg-[#22d3ee] text-[#111827] rounded-lg px-4 py-2 shadow-lg font-semibold transition-all duration-150" title="เพิ่มพนักงาน">
                <span class="text-lg">+</span>
                <span>เพิ่มพนักงานใหม่</span>
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

        <!-- Employee Modal -->
        <div id="employeeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-[#1f2937] border-[#374151] animate-slide-down">
                <div class="flex justify-between items-center pb-3 border-b border-[#374151]">
                    <h3 id="modalTitle" class="text-xl font-semibold text-[#f9fafb]">เพิ่มพนักงานใหม่</h3>
                    <button onclick="closeEmployeeModal()" class="text-[#9ca3af] hover:text-[#f87171] transition-colors duration-200">
                        <span class="text-2xl">&times;</span>
                    </button>
                </div>
                
                <form id="employeeForm" method="post" class="space-y-6 mt-6">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">รหัสพนักงาน *</label>
                                <input type="text" name="employee_code" id="employee_code" required class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" placeholder="EMP001">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ชื่อ *</label>
                                <input type="text" name="first_name" id="first_name" required class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" placeholder="ชื่อ">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">นามสกุล *</label>
                                <input type="text" name="last_name" id="last_name" required class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" placeholder="นามสกุล">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ชื่อเล่น</label>
                                <input type="text" name="nickname" id="nickname" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" placeholder="ชื่อเล่น">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">เพศ</label>
                                <select name="gender" id="gender" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                                    <option value="">เลือกเพศ</option>
                                    <option value="male">ชาย</option>
                                    <option value="female">หญิง</option>
                                    <option value="other">อื่นๆ</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">วันเกิด</label>
                                <input type="date" name="birth_date" id="birth_date" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">เบอร์โทรศัพท์</label>
                                <input type="tel" name="phone_number" id="phone_number" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" placeholder="081-234-5678">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">อีเมล</label>
                                <input type="email" name="email" id="email" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" placeholder="email@example.com">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">แผนก *</label>
                                <select name="department_id" id="department_id" required class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                                    <option value="">เลือกแผนก</option>
                                    <option value="1">บุคคล</option>
                                    <option value="2">การเงิน</option>
                                    <option value="3">โลจิสติกส์</option>
                                    <option value="4">คลังสินค้า</option>
                                    <option value="5">ขนส่ง</option>
                                    <option value="6">ซ่อมบำรุง</option>
                                    <option value="7">IT</option>
                                    <option value="8">บริหารทั่วไป</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ตำแหน่ง *</label>
                                <select name="job_title" id="job_title" required class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                                    <option value="">เลือกตำแหน่ง</option>
                                    <option value="ผู้จัดการ">ผู้จัดการ</option>
                                    <option value="หัวหน้างาน">หัวหน้างาน</option>
                                    <option value="พนักงานอาวุโส">พนักงานอาวุโส</option>
                                    <option value="พนักงาน">พนักงาน</option>
                                    <option value="พนักงานขับรถ">พนักงานขับรถ</option>
                                    <option value="ช่างซ่อม">ช่างซ่อม</option>
                                    <option value="พนักงานคลัง">พนักงานคลัง</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">วันเริ่มงาน *</label>
                                <input type="date" name="hire_date" id="hire_date" required class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">เงินเดือน</label>
                                <input type="number" name="base_salary" id="base_salary" step="0.01" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" placeholder="25000.00">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Driver Information -->
                    <div class="border-t border-[#374151] pt-4">
                        <div class="flex items-center mb-4">
                            <input type="checkbox" id="isDriver" class="h-4 w-4 text-[#4f46e5] focus:ring-[#4f46e5] bg-[#111827] border-[#374151] rounded">
                            <label for="isDriver" class="ml-2 block text-sm text-[#e5e7eb]">เป็นพนักงานขับรถ</label>
                        </div>
                        
                        <div id="driverInfo" class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">เลขใบขับขี่</label>
                                <input type="text" name="driver_license_number" id="driver_license_number" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" placeholder="เลขใบขับขี่">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[#e5e7eb] mb-2">วันหมดอายุใบขับขี่</label>
                                <input type="date" name="license_expiry_date" id="license_expiry_date" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address -->
                    <div class="border-t border-[#374151] pt-4">
                        <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ที่อยู่</label>
                        <textarea rows="3" name="address" id="address" class="w-full px-4 py-2 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors resize-none" placeholder="ที่อยู่ปัจจุบัน"></textarea>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-4 pt-4 border-t border-[#374151]">
                        <button type="button" onclick="closeEmployeeModal()" class="px-6 py-2 bg-[#374151] border border-[#4b5563] rounded-lg text-[#e5e7eb] hover:bg-[#4b5563] transition-colors duration-200">
                            ยกเลิก
                        </button>
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-[#4f46e5] to-[#7c3aed] text-white rounded-lg hover:shadow-lg hover:shadow-[#4f46e5]/20 hover:-translate-y-1 transition-all duration-300">
                            บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
function openEmployeeModal() {
    document.getElementById('modalTitle').innerText = 'เพิ่มพนักงานใหม่';
    document.getElementById('employeeForm').reset();
    document.getElementById('edit_id').value = '';
    document.getElementById('driverInfo').classList.add('hidden');
    document.getElementById('isDriver').checked = false;
    document.getElementById('employeeModal').classList.remove('hidden');
}

function closeEmployeeModal() {
    document.getElementById('employeeModal').classList.add('hidden');
}

function editEmployee(data) {
    document.getElementById('modalTitle').innerText = 'แก้ไขข้อมูลพนักงาน';
    document.getElementById('edit_id').value = data.employee_id || '';
    document.getElementById('employee_code').value = data.employee_code || '';
    document.getElementById('first_name').value = data.first_name || '';
    document.getElementById('last_name').value = data.last_name || '';
    document.getElementById('email').value = data.email || '';
    document.getElementById('phone_number').value = data.phone_number || '';
    document.getElementById('job_title').value = data.job_title || '';
    document.getElementById('driver_license_number').value = data.driver_license_number || '';
    document.getElementById('license_expiry_date').value = data.license_expiry_date || '';
    
    // Show driver info if license number exists
    if (data.driver_license_number) {
        document.getElementById('isDriver').checked = true;
        document.getElementById('driverInfo').classList.remove('hidden');
    } else {
        document.getElementById('isDriver').checked = false;
        document.getElementById('driverInfo').classList.add('hidden');
    }
    
    document.getElementById('employeeModal').classList.remove('hidden');
}

// Handle driver checkbox toggle
document.addEventListener('DOMContentLoaded', function() {
    const isDriverCheckbox = document.getElementById('isDriver');
    const driverInfo = document.getElementById('driverInfo');
    
    if (isDriverCheckbox && driverInfo) {
        isDriverCheckbox.addEventListener('change', function() {
            if (this.checked) {
                driverInfo.classList.remove('hidden');
            } else {
                driverInfo.classList.add('hidden');
                // Clear driver fields when unchecked
                document.getElementById('driver_license_number').value = '';
                document.getElementById('license_expiry_date').value = '';
            }
        });
    }
});
</script>
</body>

</html>