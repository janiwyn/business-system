<?php
include '../includes/auth.php';
require_role(['admin', 'manager']);
include '../includes/header.php';
include '../includes/db.php';

// Get branch id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Branch not found.");
}
$branch_id = intval($_GET['id']);

// Fetch branch info
$sql = "SELECT * FROM branch WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();

if (!$branch) {
    die("Branch not found.");
}

// Fetch sales
$sales_sql = "SELECT SUM(amount) AS total_sales FROM sales WHERE `branch-id` = ?";
$stmt = $conn->prepare($sales_sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$total_sales = $stmt->get_result()->fetch_assoc()['total_sales'] ?? 0;

// Fetch expenses
$expenses_sql = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE `branch-id` = ?";
$stmt = $conn->prepare($expenses_sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$total_expenses = $stmt->get_result()->fetch_assoc()['total_expenses'] ?? 0;

// Fetch stock left
$stock_sql = "SELECT p.name, s.`quantity-received` - IFNULL(SUM(sales.quantity), 0) AS stock_left
              FROM stock s
              JOIN products p ON s.`product-id` = p.id
              LEFT JOIN sales ON sales.`product-id` = p.id AND sales.`branch-id` = s.`branch-id`
              WHERE s.`branch-id` = ?
              GROUP BY p.id, s.`quantity-received`";
$stmt = $conn->prepare($stock_sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stock_result = $stmt->get_result();

// Fetch workers in this branch
$workers_sql = "SELECT u.id, u.username, u.role, u.phone
                FROM users u
                WHERE u.`branch-id` = ?";
$stmt = $conn->prepare($workers_sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$workers_result = $stmt->get_result();

// Calculate profit
$profit = $total_sales - $total_expenses;
?>

<div class="container mt-5">
    <h2 class="fw-bold">Branch: <?php echo htmlspecialchars($branch['name']); ?></h2>
    <p><strong>Location:</strong> <?php echo htmlspecialchars($branch['location']); ?></p>
    <p><strong>Contact:</strong> <?php echo htmlspecialchars($branch['contact']); ?></p>
    <hr>

    <h4>Financial Overview</h4>
    <p><strong>Total Sales:</strong> <?php echo number_format($total_sales, 2); ?></p>
    <p><strong>Total Expenses:</strong> <?php echo number_format($total_expenses, 2); ?></p>
    <p><strong>Profit:</strong> <?php echo number_format($profit, 2); ?></p>

    <h4 class="mt-4">Stock Left</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Product</th>
                <th>Stock Left</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $stock_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo $row['stock_left']; ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h4 class="mt-4">Workers in this Branch</h4>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Worker Name</th>
                <th>Role</th>
                <th>Phone</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($workers_result->num_rows > 0): ?>
            <?php while($worker = $workers_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $worker['id']; ?></td>
                <td><?php echo htmlspecialchars($worker['username']); ?></td>
                <td><?php echo htmlspecialchars($worker['role']); ?></td>
                <td><?php echo htmlspecialchars($worker['phone']); ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center text-muted">No workers assigned to this branch</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <a href="list_branches.php" class="btn btn-secondary mt-3">← Back to Branches</a>
</div>
