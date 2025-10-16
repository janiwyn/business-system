<?php
session_start();
include '../includes/db.php';

$role = $_SESSION['role'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;
$count = 0;

$branch_condition = ($role === 'manager' || $role === 'staff') && $branch_id ? "AND `branch-id` = '$branch_id'" : "";

// ✅ Count low stock products
$lowStockQuery = $conn->query("
    SELECT COUNT(*) AS count
    FROM products
    WHERE stock < 10
    $branch_condition
");
if ($lowStockQuery) {
    $count += (int) $lowStockQuery->fetch_assoc()['count'];
}

// ✅ Count expired products
$expiredQuery = $conn->query("
    SELECT COUNT(*) AS count
    FROM products
    WHERE expiry_date IS NOT NULL 
      AND expiry_date <> '' 
      AND expiry_date < CURDATE()
    $branch_condition
");
if ($expiredQuery) {
    $count += (int) $expiredQuery->fetch_assoc()['count'];
}

echo json_encode(['count' => $count]);
?>
