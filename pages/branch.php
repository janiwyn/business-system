<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar_admin.php';
include '../includes/header.php';

// Branch ID can be passed via GET
$branch_id = isset($_GET['id']) ? intval($_GET['id']) : 1;

// Fetch all branches for dropdown
$all_branches = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");

// Get Branch Info
$branch_stmt = $conn->prepare("SELECT * FROM branch WHERE id = ?");
$branch_stmt->bind_param("i", $branch_id);
$branch_stmt->execute();
$branch = $branch_stmt->get_result()->fetch_assoc();
?>

<style>
/* Summary Cards */
.stat-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: transform 0.2s ease-in-out;
    color: #fff;
}
.stat-card:hover { transform: translateY(-5px); }
.stat-icon {
    font-size: 2rem;
    opacity: 0.8;
}
.gradient-success { background: linear-gradient(135deg, #56ccf2, #2f80ed); }
.gradient-danger  { background: linear-gradient(135deg, #eb3349, #f45c43); }
.gradient-info    { background: linear-gradient(135deg, #00c6ff, #0072ff); }
.gradient-primary { background: linear-gradient(135deg, #3498db, #2980b9); }
.gradient-warning { background: linear-gradient(135deg, #f1c40f, #f39c12); }
.gradient-secondary { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
body.dark-mode .stat-card,
body.dark-mode .gradient-success,
body.dark-mode .gradient-danger,
body.dark-mode .gradient-info,
body.dark-mode .gradient-primary,
body.dark-mode .gradient-warning,
body.dark-mode .gradient-secondary {
    color: #fff !important;
}

/* Card theme for branch info */
.branch-info-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 4px 12px var(--card-shadow);
    color: var(--text-color);
    border: none;
}
body.dark-mode .branch-info-card {
    background: var(--card-bg);
    color: #fff;
}

/* Table styling */
.transactions-table table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px var(--card-shadow);
}
.transactions-table thead {
    background: var(--primary-color);
    color: #fff;
    text-transform: uppercase;
    font-size: 13px;
}
.transactions-table tbody td {
    color: var(--text-color);
    padding: 0.75rem 1rem;
}
.transactions-table tbody tr {
    background-color: #fff;
    transition: background 0.2s;
}
.transactions-table tbody tr:nth-child(even) {
    background-color: #f4f6f9;
}
.transactions-table tbody tr:hover {
    background-color: rgba(0,0,0,0.05);
}
body.dark-mode .transactions-table table {
    background: var(--card-bg);
}
body.dark-mode .transactions-table thead {
    background-color: #1abc9c;
    color: #ffffff;
}
body.dark-mode .transactions-table tbody tr {
    background-color: #2c2c3a !important;
}
body.dark-mode .transactions-table tbody tr:nth-child(even) {
    background-color: #272734 !important;
}
body.dark-mode .transactions-table tbody td {
    color: #ffffff !important;
}
body.dark-mode .transactions-table tbody tr:hover {
    background-color: rgba(255,255,255,0.1) !important;
}

/* Card header theme */
.card-header {
    font-weight: 600;
    background: var(--primary-color);
    color: #fff !important;
    border-radius: 12px 12px 0 0 !important;
    font-size: 1.1rem;
    letter-spacing: 1px;
}
body.dark-mode .card-header {
    background-color: #2c3e50 !important;
    color: #fff !important;
}
</style>

<div class="container mt-5">

    <!-- Branch Selector Dropdown -->
    <div class="d-flex justify-content-end mb-3">
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="branchDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <?= $branch ? "Viewing: " . htmlspecialchars($branch['name']) : "Select Branch to View" ?>
            </button>
            <ul class="dropdown-menu" aria-labelledby="branchDropdown">
                <?php while ($row = $all_branches->fetch_assoc()): ?>
                    <li>
                        <a class="dropdown-item <?= ($row['id'] == $branch_id) ? 'active' : '' ?>" 
                           href="branch.php?id=<?= $row['id'] ?>">
                           <?= htmlspecialchars($row['name'] ?? 'Unknown') ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

<?php if (!$branch): ?>
    <div class='alert alert-warning'>No branches have been created yet or branch does not exist. Please add a branch below.</div>

    <!-- Add Branch Form -->
    <div class="card branch-info-card">
        <div class="card-header">Add New Branch</div>
        <div class="card-body">
            <form method="POST" action="create_branch.php">
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Branch Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="location" class="form-label fw-semibold">Location</label>
                    <input type="text" class="form-control" id="location" name="location" required>
                </div>
                <div class="mb-3">
                    <label for="contact" class="form-label fw-semibold">Contact</label>
                    <input type="text" class="form-control" id="contact" name="contact" required>
                </div>
                <button type="submit" class="btn btn-primary">Create Branch</button>
            </form>
        </div>
    </div>

<?php else:

    // Fetch staff for the current branch
    $stmt = $conn->prepare("SELECT username, role FROM users WHERE `branch-id` = ? AND role = 'staff'");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $staff_result = $stmt->get_result();

    $inventory_result = $conn->query("SELECT COUNT(*) AS total_products, COALESCE(SUM(stock), 0) AS stock FROM products WHERE `branch-id` = $branch_id");
    $inventory = $inventory_result->fetch_assoc();

    $sales_result = $conn->query("SELECT COUNT(*) AS total_sales, SUM(amount) AS revenue FROM sales WHERE `branch-id` = $branch_id");
    $sales = $sales_result->fetch_assoc();

    $expense_result = $conn->query("SELECT SUM(amount) AS total_expense FROM expenses WHERE `branch-id` = $branch_id");
    $expenses = $expense_result->fetch_assoc();

    $profit = ($sales['revenue'] ?? 0) - ($expenses['total_expense'] ?? 0);

    $top_products_result = $conn->query("
        SELECT p.name, SUM(s.quantity) AS total_sold 
        FROM sales s 
        JOIN products p ON s.`product-id` = p.id 
        WHERE s.`branch-id` = $branch_id 
        GROUP BY s.`product-id` 
        ORDER BY total_sold DESC 
        LIMIT 5
    ");

    // Pagination for Top Selling Products
    $top_products_page = isset($_GET['top_products_page']) ? max(1, intval($_GET['top_products_page'])) : 1;
    $top_products_per_page = 10;
    $top_products_offset = ($top_products_page - 1) * $top_products_per_page;

    // Get total count for top products
    $total_top_products_result = $conn->query("
        SELECT COUNT(DISTINCT s.`product-id`) AS total
        FROM sales s
        WHERE s.`branch-id` = $branch_id
    ");
    $total_top_products = $total_top_products_result->fetch_assoc()['total'] ?? 0;
    $total_top_products_pages = ceil($total_top_products / $top_products_per_page);

    // Paginated top products
    $top_products_result = $conn->query("
        SELECT p.name, SUM(s.quantity) AS total_sold 
        FROM sales s 
        JOIN products p ON s.`product-id` = p.id 
        WHERE s.`branch-id` = $branch_id 
        GROUP BY s.`product-id` 
        ORDER BY total_sold DESC 
        LIMIT $top_products_per_page OFFSET $top_products_offset
    ");

    // For donut chart (top 5 products)
    $donut_products_result = $conn->query("
        SELECT p.name, SUM(s.quantity) AS total_sold 
        FROM sales s 
        JOIN products p ON s.`product-id` = p.id 
        WHERE s.`branch-id` = $branch_id 
        GROUP BY s.`product-id` 
        ORDER BY total_sold DESC 
        LIMIT 5
    ");
    $donut_labels = [];
    $donut_data = [];
    while ($row = $donut_products_result->fetch_assoc()) {
        $donut_labels[] = $row['name'] ?? 'Unknown';
        $donut_data[] = $row['total_sold'] ?? 0;
    }

    // For bar chart (top 5 products)
    // IMPORTANT: Only run this query ONCE and use the result for both the table and the chart
    // If you run $top_products_result->fetch_assoc() in the table, the pointer is at the end for the chart
    // So, fetch the data for the table into an array, and reuse it for the chart

    // Rewind the pointer for the paginated table (already used above)
    // But for the chart, we need a separate query for top 5 products only
    $bar_products_result = $conn->query("
        SELECT p.name, SUM(s.quantity) AS total_sold 
        FROM sales s 
        JOIN products p ON s.`product-id` = p.id 
        WHERE s.`branch-id` = $branch_id 
        GROUP BY s.`product-id` 
        ORDER BY total_sold DESC 
        LIMIT 5
    ");
    $bar_labels = [];
    $bar_data = [];
    while ($row = $bar_products_result->fetch_assoc()) {
        $bar_labels[] = $row['name'] ?? 'Unknown';
        $bar_data[] = $row['total_sold'] ?? 0;
    }

    // Pagination for Staff
    $staff_page = isset($_GET['staff_page']) ? max(1, intval($_GET['staff_page'])) : 1;
    $staff_per_page = 10;
    $staff_offset = ($staff_page - 1) * $staff_per_page;

    // Get total staff count
    $staff_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE `branch-id` = ? AND role = 'staff'");
    $staff_count_stmt->bind_param("i", $branch_id);
    $staff_count_stmt->execute();
    $staff_count_result = $staff_count_stmt->get_result()->fetch_assoc();
    $total_staff = $staff_count_result['total'] ?? 0;
    $total_staff_pages = ceil($total_staff / $staff_per_page);

    // Paginated staff
    $staff_stmt = $conn->prepare("SELECT username, role FROM users WHERE `branch-id` = ? AND role = 'staff' LIMIT ? OFFSET ?");
    $staff_stmt->bind_param("iii", $branch_id, $staff_per_page, $staff_offset);
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
?>

    <!-- Branch Information -->
    <div class="card mb-4 branch-info-card">
        <div class="card-header">Branch Information</div>
        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($branch['name'] ?? '-') ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($branch['location'] ?? '-') ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($branch['contact'] ?? '-') ?></p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card stat-card gradient-primary h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fa-solid fa-box stat-icon me-3"></i>
                    <div>
                        <h6>Inventory Summary</h6>
                        <h3><?= $inventory['total_products'] ?? 0 ?></h3>
                        <div>Items in Stock: <?= $inventory['stock'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card gradient-success h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fa-solid fa-coins stat-icon me-3"></i>
                    <div>
                        <h6>Sales Summary</h6>
                        <h3><?= $sales['total_sales'] ?? 0 ?></h3>
                        <div>Revenue: UGX <?= number_format($sales['revenue'] ?? 0, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card gradient-danger h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fa-solid fa-chart-line stat-icon me-3"></i>
                    <div>
                        <h6>Profit Analysis</h6>
                        <div>Expenses: UGX <?= number_format($expenses['total_expense'] ?? 0, 2) ?></div>
                        <h3>Net Profit: UGX <?= number_format($profit, 2) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="card mb-4">
        <div class="card-header">Top Selling Products</div>
        <div class="card-body">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $top_products_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name'] ?? '-') ?></td>
                                <td><?= $row['total_sold'] ?? 0 ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination for Top Products -->
            <?php if ($total_top_products_pages > 1): ?>
            <nav aria-label="Top Products Pagination">
                <ul class="pagination justify-content-center mt-3">
                    <?php for ($p = 1; $p <= $total_top_products_pages; $p++): ?>
                        <li class="page-item <?= ($p == $top_products_page) ? 'active' : '' ?>">
                            <a class="page-link" href="?id=<?= $branch_id ?>&top_products_page=<?= $p ?><?= ($staff_page > 1 ? '&staff_page=' . $staff_page : '') ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Staff -->
    <div class="card mb-4">
        <div class="card-header">Branch Staff</div>
        <div class="card-body">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($staff_result->num_rows > 0): ?>
                            <?php while($staff = $staff_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($staff['name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($staff['username'] ?? '-') ?></td>
                                    <td><?= ucfirst($staff['role'] ?? '-') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">No staff found for this branch.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination for Staff -->
            <?php if ($total_staff_pages > 1): ?>
            <nav aria-label="Staff Pagination">
                <ul class="pagination justify-content-center mt-3">
                    <?php for ($p = 1; $p <= $total_staff_pages; $p++): ?>
                        <li class="page-item <?= ($p == $staff_page) ? 'active' : '' ?>">
                            <a class="page-link" href="?id=<?= $branch_id ?>&staff_page=<?= $p ?><?= ($top_products_page > 1 ? '&top_products_page=' . $top_products_page : '') ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-5">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Sales Chart</div>
                <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Top Products Donut</div>
                <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                    <canvas id="donutChart" style="max-width:320px;max-height:320px;width:100%;height:auto;display:block;"></canvas>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if ($branch): ?>
<script>
const salesChartElem = document.getElementById('salesChart');
const donutChartElem = document.getElementById('donutChart');

// Defensive: Only get context if canvas exists
const ctx = salesChartElem ? salesChartElem.getContext('2d') : null;
const donutCtx = donutChartElem ? donutChartElem.getContext('2d') : null;

const branchChartLabels = <?= json_encode($bar_labels) ?>;
const branchChartData = <?= json_encode($bar_data) ?>;
const donutLabels = <?= json_encode($donut_labels) ?>;
const donutData = <?= json_encode($donut_data) ?>;

function isDarkMode() {
    return document.body.classList.contains('dark-mode');
}
function getChartFontColor() {
    return isDarkMode() ? '#fff' : '#2c3e50';
}
function getChartGridColor() {
    return isDarkMode() ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)';
}

function renderBranchCharts() {
    if (window.branchSalesChart && typeof window.branchSalesChart.destroy === 'function') window.branchSalesChart.destroy();
    if (window.donutChart && typeof window.donutChart.destroy === 'function') window.donutChart.destroy();

    // Only render if canvas/context exists
    if (ctx) {
        window.branchSalesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: branchChartLabels,
                datasets: [{
                    label: 'Quantity Sold',
                    data: branchChartData,
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderRadius: 6,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ticks: { color: getChartFontColor() },
                        grid: { color: getChartGridColor() }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: getChartFontColor() },
                        grid: { color: getChartGridColor() }
                    }
                }
            }
        });
    }

    if (donutCtx) {
        window.donutChart = new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: donutLabels,
                datasets: [{
                    data: donutData,
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { color: getChartFontColor() }
                    }
                }
            }
        });
    }
}
renderBranchCharts();
document.getElementById('themeToggle')?.addEventListener('change', renderBranchCharts);

// Set donut chart size smaller via JS as well for extra safety
if (donutChartElem) {
    donutChartElem.width = 320;
    donutChartElem.height = 320;
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
