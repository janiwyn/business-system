<?php
// --- MOVE THIS BLOCK TO THE VERY TOP OF THE FILE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_supplier_products'])) {
    include '../includes/db.php'; // Ensure DB connection
    header('Content-Type: application/json');
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $products = [];
    if ($supplier_id > 0) {
        $res = $conn->query("SELECT id, product_name, unit_price FROM supplier_products WHERE supplier_id = $supplier_id ORDER BY product_name ASC");
        while ($row = $res->fetch_assoc()) $products[] = $row;
    }
    echo json_encode(['success'=>true, 'products'=>$products]);
    exit;
}

// --- BEGIN: Handle form submissions and redirects BEFORE any output ---
include '../includes/db.php';

$message = "";
$amount = 0;

// Fetch branches and suppliers for dropdowns
$branches_res = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");
$branches = $branches_res ? $branches_res->fetch_all(MYSQLI_ASSOC) : [];
$suppliers_res = $conn->query("SELECT id, name FROM suppliers ORDER BY name ASC");
$suppliers = $suppliers_res ? $suppliers_res->fetch_all(MYSQLI_ASSOC) : [];

// --- Fetch products for lookup (for table display) ---
$products_lookup = [];
$products_res = $conn->query("SELECT id, product_name FROM supplier_products");
while ($row = $products_res->fetch_assoc()) {
    $products_lookup[$row['id']] = $row['product_name'];
}

// Handle form submission (single product, only if cart_json is not set or empty)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !isset($_POST['fetch_supplier_products']) &&
    (empty($_POST['cart_json']) || $_POST['cart_json'] === '[]')
) {
    $category   = mysqli_real_escape_string($conn, $_POST['category']);
    $branch_id  = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $product    = mysqli_real_escape_string($conn, $_POST['product']);
    $quantity   = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $unit_price = isset($_POST['unit_price']) ? floatval($_POST['unit_price']) : 0;
    $amount     = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $date       = $_POST['date'];
    $spent_by   = mysqli_real_escape_string($conn, $_POST['spent_by']);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);

    if (!empty($category) && !empty($branch_id) && !empty($supplier_id) && !empty($product) && $quantity > 0 && $unit_price > 0 && !empty($date)) {
        // Insert into expenses
        $sql = "INSERT INTO expenses (category, `branch-id`, supplier_id, product, quantity, unit_price, amount, description, date, `spent-by`) 
                VALUES ('$category', '$branch_id', '$supplier_id', '$product', $quantity, $unit_price, $amount, '$description', '$date', '$spent_by')";
        if ($conn->query($sql)) {
            // Insert into supplier_transactions
            $products_res = $conn->query("SELECT product_name FROM supplier_products WHERE id = $product");
            $product_name = '';
            if ($products_res && $row = $products_res->fetch_assoc()) {
                $product_name = $row['product_name'];
            }
            $branch_name = '';
            $branch_res = $conn->query("SELECT name FROM branch WHERE id = $branch_id");
            if ($branch_res && $brow = $branch_res->fetch_assoc()) {
                $branch_name = $brow['name'];
            }
            $balance = $amount - $amount_paid;
            $now = date('Y-m-d H:i:s');
            $payment_method = '';
            $stmt = $conn->prepare("INSERT INTO supplier_transactions (supplier_id, date_time, branch, products_supplied, quantity, unit_price, amount, payment_method, amount_paid, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "isssiddsdd",
                $supplier_id,
                $now,
                $branch_name,
                $product_name,
                $quantity,
                $unit_price,
                $amount,
                $payment_method,
                $amount_paid,
                $balance
            );
            $stmt->execute();
            $stmt->close();
            // --- End supplier_transactions insert ---
            $message = "Expense added successfully.";
            // PRG pattern: redirect after successful insert
            header("Location: expense.php?added=1");
            exit;
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

// --- Handle form submission for multiple products (cart) ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !isset($_POST['fetch_supplier_products']) &&
    !empty($_POST['cart_json']) && $_POST['cart_json'] !== '[]'
) {
    $cart = json_decode($_POST['cart_json'] ?? '[]', true);
    $branch_id  = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $supplier_id = mysqli_real_escape_string($conn, $_POST['supplier_id']);
    $date       = $_POST['date'];
    $spent_by   = mysqli_real_escape_string($conn, $_POST['spent_by']);
    $category   = mysqli_real_escape_string($conn, $_POST['category']);
    // $description = mysqli_real_escape_string($conn, $_POST['description']);
    // For each cart item, insert into expenses and supplier_transactions
    if (is_array($cart) && count($cart) > 0) {
        foreach ($cart as $item) {
            $product    = mysqli_real_escape_string($conn, $item['product']);
            $quantity   = isset($item['quantity']) ? intval($item['quantity']) : 0;
            $unit_price = isset($item['unit_price']) ? floatval($item['unit_price']) : 0;
            $amount     = isset($item['amount']) ? floatval($item['amount']) : 0;
            $amount_paid = isset($item['amount_paid']) ? floatval($item['amount_paid']) : 0;
            // Insert into expenses
            $sql = "INSERT INTO expenses (category, `branch-id`, supplier_id, product, quantity, unit_price, amount, date, `spent-by`) 
                    VALUES ('$category', '$branch_id', '$supplier_id', '$product', $quantity, $unit_price, $amount, '$date', '$spent_by')";
            $conn->query($sql);
            // Insert into supplier_transactions
            $products_res = $conn->query("SELECT product_name FROM supplier_products WHERE id = $product");
            $product_name = '';
            if ($products_res && $row = $products_res->fetch_assoc()) {
                $product_name = $row['product_name'];
            }
            $branch_name = '';
            $branch_res = $conn->query("SELECT name FROM branch WHERE id = $branch_id");
            if ($branch_res && $brow = $branch_res->fetch_assoc()) {
                $branch_name = $brow['name'];
            }
            $balance = $amount - $amount_paid;
            $now = date('Y-m-d H:i:s');
            $payment_method = ''; // Set as needed
            $stmt = $conn->prepare("INSERT INTO supplier_transactions (supplier_id, date_time, branch, products_supplied, quantity, unit_price, amount, payment_method, amount_paid, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "isssiddsdd",
                $supplier_id,
                $now,
                $branch_name,
                $product_name,
                $quantity,
                $unit_price,
                $amount,
                $payment_method,
                $amount_paid,
                $balance
            );
            $stmt->execute();
            $stmt->close();
        }
        $message = "Expenses added successfully.";
        // PRG pattern: redirect after successful insert
        header("Location: expense.php?added=1");
        exit;
    } else {
        $message = "Please add at least one product to the cart.";
    }
}

// Show success message if redirected after creation
if (isset($_GET['added']) && $_GET['added'] == '1') {
    $message = "Expense(s) added successfully.";
}

// --- END: Handle form submissions and redirects BEFORE any output ---

include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

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
$expenses_res = $conn->query("
    SELECT e.*, u.username, b.name AS branch_name
    FROM expenses e 
    LEFT JOIN users u ON e.`spent-by` = u.id 
    LEFT JOIN branch b ON e.`branch-id` = b.id
    $whereClause
    ORDER BY e.date DESC
    LIMIT $items_per_page OFFSET $offset
");

// Convert expenses to array for reuse in both tables
$expenses_arr = [];
if ($expenses_res && $expenses_res->num_rows > 0) {
    while ($row = $expenses_res->fetch_assoc()) {
        $expenses_arr[] = $row;
    }
}

// Get total expenses (filtered)
$total_result = $conn->query("SELECT SUM(e.amount) AS total_expenses FROM expenses e $whereClause");
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

/* Make Add to Cart button match Add Expense button */
.add-to-cart-btn {
    background: var(--primary-color) !important;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    color: #fff !important;
    box-shadow: 0px 3px 8px rgba(0,0,0,0.2);
    transition: background 0.2s;
    font-size: 1rem;
    padding: 8px 18px;
}
.add-to-cart-btn:hover, .add-to-cart-btn:focus {
    background: #159c8c !important;
    color: #fff !important;
}
.add-to-cart-btn i {
    margin-right: 6px;
    font-size: 1.1em;
    vertical-align: middle;
}

/* Cart Table Styling */
.cart-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(44,62,80,0.08);
    margin-bottom: 1rem;
}
.cart-table thead {
    background: var(--primary-color);
    color: #fff;
    font-weight: 600;
    font-size: 14px;
    letter-spacing: 1px;
}
.cart-table th, .cart-table td {
    padding: 0.85rem 1rem;
    text-align: left;
    vertical-align: middle;
}
.cart-table tbody tr {
    background-color: #f8fafc;
    transition: background 0.2s;
}
.cart-table tbody tr:nth-child(even) {
    background-color: #eef2f7;
}
.cart-table tbody tr:hover {
    background-color: #e0f7fa;
}
.cart-table tfoot td {
    background: #f4f6f9;
    font-weight: bold;
    color: var(--primary-color);
    border-top: 2px solid #e0e0e0;
}
.cart-table .btn-danger {
    border-radius: 6px;
    font-size: 0.95rem;
    padding: 4px 14px;
    font-weight: 600;
    background: #e74c3c !important;
    border: none;
    transition: background 0.15s;
}
.cart-table .btn-danger:hover, .cart-table .btn-danger:focus {
    background: #c0392b !important;
}

/* Cart section header */
#cartSection h6 {
    font-size: 1.15rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}
#cartSection h6 i {
    margin-right: 8px;
    font-size: 1.2em;
    color: #1abc9c;
}
body.dark-mode .cart-table {
    background: #23243a;
    box-shadow: 0 2px 10px rgba(44,62,80,0.18);
}
body.dark-mode .cart-table thead {
    background-color: #1abc9c !important;
    color: #fff !important;
}
body.dark-mode .cart-table th, 
body.dark-mode .cart-table td {
    color: #fff !important;
    background-color: #23243a !important;
}
body.dark-mode .cart-table tbody tr {
    background-color: #2c2c3a !important;
}
body.dark-mode .cart-table tbody tr:nth-child(even) {
    background-color: #272734 !important;
}
body.dark-mode .cart-table tbody tr:hover {
    background-color: #1abc9c22 !important;
}
body.dark-mode .cart-table tfoot td {
    background: #23243a !important;
    color: #1abc9c !important;
    border-top: 2px solid #444 !important;
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
            <form method="post" id="addExpenseForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="category" class="form-label fw-semibold">Category *</label>
                        <input type="text" name="category" id="category" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="branch_id" class="form-label fw-semibold">Branch *</label>
                        <select name="branch_id" id="branch_id" class="form-select" required>
                            <option value="">Select branch</option>
                            <?php foreach($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="supplier_id" class="form-label fw-semibold">Supplier *</label>
                        <select name="supplier_id" id="supplier_id" class="form-select" required>
                            <option value="">Select supplier</option>
                            <?php foreach($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="product" class="form-label fw-semibold">Product *</label>
                        <select name="product" id="product" class="form-select">
                            <option value="">Select product</option>
                            <!-- Populated by JS -->
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="unit_price" class="form-label fw-semibold">Unit Price</label>
                        <input type="number" id="unit_price" class="form-control" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="quantity" class="form-label fw-semibold">Quantity *</label>
                        <input type="number" id="quantity" class="form-control" min="1">
                    </div>
                    <div class="col-md-2">
                        <label for="amount" class="form-label fw-semibold">Amount</label>
                        <input type="number" id="amount" class="form-control" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="amount_paid" class="form-label fw-semibold">Amount Paid</label>
                        <input type="number" id="amount_paid" class="form-control" min="0" step="0.01">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" id="addToCartBtn" class="btn btn-primary w-100 add-to-cart-btn" title="Add to Cart" style="display:flex;align-items:center;justify-content:center;">
        <!-- Use a Bootstrap or FontAwesome icon, fallback to SVG if not available -->
        <span style="font-size:1.4em;line-height:1;">
            <!-- Bootstrap icon (if using Bootstrap Icons) -->
            <i class="bi bi-cart-plus"></i>
            <!-- If Bootstrap Icons not loaded, fallback to SVG: -->
            <!--
            <svg xmlns="http://www.w3.org/2000/svg" width="1.4em" height="1.4em" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 7V4a.5.5 0 0 1 1 0v3h3a.5.5 0 0 1 0 1H9v3a.5.5 0 0 1-1 0V8H5a.5.5 0 0 1 0-1h3z"/>
                <path d="M0 1.5A.5.5 0 0 1 .5 1h1a.5.5 0 0 1 .485.379L2.89 5H14.5a.5.5 0 0 1 .49.598l-1.5 7A.5.5 0 0 1 13 13H4a.5.5 0 0 1-.491-.408L1.01 2H.5a.5.5 0 0 1-.5-.5zm3.14 4l1.25 6h7.22l1.25-6H3.14z"/>
            </svg>
            -->
        </span>
    </button>
                    </div>
                    <!-- <div class="col-md-4">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="1"></textarea>
                    </div> -->
                    <div class="col-md-2">
                        <label for="date" class="form-label fw-semibold">Date *</label>
                        <input type="date" name="date" id="date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
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
                </div>
                <!-- Cart Section -->
                <div id="cartSection" style="display:none; margin-top:1.5rem;">
                    <h6 style="font-size:1.15rem; font-weight:bold; color:var(--primary-color); margin-bottom:1rem;">
        <i class="bi bi-cart4"></i> Cart
    </h6>
                    <div class="table-responsive">
                        <table class="cart-table align-middle shadow-sm" style="border-radius:12px; overflow:hidden;">
                            <thead style="background:var(--primary-color);color:#fff;">
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Amount</th>
                                    <th>Amount Paid</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cartItems"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Total</td>
                                    <td id="cartTotal" class="fw-bold" style="color:#1abc9c;">0</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <input type="hidden" name="cart_json" id="cart_json">
                <div class="col-12 text-end mt-3">
                    <button type="submit" class="btn btn-primary">Add Expense</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filter & Table -->
    <ul class="nav nav-tabs mb-3" id="expensesTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expensesTab" type="button" role="tab" aria-controls="expensesTab" aria-selected="true">
                Expenses
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="total-expenses-tab" data-bs-toggle="tab" data-bs-target="#totalExpensesTab" type="button" role="tab" aria-controls="totalExpensesTab" aria-selected="false">
                Total Expenses
            </button>
        </li>
    </ul>
    <div class="tab-content" id="expensesTabsContent">
        <!-- Expenses Tab -->
        <div class="tab-pane fade show active" id="expensesTab" role="tabpanel" aria-labelledby="expenses-tab">
            <!-- Card wrapper for small devices -->
            <div class="d-block d-md-none mb-4">
                <div class="card transactions-card">
                    <div class="card-body">
                        <!-- Report Button: icon for small, full for md+ -->
                        <button type="button" class="btn btn-success mb-3 d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('expenses')">
                            <i class="fa fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-success mb-3 d-none d-md-inline-flex" onclick="openReportGen('expenses')">
                            <i class="fa fa-file-pdf"></i> Generate Report
                        </button>
                        <!-- Filter tools (smaller on small devices) -->
                        <form method="GET" class="expenses-filter-form d-flex align-items-center flex-wrap gap-2 mb-3">
                            <label class="fw-bold me-2">From:</label>
                            <input type="date" name="date_from" class="form-select me-2" value="<?= htmlspecialchars($date_from) ?>" style="width:110px;">
                            <label class="fw-bold me-2">To:</label>
                            <input type="date" name="date_to" class="form-select me-2" value="<?= htmlspecialchars($date_to) ?>" style="width:110px;">
                            <label class="fw-bold me-2">Branch:</label>
                            <select name="branch" class="form-select me-2" onchange="this.form.submit()" style="width:120px;">
                                <option value="">-- All Branches --</option>
                                <?php
                                $branches = $conn->query("SELECT id, name FROM branch");
                                while ($b = $branches->fetch_assoc()):
                                    $selected = ($branch_filter == $b['id']) ? 'selected' : '';
                                    echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                                endwhile;
                                ?>
                            </select>
                            <button type="submit" class="btn btn-primary ms-2" style="padding: 4px 12px; font-size: 0.95rem;">Filter</button>
                        </form>
                        <div class="table-responsive-sm">
                            <div class="transactions-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date & Time</th>
                                            <th>Supplier</th>
                                            <th>Branch</th>
                                            <th>Category</th>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Amount Expected</th>
                                            <th>Spent By</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($expenses_arr) > 0): ?>
                                            <?php foreach ($expenses_arr as $row): ?>
                                                <tr>
                                                    <td><?= isset($row['id']) ? htmlspecialchars($row['id']) : '' ?></td>
                                                    <td><?= isset($row['date']) ? htmlspecialchars($row['date']) : '' ?></td>
                                                    <td>
                                                        <?php
                                                        $sup_name = '';
                                                        if (isset($row['supplier_id'])) {
                                                          foreach ($suppliers as $sup) {
                                                            if ($sup['id'] == $row['supplier_id']) {
                                                              $sup_name = $sup['name'];
                                                              break;
                                                          }
                                                        }
                                                        }
                                                        echo htmlspecialchars($sup_name);
                                                        ?>
                                                    </td>
                                                    <td><?= isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '' ?></td>
                                                    <td><?= isset($row['category']) ? htmlspecialchars($row['category']) : '' ?></td>
                                                    <td>
                                                        <?php
                                                        $prod_name = (isset($row['product']) && isset($products_lookup[$row['product']])) ? $products_lookup[$row['product']] : '';
                                                        echo htmlspecialchars($prod_name);
                                                        ?>
                                                    </td>
                                                    <td><?= isset($row['quantity']) ? htmlspecialchars($row['quantity']) : '' ?></td>
                                                    <td>UGX<?= isset($row['unit_price']) ? number_format($row['unit_price'], 2) : '0.00' ?></td>
                                                    <td>UGX<?= isset($row['amount']) ? number_format($row['amount'], 2) : '0.00' ?></td>
                                                    <td><?= isset($row['username']) ? htmlspecialchars($row['username']) : '' ?></td>
                                                    <td><?= isset($row['description']) ? htmlspecialchars($row['description']) : '' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center text-muted">No expenses recorded yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table for medium and large devices -->
            <div class="card mb-5 d-none d-md-block">
                <div class="card-header bg-light text-black d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span class="fw-bold title-card"><i class="fa-solid fa-wallet"></i> All Expenses</span>
                    <button type="button" class="btn btn-success d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('expenses')">
                        <i class="fa fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-success d-none d-md-inline-flex" onclick="openReportGen('expenses')">
                        <i class="fa fa-file-pdf"></i> Generate Report
                    </button>
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
                                <th>ID</th>
                                <th>Date & Time</th>
                                <th>Supplier</th>
                                <th>Branch</th>
                                <th>Category</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Amount Expected</th>
                                <th>Spent By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($expenses_arr) > 0): ?>
                                <?php foreach ($expenses_arr as $row): ?>
                                    <tr>
                                        <td><?= isset($row['id']) ? htmlspecialchars($row['id']) : '' ?></td>
                                        <td><?= isset($row['date']) ? htmlspecialchars($row['date']) : '' ?></td>
                                        <td>
                                            <?php
                                            $sup_name = '';
                                            if (isset($row['supplier_id'])) {
                                                foreach ($suppliers as $sup) {
                                                    if ($sup['id'] == $row['supplier_id']) {
                                                        $sup_name = $sup['name'];
                                                        break;
                                                    }
                                                }
                                            }
                                            echo htmlspecialchars($sup_name);
                                            ?>
                                        </td>
                                        <td><?= isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '' ?></td>
                                        <td><?= isset($row['category']) ? htmlspecialchars($row['category']) : '' ?></td>
                                        <td>
                                            <?php
                                            $prod_name = (isset($row['product']) && isset($products_lookup[$row['product']])) ? $products_lookup[$row['product']] : '';
                                            echo htmlspecialchars($prod_name);
                                            ?>
                                        </td>
                                        <td><?= isset($row['quantity']) ? htmlspecialchars($row['quantity']) : '' ?></td>
                                        <td>UGX<?= isset($row['unit_price']) ? number_format($row['unit_price'], 2) : '0.00' ?></td>
                                        <td>UGX<?= isset($row['amount']) ? number_format($row['amount'], 2) : '0.00' ?></td>
                                        <td><?= isset($row['username']) ? htmlspecialchars($row['username']) : '' ?></td>
                                        <td><?= isset($row['description']) ? htmlspecialchars($row['description']) : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted">No expenses recorded yet.</td>
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
        <!-- Total Expenses Tab -->
        <div class="tab-pane fade" id="totalExpensesTab" role="tabpanel" aria-labelledby="total-expenses-tab">
            <div class="card mb-5">
                <div class="card-header bg-light text-black" style="border-radius:12px 12px 0 0;">
                    <span class="fw-bold title-card"><i class="fa-solid fa-calculator"></i> Total Expenses</span>
                    <button type="button" class="btn btn-success d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('total_expenses')">
                        <i class="fa fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-success d-none d-md-inline-flex" onclick="openReportGen('total_expenses')">
                        <i class="fa fa-file-pdf"></i> Generate Report
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Expenses</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Query: group by date and branch, sum(amount)
                                $totals_res = $conn->query("
                                    SELECT DATE(e.date) as expense_date, b.name as branch_name, COUNT(e.id) as expenses_count, SUM(e.amount) as total_expenses
                                    FROM expenses e
                                    LEFT JOIN branch b ON e.`branch-id` = b.id
                                    $whereClause
                                    GROUP BY expense_date, branch_name
                                    ORDER BY expense_date DESC, branch_name ASC
                                ");
                                $grand_total = 0;
                                if ($totals_res && $totals_res->num_rows > 0):
                                    while ($row = $totals_res->fetch_assoc()):
                                        $grand_total += $row['total_expenses'];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['expense_date']) ?></td>
                                        <td><?= htmlspecialchars($row['branch_name']) ?></td>
                                        <td><?= htmlspecialchars($row['expenses_count']) ?></td>
                                        <td class="text-end">UGX <?= number_format($row['total_expenses'], 2) ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No expense totals found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 text-end">
                        <h5 class="fw-bold">Grand Total: <span class="total-expenses-value">UGX <?= number_format($grand_total, 2) ?></span></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for report generation -->
<div class="modal fade" id="reportGenModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="reportGenForm">
      <div class="modal-header">
        <h5 class="modal-title" id="reportGenModalTitle">Generate Expenses Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">From</label>
          <input type="date" name="date_from" id="report_date_from" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">To</label>
          <input type="date" name="date_to" id="report_date_to" class="form-control" required>
        </div>
        <div class="col-md-12">
          <label class="form-label">Branch</label>
          <select name="branch" id="report_branch" class="form-select">
            <option value="">All Branches</option>
            <?php foreach($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Generate & Print</button>
      </div>
    </form>
  </div>
</div>

<?php
include '../includes/footer.php';
?>

<script>
// Populate products dropdown and unit price when supplier is selected
document.getElementById('supplier_id').addEventListener('change', function() {
    const supplierId = this.value;
    const productSelect = document.getElementById('product');
    productSelect.innerHTML = '<option value="">Select product</option>';
    document.getElementById('unit_price').value = '';
    document.getElementById('quantity').value = '';
    document.getElementById('amount').value = '';
    if (!supplierId) return;
    // Fetch products for this supplier via AJAX
    fetch('expense.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'fetch_supplier_products=1&supplier_id=' + encodeURIComponent(supplierId)
    }).then(res => res.json()).then(data => {
        if (data.success && Array.isArray(data.products)) {
            data.products.forEach(p => {
                productSelect.innerHTML += `<option value="${p.id}" data-unit_price="${p.unit_price}">${p.product_name}</option>`;
            });
        }
    });
});

// When product is selected, show unit price
document.getElementById('product').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const unitPrice = selected.getAttribute('data-unit_price') || '';
    document.getElementById('unit_price').value = unitPrice;
    document.getElementById('quantity').value = '';
    document.getElementById('amount').value = '';
});

// Calculate amount when quantity changes
document.getElementById('quantity').addEventListener('input', function() {
    const qty = parseFloat(this.value) || 0;
    const unitPrice = parseFloat(document.getElementById('unit_price').value) || 0;
    document.getElementById('amount').value = (qty * unitPrice).toFixed(2);
});

let cart = [];

function updateCartTable() {
    const cartSection = document.getElementById('cartSection');
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');
    if (cart.length === 0) {
        cartSection.style.display = 'none';
        cartItems.innerHTML = '';
        cartTotal.textContent = '0';
        return;
    }
    cartSection.style.display = '';
    let total = 0;
    cartItems.innerHTML = '';
    cart.forEach((item, idx) => {
        total += parseFloat(item.amount || 0);
        cartItems.innerHTML += `
            <tr>
                <td>${item.product_name}</td>
                <td>${item.quantity}</td>
                <td>UGX ${parseFloat(item.unit_price).toFixed(2)}</td>
                <td>UGX ${parseFloat(item.amount).toFixed(2)}</td>
                <td>UGX ${parseFloat(item.amount_paid).toFixed(2)}</td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeCartItem(${idx})">Remove</button></td>
            </tr>
        `;
    });
    cartTotal.textContent = 'UGX ' + total.toFixed(2);
}

function removeCartItem(idx) {
    cart.splice(idx, 1);
    updateCartTable();
}

document.getElementById('addToCartBtn').addEventListener('click', function() {
    const productSelect = document.getElementById('product');
    const productId = productSelect.value;
    const productName = productSelect.options[productSelect.selectedIndex]?.text || '';
    const unitPrice = parseFloat(document.getElementById('unit_price').value) || 0;
    const quantity = parseInt(document.getElementById('quantity').value) || 0;
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
    if (!productId || quantity <= 0 || unitPrice <= 0) {
        alert('Please select a product and enter valid quantity and unit price.');
        return;
    }
    cart.push({
        product: productId,
        product_name: productName,
        quantity: quantity,
        unit_price: unitPrice,
        amount: amount,
        amount_paid: amountPaid
    });
    updateCartTable();
    // Reset product fields
    productSelect.selectedIndex = 0;
    document.getElementById('unit_price').value = '';
    document.getElementById('quantity').value = '';
    document.getElementById('amount').value = '';
    document.getElementById('amount_paid').value = '';
});

document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        alert('Please add at least one product to the cart.');
        e.preventDefault();
        return false;
    }
    document.getElementById('cart_json').value = JSON.stringify(cart);
    // Allow form to submit
});

function openReportGen(type) {
    // Set modal title
    document.getElementById('reportGenModalTitle').textContent =
        type === 'expenses' ? 'Generate Expenses Report' : 'Generate Total Expenses Report';
    // Store type for submit
    document.getElementById('reportGenForm').dataset.reportType = type;
    // Show modal
    new bootstrap.Modal(document.getElementById('reportGenModal')).show();
}

document.getElementById('reportGenForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const type = this.dataset.reportType || 'expenses';
    const date_from = document.getElementById('report_date_from').value;
    const date_to = document.getElementById('report_date_to').value;
    const branch = document.getElementById('report_branch').value;
    const url = `reports_generator.php?type=${encodeURIComponent(type)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&branch=${encodeURIComponent(branch)}`;
    window.open(url, '_blank');
    bootstrap.Modal.getInstance(document.getElementById('reportGenModal')).hide();
});
</script>
