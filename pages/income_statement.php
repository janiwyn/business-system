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
        <div class="card-header income-header">Income (Sales)</div>
        <div class="card-body">
          <table class="income-table align-middle">
            <thead>
              <tr>
                <th>Sale Description</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $total_income = 0;
              $sales_query = mysqli_query($conn, "SELECT * FROM sales ORDER BY date ASC");
              while ($sale = mysqli_fetch_assoc($sales_query)) {
                  $desc = "Invoice #" . $sale['invoice_no'];
                  $amount = $sale['amount'];
                  $total_income += $amount;
                  echo "<tr>
                          <td>$desc</td>
                          <td class='text-end'>$amount</td>
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
                <th>Expense Description</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $total_expense = 0;
              $expense_query = mysqli_query($conn, "SELECT * FROM expenses ORDER BY date ASC");
              while ($exp = mysqli_fetch_assoc($expense_query)) {
                  $desc = $exp['category'] . " - " . $exp['description'];
                  $amount = $exp['amount'];
                  $total_expense += $amount;
                  echo "<tr>
                          <td>$desc</td>
                          <td class='text-end'>$amount</td>
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
