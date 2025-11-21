<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "super"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5">
  <div class="card add-cash-entry-card mb-4"  style="border-left: 4px solid teal;">
    <div class="card-header">Cash Book</div>
    <div class="card-body">

      <!-- Manual Entry Form -->
      <form method="POST" class="mb-4">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="type" class="form-select" required>
              <option value="receipt">Receipt</option>
              <option value="payment">Payment</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Particulars</label>
            <input type="text" name="particulars" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cash Amount</label>
            <input type="number" step="0.01" name="cash" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Bank Amount</label>
            <input type="number" step="0.01" name="bank" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Discount</label>
            <input type="number" step="0.01" name="discount" class="form-control">
          </div>
        </div>
        <div class="mt-4 text-end">
          <button type="submit" name="save" class="btn btn-primary">Save Entry</button>
          <a href="accounting.php" class="btn btn-secondary">← Back</a>
        </div>
      </form>

      <?php
      // Save manual cash entry
      if (isset($_POST['save'])) {
        $date = $_POST['date'];
        $type = $_POST['type'];
        $particulars = $_POST['particulars'];
        $cash = $_POST['cash'] ?: 0;
        $bank = $_POST['bank'] ?: 0;
        $discount = $_POST['discount'] ?: 0;
        $sql = "INSERT INTO cash_book (date, particulars, cash, bank, discount, type)
                VALUES ('$date', '$particulars', '$cash', '$bank', '$discount', '$type')";
        mysqli_query($conn, $sql);
        echo "<script>alert('Cash book entry added successfully!');</script>";
      }

      // Fetch all cash-related entries dynamically
      $cash_entries_sql = "
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

      $cash_entries = mysqli_query($conn, $cash_entries_sql);
      ?>

      <!-- Display Cash Book Table -->
      <div class="table-responsive">
        <table class="table table-bordered table-striped mt-3">
          <thead>
            <tr>
              <th>Date</th>
              <th>Particulars</th>
              <th>Cash</th>
              <th>Bank</th>
              <th>Discount</th>
              <th>Type</th>
              <th>Source</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $total_cash = 0;
            $total_bank = 0;
            $total_discount = 0;

            while ($row = mysqli_fetch_assoc($cash_entries)) {
              $cash = $row['cash'] ? number_format($row['cash'],2) : '';
              $bank = $row['bank'] ? number_format($row['bank'],2) : '';
              $discount = $row['discount'] ? number_format($row['discount'],2) : '';

              $total_cash += $row['cash'];
              $total_bank += $row['bank'];
              $total_discount += $row['discount'];

              echo "<tr>
                      <td>{$row['date']}</td>
                      <td>{$row['particulars']}</td>
                      <td>$cash</td>
                      <td>$bank</td>
                      <td>$discount</td>
                      <td>{$row['type']}</td>
                      <td>{$row['source']}</td>
                    </tr>";
            }

            echo "<tr class='fw-bold table-secondary'>
                    <td colspan='2' class='text-end'>Totals:</td>
                    <td>".number_format($total_cash,2)."</td>
                    <td>".number_format($total_bank,2)."</td>
                    <td>".number_format($total_discount,2)."</td>
                    <td colspan='2'></td>
                  </tr>";
            ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
