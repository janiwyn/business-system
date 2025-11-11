<?php 
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<div class="container mt-5">
  <div class="card add-account-card mb-4"  style="border-left: 4px solid teal;">
    <div class="card-header">Add New Account</div>
    <div class="card-body">
      <form method="POST">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Account Name</label>
            <input type="text" name="account_name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Account Type</label>
            <select name="type" class="form-select" required>
              <option value="asset">Asset</option>
              <option value="liability">Liability</option>
              <option value="income">Income</option>
              <option value="expense">Expense</option>
              <option value="equity">Equity</option>
            </select>
          </div>
        </div>
        <div class="mt-4 text-end">
          <button type="submit" name="save" class="btn btn-primary">Save Account</button>
          <a href="accounting.php" class="btn btn-secondary">← Back</a>
        </div>
      </form>
      <?php
      if (isset($_POST['save'])) {
        $name = $_POST['account_name'];
        $type = $_POST['type'];
        $sql = "INSERT INTO accounts (account_name, type) VALUES ('$name', '$type')";
        mysqli_query($conn, $sql);
        echo "<script>alert('Account added successfully!');</script>";
      }
      ?>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
