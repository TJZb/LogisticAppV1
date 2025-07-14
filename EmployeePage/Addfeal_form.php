<?php
    $uploadDir = __DIR__ . '/../uploads/'; // กำหนด path โฟลเดอร์หลักฐานไว้ที่เดียว

    require_once __DIR__ . '/../service/connect.php';
    $conn = connect_db();

    session_start();

    // ฟังก์ชันสำหรับลดขนาดและปรับปรุงรูปภาพ
    function resizeImage($sourcePath, $targetPath, $maxWidth = 1200, $maxHeight = 1200, $quality = 85) {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) return false;
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $imageType = $imageInfo[2];
        
        // คำนวณขนาดใหม่โดยรักษาสัดส่วน
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        if ($ratio >= 1) {
            // ถ้ารูปเล็กกว่าขนาดที่กำหนด ก็ใช้ขนาดเดิม
            $newWidth = $sourceWidth;
            $newHeight = $sourceHeight;
        } else {
            $newWidth = round($sourceWidth * $ratio);
            $newHeight = round($sourceHeight * $ratio);
        }
        
        // สร้าง resource จากไฟล์ต้นฉบับ
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) return false;
        
        // สร้างภาพใหม่ตามขนาดที่คำนวณ
        $targetImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // รักษาความโปร่งใสสำหรับ PNG และ GIF
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // ปรับขนาดภาพ
        imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        
        // บันทึกภาพใหม่ (แปลงเป็น JPEG เพื่อลดขนาดไฟล์)
        $result = imagejpeg($targetImage, $targetPath, $quality);
        
        // ล้างหน่วยความจำ
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        
        return $result;
    }

    // ฟังก์ชันสำหรับสร้างชื่อไฟล์ที่มีความหมาย
    function generateFileName($originalName, $type, $licensePlate, $employeeId) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $timestamp = date('Y-m-d_H-i-s');
        $randomString = substr(md5(uniqid()), 0, 6);
        
        // ทำความสะอาดทะเบียนรถ (ลบอักขระพิเศษ)
        $cleanPlate = preg_replace('/[^a-zA-Z0-9ก-๙]/', '', $licensePlate);
        
        return "{$type}_{$cleanPlate}_{$timestamp}_{$employeeId}_{$randomString}.jpg";
    }


    // ลบฟังก์ชัน getCar ออกเพราะมีในไฟล์ service แล้ว
    function getLastFuelRecord($conn, $vehicle_id) {
        $stmt = $conn->prepare("SELECT TOP 1 * FROM FuelRecords WHERE vehicle_id = ? ORDER BY fuel_date DESC");
        $stmt->execute([$vehicle_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $car = null;
    $fuel = null;
    $editMode = false;

    if (isset($_GET['license_plate'])) {
        $car = getCar($conn, $_GET['license_plate']);
        if ($car) {
            // กรณีแก้ไข ให้เช็คว่ามี parameter edit=1 หรือ fuel_record_id
            if (isset($_GET['edit']) && isset($_GET['fuel_record_id'])) {
                // ดึงข้อมูลรายการที่จะแก้ไข
                $stmt = $conn->prepare("SELECT * FROM FuelRecords WHERE fuel_record_id = ?");
                $stmt->execute([$_GET['fuel_record_id']]);
                $fuel = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($fuel) {
                    $editMode = true;
                }
            }
            // ถ้าไม่ใช่โหมดแก้ไข ไม่ต้อง set $fuel, $editMode
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'])) {
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $files = [];
        $employeeId = $_SESSION['employee_id'] ?? 1;
        
        // อัปโหลดและประมวลผลไฟล์หลักฐาน
        $fileFields = [
            'gauge_before_img' => 'gauge_before',
            'gauge_after_img' => 'gauge_after', 
            'receipt_file' => 'receipt'
        ];
        
        foreach ($fileFields as $field => $type) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $tempFile = $_FILES[$field]['tmp_name'];
                $originalName = $_FILES[$field]['name'];
                $fileType = $_FILES[$field]['type'];
                
                // สร้างชื่อไฟล์ใหม่
                $newFileName = generateFileName($originalName, $type, $car['license_plate'], $employeeId);
                $targetPath = $uploadDir . $newFileName;
                
                // ตรวจสอบว่าเป็นไฟล์รูปภาพหรือไม่
                if (strpos($fileType, 'image/') === 0) {
                    // ลดขนาดรูปภาพและบันทึก
                    if (resizeImage($tempFile, $targetPath)) {
                        $files[$field] = $newFileName;
                    } else {
                        // ถ้าลดขนาดไม่ได้ ให้คัดลอกไฟล์เดิม
                        if (move_uploaded_file($tempFile, $targetPath)) {
                            $files[$field] = $newFileName;
                        }
                    }
                } else {
                    // สำหรับไฟล์ PDF ให้คัดลอกโดยตรง
                    if (move_uploaded_file($tempFile, $targetPath)) {
                        $files[$field] = $newFileName;
                    }
                }
                
                if (!isset($files[$field])) {
                    echo "<script>alert('เกิดข้อผิดพลาดในการอัปโหลดไฟล์ {$field}');window.history.back();</script>";
                    exit;
                }
            } else {
                echo "<script>alert('กรุณาแนบไฟล์รูปเกจน้ำมันก่อน/หลังเติม และไฟล์ใบเสร็จ');window.history.back();</script>";
                exit;
            }
        }

        $vehicle_id = $_POST['vehicle_id'];
        $trailer_vehicle_id = isset($_POST['trailer_vehicle_id']) && $_POST['trailer_vehicle_id'] !== '' ? $_POST['trailer_vehicle_id'] : null;
        $recorded_by_employee_id = $employeeId;

        // เพิ่ม trailer_vehicle_id ลงใน FuelRecords ถ้ามี
        if ($trailer_vehicle_id) {
            $sql = "INSERT INTO FuelRecords (vehicle_id, trailer_vehicle_id, fuel_date, recorded_by_employee_id, status) 
                    OUTPUT INSERTED.fuel_record_id 
                    VALUES (?, ?, GETDATE(), ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$vehicle_id, $trailer_vehicle_id, $recorded_by_employee_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $fuel_record_id = $result['fuel_record_id'];
        } else {
            $sql = "INSERT INTO FuelRecords (vehicle_id, fuel_date, recorded_by_employee_id, status) 
                    OUTPUT INSERTED.fuel_record_id 
                    VALUES (?, GETDATE(), ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$vehicle_id, $recorded_by_employee_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $fuel_record_id = $result['fuel_record_id'];
        }
        $msg = "บันทึกข้อมูลสำเร็จ";

        // แนบไฟล์หลักฐาน
        $attachmentTypes = [
            'gauge_before_img' => 'gauge_before',
            'gauge_after_img'  => 'gauge_after',
            'receipt_file'     => 'receipt'
        ];
        foreach ($attachmentTypes as $field => $type) {
            if (isset($files[$field])) {
                $stmt = $conn->prepare("INSERT INTO FuelReceiptAttachments 
                    (fuel_record_id, attachment_type, file_path, uploaded_at, uploaded_by_employee_id)
                    VALUES (?, ?, ?, GETDATE(), ?)");
                $stmt->execute([
                    $fuel_record_id,
                    $type,
                    $files[$field],
                    $recorded_by_employee_id
                ]);
            }
        }

        header("Location: index.php");
        exit;
    }

    // ไม่ต้องดึงประเภทเชื้อเพลิงอีกต่อไป
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- เพิ่ม meta viewport -->
    <title><?= $editMode ? 'แก้ไข' : 'เพิ่ม' ?>ข้อมูลเติมน้ำมัน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=Poppins:wght@400;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/app-theme.css">
    <style>
      body { font-family: 'Sarabun', 'Poppins', 'Inter', sans-serif; }
      /* ปรับขนาด input/button ให้เหมาะกับมือถือ */
      @media (max-width: 640px) {
        .max-w-xl { max-width: 100% !important; }
        .p-8 { padding: 1.25rem !important; }
        .rounded-2xl { border-radius: 1rem !important; }
        .space-y-5 > :not([hidden]) ~ :not([hidden]) { margin-top: 1rem !important; }
        input, select, textarea, button {
          font-size: 1rem !important;
          padding: 0.75rem 1rem !important;
        }
        label { font-size: 1rem !important; }
        .h-20 { height: 4rem !important; }
      }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1e293b] to-[#0f172a] text-[#e0e0e0]">
<div class="container mx-auto py-4 px-2 sm:px-0"> <!-- เพิ่ม px-2 สำหรับมือถือ -->
    <?php if ($car): ?>
    <div class="max-w-xl mx-auto bg-[#1f2937] rounded-2xl shadow-xl p-8 mb-8 border-2 border-transparent">
        <h2 class="text-2xl font-bold mb-6 text-[#60a5fa] text-center"><?= $editMode ? 'แก้ไข' : 'เพิ่ม' ?>ข้อมูลเติมน้ำมันสำหรับรถทะเบียน <?=htmlspecialchars($car['license_plate'])?></h2>
        <form action="" method="post" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="vehicle_id" value="<?=htmlspecialchars($car['vehicle_id'])?>">
            <?php
            // ดึงรถพ่วงทั้งหมด จากตาราง vehicles ที่มี category เป็นรถพ่วง
            $trailer_stmt = $conn->prepare("
                SELECT v.vehicle_id, v.license_plate 
                FROM vehicles v
                LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
                WHERE v.status='active' 
                AND v.is_deleted = 0
                AND (vc.category_name LIKE N'%พ่วง%' OR vc.category_code = 'TRL')
                ORDER BY v.license_plate
            ");
            $trailer_stmt->execute();
            $trailers = $trailer_stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (count($trailers) > 0): ?>
            <div>
                <label class="block font-semibold mb-1">เลือกรถพ่วง (ถ้ามี):</label>
                <input type="text" id="trailerSearch" placeholder="ค้นหาทะเบียนรถพ่วง..." class="rounded-lg px-3 py-2 w-full mb-2 bg-[#111827] border border-[#374151] text-[#e0e0e0]">
                <select name="trailer_vehicle_id" id="trailerDropdown" class="w-full rounded px-3 py-2 text-[#111827]">
                    <option value="">-- ไม่เลือกรถพ่วง --</option>
                    <?php foreach ($trailers as $tr): ?>
                        <option value="<?=htmlspecialchars($tr['vehicle_id'])?>">ทะเบียน: <?=htmlspecialchars($tr['license_plate'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var search = document.getElementById('trailerSearch');
                var dropdown = document.getElementById('trailerDropdown');
                search.addEventListener('input', function() {
                    var val = search.value.toLowerCase();
                    Array.from(dropdown.options).forEach(function(opt, idx) {
                        if(idx === 0) { opt.style.display = ''; return; } // always show first option
                        opt.style.display = opt.text.toLowerCase().includes(val) ? '' : 'none';
                    });
                });
            });
            </script>
            <?php endif; ?>
            <div>
                <label class="block font-semibold mb-1">แนบรูปเกจน้ำมันก่อนเติม</label>
                <input type="file" name="gauge_before_img" class="w-full text-[#e0e0e0] bg-[#1f2937]" accept="image/*" required onchange="showPreview(this, 'preview_before')">
                <div id="preview_before" class="mt-2"></div>
            </div>
            <div>
                <label class="block font-semibold mb-1">แนบรูปเกจน้ำมันหลังเติม</label>
                <input type="file" name="gauge_after_img" class="w-full text-[#e0e0e0] bg-[#1f2937]" accept="image/*" required onchange="showPreview(this, 'preview_after')">
                <div id="preview_after" class="mt-2"></div>
            </div>
            <div>
                <label class="block font-semibold mb-1">แนบไฟล์ใบเสร็จ (เฉพาะรูปภาพหรือ PDF)</label>
                <input type="file" name="receipt_file" class="w-full text-[#e0e0e0] bg-[#1f2937]" accept="image/*,application/pdf" required onchange="showPreview(this, 'preview_receipt')">
                <div id="preview_receipt" class="mt-2"></div>
            </div>
            <div class="flex flex-col md:flex-row gap-4 mt-8">
                <button type="submit" id="submitBtn"
                    class="transition-all duration-150 bg-[#60a5fa] hover:bg-[#4ade80] text-[#111827] font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#4ade80] w-full md:w-auto flex items-center justify-center">
                    <svg id="spinner" class="animate-spin h-5 w-5 mr-2 text-[#111827] hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span class="font-bold">ส่งข้อมูล</span>
                </button>
                <a href="index.php"
                   class="transition-all duration-150 bg-[#f87171] hover:bg-[#ef4444] text-white font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#f87171] w-full md:w-auto text-center"
                   aria-label="กลับหน้าหลัก">
                   กลับ
                </a>
            </div>
        </form>
    </div>
    <?php else: ?>
        <div class="max-w-xl mx-auto my-8 p-4 bg-red-100 text-red-700 rounded flex flex-col items-center">
            ไม่พบข้อมูลรถ
            <a href="index.php"
               class="mt-4 transition-all duration-150 bg-[#f87171] hover:bg-[#ef4444] text-white font-semibold px-6 py-2 rounded-lg shadow hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#f87171]">
               กลับ
            </a>
        </div>
    <?php endif; ?>
</div>
<script>
function toggleOtherFuelType() {
    var sel = document.getElementById('fuel_type_select');
    var other = document.getElementById('fuel_type_other');
    if (sel.value === 'other') {
        other.style.display = '';
        other.required = true;
    } else {
        other.style.display = 'none';
        other.required = false;
        other.value = '';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    // Loading spinner on submit
    document.querySelector('form').addEventListener('submit', function() {
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('spinner').classList.remove('hidden');
    });
});

// Show preview function
function showPreview(input, previewId) {
    let file = input.files[0];
    let preview = document.getElementById(previewId);
    if (!preview) {
        preview = document.createElement('div');
        preview.id = previewId;
        preview.className = "mt-2";
        input.parentNode.appendChild(preview);
    }
    preview.innerHTML = '';
    if (file) {
        if (file.type.startsWith('image/')) {
            let img = document.createElement('img');
            img.className = "h-20 rounded shadow cursor-pointer transition-transform hover:scale-105";
            img.src = URL.createObjectURL(file);
            img.alt = "preview";
            img.onclick = function() { openModal(img.src, file.type); };
            preview.appendChild(img);
        } else if (file.type === 'application/pdf') {
            let link = document.createElement('a');
            link.href = URL.createObjectURL(file);
            link.textContent = "ดูไฟล์ PDF (คลิกดูเต็มจอ)";
            link.className = "text-[#60a5fa] underline cursor-pointer";
            link.onclick = function(e) {
                e.preventDefault();
                openModal(link.href, file.type);
            };
            preview.appendChild(link);
        }
    }
}

// Modal for full-screen image preview
function openModal(src, type) {
    let modal = document.getElementById('imgModal');
    let modalContent = document.getElementById('imgModalContent');
    let modalImg = document.getElementById('imgModalImg');
    let modalPdf = document.getElementById('imgModalPdf');
    if (!modal) return;
    if (type.startsWith('image/')) {
        modalImg.src = src;
        modalImg.style.display = '';
        modalPdf.style.display = 'none';
    } else if (type === 'application/pdf') {
        modalPdf.src = src;
        modalPdf.style.display = '';
        modalImg.style.display = 'none';
    }
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    let modal = document.getElementById('imgModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        // Clean up src to release memory
        document.getElementById('imgModalImg').src = '';
        document.getElementById('imgModalPdf').src = '';
    }
}
</script>
<!-- Modal for full-screen image/pdf preview -->
<div id="imgModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80 hidden" onclick="closeModal()">
  <div id="imgModalContent" class="relative max-w-full max-h-full flex items-center justify-center" onclick="event.stopPropagation()">
    <button onclick="closeModal()" class="absolute top-2 right-2 bg-[#1e293b] text-white rounded-full p-2 shadow-lg z-10 hover:bg-[#334155] focus:outline-none" aria-label="ปิดตัวอย่างเต็มจอ">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
    </button>
    <img id="imgModalImg" src="" alt="preview" class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-xl" style="display:none;" />
    <iframe id="imgModalPdf" src="" class="w-[90vw] h-[90vh] rounded-lg shadow-xl" style="display:none; background:white;"></iframe>
  </div>
</div>
<?php if (isset($msg)): ?>
<script>
    window.onload = function() {
        alert("<?=htmlspecialchars($msg)?>");
    }
</script>
<?php endif; ?>
</body>
</html>