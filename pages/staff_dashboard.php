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
// Handle debtor record (no invoice/receipt)
if (isset($_POST['record_debtor'])) {
    $debtor_name    = trim($_POST['debtor_name']);
    $debtor_contact = trim($_POST['debtor_contact']);
    $debtor_email   = trim($_POST['debtor_email']);
    $created_by     = $user_id;
    $branch         = $branch_id;
    $date           = date('Y-m-d H:i:s');

    // Get cart and payment info from POST
    $cart = json_decode($_POST['cart_data'] ?? '[]', true);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);

    // Calculate item_taken, quantity_taken, total_amount
    $item_taken = '';
    $quantity_taken = 0;
    $total_amount = 0;
    if ($cart && is_array($cart)) {
        $item_names = [];
        foreach ($cart as $item) {
            $item_names[] = $item['name'];
            $quantity_taken += intval($item['quantity']);
            $total_amount += floatval($item['price']) * intval($item['quantity']);
        }
        $item_taken = implode(', ', $item_names);
    }
    $balance = $total_amount - $amount_paid;

    // Only insert if all required fields are present
    if ($debtor_name && $quantity_taken > 0 && $balance > 0 && !empty($item_taken)) {
        $stmt = $conn->prepare("INSERT INTO debtors (debtor_name, debtor_contact, debtor_email, item_taken, quantity_taken, amount_paid, balance, `branch-id`, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiddiss", $debtor_name, $debtor_contact, $debtor_email, $item_taken, $quantity_taken, $amount_paid, $balance, $branch, $created_by, $date);
        if ($stmt->execute()) {
            $message = "✅ Debtor recorded successfully!";
        } else {
            $message = "❌ Failed to record debtor: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "⚠️ Debtor name, item taken, quantity, and balance are required.";
    }
}

// Handle cart sale submission (must be BEFORE any HTML output)
if (isset($_POST['submit_cart']) && !empty($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $currentDate = date("Y-m-d");
    $conn->begin_transaction();
    $success = true;
    $messages = [];
    $total = 0;

    // Calculate total cart value
    foreach ($cart as $item) {
        $total += floatval($item['price']) * intval($item['quantity']);
    }

    // Only record sale if fully paid
    if ($amount_paid >= $total) {
        foreach ($cart as $item) {
            $product_id = (int)$item['id'];
            $quantity = (int)$item['quantity'];

            // Get product info
            $stmt = $conn->prepare("SELECT name, `selling-price`, `buying-price`, stock FROM products WHERE id = ? AND `branch-id` = ?");
            $stmt->bind_param("ii", $product_id, $branch_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product || $product['stock'] < $quantity) {
                $success = false;
                $messages[] = "Product not found or not enough stock for " . htmlspecialchars($item['name']);
                break;
            }

            $total_price  = $product['selling-price'] * $quantity;
            $cost_price   = $product['buying-price'] * $quantity;
            $total_profit = $total_price - $cost_price;

            // Insert sale row for each cart item
            $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiididd", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit);
            $stmt->execute();
            $stmt->close();

            // Update stock
            $new_stock = $product['stock'] - $quantity;
            $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $update->bind_param("ii", $new_stock, $product_id);
            $update->execute();
            $update->close();

            // Update profits
            $stmt = $conn->prepare("SELECT * FROM profits WHERE date = ? AND `branch-id` = ?");
            $stmt->bind_param("si", $currentDate, $branch_id);
            $stmt->execute();
            $profit_result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
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
        if ($success) {
            $conn->commit();
            $message = '✅ Sale recorded successfully!';
        } else {
            $conn->rollback();
            $message = implode(' ', $messages);
        }
    } else {
        // Not fully paid, do not record sale here (handled by debtor logic)
        $conn->rollback();
    }
}
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
    color: #000000ff;
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


</style>

<!-- Main Content -->

<br>
    <div class="welcome-banner mb-4" style="position:relative;overflow:hidden;">
        <div class="welcome-balls"></div>
        <h3 class="welcome-text" style="position:relative;z-index:2;">
            Welcome, <?= htmlspecialchars($_SESSION['username']); ?> 👋
        </h3>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info shadow-sm"><?= $message; ?></div>
    <?php endif; ?>

    <!-- Sale Entry Form -->
    <div class="card mb-4">
        <div class="card-header">Add Sale</div>
        <div class="card-body">
            <form id="addSaleForm" class="row g-3" onsubmit="return false;">
                <div class="col-md-6">
                    <label for="product_id" class="form-label">Product</label>
                    <select class="form-select" name="product_id" id="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php
                        // Re-query products for JS cart
                        $product_query2 = $conn->prepare("SELECT id, name, stock, `selling-price` FROM products WHERE `branch-id` = ?");
                        $product_query2->bind_param("i", $branch_id);
                        $product_query2->execute();
                        $products_for_js = $product_query2->get_result();
                        $product_list = [];
                        while ($row = $products_for_js->fetch_assoc()) {
                            $product_list[$row['id']] = $row;
                            echo '<option value="' . $row['id'] . '" ' . ($row['stock'] < 10 ? 'class="low-stock"' : '') . '>' . htmlspecialchars($row['name']) . ' (Stock: ' . $row['stock'] . ($row['stock'] < 10 ? ' 🔴 Low' : '') . ')</option>';
                        }
                        $product_query2->close();
                        ?>
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
            <div id="cartSection" style="display:none; margin-top:1.5rem;">
                <h6>Cart</h6>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="cartItems"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total</td>
                                <td id="cartTotal" class="fw-bold">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="amount_paid" class="form-label">Amount Paid</label>
                        <input type="number" class="form-control" id="amount_paid" min="0" value="">
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="sellBtn" class="btn btn-success w-100">Sell</button>
                    </div>
                </div>
                <div id="cartMessage" class="mt-2"></div>
            </div>
        </div>
    </div>
    <script>
    // Cart logic
    const productData = <?php echo json_encode($product_list); ?>;
    let cart = [];

    // Hidden form for submitting cart to PHP
    const hiddenSaleForm = document.createElement('form');
    hiddenSaleForm.method = 'POST';
    hiddenSaleForm.style.display = 'none';
    hiddenSaleForm.innerHTML = `
        <input type="hidden" name="cart_data" id="cart_data">
        <input type="hidden" name="amount_paid" id="cart_amount_paid">
        <input type="hidden" name="submit_cart" value="1">
    `;
    document.body.appendChild(hiddenSaleForm);

    document.getElementById('sellBtn').onclick = function() {
        const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
        const amountPaid = parseInt(document.getElementById('amount_paid').value, 10) || 0;
        const debtorsFormCard = document.getElementById('debtorsFormCard');
        const cartMessage = document.getElementById('cartMessage');

        if (cart.length === 0) {
            cartMessage.innerHTML = '<span class="text-danger">Cart is empty.</span>';
            debtorsFormCard.style.display = 'none';
            return;
        }

        // Overpayment check
        if (amountPaid > total) {
            const balance = amountPaid - total;
            showBalanceModal(balance, function() {
                // After OK, proceed with sale as normal
                document.getElementById('cart_data').value = JSON.stringify(cart);
                document.getElementById('cart_amount_paid').value = amountPaid;
                document.getElementById('cartSection').style.display = 'none';
                hiddenSaleForm.submit();
                debtorsFormCard.style.display = 'none';
                cart = [];
                updateCartUI();
                // Reload page after short delay to ensure PHP processes sale and table updates
                setTimeout(() => window.location.reload(), 600);
            });
            return;
        }

        if (amountPaid >= total) {
            document.getElementById('cart_data').value = JSON.stringify(cart);
            document.getElementById('cart_amount_paid').value = amountPaid;
            document.getElementById('cartSection').style.display = 'none';
            hiddenSaleForm.submit();
            debtorsFormCard.style.display = 'none';
            cart = [];
            updateCartUI();
            setTimeout(() => window.location.reload(), 600);
        } else {
            cartMessage.innerHTML = '<span class="text-warning">Amount paid is less than total. Please record debtor information below.</span>';
            document.getElementById('debtor_cart_data').value = JSON.stringify(cart);
            document.getElementById('debtor_amount_paid').value = amountPaid;
            debtorsFormCard.style.display = '';
            debtorsFormCard.scrollIntoView({behavior:'smooth'});
        }
    };

    // Show balance modal for overpayment, and call callback on OK
    function showBalanceModal(balance, callback) {
        let modal = document.getElementById('overpayModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'overpayModal';
            modal.style.position = 'fixed';
            modal.style.top = 0;
            modal.style.left = 0;
            modal.style.width = '100vw';
            modal.style.height = '100vh';
            modal.style.background = 'rgba(0,0,0,0.3)';
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            modal.style.zIndex = 99999;
            document.body.appendChild(modal);
        }
        modal.innerHTML = `
            <div style="background:#fff;padding:2rem 2.5rem;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.18);text-align:center;max-width:350px;">
                <div style="font-size:1.2rem;margin-bottom:1rem;">
                    <strong>Balance is UGX ${balance.toLocaleString()}</strong>
                </div>
                <button id="overpayOkBtn" class="btn btn-primary">OK</button>
            </div>
        `;
        document.getElementById('overpayOkBtn').onclick = function() {
            modal.remove();
            if (typeof callback === 'function') callback();
        };
    }

    function updateCartUI() {
        const cartSection = document.getElementById('cartSection');
        const cartItems = document.getElementById('cartItems');
        const cartTotal = document.getElementById('cartTotal');
        if (cart.length === 0) {
            cartSection.style.display = 'none';
            return;
        }
        cartSection.style.display = '';
        let total = 0;
        cartItems.innerHTML = cart.map((item, idx) => {
            const subtotal = item.quantity * item.price;
            total += subtotal;
            return `<tr>
                <td>${item.name}</td>
                <td>${item.quantity}</td>
                <td>UGX ${item.price.toLocaleString()}</td>
                <td>UGX ${subtotal.toLocaleString()}</td>
                <td><button class='btn btn-sm btn-danger' onclick='removeCartItem(${idx})'>Remove</button></td>
            </tr>`;
        }).join('');
        cartTotal.textContent = 'UGX ' + total.toLocaleString();
    }
    function removeCartItem(idx) {
        cart.splice(idx, 1);
        updateCartUI();
    }
    document.getElementById('addToCartBtn').onclick = function() {
        const productId = document.getElementById('product_id').value;
        const quantity = parseInt(document.getElementById('quantity').value, 10);
        if (!productId || !quantity || quantity < 1) return;
        const prod = productData[productId];
        if (!prod) return;
        // Check if already in cart
        const existing = cart.find(item => item.id == productId);
        if (existing) {
            existing.quantity += quantity;
        } else {
            cart.push({ id: productId, name: prod.name, price: parseInt(prod['selling-price'],10), quantity });
        }
        updateCartUI();
        document.getElementById('addSaleForm').reset();
    };
    </script>

    <!-- Debtors Entry Form (hidden, shown by JS if needed) -->
    <div id="debtorsFormCard" class="card mb-4" style="display:none;">
        <div class="card-header">Record Debtor</div>
        <div class="card-body">
            <form method="POST" action="" class="row g-3">
                <input type="hidden" name="cart_data" id="debtor_cart_data">
                <input type="hidden" name="amount_paid" id="debtor_amount_paid">
                <div class="col-md-4">
                    <label for="debtor_name" class="form-label">Debtor Name</label>
                    <input type="text" class="form-control" name="debtor_name" id="debtor_name" required>
                </div>
                <div class="col-md-3">
                    <label for="debtor_contact" class="form-label">Contact</label>
                    <input type="text" class="form-control" name="debtor_contact" id="debtor_contact">
                </div>
                <div class="col-md-3">
                    <label for="debtor_email" class="form-label">Email</label>
                    <input type="email" class="form-control" name="debtor_email" id="debtor_email">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="record_debtor" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus"></i> Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Low Stock Products Panel -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">⚠️ Low Stock Products (Branch <?= $branch_id; ?>)</div>
        <div class="card-body">
            <?php if ($low_stock_query->num_rows > 0): ?>
                <ul class="list-group">
                    <?php while ($low = $low_stock_query->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($low['name']); ?>
                            <span class="badge bg-danger rounded-pill"><?= $low['stock']; ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted fst-italic">All products have sufficient stock in your branch.</p>
            <?php endif; ?>
        </div>
    </div>


    <!-- Tabs for Sales and Debtors -->
    <?php
    // Fetch debtors for this branch
    $debtors_stmt = $conn->prepare("SELECT id, debtor_name, debtor_contact, debtor_email, item_taken, quantity_taken, amount_paid, balance, is_paid, created_at FROM debtors WHERE `branch-id` = ? ORDER BY created_at DESC LIMIT 10");
    $debtors_stmt->bind_param("i", $branch_id);
    $debtors_stmt->execute();
    $debtors_result = $debtors_stmt->get_result();
    $debtors_stmt->close();
    ?>
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
            <div class="card mb-4">
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
            <div class="card mb-4">
                <div class="card-header">Debtors</div>
                <div class="card-body">
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Debtor Name</th>
                                    <th>Debtor Email</th>
                                    <th>Item Taken</th>
                                    <th>Quantity Taken</th>
                                    <th>Amount Paid</th>
                                    <th>Balance</th>
                                    <th>Paid Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($debtors_result->num_rows > 0): ?>
                                <?php while ($debtor = $debtors_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date("M d, Y H:i", strtotime($debtor['created_at'])); ?></td>
                                        <td><?= htmlspecialchars($debtor['debtor_name']); ?></td>
                                        <td><?= htmlspecialchars($debtor['debtor_email']); ?></td>
                                        <td><?= htmlspecialchars($debtor['item_taken'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($debtor['quantity_taken'] ?? '-'); ?></td>
                                        <td>UGX <?= number_format($debtor['amount_paid'] ?? 0, 2); ?></td>
                                        <td>UGX <?= number_format($debtor['balance'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php if (!empty($debtor['is_paid'])): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-success btn-sm">Mark as Paid</button>
                                            <button class="btn btn-primary btn-sm">Pay</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No debtors recorded yet in this branch.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


<script>
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
    // Sell button logic
    document.getElementById('sellBtn').onclick = function() {
        const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
        const amountPaid = parseInt(document.getElementById('amount_paid').value, 10) || 0;
        const debtorsFormCard = document.getElementById('debtorsFormCard');
        const cartMessage = document.getElementById('cartMessage');

        if (cart.length === 0) {
            cartMessage.innerHTML = '<span class="text-danger">Cart is empty.</span>';
            debtorsFormCard.style.display = 'none';
            return;
        }

        // Overpayment check
        if (amountPaid > total) {
            const balance = amountPaid - total;
            showBalanceModal(balance, function() {
                // After OK, proceed with sale as normal
                document.getElementById('cart_data').value = JSON.stringify(cart);
                document.getElementById('cart_amount_paid').value = amountPaid;
                document.getElementById('cartSection').style.display = 'none';
                hiddenSaleForm.submit();
                debtorsFormCard.style.display = 'none';
                cart = [];
                updateCartUI();
                // Reload page after short delay to ensure PHP processes sale and table updates
                setTimeout(() => window.location.reload(), 600);
            });
            return;
        }

        if (amountPaid >= total) {
            document.getElementById('cart_data').value = JSON.stringify(cart);
            document.getElementById('cart_amount_paid').value = amountPaid;
            document.getElementById('cartSection').style.display = 'none';
            hiddenSaleForm.submit();
            debtorsFormCard.style.display = 'none';
            cart = [];
            updateCartUI();
            setTimeout(() => window.location.reload(), 600);
        } else {
            cartMessage.innerHTML = '<span class="text-warning">Amount paid is less than total. Please record debtor information below.</span>';
            document.getElementById('debtor_cart_data').value = JSON.stringify(cart);
            document.getElementById('debtor_amount_paid').value = amountPaid;
            debtorsFormCard.style.display = '';
            debtorsFormCard.scrollIntoView({behavior:'smooth'});
        }
    };
// Handle cart sale submission (add after existing sale logic)
if (isset($_POST['submit_cart']) && !empty($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $currentDate = date("Y-m-d");
    $conn->begin_transaction();
    $success = true;
    $messages = [];
    $total = 0;

    foreach ($cart as $item) {
        $product_id = (int)$item['id'];
        $quantity = (int)$item['quantity'];
        // Get product info
        $stmt = $conn->prepare("SELECT name, `selling-price`, `buying-price`, stock FROM products WHERE id = ? AND `branch-id` = ?");
        $stmt->bind_param("ii", $product_id, $branch_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$product || $product['stock'] < $quantity) {
            $success = false;
            $messages[] = "Product not found or not enough stock for " . htmlspecialchars($item['name']);
            break;
        }
        $total_price  = $product['selling-price'] * $quantity;
        $cost_price   = $product['buying-price'] * $quantity;
        $total_profit = $total_price - $cost_price;
        $total += $total_price;

        // Only record sale if amount_paid >= total (fully paid)
        // If not fully paid, do NOT record sale here (handled by debtor logic)
        if ($amount_paid >= $total) {
            $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiididd", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit);
            $stmt->execute();
            $stmt->close();

            $new_stock = $product['stock'] - $quantity;
            $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $update->bind_param("ii", $new_stock, $product_id);
            $update->execute();
            $update->close();

            // Update profits
            $stmt = $conn->prepare("SELECT * FROM profits WHERE date = ? AND `branch-id` = ?");
            $stmt->bind_param("si", $currentDate, $branch_id);
            $stmt->execute();
            $profit_result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
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

    if ($success && $amount_paid >= $total) {
        $conn->commit();
        $message = '✅ Sale recorded successfully!';
    } else if ($success && $amount_paid < $total) {
        // Do not record sale, handled by debtor logic
        $conn->rollback();
        // Optionally, set a message here if needed
    } else {
        $conn->rollback();
        $message = implode(' ', $messages);
    }
}
    </script>

<?php include '../includes/footer.php'; ?>
        }
    }

    if ($success && $amount_paid >= $total) {
        $conn->commit();
        $message = '✅ Sale recorded successfully!';
    } else if ($success && $amount_paid < $total) {
        // Do not record sale, handled by debtor logic
        $conn->rollback();
        // Optionally, set a message here if needed
    } else {
        $conn->rollback();
        $message = implode(' ', $messages);
    }
}
    </script>

<?php include '../includes/footer.php'; ?>

