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
    <div class="card-header">Add Cash Book Entry</div>
    <div class="card-body">
      <form method="POST">
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
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
