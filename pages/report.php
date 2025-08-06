<?php
include '../includes/db.php';
include '../includes/header.php';
include '../pages/sidebar.php';
include '../includes/auth.php';
require_role("manager", "admin");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-4">

    <h2 class="mb-4">Business Report</h2>

    <!-- Filter Section -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-5">
            <label for="start_date" class="form-label">From:</label>
            <input type="date" class="form-control" name="start_date" required>
        </div>
        <div class="col-md-5">
            <label for="end_date" class="form-label">To:</label>
            <input type="date" class="form-control" name="end_date" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="row text-white mb-4">
        <div class="col-md-4">
            <div class="card bg-success">
                <div class="card-body">
                    <h5>Total Sales</h5>
                    <h3>
                        <?php
                        $sales_q = mysqli_query($conn, "SELECT SUM(quantity * total_price) AS total_sales FROM sales");
                        $sales_row = mysqli_fetch_assoc($sales_q);
                        echo 'UGX ' . number_format($sales_row['total_sales'] ?? 0);
                        ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-danger">
                <div class="card-body">
                    <h5>Total Expenses</h5>
                    <h3>
                        <?php
                        $exp_q = mysqli_query($conn, "SELECT SUM(amount) AS total_expenses FROM expenses");
                        $exp_row = mysqli_fetch_assoc($exp_q);
                        echo 'UGX ' . number_format($exp_row['total_expenses'] ?? 0);
                        ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-info">
                <div class="card-body">
                    <h5>Net Profit</h5>
                    <h3>
                        <?php
                        $profit = ($sales_row['total_sales'] ?? 0) - ($exp_row['total_expenses'] ?? 0);
                        echo 'UGX ' . number_format($profit);
                        ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card mb-4">
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
                            <td>{$row['product_id']}</td>
                            <td>{$row['quantity']}</td>
                            <td>{$row['sold_by']}</td>
                            <td>UGX " . number_format($row['total_price']) . "</td>
                            <td>UGX " . number_format($row['quantity'] * $row['total_price']) . "</td>
                            <td>{$row['date']}</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Expense Table -->
    <div class="card mb-4">
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

    <!-- Charts Section (Optional) -->
    <div class="row mb-5">
        <div class="col-md-6">
            <canvas id="salesChart"></canvas>
        </div>
        <div class="col-md-6">
            <canvas id="expensesChart"></canvas>
        </div>
    </div>

</div>

<script>
    const salesChart = new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
            datasets: [{
                label: 'Sales',
                data: [1000000, 1500000, 1200000, 1700000, 2000000],
                backgroundColor: 'rgba(40, 167, 69, 0.6)'
            }]
        },
        options: {
            responsive: true
        }
    });

    const expensesChart = new Chart(document.getElementById('expensesChart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
            datasets: [{
                label: 'Expenses',
                data: [600000, 700000, 550000, 800000, 950000],
                backgroundColor: 'rgba(220, 53, 69, 0.6)'
            }]
        },
        options: {
            responsive: true
        }
    });
</script>
<?php
    include '../includes/footer.php';

?>