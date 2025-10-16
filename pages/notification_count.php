<?php
include '../includes/db.php';
include '../includes/auth.php';

$role = $_SESSION['role'];
$branch_id = $_SESSION['branch_id'] ?? null;

// Count notifications
$count = 0;

// Low stock products
$lowStockQuery = "SELECT COUNT(*) AS count FROM products WHERE stock < 10";
if ($role === 'staff') {
    $lowStockQuery .= " AND `branch-id` = $branch_id";
}
$count += $conn->query($lowStockQuery)->fetch_assoc()['count'];

// Expired products
$expiredQuery = "SELECT COUNT(*) AS count FROM products WHERE expiry_date < CURDATE()";
if ($role === 'staff') {
    $expiredQuery .= " AND `branch-id` = $branch_id";
}
$count += $conn->query($expiredQuery)->fetch_assoc()['count'];

// Expiring products
$expiringQuery = "SELECT COUNT(*) AS count FROM products WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
if ($role === 'staff') {
    $expiringQuery .= " AND `branch-id` = $branch_id";
}
$count += $conn->query($expiringQuery)->fetch_assoc()['count'];

echo json_encode(['count' => $count]);
?>
