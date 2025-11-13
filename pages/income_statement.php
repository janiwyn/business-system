<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "super"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5 mb-5">
  <h2 class="page-title mb-4 text-center">Income Statement (Profit & Loss)</h2>
  <div class="row">
    <!-- Income Section -->
    <div class="col-md-6">
      <div class="card income-card mb-4"  style="border-left: 4px solid teal;">
        <div class="card-header income-header">Income</div>
        <div class="card-body">
          <table class="income-table align-middle">
            <thead>
              <tr>
                <th>Account</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $total_income = 0;

              $income_accounts = mysqli_query($conn, "SELECT * FROM accounts WHERE type='income'");
              while ($acc = mysqli_fetch_assoc($income_accounts)) {
                $id = $acc['id'];

                // Calculate total credits (income usually has credits)
                $credit_query = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE credit_account_id = $id");
                $credit_row = mysqli_fetch_assoc($credit_query);
                $credit_total = $credit_row['total'] ?? 0;

                // Subtract any debits to get net income
                $debit_query = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE debit_account_id = $id");
                $debit_row = mysqli_fetch_assoc($debit_query);
                $debit_total = $debit_row['total'] ?? 0;

                $income = $credit_total - $debit_total;
                $total_income += $income;

                echo "<tr>
                        <td>{$acc['account_name']}</td>
                        <td class='text-end'>$income</td>
                      </tr>";
              }

              echo "<tr class='fw-bold table-secondary'>
                      <td>Total Income</td>
                      <td class='text-end'>$total_income</td>
                    </tr>";
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!-- Expenses Section -->
    <div class="col-md-6">
      <div class="card expense-card mb-4"  style="border-left: 4px solid teal;">
        <div class="card-header expense-header">Expenses</div>
        <div class="card-body">
          <table class="expense-table align-middle">
            <thead>
              <tr>
                <th>Account</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $total_expense = 0;

              $expense_accounts = mysqli_query($conn, "SELECT * FROM accounts WHERE type='expense'");
              while ($acc = mysqli_fetch_assoc($expense_accounts)) {
                $id = $acc['id'];

                // Expenses usually have debits
                $debit_query = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE debit_account_id = $id");
                $debit_row = mysqli_fetch_assoc($debit_query);
                $debit_total = $debit_row['total'] ?? 0;

                // Subtract credits
                $credit_query = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE credit_account_id = $id");
                $credit_row = mysqli_fetch_assoc($credit_query);
                $credit_total = $credit_row['total'] ?? 0;

                $expense = $debit_total - $credit_total;
                $total_expense += $expense;

                echo "<tr>
                        <td>{$acc['account_name']}</td>
                        <td class='text-end'>$expense</td>
                      </tr>";
              }

              echo "<tr class='fw-bold table-secondary'>
                      <td>Total Expenses</td>
                      <td class='text-end'>$total_expense</td>
                    </tr>";
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <!-- Net Profit / Loss -->
  <div class="card net-result-card shadow-sm mt-4 mb-4"  style="border-left: 4px solid teal;">
    <div class="card-header net-result-header text-center">
      Net Result
    </div>
    <div class="card-body text-center">
      <?php
      $net_profit = $total_income - $total_expense;
      if ($net_profit > 0) {
        echo "<h4 class='text-success fw-bold'>Net Profit: $net_profit</h4>";
      } elseif ($net_profit < 0) {
        echo "<h4 class='text-danger fw-bold'>Net Loss: " . abs($net_profit) . "</h4>";
      } else {
        echo "<h4 class='text-secondary fw-bold'>No Profit, No Loss (Balanced)</h4>";
      }
      ?>
    </div>
  </div>
  <div class="text-end">
    <a href="accounting.php" class="btn btn-secondary">← Back</a>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
