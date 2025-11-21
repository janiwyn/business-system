<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin","manager","staff", "super"]);
include '../pages/sidebar.php';
include '../includes/header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { echo "<div class='container mt-5'><div class='alert alert-danger'>Invalid customer ID.</div></div>"; include '../includes/footer.php'; exit; }

$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i",$id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$c) { echo "<div class='container mt-5'><div class='alert alert-danger'>Customer not found.</div></div>"; include '../includes/footer.php'; exit; }

// Fetch customer transactions WITH branch name
$user_role = $_SESSION['role'] ?? 'staff';
$user_branch = $_SESSION['branch_id'] ?? null;

if ($user_role === 'staff') {
    // Staff: only see transactions from their branch
    $trans_stmt = $conn->prepare("SELECT ct.*, b.name AS branch_name FROM customer_transactions ct LEFT JOIN branch b ON ct.branch_id = b.id WHERE ct.customer_id = ? AND ct.branch_id = ? ORDER BY ct.date_time DESC");
    $trans_stmt->bind_param("ii", $id, $user_branch);
} else {
    // Admin/Manager: see all transactions
    $trans_stmt = $conn->prepare("SELECT ct.*, b.name AS branch_name FROM customer_transactions ct LEFT JOIN branch b ON ct.branch_id = b.id WHERE ct.customer_id = ? ORDER BY ct.date_time DESC");
    $trans_stmt->bind_param("i", $id);
}
$trans_stmt->execute();
$transactions_result = $trans_stmt->get_result();
$trans_stmt->close();

?>
<div class="container-fluid mt-4">
  <div class="container d-flex justify-content-center align-items-start" style="min-height:60vh;">
    <div class="card" style="max-width:720px; width:100%; margin-top:2rem;">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0"><?= htmlspecialchars($c['name'] ?? 'Unnamed Customer') ?></h5>
        </div>
        <div>
          <a href="customer_management.php" class="btn btn-secondary btn-sm">← Back</a>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <strong>Contact:</strong> <?= htmlspecialchars($c['contact'] ?? '-') ?>
          </div>
          <div class="col-md-6">
            <strong>Email:</strong> <?= htmlspecialchars($c['email'] ?? '-') ?>
          </div>
          <div class="col-md-6">
            <strong>Payment Method:</strong> <?= htmlspecialchars($c['payment_method'] ?? '-') ?>
          </div>
          <div class="col-md-6">
            <strong>Opening Date:</strong> <?= htmlspecialchars($c['opening_date'] ?? '-') ?>
          </div>
          <div class="col-md-6">
            <strong>Account Balance:</strong> <span class="text-success fw-bold">UGX <?= number_format(floatval($c['account_balance'] ?? 0), 2) ?></span>
          </div>
          <div class="col-md-6">
            <strong>Amount Credited:</strong> <span class="text-danger fw-bold">UGX <?= number_format(floatval($c['amount_credited'] ?? 0), 2) ?></span>
          </div>
        </div>

        <hr>

        <h6 class="mb-2">Recent Transactions</h6>
        
        <!-- Table for medium and large devices -->
        <div class="transactions-table d-none d-md-block">
          <table>
            <thead>
              <tr>
                <th>Date & Time</th>
                <th>Branch</th>
                <th>Invoice/Receipt No.</th>
                <th>Products</th>
                <th>Amount Paid</th>
                <th>Amount Credited</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($transactions_result && $transactions_result->num_rows > 0): ?>
                <?php while ($trans = $transactions_result->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($trans['date_time']) ?></td>
                    <td><?= htmlspecialchars($trans['branch_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($trans['invoice_receipt_no'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($trans['products_bought'] ?? '-') ?></td>
                    <td>UGX <?= number_format(floatval($trans['amount_paid'] ?? 0), 2) ?></td>
                    <td>UGX <?= number_format(floatval($trans['amount_credited'] ?? 0), 2) ?></td>
                    <td><span class="badge bg-<?= ($trans['status'] === 'paid') ? 'success' : 'warning' ?>"><?= htmlspecialchars($trans['status'] ?? 'pending') ?></span></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted">No transactions found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Responsive Table Card for Small Devices -->
        <div class="d-block d-md-none mb-4">
          <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <div class="card-body">
              <div class="table-responsive-sm">
                <div class="transactions-table">
                  <table>
                    <thead>
                      <tr>
                        <th>Date & Time</th>
                        <th>Branch</th>
                        <th>Invoice/Receipt No.</th>
                        <th>Products</th>
                        <th>Amount Paid</th>
                        <th>Amount Credited</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      // Reset result pointer for mobile table
                      if ($transactions_result) $transactions_result->data_seek(0);
                      ?>
                      <?php if ($transactions_result && $transactions_result->num_rows > 0): ?>
                        <?php while ($trans = $transactions_result->fetch_assoc()): ?>
                          <tr>
                            <td><?= htmlspecialchars($trans['date_time']) ?></td>
                            <td><?= htmlspecialchars($trans['branch_name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($trans['invoice_receipt_no'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($trans['products_bought'] ?? '-') ?></td>
                            <td>UGX <?= number_format(floatval($trans['amount_paid'] ?? 0), 2) ?></td>
                            <td>UGX <?= number_format(floatval($trans['amount_credited'] ?? 0), 2) ?></td>
                            <td><span class="badge bg-<?= ($trans['status'] === 'paid') ? 'success' : 'warning' ?>"><?= htmlspecialchars($trans['status'] ?? 'pending') ?></span></td>
                          </tr>
                        <?php endwhile; ?>
                      <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No transactions found.</td></tr>
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
  </div>
</div>

<style>
/* ...existing code... */
</style>

<?php include '../includes/footer.php'; ?>
