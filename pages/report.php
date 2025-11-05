<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin","manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';     // Use modern header

// ==========================
// Summary Cards Data
// ==========================
$sales_total_q = $conn->query("SELECT SUM(amount) AS total_sales FROM sales");
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
    SELECT DATE_FORMAT(date, '%b %Y') AS month, SUM(amount) AS total
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
    SELECT DATE_FORMAT(date, '%b %Y') AS month, SUM(amount) AS total
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
// Handle Preview Form Submission (date range + branch)
// ==========================
$preview_sales = [];
$preview_total = 0;
$preview_branch_label = 'All Branches';
$report_from = '';
$report_to = '';

if (isset($_POST['preview_report'])) {
    // POST fields from modal
    $report_from = $_POST['report_from'] ?? '';
    $report_to = $_POST['report_to'] ?? '';
    $branch_id = $_POST['branch'] ?? '';

    $where = [];
    if ($branch_id && $branch_id !== 'all') {
        $where[] = "sales.`branch-id` = " . intval($branch_id);
        // get branch label for preview header
        $bq = $conn->query("SELECT name FROM branch WHERE id = " . intval($branch_id));
        if ($br = $bq->fetch_assoc()) $preview_branch_label = $br['name'];
    } else {
        $preview_branch_label = 'All Branches';
    }

    if ($report_from && $report_to) {
        $where[] = "DATE(sales.date) BETWEEN '" . date('Y-m-d', strtotime($report_from)) . "' AND '" . date('Y-m-d', strtotime($report_to)) . "'";
    } elseif ($report_from) {
        $where[] = "DATE(sales.date) >= '" . date('Y-m-d', strtotime($report_from)) . "'";
    } elseif ($report_to) {
        $where[] = "DATE(sales.date) <= '" . date('Y-m-d', strtotime($report_to)) . "'";
    }

    $where_sql = count($where) ? "WHERE " . implode(' AND ', $where) : "";
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

    <!-- Responsive Charts Carousel for Small Devices -->
    <div class="d-block d-md-none mb-4">
      <div id="reportsChartsCarousel" class="carousel slide charts-carousel" data-bs-ride="false" data-bs-touch="true">
        <div class="carousel-inner">
          <div class="carousel-item active">
            <div class="card">
              <div class="card-header">Sales Report</div>
              <div class="card-body">
                <canvas id="salesChartMobile"></canvas>
              </div>
            </div>
          </div>
          <div class="carousel-item">
            <div class="card">
              <div class="card-header">Profit Report</div>
              <div class="card-body">
                <canvas id="profitChartMobile"></canvas>
              </div>
            </div>
          </div>
          <!-- Add more carousel-item blocks for additional charts if needed -->
        </div>
        <div class="d-flex justify-content-center mt-3">
          <div class="carousel-indicators position-static mb-0">
            <button type="button" data-bs-target="#reportsChartsCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Sales Report"></button>
            <button type="button" data-bs-target="#reportsChartsCarousel" data-bs-slide-to="1" aria-label="Profit Report"></button>
            <!-- Add more buttons for additional charts if needed -->
          </div>
        </div>
      </div>
    </div>

    <!-- Charts for medium and large devices -->
    <div class="row mb-4 d-none d-md-flex">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header" style="color: #1abc9c;"><b>Sales Report</b></div>
          <div class="card-body">
            <canvas id="salesChart"></canvas>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header" style="color: #1abc9c;"><b>Profit Report</b></div>
          <div class="card-body">
            <canvas id="profitChart"></canvas>
          </div>
        </div>
      </div>
      <!-- Add more chart columns if needed -->
    </div>

    <!-- Generate Report Button -->
    <div class="mb-3 text-end">
        <button type="button" class="btn btn-success d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('sales')">
            <i class="fa fa-file-pdf"></i>
        </button>
        <button type="button" class="btn btn-success d-none d-md-inline-flex" onclick="openReportGen('sales')">
            <i class="fa fa-file-pdf"></i> Generate Report
        </button>
    </div>

    <!-- Main Sales Table -->
    <div class="card mb-4 chart-card">
        <div class="card-header bg-light text-black d-flex justify-content-between align-items-center">
            <span>Sales Records</span>
            <form method="GET" class="d-flex align-items-center">
                <label class="me-2 fw-bold">Filter by Branch:</label>
                <select name="branch" class="form-select me-2" onchange="this.form.submit()">
                    <option value="all">All Branches</option>
                    <?php
                    // fresh query for branch list for main filter
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
                                <td><?= htmlspecialchars($s['date']) ?></td>
                                <td><?= htmlspecialchars($s['product_name']) ?></td>
                                <td><?= htmlspecialchars($s['quantity']) ?></td>
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
                            <a class="page-link" href="?page=<?= $i ?>&branch=<?= htmlspecialchars($branch_filter) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal for report generation -->
<div class="modal fade" id="reportGenModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content report-modal-content" id="reportGenForm">
      <div class="modal-header report-modal-header">
        <h5 class="modal-title" id="reportGenModalTitle">Generate Sales Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3 report-modal-body">
        <div class="col-md-6">
          <label class="form-label">From</label>
          <input type="date" name="date_from" id="report_date_from" class="form-control report-modal-input" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">To</label>
          <input type="date" name="date_to" id="report_date_to" class="form-control report-modal-input" required>
        </div>
        <div class="col-md-12">
          <label class="form-label">Branch</label>
          <select name="branch" id="report_branch" class="form-select report-modal-input">
            <option value="">All Branches</option>
            <?php
            $branches = $conn->query("SELECT id, name FROM branch");
            while ($b = $branches->fetch_assoc()):
                echo "<option value='{$b['id']}'>" . htmlspecialchars($b['name']) . "</option>";
            endwhile;
            ?>
          </select>
        </div>
      </div>
      <div class="modal-footer report-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Generate & Print</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReportGen(type) {
    document.getElementById('reportGenModalTitle').textContent = 'Generate Sales Report';
    document.getElementById('reportGenForm').dataset.reportType = type;
    new bootstrap.Modal(document.getElementById('reportGenModal')).show();
}

document.getElementById('reportGenForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const type = this.dataset.reportType || 'sales';
    const date_from = document.getElementById('report_date_from').value;
    const date_to = document.getElementById('report_date_to').value;
    const branch = document.getElementById('report_branch').value;
    const url = `reports_generator.php?type=${encodeURIComponent(type)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&branch=${encodeURIComponent(branch)}`;
    window.open(url, '_blank');
    bootstrap.Modal.getInstance(document.getElementById('reportGenModal')).hide();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function getChartColors() {
    const isDark = document.body.classList.contains('dark-mode');
    return {
        textColor: isDark ? '#fff' : '#222',
        gridColor: isDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)'
    };
}
function renderCharts() {
    const { textColor, gridColor } = getChartColors();

    // Sales Chart
    var ctxSales = document.getElementById('salesChart').getContext('2d');
    window.salesChartInstance = new Chart(ctxSales, {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($salesData) ?>,
                backgroundColor: '#1abc9c'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false, labels: { color: textColor } } },
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            }
        }
    });

    // Profit Chart
    var ctxProfit = document.getElementById('profitChart').getContext('2d');
    window.profitChartInstance = new Chart(ctxProfit, {
        type: 'bar',
        data: {
            labels: <?= json_encode($expenseMonths) ?>,
            datasets: [{
                label: 'Expenses',
                data: <?= json_encode($expenseData) ?>,
                backgroundColor: '#e74c3c'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false, labels: { color: textColor } } },
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            }
        }
    });

    // Mobile charts
    var ctxSalesMobile = document.getElementById('salesChartMobile').getContext('2d');
    window.salesChartMobileInstance = new Chart(ctxSalesMobile, {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($salesData) ?>,
                backgroundColor: '#1abc9c'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false, labels: { color: textColor } } },
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            }
        }
    });

    var ctxProfitMobile = document.getElementById('profitChartMobile').getContext('2d');
    window.profitChartMobileInstance = new Chart(ctxProfitMobile, {
        type: 'bar',
        data: {
            labels: <?= json_encode($expenseMonths) ?>,
            datasets: [{
                label: 'Expenses',
                data: <?= json_encode($expenseData) ?>,
                backgroundColor: '#e74c3c'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false, labels: { color: textColor } } },
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            }
        }
    });
}

// Initial render
document.addEventListener('DOMContentLoaded', renderCharts);

// Re-render charts on theme change
const themeObserver = new MutationObserver(function() {
    // Destroy old charts
    if (window.salesChartInstance) window.salesChartInstance.destroy();
    if (window.profitChartInstance) window.profitChartInstance.destroy();
    if (window.salesChartMobileInstance) window.salesChartMobileInstance.destroy();
    if (window.profitChartMobileInstance) window.profitChartMobileInstance.destroy();
    renderCharts();
});
themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
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

/* Modal form styling for both light and dark mode */
.report-modal-content {
    border-radius: 14px;
    background: #fff;
    color: #222;
    box-shadow: 0 4px 24px #0002;
}
.report-modal-header {
    background: var(--primary-color, #1abc9c);
    color: #fff;
    border-radius: 14px 14px 0 0;
    border-bottom: none;
}
.report-modal-header .modal-title {
    color: #fff !important;
    font-weight: bold;
}
.report-modal-body label {
    color: var(--primary-color, #1abc9c);
    font-weight: 600;
}
.report-modal-input {
    border-radius: 8px;
    background: #fff;
    color: #222;
    border: 1px solid #dee2e6;
    transition: background 0.2s, color 0.2s;
}
.report-modal-input:focus {
    background: #f8f9fa;
    color: #222;
    border-color: var(--primary-color, #1abc9c);
}
.report-modal-footer {
    border-top: none;
    background: #f8f9fa;
    border-radius: 0 0 14px 14px;
}

/* Dark mode overrides */
body.dark-mode .report-modal-content {
    background: #23243a !important;
    color: #fff !important;
    box-shadow: 0 4px 24px #0006;
}
body.dark-mode .report-modal-header {
    background: #1abc9c !important;
    color: #fff !important;
}
body.dark-mode .report-modal-header .modal-title {
    color: #fff !important;
}
body.dark-mode .report-modal-body label {
    color: #ffd200 !important;
}
body.dark-mode .report-modal-input {
    background: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .report-modal-input:focus {
    background: #23243a !important;
    color: #fff !important;
    border-color: #ffd200 !important;
}
body.dark-mode .report-modal-footer {
    background: #23243a !important;
    border-top: none;
    color: #fff !important;
    border-radius: 0 0 14px 14px;
}
</style>

<?php include '../includes/footer.php'; ?>
