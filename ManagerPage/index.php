<?php
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['manager', 'admin']);
$conn = connect_db();

// --- ส่วนจัดการรถพ่วง (Trailers) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trailer_form'])) {
    $license_plate = $_POST['trailer_license_plate'];
    $status = $_POST['trailer_status'];
    if (isset($_POST['trailer_edit_id']) && $_POST['trailer_edit_id'] !== '') {
        // UPDATE - ตรวจสอบว่าทะเบียนซ้ำกับรถพ่วงคันอื่นหรือไม่
        $stmt_check = $conn->prepare("SELECT trailer_id FROM trailers WHERE trailer_name = ? AND trailer_id != ?");
        $stmt_check->execute([$license_plate, $_POST['trailer_edit_id']]);
        if ($stmt_check->rowCount() > 0) {
            echo "<script>alert('ทะเบียนรถพ่วงนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE trailers SET trailer_name=?, status=? WHERE trailer_id=?");
        try {
            $stmt->execute([$license_plate, $status, $_POST['trailer_edit_id']]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE KEY constraint') !== false) {
                echo "<script>alert('ทะเบียนรถพ่วงนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            } else {
                echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "');window.history.back();</script>";
            }
            exit;
        }
    } else {
        // INSERT - ตรวจสอบทะเบียนซ้ำ
        $stmt_check = $conn->prepare("SELECT trailer_id FROM trailers WHERE trailer_name = ?");
        $stmt_check->execute([$license_plate]);
        if ($stmt_check->rowCount() > 0) {
            echo "<script>alert('ทะเบียนรถพ่วงนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO trailers (trailer_name, status) VALUES (?, ?)");
        try {
            $stmt->execute([$license_plate, $status]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE KEY constraint') !== false) {
                echo "<script>alert('ทะเบียนรถพ่วงนี้มีอยู่ในระบบแล้ว กรุณาใช้ทะเบียนอื่น');window.history.back();</script>";
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
    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $year = trim($_POST['year']);
    $vin = trim($_POST['vin']);
    $engine_number = trim($_POST['engine_number']);
    $color = trim($_POST['color']);
    $current_mileage = trim($_POST['current_mileage']);
    $acquisition_date = trim($_POST['acquisition_date']);
    $asset_tag = trim($_POST['asset_tag']);
    $vehicle_type = $_POST['vehicle_type'] === 'อื่นๆ' ? trim($_POST['vehicle_type_other']) : trim($_POST['vehicle_type']);
    $fuel_type = trim($_POST['fuel_type']);
    $department = trim($_POST['department']);
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
        
        $sql = "UPDATE vehicles SET license_plate=?, make=?, model=?, year=?, vin=?, engine_number=?, color=?, current_mileage=?, acquisition_date=?, asset_tag=?, vehicle_type=?, fuel_type=?, department=?, status=?, last_updated_by_employee_id=?, last_updated_at=?
                WHERE vehicle_id=?";
        $stmt = $conn->prepare($sql);
        try {
            $stmt->execute([
                $license_plate, $make, $model, $year, $vin, $engine_number, $color, $current_mileage, $acquisition_date, $asset_tag, $vehicle_type, $fuel_type, $department, $status, $last_updated_by_employee_id, $last_updated_at, $_POST['edit_id']
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
        
        $sql = "INSERT INTO vehicles (license_plate, make, model, year, vin, engine_number, color, current_mileage, acquisition_date, asset_tag, vehicle_type, fuel_type, department, status, last_updated_by_employee_id, last_updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        try {
            $stmt->execute([
                $license_plate, $make, $model, $year, $vin, $engine_number, $color, $current_mileage, $acquisition_date, $asset_tag, $vehicle_type, $fuel_type, $department, $status, $last_updated_by_employee_id, $last_updated_at
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

// ลบข้อมูลรถพ่วง
if (isset($_GET['trailer_delete'])) {
    $stmt = $conn->prepare("DELETE FROM trailers WHERE trailer_id=?");
    $stmt->execute([$_GET['trailer_delete']]);
    header("Location: index.php");
    exit;
}

// ดึงข้อมูลรถหลักและรถพ่วงรวมกัน
$vehicles = [];
$stmt = $conn->query("SELECT *, 'vehicle' AS type FROM vehicles ORDER BY vehicle_id DESC");
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->query("SELECT trailer_id AS vehicle_id, trailer_name AS license_plate, '' AS make, '' AS model, '' AS year, '' AS vin, '' AS engine_number, '' AS color, '' AS current_mileage, '' AS acquisition_date, '' AS asset_tag, '' AS vehicle_type, '' AS fuel_type, status, 'trailer' AS type FROM trailers ORDER BY trailer_id DESC");
$trailers_as_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$vehicles = array_merge($vehicles, $trailers_as_vehicles);
$vehicles = array_reverse($vehicles); // ให้รถพ่วงใหม่อยู่บนสุดเหมือนรถหลัก

// ดึงข้อมูลรถที่จะแก้ไข (ถ้ามี)
$edit_vehicle = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_id=?");
    $stmt->execute([$_GET['edit']]);
    $edit_vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
}

$trailers = $conn->query("SELECT * FROM trailers ORDER BY trailer_id DESC")->fetchAll(PDO::FETCH_ASSOC);
$edit_trailer = null;
if (isset($_GET['trailer_edit'])) {
    $stmt = $conn->prepare("SELECT * FROM trailers WHERE trailer_id=?");
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
            // ดึงรายการประเภทรถที่มีในฐานข้อมูล (vehicles) ที่ไม่ใช่ประเภทพื้นฐาน
            $db_vehicle_types = $conn->query("SELECT DISTINCT vehicle_type FROM vehicles WHERE vehicle_type IS NOT NULL AND vehicle_type <> '' ORDER BY vehicle_type ASC")->fetchAll(PDO::FETCH_COLUMN);
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
                        <th class="px-4 py-3 text-left font-bold">สังกัด</th>
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
                    <tr class="even:bg-[#111827] odd:bg-[#1f2937] hover:bg-[#374151] transition-colors">
                        <td class="px-4 py-2"><?=htmlspecialchars($v['license_plate'])?></td>
                        <td class="px-4 py-2"><?= $v['type'] === 'vehicle' ? htmlspecialchars($v['make']) : '-' ?></td>
                        <td class="px-4 py-2"><?= $v['type'] === 'vehicle' ? htmlspecialchars($v['model']) : '-' ?></td>
                        <td class="px-4 py-2"><?= $v['type'] === 'vehicle' ? htmlspecialchars($v['year']) : '-' ?></td>
                        <td class="px-4 py-2"><?= $v['type'] === 'vehicle' ? htmlspecialchars($v['current_mileage']) : '-' ?></td>
                        <td class="px-4 py-2"><?= $v['type'] === 'vehicle' ? htmlspecialchars($v['department']) : '-' ?></td>
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

        <!-- Modal ฟอร์มรถหลัก -->
        <div id="vehicleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 overflow-y-auto max-h-screen hidden">
            <div class="bg-[#1f2937] rounded-2xl shadow-xl p-4 sm:p-8 max-w-xs sm:max-w-lg md:max-w-2xl w-full relative text-[#e0e0e0] mt-4 sm:mt-0">
                <button onclick="closeVehicleModal()" class="absolute top-2 right-2 text-2xl text-[#f87171] hover:text-[#ef4444] font-bold">&times;</button>
                <h2 id="vehicleModalTitle" class="text-xl font-bold mb-4 text-[#60a5fa]">เพิ่มรถ</h2>
                <form id="vehicleForm" method="post" class="space-y-5">
                    <input type="hidden" name="edit_id" id="vehicle_edit_id">
                    <!-- ฟอร์มรถหลัก -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:space-y-0 space-y-4">
                        <div>
                            <label class="block font-semibold mb-2">ทะเบียน</label>
                            <input type="text" name="license_plate" id="license_plate" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">ยี่ห้อ</label>
                            <input type="text" name="make" id="make" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">รุ่น</label>
                            <input type="text" name="model" id="model" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">ปี</label>
                            <input type="number" name="year" id="year" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">VIN</label>
                            <input type="text" name="vin" id="vin" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">เลขเครื่องยนต์</label>
                            <input type="text" name="engine_number" id="engine_number" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">สี</label>
                            <input type="text" name="color" id="color" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">เลขไมล์ปัจจุบัน</label>
                            <input type="number" name="current_mileage" id="current_mileage" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">วันที่รับรถ</label>
                            <input type="date" name="acquisition_date" id="acquisition_date" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">Asset Tag</label>
                            <input type="text" name="asset_tag" id="asset_tag" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">ประเภทรถ</label>
                            <?php
                            // ประเภทรถพื้นฐาน
                            $basic_vehicle_types = ['4 ล้อ', '6 ล้อ', '10 ล้อ', 'พ่วง'];
                            // ดึงรายการประเภทรถที่มีในฐานข้อมูล (Vehicles) ที่ไม่ใช่ประเภทพื้นฐาน
                            $db_vehicle_types = $conn->query("SELECT DISTINCT vehicle_type FROM vehicles WHERE vehicle_type IS NOT NULL AND vehicle_type <> '' ORDER BY vehicle_type ASC")->fetchAll(PDO::FETCH_COLUMN);
                            $additional_types = array_diff($db_vehicle_types, $basic_vehicle_types);
                            $vehicle_types_form = array_merge($basic_vehicle_types, $additional_types);
                            ?>
                            <select name="vehicle_type" id="vehicle_type" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]" required>
                                <?php foreach ($vehicle_types_form as $vt): ?>
                                    <option value="<?=htmlspecialchars($vt)?>"><?=htmlspecialchars($vt)?></option>
                                <?php endforeach; ?>
                                <option value="อื่นๆ">อื่น ๆ</option>
                            </select>
                            <input type="text" name="vehicle_type_other" id="vehicle_type_other" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] mt-2 hidden" placeholder="ระบุประเภทรถ...">
                        </div>
<script>
// Auto-prefix license plate with "พ่วง" if vehicle_type is "รถพ่วง"
// Show input for "อื่น ๆ" type
document.addEventListener('DOMContentLoaded', function() {
    var vehicleType = document.getElementById('vehicle_type');
    var licensePlate = document.getElementById('license_plate');
    var vehicleTypeOther = document.getElementById('vehicle_type_other');
    function updateLicensePlatePrefix() {
        if (vehicleType.value === 'รถพ่วง') {
            if (!licensePlate.value.startsWith('พ่วง')) {
                licensePlate.value = 'พ่วง' + licensePlate.value.replace(/^พ่วง/, '');
            }
            licensePlate.readOnly = false;
            licensePlate.addEventListener('input', enforcePrefix);
        } else {
            // Remove prefix if present
            if (licensePlate.value.startsWith('พ่วง')) {
                licensePlate.value = licensePlate.value.replace(/^พ่วง/, '');
            }
            licensePlate.readOnly = false;
            licensePlate.removeEventListener('input', enforcePrefix);
        }
        // Show/hide "อื่น ๆ" input
        if (vehicleType.value === 'อื่นๆ') {
            vehicleTypeOther.classList.remove('hidden');
            vehicleTypeOther.required = true;
        } else {
            vehicleTypeOther.classList.add('hidden');
            vehicleTypeOther.required = false;
            vehicleTypeOther.value = '';
        }
    }
    function enforcePrefix() {
        if (!licensePlate.value.startsWith('พ่วง')) {
            licensePlate.value = 'พ่วง' + licensePlate.value.replace(/^พ่วง/, '');
        }
    }
    vehicleType.addEventListener('change', updateLicensePlatePrefix);
    // On modal open, set prefix if needed
    updateLicensePlatePrefix();
});
</script>
                        <div>
                            <label class="block font-semibold mb-2">ชนิดน้ำมัน</label>
                            <input type="text" name="fuel_type" id="fuel_type" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">สังกัด</label>
                            <input type="text" name="department" id="department" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">สถานะ</label>
                            <select name="status" id="status" class="w-full text-base rounded-lg px-3 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                                <option value="active">ใช้งาน</option>
                                <option value="inactive">ไม่ใช้งาน</option>
                                <option value="maintenance">ซ่อมบำรุง</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 text-center flex flex-col md:flex-row gap-4 justify-center">
                        <button type="submit" id="vehicleSubmitBtn" class="transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 w-full md:w-auto">บันทึก</button>
                        <button type="button" onclick="closeVehicleModal()" class="transition-all duration-150 bg-[#f87171] hover:bg-[#ef4444] text-white font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 w-full md:w-auto">ยกเลิก</button>
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
    document.getElementById('vehicleModalTitle').innerText = 'เพิ่มรถ';
    document.getElementById('vehicleForm').reset();
    document.getElementById('vehicle_edit_id').value = '';
    document.getElementById('vehicleModal').classList.remove('hidden');
    isSubmitting = false;
}
function closeVehicleModal() {
    document.getElementById('vehicleModal').classList.add('hidden');
    isSubmitting = false;
}
function editVehicle(data) {
    document.getElementById('vehicleModalTitle').innerText = 'แก้ไขข้อมูลรถ';
    document.getElementById('vehicle_edit_id').value = data.vehicle_id || '';
    document.getElementById('license_plate').value = data.license_plate || '';
    document.getElementById('make').value = data.make || '';
    document.getElementById('model').value = data.model || '';
    document.getElementById('year').value = data.year || '';
    document.getElementById('vin').value = data.vin || '';
    document.getElementById('engine_number').value = data.engine_number || '';
    document.getElementById('color').value = data.color || '';
    document.getElementById('current_mileage').value = data.current_mileage || '';
    document.getElementById('acquisition_date').value = data.acquisition_date || '';
    document.getElementById('asset_tag').value = data.asset_tag || '';
    document.getElementById('vehicle_type').value = data.vehicle_type || '';
    document.getElementById('fuel_type').value = data.fuel_type || '';
    document.getElementById('department').value = data.department || '';
    document.getElementById('status').value = data.status || 'active';
    document.getElementById('vehicleModal').classList.remove('hidden');
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
            
            if (!licensePlate || !make || !model || !year) {
                alert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
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