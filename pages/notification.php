<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../pages/sidebar.php';
include '../includes/header.php';

$role = $_SESSION['role'];
$branch_id = $_SESSION['branch_id'] ?? null;

// Fetch notifications
$notifications = [];

// Low stock products
$lowStockQuery = "SELECT p.name, p.stock, b.name AS branch_name FROM products p JOIN branch b ON p.`branch-id` = b.id WHERE p.stock < 10";
if ($role === 'staff') {
    $lowStockQuery .= " AND p.`branch-id` = $branch_id";
}
$lowStockResult = $conn->query($lowStockQuery);
while ($row = $lowStockResult->fetch_assoc()) {
    $notifications[] = [
        'type' => 'Low Stock',
        'message' => "Product '{$row['name']}' is low in stock ({$row['stock']} left) in branch '{$row['branch_name']}'."
    ];
}

// Expired products
$expiredQuery = "SELECT p.name, p.expiry_date, b.name AS branch_name FROM products p JOIN branch b ON p.`branch-id` = b.id WHERE p.expiry_date < CURDATE()";
if ($role === 'staff') {
    $expiredQuery .= " AND p.`branch-id` = $branch_id";
}
$expiredResult = $conn->query($expiredQuery);
while ($row = $expiredResult->fetch_assoc()) {
    $notifications[] = [
        'type' => 'Expired Product',
        'message' => "Product '{$row['name']}' expired on {$row['expiry_date']} in branch '{$row['branch_name']}'."
    ];
}

// Expiring products
$expiringQuery = "SELECT p.name, p.expiry_date, b.name AS branch_name FROM products p JOIN branch b ON p.`branch-id` = b.id WHERE p.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
if ($role === 'staff') {
    $expiringQuery .= " AND p.`branch-id` = $branch_id";
}
$expiringResult = $conn->query($expiringQuery);
while ($row = $expiringResult->fetch_assoc()) {
    $notifications[] = [
        'type' => 'Expiring Product',
        'message' => "Product '{$row['name']}' is expiring on {$row['expiry_date']} in branch '{$row['branch_name']}'."
    ];
}
?>

<div class="container mt-5">
    <h2 class="mb-4" style="color: var(--primary-color); font-weight: 700;">Notifications</h2>
    <?php if (count($notifications) > 0): ?>
        <div class="list-group">
            <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item">
                    <strong><?= $notification['type'] ?>:</strong> <?= $notification['message'] ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No notifications at the moment.</div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
