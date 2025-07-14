<?php
session_start();
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../service/auth.php';
require_once __DIR__ . '/../database/database_config.php';
auth(['admin', 'manager']);

$conn = connect_db();

// ดึงข้อมูลสรุปจาก Views
try {
    // รายงานสรุปรถทั้งหมด
    $stmt = $conn->query("SELECT * FROM VehicleSummaryReport");
    $vehicleSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    // รายงานตามประเภทรถ
    $stmt = $conn->query("SELECT * FROM VehicleStatsByCategory ORDER BY total_vehicles DESC");
    $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานตามยี่ห้อ
    $stmt = $conn->query("SELECT TOP 10 * FROM VehicleStatsByBrand ORDER BY total_vehicles DESC");
    $brandStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานตามประเภทเชื้อเพลิง
    $stmt = $conn->query("SELECT * FROM VehicleStatsByFuelType ORDER BY total_vehicles DESC");
    $fuelStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานตามจังหวัด
    $stmt = $conn->query("SELECT TOP 10 * FROM VehicleStatsByProvince ORDER BY total_vehicles DESC");
    $provinceStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานตามช่วงปี
    $stmt = $conn->query("SELECT * FROM VehicleStatsByYearRange ORDER BY min_year DESC");
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
    // Fallback ถ้า Views ยังไม่ถูกสร้าง - ใช้ query ปกติ
    $stmt = $conn->query("SELECT COUNT(*) as total_vehicles FROM vehicles WHERE is_deleted = 0");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT COUNT(*) as active_vehicles FROM vehicles WHERE status = 'active' AND is_deleted = 0");
    $active = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT COUNT(*) as maintenance_vehicles FROM vehicles WHERE status = 'maintenance' AND is_deleted = 0");
    $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $vehicleSummary = [
        'total_vehicles' => $total['total_vehicles'],
        'active_vehicles' => $active['active_vehicles'],
        'maintenance_vehicles' => $maintenance['maintenance_vehicles'],
        'in_use_vehicles' => 0,
        'out_of_service_vehicles' => 0,
        'total_brands' => 0,
        'total_categories' => 0,
        'avg_vehicle_age' => 0,
        'oldest_vehicle_year' => 0,
        'newest_vehicle_year' => 0,
        'total_payload_capacity' => 0,
        'avg_payload_capacity' => 0
    ];
    $categoryStats = [];
    $brandStats = [];
    $fuelStats = [];
    $provinceStats = [];
    $yearRangeStats = [];
    $oldVehicles = [];
    $maintenanceVehicles = [];
}
?>
<?php include '../container/header.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปยานพาหนะ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
<div class="container mx-auto py-10">
    <h1 class="text-3xl font-bold mb-8 text-center text-[#f9fafb]">รายงานสรุปยานพาหนะ</h1>

    <!-- สรุปข้อมูลหลัก -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-10">
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent flex flex-col items-center">
            <div class="text-lg font-bold text-[#60a5fa] mb-2">รถทั้งหมด</div>
            <div class="text-3xl font-extrabold text-[#f9fafb]"><?= number_format($vehicleSummary['total_vehicles'] ?? 0) ?></div>
        </div>
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent flex flex-col items-center">
            <div class="text-lg font-bold text-[#4ade80] mb-2">พร้อมใช้งาน</div>
            <div class="text-3xl font-extrabold text-[#f9fafb]"><?= number_format($vehicleSummary['active_vehicles'] ?? 0) ?></div>
        </div>
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent flex flex-col items-center">
            <div class="text-lg font-bold text-[#facc15] mb-2">กำลังใช้งาน</div>
            <div class="text-3xl font-extrabold text-[#f9fafb]"><?= number_format($vehicleSummary['in_use_vehicles'] ?? 0) ?></div>
        </div>
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent flex flex-col items-center">
            <div class="text-lg font-bold text-[#f87171] mb-2">ซ่อมบำรุง</div>
            <div class="text-3xl font-extrabold text-[#f9fafb]"><?= number_format($vehicleSummary['maintenance_vehicles'] ?? 0) ?></div>
        </div>
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent flex flex-col items-center">
            <div class="text-lg font-bold text-[#9ca3af] mb-2">หยุดใช้งาน</div>
            <div class="text-3xl font-extrabold text-[#f9fafb]"><?= number_format($vehicleSummary['out_of_service_vehicles'] ?? 0) ?></div>
        </div>
    </div>

    <!-- ข้อมูลเพิ่มเติม -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent">
            <div class="text-xl font-bold text-[#60a5fa] mb-4">ข้อมูลเพิ่มเติม</div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>จำนวนยี่ห้อ:</span>
                    <span class="font-bold"><?= number_format($vehicleSummary['total_brands'] ?? 0) ?> ยี่ห้อ</span>
                </div>
                <div class="flex justify-between">
                    <span>จำนวนประเภท:</span>
                    <span class="font-bold"><?= number_format($vehicleSummary['total_categories'] ?? 0) ?> ประเภท</span>
                </div>
                <div class="flex justify-between">
                    <span>อายุเฉลี่ย:</span>
                    <span class="font-bold"><?= number_format($vehicleSummary['avg_vehicle_age'] ?? 0, 1) ?> ปี</span>
                </div>
                <div class="flex justify-between">
                    <span>รถเก่าสุด:</span>
                    <span class="font-bold">ปี <?= $vehicleSummary['oldest_vehicle_year'] ?? '-' ?></span>
                </div>
                <div class="flex justify-between">
                    <span>รถใหม่สุด:</span>
                    <span class="font-bold">ปี <?= $vehicleSummary['newest_vehicle_year'] ?? '-' ?></span>
                </div>
            </div>
        </div>
        
        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent">
            <div class="text-xl font-bold text-[#60a5fa] mb-4">ข้อมูลน้ำหนักบรรทุก</div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>น้ำหนักรวมทั้งหมด:</span>
                    <span class="font-bold"><?= number_format($vehicleSummary['total_payload_capacity'] ?? 0, 0) ?> กก.</span>
                </div>
                <div class="flex justify-between">
                    <span>น้ำหนักเฉลี่ย:</span>
                    <span class="font-bold"><?= number_format($vehicleSummary['avg_payload_capacity'] ?? 0, 0) ?> กก.</span>
                </div>
            </div>
        </div>

        <div class="bg-[#1f2937] rounded-2xl shadow-xl p-6 border-2 border-transparent">
            <div class="text-xl font-bold text-[#f87171] mb-4">แจ้งเตือน</div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>รถอายุ > 10 ปี:</span>
                    <span class="font-bold text-red-400"><?= count($oldVehicles) ?> คัน</span>
                </div>
                <div class="flex justify-between">
                    <span>รถกำลังซ่อม:</span>
                    <span class="font-bold text-yellow-400"><?= count($maintenanceVehicles) ?> คัน</span>
                </div>
            </div>
        </div>
    </div>

    <!-- รายงานตามประเภทรถ -->
    <?php if (!empty($categoryStats)): ?>
    <div class="mb-8 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <h2 class="text-xl font-semibold mb-4 text-[#60a5fa]">รายงานตามประเภทรถ</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full bg-[#111827] rounded-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#6366f1] to-[#4ade80] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">ประเภทรถ</th>
                    <th class="px-4 py-3 text-left font-bold">จำนวนทั้งหมด</th>
                    <th class="px-4 py-3 text-left font-bold">พร้อมใช้งาน</th>
                    <th class="px-4 py-3 text-left font-bold">ซ่อมบำรุง</th>
                    <th class="px-4 py-3 text-left font-bold">อายุเฉลี่ย</th>
                    <th class="px-4 py-3 text-left font-bold">น้ำหนักรวม (กก.)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($categoryStats as $cat): ?>
                <tr class="even:bg-[#1f2937] odd:bg-[#111827] hover:bg-[#374151] transition-colors">
                    <td class="px-4 py-2"><?= htmlspecialchars($cat['category_name'] ?? 'ไม่ระบุ') ?></td>
                    <td class="px-4 py-2"><?= number_format($cat['total_vehicles']) ?></td>
                    <td class="px-4 py-2"><?= number_format($cat['active_count']) ?></td>
                    <td class="px-4 py-2"><?= number_format($cat['maintenance_count']) ?></td>
                    <td class="px-4 py-2"><?= number_format($cat['avg_age'] ?? 0, 1) ?> ปี</td>
                    <td class="px-4 py-2"><?= number_format($cat['total_payload_capacity'] ?? 0, 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- รายงานตามยี่ห้อ -->
    <?php if (!empty($brandStats)): ?>
    <div class="mb-8 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <h2 class="text-xl font-semibold mb-4 text-[#60a5fa]">รายงานตามยี่ห้อรถ (Top 10)</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full bg-[#111827] rounded-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#6366f1] to-[#4ade80] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">ยี่ห้อ</th>
                    <th class="px-4 py-3 text-left font-bold">ประเทศ</th>
                    <th class="px-4 py-3 text-left font-bold">จำนวนทั้งหมด</th>
                    <th class="px-4 py-3 text-left font-bold">พร้อมใช้งาน</th>
                    <th class="px-4 py-3 text-left font-bold">อายุเฉลี่ย</th>
                    <th class="px-4 py-3 text-left font-bold">น้ำหนักรวม (กก.)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($brandStats as $brand): ?>
                <tr class="even:bg-[#1f2937] odd:bg-[#111827] hover:bg-[#374151] transition-colors">
                    <td class="px-4 py-2"><?= htmlspecialchars($brand['brand_name'] ?? 'ไม่ระบุ') ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($brand['country'] ?? '-') ?></td>
                    <td class="px-4 py-2"><?= number_format($brand['total_vehicles']) ?></td>
                    <td class="px-4 py-2"><?= number_format($brand['active_count']) ?></td>
                    <td class="px-4 py-2"><?= number_format($brand['avg_age'] ?? 0, 1) ?> ปี</td>
                    <td class="px-4 py-2"><?= number_format($brand['total_payload_capacity'] ?? 0, 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- รายงานรถที่มีอายุมาก -->
    <?php if (!empty($oldVehicles)): ?>
    <div class="mb-8 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <h2 class="text-xl font-semibold mb-4 text-[#f87171]">รายงานรถที่มีอายุมากกว่า 10 ปี</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full bg-[#111827] rounded-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#f87171] to-[#facc15] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">ทะเบียน</th>
                    <th class="px-4 py-3 text-left font-bold">ยี่ห้อ</th>
                    <th class="px-4 py-3 text-left font-bold">รุ่น</th>
                    <th class="px-4 py-3 text-left font-bold">ปีผลิต</th>
                    <th class="px-4 py-3 text-left font-bold">อายุ</th>
                    <th class="px-4 py-3 text-left font-bold">สถานะ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($oldVehicles as $old): ?>
                <tr class="even:bg-[#1f2937] odd:bg-[#111827] hover:bg-[#374151] transition-colors">
                    <td class="px-4 py-2"><?= htmlspecialchars($old['license_plate']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($old['brand_name'] ?? 'ไม่ระบุ') ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($old['model_name']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($old['year_manufactured']) ?></td>
                    <td class="px-4 py-2 text-red-400 font-bold"><?= htmlspecialchars($old['vehicle_age']) ?> ปี</td>
                    <td class="px-4 py-2"><?= htmlspecialchars($old['status_thai']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- รายงานรถที่กำลังซ่อมบำรุง -->
    <?php if (!empty($maintenanceVehicles)): ?>
    <div class="mb-8 bg-[#1f2937] rounded-2xl shadow-xl p-8 border-2 border-transparent">
        <h2 class="text-xl font-semibold mb-4 text-[#facc15]">รายงานรถที่กำลังซ่อมบำรุง</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full bg-[#111827] rounded-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#facc15] to-[#4ade80] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">ทะเบียน</th>
                    <th class="px-4 py-3 text-left font-bold">ยี่ห้อ</th>
                    <th class="px-4 py-3 text-left font-bold">รุ่น</th>
                    <th class="px-4 py-3 text-left font-bold">ประเภท</th>
                    <th class="px-4 py-3 text-left font-bold">อัพเดทล่าสุด</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($maintenanceVehicles as $maint): ?>
                <tr class="even:bg-[#1f2937] odd:bg-[#111827] hover:bg-[#374151] transition-colors">
                    <td class="px-4 py-2"><?= htmlspecialchars($maint['license_plate']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($maint['brand_name'] ?? 'ไม่ระบุ') ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($maint['model_name']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($maint['category_name'] ?? 'ไม่ระบุ') ?></td>
                    <td class="px-4 py-2"><?= date('d/m/Y H:i', strtotime($maint['updated_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
