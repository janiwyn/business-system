<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Branch ID can be passed via GET; use intval for safety
$branch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch all branches (grab all columns so we can handle different column names safely)
$all_branches = $conn->query("SELECT * FROM branch ORDER BY id ASC");

// Get Branch Info if id provided
$branch = null;
if ($branch_id > 0) {
    $branch_stmt = $conn->prepare("SELECT * FROM branch WHERE id = ?");
    $branch_stmt->bind_param("i", $branch_id);
    $branch_stmt->execute();
    $branch = $branch_stmt->get_result()->fetch_assoc();
    $branch_stmt->close();
}

// Helper to get a display name for a branch row (handles different column names)
function branch_display_name($row) {
    if (!is_array($row)) return '';
    return $row['name'] ?? $row['branch-name'] ?? $row['branch_name'] ?? $row['branchName'] ?? $row['branch'] ?? ("Branch " . ($row['id'] ?? ''));
}

// Helper to get staff display name (some user tables use 'name', others 'username')
function staff_display_name($userRow) {
    if (!is_array($userRow)) return '';
    return $userRow['name'] ?? $userRow['full_name'] ?? $userRow['username'] ?? $userRow['email'] ?? 'Staff';
}
?>

<div class="container mt-5">

    <!-- Branch Selector Dropdown -->
    <div class="d-flex justify-content-end mb-3">
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="branchDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if ($branch): ?>
                    <?= "Viewing: " . htmlspecialchars(branch_display_name($branch)) ?>
                <?php else: ?>
                    Select Branch to View
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu" aria-labelledby="branchDropdown">
                <?php if ($all_branches && $all_branches->num_rows > 0): ?>
                    <?php while ($row = $all_branches->fetch_assoc()): 
                        $display = branch_display_name($row);
                        $id = intval($row['id'] ?? 0);
                    ?>
                        <li>
                            <a class="dropdown-item <?= ($id === $branch_id) ? 'active' : '' ?>" 
                               href="branch.php?id=<?= $id ?>">
                               <?= htmlspecialchars($display) ?>
                            </a>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li><span class="dropdown-item text-muted">No branches</span></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

<?php if (!$branch): ?>
    <div class='alert alert-warning'>No branches have been created yet. Please add a branch below.</div>

    <!-- Add Branch Form -->
    <div class="card shadow-sm rounded">
        <div class="card-header bg-primary text-white fw-bold">Add New Branch</div>
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

    // Ensure branch_id is an integer
    $branch_id = intval($branch_id);

    // Staff assigned to this branch
    $staff_result = $conn->query("SELECT * FROM users WHERE `branch-id` = $branch_id");

    // Inventory summary
    $inventory_result = $conn->query("SELECT COUNT(*) AS total_products, COALESCE(SUM(stock), 0) AS stock FROM products WHERE `branch-id` = $branch_id");
    $inventory = $inventory_result ? $inventory_result->fetch_assoc() : ['total_products' => 0, 'stock' => 0];

    // Sales summary
    $sales_result = $conn->query("SELECT COUNT(*) AS total_sales, COALESCE(SUM(amount),0) AS revenue FROM sales WHERE `branch-id` = $branch_id");
    $sales = $sales_result ? $sales_result->fetch_assoc() : ['total_sales' => 0, 'revenue' => 0];

    // Expenses
    $expense_result = $conn->query("SELECT COALESCE(SUM(amount),0) AS total_expense FROM expenses WHERE `branch-id` = $branch_id");
    $expenses = $expense_result ? $expense_result->fetch_assoc() : ['total_expense' => 0];

    $profit = ($sales['revenue'] ?? 0) - ($expenses['total_expense'] ?? 0);

    // Top products
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

    <h2 class="mb-4 text-center fw-bold"><?= htmlspecialchars(branch_display_name($branch)) ?> - Branch Dashboard</h2>

    <!-- Branch Information -->
    <div class="card mb-4 shadow-sm rounded border-0">
        <div class="card-header bg-gradient-primary text-white fw-bold d-flex align-items-center">
            <i class="bi bi-building me-2"></i> Branch Information
        </div>
        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars(branch_display_name($branch)) ?></p>
            <p><strong>Location:</strong> <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= htmlspecialchars($branch['location'] ?? '') ?></p>
            <p><strong>Contact:</strong> <i class="bi bi-telephone-fill text-success me-1"></i> <?= htmlspecialchars($branch['contact'] ?? '') ?></p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card text-white shadow-sm rounded border-0 bg-gradient-primary h-100 hover-scale">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-box-seam fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Inventory Summary</h5>
                        <p class="card-text mb-0">Products: <?= intval($inventory['total_products'] ?? 0) ?></p>
                        <p class="card-text">Items in Stock: <?= intval($inventory['stock'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white shadow-sm rounded border-0 bg-gradient-success h-100 hover-scale">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-currency-dollar fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Sales Summary</h5>
                        <p class="card-text mb-0">Sales: <?= intval($sales['total_sales'] ?? 0) ?></p>
                        <p class="card-text">Revenue: UGX <?= number_format(floatval($sales['revenue'] ?? 0), 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white shadow-sm rounded border-0 bg-gradient-dark h-100 hover-scale">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-graph-up-arrow fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Profit Analysis</h5>
                        <p class="card-text mb-0">Expenses: UGX <?= number_format(floatval($expenses['total_expense'] ?? 0), 2) ?></p>
                        <p class="card-text">Net Profit: UGX <?= number_format(floatval($profit ?? 0), 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="card mb-4 shadow-sm rounded border-0">
        <div class="card-header bg-gradient-secondary text-white fw-bold">Top Selling Products</div>
        <div class="card-body table-responsive shadow-sm rounded">
            <table class="table table-striped table-hover rounded">
                <thead class="table-dark rounded">
                    <tr>
                        <th>Product</th>
                        <th>Quantity Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($top_products_result && $top_products_result->num_rows > 0): ?>
                        <?php while($row = $top_products_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name'] ?? '—') ?></td>
                                <td><?= intval($row['total_sold'] ?? 0) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-center text-muted">No product sales yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Staff -->
    <div class="card mb-4 shadow-sm rounded border-0">
        <div class="card-header bg-gradient-info text-white fw-bold">Branch Staff</div>
        <div class="card-body table-responsive shadow-sm rounded">
            <table class="table table-striped table-hover rounded">
                <thead class="table-dark rounded">
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staff_result && $staff_result->num_rows > 0): ?>
                        <?php while($staff = $staff_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars(staff_display_name($staff)) ?></td>
                                <td><?= htmlspecialchars($staff['username'] ?? '') ?></td>
                                <td><?= htmlspecialchars(ucfirst($staff['role'] ?? '')) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center text-muted">No staff assigned to this branch.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Charts -->
    <div class="card mb-5 shadow-sm rounded border-0">
        <div class="card-header bg-gradient-warning text-white fw-bold">Sales Chart</div>
        <div class="card-body p-4 shadow-sm rounded">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

<?php endif; ?>
</div>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if ($branch && isset($top_products_result) && $top_products_result): ?>
<script>
    const ctx = document.getElementById('salesChart');
    const salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php
                // collect labels safely
                $labels = [];
                if ($top_products_result) {
                    $top_products_result->data_seek(0);
                    while ($r = $top_products_result->fetch_assoc()) {
                        $labels[] = addslashes($r['name'] ?? '');
                    }
                }
                echo "'" . implode("','", $labels) . "'";
            ?>],
            datasets: [{
                label: 'Quantity Sold',
                data: [<?php
                    $data = [];
                    if ($top_products_result) {
                        $top_products_result->data_seek(0);
                        while ($r = $top_products_result->fetch_assoc()) {
                            $data[] = intval($r['total_sold'] ?? 0);
                        }
                    }
                    echo implode(",", $data);
                ?>],
                backgroundColor: function(context) {
                    const colors = ['rgba(75, 192, 192, 0.7)', 'rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)',
                                    'rgba(255, 99, 132, 0.7)', 'rgba(153, 102, 255, 0.7)'];
                    return colors[context.dataIndex % colors.length];
                },
                borderRadius: 6,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            animation: {
                duration: 1200,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: { display: false },
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                x: { grid: { color: '#f0f0f0' } }
            }
        }
    });
</script>
<?php endif; ?>

<style>
/* Gradient Cards */
.bg-gradient-primary { background: linear-gradient(135deg,#0d6efd,#6610f2); }
.bg-gradient-success { background: linear-gradient(135deg,#198754,#20c997); }
.bg-gradient-dark { background: linear-gradient(135deg,#212529,#343a40); }
.bg-gradient-secondary { background: linear-gradient(135deg,#6c757d,#adb5bd); }
.bg-gradient-info { background: linear-gradient(135deg,#0dcaf0,#3b82f6); }
.bg-gradient-warning { background: linear-gradient(135deg,#ffc107,#fd7e14); }

.hover-scale:hover { transform: scale(1.03); transition: 0.3s; }
.card { border-radius: 12px; }
.table-hover tbody tr:hover { background-color: rgba(0,0,0,0.05); }
</style>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
