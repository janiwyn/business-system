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
    <h2 class="mt-4">Edit Product</h2>

    <!-- Product selector -->
    <form method="get" class="mb-4">
        <label>Select Product to Edit:</label>
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
            <label>Product Name:</label>
            <input type="text" name="name" class="form-control" 
                   value="<?= htmlspecialchars($product['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Buying Price:</label>
            <input type="number" step="0.01" name="buying-price" class="form-control" 
                   value="<?= htmlspecialchars($product['buying-price']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Selling Price:</label>
            <input type="number" step="0.01" name="selling-price" class="form-control" 
                   value="<?= htmlspecialchars($product['selling-price']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Stock:</label>
            <input type="number" name="stock" class="form-control" 
                   value="<?= htmlspecialchars($product['stock']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" <?= $id == 0 ? 'disabled' : '' ?>>
            Update Product
        </button>
    </form>

<?php include '../includes/footer.php'; ?>
