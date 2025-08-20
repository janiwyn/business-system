<?php
include '../includes/auth.php';
require_role("staff","manager","admin");
include '../includes/db.php';
include '../includes/header.php';
include '../pages/sidebar.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get values from form (make sure names match DB column hyphenation)
    $product_id = $_POST["product-id"] ?? null;
    $quantity   = $_POST["quantity"] ?? null;
    $sold_by    = $_POST["sold-by"] ?? null;
    $branch_id  = 1; // for now, fixed branch

    // Get current stock, selling price, buying price
    $query = $conn->prepare("SELECT stock, `selling-price`, `buying-price` FROM products WHERE id = ?");
    $query->bind_param("i", $product_id);
    $query->execute();
    $result = $query->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        $message = "Product not found!";
    } elseif ($product['stock'] < $quantity) {
        $message = "Not enough stock available!";
    } else {
        $new_stock   = $product['stock'] - $quantity;
        $total_price = $product['selling-price'] * $quantity;
        $cost_price  = $product['buying-price'] * $quantity;

        // Insert into sales (use backticks for hyphenated columns)
        $insert = $conn->prepare("
            INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, date) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $insert->bind_param("iiidsd", $product_id, $branch_id, $quantity, $total_price, $sold_by, $cost_price);
        $insert->execute();

        // Update product stock
        $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $update->bind_param("ii", $new_stock, $product_id);
        $update->execute();

        $message = "Sale recorded successfully!";
    }
}

// Get product list for form
$products = $conn->query("SELECT id, name FROM products");
?>

<body class="bg-light">
    <div class="container mt-5">
        <h2 class="mb-4">Record a Sale</h2>

        <?php if ($message): ?>
            <div class="alert alert-info"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" action="sales.php">
            <div class="mb-3">
                <label class="form-label">Product</label>
                <select name="product-id" class="form-select" required>
                    <option value="">-- Select Product --</option>
                    <?php while ($row = $products->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= $row['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" required min="1">
            </div>

            <div class="mb-3">
                <label class="form-label">Sold By</label>
                <input type="text" name="sold-by" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-success">Submit Sale</button>
        </form>

        <h4 class="mt-5">Recent Sales</h4>
        <table class="table table-bordered">
            <thead>
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
                        <td><?= $row['product-name'] ?></td>
                        <td><?= $row['quantity'] ?></td>
                        <td><?= number_format($row['amount'], 2) ?></td>
                        <td><?= $row['date'] ?></td>
                        <td><?= $row['sold-by'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php include '../includes/footer.php'; ?>
