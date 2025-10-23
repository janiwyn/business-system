<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "super"]);
include '../pages/sidebar.php';
include '../includes/header.php';
 ?>

<!DOCTYPE html>
<html>
<head>
  <title>Add Cash Book Entry</title>
</head>
<body class="bg-light">

<div class="container mt-5">
  <h2 class="mb-4">Add Cash Book Entry</h2>

  <form method="POST">
    <div class="row">
      <div class="col-md-4 mb-3">
        <label>Date</label>
        <input type="date" name="date" class="form-control" required>
      </div>
      <div class="col-md-4 mb-3">
        <label>Type</label>
        <select name="type" class="form-select" required>
          <option value="receipt">Receipt</option>
          <option value="payment">Payment</option>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label>Particulars</label>
        <input type="text" name="particulars" class="form-control" required>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4 mb-3">
        <label>Cash Amount</label>
        <input type="number" step="0.01" name="cash" class="form-control">
      </div>
      <div class="col-md-4 mb-3">
        <label>Bank Amount</label>
        <input type="number" step="0.01" name="bank" class="form-control">
      </div>
      <div class="col-md-4 mb-3">
        <label>Discount</label>
        <input type="number" step="0.01" name="discount" class="form-control">
      </div>
    </div>

    <button type="submit" name="save" class="btn btn-success">Save Entry</button>
    <a href="accounting.php" class="btn btn-secondary">← Back</a>
  </form>
</div>

<?php
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
?>

</body>
</html>
