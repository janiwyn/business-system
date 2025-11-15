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
        $payment_method = trim($_POST['payment_method'] ?? '');
        $pm_other = trim($_POST['payment_method_other'] ?? ''); // <-- NEW
        // Use custom payment method if 'Other' selected
        if (strcasecmp($payment_method, 'Other') === 0 && $pm_other !== '') {
            $payment_method = $pm_other;
        }
        $opening_date = $_POST['opening_date'] ?? date('Y-m-d');
        if ($name === '') {
            echo json_encode(['success'=>false,'message'=>'Name required']);
            exit;
        }
        // Store payment_method
        $stmt = $conn->prepare("INSERT INTO customers (name, contact, email, payment_method, opening_date, amount_credited, account_balance) VALUES (?, ?, ?, ?, ?, 0, 0)");
        $stmt->bind_param("sssss", $name, $contact, $email, $payment_method, $opening_date);
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
        // Include customer's payment_method in the response
        $stmt = $conn->prepare("SELECT ct.*, c.payment_method FROM customer_transactions ct JOIN customers c ON c.id = ct.customer_id WHERE ct.customer_id = ? ORDER BY ct.date_time DESC");
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
?>
<link rel="stylesheet" href="assets/css/staff.css">
<!-- If you want global styles, also add: -->
<!-- <link rel="stylesheet" href="assets/css/style.css"> -->

<?php
// Load customers list for page render
$customers_res = $conn->query("
    SELECT c.*,
           COALESCE((
               SELECT SUM(ct.amount_credited)
               FROM customer_transactions ct
               WHERE ct.customer_id = c.id
           ), 0) AS credited_sum
    FROM customers c
    ORDER BY c.id DESC
");
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
        <div class="card create-customer-card mb-4"  style="border-left: 4px solid teal;">
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
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-select" id="pmSelect">
                  <option value="">-- Select --</option>
                  <option value="Cash">Cash</option>
                  <option value="MTN MoMo">MTN MoMo</option>
                  <option value="Airtel Money">Airtel Money</option>
                  <option value="Bank">Bank</option>
                  <option value="Customer File">Customer File</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <!-- NEW: Other Payment Method text input (shown only when 'Other' selected) -->
              <div class="col-md-4" id="pmOtherWrap" style="display:none;">
                <label class="form-label">Other Payment Method</label>
                <input name="payment_method_other" class="form-control" id="pmOtherInput" placeholder="Enter payment method">
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
        <div class="card mb-4"  style="border-left: 4px solid teal;">
          <div class="card-header" color = #1abc9c><b>View Customer Files</b></div>
          <div class="card-body">
            <!-- Responsive Table Card for Small Devices -->
            <div class="d-block d-md-none mb-4">
              <div class="card transactions-card"  style="border-left: 4px solid teal;">
                <div class="card-body">
                  <div class="table-responsive-sm">
                    <div class="transactions-table">
                      <table>
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Payment Method</th> <!-- NEW -->
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
                              <td><?= htmlspecialchars($c['payment_method'] ?? '') ?></td> <!-- NEW -->
                              <td><?= htmlspecialchars($c['opening_date'] ?? '') ?></td>
                              <td class="text-center">
                                <a href="view_customer_file.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm" title="Open File">
                                  <i class="fa fa-folder-open"></i>
                                </a>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Table for medium and large devices -->
            <div class="transactions-table d-none d-md-block">
              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Payment Method</th> <!-- NEW -->
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
                      <td><?= htmlspecialchars($c['payment_method'] ?? '') ?></td> <!-- NEW -->
                      <td><?= htmlspecialchars($c['opening_date'] ?? '') ?></td>
                      <td class="text-center">
                        <a href="view_customer_file.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm">Open File</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if (!count($customers)): ?>
              <p class="text-muted">No customer files yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- MANAGE -->
      <div class="tab-pane fade" id="tab-manage">
        <div class="card mb-4"  style="border-left: 4px solid teal;">
          <div class="card-header">Manage Customers</div>
          <div class="card-body">
            <!-- NEW: Report/Export buttons -->
            <div class="mb-3 d-flex gap-2 flex-wrap">
              <button class="btn btn-warning btn-sm" id="btnGenerateReport">
                <i class="fa fa-file-alt"></i> Generate Report
              </button>
              <button class="btn btn-primary btn-sm" id="btnExportExcel">
                <i class="fa fa-file-excel"></i> Export to Excel
              </button>
            </div>

            <!-- Responsive Table Card for Small Devices -->
            <div class="d-block d-md-none mb-4"  style="border-left: 4px solid teal;">
              <div class="card transactions-card" >
                <div class="card-body">
                  <div class="table-responsive-sm">
                    <div class="transactions-table">
                      <table id="manageCustomersTableMobile">
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
                              <td class="text-end">
                                <?php $showCred = isset($c['credited_sum']) ? $c['credited_sum'] : ($c['amount_credited'] ?? 0); ?>
                                <span class="fw-bold text-danger">UGX <?= number_format(floatval($showCred), 2) ?></span>
                              </td>
                              <td class="text-end">
                                <span class="fw-bold text-success">UGX <?= number_format(floatval($c['account_balance'] ?? 0), 2) ?></span>
                              </td>
                              <td>
                                <button class="btn btn-primary btn-sm me-1 add-money-btn" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" title="Add Money">
                                  <i class="fa fa-plus"></i>
                                </button>
                                <button class="btn btn-danger btn-sm delete-customer-btn" data-id="<?= $c['id'] ?>" title="Delete File">
                                  <i class="fa fa-trash"></i>
                                </button>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Table for medium and large devices -->
            <div class="transactions-table d-none d-md-block">
              <table id="manageCustomersTable">
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
                      <td class="text-end">
                        <?php $showCred = isset($c['credited_sum']) ? $c['credited_sum'] : ($c['amount_credited'] ?? 0); ?>
                        <span class="fw-bold text-danger">UGX <?= number_format(floatval($showCred), 2) ?></span>
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
            <?php if (!count($customers)): ?>
              <p class="text-muted">No customers to manage.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TRANSACTIONS -->
      <div class="tab-pane fade" id="tab-trans">
        <div class="card mb-4"  style="border-left: 4px solid teal;">
          <div class="card-header">Customer Transactions</div>
          <div class="card-body">
            <!-- Responsive Table Card for Small Devices -->
            <div class="d-block d-md-none mb-4" >
              <div class="card transactions-card">
                <div class="card-body">
                  <div class="table-responsive-sm">
                    <div class="transactions-table">
                      <?php if (count($customers)): ?>
                        <div class="accordion" id="customersAccordionMobile">
                          <?php foreach($customers as $c): ?>
                            <div class="accordion-item mb-2" style="border-left: 4px solid teal;">
                              <h2 class="accordion-header" id="heading<?= $c['id'] ?>m">
                                <div class="d-flex align-items-center w-100">
                                  <?php $showCred = isset($c['credited_sum']) ? $c['credited_sum'] : ($c['amount_credited'] ?? 0); ?>
                                  <button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $c['id'] ?>m" aria-expanded="false" aria-controls="collapse<?= $c['id'] ?>m" style="white-space: nowrap;">
                                    <?= htmlspecialchars($c['name']) ?> — Balance: UGX <?= number_format(floatval($c['account_balance'] ?? 0), 2) ?> — Credited: UGX <?= number_format(floatval($showCred), 2) ?>
                                  </button>
                                  <button type="button" class="btn btn-sm btn-outline-secondary ms-2 cust-report-btn" title="Generate Report" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                                    <i class="fa fa-file-alt"></i>
                                  </button>
                                  <button type="button" class="btn btn-sm btn-outline-success ms-1 cust-export-btn" title="Export to Excel" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                                    <i class="fa fa-file-excel"></i>
                                  </button>
                                </div>
                              </h2>
                              <div id="collapse<?= $c['id'] ?>m" class="accordion-collapse collapse" aria-labelledby="heading<?= $c['id'] ?>m" data-bs-parent="#customersAccordionMobile">
                                <div class="accordion-body">
                                  <div class="transactions-table" id="transContainer<?= $c['id'] ?>m">Loading transactions...</div>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                        <script>
                        // FIX: define escapeHtml before usage (mobile)
                        if(typeof escapeHtml!=='function'){
                          function escapeHtml(s){return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])):'';}
                        }
                        // Mobile Customer Transactions Accordion
                        document.querySelectorAll('#customersAccordionMobile .accordion-button').forEach(btn=>{
                          btn.addEventListener('click', async (e) => {
                            const target = e.target.closest('.accordion-button');
                            const collapseId = target.getAttribute('data-bs-target').substring(1);
                            const customerId = collapseId.replace('collapse','').replace('m','');
                            const container = document.getElementById('transContainer'+customerId+'m');
                            if (container.dataset.loaded) return;
                            container.innerHTML = '<div class="text-muted">Loading...</div>';
                            const res = await fetch('customer_management.php?fetch_transactions=1&customer_id='+customerId);
                            let data;
                            try { data = await res.json(); } catch(err){
                              container.innerHTML = '<div class="text-muted">Error loading.</div>';
                              container.dataset.loaded='1';
                              return;
                            }
                            if (!data.success) { container.innerHTML = '<div class="text-muted">No transactions.</div>'; return; }
                            if (!data.rows.length) { container.innerHTML = '<div class="text-muted">No transactions.</div>'; container.dataset.loaded = '1'; return; }

                            // Add Payment Method column
                            let html = '<table><thead><tr><th>Date & Time</th><th>Products</th><th class="text-center">Quantity</th><th class="text-end">Amount Paid</th><th class="text-end">Amount Credited</th><th>Payment Method</th><th>Sold By</th></tr></thead><tbody>';
                            data.rows.forEach(r=>{
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
                                         <td>${escapeHtml(r.payment_method || '')}</td>
                                         <td>${soldBy}</td>
                                       </tr>`;
                            });
                            html += '</tbody></table>';
                            container.innerHTML = html;
                            container.dataset.loaded = '1';
                          });
                        });
                        </script>
                      <?php else: ?>
                        <p class="text-muted">No customers.</p>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Table for medium and large devices -->
            <div class="accordion d-none d-md-block" id="customersAccordion" >
              <?php foreach($customers as $c): ?>
                <div class="accordion-item mb-2"  style="border-left: 4px solid teal;">
                  <h2 class="accordion-header" id="heading<?= $c['id'] ?>">
                    <div class="d-flex align-items-center w-100">
                      <?php $showCred = isset($c['credited_sum']) ? $c['credited_sum'] : ($c['amount_credited'] ?? 0); ?>
                      <button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $c['id'] ?>" aria-expanded="false" aria-controls="collapse<?= $c['id'] ?>" style="white-space: nowrap;">
                        <?= htmlspecialchars($c['name']) ?> — Balance: UGX <?= number_format(floatval($c['account_balance'] ?? 0), 2) ?> — Credited: UGX <?= number_format(floatval($showCred), 2) ?>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-secondary ms-2 cust-report-btn" title="Generate Report" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                        <i class="fa fa-file-alt"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-success ms-1 cust-export-btn" title="Export to Excel" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                        <i class="fa fa-file-excel"></i>
                      </button>
                    </div>
                  </h2>
                  <div id="collapse<?= $c['id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $c['id'] ?>" data-bs-parent="#customersAccordion">
                    <div class="accordion-body">
                      <div id="transContainer<?= $c['id'] ?>">Loading transactions...</div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (!count($customers)): ?>
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
body.dark_mode label,
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
// Toggle "Other Payment Method" input visibility
(function(){
  const pmSelect = document.getElementById('pmSelect');
  const pmOtherWrap = document.getElementById('pmOtherWrap');
  const pmOtherInput = document.getElementById('pmOtherInput');
  if (pmSelect) {
    const toggle = () => {
      if (pmSelect.value === 'Other') {
        pmOtherWrap.style.display = '';
        pmOtherInput?.focus();
      } else {
        pmOtherWrap.style.display = 'none';
        if (pmOtherInput) pmOtherInput.value = '';
      }
    };
    pmSelect.addEventListener('change', toggle);
    toggle();
  }
})();

/* Create customer via AJAX */
document.getElementById('createCustomerForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const pmSelect = document.getElementById('pmSelect');
  const pmOtherInput = document.getElementById('pmOtherInput');
  const msg = document.getElementById('createMsg');

  // Require custom text when 'Other' selected
  if (pmSelect && pmSelect.value === 'Other') {
    if (!pmOtherInput || !pmOtherInput.value.trim()) {
      msg.innerHTML = '<div class="alert alert-warning">Please enter the other payment method.</div>';
      return;
    }
  }

  const form = new FormData(this);
  form.append('action','create_customer');
  // Use current page for AJAX POST
  const res = await fetch(location.pathname, {method:'POST', body: form});
  const data = await res.json();
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
    if (container.dataset.loaded) return;
    container.innerHTML = '<div class="text-muted">Loading...</div>';
    const res = await fetch('customer_management.php?fetch_transactions=1&customer_id='+customerId);
    let data;
    try { data = await res.json(); } catch(err){
      container.innerHTML = '<div class="text-muted">Error loading.</div>';
      container.dataset.loaded='1';
      return;
    }
    if (!data.success) { container.innerHTML = '<div class="text-muted">No transactions.</div>'; return; }
    if (!data.rows.length) { container.innerHTML = '<div class="text-muted">No transactions.</div>'; container.dataset.loaded = '1'; return; }

    // Add Payment Method column
    let html = '<div class="transactions-table"><table><thead><tr><th>Date & Time</th><th>Products</th><th class="text-center">Quantity</th><th class="text-end">Amount Paid</th><th class="text-end">Amount Credited</th><th>Payment Method</th><th>Sold By</th></tr></thead><tbody>';
    data.rows.forEach(r=>{
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
                 <td>${escapeHtml(r.payment_method || '')}</td>
                 <td>${soldBy}</td>
               </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
    container.dataset.loaded = '1';
  });
});

// --- NEW: Per-customer report/export buttons on accordions ---
function attachCustomerActionHandlers() {
  document.querySelectorAll('.cust-report-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      const id = btn.dataset.id, name = btn.dataset.name || 'Customer';
      generateCustomerReport(id, name);
    });
  });
  document.querySelectorAll('.cust-export-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      const id = btn.dataset.id, name = btn.dataset.name || 'Customer';
      exportCustomerTransactions(id, name);
    });
  });
}
attachCustomerActionHandlers();

// Build printable report for a single customer's transactions
async function generateCustomerReport(customerId, customerName){
  const res = await fetch('customer_management.php?fetch_transactions=1&customer_id='+encodeURIComponent(customerId));
  const data = await res.json();
  if (!data.success || !data.rows.length) { alert('No transactions found for '+customerName); return; }

  let totalPaid = 0, totalCredited = 0;
  const rowsHtml = data.rows.map(r => {
    let prodDisplay = '', totalQty = 0;
    try {
      const pb = JSON.parse(r.products_bought || '[]');
      if (Array.isArray(pb)) {
        prodDisplay = pb.map(p => {
          const name = (p.name || p.product || '').toString();
          const qty = parseInt(p.quantity || p.qty || 0) || 0;
          totalQty += qty;
          return `${escapeHtml(name)} x${qty}`;
        }).join(', ');
      } else { prodDisplay = escapeHtml(String(r.products_bought || '')); }
    } catch { prodDisplay = escapeHtml(String(r.products_bought || '')); }
    const paid = parseFloat(r.amount_paid || 0); totalPaid += paid;
    const credited = parseFloat(r.amount_credited || 0); totalCredited += credited;
    return `<tr>
      <td>${escapeHtml(r.date_time||'')}</td>
      <td>${prodDisplay||'-'}</td>
      <td class="text-center">${totalQty}</td>
      <td class="text-end">UGX ${paid.toFixed(2)}</td>
      <td class="text-end">UGX ${credited.toFixed(2)}</td>
      <td>${escapeHtml(r.payment_method||'')}</td>           <!-- NEW -->
      <td>${escapeHtml(r.sold_by||'')}</td>
    </tr>`;
  }).join('');

  const html = `
<!DOCTYPE html>
<html>
<head>
  <title>${escapeHtml(customerName)} - Transactions Report</title>
  <meta charset="utf-8">
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background:#f8f9fa; color:#222; margin:0; padding:0; }
    .report-container { max-width: 900px; margin: 2rem auto; background:#fff; border-radius:14px; box-shadow:0 4px 24px #0002; padding:2rem 2.5rem; }
    .report-header { text-align:center; margin-bottom:2rem; }
    .report-title { font-size:2rem; font-weight:bold; color:#1abc9c; margin-bottom:.4rem; }
    .report-meta { font-size:1.05rem; color:#555; }
    table { width:100%; border-collapse:collapse; margin-top:1rem; }
    th, td { padding:.7rem 1rem; border-bottom:1px solid #e0e0e0; font-size:1rem; }
    th { background:#1abc9c; color:#fff; font-weight:600; }
    tbody tr:nth-child(even) { background:#f4f6f9; }
    tbody tr:hover { background:#e0f7fa; }
    tfoot td { font-weight:bold; }
    .print-btn { display:block; margin:1.5rem auto 0; padding:.7rem 2.5rem; font-size:1.1rem; background:#1abc9c; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer; box-shadow:0 2px 8px #0002; }
    @media print { .print-btn { display:none; } .report-container { box-shadow:none; border-radius:0; padding:.5rem; } }
  </style>
</head>
<body>
  <div class="report-container">
    <div class="report-header">
      <div class="report-title">Customer Transactions</div>
      <div class="report-meta">
        Customer: ${escapeHtml(customerName)}<br>
        Generated: ${new Date().toLocaleString()}
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Date & Time</th>
          <th>Products</th>
          <th class="text-center">Quantity</th>
          <th class="text-end">Amount Paid</th>
          <th class="text-end">Amount Credited</th>
          <th>Payment Method</th>                            <!-- NEW -->
          <th>Sold By</th>
        </tr>
      </thead>
      <tbody>${rowsHtml}</tbody>
      <tfoot>
        <tr>
          <td colspan="4">Totals</td>                        <!-- ADJ colspan for new column -->
          <td class="text-end">UGX ${totalCredited.toFixed(2)}</td>
          <td></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <button class="print-btn" onclick="window.print()">Print Report</button>
  </div>
  <script>
    function escapeHtml(s){return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])):'';}
  <\/script>
</body>
</html>`;
  const w = window.open('', '_blank');
  w.document.write(html);
  w.document.close();
}

// Export single customer's transactions to CSV (Excel-compatible)
async function exportCustomerTransactions(customerId, customerName){
  const res = await fetch('customer_management.php?fetch_transactions=1&customer_id='+encodeURIComponent(customerId));
  const data = await res.json();
  if (!data.success || !data.rows.length) { alert('No transactions found for '+customerName); return; }

  const header = ['Date & Time','Products','Quantity','Amount Paid','Amount Credited','Payment Method','Sold By']; // NEW
  const csvRows = [header.join(',')];

  data.rows.forEach(r => {
    let prodDisplay = '', totalQty = 0;
    try {
      const pb = JSON.parse(r.products_bought || '[]');
      if (Array.isArray(pb)) {
        prodDisplay = pb.map(p => {
          const name = (p.name || p.product || '').toString();
          const qty = parseInt(p.quantity || p.qty || 0) || 0;
          totalQty += qty;
          return `${name} x${qty}`;
        }).join('; ');
      } else { prodDisplay = String(r.products_bought || ''); }
    } catch { prodDisplay = String(r.products_bought || ''); }

    const row = [
      csvEscape(r.date_time||''),
      csvEscape(prodDisplay||''),
      String(totalQty),
      String(parseFloat(r.amount_paid||0)),
      String(parseFloat(r.amount_credited||0)),
      csvEscape(r.payment_method||''),                       // NEW
      csvEscape(r.sold_by||'')
    ];
    csvRows.push(row.join(','));
  });

  const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `customer_${customerId}_transactions.csv`;
  document.body.appendChild(a); a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

function csvEscape(v) {
  const s = String(v ?? '');
  if (/[",\n]/.test(s)) return '"' + s.replace(/"/g,'""') + '"';
  return s;
}

// ...existing code...
</script>
<?php include '../includes/footer.php'; ?>
