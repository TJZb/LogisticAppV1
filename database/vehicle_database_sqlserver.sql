-- ฐานข้อมูลระบบจัดการรถและยานพาหนะ
-- Logistic App - Vehicle Management Database (SQL Server)

-- สร้างฐานข้อมูล
IF NOT EXISTS (SELECT name FROM master.dbo.sysdatabases WHERE name = N'logistic_app_db')
BEGIN
    CREATE DATABASE [logistic_app_db]
    COLLATE Thai_CI_AS
END
GO

USE [logistic_app_db]
GO

-- ตารางประเภทเชื้อเพลิง
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='fuel_types' AND xtype='U')
BEGIN
    CREATE TABLE fuel_types (
        fuel_type_id INT IDENTITY(1,1) PRIMARY KEY,
        fuel_name NVARCHAR(50) NOT NULL, -- ชื่อประเภทเชื้อเพลิง
        fuel_code NVARCHAR(10) NOT NULL UNIQUE, -- รหัสเชื้อเพลิง
        description NVARCHAR(MAX) NULL, -- รายละเอียดเพิ่มเติม
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ข้อมูลประเภทเชื้อเพลิงเริ่มต้น
INSERT INTO fuel_types (fuel_name, fuel_code, description) VALUES
(N'เบนซิน 95', 'G95', N'เบนซินออกเทน 95'),
(N'เบนซิน 91', 'G91', N'เบนซินออกเทน 91'),
(N'ดีเซล', 'DSL', N'น้ำมันดีเซล'),
(N'แก๊สโซฮอล์ 95', 'GH95', N'แก๊สโซฮอล์ออกเทน 95'),
(N'แก๊สโซฮอล์ 91', 'GH91', N'แก๊สโซฮอล์ออกเทน 91'),
(N'แก๊สโซฮอล์ E20', 'E20', N'แก๊สโซฮอล์ E20'),
(N'แก๊สโซฮอล์ E85', 'E85', N'แก๊สโซฮอล์ E85'),
(N'แก๊ส NGV', 'NGV', N'แก๊สธรรมชาติ'),
(N'แก๊ส LPG', 'LPG', N'แก๊สหุงต้ม'),
(N'ไฟฟ้า', 'EV', N'รถยนต์ไฟฟ้า'),
(N'ไฮบริด', 'HYB', N'รถยนต์ไฮบริด')
GO

-- ตารางยี่ห้อรถ
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vehicle_brands' AND xtype='U')
BEGIN
    CREATE TABLE vehicle_brands (
        brand_id INT IDENTITY(1,1) PRIMARY KEY,
        brand_name NVARCHAR(100) NOT NULL, -- ชื่อยี่ห้อ
        brand_code NVARCHAR(20) NOT NULL UNIQUE, -- รหัสยี่ห้อ
        country NVARCHAR(50) NULL, -- ประเทศต้นกำเนิด
        logo_url NVARCHAR(255) NULL, -- URL โลโก้ยี่ห้อ
        is_active BIT DEFAULT 1, -- สถานะการใช้งาน
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ข้อมูลยี่ห้อรถเริ่มต้น
INSERT INTO vehicle_brands (brand_name, brand_code, country) VALUES
('Isuzu', 'ISU', 'Japan'),
('Hino', 'HIN', 'Japan'),
('Mercedes-Benz', 'MBZ', 'Germany'),
('Volvo', 'VOL', 'Sweden'),
('Scania', 'SCA', 'Sweden'),
('MAN', 'MAN', 'Germany'),
('DAF', 'DAF', 'Netherlands'),
('Iveco', 'IVE', 'Italy'),
('Mitsubishi Fuso', 'MFS', 'Japan'),
('UD Trucks', 'UD', 'Japan'),
('Toyota', 'TOY', 'Japan'),
('Nissan', 'NIS', 'Japan'),
('Ford', 'FOR', 'USA'),
('Chevrolet', 'CHE', 'USA')
GO

-- ตารางประเภทรถ
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vehicle_categories' AND xtype='U')
BEGIN
    CREATE TABLE vehicle_categories (
        category_id INT IDENTITY(1,1) PRIMARY KEY,
        category_name NVARCHAR(100) NOT NULL, -- ชื่อประเภทรถ
        category_code NVARCHAR(20) NOT NULL UNIQUE, -- รหัสประเภทรถ
        description NVARCHAR(MAX) NULL, -- รายละเอียดประเภทรถ
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ข้อมูลประเภทรถเริ่มต้น
INSERT INTO vehicle_categories (category_name, category_code, description) VALUES
(N'รถบรรทุก 4 ล้อ', 'TRK4', N'รถบรรทุกขนาดเล็ก 4 ล้อ'),
(N'รถบรรทุก 6 ล้อ', 'TRK6', N'รถบรรทุกขนาดกลาง 6 ล้อ'),
(N'รถบรรทุก 10 ล้อ', 'TRK10', N'รถบรรทุกขนาดใหญ่ 10 ล้อ'),
(N'รถพ่วง', 'TRL', N'รถพ่วงสำหรับขนส่งสินค้า'),
(N'รถถังน้ำมัน', 'TNK', N'รถถังสำหรับขนส่งเชื้อเพลิง'),
(N'รถกระบะ', 'PCK', N'รถกระบะขนาดเล็ก'),
(N'รถเก็บขยะ', 'GRB', N'รถเก็บขยะ'),
(N'รถยก', 'CRN', N'รถยกสำหรับงานก่อสร้าง'),
(N'รถคอนเทนเนอร์', 'CON', N'รถขนส่งคอนเทนเนอร์'),
(N'รถขนส่งรถยนต์', 'CAR', N'รถขนส่งรถยนต์')
GO

-- ตารางหลักประวัติรถ
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vehicles' AND xtype='U')
BEGIN
    CREATE TABLE vehicles (
        vehicle_id INT IDENTITY(1,1) PRIMARY KEY,
        
        -- ข้อมูลการจดทะเบียน
        license_plate NVARCHAR(20) NOT NULL UNIQUE, -- ทะเบียนรถ
        province NVARCHAR(100) NOT NULL, -- จังหวัดที่จดทะเบียน
        registration_date DATE NOT NULL, -- วันที่จดทะเบียน
        
        -- ข้อมูลรถ
        brand_id INT NOT NULL, -- ยี่ห้อรถ
        model_name NVARCHAR(100) NOT NULL, -- ชื่อรุ่น
        model_code NVARCHAR(50) NULL, -- รหัสรุ่น
        year_manufactured INT NOT NULL, -- ปีที่ผลิต
        color NVARCHAR(50) NOT NULL, -- สีรถ
        
        -- เลขประจำตัวรถ
        chassis_number NVARCHAR(50) NOT NULL UNIQUE, -- เลขตัวรถ/เลขถัง
        engine_number NVARCHAR(50) NULL, -- เลขเครื่องยนต์
        
        -- ข้อมูลเชื้อเพลิง
        fuel_type_id INT NOT NULL, -- ประเภทเชื้อเพลิงหลัก
        gas_tank_number NVARCHAR(30) NULL, -- เลขถังแก๊ส (กรณีใช้แก๊ส)
        fuel_capacity DECIMAL(8,2) NULL, -- ความจุถังเชื้อเพลิง (ลิตร)
        
        -- น้ำหนักและขนาด
        payload_weight DECIMAL(10,2) NOT NULL, -- น้ำหนักที่บรรทุกได้ (กิโลกรัม)
        gross_weight DECIMAL(10,2) NOT NULL, -- น้ำหนักรวม (กิโลกรัม)
        curb_weight DECIMAL(10,2) NULL, -- น้ำหนักรถเปล่า (กิโลกรัม)
        
        -- ข้อมูลที่นั่ง
        seating_capacity INT NOT NULL DEFAULT 2, -- จำนวนที่นั่ง
        
        -- ประเภทรถ
        category_id INT NOT NULL, -- ประเภทรถ
        
        -- ข้อมูลเพิ่มเติม
        vehicle_description NVARCHAR(MAX) NULL, -- รายละเอียดเพิ่มเติม
        vehicle_image_url NVARCHAR(255) NULL, -- URL รูปภาพรถ
        
        -- สถานะ
        status NVARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'maintenance', 'in_use', 'out_of_service', 'sold')), -- สถานะรถ
        is_deleted BIT DEFAULT 0, -- สถานะการลบ
        
        -- ข้อมูลการซื้อ/ขาย
        purchase_date DATE NULL, -- วันที่ซื้อ
        purchase_price DECIMAL(12,2) NULL, -- ราคาซื้อ
        current_value DECIMAL(12,2) NULL, -- มูลค่าปัจจุบัน
        
        -- ข้อมูลประกัน
        insurance_company NVARCHAR(100) NULL, -- บริษัทประกัน
        insurance_policy NVARCHAR(50) NULL, -- เลขที่กรมธรรม์
        insurance_expire_date DATE NULL, -- วันหมดอายุประกัน
        
        -- ข้อมูลตรวจสภาพรถ
        inspection_expire_date DATE NULL, -- วันหมดอายุการตรวจสภาพรถ
        
        -- Timestamps
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE(),
        created_by INT NULL, -- ผู้สร้างรายการ
        updated_by INT NULL, -- ผู้แก้ไขล่าสุด
        
        -- Foreign Keys
        FOREIGN KEY (brand_id) REFERENCES vehicle_brands(brand_id),
        FOREIGN KEY (fuel_type_id) REFERENCES fuel_types(fuel_type_id),
        FOREIGN KEY (category_id) REFERENCES vehicle_categories(category_id)
    )
END
GO

-- สร้าง Index
CREATE NONCLUSTERED INDEX IX_vehicles_license_plate ON vehicles (license_plate)
CREATE NONCLUSTERED INDEX IX_vehicles_brand_model ON vehicles (brand_id, model_name)
CREATE NONCLUSTERED INDEX IX_vehicles_status ON vehicles (status)
CREATE NONCLUSTERED INDEX IX_vehicles_registration_date ON vehicles (registration_date)
CREATE NONCLUSTERED INDEX IX_vehicles_year ON vehicles (year_manufactured)
CREATE NONCLUSTERED INDEX IX_vehicles_category ON vehicles (category_id)
GO

-- ตารางประวัติการบำรุงรักษา
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='maintenance_history' AND xtype='U')
BEGIN
    CREATE TABLE maintenance_history (
        maintenance_id INT IDENTITY(1,1) PRIMARY KEY,
        vehicle_id INT NOT NULL,
        maintenance_date DATE NOT NULL, -- วันที่บำรุงรักษา
        maintenance_type NVARCHAR(20) NOT NULL CHECK (maintenance_type IN ('preventive', 'corrective', 'emergency')), -- ประเภทการบำรุงรักษา
        description NVARCHAR(MAX) NOT NULL, -- รายละเอียดการบำรุงรักษา
        cost DECIMAL(10,2) NULL, -- ค่าใช้จ่าย
        service_provider NVARCHAR(100) NULL, -- ผู้ให้บริการ
        mileage INT NULL, -- เลขไมล์ขณะบำรุงรักษา
        next_maintenance_date DATE NULL, -- วันนัดบำรุงรักษาครั้งถัดไป
        next_maintenance_mileage INT NULL, -- ไมล์นัดบำรุงรักษาครั้งถัดไป
        created_at DATETIME2 DEFAULT GETDATE(),
        created_by INT NULL, -- ผู้บันทึก
        
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE
    )
END
GO

-- สร้าง Index สำหรับ maintenance_history
CREATE NONCLUSTERED INDEX IX_maintenance_history_vehicle_date ON maintenance_history (vehicle_id, maintenance_date)
CREATE NONCLUSTERED INDEX IX_maintenance_history_type ON maintenance_history (maintenance_type)
GO

-- ตารางประวัติการใช้งาน
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vehicle_usage_history' AND xtype='U')
BEGIN
    CREATE TABLE vehicle_usage_history (
        usage_id INT IDENTITY(1,1) PRIMARY KEY,
        vehicle_id INT NOT NULL,
        driver_name NVARCHAR(100) NULL, -- ชื่อคนขับ
        start_date DATE NOT NULL, -- วันที่เริ่มใช้งาน
        end_date DATE NULL, -- วันที่สิ้นสุดการใช้งาน
        start_mileage INT NULL, -- เลขไมล์เริ่มต้น
        end_mileage INT NULL, -- เลขไมล์สิ้นสุด
        purpose NVARCHAR(MAX) NULL, -- วัตถุประสงค์การใช้งาน
        destination NVARCHAR(255) NULL, -- จุดหมายปลายทาง
        fuel_consumed DECIMAL(8,2) NULL, -- เชื้อเพลิงที่ใช้ (ลิตร)
        notes NVARCHAR(MAX) NULL, -- หมายเหตุ
        created_at DATETIME2 DEFAULT GETDATE(),
        created_by INT NULL, -- ผู้บันทึก
        
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE
    )
END
GO

-- สร้าง Index สำหรับ vehicle_usage_history
CREATE NONCLUSTERED INDEX IX_vehicle_usage_history_vehicle_date ON vehicle_usage_history (vehicle_id, start_date)
CREATE NONCLUSTERED INDEX IX_vehicle_usage_history_driver ON vehicle_usage_history (driver_name)
GO

-- ข้อมูลตัวอย่างรถ
INSERT INTO vehicles (
    license_plate, province, registration_date, brand_id, model_name, model_code,
    year_manufactured, color, chassis_number, engine_number, fuel_type_id,
    payload_weight, gross_weight, curb_weight, seating_capacity, category_id,
    vehicle_description, status, purchase_date, purchase_price
) VALUES 
(
    N'กข-1234', N'กรุงเทพมหานคร', '2021-03-15', 1, 'FRR 90 N', 'FRR90N',
    2021, N'ขาว', 'JALE5W16057000001', 'ENG001234', 3,
    5000.00, 8500.00, 3500.00, 2, 2,
    N'รถบรรทุก 6 ล้อ สำหรับขนส่งสินค้าทั่วไป', 'active', '2021-03-10', 1850000.00
),
(
    N'คง-5678', N'กรุงเทพมหานคร', '2020-08-20', 2, 'FM 260 JD', 'FM260JD',
    2020, N'เงิน', 'JHDG5W16048000002', 'ENG005678', 3,
    15000.00, 25000.00, 10000.00, 2, 5,
    N'รถถังน้ำมันสำหรับขนส่งเชื้อเพลิง', 'in_use', '2020-08-15', 3200000.00
),
(
    N'กค-9012', N'กรุงเทพมหานคร', '2019-12-05', 3, 'Actros 2542', 'ACTROS2542',
    2019, N'น้ำเงิน', 'WDB9304461L123456', 'ENG009012', 3,
    25000.00, 42000.00, 17000.00, 2, 4,
    N'รถพ่วงสำหรับขนส่งสินค้าหนัก', 'maintenance', '2019-11-28', 4500000.00
)
GO

-- สร้าง View สำหรับดูข้อมูลรถพร้อมรายละเอียด
CREATE VIEW vehicle_details_view AS
SELECT 
    v.vehicle_id,
    v.license_plate,
    v.province,
    v.registration_date,
    vb.brand_name,
    v.model_name,
    v.model_code,
    v.year_manufactured,
    v.color,
    v.chassis_number,
    ft.fuel_name,
    v.gas_tank_number,
    v.payload_weight,
    v.gross_weight,
    v.curb_weight,
    v.seating_capacity,
    vc.category_name,
    v.status,
    v.purchase_date,
    v.purchase_price,
    v.insurance_expire_date,
    v.inspection_expire_date,
    v.created_at
FROM vehicles v
LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
LEFT JOIN fuel_types ft ON v.fuel_type_id = ft.fuel_type_id
LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id
WHERE v.is_deleted = 0
GO

-- สร้าง Stored Procedure สำหรับเพิ่มรถใหม่
CREATE PROCEDURE AddNewVehicle
    @license_plate NVARCHAR(20),
    @province NVARCHAR(100),
    @registration_date DATE,
    @brand_id INT,
    @model_name NVARCHAR(100),
    @year_manufactured INT,
    @color NVARCHAR(50),
    @chassis_number NVARCHAR(50),
    @fuel_type_id INT,
    @payload_weight DECIMAL(10,2),
    @gross_weight DECIMAL(10,2),
    @seating_capacity INT,
    @category_id INT,
    @created_by INT = NULL
AS
BEGIN
    DECLARE @vehicle_exists INT = 0
    
    -- ตรวจสอบว่าทะเบียนรถซ้ำหรือไม่
    SELECT @vehicle_exists = COUNT(*) 
    FROM vehicles 
    WHERE license_plate = @license_plate AND is_deleted = 0
    
    IF @vehicle_exists > 0
    BEGIN
        RAISERROR (N'ทะเบียนรถนี้มีอยู่ในระบบแล้ว', 16, 1)
        RETURN
    END
    
    INSERT INTO vehicles (
        license_plate, province, registration_date, brand_id, model_name,
        year_manufactured, color, chassis_number, fuel_type_id,
        payload_weight, gross_weight, seating_capacity, category_id, created_by
    ) VALUES (
        @license_plate, @province, @registration_date, @brand_id, @model_name,
        @year_manufactured, @color, @chassis_number, @fuel_type_id,
        @payload_weight, @gross_weight, @seating_capacity, @category_id, @created_by
    )
    
    SELECT SCOPE_IDENTITY() as vehicle_id, N'เพิ่มรถใหม่เรียบร้อยแล้ว' as message
END
GO

-- สร้าง Function สำหรับคำนวณอายุรถ
CREATE FUNCTION GetVehicleAge(@vehicle_year INT) 
RETURNS INT
AS
BEGIN
    RETURN YEAR(GETDATE()) - @vehicle_year
END
GO

-- สร้าง Trigger สำหรับอัปเดต updated_at อัตโนมัติ
CREATE TRIGGER tr_vehicles_update 
ON vehicles
AFTER UPDATE
AS
BEGIN
    UPDATE vehicles 
    SET updated_at = GETDATE()
    FROM vehicles v
    INNER JOIN inserted i ON v.vehicle_id = i.vehicle_id
END
GO

CREATE TRIGGER tr_vehicle_brands_update 
ON vehicle_brands
AFTER UPDATE
AS
BEGIN
    UPDATE vehicle_brands 
    SET updated_at = GETDATE()
    FROM vehicle_brands vb
    INNER JOIN inserted i ON vb.brand_id = i.brand_id
END
GO

CREATE TRIGGER tr_fuel_types_update 
ON fuel_types
AFTER UPDATE
AS
BEGIN
    UPDATE fuel_types 
    SET updated_at = GETDATE()
    FROM fuel_types ft
    INNER JOIN inserted i ON ft.fuel_type_id = i.fuel_type_id
END
GO

CREATE TRIGGER tr_vehicle_categories_update 
ON vehicle_categories
AFTER UPDATE
AS
BEGIN
    UPDATE vehicle_categories 
    SET updated_at = GETDATE()
    FROM vehicle_categories vc
    INNER JOIN inserted i ON vc.category_id = i.category_id
END
GO

-- ตารางแผนก
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='departments' AND xtype='U')
BEGIN
    CREATE TABLE departments (
        department_id INT IDENTITY(1,1) PRIMARY KEY,
        department_code NVARCHAR(20) NOT NULL UNIQUE, -- รหัสแผนก
        department_name NVARCHAR(100) NOT NULL, -- ชื่อแผนก
        description NVARCHAR(MAX) NULL, -- รายละเอียดแผนก
        manager_id INT NULL, -- รหัสหัวหน้าแผนก
        is_active BIT DEFAULT 1, -- สถานะการใช้งาน
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ข้อมูลแผนกเริ่มต้น
INSERT INTO departments (department_code, department_name, description) VALUES
('HR', N'บุคคล', N'แผนกทรัพยากรบุคคล'),
('FINANCE', N'การเงิน', N'แผนกการเงินและบัญชี'),
('LOGISTIC', N'โลจิสติกส์', N'แผนกขนส่งและคลังสินค้า'),
('WAREHOUSE', N'คลังสินค้า', N'แผนกจัดการคลังสินค้า'),
('TRANSPORT', N'ขนส่ง', N'แผนกขนส่งและจัดส่ง'),
('MAINTENANCE', N'ซ่อมบำรุง', N'แผนกซ่อมบำรุงยานพาหนะ'),
('IT', N'เทคโนโลยีสารสนเทศ', N'แผนกเทคโนโลยีสารสนเทศ'),
('ADMIN', N'บริหารทั่วไป', N'แผนกบริหารทั่วไป')
GO

-- ตารางตำแหน่งงาน
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='positions' AND xtype='U')
BEGIN
    CREATE TABLE positions (
        position_id INT IDENTITY(1,1) PRIMARY KEY,
        position_code NVARCHAR(20) NOT NULL UNIQUE, -- รหัสตำแหน่ง
        position_name NVARCHAR(100) NOT NULL, -- ชื่อตำแหน่ง
        position_level NVARCHAR(20) NOT NULL DEFAULT 'staff' CHECK (position_level IN ('executive', 'manager', 'supervisor', 'senior', 'staff', 'intern')), -- ระดับตำแหน่ง
        salary_min DECIMAL(10,2) NULL, -- เงินเดือนขั้นต่ำ
        salary_max DECIMAL(10,2) NULL, -- เงินเดือนขั้นสูง
        description NVARCHAR(MAX) NULL, -- รายละเอียดตำแหน่ง
        is_active BIT DEFAULT 1, -- สถานะการใช้งาน
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ข้อมูลตำแหน่งงานเริ่มต้น
INSERT INTO positions (position_code, position_name, position_level, salary_min, salary_max, description) VALUES
('MGR', N'ผู้จัดการ', 'manager', 50000.00, 80000.00, N'ผู้จัดการแผนก'),
('SUPR', N'หัวหน้างาน', 'supervisor', 35000.00, 50000.00, N'หัวหน้างานในแผนก'),
('SNR', N'พนักงานอาวุโส', 'senior', 25000.00, 40000.00, N'พนักงานอาวุโส'),
('STAFF', N'พนักงาน', 'staff', 18000.00, 30000.00, N'พนักงานทั่วไป'),
('DRIVER', N'พนักงานขับรถ', 'staff', 20000.00, 35000.00, N'พนักงานขับรถบรรทุก'),
('MECH', N'ช่างซ่อม', 'staff', 22000.00, 35000.00, N'ช่างซ่อมบำรุงรถ'),
('WAREH', N'พนักงานคลัง', 'staff', 18000.00, 28000.00, N'พนักงานคลังสินค้า'),
('ACCT', N'เจ้าหน้าที่บัญชี', 'staff', 22000.00, 35000.00, N'เจ้าหน้าที่บัญชี'),
('HR', N'เจ้าหน้าที่บุคคล', 'staff', 22000.00, 35000.00, N'เจ้าหน้าที่ทรัพยากรบุคคล'),
('IT', N'เจ้าหน้าที่ IT', 'staff', 25000.00, 45000.00, N'เจ้าหน้าที่เทคโนโลยีสารสนเทศ')
GO

-- ตารางพนักงาน
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='employees' AND xtype='U')
BEGIN
    CREATE TABLE employees (
        employee_id INT IDENTITY(1,1) PRIMARY KEY,
        employee_code NVARCHAR(20) NOT NULL UNIQUE, -- รหัสพนักงาน
        
        -- ข้อมูลส่วนตัว
        first_name NVARCHAR(100) NOT NULL, -- ชื่อ
        last_name NVARCHAR(100) NOT NULL, -- นามสกุล
        full_name AS (first_name + ' ' + last_name) PERSISTED, -- ชื่อเต็ม (computed column)
        nickname NVARCHAR(50) NULL, -- ชื่อเล่น
        
        -- ข้อมูลการติดต่อ
        phone NVARCHAR(20) NULL, -- เบอร์โทรศัพท์
        email NVARCHAR(100) NULL, -- อีเมล
        address NVARCHAR(MAX) NULL, -- ที่อยู่
        
        -- ข้อมูลประจำตัว
        citizen_id NVARCHAR(13) NULL UNIQUE, -- เลขบัตรประชาชน
        passport_number NVARCHAR(20) NULL, -- เลขหนังสือเดินทาง
        birth_date DATE NULL, -- วันเกิด
        gender NVARCHAR(10) NULL CHECK (gender IN ('male', 'female', 'other')), -- เพศ
        
        -- ข้อมูลการทำงาน
        department_id INT NOT NULL, -- แผนกที่สังกัด
        position_id INT NOT NULL, -- ตำแหน่งงาน
        hire_date DATE NOT NULL, -- วันที่เริ่มงาน
        probation_end_date DATE NULL, -- วันสิ้นสุดการทดลองงาน
        resign_date DATE NULL, -- วันลาออก
        
        -- ข้อมูลเงินเดือน
        base_salary DECIMAL(10,2) NULL, -- เงินเดือนพื้นฐาน
        allowances DECIMAL(10,2) NULL DEFAULT 0, -- เบี้ยเลี้ยง/ค่าตอบแทนพิเศษ
        
        -- ข้อมูลผู้ควบคุม
        supervisor_id INT NULL, -- รหัสหัวหน้างาน
        
        -- สถานะ
        status NVARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'probation', 'resigned', 'terminated', 'suspended')), -- สถานะการทำงาน
        is_driver BIT DEFAULT 0, -- เป็นพนักงานขับรถหรือไม่
        driver_license_number NVARCHAR(20) NULL, -- เลขใบขับขี่
        driver_license_expire_date DATE NULL, -- วันหมดอายุใบขับขี่
        
        -- ข้อมูลภาพ
        profile_image_url NVARCHAR(255) NULL, -- URL รูปโปรไฟล์
        
        -- Timestamps
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE(),
        created_by INT NULL, -- ผู้สร้างรายการ
        updated_by INT NULL, -- ผู้แก้ไขล่าสุด
        
        -- Foreign Keys
        FOREIGN KEY (department_id) REFERENCES departments(department_id),
        FOREIGN KEY (position_id) REFERENCES positions(position_id),
        FOREIGN KEY (supervisor_id) REFERENCES employees(employee_id)
    )
END
GO

-- สร้าง Index สำหรับตาราง employees
CREATE NONCLUSTERED INDEX IX_employees_employee_code ON employees (employee_code)
CREATE NONCLUSTERED INDEX IX_employees_department ON employees (department_id)
CREATE NONCLUSTERED INDEX IX_employees_position ON employees (position_id)
CREATE NONCLUSTERED INDEX IX_employees_name ON employees (first_name, last_name)
CREATE NONCLUSTERED INDEX IX_employees_status ON employees (status)
CREATE NONCLUSTERED INDEX IX_employees_hire_date ON employees (hire_date)
CREATE NONCLUSTERED INDEX IX_employees_supervisor ON employees (supervisor_id)
GO

-- เพิ่ม Foreign Key ให้ departments.manager_id อ้างอิงไปยัง employees
ALTER TABLE departments 
ADD CONSTRAINT FK_departments_manager 
FOREIGN KEY (manager_id) REFERENCES employees(employee_id)
GO

-- ข้อมูลพนักงานตัวอย่าง
INSERT INTO employees (
    employee_code, first_name, last_name, phone, email, 
    department_id, position_id, hire_date, base_salary, status, is_driver
) VALUES 
('EMP001', N'สมชาย', N'ใจดี', '081-234-5678', 'somchai@logistic.com', 
 3, 1, '2020-01-15', 55000.00, 'active', 0),
('EMP002', N'สมหญิง', N'รักงาน', '082-345-6789', 'somying@logistic.com', 
 5, 2, '2020-03-01', 40000.00, 'active', 0),
('EMP003', N'สมศักดิ์', N'ขับดี', '083-456-7890', 'somsak@logistic.com', 
 5, 5, '2021-06-01', 28000.00, 'active', 1),
('EMP004', N'สมปอง', N'ซ่อมเก่ง', '084-567-8901', 'sompong@logistic.com', 
 6, 6, '2021-02-15', 30000.00, 'active', 0),
('EMP005', N'สมใส', N'จัดการ', '085-678-9012', 'somsai@logistic.com', 
 4, 2, '2019-08-20', 42000.00, 'active', 0)
GO

-- สร้าง View สำหรับดูข้อมูลพนักงานพร้อมรายละเอียด
CREATE VIEW employee_details_view AS
SELECT 
    e.employee_id,
    e.employee_code,
    e.first_name,
    e.last_name,
    e.full_name,
    e.nickname,
    e.phone,
    e.email,
    d.department_name,
    d.department_code,
    p.position_name,
    p.position_level,
    e.hire_date,
    e.base_salary,
    e.allowances,
    (e.base_salary + ISNULL(e.allowances, 0)) as total_salary,
    supervisor.full_name as supervisor_name,
    e.status,
    e.is_driver,
    e.driver_license_number,
    e.driver_license_expire_date,
    DATEDIFF(YEAR, e.hire_date, GETDATE()) as years_of_service,
    CASE 
        WHEN e.driver_license_expire_date IS NOT NULL AND e.driver_license_expire_date < DATEADD(MONTH, 3, GETDATE()) 
        THEN 1 ELSE 0 
    END as license_expiring_soon,
    e.created_at
FROM employees e
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN positions p ON e.position_id = p.position_id
LEFT JOIN employees supervisor ON e.supervisor_id = supervisor.employee_id
WHERE e.status != 'resigned'
GO

-- สร้าง Stored Procedure สำหรับเพิ่มพนักงานใหม่
CREATE PROCEDURE AddNewEmployee
    @employee_code NVARCHAR(20),
    @first_name NVARCHAR(100),
    @last_name NVARCHAR(100),
    @phone NVARCHAR(20) = NULL,
    @email NVARCHAR(100) = NULL,
    @department_id INT,
    @position_id INT,
    @hire_date DATE,
    @base_salary DECIMAL(10,2) = NULL,
    @supervisor_id INT = NULL,
    @is_driver BIT = 0,
    @created_by INT = NULL
AS
BEGIN
    DECLARE @employee_exists INT = 0
    
    -- ตรวจสอบว่ารหัสพนักงานซ้ำหรือไม่
    SELECT @employee_exists = COUNT(*) 
    FROM employees 
    WHERE employee_code = @employee_code
    
    IF @employee_exists > 0
    BEGIN
        RAISERROR (N'รหัสพนักงานนี้มีอยู่ในระบบแล้ว', 16, 1)
        RETURN
    END
    
    INSERT INTO employees (
        employee_code, first_name, last_name, phone, email,
        department_id, position_id, hire_date, base_salary, 
        supervisor_id, is_driver, created_by
    ) VALUES (
        @employee_code, @first_name, @last_name, @phone, @email,
        @department_id, @position_id, @hire_date, @base_salary,
        @supervisor_id, @is_driver, @created_by
    )
    
    SELECT SCOPE_IDENTITY() as employee_id, N'เพิ่มพนักงานใหม่เรียบร้อยแล้ว' as message
END
GO

-- สร้าง Function สำหรับคำนวณอายุงาน
CREATE FUNCTION GetWorkExperience(@hire_date DATE) 
RETURNS NVARCHAR(50)
AS
BEGIN
    DECLARE @years INT = DATEDIFF(YEAR, @hire_date, GETDATE())
    DECLARE @months INT = DATEDIFF(MONTH, @hire_date, GETDATE()) % 12
    
    RETURN CAST(@years AS NVARCHAR(10)) + N' ปี ' + CAST(@months AS NVARCHAR(10)) + N' เดือน'
END
GO

-- สร้าง Trigger สำหรับอัปเดต updated_at อัตโนมัติ
CREATE TRIGGER tr_employees_update 
ON employees
AFTER UPDATE
AS
BEGIN
    UPDATE employees 
    SET updated_at = GETDATE()
    FROM employees e
    INNER JOIN inserted i ON e.employee_id = i.employee_id
END
GO

CREATE TRIGGER tr_departments_update 
ON departments
AFTER UPDATE
AS
BEGIN
    UPDATE departments 
    SET updated_at = GETDATE()
    FROM departments d
    INNER JOIN inserted i ON d.department_id = i.department_id
END
GO

CREATE TRIGGER tr_positions_update 
ON positions
AFTER UPDATE
AS
BEGIN
    UPDATE positions 
    SET updated_at = GETDATE()
    FROM positions p
    INNER JOIN inserted i ON p.position_id = i.position_id
END
GO

PRINT N'ฐานข้อมูล logistic_app_db สำหรับ SQL Server สร้างเรียบร้อยแล้ว!'
GO
