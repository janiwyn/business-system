<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin"]);
include '../pages/sidebar.php';
include '../includes/header.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST["product-id"] ?? null;
    $quantity   = $_POST["quantity"] ?? null;
    $sold_by    = $_POST["sold-by"] ?? null;
    $branch_id  = 1; // static branch for now

    $query = $conn->prepare("SELECT stock, `selling-price`, `buying-price` FROM products WHERE id = ?");
    $query->bind_param("i", $product_id);
    $query->execute();
    $result = $query->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        $message = "❌ Product not found!";
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

    }
}

$products = $conn->query("SELECT id, name FROM products");
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
        <div class="card-header bg-success text-white fw-bold rounded-top-4">
            <i class="bi bi-receipt-cutoff"></i> Recent Sales
        </div>
        <div class="card-body">
            <table class="table table-hover align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Sold At</th>
                        <th>Sold By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sales = $conn->query("
                        SELECT sales.id, products.name AS `product-name`, sales.quantity, sales.amount, sales.`sold-by`, sales.date
                        FROM sales
                        JOIN products ON sales.`product-id` = products.id
                        ORDER BY sales.id DESC
                        LIMIT 10
                    ");
                    $i = 1;
                    while ($row = $sales->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><span class="badge bg-primary"><?= $row['product-name'] ?></span></td>
                            <td><?= $row['quantity'] ?></td>
                            <td><span class="fw-bold text-success">$<?= number_format($row['amount'], 2) ?></span></td>
                            <td><small class="text-muted"><?= $row['date'] ?></small></td>
                            <td><?= $row['sold-by'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
