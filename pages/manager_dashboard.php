<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../pages/sidebar_manager.php';
include '../includes/header.php';

require_role(['manager']);

// Total Sales Today
$sql = "SELECT SUM(amount) AS total FROM sales WHERE DATE(`date`) = CURDATE()";
$result = mysqli_query($conn, $sql);
$sales_today = ($row = mysqli_fetch_assoc($result)) ? $row['total'] ?? 0 : 0;

// Total Expenses Today
$sql = "SELECT SUM(amount) AS total FROM expenses WHERE DATE(`date`) = CURDATE()";
$result = mysqli_query($conn, $sql);
$expenses_today = ($row = mysqli_fetch_assoc($result)) ? $row['total'] ?? 0 : 0;

// Total Products
$sql = "SELECT COUNT(*) AS total FROM products";
$result = mysqli_query($conn, $sql);
$total_products = ($row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;

// Total Staff
$sql = "SELECT COUNT(*) AS total FROM users WHERE role = 'staff'";
$result = mysqli_query($conn, $sql);
$total_staff = ($row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Branch filter
$selected_branch = $_GET['branch'] ?? '';
$whereClause = $selected_branch ? "WHERE s.`branch-id` = ".intval($selected_branch) : "";

// Count total sales for pagination
$count_sql = "SELECT COUNT(*) AS total FROM sales s $whereClause";
$count_result = mysqli_query($conn, $count_sql);
$total_sales = ($row = mysqli_fetch_assoc($count_result)) ? $row['total'] : 0;
$total_pages = ceil($total_sales / $limit);

// Fetch recent sales with branch info
$sales_sql = "
    SELECT s.date, p.name AS product_name, s.quantity, s.amount, u.username, b.name AS branch_name
    FROM sales s
    JOIN products p ON s.`product-id` = p.id
    JOIN users u ON s.`sold-by` = u.id
    JOIN branch b ON s.`branch-id` = b.id
    $whereClause
    ORDER BY s.date DESC
    LIMIT $limit OFFSET $offset
";
$sales_result = mysqli_query($conn, $sales_sql);

// Fetch all branches for dropdown
$branches = $conn->query("SELECT id, name FROM branch");
?>

<div class="container my-5">
    <h2 class="mb-4 fw-bold text-center">Manager Dashboard</h2>
    <h5 class="text-muted mb-4 text-center">Welcome, <?= htmlspecialchars($username); ?> 👋</h5>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card shadow-sm rounded border-0 text-white bg-gradient-success hover-scale h-100">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar fs-1 mb-2"></i>
                    <h5 class="card-title">Sales Today</h5>
                    <p class="card-text fs-5 fw-bold">UGX <?= number_format($sales_today); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded border-0 text-white bg-gradient-danger hover-scale h-100">
                <div class="card-body text-center">
                    <i class="bi bi-wallet2 fs-1 mb-2"></i>
                    <h5 class="card-title">Expenses Today</h5>
                    <p class="card-text fs-5 fw-bold">UGX <?= number_format($expenses_today); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded border-0 text-white bg-gradient-info hover-scale h-100">
                <div class="card-body text-center">
                    <i class="bi bi-box-seam fs-1 mb-2"></i>
                    <h5 class="card-title">Products in Stock</h5>
                    <p class="card-text fs-5 fw-bold"><?= $total_products; ?> items</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm rounded border-0 text-white bg-gradient-primary hover-scale h-100">
                <div class="card-body text-center">
                    <i class="bi bi-people fs-1 mb-2"></i>
                    <h5 class="card-title">Branch Staff</h5>
                    <p class="card-text fs-5 fw-bold"><?= $total_staff; ?> staff</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card mb-4 shadow-sm rounded border-0">
        <div class="card-header bg-gradient-primary text-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bar-chart-line me-2"></i> Recent Sales</span>
            <form method="GET" class="d-flex align-items-center">
                <label class="me-2 fw-bold mb-0">Branch:</label>
                <select name="branch" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Branches --</option>
                    <?php
                    $branches->data_seek(0); // Reset result pointer
                    while($b = $branches->fetch_assoc()): ?>
                        <option value="<?= $b['id'] ?>" <?= ($selected_branch == $b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Sold By</th>
                        <th>Branch</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($sales_result->num_rows > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($sales_result)): ?>
                        <tr>
                            <td><?= $row['date'] ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td><?= $row['quantity'] ?></td>
                            <td>UGX <?= number_format($row['amount']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No sales found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Sales pagination">
                    <ul class="pagination justify-content-center mt-3">
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?branch=<?= urlencode($selected_branch) ?>&page=<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card mb-4 shadow-sm rounded border-0">
        <div class="card-header bg-gradient-danger text-white fw-bold">
            <i class="bi bi-cash-coin me-2"></i> Recent Expenses
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Spent By</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sql = "
                    SELECT e.date, e.category, e.amount, u.username 
                    FROM expenses e 
                    JOIN users u ON e.`spent-by` = u.id 
                    ORDER BY e.date DESC 
                    LIMIT 5
                ";
                $result = mysqli_query($conn, $sql);
                while($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>
                        <td>{$row['date']}</td>
                        <td>{$row['category']}</td>
                        <td>UGX ".number_format($row['amount'])."</td>
                        <td>{$row['username']}</td>
                    </tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Actions -->
    <div class="d-flex gap-3 justify-content-center">
        <a href="sales.php" class="btn btn-success px-4"><i class="bi bi-plus-circle me-1"></i> Add Sale</a>
        <a href="expense.php" class="btn btn-danger px-4"><i class="bi bi-wallet2 me-1"></i> Add Expense</a>
        <a href="report.php" class="btn btn-secondary px-4"><i class="bi bi-file-earmark-bar-graph me-1"></i> Generate Report</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
/* Gradient Backgrounds */
.bg-gradient-success { background: linear-gradient(135deg,#198754,#20c997); }
.bg-gradient-danger { background: linear-gradient(135deg,#dc3545,#fd7e14); }
.bg-gradient-info { background: linear-gradient(135deg,#0dcaf0,#3b82f6); }
.bg-gradient-primary { background: linear-gradient(135deg,#0d6efd,#6610f2); }

/* Cards Hover */
.hover-scale:hover { transform: scale(1.03); transition: 0.3s; }

/* Card Style */
.card { border-radius: 14px; }

/* Tables */
.table-hover tbody tr:hover { background-color: rgba(0,0,0,0.05); }
</style>
