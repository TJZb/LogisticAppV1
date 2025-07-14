-- สร้างตารางผู้ใช้และข้อมูลตัวอย่าง
-- สำหรับระบบ Logistic App

USE [logistic_app_db]
GO

-- =============================================
-- สร้างตาราง Departments (แผนก)
-- =============================================
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

-- เพิ่มข้อมูลแผนกเริ่มต้น
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

-- =============================================
-- สร้างตาราง Employees (พนักงาน)
-- =============================================
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

-- =============================================
-- สร้างตาราง Users (ผู้ใช้ระบบ)
-- =============================================
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

-- =============================================
-- สร้างตาราง FuelRecords (บันทึกการเติมน้ำมัน)
-- =============================================
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

-- =============================================
-- สร้างตาราง FuelReceiptAttachments (ไฟล์แนบใบเสร็จ)
-- =============================================
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
-- เพิ่มข้อมูลพนักงานตัวอย่าง
-- =============================================
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

-- =============================================
-- เพิ่มข้อมูลผู้ใช้ระบบ (Users)
-- =============================================
IF NOT EXISTS (SELECT * FROM users WHERE username = 'admin')
BEGIN
    -- รหัสผ่านง่าย ๆ สำหรับการทดสอบ: 123456
    DECLARE @defaultHash NVARCHAR(255) = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm'
    
    INSERT INTO users (username, password_hash, role, employee_id, active) VALUES
    ('admin', @defaultHash, 'admin', 1, 1),
    ('manager', @defaultHash, 'manager', 2, 1),
    ('employee1', @defaultHash, 'employee', 3, 1),
    ('employee2', @defaultHash, 'employee', 4, 1),
    ('mechanic', @defaultHash, 'employee', 5, 1)
END
GO

-- =============================================
-- เพิ่มข้อมูลรถตัวอย่าง (ถ้ายังไม่มี)
-- =============================================
IF NOT EXISTS (SELECT * FROM vehicles WHERE license_plate = N'กข-1234')
BEGIN
    -- หาค่า brand_id, fuel_type_id, category_id
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

-- =============================================
-- เพิ่มข้อมูลการเติมน้ำมันตัวอย่าง
-- =============================================
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
-- สร้าง Index สำหรับเพิ่มประสิทธิภาพ
-- =============================================
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_users_username')
    CREATE INDEX IX_users_username ON users(username)
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_users_employee_id')
    CREATE INDEX IX_users_employee_id ON users(employee_id)
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fuel_records_vehicle_id')
    CREATE INDEX IX_fuel_records_vehicle_id ON fuel_records(vehicle_id)
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fuel_records_fuel_date')
    CREATE INDEX IX_fuel_records_fuel_date ON fuel_records(fuel_date)
GO

-- =============================================
-- สร้าง View สำหรับแสดงข้อมูลผู้ใช้พร้อมรายละเอียด
-- =============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'UserDetailsView')
    DROP VIEW UserDetailsView
GO

CREATE VIEW UserDetailsView AS
SELECT 
    u.user_id,
    u.username,
    u.role,
    u.last_login,
    u.active,
    e.employee_code,
    e.first_name,
    e.last_name,
    e.email,
    e.phone_number,
    e.job_title,
    d.department_name,
    d.department_code
FROM users u
LEFT JOIN employees e ON u.employee_id = e.employee_id
LEFT JOIN departments d ON e.department_id = d.department_id
WHERE u.active = 1
GO

PRINT N'สร้างตารางและข้อมูลเริ่มต้นเรียบร้อยแล้ว'
PRINT N'ผู้ใช้ Admin:'
PRINT N'Username: admin'
PRINT N'Password: admin123'
PRINT N''
PRINT N'ผู้ใช้ Manager:'
PRINT N'Username: manager' 
PRINT N'Password: manager123'
PRINT N''
PRINT N'ผู้ใช้ Employee:'
PRINT N'Username: employee1, employee2'
PRINT N'Password: employee123'
