<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(['staff']);
include '../pages/sidebar_staff.php';
include '../includes/header.php';
include 'handle_debtor_payment.php';
include 'handle_cart_sale.php';

// NEW: Handle AJAX request to mark notifications as shown
if (isset($_POST['mark_notifications_shown'])) {
    $_SESSION['shown_login_notifications'] = true;
    exit;
}

// NEW: Include notification popup (shows once per login)
include '../includes/notification_popup.php';

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

// Ensure debtors.products_json column exists
$checkDebtorCol = $conn->query("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'debtors' 
      AND COLUMN_NAME = 'products_json'
");
if (!$checkDebtorCol || $checkDebtorCol->num_rows === 0) {
    $conn->query("ALTER TABLE debtors ADD COLUMN products_json TEXT NULL");
    if ($conn->errno) {
        @$conn->query("ALTER TABLE debtors ADD COLUMN products_json TEXT NULL");
    }
}

// Ensure debtors.invoice_no column exists
$checkDebtorInvoiceCol = $conn->query("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'debtors' 
      AND COLUMN_NAME = 'invoice_no'
");
if (!$checkDebtorInvoiceCol || $checkDebtorInvoiceCol->num_rows === 0) {
    $conn->query("ALTER TABLE debtors ADD COLUMN invoice_no VARCHAR(32) NULL");
    if ($conn->errno) {
        @$conn->query("ALTER TABLE debtors ADD COLUMN invoice_no VARCHAR(32) NULL");
    }
}

// Ensure debtors.due_date column exists
$checkDebtorDueDateCol = $conn->query("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'debtors' 
      AND COLUMN_NAME = 'due_date'
");
if (!$checkDebtorDueDateCol || $checkDebtorDueDateCol->num_rows === 0) {
    $conn->query("ALTER TABLE debtors ADD COLUMN due_date DATE NULL");
    if ($conn->errno) {
        @$conn->query("ALTER TABLE debtors ADD COLUMN due_date DATE NULL");
    }
}

// if ($_SESSION['role'] !== 'staff') {
//     header("Location: ../auth/login.php");
//     exit();
// }

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$branch_id = $_SESSION['branch_id'];
$message   = "";

// Show message if redirected after sale
if (isset($_SESSION['cart_sale_message'])) {
    $message = $_SESSION['cart_sale_message'];
    unset($_SESSION['cart_sale_message']);
} elseif (isset($_GET['success'])) {
    $message = "✅ Sale recorded successfully!";
} elseif (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
}

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
        // if ($profit_result) {
        //     $total_amount = $profit_result['total'] + $total_profit;
        //     $expenses     = $profit_result['expenses'] ?? 0;
        //     $net_profit   = $total_amount - $expenses;

        //     $stmt2 = $conn->prepare("UPDATE profits SET total=?, `net-profits`=? WHERE date=? AND `branch-id`=?");
        //     $stmt2->bind_param("ddsi", $total_amount, $net_profit, $currentDate, $branch_id);
        //     $stmt2->execute();
        //     $stmt2->close();
        // } else {
        //     $total_amount = $total_profit;
        //     $net_profit   = $total_profit;
        //     $expenses     = 0;

        //     $stmt2 = $conn->prepare("INSERT INTO profits (`branch-id`, total, `net-profits`, expenses, date) VALUES (?, ?, ?, ?, ?)");
        //     $stmt2->bind_param("iddis", $branch_id, $total_amount, $net_profit, $expenses, $currentDate);
        //     $stmt2->execute();
        //     $stmt2->close();
        // }
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

// Fetch recent sales (match sales.php columns/aliases) - NEW: include products_json
$sales_stmt = $conn->prepare("
    SELECT s.id, 
           s.`product-id`,
           p.name AS `product-name`, 
           s.quantity, 
           s.amount, 
           s.`sold-by`, 
           s.date, 
           b.name AS branch_name, 
           s.payment_method,
           s.receipt_no,
           s.products_json
    FROM sales s
    LEFT JOIN products p ON s.`product-id` = p.id
    JOIN branch b ON s.`branch-id` = b.id
    WHERE s.`branch-id` = ?
    ORDER BY s.id DESC
    LIMIT 10
");
$sales_stmt->bind_param("i", $branch_id);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
$sales_stmt->close();

// Fetch recent debtors (match sales.php columns/aliases) - ADD due_date
$debtors_stmt = $conn->prepare("
    SELECT id, debtor_name, debtor_email, item_taken, quantity_taken, amount_paid, balance, is_paid, created_at, due_date
    FROM debtors
    WHERE branch_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$debtors_stmt->bind_param("i", $branch_id);
$debtors_stmt->execute();
$debtors_result = $debtors_stmt->get_result();
$debtors_stmt->close();

// Handle debtor record (WITH INVOICE NUMBER)
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

	// --- Store full cart as JSON for proper reconstruction ---
	$products_json = $_POST['cart_data'] ?? '[]'; // Keep raw JSON string

	// Calculate item_taken (WITH QUANTITIES like customer debtors), quantity_taken, total_amount
	$item_taken = '';
	$quantity_taken = 0;
	$total_amount = 0;
	if ($cart && is_array($cart)) {
	    $item_names = [];
	    foreach ($cart as $item) {
	        // Include quantity in item_taken display
	        $item_names[] = $item['name'] . ' x' . intval($item['quantity']);
	        $quantity_taken += intval($item['quantity']);
	        $total_amount += floatval($item['price']) * intval($item['quantity']);
	    }
	    $item_taken = implode(', ', $item_names);
	}
	$balance = $total_amount - $amount_paid;

	// CHANGED: Generate SEQUENTIAL invoice number using receipt_counter
	$invoice_no = generateReceiptNumber($conn, 'INV'); // <-- SEQUENTIAL INV-00001, INV-00002...

	// Only insert if all required fields are present
	if ($debtor_name && $quantity_taken > 0 && $balance > 0 && !empty($item_taken)) {
	    // Add products_json AND invoice_no columns to debtors table
	    $stmt = $conn->prepare("INSERT INTO debtors (debtor_name, debtor_contact, debtor_email, item_taken, quantity_taken, amount_paid, balance, branch_id, created_by, created_at, products_json, invoice_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	    $stmt->bind_param("ssssiddiisss", $debtor_name, $debtor_contact, $debtor_email, $item_taken, $quantity_taken, $amount_paid, $balance, $branch, $created_by, $date, $products_json, $invoice_no);
	    if ($stmt->execute()) {
	        $debtor_id_new = $stmt->insert_id;
	        $stmt->close();

	        // NEW: decrement stock for each product in the cart (validate first)
	        $stock_ok = true;
	        $stock_errors = [];
	        $conn->begin_transaction();
	        try {
	            // Validate all items
	            $chkStmt = $conn->prepare("SELECT stock FROM products WHERE id = ? AND `branch-id` = ? LIMIT 1");
	            foreach ($cart as $item) {
	                $pid = intval($item['id'] ?? 0);
	                $qty = max(0, intval($item['quantity'] ?? 0));
	                if ($pid <= 0 || $qty <= 0) continue;
	                $chkStmt->bind_param("ii", $pid, $branch);
	                $chkStmt->execute();
	                $res = $chkStmt->get_result()->fetch_assoc();
	                if (!$res) {
	                    $stock_ok = false;
	                    $stock_errors[] = "Product ID {$pid} not found in branch.";
	                    break;
	                }
	                if (intval($res['stock']) < $qty) {
	                    $stock_ok = false;
	                    $stock_errors[] = "Not enough stock for product ID {$pid}.";
	                    break;
	                }
	            }
	            $chkStmt->close();

	            if (!$stock_ok) {
	                // rollback and delete inserted debtor (we don't want dangling debtor if stock invalid)
	                $conn->rollback();
	                $del = $conn->prepare("DELETE FROM debtors WHERE id = ?");
	                $del->bind_param("i", $debtor_id_new);
	                $del->execute();
	                $del->close();
	                $message = "❌ Cannot record debtor: " . implode(' ', $stock_errors);
	            } else {
	                // Decrement stock
	                $updStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND `branch-id` = ?");
	                foreach ($cart as $item) {
	                    $pid = intval($item['id'] ?? 0);
	                    $qty = max(0, intval($item['quantity'] ?? 0));
	                    if ($pid <= 0 || $qty <= 0) continue;
	                    $updStmt->bind_param("iii", $qty, $pid, $branch);
	                    $updStmt->execute();
	                }
	                $updStmt->close();
	                $conn->commit();

	                $message = "✅ Debtor recorded successfully! Invoice: " . $invoice_no;
	            }
	        } catch (Throwable $e) {
	            $conn->rollback();
	            // remove debtor record to keep DB consistent
	            $del = $conn->prepare("DELETE FROM debtors WHERE id = ?");
	            $del->bind_param("i", $debtor_id_new);
	            $del->execute();
	            $del->close();
	            $message = "❌ Failed to record debtor: " . $e->getMessage();
	        }
	    } else {
	        $message = "❌ Failed to record debtor: " . $stmt->error;
	        $stmt->close();
	    }
	} else {
	    $message = "⚠️ Debtor name, item taken, quantity, and balance are required.";
	}
}

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
    <div class="card add-sale-card mb-4" style="border-left: 4px solid teal;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Add Sale</span>
            <!-- Scan icon button -->
            <button type="button" id="scanBarcodeBtn" class="btn btn-outline-primary btn-scan-barcode" title="Scan Barcode">
                <i class="fa-solid fa-barcode"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="addSaleForm" class="row g-3" onsubmit="return false;">
                <div class="col-md-6">
                    <label for="product_id" class="form-label">Product</label>
                    <select class="form-select" name="product_id" id="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php
                        // Re-query products for JS cart
                        $product_query2 = $conn->prepare("SELECT id, name, stock, `selling-price`, barcode FROM products WHERE `branch-id` = ?");
                        $product_query2->bind_param("i", $branch_id);
                        $product_query2->execute();
                        $products_for_js = $product_query2->get_result();
                        $product_list = [];
                        while ($row = $products_for_js->fetch_assoc()) {
                            $row['barcode'] = trim($row['barcode'] ?? ''); // Ensure barcode is trimmed
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
                    <table class="cart-table align-middle">
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

    <!-- Barcode Scan Modal/Card -->
    <div id="barcodeScanModal" class="barcode-scan-modal" style="display:none;">
        <div class="barcode-scan-card">
            <div class="barcode-scan-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-barcode"></i> Scan Item</span>
                <button type="button" id="closeBarcodeScan" class="btn btn-close"></button>
            </div>
            <div class="barcode-scan-body">
                <div class="barcode-scan-view-area">
                    <label class="me-2">Scan Mode:</label>
                    <video id="barcodeScanVideo" autoplay muted playsinline></video>
                    <select id="barcodeScanMode" class="form-select d-inline-block" style="width:auto;">
                        <option value="camera">Camera</option>
                        <option value="hardware">Barcode Hardware</option>
                    </select>
                    <canvas id="barcodeScanCanvas" style="display:none;"></canvas>
                    <button type="button" id="rotateCameraBtn" class="btn btn-secondary barcode-rotate-btn" title="Switch Camera">
                        <i class="fa-solid fa-camera-rotate"></i>
                    </button>
                </div>
                <div id="barcodeScanStatus" class="barcode-scan-status text-center"></div>
                <div class="barcode-scan-text mt-3 mb-2 text-center">
                    <span>Scan item to add to cart.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Debtors Entry Form (hidden, shown by JS if needed) -->
    <div id="debtorsFormCard" class="card mb-4" style="display:none; border-left: 4px solid teal;">
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
    <div class="card mb-4" style="border-left: 4px solid teal;">
        <div class="card-header low-stock-header">
            <span class="low-stock-title">⚠️ Low Stock Products (Branch <?= $branch_id; ?>)</span>
        </div>
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
                <p class="low-stock-info text-muted fst-italic">All products have sufficient stock in your branch.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs for Sales and Debtors -->
    <ul class="nav nav-tabs mb-4" id="salesTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales-table" type="button" role="tab" aria-controls="sales-table" aria-selected="true">
                Recent Sales
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="debtors-tab" data-bs-toggle="tab" data-bs-target="#debtors-table" type="button" role="tab" aria-controls="debtors-table" aria-selected="false">
                Debtors
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="salesTabsContent">
        <!-- Recent Sales Table Tab (copied from sales.php, last 10 only, no pagination/filter) -->
        <div class="tab-pane fade show active" id="sales-table" role="tabpanel" aria-labelledby="sales-tab">
            <div class="card mb-4 chart-card" style="border-left: 4px solid teal;">
                <div class="card-header bg-light text-black d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span class="fw-bold title-card"><i class="fa-solid fa-receipt"></i> Recent Sales (Last 10)</span>
                </div>
                <div class="card-body table-responsive">
                    <div class="transactions-table">
                        <table class="recent-sales-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Receipt No.</th>
                                    <th>Product(s)</th>
                                    <th>Quantity</th>
                                    <th>Total Price</th>
                                    <th>Payment Method</th>
                                    <th>Sold At</th>
                                    <th>Sold By</th>
                                    <th>Actions</th> <!-- NEW COLUMN -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                while ($row = $sales_result->fetch_assoc()):
                                    // Parse products_json if available (SAME LOGIC AS sales.php)
                                    $products_display = '';
                                    if ($row['products_json']) {
                                        $products_data = json_decode($row['products_json'], true);
                                        if (is_array($products_data)) {
                                            $products_display = implode(', ', array_map(function($p) {
                                                return htmlspecialchars($p['name']) . ' x' . $p['quantity'];
                                            }, $products_data));
                                        } else {
                                            $products_display = htmlspecialchars($row['product-name'] ?? 'Unknown');
                                        }
                                    } else {
                                        $products_display = htmlspecialchars($row['product-name'] ?? 'Unknown');
                                    }
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($row['receipt_no'] ?? '-') ?></td>
                                        <td><span class="badge bg-primary"><?= $products_display ?></span></td>
                                        <td><?= $row['quantity'] ?></td>
                                        <td><span class="fw-bold text-success">UGX <?= number_format($row['amount'], 2) ?></span></td>
                                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                        <td><small class="text-muted"><?= date("M d, Y H:i", strtotime($row['date'])) ?></small></td>
                                        <td><?= htmlspecialchars($row['sold-by']) ?></td>
                                        <td>
                                            <!-- NEW: Receipt button -->
                                            <button class="btn btn-info btn-sm print-receipt-btn" 
                                                    data-sale-id="<?= $row['id'] ?>"
                                                    data-products='<?= htmlspecialchars($row['products_json'] ?: '[]') ?>'
                                                    data-total="<?= $row['amount'] ?>"
                                                    data-payment="<?= htmlspecialchars($row['payment_method']) ?>"
                                                    data-receipt="<?= htmlspecialchars($row['receipt_no'] ?? '') ?>"
                                                    title="Print Receipt">
                                                <i class="fa fa-print"></i> Receipt
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Debtors Table Tab (copied from sales.php, last 10 only, no pagination/filter) -->
        <div class="tab-pane fade" id="debtors-table" role="tabpanel" aria-labelledby="debtors-tab" >
            <div class="card mb-4 chart-card" style="border-left: 4px solid teal;">
                <div class="card-header bg-light text-black fw-bold d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span><i class="fa-solid fa-user-clock"></i> Debtors</span>
                </div>
                <div class="card-body table-responsive">
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
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($debtors_result && $debtors_result->num_rows > 0): ?>
                                    <?php while ($debtor = $debtors_result->fetch_assoc()): ?>
                                        <?php
                                        // Check if due date button should be shown
                                        $show_due_date_btn = true;
                                        $due_date_display = '-';
                                        if ($debtor['due_date']) {
                                            $due_date_display = date('M d, Y', strtotime($debtor['due_date']));
                                            $today = new DateTime();
                                            $due = new DateTime($debtor['due_date']);
                                            $diff = $today->diff($due);
                                            $days_diff = (int)$diff->format('%r%a');
                                            // Show button if due date exceeded by 4+ days
                                            $show_due_date_btn = ($days_diff < -3);
                                        }
                                        ?>
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
                                                <?php if ($show_due_date_btn): ?>
                                                    <button class="btn btn-sm btn-outline-primary set-due-date-btn-staff" 
                                                            data-id="<?= $debtor['id'] ?>"
                                                            data-name="<?= htmlspecialchars($debtor['debtor_name']) ?>"
                                                            title="Set Due Date">
                                                        <i class="fa fa-calendar"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted"><?= $due_date_display ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <!-- Only show Pay button. Pass debtor metadata for the modal -->
                                                <button class="btn btn-primary btn-sm btn-pay-debtor"
                                                        data-id="<?= $debtor['id'] ?>"
                                                        data-balance="<?= htmlspecialchars($debtor['balance'] ?? 0) ?>"
                                                        data-name="<?= htmlspecialchars($debtor['debtor_name']) ?>">
                                                    Pay
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">No debtors recorded yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Debtor Pay Modal (copied from sales.php) -->
<div class="modal fade" id="payDebtorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary-color);color:#fff;">
        <h5 class="modal-title">Record Debtor Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="pdDebtorLabel" class="mb-2 fw-semibold"></p>
        <div class="mb-3">
          <label class="form-label">Amount Paid (UGX)</label>
          <input type="number" id="pdAmount" class="form-control" min="0" step="0.01" placeholder="Enter amount">
        </div>
        <p>Outstanding Balance: <strong id="pdBalanceText">UGX 0.00</strong></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="pdConfirmBtn" class="btn btn-primary">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- NEW: Set Due Date Modal (Staff) -->
<div class="modal fade" id="setDueDateModalStaff" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary-color);color:#fff;">
        <h5 class="modal-title">Set Due Date</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="ddDebtorLabelStaff" class="mb-3 fw-semibold"></p>
        <input type="hidden" id="ddDebtorIdStaff" value="">
        <div class="mb-3">
          <label class="form-label">Expected Payment Date</label>
          <input type="date" id="ddDueDateStaff" class="form-control" min="<?= date('Y-m-d') ?>">
        </div>
        <div id="ddMsgStaff"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="ddConfirmBtnStaff" class="btn btn-primary">OK</button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="assets/css/staff.css">
<script>
    window.productData = <?php echo json_encode($product_list); ?>;
    window.customers = <?php echo json_encode($customers_list); ?>;
</script>
<script src="staff_dashboard.js"></script>

<!-- NEW: Receipt printing script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Receipt button clicks
    document.querySelectorAll('.print-receipt-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productsJson = btn.getAttribute('data-products');
            const total = parseFloat(btn.getAttribute('data-total') || 0);
            const paymentMethod = btn.getAttribute('data-payment');
            const receiptNo = btn.getAttribute('data-receipt');
            
            // Parse products
            let cart = [];
            try {
                cart = JSON.parse(productsJson);
                if (!Array.isArray(cart)) cart = [];
            } catch(e) {
                console.error('Failed to parse products:', e);
                cart = [];
            }
            
            // Open receipt preview window
            printReceiptFromSale(cart, total, paymentMethod, receiptNo);
        });
    });
    
    // Function to print receipt (same styling as receipt_preview.php)
    function printReceiptFromSale(cart, total, paymentMethod, receiptNo) {
        const now = new Date();
        const dateStr = now.toLocaleString();
        const company = "CYINIBEL SUPERMARKET LIMITED";
        const till = "2";
        const tillSales = receiptNo || "N/A";
        const tin = "1017004561";
        
        // Build items HTML
        let itemsHtml = '';
        if (cart && cart.length > 0) {
            cart.forEach(item => {
                const qty = parseInt(item.quantity || 0);
                const price = parseFloat(item.price || 0);
                const subtotal = qty * price;
                itemsHtml += `
                <tr>
                    <td style="text-align:left;">${qty}</td>
                    <td style="text-align:left;">${escapeHtml(item.name)}</td>
                    <td style="text-align:right;">UGX ${subtotal.toLocaleString()}</td>
                </tr>`;
            });
        } else {
            itemsHtml = `<tr><td colspan="3" style="text-align:center;">No items</td></tr>`;
        }
        
        // Receipt HTML (same as receipt_preview.php)
        const receiptHtml = `
<div id="receiptToPrint" style="width:320px;max-width:100vw;padding:0;font-family:'Courier New',monospace;">
    <div style="text-align:center;margin-top:10px;">
        <img src="https://i.ibb.co/6w1yQnQ/cyinibel-logo.png" alt="Logo" style="width:80px;height:80px;object-fit:contain;margin-bottom:8px;">
    </div>
    <div style="text-align:center;font-weight:bold;font-size:15px;margin-bottom:2px;">${company}</div>
    <div style="text-align:center;font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <div style="text-align:center;font-size:13px;margin-bottom:2px;">${dateStr}</div>
    <div style="font-size:12px;margin-bottom:2px;">TILL: ${till} &nbsp; Till Sales: ${tillSales}</div>
    <div style="font-size:12px;margin-bottom:2px;">TIN: ${tin}</div>
    <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <table style="width:100%;font-size:13px;margin-bottom:2px;border-collapse:collapse;">
        <tbody>${itemsHtml}</tbody>
    </table>
    <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <table style="width:100%;font-size:13px;">
        <tr>
            <td style="text-align:left;">Subtotal</td>
            <td style="text-align:right;">UGX ${Number(total).toLocaleString()}</td>
        </tr>
        <tr>
            <td style="text-align:left;">Total</td>
            <td style="text-align:right;">UGX ${Number(total).toLocaleString()}</td>
        </tr>
    </table>
    <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <table style="width:100%;font-size:13px;">
        <tr>
            <td style="text-align:left;">Payment Method</td>
            <td style="text-align:right;">${escapeHtml(paymentMethod)}</td>
        </tr>
    </table>
    <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <div style="text-align:center;font-size:13px;margin:10px 0 2px 0;">THANK YOU</div>
    <div style="text-align:center;font-size:13px;margin-bottom:8px;">HAVE A NICE DAY</div>
    <div style="text-align:center;margin-top:8px;">
        <svg id="barcodeSvg" style="width:180px;height:40px;"></svg>
    </div>
</div>
        `;
        
        // Open print window
        const win = window.open('', '_blank', 'width=400,height=600');
        win.document.write(`<html><head><title>Receipt - ${receiptNo}</title>
<style>
@media print {
  body * { visibility: hidden !important; }
  #receiptToPrint, #receiptToPrint * {
    visibility: visible !important;
  }
  #receiptToPrint {
    position: absolute;
    left: 0; top: 0;
    width: 58mm;
    min-width: 0;
    max-width: 100vw;
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    background: #fff !important;
    color: #000 !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  #receiptToPrint table { width:100%; }
  #receiptToPrint tr, #receiptToPrint td { font-size:13px; }
}
</style>
</head><body>${receiptHtml}
<script>
function escapeHtml(s){return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])):'';}
(function() {
    var svg = document.getElementById('barcodeSvg');
    if (svg) {
        var code = "${tillSales}";
        var bars = '';
        var x = 0;
        for (var i = 0; i < code.length; i++) {
            var val = code.charCodeAt(i) % 7 + 1;
            for (var j = 0; j < val; j++) {
                bars += '<rect x="'+x+'" y="0" width="2" height="40" fill="#000"/>';
                x += 3;
            }
            x += 2;
        }
        svg.innerHTML = bars;
    }
    setTimeout(function() { window.print(); setTimeout(function(){window.close();}, 400); }, 200);
})();
<\/script>
</body></html>`);
        win.document.close();
        win.focus();
    }
    
    // Helper function for HTML escaping
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(match) {
            const escapeMap = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return escapeMap[match];
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>


