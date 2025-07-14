<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
<nav style="background-color:#111827; border-bottom:1px solid #374151; height:60px;" class="px-4 flex items-center justify-between relative">
    <!-- Mobile menu button -->
    <button id="mobileMenuBtn" class="md:hidden flex items-center text-blue-400 focus:outline-none" aria-label="เมนู">
        <span class="material-icons md-28">menu</span>
    </button>
    <div class="flex items-center">
        <span class="nav-logo font-bold text-xl text-blue-400 mr-6 select-none">LogisticApp</span>
        <div id="navLinks" class="hidden md:flex items-center">
            <?php if ($role === 'employee'|| $role === 'manager'||$role === 'admin'): ?>
                <a href="../EmployeePage/index.php" class="nav-link flex items-center mr-6 hover:underline"><span class="material-icons md-18 mr-1">home</span>หน้าหลัก</a>
                <a href="../EmployeePage/profile_setting.php" class="nav-link flex items-center mr-6 hover:underline"><span class="material-icons md-18 mr-1">person</span>โปรไฟล์ของฉัน</a>
                <a href="../EmployeePage/fuel_history.php" class="nav-link flex items-center mr-6 hover:underline"><span class="material-icons md-18 mr-1">history</span>ประวัติเติมน้ำมัน</a>
                <?php if ($role === 'manager'||$role === 'admin'): ?>
                    <a href="../ManagerPage/index.php" class="nav-link flex items-center mr-6 hover:underline"><span class="material-icons md-18 mr-1">local_shipping</span>จัดการรถ</a>
                    <a href="../ManagerPage/employee.php" class="nav-link flex items-center mr-6 hover:underline"><span class="material-icons md-18 mr-1">groups</span>จัดการพนักงาน</a>
                    <a href="../ManagerPage/orderlist.php" class="nav-link flex items-center mr-6 hover:underline"><span class="material-icons md-18 mr-1">assignment</span>อนุมัติเติมน้ำมัน</a>
                    <a href="../ManagerPage/report.php" class="nav-link flex items-center mr-6 hover:underline"><span class="material-icons md-18 mr-1">bar_chart</span>รายงาน</a>
                    <?php if ($role === 'admin'): ?>
                        <a href="../AdminPage/user_manage.php" class="nav-link flex items-center mr-6 hover:underline"><span class="material-icons md-18 mr-1">admin_panel_settings</span>จัดการผู้ใช้</a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="hidden md:flex items-center gap-4">
        <?php if (!empty($role)): ?>
            <span class="text-[#e5e7eb] text-sm">
                สวัสดี, <?= htmlspecialchars($full_name ?: $username) ?>
                <span class="text-[#9ca3af]">(<?= htmlspecialchars($role) ?>)</span>
            </span>
            <a href="../logout.php" class="nav-link flex items-center text-red-300 hover:underline"><span class="material-icons md-18 mr-1">logout</span>ออกจากระบบ</a>
        <?php else: ?>
            <a href="../login.php" class="nav-link flex items-center text-blue-300 hover:underline"><span class="material-icons md-18 mr-1">login</span>เข้าสู่ระบบ</a>
        <?php endif; ?>
    </div>
    <!-- Mobile menu dropdown -->
    <div id="mobileMenu" class="md:hidden absolute top-full left-0 w-full bg-[#111827] border-t border-[#374151] z-50 hidden">
        <div class="flex flex-col py-2">
            <?php if ($role === 'employee'|| $role === 'manager'||$role === 'admin'): ?>
                <a href="../EmployeePage/index.php" class="nav-link flex items-center px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">home</span>หน้าหลัก</a>
                <a href="../EmployeePage/profile_setting.php" class="nav-link flex items-center px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">person</span>โปรไฟล์ของฉัน</a>
                <a href="../EmployeePage/fuel_history.php" class="nav-link flex items-center px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">history</span>ประวัติเติมน้ำมัน</a>
                <?php if ($role === 'manager'||$role === 'admin'): ?>
                    <a href="../ManagerPage/index.php" class="nav-link flex items-center px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">local_shipping</span>จัดการรถ</a>
                    <a href="../ManagerPage/employee.php" class="nav-link flex items-center px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">groups</span>จัดการพนักงาน</a>
                    <a href="../ManagerPage/orderlist.php" class="nav-link flex items-center px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">assignment</span>อนุมัติเติมน้ำมัน</a>
                    <a href="../ManagerPage/report.php" class="nav-link flex items-center px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">bar_chart</span>รายงาน</a>
                    <?php if ($role === 'admin'): ?>
                        <a href="../AdminPage/user_manage.php" class="nav-link flex items-center px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">admin_panel_settings</span>จัดการผู้ใช้</a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($role)): ?>
                <div class="border-t border-[#374151] mt-2 pt-2">
                    <div class="px-4 py-2 text-[#e5e7eb] text-sm">
                        สวัสดี, <?= htmlspecialchars($full_name ?: $username) ?>
                        <span class="text-[#9ca3af] block">(<?= htmlspecialchars($role) ?>)</span>
                    </div>
                    <a href="../logout.php" class="nav-link flex items-center text-red-300 px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">logout</span>ออกจากระบบ</a>
                </div>
            <?php else: ?>
                <a href="../login.php" class="nav-link flex items-center text-blue-300 px-4 py-2 hover:underline"><span class="material-icons md-18 mr-1">login</span>เข้าสู่ระบบ</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<style>
    .nav-link { color: #cbd5e1; font-weight: 500; transition: color 0.2s; }
    .nav-link:hover, .nav-link.active { color: #60a5fa; }
    .nav-logo { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
    @media (max-width: 768px) {
        nav { height: auto !important; }
        .nav-logo { font-size: 1.1rem !important; }
    }
</style>
<script>
    // Mobile menu toggle
    const btn = document.getElementById('mobileMenuBtn');
    const menu = document.getElementById('mobileMenu');
    const navLinks = document.getElementById('navLinks');
    btn.addEventListener('click', function() {
        menu.classList.toggle('hidden');
    });
    // Close menu when click outside
    document.addEventListener('click', function(e) {
        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
</script>