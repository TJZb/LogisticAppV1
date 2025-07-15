<?php
session_start();
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['employee', 'manager', 'admin']);

$conn = connect_db();
?>
<?php include '../container/header.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกรถเพื่อเติมน้ำมัน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
<div class="container mx-auto py-10">
    <h1 class="text-3xl font-bold mb-8 text-center text-[#f9fafb]">เลือกรถเพื่อเริ่มรายการเติมน้ำมัน</h1>
    <!-- เพิ่มช่องค้นหา -->
    <div class="mb-6 flex justify-center">
        <input id="carSearch" type="text" placeholder="ค้นหาทะเบียน/ยี่ห้อ/รุ่น" class="rounded-lg px-4 py-2 w-full max-w-md bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:ring-2 focus:ring-[#4ade80]">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8" id="carGrid">
    <?php
    // แสดงรถทุกคันที่สถานะ active และไม่ใช่รถพ่วง จากฐานข้อมูลใหม่
    $stmt = $conn->prepare("
        SELECT v.license_plate, vb.brand_name, v.model_name, vc.category_name
        FROM vehicles v
        LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
        LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
        WHERE v.status = 'active' 
        AND v.is_deleted = 0
        AND (vc.category_code != 'TRAILER' OR vc.category_code IS NULL)
        ORDER BY v.license_plate
    ");
    $stmt->execute();
    $found = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $found = true;
        $img = "https://source.unsplash.com/400x200/?car&sig=" . rand(1,1000);
        echo '
        <div class="transition-all duration-200 bg-[#1f2937] rounded-2xl shadow-xl p-5 flex flex-col items-center border-2 border-transparent hover:border-[#4ade80] hover:scale-105 group focus-within:border-[#4ade80]">
            <img src="'.$img.'" alt="car" class="w-40 h-24 object-cover rounded-lg mb-4 shadow-md">
            <div class="w-full flex-1 text-center">
                <div class="text-xl font-bold text-[#60a5fa] mb-1">ทะเบียน: '.htmlspecialchars($row['license_plate']).'</div>
                <div class="text-[#9ca3af] mb-2">ยี่ห้อ: '.htmlspecialchars($row['brand_name'] ?? 'ไม่ระบุ').'</div>
                <div class="text-[#9ca3af] mb-2">รุ่น: '.htmlspecialchars($row['model_name']).'</div>
                <div class="text-[#9ca3af] mb-4">ประเภท: '.htmlspecialchars($row['category_name'] ?? 'ไม่ระบุ').'</div>
                <a href="Addfeal_form.php?license_plate='.urlencode($row['license_plate']).'"
                   class="transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-semibold px-5 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#4ade80] inline-block"
                   aria-label="เริ่มเติมน้ำมันสำหรับ '.htmlspecialchars($row['license_plate']).'">
                   เริ่มเติมน้ำมัน
                </a>
            </div>
        </div>
        ';
    }
    ?>
    </div>
    <script>
    document.getElementById('carSearch').addEventListener('input', function() {
        const val = this.value.toLowerCase();
        document.querySelectorAll('#carGrid > div').forEach(card => {
            card.style.display = card.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });
    </script>
    <?php
    // ถ้าไม่มีรถ
    if (!$found) {
        echo '<div class="col-span-full text-center text-[#f87171] font-bold text-xl">ไม่พบรถที่พร้อมใช้งาน</div>';
    }
    ?>
</div>
</body>
</html>