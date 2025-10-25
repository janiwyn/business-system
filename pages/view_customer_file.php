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

              // Prepare grouping by date
              $grouped = [];
              while ($tr = $trs->fetch_assoc()) {
                  $dt = $tr['date_time'] ?? $tr['date'] ?? '';
                  $date_key = date('Y-m-d', strtotime($dt));
                  if (!isset($grouped[$date_key])) $grouped[$date_key] = [];
                  $grouped[$date_key][] = $tr;
              }
              $tstmt->close();

              // Calculate running balance from zero, process oldest to newest
              $balance = 0;
              $credited = 0;
              $rows = [];
              $dates = array_keys($grouped); // chronological order
              foreach ($dates as $date_key) {
                  $topup = null;
                  $deduction = 0;
                  $purchase_deduction = 0;
                  $amount_credited = 0;
                  $served_by = '-';
                  $dt_display = '';
                  $type = [];
                  foreach ($grouped[$date_key] as $tr) {
                      $pb = $tr['products_bought'] ?? '';
                      $served_by = htmlspecialchars($tr['sold_by'] ?? '-');
                      $dt_display = htmlspecialchars($tr['date_time'] ?? $tr['date'] ?? '');

                      if (trim($pb) === 'Account Top-up') {
                          $topup = $tr;
                          $amount_credited += floatval($tr['amount_credited'] ?? 0);
                          $type[] = 'TOP UP';
                      } elseif (trim($pb) === 'Account Deduction') {
                          $deduction += floatval($tr['amount_paid'] ?? 0);
                          $amount_credited += floatval($tr['amount_credited'] ?? 0);
                          $type[] = 'REDUCTION';
                      } else {
                          // Purchases: products_bought is not a string, or is a JSON array
                          // Only count as deduction if amount_paid > 0
                          $purchase_deduction += floatval($tr['amount_paid'] ?? 0);
                          $type[] = 'PURCHASE';
                      }
                  }
                  // Calculate amounts
                  $amount_topup = $topup ? floatval($topup['amount_paid'] ?? 0) : 0;
                  $total_deducted = $deduction + $purchase_deduction;

                  // Type column
                  $type_str = implode(' AND ', array_unique($type));

                  // Update running balance
                  $balance += $amount_topup;
                  $balance -= $total_deducted;
                  $credited -= $amount_credited;

                  $rows[] = [
                      'dt' => $dt_display,
                      'type' => $type_str,
                      'amount_topup' => $amount_topup,
                      'amount_credited' => $amount_credited,
                      'amount_deducted' => $total_deducted,
                      'balance' => $balance,
                      'served_by' => $served_by
                  ];
              }

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

<?php include '../includes/footer.php'; ?>
