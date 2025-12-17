<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "staff", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $proofId = intval($_POST['proof_id'] ?? 0);
    $action = $_POST['action']; // 'verify' or 'reject'
    $userId = $_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');
    
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

// Fetch MTN proofs
$mtnProofs = $conn->query("
    SELECT pp.*, u.username as verified_by_name
    FROM payment_proofs pp
    LEFT JOIN users u ON pp.verified_by = u.id
    WHERE pp.payment_method = 'MTN Merchant'
    ORDER BY pp.created_at DESC
    LIMIT 100
");

// Fetch Airtel proofs
$airtelProofs = $conn->query("
    SELECT pp.*, u.username as verified_by_name
    FROM payment_proofs pp
    LEFT JOIN users u ON pp.verified_by = u.id
    WHERE pp.payment_method = 'Airtel Merchant'
    ORDER BY pp.created_at DESC
    LIMIT 100
");
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
                <div class="card-body">
                    <h5 class="title-card">MTN Mobile Money Payment Proofs</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order Ref</th>
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
                                <?php while ($proof = $mtnProofs->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($proof['order_reference']) ?></td>
                                    <td><?= htmlspecialchars($proof['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($proof['customer_phone']) ?></td>
                                    <td><?= htmlspecialchars($proof['delivery_location']) ?></td>
                                    <td>
                                        <a href="../uploads/payment_proofs/<?= $proof['screenshot_path'] ?>" 
                                            target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-image"></i> View
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
                                                onclick="verifyProof(<?= $proof['id'] ?>)">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                            <button class="btn btn-sm btn-danger" 
                                                onclick="rejectProof(<?= $proof['id'] ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Airtel Tab -->
        <div class="tab-pane fade" id="airtelTab">
            <div class="card" style="border-left: 4px solid teal;">
                <div class="card-body">
                    <h5 class="title-card">Airtel Money Payment Proofs</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order Ref</th>
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
                                <?php while ($proof = $airtelProofs->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($proof['order_reference']) ?></td>
                                    <td><?= htmlspecialchars($proof['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($proof['customer_phone']) ?></td>
                                    <td><?= htmlspecialchars($proof['delivery_location']) ?></td>
                                    <td>
                                        <a href="../uploads/payment_proofs/<?= $proof['screenshot_path'] ?>" 
                                            target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-image"></i> View
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
                                                onclick="verifyProof(<?= $proof['id'] ?>)">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                            <button class="btn btn-sm btn-danger" 
                                                onclick="rejectProof(<?= $proof['id'] ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
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
