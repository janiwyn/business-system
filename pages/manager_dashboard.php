<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(['manager', 'super']);
include '../pages/sidebar.php';
include '../includes/header.php';

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

<style>
/* Welcome Banner (same as admin_dashboard) */
.welcome-banner {
    background: linear-gradient(90deg, #1abc9c 0%, #56ccf2 100%);
    border-radius: 14px;
    padding: 1.5rem 2rem;
    box-shadow: 0 2px 12px var(--card-shadow);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    position: relative;
    overflow: hidden;
}
.welcome-balls {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 1;
    pointer-events: none;
}
.welcome-ball {
    position: absolute;
    border-radius: 50%;
    opacity: 0.18;
    transition: background 0.3s;
    box-shadow: 0 2px 12px rgba(44,62,80,0.12);
    will-change: left, top;
}
.welcome-text {
    color: #fff;
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: 1px;
    margin: 0;
    text-shadow: 0 2px 8px rgba(44,62,80,0.12);
}
body.dark-mode .welcome-banner {
    background: linear-gradient(90deg, #23243a 0%, #1abc9c 100%);
    box-shadow: 0 2px 12px rgba(44,62,80,0.18);
}
body.dark-mode .welcome-text {
    color: #ffd200;
    text-shadow: 0 2px 8px rgba(44,62,80,0.18);
}

/* Table Styling (like admin_dashboard recent transactions) */
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

/* Card header theme for tables */
.card-header {
    font-weight: 600;
    background: var(--primary-color);
    color: #fff !important;
    border-radius: 12px 12px 0 0 !important;
    font-size: 1.1rem;
    letter-spacing: 1px;
}
body.dark-mode .card-header {
    background-color: #2c3e50 !important;
    color: #fff !important;
}

/* Summary Cards Styling (like admin_dashboard, but keep manager content) */
.summary-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: transform 0.2s ease-in-out;
    color: #fff;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 120px;
}
.summary-card:hover { transform: translateY(-5px);}
.summary-icon {
    font-size: 2.2rem;
    opacity: 0.85;
    margin-left: 0.5rem;
}
.gradient-primary { background: linear-gradient(135deg, #3498db, #2980b9); }
.gradient-success { background: linear-gradient(135deg, #2ecc71, #27ae60); }
.gradient-warning { background: linear-gradient(135deg, #f1c40f, #f39c12); }
.gradient-danger  { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.gradient-info    { background: linear-gradient(135deg, #00c6ff, #0072ff); }
body.dark-mode .summary-card,
body.dark-mode .gradient-primary,
body.dark-mode .gradient-success,
body.dark-mode .gradient-warning,
body.dark-mode .gradient-danger,
body.dark-mode .gradient-info {
    color: #fff !important;
}
.summary-card .summary-label {
    font-size: 1.05rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    letter-spacing: 0.5px;
}
.summary-card .summary-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0;
    letter-spacing: 1px;
}
</style>

<div class="container-fluid mt-4">
    <!-- Welcome Banner -->
    <div class="welcome-banner mb-4" style="position:relative;overflow:hidden;">
        <div class="welcome-balls"></div>
        <h3 class="welcome-text" style="position:relative;z-index:2;">
            Welcome, <?= htmlspecialchars($_SESSION['username']); ?> 👋
        </h3>
    </div>

    <div class="container my-5">
        <!-- Summary Cards (styled like admin_dashboard, but manager content/icons) -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="summary-card gradient-success h-100">
                    <div>
                        <div class="summary-label">Sales Today</div>
                        <div class="summary-value">UGX <?= number_format($sales_today); ?></div>
                    </div>
                    <i class="fa-solid fa-coins summary-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card gradient-danger h-100">
                    <div>
                        <div class="summary-label">Expenses Today</div>
                        <div class="summary-value">UGX <?= number_format($expenses_today); ?></div>
                    </div>
                    <i class="fa-solid fa-wallet summary-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card gradient-info h-100">
                    <div>
                        <div class="summary-label">Products in Stock</div>
                        <div class="summary-value"><?= $total_products; ?> items</div>
                    </div>
                    <i class="fa-solid fa-boxes-stacked summary-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card gradient-primary h-100">
                    <div>
                        <div class="summary-label">Branch Staff</div>
                        <div class="summary-value"><?= $total_staff; ?> staff</div>
                    </div>
                    <i class="fa-solid fa-users summary-icon"></i>
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
            <div class="card-body">
                <div class="transactions-table">
                    <table>
                        <thead>
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
                </div>
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
            <div class="card-body">
                <div class="transactions-table">
                    <table>
                        <thead>
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
        </div>

        <!-- Actions -->
        <div class="d-flex gap-3 justify-content-center">
            <a href="sales.php" class="btn btn-success px-4"><i class="bi bi-plus-circle me-1"></i> Add Sale</a>
            <a href="expense.php" class="btn btn-danger px-4"><i class="bi bi-wallet2 me-1"></i> Add Expense</a>
            <a href="report.php" class="btn btn-secondary px-4"><i class="bi bi-file-earmark-bar-graph me-1"></i> Generate Report</a>
        </div>
    </div>

</div>

<script>
// Welcome balls animation (same as admin_dashboard)
(function() {
  const banner = document.querySelector('.welcome-banner');
  const ballsContainer = document.querySelector('.welcome-balls');
  if (!banner || !ballsContainer) return;

  function getColors() {
    if (document.body.classList.contains('dark-mode')) {
      return ['#ffd200', '#1abc9c', '#56ccf2', '#23243a', '#fff'];
    } else {
      return ['#1abc9c', '#56ccf2', '#ffd200', '#3498db', '#fff'];
    }
  }

  ballsContainer.innerHTML = '';
  ballsContainer.style.position = 'absolute';
  ballsContainer.style.top = 0;
  ballsContainer.style.left = 0;
  ballsContainer.style.width = '100%';
  ballsContainer.style.height = '100%';
  ballsContainer.style.zIndex = 1;
  ballsContainer.style.pointerEvents = 'none';

  const balls = [];
  const colors = getColors();
  const numBalls = 7;
  for (let i = 0; i < numBalls; i++) {
    const ball = document.createElement('div');
    ball.className = 'welcome-ball';
    ball.style.position = 'absolute';
    ball.style.borderRadius = '50%';
    ball.style.opacity = '0.18';
    ball.style.background = colors[i % colors.length];
    ball.style.width = ball.style.height = (32 + Math.random() * 32) + 'px';
    ball.style.top = (10 + Math.random() * 60) + '%';
    ball.style.left = (5 + Math.random() * 85) + '%';
    ballsContainer.appendChild(ball);
    balls.push({
      el: ball,
      x: parseFloat(ball.style.left),
      y: parseFloat(ball.style.top),
      r: Math.random() * 0.5 + 0.2,
      dx: (Math.random() - 0.5) * 0.2,
      dy: (Math.random() - 0.5) * 0.2
    });
  }

  function animateBalls() {
    balls.forEach(ball => {
      ball.x += ball.dx;
      ball.y += ball.dy;
      if (ball.x < 0 || ball.x > 95) ball.dx *= -1;
      if (ball.y < 5 || ball.y > 80) ball.dy *= -1;
      ball.el.style.left = ball.x + '%';
      ball.el.style.top = ball.y + '%';
    });
    requestAnimationFrame(animateBalls);
  }
  animateBalls();

  window.addEventListener('storage', () => {
    const newColors = getColors();
    balls.forEach((ball, i) => {
      ball.el.style.background = newColors[i % newColors.length];
    });
  });
  document.getElementById('themeToggle')?.addEventListener('change', () => {
    const newColors = getColors();
    balls.forEach((ball, i) => {
      ball.el.style.background = newColors[i % newColors.length];
    });
  });
})();
</script>

<?php include '../includes/footer.php'; ?>
