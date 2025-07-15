<?php
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['manager', 'admin']);
$conn = connect_db();

// --- ส่วนจัดการรถทั้งหมด (รถหลักและรถพ่วง) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_plate = trim($_POST['license_plate']);
    $province = trim($_POST['province'] ?? '');
    $make = trim($_POST['make'] ?? '');
    // ถ้าเลือก "อื่นๆ" ให้ใช้ค่าจาก make_other
    if ($make === 'อื่นๆ') {
        $make = trim($_POST['make_other'] ?? '');
    }
    $model = trim($_POST['model'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $vin = trim($_POST['vin'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $vehicle_description = trim($_POST['vehicle_description'] ?? '');
    $status = trim($_POST['status']);
    
    // ตรวจสอบว่าเป็นรถพ่วงหรือไม่
    $is_trailer_form = isset($_POST['trailer_form']) && $_POST['trailer_form'] === '1';
    $vehicle_type = $is_trailer_form ? 'รถพ่วง' : ($_POST['vehicle_type'] === 'อื่นๆ' ? trim($_POST['vehicle_type_other']) : trim($_POST['vehicle_type']));
    $is_trailer = ($vehicle_type === 'รถพ่วง');
    
    // เติม "พ่วง" ข้างหน้าทะเบียนหากเป็นรถพ่วง
    if ($is_trailer && substr($license_plate, 0, 4) !== 'พ่วง') {
        $license_plate = 'พ่วง' . $license_plate;
    }
    
    // ตรวจสอบว่าทะเบียนไม่ว่าง
    if (empty(trim(str_replace('พ่วง', '', $license_plate)))) {
        echo "<script>alert('กรุณากรอกทะเบียนรถ');window.history.back();</script>";
        exit;
    }
    
    // Debug logging
    error_log("Processing vehicle - Type: " . $vehicle_type . ", License: " . $license_plate . ", Is Trailer: " . ($is_trailer ? 'Yes' : 'No'));
    
    // กำหนดค่าเริ่มต้นสำหรับรถพ่วง
    if ($is_trailer) {
        $fuel_type = ''; // รถพ่วงไม่มีน้ำมัน
        $current_mileage = 0; // รถพ่วงไม่มีเลขไมล์
        $vin = $vin ?: '-'; // ถ้าไม่มี VIN ให้ใส่ -
        $color = $color ?: '-'; // ถ้าไม่มีสีให้ใส่ -
    } else {
        $fuel_type = trim($_POST['fuel_type'] ?? '');
        $current_mileage = !empty($_POST['current_mileage']) ? intval($_POST['current_mileage']) : null;
    }
    
    // หา category_id
    $stmt_cat = $conn->prepare("SELECT category_id FROM vehicle_categories WHERE category_code = ? OR category_name = ?");
    if ($is_trailer) {
        $stmt_cat->execute(['TRAILER', 'รถพ่วง']);
    } else {
        $stmt_cat->execute([$vehicle_type, $vehicle_type]);
    }
    $category = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    $category_id = $category['category_id'] ?? null;
    
    if (!$category_id) {
        // สร้าง category ใหม่
        $stmt_cat_insert = $conn->prepare("INSERT INTO vehicle_categories (category_name, category_code, created_at, updated_at) VALUES (?, ?, GETDATE(), GETDATE())");
        if ($is_trailer) {
            $stmt_cat_insert->execute(['รถพ่วง', 'TRAILER']);
        } else {
            $category_code = strtoupper(str_replace(' ', '_', $vehicle_type));
            $stmt_cat_insert->execute([$vehicle_type, $category_code]);
        }
        $category_id = $conn->lastInsertId();
    }
    
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
    
    // ตรวจสอบว่าเป็น UPDATE หรือ INSERT
    $edit_id = $is_trailer_form ? ($_POST['trailer_edit_id'] ?? '') : ($_POST['edit_id'] ?? '');
    $is_update = !empty($edit_id);
    
    if ($is_update) {
        // UPDATE - ตรวจสอบทะเบียนซ้ำ
        $stmt_check = $conn->prepare("SELECT vehicle_id, license_plate FROM vehicles WHERE license_plate = ? AND vehicle_id != ? AND is_deleted = 0");
        $stmt_check->execute([$license_plate, $edit_id]);
        $existing_vehicle = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if ($existing_vehicle) {
            error_log("Duplicate license plate found during update: " . $existing_vehicle['license_plate'] . " (ID: " . $existing_vehicle['vehicle_id'] . ")");
            echo "<script>alert('ทะเบียนรถ \"" . htmlspecialchars($existing_vehicle['license_plate']) . "\" มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            exit;
        }
        
        $sql = "UPDATE vehicles SET license_plate=?, province=?, brand_id=?, model_name=?, year_manufactured=?, chassis_number=?, color=?, category_id=?, fuel_type_id=?, current_mileage=?, vehicle_description=?, status=?, updated_at=GETDATE() WHERE vehicle_id=?";
        $stmt = $conn->prepare($sql);
        try {
            $stmt->execute([
                $license_plate, $province, $brand_id, $model, $year, $vin, $color, $category_id, $fuel_type_id, $current_mileage, $vehicle_description, $status, $edit_id
            ]);
            error_log("Successfully updated vehicle: " . $license_plate . " (ID: " . $edit_id . ")");
        } catch (PDOException $e) {
            error_log("Error updating vehicle: " . $e->getMessage());
            if (strpos($e->getMessage(), 'UNIQUE KEY constraint') !== false) {
                echo "<script>alert('ทะเบียนรถนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            } else {
                echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "');window.history.back();</script>";
            }
            exit;
        }
    } else {
        // INSERT - ตรวจสอบทะเบียนซ้ำ
        $stmt_check = $conn->prepare("SELECT vehicle_id, license_plate FROM vehicles WHERE license_plate = ? AND is_deleted = 0");
        $stmt_check->execute([$license_plate]);
        $existing_vehicle = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if ($existing_vehicle) {
            error_log("Duplicate license plate found: " . $existing_vehicle['license_plate'] . " (ID: " . $existing_vehicle['vehicle_id'] . ")");
            echo "<script>alert('ทะเบียนรถ \"" . htmlspecialchars($existing_vehicle['license_plate']) . "\" มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            exit;
        }
        
        $sql = "INSERT INTO vehicles (license_plate, province, brand_id, model_name, year_manufactured, chassis_number, color, category_id, fuel_type_id, current_mileage, vehicle_description, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
        $stmt = $conn->prepare($sql);
        try {
            $stmt->execute([
                $license_plate, $province, $brand_id, $model, $year, $vin, $color, $category_id, $fuel_type_id, $current_mileage, $vehicle_description, $status
            ]);
            error_log("Successfully inserted vehicle: " . $license_plate . " (Type: " . $vehicle_type . ")");
        } catch (PDOException $e) {
            error_log("Error inserting vehicle: " . $e->getMessage());
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

// ลบข้อมูล (เปลี่ยน status เป็น inactive)
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("UPDATE vehicles SET status = 'inactive', updated_at = GETDATE() WHERE vehicle_id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: index.php");
    exit;
}

// ลบข้อมูลรถพ่วง (เปลี่ยน status เป็น inactive)
if (isset($_GET['trailer_delete'])) {
    $stmt = $conn->prepare("UPDATE vehicles SET status = 'inactive', updated_at = GETDATE() WHERE vehicle_id=?");
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

// ดึงข้อมูลประเภทรถจากฐานข้อมูล
$vehicle_categories = $conn->query("SELECT DISTINCT category_name FROM vehicle_categories WHERE category_name IS NOT NULL AND category_name <> '' ORDER BY category_name ASC")->fetchAll(PDO::FETCH_COLUMN);

// ดึงข้อมูลแบรนด์รถจากฐานข้อมูล
$vehicle_brands = $conn->query("SELECT DISTINCT brand_name FROM vehicle_brands WHERE brand_name IS NOT NULL AND brand_name <> '' ORDER BY brand_name ASC")->fetchAll(PDO::FETCH_COLUMN);

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
            $basic_vehicle_types = [];
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
                    // กำหนดเลขไมล์ที่จะแสดง - ทั้งรถหลักและรถพ่วง
                    $current_mileage = $v['current_mileage'] ?? $v['last_odometer_reading'] ?? null;
                    $mileage_display = is_numeric($current_mileage) ? number_format($current_mileage) . ' กม.' : 'ไม่ระบุ';
                    ?>
                    <tr class="even:bg-[#111827] odd:bg-[#1f2937] hover:bg-[#374151] transition-colors">
                        <td class="px-4 py-2"><?=htmlspecialchars($v['license_plate'])?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($v['make'] ?? '-') ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($v['model'] ?? '-') ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($v['year'] ?? '-') ?></td>
                        <td class="px-4 py-2 <?= is_numeric($current_mileage) ? 'text-[#fbbf24] font-semibold' : '' ?>"><?= $mileage_display ?></td>
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
                            <button onclick='editVehicle(<?=json_encode($v)?>)' class="text-[#60a5fa] underline mr-2 hover:text-[#4ade80] transition">แก้ไข</button>
                            <?php if ($v['type'] === 'vehicle'): ?>
                                <a href="?delete=<?=$v['vehicle_id']?>" class="text-[#f87171] underline hover:text-[#ef4444] transition" onclick="return confirm('ยืนยันการลบ?')">ลบ</a>
                            <?php else: ?>
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
                    <input type="hidden" name="trailer_edit_id" id="trailer_edit_id">
                    <input type="hidden" name="trailer_form" id="trailer_form_hidden">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">ทะเบียนรถ</label>
                            <div class="flex">
                                <span id="trailer_prefix" class="bg-[#374151] border border-r-0 border-[#374151] rounded-l-lg px-3 py-3 text-[#e0e0e0] hidden">พ่วง</span>
                                <input type="text" name="license_plate" id="license_plate" placeholder="กข-1234" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors" required>
                            </div>
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
                                <?php foreach ($vehicle_brands as $brand): ?>
                                    <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                                <?php endforeach; ?>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                            <input type="text" name="make_other" id="make_other" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] mt-2 hidden transition-colors" placeholder="ระบุยี่ห้อรถ...">
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
                                <?php foreach ($vehicle_categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                                <option value="อื่นๆ">อื่น ๆ</option>
                            </select>
                            <input type="text" name="vehicle_type_other" id="vehicle_type_other" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] mt-2 hidden transition-colors" placeholder="ระบุประเภทรถ...">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="vehicle_details">
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">VIN / เลขตัวถัง</label>
                            <input type="text" name="vin" id="vin" placeholder="1HGBH41JXMN109186" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#e5e7eb] mb-2">สี</label>
                            <input type="text" name="color" id="color" placeholder="ขาว" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] placeholder-[#9ca3af] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="fuel_mileage">
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
                    
                    <!-- Hidden input สำหรับรถพ่วง จะส่งค่า 0 เสมอ -->
                    <input type="hidden" name="trailer_mileage_override" id="trailer_mileage_override" value="0">
                    
                    <div>
                        <label class="block text-sm font-medium text-[#e5e7eb] mb-2">สถานะ</label>
                        <select name="status" id="status" class="w-full p-3 bg-[#111827] border border-[#374151] rounded-lg text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5] focus:border-[#4f46e5] transition-colors">
                            <option value="active">ใช้งาน</option>
                            <option value="maintenance">ซ่อมบำรุง</option>
                            <option value="inactive">ไม่ใช้งาน</option>
                        </select>
                    </div>
                    
                    <div id="vehicle_description_field">
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
</div>

<script>
// ป้องกันการส่งฟอร์มซ้ำ
let isSubmitting = false;

function openVehicleModal() {
    document.getElementById('vehicleModalTitle').innerText = 'เพิ่มรถใหม่';
    document.getElementById('vehicleForm').reset();
    document.getElementById('vehicle_edit_id').value = '';
    document.getElementById('trailer_edit_id').value = '';
    document.getElementById('trailer_form_hidden').value = '';
    
    // ซ่อน prefix รถพ่วง
    hideTrailerPrefix();
    
    // ซ่อนฟิลด์ "อื่นๆ"
    document.getElementById('vehicle_type_other').classList.add('hidden');
    document.getElementById('vehicle_type_other').required = false;
    document.getElementById('make_other').classList.add('hidden');
    document.getElementById('make_other').required = false;
    
    document.getElementById('vehicleModal').classList.remove('hidden');
    document.getElementById('vehicleModal').style.display = 'flex';
    isSubmitting = false;
}

function closeVehicleModal() {
    document.getElementById('vehicleModal').classList.add('hidden');
    document.getElementById('vehicleModal').style.display = 'none';
    isSubmitting = false;
}

function showTrailerPrefix() {
    const prefix = document.getElementById('trailer_prefix');
    const licensePlate = document.getElementById('license_plate');
    prefix.classList.remove('hidden');
    licensePlate.classList.remove('rounded-lg');
    licensePlate.classList.add('rounded-r-lg');
    licensePlate.placeholder = 'กข-1234';
}

function hideTrailerPrefix() {
    const prefix = document.getElementById('trailer_prefix');
    const licensePlate = document.getElementById('license_plate');
    prefix.classList.add('hidden');
    licensePlate.classList.remove('rounded-r-lg');
    licensePlate.classList.add('rounded-lg');
    licensePlate.placeholder = 'กข-1234';
}

function toggleFieldsForTrailer(isTrailer) {
    const fieldsToHide = ['vehicle_details', 'fuel_mileage'];  // ลบ vehicle_description_field ออก
    const makeField = document.getElementById('make');
    const modelField = document.getElementById('model');
    const yearField = document.getElementById('year');
    const currentMileageField = document.getElementById('current_mileage');
    
    fieldsToHide.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            if (isTrailer) {
                field.style.display = 'none';
            } else {
                field.style.display = 'block';
            }
        }
    });
    
    // จัดการ required fields - รถพ่วงสามารถมียี่ห้อ รุ่น ปี ได้
    if (isTrailer) {
        makeField.required = false;  // ไม่บังคับแต่ยังสามารถกรอกได้
        modelField.required = false;
        yearField.required = false;
        // ตั้งเลขไมล์เป็น 0 สำหรับรถพ่วงใหม่ (ซ่อนฟิลด์แต่ยังส่งค่า)
        if (!document.getElementById('trailer_edit_id').value) {
            currentMileageField.value = '0';
        }
        showTrailerPrefix();
    } else {
        makeField.required = true;
        modelField.required = true;
        yearField.required = true;
        hideTrailerPrefix();
    }
}

function editVehicle(data) {
    const isTrailer = (data.type === 'trailer' || data.vehicle_type === 'รถพ่วง' || data.category_name === 'รถพ่วง');
    
    if (isTrailer) {
        document.getElementById('vehicleModalTitle').innerText = 'แก้ไขข้อมูลรถพ่วง';
        document.getElementById('trailer_edit_id').value = data.vehicle_id || '';
        document.getElementById('trailer_form_hidden').value = '1';
        
        // ลบ "พ่วง" ออกจากทะเบียนเพื่อแสดงในฟิลด์
        let licensePlate = data.license_plate || '';
        if (licensePlate.startsWith('พ่วง')) {
            licensePlate = licensePlate.substring(4);
        }
        document.getElementById('license_plate').value = licensePlate;
        document.getElementById('province').value = data.province || '';
        
        // จัดการยี่ห้อรถ
        const makeSelect = document.getElementById('make');
        const makeOther = document.getElementById('make_other');
        const makeValue = data.make || '';
        const makeOption = Array.from(makeSelect.options).find(option => option.value === makeValue);
        
        if (makeOption && makeValue !== 'อื่นๆ') {
            makeSelect.value = makeValue;
            makeOther.classList.add('hidden');
            makeOther.required = false;
        } else if (makeValue) {
            makeSelect.value = 'อื่นๆ';
            makeOther.classList.remove('hidden');
            makeOther.value = makeValue;
            makeOther.required = true;
        }
        
        document.getElementById('model').value = data.model || '';
        document.getElementById('year').value = data.year || '';
        document.getElementById('vehicle_type').value = 'รถพ่วง';
        document.getElementById('status').value = data.status || 'active';
        document.getElementById('vehicle_description').value = data.vehicle_description || '';
        
        toggleFieldsForTrailer(true);
    } else {
        document.getElementById('vehicleModalTitle').innerText = 'แก้ไขข้อมูลรถ';
        document.getElementById('vehicle_edit_id').value = data.vehicle_id || '';
        document.getElementById('trailer_form_hidden').value = '';
        document.getElementById('license_plate').value = data.license_plate || '';
        document.getElementById('province').value = data.province || '';
        
        // จัดการยี่ห้อรถ
        const makeSelect = document.getElementById('make');
        const makeOther = document.getElementById('make_other');
        const makeValue = data.make || '';
        const makeOption = Array.from(makeSelect.options).find(option => option.value === makeValue);
        
        if (makeOption && makeValue !== 'อื่นๆ') {
            makeSelect.value = makeValue;
            makeOther.classList.add('hidden');
            makeOther.required = false;
        } else if (makeValue) {
            makeSelect.value = 'อื่นๆ';
            makeOther.classList.remove('hidden');
            makeOther.value = makeValue;
            makeOther.required = true;
        }
        
        document.getElementById('model').value = data.model || '';
        document.getElementById('year').value = data.year || '';
        document.getElementById('vin').value = data.vin || data.chassis_number || '';
        document.getElementById('color').value = data.color || '';
        
        // จัดการประเภทรถ
        const vehicleTypeSelect = document.getElementById('vehicle_type');
        const vehicleTypeOther = document.getElementById('vehicle_type_other');
        const vehicleTypeValue = data.vehicle_type || data.category_name || '';
        const vehicleTypeOption = Array.from(vehicleTypeSelect.options).find(option => option.value === vehicleTypeValue);
        
        if (vehicleTypeOption && vehicleTypeValue !== 'อื่นๆ') {
            vehicleTypeSelect.value = vehicleTypeValue;
            vehicleTypeOther.classList.add('hidden');
            vehicleTypeOther.required = false;
        } else if (vehicleTypeValue) {
            vehicleTypeSelect.value = 'อื่นๆ';
            vehicleTypeOther.classList.remove('hidden');
            vehicleTypeOther.value = vehicleTypeValue;
            vehicleTypeOther.required = true;
        }
        
        document.getElementById('fuel_type').value = data.fuel_type || '';
        document.getElementById('current_mileage').value = data.current_mileage || data.last_odometer_reading || '';
        document.getElementById('vehicle_description').value = data.vehicle_description || '';
        document.getElementById('status').value = data.status || 'active';
        
        toggleFieldsForTrailer(false);
    }
    
    document.getElementById('vehicleModal').classList.remove('hidden');
    document.getElementById('vehicleModal').style.display = 'flex';
    isSubmitting = false;
}

// ป้องกันการส่งฟอร์มซ้ำ
document.addEventListener('DOMContentLoaded', function() {
    const vehicleForm = document.getElementById('vehicleForm');
    const vehicleSubmitBtn = document.getElementById('vehicleSubmitBtn');
    const vehicleType = document.getElementById('vehicle_type');
    const vehicleTypeOther = document.getElementById('vehicle_type_other');
    
    // Handle vehicle type change
    if (vehicleType && vehicleTypeOther) {
        vehicleType.addEventListener('change', function() {
            const isTrailer = (this.value === 'รถพ่วง');
            
            // แสดง/ซ่อนฟิลด์ตามประเภทรถ
            toggleFieldsForTrailer(isTrailer);
            
            // ตั้งเลขไมล์เป็น 0 สำหรับรถพ่วงใหม่
            if (isTrailer && !document.getElementById('trailer_edit_id').value) {
                document.getElementById('current_mileage').value = '0';
            }
            
            // จัดการฟิลด์ "อื่นๆ" เฉพาะสำหรับรถที่ไม่ใช่รถพ่วง
            if (!isTrailer && this.value === 'อื่นๆ') {
                vehicleTypeOther.classList.remove('hidden');
                vehicleTypeOther.required = true;
            } else {
                vehicleTypeOther.classList.add('hidden');
                vehicleTypeOther.required = false;
                vehicleTypeOther.value = '';
            }
        });
    }
    
    // Handle make change
    const makeSelect = document.getElementById('make');
    const makeOther = document.getElementById('make_other');
    if (makeSelect && makeOther) {
        makeSelect.addEventListener('change', function() {
            if (this.value === 'อื่นๆ') {
                makeOther.classList.remove('hidden');
                makeOther.required = true;
            } else {
                makeOther.classList.add('hidden');
                makeOther.required = false;
                makeOther.value = '';
            }
        });
    }
    
    if (vehicleForm) {
        vehicleForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            const vehicleTypeVal = document.getElementById('vehicle_type').value.trim();
            const isTrailer = (vehicleTypeVal === 'รถพ่วง');
            
            // ตรวจสอบข้อมูลพื้นฐาน
            const licensePlate = document.getElementById('license_plate').value.trim();
            
            if (!licensePlate) {
                alert('กรุณากรอกทะเบียนรถ');
                e.preventDefault();
                return false;
            }
            
            if (!vehicleTypeVal) {
                alert('กรุณาเลือกประเภทรถ');
                e.preventDefault();
                return false;
            }
                 // ตรวจสอบข้อมูลเพิ่มเติมสำหรับรถที่ไม่ใช่รถพ่วง
        if (!isTrailer) {
            const make = document.getElementById('make').value.trim();
            const makeOther = document.getElementById('make_other').value.trim();
            const model = document.getElementById('model').value.trim();
            const year = document.getElementById('year').value.trim();
            
            // ตรวจสอบยี่ห้อ
            if (!make) {
                alert('กรุณาเลือกยี่ห้อรถ');
                e.preventDefault();
                return false;
            }
            
            if (make === 'อื่นๆ' && !makeOther) {
                alert('กรุณาระบุยี่ห้อรถ');
                document.getElementById('make_other').focus();
                e.preventDefault();
                return false;
            }
            
            if (!model || !year) {
                alert('กรุณากรอกข้อมูลรุ่น และปีที่ผลิต');
                e.preventDefault();
                return false;
            }
        }
        
        // ตรวจสอบว่าถ้าเลือก "อื่นๆ" ต้องระบุประเภท (เฉพาะรถที่ไม่ใช่รถพ่วง)
        if (!isTrailer && vehicleTypeVal === 'อื่นๆ' && !vehicleTypeOther.value.trim()) {
            alert('กรุณาระบุประเภทรถ');
            vehicleTypeOther.focus();
            e.preventDefault();
            return false;
        }
            
            // ตั้งค่า trailer_form หากเป็นรถพ่วง
            if (isTrailer) {
                document.getElementById('trailer_form_hidden').value = '1';
                // ตั้งเลขไมล์เป็น 0 สำหรับรถพ่วงใหม่
                if (!document.getElementById('trailer_edit_id').value) {
                    document.getElementById('current_mileage').value = '0';
                }
            }
            
            isSubmitting = true;
            vehicleSubmitBtn.disabled = true;
            vehicleSubmitBtn.textContent = 'กำลังบันทึก...';
        });
    }
});
</script>