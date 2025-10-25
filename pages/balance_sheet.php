<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';
 ?>

<!DOCTYPE html>
<html>
<head>
  <title>Balance Sheet</title>
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5 mb-5">
  <h2 class="text-center mb-4 fw-bold">Balance Sheet</h2>

  <div class="row">
    <!-- ASSETS SECTION -->
    <div class="col-md-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-success text-white fw-bold text-center">Assets</div>
        <div class="card-body">
          <table class="table table-bordered">
            <thead class="table-light">
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
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-danger text-white fw-bold text-center">Liabilities</div>
        <div class="card-body">
          <table class="table table-bordered">
            <thead class="table-light">
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
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-primary text-white fw-bold text-center">Owner’s Equity</div>
        <div class="card-body">
          <table class="table table-bordered">
            <thead class="table-light">
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
  <div class="card shadow-sm mt-4">
    <div class="card-header bg-dark text-white fw-bold text-center">
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
      <a href="accounting.php" class="btn btn-secondary">← Back</a>

</div>

</body>
</html>
