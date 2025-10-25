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
              <tr>
                <th>Date & Time</th>
                <th>Top-up/Deduction</th>
                <th class="text-end">Amount Topped Up</th>
                <th class="text-end">Amount Credited</th>
                <th class="text-end">Amount Deducted</th>
                <th class="text-end">Account Balance</th>
                <th>Served By</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $tstmt = $conn->prepare("SELECT * FROM customer_transactions WHERE customer_id = ? ORDER BY date_time ASC");
              $tstmt->bind_param("i", $id);
              $tstmt->execute();
              $trs = $tstmt->get_result();

              // Start from zero, track balance and credited
              $balance = 0;
              $credited = 0;
              $rows = [];
              while ($tr = $trs->fetch_assoc()) {
                  $pb = $tr['products_bought'] ?? '';
                  $dt_display = htmlspecialchars($tr['date_time'] ?? $tr['date'] ?? '');
                  $served_by = htmlspecialchars($tr['sold_by'] ?? '-');
                  $amount_topup = 0;
                  $amount_credited = 0;
                  $amount_deducted = 0;
                  $type = '';

                  if (trim($pb) === 'Account Top-up') {
                      $type = 'TOP UP';
                      $amount_topup = floatval($tr['amount_paid'] ?? 0);
                      $amount_credited = floatval($tr['amount_credited'] ?? 0);

                      // Top-up logic: first pay off credited, remainder goes to balance
                      $credit_to_pay = min($amount_topup, $credited);
                      $credited -= $credit_to_pay;
                      $balance += ($amount_topup - $credit_to_pay);

                  } elseif (trim($pb) === 'Account Deduction') {
                      $type = 'REDUCTION';
                      $amount_deducted = floatval($tr['amount_paid'] ?? 0);
                      $amount_credited = floatval($tr['amount_credited'] ?? 0);

                      // Deduction logic: subtract from balance, if not enough, add to credited
                      if ($balance >= $amount_deducted) {
                          $balance -= $amount_deducted;
                      } else {
                          $credited += ($amount_deducted - $balance);
                          $balance = 0;
                      }
                      $credited -= $amount_credited; // If deduction pays off credited

                  } else {
                      // Purchases: products_bought is not a string, or is a JSON array
                      $type = 'PURCHASE';
                      $amount_deducted = floatval($tr['amount_paid'] ?? 0);

                      // Purchase logic: subtract from balance, if not enough, add to credited
                      if ($balance >= $amount_deducted) {
                          $balance -= $amount_deducted;
                      } else {
                          $credited += ($amount_deducted - $balance);
                          $balance = 0;
                      }
                  }

                  $rows[] = [
                      'dt' => $dt_display,
                      'type' => $type,
                      'amount_topup' => $amount_topup,
                      'amount_credited' => $amount_credited,
                      'amount_deducted' => $amount_deducted,
                      'balance' => $balance,
                      'served_by' => $served_by
                  ];
              }
              $tstmt->close();

              if (count($rows)) {
                  foreach ($rows as $row) {
                      echo "<tr>
                          <td>{$row['dt']}</td>
                          <td>{$row['type']}</td>
                          <td class='text-end'>" . ($row['amount_topup'] ? "UGX " . number_format($row['amount_topup'],2) : '-') . "</td>
                          <td class='text-end'>" . ($row['amount_credited'] ? "UGX " . number_format($row['amount_credited'],2) : '-') . "</td>
                          <td class='text-end'>" . ($row['amount_deducted'] ? "UGX " . number_format($row['amount_deducted'],2) : '-') . "</td>
                          <td class='text-end'>UGX " . number_format($row['balance'],2) . "</td>
                          <td>{$row['served_by']}</td>
                      </tr>";
                  }
              } else {
                  echo "<tr><td colspan='7' class='text-center text-muted'>No account top-ups or deductions yet.</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* ...existing code... */
body.dark-mode,
body.dark-mode .card,
body.dark-mode .card-header,
body.dark-mode .title-card,
body.dark-mode .form-label,
body.dark-mode label,
body.dark-mode .card-body,
body.dark-mode .transactions-table thead,
body.dark-mode .transactions-table tbody td,
body.dark-mode .transactions-table tbody tr,
body.dark-mode .alert,
body.dark-mode .nav-tabs .nav-link,
body.dark-mode .accordion-button,
body.dark-mode .accordion-body {
    color: #fff !important;
    background-color: #23243a !important;
}
body.dark-mode .transactions-table thead {
    background-color: #1abc9c !important;
    color: #fff !important;
}
body.dark-mode .transactions-table tbody tr {
    background-color: #2c2c3a !important;
}
body.dark-mode .transactions-table tbody tr:nth-child(even) {
    background-color: #272734 !important;
}
body.dark-mode .form-control,
body.dark-mode .form-select {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .form-control:focus,
body.dark-mode .form-select:focus {
    background-color: #23243a !important;
    color: #fff !important;
}
body.dark-mode .btn,
body.dark-mode .btn-primary,
body.dark-mode .btn-success,
body.dark-mode .btn-danger,
body.dark-mode .btn-warning {
    color: #fff !important;
}
</style>

<?php include '../includes/footer.php'; ?>
