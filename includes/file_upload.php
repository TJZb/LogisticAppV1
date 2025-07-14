<?php
/**
 * ไฟล์สำหรับจัดการการอัปโหลดไฟล์
 * รวมฟังก์ชันที่เกี่ยวข้องกับการอัปโหลด จัดการ และตรวจสอบไฟล์
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Class สำหรับจัดการการอัปโหลดไฟล์
 */
class FileUploadManager {
    
    private $upload_path;
    private $allowed_extensions;
    private $max_file_size;
    
    public function __construct() {
        $this->upload_path = UPLOAD_PATH;
        $this->allowed_extensions = ALLOWED_EXTENSIONS;
        $this->max_file_size = MAX_FILE_SIZE;
        
        // สร้างโฟล์เดอร์ uploads หากไม่มี
        if (!file_exists($this->upload_path)) {
            mkdir($this->upload_path, 0755, true);
        }
    }
    
    /**
     * ฟังก์ชันหลักสำหรับการอัปโหลดไฟล์
     */
    public function upload_file($file, $prefix = '', $fuel_record_id = null) {
        // ตรวจสอบข้อผิดพลาดของไฟล์
        $validation_errors = $this->validate_file($file);
        if (!empty($validation_errors)) {
            return ['success' => false, 'errors' => $validation_errors];
        }
        
        try {
            // สร้างชื่อไฟล์ใหม่
            $new_filename = $this->generate_filename($file['name'], $prefix, $fuel_record_id);
            $target_path = $this->upload_path . $new_filename;
            
            // ย้ายไฟล์ไปยังตำแหน่งที่กำหนด
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                log_activity('FILE_UPLOAD', "File: $new_filename");
                return [
                    'success' => true, 
                    'filename' => $new_filename,
                    'path' => $target_path,
                    'size' => $file['size']
                ];
            } else {
                return ['success' => false, 'errors' => ['ไม่สามารถย้ายไฟล์ได้']];
            }
            
        } catch (Exception $e) {
            log_activity('FILE_UPLOAD_ERROR', $e->getMessage());
            return ['success' => false, 'errors' => ['เกิดข้อผิดพลาดในการอัปโหลด: ' . $e->getMessage()]];
        }
    }
    
    /**
     * ฟังก์ชันสำหรับตรวจสอบไฟล์
     */
    private function validate_file($file) {
        $errors = [];
        
        // ตรวจสอบว่ามีไฟล์หรือไม่
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = 'ไม่พบไฟล์ที่จะอัปโหลด';
            return $errors;
        }
        
        // ตรวจสอบข้อผิดพลาดการอัปโหลด
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'ไฟล์มีขนาดใหญ่เกินไป';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'ไฟล์อัปโหลดไม่สมบูรณ์';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'ไม่พบไฟล์ที่จะอัปโหลด';
                    break;
                default:
                    $errors[] = 'เกิดข้อผิดพลาดในการอัปโหลด';
                    break;
            }
        }
        
        // ตรวจสอบขนาดไฟล์
        if ($file['size'] > $this->max_file_size) {
            $errors[] = 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด ' . ($this->max_file_size / 1024 / 1024) . 'MB)';
        }
        
        // ตรวจสอบนามสกุลไฟล์
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            $errors[] = 'นามสกุลไฟล์ไม่ถูกต้อง (อนุญาต: ' . implode(', ', $this->allowed_extensions) . ')';
        }
        
        // ตรวจสอบประเภทไฟล์ด้วย MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf'
        ];
        
        if (!in_array($mime_type, $allowed_mimes)) {
            $errors[] = 'ประเภทไฟล์ไม่ถูกต้อง';
        }
        
        return $errors;
    }
    
    /**
     * ฟังก์ชันสำหรับสร้างชื่อไฟล์ใหม่
     */
    private function generate_filename($original_name, $prefix = '', $fuel_record_id = null) {
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $timestamp = date('Y-m-d_H-i-s');
        $random = substr(md5(uniqid()), 0, 6);
        
        $filename_parts = [];
        
        if ($prefix) {
            $filename_parts[] = $prefix;
        }
        
        if ($fuel_record_id) {
            $filename_parts[] = $fuel_record_id;
        }
        
        $filename_parts[] = $timestamp;
        $filename_parts[] = rand(1, 9999);
        $filename_parts[] = $random;
        
        return implode('_', $filename_parts) . '.' . $extension;
    }
    
    /**
     * ฟังก์ชันสำหรับการลบไฟล์
     */
    public function delete_file($filename) {
        $file_path = $this->upload_path . $filename;
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                log_activity('FILE_DELETE', "File: $filename");
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ฟังก์ชันสำหรับตรวจสอบว่าไฟล์มีอยู่หรือไม่
     */
    public function file_exists($filename) {
        return file_exists($this->upload_path . $filename);
    }
    
    /**
     * ฟังก์ชันสำหรับดึงข้อมูลไฟล์
     */
    public function get_file_info($filename) {
        $file_path = $this->upload_path . $filename;
        
        if (file_exists($file_path)) {
            return [
                'filename' => $filename,
                'size' => filesize($file_path),
                'modified' => filemtime($file_path),
                'path' => $file_path
            ];
        }
        
        return null;
    }
    
    /**
     * ฟังก์ชันสำหรับการย่อขนาดรูปภาพ (สำหรับรูปภาพขนาดใหญ่)
     */
    public function resize_image($source_path, $target_path, $max_width = 800, $max_height = 600) {
        $image_info = getimagesize($source_path);
        
        if (!$image_info) {
            return false;
        }
        
        $original_width = $image_info[0];
        $original_height = $image_info[1];
        $image_type = $image_info[2];
        
        // คำนวณขนาดใหม่
        $ratio = min($max_width / $original_width, $max_height / $original_height);
        $new_width = round($original_width * $ratio);
        $new_height = round($original_height * $ratio);
        
        // สร้างรูปภาพจากไฟล์ต้นฉบับ
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source_image = imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_GIF:
                $source_image = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }
        
        // สร้างรูปภาพใหม่
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // รักษาความโปร่งใส (สำหรับ PNG และ GIF)
        if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
        }
        
        // ย่อขนาดรูปภาพ
        imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, 
                          $new_width, $new_height, $original_width, $original_height);
        
        // บันทึกรูปภาพใหม่
        $result = false;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($new_image, $target_path, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($new_image, $target_path);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($new_image, $target_path);
                break;
        }
        
        // ล้างหน่วยความจำ
        imagedestroy($source_image);
        imagedestroy($new_image);
        
        return $result;
    }
}
?>
