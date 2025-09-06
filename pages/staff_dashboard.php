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
    $quantity   = $_POST['quantity'];

    // Fetch product details
    $stmt = $conn->prepare("SELECT name, `selling-price`, `buying-price`, `branch-id`, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    $currentDate = date("Y-m-d");

    // Fetch today's profit record
    $stmt = $conn->prepare("SELECT * FROM profits WHERE date = ?");
    $stmt->bind_param("s", $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $profit_result = $result->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $message = "⚠️ Product not found.";
    } elseif ($product['stock'] < $quantity) {
        $message = "⚠️ Not enough stock available!";
    } else {
        // Calculate amount, cost, profit
        $total_price  = $product['selling-price'] * $quantity;
        $cost_price   = $product['buying-price'] * $quantity;
        $total_profit = $total_price - $cost_price;
        $branch_id    = $product['branch-id'];

        // Insert into sales table
        $stmt = $conn->prepare("
            INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiididd", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit);

        if ($stmt->execute()) {
            // Update product stock
            $new_stock = $product['stock'] - $quantity;
            $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $update->bind_param("ii", $new_stock, $product_id);
            $update->execute();
            $update->close();

            $message = "✅ Sale recorded successfully!";

            // Stock threshold warning
            if ($new_stock < 10) {
                $message .= "<br>⚠️ Stock for <strong>" . htmlspecialchars($product['name']) . "</strong> is below the threshold ({$new_stock} left). Please restock!";
            }
        } else {
            $message = "❌ Error recording sale.";
        }
        $stmt->close();

        // Handle profits for today
        if ($profit_result) {
            $total_amount = $profit_result['total'] + $total_profit;
            $expenses     = $profit_result['expenses'] ?? 0;
            $net_profit   = $total_amount - $expenses;

            $stmt2 = $conn->prepare("
                UPDATE profits SET `branch-id`=?, total=?, `net-profits`=? WHERE date=?
            ");
            $stmt2->bind_param("idds", $branch_id, $total_amount, $net_profit, $currentDate);
            $stmt2->execute();
            $stmt2->close();
        } else {
            // No record for today exists, create one
            $total_amount = $total_profit;
            $net_profit   = $total_profit;
            $expenses     = 0;

            $stmt2 = $conn->prepare("
                INSERT INTO profits (`branch-id`, total, `net-profits`, expenses, date) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt2->bind_param("iddis", $branch_id, $total_amount, $net_profit, $expenses, $currentDate);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

// Fetch products for dropdown (include stock now)
$product_query = $conn->query("SELECT id, name, stock FROM products");

// Fetch recent sales
$sales_query = $conn->prepare("
    SELECT s.id, p.name, s.quantity, s.amount, s.total_profits, s.date 
    FROM sales s 
    JOIN products p ON s.`product-id` = p.id 
    WHERE s.`sold-by` = ? 
    ORDER BY s.date DESC 
    LIMIT 10
");
$sales_query->bind_param("i", $user_id);
$sales_query->execute();
$sales_result = $sales_query->get_result();

// Fetch low stock products for panel
$low_stock_query = $conn->query("SELECT name, stock FROM products WHERE stock < 10 ORDER BY stock ASC");
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #4e73df, #224abe);
    color: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    margin-bottom: 25px;
    animation: fadeInDown 0.8s ease-in-out;
}
.dashboard-header h3 { margin: 0; font-weight: 600; }
.card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: transform 0.2s ease; }
.card:hover { transform: translateY(-3px); }
table th { background: #f8f9fc; }
.low-stock { color: red; font-weight: bold; }
@keyframes fadeInDown { from {opacity: 0; transform: translateY(-20px);} to {opacity: 1; transform: translateY(0);} }
</style>

<div class="container mt-4">
    <div class="dashboard-header d-flex justify-content-between align-items-center">
        <h3>👋 Welcome, <?= htmlspecialchars($username); ?> </h3>
        <span class="small">Staff Dashboard</span>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info shadow-sm"><?= $message; ?></div>
    <?php endif; ?>

    <!-- Sale Entry Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">➕ Add Sale</div>
        <div class="card-body">
            <form method="POST" action="" class="row g-3">
                <div class="col-md-6">
                    <label for="product_id" class="form-label">Product</label>
                    <select class="form-select" name="product_id" id="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php while ($row = $product_query->fetch_assoc()): ?>
                            <?php if ($row['stock'] < 10): ?>
                                <option value="<?= $row['id']; ?>" class="low-stock">
                                    <?= htmlspecialchars($row['name']); ?> (Stock: <?= $row['stock']; ?> 🔴 Low)
                                </option>
                            <?php else: ?>
                                <option value="<?= $row['id']; ?>">
                                    <?= htmlspecialchars($row['name']); ?> (Stock: <?= $row['stock']; ?>)
                                </option>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="quantity" id="quantity" required min="1">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="add_sale" class="btn btn-success w-100">
                        <i class="bi bi-cart-check"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Low Stock Products Panel -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">⚠️ Low Stock Products (Below 10)</div>
        <div class="card-body">
            <?php if ($low_stock_query->num_rows > 0): ?>
                <ul class="list-group">
                    <?php while ($low = $low_stock_query->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($low['name']); ?>
                            <span class="badge bg-danger rounded-pill"><?= $low['stock']; ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted fst-italic">All products have sufficient stock.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Sales Table -->
    <div class="card">
        <div class="card-header bg-secondary text-white">📊 Recent Sales</div>
        <div class="card-body">
            <?php if ($sales_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
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
                                    <td><i class="bi bi-box"></i> <?= htmlspecialchars($sale['name']); ?></td>
                                    <td><?= $sale['quantity']; ?></td>
                                    <td><span class="badge bg-success">UGX <?= number_format($sale['amount'], 2); ?></span></td>
                                    <td><?= date("M d, Y H:i", strtotime($sale['date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted fst-italic">No sales recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
