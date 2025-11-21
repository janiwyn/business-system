<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "super"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5">
  <div class="card cash-book-card mb-4" style="border-left: 4px solid teal;">
    <div class="card-header">Three-Column Cash Book</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="cash-book-table align-middle text-center">
          <thead>
            <tr>
              <th rowspan="2">Date</th>
              <th rowspan="2">Particulars</th>
              <th colspan="3">Receipts</th>
              <th colspan="3">Payments</th>
            </tr>
            <tr>
              <th>Cash</th>
              <th>Bank</th>
              <th>Discount</th>
              <th>Cash</th>
              <th>Bank</th>
              <th>Discount</th>
            </tr>
          </thead>
          <tbody>
          <?php
          // Combine all cash-related entries (manual, sales, expenses)
          $entries_sql = "
            SELECT date, particulars, cash, bank, discount, type, 'Manual Entry' AS source
            FROM cash_book
            UNION ALL
            SELECT date, CONCAT('Cash Sale Invoice #', invoice_no) AS particulars, amount AS cash, 0 AS bank, 0 AS discount, 'receipt' AS type, 'Sales' AS source
            FROM sales
            WHERE payment_method='cash'
            UNION ALL
            SELECT date, CONCAT(category, ' - ', description) AS particulars, amount AS cash, 0 AS bank, 0 AS discount, 'payment' AS type, 'Expenses' AS source
            FROM expenses
            ORDER BY date ASC
          ";

          $entries = mysqli_query($conn, $entries_sql);

          $total_cash_receipt = $total_bank_receipt = $total_discount_receipt = 0;
          $total_cash_payment = $total_bank_payment = $total_discount_payment = 0;

          while ($row = mysqli_fetch_assoc($entries)) {
              echo "<tr>
                      <td>{$row['date']}</td>
                      <td>{$row['particulars']}</td>";

              if ($row['type'] == 'receipt') {
                  echo "<td>{$row['cash']}</td>
                        <td>{$row['bank']}</td>
                        <td>{$row['discount']}</td>
                        <td></td><td></td><td></td>";
                  $total_cash_receipt += $row['cash'];
                  $total_bank_receipt += $row['bank'];
                  $total_discount_receipt += $row['discount'];
              } else {
                  echo "<td></td><td></td><td></td>
                        <td>{$row['cash']}</td>
                        <td>{$row['bank']}</td>
                        <td>{$row['discount']}</td>";
                  $total_cash_payment += $row['cash'];
                  $total_bank_payment += $row['bank'];
                  $total_discount_payment += $row['discount'];
              }

              echo "</tr>";
          }

          // Totals
          echo "<tr class='fw-bold table-secondary'>
                  <td colspan='2' class='text-end'>Totals:</td>
                  <td>$total_cash_receipt</td>
                  <td>$total_bank_receipt</td>
                  <td>$total_discount_receipt</td>
                  <td>$total_cash_payment</td>
                  <td>$total_bank_payment</td>
                  <td>$total_discount_payment</td>
                </tr>";

          // Closing balances
          $cash_balance = $total_cash_receipt - $total_cash_payment;
          $bank_balance = $total_bank_receipt - $total_bank_payment;

          echo "<tr class='fw-bold table-info'>
                  <td colspan='2' class='text-end'>Closing Balance:</td>
                  <td colspan='3'>Cash: $cash_balance</td>
                  <td colspan='3'>Bank: $bank_balance</td>
                </tr>";
          ?>
          </tbody>
        </table>
      </div>
      <a href="accounting.php" class="btn btn-secondary mt-3">← Back</a>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
