<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff", "super"]);
include '../pages/sidebar.php';
include '../includes/header.php';
?>
<link rel="stylesheet" href="assets/css/accounting.css">

<?php
// Handle create supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_supplier') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $products = trim($_POST['products'] ?? '');
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO suppliers (name, location, products, unit_price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $name, $location, $products, $unit_price);
        $stmt->execute();
        $stmt->close();
        $message = "<div class='alert alert-success'>Supplier created successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Supplier name is required.</div>";
    }
}

// Handle delete supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_supplier') {
    $id = intval($_POST['id']);
    $conn->query("DELETE FROM suppliers WHERE id = $id");
    echo json_encode(['success'=>true]);
    exit;
}

// Handle edit supplier (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_supplier') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $products = trim($_POST['products'] ?? '');
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $stmt = $conn->prepare("UPDATE suppliers SET name=?, location=?, products=?, unit_price=? WHERE id=?");
    $stmt->bind_param("sssdi", $name, $location, $products, $unit_price, $id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success'=>$ok]);
    exit;
}

// Fetch suppliers for manage tab
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY id DESC");
?>

<div class="container mt-5 mb-5">
    <h2 class="page-title mb-4 text-center">Suppliers Management</h2>
    <ul class="nav nav-tabs" id="supplierTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-create">Create Supplier</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-manage">Manage Suppliers</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-trans">Supplier Transactions</button>
        </li>
    </ul>
    <div class="tab-content mt-3">
        <!-- CREATE SUPPLIER TAB -->
        <div class="tab-pane fade show active" id="tab-create">
            <div class="card add-supplier-card mb-4">
                <div class="card-header">Create Supplier</div>
                <div class="card-body">
                    <?= isset($message) ? $message : "" ?>
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="create_supplier">
                        <div class="col-md-6">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Products Supplied</label>
                            <input type="text" name="products" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" required>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Create Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- MANAGE SUPPLIERS TAB -->
        <div class="tab-pane fade" id="tab-manage">
            <div class="card mb-4">
                <div class="card-header">Manage Suppliers</div>
                <div class="card-body">
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Products Supplied</th>
                                    <th>Unit Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($suppliers && $suppliers->num_rows > 0): ?>
                                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                                        <tr data-id="<?= $s['id'] ?>">
                                            <td><?= htmlspecialchars($s['name']) ?></td>
                                            <td><?= htmlspecialchars($s['location']) ?></td>
                                            <td><?= htmlspecialchars($s['products']) ?></td>
                                            <td>UGX <?= number_format($s['unit_price'],2) ?></td>
                                            <td>
                                                <button class="btn btn-warning btn-sm edit-supplier-btn" 
                                                    data-id="<?= $s['id'] ?>"
                                                    data-name="<?= htmlspecialchars($s['name']) ?>"
                                                    data-location="<?= htmlspecialchars($s['location']) ?>"
                                                    data-products="<?= htmlspecialchars($s['products']) ?>"
                                                    data-unit_price="<?= $s['unit_price'] ?>">Edit</button>
                                                <button class="btn btn-danger btn-sm delete-supplier-btn" data-id="<?= $s['id'] ?>">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted">No suppliers found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Edit Supplier Modal -->
            <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" id="editSupplierForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <input type="hidden" name="id" id="editSupplierId">
                            <div class="col-md-6">
                                <label class="form-label">Supplier Name</label>
                                <input type="text" name="name" id="editSupplierName" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" id="editSupplierLocation" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Products Supplied</label>
                                <input type="text" name="products" id="editSupplierProducts" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unit Price</label>
                                <input type="number" step="0.01" name="unit_price" id="editSupplierUnitPrice" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">OK</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- SUPPLIER TRANSACTIONS TAB (placeholder) -->
        <div class="tab-pane fade" id="tab-trans">
            <div class="card mb-4">
                <div class="card-header">Supplier Transactions</div>
                <div class="card-body">
                    <div class="alert alert-info">Supplier transactions functionality coming soon.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Delete supplier
document.querySelectorAll('.delete-supplier-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Delete this supplier?')) return;
        const id = btn.getAttribute('data-id');
        fetch('suppliers.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=delete_supplier&id=' + encodeURIComponent(id)
        }).then(res => res.json()).then(data => {
            if (data.success) location.reload();
        });
    });
});

// Edit supplier
document.querySelectorAll('.edit-supplier-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editSupplierId').value = btn.getAttribute('data-id');
        document.getElementById('editSupplierName').value = btn.getAttribute('data-name');
        document.getElementById('editSupplierLocation').value = btn.getAttribute('data-location');
        document.getElementById('editSupplierProducts').value = btn.getAttribute('data-products');
        document.getElementById('editSupplierUnitPrice').value = btn.getAttribute('data-unit_price');
        new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
    });
});

// Handle edit supplier form submit
document.getElementById('editSupplierForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = new FormData(this);
    form.append('action', 'edit_supplier');
    fetch('suppliers.php', {
        method: 'POST',
        body: form
    }).then(res => res.json()).then(data => {
        if (data.success) location.reload();
    });
});
</script>

<style>
/* filepath: d:\xamp\htdocs\business-system\pages\suppliers.php */
/* Add Supplier Form Styling (match Add Product form) */
.add-supplier-card {
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    background: var(--card-bg);
}
.add-supplier-card .card-header {
    font-weight: bold;
    background: var(--primary-color);
    color: #fff !important;
    border-radius: 12px 12px 0 0 !important;
    font-size: 1.1rem;
    letter-spacing: 1px;
}
.add-supplier-card .form-control,
.add-supplier-card .form-select {
    border-radius: 8px;
}
.add-supplier-card .btn-primary {
    background: var(--primary-color) !important;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    color: #fff !important;
    box-shadow: 0px 3px 8px rgba(0,0,0,0.2);
    transition: background 0.2s;
}
.add-supplier-card .btn-primary:hover,
.add-supplier-card .btn-primary:focus {
    background: #159c8c !important;
    color: #fff !important;
}
.add-supplier-card .form-label {
    font-weight: 600;
}
.add-supplier-card .row.g-3 > div {
    margin-bottom: 1rem;
}

/* Table Styling (match application tables) */
.transactions-table table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px var(--card-shadow);
}
.transactions-table thead {
    background: var(--primary-color);
    color: #fff;
    text-transform: uppercase;
    font-size: 13px;
}
.transactions-table tbody td {
    color: var(--text-color);
    padding: 0.75rem 1rem;
}
.transactions-table tbody tr {
    background-color: #fff;
    transition: background 0.2s;
}
.transactions-table tbody tr:nth-child(even) {
    background-color: #f4f6f9;
}
.transactions-table tbody tr:hover {
    background-color: rgba(0,0,0,0.05);
}

/* Dark mode styles */
body.dark-mode .add-supplier-card,
body.dark-mode .add-supplier-card .card-header,
body.dark-mode .add-supplier-card .card-body {
    background-color: #23243a !important;
    color: #fff !important;
}
body.dark-mode .add-supplier-card .card-header {
    background-color: #2c3e50 !important;
    color: #1abc9c !important;
}
body.dark-mode .add-supplier-card .form-label,
body.dark-mode .add-supplier-card label {
    color: #fff !important;
}
body.dark-mode .add-supplier-card .form-control,
body.dark-mode .add-supplier-card .form-select {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .add-supplier-card .form-control:focus,
body.dark-mode .add-supplier-card .form-select:focus {
    background-color: #23243a !important;
    color: #fff !important;
}
body.dark-mode .add-supplier-card .btn,
body.dark-mode .add-supplier-card .btn-primary {
    color: #fff !important;
}
body.dark-mode .transactions-table table {
    background: #23243a !important;
}
body.dark-mode .transactions-table thead {
    background-color: #1abc9c !important;
    color: #fff !important;
}
body.dark-mode .transactions-table tbody tr {
    background-color: #2c2c3a !important;
}
body.dark-mode .transactions-table tbody tr:nth-child(even) {
    background-color: #272734 !important;
}
body.dark-mode .transactions-table tbody td {
    color: #fff !important;
}
body.dark-mode .transactions-table tbody tr:hover {
    background-color: #1abc9c22 !important;
}
</style>

<?php include '../includes/footer.php'; ?>
