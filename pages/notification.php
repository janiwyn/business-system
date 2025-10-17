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

// Fetch notifications based on role
if ($role === 'staff' || $role === 'manager' || $role === 'admin') {
    // Low stock products
    $lowStockQuery = $conn->query("
        SELECT p.name, p.stock, b.name AS branch_name
        FROM products p
        JOIN branch b ON p.`branch-id` = b.id
        WHERE p.stock < 10
        " . (($role === 'manager' || $role === 'staff') ? "AND p.`branch-id` = $branch_id" : "") . "
        ORDER BY p.stock ASC
    ");
    while ($row = $lowStockQuery->fetch_assoc()) {
        $notifications[] = [
            'type' => 'Low Stock',
            'message' => "Product '{$row['name']}' is low in stock ({$row['stock']} left)" . ($role !== 'staff' ? " in branch '{$row['branch_name']}'" : "") . ".",
            'status' => 'Warning',
            'created_at' => date('Y-m-d H:i:s') // Use the current timestamp as a placeholder
        ];
    }

    // Expired products
    $expiredQuery = $conn->query("
        SELECT p.name, p.expiry_date, b.name AS branch_name
        FROM products p
        JOIN branch b ON p.`branch-id` = b.id
        WHERE p.expiry_date IS NOT NULL AND p.expiry_date < CURDATE()
        " . (($role === 'manager' || $role === 'staff') ? "AND p.`branch-id` = $branch_id" : "") . "
        ORDER BY p.expiry_date ASC
    ");
    while ($row = $expiredQuery->fetch_assoc()) {
        $notifications[] = [
            'type' => 'Expired Product',
            'message' => "Product '{$row['name']}' expired on {$row['expiry_date']}" . ($role !== 'staff' ? " in branch '{$row['branch_name']}'" : "") . ".",
            'status' => 'Critical',
            'created_at' => date('Y-m-d H:i:s') // Use the current timestamp as a placeholder
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
                        <div class="mt-2">
                            <span class="badge bg-<?= $notification['status'] === 'Critical' ? 'danger' : 'warning' ?>">
                                <?= $notification['status'] ?>
                            </span>
                            <small class="text-muted float-end" data-created-at="<?= $notification['created_at'] ?>"></small>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-warning snooze-btn">Snooze</button>
                        <button class="btn btn-sm btn-success confirm-btn">Confirm</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No notifications available.</div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Update notification timestamps
    const updateTimestamps = () => {
        document.querySelectorAll('[data-created-at]').forEach(el => {
            const createdAt = new Date(el.getAttribute('data-created-at'));
            const now = new Date();
            const diff = Math.floor((now - createdAt) / 1000); // Difference in seconds
            let timeString = '';
            if (diff < 60) {
                timeString = `${diff} seconds ago`;
            } else if (diff < 3600) {
                timeString = `${Math.floor(diff / 60)} minutes ago`;
            } else if (diff < 86400) {
                timeString = `${Math.floor(diff / 3600)} hours ago`;
            } else {
                timeString = `${Math.floor(diff / 86400)} days ago`;
            }
            el.textContent = timeString;
        });
    };
    setInterval(updateTimestamps, 60000); // Update every minute
    updateTimestamps();

    // Snooze button functionality
    document.querySelectorAll('.snooze-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const snoozeTime = prompt('Enter snooze time in minutes:');
            if (snoozeTime && !isNaN(snoozeTime)) {
                const notification = this.closest('.list-group-item');
                notification.style.display = 'none';
                setTimeout(() => {
                    notification.style.display = 'flex';
                }, snoozeTime * 60000);
            }
        });
    });

    // Confirm button functionality
    document.querySelectorAll('.confirm-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const notification = this.closest('.list-group-item');
            notification.remove();
        });
    });
});
</script>
<?php include '../includes/footer.php'; ?>
