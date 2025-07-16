<?php
session_start();
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['admin', 'manager']);

$conn = connect_db();

// ดึงข้อมูลสรุปจาก Tables และ Views ที่มีอยู่
try {
    // รายงานสรุปรถทั้งหมด (ใช้การนับจากตาราง vehicles โดยตรง)
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_vehicles,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vehicles,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_vehicles,
            SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as in_use_vehicles,
            SUM(CASE WHEN status = 'out_of_service' THEN 1 ELSE 0 END) as out_of_service_vehicles
        FROM vehicles 
        WHERE is_deleted = 0
    ");
    $vehicleSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    // รายงานตามประเภทรถ (ใช้จาก VehicleDetailsView)
    $stmt = $conn->query("
        SELECT 
            category_name,
            COUNT(*) as total_vehicles
        FROM VehicleDetailsView 
        GROUP BY category_name 
        ORDER BY total_vehicles DESC
    ");
    $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานตามยี่ห้อ
    $stmt = $conn->query("
        SELECT TOP 10
            brand_name,
            COUNT(*) as total_vehicles
        FROM VehicleDetailsView 
        GROUP BY brand_name 
        ORDER BY total_vehicles DESC
    ");
    $brandStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานตามประเภทเชื้อเพลิง
    $stmt = $conn->query("
        SELECT 
            fuel_name,
            COUNT(*) as total_vehicles
        FROM VehicleDetailsView 
        GROUP BY fuel_name 
        ORDER BY total_vehicles DESC
    ");
    $fuelStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานตามจังหวัด
    $stmt = $conn->query("
        SELECT TOP 10
            province,
            COUNT(*) as total_vehicles
        FROM VehicleDetailsView 
        GROUP BY province 
        ORDER BY total_vehicles DESC
    ");
    $provinceStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานตามช่วงปี
    $stmt = $conn->query("
        SELECT 
            CASE 
                WHEN vehicle_age <= 5 THEN '0-5 ปี'
                WHEN vehicle_age <= 10 THEN '6-10 ปี'
                WHEN vehicle_age <= 15 THEN '11-15 ปี'
                ELSE 'มากกว่า 15 ปี'
            END as year_range,
            COUNT(*) as total_vehicles,
            MIN(year_manufactured) as min_year,
            MAX(year_manufactured) as max_year
        FROM VehicleDetailsView 
        GROUP BY 
            CASE 
                WHEN vehicle_age <= 5 THEN '0-5 ปี'
                WHEN vehicle_age <= 10 THEN '6-10 ปี'
                WHEN vehicle_age <= 15 THEN '11-15 ปี'
                ELSE 'มากกว่า 15 ปี'
            END
        ORDER BY min_year DESC
    ");
    $yearRangeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานรถที่มีอายุมาก (>10 ปี)
    $stmt = $conn->query("
        SELECT license_plate, brand_name, model_name, year_manufactured, vehicle_age, status_thai
        FROM VehicleDetailsView 
        WHERE vehicle_age > 10 
        ORDER BY vehicle_age DESC
    ");
    $oldVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานรถที่กำลังซ่อมบำรุง
    $stmt = $conn->query("
        SELECT license_plate, brand_name, model_name, category_name, updated_at
        FROM VehicleDetailsView 
        WHERE status = 'maintenance'
        ORDER BY updated_at DESC
    ");
    $maintenanceVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Fallback ถ้า Views ยังไม่ถูกสร้าง
    $vehicleSummary = [
        'total_vehicles' => 0,
        'active_vehicles' => 0,
        'maintenance_vehicles' => 0,
        'in_use_vehicles' => 0,
        'out_of_service_vehicles' => 0
    ];
    $categoryStats = [];
    $brandStats = [];
    $fuelStats = [];
    $provinceStats = [];
    $yearRangeStats = [];
    $oldVehicles = [];
    $maintenanceVehicles = [];
}

// เตรียมข้อมูลกราฟ (7 วัน, 1 เดือน, 1 ปี)
$labels7d = [];
$data7d = [];
for($i=6;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels7d[] = date('d/m', strtotime($d));
    $stmt = $conn->prepare("SELECT SUM(volume_liters) as total FROM fuel_records WHERE CONVERT(date, fuel_date) = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$d]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $data7d[] = $row['total'] ? floatval($row['total']) : 0;
}

$labels1m = [];
$data1m = [];
for($i=29;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels1m[] = date('d/m', strtotime($d));
    $stmt = $conn->prepare("SELECT SUM(volume_liters) as total FROM fuel_records WHERE CONVERT(date, fuel_date) = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$d]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $data1m[] = $row['total'] ? floatval($row['total']) : 0;
}

$labels1y = [];
$data1y = [];
for($i=11;$i>=0;$i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $labels1y[] = date('m/Y', strtotime($month.'-01'));
    $stmt = $conn->prepare("SELECT SUM(volume_liters) as total FROM fuel_records WHERE FORMAT(fuel_date, 'yyyy-MM') = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $data1y[] = $row['total'] ? floatval($row['total']) : 0;
}

// ดึงข้อมูลสรุปทั้งหมด
try {
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_fills,
            SUM(volume_liters) as total_liters,
            SUM(total_cost) as total_cost
        FROM fuel_records 
        WHERE status IN ('pending', 'approved')
    ");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_fills = $summary['total_fills'] ?? 0;
    $total_liters = $summary['total_liters'] ?? 0;
    $total_cost = $summary['total_cost'] ?? 0;
} catch (Exception $e) {
    $total_fills = 0;
    $total_liters = 0;
    $total_cost = 0;
}

// ดึงข้อมูล Top 5 รถที่เติมน้ำมันมากที่สุด
try {
    $stmt = $conn->query("
        SELECT TOP 5
            v.license_plate,
            COUNT(f.fuel_record_id) as fill_count,
            SUM(f.volume_liters) as total_liters,
            SUM(f.total_cost) as total_cost
        FROM fuel_records f
        JOIN vehicles v ON f.vehicle_id = v.vehicle_id
        WHERE f.status IN ('pending', 'approved')
        GROUP BY v.license_plate, v.vehicle_id
        ORDER BY total_liters DESC
    ");
    $top5 = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top5 = [];
}

// ดึงข้อมูลสรุปรายเดือน
try {
    $stmt = $conn->query("
        SELECT 
            FORMAT(fuel_date, 'yyyy-MM') as month,
            COUNT(*) as fill_count,
            SUM(volume_liters) as total_liters,
            SUM(total_cost) as total_cost
        FROM fuel_records 
        WHERE status IN ('pending', 'approved')
        GROUP BY FORMAT(fuel_date, 'yyyy-MM')
        ORDER BY month DESC
    ");
    $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $monthly = [];
}

// ดึงข้อมูลสรุปแยกตามรถ
try {
    $stmt = $conn->query("
        SELECT 
            v.license_plate,
            COUNT(f.fuel_record_id) as fill_count,
            SUM(f.volume_liters) as total_liters,
            SUM(f.total_cost) as total_cost
        FROM fuel_records f
        JOIN vehicles v ON f.vehicle_id = v.vehicle_id
        WHERE f.status IN ('pending', 'approved')
        GROUP BY v.license_plate, v.vehicle_id
        ORDER BY total_liters DESC
    ");
    $bycar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bycar = [];
}

// ดึงข้อมูลรายงานแยกตามเดือน (สำหรับ dropdown เลือกเดือน)
try {
    $stmt = $conn->query("
        SELECT DISTINCT 
            FORMAT(fuel_date, 'yyyy-MM') as month_value,
            FORMAT(fuel_date, 'MM/yyyy') as month_display
        FROM fuel_records 
        WHERE status IN ('pending', 'approved')
        ORDER BY month_value DESC
    ");
    $available_months = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $available_months = [];
}

// ดึงข้อมูลรายงานรายเดือนแบบละเอียด (เมื่อเลือกเดือน)
$selected_month = $_GET['month'] ?? '';
$monthly_detail_data = [];
if ($selected_month) {
    try {
        // ดึงข้อมูลการเติมน้ำมันในเดือนที่เลือก พร้อมข้อมูลรถและรถพ่วง
        $stmt = $conn->prepare("
            WITH VehicleTrips AS (
                SELECT DISTINCT
                    f.vehicle_id,
                    v.license_plate,
                    vc.category_code,
                    CASE WHEN vc.category_code = 'TRAILER' THEN 'trailer' ELSE 'vehicle' END as vehicle_type,
                    CONVERT(date, f.fuel_date) as fuel_date,
                    -- ดึงรถพ่วงที่เป็นคู่กัน (ถ้ามี)
                    (SELECT TOP 1 v2.vehicle_id 
                     FROM fuel_records f2 
                     JOIN vehicles v2 ON f2.vehicle_id = v2.vehicle_id 
                     JOIN vehicle_categories vc2 ON v2.category_id = vc2.category_id
                     WHERE CONVERT(date, f2.fuel_date) = CONVERT(date, f.fuel_date)
                       AND f2.status IN ('pending', 'approved')
                       AND FORMAT(f2.fuel_date, 'yyyy-MM') = ?
                       AND vc2.category_code = 'TRAILER'
                       AND f2.vehicle_id != f.vehicle_id
                       AND vc.category_code != 'TRAILER'
                    ) as paired_trailer_id
                FROM fuel_records f
                JOIN vehicles v ON f.vehicle_id = v.vehicle_id
                JOIN vehicle_categories vc ON v.category_id = vc.category_id
                WHERE f.status IN ('pending', 'approved')
                  AND FORMAT(fuel_date, 'yyyy-MM') = ?
            ),
            DetailedRecords AS (
                SELECT 
                    f.fuel_record_id,
                    f.vehicle_id,
                    v.license_plate,
                    vc.category_name,
                    CASE WHEN vc.category_code = 'TRAILER' THEN 'trailer' ELSE 'vehicle' END as vehicle_type,
                    f.fuel_date,
                    CONVERT(date, f.fuel_date) as fuel_date_only,
                    f.volume_liters,
                    f.total_cost,
                    f.odometer_reading,
                    ROW_NUMBER() OVER (PARTITION BY f.vehicle_id, CONVERT(date, f.fuel_date) ORDER BY f.fuel_date) as daily_sequence
                FROM fuel_records f
                JOIN vehicles v ON f.vehicle_id = v.vehicle_id
                JOIN vehicle_categories vc ON v.category_id = vc.category_id
                WHERE f.status IN ('pending', 'approved')
                  AND FORMAT(fuel_date, 'yyyy-MM') = ?
            )
            SELECT 
                dr.*,
                vt.paired_trailer_id,
                -- ข้อมูลรถพ่วงที่เป็นคู่
                (SELECT v3.license_plate FROM vehicles v3 WHERE v3.vehicle_id = vt.paired_trailer_id) as paired_trailer_plate
            FROM DetailedRecords dr
            LEFT JOIN VehicleTrips vt ON dr.vehicle_id = vt.vehicle_id AND dr.fuel_date_only = vt.fuel_date
            ORDER BY dr.vehicle_id, dr.fuel_date_only, dr.daily_sequence
        ");
        $stmt->execute([$selected_month, $selected_month, $selected_month]);
        $monthly_detail_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // จัดกลุ่มข้อมูลตามรถและวัน
        $monthly_detail_data = [];
        foreach ($monthly_detail_records as $record) {
            $vehicle_id = $record['vehicle_id'];
            $date = $record['fuel_date_only'];
            
            if (!isset($monthly_detail_data[$vehicle_id])) {
                $monthly_detail_data[$vehicle_id] = [
                    'license_plate' => $record['license_plate'],
                    'vehicle_type' => $record['vehicle_type'],
                    'category_name' => $record['category_name'],
                    'days' => [],
                    'total_liters' => 0,
                    'total_cost' => 0,
                    'paired_trailer_id' => $record['paired_trailer_id'],
                    'paired_trailer_plate' => $record['paired_trailer_plate']
                ];
            }
            
            if (!isset($monthly_detail_data[$vehicle_id]['days'][$date])) {
                $monthly_detail_data[$vehicle_id]['days'][$date] = [
                    'records' => [],
                    'daily_liters' => 0,
                    'daily_cost' => 0
                ];
            }
            
            $monthly_detail_data[$vehicle_id]['days'][$date]['records'][] = $record;
            $monthly_detail_data[$vehicle_id]['days'][$date]['daily_liters'] += $record['volume_liters'];
            $monthly_detail_data[$vehicle_id]['days'][$date]['daily_cost'] += $record['total_cost'];
            
            $monthly_detail_data[$vehicle_id]['total_liters'] += $record['volume_liters'];
            $monthly_detail_data[$vehicle_id]['total_cost'] += $record['total_cost'];
        }
        
        // เรียงลำดับรถหลักก่อน แล้วตามด้วยรถพ่วง
        uasort($monthly_detail_data, function($a, $b) {
            if ($a['vehicle_type'] != $b['vehicle_type']) {
                return ($a['vehicle_type'] == 'vehicle') ? -1 : 1;
            }
            return strcmp($a['license_plate'], $b['license_plate']);
        });
        
    } catch (Exception $e) {
        $monthly_detail_data = [];
        error_log("Error in monthly detail report: " . $e->getMessage());
    }
}
?>
<?php include '../container/header.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปการเติมน้ำมัน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
<div class="container mx-auto py-10">
    <h1 class="text-3xl font-bold mb-8 text-center text-[#f9fafb]">รายงานสรุปการเติมน้ำมัน</h1>


    <!-- Executive Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent flex flex-col items-center">
            <div class="text-2xl font-bold text-[#60a5fa] mb-2">ราคารวมทั้งหมด</div>
            <div class="text-3xl font-extrabold text-[#f9fafb]">฿<?=number_format($total_cost,2)?></div>
        </div>
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent flex flex-col items-center">
            <div class="text-2xl font-bold text-[#60a5fa] mb-2">ปริมาณน้ำมันรวม</div>
            <div class="text-3xl font-extrabold text-[#f9fafb]"><?=number_format($total_liters,2)?> ลิตร</div>
        </div>
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent flex flex-col items-center">
            <div class="text-2xl font-bold text-[#60a5fa] mb-2">จำนวนครั้งที่เติม</div>
            <div class="text-3xl font-extrabold text-[#f9fafb]"><?=number_format($total_fills)?></div>
        </div>
    </div>

    <!-- Fuel Usage Chart (7 วัน, 1 เดือน, 1 ปี) -->
    <div class="mb-10 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-4">
            <h2 class="text-xl font-semibold text-[#60a5fa]">กราฟการใช้เชื้อเพลิง</h2>
            <div class="flex gap-2">
                <button id="btn7d" class="px-4 py-2 rounded-lg bg-[#6366f1] text-white font-bold focus:outline-none focus:ring-2 focus:ring-[#60a5fa]">7 วัน</button>
                <button id="btn1m" class="px-4 py-2 rounded-lg bg-[#4ade80] text-[#111827] font-bold focus:outline-none focus:ring-2 focus:ring-[#60a5fa]">1 เดือน</button>
                <button id="btn1y" class="px-4 py-2 rounded-lg bg-[#facc15] text-[#111827] font-bold focus:outline-none focus:ring-2 focus:ring-[#60a5fa]">1 ปี</button>
            </div>
        </div>
        <div class="w-full h-80">
            <canvas id="fuelChart" class="w-full h-full"></canvas>
        </div>
        <p class="text-sm text-gray-400 mt-2">* กราฟนี้แสดงปริมาณการใช้เชื้อเพลิงตามช่วงเวลาที่เลือก</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const chartData = {
        '7d': {
            labels: <?= json_encode($labels7d) ?>,
            data: <?= json_encode($data7d) ?>
        },
        '1m': {
            labels: <?= json_encode($labels1m) ?>,
            data: <?= json_encode($data1m) ?>
        },
        '1y': {
            labels: <?= json_encode($labels1y) ?>,
            data: <?= json_encode($data1y) ?>
        }
    };
    let currentRange = '7d';
    const ctx = document.getElementById('fuelChart').getContext('2d');
    let fuelChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData['7d'].labels,
            datasets: [{
                label: 'ปริมาณการใช้เชื้อเพลิง (ลิตร)',
                data: chartData['7d'].data,
                backgroundColor: 'rgba(96, 165, 250, 0.2)',
                borderColor: '#60a5fa',
                borderWidth: 3,
                pointBackgroundColor: '#facc15',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#e0e0e0' },
                    grid: { color: '#374151' }
                },
                x: {
                    ticks: { color: '#e0e0e0' },
                    grid: { color: '#374151' }
                }
            }
        }
    });
    document.getElementById('btn7d').onclick = function() {
        fuelChart.data.labels = chartData['7d'].labels;
        fuelChart.data.datasets[0].data = chartData['7d'].data;
        fuelChart.update();
    };
    document.getElementById('btn1m').onclick = function() {
        fuelChart.data.labels = chartData['1m'].labels;
        fuelChart.data.datasets[0].data = chartData['1m'].data;
        fuelChart.update();
    };
    document.getElementById('btn1y').onclick = function() {
        fuelChart.data.labels = chartData['1y'].labels;
        fuelChart.data.datasets[0].data = chartData['1y'].data;
        fuelChart.update();
    };
    </script>

    <!-- Top 5 Cars -->
    <div class="mb-8 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <h2 class="text-xl font-semibold mb-4 text-[#60a5fa]">Top 5 รถที่เติมน้ำมันมากที่สุด</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full bg-[#111827] rounded-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#6366f1] to-[#4ade80] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">ทะเบียนรถ</th>
                    <th class="px-4 py-3 text-left font-bold">จำนวนครั้ง</th>
                    <th class="px-4 py-3 text-left font-bold">ปริมาณ (ลิตร)</th>
                    <th class="px-4 py-3 text-left font-bold">ราคารวม (บาท)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($top5 as $c): ?>
                <tr class="even:bg-[#1f2937] odd:bg-[#111827] hover:bg-[#374151] transition-colors">
                    <td class="px-4 py-2"><?=htmlspecialchars($c['license_plate'])?></td>
                    <td class="px-4 py-2"><?=htmlspecialchars($c['fill_count'])?></td>
                    <td class="px-4 py-2"><?=number_format($c['total_liters'],2)?></td>
                    <td class="px-4 py-2"><?=number_format($c['total_cost'],2)?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Monthly Summary Table -->
    <div class="mb-8 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <h2 class="text-xl font-semibold mb-4 text-[#60a5fa]">สรุปยอดเติมน้ำมันรายเดือน</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full bg-[#111827] rounded-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#6366f1] to-[#4ade80] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">เดือน</th>
                    <th class="px-4 py-3 text-left font-bold">จำนวนครั้ง</th>
                    <th class="px-4 py-3 text-left font-bold">ปริมาณ (ลิตร)</th>
                    <th class="px-4 py-3 text-left font-bold">ราคารวม (บาท)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($monthly as $m): ?>
                <tr class="even:bg-[#1f2937] odd:bg-[#111827] hover:bg-[#374151] transition-colors">
                    <td class="px-4 py-2"><?=htmlspecialchars($m['month'])?></td>
                    <td class="px-4 py-2"><?=htmlspecialchars($m['fill_count'])?></td>
                    <td class="px-4 py-2"><?=number_format($m['total_liters'],2)?></td>
                    <td class="px-4 py-2"><?=number_format($m['total_cost'],2)?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- By Car Table -->
    <div class="bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <h2 class="text-xl font-semibold mb-4 text-[#60a5fa]">สรุปยอดเติมน้ำมันแยกรถ</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full bg-[#111827] rounded-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#6366f1] to-[#4ade80] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">ทะเบียนรถ</th>
                    <th class="px-4 py-3 text-left font-bold">จำนวนครั้ง</th>
                    <th class="px-4 py-3 text-left font-bold">ปริมาณ (ลิตร)</th>
                    <th class="px-4 py-3 text-left font-bold">ราคารวม (บาท)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($bycar as $c): ?>
                <tr class="even:bg-[#1f2937] odd:bg-[#111827] hover:bg-[#374151] transition-colors">
                    <td class="px-4 py-2"><?=htmlspecialchars($c['license_plate'])?></td>
                    <td class="px-4 py-2"><?=htmlspecialchars($c['fill_count'])?></td>
                    <td class="px-4 py-2"><?=number_format($c['total_liters'],2)?></td>
                    <td class="px-4 py-2"><?=number_format($c['total_cost'],2)?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- รายงานรายเดือนแบบละเอียด -->
    <div class="mt-10 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
            <h2 class="text-xl font-semibold text-[#60a5fa]">รายงานรายเดือนแบบละเอียด</h2>
            <form method="get" class="flex gap-2">
                <select name="month" class="px-4 py-2 rounded-lg bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:ring-2 focus:ring-[#4f46e5]" onchange="this.form.submit()">
                    <option value="">เลือกเดือน</option>
                    <?php foreach($available_months as $month): ?>
                        <option value="<?=htmlspecialchars($month['month_value'])?>" <?=$selected_month === $month['month_value'] ? 'selected' : ''?>>
                            <?=htmlspecialchars($month['month_display'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if($selected_month): ?>
                    <a href="?" class="px-4 py-2 rounded-lg bg-[#374151] text-[#e5e7eb] hover:bg-[#4b5563] transition-colors">ล้าง</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if($selected_month && !empty($monthly_detail_data)): ?>
            <div class="mb-4 p-4 bg-[#111827] rounded-lg">
                <h3 class="text-lg font-semibold text-[#4ade80] mb-2">รายงานประจำเดือน <?=date('m/Y', strtotime($selected_month.'-01'))?></h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <?php
                    $month_total_liters = array_sum(array_column($monthly_detail_data, 'total_liters'));
                    $month_total_cost = array_sum(array_column($monthly_detail_data, 'total_cost'));
                    $month_total_vehicles = count($monthly_detail_data);
                    ?>
                    <div class="text-center">
                        <div class="text-[#60a5fa] font-semibold">รถทั้งหมด</div>
                        <div class="text-[#f9fafb] text-lg"><?=$month_total_vehicles?> คัน</div>
                    </div>
                    <div class="text-center">
                        <div class="text-[#60a5fa] font-semibold">ปริมาณรวม</div>
                        <div class="text-[#f9fafb] text-lg"><?=number_format($month_total_liters, 2)?> ลิตร</div>
                    </div>
                    <div class="text-center">
                        <div class="text-[#60a5fa] font-semibold">ราคารวม</div>
                        <div class="text-[#f9fafb] text-lg">฿<?=number_format($month_total_cost, 2)?></div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <?php 
                $vehicle_groups = [];
                // จัดกลุ่มรถหลักกับรถพ่วง
                foreach($monthly_detail_data as $vehicle_id => $vehicle_data) {
                    if ($vehicle_data['vehicle_type'] === 'vehicle') {
                        $group_key = $vehicle_id;
                        $vehicle_groups[$group_key] = [
                            'main_vehicle' => $vehicle_data,
                            'trailers' => []
                        ];
                        
                        // หารถพ่วงที่เป็นคู่กัน
                        if ($vehicle_data['paired_trailer_id']) {
                            foreach($monthly_detail_data as $trailer_id => $trailer_data) {
                                if ($trailer_id == $vehicle_data['paired_trailer_id']) {
                                    $vehicle_groups[$group_key]['trailers'][] = $trailer_data;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // เพิ่มรถพ่วงที่ไม่มีคู่
                foreach($monthly_detail_data as $vehicle_id => $vehicle_data) {
                    if ($vehicle_data['vehicle_type'] === 'trailer') {
                        $found_in_group = false;
                        foreach($vehicle_groups as $group) {
                            foreach($group['trailers'] as $trailer) {
                                if ($trailer['license_plate'] === $vehicle_data['license_plate']) {
                                    $found_in_group = true;
                                    break 2;
                                }
                            }
                        }
                        if (!$found_in_group) {
                            $vehicle_groups['trailer_'.$vehicle_id] = [
                                'main_vehicle' => null,
                                'trailers' => [$vehicle_data]
                            ];
                        }
                    }
                }
                ?>

                <?php foreach($vehicle_groups as $group_key => $group): ?>
                    <div class="bg-[#111827] rounded-xl p-6 border border-[#374151]">
                        <?php if($group['main_vehicle']): ?>
                            <!-- รถหลัก -->
                            <div class="mb-4">
                                <h4 class="text-lg font-semibold text-[#4ade80] mb-3 flex items-center">
                                    <span class="w-3 h-3 bg-[#4ade80] rounded-full mr-2"></span>
                                    รถหลัก: <?=htmlspecialchars($group['main_vehicle']['license_plate'])?>
                                    <span class="ml-2 text-sm font-normal text-[#9ca3af]">(<?=htmlspecialchars($group['main_vehicle']['category_name'])?>)</span>
                                </h4>
                                
                                <div class="overflow-x-auto mb-4">
                                    <table class="min-w-full bg-[#1f2937] rounded-lg overflow-hidden">
                                        <thead>
                                            <tr class="bg-[#374151]">
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">วันที่</th>
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">เวลา</th>
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">ปริมาณ (ลิตร)</th>
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">ราคา (บาท)</th>
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">เลขไมล์</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $vehicle_daily_totals = [];
                                            foreach($group['main_vehicle']['days'] as $date => $day_data): 
                                                if (!isset($vehicle_daily_totals[$date])) {
                                                    $vehicle_daily_totals[$date] = ['liters' => 0, 'cost' => 0];
                                                }
                                                $vehicle_daily_totals[$date]['liters'] += $day_data['daily_liters'];
                                                $vehicle_daily_totals[$date]['cost'] += $day_data['daily_cost'];
                                            ?>
                                                <?php foreach($day_data['records'] as $record): ?>
                                                    <tr class="hover:bg-[#374151] transition-colors">
                                                        <td class="px-3 py-2 text-sm"><?=date('d/m/Y', strtotime($record['fuel_date']))?></td>
                                                        <td class="px-3 py-2 text-sm"><?=date('H:i', strtotime($record['fuel_date']))?></td>
                                                        <td class="px-3 py-2 text-sm"><?=number_format($record['volume_liters'], 2)?></td>
                                                        <td class="px-3 py-2 text-sm"><?=number_format($record['total_cost'], 2)?></td>
                                                        <td class="px-3 py-2 text-sm"><?=$record['odometer_reading'] ? number_format($record['odometer_reading']) : '-'?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="flex justify-end bg-[#374151] rounded-lg p-3">
                                    <div class="text-sm">
                                        <span class="text-[#9ca3af]">รวมรถหลัก:</span>
                                        <span class="text-[#4ade80] font-semibold ml-2"><?=number_format($group['main_vehicle']['total_liters'], 2)?> ลิตร</span>
                                        <span class="text-[#60a5fa] font-semibold ml-4">฿<?=number_format($group['main_vehicle']['total_cost'], 2)?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach($group['trailers'] as $trailer): ?>
                            <!-- รถพ่วง -->
                            <div class="mb-4 <?=$group['main_vehicle'] ? 'ml-4 border-l-2 border-[#facc15] pl-4' : ''?>">
                                <h4 class="text-lg font-semibold text-[#facc15] mb-3 flex items-center">
                                    <span class="w-3 h-3 bg-[#facc15] rounded-full mr-2"></span>
                                    <?=$group['main_vehicle'] ? 'รถพ่วง' : 'รถพ่วงอิสระ'?>: <?=htmlspecialchars($trailer['license_plate'])?>
                                    <span class="ml-2 text-sm font-normal text-[#9ca3af]">(<?=htmlspecialchars($trailer['category_name'])?>)</span>
                                </h4>
                                
                                <div class="overflow-x-auto mb-4">
                                    <table class="min-w-full bg-[#1f2937] rounded-lg overflow-hidden">
                                        <thead>
                                            <tr class="bg-[#374151]">
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">วันที่</th>
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">เวลา</th>
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">ปริมาณ (ลิตร)</th>
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">ราคา (บาท)</th>
                                                <th class="px-3 py-2 text-left text-sm font-semibold text-[#e5e7eb]">เลขไมล์</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($trailer['days'] as $date => $day_data): ?>
                                                <?php foreach($day_data['records'] as $record): ?>
                                                    <tr class="hover:bg-[#374151] transition-colors">
                                                        <td class="px-3 py-2 text-sm"><?=date('d/m/Y', strtotime($record['fuel_date']))?></td>
                                                        <td class="px-3 py-2 text-sm"><?=date('H:i', strtotime($record['fuel_date']))?></td>
                                                        <td class="px-3 py-2 text-sm"><?=number_format($record['volume_liters'], 2)?></td>
                                                        <td class="px-3 py-2 text-sm"><?=number_format($record['total_cost'], 2)?></td>
                                                        <td class="px-3 py-2 text-sm"><?=$record['odometer_reading'] ? number_format($record['odometer_reading']) : '-'?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="flex justify-end bg-[#374151] rounded-lg p-3">
                                    <div class="text-sm">
                                        <span class="text-[#9ca3af]">รวมรถพ่วง:</span>
                                        <span class="text-[#facc15] font-semibold ml-2"><?=number_format($trailer['total_liters'], 2)?> ลิตร</span>
                                        <span class="text-[#60a5fa] font-semibold ml-4">฿<?=number_format($trailer['total_cost'], 2)?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- สรุปรวมกลุ่ม -->
                        <?php if($group['main_vehicle'] && !empty($group['trailers'])): ?>
                            <div class="mt-4 pt-4 border-t border-[#374151]">
                                <div class="flex justify-end bg-gradient-to-r from-[#6366f1] to-[#4ade80] rounded-lg p-4">
                                    <div class="text-sm text-[#111827] font-semibold">
                                        <?php 
                                        $group_total_liters = $group['main_vehicle']['total_liters'];
                                        $group_total_cost = $group['main_vehicle']['total_cost'];
                                        foreach($group['trailers'] as $trailer) {
                                            $group_total_liters += $trailer['total_liters'];
                                            $group_total_cost += $trailer['total_cost'];
                                        }
                                        ?>
                                        <span>รวมทั้งกลุ่ม:</span>
                                        <span class="ml-2"><?=number_format($group_total_liters, 2)?> ลิตร</span>
                                        <span class="ml-4">฿<?=number_format($group_total_cost, 2)?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif($selected_month): ?>
            <div class="text-center py-8 text-[#9ca3af]">
                <p>ไม่พบข้อมูลการเติมน้ำมันในเดือนที่เลือก</p>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-[#9ca3af]">
                <p>กรุณาเลือกเดือนที่ต้องการดูรายงาน</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('monthSelect').addEventListener('change', function() {
    const selectedMonth = this.value;
    const url = new URL(window.location.href);
    url.searchParams.set('month', selectedMonth);
    window.location.href = url.toString();
});
</script>
</body>
</html>