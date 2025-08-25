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
            $message = "✅ Sale recorded successfully!";
        } else {
            $message = "❌ Error recording sale.";
        }
        $stmt->close();
    } else {
        $message = "⚠️ Product not found.";
    }
}

$product_query = $conn->query("SELECT id, name FROM products");

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
    .dashboard-header h3 {
        margin: 0;
        font-weight: 600;
    }
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transition: transform 0.2s ease;
    }
    .card:hover {
        transform: translateY(-3px);
    }
    table th {
        background: #f8f9fc;
    }
    @keyframes fadeInDown {
        from {opacity: 0; transform: translateY(-20px);}
        to {opacity: 1; transform: translateY(0);}
    }
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
                            <option value="<?= $row['id']; ?>"><?= htmlspecialchars($row['name']); ?></option>
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
