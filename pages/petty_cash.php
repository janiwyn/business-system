<?php
ob_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff", "super"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Handle add/remove petty cash balance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['petty_action'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    if ($_POST['petty_action'] === 'add' && $amount > 0) {
        $conn->query("INSERT INTO petty_cash_balance (amount, type, created_at) VALUES ($amount, 'add', NOW())");
    }
    if ($_POST['petty_action'] === 'remove' && $amount > 0) {
        $conn->query("INSERT INTO petty_cash_balance (amount, type, created_at) VALUES ($amount, 'remove', NOW())");
    }
    header("Location: petty_cash.php");
    exit;
}

// Handle petty cash transaction (take out)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_petty_transaction'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $branch_id = intval($_POST['branch_id']);
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
    $company_reason = mysqli_real_escape_string($conn, $_POST['company_reason'] ?? '');
    $amount = floatval($_POST['amount']);
    $approved_by = mysqli_real_escape_string($conn, $_POST['approved_by']);
    $date = date('Y-m-d H:i:s');
    $final_reason = ($purpose === 'company' && $company_reason !== 'other') ? $company_reason : $reason;
    $balance = ($purpose === 'personal') ? $amount : 0;
    $conn->query("INSERT INTO petty_cash_transactions (name, branch_id, purpose, reason, amount, balance, approved_by, created_at) VALUES ('$name', $branch_id, '$purpose', '$final_reason', $amount, $balance, '$approved_by', '$date')");
    header("Location: petty_cash.php?tab=transactions");
    exit;
}

// Handle petty cash repayment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_petty_cash'])) {
    $id = intval($_POST['id']);
    $pay_amount = floatval($_POST['pay_amount']);
    $trans = $conn->query("SELECT * FROM petty_cash_transactions WHERE id=$id")->fetch_assoc();
    if ($trans && $trans['purpose'] === 'personal' && $trans['balance'] > 0 && $pay_amount > 0) {
        $new_balance = max(0, $trans['balance'] - $pay_amount);
        $conn->query("UPDATE petty_cash_transactions SET balance=$new_balance WHERE id=$id");
        // If fully repaid, duplicate row with balance 0 and action 'repaid'
        if ($new_balance == 0) {
            $now = date('Y-m-d H:i:s');
            $conn->query("INSERT INTO petty_cash_transactions (name, branch_id, purpose, reason, amount, balance, approved_by, created_at, action_type) VALUES ('{$trans['name']}', {$trans['branch_id']}, '{$trans['purpose']}', '{$trans['reason']}', {$trans['amount']}, 0, '{$trans['approved_by']}', '$now', 'repaid')");
        }
    }
    header("Location: petty_cash.php?tab=transactions");
    exit;
}

// Calculate petty cash balance
$res_add = $conn->query("SELECT SUM(amount) AS total_add FROM petty_cash_balance WHERE type='add'");
$res_remove = $conn->query("SELECT SUM(amount) AS total_remove FROM petty_cash_balance WHERE type='remove'");
$total_add = $res_add ? floatval($res_add->fetch_assoc()['total_add']) : 0;
$total_remove = $res_remove ? floatval($res_remove->fetch_assoc()['total_remove']) : 0;
$petty_balance = $total_add - $total_remove;

// Fetch branches
$branches_res = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");
$branches = $branches_res ? $branches_res->fetch_all(MYSQLI_ASSOC) : [];

// Filters for transactions
$branch_filter = $_GET['branch'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$where = [];
if ($branch_filter) $where[] = "t.branch_id = " . intval($branch_filter);
if ($date_from) $where[] = "DATE(t.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
if ($date_to) $where[] = "DATE(t.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
$whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";

// Fetch petty cash transactions
$transactions = $conn->query("
    SELECT t.*, b.name AS branch_name
    FROM petty_cash_transactions t
    LEFT JOIN branch b ON t.branch_id = b.id
    $whereClause
    ORDER BY t.created_at DESC
");
?>
<link rel="stylesheet" href="assets/css/accounting.css">
<style>
/* ...existing styles... */
.petty-balance {
    font-size: 1.5rem;
    font-weight: bold;
    color: #1abc9c;
}
.petty-action-btn {
    margin-left: 1rem;
    font-weight: 600;
    border-radius: 8px;
}
</style>
<div class="container mt-5 mb-5">
    <h2 class="page-title mb-4 text-center">Petty Cash Management</h2>
    <ul class="nav nav-tabs" id="pettyTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?= (!isset($_GET['tab']) || $_GET['tab'] !== 'transactions') ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-petty" type="button">Petty Cash</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= (isset($_GET['tab']) && $_GET['tab'] === 'transactions') ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-transactions" type="button">Petty Cash Transactions</button>
        </li>
    </ul>
    <div class="tab-content mt-3">
        <!-- Petty Cash Tab -->
        <div class="tab-pane fade <?= (!isset($_GET['tab']) || $_GET['tab'] !== 'transactions') ? 'show active' : '' ?>" id="tab-petty">
            <div class="card mb-4">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <span class="petty-balance">Account Balance: UGX <?= number_format($petty_balance, 2) ?></span>
                    </div>
                    <div>
                        <button class="btn btn-success petty-action-btn" data-bs-toggle="modal" data-bs-target="#addPettyModal">Add More</button>
                        <button class="btn btn-danger petty-action-btn" data-bs-toggle="modal" data-bs-target="#removePettyModal">Remove Petty Cash</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Petty Cash Transactions Tab -->
        <div class="tab-pane fade <?= (isset($_GET['tab']) && $_GET['tab'] === 'transactions') ? 'show active' : '' ?>" id="tab-transactions">
            <div class="card mb-4">
                <div class="card-header">Add Petty Cash Transaction</div>
                <div class="card-body">
                    <form method="POST" class="row g-3" id="pettyTransForm">
                        <input type="hidden" name="record_petty_transaction" value="1">
                        <div class="col-md-4">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select branch</option>
                                <?php foreach($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purpose</label>
                            <select name="purpose" id="purposeSelect" class="form-select" required>
                                <option value="">Select purpose</option>
                                <option value="company">Company Use</option>
                                <option value="personal">Personal Use</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="companyReasonDiv">
                            <label class="form-label">Company Reason</label>
                            <select name="company_reason" id="companyReasonSelect" class="form-select">
                                <option value="">Select reason</option>
                                <option value="Electricity Bill">Electricity Bill</option>
                                <option value="Water Bill">Water Bill</option>
                                <option value="Fuel">Fuel</option>
                                <option value="Machine Maintenance">Machine Maintenance</option>
                                <option value="Paying People">Paying People</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="reasonDiv">
                            <label class="form-label" id="reasonLabel">Reason</label>
                            <input type="text" name="reason" id="reasonInput" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Approved By</label>
                            <input type="text" name="approved_by" class="form-control" required>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Record</button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Filters -->
            <form method="GET" class="d-flex align-items-center flex-wrap gap-2 mb-3" style="gap:1rem;">
                <input type="hidden" name="tab" value="transactions">
                <label class="fw-bold me-2">From:</label>
                <input type="date" name="date_from" class="form-select me-2" value="<?= htmlspecialchars($date_from) ?>" style="width:150px;">
                <label class="fw-bold me-2">To:</label>
                <input type="date" name="date_to" class="form-select me-2" value="<?= htmlspecialchars($date_to) ?>" style="width:150px;">
                <label class="fw-bold me-2">Branch:</label>
                <select name="branch" class="form-select me-2" onchange="this.form.submit()" style="width:180px;">
                    <option value="">-- All Branches --</option>
                    <?php
                    $branches2 = $conn->query("SELECT id, name FROM branch");
                    while ($b = $branches2->fetch_assoc()):
                        $selected = ($branch_filter == $b['id']) ? 'selected' : '';
                        echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                    endwhile;
                    ?>
                </select>
                <button type="submit" class="btn btn-primary ms-2">Filter</button>
            </form>
            <!-- Transactions Table -->
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Branch</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Reason</th>
                            <th>Amount</th>
                            <th>Balance</th>
                            <th>Approved By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($transactions && $transactions->num_rows > 0): ?>
                            <?php while ($row = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    <td><?= htmlspecialchars($row['branch_name']) ?></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($row['purpose'])) ?></td>
                                    <td><?= htmlspecialchars($row['reason']) ?></td>
                                    <td>UGX <?= number_format($row['amount'], 2) ?></td>
                                    <td>
                                        <?php if ($row['purpose'] === 'personal'): ?>
                                            UGX <?= number_format($row['balance'], 2) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['approved_by']) ?></td>
                                    <td>
                                        <?php
                                        if ($row['purpose'] === 'company') {
                                            echo '<span class="badge bg-info">Company Use</span>';
                                        } elseif ($row['purpose'] === 'personal') {
                                            if ($row['balance'] > 0 && (!isset($row['action_type']) || $row['action_type'] != 'repaid')) {
                                                echo '<button class="btn btn-success btn-sm pay-petty-btn" data-id="'.$row['id'].'" data-balance="'.$row['balance'].'">Pay</button>';
                                            } elseif ($row['balance'] == 0 && isset($row['action_type']) && $row['action_type'] == 'repaid') {
                                                echo '<span class="badge bg-success">Repaid</span>';
                                            } elseif ($row['balance'] == 0) {
                                                echo '<span class="badge bg-secondary">Cleared</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No petty cash transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Petty Cash Modal -->
<div class="modal fade" id="addPettyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST">
      <div class="modal-header">
        <h5 class="modal-title">Add Petty Cash</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="petty_action" value="add">
        <div class="mb-3">
          <label class="form-label">Amount to Add</label>
          <input name="amount" class="form-control" type="number" min="0" step="0.01" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>
<!-- Remove Petty Cash Modal -->
<div class="modal fade" id="removePettyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST">
      <div class="modal-header">
        <h5 class="modal-title">Remove Petty Cash</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="petty_action" value="remove">
        <div class="mb-3">
          <label class="form-label">Amount to Remove</label>
          <input name="amount" class="form-control" type="number" min="0" step="0.01" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Remove</button>
      </div>
    </form>
  </div>
</div>
<!-- Pay Petty Cash Modal -->
<div class="modal fade" id="payPettyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" id="payPettyForm">
      <div class="modal-header">
        <h5 class="modal-title">Pay Petty Cash</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="pay_petty_cash" value="1">
        <input type="hidden" name="id" id="payPettyId">
        <div class="mb-3">
          <label class="form-label">Amount to Pay</label>
          <input name="pay_amount" id="payPettyAmount" class="form-control" type="number" min="0" step="0.01" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success">OK</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('purposeSelect').addEventListener('change', function() {
    const val = this.value;
    document.getElementById('companyReasonDiv').classList.add('d-none');
    document.getElementById('reasonDiv').classList.add('d-none');
    if (val === 'company') {
        document.getElementById('companyReasonDiv').classList.remove('d-none');
        document.getElementById('reasonDiv').classList.add('d-none');
    } else if (val === 'personal') {
        document.getElementById('reasonDiv').classList.remove('d-none');
        document.getElementById('companyReasonDiv').classList.add('d-none');
        document.getElementById('reasonLabel').textContent = 'Reason';
    }
});
document.getElementById('companyReasonSelect').addEventListener('change', function() {
    if (this.value === 'other') {
        document.getElementById('reasonDiv').classList.remove('d-none');
        document.getElementById('reasonLabel').textContent = 'Other Reason';
    } else {
        document.getElementById('reasonDiv').classList.add('d-none');
    }
});
// Pay button logic
document.querySelectorAll('.pay-petty-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('payPettyId').value = btn.getAttribute('data-id');
        document.getElementById('payPettyAmount').value = btn.getAttribute('data-balance');
        new bootstrap.Modal(document.getElementById('payPettyModal')).show();
    });
});
</script>
<?php include '../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
