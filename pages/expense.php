<?php
include '../includes/db.php';
include '../includes/header.php';
include '../includes/auth.php';
require_role("manager", "admin");
include '../pages/sidebar.php';
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'];
    $branch_id = $_POST['branch_id'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $spent_by = $_POST['spent_by'];

    if (!empty($category) && !empty($amount) && !empty($date)) {
        $stmt = $conn->prepare("INSERT INTO expenses (category, `branch-id`, amount, description, date, `spent-by`) VALUES (?, ?, ?, ?, ?,?)");
        $stmt->bind_param("sidsss", $category, $branch_id, $amount, $description, $date, $spent_by);
        if ($stmt->execute()) {
            $message = "Expense added successfully.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Please fill in all required fields.";
    }
}

// Fetch expenses
$expenses = $conn->query("SELECT * FROM expenses ORDER BY date DESC");

// Get total
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
                        <label for="branch_id" class="form-label">Branch_id *</label>
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
                        <label for="spent_by" class="form-label">Spent By</label>
                        <input type="text" name="spent_by" id="spent_by" class="form-control">
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
                                <td><?php echo htmlspecialchars($row['spent-by']); ?></td>
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