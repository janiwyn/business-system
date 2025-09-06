<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff"]);
include '../pages/sidebar.php';
include '../includes/header.php';

$message = "";

// Logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Handle sale submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST["product-id"] ?? null;
    $quantity   = $_POST["quantity"] ?? null;
    $sold_by    = $_POST["sold-by"] ?? null;

    // Staff can only sell in their branch
    $branch_id  = ($user_role === 'staff') ? $user_branch : ($_POST['branch-id'] ?? 1);

    // Fetch product details
    $query = $conn->prepare("SELECT stock, `selling-price`, `buying-price` FROM products WHERE id = ? AND (? IS NULL OR `branch-id` = ?)");
    $query->bind_param("iii", $product_id, $branch_id, $branch_id);
    $query->execute();
    $result = $query->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        $message = "❌ Product not found or not in this branch!";
    } elseif ($product['stock'] < $quantity) {
        $message = "⚠️ Not enough stock available!";
    } else {
        $new_stock   = $product['stock'] - $quantity;
        $total_price = $product['selling-price'] * $quantity;
        $cost_price  = $product['buying-price'] * $quantity;

        $insert = $conn->prepare("
            INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, date) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $insert->bind_param("iiidsd", $product_id, $branch_id, $quantity, $total_price, $sold_by, $cost_price);
        $insert->execute();

        $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $update->bind_param("ii", $new_stock, $product_id);
        $update->execute();

        $message = "✅ Sale recorded successfully!";
    }
}

// Branch filter for admin/manager
$selected_branch = '';
$whereClause = '';
if ($user_role === 'staff') {
    $whereClause = "WHERE sales.`branch-id` = $user_branch";
} else {
    $selected_branch = $_GET['branch'] ?? '';
    if ($selected_branch) {
        $whereClause = "WHERE sales.`branch-id` = ".intval($selected_branch);
    }
}

// Fetch sales
$sales_query = "
    SELECT sales.id, products.name AS `product-name`, sales.quantity, sales.amount, sales.`sold-by`, sales.date, branch.name AS branch_name
    FROM sales
    JOIN products ON sales.`product-id` = products.id
    JOIN branch ON sales.`branch-id` = branch.id
    $whereClause
    ORDER BY sales.id DESC
    LIMIT 10
";
$sales = $conn->query($sales_query);

// Fetch products for dropdown
if ($user_role === 'staff') {
    $products = $conn->query("SELECT id, name FROM products WHERE `branch-id` = $user_branch");
} else {
    $products = $conn->query("SELECT id, name FROM products");
}

// Fetch branches for admin/manager filter
$branches = ($user_role !== 'staff') ? $conn->query("SELECT id, name FROM branch") : [];
?>

<body class="bg-light">
<div class="container mt-5">

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-info text-center fw-bold"><?= $message ?></div>
    <?php endif; ?>

    <!-- Card for form -->
    <div class="card shadow-lg border-0 rounded-4 p-4">
        <h3 class="text-center mb-4">
            <i class="bi bi-cash-coin text-success"></i> 🛒 Record a Sale
        </h3>

        <form method="POST" action="sales.php">
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="bi bi-box-seam"></i> Product</label>
                <select name="product-id" class="form-select" required>
                    <option value="">-- Select Product --</option>
                    <?php while ($row = $products->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= $row['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <?php if ($user_role !== 'staff'): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="bi bi-building"></i> Branch</label>
                <select name="branch-id" class="form-select" required>
                    <?php while ($b = $branches->fetch_assoc()): ?>
                        <option value="<?= $b['id'] ?>" <?= ($selected_branch == $b['id']) ? 'selected' : '' ?>><?= $b['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="bi bi-cart-check"></i> Quantity</label>
                <input type="number" name="quantity" class="form-control" required min="1">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold"><i class="bi bi-person-badge"></i> Sold By</label>
                <input type="text" name="sold-by" class="form-control" required>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-success btn-lg rounded-3 shadow">
                    <i class="bi bi-check-circle-fill"></i> Submit Sale
                </button>
            </div>
        </form>
    </div>

    <!-- Recent sales table -->
    <div class="card shadow mt-5 border-0 rounded-4">
        <div class="card-header bg-success text-white fw-bold rounded-top-4 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt-cutoff"></i> Recent Sales</span>
            <?php if ($user_role !== 'staff'): ?>
            <form method="GET" class="d-flex align-items-center">
                <label class="me-2 fw-bold">Filter by Branch:</label>
                <select name="branch" class="form-control" onchange="this.form.submit()">
                    <option value="">-- All Branches --</option>
                    <?php
                    $branches = $conn->query("SELECT id, name FROM branch");
                    while ($b = $branches->fetch_assoc()):
                        $selected = ($selected_branch == $b['id']) ? 'selected' : '';
                        echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                    endwhile;
                    ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <table class="table table-hover align-middle text-center">
                <thead class="table-light">
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
                    $i = 1;
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
    </div>
</div>

<?php include '../includes/footer.php'; ?>
