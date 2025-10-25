<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Always include db/auth BEFORE AJAX handler!
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin","manager","staff", "super"]);

// --- AJAX HANDLER MUST BE BEFORE ANY HTML OR INCLUDES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    if ($action === 'create_customer') {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $opening_date = $_POST['opening_date'] ?? date('Y-m-d');
        if ($name === '') {
            echo json_encode(['success'=>false,'message'=>'Name required']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO customers (name, contact, email, opening_date, amount_credited, account_balance) VALUES (?, ?, ?, ?, 0, 0)");
        $stmt->bind_param("ssss", $name, $contact, $email, $opening_date);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'id'=>$stmt->insert_id]);
        } else {
            echo json_encode(['success'=>false,'message'=>$stmt->error]);
        }
        $stmt->close();
        exit;
    }
    if ($action === 'add_money') {
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        if ($customer_id <= 0 || $amount <= 0) {
            echo json_encode(['success'=>false,'message'=>'Invalid input']);
            exit;
        }
        // Fetch current credited amount and balance
        $stmt = $conn->prepare("SELECT amount_credited, account_balance FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $cust = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $credited = floatval($cust['amount_credited'] ?? 0);
        $balance = floatval($cust['account_balance'] ?? 0);

        $amount_to_credit = min($amount, $credited);
        $amount_to_balance = $amount - $amount_to_credit;

        // Update credited and balance
        $stmt = $conn->prepare("UPDATE customers SET amount_credited = amount_credited - ?, account_balance = account_balance + ? WHERE id = ?");
        $stmt->bind_param("ddi", $amount_to_credit, $amount_to_balance, $customer_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $now = date('Y-m-d H:i:s');
            $sold_by = $_SESSION['username'];

            // 1. Record deduction transaction if any credited amount was paid off
            if ($amount_to_credit > 0) {
                $products = 'Account Deduction';
                $amount_paid = $amount_to_credit;
                $amount_credited = $amount_to_credit;
                $stmt2 = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("issdds", $customer_id, $now, $products, $amount_paid, $amount_credited, $sold_by);
                $stmt2->execute();
                $stmt2->close();
            }

            // 2. Record top-up transaction for remaining balance
            if ($amount_to_balance > 0) {
                $products = 'Account Top-up';
                $amount_paid = $amount_to_balance;
                $amount_credited = 0;
                $stmt2 = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("issdds", $customer_id, $now, $products, $amount_paid, $amount_credited, $sold_by);
                $stmt2->execute();
                $stmt2->close();
            }

            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Failed to update balance']);
        }
        exit;
    }
    if ($action === 'delete_customer') {
        $customer_id = intval($_POST['customer_id'] ?? 0);
        if ($customer_id <= 0) { echo json_encode(['success'=>false]); exit; }
        $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customer_id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            // Optionally cascade-delete transactions
            $stmt2 = $conn->prepare("DELETE FROM customer_transactions WHERE customer_id = ?");
            $stmt2->bind_param("i", $customer_id);
            $stmt2->execute();
            $stmt2->close();
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Delete failed']);
        }
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_transactions'])) {
    $customer_id = intval($_GET['customer_id'] ?? 0);
    $out = ['success'=>false,'rows'=>[]];
    if ($customer_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM customer_transactions WHERE customer_id = ? ORDER BY date_time DESC");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $out['rows'][] = $r;
        $stmt->close();
        $out['success'] = true;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
    exit;
}
// include sidebar/header (keeps layout consistent)
include '../pages/sidebar.php';
include '../includes/header.php';

// Load customers list for page render
$customers_res = $conn->query("SELECT * FROM customers ORDER BY id DESC");
$customers = $customers_res ? $customers_res->fetch_all(MYSQLI_ASSOC) : [];

?>
  <div class="container-fluid mt-4">
    <!-- Tabs -->
    <ul class="nav nav-tabs" id="custTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-create">Create Customer File</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-view">View Customer Files</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-manage">Manage Customers</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-trans">Customer Transactions</button>
      </li>
    </ul>

    <div class="tab-content mt-3">

      <!-- CREATE -->
      <div class="tab-pane fade show active" id="tab-create">
        <div class="card mb-4">
          <div class="card-header">Create Customer File</div>
          <div class="card-body">
            <form id="createCustomerForm" class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Customer Name</label>
                <input name="name" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Contact Number</label>
                <input name="contact" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">Opening Date</label>
                <input name="opening_date" type="date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary" id="createCustomerBtn">Create Customer</button>
              </div>
            </form>
            <div id="createMsg" class="mt-3"></div>
          </div>
        </div>
      </div>

      <!-- VIEW -->
      <div class="tab-pane fade" id="tab-view">
        <div class="card mb-4">
          <div class="card-header" color = #1abc9c><b>View Customer Files</b></div>
          <div class="card-body">
            <?php if (count($customers)): ?>
              <div class="transactions-table">
                <table>
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Contact</th>
                      <th>Email</th>
                      <th>Opening Date</th>
                      <th class="text-center">Open File</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($customers as $c): ?>
                      <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= htmlspecialchars($c['contact']) ?></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td><?= htmlspecialchars($c['opening_date'] ?? '') ?></td>
                        <td class="text-center">
                          <a href="view_customer_file.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm">Open File</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted">No customer files yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- MANAGE -->
      <div class="tab-pane fade" id="tab-manage">
        <div class="card mb-4">
          <div class="card-header">Manage Customers</div>
          <div class="card-body">
            <?php if (count($customers)): ?>
              <div class="transactions-table">
                <table>
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Contact</th>
                      <th class="text-end">Amount Credited</th>
                      <th class="text-end">Account Balance</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($customers as $c): ?>
                      <tr>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= htmlspecialchars($c['contact']) ?></td>
                        <!-- safe formatting to avoid undefined key warnings -->
                        <td class="text-end">
                          <span class="fw-bold text-danger">UGX <?= number_format(floatval($c['amount_credited'] ?? 0), 2) ?></span>
                        </td>
                        <td class="text-end">
                          <span class="fw-bold text-success">UGX <?= number_format(floatval($c['account_balance'] ?? 0), 2) ?></span>
                        </td>
                        <td>
                          <button class="btn btn-primary btn-sm me-1 add-money-btn" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">Add Money</button>
                          <button class="btn btn-danger btn-sm delete-customer-btn" data-id="<?= $c['id'] ?>">Delete File</button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted">No customers to manage.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TRANSACTIONS -->
      <div class="tab-pane fade" id="tab-trans">
        <div class="card mb-4">
          <div class="card-header">Customer Transactions</div>
          <div class="card-body">
            <?php if (count($customers)): ?>
              <div class="accordion" id="customersAccordion">
                <?php foreach($customers as $c): ?>
                  <div class="accordion-item mb-2">
                    <h2 class="accordion-header" id="heading<?= $c['id'] ?>">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $c['id'] ?>" aria-expanded="false" aria-controls="collapse<?= $c['id'] ?>">
                        <?= htmlspecialchars($c['name']) ?> — Balance: UGX <?= number_format(floatval($c['account_balance'] ?? 0), 2) ?>
                      </button>
                    </h2>
                    <div id="collapse<?= $c['id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $c['id'] ?>" data-bs-parent="#customersAccordion">
                      <div class="accordion-body">
                        <div id="transContainer<?= $c['id'] ?>">Loading transactions...</div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-muted">No customers.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Add Money Modal -->
<div class="modal fade" id="addMoneyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Money to <span id="amCustomerName"></span></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="amCustomerId">
        <div class="mb-3">
          <label class="form-label">Amount (UGX)</label>
          <input id="amAmount" class="form-control" type="number" step="0.01" min="0">
        </div>
        <div id="amMsg"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="amConfirmBtn">Confirm</button>
      </div>
    </div>
  </div>


<style>
/* ...existing code... */
body.dark-mode,
body.dark-mode .card,
body.dark-mode .card-header,
body.dark-mode .title-card,
body.dark-mode .form-label,
body.dark-mode label,
body.dark-mode .card-body,
body.dark-mode .transactions-table thead,
body.dark-mode .transactions-table tbody td,
body.dark-mode .transactions-table tbody tr,
body.dark-mode .alert,
body.dark-mode .nav-tabs .nav-link,
body.dark-mode .accordion-button,
body.dark-mode .accordion-body {
    color: #fff !important;
    background-color: #23243a !important;
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
body.dark-mode .btn,
body.dark-mode .btn-primary,
body.dark-mode .btn-success,
body.dark-mode .btn-danger,
body.dark-mode .btn-warning {
    color: #fff !important;
}
</style>

<script>
/* Create customer via AJAX */
document.getElementById('createCustomerForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const form = new FormData(this);
  form.append('action','create_customer');
  // Use current page for AJAX POST
  const res = await fetch(location.pathname, {method:'POST', body: form});
  const data = await res.json();
  const msg = document.getElementById('createMsg');
  if (data.success) {
    msg.innerHTML = '<div class="alert alert-success">Customer created. <a href="view_customer_file.php?id='+data.id+'">Open file</a></div>';
    setTimeout(()=>location.reload(),900);
  } else {
    msg.innerHTML = '<div class="alert alert-danger">'+(data.message||'Error')+'</div>';
  }
});

/* Add Money button open modal */
document.querySelectorAll('.add-money-btn').forEach(btn=>{
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const name = btn.dataset.name;
    document.getElementById('amCustomerId').value = id;
    document.getElementById('amCustomerName').textContent = name;
    document.getElementById('amAmount').value = '';
    document.getElementById('amMsg').innerHTML = '';
    new bootstrap.Modal(document.getElementById('addMoneyModal')).show();
  });
});

/* Confirm add money */
document.getElementById('amConfirmBtn').addEventListener('click', async () => {
  const id = document.getElementById('amCustomerId').value;
  const amount = parseFloat(document.getElementById('amAmount').value || 0);
  if (!id || amount <= 0) { document.getElementById('amMsg').innerHTML = '<div class="alert alert-warning">Enter valid amount.</div>'; return; }
  const form = new FormData();
  form.append('action','add_money');
  form.append('customer_id', id);
  form.append('amount', amount);
  const res = await fetch('customer_management.php', {method:'POST', body: form});
  const data = await res.json();
  if (data.success) {
    document.getElementById('amMsg').innerHTML = '<div class="alert alert-success">Amount added.</div>';
    setTimeout(()=>location.reload(),700);
  } else {
    document.getElementById('amMsg').innerHTML = '<div class="alert alert-danger">'+(data.message||'Error')+'</div>';
  }
});

/* Delete customer */
document.querySelectorAll('.delete-customer-btn').forEach(btn=>{
  btn.addEventListener('click', async () => {
    if (!confirm('Delete this customer file? This will remove all transactions.')) return;
    const id = btn.dataset.id;
    const form = new FormData();
    form.append('action','delete_customer');
    form.append('customer_id', id);
    const res = await fetch('customer_management.php', {method:'POST', body: form});
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message || 'Delete failed');
  });
});

/* Load transactions when accordion opened */
document.querySelectorAll('#customersAccordion .accordion-button').forEach(btn=>{
  btn.addEventListener('click', async (e) => {
    const target = e.target.closest('.accordion-button');
    const collapseId = target.getAttribute('data-bs-target').substring(1);
    const customerId = collapseId.replace('collapse','');
    const container = document.getElementById('transContainer'+customerId);
    // If already loaded once, skip re-fetch (simple caching)
    if (container.dataset.loaded) return;
    container.innerHTML = '<div class="text-muted">Loading...</div>';
    const res = await fetch('customer_management.php?fetch_transactions=1&customer_id='+customerId);
    const data = await res.json();
    if (!data.success) { container.innerHTML = '<div class="text-muted">No transactions.</div>'; return; }
    if (!data.rows.length) { container.innerHTML = '<div class="text-muted">No transactions.</div>'; container.dataset.loaded = '1'; return; }

    // Build table with parsed products and quantity column
    let html = '<div class="transactions-table"><table><thead><tr><th>Date & Time</th><th>Products</th><th class="text-center">Quantity</th><th class="text-end">Amount Paid</th><th class="text-end">Amount Credited</th><th>Sold By</th></tr></thead><tbody>';
    data.rows.forEach(r=>{
      // parse products_bought JSON if possible
      let prodDisplay = '';
      let totalQty = 0;
      try {
        const pb = JSON.parse(r.products_bought || '[]');
        if (Array.isArray(pb)) {
          const parts = pb.map(p => {
            const name = (p.name || p.product || '').toString();
            const qty = parseInt(p.quantity || p.qty || 0) || 0;
            totalQty += qty;
            return `${escapeHtml(name)} x${qty}`;
          });
          prodDisplay = parts.join(', ');
        } else {
          prodDisplay = escapeHtml(String(r.products_bought || ''));
        }
      } catch (err) {
        prodDisplay = escapeHtml(String(r.products_bought || ''));
      }

      const paid = parseFloat(r.amount_paid || 0).toFixed(2);
      const credited = parseFloat(r.amount_credited || 0).toFixed(2);
      const soldBy = escapeHtml(r.sold_by || '');
      html += `<tr>
                 <td>${escapeHtml(r.date_time)}</td>
                 <td>${prodDisplay || '-'}</td>
                 <td class="text-center">${totalQty}</td>
                 <td class="text-end">UGX ${paid}</td>
                 <td class="text-end">UGX ${credited}</td>
                 <td>${soldBy}</td>
               </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
    container.dataset.loaded = '1';
  });
});

function escapeHtml(s){ return s ? s.replace(/[&<>"']/g, c=> ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])) : ''; }
</script>

<?php include '../includes/footer.php'; ?>
