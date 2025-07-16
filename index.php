<?php
/**
 * หน้าหลักของระบบ
 * ตรวจสอบการเข้าสู่ระบบและส่งไปยังหน้าที่เหมาะสม
 */

require_once 'includes/auth.php';

// ตรวจสอบการเข้าสู่ระบบ
if (check_session()) {
    // ส่งไปยังหน้าหลักตามบทบาท
    $user = get_current_user_info();
    
    switch ($user['role']) {
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
            // ถ้าบทบาทไม่ชัดเจน ให้ออกจากระบบ
            destroy_session();
            header('Location: login.php?error=invalid_role');
            break;
    }
} else {
    // ถ้ายังไม่ได้เข้าสู่ระบบ ส่งไปหน้า login
    header('Location: login.php');
}

exit();
?>