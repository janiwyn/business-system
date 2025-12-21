<?php
require_once __DIR__ . '/../config/config.php';

// Database Connection
function getDBConnection() {
    global $conn;
    return $conn;
}

function closeDBConnection($connection) {
    mysqli_close($connection);
}

// Sanitize Input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate Phone Number
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return (strlen($phone) >= 10 && strlen($phone) <= 15);
}

// Generate Order Reference
function generateOrderReference() {
    $year = date('Y');
    $timestamp = time();
    $random = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    return ORDER_PREFIX . '-' . $year . '-' . $random;
}

// Generate QR Code Data
function generateQRCodeData($orderId, $orderReference) {
    $data = json_encode([
        'order_id' => $orderId,
        'order_reference' => $orderReference,
        'timestamp' => time(),
        'type' => 'remote_order'
    ]);
    return base64_encode($data);
}

// Decode QR Code Data
function decodeQRCodeData($qrCode) {
    try {
        $decoded = base64_decode($qrCode);
        return json_decode($decoded, true);
    } catch (Exception $e) {
        return null;
    }
}

// Validate QR Code Data
function validateQRCodeData($qrCode) {
    $decoded = decodeQRCodeData($qrCode);
    
    if (!$decoded || !isset($decoded['order_id'], $decoded['type'])) {
        return false;
    }
    
    if ($decoded['type'] !== 'remote_order') {
        return false;
    }
    
    return $decoded;
}

// Check if Order is Expired
function isOrderExpired($expiresAt) {
    if (!$expiresAt) return false;
    return strtotime($expiresAt) < time();
}

// Log Audit Action
function logAuditAction($conn, $orderId, $action, $performedBy, $userId = null, $oldStatus = null, $newStatus = null, $notes = '') {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = mysqli_prepare($conn, "INSERT INTO remote_order_audit_logs 
        (order_id, action, performed_by, user_id, old_status, new_status, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt, "isissss", 
        $orderId, $action, $performedBy, $userId, $oldStatus, $newStatus, $notes);
    
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Check if user is authenticated (POS)
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Redirect if not authenticated
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// JSON Response
function jsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Format Currency
function formatCurrency($amount) {
    return 'UGX ' . number_format($amount, 0);
}

// Get Order Statistics
function getOrderStats($conn, $branchId, $date = null) {
    $dateFilter = $date ? "AND DATE(created_at) = '$date'" : "AND DATE(created_at) = CURDATE()";
    
    $pending = mysqli_query($conn, "SELECT COUNT(*) as count FROM remote_orders 
        WHERE branch_id = $branchId AND status = 'pending' $dateFilter")->fetch_assoc()['count'];
    
    $finished = mysqli_query($conn, "SELECT COUNT(*) as count FROM remote_orders 
        WHERE branch_id = $branchId AND status = 'finished' $dateFilter")->fetch_assoc()['count'];
    
    return ['pending' => $pending, 'finished' => $finished];
}
?>
