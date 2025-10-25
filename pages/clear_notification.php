<?php
session_start();
include '../includes/db.php';

$type = $_POST['type'] ?? '';
$product = $_POST['product'] ?? '';

if ($type && $product) {
    if (strpos($type, 'Low Stock') !== false && $product) {
        // Mark product as ignored for low stock notifications (add a column if needed)
        // Example: update products set stock = 999 where name = $product
        $stmt = $conn->prepare("UPDATE products SET stock = 999 WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $product);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }
    // For other notification types, implement as needed
    echo json_encode(['success' => true]);
    exit;
}
echo json_encode(['success' => false]);
?>
