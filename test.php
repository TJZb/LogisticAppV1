<?php
require_once 'service/connect.php';

// Ensure database connection
$conn = connect_db();

if (!$conn) {
    die("Database connection failed!");
}

echo "<h1>Debug Form Submission</h1>";

echo "<h2>Database Connection Status:</h2>";
if ($conn) {
    echo "<p style='color: green;'>✅ Database connected successfully</p>";
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
}

echo "<h2>POST Data Received:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>GET Data Received:</h2>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h2>Request Method:</h2>";
echo "<p>" . $_SERVER['REQUEST_METHOD'] . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Processing License Plate Check:</h2>";
    
    // Check if it's trailer form
    if (isset($_POST['trailer_form'])) {
        echo "<p>Form type: <strong>TRAILER FORM</strong></p>";
        $license_plate = trim($_POST['license_plate']);
        echo "<p>Original license plate: <strong>" . htmlspecialchars($license_plate) . "</strong></p>";
        
        // Add "พ่วง" prefix if not exists
        if (substr($license_plate, 0, 4) !== 'พ่วง') {
            $license_plate = 'พ่วง' . $license_plate;
        }
        echo "<p>Final license plate: <strong>" . htmlspecialchars($license_plate) . "</strong></p>";
    } else {
        echo "<p>Form type: <strong>MAIN VEHICLE FORM</strong></p>";
        $license_plate = trim($_POST['license_plate']);
        echo "<p>Original license plate: <strong>" . htmlspecialchars($license_plate) . "</strong></p>";
        
        $vehicle_type = $_POST['vehicle_type'] === 'อื่นๆ' ? trim($_POST['vehicle_type_other']) : trim($_POST['vehicle_type']);
        echo "<p>Vehicle type: <strong>" . htmlspecialchars($vehicle_type) . "</strong></p>";
        
        // Check if it's trailer
        $is_trailer = ($vehicle_type === 'รถพ่วง');
        if ($is_trailer && substr($license_plate, 0, 4) !== 'พ่วง') {
            $license_plate = 'พ่วง' . $license_plate;
        }
        echo "<p>Is trailer: <strong>" . ($is_trailer ? 'YES' : 'NO') . "</strong></p>";
        echo "<p>Final license plate: <strong>" . htmlspecialchars($license_plate) . "</strong></p>";
    }
    
    // Check for empty license plate
    if ($license_plate === '' || $license_plate === 'พ่วง') {
        echo "<p style='color: red;'>ERROR: Empty license plate!</p>";
    } else {
        // Check for duplicates
        try {
            $stmt_check = $conn->prepare("SELECT vehicle_id, license_plate FROM vehicles WHERE license_plate = ? AND is_deleted = 0");
            $stmt_check->execute([$license_plate]);
            $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            echo "<h3>Duplicate Check Result:</h3>";
            if ($existing) {
                echo "<p style='color: red;'>DUPLICATE FOUND!</p>";
                echo "<p>Existing Vehicle ID: " . $existing['vehicle_id'] . "</p>";
                echo "<p>Existing License: " . htmlspecialchars($existing['license_plate']) . "</p>";
            } else {
                echo "<p style='color: green;'>No duplicate found - this license can be used</p>";
            }
            
            // Show all vehicles with similar license
            $stmt_all = $conn->prepare("SELECT * FROM vehicles WHERE license_plate LIKE ? AND is_deleted = 0");
            $stmt_all->execute(["%$license_plate%"]);
            $similar = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Similar License Plates in Database:</h3>";
            if (empty($similar)) {
                echo "<p>No similar license plates found</p>";
            } else {
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>ID</th><th>License</th><th>Category</th><th>Status</th><th>Created</th></tr>";
                foreach ($similar as $vehicle) {
                    echo "<tr>";
                    echo "<td>" . $vehicle['vehicle_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($vehicle['license_plate']) . "</td>";
                    echo "<td>" . htmlspecialchars($vehicle['category_id'] ?? 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($vehicle['status']) . "</td>";
                    echo "<td>" . htmlspecialchars($vehicle['created_at'] ?? 'N/A') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<h2>All Vehicles in Database:</h2>";
try {
    $stmt = $conn->prepare("SELECT TOP 10 vehicle_id, license_plate, status, is_deleted, created_at FROM vehicles ORDER BY created_at DESC");
    $stmt->execute();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>License Plate</th><th>Status</th><th>Deleted</th><th>Created</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['vehicle_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['license_plate']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . ($row['is_deleted'] ? 'YES' : 'NO') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Show total count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM vehicles WHERE is_deleted = 0");
    $count_stmt->execute();
    $total = $count_stmt->fetchColumn();
    echo "<p>Total active vehicles: <strong>$total</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>

<form method="POST" action="debug_form.php">
    <h2>Test Form Submission:</h2>
    
    <h3>Test as Main Vehicle:</h3>
    <input type="text" name="license_plate" placeholder="Enter license plate" required>
    <select name="vehicle_type" required>
        <option value="">Select type</option>
        <option value="รถบรรทุก">รถบรรทุก</option>
        <option value="รถพ่วง">รถพ่วง</option>
        <option value="4 ล้อ">4 ล้อ</option>
    </select>
    <button type="submit">Test Main Vehicle</button>
    
    <hr>
    
    <h3>Test as Trailer:</h3>
    <input type="text" name="license_plate" placeholder="Enter license plate" required>
    <input type="hidden" name="trailer_form" value="1">
    <button type="submit">Test Trailer</button>
</form>
