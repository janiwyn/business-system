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
                    <div class="d-flex gap-2">
                        <!-- Snooze button: icon for small devices, text for md+ -->
                        <button class="btn btn-sm btn-warning snooze-btn d-none d-sm-inline-flex">
                            <i class="bi bi-clock me-1"></i> Snooze
                        </button>
                        <button class="btn btn-sm btn-warning snooze-btn d-inline-flex d-sm-none" title="Snooze">
                            <i class="bi bi-clock"></i>
                        </button>
                        <!-- Clear button: icon for small devices, text for md+ -->
                        <button class="btn btn-sm btn-danger confirm-btn d-none d-sm-inline-flex">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </button>
                        <button class="btn btn-sm btn-danger confirm-btn d-inline-flex d-sm-none" title="Clear">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No notifications available.</div>
    <?php endif; ?>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="clearNotifModal" tabindex="-1" aria-labelledby="clearNotifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="clearNotifModalLabel">Clear Notification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to clear this notification? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmClearBtn" class="btn btn-danger">Clear</button>
      </div>
    </div>
  </div>
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

    let notifToClear = null;

    // Change Confirm button to Clear and handle modal
    document.querySelectorAll('.confirm-btn').forEach(btn => {
        btn.textContent = ''; // Remove text for icon-only buttons
        // If icon-only, keep icon only, else add text
        if (btn.classList.contains('d-sm-inline-flex')) {
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Clear';
        } else {
            btn.innerHTML = '<i class="bi bi-x-circle"></i>';
        }
        btn.classList.remove('btn-success');
        btn.classList.add('btn-danger');
        btn.addEventListener('click', function () {
            notifToClear = this.closest('.list-group-item');
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('clearNotifModal'));
            modal.show();
        });
    });

    // Handle modal confirm
    document.getElementById('confirmClearBtn').addEventListener('click', function () {
        if (!notifToClear) return;
        // Get notification type and product name if low stock
        const notifType = notifToClear.querySelector('strong')?.textContent || '';
        let productName = '';
        if (notifType.includes('Low Stock')) {
            const msg = notifToClear.querySelector('.list-group-item div').textContent;
            const match = msg.match(/Product '([^']+)'/);
            if (match) productName = match[1];
        }
        // Remove notification from UI
        notifToClear.remove();
        notifToClear = null;
        // Hide modal
        bootstrap.Modal.getInstance(document.getElementById('clearNotifModal')).hide();

        // AJAX: clear notification server-side
        fetch('clear_notification.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `type=${encodeURIComponent(notifType)}&product=${encodeURIComponent(productName)}`
        }).then(res => res.json()).then(data => {
            // Optionally show a toast or message
            if (data.success && notifType.includes('Low Stock')) {
                // Optionally trigger update in staff dashboard via localStorage or event
                localStorage.setItem('lowStockCleared', productName);
            }
        });
    });
});
</script>
<?php include '../includes/footer.php'; ?>
