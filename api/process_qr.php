    $order['items'] = $items;
    mysqli_stmt_close($itemsStmt);
    
    jsonResponse(true, 'QR code validated', $order);
} else {
    jsonResponse(false, 'Order not found', null, 404);
}

mysqli_stmt_close($stmt);
closeDBConnection($conn);
?>
