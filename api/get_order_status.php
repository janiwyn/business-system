<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['order_reference'])) {
    jsonResponse(false, 'Order reference is required', null, 400);
}

$orderReference = sanitizeInput($_GET['order_reference']);

$stmt = mysqli_prepare($conn, "SELECT id, order_reference, customer_name, customer_phone, 
    expected_amount, status, qr_code_expires_at, created_at 
    FROM remote_orders WHERE order_reference = ?");

mysqli_stmt_bind_param($stmt, "s", $orderReference);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $orderId = $row['id'];
    
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
    
    $row['items'] = $items;
    mysqli_stmt_close($itemsStmt);
    
    jsonResponse(true, 'Order found', $row);
} else {
    jsonResponse(false, 'Order not found', null, 404);
}

mysqli_stmt_close($stmt);
closeDBConnection($conn);
?>
