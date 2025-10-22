<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(['staff']);
include '../pages/sidebar_staff.php';
include '../includes/header.php';
include 'handle_debtor_payment.php';
include 'handle_cart_sale.php';

// Ensure customers.amount_credited column exists to avoid "Unknown column" errors.
$checkCol = $conn->query("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'amount_credited'
");
if (!$checkCol || $checkCol->num_rows === 0) {
    // add the missing column safely
    $conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS amount_credited DECIMAL(12,2) NOT NULL DEFAULT 0");
    // defensive: if IF NOT EXISTS isn't supported, ignore error (avoid fatal)
    if ($conn->errno) {
        // try without IF NOT EXISTS for MySQL versions that don't support it, suppress warnings
        @$conn->query("ALTER TABLE customers ADD COLUMN amount_credited DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
}

// Ensure sales.customer_id column exists to avoid "Unknown column 'customer_id'" errors.
// Place this check before any INSERT INTO sales (...) that includes customer_id.
$checkSalesCol = $conn->query("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sales'
      AND COLUMN_NAME = 'customer_id'
");
if (!$checkSalesCol || $checkSalesCol->num_rows === 0) {
    // Add nullable customer_id column to sales table.
    // Avoid adding foreign key here to keep migration simple and permission-safe.
    $conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT NULL");
    if ($conn->errno) {
        // For MySQL versions that don't support IF NOT EXISTS in ALTER, try without it, suppress warnings
        @$conn->query("ALTER TABLE sales ADD COLUMN customer_id INT NULL");
    }
}

// --- NEW: ensure customer_transactions.status column exists (prevents INSERT/SELECT failures) ---
$checkCTCol = $conn->query("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customer_transactions'
      AND COLUMN_NAME = 'status'
");
if (!$checkCTCol || $checkCTCol->num_rows === 0) {
    // Add a simple status column to record 'paid' / 'debtor' etc.
    $conn->query("ALTER TABLE customer_transactions ADD COLUMN IF NOT EXISTS `status` VARCHAR(32) DEFAULT 'pending'");
    if ($conn->errno) {
        // Fallback for MySQL versions that don't support IF NOT EXISTS in ALTER
        @$conn->query("ALTER TABLE customer_transactions ADD COLUMN `status` VARCHAR(32) DEFAULT 'pending'");
    }
}

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
    SELECT s.id, p.name, s.quantity, s.amount, s.payment_method, s.date
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
    SELECT s.id, p.name, s.quantity, s.amount, s.payment_method, s.total_profits, s.date 
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
        $stmt = $conn->prepare("INSERT INTO debtors (debtor_name, debtor_contact, debtor_email, item_taken, quantity_taken, amount_paid, balance, branch_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
    // prefer explicit hidden fields if present
    $payment_method = $_POST['payment_method'] ?? $_POST['hidden_payment_method'] ?? 'Cash';
    $customer_id = intval($_POST['customer_id'] ?? $_POST['hidden_customer_id'] ?? 0);
    $currentDate = date("Y-m-d");
    $user_name = $_SESSION['username'];
    $user_id = $_SESSION['user_id'];
    $conn->begin_transaction();
    $success = true;
    $messages = [];

    // compute total server-side
    $total = 0.0;
    foreach ($cart as $item) $total += floatval($item['price']) * intval($item['quantity']);

    if ($payment_method === 'Customer File') {
        if ($customer_id <= 0) {
            $conn->rollback();
            $message = "⚠️ Select a customer for Customer File payment.";
        } else {
            // fetch customer balance
            $cstmt = $conn->prepare("SELECT id, COALESCE(account_balance,0) AS account_balance, COALESCE(amount_credited,0) AS amount_credited FROM customers WHERE id = ?");
            $cstmt->bind_param("i", $customer_id);
            $cstmt->execute();
            $cust = $cstmt->get_result()->fetch_assoc();
            $cstmt->close();

            if (!$cust) {
                $conn->rollback();
                $message = "⚠️ Customer not found.";
            } else {
                $available = floatval($cust['account_balance']);
                if ($available >= $total) {
                    // fully covered: deduct, insert sales per item, record customer_transactions as 'paid'
                    $new_balance = $available - $total;
                    $ust = $conn->prepare("UPDATE customers SET account_balance = ? WHERE id = ?");
                    $ust->bind_param("di", $new_balance, $customer_id);
                    $ust->execute();
                    $ust->close();

                    foreach ($cart as $item) {
                        $product_id = intval($item['id']);
                        $quantity = intval($item['quantity']);

                        // get product info
                        $pstmt = $conn->prepare("SELECT `selling-price`, `buying-price`, stock FROM products WHERE id = ? AND `branch-id` = ?");
                        $pstmt->bind_param("ii", $product_id, $branch_id);
                        $pstmt->execute();
                        $product = $pstmt->get_result()->fetch_assoc();
                        $pstmt->close();

                        if (!$product || $product['stock'] < $quantity) {
                            $success = false;
                            $messages[] = "Product not found or insufficient stock for " . htmlspecialchars($item['name']);
                            break;
                        }

                        $total_price  = $product['selling-price'] * $quantity;
                        $cost_price   = $product['buying-price'] * $quantity;
                        $total_profit = $total_price - $cost_price;
                        $date = date('Y-m-d');

                        // insert sale (includes customer_id)
                        $sstmt = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method,customer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $sstmt->bind_param("iiididdsis", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit, $date, $payment_method, $customer_id);
                        $sstmt->execute();
                        $sstmt->close();

                        // update stock
                        $new_stock = $product['stock'] - $quantity;
                        $u = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
                        $u->bind_param("ii", $new_stock, $product_id);
                        $u->execute();
                        $u->close();

                        // update profits (existing logic)
                        $pstmt = $conn->prepare("SELECT * FROM profits WHERE date = ? AND `branch-id` = ?");
                        $pstmt->bind_param("si", $currentDate, $branch_id);
                        $pstmt->execute();
                        $profit_result = $pstmt->get_result()->fetch_assoc();
                        $pstmt->close();
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
                    } // end foreach

                    if ($success) {
                        // record customer_transactions (paid)
                        $products_json = json_encode($cart);
                        $now = date('Y-m-d H:i:s');
                        $ct = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by, status) VALUES (?, ?, ?, ?, ?, ?, 'paid')");
                        $zero = 0.0;
                        $ct->bind_param("issdds", $customer_id, $now, $products_json, $total, $zero, $user_name);
                        $ct->execute();
                        $ct->close();

                        $conn->commit();
                        $message = "✅ Sale recorded and charged to Customer File.";
                    } else {
                        $conn->rollback();
                        $message = implode(' ', $messages);
                    }
                } else {
                    // insufficient funds: deduct available, set account_balance=0, add remaining to amount_credited, insert customer_transactions status='debtor'
                    $available_amt = $available;
                    $remaining = $total - $available_amt;

                    $ust = $conn->prepare("UPDATE customers SET account_balance = 0, amount_credited = COALESCE(amount_credited,0) + ? WHERE id = ?");
                    $ust->bind_param("di", $remaining, $customer_id);
                    $ust->execute();
                    $ust->close();

                    $products_json = json_encode($cart);
                    $now = date('Y-m-d H:i:s');
                    $ct = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by, status) VALUES (?, ?, ?, ?, ?, ?, 'debtor')");
                    $ct->bind_param("issdds", $customer_id, $now, $products_json, $available_amt, $remaining, $user_name);
                    $ct->execute();
                    $ct->close();

                    $conn->commit();
                    $message = "⚠️ Insufficient customer balance. Sale recorded as debtor (UGX " . number_format($remaining,2) . "). It will be finalized once the customer clears their balance.";
                }
            }
        }
    } else {
        // Existing non-Customer File flow (unchanged, keep previous behavior)
        $success = true; $messages = [];
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

            // Insert sale row
            $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->bind_param("iiididds", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit, $payment_method);
            $stmt->execute();
            $stmt->close();

            // Update stock
            $new_stock = $product['stock'] - $quantity;
            $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $update->bind_param("ii", $new_stock, $product_id);
            $update->execute();
            $update->close();

            // Update profits (existing logic)
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
    } // end payment_method branch
} // end submit_cart handler

// --- NEW: fetch customers for "Customer File" option (staff only) ---
$cust_stmt = $conn->prepare("SELECT id, name, COALESCE(account_balance,0) AS account_balance FROM customers ORDER BY name ASC");
$cust_stmt->execute();
$customers_res = $cust_stmt->get_result();
$customers_list = $customers_res ? $customers_res->fetch_all(MYSQLI_ASSOC) : [];
$cust_stmt->close();
?>


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
            <!-- Cart Section -->
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
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select id="payment_method" class="form-select" required>
                            <option value="Cash">Cash</option>
                            <option value="MTN MoMo">MTN MoMo</option>
                            <option value="Airtel Money">Airtel Money</option>
                            <option value="Bank">Bank</option>
                            <option value="Customer File">Customer File</option>
                        </select>
                    </div>

                    <!-- Customer dropdown for Customer File payments (hidden by default) -->
                    <div class="col-md-4" id="customer_select_wrap" style="display:none;">
                        <label for="customer_select" class="form-label">Customer</label>
                        <select id="customer_select" class="form-select">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers_list as $cust): ?>
                                <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?> (UGX <?= number_format(floatval($cust['account_balance']),2) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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

    // expose product/customer data (productData may already be defined elsewhere)
    const customers = <?php echo json_encode($customers_list); ?> || [];

    // Hidden form for submitting cart to PHP (ensure customer_id & payment_method included)
    const hiddenSaleForm = document.createElement('form');
    hiddenSaleForm.method = 'POST';
    hiddenSaleForm.style.display = 'none';
    hiddenSaleForm.innerHTML = `
        <input type="hidden" name="cart_data" id="cart_data">
        <input type="hidden" name="amount_paid" id="cart_amount_paid">
        <input type="hidden" name="submit_cart" value="1">
        <input type="hidden" name="payment_method" id="hidden_payment_method">
        <input type="hidden" name="customer_id" id="hidden_customer_id">
    `;
    document.body.appendChild(hiddenSaleForm);

    // Toggle customer select / amount_paid when payment method changes
    document.getElementById('payment_method').addEventListener('change', function() {
        const pm = this.value;
        const wrap = document.getElementById('customer_select_wrap');
        const amt = document.getElementById('amount_paid');
        if (pm === 'Customer File') {
            wrap.style.display = '';
            amt.value = '';
            amt.disabled = true;
            amt.closest('.col-md-4').style.opacity = 0.6;
        } else {
            wrap.style.display = 'none';
            amt.disabled = false;
            amt.closest('.col-md-4').style.opacity = 1;
        }
    });

    // Ensure initial state
    document.getElementById('payment_method').dispatchEvent(new Event('change'));

    // Sell button logic (adjusted to include customer file flow)
    document.getElementById('sellBtn').onclick = function() {
        const paymentMethod = document.getElementById('payment_method').value;
        const amountPaid = parseFloat(document.getElementById('amount_paid').value || 0);

        // Calculate total cart value
        let total = 0;
        cart.forEach(item => {
            total += item.price * item.quantity;
        });

        if (paymentMethod === 'Customer File') {
            const custId = document.getElementById('customer_select').value;
            if (!custId) { alert('Please select a customer for Customer File payment.'); return; }
            // submit with customer_id; amount_paid left as 0
            document.getElementById('cart_data').value = JSON.stringify(cart);
            document.getElementById('cart_amount_paid').value = 0;
            document.getElementById('hidden_payment_method').value = paymentMethod;
            document.getElementById('hidden_customer_id').value = custId;
            hiddenSaleForm.submit();
            return;
        }

        // existing flow for other payment methods
        if (amountPaid >= total) {
            const balance = amountPaid - total;
            if (balance > 0) {
                alert(`Balance is UGX ${balance.toLocaleString()}`);
            }
            document.getElementById('cart_data').value = JSON.stringify(cart);
            document.getElementById('cart_amount_paid').value = amountPaid;
            document.getElementById('hidden_payment_method').value = paymentMethod;
            document.getElementById('hidden_customer_id').value = '';
            hiddenSaleForm.submit();
        } else {
            // Underpayment: Show debtor form
            const debtorForm = document.getElementById('debtorsFormCard');
            document.getElementById('debtor_cart_data').value = JSON.stringify(cart);
            document.getElementById('debtor_amount_paid').value = amountPaid;
            debtorForm.style.display = 'block';
            window.scrollTo({ top: debtorForm.offsetTop, behavior: 'smooth' });
        }
    };

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
    $debtors_stmt = $conn->prepare("
        SELECT id, debtor_name, debtor_contact, debtor_email, item_taken, quantity_taken, amount_paid, balance, is_paid, created_at 
        FROM debtors 
        WHERE branch_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
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
                                        <th>Payment Method</th>
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
                                            <td><?= htmlspecialchars($sale['payment_method'] ?? 'Cash'); ?></td>
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
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-success btn-sm btn-action px-2 py-1">Paid</button>
                                                <button class="btn btn-primary btn-sm btn-action px-2 py-1 btn-pay-debtor" data-id="<?= $debtor['id'] ?>">Pay</button>
                                            </div>
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
        const paymentMethod = document.getElementById('payment_method').value;
        const amountPaid = parseFloat(document.getElementById('amount_paid').value || 0);

        // Calculate total cart value
        let total = 0;
        cart.forEach(item => {
            total += item.price * item.quantity;
        });

        if (paymentMethod === 'Customer File') {
            const custId = document.getElementById('customer_select').value;
            if (!custId) { alert('Please select a customer for Customer File payment.'); return; }
            // submit with customer_id; amount_paid left as 0
            document.getElementById('cart_data').value = JSON.stringify(cart);
            document.getElementById('cart_amount_paid').value = 0;
            document.getElementById('hidden_payment_method').value = paymentMethod;
            document.getElementById('hidden_customer_id').value = custId;
            hiddenSaleForm.submit();
            return;
        }

        // existing flow for other payment methods
        if (amountPaid >= total) {
            const balance = amountPaid - total;
            if (balance > 0) {
                alert(`Balance is UGX ${balance.toLocaleString()}`);
            }
            document.getElementById('cart_data').value = JSON.stringify(cart);
            document.getElementById('cart_amount_paid').value = amountPaid;
            document.getElementById('hidden_payment_method').value = paymentMethod;
            document.getElementById('hidden_customer_id').value = '';
            hiddenSaleForm.submit();
        } else {
            // Underpayment: Show debtor form
            const debtorForm = document.getElementById('debtorsFormCard');
            document.getElementById('debtor_cart_data').value = JSON.stringify(cart);
            document.getElementById('debtor_amount_paid').value = amountPaid;
            debtorForm.style.display = 'block';
            window.scrollTo({ top: debtorForm.offsetTop, behavior: 'smooth' });
        }
    };
    </script>
   
<script>
// Pay button logic for debtors table
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-pay-debtor').forEach(function(btn) {
        btn.onclick = function() {
            const row = btn.closest('tr');
            const debtorId = btn.getAttribute('data-id');
            const debtorName = row.querySelector('td:nth-child(2)').textContent;
            const balance = parseFloat(row.querySelector('td:nth-child(7)').textContent.replace(/[^\d.]/g, '')) || 0;

            // Show prompt for amount
            let amount = prompt(`Enter amount paid for ${debtorName} (Balance: UGX ${balance.toLocaleString()}):`, balance);
            if (amount === null) return; // Cancelled
            amount = parseFloat(amount);
            if (isNaN(amount) || amount <= 0) {
                alert('Please enter a valid amount.');
                return;
            }
            if (amount > balance) {
                alert('Amount cannot be greater than the balance.');
                return;
            }

            // AJAX to process payment
            fetch('staff_dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `pay_debtor=1&id=${debtorId}&amount=${amount}`
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.reload) window.location.reload();
            });
        };
    });
});
</script>

<?php include '../includes/footer.php'; ?>


