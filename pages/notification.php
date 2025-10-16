<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

// Include the correct sidebar based on the user's role
if ($_SESSION['role'] === 'staff') {
    include '../pages/sidebar_staff.php';
} else {
    include '../pages/sidebar.php';
}

include '../includes/header.php';

$role = $_SESSION['role'];
$branch_id = $_SESSION['branch_id'] ?? null;
$notifications = [];

// Fetch notifications based on role
if ($role === 'staff' || $role === 'manager' || $role === 'admin') {
    // Low stock products
    $lowStockQuery = $conn->query("
        SELECT p.name, p.stock, b.name AS branch_name
        FROM products p
        JOIN branch b ON p.`branch-id` = b.id
        WHERE p.stock < 10
        " . ($role === 'manager' || $role === 'staff' ? "AND p.`branch-id` = $branch_id" : "") . "
        ORDER BY p.stock ASC
    ");
    while ($row = $lowStockQuery->fetch_assoc()) {
        $notifications[] = [
            'type' => 'Low Stock',
            'message' => "Product '{$row['name']}' is low in stock ({$row['stock']} left)" . ($role !== 'staff' ? " in branch '{$row['branch_name']}'" : "") . "."
        ];
    }

    // Expired products
    $expiredQuery = $conn->query("
        SELECT p.name, p.expiry_date, b.name AS branch_name
        FROM products p
        JOIN branch b ON p.`branch-id` = b.id
        WHERE p.expiry_date IS NOT NULL AND p.expiry_date < CURDATE()
        " . ($role === 'manager' || $role === 'staff' ? "AND p.`branch-id` = $branch_id" : "") . "
        ORDER BY p.expiry_date ASC
    ");
    while ($row = $expiredQuery->fetch_assoc()) {
        $notifications[] = [
            'type' => 'Expired Product',
            'message' => "Product '{$row['name']}' expired on {$row['expiry_date']}" . ($role !== 'staff' ? " in branch '{$row['branch_name']}'" : "") . "."
        ];
    }
}
?>
<div class="container mt-5">
    <h2 class="mb-4" style="color:var(--primary-color);font-weight:700;">Notifications</h2>
    <?php if (count($notifications) > 0): ?>
        <div class="list-group">
            <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= $notification['type'] ?>:</strong> <?= $notification['message'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No notifications available.</div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>