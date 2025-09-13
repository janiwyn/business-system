<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin","manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';     // Use modern header

// ==========================
// Summary Cards Data
// ==========================
$sales_total_q = $conn->query("SELECT SUM(quantity*amount) AS total_sales FROM sales");
$sales_total = ($row = $sales_total_q->fetch_assoc()) ? $row['total_sales'] ?? 0 : 0;

$expenses_total_q = $conn->query("SELECT SUM(amount) AS total_expenses FROM expenses");
$expenses_total = ($row = $expenses_total_q->fetch_assoc()) ? $row['total_expenses'] ?? 0 : 0;

$profit_total = $sales_total - $expenses_total;

// ==========================
// Sales per month for chart
// ==========================
$months = [];
$salesData = [];
$sales_q = $conn->query("
    SELECT DATE_FORMAT(date, '%b') AS month, SUM(amount*quantity) AS total
    FROM sales
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY MIN(date)
");
while ($row = $sales_q->fetch_assoc()) {
    $months[] = $row['month'];
    $salesData[] = $row['total'];
}

// ==========================
// Expenses per month for chart
// ==========================
$expenseMonths = [];
$expenseData = [];
$exp_q = $conn->query("
    SELECT DATE_FORMAT(date, '%b') AS month, SUM(amount) AS total
    FROM expenses
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY MIN(date)
");
while ($row = $exp_q->fetch_assoc()) {
    $expenseMonths[] = $row['month'];
    $expenseData[] = $row['total'];
}

// ==========================
// Branches for dropdown
// ==========================
$branches = $conn->query("SELECT id, name FROM branch");

// ==========================
// Handle Preview Form Submission
// ==========================
$preview_sales = [];
$preview_total = 0;
if (isset($_POST['preview_report'])) {
    $date = $_POST['report_date'] ?? '';
    $branch_id = $_POST['branch'] ?? '';

    $where = [];
    if ($branch_id && $branch_id !== 'all') {
        $where[] = "sales.`branch-id` = ".intval($branch_id);
    }
    if ($date) {
        $where[] = "DATE(sales.date) = '".date('Y-m-d', strtotime($date))."'";
    }

    $where_sql = count($where) ? "WHERE ".implode(' AND ', $where) : "";
    $preview_query = "
        SELECT sales.id, products.name AS product_name, sales.quantity, sales.amount, sales.`sold-by`, branch.name AS branch_name, sales.date
        FROM sales
        JOIN products ON sales.`product-id` = products.id
        JOIN branch ON sales.`branch-id` = branch.id
        $where_sql
        ORDER BY sales.date DESC
    ";
    $res = $conn->query($preview_query);
    while ($row = $res->fetch_assoc()) {
        $preview_sales[] = $row;
        $preview_total += $row['quantity'] * $row['amount'];
    }
}

// ==========================
// Pagination for main sales table
// ==========================
$limit = 10;
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$start = ($page-1)*$limit;

$branch_filter = $_GET['branch'] ?? '';
$where_main = $branch_filter && $branch_filter !== 'all' ? "WHERE sales.`branch-id` = ".intval($branch_filter) : "";

$sales_count_q = $conn->query("SELECT COUNT(*) AS total FROM sales $where_main");
$total_sales_count = ($row = $sales_count_q->fetch_assoc()) ? $row['total'] : 0;
$total_pages = ceil($total_sales_count / $limit);

$sales_main = $conn->query("
    SELECT sales.id, products.name AS product_name, sales.quantity, sales.amount, sales.`sold-by`, branch.name AS branch_name, sales.date
    FROM sales
    JOIN products ON sales.`product-id` = products.id
    JOIN branch ON sales.`branch-id` = branch.id
    $where_main
    ORDER BY sales.date DESC
    LIMIT $start,$limit
");
?>
<div class="container-fluid mt-4">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card stat-card gradient-success">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Sales 💰</h6>
                        <h3>UGX <?= number_format($sales_total) ?></h3>
                    </div>
                    <i class="fa-solid fa-coins stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card stat-card gradient-danger">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Expenses 💸</h6>
                        <h3>UGX <?= number_format($expenses_total) ?></h3>
                    </div>
                    <i class="fa-solid fa-wallet stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card stat-card gradient-info">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Net Profit 📈</h6>
                        <h3>UGX <?= number_format($profit_total) ?></h3>
                    </div>
                    <i class="fa-solid fa-chart-line stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card chart-card">
                <div class="card-body">
                    <h5 class="title-card">Sales</h5>
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card chart-card">
                <div class="card-body">
                    <h5 class="title-card">Expenses</h5>
                    <canvas id="expensesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Button -->
    <div class="mb-3 text-end">
        <button class="btn print-report-btn" data-bs-toggle="modal" data-bs-target="#printModal">Print Report</button>
    </div>

    <!-- Print Modal -->
    <div class="modal fade" id="printModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Print Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Select Date</label>
                        <input type="date" name="report_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Select Branch</label>
                        <select name="branch" class="form-select">
                            <option value="all">All Branches</option>
                            <?php while($b = $branches->fetch_assoc()): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="preview_report" class="btn btn-primary">Preview Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Report -->
    <?php if(!empty($preview_sales)): ?>
        <div id="printableReport" class="card mb-4 chart-card">
            <div class="card-header preview-report-header text-white">
                <img src="../uploads/logo.png" alt="Company Logo" style="height:50px; float:left; margin-right:10px;">
                <h5>Sales Report - <?= htmlspecialchars($_POST['report_date']) ?> (<?= $_POST['branch']=='all' ? 'All Branches' : 'Selected Branch' ?>)</h5>
                <div style="clear:both;"></div>
            </div>
            <div class="card-body table-responsive">
                <div class="transactions-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Total</th>
                                <th>Sold By</th>
                                <th>Branch</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($preview_sales as $s): ?>
                                <tr>
                                    <td><?= $s['date'] ?></td>
                                    <td><?= htmlspecialchars($s['product_name']) ?></td>
                                    <td><?= $s['quantity'] ?></td>
                                    <td>UGX <?= number_format($s['amount']) ?></td>
                                    <td>UGX <?= number_format($s['quantity']*$s['amount']) ?></td>
                                    <td><?= htmlspecialchars($s['sold-by']) ?></td>
                                    <td><?= htmlspecialchars($s['branch_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <h5 class="text-end">Total Sales: UGX <?= number_format($preview_total) ?></h5>
                <div class="text-end mt-3">
                    <button class="btn btn-success" onclick="printSection('printableReport')">Print</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Sales Table -->
    <div class="card mb-4 chart-card">
        <div class="card-header bg-light text-black d-flex justify-content-between align-items-center">
            <span>Sales Records</span>
            <form method="GET" class="d-flex align-items-center">
                <label class="me-2 fw-bold">Filter by Branch:</label>
                <select name="branch" class="form-select me-2" onchange="this.form.submit()">
                    <option value="all">All Branches</option>
                    <?php
                    $branches_main = $conn->query("SELECT id,name FROM branch");
                    while($b = $branches_main->fetch_assoc()) {
                        $selected = ($branch_filter==$b['id'])?"selected":""; 
                        echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                    }
                    ?>
                </select>
            </form>
        </div>
        <div class="card-body table-responsive">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Sold By</th>
                            <th>Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($s = $sales_main->fetch_assoc()): ?>
                            <tr>
                                <td><?= $s['date'] ?></td>
                                <td><?= htmlspecialchars($s['product_name']) ?></td>
                                <td><?= $s['quantity'] ?></td>
                                <td>UGX <?= number_format($s['amount']) ?></td>
                                <td>UGX <?= number_format($s['quantity']*$s['amount']) ?></td>
                                <td><?= htmlspecialchars($s['sold-by']) ?></td>
                                <td><?= htmlspecialchars($s['branch_name']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for($i=1;$i<=$total_pages;$i++): ?>
                        <li class="page-item <?= ($i==$page)?'active':'' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&branch=<?= $branch_filter ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function isDarkMode() {
    return document.body.classList.contains('dark-mode');
}
function getChartFontColor() {
    return isDarkMode() ? '#fff' : '#2c3e50';
}
function getChartGridColor() {
    return isDarkMode() ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)';
}

function renderCharts() {
    // Remove old canvases if re-rendering
    if (window.salesChartInstance) window.salesChartInstance.destroy();
    if (window.expensesChartInstance) window.expensesChartInstance.destroy();

    const fontColor = getChartFontColor();
    const gridColor = getChartGridColor();

    window.salesChartInstance = new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($salesData) ?>,
                backgroundColor: 'rgba(40,167,69,0.7)',
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: false }
            },
            scales: {
                x: {
                    ticks: { color: fontColor },
                    grid: { color: gridColor }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: fontColor },
                    grid: { color: gridColor }
                }
            }
        }
    });

    window.expensesChartInstance = new Chart(document.getElementById('expensesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($expenseMonths) ?>,
            datasets: [{
                label: 'Expenses',
                data: <?= json_encode($expenseData) ?>,
                backgroundColor: 'rgba(220,53,69,0.7)',
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: false }
            },
            scales: {
                x: {
                    ticks: { color: fontColor },
                    grid: { color: gridColor }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: fontColor },
                    grid: { color: gridColor }
                }
            }
        }
    });
}

// Initial render
renderCharts();

// Re-render charts on dark mode toggle
document.getElementById('themeToggle')?.addEventListener('change', () => {
    renderCharts();
});

function printSection(sectionId) {
    var content = document.getElementById(sectionId).innerHTML;
    var printWindow = window.open('', '', 'height=800,width=1000');
    printWindow.document.write('<html><head><title>Print Report</title>');
    printWindow.document.write('<style>table{width:100%;border-collapse:collapse;} table,th,td{border:1px solid black;padding:8px;} h5{text-align:center;}</style>');
    printWindow.document.write('</head><body >');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>

<style>
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
body.dark-mode .card .card-header.bg-light {
    background-color: #2c3e50 !important;
    color: #fff !important;
    border-bottom: none;
}
body.dark-mode .card .card-header.bg-light label,
body.dark-mode .card .card-header.bg-light select,
body.dark-mode .card .card-header.bg-light span {
    color: #fff !important;
}
body.dark-mode .card .card-header.bg-light .form-select {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .card .card-header.bg-light .form-select:focus {
    background-color: #23243a !important;
    color: #fff !important;
}
.print-report-btn {
    background: var(--primary-color) !important;
    color: #fff !important;
    border: none;
    font-weight: 600;
    padding: 0.5rem 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(44,62,80,0.08);
    transition: background 0.2s;
}
.print-report-btn:hover, .print-report-btn:focus {
    background: #159c8c !important;
    color: #fff !important;
}
.preview-report-header {
    background-color: #2c3e50 !important;
    color: #fff !important;
    border-bottom: none;
}
body.dark-mode .preview-report-header {
    background-color: #2c3e50 !important;
    color: #fff !important;
}
.preview-report-header h5 {
    color: #fff !important;
}
.preview-report-header img {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(44,62,80,0.12);
}
</style>

<?php include '../includes/footer.php'; ?>
