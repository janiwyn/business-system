<?php
include '../includes/auth.php';
require_role("staff","manager","admin");
include '../includes/db.php';
include '../includes/header.php';
include '../pages/sidebar.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST["product_id"];
    $quantity = $_POST["quantity"];
    $sold_by = $_POST["sold_by"];
    $branch_id = 1; 

    // Get current stock, selling price, buying price
    $query = $conn->prepare("SELECT stock, selling_price, buying_price FROM products WHERE id = ?");
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
        $total_price = $product['selling_price'] * $quantity;
        $cost_price = $product['buying_price'] * $quantity;

        // Insert into sales (AFTER validation!)
        $insert = $conn->prepare("INSERT INTO sales (product_id, branch_id, quantity, total_price, sold_by, cost_price, date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $insert->bind_param("iiidsd", $product_id, $branch_id, $quantity, $total_price, $sold_by, $cost_price);
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
                <th>Sold At</th>
                <th>sold_by</th>
                
            </tr>
        </thead>
        <tbody>
            <?php
            $sales = $conn->query("
                SELECT sales.id, products.name AS product_name, sales.quantity, sales.total_price,  sales.sold_by, sales.date
                FROM sales
                JOIN products ON sales.product_id = products.id
                ORDER BY sales.id DESC
                LIMIT 10
            ");
            $i = 1;
            while ($row = $sales->fetch_assoc()):
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $row['product_name'] ?></td>
                    <td><?= $row['quantity'] ?></td>
                    <td><?= number_format($row['total_price'], 2) ?></td>
                    <td><?= $row['date'] ?></td>

                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
<?php
    include '../includes/footer.php';
?>
