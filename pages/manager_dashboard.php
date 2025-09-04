<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../pages/sidebar_manager.php';
include '../includes/header.php';

require_role(['manager']);
// $branch_id = $_SESSION['branch-id'] ?? 0;
// if($branch_id == 0){
//     die('No branch assigned to this manager');
// }

// Fetch data
// Total Sales Today
$sales_today = 0;
$stmt = $conn->prepare("SELECT SUM(amount)   FROM sales WHERE `branch-id` = ? AND DATE(`date`) = CURDATE()");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stmt->bind_result($sales_today);
$stmt->fetch();
$stmt->close();

// Total Expenses Today
$expenses_today = 0;
$stmt = $conn->prepare("SELECT SUM(amount) FROM expenses WHERE `branch-id` = ? AND DATE(`date`) = CURDATE()");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stmt->bind_result($expenses_today);
$stmt->fetch();
$stmt->close();

// Total Products
$total_products = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE `branch-id` = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stmt->bind_result($total_products);
$stmt->fetch();
$stmt->close();



// Total Staff
$total_staff = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE `branch-id` = ? AND role = 'staff'");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$stmt->bind_result($total_staff);
$stmt->fetch();
$stmt->close();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
?>

<div class="container my-4">
    <h3 class="mb-4">Welcome, <?= htmlspecialchars($username); ?> </h3>

    <div class="row mb-4">
        <!-- Sales -->
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Sales Today</h5>
                    <p class="card-text">UGX <?php echo number_format($sales_today); ?></p>
                </div>
            </div>
        </div>
        <!-- Expenses -->
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Expenses Today</h5>
                    <p class="card-text">UGX <?php echo number_format($expenses_today); ?></p>
                </div>
            </div>
        </div>
        <!-- Products -->
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Products in Stock</h5>
                    <p class="card-text"><?php echo $total_products; ?> items</p>
                </div>
            </div>
        </div>
        <!-- Staff -->
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Branch Staff</h5>
                    <p class="card-text"><?php echo $total_staff; ?> staff</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card mb-4">
        <div class="card-header">Recent Sales</div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Sold By</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $conn->prepare("
                    SELECT s.date, p.name, s.quantity, s.amount, u.username 
                    FROM sales s 
                    JOIN products p ON s.`product-id` = p.id 
                    JOIN users u ON s.`sold-by` = u.id 
                    WHERE s.`branch-id` = ? 
                    ORDER BY s.date DESC 
                    LIMIT 5
                ");
                $stmt->bind_param("i", $branch_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['date']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['quantity']}</td>
                        <td>UGX ".number_format($row['amount'])."</td>
                        <td>{$row['username']}</td>
                    </tr>";
                }
                $stmt->close();
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card mb-4">
        <div class="card-header">Recent Expenses</div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Spent By</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $conn->prepare("
                    SELECT e.date, e.category, e.amount, u.username 
                    FROM expenses e 
                    JOIN users u ON e.`spent-by` = u.id 
                    WHERE e.`branch-id` = ? 
                    ORDER BY e.date DESC 
                    LIMIT 5
                ");
                $stmt->bind_param("i", $branch_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['date']}</td>
                        <td>{$row['category']}</td>
                        <td>UGX ".number_format($row['amount'])."</td>
                        <td>{$row['username']}</td>
                    </tr>";
                }
                $stmt->close();
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Optional: Actions -->
    <div class="d-flex gap-3">
        <a href="sales.php" class="btn btn-success">Add New Sale</a>
        <a href="expense.php" class="btn btn-danger">Add New Expense</a>
        <a href="edit_product.php" class="btn btn-info">Manage Products</a>
     <a href="report.php" class="btn btn-secondary">Generate Report</a>
    </div>

</div>

<?php
    include '../includes/footer.php';
?>