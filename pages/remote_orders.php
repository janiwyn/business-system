<?php
// STEP 1: Handle AJAX actions FIRST (before ANY includes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean all output buffers
    while (ob_get_level()) ob_end_clean();
    ob_start();
    
    // Prevent any output
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Start fresh session if needed
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include ONLY database connection
    require_once '../includes/db.php';
    
    // Set JSON header FIRST
    header('Content-Type: application/json; charset=utf-8');
    
    $orderId = intval($_POST['order_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($action === 'cancel') {
        try {
            // FIXED: Remove processed_by and processed_at (columns don't exist)
            $stmt = $conn->prepare("UPDATE remote_orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $orderId);
            
            if ($stmt->execute()) {
                // Log audit
                $stmt2 = $conn->prepare("INSERT INTO remote_order_audit_logs (order_id, action, performed_by, user_id, old_status, new_status, notes) VALUES (?, 'order_cancelled', ?, ?, 'pending', 'cancelled', 'Order cancelled by staff')");
                $stmt2->bind_param("isi", $orderId, $_SESSION['username'], $_SESSION['user_id']);
                $stmt2->execute();
                $stmt2->close();
                
                echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'finish') {
        try {
            // FIXED: Remove processed_by and processed_at
            $stmt = $conn->prepare("UPDATE remote_orders SET status = 'finished' WHERE id = ?");
            $stmt->bind_param("i", $orderId);
            
            if ($stmt->execute()) {
                $stmt2 = $conn->prepare("INSERT INTO remote_order_audit_logs (order_id, action, performed_by, user_id, old_status, new_status, notes) VALUES (?, 'order_finished', ?, ?, 'pending', 'finished', 'Order completed by staff')");
                $stmt2->bind_param("isi", $orderId, $_SESSION['username'], $_SESSION['user_id']);
                $stmt2->execute();
                $stmt2->close();
                
                echo json_encode(['success' => true, 'message' => 'Order marked as finished']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
    ob_end_flush();
    exit;
}

// STEP 2: NOW include normal page files (for HTML rendering)
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "staff", "manager"]);

// Auto-cleanup old cancelled orders (runs on page load)
include '../includes/cleanup_orders.php';

include '../pages/sidebar.php';
include '../includes/header.php';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $orderId = intval($_POST['order_id']);
        $action = $_POST['action'];
        
        if ($action === 'cancel') {
            // FIXED: Add cancelled_at timestamp
            $stmt = $conn->prepare("UPDATE remote_orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $orderId);
            
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

// Get stats and filters
$branchId = $_SESSION['branch_id'];
$user_role = $_SESSION['role'];

// NEW: Pagination setup
$items_per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// NEW: Filter parameters
$filter_branch = $_GET['filter_branch'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';
$search_customer = $_GET['search_customer'] ?? '';

// Build WHERE clause
$where_conditions = [];
$where_params = [];
$where_types = '';

// Staff: only their branch
if ($user_role === 'staff' && $branchId) {
    $where_conditions[] = "ro.branch_id = ?";
    $where_params[] = $branchId;
    $where_types .= 'i';
} elseif ($filter_branch) {
    // Admin/Manager: optional branch filter
    $where_conditions[] = "ro.branch_id = ?";
    $where_params[] = intval($filter_branch);
    $where_types .= 'i';
}

// Status filter
if ($filter_status) {
    $where_conditions[] = "ro.status = ?";
    $where_params[] = $filter_status;
    $where_types .= 's';
}

// Date range filter
if ($filter_date_from) {
    $where_conditions[] = "DATE(ro.created_at) >= ?";
    $where_params[] = $filter_date_from;
    $where_types .= 's';
}

if ($filter_date_to) {
    $where_conditions[] = "DATE(ro.created_at) <= ?";
    $where_params[] = $filter_date_to;
    $where_types .= 's';
}

// Customer name search
if ($search_customer) {
    $where_conditions[] = "ro.customer_name LIKE ?";
    $where_params[] = '%' . $search_customer . '%';
    $where_types .= 's';
}

$whereClause = count($where_conditions) > 0 ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM remote_orders ro $whereClause";
$count_stmt = $conn->prepare($count_query);
if ($where_types) {
    $count_stmt->bind_param($where_types, ...$where_params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);
$count_stmt->close();

// Get stats (filtered)
$pendingCount = 0;
$finishedCount = 0;

$pending_query = "SELECT COUNT(*) as count FROM remote_orders ro WHERE ro.status = 'pending'" . ($whereClause ? " AND " . str_replace("WHERE ", "", $whereClause) : "");
$pending_stmt = $conn->prepare($pending_query);
if ($where_types) {
    $pending_stmt->bind_param($where_types, ...$where_params);
}
$pending_stmt->execute();
$pendingCount = $pending_stmt->get_result()->fetch_assoc()['count'];
$pending_stmt->close();

$finished_query = "SELECT COUNT(*) as count FROM remote_orders ro WHERE ro.status = 'finished'" . ($whereClause ? " AND " . str_replace("WHERE ", "", $whereClause) : "");
$finished_stmt = $conn->prepare($finished_query);
if ($where_types) {
    $finished_stmt->bind_param($where_types, ...$where_params);
}
$finished_stmt->execute();
$finishedCount = $finished_stmt->get_result()->fetch_assoc()['count'];
$finished_stmt->close();

// Fetch orders with pagination
$orders_query = "
    SELECT ro.*, b.name as branch_name 
    FROM remote_orders ro
    LEFT JOIN branch b ON ro.branch_id = b.id
    $whereClause
    ORDER BY ro.created_at DESC
    LIMIT ? OFFSET ?
";

$orders_stmt = $conn->prepare($orders_query);
// Add LIMIT and OFFSET to params
$limit_params = $where_params;
$limit_params[] = $items_per_page;
$limit_params[] = $offset;
$limit_types = $where_types . 'ii';

$orders_stmt->bind_param($limit_types, ...$limit_params);
$orders_stmt->execute();
$ordersQuery = $orders_stmt->get_result();
?>

<div class="container-fluid mt-4 main-content-scroll">
    <h2 class="mb-4"><i class="fas fa-shopping-bag me-2"></i>Remote Orders</h2>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card stat-card gradient-warning animate-stat">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Pending Orders</h6>
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
                        <h6>Finished Orders</h6>
                        <h2 class="counter" data-target="<?= $finishedCount ?>">0</h2>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card" style="border-left: 4px solid teal;">
        <div class="card-header bg-light">
            <h5 class="title-card mb-3">All Remote Orders</h5>
            
            <!-- Filters -->
            <form method="GET" class="row g-3 align-items-end">
                <?php if ($user_role !== 'staff'): ?>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Branch</label>
                    <select name="filter_branch" class="form-select">
                        <option value="">All Branches</option>
                        <?php
                        $branches = $conn->query("SELECT id, name FROM branch ORDER BY name");
                        while ($b = $branches->fetch_assoc()) {
                            $selected = ($filter_branch == $b['id']) ? 'selected' : '';
                            echo "<option value='{$b['id']}' $selected>" . htmlspecialchars($b['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-2">
                    <label class="form-label fw-bold">Status</label>
                    <select name="filter_status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="finished" <?= $filter_status === 'finished' ? 'selected' : '' ?>>Finished</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-bold">From Date</label>
                    <input type="date" name="filter_date_from" class="form-control" value="<?= htmlspecialchars($filter_date_from) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-bold">To Date</label>
                    <input type="date" name="filter_date_to" class="form-control" value="<?= htmlspecialchars($filter_date_to) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold">Search Customer</label>
                    <input type="text" name="search_customer" class="form-control" placeholder="Customer name..." value="<?= htmlspecialchars($search_customer) ?>">
                </div>
                
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
                
                <?php if ($filter_branch || $filter_status || $filter_date_from || $filter_date_to || $search_customer): ?>
                <div class="col-md-12">
                    <a href="remote_orders.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <div class="transactions-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Order Ref</th>
                                <th>Date & Time</th>
                                <?php if ($user_role !== 'staff'): ?>
                                <th>Branch</th>
                                <?php endif; ?>
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
                                <?php if ($user_role !== 'staff'): ?>
                                <td><?= htmlspecialchars($order['branch_name'] ?? 'N/A') ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick='showOrderDetails(<?= $order['id'] ?>, <?= json_encode($items, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="fas fa-eye me-1"></i>View (<?= count($items) ?>)
                                    </button>
                                </td>
                                <td><span class="fw-bold text-success">UGX <?= number_format($order['expected_amount'], 2) ?></span></td>
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
                                        <button class="btn btn-sm btn-danger" onclick="cancelOrder(<?= $order['id'] ?>)" title="Cancel Order">
                                            <i class="fas fa-times"></i> Cancel
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
                                <td colspan="<?= $user_role !== 'staff' ? '9' : '8' ?>" class="text-center text-muted">No remote orders found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?><?= $filter_branch ? '&filter_branch=' . $filter_branch : '' ?><?= $filter_status ? '&filter_status=' . $filter_status : '' ?><?= $filter_date_from ? '&filter_date_from=' . $filter_date_from : '' ?><?= $filter_date_to ? '&filter_date_to=' . $filter_date_to : '' ?><?= $search_customer ? '&search_customer=' . urlencode($search_customer) : '' ?>">Previous</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <?php if ($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?><?= $filter_branch ? '&filter_branch=' . $filter_branch : '' ?><?= $filter_status ? '&filter_status=' . $filter_status : '' ?><?= $filter_date_from ? '&filter_date_from=' . $filter_date_from : '' ?><?= $filter_date_to ? '&filter_date_to=' . $filter_date_to : '' ?><?= $search_customer ? '&search_customer=' . urlencode($search_customer) : '' ?>"><?= $p ?></a>
                        </li>
                        <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?><?= $filter_branch ? '&filter_branch=' . $filter_branch : '' ?><?= $filter_status ? '&filter_status=' . $filter_status : '' ?><?= $filter_date_from ? '&filter_date_from=' . $filter_date_from : '' ?><?= $filter_date_to ? '&filter_date_to=' . $filter_date_to : '' ?><?= $search_customer ? '&search_customer=' . urlencode($search_customer) : '' ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <p class="text-center text-muted">
                Showing <?= ($offset + 1) ?> to <?= min($offset + $items_per_page, $total_items) ?> of <?= $total_items ?> orders
            </p>
            <?php endif; ?>
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

// Cancel Order with confirmation - FIXED
function cancelOrder(orderId) {
    // Show confirmation dialog
    if (!confirm('⚠️ Are you sure you want to cancel this order?\n\nThis action cannot be undone.')) {
        return;
    }
    
    fetch('remote_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=cancel&order_id=${orderId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Failed to cancel order: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ An error occurred while cancelling the order: ' + error.message);
    });
}

// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);
</script>
