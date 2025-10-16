<?php
session_start();
include '../includes/db.php';

$role = $_SESSION['role'];
$branch_id = $_SESSION['branch_id'] ?? null;
$count = 0;

// Count low stock products
$lowStockQuery = $conn->query("
    SELECT COUNT(*) AS count
    FROM products
    WHERE stock < 10
    " . ($role === 'manager' || $role === 'staff' ? "AND `branch-id` = $branch_id" : "")
);
$count += $lowStockQuery->fetch_assoc()['count'];

// Count expired products
$expiredQuery = $conn->query("
    SELECT COUNT(*) AS count
    FROM products
    WHERE expiry_date < CURDATE()
    " . ($role === 'manager' || $role === 'staff' ? "AND `branch-id` = $branch_id" : "")
);
$count += $expiredQuery->fetch_assoc()['count'];

echo json_encode(['count' => $count]);
?>