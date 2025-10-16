<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';
 ?>

<!DOCTYPE html>
<html>
<head>
  <title>Record Transaction</title>
</head>
<body class="bg-light">

<div class="container mt-5">
  <h2 class="mb-4">Record New Transaction</h2>

  <form method="POST">
    <div class="mb-3">
      <label>Date</label>
      <input type="date" name="date" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Description</label>
      <input type="text" name="description" class="form-control" required>
    </div>

    <div class="row">
      <div class="col-md-6">
        <label>Debit Account</label>
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
        <label>Credit Account</label>
        <select name="credit_account" class="form-select">
          <?php
          $result = mysqli_query($conn, "SELECT * FROM accounts");
          while ($row = mysqli_fetch_assoc($result)) {
            echo "<option value='{$row['id']}'>{$row['account_name']}</option>";
          }
          ?>
        </select>
      </div>
    </div>

    <div class="mt-3">
      <label>Amount</label>
      <input type="number" step="0.01" name="amount" class="form-control" required>
    </div>

    <button type="submit" name="save" class="btn btn-success mt-3">Save Transaction</button>
  </form>
</div>

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

</body>
</html>
