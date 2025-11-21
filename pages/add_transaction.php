<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Fetch sales transactions (INCOME) with branch and user names
$sales_query = "
    SELECT 
        s.id,
        s.date,
        s.amount,
        s.payment_method,
        s.invoice_no,
        'Sale' AS type,
        CONCAT('Sale Invoice #', s.invoice_no) AS description,
        u.username AS sold_by_name,
        b.name AS branch_name
    FROM sales s
    LEFT JOIN users u ON s.`sold-by` = u.id
    LEFT JOIN branch b ON s.`branch-id` = b.id
    ORDER BY s.date DESC
";

$sales_result = mysqli_query($conn, $sales_query);

// Fetch expenses transactions (EXPENSE) with branch and user names
$expense_query = "
    SELECT 
        e.id,
        e.date,
        e.amount,
        e.category,
        e.description,
        'Expense' AS type,
        u.username AS spent_by_name,
        b.name AS branch_name
    FROM expenses e
    LEFT JOIN users u ON e.`spent-by` = u.id
    LEFT JOIN branch b ON e.`branch-id` = b.id
    ORDER BY e.date DESC
";

$expense_result = mysqli_query($conn, $expense_query);
?>

<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5">
  <div class="card mb-4" style="border-left: 4px solid teal;">
    <div class="card-header">
      <h5 class="mb-0">Business Transactions (Auto-generated)</h5>
    </div>

    <div class="card-body">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Description</th>
            <th>Branch</th>
            <th>Amount</th>
            <th>Handled By</th>
          </tr>
        </thead>
       <tbody>
  <!-- SALES / INCOME -->
  <tr>
    <td colspan="6" class="text-center bg-light"><strong>Income Transactions</strong></td>
  </tr>
  <?php 
  $total_income = 0;
  while ($sale = mysqli_fetch_assoc($sales_result)) { 
      $total_income += $sale['amount'];
  ?>
    <tr>
      <td><?php echo $sale['date']; ?></td>
      <td><span class="badge bg-success">Income</span></td>
      <td><?php echo $sale['description']; ?></td>
      <td><?php echo $sale['branch_name']; ?></td>
      <td><strong><?php echo number_format($sale['amount'], 2); ?></strong></td>
      <td><?php echo $sale['sold_by_name']; ?></td>
    </tr>
  <?php } ?>
  <tr>
    <td colspan="4" class="text-end"><strong>Total Income:</strong></td>
    <td colspan="2"><strong><?php echo number_format($total_income, 2); ?></strong></td>
  </tr>

  <!-- EXPENSES -->
  <tr>
    <td colspan="6" class="text-center bg-light"><strong>Expense Transactions</strong></td>
  </tr>
  <?php 
  $total_expense = 0;
  while ($exp = mysqli_fetch_assoc($expense_result)) { 
      $total_expense += $exp['amount'];
  ?>
    <tr>
      <td><?php echo $exp['date']; ?></td>
      <td><span class="badge bg-danger">Expense</span></td>
      <td><?php echo $exp['category'] . " - " . $exp['description']; ?></td>
      <td><?php echo $exp['branch_name']; ?></td>
      <td><strong>-<?php echo number_format($exp['amount'], 2); ?></strong></td>
      <td><?php echo $exp['spent_by_name']; ?></td>
    </tr>
  <?php } ?>
  <tr>
    <td colspan="4" class="text-end"><strong>Total Expenses:</strong></td>
    <td colspan="2"><strong>-<?php echo number_format($total_expense, 2); ?></strong></td>
  </tr>
</tbody>

      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
