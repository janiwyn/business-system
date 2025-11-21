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

        // Fetch account name
        $acc_res = mysqli_query($conn, "SELECT account_name FROM accounts WHERE id = $account_id");
        $account = mysqli_fetch_assoc($acc_res);
        echo "<h5 class='mb-3'>Ledger for: <strong>{$account['account_name']}</strong></h5>";

        // Dynamic ledger query combining sales, expenses, and manual transactions
        $ledger_sql = "
          SELECT date, description,
                 CASE WHEN type = 'Income' THEN amount ELSE NULL END AS debit,
                 CASE WHEN type = 'Expense' THEN amount ELSE NULL END AS credit
          FROM (
            SELECT s.date, CONCAT('Sale Invoice #', s.invoice_no) AS description, s.amount, 'Income' AS type
            FROM sales s
            WHERE s.`branch-id` = $account_id

            UNION ALL

            SELECT e.date, CONCAT(e.category, ' - ', e.description) AS description, e.amount, 'Expense' AS type
            FROM expenses e
            WHERE e.`branch-id` = $account_id

            UNION ALL

            SELECT t.date, t.description,
                   CASE WHEN t.debit_account_id = $account_id THEN t.amount ELSE NULL END AS debit,
                   CASE WHEN t.credit_account_id = $account_id THEN t.amount ELSE NULL END AS credit
            FROM transactions t
            WHERE t.debit_account_id = $account_id OR t.credit_account_id = $account_id
          ) AS ledger_data
          ORDER BY date ASC
        ";

        $ledger_result = mysqli_query($conn, $ledger_sql);

        // Display ledger table
        echo "<div class='table-responsive'><table class='ledger-table align-middle table table-bordered'>
                <thead class='table-light'>
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

        while ($row = mysqli_fetch_assoc($ledger_result)) {
          $debit = $row['debit'] ? number_format($row['debit'], 2) : '';
          $credit = $row['credit'] ? number_format($row['credit'], 2) : '';

          if ($row['debit']) $total_debit += $row['debit'];
          if ($row['credit']) $total_credit += $row['credit'];

          echo "<tr>
                  <td>{$row['date']}</td>
                  <td>{$row['description']}</td>
                  <td>$debit</td>
                  <td>$credit</td>
                </tr>";
        }

        $balance = $total_debit - $total_credit;
        $balance_label = $balance >= 0 ? "Dr" : "Cr";
        $balance = number_format(abs($balance), 2);

        echo "<tr class='fw-bold'>
                <td colspan='2' class='text-end'>Totals:</td>
                <td>".number_format($total_debit, 2)."</td>
                <td>".number_format($total_credit, 2)."</td>
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
