<?php
require_once '../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    if (!isset($_GET['branch_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Branch ID is required',
            'data' => null
        ]);
        exit;
    }

    $branchId = intval($_GET['branch_id']);

    // Get SHELF products - removed 'image' column since it doesn't exist
    $stmt = mysqli_prepare($conn, "SELECT id, name, `selling-price` as price, stock, barcode 
        FROM products 
        WHERE `branch-id` = ? AND stock > 0 
        ORDER BY name ASC");

    mysqli_stmt_bind_param($stmt, "i", $branchId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'stock' => $row['stock'],
            'barcode' => $row['barcode'],
            'image' => null // No image column in your database
        ];
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Products fetched successfully',
        'data' => $products
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => null
    ]);
}
?>
