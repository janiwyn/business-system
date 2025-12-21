<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isAuthenticated()) {
    jsonResponse(false, 'Unauthorized', null, 401);
}

$branchId = $_SESSION['branch_id'] ?? null;

if (!$branchId) {
    jsonResponse(false, 'Branch not found', null, 400);
}

try {
    $conn = getDBConnection();
    
    // Get remote orders for this branch
    $stmt = mysqli_prepare($conn, "SELECT ro.*, b.name as branch_name 
        FROM remote_orders ro
        JOIN branch b ON ro.branch_id = b.id
        WHERE ro.branch_id = ?
        ORDER BY ro.created_at DESC
        LIMIT 100");
    
    mysqli_stmt_bind_param($stmt, "i", $branchId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Get order items
        $itemsStmt = mysqli_prepare($conn, "SELECT product_name, quantity, unit_price 
            FROM remote_order_items WHERE order_id = ?");
        mysqli_stmt_bind_param($itemsStmt, "i", $row['id']);
        mysqli_stmt_execute($itemsStmt);
        $itemsResult = mysqli_stmt_get_result($itemsStmt);
        
        $items = [];
        while ($item = mysqli_fetch_assoc($itemsResult)) {
            $items[] = $item;
        }
        
        $row['items'] = $items;
        $orders[] = $row;
        
        mysqli_stmt_close($itemsStmt);
    }
    
    mysqli_stmt_close($stmt);
    closeDBConnection($conn);
    
    jsonResponse(true, 'Orders fetched successfully', $orders);
    
} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
