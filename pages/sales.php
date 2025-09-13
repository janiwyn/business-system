<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff"]);
include '../pages/sidebar_admin.php';
include '../includes/header.php';

$message = "";

// Logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Filters
$selected_branch = $_GET['branch'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause for filters
$where = [];
if ($user_role === 'staff') {
    $where[] = "sales.`branch-id` = $user_branch";
} elseif ($selected_branch) {
    $where[] = "sales.`branch-id` = " . intval($selected_branch);
}
if ($date_from) {
    $where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($date_to) . "'";
}
$whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";

// Pagination setup
$items_per_page = 60;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Count total sales for pagination
$count_query = "SELECT COUNT(*) as total FROM sales JOIN products ON sales.`product-id` = products.id $whereClause";
$total_result = $conn->query($count_query);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Fetch sales for current page
$sales_query = "
    SELECT sales.id, products.name AS `product-name`, sales.quantity, sales.amount, sales.`sold-by`, sales.date, branch.name AS branch_name
    FROM sales
    JOIN products ON sales.`product-id` = products.id
    JOIN branch ON sales.`branch-id` = branch.id
    $whereClause
    ORDER BY sales.id DESC
    LIMIT $items_per_page OFFSET $offset
";
$sales = $conn->query($sales_query);

// Fetch branches for admin/manager filter
$branches = ($user_role !== 'staff') ? $conn->query("SELECT id, name FROM branch") : [];

// Calculate total sum of sales (filtered)
$sum_query = "
    SELECT SUM(sales.amount) AS total_sales
    FROM sales
    JOIN products ON sales.`product-id` = products.id
    $whereClause
";
$sum_result = $conn->query($sum_query);
$sum_row = $sum_result->fetch_assoc();
$total_sales_sum = $sum_row['total_sales'] ?? 0;
?>

<div class="container-fluid mt-4">
    <div class="card mb-4 chart-card">
        <div class="card-header bg-light text-black d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
            <span class="fw-bold title-card"><i class="fa-solid fa-receipt"></i> Recent Sales</span>
            <form method="GET" class="d-flex align-items-center flex-wrap gap-2" style="gap:1rem;">
                <label class="fw-bold me-2">From:</label>
                <input type="date" name="date_from" class="form-select me-2" value="<?= htmlspecialchars($date_from) ?>" style="width:150px;">
                <label class="fw-bold me-2">To:</label>
                <input type="date" name="date_to" class="form-select me-2" value="<?= htmlspecialchars($date_to) ?>" style="width:150px;">
                <?php if ($user_role !== 'staff'): ?>
                <label class="fw-bold me-2">Branch:</label>
                <select name="branch" class="form-select me-2" onchange="this.form.submit()" style="width:180px;">
                    <option value="">-- All Branches --</option>
                    <?php
                    $branches = $conn->query("SELECT id, name FROM branch");
                    while ($b = $branches->fetch_assoc()):
                        $selected = ($selected_branch == $b['id']) ? 'selected' : '';
                        echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                    endwhile;
                    ?>
                </select>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary ms-2">Filter</button>
            </form>
        </div>
        <div class="card-body table-responsive">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if ($user_role !== 'staff' && empty($selected_branch)) echo "<th>Branch</th>"; ?>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Sold At</th>
                            <th>Sold By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = $offset + 1;
                        while ($row = $sales->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <?php if ($user_role !== 'staff' && empty($selected_branch)) echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>"; ?>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($row['product-name']) ?></span></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><span class="fw-bold text-success">$<?= number_format($row['amount'], 2) ?></span></td>
                                <td><small class="text-muted"><?= $row['date'] ?></small></td>
                                <td><?= htmlspecialchars($row['sold-by']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($sales->num_rows === 0): ?>
                            <tr><td colspan="<?= ($user_role !== 'staff' && empty($selected_branch)) ? 7 : 6 ?>" class="text-center text-muted">No sales found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-3">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?><?= ($selected_branch ? '&branch=' . $selected_branch : '') ?><?= ($date_from ? '&date_from=' . $date_from : '') ?><?= ($date_to ? '&date_to=' . $date_to : '') ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <!-- Total Sales Sum -->
            <div class="mt-4 text-end">
                <h5 class="fw-bold">Total Sales Value: <span class="text-success">$<?= number_format($total_sales_sum, 2) ?></span></h5>
            </div>
        </div>
    </div>
</div>

<style>
/* ...existing code... */
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
.title-card {
    color: var(--primary-color);
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0;
    text-align: left;
}
/* ...existing code... */
</style>

<?php include '../includes/footer.php'; ?>
