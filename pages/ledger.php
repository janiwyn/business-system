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
  <title>Account Ledger</title>
</head>
<body class="bg-light">

<div class="container mt-5">
  <h2 class="mb-4">Account Ledger</h2>

  <!-- Select account -->
  <form method="GET" class="mb-4">
    <div class="row">
      <div class="col-md-8">
        <select name="account_id" class="form-select" required>
          <option value="">Select Account</option>
          <?php
          $accounts = mysqli_query($conn, "SELECT * FROM accounts");
          while ($a = mysqli_fetch_assoc($accounts)) {
            $selected = (isset($_GET['account_id']) && $_GET['account_id'] == $a['id']) ? 'selected' : '';
            echo "<option value='{$a['id']}' $selected>{$a['account_name']} ({$a['type']})</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-primary w-100">View Ledger</button>
      </div>
    </div>
  </form>

  <?php
  if (isset($_GET['account_id'])) {
    $account_id = $_GET['account_id'];

    $sql = "SELECT * FROM transactions 
            WHERE debit_account_id = $account_id OR credit_account_id = $account_id 
            ORDER BY date ASC";
    $result = mysqli_query($conn, $sql);

    echo "<table class='table table-bordered table-striped'>
            <thead class='table-dark'>
              <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Debit</th>
                <th>Credit</th>
              </tr>
            </thead>
            <tbody>";

    $total_debit = 0;
    $total_credit = 0;

    while ($row = mysqli_fetch_assoc($result)) {
      $debit = $row['debit_account_id'] == $account_id ? $row['amount'] : '';
      $credit = $row['credit_account_id'] == $account_id ? $row['amount'] : '';
      
      if ($debit) $total_debit += $row['amount'];
      if ($credit) $total_credit += $row['amount'];

      echo "<tr>
              <td>{$row['date']}</td>
              <td>{$row['description']}</td>
              <td>$debit</td>
              <td>$credit</td>
            </tr>";
    }

    $balance = $total_debit - $total_credit;
    $balance_label = $balance >= 0 ? "Dr" : "Cr";
    $balance = abs($balance);

    echo "<tr class='fw-bold'>
            <td colspan='2' class='text-end'>Totals:</td>
            <td>$total_debit</td>
            <td>$total_credit</td>
          </tr>
          <tr class='table-secondary fw-bold'>
            <td colspan='2' class='text-end'>Balance:</td>
            <td colspan='2'>$balance $balance_label</td>
          </tr>";

    echo "</tbody></table>";
  }
  ?>
</div>

</body>
</html>
