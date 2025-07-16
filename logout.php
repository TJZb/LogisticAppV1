<?php
/**
 * หน้าออกจากระบบ
 */

require_once 'includes/auth.php';

// ทำลายเซสชัน
destroy_session();

// ส่งกลับไปหน้าเข้าสู่ระบบ
header('Location: login.php');
exit();
?>