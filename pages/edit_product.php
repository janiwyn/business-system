<?php
include '../includes/db.php';
include '../includes/header.php';
include '../includes/auth.php';
require_role("manager", "admin");
include '../pages/sidebar.php';

if (!isset($_GET['id'])) {
    echo "No product selected.";
    exit;
}

$id = $_GET['id'];
$query = $conn->prepare("SELECT * FROM products WHERE id = ?");
$query->bind_param("i", $id);
$query->execute();
$result = $query->get_result();
$product = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $price = $_POST['selling-price'];
    $cost = $_POST['buying-price'];
    $stock = $_POST['stock'];
    
    $stmt = $conn->prepare("UPDATE products SET name=?, buying_price=?, selling_price=?, stock=? WHERE id=?");
    $stmt->bind_param("sddii", $name, $buying_price, $selling_price, $stock, $id);

    if ($stmt->execute()) {
        header("Location: product.php");
        exit;
    } else {
        echo "Failed to update product.";
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
    <form method="POST">
        <div class="mb-3">
            <label>Product Name:</label>
            <input type="text" name="name" class="form-control" value="<?= $product['name'] ?>" required>
        </div>
        <div class="mb-3">
            <label>Buying Price:</label>
            <input type="number" step="0.01" name="buying_price" class="form-control" value="<?= $product['buying_price'] ?>" required>
        </div>
        <div class="mb-3">
            <label>Selling Price:</label>
            <input type="number" step="0.01" name="selling_price" class="form-control" value="<?= $product['selling_price'] ?>" required>
        </div>
        <div class="mb-3">
            <label>Stock:</label>
            <input type="number" name="stock" class="form-control" value="<?= $product['stock'] ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Product</button>
    </form>
<?php
    include '../includes/footer.php';
?>