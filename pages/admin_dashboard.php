<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin"]);
include '../pages/sidebar_admin.php'; // Admin sidebar
include '../includes/header.php';      // Header


$message = "";

// Dates
$currentMonth =  date('m');
$lastMonth = date('m', strtotime('-1 month'));
$year =  date('Y');

// Current month sales
$currentQuery = $conn->prepare("SELECT SUM(amount) as total FROM sales WHERE MONTH(date) = ? AND YEAR(date) = ?");
$currentQuery->bind_param("ss", $currentMonth, $year);
$currentQuery->execute();
$currentResult = $currentQuery->get_result()->fetch_assoc();
$currentSales = $currentResult['total'] ?? 0;

// Last month sales
$lastQuery = $conn->prepare("SELECT SUM(amount) as total FROM sales WHERE MONTH(date) = ? AND YEAR(date) = ?");
$lastQuery->bind_param("ii", $lastMonth, $year);
$lastQuery->execute();
$lastResult = $lastQuery->get_result()->fetch_assoc();
$lastSales = $lastResult['total'] ?? 0;

// Growth
$growth = $lastSales > 0 ? (($currentSales - $lastSales) / $lastSales) * 100 : 0;

// Employees
$employee = $conn->query("SELECT COUNT(*) AS total_employees FROM users WHERE role='staff'")
                 ->fetch_assoc()['total_employees'];

$totalbranches = $conn->query('SELECT COUNT(*) AS total_branches FROM branch')->fetch_assoc()['total_branches'];
$totalStock = $conn->query('SELECT SUM(stock) AS total_stock FROM products')->fetch_assoc()['total_stock'];
$totalProfit = $conn->query('SELECT SUM(`net-profits`) AS total_profits FROM profits')->fetch_assoc()['total_profits'];

// Most selling product
$productRes = $conn->query('
   SELECT p.name, SUM(s.quantity) AS total_sold FROM sales s
   JOIN products p ON s.`product-id` = p.id
   GROUP BY p.name
   ORDER BY total_sold DESC 
   LIMIT 1
');
$topProduct = $productRes->fetch_assoc();

// Most active branch
$branchSales = $conn->query("
    SELECT b.name, COUNT(s.id) AS sales_count
    FROM sales s
    JOIN branch b ON s.`branch-id` = b.id
    GROUP BY b.name
    ORDER BY sales_count DESC
    LIMIT 1
");
$topBranch = $branchSales->fetch_assoc();

// Branch sales & profits per branch
$branchData = $conn->query("
    SELECT 
        b.name AS branch_name,
        SUM(s.amount) AS total_sales,
        SUM(pr.`net-profits`) AS total_profits
    FROM branch b
    LEFT JOIN sales s ON s.`branch-id` = b.id
    LEFT JOIN profits pr ON pr.`branch-id` = b.id
    GROUP BY b.name
");

$branchLabels = [];
$sales = [];
$profits = [];

while ($row = $branchData->fetch_assoc()) {
    $branchLabels[] = $row['branch_name'];
    $sales[]        = $row['total_sales'] ?? 0;
    $profits[]      = $row['total_profits'] ?? 0;
}

// Total sales & profits
$query = $conn->query("
    SELECT 
        SUM(amount) AS total_sales,
        SUM(total_profits) AS total_profits
    FROM sales
");
$result = $query->fetch_assoc();
$totalSales   = $result['total_sales'];
$totalProfits = $result['total_profits'];

// Monthly sales (last 12 months)
$monthlySalesQuery = $conn->query("
  SELECT DATE_FORMAT(date, '%b %Y') as month_label, SUM(amount) AS total
  FROM sales
  WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY YEAR(date), MONTH(date)
  ORDER BY YEAR(date), MONTH(date)
");

$months = [];
$monthlyTotals = array_fill(0, 12, 0); // Initialize with zeros for 12 months
$currentDate = new DateTime();
for ($i = 11; $i >= 0; $i--) {
    $date = (clone $currentDate)->modify("-$i months");
    $months[] = $date->format('M Y'); 
}

while ($row = $monthlySalesQuery->fetch_assoc()) {
    $monthIndex = array_search($row['month_label'], $months);
    if ($monthIndex !== false) {
        $monthlyTotals[$monthIndex] = $row['total'];
    }
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
?>

<div class="container-fluid mt-4">
  <div class="welcome-banner mb-4">
    <h3 class="welcome-text">Welcome, <?= htmlspecialchars($username); ?> 👋</h3>
  </div>
  <!-- Summary Cards -->
  <div class="row text-white mb-4">
    <div class="col-md-3 mb-3">
      <div class="card stat-card gradient-primary">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <h6>Total Employees</h6>
            <h3><?= $employee ?></h3>
          </div>
          <i class="fa-solid fa-users stat-icon"></i>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card stat-card gradient-success">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <h6>Total Branches</h6>
            <h3><?= $totalbranches ?></h3>
          </div>
          <i class="fa-solid fa-building stat-icon"></i>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card stat-card gradient-warning">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <h6>Total Stock</h6>
            <h3><?= $totalStock ?></h3>
          </div>
          <i class="fa-solid fa-cubes stat-icon"></i>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card stat-card gradient-danger">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <h6>Total Profit</h6>
            <h3>$<?= number_format($totalProfits, 2) ?></h3>
          </div>
          <i class="fa-solid fa-sack-dollar stat-icon"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h5 class="title-card">Sales vs Profits</h5>
          <canvas id="barChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h5 class="title-card">Sales Per Month</h5>
          <canvas id="lineChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Extra Stats -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card stat-card gradient-info">
        <div class="card-body">
          <h6>Most Selling Product</h6>
          <p><?= $topProduct['name'] ?? 'N/A' ?> (<?= $topProduct['total_sold'] ?? '0' ?> sold)</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card gradient-secondary">
        <div class="card-body">
          <h6>Most Active Branch</h6>
          <p><?= $topBranch['name'] ?? 'N/A' ?> (<?= $topBranch['sales_count'] ?? '0' ?> sales)</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card gradient-success">
        <div class="card-body">
          <h6>Revenue Growth</h6>
          <p><?= number_format($growth, 2) ?>% <?= $growth >= 0 ? 'increase 📈' : 'decrease 📉' ?> from last month</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Transactions -->
<div class="transactions-table mt-5">
  <h5 class="transactions-title">Recent Transactions</h5>
  <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Product</th>
          <th>Quantity</th>
          <th>Amount</th>
          <th>Date</th>
          <th>Sold By</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $salesData = $conn->query("
            SELECT sales.id, products.name AS product_name, sales.quantity, sales.amount, sales.`sold-by`, sales.date
            FROM sales
            JOIN products ON sales.`product-id` = products.id
            ORDER BY sales.id DESC
            LIMIT 10
        ");
        $i = 1;
        while ($row = $salesData->fetch_assoc()):
        ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= $row['product_name'] ?></td>
            <td><?= $row['quantity'] ?></td>
            <td>$<?= number_format($row['amount'], 2) ?></td>
            <td><?= date('d-M-Y', strtotime($row['date'])) ?></td>
            <td><?= $row['sold-by'] ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const branchLabels = <?= json_encode($branchLabels) ?>;
const salesData    = <?= json_encode($sales) ?>;
const profitData   = <?= json_encode($profits) ?>;
const months       = <?= json_encode($months) ?>;
const monthlyTotals = <?= json_encode($monthlyTotals) ?>;

function isDarkMode() {
  return document.body.classList.contains('dark-mode');
}

function getChartColors() {
  if (isDarkMode()) {
    return {
      salesColor: 'rgba(54, 162, 235, 0.8)',
      profitColor: 'rgba(46, 204, 113, 0.8)',
      monthlyLine: 'rgba(231,76,60,0.9)',
      monthlyFill: 'rgba(231,76,60,0.2)',
      fontColor: '#f4f4f4',
      gridColor: 'rgba(255,255,255,0.2)'
    };
  } else {
    return {
      salesColor: 'rgba(54, 162, 235, 0.7)',
      profitColor: 'rgba(46, 204, 113, 0.7)',
      monthlyLine: 'rgba(231,76,60,0.9)',
      monthlyFill: 'rgba(231,76,60,0.2)',
      fontColor: '#2c3e50',
      gridColor: 'rgba(0,0,0,0.1)'
    };
  }
}

function createBarChart() {
  const colors = getChartColors();
  new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
      labels: branchLabels,
      datasets: [
        { label: 'Sales', data: salesData, backgroundColor: colors.salesColor },
        { label: 'Profits', data: profitData, backgroundColor: colors.profitColor }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: colors.fontColor } } },
      scales: {
        x: { ticks: { color: colors.fontColor }, grid: { color: colors.gridColor } },
        y: { ticks: { color: colors.fontColor }, grid: { color: colors.gridColor }, beginAtZero: true }
      }
    }
  });
}

function createLineChart() {
  const colors = getChartColors();
  new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
      labels: months,
      datasets: [{
        label: 'Monthly Sales',
        data: monthlyTotals,
        borderColor: colors.monthlyLine,
        backgroundColor: colors.monthlyFill,
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: colors.fontColor } } },
      scales: {
        x: { ticks: { color: colors.fontColor }, grid: { color: colors.gridColor } },
        y: { ticks: { color: colors.fontColor }, grid: { color: colors.gridColor }, beginAtZero: true }
      }
    }
  });
}

// Initialize charts
createBarChart();
createLineChart();

// Re-render charts on dark mode toggle
document.querySelector('.dark-toggle').addEventListener('click', () => {
  document.querySelectorAll('canvas').forEach(canvas => canvas.remove());
  // Re-add canvas elements
  const barDiv = document.createElement('canvas'); barDiv.id = 'barChart';
  document.getElementById('barChart').parentNode.appendChild(barDiv);

  const lineDiv = document.createElement('canvas'); lineDiv.id = 'lineChart';
  document.getElementById('lineChart').parentNode.appendChild(lineDiv);

  createBarChart();
  createLineChart();
});
</script>
</script>
