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

                // CASH & BANK from cash_book
                $cash_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(cash) AS total FROM cash_book WHERE type='receipt'"))['total'] ?? 0;
                $bank_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(bank) AS total FROM cash_book WHERE type='receipt'"))['total'] ?? 0;
                $cash_payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(cash) AS total FROM cash_book WHERE type='payment'"))['total'] ?? 0;
                $bank_payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(bank) AS total FROM cash_book WHERE type='payment'"))['total'] ?? 0;

                $cash_balance = $cash_total - $cash_payment;
                $bank_balance = $bank_total - $bank_payment;

                $total_assets += $cash_balance + $bank_balance;

                echo "<tr><td>Cash</td><td class='text-end'>".number_format($cash_balance,2)."</td></tr>";
                echo "<tr><td>Bank</td><td class='text-end'>".number_format($bank_balance,2)."</td></tr>";

                // INVENTORY (total cost of unsold products)
                $inventory = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(`cost-price` * quantity) AS total FROM sales WHERE date >= '0000-00-00'"))['total'] ?? 0;
                $total_assets += $inventory;
                echo "<tr><td>Inventory</td><td class='text-end'>".number_format($inventory,2)."</td></tr>";

                echo "<tr class='fw-bold table-secondary'><td>Total Assets</td><td class='text-end'>".number_format($total_assets,2)."</td></tr>";
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
                // Example: total expenses as liabilities (if unpaid)
                $liabilities = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM expenses"))['total'] ?? 0;
                $total_liabilities += $liabilities;
                echo "<tr><td>Accounts Payable</td><td class='text-end'>".number_format($liabilities,2)."</td></tr>";
                echo "<tr class='fw-bold table-secondary'><td>Total Liabilities</td><td class='text-end'>".number_format($total_liabilities,2)."</td></tr>";
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

                // Retained earnings = Total Income - Total Expenses
                $total_income = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM sales"))['total'] ?? 0;
                $total_expense = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS total FROM expenses"))['total'] ?? 0;
                $retained_earnings = $total_income - $total_expense;

                $total_equity += $retained_earnings;
                echo "<tr><td>Retained Earnings</td><td class='text-end'>".number_format($retained_earnings,2)."</td></tr>";

                echo "<tr class='fw-bold table-secondary'><td>Total Equity</td><td class='text-end'>".number_format($total_equity,2)."</td></tr>";
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
          echo "<h5>Total Assets: <span class='text-success fw-bold'>".number_format($total_assets,2)."</span></h5>";
          echo "<h5>Total Liabilities + Equity: <span class='text-primary fw-bold'>".number_format($total_liabilities_equity,2)."</span></h5>";

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
