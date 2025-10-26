<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff", "super"]);

include '../pages/sidebar.php';
include '../includes/header.php';

// Fetch suppliers for display
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY id DESC");
?>

<link rel="stylesheet" href="assets/css/supply.css">

<div class="container mt-5 mb-5">
    <h2 class="page-title mb-4 text-center">Suppliers Management</h2>

    <!-- Tabs -->
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

        <!-- ================= CREATE SUPPLIER TAB ================= -->
        <div class="tab-pane fade show active" id="tab-create">
            <div class="card add-supplier-card mb-4">
                <div class="card-header bg-primary text-white">Create Supplier</div>
                <div class="card-body">
                    <form id="createSupplierForm" class="row g-3">
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
                            <label class="form-label">Unit Price (UGX)</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" required>
                        </div>

                        <div class="col-12 text-end mt-3">
                            <button type="submit" class="btn btn-primary">Create Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ================= MANAGE SUPPLIERS TAB ================= -->
        <div class="tab-pane fade" id="tab-manage">
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">Manage Suppliers</div>
                <div class="card-body">
                    <div class="transactions-table table-responsive">
                        <table class="table table-striped table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Products Supplied</th>
                                    <th>Unit Price (UGX)</th>
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
                                            <td><?= number_format($s['unit_price'], 2) ?></td>
                                            <td>
                                                <button class="btn btn-warning btn-sm edit-supplier-btn"
                                                    data-id="<?= $s['id'] ?>"
                                                    data-name="<?= htmlspecialchars($s['name']) ?>"
                                                    data-location="<?= htmlspecialchars($s['location']) ?>"
                                                    data-products="<?= htmlspecialchars($s['products']) ?>"
                                                    data-unit_price="<?= $s['unit_price'] ?>">Edit</button>

                                                <button class="btn btn-danger btn-sm delete-supplier-btn"
                                                    data-id="<?= $s['id'] ?>">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No suppliers found.</td>
                                    </tr>
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
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                <label class="form-label">Unit Price (UGX)</label>
                                <input type="number" step="0.01" name="unit_price" id="editSupplierUnitPrice" class="form-control" required>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ================= SUPPLIER TRANSACTIONS TAB ================= -->
        <div class="tab-pane fade" id="tab-trans">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">Supplier Transactions</div>
                <div class="card-body">
                    <div class="alert alert-info text-center">Supplier transactions functionality coming soon.</div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ===================================================== -->
<!-- JavaScript Logic -->
<!-- ===================================================== -->
<script>
// CREATE supplier
document.getElementById('createSupplierForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = new FormData(this);

    fetch('../pages/api/suppliers_api.php', {
        method: 'POST',
        body: form
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Supplier created successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error creating supplier.');
        }
    })
    .catch(err => console.error(err));
});

// DELETE supplier
document.querySelectorAll('.delete-supplier-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Are you sure you want to delete this supplier?')) return;
        const id = this.dataset.id;

        const formData = new FormData();
        formData.append('action', 'delete_supplier');
        formData.append('id', id);

        fetch('../pages/api/suppliers_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Supplier deleted successfully!');
                location.reload();
            } else {
                alert('Failed to delete supplier.');
            }
        })
        .catch(err => console.error(err));
    });
});

// OPEN edit modal
document.querySelectorAll('.edit-supplier-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editSupplierId').value = this.dataset.id;
        document.getElementById('editSupplierName').value = this.dataset.name;
        document.getElementById('editSupplierLocation').value = this.dataset.location;
        document.getElementById('editSupplierProducts').value = this.dataset.products;
        document.getElementById('editSupplierUnitPrice').value = this.dataset.unit_price;

        const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
        modal.show();
    });
});

// SAVE edit form
document.getElementById('editSupplierForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = new FormData(this);
    form.append('action', 'edit_supplier');

    fetch('../pages/api/suppliers_api.php', {
        method: 'POST',
        body: form
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Supplier updated successfully!');
            location.reload();
        } else {
            alert('Failed to update supplier.');
        }
    })
    .catch(err => console.error(err));
});
</script>

<?php include '../includes/footer.php'; ?>
