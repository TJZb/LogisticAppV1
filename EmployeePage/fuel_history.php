<?php
require_once __DIR__ . '/../service/connect.php';
require_once __DIR__ . '/../includes/auth.php';
auth(['employee', 'manager', 'admin']);
session_start();
$conn = connect_db();

// --- SQL Query: Combine and use LIMIT 1 for MySQL ---
function getFuelRecords($conn, $role, $employee_id = null) {
    $baseSelect = "SELECT f.*, v.license_plate, v.department, v.current_mileage AS main_current_mileage, e.first_name, e.last_name,
        t.license_plate AS trailer_license_plate, t.current_mileage AS trailer_current_mileage,
        (SELECT file_path FROM FuelReceiptAttachments WHERE fuel_record_id = f.fuel_record_id AND attachment_type = 'gauge_before') AS gauge_before_img,
        (SELECT file_path FROM FuelReceiptAttachments WHERE fuel_record_id = f.fuel_record_id AND attachment_type = 'gauge_after') AS gauge_after_img,
        (SELECT file_path FROM FuelReceiptAttachments WHERE fuel_record_id = f.fuel_record_id AND attachment_type = 'receipt' ) AS receipt_file
        FROM FuelRecords f
        JOIN Vehicles v ON f.vehicle_id = v.vehicle_id
        LEFT JOIN Vehicles t ON f.trailer_vehicle_id = t.vehicle_id
        LEFT JOIN Employees e ON f.recorded_by_employee_id = e.employee_id
        WHERE f.status IN ('pending', 'approved')";
    if ($role === 'admin' || $role === 'manager') {
        $sql = $baseSelect . " ORDER BY f.fuel_date DESC";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = $baseSelect . " AND f.recorded_by_employee_id = ? ORDER BY f.fuel_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$role = $_SESSION['role'];
$employee_id = $_SESSION['employee_id'] ?? null;
$records = getFuelRecords($conn, $role, $employee_id);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการเติมน้ำมัน</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
    </style>
</head>
<?php include '../container/header.php'; ?>
<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
<div class="container mx-auto py-10">
    <h1 class="text-3xl font-bold mb-8 text-center text-[#f9fafb]">ประวัติการเติมน้ำมัน</h1>
    <!-- ช่องค้นหาและกรอง -->
    <div class="flex flex-col md:flex-row gap-4 mb-6 justify-between items-center">
        <input id="searchInput" type="text" placeholder="ค้นหาทะเบียนรถ, สถานะ, หรือหมายเหตุ" class="rounded-lg px-4 py-2 w-full md:w-1/5 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:ring-2 focus:ring-[#4ade80]">
        <?php
        // ดึงรายการสังกัดที่มีในข้อมูล
        if ($role === 'admin' || $role === 'manager') {
            $departments = $conn->query("SELECT DISTINCT v.department FROM FuelRecords f JOIN Vehicles v ON f.vehicle_id = v.vehicle_id WHERE v.department IS NOT NULL AND v.department <> '' ORDER BY v.department ASC")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT v.department FROM FuelRecords f JOIN Vehicles v ON f.vehicle_id = v.vehicle_id WHERE f.recorded_by_employee_id = ? AND v.department IS NOT NULL AND v.department <> '' ORDER BY v.department ASC");
            $stmt->execute([$employee_id]);
            $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        ?>
        <select id="departmentFilter" class="rounded-lg px-4 py-2 w-full md:w-1/5 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:ring-2 focus:ring-[#4ade80]">
            <option value="">ทุกสังกัด</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?=htmlspecialchars($dept)?>"><?=htmlspecialchars($dept)?></option>
            <?php endforeach; ?>
        </select>
        <input id="dateStart" type="date" class="rounded-lg px-4 py-2 w-full md:w-1/5 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:ring-2 focus:ring-[#4ade80]" placeholder="วันที่เริ่มต้น">
        <input id="dateEnd" type="date" class="rounded-lg px-4 py-2 w-full md:w-1/5 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:ring-2 focus:ring-[#4ade80]" placeholder="วันที่สิ้นสุด">
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
        <div class="flex gap-2 w-full md:w-auto">
            <select id="exportFormat" class="rounded-lg px-4 py-2 bg-[#111827] border border-[#374151] text-[#e0e0e0] focus:ring-2 focus:ring-[#4ade80]">
                <option value="xlsx">Excel (.xlsx)</option>
                <option value="ods">LibreOffice (.ods)</option>
                <option value="csv">CSV (.csv)</option>
                <option value="pdf">PDF (.pdf)</option>
            </select>
            <button id="exportBtn" onclick="exportData()" class="transition-all duration-150 bg-[#facc15] hover:bg-[#fbbf24] text-[#111827] font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#facc15]">Export</button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- แสดงสถานะตัวกรอง -->
    <div id="filterStatus" class="mb-4 text-sm text-[#94a3b8] hidden">
        <span id="filterStatusText"></span>
    </div>
    
    <div class="overflow-x-auto">
        <table id="fuelTable" class="min-w-full bg-[#1f2937] rounded-2xl shadow-xl overflow-hidden">
            <thead>
                <tr class="bg-gradient-to-r from-[#6366f1] to-[#4ade80] text-[#111827]">
                    <th class="px-4 py-3 text-left font-bold">วันที่</th>
                    <th class="px-4 py-3 text-left font-bold">ทะเบียนรถ</th>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
                        <th class="px-4 py-3 text-left font-bold">ผู้ทำรายการ</th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-left font-bold">สังกัด</th>
                    <th class="px-4 py-3 text-left font-bold">สถานะ</th>
                    <th class="px-4 py-3 text-left font-bold">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($records as $i => $rec): ?>
                <tr class="even:bg-[#111827] odd:bg-[#1f2937] hover:bg-[#374151] transition-colors cursor-pointer" onclick="showDetail(<?= $i ?>)">
                    <td class="px-4 py-2"><?=date('Y-m-d H:i', strtotime($rec['fuel_date']))?></td>
                    <td class="px-4 py-2"><?=htmlspecialchars($rec['license_plate'])?></td>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
                        <td class="px-4 py-2">
                            <?php
                                if (!empty($rec['first_name']) || !empty($rec['last_name'])) {
                                    echo htmlspecialchars(trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')));
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                    <?php endif; ?>
                    <td class="px-4 py-2"><?=htmlspecialchars($rec['department'] ?? '-')?></td>
                    <td class="px-4 py-2">
                        <?php
                        $statusText = '';
                        $statusClass = '';
                        switch($rec['status']) {
                            case 'pending':
                                $statusText = 'รออนุมัติ';
                                $statusClass = 'text-yellow-400';
                                break;
                            case 'approved':
                                $statusText = 'อนุมัติแล้ว';
                                $statusClass = 'text-green-400';
                                break;
                            case 'rejected':
                                $statusText = 'ไม่อนุมัติ';
                                $statusClass = 'text-red-400';
                                break;
                            case 'cancelled':
                                $statusText = 'ยกเลิก';
                                $statusClass = 'text-red-400';
                                break;
                            default:
                                $statusText = htmlspecialchars($rec['status'] ?? '-');
                                $statusClass = '';
                        }
                        ?>
                        <span class="<?=$statusClass?>"><?=$statusText?></span>
                    </td>
                    <td class="px-4 py-2">
                        <?php
                        $notes = $rec['notes'] ?? '';
                        
                        // ลบข้อมูลระยะวิ่งออกจากหมายเหตุเพื่อให้เหลือแต่เหตุผลหรือคอมเมนต์ที่เป็นประโยชน์
                        $displayNotes = preg_replace('/\[ระยะวิ่งรถหลัก:.*?\]/', '', $notes);
                        $displayNotes = preg_replace('/\[ระยะวิ่งรวม:.*?\]/', '', $displayNotes);
                        $displayNotes = preg_replace('/\[ระยะวิ่งรถพ่วง:.*?\]/', '', $displayNotes);
                        $displayNotes = trim($displayNotes);
                        
                        // ถ้าไม่มีหมายเหตุที่เป็นประโยชน์ แสดง -
                        if (empty($displayNotes)) {
                            echo '-';
                        } else {
                            // แสดงหมายเหตุที่เป็นประโยชน์ เช่น เหตุผลในการยกเลิก, คอมเมนต์
                            echo htmlspecialchars($displayNotes);
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Popup Modal -->
<div id="detailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-[#1f2937] rounded-2xl shadow-xl p-8 max-w-md w-full relative text-[#e0e0e0]">
        <button onclick="closeDetail()" class="absolute top-2 right-2 text-2xl text-[#f87171] hover:text-[#ef4444] font-bold">&times;</button>
        <h2 class="text-xl font-bold mb-4 text-[#60a5fa]">รายละเอียดการเติมน้ำมัน</h2>
        <div id="modalContent"></div>
    </div>
</div>
<script>
const records = <?= json_encode($records, JSON_UNESCAPED_UNICODE) ?>;

function showDetail(idx) {
    const rec = records[idx];
    let liters = '-';
    if (rec.total_cost && rec.cost_per_liter && Number(rec.cost_per_liter) > 0) {
        liters = (Number(rec.total_cost) / Number(rec.cost_per_liter)).toFixed(2);
    } else if (rec.liters) {
        liters = escapeHtml(rec.liters);
    }

    // หลักฐานแนวนอน ขนาดเล็ก คลิกดูใหญ่
    let evidenceHtml = '';
    let images = [];
    if (rec.gauge_before_img && rec.gauge_before_img !== "null" && rec.gauge_before_img !== "") {
        images.push({
            label: "เกจน้ำมันก่อนเติม",
            src: `../uploads/${escapeHtml(rec.gauge_before_img)}`
        });
    }
    if (rec.gauge_after_img && rec.gauge_after_img !== "null" && rec.gauge_after_img !== "") {
        images.push({
            label: "เกจน้ำมันหลังเติม",
            src: `../uploads/${escapeHtml(rec.gauge_after_img)}`
        });
    }
    if (rec.receipt_file && rec.receipt_file !== "null" && rec.receipt_file !== "") {
        const ext = rec.receipt_file.split('.').pop().toLowerCase();
        if (['jpg','jpeg','png','gif','bmp','webp'].includes(ext)) {
            images.push({
                label: "ใบเสร็จ",
                src: `../uploads/${escapeHtml(rec.receipt_file)}`
            });
        }
    }
    if (images.length > 0) {
        evidenceHtml += `<div class="flex flex-row gap-2 justify-center mt-4">`;
        images.forEach(img => {
            evidenceHtml += `
                <div class="text-center">
                    <div class="text-xs mb-1">${img.label}</div>
                    <a href="${img.src}" target="_blank">
                        <img src="${img.src}" alt="${img.label}" class="inline-block h-16 w-auto rounded border border-[#374151] shadow hover:scale-150 transition-transform duration-200" style="object-fit:contain;"/>
                    </a>
                </div>
            `;
        });
        evidenceHtml += `</div>`;
    }
    // PDF ใบเสร็จ (ถ้ามี)
    if (rec.receipt_file && rec.receipt_file !== "null" && rec.receipt_file !== "") {
        const ext = rec.receipt_file.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
            evidenceHtml += `<div class="text-center mt-2"><span class="font-semibold">ใบเสร็จ:</span><br>
                <a href="../uploads/${escapeHtml(rec.receipt_file)}" target="_blank" class="text-[#60a5fa] underline">เปิดไฟล์ PDF</a></div>`;
        }
    }

    let html = `
        <div class="mb-2"><span class="font-semibold">วันที่:</span> ${formatDate(rec.fuel_date)}</div>
        <div class="mb-2"><span class="font-semibold">ทะเบียนรถ:</span> ${escapeHtml(rec.license_plate)}</div>
        ${rec.trailer_license_plate ? `<div class=\"mb-2\"><span class=\"font-semibold text-yellow-400\">รถพ่วง:</span> ${escapeHtml(rec.trailer_license_plate)}</div>` : ''}
        <div class="mb-2"><span class="font-semibold">เลขไมล์ (ขณะเติม):</span> ${escapeHtml(rec.mileage_at_fuel)}</div>
        ${rec.trailer_license_plate && rec.trailer_mileage_at_fuel ? `<div class=\"mb-2\"><span class=\"font-semibold text-yellow-400\">เลขไมล์รถพ่วง (ขณะเติม):</span> ${escapeHtml(rec.trailer_mileage_at_fuel)}</div>` : ''}
        <div class="mb-2"><span class="font-semibold">เลขไมล์ปัจจุบัน (รถหลัก):</span> ${escapeHtml(rec.main_current_mileage ?? '-')}</div>
        ${rec.trailer_license_plate ? `<div class=\"mb-2\"><span class=\"font-semibold text-yellow-400\">เลขไมล์ปัจจุบัน (รถพ่วง):</span> ${escapeHtml(rec.trailer_current_mileage ?? '-')}</div>` : ''}
        <div class="mb-2"><span class="font-semibold">ชนิดน้ำมัน:</span> ${escapeHtml(rec.fuel_type ?? '-')}</div>
        <div class="mb-2"><span class="font-semibold">จำนวนที่เติม (ลิตร):</span> ${liters}</div>
        <div class="mb-2"><span class="font-semibold">ราคาต่อหน่วย:</span> ${escapeHtml(rec.cost_per_liter ?? '-')}</div>
        <div class="mb-2"><span class="font-semibold">ราคารวม:</span> ${escapeHtml(rec.total_cost ?? '-')}</div>
        ${rec.first_name ? `<div class=\"mb-2\"><span class=\"font-semibold\">ผู้บันทึก:</span> ${escapeHtml(rec.first_name + ' ' + rec.last_name)}</div>` : ''}
        <div class="mb-2"><span class="font-semibold">สถานะ:</span> ${getStatusDisplay(rec.status)}</div>
        ${getNotesDisplay(rec.notes, rec.status)}
        ${evidenceHtml}
    `;
    document.getElementById('modalContent').innerHTML = html;
    document.getElementById('detailModal').classList.remove('hidden');
}
function closeDetail() {
    document.getElementById('detailModal').classList.add('hidden');
}
function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/[&<>"']/g, function(m) {
        return ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[m];
    });
}
function formatDate(dateStr) {
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.getFullYear() + '-' +
        String(d.getMonth() + 1).padStart(2, '0') + '-' +
        String(d.getDate()).padStart(2, '0') + ' ' +
        String(d.getHours()).padStart(2, '0') + ':' +
        String(d.getMinutes()).padStart(2, '0');
}

function getStatusDisplay(status) {
    switch(status) {
        case 'pending':
            return '<span class="text-yellow-400 font-semibold">รออนุมัติ</span>';
        case 'approved':
            return '<span class="text-green-400 font-semibold">อนุมัติแล้ว</span>';
        case 'rejected':
            return '<span class="text-red-400 font-semibold">ไม่อนุมัติ</span>';
        case 'cancelled':
            return '<span class="text-red-400 font-semibold">ยกเลิก</span>';
        default:
            return escapeHtml(status ?? '-');
    }
}

function getNotesDisplay(notes, status) {
    if (!notes || notes === '-') {
        return '<div class="mb-2"><span class="font-semibold">หมายเหตุ:</span> -</div>';
    }
    
    // แยกข้อมูลระยะวิ่งออกมาแสดงแยก
    let distanceInfo = '';
    const mainDistanceMatch = notes.match(/\[ระยะวิ่งรถหลัก:\s*([^\]]+)\]/);
    const trailerDistanceMatch = notes.match(/\[ระยะวิ่งรถพ่วง:\s*([^\]]+)\]/);
    
    if (mainDistanceMatch || trailerDistanceMatch) {
        distanceInfo += '<div class="mb-2 bg-blue-900 bg-opacity-30 p-2 rounded border-l-4 border-blue-400">';
        distanceInfo += '<span class="font-semibold text-blue-300">ข้อมูลระยะทาง:</span><br>';
        if (mainDistanceMatch) {
            distanceInfo += `<span class="text-sm">• รถหลัก: ${escapeHtml(mainDistanceMatch[1])}</span><br>`;
        }
        if (trailerDistanceMatch) {
            distanceInfo += `<span class="text-sm text-yellow-300">• รถพ่วง: ${escapeHtml(trailerDistanceMatch[1])}</span>`;
        }
        distanceInfo += '</div>';
    }
    
    // ลบข้อมูลระยะวิ่งออกจากหมายเหตุเพื่อให้เหลือแต่เหตุผลหรือคอมเมนต์ที่เป็นประโยชน์
    let displayNotes = notes;
    displayNotes = displayNotes.replace(/\[ระยะวิ่งรถหลัก:.*?\]/g, '');
    displayNotes = displayNotes.replace(/\[ระยะวิ่งรวม:.*?\]/g, '');
    displayNotes = displayNotes.replace(/\[ระยะวิ่งรถพ่วง:.*?\]/g, '');
    displayNotes = displayNotes.trim();
    
    let notesHtml = '';
    if (!displayNotes) {
        notesHtml = '<div class="mb-2"><span class="font-semibold">หมายเหตุ:</span> -</div>';
    } else {
        notesHtml = `<div class="mb-2"><span class="font-semibold">หมายเหตุ:</span> ${escapeHtml(displayNotes)}</div>`;
    }
    
    return distanceInfo + notesHtml;
}

// ฟิลเตอร์ตาราง
const searchInput = document.getElementById('searchInput');
const departmentFilter = document.getElementById('departmentFilter');
const dateStart = document.getElementById('dateStart');
const dateEnd = document.getElementById('dateEnd');
const table = document.getElementById('fuelTable');
searchInput.addEventListener('input', filterTable);
departmentFilter.addEventListener('change', filterTable);
dateStart.addEventListener('change', filterTable);
dateEnd.addEventListener('change', filterTable);

function filterTable() {
    const search = searchInput.value.toLowerCase();
    const department = departmentFilter.value;
    const start = dateStart.value;
    const end = dateEnd.value;
    let visibleCount = 0;
    
    for (const row of table.tBodies[0].rows) {
        const plate = row.cells[1].innerText.toLowerCase();
        const dept = row.cells[<?= $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager' ? '3' : '2' ?>].innerText;
        const status = row.cells[<?= $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager' ? '4' : '3' ?>].innerText.toLowerCase();
        const notes = row.cells[<?= $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager' ? '5' : '4' ?>].innerText.toLowerCase();
        const dateText = row.cells[0].innerText.slice(0, 10); // yyyy-mm-dd
        let show = true;
        
        // ค้นหาในทะเบียนรถ, สถานะ, หมายเหตุ
        if (search && !(plate.includes(search) || status.includes(search) || notes.includes(search))) show = false;
        if (department && dept !== department) show = false;
        if (start && dateText < start) show = false;
        if (end && dateText > end) show = false;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    }
    
    // แสดงสถานะตัวกรอง
    updateFilterStatus(search, department, start, end, visibleCount);
}

function updateFilterStatus(search, department, start, end, visibleCount) {
    const filterStatus = document.getElementById('filterStatus');
    const filterStatusText = document.getElementById('filterStatusText');
    
    let statusMessages = [];
    if (search) statusMessages.push(`ค้นหา: "${search}"`);
    if (department) statusMessages.push(`สังกัด: ${department}`);
    if (start || end) {
        const dateRange = start && end ? `${start} ถึง ${end}` : 
                         start ? `ตั้งแต่ ${start}` : `จนถึง ${end}`;
        statusMessages.push(`วันที่: ${dateRange}`);
    }
    
    if (statusMessages.length > 0) {
        filterStatusText.textContent = `ตัวกรองที่ใช้: ${statusMessages.join(' | ')} | แสดง ${visibleCount} รายการ`;
        filterStatus.classList.remove('hidden');
    } else {
        filterStatus.classList.add('hidden');
    }
}

// Export Data (เฉพาะ manager/admin และเฉพาะข้อมูลที่ filter)
<?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager'): ?>
function exportData() {
    const format = document.getElementById('exportFormat').value;
    switch(format) {
        case 'xlsx':
            exportToExcel();
            break;
        case 'ods':
            exportToODS();
            break;
        case 'csv':
            exportToCSV();
            break;
        case 'pdf':
            exportToPDF();
            break;
        default:
            exportToExcel();
    }
}

// ฟังก์ชันสำหรับกรองข้อมูลตามฟิลเตอร์ทั้งหมด
function getFilteredRecords() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const department = document.getElementById('departmentFilter').value;
    const start = document.getElementById('dateStart').value;
    const end = document.getElementById('dateEnd').value;
    
    return records.filter(function(rec) {
        // ตรวจสอบการค้นหาในทะเบียนรถ, สถานะ, หมายเหตุ
        const plate = (rec.license_plate || '').toLowerCase();
        const status = getStatusText(rec.status).toLowerCase();
        const notes = getCleanNotes(rec.notes || '').toLowerCase();
        const searchMatch = !search || plate.includes(search) || status.includes(search) || notes.includes(search);
        
        // ตรวจสอบสังกัด
        const departmentMatch = !department || rec.department === department;
        
        // ตรวจสอบช่วงวันที่
        const dateText = new Date(rec.fuel_date).toISOString().slice(0, 10); // yyyy-mm-dd
        const startMatch = !start || dateText >= start;
        const endMatch = !end || dateText <= end;
        
        return searchMatch && departmentMatch && startMatch && endMatch;
    });
}

// ฟังก์ชันสำหรับแปลงสถานะเป็นข้อความ
function getStatusText(status) {
    switch(status) {
        case 'pending': return 'รออนุมัติ';
        case 'approved': return 'อนุมัติแล้ว';
        case 'rejected': return 'ไม่อนุมัติ';
        case 'cancelled': return 'ยกเลิก';
        default: return status || '';
    }
}

// ฟังก์ชันสำหรับทำความสะอาดหมายเหตุ (เอาข้อมูลระยะวิ่งออก)
function getCleanNotes(notes) {
    let displayNotes = notes;
    displayNotes = displayNotes.replace(/\[ระยะวิ่งรถหลัก:.*?\]/g, '');
    displayNotes = displayNotes.replace(/\[ระยะวิ่งรวม:.*?\]/g, '');
    displayNotes = displayNotes.replace(/\[ระยะวิ่งรถพ่วง:.*?\]/g, '');
    return displayNotes.trim();
}

// ฟังก์ชันสำหรับสร้างข้อความตัวกรอง
function getFilterDescription() {
    const search = document.getElementById('searchInput').value;
    const department = document.getElementById('departmentFilter').value;
    const dateStart = document.getElementById('dateStart').value;
    const dateEnd = document.getElementById('dateEnd').value;
    
    let filterParts = [];
    if (search) filterParts.push(`ค้นหา: ${search}`);
    if (department) filterParts.push(`สังกัด: ${department}`);
    if (dateStart || dateEnd) {
        const dateRange = dateStart && dateEnd ? `${dateStart} ถึง ${dateEnd}` : 
                         dateStart ? `ตั้งแต่ ${dateStart}` : `จนถึง ${dateEnd}`;
        filterParts.push(`วันที่: ${dateRange}`);
    }
    
    return filterParts.length > 0 ? `ตัวกรอง: ${filterParts.join(' | ')}` : '';
}

// ฟังก์ชันรวมสำหรับเตรียมข้อมูล Excel/ODS
function getExcelData() {
    // หัวรายงาน
    const selectedDepartment = document.getElementById('departmentFilter').value;
    const orgName = selectedDepartment || "รวมทุกรายการ";
    const reportTitle = `รายการน้ำมัน${orgName}`;
    const reportSub = 'สำเนารายการจากสลิปน้ำมัน';
    const headers = [
        'วันที่', 'เวลา', 'ทะเบียนรถ', 'เลขไมล์', 'ชนิดน้ำมัน', 'ราคาต่อลิตร', 'ลิตร', 'ราคารวม',
        'ระยะที่วิ่ง(กม.)', 'เฉลี่ยบาทต่อกม.', 'เฉลี่ยกม.ต่อลิตร', 'เฉลี่ยลิตรต่อกม.'
    ];
    
    // ใช้ฟังก์ชันกรองข้อมูลใหม่
    const filteredRecords = getFilteredRecords();
    
    let dataRows = [];
    
    // --- กลุ่มรถหลัก ---
    const mainGrouped = {};
    filteredRecords.forEach(function(rec) {
        const plate = rec.license_plate ?? '';
        if (!mainGrouped[plate]) mainGrouped[plate] = [];
        mainGrouped[plate].push(rec);
    });
    
    Object.keys(mainGrouped).forEach(function(plate) {
        // เรียงข้อมูลแต่ละกลุ่มรถจากวันที่เก่าสุดไปใหม่สุด
        mainGrouped[plate].sort(function(a, b) {
            return new Date(a.fuel_date) - new Date(b.fuel_date);
        });
        
        // เพิ่มหัวกลุ่มรถ
        dataRows.push({
            type: 'group-header',
            text: `ทะเบียนรถ: ${plate}`,
            colspan: headers.length
        });
        
        // เพิ่มหัวตาราง
        dataRows.push({
            type: 'table-header',
            cells: headers
        });
        
        // คำนวณระยะวิ่งต่อรอบ = เลขไมล์ปัจจุบัน - เลขไมล์ย้อนหลังล่าสุดของรถคันเดียวกัน
        const plateRecords = mainGrouped[plate] || [];
        let sumLiters = 0, sumCost = 0, sumDistance = 0, countDistance = 0;
        
        plateRecords.forEach(function(rec, idx) {
            const d = new Date(rec.fuel_date);
            const date = isNaN(d) ? '' : d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            const time = isNaN(d) ? '' : String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
            const mileage = rec.mileage_at_fuel ?? '';
            const fuelType = rec.fuel_type ?? '';
            const costPerLiter = rec.cost_per_liter ?? '';
            
            let liters = '';
            if (rec.total_cost && rec.cost_per_liter && Number(rec.cost_per_liter) > 0) {
                liters = (Number(rec.total_cost) / Number(rec.cost_per_liter)).toFixed(2);
            } else if (rec.liters) {
                liters = rec.liters;
            }
            const totalCost = rec.total_cost ?? '';
            
            let mainDistance = '';
            let prevMileage = '';
            for (let j = idx - 1; j >= 0; j--) {
                if (plateRecords[j].mileage_at_fuel && !isNaN(Number(plateRecords[j].mileage_at_fuel))) {
                    prevMileage = plateRecords[j].mileage_at_fuel;
                    break;
                }
            }
            if (rec.mileage_at_fuel && prevMileage !== '' && !isNaN(Number(rec.mileage_at_fuel))) {
                mainDistance = Number(rec.mileage_at_fuel) - Number(prevMileage);
                if (mainDistance < 0) mainDistance = '';
            }
            if (mainDistance === '' && rec.notes) {
                // อ่านค่าระยะวิ่งจาก notes ที่บันทึกไว้ใน orderlist.php
                const matchMain = rec.notes.match(/ระยะวิ่งรถหลัก:\s*([\d.]+)/);
                if (matchMain) {
                    mainDistance = matchMain[1];
                }
            }
            
            // --- สะสมยอดรวม ---
            if (liters && !isNaN(Number(liters))) sumLiters += Number(liters);
            if (totalCost && !isNaN(Number(totalCost))) sumCost += Number(totalCost);
            if (mainDistance && !isNaN(Number(mainDistance))) { sumDistance += Number(mainDistance); countDistance++; }
            
            // --- เฉลี่ย 3 ช่องท้าย ---
            // หาลิตรจากครั้งก่อน (น้ำมันที่ใช้ไปจริง)
            let prevLiters = '';
            for (let j = idx - 1; j >= 0; j--) {
                const prevRec = plateRecords[j];
                if (prevRec.total_cost && prevRec.cost_per_liter && Number(prevRec.cost_per_liter) > 0) {
                    prevLiters = (Number(prevRec.total_cost) / Number(prevRec.cost_per_liter)).toFixed(2);
                    break;
                } else if (prevRec.liters) {
                    prevLiters = prevRec.liters;
                    break;
                }
            }
            
            let avgBahtPerKm = '';
            if (mainDistance && totalCost) avgBahtPerKm = (Number(totalCost) / Number(mainDistance)).toFixed(2);
            let avgKmPerLiter = '';
            if (mainDistance && prevLiters) avgKmPerLiter = (Number(mainDistance) / Number(prevLiters)).toFixed(2);
            let avgLiterPerKm = '';
            if (mainDistance && prevLiters) avgLiterPerKm = (Number(prevLiters) / Number(mainDistance)).toFixed(4);
            
            let row = [date, time, rec.license_plate ?? '', mileage, fuelType, costPerLiter, liters, totalCost,
                mainDistance, avgBahtPerKm, avgKmPerLiter, avgLiterPerKm
            ];
            
            dataRows.push({
                type: 'data-row',
                cells: row
            });
        });
        
        // --- แสดงยอดรวมใต้ตาราง ---
        let sumAvgBahtPerKm = (sumDistance && sumCost) ? (sumCost / sumDistance).toFixed(2) : '';
        let sumAvgKmPerLiter = (sumLiters && sumDistance) ? (sumDistance / sumLiters).toFixed(2) : '';
        let sumAvgLiterPerKm = (sumLiters && sumDistance) ? (sumLiters / sumDistance).toFixed(4) : '';
        
        dataRows.push({
            type: 'summary-row',
            cells: ['รวม', '', '', '', '', '', sumLiters.toFixed(2), sumCost.toFixed(2), sumDistance.toFixed(2), sumAvgBahtPerKm, sumAvgKmPerLiter, sumAvgLiterPerKm]
        });
    });
    
    // --- กลุ่มรถพ่วง ---
    const trailerGrouped = {};
    filteredRecords.forEach(function(rec) {
        const trailerPlate = rec.trailer_license_plate ?? '';
        if (trailerPlate && trailerPlate.trim() !== '') {
            if (!trailerGrouped[trailerPlate]) trailerGrouped[trailerPlate] = [];
            let trailerRec = Object.assign({}, rec);
            trailerRec.license_plate = trailerPlate;
            trailerRec.trailer_license_plate = '';
            trailerGrouped[trailerPlate].push(trailerRec);
        }
    });
    
    Object.keys(trailerGrouped).forEach(function(trailerPlate) {
        // เรียงข้อมูลแต่ละกลุ่มรถพ่วงจากวันที่เก่าสุดไปใหม่สุด
        trailerGrouped[trailerPlate].sort(function(a, b) {
            return new Date(a.fuel_date) - new Date(b.fuel_date);
        });
        
        // เพิ่มหัวกลุ่มรถพ่วง
        dataRows.push({
            type: 'group-header',
            text: `ทะเบียนรถพ่วง: ${trailerPlate}`,
            colspan: headers.length
        });
        
        // เพิ่มหัวตาราง
        dataRows.push({
            type: 'table-header',
            cells: headers
        });
        
        let sumLiters = 0, sumCost = 0, sumDistance = 0, countDistance = 0;
        
        trailerGrouped[trailerPlate].forEach(function(rec, idx) {
            const d = new Date(rec.fuel_date);
            const date = isNaN(d) ? '' : d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            const time = isNaN(d) ? '' : String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
            // ใช้เลขไมล์รถพ่วงจากฟิลด์ trailer_mileage_at_fuel แทน mileage_at_fuel
            const mileage = rec.trailer_mileage_at_fuel ?? '';
            const fuelType = rec.fuel_type ?? '';
            const costPerLiter = rec.cost_per_liter ?? '';
            
            let liters = '';
            if (rec.total_cost && rec.cost_per_liter && Number(rec.cost_per_liter) > 0) {
                liters = (Number(rec.total_cost) / Number(rec.cost_per_liter)).toFixed(2);
            } else if (rec.liters) {
                liters = rec.liters;
            }
            const totalCost = rec.total_cost ?? '';
            
            // คำนวณระยะวิ่งรถพ่วงจากการเปรียบเทียบเลขไมล์รถพ่วงขณะเติม
            let trailerDistance = '';
            let prevTrailerMileage = '';
            for (let j = idx - 1; j >= 0; j--) {
                if (trailerGrouped[trailerPlate][j].trailer_mileage_at_fuel && !isNaN(Number(trailerGrouped[trailerPlate][j].trailer_mileage_at_fuel))) {
                    prevTrailerMileage = trailerGrouped[trailerPlate][j].trailer_mileage_at_fuel;
                    break;
                }
            }
            if (rec.trailer_mileage_at_fuel && prevTrailerMileage !== '' && !isNaN(Number(rec.trailer_mileage_at_fuel))) {
                trailerDistance = Number(rec.trailer_mileage_at_fuel) - Number(prevTrailerMileage);
                if (trailerDistance < 0) trailerDistance = '';
            }
            
            // ถ้าไม่มีข้อมูลใน trailer_mileage_at_fuel ให้อ่านจาก notes
            if (trailerDistance === '' && rec.notes) {
                const matchTrailer = rec.notes.match(/ระยะวิ่งรถพ่วง:\s*([\d.]+)/);
                if (matchTrailer) {
                    trailerDistance = matchTrailer[1];
                }
            }
            
            // --- สะสมยอดรวม ---
            if (liters && !isNaN(Number(liters))) sumLiters += Number(liters);
            if (totalCost && !isNaN(Number(totalCost))) sumCost += Number(totalCost);
            if (trailerDistance && !isNaN(Number(trailerDistance))) { sumDistance += Number(trailerDistance); countDistance++; }
            
            // --- เฉลี่ย 3 ช่องท้าย ---
            // หาลิตรจากครั้งก่อน (น้ำมันที่ใช้ไปจริง) สำหรับรถพ่วง
            let prevLiters = '';
            for (let j = idx - 1; j >= 0; j--) {
                const prevRec = trailerGrouped[trailerPlate][j];
                if (prevRec.total_cost && prevRec.cost_per_liter && Number(prevRec.cost_per_liter) > 0) {
                    prevLiters = (Number(prevRec.total_cost) / Number(prevRec.cost_per_liter)).toFixed(2);
                    break;
                } else if (prevRec.liters) {
                    prevLiters = prevRec.liters;
                    break;
                }
            }
            
            let avgBahtPerKm = '';
            if (trailerDistance && totalCost) avgBahtPerKm = (Number(totalCost) / Number(trailerDistance)).toFixed(2);
            let avgKmPerLiter = '';
            if (trailerDistance && prevLiters) avgKmPerLiter = (Number(trailerDistance) / Number(prevLiters)).toFixed(2);
            let avgLiterPerKm = '';
            if (trailerDistance && prevLiters) avgLiterPerKm = (Number(prevLiters) / Number(trailerDistance)).toFixed(4);
            
            let row = [date, time, trailerPlate, mileage, fuelType, costPerLiter, liters, totalCost,
                trailerDistance, avgBahtPerKm, avgKmPerLiter, avgLiterPerKm
            ];
            
            dataRows.push({
                type: 'data-row',
                cells: row
            });
        });
        
        // --- แสดงยอดรวมใต้ตาราง ---
        let sumAvgBahtPerKm = (sumDistance && sumCost) ? (sumCost / sumDistance).toFixed(2) : '';
        let sumAvgKmPerLiter = (sumLiters && sumDistance) ? (sumDistance / sumLiters).toFixed(2) : '';
        let sumAvgLiterPerKm = (sumLiters && sumDistance) ? (sumLiters / sumDistance).toFixed(4) : '';
        
        dataRows.push({
            type: 'summary-row',
            cells: ['รวม', '', '', '', '', '', sumLiters.toFixed(2), sumCost.toFixed(2), sumDistance.toFixed(2), sumAvgBahtPerKm, sumAvgKmPerLiter, sumAvgLiterPerKm]
        });
    });
    
    return {
        reportTitle: reportTitle,
        reportSub: reportSub,
        headers: headers,
        dataRows: dataRows
    };
}

function exportToExcel() {
    // ใช้ข้อมูลจากฟังก์ชันรวม
    const excelData = getExcelData();
    const filterText = getFilterDescription();
    
    // สร้าง CSV สำหรับ Excel
    let csv = [];
    const BOM = '\uFEFF';
    
    // เพิ่มหัวกระดาษ
    csv.push('<table style="font-family: Angsana New, AngsanaUPC, Tahoma, Arial, sans-serif; font-size:16pt;">');
    
    // เพิ่มข้อมูลตัวกรองที่มุมขวาบน (ถ้ามี)
    if (filterText) {
        csv.push(`<tr><td colspan="${excelData.headers.length - 3}" style="text-align:center;font-size:22pt;font-weight:bold;">${excelData.reportTitle}</td><td colspan="3" style="text-align:right;font-size:10pt;font-family:Angsana New;vertical-align:top;">${filterText}</td></tr>`);
    } else {
        csv.push(`<tr><td colspan="${excelData.headers.length}" style="text-align:center;font-size:22pt;font-weight:bold;">${excelData.reportTitle}</td></tr>`);
    }
    
    csv.push(`<tr><td colspan="${excelData.headers.length}" style="text-align:center;font-size:20pt;">${excelData.reportSub}</td></tr>`);
    
    // วนลูปข้อมูลจาก dataRows
    excelData.dataRows.forEach(function(row) {
        if (row.type === 'group-header') {
            csv.push(`<tr><td colspan="${row.colspan}"><b>${row.text}</b></td></tr>`);
        } else if (row.type === 'table-header') {
            csv.push('<tr>' + row.cells.map(h => `<th style=\"border:1px solid #888;background:#eee;\">${h}</th>`).join('') + '</tr>');
        } else if (row.type === 'data-row') {
            csv.push('<tr>' + row.cells.map(x => `<td style=\"border:1px solid #888;\">${x ?? ''}</td>`).join('') + '</tr>');
        } else if (row.type === 'summary-row') {
            csv.push('<tr>' +
                `<td colspan="6" style=\"border:1px solid #888;font-weight:bold;background:#fef08a;\">${row.cells[0]}</td>` +
                row.cells.slice(6).map(x => `<td style=\"border:1px solid #888;font-weight:bold;background:#fef08a;\">${x ?? ''}</td>`).join('') +
            '</tr>');
        }
    });
    
    csv.push('</table>');
    // สร้างไฟล์ HTML (Excel เปิดได้เป็นตาราง)
    let htmlContent = BOM + csv.join('\n');
    let blob = new Blob([htmlContent], {type: "application/vnd.ms-excel"});
    let downloadLink = document.createElement("a");
    downloadLink.download = "fuel_history.xlsx";
    downloadLink.href = window.URL.createObjectURL(blob);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

function exportToODS() {
    // ใช้ข้อมูลจากฟังก์ชันรวมเดียวกับ Excel
    const excelData = getExcelData();
    const filterText = getFilterDescription();
    
    // สร้าง ODS content XML
    let xmlDeclaration = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>';
    let odsContent = xmlDeclaration + '\n' +
'<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" \n' +
    'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" \n' +
    'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" \n' +
    'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" \n' +
    'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"\n' +
    'office:version="1.3">\n' +
'<office:automatic-styles>\n' +
    '<style:style style:name="Default" style:family="table-cell">\n' +
        '<style:table-cell-properties fo:border="0.5pt solid #000000" style:vertical-align="top"/>\n' +
        '<style:text-properties style:font-name="AngsanaUPC" fo:font-size="16pt"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="TitleStyle" style:family="table-cell" style:parent-style-name="Default">\n' +
        '<style:table-cell-properties fo:border="1.5pt solid #000000" fo:background-color="#ffffff" style:text-align-source="fix" style:repeat-content="false"/>\n' +
        '<style:paragraph-properties fo:text-align="center"/>\n' +
        '<style:text-properties style:font-name="AngsanaUPC" fo:font-weight="bold" fo:font-size="22pt"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="SubTitleStyle" style:family="table-cell" style:parent-style-name="Default">\n' +
        '<style:table-cell-properties fo:border="1.5pt solid #000000" fo:background-color="#ffffff" style:text-align-source="fix" style:repeat-content="false"/>\n' +
        '<style:paragraph-properties fo:text-align="center"/>\n' +
        '<style:text-properties style:font-name="AngsanaUPC" fo:font-weight="bold" fo:font-size="20pt"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="FilterStyle" style:family="table-cell" style:parent-style-name="Default">\n' +
        '<style:table-cell-properties fo:border="0.5pt solid #000000" fo:background-color="#ffffff" style:text-align-source="fix" style:repeat-content="false"/>\n' +
        '<style:paragraph-properties fo:text-align="right"/>\n' +
        '<style:text-properties style:font-name="Angsana New" fo:font-size="10pt"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="HeaderStyle" style:family="table-cell" style:parent-style-name="Default">\n' +
        '<style:table-cell-properties fo:background-color="#eeeeee" style:text-align-source="fix" style:repeat-content="false"/>\n' +
        '<style:paragraph-properties fo:text-align="center"/>\n' +
        '<style:text-properties style:font-name="AngsanaUPC" fo:font-weight="bold" fo:font-size="16pt"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="GroupHeaderStyle" style:family="table-cell" style:parent-style-name="Default">\n' +
        '<style:table-cell-properties fo:background-color="#e0e0e0" style:text-align-source="fix" style:repeat-content="false"/>\n' +
        '<style:paragraph-properties fo:text-align="left"/>\n' +
        '<style:text-properties style:font-name="AngsanaUPC" fo:font-weight="bold" fo:font-size="16pt"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="SummaryStyle" style:family="table-cell" style:parent-style-name="Default">\n' +
        '<style:table-cell-properties fo:background-color="#fef08a" style:text-align-source="fix" style:repeat-content="false"/>\n' +
        '<style:text-properties style:font-name="AngsanaUPC" fo:font-weight="bold" fo:font-size="16pt"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colDate" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colTime" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="1.5cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colPlate" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.5cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colMileage" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colFuelType" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colPricePerL" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colLiters" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colTotal" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colDistance" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colAvgBahtKm" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colAvgKmL" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
    '<style:style style:name="colAvgLKm" style:family="table-column">\n' +
        '<style:table-column-properties style:column-width="2.2cm"/>\n' +
    '</style:style>\n' +
'</office:automatic-styles>\n' +
'<office:body>\n' +
'<office:spreadsheet>\n' +
    '<table:table table:name="Sheet1">\n' +
        '<table:table-column table:style-name="colDate"/>\n' +
        '<table:table-column table:style-name="colTime"/>\n' +
        '<table:table-column table:style-name="colPlate"/>\n' +
        '<table:table-column table:style-name="colMileage"/>\n' +
        '<table:table-column table:style-name="colFuelType"/>\n' +
        '<table:table-column table:style-name="colPricePerL"/>\n' +
        '<table:table-column table:style-name="colLiters"/>\n' +
        '<table:table-column table:style-name="colTotal"/>\n' +
        '<table:table-column table:style-name="colDistance"/>\n' +
        '<table:table-column table:style-name="colAvgBahtKm"/>\n' +
        '<table:table-column table:style-name="colAvgKmL"/>\n' +
        '<table:table-column table:style-name="colAvgLKm"/>\n' +
        // ส่วนหัวรายงาน
        '<table:table-row>\n';
    
    // ถ้ามีข้อมูลตัวกรอง ให้แบ่ง title และ filter
    if (filterText) {
        const titleCols = excelData.headers.length - 3;
        odsContent += '<table:table-cell table:style-name="TitleStyle" table:number-columns-spanned="' + titleCols + '" office:value-type="string">\n' +
                '<text:p>' + excelData.reportTitle + '</text:p>\n' +
            '</table:table-cell>\n';
        // เพิ่ม covered cells สำหรับ title
        for (let i = 1; i < titleCols; i++) {
            odsContent += '<table:covered-table-cell/>\n';
        }
        // เพิ่มข้อมูลตัวกรองที่มุมขวาบน
        odsContent += '<table:table-cell table:style-name="FilterStyle" table:number-columns-spanned="3" office:value-type="string">\n' +
                '<text:p>' + filterText + '</text:p>\n' +
            '</table:table-cell>\n';
        // เพิ่ม covered cells สำหรับ filter
        for (let i = 1; i < 3; i++) {
            odsContent += '<table:covered-table-cell/>\n';
        }
    } else {
        odsContent += '<table:table-cell table:style-name="TitleStyle" table:number-columns-spanned="' + excelData.headers.length + '" office:value-type="string">\n' +
                '<text:p>' + excelData.reportTitle + '</text:p>\n' +
            '</table:table-cell>\n';
        // เพิ่ม covered cells สำหรับ title row
        for (let i = 1; i < excelData.headers.length; i++) {
            odsContent += '<table:covered-table-cell/>\n';
        }
    }
    
    odsContent += '</table:table-row>\n' +
        '<table:table-row>\n' +
            '<table:table-cell table:style-name="SubTitleStyle" table:number-columns-spanned="' + excelData.headers.length + '" office:value-type="string">\n' +
                '<text:p>' + excelData.reportSub + '</text:p>\n' +
            '</table:table-cell>\n';
    // เพิ่ม covered cells สำหรับ subtitle row
    for (let i = 1; i < excelData.headers.length; i++) {
        odsContent += '<table:covered-table-cell/>\n';
    }
    odsContent += '</table:table-row>\n';
    
    // วนลูปข้อมูลจาก dataRows
    excelData.dataRows.forEach(function(row) {
        if (row.type === 'group-header') {
            odsContent += '<table:table-row>\n' +
                '<table:table-cell table:style-name="GroupHeaderStyle" table:number-columns-spanned="' + row.colspan + '" office:value-type="string">\n' +
                    '<text:p>' + row.text + '</text:p>\n' +
                '</table:table-cell>\n';
            // เพิ่ม covered cells สำหรับ group header
            for (let i = 1; i < row.colspan; i++) {
                odsContent += '<table:covered-table-cell/>\n';
            }
            odsContent += '</table:table-row>\n';
        } else if (row.type === 'table-header') {
            odsContent += '<table:table-row>\n';
            row.cells.forEach(function(header) {
                odsContent += '<table:table-cell table:style-name="HeaderStyle" office:value-type="string">\n' +
                    '<text:p>' + header + '</text:p>\n' +
                '</table:table-cell>\n';
            });
            odsContent += '</table:table-row>\n';
        } else if (row.type === 'data-row') {
            odsContent += '<table:table-row>\n';
            row.cells.forEach(function(cell) {
                const value = cell ?? '';
                const isNumeric = !isNaN(Number(value)) && value !== '';
                odsContent += '<table:table-cell table:style-name="Default" office:value-type="' + (isNumeric ? 'float' : 'string') + '"' + 
                    (isNumeric ? ' office:value="' + value + '"' : '') + '>\n' +
                    '<text:p>' + value + '</text:p>\n' +
                '</table:table-cell>\n';
            });
            odsContent += '</table:table-row>\n';
        } else if (row.type === 'summary-row') {
            odsContent += '<table:table-row>\n';
            // เซลล์ "รวม" ที่ span 6 columns
            odsContent += '<table:table-cell table:style-name="SummaryStyle" table:number-columns-spanned="6" office:value-type="string">\n' +
                '<text:p>' + row.cells[0] + '</text:p>\n' +
            '</table:table-cell>\n';
            // เพิ่ม covered cells สำหรับ columns ที่ถูก span (columns 1-5)
            for (let j = 1; j < 6; j++) {
                odsContent += '<table:covered-table-cell/>\n';
            }
            // ข้อมูลสรุป (6 columns ที่เหลือ: columns 6-11)
            for (let i = 6; i < row.cells.length; i++) {
                const value = row.cells[i] ?? '';
                const isNumeric = !isNaN(Number(value)) && value !== '' && value !== 'NaN';
                odsContent += '<table:table-cell table:style-name="SummaryStyle" office:value-type="' + (isNumeric ? 'float' : 'string') + '"' + 
                    (isNumeric ? ' office:value="' + value + '"' : '') + '>\n' +
                    '<text:p>' + value + '</text:p>\n' +
                '</table:table-cell>\n';
            }
            odsContent += '</table:table-row>\n';
        }
    });
    
    odsContent += '</table:table>\n' +
'</office:spreadsheet>\n' +
'</office:body>\n' +
'</office:document-content>';
    
    // สร้างไฟล์ ODS อื่นๆ ที่จำเป็น
    const odsManifest = xmlDeclaration + '\n' +
'<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">\n' +
    '<manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>\n' +
    '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>\n' +
    '<manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>\n' +
    '<manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>\n' +
'</manifest:manifest>';

    const odsMeta = xmlDeclaration + '\n' +
'<office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" \n' +
    'xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" office:version="1.3">\n' +
'<office:meta>\n' +
    '<meta:creation-date>' + new Date().toISOString() + '</meta:creation-date>\n' +
    '<meta:generator>Fuel History System</meta:generator>\n' +
'</office:meta>\n' +
'</office:document-meta>';

    const odsStyles = xmlDeclaration + '\n' +
'<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" \n' +
    'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" \n' +
    'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" office:version="1.3">\n' +
'<office:font-face-decls>\n' +
    '<style:font-face style:name="Angsana New" svg:font-family="&apos;Angsana New&apos;" style:font-family-generic="roman" style:font-pitch="variable"/>\n' +
'</office:font-face-decls>\n' +
'<office:styles/>\n' +
'<office:automatic-styles/>\n' +
'<office:master-styles/>\n' +
'</office:document-styles>';

    // สร้างไฟล์ ZIP
    const zip = new JSZip();
    zip.file("META-INF/manifest.xml", odsManifest);
    zip.file("content.xml", odsContent);
    zip.file("meta.xml", odsMeta);
    zip.file("styles.xml", odsStyles);
    
    // ดาวน์โหลดไฟล์
    zip.generateAsync({type: "blob", mimeType: "application/vnd.oasis.opendocument.spreadsheet"})
        .then(function(content) {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(content);
            link.download = 'fuel_history.ods';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
}

function exportToCSV() {
    const filterText = getFilterDescription();
    const headers = ['วันที่', 'เวลา', 'ทะเบียนรถ', 'เลขไมล์', 'ชนิดน้ำมัน', 'ราคาต่อลิตร', 'ลิตร', 'ราคารวม', 'สังกัด', 'หมายเหตุ'];
    let csvContent = '\uFEFF';
    
    // เพิ่มข้อมูลตัวกรองที่มุมขวาบน (ถ้ามี)
    if (filterText) {
        csvContent += ',,,,,,,' + filterText + '\n';
    }
    
    csvContent += headers.join(',') + '\n';
    
    // ใช้ฟังก์ชันกรองข้อมูลใหม่
    const filteredRecords = getFilteredRecords();
    
    filteredRecords.forEach(function(rec) {
        const d = new Date(rec.fuel_date);
        const date = isNaN(d) ? '' : d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
        const time = isNaN(d) ? '' : String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
        
        let liters = '';
        if (rec.total_cost && rec.cost_per_liter && Number(rec.cost_per_liter) > 0) {
            liters = (Number(rec.total_cost) / Number(rec.cost_per_liter)).toFixed(2);
        } else if (rec.liters) {
            liters = rec.liters;
        }
        
        const row = [
            date, time, rec.license_plate ?? '', rec.mileage_at_fuel ?? '', 
            rec.fuel_type ?? '', rec.cost_per_liter ?? '', liters, 
            rec.total_cost ?? '', rec.department ?? '', getCleanNotes(rec.notes ?? '')
        ];
        csvContent += row.map(field => `"${field}"`).join(',') + '\n';
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const downloadLink = document.createElement("a");
    downloadLink.download = "fuel_history.csv";
    downloadLink.href = window.URL.createObjectURL(blob);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

function exportToPDF() {
    // สร้างหน้าต่างใหม่สำหรับพิมพ์เป็น PDF
    const printWindow = window.open('', '_blank');
    const selectedDepartment = document.getElementById('departmentFilter').value;
    const orgName = selectedDepartment || "รวมทุกรายการ";
    const reportTitle = `รายการน้ำมัน${orgName}`;
    const reportSub = 'สำเนารายการจากสลิปน้ำมัน';
    const filterText = getFilterDescription();
    const headers = [
        'วันที่', 'เวลา', 'ทะเบียนรถ', 'เลขไมล์', 'ชนิดน้ำมัน', 'ราคาต่อลิตร', 'ลิตร', 'ราคารวม',
        'ระยะที่วิ่ง(กม.)', 'เฉลี่ยบาทต่อกม.', 'เฉลี่ยกม.ต่อลิตร', 'เฉลี่ยลิตรต่อกม.'
    ];
    
    // ใช้ฟังก์ชันกรองข้อมูลใหม่
    const filteredRecords = getFilteredRecords();
    
    let printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>${reportTitle}</title>
            <style>
                @page { size: A4 landscape; margin: 0.5cm; }
                body { font-family: 'Sarabun', Arial, sans-serif; font-size: 16px; }
                .header { text-align: center; margin-bottom: 15px; position: relative; }
                .header h1 { margin: 0; font-size: 20px; font-weight: bold; }
                .header h2 { margin: 0; font-size: 18px; }
                .filter-info { position: absolute; top: 0; right: 0; font-family: 'Angsana New', Arial, sans-serif; font-size: 10px; text-align: right; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; page-break-inside: avoid; }
                th, td { border: 1px solid #333; padding: 3px; text-align: left; font-size: 16px; word-wrap: break-word; }
                th { background-color: #f0f0f0; font-weight: bold; }
                .group-header { background-color: #e0e0e0; font-weight: bold; font-size: 16px; }
                .summary { background-color: #fff2cc; font-weight: bold; }
                /* ปรับความกว้างคอลัมน์ให้เหมาะสม */
                th:nth-child(1), td:nth-child(1) { width: 8%; } /* วันที่ */
                th:nth-child(2), td:nth-child(2) { width: 6%; } /* เวลา */
                th:nth-child(3), td:nth-child(3) { width: 10%; } /* ทะเบียนรถ */
                th:nth-child(4), td:nth-child(4) { width: 8%; } /* เลขไมล์ */
                th:nth-child(5), td:nth-child(5) { width: 10%; } /* ชนิดน้ำมัน */
                th:nth-child(6), td:nth-child(6) { width: 8%; } /* ราคาต่อลิตร */
                th:nth-child(7), td:nth-child(7) { width: 8%; } /* ลิตร */
                th:nth-child(8), td:nth-child(8) { width: 8%; } /* ราคารวม */
                th:nth-child(9), td:nth-child(9) { width: 8%; } /* ระยะที่วิ่ง */
                th:nth-child(10), td:nth-child(10) { width: 8%; } /* เฉลี่ยบาท/กม */
                th:nth-child(11), td:nth-child(11) { width: 9%; } /* เฉลี่ยกม/ลิตร */
                th:nth-child(12), td:nth-child(12) { width: 9%; } /* เฉลี่ยลิตร/กม */
                /* ให้ตารางสามารถแบ่งหน้าได้ */
                tr { page-break-inside: avoid; }
                .group-header { page-break-before: auto; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>${reportTitle}</h1>
                <h2>${reportSub}</h2>
                ${filterText ? `<div class="filter-info">${filterText}</div>` : ''}
            </div>
    `;
    
    // --- กลุ่มรถหลัก ---
    const mainGrouped = {};
    filteredRecords.forEach(function(rec) {
        const plate = rec.license_plate ?? '';
        if (!mainGrouped[plate]) mainGrouped[plate] = [];
        mainGrouped[plate].push(rec);
    });
    
    Object.keys(mainGrouped).forEach(function(plate) {
        // เรียงข้อมูลแต่ละกลุ่มรถจากวันที่เก่าสุดไปใหม่สุด
        mainGrouped[plate].sort(function(a, b) {
            return new Date(a.fuel_date) - new Date(b.fuel_date);
        });
        
        printContent += `
            <table>
                <tr class="group-header">
                    <td colspan="${headers.length}"><b>ทะเบียนรถ: ${plate}</b></td>
                </tr>
                <tr>
                    ${headers.map(h => `<th>${h}</th>`).join('')}
                </tr>
        `;
        
        const plateRecords = mainGrouped[plate] || [];
        let sumLiters = 0, sumCost = 0, sumDistance = 0;
        
        plateRecords.forEach(function(rec, idx) {
            const d = new Date(rec.fuel_date);
            const date = isNaN(d) ? '' : d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            const time = isNaN(d) ? '' : String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
            const mileage = rec.mileage_at_fuel ?? '';
            const fuelType = rec.fuel_type ?? '';
            const costPerLiter = rec.cost_per_liter ?? '';
            
            let liters = '';
            if (rec.total_cost && rec.cost_per_liter && Number(rec.cost_per_liter) > 0) {
                liters = (Number(rec.total_cost) / Number(rec.cost_per_liter)).toFixed(2);
            } else if (rec.liters) {
                liters = rec.liters;
            }
            const totalCost = rec.total_cost ?? '';
            
            // คำนวณระยะวิ่ง
            let mainDistance = '';
            let prevMileage = '';
            for (let j = idx - 1; j >= 0; j--) {
                if (plateRecords[j].mileage_at_fuel && !isNaN(Number(plateRecords[j].mileage_at_fuel))) {
                    prevMileage = plateRecords[j].mileage_at_fuel;
                    break;
                }
            }
            if (rec.mileage_at_fuel && prevMileage !== '' && !isNaN(Number(rec.mileage_at_fuel))) {
                mainDistance = Number(rec.mileage_at_fuel) - Number(prevMileage);
                if (mainDistance < 0) mainDistance = '';
            }
            if (mainDistance === '' && rec.notes) {
                // อ่านค่าระยะวิ่งจาก notes ที่บันทึกไว้ใน orderlist.php
                const matchMain = rec.notes.match(/ระยะวิ่งรถหลัก:\s*([\d.]+)/);
                if (matchMain) {
                    mainDistance = matchMain[1];
                }
            }
            
            // สะสมยอดรวม
            if (liters && !isNaN(Number(liters))) sumLiters += Number(liters);
            if (totalCost && !isNaN(Number(totalCost))) sumCost += Number(totalCost);
            if (mainDistance && !isNaN(Number(mainDistance))) sumDistance += Number(mainDistance);
            
            // หาลิตรจากครั้งก่อน (น้ำมันที่ใช้ไปจริง)
            let prevLiters = '';
            for (let j = idx - 1; j >= 0; j--) {
                const prevRec = plateRecords[j];
                if (prevRec.total_cost && prevRec.cost_per_liter && Number(prevRec.cost_per_liter) > 0) {
                    prevLiters = (Number(prevRec.total_cost) / Number(prevRec.cost_per_liter)).toFixed(2);
                    break;
                } else if (prevRec.liters) {
                    prevLiters = prevRec.liters;
                    break;
                }
            }
            
            // คำนวณเฉลี่ย
            let avgBahtPerKm = '';
            if (mainDistance && totalCost) avgBahtPerKm = (Number(totalCost) / Number(mainDistance)).toFixed(2);
            let avgKmPerLiter = '';
            if (mainDistance && prevLiters) avgKmPerLiter = (Number(mainDistance) / Number(prevLiters)).toFixed(2);
            let avgLiterPerKm = '';
            if (mainDistance && prevLiters) avgLiterPerKm = (Number(prevLiters) / Number(mainDistance)).toFixed(4);
            
            printContent += `
                <tr>
                    <td>${date}</td>
                    <td>${time}</td>
                    <td>${rec.license_plate ?? ''}</td>
                    <td>${mileage}</td>
                    <td>${fuelType}</td>
                    <td>${costPerLiter}</td>
                    <td>${liters}</td>
                    <td>${totalCost}</td>
                    <td>${mainDistance}</td>
                    <td>${avgBahtPerKm}</td>
                    <td>${avgKmPerLiter}</td>
                    <td>${avgLiterPerKm}</td>
                </tr>
            `;
        });
        
        // แสดงยอดรวม
        let sumAvgBahtPerKm = (sumDistance && sumCost) ? (sumCost / sumDistance).toFixed(2) : '';
        let sumAvgKmPerLiter = (sumLiters && sumDistance) ? (sumDistance / sumLiters).toFixed(2) : '';
        let sumAvgLiterPerKm = (sumLiters && sumDistance) ? (sumLiters / sumDistance).toFixed(4) : '';
        
        printContent += `
                <tr class="summary">
                    <td colspan="6"><b>รวม</b></td>
                    <td><b>${sumLiters.toFixed(2)}</b></td>
                    <td><b>${sumCost.toFixed(2)}</b></td>
                    <td><b>${sumDistance.toFixed(2)}</b></td>
                    <td><b>${sumAvgBahtPerKm}</b></td>
                    <td><b>${sumAvgKmPerLiter}</b></td>
                    <td><b>${sumAvgLiterPerKm}</b></td>
                </tr>
            </table>
        `;
    });
    
    // --- กลุ่มรถพ่วง ---
    const trailerGrouped3 = {};
    filteredRecords.forEach(function(rec) {
        const trailerPlate = rec.trailer_license_plate ?? '';
        if (trailerPlate && trailerPlate.trim() !== '') {
            if (!trailerGrouped3[trailerPlate]) trailerGrouped3[trailerPlate] = [];
            let trailerRec = Object.assign({}, rec);
            trailerRec.license_plate = trailerPlate;
            trailerRec.trailer_license_plate = '';
            trailerGrouped3[trailerPlate].push(trailerRec);
        }
    });
    
    Object.keys(trailerGrouped3).forEach(function(trailerPlate) {
        // เรียงข้อมูลแต่ละกลุ่มรถพ่วงจากวันที่เก่าสุดไปใหม่สุด
        trailerGrouped3[trailerPlate].sort(function(a, b) {
            return new Date(a.fuel_date) - new Date(b.fuel_date);
        });
        
        printContent += `
            <table>
                <tr class="group-header">
                    <td colspan="${headers.length}"><b>ทะเบียนรถพ่วง: ${trailerPlate}</b></td>
                </tr>
                <tr>
                    ${headers.map(h => `<th>${h}</th>`).join('')}
                </tr>
        `;
        
        let sumLiters = 0, sumCost = 0, sumDistance = 0;
        
        trailerGrouped3[trailerPlate].forEach(function(rec, idx) {
            const d = new Date(rec.fuel_date);
            const date = isNaN(d) ? '' : d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            const time = isNaN(d) ? '' : String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
            // ใช้เลขไมล์รถพ่วงจากฟิลด์ trailer_mileage_at_fuel แทน mileage_at_fuel
            const mileage = rec.trailer_mileage_at_fuel ?? '';
            const fuelType = rec.fuel_type ?? '';
            const costPerLiter = rec.cost_per_liter ?? '';
            
            let liters = '';
            if (rec.total_cost && rec.cost_per_liter && Number(rec.cost_per_liter) > 0) {
                liters = (Number(rec.total_cost) / Number(rec.cost_per_liter)).toFixed(2);
            } else if (rec.liters) {
                liters = rec.liters;
            }
            const totalCost = rec.total_cost ?? '';
            
            // คำนวณระยะวิ่งรถพ่วงจากการเปรียบเทียบเลขไมล์รถพ่วงขณะเติม
            let trailerDistance = '';
            let prevTrailerMileage = '';
            for (let j = idx - 1; j >= 0; j--) {
                if (trailerGrouped3[trailerPlate][j].trailer_mileage_at_fuel && !isNaN(Number(trailerGrouped3[trailerPlate][j].trailer_mileage_at_fuel))) {
                    prevTrailerMileage = trailerGrouped3[trailerPlate][j].trailer_mileage_at_fuel;
                    break;
                }
            }
            if (rec.trailer_mileage_at_fuel && prevTrailerMileage !== '' && !isNaN(Number(rec.trailer_mileage_at_fuel))) {
                trailerDistance = Number(rec.trailer_mileage_at_fuel) - Number(prevTrailerMileage);
                if (trailerDistance < 0) trailerDistance = '';
            }
            
            // ถ้าไม่มีข้อมูลใน trailer_mileage_at_fuel ให้อ่านจาก notes
            if (trailerDistance === '' && rec.notes) {
                const matchTrailer = rec.notes.match(/ระยะวิ่งรถพ่วง:\s*([\d.]+)/);
                if (matchTrailer) {
                    trailerDistance = matchTrailer[1];
                }
            }
            
            // สะสมยอดรวม
            if (liters && !isNaN(Number(liters))) sumLiters += Number(liters);
            if (totalCost && !isNaN(Number(totalCost))) sumCost += Number(totalCost);
            if (trailerDistance && !isNaN(Number(trailerDistance))) sumDistance += Number(trailerDistance);
            
            // หาลิตรจากครั้งก่อน (น้ำมันที่ใช้ไปจริง) สำหรับรถพ่วง
            let prevLiters = '';
            for (let j = idx - 1; j >= 0; j--) {
                const prevRec = trailerGrouped3[trailerPlate][j];
                if (prevRec.total_cost && prevRec.cost_per_liter && Number(prevRec.cost_per_liter) > 0) {
                    prevLiters = (Number(prevRec.total_cost) / Number(prevRec.cost_per_liter)).toFixed(2);
                    break;
                } else if (prevRec.liters) {
                    prevLiters = prevRec.liters;
                    break;
                }
            }
            
            // คำนวณเฉลี่ย
            let avgBahtPerKm = '';
            if (trailerDistance && totalCost) avgBahtPerKm = (Number(totalCost) / Number(trailerDistance)).toFixed(2);
            let avgKmPerLiter = '';
            if (trailerDistance && prevLiters) avgKmPerLiter = (Number(trailerDistance) / Number(prevLiters)).toFixed(2);
            let avgLiterPerKm = '';
            if (trailerDistance && prevLiters) avgLiterPerKm = (Number(prevLiters) / Number(trailerDistance)).toFixed(4);
            
            printContent += `
                <tr>
                    <td>${date}</td>
                    <td>${time}</td>
                    <td>${trailerPlate}</td>
                    <td>${mileage}</td>
                    <td>${fuelType}</td>
                    <td>${costPerLiter}</td>
                    <td>${liters}</td>
                    <td>${totalCost}</td>
                    <td>${trailerDistance}</td>
                    <td>${avgBahtPerKm}</td>
                    <td>${avgKmPerLiter}</td>
                    <td>${avgLiterPerKm}</td>
                </tr>
            `;
        });
        
        // --- แสดงยอดรวมใต้ตาราง ---
        let sumAvgBahtPerKm = (sumDistance && sumCost) ? (sumCost / sumDistance).toFixed(2) : '';
        let sumAvgKmPerLiter = (sumLiters && sumDistance) ? (sumDistance / sumLiters).toFixed(2) : '';
        let sumAvgLiterPerKm = (sumLiters && sumDistance) ? (sumLiters / sumDistance).toFixed(4) : '';
        
        printContent += `
                <tr class="summary">
                    <td colspan="6"><b>รวม</b></td>
                    <td><b>${sumLiters.toFixed(2)}</b></td>
                    <td><b>${sumCost.toFixed(2)}</b></td>
                    <td><b>${sumDistance.toFixed(2)}</b></td>
                    <td><b>${sumAvgBahtPerKm}</b></td>
                    <td><b>${sumAvgKmPerLiter}</b></td>
                    <td><b>${sumAvgLiterPerKm}</b></td>
                </tr>
            </table>
        `;
    });
    
    printContent += `
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

// สำหรับเก็บฟังก์ชันเดิม (backward compatibility)
function exportTableToCSV() {
    exportToExcel();
}
<?php endif; ?>
</script>
</body>
</html>