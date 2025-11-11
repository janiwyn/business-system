<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5">
  <div class="card trial-balance-card mb-4"  style="border-left: 4px solid teal;">
    <div class="card-header">Trial Balance</div>
    <div class="card-body">
      <table class="trial-balance-table align-middle">
        <thead>
          <tr>
            <th>Account Name</th>
            <th>Debit (Dr)</th>
            <th>Credit (Cr)</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $accounts = mysqli_query($conn, "SELECT * FROM accounts");
        $grand_debit = 0;
        $grand_credit = 0;

        while ($acc = mysqli_fetch_assoc($accounts)) {
          $id = $acc['id'];

          // Sum of debits for this account
          $sql_debit = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE debit_account_id = $id");
          $debit_row = mysqli_fetch_assoc($sql_debit);
          $debit_total = $debit_row['total'] ?? 0;

          // Sum of credits for this account
          $sql_credit = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE credit_account_id = $id");
          $credit_row = mysqli_fetch_assoc($sql_credit);
          $credit_total = $credit_row['total'] ?? 0;

          // Determine final balance
          if ($debit_total > $credit_total) {
            $final_debit = $debit_total - $credit_total;
            $final_credit = 0;
          } else {
            $final_credit = $credit_total - $debit_total;
            $final_debit = 0;
          }

          $grand_debit += $final_debit;
          $grand_credit += $final_credit;

          echo "<tr>
                  <td>{$acc['account_name']}</td>
                  <td>$final_debit</td>
                  <td>$final_credit</td>
                </tr>";
        }

        echo "<tr class='fw-bold table-secondary'>
                <td class='text-end'>Total:</td>
                <td>$grand_debit</td>
                <td>$grand_credit</td>
              </tr>";

        if ($grand_debit == $grand_credit) {
          echo "<tr class='table-success text-center fw-bold'><td colspan='3'>Trial Balance is Balanced ✅</td></tr>";
        } else {
          echo "<tr class='table-danger text-center fw-bold'><td colspan='3'>Trial Balance is NOT Balanced ❌</td></tr>";
        }
        ?>
        </tbody>
      </table>
      <a href="accounting.php" class="btn btn-secondary">← Back</a>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
