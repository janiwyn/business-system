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

// Pagination setup for expenses table
$items_per_page = 30;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Get total expenses count for pagination
$count_result = $conn->query("SELECT COUNT(*) AS total FROM expenses");
$count_row = $count_result->fetch_assoc();
$total_items = $count_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Fetch expenses for current page
$expenses = $conn->query("
    SELECT e.*, u.username 
    FROM expenses e 
    LEFT JOIN users u ON e.`spent-by` = u.id 
    ORDER BY e.date DESC
    LIMIT $items_per_page OFFSET $offset
");

// Get total expenses
$total_result = $conn->query("SELECT SUM(amount) AS total_expenses FROM expenses");
$total_data = $total_result->fetch_assoc();
$total_expenses = $total_data['total_expenses'] ?? 0;
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

    <!-- Total Expenses -->
    <div class="alert alert-secondary">
        <strong>Total Expenses:</strong> UGX <?php echo number_format($total_expenses, 2); ?>
    </div>

    <!-- Expense Table -->
    <div class="card mb-5">
        <div class="card-header title-card">📋 All Expenses</div>
        <div class="card-body p-0">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
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
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><?php echo number_format($row['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><?php echo $row['date']; ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted">No expenses recorded yet.</td></tr>
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
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
