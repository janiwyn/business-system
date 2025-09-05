<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Handle Add Product Form Submission
if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $price = $_POST['selling-price'];
    $cost = $_POST['buying-price'];
    $stock = $_POST['stock'];
    $branch_id = $_POST['branch_id'];

    $stmt = $conn->prepare("INSERT INTO products (name, `selling-price`, `buying-price`, `stock`,`branch-id`) VALUES (?, ?, ?,?, ?)");
    $stmt->bind_param("sddii", $name, $price, $cost, $stock, $branch_id);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success shadow-sm'> Product added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger shadow-sm'> Error adding product: " . $stmt->error . "</div>";
    }
}
?>

<!-- Custom Styling -->
<style>
    .page-title {
        font-size: 28px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 25px;
        animation: fadeInDown 0.8s;
    }
    .card {
        border-radius: 12px;
        box-shadow: 0px 4px 12px rgba(0,0,0,0.08);
        transition: transform 0.2s ease-in-out;
    }
    .card:hover {
        transform: translateY(-2px);
    }
    .card-header {
        font-weight: 600;
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        border-radius: 12px 12px 0 0 !important;
    }
    .form-control {
        border-radius: 8px;
    }
    .btn-primary {
        border-radius: 8px;
        padding: 8px 18px;
        font-weight: 500;
        box-shadow: 0px 3px 8px rgba(0,0,0,0.2);
    }
    .btn-warning, .btn-danger {
        border-radius: 6px;
        font-size: 13px;
        padding: 5px 12px;
    }
    table {
        border-radius: 10px;
        overflow: hidden;
    }
    thead.table-dark th {
        background: #2c3e50 !important;
        color: #fff;
        text-transform: uppercase;
        font-size: 13px;
    }
    tbody tr:hover {
        background-color: #f8f9fa;
        transition: 0.3s;
    }
    @keyframes fadeInDown {
        from {opacity: 0; transform: translateY(-15px);}
        to {opacity: 1; transform: translateY(0);}
    }
</style>

<div class="container mt-5">
    <h2 class="page-title text-center">📦 Product Management</h2>

    <!-- Add Product Form -->
    <div class="card mb-4">
        <div class="card-header">➕ Add New Product</div>
        <div class="card-body">
            <?= isset($message) ? $message : "" ?>
            <!-- ✅ Fixed form action -->
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Coca-Cola 500ml" required>
                    </div>
                    <div class="col-md-3">
                        <label for="price" class="form-label">Selling Price</label>
                        <input type="number" step="0.01" name="price" id="price" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label for="cost" class="form-label">Buying Price</label>
                        <input type="number" step="0.01" name="cost" id="cost" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label for="stock" class="form-label">Stock Quantity</label>
                        <input type="number" name="stock" id="stock" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-md-3">
    <label for="branch" class="form-label">Branch</label>
    <select name="branch_id" id="branch" class="form-control" required>
        <option value="">-- Select Branch --</option>
        <?php
        $branches = $conn->query("SELECT id, name FROM branch");
        while ($b = $branches->fetch_assoc()) {
            echo "<option value='{$b['id']}'>" . htmlspecialchars($b['name']) . "</option>";
        }
        ?>
    </select>
</div>

                </div>
                <div class="mt-3">
                    <button type="submit" name="add_product" class="btn btn-primary">➕ Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Display Product List -->
    <div class="card mb-5">
        <div class="card-header">📋 Product List</div>
        <div class="card-body">
            <table class="table table-bordered table-striped align-middle text-center">
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
                                <td>$" . number_format($row['selling-price'], 2) . "</td>
                                <td>$" . number_format($row['buying-price'], 2) . "</td>
                                <td>{$row['stock']}</td>
                                <td>
                                    <a href='edit_product.php?id={$row['id']}' class='btn btn-sm btn-warning me-1'>✏️ Edit</a>
                                    <a href='delete_product.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this product?\")'>🗑️ Delete</a>
                                </td>
                            </tr>";
                            $i++;
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted'>No products found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


