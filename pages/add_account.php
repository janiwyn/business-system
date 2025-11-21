<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["manager", "admin"]);

// Handle form submission
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['account_name']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);

    // Check if account already exists
    $check = mysqli_query($conn, "SELECT * FROM accounts WHERE account_name='$name'");
    
    if (mysqli_num_rows($check) > 0) {
        $message = "<div class='alert alert-danger'>Account already exists!</div>";
    } else {
        $query = "INSERT INTO accounts (account_name, type) VALUES ('$name', '$type')";
        if (mysqli_query($conn, $query)) {
            $message = "<div class='alert alert-success'>Account added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to add account. Try again.</div>";
        }
    }
}

// Fetch accounts dynamically
$accounts = mysqli_query($conn, "SELECT * FROM accounts ORDER BY id DESC");

// Sidebar
if ($_SESSION['role'] === 'manager' || $_SESSION['role'] === 'admin') {
    include '../pages/sidebar.php';
} else {
    include '../pages/sidebar_staff.php';
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <h3 class="mb-4">Add New Account</h3>

    <?php echo $message; ?>

    <!-- Add Account Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Account Name</label>
                    <input type="text" name="account_name" class="form-control" placeholder="Enter account name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Account Type</label>
                    <select name="type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="expense">Expense</option>
                        <option value="incomes">Income</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Add Account</button>
            </form>
        </div>
    </div>

    <!-- Show All Accounts Dynamically -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">All Accounts</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Account Name</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($accounts)) { ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['account_name']; ?></td>
                            <td class="text-capitalize"><?php echo $row['type']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
