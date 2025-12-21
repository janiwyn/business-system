<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "staff", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Get user info
$user_role = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $proofId = intval($_POST['proof_id'] ?? 0);
    $action = $_POST['action']; // 'verify' or 'reject'
    $userId = $_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');
    
    // SECURITY: Staff can only verify/reject proofs from their branch
    if ($user_role === 'staff' && $user_branch) {
        $check_stmt = $conn->prepare("
            SELECT pp.id 
            FROM payment_proofs pp
            WHERE pp.id = ? AND pp.branch_id = ?
        ");
        $check_stmt->bind_param("ii", $proofId, $user_branch);
        $check_stmt->execute();
        $allowed = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if (!$allowed) {
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only manage proofs from your branch']);
            exit;
        }
    }
    
    $newStatus = ($action === 'verify') ? 'verified' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE payment_proofs SET status = ?, verified_by = ?, verified_at = ? WHERE id = ?");
    $stmt->bind_param("sisi", $newStatus, $userId, $now, $proofId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// NEW: Get filter parameters
$filter_branch = $_GET['filter_branch'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';

// Build WHERE clause for filters
$where_conditions = [];
$where_params = [];
$where_types = '';

// Staff: only their branch
if ($user_role === 'staff' && $user_branch) {
    $where_conditions[] = "pp.branch_id = ?";
    $where_params[] = $user_branch;
    $where_types .= 'i';
} elseif ($filter_branch) {
    // Admin/Manager: optional branch filter
    $where_conditions[] = "pp.branch_id = ?";
    $where_params[] = intval($filter_branch);
    $where_types .= 'i';
}

// NEW: Status filter
if ($filter_status) {
    $where_conditions[] = "pp.status = ?";
    $where_params[] = $filter_status;
    $where_types .= 's';
}

// Date range filter
if ($filter_date_from) {
    $where_conditions[] = "DATE(pp.created_at) >= ?";
    $where_params[] = $filter_date_from;
    $where_types .= 's';
}

if ($filter_date_to) {
    $where_conditions[] = "DATE(pp.created_at) <= ?";
    $where_params[] = $filter_date_to;
    $where_types .= 's';
}

// Fetch MTN proofs (filtered)
$mtn_where = array_merge($where_conditions, ["pp.payment_method = 'MTN Merchant'"]);
$mtnWhereClause = "WHERE " . implode(' AND ', $mtn_where);

$mtn_query = "
    SELECT pp.*, u.username as verified_by_name, b.name as branch_name
    FROM payment_proofs pp
    LEFT JOIN users u ON pp.verified_by = u.id
    LEFT JOIN branch b ON pp.branch_id = b.id
    $mtnWhereClause
    ORDER BY pp.created_at DESC
    LIMIT 100
";

$mtn_stmt = $conn->prepare($mtn_query);
if ($where_types) {
    $mtn_stmt->bind_param($where_types, ...$where_params);
}
$mtn_stmt->execute();
$mtnProofs = $mtn_stmt->get_result();

// Fetch Airtel proofs (filtered)
$airtel_where = array_merge($where_conditions, ["pp.payment_method = 'Airtel Merchant'"]);
$airtelWhereClause = "WHERE " . implode(' AND ', $airtel_where);

$airtel_query = "
    SELECT pp.*, u.username as verified_by_name, b.name as branch_name
    FROM payment_proofs pp
    LEFT JOIN users u ON pp.verified_by = u.id
    LEFT JOIN branch b ON pp.branch_id = b.id
    $airtelWhereClause
    ORDER BY pp.created_at DESC
    LIMIT 100
";

$airtel_stmt = $conn->prepare($airtel_query);
if ($where_types) {
    $airtel_stmt->bind_param($where_types, ...$where_params);
}
$airtel_stmt->execute();
$airtelProofs = $airtel_stmt->get_result();
?>

<div class="container-fluid mt-5">
    <h2 class="mb-4"><i class="fas fa-receipt me-2"></i>Payment Proofs</h2>

    <!-- Tabs -->
    <ul class="nav nav-pills tm-main-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link tm-tab-btn active" data-bs-toggle="tab" data-bs-target="#mtnTab">
                MTN Mobile Money
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link tm-tab-btn" data-bs-toggle="tab" data-bs-target="#airtelTab">
                Airtel Money
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- MTN Tab -->
        <div class="tab-pane fade show active" id="mtnTab">
            <div class="card" style="border-left: 4px solid teal;">
                <div class="card-header bg-light">
                    <h5 class="title-card mb-3">MTN Mobile Money Payment Proofs</h5>
                    
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
                                <option value="verified" <?= $filter_status === 'verified' ? 'selected' : '' ?>>Verified</option>
                                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
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
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                        
                        <?php if ($filter_branch || $filter_status || $filter_date_from || $filter_date_to): ?>
                        <div class="col-md-12">
                            <a href="payment_proofs.php" class="btn btn-secondary btn-sm">
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
                                        <th>Branch</th>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th>Delivery Location</th>
                                        <th>Screenshot</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($mtnProofs && $mtnProofs->num_rows > 0): ?>
                                        <?php while ($proof = $mtnProofs->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($proof['order_reference']) ?></strong></td>
                                            <td><?= htmlspecialchars($proof['branch_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($proof['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($proof['customer_phone']) ?></td>
                                            <td><?= htmlspecialchars($proof['delivery_location']) ?></td>
                                            <td>
                                                <a href="../uploads/payment_proofs/<?= $proof['screenshot_path'] ?>" 
                                                    target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-image me-1"></i> View
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($proof['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($proof['status'] === 'verified'): ?>
                                                    <span class="badge bg-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d M Y, H:i', strtotime($proof['created_at'])) ?></td>
                                            <td>
                                                <?php if ($proof['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="verifyProof(<?= $proof['id'] ?>)" title="Verify Payment">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">No MTN payment proofs found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Airtel Tab -->
        <div class="tab-pane fade" id="airtelTab">
            <div class="card" style="border-left: 4px solid teal;">
                <div class="card-header bg-light">
                    <h5 class="title-card mb-3">Airtel Money Payment Proofs</h5>
                    
                    <!-- Filters (same as MTN) -->
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
                                <option value="verified" <?= $filter_status === 'verified' ? 'selected' : '' ?>>Verified</option>
                                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
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
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                        
                        <?php if ($filter_branch || $filter_status || $filter_date_from || $filter_date_to): ?>
                        <div class="col-md-12">
                            <a href="payment_proofs.php" class="btn btn-secondary btn-sm">
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
                                        <th>Branch</th>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th>Delivery Location</th>
                                        <th>Screenshot</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($airtelProofs && $airtelProofs->num_rows > 0): ?>
                                        <?php while ($proof = $airtelProofs->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($proof['order_reference']) ?></strong></td>
                                            <td><?= htmlspecialchars($proof['branch_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($proof['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($proof['customer_phone']) ?></td>
                                            <td><?= htmlspecialchars($proof['delivery_location']) ?></td>
                                            <td>
                                                <a href="../uploads/payment_proofs/<?= $proof['screenshot_path'] ?>" 
                                                    target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-image me-1"></i> View
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($proof['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($proof['status'] === 'verified'): ?>
                                                    <span class="badge bg-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d M Y, H:i', strtotime($proof['created_at'])) ?></td>
                                            <td>
                                                <?php if ($proof['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="verifyProof(<?= $proof['id'] ?>)" title="Verify Payment">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">No Airtel payment proofs found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function verifyProof(proofId) {
    if (!confirm('Verify this payment?')) return;
    
    const formData = new FormData();
    formData.append('action', 'verify');
    formData.append('proof_id', proofId);
    
    const response = await fetch('', { method: 'POST', body: formData });
    const data = await response.json();
    
    if (data.success) {
        alert('Payment verified!');
        location.reload();
    } else {
        alert('Error: ' + data.message);
    }
}

async function rejectProof(proofId) {
    if (!confirm('Reject this payment?')) return;
    
    const formData = new FormData();
    formData.append('action', 'reject');
    formData.append('proof_id', proofId);
    
    const response = await fetch('', { method: 'POST', body: formData });
    const data = await response.json();
    
    if (data.success) {
        alert('Payment rejected!');
        location.reload();
    } else {
        alert('Error: ' + data.message);
    }
}
</script>

<?php include '../includes/footer.php'; ?>
