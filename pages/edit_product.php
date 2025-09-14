<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(['manager','admin']);
include '../pages/sidebar.php';
include '../includes/header.php';

$product = [
    'name' => '',
    'buying-price' => '',
    'selling-price' => '',
    'stock' => ''
];

$id = 0;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // sanitize

    // Fetch product if id is given
    $query = "SELECT * FROM products WHERE id = $id";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();
    }
}

// Fetch all products for dropdown
$products_result = $conn->query("SELECT id, name FROM products ORDER BY name ASC");

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $id > 0) {
    $name = $conn->real_escape_string($_POST['name']);
    $buying_price = floatval($_POST['buying-price']);
    $selling_price = floatval($_POST['selling-price']);
    $stock = intval($_POST['stock']);

    $update = "
        UPDATE products 
        SET name = '$name', 
            `buying-price` = $buying_price, 
            `selling-price` = $selling_price, 
            stock = $stock 
        WHERE id = $id
    ";
if ($conn->query($update)) {
    echo "<script>window.location.href='product.php';</script>";
    exit;
} else {
    echo "Failed to update product: " . $conn->error;
}

}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Business System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container">
<style>
body {
    background: var(--bg-color);
    color: var(--text-color);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
}
.main-container {
    margin-left: 250px;
    padding: 2rem 1.5rem 2rem 1.5rem;
    min-height: 100vh;
    transition: margin-left 0.3s ease;
    max-width: 100vw;
}
@media (max-width: 768px) {
    .main-container { margin-left: 0; padding: 1rem; }
}
.card {
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s ease-in-out;
    background: var(--card-bg);
}
.card-header {
    font-weight: 600;
    background: var(--primary-color);
    color: #fff !important;
    border-radius: 12px 12px 0 0 !important;
    font-size: 1.1rem;
    letter-spacing: 1px;
}
body.dark-mode .card-header {
    background-color: #2c3e50 !important;
    color: #fff !important;
}
.form-control, .form-select {
    border-radius: 8px;
}
body.dark-mode .form-label,
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
</style>

<div class="main-container">
    <div class="card mb-4" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">Edit Product</div>
        <div class="card-body">
            <!-- Product selector -->
            <form method="get" class="mb-4">
                <label class="form-label fw-semibold">Select Product to Edit:</label>
                <select name="id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Choose a product --</option>
                    <?php while ($row = $products_result->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>" <?= ($row['id'] == $id ? 'selected' : '') ?>>
                            <?= htmlspecialchars($row['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>

            <!-- Product form -->
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Product Name:</label>
                    <input type="text" name="name" class="form-control" 
                        value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Buying Price:</label>
                    <input type="number" step="0.01" name="buying-price" class="form-control" 
                        value="<?= htmlspecialchars($product['buying-price']) ?>" required>
                </div>
                <div class="mb-3"></div>
                    <label class="form-label fw-semibold">Selling Price:</label>
                    <input type="number" step="0.01" name="selling-price" class="form-control" 
                        value="<?= htmlspecialchars($product['selling-price']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Stock:</label>
                    <input type="number" name="stock" class="form-control" 
                        value="<?= htmlspecialchars($product['stock']) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary" <?= $id == 0 ? 'disabled' : '' ?>>
                    Update Product
                </button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
