<?php
// Database connection
$host = 'localhost';  // or '127.0.0.1'
$user = 'root';
$pass = '';          // default XAMPP password is empty
$db   = 'business-system';
$port = 3306;        // default MySQL port

// Try connection with error handling
try {
    $conn = mysqli_connect($host, $user, $pass, $db, $port);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Set charset
    mysqli_set_charset($conn, "utf8mb4");
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Database connection error: " . $e->getMessage());
    
    // User-friendly error message
    die("
    <div style='padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;margin:20px;'>
        <h3 style='color:#856404;'>⚠️ Database Connection Error</h3>
        <p style='color:#856404;'>Please ensure:</p>
        <ul style='color:#856404;'>
            <li>XAMPP MySQL service is running (check XAMPP Control Panel)</li>
            <li>Database 'business-system' exists</li>
            <li>Connection settings are correct</li>
        </ul>
        <p style='color:#856404;'><small>Error: " . htmlspecialchars($e->getMessage()) . "</small></p>
    </div>
    ");
}

// Add location columns to remote_orders table
$conn->query("ALTER TABLE remote_orders ADD COLUMN IF NOT EXISTS customer_location_lat DECIMAL(10, 8) NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE remote_orders ADD COLUMN customer_location_lat DECIMAL(10, 8) NULL"); }

$conn->query("ALTER TABLE remote_orders ADD COLUMN IF NOT EXISTS customer_location_lng DECIMAL(11, 8) NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE remote_orders ADD COLUMN customer_location_lng DECIMAL(11, 8) NULL"); }

$conn->query("ALTER TABLE remote_orders ADD COLUMN IF NOT EXISTS customer_address TEXT NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE remote_orders ADD COLUMN customer_address TEXT NULL"); }
?>