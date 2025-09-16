<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(['staff']);
include '../pages/sidebar_staff.php';
include '../includes/header.php';

if ($_SESSION['role'] !== 'staff') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$branch_id = $_SESSION['branch_id']; // ✅ fixed
$message   = "";

// Handle sale submission
if (isset($_POST['add_sale'])) {
    $product_id = $_POST['product_id'];
    $quantity   = $_POST['quantity'];

    $stmt = $conn->prepare("SELECT name, `selling-price`, `buying-price`, `branch-id`, stock FROM products WHERE id = ? AND `branch-id` = ?");
    $stmt->bind_param("ii", $product_id, $branch_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentDate = date("Y-m-d");

    $stmt = $conn->prepare("SELECT * FROM profits WHERE date = ? AND `branch-id` = ?");
    $stmt->bind_param("si", $currentDate, $branch_id);
    $stmt->execute();
    $profit_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $message = "⚠️ Product not found or not in your branch.";
    } elseif ($product['stock'] < $quantity) {
        $message = "⚠️ Not enough stock available!";
    } else {
        $total_price  = $product['selling-price'] * $quantity;
        $cost_price   = $product['buying-price'] * $quantity;
        $total_profit = $total_price - $cost_price;

        $stmt = $conn->prepare("
            INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiididd", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit);
        $stmt->execute();
        $stmt->close();

        $new_stock = $product['stock'] - $quantity;
        $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $update->bind_param("ii", $new_stock, $product_id);
        $update->execute();
        $update->close();

        $message = "✅ Sale recorded successfully!";
        if ($new_stock < 10) {
            $message .= "<br>⚠️ Stock for <strong>" . htmlspecialchars($product['name']) . "</strong> is below threshold ({$new_stock} left).";
        }

        // Update profits
        if ($profit_result) {
            $total_amount = $profit_result['total'] + $total_profit;
            $expenses     = $profit_result['expenses'] ?? 0;
            $net_profit   = $total_amount - $expenses;

            $stmt2 = $conn->prepare("UPDATE profits SET total=?, `net-profits`=? WHERE date=? AND `branch-id`=?");
            $stmt2->bind_param("ddsi", $total_amount, $net_profit, $currentDate, $branch_id);
            $stmt2->execute();
            $stmt2->close();
        } else {
            $total_amount = $total_profit;
            $net_profit   = $total_profit;
            $expenses     = 0;

            $stmt2 = $conn->prepare("INSERT INTO profits (`branch-id`, total, `net-profits`, expenses, date) VALUES (?, ?, ?, ?, ?)");
            $stmt2->bind_param("iddis", $branch_id, $total_amount, $net_profit, $expenses, $currentDate);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

// Fetch products for dropdown
$stmt = $conn->prepare("SELECT id, name, stock FROM products WHERE `branch-id` = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$product_query = $stmt->get_result();
$stmt->close();

// Fetch low stock
$stmt = $conn->prepare("SELECT name, stock FROM products WHERE `branch-id` = ? AND stock < 10 ORDER BY stock ASC");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$low_stock_query = $stmt->get_result();
$stmt->close();

// Fetch recent sales
$sales_stmt = $conn->prepare("
    SELECT s.id, p.name, s.quantity, s.amount, s.total_profits, s.date 
    FROM sales s 
    JOIN products p ON s.`product-id` = p.id 
    WHERE s.`branch-id` = ? 
    ORDER BY s.date DESC 
    LIMIT 10
");
$sales_stmt->bind_param("i", $branch_id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
$sales_stmt->close();
?>

<style>
/* Sidebar styling to match sidebar_admin
.sidebar {
    width: 250px;
    min-height: 100vh;
    background: #2c3e50;
    color: #fff;
    padding: 1rem;
    transition: width 0.3s ease;
    position: fixed;
    top: 0; left: 0;
    z-index: 10;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
}
.sidebar-title {
    text-align: center;
    margin-bottom: 1.5rem;
    font-weight: 700;
    font-size: 1.4rem;
    color: #1abc9c;
    letter-spacing: 1px;
}
.sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}
.sidebar-nav li {
    margin: 0.5rem 0;
}
.sidebar-nav li a {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 1rem;
    color: #fff;
    transition: background 0.2s, color 0.2s;
    gap: 0.5rem;
}
.sidebar-nav li a i {
    margin-right: 0.5rem;
    font-size: 1.1rem;
}
.sidebar-nav li a:hover,
.sidebar-nav li a.active {
    background: var(--primary-color, #1abc9c);
    color: #fff;
    text-decoration: none;
}
.sidebar-nav li a.text-danger {
    color: #e74c3c !important;
}
.sidebar-nav li a.text-danger:hover {
    background: #e74c3c !important;
    color: #fff !important;
}
@media (max-width: 768px) {
    .sidebar { width: 100%; min-height: auto; position: relative; border-radius: 0; }
} */

/* Welcome Banner (same as admin_dashboard) */
.welcome-banner {
    background: linear-gradient(90deg, #1abc9c 0%, #56ccf2 100%);
    border-radius: 14px;
    padding: 1.5rem 2rem;
    box-shadow: 0 2px 12px var(--card-shadow);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    position: relative;
    overflow: hidden;
}
.welcome-balls {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 1;
    pointer-events: none;
}
.welcome-ball {
    position: absolute;
    border-radius: 50%;
    opacity: 0.18;
    transition: background 0.3s;
    box-shadow: 0 2px 12px rgba(44,62,80,0.12);
    will-change: left, top;
}
.welcome-text {
    color: #fff;
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: 1px;
    margin: 0;
    text-shadow: 0 2px 8px rgba(44,62,80,0.12);
}
body.dark-mode .welcome-banner {
    background: linear-gradient(90deg, #23243a 0%, #1abc9c 100%);
    box-shadow: 0 2px 12px rgba(44,62,80,0.18);
}
body.dark-mode .welcome-text {
    color: #ffd200;
    text-shadow: 0 2px 8px rgba(44,62,80,0.18);
}

/* Table Styling (like manager_dashboard) */
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
body.dark-mode .transactions-table table {
    background: var(--card-bg);
}
body.dark-mode .transactions-table thead {
    background-color: #1abc9c;
    color: #ffffff;
}
body.dark-mode .transactions-table tbody tr {
    background-color: #2c2c3a !important;
}
body.dark-mode .transactions-table tbody tr:nth-child(even) {
    background-color: #272734 !important;
}
body.dark-mode .transactions-table tbody td {
    color: #ffffff !important;
}
body.dark-mode .transactions-table tbody tr:hover {
    background-color: rgba(255,255,255,0.1) !important;
}

/* Card header theme for tables */
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

/* Form styling */
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
.cart-table {
    width: 100%;
    margin-top: 1rem;
    border-radius: 8px;
    background: var(--card-bg);
    box-shadow: 0 2px 8px var(--card-shadow);
    overflow: hidden;
}
.cart-table th, .cart-table td {
    padding: 0.5rem 1rem;
    font-size: 1rem;
}
.cart-table th {
    background: var(--primary-color);
    color: #fff;
}
.cart-table tbody tr {
    background: #fff;
}
body.dark-mode .cart-table th {
    background: #1abc9c;
    color: #fff;
}
body.dark-mode .cart-table tbody tr {
    background: #2c2c3a;
    color: #fff;
}
.cart-total-row td {
    font-weight: bold;
    font-size: 1.1rem;
}
.receipt-modal, .invoice-modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(44,62,80,0.18);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.receipt-content, .invoice-content {
    background: #fff;
    border-radius: 10px;
    padding: 2rem 1.5rem;
    min-width: 320px;
    max-width: 350px;
    box-shadow: 0 4px 24px rgba(44,62,80,0.18);
    font-family: monospace;
    position: relative;
}
body.dark-mode .receipt-content, body.dark-mode .invoice-content {
    background: #23243a;
    color: #fff;
}
.receipt-close, .invoice-close {
    position: absolute;
    top: 0.5rem; right: 0.5rem;
    font-size: 1.3rem;
    color: #e74c3c;
    cursor: pointer;
    background: none;
    border: none;
}
.receipt-actions, .invoice-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1rem;
}
@media (max-width: 500px) {
    .receipt-content, .invoice-content { min-width: 90vw; max-width: 98vw; padding: 1rem 0.5rem; }
}
</style>

<!-- Main Content -->


    <div class="welcome-banner mb-4" style="position:relative;overflow:hidden;">
        <div class="welcome-balls"></div>
        <h3 class="welcome-text" style="position:relative;z-index:2;">
            Welcome, <?= htmlspecialchars($_SESSION['username']); ?> 👋
        </h3>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info shadow-sm"><?= $message; ?></div>
    <?php endif; ?>

    <!-- Add Sale Form with Cart -->
    <div class="card mb-4">
        <div class="card-header">Add Sale</div>
        <div class="card-body">
            <form id="addSaleForm" class="row g-3" onsubmit="return false;">
                <div class="col-md-6">
                    <label for="product_id" class="form-label">Product</label>
                    <select class="form-select" name="product_id" id="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php
                        $product_options = [];
                        $product_query->data_seek(0);
                        while ($row = $product_query->fetch_assoc()):
                            $product_options[] = $row;
                        ?>
                            <option value="<?= $row['id']; ?>" data-name="<?= htmlspecialchars($row['name']) ?>" data-stock="<?= $row['stock'] ?>">
                                <?= htmlspecialchars($row['name']); ?> (Stock: <?= $row['stock']; ?><?= $row['stock'] < 10 ? ' 🔴 Low' : '' ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="quantity" id="quantity" required min="1">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" id="addToCartBtn" class="btn btn-primary w-100">
                        <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                </div>
            </form>
            <!-- Cart Section -->
            <div id="cartSection" style="display:none;">
                <table class="cart-table mt-3">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Cost</th>
                            <th>Remove</th>
                        </tr>
                    </thead>
                    <tbody id="cartBody"></tbody>
                    <tfoot>
                        <tr class="cart-total-row">
                            <td colspan="2">Total</td>
                            <td id="cartTotal">UGX 0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="amount_paid" class="form-label">Amount Paid</label>
                        <input type="number" class="form-control" id="amount_paid" min="0" value="0">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" id="sellBtn" class="btn btn-success w-100">
                            <i class="bi bi-cash-coin"></i> Sell
                        </button>
                    </div>
                </div>
            </div>
            <!-- Debtor Form (hidden by default) -->
            <div id="debtorFormSection" style="display:none; margin-top:2rem;">
                <form id="debtorForm" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Debtor Name</label>
                        <input type="text" class="form-control" id="debtor_name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Debtor Contact</label>
                        <input type="text" class="form-control" id="debtor_contact">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Debtor Email</label>
                        <input type="email" class="form-control" id="debtor_email">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Terms & Conditions</label>
                        <textarea class="form-control" id="debtor_terms" rows="2"></textarea>
                    </div>
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="button" id="recordDebtorBtn" class="btn btn-warning">Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabs for Sales and Debtors -->
    <ul class="nav nav-tabs mb-4" id="salesTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales-table" type="button" role="tab" aria-controls="sales-table" aria-selected="true">
                Sales Records
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="debtors-tab" data-bs-toggle="tab" data-bs-target="#debtors-table" type="button" role="tab" aria-controls="debtors-table" aria-selected="false">
                Debtors
            </button>
        </li>
    </ul>
    <div class="tab-content" id="salesTabsContent">
        <!-- Sales Table Tab -->
        <div class="tab-pane fade show active" id="sales-table" role="tabpanel" aria-labelledby="sales-tab">
            <!-- ...existing recent sales table... -->
            <div class="card">
                <div class="card-header">Recent Sales</div>
                <div class="card-body">
                    <?php if ($sales_result->num_rows > 0): ?>
                        <div class="transactions-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Qty</th>
                                        <th>Total</th>
                                        <th>Sold By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sale = $sales_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date("M d, Y H:i", strtotime($sale['date'])); ?></td>
                                            <td><i class="bi bi-box"></i> <?= htmlspecialchars($sale['name']); ?></td>
                                            <td><?= $sale['quantity']; ?></td>
                                            <td><span class="badge bg-success">UGX <?= number_format($sale['amount'], 2); ?></span></td>
                                            <td><?= htmlspecialchars($username); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted fst-italic">No sales recorded yet in this branch.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Debtors Table Tab -->
        <div class="tab-pane fade" id="debtors-table" role="tabpanel" aria-labelledby="debtors-tab">
            <div class="card mb-4 chart-card">
                <div class="card-header bg-light text-black fw-bold">
                    <i class="fa-solid fa-user-clock"></i> Debtors
                </div>
                <div class="card-body table-responsive">
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Invoice No.</th>
                                    <th>Debtor Name</th>
                                    <th>Debtor Contact</th>
                                    <th>Debtor Email</th>
                                    <th>Quantity Taken</th>
                                    <th>Amount Paid</th>
                                    <th>Balance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- TODO: Fetch and display debtors for this branch -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


<script>
// --- Cart Logic ---
let cart = [];
let cartTotal = 0;
const productOptions = <?= json_encode($product_options) ?>;
const addToCartBtn = document.getElementById('addToCartBtn');
const cartSection = document.getElementById('cartSection');
const cartBody = document.getElementById('cartBody');
const cartTotalTd = document.getElementById('cartTotal');
const amountPaidInput = document.getElementById('amount_paid');
const sellBtn = document.getElementById('sellBtn');
const debtorFormSection = document.getElementById('debtorFormSection');
const receiptModal = document.getElementById('receiptModal');
const receiptContent = document.getElementById('receiptContent');
const invoiceModal = document.getElementById('invoiceModal');
const invoiceContent = document.getElementById('invoiceContent');

function updateCartTable() {
    cartBody.innerHTML = '';
    cartTotal = 0;
    cart.forEach((item, idx) => {
        cartTotal += item.price * item.qty;
        cartBody.innerHTML += `
            <tr>
                <td>${item.name}</td>
                <td>${item.qty}</td>
                <td>UGX ${Number(item.price * item.qty).toLocaleString()}</td>
                <td><button class="btn btn-sm btn-danger" onclick="removeCartItem(${idx})">&times;</button></td>
            </tr>
        `;
    });
    cartTotalTd.textContent = 'UGX ' + cartTotal.toLocaleString();
    cartSection.style.display = cart.length > 0 ? '' : 'none';
}
window.removeCartItem = function(idx) {
    cart.splice(idx, 1);
    updateCartTable();
};

addToCartBtn.onclick = function() {
    const productId = document.getElementById('product_id').value;
    const qty = parseInt(document.getElementById('quantity').value, 10);
    if (!productId || !qty || qty < 1) return;
    const product = productOptions.find(p => p.id == productId);
    if (!product) return;
    // For demo, assume price is not available, set to 1000
    let price = 1000;
    // TODO: fetch price from backend if needed
    // If already in cart, increase qty
    let found = cart.find(item => item.id == productId);
    if (found) {
        found.qty += qty;
    } else {
        cart.push({id: productId, name: product.name, qty, price});
    }
    updateCartTable();
    document.getElementById('product_id').value = '';
    document.getElementById('quantity').value = '';
};

// --- Sell Logic ---
sellBtn.onclick = function() {
    const amountPaid = parseFloat(amountPaidInput.value) || 0;
    if (cart.length === 0) return;
    if (amountPaid >= cartTotal) {
        updateCartTable();
        amountPaidInput.value = '';
    } else {
        // Show debtor form
        debtorFormSection.style.display = '';
        document.getElementById('debtor_name').focus();
    }
};

// --- Debtor Logic ---
document.getElementById('recordDebtorBtn').onclick = function() {
    // Save debtor info (AJAX or form submit)
    // For demo, just show invoice
    const debtor = {
        name: document.getElementById('debtor_name').value,
        contact: document.getElementById('debtor_contact').value,
        email: document.getElementById('debtor_email').value,
        terms: document.getElementById('debtor_terms').value
    };
    cart = [];
    updateCartTable();
    amountPaidInput.value = '';
    debtorFormSection.style.display = 'none';
    // TODO: Save debtor to backend
};



// Load html2canvas for saving as image
(function(){
    var script = document.createElement('script');
    script.src = "https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js";
    document.head.appendChild(script);
})();

// Welcome balls animation (same as admin_dashboard)
(function() {
  const banner = document.querySelector('.welcome-banner');
  const ballsContainer = document.querySelector('.welcome-balls');
  if (!banner || !ballsContainer) return;

  function getColors() {
    if (document.body.classList.contains('dark-mode')) {
      return ['#ffd200', '#1abc9c', '#56ccf2', '#23243a', '#fff'];
    } else {
      return ['#1abc9c', '#56ccf2', '#ffd200', '#3498db', '#fff'];
    }
  }

  ballsContainer.innerHTML = '';
  ballsContainer.style.position = 'absolute';
  ballsContainer.style.top = 0;
  ballsContainer.style.left = 0;
  ballsContainer.style.width = '100%';
  ballsContainer.style.height = '100%';
  ballsContainer.style.zIndex = 1;
  ballsContainer.style.pointerEvents = 'none';

  const balls = [];
  const colors = getColors();
  const numBalls = 7;
  for (let i = 0; i < numBalls; i++) {
    const ball = document.createElement('div');
    ball.className = 'welcome-ball';
    ball.style.position = 'absolute';
    ball.style.borderRadius = '50%';
    ball.style.opacity = '0.18';
    ball.style.background = colors[i % colors.length];
    ball.style.width = ball.style.height = (32 + Math.random() * 32) + 'px';
    ball.style.top = (10 + Math.random() * 60) + '%';
    ball.style.left = (5 + Math.random() * 85) + '%';
    ballsContainer.appendChild(ball);
    balls.push({
      el: ball,
      x: parseFloat(ball.style.left),
      y: parseFloat(ball.style.top),
      r: Math.random() * 0.5 + 0.2,
      dx: (Math.random() - 0.5) * 0.2,
      dy: (Math.random() - 0.5) * 0.2
    });
  }

  function animateBalls() {
    balls.forEach(ball => {
      ball.x += ball.dx;
      ball.y += ball.dy;
      if (ball.x < 0 || ball.x > 95) ball.dx *= -1;
      if (ball.y < 5 || ball.y > 80) ball.dy *= -1;
      ball.el.style.left = ball.x + '%';
      ball.el.style.top = ball.y + '%';
    });
    requestAnimationFrame(animateBalls);
  }
  animateBalls();

  window.addEventListener('storage', () => {
    const newColors = getColors();
    balls.forEach((ball, i) => {
      ball.el.style.background = newColors[i % newColors.length];
    });
  });
  document.getElementById('themeToggle')?.addEventListener('change', () => {
    const newColors = getColors();
    balls.forEach((ball, i) => {
      ball.el.style.background = newColors[i % newColors.length];
    });
  });
})();
</script>

<?php include '../includes/footer.php'; ?>

