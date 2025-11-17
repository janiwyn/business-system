<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user_branch = $_SESSION['branch_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'staff';

$count = 0;
$today = date('Y-m-d');

// Count overdue shop debtors
$where_shop = ($user_role === 'staff' && $user_branch) 
    ? "WHERE branch_id = $user_branch AND due_date IS NOT NULL AND due_date <= '$today' AND is_paid = 0"
    : "WHERE due_date IS NOT NULL AND due_date <= '$today' AND is_paid = 0";
$res = $conn->query("SELECT COUNT(*) as cnt FROM debtors $where_shop");
if ($res) {
    $count += intval($res->fetch_assoc()['cnt'] ?? 0);
}

// Count overdue customer debtors
$where_cust = "WHERE status = 'debtor' AND due_date IS NOT NULL AND due_date <= '$today'";
$res = $conn->query("SELECT COUNT(*) as cnt FROM customer_transactions $where_cust");
if ($res) {
    $count += intval($res->fetch_assoc()['cnt'] ?? 0);
}

// NEW: Count low stock products (stock < 10)
$where_stock = ($user_role === 'staff' && $user_branch) 
    ? "WHERE `branch-id` = $user_branch AND stock < 10"
    : "WHERE stock < 10";
$res = $conn->query("SELECT COUNT(*) as cnt FROM products $where_stock");
if ($res) {
    $count += intval($res->fetch_assoc()['cnt'] ?? 0);
}

echo json_encode(['count' => $count]);
exit;
?>
