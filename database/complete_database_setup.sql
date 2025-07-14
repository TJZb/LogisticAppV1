-- =============================================
-- ระบบฐานข้อมูลจัดการขนส่ง (Logistic App)
-- SQL Server Complete Database Setup
-- =============================================

-- สร้างฐานข้อมูล
IF NOT EXISTS (SELECT name FROM master.dbo.sysdatabases WHERE name = N'logistic_app_db')
BEGIN
    CREATE DATABASE [logistic_app_db]
    COLLATE Thai_CI_AS
END
GO

USE [logistic_app_db]
GO

-- =============================================
-- ส่วนที่ 1: สร้างตารางข้อมูลพื้นฐาน
-- =============================================

-- ตารางประเภทเชื้อเพลิง
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='fuel_types' AND xtype='U')
BEGIN
    CREATE TABLE fuel_types (
        fuel_type_id INT IDENTITY(1,1) PRIMARY KEY,
        fuel_name NVARCHAR(50) NOT NULL,
        fuel_code NVARCHAR(10) NOT NULL UNIQUE,
        description NVARCHAR(MAX) NULL,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ตารางยี่ห้อรถ
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vehicle_brands' AND xtype='U')
BEGIN
    CREATE TABLE vehicle_brands (
        brand_id INT IDENTITY(1,1) PRIMARY KEY,
        brand_name NVARCHAR(100) NOT NULL,
        brand_code NVARCHAR(20) NOT NULL UNIQUE,
        country_origin NVARCHAR(50) NULL,
        description NVARCHAR(MAX) NULL,
        is_active BIT DEFAULT 1,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ตารางประเภทรถ
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vehicle_categories' AND xtype='U')
BEGIN
    CREATE TABLE vehicle_categories (
        category_id INT IDENTITY(1,1) PRIMARY KEY,
        category_name NVARCHAR(100) NOT NULL,
        category_code NVARCHAR(20) NOT NULL UNIQUE,
        description NVARCHAR(MAX) NULL,
        is_active BIT DEFAULT 1,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ตารางแผนกงาน
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='departments' AND xtype='U')
BEGIN
    CREATE TABLE departments (
        department_id INT IDENTITY(1,1) PRIMARY KEY,
        department_name NVARCHAR(100) NOT NULL,
        department_code NVARCHAR(20) NOT NULL UNIQUE,
        description NVARCHAR(MAX) NULL,
        is_active BIT DEFAULT 1,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE()
    )
END
GO

-- ตารางพนักงาน
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='employees' AND xtype='U')
BEGIN
    CREATE TABLE employees (
        employee_id INT IDENTITY(1,1) PRIMARY KEY,
        employee_code NVARCHAR(20) NOT NULL UNIQUE,
        first_name NVARCHAR(100) NOT NULL,
        last_name NVARCHAR(100) NOT NULL,
        email NVARCHAR(255) UNIQUE NULL,
        phone_number NVARCHAR(20) NULL,
        department_id INT NULL,
        job_title NVARCHAR(100) NULL,
        hire_date DATE DEFAULT GETDATE(),
        driver_license_number NVARCHAR(50) NULL,
        license_expiry_date DATE NULL,
        salary DECIMAL(10,2) NULL,
        is_active BIT DEFAULT 1,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE(),
        FOREIGN KEY (department_id) REFERENCES departments(department_id)
    )
END
GO

-- ตารางผู้ใช้ระบบ
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='users' AND xtype='U')
BEGIN
    CREATE TABLE users (
        user_id INT IDENTITY(1,1) PRIMARY KEY,
        username NVARCHAR(50) NOT NULL UNIQUE,
        password_hash NVARCHAR(255) NOT NULL,
        role NVARCHAR(20) NOT NULL CHECK (role IN ('admin', 'manager', 'employee')),
        employee_id INT NULL,
        last_login DATETIME2 NULL,
        login_attempts INT DEFAULT 0,
        is_locked BIT DEFAULT 0,
        active BIT DEFAULT 1,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE(),
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
    )
END
GO

-- ตารางรถและยานพาหนะ
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vehicles' AND xtype='U')
BEGIN
    CREATE TABLE vehicles (
        vehicle_id INT IDENTITY(1,1) PRIMARY KEY,
        license_plate NVARCHAR(20) NOT NULL UNIQUE,
        province NVARCHAR(100) NULL,
        registration_date DATE NULL,
        brand_id INT NULL,
        model_name NVARCHAR(100) NULL,
        year_manufactured INT NULL,
        color NVARCHAR(50) NULL,
        chassis_number NVARCHAR(100) UNIQUE NULL,
        fuel_type_id INT NULL,
        gas_tank_number NVARCHAR(50) NULL,
        payload_weight DECIMAL(8,2) NULL,
        gross_weight DECIMAL(8,2) NULL,
        seating_capacity INT NULL,
        category_id INT NULL,
        status NVARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'maintenance', 'in_use', 'out_of_service', 'sold')),
        vehicle_description NVARCHAR(MAX) NULL,
        is_deleted BIT DEFAULT 0,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE(),
        FOREIGN KEY (brand_id) REFERENCES vehicle_brands(brand_id),
        FOREIGN KEY (fuel_type_id) REFERENCES fuel_types(fuel_type_id),
        FOREIGN KEY (category_id) REFERENCES vehicle_categories(category_id)
    )
END
GO

-- ตารางบันทึกการเติมน้ำมัน
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='fuel_records' AND xtype='U')
BEGIN
    CREATE TABLE fuel_records (
        fuel_record_id INT IDENTITY(1,1) PRIMARY KEY,
        vehicle_id INT NOT NULL,
        trailer_vehicle_id INT NULL,
        fuel_date DATETIME2 DEFAULT GETDATE(),
        fuel_type NVARCHAR(50) NULL,
        volume_liters DECIMAL(8,2) NULL,
        price_per_liter DECIMAL(8,2) NULL,
        total_cost DECIMAL(10,2) NULL,
        odometer_reading INT NULL,
        gas_station_name NVARCHAR(200) NULL,
        receipt_number NVARCHAR(100) NULL,
        recorded_by_employee_id INT NOT NULL,
        status NVARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
        notes NVARCHAR(MAX) NULL,
        approved_by_employee_id INT NULL,
        approved_at DATETIME2 NULL,
        created_at DATETIME2 DEFAULT GETDATE(),
        updated_at DATETIME2 DEFAULT GETDATE(),
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
        FOREIGN KEY (trailer_vehicle_id) REFERENCES vehicles(vehicle_id),
        FOREIGN KEY (recorded_by_employee_id) REFERENCES employees(employee_id),
        FOREIGN KEY (approved_by_employee_id) REFERENCES employees(employee_id)
    )
END
GO

-- ตารางไฟล์แนบใบเสร็จ
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='fuel_receipt_attachments' AND xtype='U')
BEGIN
    CREATE TABLE fuel_receipt_attachments (
        attachment_id INT IDENTITY(1,1) PRIMARY KEY,
        fuel_record_id INT NOT NULL,
        attachment_type NVARCHAR(50) NOT NULL CHECK (attachment_type IN ('gauge_before', 'gauge_after', 'receipt')),
        file_path NVARCHAR(500) NOT NULL,
        file_name NVARCHAR(255) NULL,
        file_size BIGINT NULL,
        mime_type NVARCHAR(100) NULL,
        uploaded_at DATETIME2 DEFAULT GETDATE(),
        uploaded_by_employee_id INT NOT NULL,
        FOREIGN KEY (fuel_record_id) REFERENCES fuel_records(fuel_record_id),
        FOREIGN KEY (uploaded_by_employee_id) REFERENCES employees(employee_id)
    )
END
GO

-- =============================================
-- ส่วนที่ 2: เพิ่มข้อมูลพื้นฐาน
-- =============================================

-- ข้อมูลประเภทเชื้อเพลิง
IF NOT EXISTS (SELECT * FROM fuel_types WHERE fuel_code = 'G95')
BEGIN
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
END
GO

-- ข้อมูลยี่ห้อรถ
IF NOT EXISTS (SELECT * FROM vehicle_brands WHERE brand_code = 'TOY')
BEGIN
    INSERT INTO vehicle_brands (brand_name, brand_code, country_origin, description) VALUES
    (N'โตโยต้า', 'TOY', N'ญี่ปุ่น', N'Toyota Motor Corporation'),
    (N'อีซูซุ', 'ISU', N'ญี่ปุ่น', N'Isuzu Motors Limited'),
    (N'ฮอนด้า', 'HON', N'ญี่ปุ่น', N'Honda Motor Co., Ltd.'),
    (N'มิตซูบิชิ', 'MIT', N'ญี่ปุ่น', N'Mitsubishi Motors Corporation'),
    (N'นิสสัน', 'NIS', N'ญี่ปุ่น', N'Nissan Motor Company'),
    (N'ฟอร์ด', 'FOR', N'สหรัฐอเมริกา', N'Ford Motor Company'),
    (N'เชฟโรเลต', 'CHE', N'สหรัฐอเมริกา', N'Chevrolet Division'),
    (N'ฮีโน่', 'HIN', N'ญี่ปุ่น', N'Hino Motors, Ltd.'),
    (N'ทาทา', 'TAT', N'อินเดีย', N'Tata Motors Limited'),
    (N'เมอร์เซเดส-เบนซ์', 'MER', N'เยอรมนี', N'Mercedes-Benz Group AG')
END
GO

-- ข้อมูลประเภทรถ
IF NOT EXISTS (SELECT * FROM vehicle_categories WHERE category_code = 'CAR')
BEGIN
    INSERT INTO vehicle_categories (category_name, category_code, description) VALUES
    (N'รถยนต์นั่งส่วนบุคคล', 'CAR', N'รถยนต์สำหรับการเดินทางส่วนบุคคล'),
    (N'รถกระบะ', 'PICKUP', N'รถกระบะขนาดเล็กถึงกลาง'),
    (N'รถบรรทุก 4 ล้อ', 'TRK4', N'รถบรรทุกขนาดเล็ก 4 ล้อ'),
    (N'รถบรรทุก 6 ล้อ', 'TRK6', N'รถบรรทุกขนาดกลาง 6 ล้อ'),
    (N'รถบรรทุก 10 ล้อ', 'TRK10', N'รถบรรทุกขนาดใหญ่ 10 ล้อ'),
    (N'รถหัวลาก', 'TRACTOR', N'รถหัวลากสำหรับลากพ่วง'),
    (N'รถพ่วง', 'TRAILER', N'รถพ่วงสำหรับขนส่งสินค้า'),
    (N'รถโดยสาร', 'BUS', N'รถโดยสารขนส่งผู้โดยสาร'),
    (N'รถตู้', 'VAN', N'รถตู้สำหรับขนส่งสินค้าหรือผู้โดยสาร'),
    (N'รถจักรยานยนต์', 'MOTOR', N'รถจักรยานยนต์ทุกประเภท')
END
GO

-- ข้อมูลแผนกงาน
IF NOT EXISTS (SELECT * FROM departments WHERE department_code = 'ADMIN')
BEGIN
    INSERT INTO departments (department_name, department_code, description) VALUES
    (N'ฝ่ายบริหาร', 'ADMIN', N'ฝ่ายบริหารจัดการทั่วไป'),
    (N'ฝ่ายขนส่ง', 'TRANSPORT', N'ฝ่ายขนส่งและโลจิสติกส์'),
    (N'ฝ่ายซ่อมบำรุง', 'MAINTENANCE', N'ฝ่ายซ่อมบำรุงยานพาหนะ'),
    (N'ฝ่ายการเงิน', 'FINANCE', N'ฝ่ายการเงินและบัญชี'),
    (N'ฝ่ายทรัพยากรบุคคล', 'HR', N'ฝ่ายทรัพยากรบุคคล')
END
GO

-- ข้อมูลพนักงานตัวอย่าง
IF NOT EXISTS (SELECT * FROM employees WHERE employee_code = 'ADMIN001')
BEGIN
    INSERT INTO employees (employee_code, first_name, last_name, email, phone_number, department_id, job_title, hire_date) VALUES
    ('ADMIN001', N'ผู้ดูแลระบบ', N'หลัก', 'admin@company.com', '02-xxx-xxxx', 1, N'ผู้ดูแลระบบ', GETDATE()),
    ('MGR001', N'จอห์น', N'สมิธ', 'manager@company.com', '02-xxx-xxxx', 2, N'ผู้จัดการฝ่ายขนส่ง', GETDATE()),
    ('EMP001', N'สมชาย', N'ใจดี', 'employee1@company.com', '08-xxx-xxxx', 2, N'พนักงานขับรถ', GETDATE()),
    ('EMP002', N'สมศรี', N'รักงาน', 'employee2@company.com', '08-xxx-xxxx', 2, N'พนักงานขับรถ', GETDATE()),
    ('MECH001', N'ช่างเบิ้ม', N'ซ่อมเก่ง', 'mechanic@company.com', '08-xxx-xxxx', 3, N'ช่างซ่อมรถ', GETDATE())
END
GO

-- ข้อมูลผู้ใช้ระบบ (รหัสผ่าน: 123456)
IF NOT EXISTS (SELECT * FROM users WHERE username = 'admin')
BEGIN
    DECLARE @defaultHash NVARCHAR(255) = '$2y$10$kSjPI4gaJ9dgE0pFZLoQfO3d81rRjiDXDeA2jGeheiVHVBsSvWdei'
    
    INSERT INTO users (username, password_hash, role, employee_id, active) VALUES
    ('admin', @defaultHash, 'admin', 1, 1),
    ('manager', @defaultHash, 'manager', 2, 1),
    ('employee1', @defaultHash, 'employee', 3, 1),
    ('employee2', @defaultHash, 'employee', 4, 1),
    ('mechanic', @defaultHash, 'employee', 5, 1)
END
GO

-- ข้อมูลรถตัวอย่าง
IF NOT EXISTS (SELECT * FROM vehicles WHERE license_plate = N'กข-1234')
BEGIN
    DECLARE @isuzuBrandId INT = (SELECT brand_id FROM vehicle_brands WHERE brand_code = 'ISU')
    DECLARE @dieselFuelId INT = (SELECT fuel_type_id FROM fuel_types WHERE fuel_code = 'DSL')
    DECLARE @truckCategoryId INT = (SELECT category_id FROM vehicle_categories WHERE category_code = 'TRK6')
    
    IF @isuzuBrandId IS NOT NULL AND @dieselFuelId IS NOT NULL AND @truckCategoryId IS NOT NULL
    BEGIN
        INSERT INTO vehicles (
            license_plate, province, registration_date, brand_id, model_name, 
            year_manufactured, color, chassis_number, fuel_type_id, 
            payload_weight, gross_weight, seating_capacity, category_id, status
        ) VALUES
        (N'กข-1234', N'กรุงเทพมหานคร', '2020-01-15', @isuzuBrandId, N'NMR 150', 2020, N'ขาว', 'ISU123456789', @dieselFuelId, 3000.00, 7500.00, 2, @truckCategoryId, 'active'),
        (N'กข-5678', N'กรุงเทพมหานคร', '2019-03-20', @isuzuBrandId, N'NPR 150', 2019, N'น้ำเงิน', 'ISU987654321', @dieselFuelId, 5000.00, 12000.00, 2, @truckCategoryId, 'active'),
        (N'กข-9999', N'กรุงเทพมหานคร', '2021-06-10', @isuzuBrandId, N'FVR 34', 2021, N'เงิน', 'ISU456789123', @dieselFuelId, 8000.00, 18000.00, 2, @truckCategoryId, 'active')
    END
END
GO

-- ข้อมูลการเติมน้ำมันตัวอย่าง
IF NOT EXISTS (SELECT * FROM fuel_records WHERE receipt_number = 'R001-2025-001')
BEGIN
    DECLARE @vehicleId1 INT = (SELECT vehicle_id FROM vehicles WHERE license_plate = N'กข-1234')
    DECLARE @employeeId1 INT = (SELECT employee_id FROM employees WHERE employee_code = 'EMP001')
    
    IF @vehicleId1 IS NOT NULL AND @employeeId1 IS NOT NULL
    BEGIN
        INSERT INTO fuel_records (
            vehicle_id, fuel_date, fuel_type, volume_liters, price_per_liter, 
            total_cost, odometer_reading, gas_station_name, receipt_number, 
            recorded_by_employee_id, status
        ) VALUES
        (@vehicleId1, DATEADD(day, -5, GETDATE()), N'ดีเซล', 80.50, 32.50, 2616.25, 125000, N'ปตท. สาขาลาดพร้าว', 'R001-2025-001', @employeeId1, 'approved'),
        (@vehicleId1, DATEADD(day, -2, GETDATE()), N'ดีเซล', 75.00, 33.00, 2475.00, 125450, N'บางจาก สาขาวิภาวดี', 'R001-2025-002', @employeeId1, 'pending')
    END
END
GO

-- =============================================
-- ส่วนที่ 3: สร้าง Index เพื่อเพิ่มประสิทธิภาพ
-- =============================================

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_users_username')
    CREATE INDEX IX_users_username ON users(username)
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_users_employee_id')
    CREATE INDEX IX_users_employee_id ON users(employee_id)
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_vehicles_license_plate')
    CREATE INDEX IX_vehicles_license_plate ON vehicles(license_plate)
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fuel_records_vehicle_id')
    CREATE INDEX IX_fuel_records_vehicle_id ON fuel_records(vehicle_id)
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fuel_records_fuel_date')
    CREATE INDEX IX_fuel_records_fuel_date ON fuel_records(fuel_date)
GO

-- =============================================
-- ส่วนที่ 4: สร้าง Views สำหรับ Reports
-- =============================================

-- View: รายงานรถทั้งหมดพร้อมรายละเอียด
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

-- View: รายงานการเติมน้ำมัน
IF EXISTS (SELECT * FROM sys.views WHERE name = 'FuelRecordsView')
    DROP VIEW FuelRecordsView
GO

CREATE VIEW FuelRecordsView AS
SELECT 
    fr.fuel_record_id,
    v.license_plate,
    vb.brand_name,
    v.model_name,
    fr.fuel_date,
    fr.fuel_type,
    fr.volume_liters,
    fr.price_per_liter,
    fr.total_cost,
    fr.odometer_reading,
    fr.gas_station_name,
    fr.receipt_number,
    e1.first_name + ' ' + e1.last_name AS recorded_by,
    fr.status,
    CASE fr.status
        WHEN 'pending' THEN N'รอการอนุมัติ'
        WHEN 'approved' THEN N'อนุมัติแล้ว'
        WHEN 'rejected' THEN N'ไม่อนุมัติ'
        ELSE fr.status
    END AS status_thai,
    e2.first_name + ' ' + e2.last_name AS approved_by,
    fr.approved_at,
    fr.notes,
    fr.created_at
FROM fuel_records fr
INNER JOIN vehicles v ON fr.vehicle_id = v.vehicle_id
LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
INNER JOIN employees e1 ON fr.recorded_by_employee_id = e1.employee_id
LEFT JOIN employees e2 ON fr.approved_by_employee_id = e2.employee_id
GO

-- View: รายงานข้อมูลผู้ใช้ระบบ
IF EXISTS (SELECT * FROM sys.views WHERE name = 'UserDetailsView')
    DROP VIEW UserDetailsView
GO

CREATE VIEW UserDetailsView AS
SELECT 
    u.user_id,
    u.username,
    u.role,
    CASE u.role
        WHEN 'admin' THEN N'ผู้ดูแลระบบ'
        WHEN 'manager' THEN N'ผู้จัดการ'
        WHEN 'employee' THEN N'พนักงาน'
        ELSE u.role
    END AS role_thai,
    u.last_login,
    u.active,
    e.employee_code,
    e.first_name,
    e.last_name,
    e.email,
    e.phone_number,
    e.job_title,
    d.department_name,
    d.department_code,
    u.created_at
FROM users u
LEFT JOIN employees e ON u.employee_id = e.employee_id
LEFT JOIN departments d ON e.department_id = d.department_id
WHERE u.active = 1
GO

-- View: สรุปสถิติการเติมน้ำมันรายเดือน
IF EXISTS (SELECT * FROM sys.views WHERE name = 'MonthlyFuelSummary')
    DROP VIEW MonthlyFuelSummary
GO

CREATE VIEW MonthlyFuelSummary AS
SELECT 
    YEAR(fr.fuel_date) AS fuel_year,
    MONTH(fr.fuel_date) AS fuel_month,
    DATENAME(MONTH, fr.fuel_date) AS month_name,
    COUNT(*) AS total_records,
    SUM(fr.volume_liters) AS total_volume,
    SUM(fr.total_cost) AS total_cost,
    AVG(fr.price_per_liter) AS avg_price_per_liter,
    COUNT(DISTINCT fr.vehicle_id) AS vehicles_count
FROM fuel_records fr
WHERE fr.status = 'approved'
GROUP BY YEAR(fr.fuel_date), MONTH(fr.fuel_date), DATENAME(MONTH, fr.fuel_date)
GO

-- View: สรุปสถิติการใช้รถรายคัน
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VehicleUsageSummary')
    DROP VIEW VehicleUsageSummary
GO

CREATE VIEW VehicleUsageSummary AS
SELECT 
    v.vehicle_id,
    v.license_plate,
    vb.brand_name,
    v.model_name,
    COUNT(fr.fuel_record_id) AS total_fuel_records,
    SUM(fr.volume_liters) AS total_fuel_consumed,
    SUM(fr.total_cost) AS total_fuel_cost,
    MAX(fr.odometer_reading) - MIN(fr.odometer_reading) AS total_distance,
    CASE 
        WHEN MAX(fr.odometer_reading) - MIN(fr.odometer_reading) > 0 
        THEN SUM(fr.volume_liters) / ((MAX(fr.odometer_reading) - MIN(fr.odometer_reading)) / 100.0)
        ELSE 0
    END AS fuel_consumption_per_100km,
    MIN(fr.fuel_date) AS first_fuel_date,
    MAX(fr.fuel_date) AS last_fuel_date
FROM vehicles v
LEFT JOIN fuel_records fr ON v.vehicle_id = fr.vehicle_id AND fr.status = 'approved'
LEFT JOIN vehicle_brands vb ON v.brand_id = vb.brand_id
WHERE v.is_deleted = 0
GROUP BY v.vehicle_id, v.license_plate, vb.brand_name, v.model_name
GO

-- =============================================
-- ส่วนที่ 5: ข้อความแจ้งเตือนเมื่อติดตั้งเสร็จ
-- =============================================

PRINT N'=============================================';
PRINT N'ติดตั้งฐานข้อมูลระบบขนส่งเรียบร้อยแล้ว!';
PRINT N'=============================================';
PRINT N'';
PRINT N'ข้อมูลการเข้าสู่ระบบ:';
PRINT N'- Username: admin | Password: 123456 (ผู้ดูแลระบบ)';
PRINT N'- Username: manager | Password: 123456 (ผู้จัดการ)';
PRINT N'- Username: employee1 | Password: 123456 (พนักงาน)';
PRINT N'- Username: employee2 | Password: 123456 (พนักงาน)';
PRINT N'- Username: mechanic | Password: 123456 (ช่างซ่อม)';
PRINT N'';
PRINT N'ตารางที่สร้าง:';
PRINT N'- fuel_types (ประเภทเชื้อเพลิง)';
PRINT N'- vehicle_brands (ยี่ห้อรถ)';
PRINT N'- vehicle_categories (ประเภทรถ)';
PRINT N'- departments (แผนกงาน)';
PRINT N'- employees (พนักงาน)';
PRINT N'- users (ผู้ใช้ระบบ)';
PRINT N'- vehicles (รถและยานพาหนะ)';
PRINT N'- fuel_records (บันทึกการเติมน้ำมัน)';
PRINT N'- fuel_receipt_attachments (ไฟล์แนบใบเสร็จ)';
PRINT N'';
PRINT N'Views สำหรับรายงาน:';
PRINT N'- VehicleDetailsView (รายละเอียดรถ)';
PRINT N'- FuelRecordsView (บันทึกการเติมน้ำมัน)';
PRINT N'- UserDetailsView (ข้อมูลผู้ใช้)';
PRINT N'- MonthlyFuelSummary (สรุปรายเดือน)';
PRINT N'- VehicleUsageSummary (สรุปการใช้รถ)';
PRINT N'';
PRINT N'พร้อมใช้งานแล้ว!';
