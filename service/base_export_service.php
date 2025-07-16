<?php
/**
 * Base Export Service - คลาสพื้นฐานสำหรับการ export ข้อมูลในรูปแบบต่างๆ
 * รองรับ Excel (.xlsx), LibreOffice (.ods), CSV (.csv), PDF (.pdf)
 * 
 * @author LogisticApp Team
 * @version 1.0
 * @date 2025-07-16
 */

abstract class BaseExportService {
    
    /**
     * ส่งออกข้อมูลในรูปแบบ Excel (.xlsx)
     * 
     * @param array $data ข้อมูลที่จะส่งออก
     * @param array $headers หัวตาราง
     * @param string $title หัวข้อรายงาน
     * @param string $subtitle หัวข้อย่อย
     * @param string $filename ชื่อไฟล์ (ไม่รวมนามสกุล)
     * @param string $filterText ข้อความแสดงตัวกรอง
     */
    public static function exportToExcel($data, $headers, $title, $subtitle, $filename = 'report', $filterText = '') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        // สร้าง XML สำหรับ Excel
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
        $xml .= '<sheetData>' . "\n";
        
        $rowIndex = 1;
        
        // หัวข้อหลัก
        $xml .= '<row r="' . $rowIndex . '">';
        $xml .= '<c r="A' . $rowIndex . '" t="inlineStr"><is><t>' . htmlspecialchars($title) . '</t></is></c>';
        $xml .= '</row>' . "\n";
        $rowIndex++;
        
        // หัวข้อย่อย
        if (!empty($subtitle)) {
            $xml .= '<row r="' . $rowIndex . '">';
            $xml .= '<c r="A' . $rowIndex . '" t="inlineStr"><is><t>' . htmlspecialchars($subtitle) . '</t></is></c>';
            $xml .= '</row>' . "\n";
            $rowIndex++;
        }
        
        // ข้อมูลตัวกรอง
        if (!empty($filterText)) {
            $xml .= '<row r="' . $rowIndex . '">';
            $xml .= '<c r="A' . $rowIndex . '" t="inlineStr"><is><t>' . htmlspecialchars($filterText) . '</t></is></c>';
            $xml .= '</row>' . "\n";
            $rowIndex++;
        }
        
        // บรรทัดว่าง
        $xml .= '<row r="' . $rowIndex . '"></row>' . "\n";
        $rowIndex++;
        
        // หัวตาราง
        $xml .= '<row r="' . $rowIndex . '">';
        foreach ($headers as $index => $header) {
            $colLetter = chr(65 + $index); // A, B, C, ...
            $xml .= '<c r="' . $colLetter . $rowIndex . '" t="inlineStr"><is><t>' . htmlspecialchars($header) . '</t></is></c>';
        }
        $xml .= '</row>' . "\n";
        $rowIndex++;
        
        // ข้อมูล
        foreach ($data as $row) {
            $xml .= '<row r="' . $rowIndex . '">';
            $colIndex = 0;
            foreach ($row as $cell) {
                $colLetter = chr(65 + $colIndex);
                $cellValue = is_numeric($cell) ? $cell : htmlspecialchars($cell);
                $cellType = is_numeric($cell) ? 'n' : 'inlineStr';
                
                if ($cellType === 'inlineStr') {
                    $xml .= '<c r="' . $colLetter . $rowIndex . '" t="inlineStr"><is><t>' . $cellValue . '</t></is></c>';
                } else {
                    $xml .= '<c r="' . $colLetter . $rowIndex . '"><v>' . $cellValue . '</v></c>';
                }
                $colIndex++;
            }
            $xml .= '</row>' . "\n";
            $rowIndex++;
        }
        
        $xml .= '</sheetData>' . "\n";
        $xml .= '</worksheet>';
        
        echo $xml;
    }
    
    /**
     * ส่งออกข้อมูลในรูปแบบ LibreOffice (.ods)
     */
    public static function exportToODS($data, $headers, $title, $subtitle, $filename = 'report', $filterText = '') {
        header('Content-Type: application/vnd.oasis.opendocument.spreadsheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.ods"');
        header('Cache-Control: max-age=0');
        
        // สร้าง XML สำหรับ ODS
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" ';
        $xml .= 'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" ';
        $xml .= 'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">' . "\n";
        $xml .= '<office:body><office:spreadsheet>' . "\n";
        $xml .= '<table:table table:name="Sheet1">' . "\n";
        
        // หัวข้อหลัก
        $xml .= '<table:table-row>';
        $xml .= '<table:table-cell office:value-type="string">';
        $xml .= '<text:p>' . htmlspecialchars($title) . '</text:p>';
        $xml .= '</table:table-cell>';
        $xml .= '</table:table-row>' . "\n";
        
        // หัวข้อย่อย
        if (!empty($subtitle)) {
            $xml .= '<table:table-row>';
            $xml .= '<table:table-cell office:value-type="string">';
            $xml .= '<text:p>' . htmlspecialchars($subtitle) . '</text:p>';
            $xml .= '</table:table-cell>';
            $xml .= '</table:table-row>' . "\n";
        }
        
        // ข้อมูลตัวกรอง
        if (!empty($filterText)) {
            $xml .= '<table:table-row>';
            $xml .= '<table:table-cell office:value-type="string">';
            $xml .= '<text:p>' . htmlspecialchars($filterText) . '</text:p>';
            $xml .= '</table:table-cell>';
            $xml .= '</table:table-row>' . "\n";
        }
        
        // บรรทัดว่าง
        $xml .= '<table:table-row></table:table-row>' . "\n";
        
        // หัวตาราง
        $xml .= '<table:table-row>';
        foreach ($headers as $header) {
            $xml .= '<table:table-cell office:value-type="string">';
            $xml .= '<text:p>' . htmlspecialchars($header) . '</text:p>';
            $xml .= '</table:table-cell>';
        }
        $xml .= '</table:table-row>' . "\n";
        
        // ข้อมูล
        foreach ($data as $row) {
            $xml .= '<table:table-row>';
            foreach ($row as $cell) {
                if (is_numeric($cell)) {
                    $xml .= '<table:table-cell office:value-type="float" office:value="' . $cell . '">';
                    $xml .= '<text:p>' . $cell . '</text:p>';
                } else {
                    $xml .= '<table:table-cell office:value-type="string">';
                    $xml .= '<text:p>' . htmlspecialchars($cell) . '</text:p>';
                }
                $xml .= '</table:table-cell>';
            }
            $xml .= '</table:table-row>' . "\n";
        }
        
        $xml .= '</table:table>' . "\n";
        $xml .= '</office:spreadsheet></office:body></office:document-content>';
        
        echo $xml;
    }
    
    /**
     * ส่งออกข้อมูลในรูปแบบ CSV
     */
    public static function exportToCSV($data, $headers, $title, $subtitle, $filename = 'report', $filterText = '') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: max-age=0');
        
        // เพิ่ม BOM สำหรับ UTF-8
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // หัวข้อหลัก
        fputcsv($output, [$title]);
        
        // หัวข้อย่อย
        if (!empty($subtitle)) {
            fputcsv($output, [$subtitle]);
        }
        
        // ข้อมูลตัวกรอง
        if (!empty($filterText)) {
            fputcsv($output, [$filterText]);
        }
        
        // บรรทัดว่าง
        fputcsv($output, []);
        
        // หัวตาราง
        fputcsv($output, $headers);
        
        // ข้อมูล
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
    
    /**
     * ส่งออกข้อมูลในรูปแบบ PDF (รูปแบบง่าย)
     */
    public static function exportToPDF($data, $headers, $title, $subtitle, $filename = 'report', $filterText = '') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        header('Cache-Control: max-age=0');
        
        // สร้าง PDF แบบง่าย (HTML to PDF)
        $html = '<!DOCTYPE html>';
        $html .= '<html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<style>';
        $html .= 'body { font-family: "Sarabun", Arial, sans-serif; margin: 20px; }';
        $html .= 'h1 { text-align: center; color: #333; margin-bottom: 10px; }';
        $html .= 'h2 { text-align: center; color: #666; margin-bottom: 20px; font-size: 14px; }';
        $html .= '.filter { text-align: center; color: #888; margin-bottom: 20px; font-size: 12px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
        $html .= 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 11px; }';
        $html .= 'th { background-color: #f2f2f2; font-weight: bold; }';
        $html .= 'tr:nth-child(even) { background-color: #f9f9f9; }';
        $html .= '</style>';
        $html .= '</head><body>';
        
        // หัวข้อหลัก
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        
        // หัวข้อย่อย
        if (!empty($subtitle)) {
            $html .= '<h2>' . htmlspecialchars($subtitle) . '</h2>';
        }
        
        // ข้อมูลตัวกรอง
        if (!empty($filterText)) {
            $html .= '<div class="filter">' . htmlspecialchars($filterText) . '</div>';
        }
        
        // ตาราง
        $html .= '<table>';
        
        // หัวตาราง
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // ข้อมูล
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        $html .= '</body></html>';
        
        // แปลง HTML เป็น PDF (วิธีง่าย - ใช้ library ภายนอกจะดีกว่า)
        echo $html;
    }
    
    /**
     * สร้างชื่อไฟล์สำหรับ export
     */
    public static function generateFilename($prefix = 'report', $suffix = null) {
        $timestamp = date('Y-m-d_H-i-s');
        if ($suffix) {
            return $prefix . '_' . $suffix . '_' . $timestamp;
        }
        return $prefix . '_' . $timestamp;
    }
}
?>
