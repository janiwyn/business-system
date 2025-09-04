<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin"]);
include '../pages/sidebar.php';
include '../includes/header.php';

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

// ✅ FIX: count staff directly from users table
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

// Branch sales & profits
// Get total sales and total profits across all branches
$query = $conn->query("
    SELECT 
        SUM(amount) AS total_sales,
        SUM(total_profits) AS total_profits
    FROM sales
");
$monthlySalesQuery = $conn->query("
  SELECT DATE_FORMAT(date, '%b %Y') as month_label, SUM(amount) AS total
  FROM sales
  WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY YEAR(date), MONTH(date)
  ORDER BY YEAR(date), MONTH(date)
");

$months = [];
$monthlyTotals = [];
while ($row = $monthlySalesQuery->fetch_assoc()) {
    $months[] = $row['month_label'];  
    $monthlyTotals[] = $row['total'];
}

$result = $query->fetch_assoc();
$totalSales   = $result['total_sales'];
$totalProfits = $result['total_profits'];
// if no sales in the month
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

<style>
  /* General */
  body {
    background: #f4f6f9;
  }
  h3, h5 {
    font-weight: 600;
    color: #2c3e50;
  }

  /* Cards */
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
  .gradient-primary { background: linear-gradient(135deg, #1d976c, #2ecc71); }
  .gradient-success { background: linear-gradient(135deg, #56ccf2, #2f80ed); }
  .gradient-warning { background: linear-gradient(135deg, #f7971e, #ffd200); }
  .gradient-danger  { background: linear-gradient(135deg, #eb3349, #f45c43); }
  .gradient-info    { background: linear-gradient(135deg, #00c6ff, #0072ff); }
  .gradient-secondary { background: linear-gradient(135deg, #757f9a, #d7dde8); }

  /* Table */
  .table {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
  }
  .table thead {
    background: #34495e;
    color: #fff;
  }
  .table tbody tr:hover {
    background: rgba(0,0,0,0.03);
    cursor: pointer;
  }
</style>

<div class="container-fluid mt-4">
  <h3 class="mb-4">Welcome, <?= htmlspecialchars($username); ?> 👋</h3>

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
          <h5>Sales vs Profits</h5>
          <canvas id="barChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h5>Sales Per Month</h5>
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
          <p><?= $topProduct['name']?? 'N/A' ?> (<?= $topProduct['total_sold'] ?? '0' ?> sold)</p>
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
  <div class="mt-5">
    <h5>Recent Transactions</h5>
    <table class="table table-striped table-hover">
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
        $sales = $conn->query("
            SELECT sales.id, products.name AS product_name, sales.quantity, sales.amount, sales.`sold-by`, sales.date
            FROM sales
            JOIN products ON sales.`product-id` = products.id
            ORDER BY sales.id DESC
            LIMIT 10
        ");
        $i = 1;
        while ($row = $sales->fetch_assoc()):
        ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= $row['product_name'] ?></td>
            <td><?= $row['quantity'] ?></td>
            <td>$<?= number_format($row['amount'], 2) ?></td>
            <td><?= $row['date'] ?></td>
            <td><?= $row['sold-by'] ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>



<script>
   src="https://cdn.jsdelivr.net/npm/chart.js">
  const branchLabels = <?= json_encode($branchLabels) ?>;
  const salesData = <?= json_encode($sales) ?>;
  const profitData = <?= json_encode($profits) ?>;

  const barChart = new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($branchLabels) ?>,
      datasets: [
        {
          label: 'Sales',
          data: <?= json_encode($sales) ?>,
          backgroundColor: 'rgba(54, 162, 235, 0.7)'
        },
        {
          label: 'Profits',
          data: <?= json_encode($profits) ?>,
          backgroundColor: 'rgba(46, 204, 113, 0.7)'
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: true } }
    }
  });

  const lineChart = new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [{
        label: 'Monthly Sales',
        data: <?= json_encode($monthlyTotals) ?>,
        borderColor: '#e74c3c',
        backgroundColor: 'rgba(231, 76, 60, 0.2)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#fff',
        pointBorderColor: '#e74c3c',
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>

<?php include '../includes/footer.php'; ?>
