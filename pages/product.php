<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff"]);
include '../pages/sidebar.php';
include '../includes/header.php';

$message = "";
$expiring_products = []; // add this near the top of your PHP file


// Get logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Add product form
if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category = trim($_POST['category'] ?? "");
    $price = trim($_POST['price'] ?? "");
    $cost = trim($_POST['cost'] ?? "");
    $stock = trim($_POST['stock'] ?? "");
    $branch_id = $_POST['branch_id'];

    $stmt = $conn->prepare("INSERT INTO products (name, `selling-price`, `buying-price`, stock, `branch-id`) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sddii", $name, $price, $cost, $stock, $branch_id);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success shadow-sm'> Product added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger shadow-sm'> Error adding product: " . $stmt->error . "</div>";
    }
}

// ==========================
// Pagination setup
// ==========================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Branch filter
$where = "";
if (!empty($_GET['branch'])) {
    $selected_branch = (int)$_GET['branch'];
    $where = "WHERE products.`branch-id` = $selected_branch";
} else {
    $selected_branch = null;
}

// Count products
$countRes = $conn->query("SELECT COUNT(*) AS total FROM products $where");
$total_products = ($countRes->fetch_assoc())['total'] ?? 0;
$total_pages = ceil($total_products / $limit);

// Fetch products with branch name
$result = $conn->query("
    SELECT products.*, branch.name AS branch_name 
    FROM products 
    JOIN branch ON products.`branch-id` = branch.id 
    $where 
    ORDER BY products.id DESC 
    LIMIT $offset,$limit
");
?>

<!-- Custom Styling -->
<style>
    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 2rem;
        text-align: center;
        letter-spacing: 1px;
        /* animation: fadeInDown 0.8s; */
    }
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
    }
    .card-header {
        font-weight: 600;
        background: var(--primary-color);
        color: #fff;
        border-radius: 12px 12px 0 0 !important;
        font-size: 1.1rem;
        letter-spacing: 1px;
    }
    .form-control, .form-select {
        border-radius: 8px;
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
    .btn-warning, .btn-danger {
        border-radius: 6px;
        font-size: 13px;
        padding: 5px 12px;
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
    body.dark-mode .card-header,
    body.dark-mode .title-card {
        color: #fff !important;
        background-color: #2c3e50 !important;
    }
    body.dark-mode .card .card-header {
        color: #fff !important;
        background-color: #2c3e50 !important;
    }
    body.dark-mode .form-label,
    body.dark-mode .fw-semibold,
    body.dark-mode label,
    body.dark-mode .card-body {
        color: #fff !important;
    }
    body.dark-mode .form-control,
    body.dark-mode .form-select {
        background-color: #23243a !important;
        color: #fff !important;
        border: 1px solid #444 !important;
    }
    body.dark-mode .form-control:focus,
    body.dark-mode .form-select:focus {
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
</style>

<div class="container mt-5">

    <div class="card mb-4">
        <div class="card-header title-card">➕ Add New Product</div>
        <div class="card-body">
            <?= isset($message) ? $message : "" ?>
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="name" class="form-label fw-semibold">Product Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Coca-Cola 500ml" required>
                    </div>
                    <div class="col-md-3">
                        <label for="price" class="form-label fw-semibold">Selling Price</label>
                        <input type="number" step="0.01" name="price" id="price" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label for="cost" class="form-label fw-semibold">Buying Price</label>
                        <input type="number" step="0.01" name="cost" id="cost" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label for="stock" class="form-label fw-semibold">Stock Quantity</label>
                        <input type="number" name="stock" id="stock" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-md-3">
                        <label for="branch" class="form-label fw-semibold">Branch</label>
                        <select name="branch_id" id="branch" class="form-select" required>
                            <option value="">-- Select Branch --</option>
                            <?php
                            $branches = $conn->query("SELECT id, name FROM branch");
                            while ($b = $branches->fetch_assoc()) {
                                echo "<option value='{$b['id']}'>" . htmlspecialchars($b['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" name="add_product" class="btn btn-primary">➕ Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Product List -->
    <div class="card mb-5">
        <div class="card-header d-flex justify-content-between align-items-center title-card">
            <span>📋 Product List</span>
            <?php if ($user_role !== 'staff'): ?>
            <form method="GET" class="d-flex align-items-center">
                <label class="me-2 fw-bold">Filter by Branch:</label>
                <select name="branch" class="form-select" onchange="this.form.submit()">
                    <option value="">-- All Branches --</option>
                    <?php
                    $branches = $conn->query("SELECT id, name FROM branch");
                    while ($b = $branches->fetch_assoc()) {
                        $selected = ($selected_branch == $b['id']) ? "selected" : "";
                        echo "<option value='{$b['id']}' $selected>" . htmlspecialchars($b['name']) . "</option>";
                    }
                    ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                           
    <th>#</th>
    <?php if (empty($selected_branch) && $user_role !== 'staff') echo "<th>Branch</th>"; ?>
    <th>Name</th>
    <th>Selling Price</th>
    <th>Buying Price</th>
    <th>Stock</th>
    <th>Expiry Date</th> <!-- new column -->
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php
if ($result->num_rows > 0) {
    $i = $offset + 1;
    while ($row = $result->fetch_assoc()) {
        // Highlight expiring products
        $highlight = "";
        foreach($expiring_products as $exp){
            if($row['id'] == $exp['id']){
                $highlight = "style='background-color: #ffcccc;'"; // light red
                break;
            }
        }

        echo "<tr $highlight>
            <td>{$i}</td>";
        if (empty($selected_branch) && $user_role !== 'staff') {
            echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
        }
        echo "<td>" . htmlspecialchars($row['name']) . "</td>
            <td>UGX " . number_format($row['selling-price'], 2) . "</td>
            <td>UGX " . number_format($row['buying-price'], 2) . "</td>
            <td>{$row['stock']}</td>
            <td>{$row['expiry_date']}</td> <!-- show expiry -->
            <td>
                <a href='edit_product.php?id={$row['id']}' class='btn btn-sm btn-warning me-1'>✏️ Edit</a>
                <a href='delete_product.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this product?\")'>🗑️ Delete</a>
            </td>
        </tr>";
        $i++;
    }
} else {
    $colspan = (empty($selected_branch) && $user_role !== 'staff') ? 8 : 7; // adjust for new column
    echo "<tr><td colspan='$colspan' class='text-center text-muted'>No products found.</td></tr>";
}
?>
</tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-3">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?><?= ($selected_branch ? '&branch=' . $selected_branch : '') ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
