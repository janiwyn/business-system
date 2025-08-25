<?php
include '../includes/db.php';
include '../includes/header.php';
include '../includes/auth.php';
require_role("manager", "admin");
include '../pages/sidebar.php';

// Branch ID can be passed via GET
$branch_id = isset($_GET['id']) ? $_GET['id'] : 1;

// Get Branch Info
$branch_stmt = $conn->prepare("SELECT * FROM branch WHERE id = ?");
$branch_stmt->bind_param("i", $branch_id);
$branch_stmt->execute();
$branch = $branch_stmt->get_result()->fetch_assoc();
?>

<div class="container mt-5">

<?php if (!$branch): ?>
    <div class='alert alert-warning'>No branches have been created yet. Please add a branch below.</div>

    <!-- Add Branch Form -->
    <div class="card">
        <div class="card-header">Add New Branch</div>
        <div class="card-body">
            <form method="POST" action="create_branch.php">
                <div class="mb-3">
                    <label for="name" class="form-label">Branch Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" class="form-control" id="location" name="location" required>
                </div>
                <div class="mb-3">
                    <label for="contact" class="form-label">Contact</label>
                    <input type="text" class="form-control" id="contact" name="contact" required>
                </div>
                <button type="submit" class="btn btn-primary">Create Branch</button>
            </form>
        </div>
    </div>

<?php else:

    // Continue loading the dashboard only if branch exists

    // Get Staff
    $staff_result = $conn->query("SELECT * FROM users WHERE `branch-id` = $branch_id");

    // Get Inventory Summary
    $branch_id = intval($branch_id);
    $sql = "SELECT COUNT(*) AS total_products, COALESCE(SUM(stock), 0) AS stock FROM products WHERE `branch-id` = $branch_id";
    $inventory_result = $conn->query($sql);
    $inventory = $inventory_result->fetch_assoc();

    // Get Sales Summary
    $sales_result = $conn->query("SELECT COUNT(*) AS total_sales, SUM(amount) AS revenue FROM sales WHERE `branch-id` = $branch_id");
    $sales = $sales_result->fetch_assoc();

    // Get Expenses
    $expense_result = $conn->query("SELECT SUM(amount) AS total_expense FROM expenses WHERE `branch-id` = $branch_id");
    $expenses = $expense_result->fetch_assoc();

    // Profit
    $profit = ($sales['revenue'] ?? 0) - ($expenses['total_expense'] ?? 0); 

    // Top Selling Products
    $top_products_result = $conn->query("
        SELECT p.name, SUM(s.quantity) AS total_sold 
        FROM sales s 
        JOIN products p ON s.`product-id` = p.id 
        WHERE s.`branch-id` = $branch_id 
        GROUP BY s.`product-id` 
        ORDER BY total_sold DESC 
        LIMIT 5
    ");
?>

    <h2 class="mb-4">Branch Dashboard - <?= $branch['name'] ?></h2>

    <!-- Branch Information -->
    <div class="card mb-4">
        <div class="card-header">Branch Information</div>
        <div class="card-body">
            <p><strong>Name:</strong> <?= $branch['name'] ?></p>
            <p><strong>Location:</strong> <?= $branch['location'] ?></p>
            <p><strong>Contact:</strong> <?= $branch['contact'] ?></p>
        </div>
    </div>

    <!-- Summaries -->
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Inventory Summary</h5>
                    <p class="card-text">Products: <?= $inventory['total_products'] ?? 0 ?> <br> Items in Stock: <?= $inventory['stock'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">Sales Summary</h5>
                    <p class="card-text">Sales: <?= $sales['total_sales'] ?> <br> Revenue: UGX <?= number_format($sales['revenue'], 2) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-dark mb-3">
                <div class="card-body">
                    <h5 class="card-title">Profit Analysis</h5>
                    <p class="card-text">Expenses: UGX <?= number_format($expenses['total_expense'], 2) ?> <br> Net Profit: UGX <?= number_format($profit, 2) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="card mb-4">
        <div class="card-header">Top Selling Products</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $top_products_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['name'] ?></td>
                            <td><?= $row['total_sold'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Staff -->
    <div class="card mb-4">
        <div class="card-header">Branch Staff</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($staff = $staff_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $staff['name'] ?></td>
                            <td><?= $staff['username'] ?></td>
                            <td><?= ucfirst($staff['role']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Charts -->
    <div class="card mb-5">
        <div class="card-header">Sales Chart</div>
        <div class="card-body">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

<?php endif; ?>
</div>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if ($branch): ?>
<script>
    const ctx = document.getElementById('salesChart');
    const salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php
                $top_products_result->data_seek(0); 
                while ($row = $top_products_result->fetch_assoc()) {
                    echo "'{$row['name']}',";
                }
            ?>],
            datasets: [{
                label: 'Quantity Sold',
                data: [<?php
                    $top_products_result->data_seek(0); 
                    while ($row = $top_products_result->fetch_assoc()) {
                        echo "{$row['total_sold']},";
                    }
                ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        }
    });
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
