<?php
// --- STEP 1: Start session and include ONLY db.php (NO HTML OUTPUT) ---
session_start();
include '../includes/db.php';

// --- STEP 2: HANDLE ALL AJAX REQUESTS FIRST (before any HTML/includes) ---

// AJAX: Shop Debtor Repayment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_debtor'])) {
    header('Content-Type: application/json; charset=utf-8');
    $debtor_id = intval($_POST['id'] ?? 0);
    $pay_amt = max(0, floatval($_POST['amount'] ?? 0));
    $pm_in = trim($_POST['pm'] ?? 'Cash');
    $uid = $_SESSION['user_id'] ?? 0;
    $user_branch = $_SESSION['branch_id'] ?? null;

    if ($debtor_id <= 0 || $pay_amt <= 0) { 
        echo json_encode(['success'=>false,'message'=>'Invalid input']); 
        exit; 
    }

    $dq = $conn->prepare("SELECT id, customer_id, invoice_no, balance, amount_paid, payment_method FROM debtors WHERE id=? LIMIT 1");
    $dq->bind_param("i", $debtor_id);
    $dq->execute();
    $debtor = $dq->get_result()->fetch_assoc();
    $dq->close();
    
    if (!$debtor) { 
        echo json_encode(['success'=>false,'message'=>'Debtor not found']); 
        exit; 
    }

    $remaining = floatval($debtor['balance']);
    if ($remaining <= 0) { 
        echo json_encode(['success'=>false,'message'=>'Already settled']); 
        exit; 
    }
    if ($pay_amt > $remaining) { 
        $pay_amt = $remaining; 
    }

    $conn->begin_transaction();
    $ok = true;

    try { 
        $rp4 = str_pad((string)random_int(0,9999), 4, '0', STR_PAD_LEFT); 
    } catch (Throwable $e) { 
        $rp4 = str_pad((string)mt_rand(0,9999), 4, '0', STR_PAD_LEFT); 
    }
    $receiptNo = 'RP-' . $rp4;
    $now = date('Y-m-d H:i:s');
    $cust_id = intval($debtor['customer_id'] ?? 0);
    $pm_to_use = ($cust_id > 0) ? 'Customer File' : ($pm_in ?: 'Cash');

    $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,`date`,payment_method,customer_id,receipt_no) VALUES (0, ?, 0, ?, ?, 0, 0, NOW(), ?, ?, ?)");
    $insS->bind_param("idisis", $user_branch, $pay_amt, $uid, $pm_to_use, $cust_id, $receiptNo);
    if (!$insS->execute()) { $ok = false; }
    $insS->close();

    if ($ok) {
        $new_bal = max(0.0, $remaining - $pay_amt);
        $is_paid = ($new_bal <= 0.00001) ? 1 : 0;
        $ud = $conn->prepare("UPDATE debtors SET balance = ?, amount_paid = amount_paid + ?, is_paid = IF(?, 1, is_paid), receipt_no = ? WHERE id = ?");
        $flag = $is_paid ? 1 : 0;
        $ud->bind_param("ddisi", $new_bal, $pay_amt, $flag, $receiptNo, $debtor_id);
        if (!$ud->execute()) { $ok = false; }
        $ud->close();
    }

    if ($ok && $cust_id > 0) {
        $invoice_no = $debtor['invoice_no'] ?? '';
        $products_text = "Repayment of invoice no. " . $invoice_no;
        $sold_by_name = $_SESSION['username'] ?? 'staff';
        $status = 'credit repayment';
        
        $ct = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by, status, invoice_receipt_no) VALUES (?, ?, ?, ?, 0, ?, ?, ?)");
        $ct->bind_param("issdsss", $cust_id, $now, $products_text, $pay_amt, $sold_by_name, $status, $receiptNo);
        if (!$ct->execute()) { $ok = false; }
        $ct->close();
    }

    if ($ok && $cust_id > 0) {
        $uc = $conn->prepare("UPDATE customers SET amount_credited = GREATEST(0, amount_credited - ?) WHERE id = ?");
        $uc->bind_param("di", $pay_amt, $cust_id);
        if (!$uc->execute()) { $ok = false; }
        $uc->close();
    }

    if ($ok) { 
        $conn->commit(); 
        echo json_encode(['success'=>true,'reload'=>true]); 
    } else { 
        $conn->rollback(); 
        echo json_encode(['success'=>false,'message'=>'Failed to record repayment']); 
    }
    exit;
}

// AJAX: Customer Debtor Repayment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_customer_debtor'])) {
    header('Content-Type: application/json; charset=utf-8');
    $transaction_id = intval($_POST['id'] ?? 0);
    $pay_amt = max(0, floatval($_POST['amount'] ?? 0));
    $uid = $_SESSION['user_id'] ?? 0;
    $user_branch = $_SESSION['branch_id'] ?? null;

    if ($transaction_id <= 0 || $pay_amt <= 0) { 
        echo json_encode(['success'=>false,'message'=>'Invalid input']); 
        exit; 
    }

    $tq = $conn->prepare("SELECT ct.*, c.id as customer_id FROM customer_transactions ct JOIN customers c ON ct.customer_id = c.id WHERE ct.id=? AND ct.status='debtor' LIMIT 1");
    $tq->bind_param("i", $transaction_id);
    $tq->execute();
    $trans = $tq->get_result()->fetch_assoc();
    $tq->close();
    
    if (!$trans) { 
        echo json_encode(['success'=>false,'message'=>'Debtor transaction not found']); 
        exit; 
    }

    $customer_id = intval($trans['customer_id']);
    $amount_credited = floatval($trans['amount_credited'] ?? 0);
    $original_invoice = $trans['invoice_receipt_no'] ?? '';
    $products_bought = $trans['products_bought'] ?? '[]';
    
    if ($amount_credited <= 0) { 
        echo json_encode(['success'=>false,'message'=>'Already settled']); 
        exit; 
    }
    
    if ($pay_amt > $amount_credited) { 
        $pay_amt = $amount_credited; 
    }

    $conn->begin_transaction();
    $ok = true;

    try { 
        $rp4 = str_pad((string)random_int(0,9999), 4, '0', STR_PAD_LEFT); 
    } catch (Throwable $e) { 
        $rp4 = str_pad((string)mt_rand(0,9999), 4, '0', STR_PAD_LEFT); 
    }
    $receiptNo = 'RP-' . $rp4;
    $now = date('Y-m-d H:i:s');
    $sold_by = $_SESSION['username'] ?? 'staff';

    if ($pay_amt >= $amount_credited) {
        // Parse products from original transaction
        $products_data = json_decode($products_bought, true);
        
        if (is_array($products_data) && count($products_data) > 0) {
            // Insert each product as a separate sale record
            foreach ($products_data as $item) {
                $product_name = $item['name'] ?? $item['product'] ?? 'Unknown Product';
                $qty = intval($item['quantity'] ?? $item['qty'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $item_total = $price * $qty;
                
                // Find product ID by name and branch
                $pstmt = $conn->prepare("SELECT id FROM products WHERE name = ? AND `branch-id` = ? LIMIT 1");
                $pstmt->bind_param("si", $product_name, $user_branch);
                $pstmt->execute();
                $prod_res = $pstmt->get_result()->fetch_assoc();
                $pstmt->close();
                
                $product_id = $prod_res ? intval($prod_res['id']) : 0;
                
                // Insert sale record with actual product info
                $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,`date`,payment_method,customer_id,receipt_no) VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), 'Customer File', ?, ?)");
                $insS->bind_param("iiidiis", $product_id, $user_branch, $qty, $item_total, $uid, $customer_id, $receiptNo);
                if (!$insS->execute()) { $ok = false; }
                $insS->close();
            }
        } else {
            // Fallback: single sale record with product-id = 0
            $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,`date`,payment_method,customer_id,receipt_no) VALUES (0, ?, 0, ?, ?, 0, 0, NOW(), 'Customer File', ?, ?)");
            $insS->bind_param("idiii", $user_branch, $pay_amt, $uid, $customer_id, $receiptNo);
            if (!$insS->execute()) { $ok = false; }
            $insS->close();
        }

        if ($ok) {
            $products_text = "Payment for invoice number " . $original_invoice;
            $ct = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by, status, invoice_receipt_no) VALUES (?, ?, ?, ?, 0, ?, 'paid', ?)");
            $ct->bind_param("issdss", $customer_id, $now, $products_text, $pay_amt, $sold_by, $receiptNo);
            if (!$ct->execute()) { $ok = false; }
            $ct->close();
        }

        if ($ok) {
            $uc = $conn->prepare("UPDATE customers SET amount_credited = GREATEST(0, amount_credited - ?) WHERE id = ?");
            $uc->bind_param("di", $pay_amt, $customer_id);
            if (!$uc->execute()) { $ok = false; }
            $uc->close();
        }

        if ($ok) {
            $ut = $conn->prepare("UPDATE customer_transactions SET status = 'paid' WHERE id = ?");
            $ut->bind_param("i", $transaction_id);
            if (!$ut->execute()) { $ok = false; }
            $ut->close();
        }
    } else {
        // Partial payment: just update the amount_credited in the debtor record
        $new_credited = $amount_credited - $pay_amt;
        $ut = $conn->prepare("UPDATE customer_transactions SET amount_credited = ? WHERE id = ?");
        $ut->bind_param("di", $new_credited, $transaction_id);
        if (!$ut->execute()) { $ok = false; }
        $ut->close();

        if ($ok) {
            $uc = $conn->prepare("UPDATE customers SET amount_credited = GREATEST(0, amount_credited - ?) WHERE id = ?");
            $uc->bind_param("di", $pay_amt, $customer_id);
            if (!$uc->execute()) { $ok = false; }
            $uc->close();
        }
    }

    if ($ok) { 
        $conn->commit(); 
        echo json_encode(['success'=>true,'reload'=>true]); 
    } else { 
        $conn->rollback(); 
        echo json_encode(['success'=>false,'message'=>'Failed to record payment']); 
    }
    exit;
}

// --- STEP 3: NOW include auth, sidebar, header (HTML output starts here) ---
include '../includes/auth.php';
require_role(["admin", "manager", "staff"]);

if ($_SESSION['role'] === 'staff') {
    include '../pages/sidebar_staff.php';
} else {
    include '../pages/sidebar.php';
}
include '../includes/header.php';
include '../pages/handle_debtor_payment.php';

// NEW: ensure required columns exist (invoice/receipt, customer link)
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS invoice_no VARCHAR(32) NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE sales ADD COLUMN invoice_no VARCHAR(32) NULL"); }
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS receipt_no VARCHAR(32) NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE sales ADD COLUMN receipt_no VARCHAR(32) NULL"); }
$conn->query("ALTER TABLE debtors ADD COLUMN IF NOT EXISTS customer_id INT NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE debtors ADD COLUMN customer_id INT NULL"); }
$conn->query("ALTER TABLE debtors ADD COLUMN IF NOT EXISTS invoice_no VARCHAR(32) NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE debtors ADD COLUMN invoice_no VARCHAR(32) NULL"); }
$conn->query("ALTER TABLE debtors ADD COLUMN IF NOT EXISTS receipt_no VARCHAR(32) NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE debtors ADD COLUMN receipt_no VARCHAR(32) NULL"); }
// NEW: ensure debtors.payment_method exists
$conn->query("ALTER TABLE debtors ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE debtors ADD COLUMN payment_method VARCHAR(50) NULL"); }
$conn->query("ALTER TABLE customer_transactions ADD COLUMN IF NOT EXISTS invoice_receipt_no VARCHAR(32) NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE customer_transactions ADD COLUMN invoice_receipt_no VARCHAR(32) NULL"); }

$message = "";
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Filters
$selected_branch = $_GET['branch'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// --- NEW: Initialize Product Summary filter variables ---
$ps_date_from = $_GET['ps_date_from'] ?? '';
$ps_date_to = $_GET['ps_date_to'] ?? '';
$ps_branch = $_GET['ps_branch'] ?? '';

// Build WHERE clause for filters
$where = [];
if ($user_role === 'staff') {
    $where[] = "sales.`branch-id` = $user_branch";
} elseif ($selected_branch) {
    $where[] = "sales.`branch-id` = " . intval($selected_branch);
}
if ($date_from) {
    $where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($date_to) . "'";
}
$whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";

// Pagination setup
$items_per_page = 60;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Count total sales for pagination (MUST use LEFT JOIN like the main query)
$count_query = "SELECT COUNT(*) as total FROM sales LEFT JOIN products ON sales.`product-id` = products.id $whereClause";
$total_result = $conn->query($count_query);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Fetch sales for current page
$sales_query = "
    SELECT sales.id, 
           COALESCE(products.name, CASE 
               WHEN sales.`product-id` = 0 THEN 'Debtor Repayment'
               ELSE 'Unknown'
           END) AS `product-name`, 
           sales.quantity, 
           sales.amount, 
           sales.`sold-by`, 
           sales.date, 
           branch.name AS branch_name, 
           sales.payment_method,
           sales.receipt_no
    FROM sales
    LEFT JOIN products ON sales.`product-id` = products.id
    JOIN branch ON sales.`branch-id` = branch.id
    $whereClause
    ORDER BY sales.id DESC
    LIMIT $items_per_page OFFSET $offset
";
$sales = $conn->query($sales_query);

// Fetch branches for admin/manager filter
$branches = ($user_role !== 'staff') ? $conn->query("SELECT id, name FROM branch") : [];

// Calculate total sum of sales (filtered) - MUST use LEFT JOIN
$sum_query = "
    SELECT SUM(sales.amount) AS total_sales
    FROM sales
    LEFT JOIN products ON sales.`product-id` = products.id
    $whereClause
";
$sum_result = $conn->query($sum_query);
$sum_row = $sum_result->fetch_assoc();
$total_sales_sum = $sum_row['total_sales'] ?? 0;

// Debtors filters
$debtor_where = [];
if ($user_role === 'staff') {
    $debtor_where[] = "debtors.branch_id = $user_branch";
} elseif (!empty($_GET['debtor_branch'])) {
    $debtor_where[] = "debtors.branch_id = " . intval($_GET['debtor_branch']);
}
if (!empty($_GET['debtor_date_from'])) {
    $debtor_where[] = "DATE(debtors.created_at) >= '" . $conn->real_escape_string($_GET['debtor_date_from']) . "'";
}
if (!empty($_GET['debtor_date_to'])) {
    $debtor_where[] = "DATE(debtors.created_at) <= '" . $conn->real_escape_string($_GET['debtor_date_to']) . "'";
}
$debtorWhereClause = count($debtor_where) ? "WHERE " . implode(' AND ', $debtor_where) : "";

// Fetch debtors for the table
$debtors_result = $conn->query("
    SELECT id, debtor_name, debtor_email, item_taken, quantity_taken, payment_method, amount_paid, balance, is_paid, created_at
    FROM debtors
    $debtorWhereClause
    ORDER BY created_at DESC
    LIMIT 100
");

// --- NEW: Fetch Customer Debtors (from customer_transactions with status='debtor') ---
$customer_debtor_where = [];
if ($user_role === 'staff') {
    // Staff can only see their branch - need to join with sales/branch if available
    // For now, we'll show all customer debtors (adjust if branch filtering needed)
}
if (!empty($_GET['cust_debtor_date_from'])) {
    $customer_debtor_where[] = "DATE(ct.date_time) >= '" . $conn->real_escape_string($_GET['cust_debtor_date_from']) . "'";
}
if (!empty($_GET['cust_debtor_date_to'])) {
    $customer_debtor_where[] = "DATE(ct.date_time) <= '" . $conn->real_escape_string($_GET['cust_debtor_date_to']) . "'";
}
$custDebtorWhereClause = count($customer_debtor_where) ? " AND " . implode(' AND ', $customer_debtor_where) : "";

$customer_debtors_result = $conn->query("
    SELECT 
        ct.id,
        ct.date_time,
        ct.invoice_receipt_no,
        c.name AS debtor_name,
        c.email AS debtor_email,
        c.contact AS debtor_contact,
        ct.products_bought,
        ct.amount_paid,
        ct.amount_credited AS balance,
        ct.sold_by,
        ct.status
    FROM customer_transactions ct
    JOIN customers c ON ct.customer_id = c.id
    WHERE ct.status = 'debtor'
    $custDebtorWhereClause
    ORDER BY ct.date_time DESC
    LIMIT 100
");

// --- NEW: Fetch Product Summary data ---
$product_summary_where = [];
if ($user_role === 'staff') {
    $product_summary_where[] = "sales.`branch-id` = $user_branch";
} elseif ($ps_branch) {
    $product_summary_where[] = "sales.`branch-id` = " . intval($ps_branch);
}
if ($ps_date_from) {
    $product_summary_where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($ps_date_from) . "'";
}
if ($ps_date_to) {
    $product_summary_where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($ps_date_to) . "'";
}
$productSummaryWhereClause = count($product_summary_where) ? "WHERE " . implode(' AND ', $product_summary_where) : "";

$product_summary_result = $conn->query("
    SELECT 
        DATE(sales.date) AS sale_date,
        branch.name AS branch_name,
        COALESCE(products.name, 'Unknown Product') AS product_name,
        SUM(sales.quantity) AS items_sold
    FROM sales
    LEFT JOIN products ON sales.`product-id` = products.id
    JOIN branch ON sales.`branch-id` = branch.id
    $productSummaryWhereClause
    GROUP BY sale_date, branch.name, products.name
    ORDER BY sale_date DESC, branch.name ASC, product_name ASC
    LIMIT 500
");
?>

<!-- Tabs for Sales and Debtors -->
<div class="container-fluid mt-4">
    <!-- New pill styles (same as Till Management) -->
    <style>
    .tm-main-tabs { display:flex; flex-wrap:wrap; gap:.75rem; margin-top:.25rem; border:none; }
    .tm-main-tabs .tm-tab-btn {
        border:2px solid var(--primary-color);
        background:#fff;
        color:var(--primary-color);
        font-weight:600;
        border-radius:14px;
        padding:.45rem 1.1rem;
        box-shadow:0 2px 6px rgba(0,0,0,.08);
        transition:background .18s,color .18s,box-shadow .18s,transform .18s;
        font-size:.95rem;
    }
    .tm-main-tabs .tm-tab-btn:hover { background:var(--primary-color); color:#fff; transform:translateY(-2px); }
    .tm-main-tabs .tm-tab-btn.active { background:var(--primary-color); color:#fff; box-shadow:0 4px 10px rgba(26,188,156,.35); }
    .tm-main-tabs .tm-tab-btn:focus { outline:none; box-shadow:0 0 0 3px rgba(26,188,156,.25); }
    body.dark-mode .tm-main-tabs .tm-tab-btn {
        background:#23243a; border-color:#1abc9c; color:#1abc9c; box-shadow:0 2px 6px rgba(0,0,0,.4);
    }
    body.dark-mode .tm-main-tabs .tm-tab-btn:hover,
    body.dark-mode .tm-main-tabs .tm-tab-btn.active { background:#1abc9c; color:#fff; }
    </style>

    <!-- Updated tabs -->
    <ul class="nav nav-pills tm-main-tabs mb-4" id="salesTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link tm-tab-btn active"
                    id="sales-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#sales-table"
                    type="button"
                    role="tab"
                    aria-controls="sales-table"
                    aria-selected="true">
                Sales Records
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link tm-tab-btn"
                    id="debtors-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#debtors-table"
                    type="button"
                    role="tab"
                    aria-controls="debtors-table"
                    aria-selected="false">
                Debtors
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link tm-tab-btn"
                    id="payment-analysis-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#payment-analysis"
                    type="button"
                    role="tab"
                    aria-controls="payment-analysis"
                    aria-selected="false">
                Payment Method Analysis
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link tm-tab-btn"
                    id="product-summary-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#product-summary"
                    type="button"
                    role="tab"
                    aria-controls="product-summary"
                    aria-selected="false">
                Product Summary
            </button>
        </li>
    </ul>

    <div class="tab-content" id="salesTabsContent">
        <!-- Payment Method Analysis Tab -->
        <div class="tab-pane fade" id="payment-analysis" role="tabpanel" aria-labelledby="payment-analysis-tab">
            <div class="card mb-4 chart-card"  style="border-left: 4px solid teal;">
                <div class="card-header bg-light text-black fw-bold" style="border-radius:12px 12px 0 0;">
                    Payment Method Analysis
                </div>
                <div class="card-body">
                    <?php
                    // Filters for Payment Analysis (separate from Sales tab filters)
                    $pa_selected_branch = $_GET['pa_branch'] ?? '';
                    $pa_date_from = $_GET['pa_date_from'] ?? '';
                    $pa_date_to = $_GET['pa_date_to'] ?? '';

                    // Build monthly totals per payment method for charts
                    $methods = ['Cash','MTN MoMo','Airtel Money','Bank'];
                    $pa_where = [];
                    if ($user_role === 'staff') {
                        $pa_where[] = "sales.`branch-id` = $user_branch";
                    } elseif ($pa_selected_branch) {
                        $pa_where[] = "sales.`branch-id` = " . intval($pa_selected_branch);
                    }
                    if ($pa_date_from) {
                        $pa_where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($pa_date_from) . "'";
                    }
                    if ($pa_date_to) {
                        $pa_where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($pa_date_to) . "'";
                    }
                    $pa_whereClause = count($pa_where) ? "WHERE " . implode(' AND ', $pa_where) : "";

                    $pm_monthly_sql = "
                        SELECT DATE_FORMAT(sales.date, '%Y-%m') AS ym, COALESCE(sales.payment_method,'Cash') AS pm, SUM(sales.amount) AS total
                        FROM sales
                        $pa_whereClause
                        GROUP BY ym, pm
                        ORDER BY ym ASC
                    ";
                    $pm_monthly_res = $conn->query($pm_monthly_sql);
                    $month_set = [];
                    $data_map = [];
                    foreach ($methods as $m) { $data_map[$m] = []; }
                    if ($pm_monthly_res) {
                        while ($r = $pm_monthly_res->fetch_assoc()) {
                            $ym = $r['ym'];
                            $pm = $r['pm'];
                            if (!in_array($ym, $month_set, true)) $month_set[] = $ym;
                            if (!isset($data_map[$pm])) $data_map[$pm] = [];
                            $data_map[$pm][$ym] = (float)$r['total'];
                        }
                    }
                    // Ensure months sorted
                    sort($month_set);
                    // Build aligned series
                    $chart_labels = $month_set;
                    $series = [];
                    foreach ($methods as $m) {
                        $row = [];
                        foreach ($chart_labels as $ym) {
                            $row[] = isset($data_map[$m][$ym]) ? round($data_map[$m][$ym], 2) : 0;
                        }
                        $series[$m] = $row;
                    }

                    // Daily totals table
                    $dailyWhere = $pa_whereClause;
                    if (empty($pa_date_from) && empty($pa_date_to)) {
                        $dailyWhere .= ($dailyWhere ? " AND " : " WHERE ") . "DATE(sales.date) >= CURDATE() - INTERVAL 30 DAY";
                    }
                    $daily_sql = "
                        SELECT DATE(sales.date) AS day, COALESCE(sales.payment_method,'Cash') AS pm, SUM(sales.amount) AS total
                        FROM sales
                        $dailyWhere
                        GROUP BY day, pm
                        ORDER BY day DESC, pm ASC
                        LIMIT 500
                    ";
                    $daily_res = $conn->query($daily_sql);
                    ?>

                    <!-- Charts Grid -->
                    <div class="row">
                        <div class="col-md-6 mb-4"><div style="height:300px"><canvas id="chartCash"></canvas></div></div>
                        <div class="col-md-6 mb-4"><div style="height:300px"><canvas id="chartMtn"></canvas></div></div>
                        <div class="col-md-6 mb-4"><div style="height:300px"><canvas id="chartAirtel"></canvas></div></div>
                        <div class="col-md-6 mb-4"><div style="height:300px"><canvas id="chartBank"></canvas></div></div>
                    </div>

                    <!-- Filters (Payment Analysis) -->
                    <div class="pa-filter-bar d-flex align-items-center flex-wrap gap-2 mb-3 p-2 rounded">
                        <form method="GET" class="d-flex align-items-center flex-wrap gap-2" style="gap:1rem;">
                            <label class="fw-bold me-2">From:</label>
                            <input type="date" name="pa_date_from" class="form-select me-2" value="<?= htmlspecialchars($pa_date_from) ?>" style="width:150px;">
                            <label class="fw-bold me-2">To:</label>
                            <input type="date" name="pa_date_to" class="form-select me-2" value="<?= htmlspecialchars($pa_date_to) ?>" style="width:150px;">
                            <?php if ($user_role !== 'staff'): ?>
                            <label class="fw-bold me-2">Branch:</label>
                            <select name="pa_branch" class="form-select me-2" style="width:180px;">
                                <option value="">-- All Branches --</option>
                                <?php $branches_pa = $conn->query("SELECT id, name FROM branch"); while ($b = $branches_pa->fetch_assoc()): $sel = ($pa_selected_branch == $b['id']) ? 'selected' : ''; ?>
                                    <option value="<?= $b['id'] ?>" <?= $sel ?>><?= htmlspecialchars($b['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary ms-2">Filter</button>
                        </form>
                        <!-- Report Button: icon for small, full for md+ -->
                        <button type="button" class="btn btn-success ms-2 d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('payment_analysis')">
                            <i class="fa fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-success ms-2 d-none d-md-inline-flex" onclick="openReportGen('payment_analysis')">
                            <i class="fa fa-file-pdf"></i> Generate Report
                        </button>
                    </div>

                    <!-- Daily Totals Table -->
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($daily_res && $daily_res->num_rows > 0): ?>
                                    <?php
                                        $currentDay = null;
                                        $grandTotal = 0;
                                        $dayTotal = 0;
                                        $dailyRows = [];
                                        while ($r = $daily_res->fetch_assoc()) { $dailyRows[] = $r; }
                                        foreach ($dailyRows as $r):
                                            if ($currentDay !== null && $currentDay !== $r['day']):
                                    ?>
                                        <tr>
                                            <td colspan="2" class="text-end fw-bold">Total for <?= htmlspecialchars($currentDay) ?></td>
                                            <td><span class="fw-bold text-primary">UGX <?= number_format($dayTotal, 2) ?></span></td>
                                        </tr>
                                    <?php
                                                $grandTotal += $dayTotal;
                                                $dayTotal = 0;
                                            endif;
                                            $currentDay = $r['day'];
                                            $dayTotal += (float)$r['total'];
                                    ?>
                                        <tr>
                                            <td><small class="text-muted"><?= htmlspecialchars($r['day']) ?></small></td>
                                            <td><?= htmlspecialchars($r['pm']) ?></td>
                                            <td><span class="fw-bold text-success">UGX <?= number_format($r['total'], 2) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($currentDay !== null): ?>
                                        <tr>
                                            <td colspan="2" class="text-end fw-bold">Total for <?= htmlspecialchars($currentDay) ?></td>
                                            <td><span class="fw-bold text-primary">UGX <?= number_format($dayTotal, 2) ?></span></td>
                                        </tr>
                                        <?php $grandTotal += $dayTotal; ?>
                                        <tr>
                                            <td colspan="2" class="text-end fw-bold">Grand Total</td>
                                            <td><span class="fw-bold text-danger">UGX <?= number_format($grandTotal, 2) ?></span></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No payments found for the selected period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Chart.js and initialization -->
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                    window.addEventListener('DOMContentLoaded', function() {
                        const labels = <?= json_encode($chart_labels) ?>.map(m => {
                            const [y, mth] = m.split('-');
                            const date = new Date(parseInt(y), parseInt(mth)-1, 1);
                            return date.toLocaleString('en-US', { month: 'short', year: 'numeric' });
                        });
                        const dataByMethod = <?= json_encode($series) ?>;
                        const colors = {
                            'Cash': '#1abc9c',
                            'MTN MoMo': '#f1c40f',
                            'Airtel Money': '#e74c3c',
                            'Bank': '#3498db'
                        };

                        const getThemeColors = () => {
                            const isDark = document.body.classList.contains('dark-mode');
                            return {
                                textColor: isDark ? '#ffffff' : '#000000',
                                gridColor: isDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)'
                            };
                        };

                        const makeOptions = (title) => {
                            const { textColor, gridColor } = getThemeColors();
                            return {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: title,
                                        data: dataByMethod[title] || [],
                                        backgroundColor: colors[title] + '88',
                                        borderColor: colors[title],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: { ticks: { color: textColor }, grid: { color: gridColor } },
                                        y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }
                                    },
                                    plugins: {
                                        legend: { display: false, labels: { color: textColor } },
                                        tooltip: { titleColor: textColor, bodyColor: textColor }
                                    }
                                }
                            };
                        };

                        const charts = {};
                        const makeChart = (id, title) => {
                            const el = document.getElementById(id)?.getContext('2d');
                            if (!el) return;
                            charts[id] = new Chart(el, makeOptions(title));
                        };
                        makeChart('chartCash', 'Cash');
                        makeChart('chartMtn', 'MTN MoMo');
                        makeChart('chartAirtel', 'Airtel Money');
                        makeChart('chartBank', 'Bank');

                        const applyThemeToCharts = () => {
                            const { textColor, gridColor } = getThemeColors();
                            Object.values(charts).forEach(ch => {
                                ch.options.scales.x.ticks.color = textColor;
                                ch.options.scales.y.ticks.color = textColor;
                                ch.options.scales.x.grid.color = gridColor;
                                ch.options.scales.y.grid.color = gridColor;
                                if (ch.options.plugins && ch.options.plugins.legend && ch.options.plugins.legend.labels) {
                                    ch.options.plugins.legend.labels.color = textColor;
                                }
                                if (ch.options.plugins && ch.options.plugins.tooltip) {
                                    ch.options.plugins.tooltip.titleColor = textColor;
                                    ch.options.plugins.tooltip.bodyColor = textColor;
                                }
                                ch.update();
                            });
                        };

                        const mo = new MutationObserver(applyThemeToCharts);
                        mo.observe(document.body, { attributes: true, attributeFilter: ['class'] });
                        window.addEventListener('storage', applyThemeToCharts);
                    });
                    </script>
                </div>
            </div>
        </div>
        <!-- Sales Table Tab -->
        <div class="tab-pane fade show active" id="sales-table" role="tabpanel" aria-labelledby="sales-tab">
            <div class="card mb-4 chart-card"  style="border-left: 4px solid teal;">
                <div class="card-header bg-light text-black d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span class="fw-bold title-card"><i class="fa-solid fa-receipt"></i> Recent Sales</span>
                    <form method="GET" class="d-flex align-items-center flex-wrap gap-2" style="gap:1rem;">
                        <label class="fw-bold me-2">From:</label>
                        <input type="date" name="date_from" class="form-select me-2" value="<?= htmlspecialchars($date_from) ?>" style="width:150px;">
                        <label class="fw-bold me-2">To:</label>
                        <input type="date" name="date_to" class="form-select me-2" value="<?= htmlspecialchars($date_to) ?>" style="width:150px;">
                        <?php if ($user_role !== 'staff'): ?>
                        <label class="fw-bold me-2">Branch:</label>
                        <select name="branch" class="form-select me-2" onchange="this.form.submit()" style="width:180px;">
                            <option value="">-- All Branches --</option>
                            <?php
                            $branches = $conn->query("SELECT id, name FROM branch");
                            while ($b = $branches->fetch_assoc()):
                                $selected = ($selected_branch == $b['id']) ? 'selected' : '';
                                echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                            endwhile;
                            ?>
                        </select>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary ms-2">Filter</button>
                    </form>
                </div>
                <div class="card-body table-responsive">
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <?php if ($user_role !== 'staff' && empty($selected_branch)) echo "<th>Branch</th>"; ?>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Total Price</th>
                                    <th>Payment Method</th>
                                    <th>Sold At</th>
                                    <th>Sold By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = $offset + 1;
                                while ($row = $sales->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <?php if ($user_role !== 'staff' && empty($selected_branch)) echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>"; ?>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($row['product-name']) ?></span></td>
                                        <td><?= $row['quantity'] ?></td>
                                        <td><span class="fw-bold text-success">UGX<?= number_format($row['amount'], 2) ?></span></td>
                                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                        <td><small class="text-muted"><?= $row['date'] ?></small></td>
                                        <td><?= htmlspecialchars($row['sold-by']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($sales->num_rows === 0): ?>
                                    <tr><td colspan="<?= ($user_role !== 'staff' && empty($selected_branch)) ? 8 : 7 ?>" class="text-center text-muted">No sales found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-3">
                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $p ?><?= ($selected_branch ? '&branch=' . $selected_branch : '') ?><?= ($date_from ? '&date_from=' . $date_from : '') ?><?= ($date_to ? '&date_to=' . $date_to : '') ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    <!-- Total Sales Sum -->
                    <div class="mt-4 text-end">
                        <h5 class="fw-bold">Total Sales Value: <span class="text-success">$<?= number_format($total_sales_sum, 2) ?></span></h5>
                    </div>
                </div>
            </div>
        </div>
        <!-- Debtors Table Tab -->
        <div class="tab-pane fade" id="debtors-table" role="tabpanel" aria-labelledby="debtors-tab">
            <div class="card mb-4 chart-card"  style="border-left: 4px solid teal;">
                <div class="card-header bg-light text-black fw-bold d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span><i class="fa-solid fa-user-clock"></i> Debtors</span>
                    <!-- Generate Report Button -->
                    <button type="button" class="btn btn-success ms-3 d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('debtors')">
                        <i class="fa fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-success ms-3 d-none d-md-inline-flex" onclick="openReportGen('debtors')">
                        <i class="fa fa-file-pdf"></i> Generate Report
                    </button>
                </div>
                <div class="card-body">
                    <!-- NEW: Sub-tabs for Shop Debtors and Customer Debtors -->
                    <ul class="nav nav-pills mb-3" id="debtorSubTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="shop-debtors-tab" data-bs-toggle="tab" data-bs-target="#shop-debtors" type="button" role="tab">
                                Shop Debtors
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="customer-debtors-tab" data-bs-toggle="tab" data-bs-target="#customer-debtors" type="button" role="tab">
                                Customer Debtors
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="debtorSubTabsContent">
                        <!-- Shop Debtors Sub-tab -->
                        <div class="tab-pane fade show active" id="shop-debtors" role="tabpanel">
                            <div class="table-responsive">
                                <div class="transactions-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Debtor Name</th>
                                                <th>Debtor Email</th>
                                                <th>Item Taken</th>
                                                <th>Quantity Taken</th>
                                                <th>Payment Method</th>
                                                <th>Amount Paid</th>
                                                <th>Balance</th>
                                                <th>Paid Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($debtors_result && $debtors_result->num_rows > 0): ?>
                                                <?php while ($debtor = $debtors_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?= date("M d, Y H:i", strtotime($debtor['created_at'])); ?></td>
                                                        <td><?= htmlspecialchars($debtor['debtor_name']); ?></td>
                                                        <td><?= htmlspecialchars($debtor['debtor_email']); ?></td>
                                                        <td><?= htmlspecialchars($debtor['item_taken'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($debtor['quantity_taken'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($debtor['payment_method'] ?? '-') ?></td>
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
                                                            <button class="btn btn-primary btn-sm btn-pay-debtor"
                                                                data-id="<?= $debtor['id'] ?>"
                                                                data-balance="<?= htmlspecialchars($debtor['balance'] ?? 0) ?>"
                                                                data-name="<?= htmlspecialchars($debtor['debtor_name']) ?>"
                                                                data-pm="<?= htmlspecialchars($debtor['payment_method'] ?? '') ?>">
                                                                Pay
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center text-muted">No shop debtors recorded yet.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Debtors Sub-tab -->
                        <div class="tab-pane fade" id="customer-debtors" role="tabpanel">
                            <div class="table-responsive">
                                <div class="transactions-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Date of Take</th>
                                                <th>Time of Take</th>
                                                <th>Invoice No.</th>
                                                <th>Debtor Name</th>
                                                <th>Email</th>
                                                <th>Contact</th>
                                                <th>Products Taken</th>
                                                <th>Total Quantity</th>
                                                <th>Unit Prices</th>
                                                <th>Payment Method</th>
                                                <th>Amount Paid</th>
                                                <th>Balance</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($customer_debtors_result && $customer_debtors_result->num_rows > 0): ?>
                                                <?php while ($cd = $customer_debtors_result->fetch_assoc()): ?>
                                                    <?php
                                                    // Parse products_bought JSON
                                                    $products_data = json_decode($cd['products_bought'] ?? '[]', true);
                                                    $products_display = '';
                                                    $total_qty = 0;
                                                    $unit_prices = [];
                                                    
                                                    if (is_array($products_data)) {
                                                        foreach ($products_data as $item) {
                                                            $name = $item['name'] ?? $item['product'] ?? '';
                                                            $qty = intval($item['quantity'] ?? $item['qty'] ?? 0);
                                                            $price = floatval($item['price'] ?? 0);
                                                            
                                                            $products_display .= htmlspecialchars($name) . ' x' . $qty . '<br>';
                                                            $total_qty += $qty;
                                                            $unit_prices[] = 'UGX ' . number_format($price, 2);
                                                        }
                                                    } else {
                                                        $products_display = htmlspecialchars($cd['products_bought'] ?? '-');
                                                    }
                                                
                                                    $unit_prices_display = implode('<br>', $unit_prices);
                                                    $date_obj = new DateTime($cd['date_time']);
                                                    ?>
                                                    <tr>
                                                        <td><?= $date_obj->format('M d, Y'); ?></td>
                                                        <td><?= $date_obj->format('H:i'); ?></td>
                                                        <td><?= htmlspecialchars($cd['invoice_receipt_no'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($cd['debtor_name']); ?></td>
                                                        <td><?= htmlspecialchars($cd['debtor_email'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($cd['debtor_contact'] ?? '-'); ?></td>
                                                        <td><?= $products_display ?: '-'; ?></td>
                                                        <td class="text-center"><?= $total_qty; ?></td>
                                                        <td><?= $unit_prices_display ?: '-'; ?></td>
                                                        <td><?= htmlspecialchars('Customer File'); ?></td>
                                                        <td>UGX <?= number_format($cd['amount_paid'] ?? 0, 2); ?></td>
                                                        <td>UGX <?= number_format($cd['balance'] ?? 0, 2); ?></td>
                                                        <td>
                                                            <button class="btn btn-primary btn-sm btn-pay-customer-debtor"
                                                                data-id="<?= $cd['id'] ?>"
                                                                data-balance="<?= htmlspecialchars($cd['balance'] ?? 0) ?>"
                                                                data-name="<?= htmlspecialchars($cd['debtor_name']) ?>">
                                                                Pay
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="13" class="text-center text-muted">No customer debtors found.</td>
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
        </div>
        <!-- NEW: Product Summary Tab -->
        <div class="tab-pane fade" id="product-summary" role="tabpanel" aria-labelledby="product-summary-tab">
            <div class="card mb-4 chart-card"  style="border-left: 4px solid teal;">
                <div class="card-header bg-light text-black fw-bold" style="border-radius:12px 12px 0 0;">
                    Product Summary (Items Sold Per Day)
                    <!-- Generate Report Button -->
                    <button type="button" class="btn btn-success ms-3 d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('product_summary')">
                        <i class="fa fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-success ms-3 d-none d-md-inline-flex" onclick="openReportGen('product_summary')">
                        <i class="fa fa-file-pdf"></i> Generate Report
                    </button>
                </div>
                <div class="card-body table-responsive">
                    <!-- Product Summary Filters -->
                    <form method="GET" class="d-flex align-items-center flex-wrap gap-2 mb-3 product-summary-filter" style="gap:1rem;">
                        <input type="hidden" name="tab" value="product-summary">
                        <label class="fw-bold me-2">From:</label>
                        <input type="date" name="ps_date_from" class="form-select me-2" value="<?= htmlspecialchars($ps_date_from) ?>" style="width:150px;">
                        <label class="fw-bold me-2">To:</label>
                        <input type="date" name="ps_date_to" class="form-select me-2" value="<?= htmlspecialchars($ps_date_to) ?>" style="width:150px;">
                        <?php if ($user_role !== 'staff'): ?>
                        <label class="fw-bold me-2">Branch:</label>
                        <select name="ps_branch" class="form-select me-2" style="width:180px;">
                            <option value="">-- All Branches --</option>
                            <?php $branches_ps = $conn->query("SELECT id, name FROM branch"); while ($b = $branches_ps->fetch_assoc()): $sel = ($ps_branch == $b['id']) ? 'selected' : ''; ?>
                                <option value="<?= $b['id'] ?>" <?= $sel ?>><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary ms-2">Filter</button>
                    </form>
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Product</th>
                                    <th>Items Sold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($product_summary_result && $product_summary_result->num_rows > 0):
                                    $prev_date = null;
                                    $prev_branch = null;
                                    while ($row = $product_summary_result->fetch_assoc()):
                                        $show_date = ($prev_date !== $row['sale_date']);
                                        $show_branch = ($prev_branch !== $row['branch_name']) || $show_date;
                                ?>
                                    <tr>
                                        <td><?= $show_date ? htmlspecialchars($row['sale_date']) : '' ?></td>
                                        <td><?= $show_branch ? htmlspecialchars($row['branch_name']) : '' ?></td>
                                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                                        <td><?= htmlspecialchars($row['items_sold']) ?></td>
                                    </tr>
                                <?php
                                        $prev_date = $row['sale_date'];
                                        $prev_branch = $row['branch_name'];
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No product summary data found.</td>
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

<!-- Modal for report generation -->
<div class="modal fade" id="reportGenModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="reportGenForm">
      <div class="modal-header">
        <h5 class="modal-title" id="reportGenModalTitle">Generate Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">From</label>
          <input type="date" name="date_from" id="report_date_from" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">To</label>
          <input type="date" name="date_to" id="report_date_to" class="form-control" required>
        </div>
        <div class="col-md-12">
          <label class="form-label">Branch</label>
          <select name="branch" id="report_branch" class="form-select">
            <option value="">All Branches</option>
            <?php
            $branches = $conn->query("SELECT id, name FROM branch");
            while ($b = $branches->fetch_assoc()):
                echo "<option value='{$b['id']}'>" . htmlspecialchars($b['name']) . "</option>";
            endwhile;
            ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Generate & Print</button>
      </div>
    </form>
  </div>
</div>

<!-- Pay Debtor Modal -->
<div class="modal fade" id="payDebtorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary-color);color:#fff;">
        <h5 class="modal-title">Record Debtor Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="pdDebtorLabel" class="mb-2 fw-semibold"></p>
        <p>Outstanding Balance: <strong id="pdBalanceText">UGX 0.00</strong></p>
        <input type="hidden" id="pdDebtorId" value="">
        <!-- NEW: repayment method select -->
        <div class="mb-3">
          <label class="form-label">Payment Method</label>
          <select id="pdMethod" class="form-select">
            <option value="Cash">Cash</option>
            <option value="MTN MoMo">MTN MoMo</option>
            <option value="Airtel Money">Airtel Money</option>
            <option value="Bank">Bank</option>
            <option value="Customer File">Customer File</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Amount Paid (UGX)</label>
          <input type="number" id="pdAmount" class="form-control" min="0" step="0.01" placeholder="Enter amount">
        </div>
        <div id="pdMsg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="pdConfirmBtn" class="btn btn-primary">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
/* Ensure Bootstrap is loaded, then init modal logic.
   This avoids "bootstrap is not defined" when our script runs before the Bootstrap bundle. */
(function() {
  function ensureBootstrap(cb) {
    if (window.bootstrap) return cb();
    const src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
    // If script already injected, poll until available
    if (document.querySelector('script[src="'+src+'"]')) {
      const t = setInterval(() => { if (window.bootstrap) { clearInterval(t); cb(); } }, 50);
      return;
    }
    const s = document.createElement('script');
    s.src = src;
    s.onload = cb;
    s.onerror = function() { console.error('Failed to load Bootstrap bundle.'); cb(); };
    document.head.appendChild(s);
  }

  function initPayModal() {
    const payButtons = document.querySelectorAll('.btn-pay-debtor');
    if (!payButtons.length) return;

    const payModalEl = document.getElementById('payDebtorModal');
    const payModal = new bootstrap.Modal(payModalEl);
    const pdDebtorLabel = document.getElementById('pdDebtorLabel');
    const pdBalanceText = document.getElementById('pdBalanceText');
    const pdDebtorId = document.getElementById('pdDebtorId');
    const pdAmount = document.getElementById('pdAmount');
    const pdMethod = document.getElementById('pdMethod'); // NEW
    const pdMsg = document.getElementById('pdMsg');
    const pdConfirmBtn = document.getElementById('pdConfirmBtn');

    payButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const balance = parseFloat(btn.getAttribute('data-balance') || 0);
        const name = btn.getAttribute('data-name') || 'Debtor';
        const origPm = btn.getAttribute('data-pm') || ''; // NEW
        pdDebtorId.value = id;
        pdAmount.value = '';
        pdDebtorLabel.textContent = `Debtor: ${name}`;
        pdBalanceText.textContent = 'UGX ' + balance.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        pdMsg.innerHTML = '';
        payModalEl.dataset.outstanding = String(balance);
        if (String(origPm).toLowerCase() === 'customer file') {
          pdMethod.value = 'Customer File';
          pdMethod.disabled = true;
        } else {
          pdMethod.disabled = false;
          pdMethod.value = 'Cash';
        }
        payModal.show();
      });
    });

    pdConfirmBtn.addEventListener('click', async () => {
      const id = pdDebtorId.value;
      let amount = parseFloat(pdAmount.value || 0);
      const outstanding = parseFloat(payModalEl.dataset.outstanding || 0);
      const pm = (pdMethod?.value || 'Cash'); // NEW

      pdMsg.innerHTML = '';
      if (!id) { pdMsg.innerHTML = '<div class="alert alert-warning">Invalid debtor selected.</div>'; return; }
      if (!amount || amount <= 0) { pdMsg.innerHTML = '<div class="alert alert-warning">Enter a valid amount.</div>'; return; }
      if (amount > outstanding) { pdMsg.innerHTML = '<div class="alert alert-warning">Amount cannot exceed outstanding balance.</div>'; return; }

      pdConfirmBtn.disabled = true;
      pdConfirmBtn.textContent = 'Processing...';
      try {
        const res = await fetch(location.pathname, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `pay_debtor=1&id=${encodeURIComponent(id)}&amount=${encodeURIComponent(amount)}&pm=${encodeURIComponent(pm)}`
        });

        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch (parseErr) {
          console.error('Invalid JSON response from server:', text);
          pdMsg.innerHTML = '<div class="alert alert-danger">Server returned an invalid response. See console for details.</div>';
          pdConfirmBtn.disabled = false;
          pdConfirmBtn.textContent = 'OK';
          return;
        }

        pdConfirmBtn.disabled = false;
        pdConfirmBtn.textContent = 'OK';

        if (data && data.reload) {
          payModal.hide();
          window.location.reload();
        } else {
          pdMsg.innerHTML = '<div class="alert alert-info">' + (data.message || 'Payment recorded') + '</div>';
        }
      } catch (err) {
        console.error('Request error:', err);
        pdConfirmBtn.disabled = false;
        pdConfirmBtn.textContent = 'OK';
        pdMsg.innerHTML = '<div class="alert alert-danger">Error processing payment. Check console.</div>';
      }
    });
  }

  // Run: ensure bootstrap then init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ensureBootstrap(initPayModal));
  } else {
    ensureBootstrap(initPayModal);
  }
})();

function openReportGen(type) {
    let title = 'Generate Report';
    if (type === 'payment_analysis') title = 'Generate Payment Analysis Report';
    else if (type === 'debtors') title = 'Generate Debtors Report';
    else if (type === 'product_summary') title = 'Generate Product Summary Report';
    document.getElementById('reportGenModalTitle').textContent = title;
    document.getElementById('reportGenForm').dataset.reportType = type;
    new bootstrap.Modal(document.getElementById('reportGenModal')).show();
}

document.getElementById('reportGenForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const type = this.dataset.reportType || 'sales';
    const date_from = document.getElementById('report_date_from').value;
    const date_to = document.getElementById('report_date_to').value;
    const branch = document.getElementById('report_branch').value;
    const url = `reports_generator.php?type=${encodeURIComponent(type)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&branch=${encodeURIComponent(branch)}`;
    window.open(url, '_blank');
    bootstrap.Modal.getInstance(document.getElementById('reportGenModal')).hide();
});

// NEW: Handle Customer Debtor payment button clicks
document.querySelectorAll('.btn-pay-customer-debtor').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const balance = parseFloat(btn.getAttribute('data-balance') || 0);
        const name = btn.getAttribute('data-name') || 'Customer';
        
        // Use same modal as shop debtors
        const payModalEl = document.getElementById('payDebtorModal');
        const payModal = new bootstrap.Modal(payModalEl);
        
        document.getElementById('pdDebtorLabel').textContent = `Customer: ${name}`;
        document.getElementById('pdBalanceText').textContent = 'UGX ' + balance.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('pdDebtorId').value = id;
        document.getElementById('pdAmount').value = '';
        document.getElementById('pdMsg').innerHTML = '';
        payModalEl.dataset.outstanding = String(balance);
        payModalEl.dataset.isCustomerDebtor = '1'; // flag to differentiate
        
        // Hide payment method select for customer debtors
        document.getElementById('pdMethod').closest('.mb-3').style.display = 'none';
        
        payModal.show();
    });
});

// Modify existing pay confirm button to handle both types
document.getElementById('pdConfirmBtn').addEventListener('click', async () => {
    const payModalEl = document.getElementById('payDebtorModal');
    const isCustomerDebtor = payModalEl.dataset.isCustomerDebtor === '1';
    const id = document.getElementById('pdDebtorId').value;
    let amount = parseFloat(document.getElementById('pdAmount').value || 0);
    const outstanding = parseFloat(payModalEl.dataset.outstanding || 0);
    const pm = document.getElementById('pdMethod')?.value || 'Cash';

    const pdMsg = document.getElementById('pdMsg');
    const pdConfirmBtn = document.getElementById('pdConfirmBtn');

    pdMsg.innerHTML = '';
    if (!id) { pdMsg.innerHTML = '<div class="alert alert-warning">Invalid selection.</div>'; return; }
    if (!amount || amount <= 0) { pdMsg.innerHTML = '<div class="alert alert-warning">Enter a valid amount.</div>'; return; }
    if (amount > outstanding) { pdMsg.innerHTML = '<div class="alert alert-warning">Amount cannot exceed balance.</div>'; return; }

    pdConfirmBtn.disabled = true;
    pdConfirmBtn.textContent = 'Processing...';
    
    try {
        const endpoint = isCustomerDebtor ? 'pay_customer_debtor' : 'pay_debtor';
        const body = `${endpoint}=1&id=${encodeURIComponent(id)}&amount=${encodeURIComponent(amount)}${!isCustomerDebtor ? '&pm='+encodeURIComponent(pm) : ''}`;
        
        const res = await fetch(location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        });

        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch (parseErr) {
            console.error('Invalid JSON response:', text);
            pdMsg.innerHTML = '<div class="alert alert-danger">Server error. See console.</div>';
            pdConfirmBtn.disabled = false;
            pdConfirmBtn.textContent = 'OK';
            return;
        }

        pdConfirmBtn.disabled = false;
        pdConfirmBtn.textContent = 'OK';

        if (data && data.reload) {
            bootstrap.Modal.getInstance(payModalEl).hide();
            window.location.reload();
        } else {
            pdMsg.innerHTML = '<div class="alert alert-info">' + (data.message || 'Payment recorded') + '</div>';
        }
    } catch (err) {
        console.error('Request error:', err);
        pdConfirmBtn.disabled = false;
        pdConfirmBtn.textContent = 'OK';
        pdMsg.innerHTML = '<div class="alert alert-danger">Error processing payment.</div>';
    }
    
    // Reset modal state
    delete payModalEl.dataset.isCustomerDebtor;
    document.getElementById('pdMethod').closest('.mb-3').style.display = '';
});
</script>

<style>
/* ...existing code... */

/* Sub-tabs styling */
#debtorSubTabs .nav-link {
    border-radius: 8px;
    padding: 0.5rem 1.2rem;
    margin-right: 0.5rem;
    border: 2px solid transparent;
    color: var(--primary-color);
    font-weight: 500;
    transition: all 0.2s;
}

#debtorSubTabs .nav-link:hover {
    background-color: rgba(26, 188, 156, 0.1);
}

#debtorSubTabs .nav-link.active {
    background-color: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

body.dark-mode #debtorSubTabs .nav-link {
    color: #1abc9c;
}

body.dark-mode #debtorSubTabs .nav-link.active {
    background-color: #1abc9c;
    color: #fff;
}
</style>
