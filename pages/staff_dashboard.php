<?php
session_start();
include '../includes/db.php';
include '../includes/header.php';
include '../pages/sidebar.php';

// Redirect if not staff
if ($_SESSION['role'] !== 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = "";

// Handle Sale Form Submission
if (isset($_POST['add_sale'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];

    // Fetch product price
    $stmt = $conn->prepare("SELECT `selling-price` FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($price);
    $stmt->fetch();
    $stmt->close();

    if ($price) {
        $total_price = $price * $quantity;

        $stmt = $conn->prepare("INSERT INTO sales (`product-id`, quantity, amount, `sold-by`, date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iidi", $product_id, $quantity, $total_price, $user_id);
        if ($stmt->execute()) {
            $message = "Sale recorded successfully.";
        } else {
            $message = "Error recording sale.";
        }
        $stmt->close();
    } else {
        $message = "Product not found.";
    }
}

// Fetch Products
$product_query = $conn->query("SELECT id, name FROM products");

// Fetch Recent Sales
$sales_query = $conn->prepare("
    SELECT s.id, p.name, s.quantity, s.amount, s.date 
    FROM sales s 
    JOIN products p ON s.`product-id` = p.id 
    WHERE s.`sold-by` = ? 
    ORDER BY s.date DESC 
    LIMIT 10
");
$sales_query->bind_param("i", $user_id);
$sales_query->execute();
$sales_result = $sales_query->get_result();
?>




<div class="container mt-5">
    <h3 class="mb-4">Welcome, <?= htmlspecialchars($username); ?> </h3>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message; ?></div>
    <?php endif; ?>

    <!-- Sale Entry Form -->
    <div class="card mb-4">
        <div class="card-header">Add Sale</div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="product_id" class="form-label">Product</label>
                    <select class="form-select" name="product_id" id="product_id" required>
                        <option value="">Select a product</option>
                        <?php while ($row = $product_query->fetch_assoc()): ?>
                            <option value="<?= $row['id']; ?>"><?= htmlspecialchars($row['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="quantity" id="quantity" required min="1">
                </div>
                <button type="submit" name="add_sale" class="btn btn-primary">Add Sale</button>
            </form>
        </div>
    </div>

    <!-- Recent Sales Table -->
    <div class="card">
        <div class="card-header">Recent Sales</div>
        <div class="card-body">
            <?php if ($sales_result->num_rows > 0): ?>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sale = $sales_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $sale['id']; ?></td>
                                <td><?= htmlspecialchars($sale['name']); ?></td>
                                <td><?= $sale['quantity']; ?></td>
                                <td>UGX <?= number_format($sale['amount'], 2); ?></td>
                                <td><?= $sale['date']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No sales recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
    include '../includes/footer.php';

?>