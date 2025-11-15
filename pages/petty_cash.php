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
    $approved_by = mysqli_real_escape_string($conn, $_POST['approved_by'] ?? '');
    if ($_POST['petty_action'] === 'add' && $amount > 0) {
        $conn->query("INSERT INTO petty_cash_balance (amount, type, created_at, approved_by) VALUES ($amount, 'add', NOW(), '$approved_by')");
    }
    if ($_POST['petty_action'] === 'remove' && $amount > 0) {
        $conn->query("INSERT INTO petty_cash_balance (amount, type, created_at, approved_by) VALUES ($amount, 'remove', NOW(), '$approved_by')");
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

// Get user role and branch for staff restrictions
$user_role = $_SESSION['role'] ?? '';
$user_branch_id = $_SESSION['branch_id'] ?? null;

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

// Fetch petty cash balance actions for table
$balance_actions = $conn->query("SELECT * FROM petty_cash_balance ORDER BY created_at DESC");

// Ensure active state matches shown pane (staff defaults to Transactions)
$pcTransActive = (isset($_GET['tab']) && $_GET['tab'] === 'transactions') || (($user_role ?? '') === 'staff');
?>
<style>
/* Petty Cash page: pill tabs identical to Sales/Till, forced over Bootstrap */
.pc-tabs { display:flex; flex-wrap:wrap; gap:.75rem; margin:.5rem 0 1rem; border:none; }
.pc-tabs .nav-link {
  border: 2px solid var(--primary-color) !important;
  background: #fff !important;
  color: var(--primary-color) !important;
  border-radius: 14px !important;
  padding: .48rem 1.15rem !important;
  font-weight: 600 !important;
  box-shadow: 0 2px 6px rgba(0,0,0,.08) !important;
  transition: background .18s, color .18s, box-shadow .18s, transform .18s !important;
}
.pc-tabs .nav-link:hover {
  background: var(--primary-color) !important;
  color: #fff !important;
  transform: translateY(-2px);
}
.pc-tabs .nav-link.active,
.pc-tabs .show > .nav-link {
  /* Match Sales/Till “active” look with soft gradient + glow */
  background: linear-gradient(135deg, #1abc9c 0%, #56ccf2 100%) !important;
  color: #fff !important;
  border-color: #1abc9c !important;
  box-shadow: 0 8px 18px rgba(26,188,156,.28) !important;
}

/* Dark mode parity */
body.dark-mode .pc-tabs .nav-link {
  background: #23243a !important;
  border-color: #1abc9c !important;
  color: #1abc9c !important;
  box-shadow: 0 2px 6px rgba(0,0,0,.4) !important;
}
body.dark-mode .pc-tabs .nav-link:hover {
  background: #1abc9c !important;
  color: #fff !important;
}
body.dark-mode .pc-tabs .nav-link.active,
body.dark-mode .pc-tabs .show > .nav-link {
  background: linear-gradient(135deg, #1abc9c 0%, #3498db 100%) !important;
  color: #fff !important;
  border-color: #1abc9c !important;
  box-shadow: 0 10px 20px rgba(26,188,156,.45) !important;
}
</style>

<div class="container mt-5 mb-5">
    <h2 class="page-title mb-4 text-center">Petty Cash Management</h2>

    <!-- Pills styled like Sales page -->
    <ul class="nav nav-pills pc-tabs mb-4" id="pettyCashTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link<?= $pcTransActive ? '' : ' active' ?>"
                id="pc-main-tab"
                data-bs-toggle="tab"
                data-bs-target="#petty-cash"
                type="button"
                role="tab"
                aria-controls="petty-cash"
                aria-selected="<?= $pcTransActive ? 'false' : 'true' ?>">
          Petty Cash
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link<?= $pcTransActive ? ' active' : '' ?>"
                id="pc-trans-tab"
                data-bs-toggle="tab"
                data-bs-target="#petty-cash-transactions"
                type="button"
                role="tab"
                aria-controls="petty-cash-transactions"
                aria-selected="<?= $pcTransActive ? 'true' : 'false' ?>">
          Petty Cash Transactions
        </button>
      </li>
    </ul>

    <div class="tab-content" id="pettyCashTabsContent">
        <!-- Petty Cash Tab (hide for staff) -->
        <?php if ($user_role !== 'staff'): ?>
        <div class="tab-pane fade <?= $pcTransActive ? '' : 'show active' ?>" id="petty-cash">
            <!-- Responsive petty balance card for small/medium devices -->
            <div class="petty-balance-card d-flex d-md-none mb-4">
                <span class="petty-balance">Account Balance: UGX <?= number_format($petty_balance, 2) ?></span>
                <div class="petty-balance-actions">
                    <button class="petty-action-icon-btn add" title="Add Petty Cash" data-bs-toggle="modal" data-bs-target="#addPettyModal">
                        <i class="fa-solid fa-circle-plus"></i>
                    </button>
                    <button class="petty-action-icon-btn remove" title="Remove Petty Cash" data-bs-toggle="modal" data-bs-target="#removePettyModal">
                        <i class="fa-solid fa-circle-minus"></i>
                    </button>
                </div>
            </div>
            <!-- Original card for desktop -->
            <div class="card mb-4 d-none d-md-flex">
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
            <!-- Improved: Balance Actions Table styled like expenses table -->
            <div class="card">
                <div class="petty-balance-header">
                    <span class="fw-bold title-card"><i class="fa-solid fa-wallet"></i> Petty Cash Balance Actions</span>
                </div>
                <div class="card-body p-0">
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Action</th>
                                    <th>Amount</th>
                                    <th>Approved By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($balance_actions && $balance_actions->num_rows > 0): ?>
                                    <?php while ($row = $balance_actions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                                            <td>
                                                <?php if ($row['type'] === 'add'): ?>
                                                    <span class="badge bg-success">Add</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Remove</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>UGX <?= number_format($row['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($row['approved_by']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No balance actions found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Petty Cash Transactions Tab -->
        <div class="tab-pane fade <?= $pcTransActive ? 'show active' : '' ?>" id="petty-cash-transactions">
            <div class="card mb-4 add-transaction-card">
                <div class="card-header title-card">➕ Add Petty Cash Transaction</div>
                <div class="card-body">
                    <form method="POST" class="row g-3" id="pettyTransForm">
                        <input type="hidden" name="record_petty_transaction" value="1">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Branch</label>
                            <select name="branch_id" class="form-select" required>
                                <?php if ($user_role === 'staff' && $user_branch_id): ?>
                                    <?php
                                    // Only show staff's branch
                                    foreach ($branches as $b) {
                                        if ($b['id'] == $user_branch_id) {
                                            echo "<option value=\"{$b['id']}\" selected>" . htmlspecialchars($b['name']) . "</option>";
                                            break;
                                        }
                                    }
                                    ?>
                                <?php else: ?>
                                    <option value="">Select branch</option>
                                    <?php foreach($branches as $b): ?>
                                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Purpose</label>
                            <select name="purpose" id="purposeSelect" class="form-select" required>
                                <option value="">Select purpose</option>
                                <option value="company">Company Use</option>
                                <option value="personal">Personal Use</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="companyReasonDiv">
                            <label class="form-label fw-semibold">Company Reason</label>
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
                            <label class="form-label fw-semibold" id="reasonLabel">Reason</label>
                            <input type="text" name="reason" id="reasonInput" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Amount</label>
                            <input type="number" name="amount" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Approved By</label>
                            <input type="text" name="approved_by" class="form-control" required>
                        </div>
                        <div class="col-12 text-end mt-3">
                            <button type="submit" class="btn btn-primary">Record</button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Filters (hide for staff) -->
            <?php if ($user_role !== 'staff'): ?>
            <div class="card-header bg-light petty-filters-header d-flex flex-wrap align-items-center gap-2 mb-0" style="gap:1rem;">
                <form method="GET" class="d-flex align-items-center flex-wrap gap-2 mb-0" style="gap:1rem; width:100%;">
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
            </div>
            <?php endif; ?>
            <!-- Responsive Table for Small Devices -->
            <div class="d-block d-md-none mb-4 petty-transactions-card">
                <div class="card transactions-card">
                    <div class="card-body">
                        <div class="table-responsive-sm">
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
                                                        // Use icons for actions on small devices
                                                        if ($row['purpose'] === 'company') {
                                                            echo '<span class="badge bg-info">Company Use</span>';
                                                        } elseif ($row['purpose'] === 'personal') {
                                                            if ($row['balance'] > 0 && (!isset($row['action_type']) || $row['action_type'] != 'repaid')) {
                                                                echo '<button class="petty-action-icon-btn pay pay-petty-btn" title="Pay" data-id="'.$row['id'].'" data-balance="'.$row['balance'].'"><i class="fa-solid fa-money-bill-wave"></i></button>';
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
            </div>
            <!-- Table for medium and large devices -->
            <div class="transactions-table d-none d-md-block">
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
                        <?php
                        // Reset pointer for $transactions
                        if ($transactions && $transactions->num_rows > 0) $transactions->data_seek(0);
                        if ($transactions && $transactions->num_rows > 0):
                            while ($row = $transactions->fetch_assoc()):
                        ?>
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
                                    // Regular buttons for medium/large devices
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
                        <?php endwhile; else: ?>
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
        <div class="mb-3">
          <label class="form-label">Approved By</label>
          <input name="approved_by" class="form-control" type="text" required>
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
        <div class="mb-3">
          <label class="form-label">Approved By</label>
          <input name="approved_by" class="form-control" type="text" required>
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
// Pay button logic (works for both icon and regular button)
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
