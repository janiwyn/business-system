<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Redirect if not staff
// if ($_SESSION['role'] !== 'admin') {
//     header("Location: ../auth/login.php");
//     exit();
// }

// $user_id = $_SESSION['user_id'];
// $username = $_SESSION['username'];
$message = "";


$currentMonth = date('m');
$lastMonth = date('m', strtotime('-1 month'));
$year = date('Y');

// Current month sales
$currentQuery = $conn->prepare("SELECT SUM(amount) as total FROM sales WHERE MONTH(date) = ? AND YEAR(date) = ?");
$currentQuery->bind_param("ss", $currentMonth, $year);
$currentQuery->execute();
$currentResult = $currentQuery->get_result()->fetch_assoc();
$currentSales = $currentResult['total'] ?? 0;

// Last month sales
$lastQuery = $conn->prepare("SELECT SUM(amount) as total FROM sales WHERE MONTH(date) = ? AND YEAR(date) = ?");
$lastQuery->bind_param("ss", $lastMonth, $year);
$lastQuery->execute();
$lastResult = $lastQuery->get_result()->fetch_assoc();
$lastSales = $lastResult['total'] ?? 0;

// Calculate growth %
$growth = $lastSales > 0 ? (($currentSales - $lastSales) / $lastSales) * 100 : 0;



// total employees
$empRes = $conn->query('SELECT COUNT(*) AS total_employees FROM employees');
$employee = $empRes->fetch_assoc()['total_employees'];

// total branches
$branchRes = $conn->query('SELECT COUNT(*) AS total_branches FROM branch');
$totalbranches = $branchRes->fetch_assoc()['total_branches'];

// total stock
$stockRes = $conn->query('SELECT SUM(total_stock) AS total_stock FROM products');
$totalStock = $stockRes->fetch_assoc()['total_stock'];

// total profits
$profitRes = $conn->query('SELECT SUM(`net_profits`) AS total_profits FROM profits');
$totalProfit = $profitRes->fetch_assoc()['total_profits'];

// most selling product
$productRes = $conn->query('
   SELECT p.name, SUM(s.quantity) AS total_sold FROM sales s
   JOIN products p ON s.`product_id` = p.id
   GROUP BY p.name
   ORDER BY total_sold DESC 
   LIMIT 1
');
$topProduct = $productRes->fetch_assoc();

// Most Active Branch
$branchSales = $conn->query("
    SELECT b.name, COUNT(s.id) AS sales_count
    FROM sales s
    JOIN branch b ON s.`branch_id` = b.id
    GROUP BY b.name
    ORDER BY sales_count DESC
    LIMIT 1
");
$topBranch = $branchSales->fetch_assoc();

// fetching sales and profits from database
$query = $conn->query("
    SELECT branch_id,
           SUM(amount) AS total_sales,
           SUM(amount - cost_price) AS total_profits
    FROM sales
    GROUP BY branch_id
");

$branchLabels = [];
$sales = [];
$profits = [];

while ($row = $query->fetch_assoc()) {
    $branchLabels[] = $row["branch_id"];
    $sales[] = $row["total_sales"];
    $profits[] = $row["total_profits"];
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

?>


  <div class="container mt-4">
    <h3 class="mb-4">Welcome, <?= htmlspecialchars($username); ?> </h3>

    <!-- Summary Cards -->
    <div class="row text-white mb-4">
      <div class="col-md-3 mb-3">
        <div class="card bg-primary">
          <div class="card-body">
            <h5>Total Employees</h5>
            <h3><?= $employee ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-success">
          <div class="card-body">
            <h5>Total Branches</h5>
            <h3><?= $totalbranches ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-warning">
          <div class="card-body">
            <h5>Total Stock</h5>
            <h3><?=$totalStock ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-danger">
          <div class="card-body">
            <h5>Total Profit</h5>
            <h3>$<?= number_format($totalProfit, 2) ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- graphs -->
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
    <div class="row">
      <div class="col-md-4">
        <div class="card bg-info text-white">
          <div class="card-body">
            <h6>Most Selling Product</h6>
            <p><?= $topProduct['name']?? 'N/A' ?>(<?=$topProduct['total_sold'] ?? '0'?> sold)</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card bg-secondary text-white">
          <div class="card-body">
            <h6>Most Active Branch</h6>
            <p><?= $topBranch['name'] ?? 'N/A'?>(<?=$topBranch['sales_count'] ?? '0'?> sales)</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card bg-secondary text-white">
          <div class="card-body">
            <h6>Revenue Growth</h6>
            <p>
        <?= number_format($growth, 2) ?>%
        <?= $growth >= 0 ? 'from last month ' : 'drop from last month ' ?>
      </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="mt-5">
      <h5>Recent Transactions</h5>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Transaction ID</th>
            <th>Product</th>
            <th>Quantity</th>
            <th>Amount</th>
            <th>Date</th>
            <th>sold_by</th>
          </tr>
        </thead>
         <tbody>
            <?php
            $sales = $conn->query("
                SELECT sales.id, products.name AS product_name, sales.quantity, sales.amount,  sales.sold_by, sales.date
                FROM sales
                JOIN products ON sales.product_id = products.id
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
                    <td><?= number_format($row['amount'], 2) ?></td>
                    <td><?= $row['date'] ?></td>
                    <td><?= $row['sold_by'] ?></td>

                </tr>
            <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php
  // Monthly sales for line chart (last 12 months)
$monthlySalesQuery = $conn->query("
  SELECT DATE_FORMAT(date, '%b') as month, SUM(amount) as total
  FROM sales
  WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY MONTH(date)
  ORDER BY MONTH(date)
");

$months = [];
$monthlyTotals = [];

while ($row = $monthlySalesQuery->fetch_assoc()) {
    $months[] = $row['month'];
    $monthlyTotals[] = $row['total'];
}

?>
  <!-- JS for Charts -->
  <script>
  // Correct variable names and data structure
  const branchLabels = <?= json_encode($branchLabels) ?>;
  const salesData = <?= json_encode($sales) ?>;
  const profitData = <?= json_encode($profits) ?>;

  const barCtx = document.getElementById('barChart').getContext('2d');

  const barChart = new Chart(barCtx, {
    type: 'bar',
    data: {
      labels: branchLabels,
      datasets: [
        {
          label: 'Sales',
          data: salesData,
          backgroundColor: 'rgba(54, 162, 235, 0.7)'
        },
        {
          label: 'Profits',
          data: profitData,
          backgroundColor: 'rgba(75, 192, 192, 0.7)'
        }
      ]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
   const lineCtx = document.getElementById('lineChart').getContext('2d');

  const lineChart = new Chart(lineCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [{
        label: 'Monthly Sales',
        data: <?= json_encode($monthlyTotals) ?>,
        borderColor: 'rgba(255, 99, 132, 0.8)',
        fill: false,
        tension: 0.3
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
</script>


<?php include '../includes/footer.php'; ?>
