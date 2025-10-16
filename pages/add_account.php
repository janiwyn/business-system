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
  <title>Add Account</title>
</head>
<body class="bg-light">

<div class="container mt-5">
  <h2 class="mb-4">Add New Account</h2>

  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Account Name</label>
      <input type="text" name="account_name" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Account Type</label>
      <select name="type" class="form-select" required>
        <option value="asset">Asset</option>
        <option value="liability">Liability</option>
        <option value="income">Income</option>
        <option value="expense">Expense</option>
        <option value="equity">Equity</option>
      </select>
    </div>

    <button type="submit" name="save" class="btn btn-primary">Save Account</button>
  </form>
</div>

<?php
if (isset($_POST['save'])) {
  $name = $_POST['account_name'];
  $type = $_POST['type'];

  $sql = "INSERT INTO accounts (account_name, type) VALUES ('$name', '$type')";
  mysqli_query($conn, $sql);

  echo "<script>alert('Account added successfully!');</script>";
}
?>

</body>
</html>
