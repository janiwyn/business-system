<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff", "super"]);

// Get logged-in user info EARLY (before any output)
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// -------------------------------
// AJAX HANDLERS FIRST (before any includes that produce output)
// -------------------------------

// Handle Restock (Move from Store to Shelf)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restock_move') {
    // Clean any output buffers
    while (ob_get_level()) ob_end_clean();
    ob_start();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $shelf_product_id = intval($_POST['shelf_product_id'] ?? 0);
    $branch_id = intval($_POST['branch_id'] ?? 0);
    $move_qty = intval($_POST['quantity'] ?? 0);
    
    // SECURITY: Staff can only restock products in their branch
    if ($user_role === 'staff' && $branch_id != $user_branch) {
        echo json_encode(['success' => false, 'message' => 'Access denied: You can only restock products in your branch']);
        ob_end_flush();
        exit;
    }
    
    if ($shelf_product_id <= 0 || $branch_id <= 0 || $move_qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        ob_end_flush();
        exit;
    }
    
    // Get shelf product details (to match by barcode in store)
    $shelf_stmt = $conn->prepare("SELECT barcode, name FROM products WHERE id = ? AND `branch-id` = ?");
    $shelf_stmt->bind_param("ii", $shelf_product_id, $branch_id);
    $shelf_stmt->execute();
    $shelf_product = $shelf_stmt->get_result()->fetch_assoc();
    $shelf_stmt->close();
    
    if (!$shelf_product) {
        echo json_encode(['success' => false, 'message' => 'Shelf product not found']);
        ob_end_flush();
        exit;
    }
    
    // Find matching product in store_products
    $store_stmt = $conn->prepare("SELECT id, stock FROM store_products WHERE barcode = ? AND `branch-id` = ?");
    $store_stmt->bind_param("si", $shelf_product['barcode'], $branch_id);
    $store_stmt->execute();
    $store_product = $store_stmt->get_result()->fetch_assoc();
    $store_stmt->close();
    
    if (!$store_product) {
        echo json_encode(['success' => false, 'message' => 'Product not found in store']);
        ob_end_flush();
        exit;
    }
    
    if ($store_product['stock'] < $move_qty) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock in store']);
        ob_end_flush();
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Decrease store stock
        $update_store = $conn->prepare("UPDATE store_products SET stock = stock - ? WHERE id = ?");
        $update_store->bind_param("ii", $move_qty, $store_product['id']);
        $update_store->execute();
        $update_store->close();
        
        // Increase shelf stock
        $update_shelf = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $update_shelf->bind_param("ii", $move_qty, $shelf_product_id);
        $update_shelf->execute();
        $update_shelf->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Product restocked successfully!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    ob_end_flush();
    exit;
}

// Handle "Move to Shelf"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_to_shelf') {
    // Clean any output buffers
    while (ob_get_level()) ob_end_clean();
    ob_start();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $store_product_id = intval($_POST['store_product_id'] ?? 0);
    $move_qty = intval($_POST['quantity'] ?? 0);
    
    // Get store product details
    $store_stmt = $conn->prepare("SELECT * FROM store_products WHERE id = ?");
    $store_stmt->bind_param("i", $store_product_id);
    $store_stmt->execute();
    $store_product = $store_stmt->get_result()->fetch_assoc();
    $store_stmt->close();
    
    if (!$store_product) {
        echo json_encode(['success' => false, 'message' => 'Store product not found']);
        ob_end_flush();
        exit;
    }
    
    // SECURITY: Staff can only move products from their own branch
    if ($user_role === 'staff' && $store_product['branch-id'] != $user_branch) {
        echo json_encode(['success' => false, 'message' => 'Access denied: You can only move products from your branch']);
        ob_end_flush();
        exit;
    }
    
    if ($store_product['stock'] < $move_qty) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock in store']);
        ob_end_flush();
        exit;
    }
    
    // Check if shelf product exists (by barcode and branch)
    $shelf_check = $conn->prepare("SELECT id, stock FROM products WHERE barcode = ? AND `branch-id` = ?");
    $shelf_check->bind_param("si", $store_product['barcode'], $store_product['branch-id']);
    $shelf_check->execute();
    $shelf_product = $shelf_check->get_result()->fetch_assoc();
    $shelf_check->close();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        if (!$shelf_product) {
            // Create new shelf product
            $create_shelf = $conn->prepare("
                INSERT INTO products (name, barcode, `selling-price`, `buying-price`, stock, `branch-id`, expiry_date, sms_sent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $create_shelf->bind_param(
                "ssddiis",
                $store_product['name'],
                $store_product['barcode'],
                $store_product['selling-price'],
                $store_product['buying-price'],
                $move_qty,
                $store_product['branch-id'],
                $store_product['expiry_date']
            );
            $create_shelf->execute();
            $create_shelf->close();
        } else {
            // Update existing shelf product stock
            $update_shelf = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $update_shelf->bind_param("ii", $move_qty, $shelf_product['id']);
            $update_shelf->execute();
            $update_shelf->close();
        }
        
        // Decrease store stock
        $update_store = $conn->prepare("UPDATE store_products SET stock = stock - ? WHERE id = ?");
        $update_store->bind_param("ii", $move_qty, $store_product_id);
        $update_store->execute();
        $update_store->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Product moved to shelf successfully!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    ob_end_flush();
    exit;
}

// Get store stock for a product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_store_stock') {
    // Clean any output buffers
    while (ob_get_level()) ob_end_clean();
    ob_start();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $barcode = trim($_POST['barcode'] ?? '');
    $branch_id = intval($_POST['branch_id'] ?? 0);
    
    // SECURITY: Staff can only check stock in their branch
    if ($user_role === 'staff' && $branch_id != $user_branch) {
        echo json_encode(['stock' => 0]);
        ob_end_flush();
        exit;
    }
    
    $stmt = $conn->prepare("SELECT stock FROM store_products WHERE barcode = ? AND `branch-id` = ?");
    $stmt->bind_param("si", $barcode, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    echo json_encode(['stock' => $result ? $result['stock'] : 0]);
    ob_end_flush();
    exit;
}

// -------------------------------
// Handle Add Product to STORE (MOVED TO TOP)
// -------------------------------
if (isset($_POST['add_store_product'])) {
    $barcode = trim($_POST['barcode']);
    $name = trim($_POST['name']);
    $selling_price = floatval($_POST['price']);
    $buying_price = floatval($_POST['cost']);
    $stock = intval($_POST['stock']);
    $branch_id = intval($_POST['branch_id']);
    $expiry_date = $_POST['expiry_date'];

    // SECURITY: Staff can only add products to their own branch
    if ($user_role === 'staff' && $branch_id != $user_branch) {
        $_SESSION['product_message'] = "<div class='alert alert-danger shadow-sm'>❌ Access denied: You can only add products to your branch</div>";
        header("Location: product.php");
        exit;
    }

    // Check if product with same barcode already exists in this branch
    $check_stmt = $conn->prepare("SELECT id, stock FROM store_products WHERE barcode = ? AND `branch-id` = ?");
    $check_stmt->bind_param("si", $barcode, $branch_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($existing) {
        // Product exists - update stock
        $new_stock = $existing['stock'] + $stock;
        $update_stmt = $conn->prepare("UPDATE store_products SET stock = ?, `selling-price` = ?, `buying-price` = ?, expiry_date = ? WHERE id = ?");
        $update_stmt->bind_param("iddsi", $new_stock, $selling_price, $buying_price, $expiry_date, $existing['id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['product_message'] = "<div class='alert alert-success shadow-sm'>✅ Product stock updated! Added {$stock} units. New total: {$new_stock}</div>";
        } else {
            $_SESSION['product_message'] = "<div class='alert alert-danger shadow-sm'>❌ Error updating stock: " . $update_stmt->error . "</div>";
        }
        $update_stmt->close();
    } else {
        // New product - insert
        $stmt = $conn->prepare("INSERT INTO store_products (name, barcode, `selling-price`, `buying-price`, stock, `branch-id`, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddiis", $name, $barcode, $selling_price, $buying_price, $stock, $branch_id, $expiry_date);
        
        if ($stmt->execute()) {
            $_SESSION['product_message'] = "<div class='alert alert-success shadow-sm'>✅ Product added to store successfully!</div>";
        } else {
            $_SESSION['product_message'] = "<div class='alert alert-danger shadow-sm'>❌ Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
    
    // REDIRECT to prevent form resubmission (BEFORE any output)
    header("Location: product.php");
    exit;
}

// NOW include files that produce output
include 'sms.php';

// Fix: Always use the correct sidebar for staff
if ($_SESSION['role'] === 'staff') {
    include '../pages/sidebar_staff.php';
} else {
    include '../pages/sidebar.php';
}
include '../includes/header.php';

$message = "";
$expiring_products = [];

// Get logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// -------------------------------
// Handle Add Product to STORE
// -------------------------------
if (isset($_POST['add_store_product'])) {
    $barcode = trim($_POST['barcode']);
    $name = trim($_POST['name']);
    $selling_price = floatval($_POST['price']);
    $buying_price = floatval($_POST['cost']);
    $stock = intval($_POST['stock']);
    $branch_id = intval($_POST['branch_id']);
    $expiry_date = $_POST['expiry_date'];

    // Check if product with same barcode already exists in this branch
    $check_stmt = $conn->prepare("SELECT id, stock FROM store_products WHERE barcode = ? AND `branch-id` = ?");
    $check_stmt->bind_param("si", $barcode, $branch_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($existing) {
        // Product exists - update stock
        $new_stock = $existing['stock'] + $stock;
        $update_stmt = $conn->prepare("UPDATE store_products SET stock = ?, `selling-price` = ?, `buying-price` = ?, expiry_date = ? WHERE id = ?");
        $update_stmt->bind_param("iddsi", $new_stock, $selling_price, $buying_price, $expiry_date, $existing['id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['product_message'] = "<div class='alert alert-success shadow-sm'>✅ Product stock updated! Added {$stock} units. New total: {$new_stock}</div>";
        } else {
            $_SESSION['product_message'] = "<div class='alert alert-danger shadow-sm'>❌ Error updating stock: " . $update_stmt->error . "</div>";
        }
        $update_stmt->close();
    } else {
        // New product - insert
        $stmt = $conn->prepare("INSERT INTO store_products (name, barcode, `selling-price`, `buying-price`, stock, `branch-id`, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddiis", $name, $barcode, $selling_price, $buying_price, $stock, $branch_id, $expiry_date);
        
        if ($stmt->execute()) {
            $_SESSION['product_message'] = "<div class='alert alert-success shadow-sm'>✅ Product added to store successfully!</div>";
        } else {
            $_SESSION['product_message'] = "<div class='alert alert-danger shadow-sm'>❌ Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
    
    // REDIRECT to prevent form resubmission
    header("Location: product.php");
    exit;
}

// -------------------------------
// Handle Restock (Move from Store to Shelf)
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restock_move') {
    // Clean any output buffers
    while (ob_get_level()) ob_end_clean();
    ob_start();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $shelf_product_id = intval($_POST['shelf_product_id'] ?? 0);
    $branch_id = intval($_POST['branch_id'] ?? 0);
    $move_qty = intval($_POST['quantity'] ?? 0);
    
    // SECURITY: Staff can only restock products in their branch
    if ($user_role === 'staff' && $branch_id != $user_branch) {
        echo json_encode(['success' => false, 'message' => 'Access denied: You can only restock products in your branch']);
        ob_end_flush();
        exit;
    }
    
    if ($shelf_product_id <= 0 || $branch_id <= 0 || $move_qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        ob_end_flush();
        exit;
    }
    
    // Get shelf product details (to match by barcode in store)
    $shelf_stmt = $conn->prepare("SELECT barcode, name FROM products WHERE id = ? AND `branch-id` = ?");
    $shelf_stmt->bind_param("ii", $shelf_product_id, $branch_id);
    $shelf_stmt->execute();
    $shelf_product = $shelf_stmt->get_result()->fetch_assoc();
    $shelf_stmt->close();
    
    if (!$shelf_product) {
        echo json_encode(['success' => false, 'message' => 'Shelf product not found']);
        ob_end_flush();
        exit;
    }
    
    // Find matching product in store_products
    $store_stmt = $conn->prepare("SELECT id, stock FROM store_products WHERE barcode = ? AND `branch-id` = ?");
    $store_stmt->bind_param("si", $shelf_product['barcode'], $branch_id);
    $store_stmt->execute();
    $store_product = $store_stmt->get_result()->fetch_assoc();
    $store_stmt->close();
    
    if (!$store_product) {
        echo json_encode(['success' => false, 'message' => 'Product not found in store']);
        ob_end_flush();
        exit;
    }
    
    if ($store_product['stock'] < $move_qty) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock in store']);
        ob_end_flush();
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Decrease store stock
        $update_store = $conn->prepare("UPDATE store_products SET stock = stock - ? WHERE id = ?");
        $update_store->bind_param("ii", $move_qty, $store_product['id']);
        $update_store->execute();
        $update_store->close();
        
        // Increase shelf stock
        $update_shelf = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $update_shelf->bind_param("ii", $move_qty, $shelf_product_id);
        $update_shelf->execute();
        $update_shelf->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Product restocked successfully!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    ob_end_flush();
    exit;
}

// -------------------------------
// NEW: Handle "Move to Shelf" (creates shelf product if doesn't exist)
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_to_shelf') {
    // Clean any output buffers
    while (ob_get_level()) ob_end_clean();
    ob_start();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $store_product_id = intval($_POST['store_product_id'] ?? 0);
    $move_qty = intval($_POST['quantity'] ?? 0);
    
    if ($store_product_id <= 0 || $move_qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        ob_end_flush();
        exit;
    }
    
    // Get store product details
    $store_stmt = $conn->prepare("SELECT * FROM store_products WHERE id = ?");
    $store_stmt->bind_param("i", $store_product_id);
    $store_stmt->execute();
    $store_product = $store_stmt->get_result()->fetch_assoc();
    $store_stmt->close();
    
    if (!$store_product) {
        echo json_encode(['success' => false, 'message' => 'Store product not found']);
        ob_end_flush();
        exit;
    }
    
    // SECURITY: Staff can only move products from their own branch
    if ($user_role === 'staff' && $store_product['branch-id'] != $user_branch) {
        echo json_encode(['success' => false, 'message' => 'Access denied: You can only move products from your branch']);
        ob_end_flush();
        exit;
    }
    
    if ($store_product['stock'] < $move_qty) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock in store']);
        ob_end_flush();
        exit;
    }
    
    // Check if shelf product exists (by barcode and branch)
    $shelf_check = $conn->prepare("SELECT id, stock FROM products WHERE barcode = ? AND `branch-id` = ?");
    $shelf_check->bind_param("si", $store_product['barcode'], $store_product['branch-id']);
    $shelf_check->execute();
    $shelf_product = $shelf_check->get_result()->fetch_assoc();
    $shelf_check->close();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        if (!$shelf_product) {
            // Create new shelf product
            $create_shelf = $conn->prepare("
                INSERT INTO products (name, barcode, `selling-price`, `buying-price`, stock, `branch-id`, expiry_date, sms_sent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $create_shelf->bind_param(
                "ssddiis",
                $store_product['name'],
                $store_product['barcode'],
                $store_product['selling-price'],
                $store_product['buying-price'],
                $move_qty,
                $store_product['branch-id'],
                $store_product['expiry_date']
            );
            $create_shelf->execute();
            $create_shelf->close();
        } else {
            // Update existing shelf product stock
            $update_shelf = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $update_shelf->bind_param("ii", $move_qty, $shelf_product['id']);
            $update_shelf->execute();
            $update_shelf->close();
        }
        
        // Decrease store stock
        $update_store = $conn->prepare("UPDATE store_products SET stock = stock - ? WHERE id = ?");
        $update_store->bind_param("ii", $move_qty, $store_product_id);
        $update_store->execute();
        $update_store->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Product moved to shelf successfully!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    ob_end_flush();
    exit;
}

// Get store stock for a product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_store_stock') {
    // Clean any output buffers
    while (ob_get_level()) ob_end_clean();
    ob_start();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $barcode = trim($_POST['barcode'] ?? '');
    $branch_id = intval($_POST['branch_id'] ?? 0);
    
    // SECURITY: Staff can only check stock in their branch
    if ($user_role === 'staff' && $branch_id != $user_branch) {
        echo json_encode(['stock' => 0]);
        ob_end_flush();
        exit;
    }
    
    $stmt = $conn->prepare("SELECT stock FROM store_products WHERE barcode = ? AND `branch-id` = ?");
    $stmt->bind_param("si", $barcode, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    echo json_encode(['stock' => $result ? $result['stock'] : 0]);
    ob_end_flush();
    exit;
}

// ==========================
// Pagination and filtering setup
// ==========================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Branch filter - UPDATED for staff
$where = "";
if ($user_role === 'staff' && $user_branch) {
    // Staff: ALWAYS restrict to their branch (ignore GET parameter)
    $selected_branch = $user_branch;
    $where = "WHERE `branch-id` = $user_branch";
} elseif (!empty($_GET['branch'])) {
    // Admin/Manager: allow branch filtering
    $selected_branch = (int)$_GET['branch'];
    $where = "WHERE `branch-id` = $selected_branch";
} else {
    // Admin/Manager: show all branches by default
    $selected_branch = null;
}

// ==========================
// Fetch STORE products
// ==========================
$store_count_res = $conn->query("SELECT COUNT(*) AS total FROM store_products $where");
$total_store_products = ($store_count_res->fetch_assoc())['total'] ?? 0;
$total_store_pages = ceil($total_store_products / $limit);

$store_result = $conn->query("
    SELECT store_products.*, branch.name AS branch_name 
    FROM store_products 
    JOIN branch ON store_products.`branch-id` = branch.id 
    $where 
    ORDER BY store_products.id DESC 
    LIMIT $offset,$limit
");

$store_products = [];
if ($store_result && $store_result->num_rows > 0) {
    while ($row = $store_result->fetch_assoc()) {
        $store_products[] = $row;
    }
}

// ==========================
// Fetch SHELF products
// ==========================
$shelf_where = str_replace('`branch-id`', 'products.`branch-id`', $where);
$shelf_count_res = $conn->query("SELECT COUNT(*) AS total FROM products $shelf_where");
$total_shelf_products = ($shelf_count_res->fetch_assoc())['total'] ?? 0;
$total_shelf_pages = ceil($total_shelf_products / $limit);

$shelf_result = $conn->query("
    SELECT products.*, branch.name AS branch_name 
    FROM products 
    JOIN branch ON products.`branch-id` = branch.id 
    $shelf_where 
    ORDER BY products.id DESC 
    LIMIT $offset,$limit
");

$shelf_products = [];
if ($shelf_result && $shelf_result->num_rows > 0) {
    while ($row = $shelf_result->fetch_assoc()) {
        $shelf_products[] = $row;
    }
}

// Display message from session (if redirected)
if (isset($_SESSION['product_message'])) {
    $message = $_SESSION['product_message'];
    unset($_SESSION['product_message']);
}
?>

<!-- Link external CSS -->
<link rel="stylesheet" href="product.css">

<div class="container mt-5">
    <?= isset($message) ? $message : "" ?>

    <!-- Tabs Navigation (Sales-style) -->
    <div class="tm-main-tabs mb-4" role="tablist">
        <button class="tm-tab-btn active" id="shelf-tab" data-bs-toggle="tab" data-bs-target="#shelf-products" type="button" role="tab">
            <i class="fa-solid fa-shelves"></i> Shelf Products
        </button>
        <button class="tm-tab-btn" id="store-tab" data-bs-toggle="tab" data-bs-target="#store-products" type="button" role="tab">
            <i class="fa-solid fa-warehouse"></i> Store Products
        </button>
    </div>

    <div class="tab-content" id="productTabsContent">
        <!-- SHELF PRODUCTS TAB (DEFAULT) -->
        <div class="tab-pane fade show active" id="shelf-products" role="tabpanel">
            <div class="card mb-4" style="border-left: 4px solid teal;">
                <div class="card-header d-flex justify-content-between align-items-center title-card">
                    <span>📋 Shelf Products <?php if ($user_role === 'staff') echo '(My Branch)'; ?></span>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="shelfSearchInput" class="form-control" placeholder="Search..." style="width:220px;">
                        <?php if ($user_role !== 'staff'): ?>
                        <!-- Branch filter dropdown (only for admin/manager) -->
                        <form method="GET" class="d-flex align-items-center ms-2">
                            <label class="me-2 fw-bold">Filter by Branch:</label>
                            <select name="branch" class="form-select" onchange="this.form.submit()">
                                <option value="">-- All Branches --</option>
                                <?php
                                $branches = $conn->query("SELECT id, name FROM branch");
                                while ($b = $branches->fetch_assoc()) {
                                    $selected = ($selected_branch == $b['id']) ? "selected" : "";
                                    echo "<option value='{$b['id']}' $selected>" . htmlspecialchars($b['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="transactions-table">
                        <table id="shelfProductsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <?php if (empty($selected_branch) && $user_role !== 'staff') echo "<th>Branch</th>"; ?>
                                    <th>Name</th>
                                    <th>Barcode</th>
                                    <th>Selling Price</th>
                                    <th>Buying Price</th>
                                    <th>Stock</th>
                                    <th>Expected Amount</th>
                                    <th>Profit/Unit</th>
                                    <th>Expected Profits</th>
                                    <th>Expiry Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($shelf_products) > 0) {
                                    $i = $offset + 1;
                                    foreach ($shelf_products as $row) {
                                        $sellingPrice = floatval($row['selling-price']);
                                        $buyingPrice = floatval($row['buying-price']);
                                        $stock = intval($row['stock']);
                                        $expectedAmount = $sellingPrice * $stock;
                                        $profitPerUnit = $sellingPrice - $buyingPrice;
                                        $expectedProfits = $profitPerUnit * $stock;

                                        echo "<tr>
                                            <td>{$i}</td>";
                                        if (empty($selected_branch) && $user_role !== 'staff') {
                                            echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
                                        }
                                        echo "<td>" . htmlspecialchars($row['name']) . "</td>
                                            <td>" . htmlspecialchars($row['barcode']) . "</td>
                                            <td>UGX " . number_format($sellingPrice, 2) . "</td>
                                            <td>UGX " . number_format($buyingPrice, 2) . "</td>
                                            <td>{$stock}</td>
                                            <td><span class='fw-bold text-primary'>UGX " . number_format($expectedAmount, 2) . "</span></td>
                                            <td><span class='fw-bold text-success'>UGX " . number_format($profitPerUnit, 2) . "</span></td>
                                            <td><span class='fw-bold text-info'>UGX " . number_format($expectedProfits, 2) . "</span></td>
                                            <td>{$row['expiry_date']}</td>
                                            <td>
                                                <button class='btn btn-sm btn-primary restock-btn' 
                                                        data-id='{$row['id']}' 
                                                        data-name='" . htmlspecialchars($row['name']) . "' 
                                                        data-barcode='" . htmlspecialchars($row['barcode']) . "' 
                                                        data-branch='{$row['branch-id']}'
                                                        title='Restock from Store'>
                                                    <i class='fa fa-plus'></i>
                                                </button>
                                            </td>
                                        </tr>";
                                        $i++;
                                    }
                                } else {
                                    $colspan = (empty($selected_branch) && $user_role !== 'staff') ? 12 : 11;
                                    echo "<tr><td colspan='$colspan' class='text-center text-muted'>No products on shelf.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- STORE PRODUCTS TAB -->
        <div class="tab-pane fade" id="store-products" role="tabpanel">
            <!-- Add Product Form -->
            <div class="card mb-4" style="border-left: 4px solid teal;">
                <div class="card-header title-card d-flex justify-content-between align-items-center">
                    <span>➕ Add New Product to Store</span>
                    <button type="button" id="scanProductBarcodeBtn" class="btn btn-outline-primary btn-scan-barcode" title="Scan Barcode">
                        <i class="fa-solid fa-barcode"></i>
                    </button>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="barcode" class="form-label fw-semibold">Barcode</label>
                                <input type="text" name="barcode" id="barcode" class="form-control" placeholder="Scan or enter barcode" required>
                            </div>
                            <div class="col-md-3">
                                <label for="name" class="form-label fw-semibold">Product Name</label>
                                <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Coca-Cola 500ml" required>
                            </div>
                            <div class="col-md-3">
                                <label for="price" class="form-label fw-semibold">Selling Price</label>
                                <input type="number" step="0.01" name="price" id="price" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="col-md-3">
                                <label for="cost" class="form-label fw-semibold">Buying Price</label>
                                <input type="number" step="0.01" name="cost" id="cost" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="col-md-3">
                                <label for="stock" class="form-label fw-semibold">Stock Quantity</label>
                                <input type="number" name="stock" id="stock" class="form-control" placeholder="0" required>
                            </div>
                            <div class="col-md-3">
                                <label for="branch" class="form-label fw-semibold">Branch</label>
                                <select name="branch_id" id="branch" class="form-select" required <?php echo ($user_role === 'staff') ? 'disabled' : ''; ?>>
                                    <option value="">-- Select Branch --</option>
                                    <?php
                                    if ($user_role === 'staff' && $user_branch) {
                                        // Staff: only show their branch
                                        $branch = $conn->prepare("SELECT id, name FROM branch WHERE id = ?");
                                        $branch->bind_param("i", $user_branch);
                                        $branch->execute();
                                        $branch_res = $branch->get_result();
                                        if ($b = $branch_res->fetch_assoc()) {
                                            echo "<option value='{$b['id']}' selected>" . htmlspecialchars($b['name']) . "</option>";
                                        }
                                        $branch->close();
                                    } else {
                                        // Admin/Manager: show all branches
                                        $branches = $conn->query("SELECT id, name FROM branch");
                                        while ($b = $branches->fetch_assoc()) {
                                            echo "<option value='{$b['id']}'>" . htmlspecialchars($b['name']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <?php if ($user_role === 'staff' && $user_branch): ?>
                                    <!-- Hidden input to ensure branch_id is submitted when dropdown is disabled -->
                                    <input type="hidden" name="branch_id" value="<?= $user_branch ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label for="expiry_date" class="form-label fw-semibold">Expiry Date</label>
                                <input type="date" name="expiry_date" id="expiry_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" name="add_store_product" class="btn btn-primary">➕ Add to Store</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Store Products Table -->
            <div class="card mb-4" style="border-left: 4px solid teal;">
                <div class="card-header d-flex justify-content-between align-items-center title-card">
                    <span>📦 Store Products <?php if ($user_role === 'staff') echo '(My Branch)'; ?></span>
                    <input type="text" id="storeSearchInput" class="form-control" placeholder="Search..." style="width:220px;">
                </div>
                <div class="card-body">
                    <div class="transactions-table">
                        <table id="storeProductsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <?php if (empty($selected_branch) && $user_role !== 'staff') echo "<th>Branch</th>"; ?>
                                    <th>Name</th>
                                    <th>Barcode</th>
                                    <th>Selling Price</th>
                                    <th>Buying Price</th>
                                    <th>Stock</th>
                                    <th>Expected Amount</th>
                                    <th>Profit/Unit</th>
                                    <th>Expected Profits</th>
                                    <th>Expiry Date</th>
                                    <th>Actions</th> <!-- NEW COLUMN -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($store_products) > 0) {
                                    $i = $offset + 1;
                                    foreach ($store_products as $row) {
                                        $sellingPrice = floatval($row['selling-price']);
                                        $buyingPrice = floatval($row['buying-price']);
                                        $stock = intval($row['stock']);
                                        $expectedAmount = $sellingPrice * $stock;
                                        $profitPerUnit = $sellingPrice - $buyingPrice;
                                        $expectedProfits = $profitPerUnit * $stock;

                                        echo "<tr>
                                            <td>{$i}</td>";
                                        if (empty($selected_branch) && $user_role !== 'staff') {
                                            echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
                                        }
                                        echo "<td>" . htmlspecialchars($row['name']) . "</td>
                                            <td>" . htmlspecialchars($row['barcode']) . "</td>
                                            <td>UGX " . number_format($sellingPrice, 2) . "</td>
                                            <td>UGX " . number_format($buyingPrice, 2) . "</td>
                                            <td>{$stock}</td>
                                            <td><span class='fw-bold text-primary'>UGX " . number_format($expectedAmount, 2) . "</span></td>
                                            <td><span class='fw-bold text-success'>UGX " . number_format($profitPerUnit, 2) . "</span></td>
                                            <td><span class='fw-bold text-info'>UGX " . number_format($expectedProfits, 2) . "</span></td>
                                            <td>{$row['expiry_date']}</td>
                                            <td>
                                                <button class='btn btn-sm btn-success move-to-shelf-btn' 
                                                        data-id='{$row['id']}' 
                                                        data-name='" . htmlspecialchars($row['name']) . "' 
                                                        data-stock='{$stock}'
                                                        title='Move to Shelf'>
                                                    <i class='fa fa-arrow-right'></i>
                                                </button>
                                            </td>
                                        </tr>";
                                        $i++;
                                    }
                                } else {
                                    $colspan = (empty($selected_branch) && $user_role !== 'staff') ? 12 : 11; // Updated colspan
                                    echo "<tr><td colspan='$colspan' class='text-center text-muted'>No products in store.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Move to Shelf Modal -->
<div class="modal fade" id="moveToShelfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary-color);color:#fff;">
                <h5 class="modal-title">Move Product to Shelf</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="moveProductName" class="fw-semibold mb-3"></p>
                <input type="hidden" id="moveStoreProductId">
                
                <div class="mb-3">
                    <label class="form-label">Available in Store</label>
                    <input type="text" id="moveAvailableStock" class="form-control" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Quantity to Move to Shelf</label>
                    <input type="number" id="moveQuantity" class="form-control" min="1" placeholder="Enter quantity">
                </div>
                
                <div id="moveMessage"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmMoveToShelf" class="btn btn-success">Move to Shelf</button>
            </div>
        </div>
    </div>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--primary-color);color:#fff;">
                <h5 class="modal-title">Restock Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="restockProductName" class="fw-semibold mb-3"></p>
                <input type="hidden" id="restockProductId">
                <input type="hidden" id="restockBranchId">
                <input type="hidden" id="restockBarcode">
                
                <div class="mb-3">
                    <label class="form-label">Available in Store</label>
                    <input type="text" id="restockAvailable" class="form-control" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Quantity to Add to Shelf</label>
                    <input type="number" id="restockQuantity" class="form-control" min="1" placeholder="Enter quantity">
                </div>
                
                <div id="restockMessage"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmRestock" class="btn btn-primary">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Barcode Scan Modal (reused from original) -->
<div id="productBarcodeScanModal" class="barcode-scan-modal" style="display:none;">
    <div class="barcode-scan-card"  style="border-left: 4px solid teal;">
        <div class="barcode-scan-header d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-barcode"></i> Scan Product Barcode</span>
            <button type="button" id="closeProductBarcodeScan" class="btn btn-close"></button>
        </div>
        <div class="barcode-scan-body">
            <div class="barcode-scan-view-area">
                <video id="productBarcodeScanVideo" autoplay muted playsinline></video>
                <canvas id="productBarcodeScanCanvas" style="display:none;"></canvas>
                <button type="button" id="rotateProductBarcodeCameraBtn" class="btn btn-secondary barcode-rotate-btn" title="Switch Camera">
                    <i class="fa-solid fa-camera-rotate"></i>
                </button>
            </div>
            <div class="barcode-scan-text mt-3 mb-2 text-center">
                <span>Scan product barcode to auto-fill.</span>
            </div>
            <div class="barcode-scan-mode mb-3 text-center">
                <label class="me-2">Scan Mode:</label>
                <select id="productBarcodeScanMode" class="form-select d-inline-block" style="width:auto;">
                    <option value="camera">Camera</option>
                    <option value="hardware">Barcode Hardware</option>
                </select>
            </div>
            <div id="productBarcodeScanStatus" class="barcode-scan-status text-center"></div>
        </div>
    </div>
</div>

<!-- Pass PHP data to JavaScript -->
<script>
    // Set column index for search filter (depends on branch column visibility)
    window.productNameColumnIndex = <?php echo (empty($selected_branch) && $user_role !== 'staff') ? '2' : '1'; ?>;
</script>

<!-- Link external JavaScript -->
<script src="product.js"></script>

<?php include '../includes/footer.php'; ?>
