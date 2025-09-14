<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Branch ID from GET (default 1 if not set)
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
/* --- CARDS --- */
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

/* Branch Info Card */
.branch-info-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 4px 12px var(--card-shadow);
    border: none;
    color: var(--text-color);
}

/* --- TABLES --- */
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
.transactions-table tbody td { padding: 0.75rem 1rem; }
.transactions-table tbody tr:nth-child(even) { background-color: #f4f6f9; }
.transactions-table tbody tr:hover { background-color: rgba(0,0,0,0.05); }

/* Card header */
.card-header {
    font-weight: 600;
    background: var(--primary-color);
    color: #fff !important;
    border-radius: 12px 12px 0 0 !important;
    font-size: 1.1rem;
}

/* Fix chart heights */
#barChart { width: 100% !important; height: 300px !important; }
#donutChart { width: 100% !important; max-width: 250px !important; height: 250px !important; margin: auto; }

/* Donut card flex layout */
.donut-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}
.donut-legend {
    flex: 1;
    font-size: 0.9rem;
    color: var(--text-color); /* ensures white text in dark mode */
}
.donut-legend ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.donut-legend li {
    margin-bottom: 8px;
    display: flex;
    align-items: center;
}
.donut-legend span.color-box {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 3px;
    margin-right: 8px;
}
</style>

<div class="container mt-5">

    <!-- Branch Selector -->
    <div class="d-flex justify-content-end mb-3">
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="branchDropdown" data-bs-toggle="dropdown">
                <?= $branch ? "Viewing: " . htmlspecialchars($branch['name']) : "Select Branch" ?>
            </button>
            <ul class="dropdown-menu" aria-labelledby="branchDropdown">
                <?php while ($row = $all_branches->fetch_assoc()): ?>
                    <li>
                        <a class="dropdown-item <?= ($row['id'] == $branch_id) ? 'active' : '' ?>" 
                           href="branch.php?id=<?= $row['id'] ?>">
                           <?= htmlspecialchars($row['name']) ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

<?php if (!$branch): ?>
    <div class='alert alert-warning'>Branch not found. Please add one below.</div>
    <div class="card branch-info-card">
        <div class="card-header">Add New Branch</div>
        <div class="card-body">
            <form method="POST" action="create_branch.php">
                <div class="mb-3"><label class="form-label">Branch Name</label><input type="text" class="form-control" name="name" required></div>
                <div class="mb-3"><label class="form-label">Location</label><input type="text" class="form-control" name="location" required></div>
                <div class="mb-3"><label class="form-label">Contact</label><input type="text" class="form-control" name="contact" required></div>
                <button type="submit" class="btn btn-primary">Create Branch</button>
            </form>
        </div>
    </div>

<?php else:

    // Data queries
    $inventory = $conn->query("SELECT COUNT(*) AS total_products, COALESCE(SUM(stock),0) AS stock FROM products WHERE `branch-id`=$branch_id")->fetch_assoc();
    $sales = $conn->query("SELECT COUNT(*) AS total_sales, SUM(amount) AS revenue FROM sales WHERE `branch-id`=$branch_id")->fetch_assoc();
    $expenses = $conn->query("SELECT SUM(amount) AS total_expense FROM expenses WHERE `branch-id`=$branch_id")->fetch_assoc();
    $profit = ($sales['revenue'] ?? 0) - ($expenses['total_expense'] ?? 0);

    // Top products
    $top_products = $conn->query("
        SELECT p.name, SUM(s.quantity) AS total_sold 
        FROM sales s 
        JOIN products p ON s.`product-id`=p.id 
        WHERE s.`branch-id`=$branch_id 
        GROUP BY s.`product-id` 
        ORDER BY total_sold DESC
    ");
    $top_products_array = [];
    while ($row = $top_products->fetch_assoc()) {
        $top_products_array[] = $row;
    }

    // Chart Data
    $chart_labels = array_slice(array_column($top_products_array, 'name'), 0, 5);
    $chart_data   = array_slice(array_column($top_products_array, 'total_sold'), 0, 5);

    // Staff
    $staff_result = $conn->query("SELECT username, role FROM users WHERE `branch-id`=$branch_id AND role='staff'");
?>

    <!-- Branch Info -->
    <div class="card mb-4 branch-info-card">
        <div class="card-header">Branch Information</div>
        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($branch['name']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($branch['location']) ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($branch['contact']) ?></p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card stat-card gradient-primary h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fa-solid fa-box stat-icon me-3"></i>
                    <div>
                        <h6>Inventory</h6>
                        <h3><?= $inventory['total_products'] ?></h3>
                        <div>Stock: <?= $inventory['stock'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card gradient-success h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fa-solid fa-coins stat-icon me-3"></i>
                    <div>
                        <h6>Sales</h6>
                        <h3><?= $sales['total_sales'] ?></h3>
                        <div>Revenue: UGX <?= number_format($sales['revenue'] ?? 0,2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card gradient-danger h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fa-solid fa-chart-line stat-icon me-3"></i>
                    <div>
                        <h6>Profit</h6>
                        <div>Expenses: UGX <?= number_format($expenses['total_expense'] ?? 0,2) ?></div>
                        <h3>Net: UGX <?= number_format($profit,2) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <!-- Bar Chart -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Top Products (Bar)</div>
                <div class="card-body p-4">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Donut Chart -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Top Products (Donut)</div>
                <div class="card-body p-4">
                    <div class="donut-wrapper">
                        <canvas id="donutChart"></canvas>
                        <div class="donut-legend">
                            <ul id="donutLegendList"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products Table -->
    <div class="card mb-4">
        <div class="card-header">Top Selling Products</div>
        <div class="card-body transactions-table">
            <table>
                <thead><tr><th>Product</th><th>Quantity Sold</th></tr></thead>
                <tbody>
                <?php foreach ($top_products_array as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= $row['total_sold'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Staff -->
    <div class="card mb-4">
        <div class="card-header">Branch Staff</div>
        <div class="card-body transactions-table">
            <table>
                <thead><tr><th>Username</th><th>Role</th></tr></thead>
                <tbody>
                <?php if ($staff_result->num_rows > 0): ?>
                    <?php while($staff = $staff_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($staff['username']) ?></td>
                            <td><?= ucfirst($staff['role']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="2" class="text-center">No staff found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($chart_labels) ?>;
const data = <?= json_encode($chart_data) ?>;
const colors = ['#3498db','#2ecc71','#f1c40f','#e74c3c','#9b59b6'];

// Function to detect theme
function isDarkMode() {
    return document.body.classList.contains("dark-mode");
}
function chartTextColor() {
    return isDarkMode() ? "#fff" : "#333";
}

// Bar Chart
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Sold', data, backgroundColor: colors }] },
    options: { 
        responsive:true, 
        plugins:{legend:{display:false}},
        scales: {
            x: { ticks: { color: chartTextColor() } },
            y: { ticks: { color: chartTextColor() } }
        }
    }
});

// Donut Chart
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data, backgroundColor: colors }] },
    options: { 
        responsive:true,
        plugins: { 
            legend: { display: false } // ✅ disable built-in legend
        } 
    }
});
//removed duplicate code

// Custom legend for donut
const legendContainer = document.getElementById("donutLegendList");
labels.forEach((label, i) => {
    const li = document.createElement("li");
    li.innerHTML = `<span class="color-box" style="background:${colors[i]}"></span> <span style="color:${chartTextColor()}">${label} (${data[i]})</span>`;
    legendContainer.appendChild(li);
});
</script>

<?php include '../includes/footer.php'; ?>
