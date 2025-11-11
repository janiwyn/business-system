<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5 mb-5">
  <div class="card balance-sheet-card mb-4"  style="border-left: 4px solid teal;">
    <div class="card-header">Balance Sheet</div>
    <div class="card-body">
      <div class="row">
        <!-- ASSETS SECTION -->
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header asset-header text-center">Assets</div>
            <div class="card-body">
              <table class="balance-sheet-table align-middle">
                <thead>
                  <tr>
                    <th>Account</th>
                    <th class="text-end">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $total_assets = 0;
                  $assets = mysqli_query($conn, "SELECT * FROM accounts WHERE type='asset'");
                  while ($acc = mysqli_fetch_assoc($assets)) {
                    $id = $acc['id'];

                    // Assets increase with debit, decrease with credit
                    $debits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM transactions WHERE debit_account_id = $id"))['total'] ?? 0;
                    $credits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM transactions WHERE credit_account_id = $id"))['total'] ?? 0;

                    $balance = $debits - $credits;
                    $total_assets += $balance;

                    echo "<tr>
                            <td>{$acc['account_name']}</td>
                            <td class='text-end'>" . number_format($balance, 2) . "</td>
                          </tr>";
                  }
                  echo "<tr class='fw-bold table-secondary'>
                          <td>Total Assets</td>
                          <td class='text-end'>" . number_format($total_assets, 2) . "</td>
                        </tr>";
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- LIABILITIES SECTION -->
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header liability-header text-center">Liabilities</div>
            <div class="card-body">
              <table class="balance-sheet-table align-middle">
                <thead>
                  <tr>
                    <th>Account</th>
                    <th class="text-end">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $total_liabilities = 0;
                  $liabilities = mysqli_query($conn, "SELECT * FROM accounts WHERE type='liability'");
                  while ($acc = mysqli_fetch_assoc($liabilities)) {
                    $id = $acc['id'];

                    // Liabilities increase with credit, decrease with debit
                    $credits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM transactions WHERE credit_account_id = $id"))['total'] ?? 0;
                    $debits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM transactions WHERE debit_account_id = $id"))['total'] ?? 0;

                    $balance = $credits - $debits;
                    $total_liabilities += $balance;

                    echo "<tr>
                            <td>{$acc['account_name']}</td>
                            <td class='text-end'>" . number_format($balance, 2) . "</td>
                          </tr>";
                  }
                  echo "<tr class='fw-bold table-secondary'>
                          <td>Total Liabilities</td>
                          <td class='text-end'>" . number_format($total_liabilities, 2) . "</td>
                        </tr>";
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- EQUITY SECTION -->
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header equity-header text-center">Owner’s Equity</div>
            <div class="card-body">
              <table class="balance-sheet-table align-middle">
                <thead>
                  <tr>
                    <th>Account</th>
                    <th class="text-end">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $total_equity = 0;
                  $equity = mysqli_query($conn, "SELECT * FROM accounts WHERE type='equity'");
                  while ($acc = mysqli_fetch_assoc($equity)) {
                    $id = $acc['id'];

                    // Equity increases with credit, decreases with debit
                    $credits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM transactions WHERE credit_account_id = $id"))['total'] ?? 0;
                    $debits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM transactions WHERE debit_account_id = $id"))['total'] ?? 0;

                    $balance = $credits - $debits;
                    $total_equity += $balance;

                    echo "<tr>
                            <td>{$acc['account_name']}</td>
                            <td class='text-end'>" . number_format($balance, 2) . "</td>
                          </tr>";
                  }
                  echo "<tr class='fw-bold table-secondary'>
                          <td>Total Equity</td>
                          <td class='text-end'>" . number_format($total_equity, 2) . "</td>
                        </tr>";
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <!-- FINAL BALANCE CHECK -->
      <div class="card summary-card shadow-sm mt-4">
        <div class="card-header summary-header text-center">
          Balance Sheet Summary
        </div>
        <div class="card-body text-center">
          <?php
          $total_liabilities_equity = $total_liabilities + $total_equity;
          echo "<h5>Total Assets: <span class='text-success fw-bold'>" . number_format($total_assets, 2) . "</span></h5>";
          echo "<h5>Total Liabilities + Equity: <span class='text-primary fw-bold'>" . number_format($total_liabilities_equity, 2) . "</span></h5>";

          if (abs($total_assets - $total_liabilities_equity) < 0.01) {
            echo "<h4 class='text-success fw-bold mt-3'>✅ Balanced</h4>";
          } else {
            echo "<h4 class='text-danger fw-bold mt-3'>⚠️ Not Balanced!</h4>";
          }
          ?>
        </div>
      </div>
      <div class="text-end">
        <a href="accounting.php" class="btn btn-secondary">← Back</a>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
