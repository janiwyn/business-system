<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Sales per month
$salesData = [];
$months = [];
$sales_q = $conn->query("
    SELECT DATE_FORMAT(date, '%b') AS month, SUM(amount) AS total
    FROM sales
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY MIN(date)
");
while ($row = $sales_q->fetch_assoc()) {
    $months[] = $row['month'];
    $salesData[] = $row['total'];
}

// Expenses per month
$expenseData = [];
$expenseMonths = [];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Improved Card Styling */
        .summary-card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            padding: 25px;
            color: white;
            margin-bottom: 20px;
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .summary-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-top: 10px;
        }
        .summary-card h5 {
            font-size: 1rem;
            font-weight: 500;
        }

        /* Chart Card Styling */
        .chart-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
            background-color: #fff;
        }

        /* Filter Button and Dropdown */
        .filter-section {
            margin-top: 20px;
            margin-bottom: 40px;
        }
        .filter-dropdown {
            display: none;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="container mt-4">

    <h2 class="mb-4">📊 Business Report</h2>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="summary-card bg-success">
                <h5>Total Sales 💰</h5>
                <h3>
                    <?php
                    $sales_q = mysqli_query($conn, "SELECT SUM(quantity * amount) AS `total-sales` FROM sales");
                    $sales_row = mysqli_fetch_assoc($sales_q);
                    echo 'UGX ' . number_format($sales_row['total-sales'] ?? 0);
                    ?>
                </h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="summary-card bg-danger">
                <h5>Total Expenses 💸</h5>
                <h3>
                    <?php
                    $exp_q = mysqli_query($conn, "SELECT SUM(amount) AS total_expenses FROM expenses");
                    $exp_row = mysqli_fetch_assoc($exp_q);
                    echo 'UGX ' . number_format($exp_row['total_expenses'] ?? 0);
                    ?>
                </h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="summary-card bg-info">
                <h5>Net Profit 📈</h5>
                <h3>
                    <?php
                    $profit = ($sales_row['total_sales'] ?? 0) - ($exp_row['total_expenses'] ?? 0);
                    echo 'UGX ' . number_format($profit);
                    ?>
                </h3>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row">
        <div class="col-md-6">
            <div class="chart-card">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-card">
                <canvas id="expensesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Filter Button -->
    <div class="filter-section text-center">
        <button id="filterBtn" class="btn btn-secondary">Filter By ⬇️</button>
        <div id="filterDropdown" class="filter-dropdown">
            <div class="row mt-3 g-3 justify-content-center">
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" onclick="showFilter('day')">By Day</button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" onclick="showFilter('week')">By Week</button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" onclick="showFilter('month')">By Month</button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" onclick="showFilter('year')">By Year</button>
                </div>
            </div>
        </div>

        <!-- Filter Forms -->
        <div id="filterForms" class="mt-3"></div>
    </div>

    <!-- Sales Table -->
    <div class="card mb-4 chart-card">
        <div class="card-header bg-light text-black">
            Sales Records
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Sold By</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Sold On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sales = mysqli_query($conn, "SELECT * FROM sales ORDER BY date DESC");
                    while ($row = mysqli_fetch_assoc($sales)) {
                        echo "<tr>
                            <td>{$row['product-id']}</td>
                            <td>{$row['quantity']}</td>
                            <td>{$row['sold-by']}</td>
                            <td>UGX " . number_format($row['amount']) . "</td>
                            <td>UGX " . number_format($row['quantity'] * $row['amount']) . "</td>
                            <td>{$row['date']}</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card mb-4 chart-card">
        <div class="card-header bg-danger text-white">
            Expense Records
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Category</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $expenses = mysqli_query($conn, "SELECT * FROM expenses ORDER BY date DESC");
                    while ($exp = mysqli_fetch_assoc($expenses)) {
                        echo "<tr>
                            <td>{$exp['description']}</td>
                            <td>UGX " . number_format($exp['amount']) . "</td>
                            <td>{$exp['category']}</td>
                            <td>{$exp['date']}</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    // Smooth Chart Load Animations
    const salesChart = new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($salesData) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            animation: { duration: 1500, easing: 'easeOutQuart' },
            plugins: { legend: { display: false } },
        }
    });

    const expensesChart = new Chart(document.getElementById('expensesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($expenseMonths) ?>,
            datasets: [{
                label: 'Expenses',
                data: <?= json_encode($expenseData) ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            animation: { duration: 1500, easing: 'easeOutQuart' },
            plugins: { legend: { display: false } },
        }
    });

    // Filter Dropdown Toggle
    const filterBtn = document.getElementById('filterBtn');
    const filterDropdown = document.getElementById('filterDropdown');
    const filterForms = document.getElementById('filterForms');

    filterBtn.addEventListener('click', () => {
        filterDropdown.style.display = filterDropdown.style.display === 'block' ? 'none' : 'block';
    });

    function showFilter(type) {
    let html = '';
    if(type === 'day'){
        html = `<input type="date" id="filterInput" class="form-control w-50 mx-auto" placeholder="Select Day">`;
    } else if(type === 'week'){
        html = `<input type="week" id="filterInput" class="form-control w-50 mx-auto" placeholder="Select Week">`;
    } else if(type === 'month'){
        html = `<select id="filterInput" class="form-select w-50 mx-auto">
                    <option value="">Select Month</option>
                    <?php
                    for($m=1;$m<=12;$m++){
                        echo "<option value='$m'>".date('F', mktime(0,0,0,$m,1))."</option>";
                    }
                    ?>
                </select>`;
    } else if(type === 'year'){
        html = `<input type="number" id="filterInput" class="form-control w-50 mx-auto" placeholder="Enter Year" min="2000" max="2100">`;
    }
    html += `<button class="btn btn-primary mt-2" id="applyFilterBtn">Apply Filter</button>`;
    filterForms.innerHTML = html;

    document.getElementById('applyFilterBtn').addEventListener('click', () => {
        let value = document.getElementById('filterInput').value;
        if(!value) return alert('Please select a value');

        fetch(`../includes/get_report_data.php?type=${type}&value=${value}`)
        .then(res => res.json())
        .then(data => {
            salesChart.data.labels = data.labels;
            salesChart.data.datasets[0].data = data.sales;
            salesChart.update();

            expensesChart.data.labels = data.labels;
            expensesChart.data.datasets[0].data = data.expenses;
            expensesChart.update();
        });
    });
}

</script>

<?php
include '../includes/footer.php';
?>
