<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

// Include the correct sidebar based on role
if ($_SESSION['role'] === 'staff') {
    include '../pages/sidebar_staff.php';
} else {
    include '../pages/sidebar.php';
}

include '../includes/header.php';

$role = $_SESSION['role'];
$branch_id = $_SESSION['branch_id'] ?? null;
$notifications = [];

// Ensure branch_id is valid for staff/manager
$branch_condition = ($role === 'manager' || $role === 'staff') && $branch_id ? "AND p.`branch-id` = '$branch_id'" : "";

// ✅ Low stock products
$lowStockQuery = $conn->query("
    SELECT p.name, p.stock, b.name AS branch_name
    FROM products p
    JOIN branch b ON p.`branch-id` = b.id
    WHERE p.stock < 10
    $branch_condition
    ORDER BY p.stock ASC
");
while ($row = $lowStockQuery->fetch_assoc()) {
    $msg = "Product '{$row['name']}' is low in stock ({$row['stock']} left)";
    if ($role !== 'staff') $msg .= " in branch '{$row['branch_name']}'";
    $notifications[] = [
        'type' => 'Low Stock',
        'message' => $msg . '.'
    ];
}

// ✅ Expired products
$expiredQuery = $conn->query("
    SELECT p.name, p.expiry_date, b.name AS branch_name
    FROM products p
    JOIN branch b ON p.`branch-id` = b.id
    WHERE p.expiry_date IS NOT NULL 
      AND p.expiry_date <> '' 
      AND p.expiry_date < CURDATE()
    $branch_condition
    ORDER BY p.expiry_date ASC
");
while ($row = $expiredQuery->fetch_assoc()) {
    $msg = "Product '{$row['name']}' expired on {$row['expiry_date']}";
    if ($role !== 'staff') $msg .= " in branch '{$row['branch_name']}'";
    $notifications[] = [
        'type' => 'Expired Product',
        'message' => $msg . '.'
    ];
}
?>

<div class="container mt-5">
    <h2 class="mb-4" style="color:var(--primary-color);font-weight:700;">Notifications</h2>

    <?php if (count($notifications) > 0): ?>
        <div class="list-group shadow-sm rounded-3">
            <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($notification['type']) ?>:</strong>
                        <?= htmlspecialchars($notification['message']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">No notifications available.</div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
