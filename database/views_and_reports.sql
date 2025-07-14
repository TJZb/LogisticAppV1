-- Views สำหรับ Reports และ Analytics
-- สำหรับระบบ Logistic App

USE [logistic_app_db]
GO

-- =============================================
-- View: รายงานรถทั้งหมดพร้อมรายละเอียด
-- =============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VehicleDetailsView')
    DROP VIEW VehicleDetailsView
GO

CREATE VIEW VehicleDetailsView AS
SELECT 
    v.vehicle_id,
    v.license_plate,
    v.province,
    v.registration_date,
    vb.brand_name,
    v.model_name,
    v.year_manufactured,
    YEAR(GETDATE()) - v.year_manufactured AS vehicle_age,
    v.color,
    v.chassis_number,
    ft.fuel_name,
    ft.fuel_code,
    vc.category_name,
    vc.category_code,
    v.payload_weight,
    v.gross_weight,
    v.seating_capacity,
    v.status,
    CASE v.status
        WHEN 'active' THEN N'พร้อมใช้งาน'
        WHEN 'maintenance' THEN N'อยู่ระหว่างซ่อมบำรุง'
        WHEN 'in_use' THEN N'กำลังใช้งาน'
        WHEN 'out_of_service' THEN N'หยุดใช้งาน'
        WHEN 'sold' THEN N'ขายแล้ว'
        ELSE v.status
    END AS status_thai,
    v.vehicle_description,
    v.created_at,
    v.updated_at
FROM vehicles v
LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
LEFT JOIN fuel_types ft ON v.fuel_type_id = ft.fuel_type_id
LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
WHERE v.is_deleted = 0
GO

-- =============================================
-- View: รายงานสถิติรถแยกตามประเภท
-- =============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VehicleStatsByCategory')
    DROP VIEW VehicleStatsByCategory
GO

CREATE VIEW VehicleStatsByCategory AS
SELECT 
    vc.category_name,
    vc.category_code,
    COUNT(*) AS total_vehicles,
    SUM(CASE WHEN v.status = 'active' THEN 1 ELSE 0 END) AS active_count,
    SUM(CASE WHEN v.status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_count,
    SUM(CASE WHEN v.status = 'in_use' THEN 1 ELSE 0 END) AS in_use_count,
    SUM(CASE WHEN v.status = 'out_of_service' THEN 1 ELSE 0 END) AS out_of_service_count,
    AVG(CAST(YEAR(GETDATE()) - v.year_manufactured AS FLOAT)) AS avg_age,
    MIN(v.year_manufactured) AS oldest_year,
    MAX(v.year_manufactured) AS newest_year,
    SUM(v.payload_weight) AS total_payload_capacity,
    AVG(v.payload_weight) AS avg_payload_capacity
FROM vehicles v
LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
WHERE v.is_deleted = 0
GROUP BY vc.category_name, vc.category_code
GO

-- =============================================
-- View: รายงานรถแยกตามยี่ห้อ
-- =============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VehicleStatsByBrand')
    DROP VIEW VehicleStatsByBrand
GO

CREATE VIEW VehicleStatsByBrand AS
SELECT 
    vb.brand_name,
    vb.brand_code,
    vb.country,
    COUNT(*) AS total_vehicles,
    SUM(CASE WHEN v.status = 'active' THEN 1 ELSE 0 END) AS active_count,
    AVG(CAST(YEAR(GETDATE()) - v.year_manufactured AS FLOAT)) AS avg_age,
    MIN(v.year_manufactured) AS oldest_year,
    MAX(v.year_manufactured) AS newest_year,
    SUM(v.payload_weight) AS total_payload_capacity
FROM vehicles v
LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
WHERE v.is_deleted = 0
GROUP BY vb.brand_name, vb.brand_code, vb.country
GO

-- =============================================
-- View: รายงานรถแยกตามประเภทเชื้อเพลิง
-- =============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VehicleStatsByFuelType')
    DROP VIEW VehicleStatsByFuelType
GO

CREATE VIEW VehicleStatsByFuelType AS
SELECT 
    ft.fuel_name,
    ft.fuel_code,
    COUNT(*) AS total_vehicles,
    SUM(CASE WHEN v.status = 'active' THEN 1 ELSE 0 END) AS active_count,
    AVG(CAST(YEAR(GETDATE()) - v.year_manufactured AS FLOAT)) AS avg_age,
    SUM(v.payload_weight) AS total_payload_capacity,
    AVG(v.payload_weight) AS avg_payload_capacity
FROM vehicles v
LEFT JOIN fuel_types ft ON v.fuel_type_id = ft.fuel_type_id
WHERE v.is_deleted = 0
GROUP BY ft.fuel_name, ft.fuel_code
GO

-- =============================================
-- View: รายงานสรุปทั่วไป
-- =============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VehicleSummaryReport')
    DROP VIEW VehicleSummaryReport
GO

CREATE VIEW VehicleSummaryReport AS
SELECT 
    (SELECT COUNT(*) FROM vehicles WHERE is_deleted = 0) AS total_vehicles,
    (SELECT COUNT(*) FROM vehicles WHERE status = 'active' AND is_deleted = 0) AS active_vehicles,
    (SELECT COUNT(*) FROM vehicles WHERE status = 'maintenance' AND is_deleted = 0) AS maintenance_vehicles,
    (SELECT COUNT(*) FROM vehicles WHERE status = 'in_use' AND is_deleted = 0) AS in_use_vehicles,
    (SELECT COUNT(*) FROM vehicles WHERE status = 'out_of_service' AND is_deleted = 0) AS out_of_service_vehicles,
    (SELECT COUNT(DISTINCT brand_id) FROM vehicles WHERE is_deleted = 0) AS total_brands,
    (SELECT COUNT(DISTINCT category_id) FROM vehicles WHERE is_deleted = 0) AS total_categories,
    (SELECT AVG(CAST(YEAR(GETDATE()) - year_manufactured AS FLOAT)) FROM vehicles WHERE is_deleted = 0) AS avg_vehicle_age,
    (SELECT SUM(payload_weight) FROM vehicles WHERE is_deleted = 0) AS total_payload_capacity,
    (SELECT AVG(payload_weight) FROM vehicles WHERE is_deleted = 0) AS avg_payload_capacity,
    (SELECT MIN(year_manufactured) FROM vehicles WHERE is_deleted = 0) AS oldest_vehicle_year,
    (SELECT MAX(year_manufactured) FROM vehicles WHERE is_deleted = 0) AS newest_vehicle_year
GO

-- =============================================
-- View: รายงานรถตามจังหวัด
-- =============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VehicleStatsByProvince')
    DROP VIEW VehicleStatsByProvince
GO

CREATE VIEW VehicleStatsByProvince AS
SELECT 
    v.province,
    COUNT(*) AS total_vehicles,
    SUM(CASE WHEN v.status = 'active' THEN 1 ELSE 0 END) AS active_count,
    SUM(CASE WHEN v.status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_count,
    AVG(CAST(YEAR(GETDATE()) - v.year_manufactured AS FLOAT)) AS avg_age,
    SUM(v.payload_weight) AS total_payload_capacity
FROM vehicles v
WHERE v.is_deleted = 0
GROUP BY v.province
GO

-- =============================================
-- View: รายงานรถตามช่วงปี
-- =============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VehicleStatsByYearRange')
    DROP VIEW VehicleStatsByYearRange
GO

CREATE VIEW VehicleStatsByYearRange AS
SELECT 
    CASE 
        WHEN year_manufactured >= 2020 THEN N'2020-ปัจจุบัน'
        WHEN year_manufactured >= 2015 THEN N'2015-2019'
        WHEN year_manufactured >= 2010 THEN N'2010-2014'
        WHEN year_manufactured >= 2005 THEN N'2005-2009'
        ELSE N'ก่อน 2005'
    END AS year_range,
    COUNT(*) AS total_vehicles,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
    MIN(year_manufactured) AS min_year,
    MAX(year_manufactured) AS max_year,
    AVG(payload_weight) AS avg_payload_capacity
FROM vehicles
WHERE is_deleted = 0
GROUP BY 
    CASE 
        WHEN year_manufactured >= 2020 THEN N'2020-ปัจจุบัน'
        WHEN year_manufactured >= 2015 THEN N'2015-2019'
        WHEN year_manufactured >= 2010 THEN N'2010-2014'
        WHEN year_manufactured >= 2005 THEN N'2005-2009'
        ELSE N'ก่อน 2005'
    END
GO

-- =============================================
-- Function: คำนวณอายุรถ
-- =============================================
IF EXISTS (SELECT * FROM sys.objects WHERE name = 'GetVehicleAge' AND type = 'FN')
    DROP FUNCTION GetVehicleAge
GO

CREATE FUNCTION GetVehicleAge(@year_manufactured INT)
RETURNS INT
AS
BEGIN
    RETURN YEAR(GETDATE()) - @year_manufactured
END
GO

-- =============================================
-- Function: แปลงสถานะเป็นภาษาไทย
-- =============================================
IF EXISTS (SELECT * FROM sys.objects WHERE name = 'GetStatusInThai' AND type = 'FN')
    DROP FUNCTION GetStatusInThai
GO

CREATE FUNCTION GetStatusInThai(@status NVARCHAR(20))
RETURNS NVARCHAR(50)
AS
BEGIN
    DECLARE @status_thai NVARCHAR(50)
    
    SET @status_thai = CASE @status
        WHEN 'active' THEN N'พร้อมใช้งาน'
        WHEN 'maintenance' THEN N'อยู่ระหว่างซ่อมบำรุง'
        WHEN 'in_use' THEN N'กำลังใช้งาน'
        WHEN 'out_of_service' THEN N'หยุดใช้งาน'
        WHEN 'sold' THEN N'ขายแล้ว'
        ELSE @status
    END
    
    RETURN @status_thai
END
GO

-- =============================================
-- Stored Procedure: ดึงรายงานสรุป
-- =============================================
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'GetVehicleSummaryReport')
    DROP PROCEDURE GetVehicleSummaryReport
GO

CREATE PROCEDURE GetVehicleSummaryReport
AS
BEGIN
    SET NOCOUNT ON;
    
    -- รายงานสรุปหลัก
    SELECT * FROM VehicleSummaryReport;
    
    -- รายงานแยกตามประเภท
    SELECT TOP 10 * FROM VehicleStatsByCategory ORDER BY total_vehicles DESC;
    
    -- รายงานแยกตามยี่ห้อ
    SELECT TOP 10 * FROM VehicleStatsByBrand ORDER BY total_vehicles DESC;
    
    -- รายงานแยกตามเชื้อเพลิง
    SELECT * FROM VehicleStatsByFuelType ORDER BY total_vehicles DESC;
END
GO

-- =============================================
-- Stored Procedure: ค้นหารถขั้นสูง
-- =============================================
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SearchVehiclesAdvanced')
    DROP PROCEDURE SearchVehiclesAdvanced
GO

CREATE PROCEDURE SearchVehiclesAdvanced
    @license_plate NVARCHAR(20) = NULL,
    @brand_id INT = NULL,
    @category_id INT = NULL,
    @fuel_type_id INT = NULL,
    @status NVARCHAR(20) = NULL,
    @year_from INT = NULL,
    @year_to INT = NULL,
    @province NVARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT * FROM VehicleDetailsView
    WHERE 
        (@license_plate IS NULL OR license_plate LIKE '%' + @license_plate + '%')
        AND (@brand_id IS NULL OR vehicle_id IN (SELECT vehicle_id FROM vehicles WHERE brand_id = @brand_id))
        AND (@category_id IS NULL OR vehicle_id IN (SELECT vehicle_id FROM vehicles WHERE category_id = @category_id))
        AND (@fuel_type_id IS NULL OR vehicle_id IN (SELECT vehicle_id FROM vehicles WHERE fuel_type_id = @fuel_type_id))
        AND (@status IS NULL OR status = @status)
        AND (@year_from IS NULL OR year_manufactured >= @year_from)
        AND (@year_to IS NULL OR year_manufactured <= @year_to)
        AND (@province IS NULL OR province LIKE '%' + @province + '%')
    ORDER BY license_plate;
END
GO

PRINT 'Views และ Functions สำหรับ Reports ถูกสร้างเรียบร้อยแล้ว'
