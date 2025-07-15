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
</div>
</body>
</html>