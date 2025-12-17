<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isAuthenticated()) {
    jsonResponse(false, 'Unauthorized', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['qr_code'])) {
    jsonResponse(false, 'QR code is required', null, 400);
}

$qrCode = $input['qr_code'];

// Validate QR code format
$qrData = validateQRCodeData($qrCode);

if (!$qrData) {
    jsonResponse(false, 'Invalid QR code format', null, 400);
}

$orderId = intval($qrData['order_id']);

try {
    $conn = getDBConnection();
    
    // Get order details
    $stmt = mysqli_prepare($conn, "SELECT ro.*, b.name as branch_name 
        FROM remote_orders ro
        JOIN branch b ON ro.branch_id = b.id
        WHERE ro.id = ?");
    
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        closeDBConnection($conn);
        jsonResponse(false, 'Order not found', null, 404);
    }
    
    mysqli_stmt_close($stmt);
    
    // Check if QR code is expired
    if (isOrderExpired($row['qr_code_expires_at'])) {
        closeDBConnection($conn);
        jsonResponse(false, 'QR code has expired', null, 400);
    }
    
    // Check if order is already processed
    if ($row['status'] !== 'pending') {
        closeDBConnection($conn);
        jsonResponse(false, 'Order already ' . $row['status'], null, 400);
    }
    
    // Get order items
    $itemsStmt = mysqli_prepare($conn, "SELECT product_name, quantity, unit_price, subtotal 
        FROM remote_order_items WHERE order_id = ?");
    mysqli_stmt_bind_param($itemsStmt, "i", $orderId);
    mysqli_stmt_execute($itemsStmt);
    $itemsResult = mysqli_stmt_get_result($itemsStmt);
    
    $items = [];
    while ($item = mysqli_fetch_assoc($itemsResult)) {
        $items[] = $item;
    }
    
    mysqli_stmt_close($itemsStmt);
    
    $row['items'] = $items;
    
    closeDBConnection($conn);
    jsonResponse(true, 'QR code is valid', $row);
    
} catch (Exception $e) {
    closeDBConnection($conn);
    jsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
