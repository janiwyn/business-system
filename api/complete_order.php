<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id'])) {
    jsonResponse(false, 'Order ID is required', null, 400);
}

$orderId = intval($input['order_id']);
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

mysqli_begin_transaction($conn);

try {
    // Update order status
    $stmt = mysqli_prepare($conn, "UPDATE remote_orders 
        SET status = 'finished', processed_by = ?, processed_at = NOW() 
        WHERE id = ? AND status = 'pending'");
    
    mysqli_stmt_bind_param($stmt, "ii", $userId, $orderId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update order');
    }
    
    if (mysqli_stmt_affected_rows($stmt) === 0) {
        throw new Exception('Order not found or already processed');
    }
    
    mysqli_stmt_close($stmt);
    
    // Log audit
    logAuditAction($conn, $orderId, 'order_completed', $username, $userId, 'pending', 'finished', 'Order completed via QR scanner');
    
    mysqli_commit($conn);
    
    jsonResponse(true, 'Order completed successfully');
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    jsonResponse(false, $e->getMessage(), null, 500);
}

closeDBConnection($conn);
?>
