<?php
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['manager', 'admin']);
$conn = connect_db();

// ดึงรายการ FuelRecords ที่รออนุมัติ (status = 'pending')

// ดึงข้อมูลรถหลักและรถพ่วง (ถ้ามี)
$sql = "SELECT fr.*, v.license_plate, v.vehicle_id,
       t.license_plate AS trailer_license_plate, t.vehicle_id AS trailer_vehicle_id
        FROM FuelRecords fr
        JOIN Vehicles v ON fr.vehicle_id = v.vehicle_id
        LEFT JOIN Vehicles t ON fr.trailer_vehicle_id = t.vehicle_id
        WHERE fr.status = 'pending' ORDER BY fr.fuel_date DESC";
$stmt = $conn->query($sql);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงประเภทเชื้อเพลิงที่เคยใช้ (ไม่ซ้ำ)
$stmt = $conn->query("SELECT DISTINCT fuel_type FROM FuelRecords WHERE fuel_type IS NOT NULL AND fuel_type <> ''");
$fuel_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ดึงไฟล์แนบของแต่ละรายการ
$attachments = [];
if ($orders) {
    $ids = array_column($orders, 'fuel_record_id');
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $stmt2 = $conn->prepare("SELECT * FROM FuelReceiptAttachments WHERE fuel_record_id IN ($in)");
    $stmt2->execute($ids);
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $att) {
        $attachments[$att['fuel_record_id']][] = $att;
    }
}

// เมื่อผู้ตรวจสอบกดยืนยันหรือยกเลิก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fuel_record_id'])) {
    $fuel_record_id = $_POST['fuel_record_id'];
    $notes = $_POST['notes'];

    // --- ตรวจสอบเลขไมล์ถอยหลังและล่วงหน้า ---
    $mileage = isset($_POST['mileage_at_fuel']) ? $_POST['mileage_at_fuel'] : null;
    $fuel_date = $_POST['fuel_date'];
    // Convert fuel_date to SQL Server format if needed
    if (strpos($fuel_date, 'T') !== false) {
        $fuel_date_check = str_replace('T', ' ', $fuel_date);
    } else {
        $fuel_date_check = $fuel_date;
    }

    // ดึงเลขไมล์ล่าสุดก่อนวันที่ในใบเสร็จที่กำลังอนุมัติ (ย้อนหลัง)
    $stmt_before = $conn->prepare("SELECT TOP 1 mileage_at_fuel FROM FuelRecords WHERE vehicle_id = (SELECT vehicle_id FROM FuelRecords WHERE fuel_record_id = ?) AND fuel_record_id <> ? AND mileage_at_fuel IS NOT NULL AND fuel_date < ? ORDER BY fuel_date DESC");
    $stmt_before->execute([$fuel_record_id, $fuel_record_id, $fuel_date_check]);
    $min_mileage = $stmt_before->fetchColumn();
    
    // ดึงเลขไมล์แรกหลังวันที่ในใบเสร็จที่กำลังอนุมัติ (ถัดไป)
    $stmt_after = $conn->prepare("SELECT TOP 1 mileage_at_fuel FROM FuelRecords WHERE vehicle_id = (SELECT vehicle_id FROM FuelRecords WHERE fuel_record_id = ?) AND fuel_record_id <> ? AND mileage_at_fuel IS NOT NULL AND fuel_date > ? ORDER BY fuel_date ASC");
    $stmt_after->execute([$fuel_record_id, $fuel_record_id, $fuel_date_check]);
    $max_mileage = $stmt_after->fetchColumn();
    
    // ตรวจสอบเลขไมล์ต่ำสุด (ย้อนหลัง)
    if ($min_mileage !== false && is_numeric($min_mileage) && is_numeric($mileage) && $mileage < $min_mileage) {
        echo "<script>alert('เลขไมล์ขณะเติมต้องมากกว่าหรือเท่ากับเลขไมล์ครั้งก่อน (" . htmlspecialchars($min_mileage) . ") ณ วันที่ " . htmlspecialchars($fuel_date_check) . "');window.history.back();</script>";
        exit;
    }
    
    // ตรวจสอบเลขไมล์สูงสุด (ถัดไป)
    if ($max_mileage !== false && is_numeric($max_mileage) && is_numeric($mileage) && $mileage > $max_mileage) {
        echo "<script>alert('เลขไมล์ขณะเติมต้องน้อยกว่าหรือเท่ากับเลขไมล์ครั้งถัดไป (" . htmlspecialchars($max_mileage) . ") ณ วันที่ " . htmlspecialchars($fuel_date_check) . "');window.history.back();</script>";
        exit;
    }

    if (isset($_POST['reject'])) {
        // กดยกเลิกรายการ: ตรวจสอบว่าต้องมีหมายเหตุ
        if (empty($notes) || trim($notes) === '') {
            echo "<script>alert('กรุณาใส่หมายเหตุเหตุผลในการยกเลิกรายการ');window.history.back();</script>";
            exit;
        }

        // อัปเดตสถานะเป็น rejected พร้อมหมายเหตุแทนการลบ
        $sql = "UPDATE FuelRecords SET status='rejected', notes=? WHERE fuel_record_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$notes, $fuel_record_id]);
        header("Location: orderlist.php");
        exit;
    } else {
        // กดยืนยันรายการ
        $fuel_date = $_POST['fuel_date'];
        $fuel_type = ($_POST['fuel_type'] === 'other') ? $_POST['fuel_type_other'] : $_POST['fuel_type'];
        $cost_per_liter = $_POST['cost_per_liter'];
        $total_cost = $_POST['total_cost'];
        $volume_liters = ($cost_per_liter > 0) ? ($total_cost / $cost_per_liter) : null;
        $mileage = $_POST['mileage_at_fuel'];
        // Convert fuel_date to SQL Server format if needed
        if (strpos($fuel_date, 'T') !== false) {
            $fuel_date = str_replace('T', ' ', $fuel_date);
        }

        // --- Begin: คำนวณระยะวิ่งรถหลัก + รถพ่วง ---
        // 1. ดึงเลขไมล์ล่าสุดของรถหลัก (ก่อนบันทึกนี้และก่อนวันที่ในใบเสร็จ)
        $stmt_last = $conn->prepare("SELECT TOP 1 mileage_at_fuel FROM FuelRecords WHERE vehicle_id = (SELECT vehicle_id FROM FuelRecords WHERE fuel_record_id = ?) AND fuel_record_id <> ? AND mileage_at_fuel IS NOT NULL AND fuel_date <= ? ORDER BY fuel_date DESC");
        $stmt_last->execute([$fuel_record_id, $fuel_record_id, $fuel_date]);
        $last_main_mileage = $stmt_last->fetchColumn();
        $main_distance = null;
        if ($last_main_mileage !== false && is_numeric($last_main_mileage) && is_numeric($mileage)) {
            $main_distance = $mileage - $last_main_mileage;
        }

        // 2. จัดการเลขไมล์รถพ่วง (ถ้ามี)
        $stmt_trailer_id = $conn->prepare("SELECT trailer_vehicle_id FROM FuelRecords WHERE fuel_record_id = ?");
        $stmt_trailer_id->execute([$fuel_record_id]);
        $trailer_vehicle_id = $stmt_trailer_id->fetchColumn();
        $trailer_distance = 0;
        $trailer_new_mileage = null;

        if ($trailer_vehicle_id && $main_distance !== null) {
            // ดึงเลขไมล์ปัจจุบันของรถพ่วงจาก Vehicles table
            $stmt_trailer = $conn->prepare("SELECT current_mileage FROM Vehicles WHERE vehicle_id = ?");
            $stmt_trailer->execute([$trailer_vehicle_id]);
            $trailer_current_mileage = $stmt_trailer->fetchColumn();

            if ($trailer_current_mileage !== false && is_numeric($trailer_current_mileage)) {
                // ระยะวิ่งรถพ่วง = ระยะวิ่งรถหลัก (เพราะขับไปพร้อมกัน)
                $trailer_distance = $main_distance;
                // เลขไมล์ใหม่ของรถพ่วง = เลขไมล์ปัจจุบันของรถพ่วง + ระยะวิ่งรถหลัก
                $trailer_new_mileage = $trailer_current_mileage + $main_distance;
            }
        }

        // 3. รวมระยะวิ่ง (ไม่ต้องบวกกันเพราะขับไปพร้อมกัน)
        $total_distance = ($main_distance !== null ? $main_distance : 0);
        // --- End: คำนวณระยะวิ่งรถหลัก + รถพ่วง ---

        // สามารถนำ $total_distance ไปใช้งานต่อ เช่น บันทึกลง notes หรือฟิลด์ใหม่
        // ตัวอย่าง: เพิ่มลง notes
        if ($trailer_vehicle_id) {
            $notes = $notes . "\n[ระยะวิ่งรถหลัก: " . $main_distance . " กม., ระยะวิ่งรถพ่วง: " . $trailer_distance . " กม.]";
        } else {
            $notes = $notes . "\n[ระยะวิ่งรถหลัก: " . $total_distance . " กม.]";
        }

        // อัปเดตเลขไมล์รถพ่วง (ถ้ามีและมีระยะวิ่ง)
        if ($trailer_vehicle_id && $trailer_new_mileage !== null) {
            // อัปเดต Vehicles (รถพ่วง) - อัปเดตเฉพาะเลขไมล์ปัจจุบันในตาราง Vehicles
            $stmt_trailer_vehicle = $conn->prepare("UPDATE Vehicles SET current_mileage = ? WHERE vehicle_id = ?");
            $stmt_trailer_vehicle->execute([$trailer_new_mileage, $trailer_vehicle_id]);
        }

        // อัปเดต Vehicles (รถหลัก)
        $stmt_main_vehicle = $conn->prepare("UPDATE Vehicles SET current_mileage = ? WHERE vehicle_id = (SELECT vehicle_id FROM FuelRecords WHERE fuel_record_id = ?)");
        $stmt_main_vehicle->execute([$mileage, $fuel_record_id]);

        // อัปเดต FuelRecords พร้อมกับ trailer_mileage_at_fuel
        $sql = "UPDATE FuelRecords SET fuel_date=?, fuel_type=?, cost_per_liter=?, volume_liters=?, total_cost=?, mileage_at_fuel=?, trailer_mileage_at_fuel=?, notes=?, status='approved' WHERE fuel_record_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$fuel_date, $fuel_type, $cost_per_liter, $volume_liters, $total_cost, $mileage, $trailer_new_mileage, $notes, $fuel_record_id]);
        header("Location: orderlist.php");
        exit;
    }
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
    <div class="container mx-auto py-6 px-2 sm:px-0">
        <h1 class="text-2xl font-bold mb-6 text-[#60a5fa] text-center">รายการเติมน้ำมันที่รออนุมัติ</h1>
        <?php if (empty($orders)): ?>
            <div class="bg-yellow-100 text-yellow-800 p-4 rounded text-center">ไม่มีรายการรออนุมัติ</div>
        <?php else: ?>
            <div class="space-y-8">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-[#1f2937] rounded-xl shadow p-6 border border-[#334155] cursor-pointer hover:ring-2 hover:ring-[#60a5fa] transition" onclick="openOrderModal<?= $order['fuel_record_id'] ?>()">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2 gap-2">
                            <div>
                                <div class="font-bold text-lg text-[#4ade80]">ทะเบียน: <?= htmlspecialchars($order['license_plate']) ?></div>
                                <?php if (!empty($order['trailer_license_plate'])): ?>
                                    <div class="font-bold text-md text-[#fbbf24]">รถพ่วง: <?= htmlspecialchars($order['trailer_license_plate']) ?></div>
                                <?php endif; ?>
                                <div class="text-sm text-[#a5b4fc]">วันที่: <?= htmlspecialchars($order['fuel_date']) ?></div>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-2 md:mt-0">
                                <?php if (!empty($attachments[$order['fuel_record_id']])): ?>
                                    <?php foreach ($attachments[$order['fuel_record_id']] as $att): ?>
                                        <?php
                                        $fileUrl = '../uploads/' . $att['file_path'];
                                        $isImg = preg_match('/\.(jpg|jpeg|png|gif)$/i', $att['file_path']);
                                        ?>
                                        <?php if ($isImg): ?>
                                            <a href="<?= $fileUrl ?>" target="_blank" class="inline-block"><img src="<?= $fileUrl ?>" alt="หลักฐาน" class="h-12 rounded shadow border border-[#334155] hover:scale-105 transition-transform" /></a>
                                        <?php else: ?>
                                            <a href="<?= $fileUrl ?>" target="_blank" class="inline-block px-2 py-1 bg-[#60a5fa] text-[#111827] rounded shadow">PDF</a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400">คลิกเพื่อจัดการ/ตรวจสอบ</div>
                    </div>
                    <!-- Modal -->
                    <div id="orderModal<?= $order['fuel_record_id'] ?>" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden">
                        <div class="bg-[#1f2937] rounded-xl shadow-xl p-6 w-full max-w-xl relative">
                            <button onclick="closeOrderModal<?= $order['fuel_record_id'] ?>()" class="absolute top-2 right-2 bg-gray-700 text-white rounded-full p-2 hover:bg-gray-600 focus:outline-none" aria-label="ปิด">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <form method="post" class="space-y-3 mt-2">
                                <input type="hidden" name="fuel_record_id" value="<?= htmlspecialchars($order['fuel_record_id']) ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-semibold mb-1">วันที่ในใบเสร็จ</label>
                                        <input type="datetime-local" name="fuel_date" id="fuel_date_<?= $order['fuel_record_id'] ?>" class="w-full rounded px-3 py-2 text-[#111827]" value="<?= htmlspecialchars(date('Y-m-d\\TH:i', strtotime($order['fuel_date']))) ?>" required onchange="updateMileageConstraint<?= $order['fuel_record_id'] ?>()">
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1">ประเภทเชื้อเพลิง</label>
                                        <select name="fuel_type" id="fuel_type_select_<?= $order['fuel_record_id'] ?>" class="w-full rounded px-3 py-2 text-[#111827]" onchange="toggleOtherFuelType<?= $order['fuel_record_id'] ?>()" required>
                                            <?php $found = false; ?>
                                            <?php foreach ($fuel_types as $ft): ?>
                                                <option value="<?= htmlspecialchars($ft) ?>" <?php if ($order['fuel_type'] === $ft) {
                                                                                                echo 'selected';
                                                                                                $found = true;
                                                                                            } ?>><?= htmlspecialchars($ft) ?></option>
                                            <?php endforeach; ?>
                                            <option value="other" <?php if (!$found) echo 'selected'; ?>>อื่น ๆ</option>
                                        </select>
                                        <input type="text" name="fuel_type_other" id="fuel_type_other_<?= $order['fuel_record_id'] ?>" class="w-full rounded px-3 py-2 text-[#111827] mt-2" placeholder="กรอกประเภทเชื้อเพลิง" style="display:<?= (($found) ? 'none' : '') ?>;" value="<?php if (!$found) echo htmlspecialchars($order['fuel_type']); ?>">
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1">ราคา/ลิตร (บาท)</label>
                                        <input type="number" step="0.01" name="cost_per_liter" id="cost_per_liter_<?= $order['fuel_record_id'] ?>" class="w-full rounded px-3 py-2 text-[#111827]" value="<?= htmlspecialchars($order['cost_per_liter']) ?>" required oninput="calcVolume<?= $order['fuel_record_id'] ?>()">
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1">ราคารวม (บาท)</label>
                                        <input type="number" step="0.01" name="total_cost" id="total_cost_<?= $order['fuel_record_id'] ?>" class="w-full rounded px-3 py-2 text-[#111827]" value="<?= htmlspecialchars($order['total_cost']) ?>" required oninput="calcVolume<?= $order['fuel_record_id'] ?>()">
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1">จำนวนลิตร (คำนวณอัตโนมัติ)</label>
                                        <input type="number" step="0.01" name="volume_liters" id="volume_liters_<?= $order['fuel_record_id'] ?>" class="w-full rounded px-3 py-2 text-[#111827] bg-gray-200" value="<?= ($order['cost_per_liter'] > 0 ? number_format($order['total_cost'] / $order['cost_per_liter'], 2, '.', '') : '') ?>" readonly>
                                    </div>
                                    <?php
                                    // ดึงเลขไมล์ล่าสุดของรถพ่วง (ถ้ามี)
                                    $trailer_mileage = '';
                                    if (!empty($order['trailer_vehicle_id'])) {
                                        $stmt_trailer = $conn->prepare("SELECT TOP 1 mileage_at_fuel FROM FuelRecords WHERE vehicle_id = ? AND mileage_at_fuel IS NOT NULL ORDER BY fuel_date DESC");
                                        $stmt_trailer->execute([$order['trailer_vehicle_id']]);
                                        $trailer_mileage = $stmt_trailer->fetchColumn();
                                    }
                                    ?>
                                    <?php /* ไม่แสดงช่องเลขไมล์ล่าสุดรถพ่วงตามคำขอ */ ?>
                                    <script>
                                        function toggleOtherFuelType<?= $order['fuel_record_id'] ?>() {
                                            var sel = document.getElementById('fuel_type_select_<?= $order['fuel_record_id'] ?>');
                                            var other = document.getElementById('fuel_type_other_<?= $order['fuel_record_id'] ?>');
                                            if (sel.value === 'other') {
                                                other.style.display = '';
                                                other.required = true;
                                            } else {
                                                other.style.display = 'none';
                                                other.required = false;
                                                other.value = '';
                                            }
                                        }

                                        function calcVolume<?= $order['fuel_record_id'] ?>() {
                                            var cost = parseFloat(document.getElementById('cost_per_liter_<?= $order['fuel_record_id'] ?>').value) || 0;
                                            var total = parseFloat(document.getElementById('total_cost_<?= $order['fuel_record_id'] ?>').value) || 0;
                                            var vol = (cost > 0) ? (total / cost) : '';
                                            document.getElementById('volume_liters_<?= $order['fuel_record_id'] ?>').value = vol ? vol.toFixed(2) : '';
                                        }

                                        function updateMileageConstraint<?= $order['fuel_record_id'] ?>() {
                                            var selectedDate = document.getElementById('fuel_date_<?= $order['fuel_record_id'] ?>').value;
                                            if (!selectedDate) return;

                                            // แปลงวันที่เป็น format สำหรับส่งไป server
                                            var dateForServer = selectedDate.replace('T', ' ');

                                            // ส่ง AJAX request เพื่อดึงข้อจำกัดเลขไมล์ใหม่
                                            fetch('get_mileage_constraint.php', {
                                                    method: 'POST',
                                                    headers: {
                                                        'Content-Type': 'application/x-www-form-urlencoded',
                                                    },
                                                    body: 'vehicle_id=<?= $order['vehicle_id'] ?>&fuel_record_id=<?= $order['fuel_record_id'] ?>&fuel_date=' + encodeURIComponent(dateForServer)
                                                })
                                                .then(response => response.json())
                                                .then(data => {
                                                    if (data.success) {
                                                        var constraintDiv = document.getElementById('mileage_constraint_<?= $order['fuel_record_id'] ?>');
                                                        var mileageInput = document.getElementById('mileage_at_fuel_<?= $order['fuel_record_id'] ?>');

                                                        var constraintText = '';
                                                        
                                                        // แสดงข้อจำกัดต่ำสุด (ย้อนหลัง)
                                                        if (data.min_mileage !== null && data.min_mileage > 0) {
                                                            constraintText += '⚠️ ต้องมากกว่าหรือเท่ากับ ' + new Intl.NumberFormat('th-TH').format(data.min_mileage) + ' กม.';
                                                            mileageInput.setAttribute('min', data.min_mileage);
                                                        } else {
                                                            mileageInput.removeAttribute('min');
                                                        }
                                                        
                                                        // แสดงข้อจำกัดสูงสุด (ถัดไป)
                                                        if (data.max_mileage !== null) {
                                                            if (constraintText) constraintText += '<br>';
                                                            constraintText += '⚠️ ต้องน้อยกว่าหรือเท่ากับ ' + new Intl.NumberFormat('th-TH').format(data.max_mileage) + ' กม.';
                                                            mileageInput.setAttribute('max', data.max_mileage);
                                                        } else {
                                                            mileageInput.removeAttribute('max');
                                                        }
                                                        
                                                        if (!constraintText) {
                                                            constraintText = '⚠️ ไม่มีข้อจำกัดเลขไมล์';
                                                        }
                                                        
                                                        constraintDiv.innerHTML = constraintText;
                                                    }
                                                })
                                                .catch(error => {
                                                    console.error('Error:', error);
                                                });
                                        }

                                        function validateReject<?= $order['fuel_record_id'] ?>() {
                                            var notes = document.getElementById('notes_<?= $order['fuel_record_id'] ?>').value.trim();
                                            if (notes === '') {
                                                alert('กรุณาใส่หมายเหตุเหตุผลในการยกเลิกรายการ');
                                                document.getElementById('notes_<?= $order['fuel_record_id'] ?>').focus();
                                                document.getElementById('notes_required_<?= $order['fuel_record_id'] ?>').style.display = 'inline';
                                                return false;
                                            }
                                            // ปิดการตรวจสอบ required ของเลขไมล์สำหรับการยกเลิก
                                            document.getElementById('mileage_at_fuel_<?= $order['fuel_record_id'] ?>').required = false;
                                            return confirm('ยืนยันการยกเลิกรายการนี้?\nหมายเหตุ: ' + notes);
                                        }

                                        function openOrderModal<?= $order['fuel_record_id'] ?>() {
                                            document.getElementById('orderModal<?= $order['fuel_record_id'] ?>').classList.remove('hidden');
                                            document.body.style.overflow = 'hidden';
                                            // รีเซ็ตการแสดงข้อความจำเป็น
                                            document.getElementById('notes_required_<?= $order['fuel_record_id'] ?>').style.display = 'none';
                                            // รีเซ็ต required ของเลขไมล์
                                            document.getElementById('mileage_at_fuel_<?= $order['fuel_record_id'] ?>').required = true;
                                        }

                                        function closeOrderModal<?= $order['fuel_record_id'] ?>() {
                                            document.getElementById('orderModal<?= $order['fuel_record_id'] ?>').classList.add('hidden');
                                            document.body.style.overflow = '';
                                        }
                                    </script>


                                    <div>
                                        <label class="block font-semibold mb-1">เลขไมล์ขณะเติม (รถหลัก)</label>
                                        <?php
                                        // ดึงเลขไมล์ล่าสุดก่อนวันที่ปัจจุบัน (ย้อนหลัง)
                                        $stmt_before = $conn->prepare("SELECT TOP 1 mileage_at_fuel FROM FuelRecords WHERE vehicle_id = ? AND fuel_record_id <> ? AND mileage_at_fuel IS NOT NULL AND fuel_date < ? ORDER BY fuel_date DESC");
                                        $stmt_before->execute([$order['vehicle_id'], $order['fuel_record_id'], $order['fuel_date']]);
                                        $initial_min_mileage = $stmt_before->fetchColumn();
                                        
                                        // ดึงเลขไมล์แรกหลังวันที่ปัจจุบัน (ถัดไป)
                                        $stmt_after = $conn->prepare("SELECT TOP 1 mileage_at_fuel FROM FuelRecords WHERE vehicle_id = ? AND fuel_record_id <> ? AND mileage_at_fuel IS NOT NULL AND fuel_date > ? ORDER BY fuel_date ASC");
                                        $stmt_after->execute([$order['vehicle_id'], $order['fuel_record_id'], $order['fuel_date']]);
                                        $initial_max_mileage = $stmt_after->fetchColumn();
                                        
                                        $constraint_text = '';
                                        if ($initial_min_mileage !== false && $initial_min_mileage > 0) {
                                            $constraint_text .= '⚠️ ต้องมากกว่าหรือเท่ากับ ' . number_format($initial_min_mileage) . ' กม.';
                                        }
                                        if ($initial_max_mileage !== false) {
                                            if ($constraint_text) $constraint_text .= '<br>';
                                            $constraint_text .= '⚠️ ต้องน้อยกว่าหรือเท่ากับ ' . number_format($initial_max_mileage) . ' กม.';
                                        }
                                        if (!$constraint_text) {
                                            $constraint_text = '⚠️ ไม่มีข้อจำกัดเลขไมล์';
                                        }
                                        ?>
                                        <div id="mileage_constraint_<?= $order['fuel_record_id'] ?>" class="text-xs text-yellow-300 mb-2">
                                            <?= $constraint_text ?>
                                        </div>
                                        <input type="number" name="mileage_at_fuel" id="mileage_at_fuel_<?= $order['fuel_record_id'] ?>" class="w-full rounded px-3 py-2 text-[#111827]" value="<?= htmlspecialchars($order['mileage_at_fuel']) ?>" required <?php if($initial_min_mileage !== false && $initial_min_mileage > 0): ?>min="<?=$initial_min_mileage?>"<?php endif; ?> <?php if($initial_max_mileage !== false): ?>max="<?=$initial_max_mileage?>"<?php endif; ?>>
                                    </div>
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1">หมายเหตุ <span id="notes_required_<?= $order['fuel_record_id'] ?>" class="text-red-500" style="display:none;">(จำเป็นสำหรับการยกเลิก)</span></label>
                                    <textarea name="notes" id="notes_<?= $order['fuel_record_id'] ?>" class="w-full rounded px-3 py-2 text-[#111827]" rows="2"><?= htmlspecialchars($order['notes']) ?></textarea>
                                </div>
                                <div class="flex gap-4 mt-2">
                                    <button type="submit" class="bg-[#4ade80] hover:bg-[#22d3ee] text-[#111827] font-bold px-6 py-2 rounded shadow w-full md:w-auto">ยืนยันรายการ</button>
                                    <button type="submit" name="reject" value="1" class="bg-[#f87171] hover:bg-[#ef4444] text-white font-bold px-6 py-2 rounded shadow w-full md:w-auto" onclick="return validateReject<?= $order['fuel_record_id'] ?>()">ยกเลิกรายการ</button>
                                    <button type="button" class="bg-gray-400 hover:bg-gray-500 text-white font-bold px-6 py-2 rounded shadow w-full md:w-auto" onclick="closeOrderModal<?= $order['fuel_record_id'] ?>()">ปิด</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.close-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    let card = btn.closest('.bg-[#1f2937]');
                    if (card) card.style.display = 'none';
                });
            });
        });
    </script>
</body>

</html>