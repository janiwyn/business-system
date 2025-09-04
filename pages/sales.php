<?php
include '../includes/auth.php';
require_role("staff","manager","admin");
include '../includes/db.php';
include '../includes/header.php';
include '../pages/sidebar.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST["product_id"];
    $branch_id = $_POST["branch_id"] ?? 1; // Default branch_id = 1
    $quantity = $_POST["quantity"];
    $sold_by = $_POST["sold_by"];

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
        $new_stock = $product['stock'] - $quantity;
        $total_price = $product['selling-price'] * $quantity;
        $cost_price = $product['buying-price'] * $quantity;
        $profit = $total_price - $cost_price;

        // Insert into sales
        $insert = $conn->prepare("INSERT INTO `sales` 
            (`product-id`, `branch-id`, `quantity`, `amount`, `cost-price`, `sold-by`, date, `total-profit`) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
        $insert->bind_param("iiidisi", $product_id, $branch_id, $quantity, $total_price, $cost_price, $sold_by, $profit);
        $insert->execute();

        // Update product stock
        $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $update->bind_param("ii", $new_stock, $product_id);
        $update->execute();

        $message = "Sale recorded successfully!";
    }
}

// Get product list
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
            <select name="product_id" class="form-select" required>
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
            <input type="text" name="sold_by" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-success">Submit Sale</button>
    </form>

    <h4>Recent Sales</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Cost Price</th>
                <th>Profit</th>
                <th>Sold At</th>
                <th>Sold By</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sales = $conn->query("
                SELECT s.id, p.name AS product_name, s.`quantity`, s.`amount`, s.`cost-price`, 
                       s.`total-profit`, s.`sold-by`, s.`date`
                FROM `sales` s
                JOIN products p ON s.`product-id` = p.id
                ORDER BY s.id DESC
                LIMIT 10
            ");
            $i = 1;
            while ($row = $sales->fetch_assoc()):
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $row['product_name'] ?></td>
                    <td><?= $row['quantity'] ?></td>
                    <td><?= number_format($row['amount'], 2) ?></td>
                    <td><?= number_format($row['cost-price'], 2) ?></td>
                    <td><?= number_format($row['total-profit'], 2) ?></td>
                    <td><?= $row['date'] ?></td>
                    <td><?= $row['sold-by'] ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
<?php
    include '../includes/footer.php';
?>
