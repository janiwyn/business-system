<?php
// --- FIX: Handle AJAX actions before any output ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_supplier') {
        header('Content-Type: application/json');
        include '../includes/db.php';
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM suppliers WHERE id = $id");
        echo json_encode(['success'=>true]);
        exit;
    }
    if ($_POST['action'] === 'edit_supplier') {
        header('Content-Type: application/json');
        include '../includes/db.php';
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
    if ($_POST['action'] === 'fetch_supplier_transactions') {
        header('Content-Type: application/json');
        include '../includes/db.php';
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $rows = [];
        if ($supplier_id > 0) {
            $stmt = $conn->prepare("SELECT * FROM supplier_transactions WHERE supplier_id = ? ORDER BY date_time DESC");
            $stmt->bind_param("i", $supplier_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
        }
        echo json_encode(['success'=>true, 'rows'=>$rows]);
        exit;
    }
    if ($_POST['action'] === 'pay_supplier_balance') {
        header('Content-Type: application/json');
        include '../includes/db.php';
        $trans_id = intval($_POST['trans_id'] ?? 0);
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);
        // Fetch original transaction
        $stmt = $conn->prepare("SELECT * FROM supplier_transactions WHERE id = ?");
        $stmt->bind_param("i", $trans_id);
        $stmt->execute();
        $orig = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$orig) { echo json_encode(['success'=>false]); exit; }
        // Duplicate transaction with updated payment info
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO supplier_transactions (supplier_id, date_time, products_supplied, quantity, unit_price, amount, payment_method, amount_paid, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("isssddsd", $orig['supplier_id'], $now, $orig['products_supplied'], $orig['quantity'], $orig['unit_price'], $orig['amount'], $orig['payment_method'], $amount_paid);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success'=>$ok]);
        exit;
    }
}

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

// Fetch suppliers for manage tab
$suppliers_res = $conn->query("SELECT * FROM suppliers ORDER BY id DESC");
$suppliers_arr = $suppliers_res ? $suppliers_res->fetch_all(MYSQLI_ASSOC) : [];
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
                                <?php if (count($suppliers_arr) > 0): ?>
                                    <?php foreach ($suppliers_arr as $s): ?>
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
                                    <?php endforeach; ?>
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
        <!-- SUPPLIER TRANSACTIONS TAB -->
        <div class="tab-pane fade" id="tab-trans">
            <div class="card mb-4">
                <div class="card-header">Supplier Transactions</div>
                <div class="card-body">
                    <?php if (count($suppliers_arr) > 0): ?>
                        <div class="accordion" id="suppliersAccordion">
                            <?php foreach($suppliers_arr as $s): ?>
                                <div class="accordion-item mb-2">
                                    <h2 class="accordion-header" id="headingS<?= $s['id'] ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseS<?= $s['id'] ?>" aria-expanded="false" aria-controls="collapseS<?= $s['id'] ?>">
                                            <?= htmlspecialchars($s['name']) ?> — Location: <?= htmlspecialchars($s['location']) ?>
                                        </button>
                                    </h2>
                                    <div id="collapseS<?= $s['id'] ?>" class="accordion-collapse collapse" aria-labelledby="headingS<?= $s['id'] ?>" data-bs-parent="#suppliersAccordion">
                                        <div class="accordion-body">
                                            <div id="transContainerS<?= $s['id'] ?>">Loading transactions...</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No suppliers found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pay Supplier Modal -->
<div class="modal fade" id="paySupplierModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pay Supplier Balance</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="payTransId">
        <div class="mb-3">
          <label class="form-label">Amount to Pay (UGX)</label>
          <input id="payAmount" class="form-control" type="number" step="0.01" min="0">
        </div>
        <div id="payMsg"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="payConfirmBtn">Confirm</button>
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
    }).then(async res => {
        let data;
        try {
            data = await res.json();
        } catch (err) {
            alert('Error: Could not update supplier. Please try again.');
            return;
        }
        if (data.success) location.reload();
        else alert('Failed to update supplier.');
    });
});

// Supplier Transactions Accordion
document.querySelectorAll('#suppliersAccordion .accordion-button').forEach(btn=>{
  btn.addEventListener('click', async (e) => {
    const target = e.target.closest('.accordion-button');
    const collapseId = target.getAttribute('data-bs-target').substring(1);
    const supplierId = collapseId.replace('collapseS','');
    const container = document.getElementById('transContainerS'+supplierId);
    if (container.dataset.loaded) return;
    container.innerHTML = '<div class="text-muted">Loading...</div>';
    const form = new FormData();
    form.append('action','fetch_supplier_transactions');
    form.append('supplier_id', supplierId);
    let data;
    try {
      const res = await fetch('suppliers.php', {method:'POST', body: form});
      data = await res.json();
    } catch (err) {
      data = {success: false, rows: []};
    }
    // Always show table headers
    let html = '<div class="transactions-table"><table><thead><tr><th>Date & Time</th><th>Branch</th><th>Products</th><th class="text-center">Quantity</th><th class="text-end">Unit Price</th><th class="text-end">Amount</th><th>Payment Method</th><th class="text-end">Amount Paid</th><th class="text-end">Balance</th><th>Actions</th></tr></thead><tbody>';
    if (!data.success || !Array.isArray(data.rows) || !data.rows.length) {
      html += '<tr><td colspan="10" class="text-center text-muted">No transactions found.</td></tr>';
      html += '</tbody></table></div>';
      container.innerHTML = html;
      container.dataset.loaded = '1';
      return;
    }

    data.rows.forEach(r=>{
      const paid = parseFloat(r.amount_paid || 0).toFixed(2);
      const balance = parseFloat(r.balance || 0).toFixed(2);
      const unitPrice = parseFloat(r.unit_price || 0).toFixed(2);
      const amount = parseFloat(r.amount || 0).toFixed(2);
      const products = escapeHtml(r.products_supplied || '');
      const qty = escapeHtml(r.quantity || '');
      const method = escapeHtml(r.payment_method || '');
      const date = escapeHtml(r.date_time || '');
      const branch = escapeHtml(r.branch || '');
      let actions = '';
      if (parseFloat(balance) > 0) {
        actions = `<button class="btn btn-success btn-sm pay-supplier-btn" data-id="${r.id}" data-balance="${balance}">Pay</button>`;
      } else {
        actions = `<span class="badge bg-success">Cleared</span>`;
      }
      html += `<tr>
        <td>${date}</td>
        <td>${branch}</td>
        <td>${products}</td>
        <td class="text-center">${qty}</td>
        <td class="text-end">UGX ${unitPrice}</td>
        <td class="text-end">UGX ${amount}</td>
        <td>${method}</td>
        <td class="text-end">UGX ${paid}</td>
        <td class="text-end">UGX ${balance}</td>
        <td>${actions}</td>
      </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
    container.dataset.loaded = '1';

    // Attach pay button events
    container.querySelectorAll('.pay-supplier-btn').forEach(btn=>{
      btn.addEventListener('click', () => {
        document.getElementById('payTransId').value = btn.getAttribute('data-id');
        document.getElementById('payAmount').value = btn.getAttribute('data-balance');
        document.getElementById('payMsg').innerHTML = '';
        new bootstrap.Modal(document.getElementById('paySupplierModal')).show();
      });
    });
  });
});

// Pay Supplier Confirm
document.getElementById('payConfirmBtn').addEventListener('click', async () => {
  const transId = document.getElementById('payTransId').value;
  const amount = parseFloat(document.getElementById('payAmount').value || 0);
  if (!transId || amount <= 0) { document.getElementById('payMsg').innerHTML = '<div class="alert alert-warning">Enter valid amount.</div>'; return; }
  const form = new FormData();
  form.append('action','pay_supplier_balance');
  form.append('trans_id', transId);
  form.append('amount_paid', amount);
  const res = await fetch('suppliers.php', {method:'POST', body: form});
  const data = await res.json();
  if (data.success) {
    document.getElementById('payMsg').innerHTML = '<div class="alert alert-success">Balance paid.</div>';
    setTimeout(()=>location.reload(),700);
  } else {
    document.getElementById('payMsg').innerHTML = '<div class="alert alert-danger">Error. Try again.</div>';
  }
});

function escapeHtml(s){ return s ? s.replace(/[&<>"']/g, c=> ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])) : ''; }
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
