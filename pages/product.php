<?php
include '../includes/db.php'; 
include '../includes/header.php';
include '../pages/sidebar.php';
include '../includes/auth.php';
// require_role("manager", "admin");

// Handle Add Product Form Submission
if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $cost = $_POST['cost'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("INSERT INTO products (name, selling_price, buying_price, stock) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sddi", $name, $price, $cost, $stock);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Product added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error adding product.</div>";
    }
}
?>

<div class="container mt-5">
    <h2 class="mb-4 text-center">Product Management</h2>

    <!-- Add Product Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Product</div>
        <div class="card-body">
            <?= isset($message) ? $message : "" ?>
            <form method="POST" action="product.php">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="price" class="form-label">Selling Price</label>
                        <input type="number" step="0.01" name="price" id="price" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="cost" class="form-label">Buying Price</label>
                        <input type="number" step="0.01" name="cost" id="cost" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="stock" class="form-label">Stock Quantity</label>
                        <input type="number" name="stock" id="stock" class="form-control" required>
                    </div>
                </div>
                <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
            </form>
        </div>
    </div>

    <!-- Display Product List -->
    <div class="card mb-5">
        <div class="card-header">Product List</div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Selling Price</th>
                        <th>Buying Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM products ORDER BY id DESC");
                    if ($result->num_rows > 0) {
                        $i = 1;
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$i}</td>
                                <td>" . htmlspecialchars($row['name']) . "</td>
                                <td>" . number_format($row['selling_price'], 2) . "</td>
                                <td>" . number_format($row['buying_price'], 2) . "</td>
                                <td>{$row['stock']}</td>
                                <td>
                                    <a href='edit_product.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='delete_product.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this product?\")'>Delete</a>
                                </td>
                            </tr>";
                            $i++;
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>No products found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
