<?php
/**
 * Fuel Export Service - ไฟล์สำหรับจัดการการ export รายงานการเติมน้ำมัน
 * รองรับ Excel (.xlsx), LibreOffice (.ods), CSV (.csv), PDF (.pdf)
 * 
 * @author LogisticApp Team
 * @version 1.0
 * @date 2025-07-16
 */

require_once __DIR__ . '/base_export_service.php';

class FuelExportService extends BaseExportService {
    
    /**
     * เตรียมข้อมูลการเติมน้ำมันสำหรับการ export
     * 
     * @param array $records ข้อมูลจากฐานข้อมูล
     * @return array ข้อมูลที่เตรียมแล้วสำหรับ export
     */
    public static function prepareFuelExportData($records) {
        $exportData = [];
        
        foreach ($records as $record) {
            $fuelDate = date('Y-m-d', strtotime($record['fuel_date']));
            $fuelTime = date('H:i', strtotime($record['fuel_date']));
            
            // คำนวณข้อมูลต่างๆ
            $distance = ($record['odometer_reading'] ?? 0) - ($record['previous_odometer'] ?? 0);
            $distance = max(0, $distance); // ป้องกันค่าติดลบ
            
            $costPerKm = $distance > 0 ? ($record['total_cost'] ?? 0) / $distance : 0;
            $kmPerLiter = ($record['liters'] ?? 0) > 0 ? $distance / ($record['liters'] ?? 1) : 0;
            $literPerKm = $distance > 0 ? ($record['liters'] ?? 0) / $distance : 0;
            
            $exportData[] = [
                $fuelDate,
                $fuelTime,
                $record['license_plate'] ?? '',
                number_format($record['odometer_reading'] ?? 0),
                $record['fuel_type'] ?? '',
                number_format($record['price_per_liter'] ?? 0, 2),
                number_format($record['liters'] ?? 0, 2),
                number_format($record['total_cost'] ?? 0, 2),
                number_format($distance, 0),
                number_format($costPerKm, 2),
                number_format($kmPerLiter, 2),
                number_format($literPerKm, 4)
            ];
        }
        
        return $exportData;
    }
    
    /**
     * สร้างชื่อไฟล์สำหรับรายงานการเติมน้ำมัน
     */
    public static function generateFuelFilename($factoryCode = null) {
        return parent::generateFilename('fuel_report', $factoryCode);
    }

    
    // เพิ่ม alias methods เพื่อความสะดวกในการใช้งาน
    public static function prepareExportData($records) {
        return self::prepareFuelExportData($records);
    }
    
    public static function generateFilename($prefix = 'fuel_report', $factoryCode = null) {
        return parent::generateFilename($prefix, $factoryCode);
    }
}
?>
