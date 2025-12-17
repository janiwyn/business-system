<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "staff", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $orderId = intval($_POST['order_id']);
        $action = $_POST['action'];
        
        if ($action === 'finish') {
            $stmt = $conn->prepare("UPDATE remote_orders SET status = 'finished', processed_by = ?, processed_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $orderId);
            
            if ($stmt->execute()) {
                // Log audit
                $stmt2 = $conn->prepare("INSERT INTO remote_order_audit_logs (order_id, action, performed_by, user_id, old_status, new_status, notes) VALUES (?, 'order_finished', ?, ?, 'pending', 'finished', 'Order completed by staff')");
                $stmt2->bind_param("isi", $orderId, $_SESSION['username'], $_SESSION['user_id']);
                $stmt2->execute();
                
                echo json_encode(['success' => true, 'message' => 'Order marked as finished']);
                exit;
            }
        } elseif ($action === 'cancel') {
            $stmt = $conn->prepare("UPDATE remote_orders SET status = 'cancelled', processed_by = ?, processed_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $_SESSION['user_id'], $orderId);
            
            if ($stmt->execute()) {
                // Log audit
                $stmt2 = $conn->prepare("INSERT INTO remote_order_audit_logs (order_id, action, performed_by, user_id, old_status, new_status, notes) VALUES (?, 'order_cancelled', ?, ?, 'pending', 'cancelled', 'Order cancelled by staff')");
                $stmt2->bind_param("isi", $orderId, $_SESSION['username'], $_SESSION['user_id']);
                $stmt2->execute();
                
                echo json_encode(['success' => true, 'message' => 'Order cancelled']);
                exit;
            }
        }
    }
}

// Get today's stats - FIXED: Get ALL orders regardless of branch for now (to debug)
$today = date('Y-m-d');
$branchId = $_SESSION['branch_id'];

// FIX: Temporarily remove branch filter to see ALL orders
$pendingCount = $conn->query("SELECT COUNT(*) as count FROM remote_orders 
    WHERE status = 'pending' 
    AND DATE(created_at) = '$today'")->fetch_assoc()['count'];

$finishedCount = $conn->query("SELECT COUNT(*) as count FROM remote_orders 
    WHERE status = 'finished' 
    AND DATE(created_at) = '$today'")->fetch_assoc()['count'];

// Get ALL orders (remove branch filter temporarily to debug)
$ordersQuery = $conn->query("
    SELECT ro.*, b.name as branch_name 
    FROM remote_orders ro
    LEFT JOIN branch b ON ro.branch_id = b.id
    ORDER BY ro.created_at DESC
    LIMIT 100
");
?>

<div class="container-fluid mt-4 main-content-scroll">
    <h2 class="mb-4"><i class="fas fa-shopping-bag me-2"></i>Remote Orders</h2>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card stat-card gradient-warning animate-stat">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Pending Orders (Today)</h6>
                        <h2 class="counter" data-target="<?= $pendingCount ?>">0</h2>
                    </div>
                    <i class="fas fa-clock fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card gradient-success animate-stat">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Finished Orders (Today)</h6>
                        <h2 class="counter" data-target="<?= $finishedCount ?>">0</h2>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card" style="border-left: 4px solid teal;">
        <div class="card-body">
            <h5 class="title-card mb-3">All Remote Orders</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order Ref</th>
                            <th>Date & Time</th>
                            <th>Branch</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Products</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($ordersQuery && $ordersQuery->num_rows > 0):
                            while ($order = $ordersQuery->fetch_assoc()): 
                                // Get order items
                                $itemsQuery = $conn->query("SELECT product_name, quantity, unit_price FROM remote_order_items WHERE order_id = {$order['id']}");
                                $items = [];
                                while ($item = $itemsQuery->fetch_assoc()) {
                                    $items[] = $item;
                                }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($order['order_reference']) ?></strong></td>
                            <td><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></td>
                            <td><?= htmlspecialchars($order['branch_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick='showOrderDetails(<?= $order['id'] ?>, <?= json_encode($items, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    View (<?= count($items) ?>)
                                </button>
                            </td>
                            <td>UGX <?= number_format($order['expected_amount'], 2) ?></td>
                            <td>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <span class="badge bg-warning pulse-badge">Pending</span>
                                <?php elseif ($order['status'] === 'finished'): ?>
                                    <span class="badge bg-success">Finished</span>
                                <?php elseif ($order['status'] === 'cancelled'): ?>
                                    <span class="badge bg-danger">Cancelled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success" onclick="updateOrderStatus(<?= $order['id'] ?>, 'finish')" title="Mark as Finished">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="updateOrderStatus(<?= $order['id'] ?>, 'cancel')" title="Cancel Order">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No remote orders found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-list me-2"></i>Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent"></div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
.pulse-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.animate-stat {
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.counter {
    font-size: 2.5rem;
    font-weight: 700;
}
</style>

<script>
// Counter Animation
document.querySelectorAll('.counter').forEach(counter => {
    const target = parseInt(counter.getAttribute('data-target'));
    const duration = 1000;
    const increment = target / (duration / 16);
    let current = 0;
    
    const updateCounter = () => {
        current += increment;
        if (current < target) {
            counter.textContent = Math.floor(current);
            requestAnimationFrame(updateCounter);
        } else {
            counter.textContent = target;
        }
    };
    
    updateCounter();
});

// Show Order Details
function showOrderDetails(orderId, items) {
    let content = '<div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead><tbody>';
    
    items.forEach(item => {
        const subtotal = item.quantity * item.unit_price;
        content += `<tr>
            <td>${item.product_name}</td>
            <td>${item.quantity}</td>
            <td>UGX ${Number(item.unit_price).toLocaleString()}</td>
            <td>UGX ${Number(subtotal).toLocaleString()}</td>
        </tr>`;
    });
    
    content += '</tbody></table></div>';
    document.getElementById('orderDetailsContent').innerHTML = content;
    
    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    modal.show();
}

// Update Order Status
function updateOrderStatus(orderId, action) {
    const actionText = action === 'finish' ? 'mark this order as finished' : 'cancel this order';
    
    if (!confirm(`Are you sure you want to ${actionText}?`)) {
        return;
    }
    
    fetch('remote_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}&order_id=${orderId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Action failed');
        }
    });
}

// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);
</script>
