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
                        <label>Date From</label>
                        <input type="date" name="report_from" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Date To</label>
                        <input type="date" name="report_to" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Select Branch</label>
                        <select name="branch" class="form-select">
                            <option value="all">All Branches</option>
                            <?php
                            // rewind or re-query if needed; we used $branches above but it may be consumed later.
                            // To be safe, fetch branches here fresh:
                            $branches_list = $conn->query("SELECT id, name FROM branch");
                            while($b = $branches_list->fetch_assoc()):
                            ?>
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
<div id="printableReport" class="card mb-4 chart-card" style="background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1); color:#222;">
    <div style="text-align:center; padding:25px 0 15px 0; border-bottom:3px solid #2c3e50;">
        <img src="../uploads/logo.png" alt="Company Logo" style="height:80px; border-radius:10px; margin-bottom:8px;"><br>
        <h3 style="margin:0; font-weight:700; color:#2c3e50;">Your Business Name</h3>
        <p style="margin:4px 0; color:#555;">123 Business Street, Kampala | Tel: +256 700 000000 | Email: info@business.com</p>
        <p style="font-size:0.9rem; color:#666;">Report Generated: <?= date('M d, Y H:i') ?></p>
    </div>

    <div class="card-body table-responsive" style="padding:25px;">
        <div style="margin-bottom:20px; text-align:center;">
            <h4 style="color:#2c3e50; font-weight:700; margin-bottom:8px;">Sales Report</h4>
            <div style="display:inline-block; background:#2c3e50; color:#fff; padding:10px 16px; border-radius:8px; font-size:0.95rem;">
                <strong>Period:</strong>
                <?= $report_from ? htmlspecialchars(date('M d, Y', strtotime($report_from))) : '—' ?>
                to
                <?= $report_to ? htmlspecialchars(date('M d, Y', strtotime($report_to))) : '—' ?>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Branch:</strong> <?= htmlspecialchars($preview_branch_label) ?>
            </div>
        </div>

        <table style="width:100%; border-collapse:collapse; font-size:0.95rem; border-radius:8px; overflow:hidden;">
            <thead style="background-color:#2c3e50; color:#fff;">
                <tr>
                    <th style="padding:10px; text-align:left;">Date</th>
                    <th style="padding:10px; text-align:left;">Product</th>
                    <th style="padding:10px;">Qty</th>
                    <th style="padding:10px;">Price</th>
                    <th style="padding:10px;">Total</th>
                    <th style="padding:10px;">Sold By</th>
                    <th style="padding:10px;">Branch</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=0; foreach($preview_sales as $s): ?>
                <tr style="border-bottom:1px solid #ddd; background:<?= ($i++ % 2 == 0) ? '#f8f9fa' : '#ffffff' ?>;">
                    <td style="padding:8px; color:#222;"><?= htmlspecialchars($s['date']) ?></td>
                    <td style="padding:8px; color:#222;"><?= htmlspecialchars($s['product_name']) ?></td>
                    <td style="padding:8px; text-align:center; color:#222;"><?= htmlspecialchars($s['quantity']) ?></td>
                    <td style="padding:8px; text-align:right; color:#222;">UGX <?= number_format($s['amount']) ?></td>
                    <td style="padding:8px; text-align:right; color:#222;">UGX <?= number_format($s['quantity']*$s['amount']) ?></td>
                    <td style="padding:8px; color:#222;"><?= htmlspecialchars($s['sold-by']) ?></td>
                    <td style="padding:8px; color:#222;"><?= htmlspecialchars($s['branch_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h4 style="text-align:right; margin-top:20px; color:#2c3e50;">
            Total Sales: <strong>UGX <?= number_format($preview_total) ?></strong>
        </h4>

        <!-- <div style="text-align:right; margin-top:30px;">
            <button class="btn btn-success" onclick="printSection('printableReport')">Print Report</button>
        </div> -->
    </div>

    <div style="text-align:center; font-size:0.8rem; color:#999; padding:10px; border-top:1px solid #eee;">
        Generated by Business System &copy; <?= date('Y') ?>
    </div>
</div>
        <div style="text-align:right; margin-top:30px;">
            <button class="btn btn-success" onclick="printSection('printableReport')">Print Report</button>
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

// Chart data and options for both desktop and mobile
const salesChartData = {
    labels: <?= json_encode($months) ?>,
    datasets: [{
        label: 'Sales',
        data: <?= json_encode($salesData) ?>,
        backgroundColor: 'rgba(40,167,69,0.7)',
        borderRadius: 10
    }]
};
const salesChartOptions = {
    responsive: true,
    plugins: {
        legend: { display: false },
        title: { display: false }
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
};

const profitChartData = {
    labels: <?= json_encode($expenseMonths) ?>,
    datasets: [{
        label: 'Expenses',
        data: <?= json_encode($expenseData) ?>,
        backgroundColor: 'rgba(220,53,69,0.7)',
        borderRadius: 10
    }]
};
const profitChartOptions = {
    responsive: true,
    plugins: {
        legend: { display: false },
        title: { display: false }
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
};

// Desktop charts initialization (guarded so canvas exists)
if (document.getElementById('salesChart')) {
  new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: salesChartData,
    options: salesChartOptions
  });
}
if (document.getElementById('profitChart')) {
  new Chart(document.getElementById('profitChart'), {
    type: 'bar',
    data: profitChartData,
    options: profitChartOptions
  });
}

// Mobile charts initialization
function createSalesChartMobile() {
  if (document.getElementById('salesChartMobile')) {
    new Chart(document.getElementById('salesChartMobile'), {
      type: 'bar',
      data: salesChartData,
      options: salesChartOptions
    });
  }
}
function createProfitChartMobile() {
  if (document.getElementById('profitChartMobile')) {
    new Chart(document.getElementById('profitChartMobile'), {
      type: 'bar',
      data: profitChartData,
      options: profitChartOptions
    });
  }
}

if (window.innerWidth < 992) {
  createSalesChartMobile();
  createProfitChartMobile();
}

function printSection(sectionId) {
    var content = document.getElementById(sectionId).innerHTML;

    // Build a full print document with styles to ensure it prints nicely
    var printWindow = window.open('', '', 'height=900,width=1200');
    var styles = `
        <style>
            @page { margin: 10mm; }
            body { font-family: Arial, sans-serif; color: #333; margin: 10mm; }
            .report-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
            .report-header img { height:70px; border-radius:6px; }
            .report-header .info { flex:1; text-align:left; }
            .report-header .meta { text-align:right; }
            h4, h5 { margin:0; padding:0; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            table, th, td { border: 1px solid #000; }
            th, td { padding: 8px; text-align: left; font-size: 12px; }
            th { background: #2c3e50; color: #fff; }
            tr:nth-child(even){ background: #f9f9f9; }
            .totals { margin-top: 12px; text-align: right; font-weight: 700; }
            .small { font-size: 11px; color: #555; }
            @media print {
                .no-print { display: none !important; }
            }
        </style>
    `;

    // Header values (recreate the header dynamic values)
    var businessHeader = document.querySelector('#' + sectionId + ' .card-header') ? document.querySelector('#' + sectionId + ' .card-header').innerHTML : '';
    var periodText = '';
    // Try to extract period/branch from the preview area if available
    var periodNode = document.querySelector('#' + sectionId + ' .card-body');
    if (periodNode) {
        // We'll include the whole body content - simpler and safer
    }

    var html = `
        <html>
        <head>
            <title>Print Report</title>
            ${styles}
        </head>
        <body>
            ${content}
        </body>
        </html>
    `;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    // Wait for resources to load then print
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
    };
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
