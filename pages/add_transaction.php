<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5">
  <div class="card add-transaction-card mb-4">
    <div class="card-header">Record New Transaction</div>
    <div class="card-body">
      <form method="POST">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Debit Account</label>
            <select name="debit_account" class="form-select">
              <?php
              $result = mysqli_query($conn, "SELECT * FROM accounts");
              while ($row = mysqli_fetch_assoc($result)) {
                echo "<option value='{$row['id']}'>{$row['account_name']}</option>";
              }
              ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Credit Account</label>
            <select name="credit_account" class="form-select">
              <?php
              $result = mysqli_query($conn, "SELECT * FROM accounts");
              while ($row = mysqli_fetch_assoc($result)) {
                echo "<option value='{$row['id']}'>{$row['account_name']}</option>";
              }
              ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control" required>
          </div>
        </div>
        <div class="mt-4 text-end">
          <button type="submit" name="save" class="btn btn-primary">Save Transaction</button>
          <a href="accounting.php" class="btn btn-secondary">← Back</a>
        </div>
      </form>
      <?php
      if (isset($_POST['save'])) {
        $date = $_POST['date'];
        $desc = $_POST['description'];
        $debit = $_POST['debit_account'];
        $credit = $_POST['credit_account'];
        $amount = $_POST['amount'];
        $sql = "INSERT INTO transactions (date, description, debit_account_id, credit_account_id, amount)
                VALUES ('$date', '$desc', '$debit', '$credit', '$amount')";
        mysqli_query($conn, $sql);
        echo "<script>alert('Transaction recorded successfully!');</script>";
      }
      ?>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
