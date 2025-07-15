<?php
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['manager', 'admin']);
$conn = connect_db();

// --- ส่วนจัดการรถพ่วง (Trailers) - ใช้ตาราง vehicles ด้วย category_id สำหรับรถพ่วง ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trailer_form'])) {
    $license_plate = $_POST['trailer_license_plate'];
    $status = $_POST['trailer_status'];
    
    // หา category_id สำหรับรถพ่วง
    $stmt_cat = $conn->prepare("SELECT category_id FROM vehicle_categories WHERE category_code = 'TRAILER'");
    $stmt_cat->execute();
    $trailer_category = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    $trailer_category_id = $trailer_category['category_id'] ?? 1;
    
    if (isset($_POST['trailer_edit_id']) && $_POST['trailer_edit_id'] !== '') {
        // UPDATE - ตรวจสอบว่าทะเบียนซ้ำกับรถคันอื่นหรือไม่
        $stmt_check = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate = ? AND vehicle_id != ? AND is_deleted = 0");
        $stmt_check->execute([$license_plate, $_POST['trailer_edit_id']]);
        if ($stmt_check->rowCount() > 0) {
            echo "<script>alert('ทะเบียนรถนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE vehicles SET license_plate=?, status=?, updated_at=GETDATE() WHERE vehicle_id=?");
        try {
            $stmt->execute([$license_plate, $status, $_POST['trailer_edit_id']]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE KEY constraint') !== false) {
                echo "<script>alert('ทะเบียนรถนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            } else {
                echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "');window.history.back();</script>";
            }
            exit;
        }
    } else {
        // INSERT - ตรวจสอบทะเบียนซ้ำ
        $stmt_check = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate = ? AND is_deleted = 0");
        $stmt_check->execute([$license_plate]);
        if ($stmt_check->rowCount() > 0) {
            echo "<script>alert('ทะเบียนรถนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO vehicles (license_plate, category_id, status, created_at, updated_at) VALUES (?, ?, ?, GETDATE(), GETDATE())");
        try {
            $stmt->execute([$license_plate, $trailer_category_id, $status]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE KEY constraint') !== false) {
                echo "<script>alert('ทะเบียนรถนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            } else {
                echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "');window.history.back();</script>";
            }
            exit;
        }
    }
    header("Location: index.php");
    exit;
}

// --- ส่วนจัดการรถหลัก (Vehicles) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['trailer_form'])) {
    $license_plate = trim($_POST['license_plate']);
    $province = trim($_POST['province'] ?? '');
    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $year = trim($_POST['year']);
    $vin = trim($_POST['vin']);
    $color = trim($_POST['color']);
    $vehicle_type = $_POST['vehicle_type'] === 'อื่นๆ' ? trim($_POST['vehicle_type_other']) : trim($_POST['vehicle_type']);
    $fuel_type = trim($_POST['fuel_type']);
    $current_mileage = !empty($_POST['current_mileage']) ? intval($_POST['current_mileage']) : null;
    $vehicle_description = trim($_POST['vehicle_description'] ?? '');
    $status = trim($_POST['status']);
    $last_updated_by_employee_id = $_SESSION['employee_id'] ?? 1;
    $last_updated_at = date('Y-m-d H:i:s');
    // Prevent empty license plate (which causes duplicate key error)
    if ($license_plate === '') {
        echo "<script>alert('กรุณากรอกทะเบียนรถ');window.history.back();</script>";
        exit;
    }

    if (isset($_POST['edit_id']) && $_POST['edit_id'] !== '') {
        // UPDATE - ตรวจสอบว่าทะเบียนซ้ำกับรถคันอื่นหรือไม่
        $stmt_check = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate = ? AND vehicle_id != ?");
        $stmt_check->execute([$license_plate, $_POST['edit_id']]);
        if ($stmt_check->rowCount() > 0) {
            echo "<script>alert('ทะเบียนรถนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            exit;
        }
        
        // UPDATE - First, handle vehicle type and brand
        // Get or create brand_id
        $brand_id = null;
        if (!empty($make)) {
            $stmt_brand = $conn->prepare("SELECT brand_id FROM vehicle_brands WHERE brand_name = ?");
            $stmt_brand->execute([$make]);
            $brand = $stmt_brand->fetch(PDO::FETCH_ASSOC);
            if ($brand) {
                $brand_id = $brand['brand_id'];
            } else {
                // Create new brand
                $stmt_brand_insert = $conn->prepare("INSERT INTO vehicle_brands (brand_name, brand_code, created_at, updated_at) VALUES (?, ?, GETDATE(), GETDATE())");
                $brand_code = strtoupper(substr($make, 0, 3)) . '_' . uniqid();
                $stmt_brand_insert->execute([$make, $brand_code]);
                $brand_id = $conn->lastInsertId();
            }
        }
        
        // Get or create category_id
        $category_id = null;
        if (!empty($vehicle_type)) {
            $stmt_cat = $conn->prepare("SELECT category_id FROM vehicle_categories WHERE category_name = ?");
            $stmt_cat->execute([$vehicle_type]);
            $category = $stmt_cat->fetch(PDO::FETCH_ASSOC);
            if ($category) {
                $category_id = $category['category_id'];
            } else {
                // Create new category
                $stmt_cat_insert = $conn->prepare("INSERT INTO vehicle_categories (category_name, category_code, created_at, updated_at) VALUES (?, ?, GETDATE(), GETDATE())");
                $category_code = strtoupper(str_replace(' ', '_', $vehicle_type));
                $stmt_cat_insert->execute([$vehicle_type, $category_code]);
                $category_id = $conn->lastInsertId();
            }
        }
        
        // Get or create fuel_type_id
        $fuel_type_id = null;
        if (!empty($fuel_type)) {
            $stmt_fuel = $conn->prepare("SELECT fuel_type_id FROM fuel_types WHERE fuel_name = ?");
            $stmt_fuel->execute([$fuel_type]);
            $fuel = $stmt_fuel->fetch(PDO::FETCH_ASSOC);
            if ($fuel) {
                $fuel_type_id = $fuel['fuel_type_id'];
            } else {
                // Create new fuel type
                $stmt_fuel_insert = $conn->prepare("INSERT INTO fuel_types (fuel_name, fuel_code, created_at, updated_at) VALUES (?, ?, GETDATE(), GETDATE())");
                $fuel_code = strtoupper(substr($fuel_type, 0, 3)) . '_' . uniqid();
                $stmt_fuel_insert->execute([$fuel_type, $fuel_code]);
                $fuel_type_id = $conn->lastInsertId();
            }
        }
        
        $sql = "UPDATE vehicles SET license_plate=?, province=?, brand_id=?, model_name=?, year_manufactured=?, chassis_number=?, color=?, category_id=?, fuel_type_id=?, current_mileage=?, vehicle_description=?, status=?, updated_at=GETDATE()
                WHERE vehicle_id=?";
        $stmt = $conn->prepare($sql);
        try {
            $stmt->execute([
                $license_plate, $province, $brand_id, $model, $year, $vin, $color, $category_id, $fuel_type_id, $current_mileage, $vehicle_description, $status, $_POST['edit_id']
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE KEY constraint') !== false) {
                echo "<script>alert('ข้อมูลที่กรอกมีการซ้ำกับข้อมูลที่มีอยู่แล้ว กรุณาตรวจสอบทะเบียนรถ VIN หรือเลขเครื่องยนต์');window.history.back();</script>";
            } else {
                echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "');window.history.back();</script>";
            }
            exit;
        }
    } else {
        // INSERT - ตรวจสอบทะเบียนซ้ำ
        $stmt_check = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate = ?");
        $stmt_check->execute([$license_plate]);
        if ($stmt_check->rowCount() > 0) {
            echo "<script>alert('ทะเบียนรถนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            exit;
        }
        
        // INSERT - Handle vehicle type and brand
        // Get or create brand_id
        $brand_id = null;
        if (!empty($make)) {
            $stmt_brand = $conn->prepare("SELECT brand_id FROM vehicle_brands WHERE brand_name = ?");
            $stmt_brand->execute([$make]);
            $brand = $stmt_brand->fetch(PDO::FETCH_ASSOC);
            if ($brand) {
                $brand_id = $brand['brand_id'];
            } else {
                // Create new brand
                $stmt_brand_insert = $conn->prepare("INSERT INTO vehicle_brands (brand_name, brand_code, created_at, updated_at) VALUES (?, ?, GETDATE(), GETDATE())");
                $brand_code = strtoupper(substr($make, 0, 3)) . '_' . uniqid();
                $stmt_brand_insert->execute([$make, $brand_code]);
                $brand_id = $conn->lastInsertId();
            }
        }
        
        // Get or create category_id
        $category_id = null;
        if (!empty($vehicle_type)) {
            $stmt_cat = $conn->prepare("SELECT category_id FROM vehicle_categories WHERE category_name = ?");
            $stmt_cat->execute([$vehicle_type]);
            $category = $stmt_cat->fetch(PDO::FETCH_ASSOC);
            if ($category) {
                $category_id = $category['category_id'];
            } else {
                // Create new category
                $stmt_cat_insert = $conn->prepare("INSERT INTO vehicle_categories (category_name, category_code, created_at, updated_at) VALUES (?, ?, GETDATE(), GETDATE())");
                $category_code = strtoupper(str_replace(' ', '_', $vehicle_type));
                $stmt_cat_insert->execute([$vehicle_type, $category_code]);
                $category_id = $conn->lastInsertId();
            }
        }
        
        // Get or create fuel_type_id
        $fuel_type_id = null;
        if (!empty($fuel_type)) {
            $stmt_fuel = $conn->prepare("SELECT fuel_type_id FROM fuel_types WHERE fuel_name = ?");
            $stmt_fuel->execute([$fuel_type]);
            $fuel = $stmt_fuel->fetch(PDO::FETCH_ASSOC);
            if ($fuel) {
                $fuel_type_id = $fuel['fuel_type_id'];
            } else {
                // Create new fuel type
                $stmt_fuel_insert = $conn->prepare("INSERT INTO fuel_types (fuel_name, fuel_code, created_at, updated_at) VALUES (?, ?, GETDATE(), GETDATE())");
                $fuel_code = strtoupper(substr($fuel_type, 0, 3)) . '_' . uniqid();
                $stmt_fuel_insert->execute([$fuel_type, $fuel_code]);
                $fuel_type_id = $conn->lastInsertId();
            }
        }
        
        $sql = "INSERT INTO vehicles (license_plate, province, brand_id, model_name, year_manufactured, chassis_number, color, category_id, fuel_type_id, current_mileage, vehicle_description, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
        $stmt = $conn->prepare($sql);
        try {
            $stmt->execute([
                $license_plate, $province, $brand_id, $model, $year, $vin, $color, $category_id, $fuel_type_id, $current_mileage, $vehicle_description, $status
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE KEY constraint') !== false) {
                echo "<script>alert('ข้อมูลที่กรอกมีการซ้ำกับข้อมูลที่มีอยู่แล้ว กรุณาตรวจสอบทะเบียนรถ VIN หรือเลขเครื่องยนต์');window.history.back();</script>";
            } else {
                echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "');window.history.back();</script>";
            }
            exit;
        }
    }
    header("Location: index.php");
    exit;
}

// ลบข้อมูล
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM vehicles WHERE vehicle_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: index.php");
    exit;
}

// ลบข้อมูลรถพ่วง (ใช้ soft delete)
if (isset($_GET['trailer_delete'])) {
    $stmt = $conn->prepare("UPDATE vehicles SET is_deleted = 1, updated_at = GETDATE() WHERE vehicle_id=?");
    $stmt->execute([$_GET['trailer_delete']]);
    header("Location: index.php");
    exit;
}

// ดึงข้อมูลรถหลักและรถพ่วงรวมกัน จากตาราง vehicles เดียวกัน พร้อมเลขไมล์ปัจจุบัน
$vehicles = [];
$stmt = $conn->query("
    SELECT v.*, 
           CASE WHEN vc.category_code = 'TRAILER' THEN 'trailer' ELSE 'vehicle' END AS type,
           vc.category_name,
           vc.category_name as vehicle_type,
           vb.brand_name as make,
           v.model_name as model,
           v.year_manufactured as year,
           v.current_mileage,
           (SELECT TOP 1 fr.odometer_reading 
            FROM fuel_records fr 
            WHERE fr.vehicle_id = v.vehicle_id 
            AND fr.odometer_reading IS NOT NULL 
            ORDER BY fr.fuel_date DESC) AS last_odometer_reading,
           v.chassis_number as vin,
           ft.fuel_name as fuel_type
    FROM vehicles v 
    LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id 
    LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
    LEFT JOIN fuel_types ft ON v.fuel_type_id = ft.fuel_type_id
    WHERE v.is_deleted = 0 
    ORDER BY v.vehicle_id DESC
");
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลรถที่จะแก้ไข (ถ้ามี)
$edit_vehicle = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT v.*, vb.brand_name as make, v.model_name as model, v.year_manufactured as year, 
                           vc.category_name as vehicle_type, v.chassis_number as vin, ft.fuel_name as fuel_type
                           FROM vehicles v 
                           LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
                           LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
                           LEFT JOIN fuel_types ft ON v.fuel_type_id = ft.fuel_type_id
                           WHERE v.vehicle_id=? AND v.is_deleted = 0");
    $stmt->execute([$_GET['edit']]);
    $edit_vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ดึงข้อมูลรถพ่วงสำหรับฟอร์มแก้ไข
$trailers = $conn->query("
    SELECT v.*, vc.category_name 
    FROM vehicles v 
    LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id 
    WHERE vc.category_code = 'TRAILER' AND v.is_deleted = 0 
    ORDER BY v.vehicle_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$edit_trailer = null;
if (isset($_GET['trailer_edit'])) {
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_id=? AND is_deleted = 0");
    $stmt->execute([$_GET['trailer_edit']]);
    $edit_trailer = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการการเติมเชื้อเพลิง</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
    </style>
</head>
<?php include '../container/header.php'; ?>
<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
<div class="container mx-auto py-10">
    <h1 class="text-3xl font-bold mb-8 text-center text-[#f9fafb]">จัดการข้อมูลรถ</h1>
    <!-- ฟิลเตอร์และค้นหาแบบเดียวกับของคนขับ -->
    <form id="filterForm" method="get" class="flex flex-col md:flex-row gap-2 md:gap-4 justify-center mb-6 items-center">
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto justify-center items-center">
            <input type="text" name="search" id="search" value="<?=isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''?>" placeholder="ค้นหาข้อมูลรถ เช่น ทะเบียน, ยี่ห้อ, รุ่น, ปี, สถานะ" class="rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] w-full sm:w-64" />
            <?php
            // ประเภทรถพื้นฐาน
            $basic_vehicle_types = ['4 ล้อ', '6 ล้อ', '10 ล้อ', 'พ่วง'];
            // ดึงรายการประเภทรถที่มีในฐานข้อมูล (vehicles) โดยใช้ join กับ vehicle_categories
            $db_vehicle_types = $conn->query("SELECT DISTINCT vc.category_name FROM vehicles v 
                LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id 
                WHERE vc.category_name IS NOT NULL AND vc.category_name <> '' 
                ORDER BY vc.category_name ASC")->fetchAll(PDO::FETCH_COLUMN);
            $additional_types = array_diff($db_vehicle_types, $basic_vehicle_types);
            $vehicle_types = array_merge($basic_vehicle_types, $additional_types);
            ?>
            <select name="type" id="type" class="rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] w-full sm:w-40">
                <option value="">ทุกประเภท</option>
                <?php foreach ($vehicle_types as $vt): ?>
                    <option value="<?=htmlspecialchars($vt)?>" <?=isset($_GET['type']) && $_GET['type'] === $vt ? 'selected' : ''?>><?=htmlspecialchars($vt)?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <script>
    // submit form on input change (search, type)
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('search').addEventListener('input', function() {
            document.getElementById('filterForm').submit();
        });
        document.getElementById('type').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
    </script>

    <!-- ตารางและ modal รถหลัก -->
    <div id="vehicleSection">
        <!-- ปุ่มเพิ่มรถ -->
        <div class="flex justify-end mb-4">
            <button onclick="openVehicleModal()" class="bg-[#4ade80] hover:bg-[#22d3ee] text-[#111827] rounded-full px-6 h-12 flex items-center justify-center shadow-lg text-lg font-bold transition-all duration-150" title="เพิ่มรถ">
                เพิ่มรถ
            </button>
        </div>
        <!-- ตารางรถหลัก -->
        <div class="overflow-x-auto mb-10" id="vehicleTableContainer">
            <table class="min-w-full bg-[#1f2937] rounded-2xl shadow-xl overflow-hidden">
                <thead>
                    <tr class="bg-gradient-to-r from-[#6366f1] to-[#4ade80] text-[#111827]">
                        <th class="px-4 py-3 text-left font-bold">ทะเบียน</th>
                        <th class="px-4 py-3 text-left font-bold">ยี่ห้อ</th>
                        <th class="px-4 py-3 text-left font-bold">รุ่น</th>
                        <th class="px-4 py-3 text-left font-bold">ปี</th>
                        <th class="px-4 py-3 text-left font-bold">เลขไมล์</th>
                        <!-- Department column removed as field doesn't exist in database -->
                        <th class="px-4 py-3 text-left font-bold">สถานะ</th>
                        <th class="px-4 py-3 text-left font-bold">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // ฟิลเตอร์ข้อมูลตาม search และ type
                $filtered_vehicles = $vehicles;
                if (isset($_GET['type']) && $_GET['type'] !== '') {
                    $filtered_vehicles = array_filter($filtered_vehicles, function($v) {
                        return isset($v['vehicle_type']) && $v['vehicle_type'] === $_GET['type'];
                    });
                }
                if (isset($_GET['search']) && trim($_GET['search']) !== '') {
                    $search = mb_strtolower(trim($_GET['search']), 'UTF-8');
                    $filtered_vehicles = array_filter($filtered_vehicles, function($v) use ($search) {
                        foreach (['license_plate','make','model','year','status'] as $field) {
                            if (isset($v[$field]) && mb_strpos(mb_strtolower($v[$field], 'UTF-8'), $search) !== false) return true;
                        }
                        return false;
                    });
                }
                ?>
                <?php foreach ($filtered_vehicles as $v): ?>
                    <?php
                    // กำหนดเลขไมล์ที่จะแสดง
                    $current_mileage = $v['current_mileage'] ?? $v['last_odometer_reading'] ?? null;
                    $mileage_display = is_numeric($current_mileage) ? number_format($current_mileage) . ' กม.' : 'ไม่ระบุ';
                    ?>
                    <tr class="even:bg-[#111827] odd:bg-[#1f2937] hover:bg-[#374151] transition-colors">
                        <td class="px-4 py-2"><?=htmlspecialchars($v['license_plate'])?></td>
                        <td class="px-4 py-2"><?= $v['type'] === 'vehicle' ? htmlspecialchars($v['make'] ?? '-') : '-' ?></td>
                        <td class="px-4 py-2"><?= $v['type'] === 'vehicle' ? htmlspecialchars($v['model'] ?? '-') : '-' ?></td>
                        <td class="px-4 py-2"><?= $v['type'] === 'vehicle' ? htmlspecialchars($v['year'] ?? '-') : '-' ?></td>
                        <td class="px-4 py-2 <?= $v['type'] === 'vehicle' && is_numeric($current_mileage) ? 'text-[#fbbf24] font-semibold' : '' ?>"><?= $v['type'] === 'vehicle' ? $mileage_display : '-' ?></td>
                        <!-- Department cell removed as field doesn't exist in database -->
                        <td class="px-4 py-2">
                            <?php
                            if ($v['status'] === 'active') echo 'ใช้งาน';
                            else if ($v['status'] === 'inactive') echo 'ไม่ใช้งาน';
                            else if ($v['status'] === 'maintenance') echo 'ซ่อมบำรุง';
                            else echo htmlspecialchars($v['status']);
                            ?>
                        </td>
                        <td class="px-4 py-2">
                            <?php if ($v['type'] === 'vehicle'): ?>
                                <button onclick='editVehicle(<?=json_encode($v)?>)' class="text-[#60a5fa] underline mr-2 hover:text-[#4ade80] transition">แก้ไข</button>
                                <a href="?delete=<?=$v['vehicle_id']?>" class="text-[#f87171] underline hover:text-[#ef4444] transition" onclick="return confirm('ยืนยันการลบ?')">ลบ</a>
                            <?php else: ?>
                                <button onclick='editTrailer({trailer_id: "<?=htmlspecialchars($v['vehicle_id'])?>", trailer_name: "<?=htmlspecialchars($v['license_plate'])?>", status: "<?=htmlspecialchars($v['status'])?>"})' class="text-[#60a5fa] underline mr-2 hover:text-[#4ade80] transition">แก้ไข</button>
                                <a href="?trailer_delete=<?=$v['vehicle_id']?>" class="text-[#f87171] underline hover:text-[#ef4444] transition" onclick="return confirm('ยืนยันการลบ?')">ลบ</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Vehicle Modal -->
        <div id="vehicleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-[#1f2937] rounded-xl p-8 m-4 max-w-2xl w-full max-h-[90vh] overflow-y-auto border border-[#374151]">
                <div class="flex justify-between items-center mb-6">
                    <h2 id="vehicleModalTitle" class="text-2xl font-bold text-[#f9fafb]">เพิ่มรถใหม่</h2>
                    <button onclick="closeVehicleModal()" class="text-[#9ca3af] hover:text-[#f87171] text-2xl transition-colors">✕</button>
                </div>
                
                <form id="vehicleForm" method="post" class="space-y-6">
                    <input type="hidden" name="edit_id" id="vehicle_edit_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ทะเบียนรถ</label>
                            <input type="text" name="license_plate" id="license_plate" placeholder="กข-1234" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">จังหวัด</label>
                            <input type="text" name="province" id="province" placeholder="กรุงเทพมหานคร" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ยี่ห้อ</label>
                            <select name="make" id="make" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" required>
                                <option value="">เลือกยี่ห้อ</option>
                                <option value="Isuzu">Isuzu</option>
                                <option value="Hino">Hino</option>
                                <option value="Mercedes-Benz">Mercedes-Benz</option>
                                <option value="Volvo">Volvo</option>
                                <option value="Scania">Scania</option>
                                <option value="Mitsubishi">Mitsubishi</option>
                                <option value="Toyota">Toyota</option>
                                <option value="Ford">Ford</option>
                                <option value="UD Trucks">UD Trucks</option>
                                <option value="MAN">MAN</option>
                                <option value="Other">อื่นๆ</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">รุ่น</label>
                            <input type="text" name="model" id="model" placeholder="FRR 90 N" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ปีที่ผลิต</label>
                            <input type="number" name="year" id="year" placeholder="2023" min="1990" max="2025" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ประเภทรถ</label>
                            <select name="vehicle_type" id="vehicle_type" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" required>
                                <option value="">เลือกประเภท</option>
                                <option value="รถบรรทุก">รถบรรทุก</option>
                                <option value="รถถังน้ำมัน">รถถังน้ำมัน</option>
                                <option value="รถพ่วง">รถพ่วง</option>
                                <option value="รถกระบะ">รถกระบะ</option>
                                <option value="4 ล้อ">4 ล้อ</option>
                                <option value="6 ล้อ">6 ล้อ</option>
                                <option value="10 ล้อ">10 ล้อ</option>
                                <option value="อื่นๆ">อื่น ๆ</option>
                            </select>
                            <input type="text" name="vehicle_type_other" id="vehicle_type_other" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] mt-2 hidden transition-colors" placeholder="ระบุประเภทรถ...">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">VIN / เลขตัวถัง</label>
                            <input type="text" name="vin" id="vin" placeholder="1HGBH41JXMN109186" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">สี</label>
                            <input type="text" name="color" id="color" placeholder="ขาว" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ชนิดน้ำมัน</label>
                            <select name="fuel_type" id="fuel_type" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                                <option value="">เลือกชนิดน้ำมัน</option>
                                <option value="ดีเซล">ดีเซล</option>
                                <option value="เบนซิน">เบนซิน</option>
                                <option value="แก๊ส">แก๊ส</option>
                                <option value="ไฟฟ้า">ไฟฟ้า</option>
                                <option value="ไฮบริด">ไฮบริด</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">เลขไมล์ปัจจุบัน</label>
                            <input type="number" name="current_mileage" id="current_mileage" placeholder="123456" min="0" step="1" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                            <p class="text-xs text-[#9ca3af] mt-1">หน่วย: กิโลเมตร</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#e5e7eb] mb-2">สถานะ</label>
                        <select name="status" id="status" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                            <option value="active">ใช้งาน</option>
                            <option value="maintenance">ซ่อมบำรุง</option>
                            <option value="inactive">ไม่ใช้งาน</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#e5e7eb] mb-2">หมายเหตุ</label>
                        <textarea name="vehicle_description" id="vehicle_description" rows="3" placeholder="ข้อมูลเพิ่มเติมเกี่ยวกับรถ..." class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors resize-none"></textarea>
                    </div>
                    
                    <div class="flex gap-4 pt-4">
                        <button type="submit" id="vehicleSubmitBtn" class="flex-1 bg-gradient-to-r from-[#4f46e5] to-[#7c3aed] text-white py-3 rounded-lg font-medium hover:shadow-lg hover:shadow-[#4f46e5]/20 hover:-translate-y-1 transition-all duration-300 focus:ring-2 focus:ring-[#4f46e5] focus:ring-offset-2 focus:ring-offset-[#1f2937]">
                            บันทึกข้อมูล
                        </button>
                        <button type="button" onclick="closeVehicleModal()" class="flex-1 bg-[#374151] text-[#e5e7eb] py-3 rounded-lg font-medium hover:bg-[#4b5563] transition-colors focus:ring-2 focus:ring-[#6b7280] focus:ring-offset-2 focus:ring-offset-[#1f2937]">
                            ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ส่วนจัดการรถพ่วง (ปุ่มเพิ่มรถพ่วง) ถูกนำออก -->

    <!-- Modal ฟอร์มรถพ่วง -->
    <div id="trailerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-8 max-w-xl w-full relative text-[#e0e0e0]">
            <button onclick="closeTrailerModal()" class="absolute top-2 right-2 text-2xl text-[#f87171] hover:text-[#ef4444] font-bold">&times;</button>
            <h2 id="trailerModalTitle" class="text-xl font-bold mb-4 text-[#60a5fa]">เพิ่มรถพ่วง</h2>
            <form id="trailerForm" method="post" class="space-y-5">
                <input type="hidden" name="trailer_form" value="1">
                <input type="hidden" name="trailer_edit_id" id="trailer_edit_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold mb-1">ทะเบียนพ่วง</label>
                        <input type="text" name="trailer_license_plate" id="trailer_license_plate" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">สถานะ</label>
                        <select name="trailer_status" id="trailer_status" class="w-full rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                            <option value="active">ใช้งาน</option>
                            <option value="inactive">ไม่ใช้งาน</option>
                            <option value="maintenance">ซ่อมบำรุง</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 text-center flex flex-col md:flex-row gap-4 justify-center">
                    <button type="submit" id="trailerSubmitBtn" class="transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-semibold px-6 py-2 rounded-lg shadow hover:scale-105">บันทึก</button>
                    <button type="button" onclick="closeTrailerModal()" class="transition-all duration-150 bg-[#f87171] hover:bg-[#ef4444] text-white font-semibold px-6 py-2 rounded-lg shadow hover:scale-105">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ป้องกันการส่งฟอร์มซ้ำ
let isSubmitting = false;

function openVehicleModal() {
    document.getElementById('vehicleModalTitle').innerText = 'เพิ่มรถใหม่';
    document.getElementById('vehicleForm').reset();
    document.getElementById('vehicle_edit_id').value = '';
    document.getElementById('vehicle_type_other').classList.add('hidden');
    document.getElementById('vehicle_type_other').required = false;
    document.getElementById('vehicleModal').classList.remove('hidden');
    document.getElementById('vehicleModal').style.display = 'flex';
    isSubmitting = false;
}
function closeVehicleModal() {
    document.getElementById('vehicleModal').classList.add('hidden');
    document.getElementById('vehicleModal').style.display = 'none';
    isSubmitting = false;
}
function editVehicle(data) {
    document.getElementById('vehicleModalTitle').innerText = 'แก้ไขข้อมูลรถ';
    document.getElementById('vehicle_edit_id').value = data.vehicle_id || '';
    document.getElementById('license_plate').value = data.license_plate || '';
    document.getElementById('province').value = data.province || '';
    document.getElementById('make').value = data.make || '';
    document.getElementById('model').value = data.model || '';
    document.getElementById('year').value = data.year || '';
    document.getElementById('vin').value = data.vin || data.chassis_number || '';
    document.getElementById('color').value = data.color || '';
    document.getElementById('vehicle_type').value = data.vehicle_type || data.category_name || '';
    document.getElementById('fuel_type').value = data.fuel_type || '';
    document.getElementById('current_mileage').value = data.current_mileage || data.last_odometer_reading || '';
    document.getElementById('vehicle_description').value = data.vehicle_description || '';
    document.getElementById('status').value = data.status || 'active';
    
    // Show/hide "อื่น ๆ" input based on vehicle type
    if (data.vehicle_type === 'อื่นๆ' || (data.category_name && !['รถบรรทุก', 'รถถังน้ำมัน', 'รถพ่วง', 'รถกระบะ', '4 ล้อ', '6 ล้อ', '10 ล้อ'].includes(data.category_name))) {
        document.getElementById('vehicle_type').value = 'อื่นๆ';
        document.getElementById('vehicle_type_other').classList.remove('hidden');
        document.getElementById('vehicle_type_other').value = data.vehicle_type || data.category_name || '';
        document.getElementById('vehicle_type_other').required = true;
    } else {
        document.getElementById('vehicle_type_other').classList.add('hidden');
        document.getElementById('vehicle_type_other').required = false;
        document.getElementById('vehicle_type_other').value = '';
    }
    
    document.getElementById('vehicleModal').classList.remove('hidden');
    document.getElementById('vehicleModal').style.display = 'flex';
    isSubmitting = false;
}

function openTrailerModal() {
    document.getElementById('trailerModalTitle').innerText = 'เพิ่มรถพ่วง';
    document.getElementById('trailerForm').reset();
    document.getElementById('trailer_edit_id').value = '';
    document.getElementById('trailerModal').classList.remove('hidden');
    isSubmitting = false;
}
function closeTrailerModal() {
    document.getElementById('trailerModal').classList.add('hidden');
    isSubmitting = false;
}
function editTrailer(data) {
    document.getElementById('trailerModalTitle').innerText = 'แก้ไขข้อมูลรถพ่วง';
    document.getElementById('trailer_edit_id').value = data.trailer_id || '';
    document.getElementById('trailer_license_plate').value = data.trailer_name || '';
    document.getElementById('trailer_status').value = data.status || 'active';
    document.getElementById('trailerModal').classList.remove('hidden');
    isSubmitting = false;
}

// ป้องกันการส่งฟอร์มซ้ำ
document.addEventListener('DOMContentLoaded', function() {
    const vehicleForm = document.getElementById('vehicleForm');
    const trailerForm = document.getElementById('trailerForm');
    const vehicleSubmitBtn = document.getElementById('vehicleSubmitBtn');
    const trailerSubmitBtn = document.getElementById('trailerSubmitBtn');
    const vehicleType = document.getElementById('vehicle_type');
    const vehicleTypeOther = document.getElementById('vehicle_type_other');
    
    // Handle vehicle type change
    if (vehicleType && vehicleTypeOther) {
        vehicleType.addEventListener('change', function() {
            if (this.value === 'อื่นๆ') {
                vehicleTypeOther.classList.remove('hidden');
                vehicleTypeOther.required = true;
            } else {
                vehicleTypeOther.classList.add('hidden');
                vehicleTypeOther.required = false;
                vehicleTypeOther.value = '';
            }
        });
    }
    
    if (vehicleForm) {
        vehicleForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            // ตรวจสอบข้อมูลพื้นฐาน
            const licensePlate = document.getElementById('license_plate').value.trim();
            const make = document.getElementById('make').value.trim();
            const model = document.getElementById('model').value.trim();
            const year = document.getElementById('year').value.trim();
            const vehicleTypeVal = document.getElementById('vehicle_type').value.trim();
            
            if (!licensePlate || !make || !model || !year || !vehicleTypeVal) {
                alert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
                e.preventDefault();
                return false;
            }
            
            // ตรวจสอบว่าถ้าเลือก "อื่นๆ" ต้องระบุประเภท
            if (vehicleTypeVal === 'อื่นๆ' && !vehicleTypeOther.value.trim()) {
                alert('กรุณาระบุประเภทรถ');
                vehicleTypeOther.focus();
                e.preventDefault();
                return false;
            }
            
            isSubmitting = true;
            vehicleSubmitBtn.disabled = true;
            vehicleSubmitBtn.textContent = 'กำลังบันทึก...';
        });
    }
    
    if (trailerForm) {
        trailerForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            const licensePlate = document.getElementById('trailer_license_plate').value.trim();
            if (!licensePlate) {
                alert('กรุณากรอกทะเบียนรถพ่วง');
                e.preventDefault();
                return false;
            }
            
            isSubmitting = true;
            trailerSubmitBtn.disabled = true;
            trailerSubmitBtn.textContent = 'กำลังบันทึก...';
        });
    }
});
</script>