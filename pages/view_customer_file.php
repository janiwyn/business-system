<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin","manager","staff"]);
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

// compute totals if necessary (or use stored columns)
?>
<div class="container-fluid mt-4">
  <div class="container d-flex justify-content-center align-items-start" style="min-height:60vh;">
    <div class="card" style="max-width:720px; width:100%; margin-top:2rem;">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0"><?= htmlspecialchars($c['name'] ?? 'Unnamed Customer') ?></h5>
          <small class="text-muted">Opened: <?= htmlspecialchars($c['opening_date'] ?? '-') ?></small>
        </div>
        <div>
          <a href="customer_management.php" class="btn btn-secondary btn-sm">Back</a>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <p class="mb-1"><strong>Contact:</strong> <?= htmlspecialchars($c['contact'] ?? '-') ?></p>
            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($c['email'] ?? '-') ?></p>
          </div>
          <div class="col-md-6 text-md-end">
            <p class="mb-1"><strong>Amount Credited:</strong> <span class="fw-bold text-danger">UGX <?= number_format(floatval($c['amount_credited'] ?? 0),2) ?></span></p>
            <p class="mb-1"><strong>Account Balance:</strong> <span class="fw-bold text-success">UGX <?= number_format(floatval($c['account_balance'] ?? 0),2) ?></span></p>
          </div>
        </div>

        <hr>

        <h6 class="mb-2">Recent Transactions</h6>
        <div class="transactions-table">
          <table>
            <thead>
              <tr><th>Date & Time</th><th>Products</th><th>Amount Paid</th><th>Amount Credited</th><th>Sold By</th></tr>
            </thead>
            <tbody>
              <?php
              $tstmt = $conn->prepare("SELECT * FROM customer_transactions WHERE customer_id = ? ORDER BY date_time DESC LIMIT 20");
              $tstmt->bind_param("i",$id);
              $tstmt->execute();
              $trs = $tstmt->get_result();
              if ($trs->num_rows) {
                while ($tr = $trs->fetch_assoc()) {
                  echo "<tr>
                          <td>{$tr['date_time']}</td>
                          <td>".htmlspecialchars($tr['products_bought'])."</td>
                          <td class='text-end'>UGX ".number_format(floatval($tr['amount_paid'] ?? 0),2)."</td>
                          <td class='text-end'>UGX ".number_format(floatval($tr['amount_credited'] ?? 0),2)."</td>
                          <td>".htmlspecialchars($tr['sold_by'])."</td>
                        </tr>";
                }
              } else {
                echo "<tr><td colspan='5' class='text-center text-muted'>No transactions yet.</td></tr>";
              }
              $tstmt->close();
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
