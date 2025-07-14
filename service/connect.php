<?php
/**
 * ไฟล์การเชื่อมต่อฐานข้อมูลหลัก
 * รวมฟังก์ชันพื้นฐานสำหรับการทำงานกับฐานข้อมูล
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * สร้างการเชื่อมต่อฐานข้อมูล
 */
function connect_db() {
    try {
        $dsn = "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME . ";TrustServerCertificate=true";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
        ]);
        return $pdo;
    } catch (PDOException $e) {
        log_activity('DATABASE_ERROR', $e->getMessage());
        die("Connection failed: " . $e->getMessage());
    }
}

/**
 * ฟังก์ชันสำหรับดึงข้อมูลรถตามทะเบียน
 */
function getCar($conn, $plate) {
    try {
        $stmt = $conn->prepare("SELECT v.*, vb.brand_name, ft.fuel_name, vc.category_name 
                               FROM vehicles v
                               LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
                               LEFT JOIN fuel_types ft ON v.fuel_type_id = ft.fuel_type_id  
                               LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
                               WHERE v.license_plate = ? AND v.is_deleted = 0");
        $stmt->execute([$plate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        log_activity('DATABASE_ERROR', "getCar error: " . $e->getMessage());
        return false;
    }
}

/**
 * ฟังก์ชันสำหรับ INSERT ข้อมูลพร้อม return ID (สำหรับ SQL Server)
 */
function insertWithId($conn, $table, $data, $idColumn = null) {
    try {
        $fields = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        // สำหรับ SQL Server ใช้ OUTPUT INSERTED
        if ($idColumn) {
            $sql = "INSERT INTO $table ($fields) OUTPUT INSERTED.$idColumn VALUES ($placeholders)";
        } else {
            $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
        }
        
        $stmt = $conn->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        if ($idColumn) {
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result[$idColumn];
        } else {
            return $stmt->execute();
        }
    } catch (PDOException $e) {
        log_activity('DATABASE_ERROR', "insertWithId error: " . $e->getMessage());
        return false;
    }
}
?>