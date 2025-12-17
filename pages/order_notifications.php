<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "staff", "manager"]);

// Get user info
$user_role = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Handle mark order as viewed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_viewed'])) {
    header('Content-Type: application/json');
    $order_id = intval($_POST['order_id'] ?? 0);
    
    if ($order_id > 0) {
        // You can add a 'viewed_at' column to remote_orders if needed
        // For now, just return success
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

include '../pages/sidebar.php';
include '../includes/header.php';

// Fetch pending orders (filtered by branch for staff)
if ($user_role === 'staff' && $user_branch) {
    $orders_query = $conn->prepare("
        SELECT ro.*, b.name as branch_name,
               (SELECT COUNT(*) FROM remote_order_items WHERE order_id = ro.id) as items_count
        FROM remote_orders ro
        LEFT JOIN branch b ON ro.branch_id = b.id
        WHERE ro.status = 'pending' AND ro.branch_id = ?
        ORDER BY ro.created_at DESC
        LIMIT 100
    ");
    $orders_query->bind_param("i", $user_branch);
} else {
    $orders_query = $conn->prepare("
        SELECT ro.*, b.name as branch_name,
               (SELECT COUNT(*) FROM remote_order_items WHERE order_id = ro.id) as items_count
        FROM remote_orders ro
        LEFT JOIN branch b ON ro.branch_id = b.id
        WHERE ro.status = 'pending'
        ORDER BY ro.created_at DESC
        LIMIT 100
    ");
}

$orders_query->execute();
$pending_orders = $orders_query->get_result();
?>

<div class="container-fluid mt-5">
    <div class="card" style="border-left: 4px solid teal;">
        <div class="card-header title-card d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Order Notifications</h5>
            <span class="badge bg-danger"><?= $pending_orders->num_rows ?> Pending</span>
        </div>
        <div class="card-body">
            <?php if ($pending_orders->num_rows > 0): ?>
                <div class="notifications-list">
                    <?php while ($order = $pending_orders->fetch_assoc()): ?>
                        <div class="notification-item border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-shopping-cart text-primary me-2"></i>
                                        <strong>New Order: <?= htmlspecialchars($order['order_reference']) ?></strong>
                                    </div>
                                    
                                    <div class="order-details ms-4">
                                        <p class="mb-1">
                                            <i class="fas fa-user me-2 text-muted"></i>
                                            <strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-phone me-2 text-muted"></i>
                                            <strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-store me-2 text-muted"></i>
                                            <strong>Branch:</strong> <?= htmlspecialchars($order['branch_name']) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-money-bill-wave me-2 text-muted"></i>
                                            <strong>Amount:</strong> UGX <?= number_format($order['expected_amount'], 2) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-credit-card me-2 text-muted"></i>
                                            <strong>Payment:</strong> <?= htmlspecialchars($order['payment_method']) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-box me-2 text-muted"></i>
                                            <strong>Items:</strong> <?= $order['items_count'] ?> product(s)
                                        </p>
                                        <p class="mb-0">
                                            <i class="fas fa-clock me-2 text-muted"></i>
                                            <strong>Ordered:</strong> <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="notification-actions ms-3">
                                    <a href="../pages/remote_orders.php" class="btn btn-sm btn-primary mb-2">
                                        <i class="fas fa-eye me-1"></i>View All Orders
                                    </a>
                                    <a href="../pages/qr_scanner.php" class="btn btn-sm btn-success">
                                        <i class="fas fa-qrcode me-1"></i>Scan QR
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Pending Orders</h5>
                    <p class="text-muted">All orders have been processed!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.notification-item {
    padding: 1rem;
    border-radius: 8px;
    transition: background 0.2s;
}

.notification-item:hover {
    background: rgba(26, 188, 156, 0.05);
}

.order-details p {
    font-size: 0.95rem;
    color: var(--text-color);
}

body.dark-mode .notification-item:hover {
    background: rgba(26, 188, 156, 0.1);
}

body.dark-mode .order-details p {
    color: #fff;
}

.notification-actions .btn {
    white-space: nowrap;
}

/* Badge styling */
.badge {
    font-size: 0.85rem;
    padding: 0.5rem 0.75rem;
}
</style>

<?php include '../includes/footer.php'; ?>
