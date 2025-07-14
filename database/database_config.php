<?php

/**
 * Database Configuration และ Helper Classes สำหรับ SQL Server
 * สำหรับระบบ Logistic App
 */

class DatabaseConfig {
    private $conn;
    private $serverName = "192.168.50.123";
    private $database = "logistic_app_db";
    private $username = "bcf_it_dev";
    private $password = "bcf@625_information";

    public function __construct() {
        $this->connect();
    }

    public function connect() {
        try {
            $this->conn = new PDO(
                "sqlsrv:server={$this->serverName};Database={$this->database}",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
                ]
            );
            return $this->conn;
        } catch (PDOException $e) {
            die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function disconnect() {
        $this->conn = null;
    }
}

/**
 * Vehicle Management Class
 * จัดการข้อมูลรถและยานพาหนะ
 */
class VehicleManager {
    private $db;

    public function __construct() {
        $this->db = new DatabaseConfig();
    }

    /**
     * ดึงข้อมูลรถทั้งหมดพร้อม JOIN กับตารางที่เกี่ยวข้อง
     */
    public function getAllVehicles($status = null, $category = null, $limit = 50, $offset = 0) {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT TOP (:limit)
                    v.vehicle_id,
                    v.license_plate,
                    v.province,
                    v.registration_date,
                    vb.brand_name,
                    v.model_name,
                    v.year_manufactured,
                    v.color,
                    ft.fuel_name,
                    vc.category_name,
                    v.payload_weight,
                    v.gross_weight,
                    v.seating_capacity,
                    v.status,
                    v.vehicle_description
                FROM vehicles v
                LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
                LEFT JOIN fuel_types ft ON v.fuel_type_id = ft.fuel_type_id
                LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
                WHERE v.is_deleted = 0";
        
        $params = [];
        
        if ($status) {
            $sql .= " AND v.status = :status";
            $params['status'] = $status;
        }
        
        if ($category) {
            $sql .= " AND vc.category_code = :category";
            $params['category'] = $category;
        }
        
        if ($offset > 0) {
            $sql .= " OFFSET :offset ROWS";
            $params['offset'] = $offset;
        }
        
        $sql .= " ORDER BY v.license_plate";
        $params['limit'] = $limit;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ค้นหารถตามคำค้น
     */
    public function searchVehicles($keyword) {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT v.vehicle_id, v.license_plate, vb.brand_name, v.model_name, 
                       v.color, v.status, vc.category_name
                FROM vehicles v
                LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
                LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
                WHERE v.is_deleted = 0
                AND (v.license_plate LIKE :keyword 
                     OR vb.brand_name LIKE :keyword 
                     OR v.model_name LIKE :keyword
                     OR v.color LIKE :keyword)
                ORDER BY v.license_plate";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(['keyword' => "%{$keyword}%"]);
        return $stmt->fetchAll();
    }

    /**
     * ดึงข้อมูลรถตาม ID
     */
    public function getVehicleById($vehicle_id) {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT * FROM vehicles WHERE vehicle_id = ? AND is_deleted = 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$vehicle_id]);
        return $stmt->fetch();
    }

    /**
     * ดึงข้อมูลรถตามทะเบียน
     */
    public function getVehicleByLicensePlate($license_plate) {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT * FROM vehicles WHERE license_plate = ? AND is_deleted = 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$license_plate]);
        return $stmt->fetch();
    }

    /**
     * เพิ่มรถใหม่
     */
    public function addVehicle($data) {
        $conn = $this->db->getConnection();
        
        $sql = "INSERT INTO vehicles (
                    license_plate, province, registration_date, brand_id, model_name,
                    year_manufactured, color, chassis_number, fuel_type_id, 
                    payload_weight, gross_weight, seating_capacity, category_id,
                    vehicle_description, status
                ) OUTPUT INSERTED.vehicle_id VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['license_plate'], $data['province'], $data['registration_date'],
            $data['brand_id'], $data['model_name'], $data['year_manufactured'],
            $data['color'], $data['chassis_number'], $data['fuel_type_id'],
            $data['payload_weight'], $data['gross_weight'], $data['seating_capacity'],
            $data['category_id'], $data['vehicle_description'] ?? '', 'active'
        ]);
        
        $result = $stmt->fetch();
        return $result['vehicle_id'];
    }

    /**
     * อัพเดทข้อมูลรถ
     */
    public function updateVehicle($vehicle_id, $data) {
        $conn = $this->db->getConnection();
        
        $sql = "UPDATE vehicles SET 
                    license_plate = ?, province = ?, brand_id = ?, model_name = ?,
                    year_manufactured = ?, color = ?, fuel_type_id = ?,
                    payload_weight = ?, gross_weight = ?, seating_capacity = ?,
                    category_id = ?, vehicle_description = ?, status = ?,
                    updated_at = GETDATE()
                WHERE vehicle_id = ?";
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute([
            $data['license_plate'], $data['province'], $data['brand_id'],
            $data['model_name'], $data['year_manufactured'], $data['color'],
            $data['fuel_type_id'], $data['payload_weight'], $data['gross_weight'],
            $data['seating_capacity'], $data['category_id'], 
            $data['vehicle_description'] ?? '', $data['status'] ?? 'active',
            $vehicle_id
        ]);
    }

    /**
     * ลบรถ (Soft Delete)
     */
    public function deleteVehicle($vehicle_id) {
        $conn = $this->db->getConnection();
        
        $sql = "UPDATE vehicles SET is_deleted = 1, updated_at = GETDATE() WHERE vehicle_id = ?";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$vehicle_id]);
    }

    /**
     * ดึงยี่ห้อรถทั้งหมด
     */
    public function getAllBrands() {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT * FROM vehicle_brands WHERE is_active = 1 ORDER BY brand_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * ดึงประเภทเชื้อเพลิงทั้งหมด
     */
    public function getAllFuelTypes() {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT * FROM fuel_types ORDER BY fuel_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * ดึงประเภทรถทั้งหมด
     */
    public function getAllCategories() {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT * FROM vehicle_categories ORDER BY category_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * ดึงสถิติรถ
     */
    public function getVehicleStatistics() {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT 
                    COUNT(*) as total_vehicles,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vehicles,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_vehicles,
                    SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as in_use_vehicles,
                    SUM(CASE WHEN status = 'out_of_service' THEN 1 ELSE 0 END) as out_of_service_vehicles
                FROM vehicles 
                WHERE is_deleted = 0";
        
        $stmt = $conn->query($sql);
        return $stmt->fetch();
    }

    public function __destruct() {
        $this->db->disconnect();
    }
}

/**
 * Helper Functions สำหรับแสดงผล
 */
class DisplayHelpers {
    
    public static function getStatusInThai($status) {
        $statusMap = [
            'active' => 'พร้อมใช้งาน',
            'maintenance' => 'อยู่ระหว่างซ่อมบำรุง',
            'in_use' => 'กำลังใช้งาน',
            'out_of_service' => 'หยุดใช้งาน',
            'sold' => 'ขายแล้ว'
        ];
        return $statusMap[$status] ?? $status;
    }

    public static function formatCurrency($amount) {
        return number_format($amount, 2) . ' บาท';
    }

    public static function formatWeight($weight) {
        return number_format($weight, 2) . ' กก.';
    }

    public static function calculateVehicleAge($year_manufactured) {
        return date('Y') - $year_manufactured;
    }
}

?>
