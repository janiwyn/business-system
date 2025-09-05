<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

$message = "";
$amount = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category   = mysqli_real_escape_string($conn, $_POST['category']);
    $branch_id  = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $amount     = floatval($_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $date       = $_POST['date'];
    $spent_by   = mysqli_real_escape_string($conn, $_POST['spent_by']);

    if (!empty($category) && !empty($amount) && !empty($date)) {
        $sql = "INSERT INTO expenses (category, `branch-id`, amount, description, date, `spent-by`) 
                VALUES ('$category', '$branch_id', $amount, '$description', '$date', '$spent_by')";
        if ($conn->query($sql)) {
            $message = "Expense added successfully.";
        } else {
            $message = "Error: " . $conn->error;
        }

        // Update profits table
        $currentDate = date("Y-m-d");
        $result = $conn->query("SELECT * FROM profits WHERE date='$currentDate'");
        $profit_result = $result->fetch_assoc();

        if ($profit_result) {
            $total_expenses = $profit_result['expenses'] + $amount;
            $net_profit = $profit_result['total'] - $total_expenses;

            $update_sql = "UPDATE profits SET expenses=$total_expenses, `net-profits`=$net_profit 
                           WHERE date='$currentDate'";
            $conn->query($update_sql);
        }
    } else {
        $message = "Please fill in all required fields.";
    }
}

// Fetch expenses with username for spent-by
$expenses = $conn->query("
    SELECT e.*, u.username 
    FROM expenses e 
    LEFT JOIN users u ON e.`spent-by` = u.id 
    ORDER BY e.date DESC
");

// Get total expenses
$total_result = $conn->query("SELECT SUM(amount) AS total_expenses FROM expenses");
$total_data = $total_result->fetch_assoc();
$total_expenses = $total_data['total_expenses'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4 text-center">Company Expenses</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Add Expense Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Expense</div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="category" class="form-label">Category *</label>
                        <input type="text" name="category" id="category" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="branch_id" class="form-label">Branch ID *</label>
                        <input type="text" name="branch_id" id="branch_id" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="amount" class="form-label">Amount *</label>
                        <input type="number" name="amount" step="0.01" id="amount" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="date" class="form-label">Date *</label>
                        <input type="date" name="date" id="date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="spent_by" class="form-label">Spent By *</label>
                        <select name="spent_by" id="spent_by" class="form-control" required>
                            <option value="">-- Select User --</option>
                            <?php
                            $users = $conn->query("SELECT id, username FROM users");
                            while ($u = $users->fetch_assoc()) {
                                echo "<option value='{$u['id']}'>{$u['username']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="1"></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Add Expense</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Total Expenses -->
    <div class="alert alert-secondary">
        <strong>Total Expenses:</strong> UGX <?php echo number_format($total_expenses, 2); ?>
    </div>

    <!-- Expense Table -->
    <div class="card">
        <div class="card-header">All Expenses</div>
        <div class="card-body p-0">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Category</th>
                        <th>Amount (UGX)</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Spent By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($expenses->num_rows > 0): ?>
                        <?php $i = 1; while ($row = $expenses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td><?php echo $row['date']; ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No expenses recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
