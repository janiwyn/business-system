<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar_admin.php'; // Use admin sidebar
include '../includes/header.php';

$message = "";
$amount = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category   = mysqli_real_escape_string($conn, $_POST['category']);
    $branch_id  = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $amount     = floatval($_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $date       = $_POST['date'];
    $spent_by   = mysqli_real_escape_string($conn, $_POST['spent_by']);

    if (!empty($category) && !empty($amount) && !empty($date)) {
        $sql = "INSERT INTO expenses (category, `branch-id`, amount, description, date, `spent-by`) 
                VALUES ('$category', '$branch_id', $amount, '$description', '$date', '$spent_by')";
        if ($conn->query($sql)) {
            $message = "Expense added successfully.";
        } else {
            $message = "Error: " . $conn->error;
        }

        // Update profits table
        $currentDate = date("Y-m-d");
        $result = $conn->query("SELECT * FROM profits WHERE date='$currentDate'");
        $profit_result = $result->fetch_assoc();

        if ($profit_result) {
            $total_expenses = $profit_result['expenses'] + $amount;
            $net_profit = $profit_result['total'] - $total_expenses;

            $update_sql = "UPDATE profits SET expenses=$total_expenses, `net-profits`=$net_profit 
                           WHERE date='$currentDate'";
            $conn->query($update_sql);
        }
    } else {
        $message = "Please fill in all required fields.";
    }
}

// Filters
$branch_filter = $_GET['branch'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause for filters
$where = [];
if ($branch_filter) {
    $where[] = "e.`branch-id` = " . intval($branch_filter);
}
if ($date_from) {
    $where[] = "DATE(e.date) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $where[] = "DATE(e.date) <= '" . $conn->real_escape_string($date_to) . "'";
}
$whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";

// Pagination setup for expenses table
$items_per_page = 30;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Get total expenses count for pagination (filtered)
$count_result = $conn->query("SELECT COUNT(*) AS total FROM expenses e $whereClause");
$count_row = $count_result->fetch_assoc();
$total_items = $count_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Fetch expenses for current page (filtered)
$expenses = $conn->query("
    SELECT e.*, u.username, b.name AS branch_name
    FROM expenses e 
    LEFT JOIN users u ON e.`spent-by` = u.id 
    LEFT JOIN branch b ON e.`branch-id` = b.id
    $whereClause
    ORDER BY e.date DESC
    LIMIT $items_per_page OFFSET $offset
");

// Get total expenses (filtered)
$total_result = $conn->query("SELECT SUM(e.amount) AS total_expenses FROM expenses e $whereClause");
$total_data = $total_result->fetch_assoc();
$total_expenses = $total_data['total_expenses'] ?? 0;

// Fetch branches for filter
$branches = $conn->query("SELECT id, name FROM branch");
?>

<!-- Custom Styling -->
<style>
.card {
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s ease-in-out;
}
.card:hover {
    transform: translateY(-2px);
}
.card-header,
.title-card {
    color: #fff !important;
    background: var(--primary-color);
    font-weight: 600;
    border-radius: 12px 12px 0 0 !important;
    font-size: 1.1rem;
    letter-spacing: 1px;
}
body.dark-mode .card-header,
body.dark-mode .title-card {
    color: #fff !important;
    background-color: #2c3e50 !important;
}
body.dark-mode .card .card-header {
    color: #fff !important;
    background-color: #2c3e50 !important;
}
.form-control, .form-select, textarea {
    border-radius: 8px;
}
body.dark-mode .form-label,
body.dark-mode .fw-semibold,
body.dark-mode label,
body.dark-mode .card-body {
    color: #fff !important;
}
body.dark-mode .form-control,
body.dark-mode .form-select,
body.dark-mode textarea {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .form-control:focus,
body.dark-mode .form-select:focus,
body.dark-mode textarea:focus {
    background-color: #23243a !important;
    color: #fff !important;
}
.btn-primary {
    background: var(--primary-color) !important;
    border: none;
    border-radius: 8px;
    padding: 8px 18px;
    font-weight: 600;
    box-shadow: 0px 3px 8px rgba(0,0,0,0.2);
    color: #fff !important;
    transition: background 0.2s;
}
.btn-primary:hover, .btn-primary:focus {
    background: #159c8c !important;
    color: #fff !important;
}
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
/* Calendar icon light in dark mode */
body.dark-mode input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1);
}
body.dark-mode input[type="date"]::-moz-calendar-picker-indicator {
    filter: invert(1);
}
body.dark-mode input[type="date"]::-ms-input-placeholder {
    color: #fff !important;
}
body.dark-mode input[type="date"] {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
/* Expenses value in red */
.expense-value {
    color: #e74c3c !important;
    font-weight: 600;
}
.total-expenses-value {
    color: #e74c3c !important;
    font-weight: 700;
    font-size: 1.2rem;
}

/* Light mode: All Expenses title and icon color, filter area background, filter label color */
.card .card-header.bg-light {
    background-color: #f8f9fa !important;
    color: #222 !important;
    border-bottom: none;
}
.card .card-header.bg-light .title-card,
.card .card-header.bg-light .title-card i {
    color: var(--primary-color) !important;
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
}
.card .card-header.bg-light .title-card {
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    background: transparent !important;
}
.card .card-header.bg-light label,
.card .card-header.bg-light select,
.card .card-header.bg-light span {
    color: #222 !important;
}
.card .card-header.bg-light .form-select,
.card .card-header.bg-light input[type="date"] {
    color: #222 !important;
    background-color: #fff !important;
    border: 1px solid #dee2e6 !important;
}
.card .card-header.bg-light .form-select:focus,
.card .card-header.bg-light input[type="date"]:focus {
    color: #222 !important;
    background-color: #fff !important;
}

/* Dark mode: All Expenses title, icon, and filter labels white */
body.dark-mode .card .card-header.bg-light {
    background-color: #2c3e50 !important;
    color: #fff !important;
    border-bottom: none;
}
body.dark-mode .card .card-header.bg-light .title-card,
body.dark-mode .card .card-header.bg-light .title-card i {
    color: #fff !important;
}
body.dark-mode .card .card-header.bg-light label,
body.dark-mode .card .card-header.bg-light select,
body.dark-mode .card .card-header.bg-light span {
    color: #fff !important;
}
body.dark-mode .card .card-header.bg-light .form-select,
body.dark-mode .card .card-header.bg-light input[type="date"] {
    color: #fff !important;
    background-color: #23243a !important;
    border: 1px solid #444 !important;
}
body.dark-mode .card .card-header.bg-light .form-select:focus,
body.dark-mode .card .card-header.bg-light input[type="date"]:focus {
    color: #fff !important;
    background-color: #23243a !important;
}
</style>

<div class="container-fluid mt-5">
    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Add Expense Form -->
    <div class="card mb-4">
        <div class="card-header title-card">➕ Add New Expense</div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="category" class="form-label fw-semibold">Category *</label>
                        <input type="text" name="category" id="category" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="branch_id" class="form-label fw-semibold">Branch ID *</label>
                        <input type="text" name="branch_id" id="branch_id" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="amount" class="form-label fw-semibold">Amount *</label>
                        <input type="number" name="amount" step="0.01" id="amount" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="date" class="form-label fw-semibold">Date *</label>
                        <input type="date" name="date" id="date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="spent_by" class="form-label fw-semibold">Spent By *</label>
                        <select name="spent_by" id="spent_by" class="form-select" required>
                            <option value="">-- Select User --</option>
                            <?php
                            $users = $conn->query("SELECT id, username FROM users");
                            while ($u = $users->fetch_assoc()) {
                                echo "<option value='{$u['id']}'>{$u['username']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="1"></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">Add Expense</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Filter & Table -->
    <div class="card mb-5">
        <div class="card-header bg-light text-black d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
            <span class="fw-bold title-card"><i class="fa-solid fa-wallet"></i> All Expenses</span>
            <form method="GET" class="d-flex align-items-center flex-wrap gap-2" style="gap:1rem;">
                <label class="fw-bold me-2">From:</label>
                <input type="date" name="date_from" class="form-select me-2" value="<?= htmlspecialchars($date_from) ?>" style="width:150px;">
                <label class="fw-bold me-2">To:</label>
                <input type="date" name="date_to" class="form-select me-2" value="<?= htmlspecialchars($date_to) ?>" style="width:150px;">
                <label class="fw-bold me-2">Branch:</label>
                <select name="branch" class="form-select me-2" onchange="this.form.submit()" style="width:180px;">
                    <option value="">-- All Branches --</option>
                    <?php
                    $branches = $conn->query("SELECT id, name FROM branch");
                    while ($b = $branches->fetch_assoc()):
                        $selected = ($branch_filter == $b['id']) ? 'selected' : '';
                        echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                    endwhile;
                    ?>
                </select>
                <button type="submit" class="btn btn-primary ms-2">Filter</button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if (empty($branch_filter)) echo "<th>Branch</th>"; ?>
                            <th>Category</th>
                            <th>Amount (UGX)</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Spent By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($expenses->num_rows > 0): ?>
                            <?php $i = $offset + 1; while ($row = $expenses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <?php if (empty($branch_filter)) echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>"; ?>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><span class="expense-value"><?php echo number_format($row['amount'], 2); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><?php echo $row['date']; ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo empty($branch_filter) ? 7 : 6; ?>" class="text-center text-muted">No expenses recorded yet.</td>
                            </tr>
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
                            <a class="page-link" href="?page=<?= $p ?><?= ($branch_filter ? '&branch=' . $branch_filter : '') ?><?= ($date_from ? '&date_from=' . $date_from : '') ?><?= ($date_to ? '&date_to=' . $date_to : '') ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <!-- Total Expenses Sum -->
            <div class="mt-4 text-end">
                <h5 class="fw-bold">Total Expenses: <span class="total-expenses-value">UGX <?= number_format($total_expenses, 2) ?></span></h5>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
