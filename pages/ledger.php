<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "super"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5">
  <div class="card ledger-card mb-4"  style="border-left: 4px solid teal;">
    <div class="card-header">Account Ledger</div>
    <div class="card-body">
      <!-- Select account -->
      <form method="GET" class="mb-4">
        <div class="row g-3">
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
        <a href="accounting.php" class="btn btn-secondary mt-3">← Back</a>
      </form>

      <?php
      if (isset($_GET['account_id'])) {
        $account_id = $_GET['account_id'];
        $sql = "SELECT * FROM transactions 
                WHERE debit_account_id = $account_id OR credit_account_id = $account_id 
                ORDER BY date ASC";
        $result = mysqli_query($conn, $sql);

        echo "<div class='table-responsive'><table class='ledger-table align-middle'>
                <thead>
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

        echo "</tbody></table></div>";
      }
      ?>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
