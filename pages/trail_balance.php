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
      <table class="trial-balance-table align-middle table table-bordered">
        <thead class="table-light">
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

          // Sum debits from manual transactions
          $sql_debit_trans = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE debit_account_id = $id");
          $debit_trans_row = mysqli_fetch_assoc($sql_debit_trans);
          $debit_trans_total = $debit_trans_row['total'] ?? 0;

          // Sum credits from manual transactions
          $sql_credit_trans = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE credit_account_id = $id");
          $credit_trans_row = mysqli_fetch_assoc($sql_credit_trans);
          $credit_trans_total = $credit_trans_row['total'] ?? 0;

          // Sum sales related to this account (if income account)
          $sql_sales = mysqli_query($conn, "
            SELECT SUM(amount) as total FROM sales s
            JOIN accounts a ON a.id = $id
            WHERE a.type='income'
          ");
          $sales_row = mysqli_fetch_assoc($sql_sales);
          $sales_total = $sales_row['total'] ?? 0;

          // Sum expenses related to this account (if expense account)
          $sql_expenses = mysqli_query($conn, "
            SELECT SUM(amount) as total FROM expenses e
            JOIN accounts a ON a.id = $id
            WHERE a.type='expense'
          ");
          $expense_row = mysqli_fetch_assoc($sql_expenses);
          $expense_total = $expense_row['total'] ?? 0;

          // Calculate final debit and credit
          if ($acc['type'] == 'asset' || $acc['type'] == 'expense') {
            $final_debit = $debit_trans_total + $expense_total;
            $final_credit = $credit_trans_total + $sales_total;
          } else {
            // liabilities, income, equity
            $final_debit = $debit_trans_total + $expense_total;
            $final_credit = $credit_trans_total + $sales_total;
          }

          $grand_debit += $final_debit;
          $grand_credit += $final_credit;

          echo "<tr>
                  <td>{$acc['account_name']}</td>
                  <td>".number_format($final_debit,2)."</td>
                  <td>".number_format($final_credit,2)."</td>
                </tr>";
        }

        echo "<tr class='fw-bold table-secondary'>
                <td class='text-end'>Total:</td>
                <td>".number_format($grand_debit,2)."</td>
                <td>".number_format($grand_credit,2)."</td>
              </tr>";

        if ($grand_debit == $grand_credit) {
          echo "<tr class='table-success text-center fw-bold'><td colspan='3'>Trial Balance is Balanced ✅</td></tr>";
        } else {
          echo "<tr class='table-danger text-center fw-bold'><td colspan='3'>Trial Balance is NOT Balanced ❌</td></tr>";
        }
        ?>
        </tbody>
      </table>
      <a href="accounting.php" class="btn btn-secondary mt-3">← Back</a>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
