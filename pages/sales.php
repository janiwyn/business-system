<?php
// --- STEP 1: Start session and include ONLY db.php (NO HTML OUTPUT) ---
session_start();
include '../includes/db.php';
include_once __DIR__ . '/../includes/receipt_helper.php'; // <-- Include helper

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

    $dq = $conn->prepare("SELECT id, customer_id, invoice_no, balance, amount_paid, payment_method, item_taken, quantity_taken, products_json, branch_id FROM debtors WHERE id=? LIMIT 1");
    $dq->bind_param("i", $debtor_id);
    $dq->execute();
    $debtor = $dq->get_result()->fetch_assoc();
    $dq->close();
    
    if (!$debtor) { 
        echo json_encode(['success'=>false,'message'=>'Debtor not found']); 
        exit; 
    }

    $remaining = floatval($debtor['balance']);
    $debtor_branch_id = intval($debtor['branch_id'] ?? $user_branch);
    
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
        // CHANGED: Use sequential receipt number
        $receiptNo = generateReceiptNumber($conn, 'RP');
    } catch (Throwable $e) { 
        $receiptNo = 'RP-' . date('YmdHis');
    }
    $now = date('Y-m-d H:i:s');
    $cust_id = intval($debtor['customer_id'] ?? 0);
    $pm_to_use = $pm_in ?: 'Cash';

    // Check if this is FULL payment (balance will be zero after this payment)
    $is_full_payment = ($pay_amt >= $remaining);

    try {
        if ($is_full_payment) {
            $products_json = $debtor['products_json'] ?? null;
            
            // Get debtor created_at date for query (FIXED: get from query result)
            $dq2 = $conn->prepare("SELECT created_at FROM debtors WHERE id=? LIMIT 1");
            $dq2->bind_param("i", $debtor_id);
            $dq2->execute();
            $debtor_date_row = $dq2->get_result()->fetch_assoc();
            $dq2->close();
            
            $original_debt_date = date('Y-m-d', strtotime($debtor_date_row['created_at'] ?? 'now'));
            
            if ($products_json) {
                $products_data = json_decode($products_json, true);
                
                if (is_array($products_data) && count($products_data) > 0) {
                    // Calculate totals for grouped sale
                    $total_quantity = 0;
                    $total_amount = 0;
                    $total_cost = 0;
                    $total_profit = 0;

                    foreach ($products_data as $item) {
                        $product_id = intval($item['id']);
                        $qty = intval($item['quantity']);
                        $price = floatval($item['price']);
                        
                        // Fetch product details for cost/profit calculation
                        $pstmt = $conn->prepare("SELECT `buying-price` FROM products WHERE id = ? AND `branch-id` = ? LIMIT 1");
                        $pstmt->bind_param("ii", $product_id, $debtor_branch_id);
                        $pstmt->execute();
                        $prod = $pstmt->get_result()->fetch_assoc();
                        $pstmt->close();
                        
                        $buying_price = $prod ? floatval($prod['buying-price']) : 0;
                        
                        $item_total = $price * $qty;
                        $item_cost = $buying_price * $qty;
                        
                        $total_quantity += $qty;
                        $total_amount += $item_total;
                        $total_cost += $item_cost;
                        $total_profit += ($item_total - $item_cost);
                    }

                    // Insert SINGLE grouped sales record (9 columns = 9 bind params)
                    // Columns: product-id, branch-id, quantity, amount, sold-by, cost-price, total_profits, date, payment_method, products_json
                    // FIX: CORRECT THE TYPE STRING - COUNT CAREFULLY
                    // INSERT INTO sales (product-id, branch-id, quantity, amount, sold-by, cost-price, total_profits, date, payment_method, receipt_no, products_json)
                    // VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    // Parameters: 1=branch_id(i), 2=total_quantity(i), 3=total_amount(d), 4=uid(i), 5=total_cost(d), 6=total_profit(d), 7=now(s), 8=pm_to_use(s), 9=receiptNo(s), 10=products_json(s)
                    // COUNT: 10 parameters = 10 type chars
                    $sstmt = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method,receipt_no,products_json,original_debt_date) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    // TYPE STRING: i i d i d d s s s s s = 11 characters
                    $sstmt->bind_param("iididdsssss", $debtor_branch_id, $total_quantity, $total_amount, $uid, $total_cost, $total_profit, $now, $pm_to_use, $receiptNo, $products_json, $original_debt_date);
                    if (!$sstmt->execute()) { $ok = false; }
                    $sstmt->close();

                    // Update profits (once for grouped sale)
                    if ($ok) {
                        $today = date('Y-m-d');
                        $pf = $conn->prepare("SELECT total, expenses FROM profits WHERE date = ? AND `branch-id` = ?");
                        $pf->bind_param("si", $today, $debtor_branch_id);
                        $pf->execute();
                        $pr = $pf->get_result()->fetch_assoc();
                        $pf->close();
                        
                        if ($pr) {
                            $new_total = ($pr['total'] ?? 0) + $total_profit;
                            $expenses = $pr['expenses'] ?? 0;
                            $net = $new_total - $expenses;
                            $up = $conn->prepare("UPDATE profits SET total = ?, `net-profits` = ? WHERE date = ? AND `branch-id` = ?");
                            $up->bind_param("ddsi", $new_total, $net, $today, $debtor_branch_id);
                            $up->execute();
                            $up->close();
                        } else {
                            $expenses = 0;
                            $new_total = $total_profit;
                            $net = $total_profit;
                            $ins = $conn->prepare("INSERT INTO profits (`branch-id`, total, `net-profits`, expenses, date) VALUES (?, ?, ?, ?, ?)");
                            $ins->bind_param("iddis", $debtor_branch_id, $new_total, $net, $expenses, $today);
                            $ins->execute();
                            $ins->close();
                        }
                    }
                } else {
                    // Fallback: single generic sale (5 columns after VALUES)
                    // INSERT INTO sales (product-id, branch-id, quantity, amount, sold-by, cost-price, total-profits, date, payment_method, receipt_no)
                    // VALUES (0, ?, 0, ?, ?, 0, 0, ?, ?, ?)
                    // Parameters: 1=branch_id(i), 2=pay_amt(d), 3=uid(i), 4=now(s), 5=pm_to_use(s), 6=receiptNo(s)
                    // COUNT: 6 parameters = 6 type chars
                    $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method,receipt_no,original_debt_date) VALUES (0, ?, 0, ?, ?, 0, 0, ?, ?, ?, ?)");
                    // TYPE STRING: i d i s s s s = 7 characters
                    $insS->bind_param("idissss", $debtor_branch_id, $pay_amt, $uid, $now, $pm_to_use, $receiptNo, $original_debt_date);
                    if (!$insS->execute()) { $ok = false; }
                    $insS->close();
                }
            } else {
                // OLD LOGIC: Parse item_taken string (fallback for old debtors without products_json)
                $items = array_filter(array_map('trim', explode(',', $debtor['item_taken'] ?? '')));
                $debtor_quantity = intval($debtor['quantity_taken'] ?? 0);

                if (count($items) > 0 && $debtor_quantity > 0) {
                    // Distribute quantity across items
                    $qty_per_item = (count($items) > 1) ? 1 : $debtor_quantity;

                    // Insert sale record for EACH product
                    foreach ($items as $item_name) {
                        // Remove quantity suffix if present (e.g., "maize x2" -> "maize")
                        $item_name_clean = preg_replace('/\s*x\d+$/i', '', $item_name);
                        
                        // Find product by name and branch
                        $pstmt = $conn->prepare("SELECT id, `selling-price`, `buying-price` FROM products WHERE name = ? AND `branch-id` = ? LIMIT 1");
                        $pstmt->bind_param("si", $item_name_clean, $debtor_branch_id);
                        $pstmt->execute();
                        $prod = $pstmt->get_result()->fetch_assoc();
                        $pstmt->close();

                        if ($prod) {
                            $product_id = intval($prod['id']);
                            $selling_price = floatval($prod['selling-price']);
                            $buying_price = floatval($prod['buying-price']);
                            $item_qty = max(1, $qty_per_item);
                            $item_amount = $selling_price * $item_qty;
                            $cost = $buying_price * $item_qty;
                            $profit = $item_amount - $cost;

                            // INSERT INTO sales (product-id, branch-id, quantity, amount, sold-by, cost-price, total-profits, date, payment_method, receipt_no)
                            // VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            // Parameters: 1=product_id(i), 2=branch_id(i), 3=item_qty(i), 4=item_amount(d), 5=uid(i), 6=cost(d), 7=profit(d), 8=now(s), 9=pm_to_use(s), 10=receiptNo(s)
                            // COUNT: 10 parameters = 10 type chars
                            $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method,receipt_no,original_debt_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            // TYPE STRING: i i i d i d d s s s s = 11 characters
                            $insS->bind_param("iiididdssss", $product_id, $debtor_branch_id, $item_qty, $item_amount, $uid, $cost, $profit, $now, $pm_to_use, $receiptNo, $original_debt_date);
                            if (!$insS->execute()) { $ok = false; }
                            $insS->close();
                        } else {
                            // INSERT has 8 ? placeholders, needs 8 variables
                            $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method,receipt_no,original_debt_date) VALUES (0, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?)");
                            $insS->bind_param("iidissss", $debtor_branch_id, $item_qty, $item_amount, $uid, $now, $pm_to_use, $receiptNo, $original_debt_date);
                            if (!$insS->execute()) { $ok = false; }
                            $insS->close();
                        }
                    }
                } else {
                    // INSERT has 7 ? placeholders, needs 7 variables
                    $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method,receipt_no,original_debt_date) VALUES (0, ?, 0, ?, ?, 0, 0, ?, ?, ?, ?)");
                    $insS->bind_param("idissss", $debtor_branch_id, $pay_amt, $uid, $now, $pm_to_use, $receiptNo, $original_debt_date);
                    if (!$insS->execute()) { $ok = false; }
                    $insS->close();
                }
            }

            // Delete debtor record (full payment)
            if ($ok) {
                $dstmt = $conn->prepare("DELETE FROM debtors WHERE id = ?");
                $dstmt->bind_param("i", $debtor_id);
                if (!$dstmt->execute()) { $ok = false; }
                $dstmt->close();
            }
        } else {
            // Partial payment: update debtor balance
            $new_balance = $remaining - $pay_amt;
            $new_paid = floatval($debtor['amount_paid']) + $pay_amt;
            $ustmt = $conn->prepare("UPDATE debtors SET balance = ?, amount_paid = ? WHERE id = ?");
            $ustmt->bind_param("ddi", $new_balance, $new_paid, $debtor_id);
            if (!$ustmt->execute()) { $ok = false; }
            $ustmt->close();
        }

        if ($ok) { 
            $conn->commit(); 
            echo json_encode(['success'=>true,'reload'=>true]); 
        } else {
            $conn->rollback();
            echo json_encode(['success'=>false,'message'=>'Failed to process payment']);
        }
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
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
        // CHANGED: Use sequential receipt number
        $receiptNo = generateReceiptNumber($conn, 'RP');
    } catch (Throwable $e) { 
        $receiptNo = 'RP-' . date('YmdHis');
    }
    $now = date('Y-m-d H:i:s');
    $sold_by = $_SESSION['username'] ?? 'staff';

    // CHANGED: Store original debt creation date
    $original_debt_date = date('Y-m-d', strtotime($trans['date_time'] ?? 'now'));

    if ($pay_amt >= $amount_credited) {
        $products_data = json_decode($products_bought, true);
        
        if (is_array($products_data) && count($products_data) > 0) {
            // Calculate totals for grouped sale
            $total_quantity = 0;
            $total_amount = 0;

            foreach ($products_data as $item) {
                $qty = intval($item['quantity'] ?? $item['qty'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $total_quantity += $qty;
                $total_amount += ($price * $qty);
            }

            // Insert SINGLE grouped sales record with products_json
            $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method,customer_id,receipt_no,products_json,original_debt_date) VALUES (0, ?, ?, ?, ?, 0, 0, NOW(), 'Customer File', ?, ?, ?, ?)");
            $insS->bind_param("iidiisss", $user_branch, $total_quantity, $total_amount, $uid, $customer_id, $receiptNo, $products_bought, $original_debt_date);
            if (!$insS->execute()) { $ok = false; }
            $insS->close();
        } else {
            // Fallback: single sale record with product-id = 0
            $insS = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method,customer_id,receipt_no,original_debt_date) VALUES (0, ?, 0, ?, ?, 0, 0, NOW(), 'Customer File', ?, ?)");
            $insS->bind_param("idiis", $user_branch, $pay_amt, $uid, $customer_id, $receiptNo, $original_debt_date);
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

// NEW: AJAX handler for setting due date (shop debtors)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_due_date'])) {
    header('Content-Type: application/json; charset=utf-8');
    $debtor_id = intval($_POST['debtor_id'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');
    
    if ($debtor_id <= 0 || !$due_date) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE debtors SET due_date = ? WHERE id = ?");
    $stmt->bind_param("si", $due_date, $debtor_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// NEW: AJAX handler for setting due date (customer debtors)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_customer_due_date'])) {
    header('Content-Type: application/json; charset=utf-8');
    $transaction_id = intval($_POST['transaction_id'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');
    
    if ($transaction_id <= 0 || !$due_date) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE customer_transactions SET due_date = ? WHERE id = ?");
    $stmt->bind_param("si", $due_date, $transaction_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
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
// NEW: ensure sales.products_json column exists
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS products_json TEXT NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE sales ADD COLUMN products_json TEXT NULL"); }
// NEW: ensure debtors.due_date column exists
$conn->query("ALTER TABLE debtors ADD COLUMN IF NOT EXISTS due_date DATE NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE debtors ADD COLUMN due_date DATE NULL"); }
// NEW: ensure customer_transactions.due_date column exists
$conn->query("ALTER TABLE customer_transactions ADD COLUMN IF NOT EXISTS due_date DATE NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE customer_transactions ADD COLUMN due_date DATE NULL"); }
// NEW: ensure sales.original_debt_date column exists
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS original_debt_date DATE NULL");
if ($conn->errno) { @$conn->query("ALTER TABLE sales ADD COLUMN original_debt_date DATE NULL"); }

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

// Fetch sales for current page (MODIFIED to show products from JSON)
$sales_query = "
    SELECT sales.id, 
           sales.`product-id`,
           COALESCE(products.name, CASE 
               WHEN sales.`product-id` = 0 AND sales.products_json IS NOT NULL THEN 'Multiple Products'
               WHEN sales.`product-id` = 0 THEN 'Debtor Repayment'
               ELSE 'Unknown'
           END) AS `product-name`, 
           sales.quantity, 
           sales.amount, 
           sales.`sold-by`, 
           sales.date, 
           branch.name AS branch_name, 
           sales.payment_method,
           sales.receipt_no,
           sales.products_json
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

// Fetch debtors for the table (ADD due_date column)
$debtors_result = $conn->query("
    SELECT id, debtor_name, debtor_email, debtor_contact, item_taken, quantity_taken, payment_method, amount_paid, balance, is_paid, created_at, invoice_no, products_json, due_date
    FROM debtors
    $debtorWhereClause
    ORDER BY created_at DESC
    LIMIT 100
");

// --- NEW: Fetch Customer Debtors (from customer_transactions with status='debtor') ---
$customer_debtor_where = [];
if ($user_role === 'staff') {
    // Staff can only see their branch customer debtors
    $customer_debtor_where[] = "ct.branch_id = $user_branch";
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
        ct.status,
        ct.due_date
    FROM customer_transactions ct
    JOIN customers c ON ct.customer_id = c.id
    WHERE ct.status = 'debtor'
    $custDebtorWhereClause
    ORDER BY ct.date_time DESC
    LIMIT 100
");


// --- NEW: Fetch Product Summary data (PHP-side expansion for compatibility) ---
$product_summary_where = [];
if ($user_role === 'staff') {
    $product_summary_where[] = "s.`branch-id` = $user_branch";
} elseif ($ps_branch) {
    $product_summary_where[] = "s.`branch-id` = " . intval($ps_branch);
}
if ($ps_date_from) {
    $product_summary_where[] = "DATE(s.date) >= '" . $conn->real_escape_string($ps_date_from) . "'";
}
if ($ps_date_to) {
    $product_summary_where[] = "DATE(s.date) <= '" . $conn->real_escape_string($ps_date_to) . "'";
}
$psWhereSql = count($product_summary_where) ? "WHERE " . implode(' AND ', $product_summary_where) : "";

// Preload products map (id -> selling-price, name) for accurate unit prices
$products_map_by_id = [];
$products_map_by_name = [];
$prodRes = $conn->query("SELECT id, name, `selling-price` FROM products");
if ($prodRes) {
    while ($p = $prodRes->fetch_assoc()) {
        $products_map_by_id[intval($p['id'])] = $p;
        $products_map_by_name[strtolower(trim($p['name']))] = $p;
    }
    $prodRes->close();
}

// NEW STRUCTURE: track items_sold_full_pay and items_sold_debtors separately
$fetch_sql = "
    SELECT s.id, s.date, s.original_debt_date, s.`branch-id` AS branch_id, b.name AS branch_name,
           s.`product-id` AS product_id, p.name AS product_name, p.`selling-price` AS product_selling_price,
           s.quantity, s.amount, s.products_json, s.payment_method
    FROM sales s
    LEFT JOIN products p ON s.`product-id` = p.id
    LEFT JOIN branch b ON s.`branch-id` = b.id
    $psWhereSql
    ORDER BY s.date DESC
    LIMIT 5000
";
$sr = $conn->query($fetch_sql);

$product_summary_map = [];
if ($sr) {
    while ($srow = $sr->fetch_assoc()) {
        // CHANGED: Use original_debt_date if available, otherwise use sale date
        $sale_date = $srow['original_debt_date'] 
            ? $srow['original_debt_date'] 
            : date('Y-m-d', strtotime($srow['date']));
        
        $branch_id_key = intval($srow['branch_id']);
        $branch_name = $srow['branch_name'] ?? 'Unknown';
        $sale_amount = floatval($srow['amount']);
        $sale_qty = floatval($srow['quantity'] ?? 0);
        $product_id = intval($srow['product_id'] ?? 0);
        $payment_method = $srow['payment_method'] ?? 'Cash';

        // Determine if this is a full-pay or debtor sale (from sales table, not debtors table)
        // Sales in the sales table are considered "full-pay" (they've been recorded as paid)
        $is_full_pay = true; // All sales records are full payments

        if ($product_id > 0) {
            // Simple sale tied to a product row
            $prod = $products_map_by_id[$product_id] ?? null;
            $prod_name = $prod['name'] ?? ($srow['product_name'] ?? 'Unknown');
            $unit_price = ($prod && $prod['selling-price'] !== null && $prod['selling-price'] !== '') ? floatval($prod['selling-price']) : (($sale_qty>0) ? ($sale_amount / $sale_qty) : 0);

            $key = implode('|', [$sale_date, $branch_id_key, 'id_'.$product_id]);
            if (!isset($product_summary_map[$key])) {
                $product_summary_map[$key] = [
                    'sale_date' => $sale_date,
                    'branch_name' => $branch_name,
                    'product_name' => $prod_name,
                    'items_sold_full_pay' => 0,
                    'items_sold_debtors' => 0,
                    'total_items_sold' => 0,
                    'unit_price' => $unit_price,
                    'expected_amount' => 0.0,
                    'amount_received' => 0.0
                ];
            }
            
            // Add to full-pay (sales table entries are payments)
            $product_summary_map[$key]['items_sold_full_pay'] += $sale_qty;
            $product_summary_map[$key]['total_items_sold'] += $sale_qty;
            $product_summary_map[$key]['expected_amount'] += ($unit_price * $sale_qty);
            $product_summary_map[$key]['amount_received'] += $sale_amount;
        } else {
            // Grouped sale: expand products_json
            $pj = $srow['products_json'] ?? null;
            if (!$pj) {
                $key = implode('|', [$sale_date, $branch_id_key, 'multiple_products']);
                if (!isset($product_summary_map[$key])) {
                    $product_summary_map[$key] = [
                        'sale_date' => $sale_date,
                        'branch_name' => $branch_name,
                        'product_name' => 'Multiple Products',
                        'items_sold_full_pay' => 0,
                        'items_sold_debtors' => 0,
                        'total_items_sold' => 0,
                        'unit_price' => 0,
                        'expected_amount' => 0.0,
                        'amount_received' => 0.0
                    ];
                }
                $product_summary_map[$key]['items_sold_full_pay'] += $sale_qty;
                $product_summary_map[$key]['total_items_sold'] += $sale_qty;
                $product_summary_map[$key]['amount_received'] += $sale_amount;
                continue;
            }

            $items = json_decode($pj, true);
            if (!is_array($items) || count($items) === 0) {
                $key = implode('|', [$sale_date, $branch_id_key, 'multiple_products']);
                if (!isset($product_summary_map[$key])) {
                    $product_summary_map[$key] = [
                        'sale_date' => $sale_date,
                        'branch_name' => $branch_name,
                        'product_name' => 'Multiple Products',
                        'items_sold_full_pay' => 0,
                        'items_sold_debtors' => 0,
                        'total_items_sold' => 0,
                        'unit_price' => 0,
                        'expected_amount' => 0.0,
                        'amount_received' => 0.0
                    ];
                }
                $product_summary_map[$key]['items_sold_full_pay'] += $sale_qty;
                $product_summary_map[$key]['total_items_sold'] += $sale_qty;
                $product_summary_map[$key]['amount_received'] += $sale_amount;
                continue;
            }

            $group_expected_total = 0.0;
            $expanded = [];
            foreach ($items as $it) {
                $it_qty = floatval($it['quantity'] ?? ($it['qty'] ?? 0));
                $it_id = intval($it['id'] ?? 0);
                $it_name = trim($it['name'] ?? ($it['product'] ?? 'Unknown'));
                $prod_info = null;
                if ($it_id && isset($products_map_by_id[$it_id])) {
                    $prod_info = $products_map_by_id[$it_id];
                } elseif ($it_name && isset($products_map_by_name[strtolower($it_name)])) {
                    $prod_info = $products_map_by_name[strtolower($it_name)];
                }
                $unit_price = ($prod_info && $prod_info['selling-price'] !== null && $prod_info['selling-price'] !== '') ? floatval($prod_info['selling-price']) : floatval($it['price'] ?? 0);
                $item_expected = $unit_price * $it_qty;
                $group_expected_total += $item_expected;

                $expanded[] = [
                    'id' => $it_id,
                    'name' => $it_name,
                    'quantity' => $it_qty,
                    'unit_price' => $unit_price,
                    'item_expected' => $item_expected
                ];
            }

            $group_received_total = $sale_amount;
            if ($group_expected_total <= 0) {
                $sum_qty = 0;
                foreach ($expanded as $ex) $sum_qty += $ex['quantity'];
                foreach ($expanded as $ex) {
                    $prop = ($sum_qty > 0) ? ($ex['quantity'] / $sum_qty) : 0;
                    $item_received = $group_received_total * $prop;
                    $prod_key = $ex['id'] ? 'id_'.$ex['id'] : 'name_'.strtolower($ex['name']);
                    $key = implode('|', [$sale_date, $branch_id_key, $prod_key]);
                    if (!isset($product_summary_map[$key])) {
                        $product_summary_map[$key] = [
                            'sale_date' => $sale_date,
                            'branch_name' => $branch_name,
                            'product_name' => $ex['name'],
                            'items_sold_full_pay' => 0,
                            'items_sold_debtors' => 0,
                            'total_items_sold' => 0,
                            'unit_price' => $ex['unit_price'],
                            'expected_amount' => 0.0,
                            'amount_received' => 0.0
                        ];
                    }
                    $product_summary_map[$key]['items_sold_full_pay'] += $ex['quantity'];
                    $product_summary_map[$key]['total_items_sold'] += $ex['quantity'];
                    $product_summary_map[$key]['expected_amount'] += $ex['unit_price'] * $ex['quantity'];
                    $product_summary_map[$key]['amount_received'] += $item_received;
                }
            } else {
                foreach ($expanded as $ex) {
                    $prop = ($group_expected_total > 0) ? ($ex['item_expected'] / $group_expected_total) : 0;
                    $item_received = $group_received_total * $prop;
                    $prod_key = $ex['id'] ? 'id_'.$ex['id'] : 'name_'.strtolower($ex['name']);
                    $key = implode('|', [$sale_date, $branch_id_key, $prod_key]);
                    if (!isset($product_summary_map[$key])) {
                        $product_summary_map[$key] = [
                            'sale_date' => $sale_date,
                            'branch_name' => $branch_name,
                            'product_name' => $ex['name'],
                            'items_sold_full_pay' => 0,
                            'items_sold_debtors' => 0,
                            'total_items_sold' => 0,
                            'unit_price' => $ex['unit_price'],
                            'expected_amount' => 0.0,
                            'amount_received' => 0.0
                        ];
                    }
                    $product_summary_map[$key]['items_sold_full_pay'] += $ex['quantity'];
                    $product_summary_map[$key]['total_items_sold'] += $ex['quantity'];
                    $product_summary_map[$key]['expected_amount'] += $ex['item_expected'];
                    $product_summary_map[$key]['amount_received'] += $item_received;
                }
            }
        }
    }
    $sr->close();
}

// --- Also include items from debtors (THESE ARE DEBTOR SALES) ---
$debtor_where = [];
if ($user_role === 'staff') {
    $debtor_where[] = "debtors.branch_id = $user_branch";
} elseif ($ps_branch) {
    $debtor_where[] = "debtors.branch_id = " . intval($ps_branch);
}
if ($ps_date_from) {
    $debtor_where[] = "DATE(debtors.created_at) >= '" . $conn->real_escape_string($ps_date_from) . "'";
}
if ($ps_date_to) {
    $debtor_where[] = "DATE(debtors.created_at) <= '" . $conn->real_escape_string($ps_date_to) . "'";
}
$debtorWhereSql = count($debtor_where) ? "WHERE " . implode(' AND ', $debtor_where) : "";

$dr = $conn->query("
    SELECT id, created_at AS date, branch_id, (SELECT name FROM branch WHERE id = debtors.branch_id) AS branch_name,
           products_json, item_taken, quantity_taken, amount_paid, balance
    FROM debtors
    $debtorWhereSql
    ORDER BY created_at DESC
    LIMIT 2000
");
if ($dr) {
    while ($d = $dr->fetch_assoc()) {
        $sale_date = date('Y-m-d', strtotime($d['date']));
        $branch_id_key = intval($d['branch_id']);
        $branch_name = $d['branch_name'] ?? 'Unknown';
        $debtor_paid = floatval($d['amount_paid'] ?? 0);
        $items_json = $d['products_json'] ?? null;
        $total_items_qty = intval($d['quantity_taken'] ?? 0);

        $expanded = [];
        if ($items_json) {
            $items = json_decode($items_json, true);
            if (is_array($items) && count($items) > 0) {
                foreach ($items as $it) {
                    $it_qty = floatval($it['quantity'] ?? ($it['qty'] ?? 0));
                    $it_id = intval($it['id'] ?? 0);
                    $it_name = trim($it['name'] ?? ($it['product'] ?? 'Unknown'));
                    $prod_info = null;
                    if ($it_id && isset($products_map_by_id[$it_id])) {
                        $prod_info = $products_map_by_id[$it_id];
                    } elseif ($it_name && isset($products_map_by_name[strtolower($it_name)])) {
                        $prod_info = $products_map_by_name[strtolower($it_name)];
                    }
                    $unit_price = ($prod_info && $prod_info['selling-price'] !== null && $prod_info['selling-price'] !== '') ? floatval($prod_info['selling-price']) : floatval($it['price'] ?? 0);
                    $item_expected = $unit_price * $it_qty;
                    $expanded[] = [
                        'id' => $it_id,
                        'name' => $it_name,
                        'quantity' => $it_qty,
                        'unit_price' => $unit_price,
                        'item_expected' => $item_expected
                    ];
                }
            }
        }

        if (empty($expanded)) {
            $items = array_filter(array_map('trim', explode(',', $d['item_taken'] ?? '')));
            if (count($items) > 0) {
                foreach ($items as $item_str) {
                    if (preg_match('/^(.+?)\s*x(\d+)$/i', $item_str, $mch)) {
                        $name = trim($mch[1]);
                        $qty = intval($mch[2]);
                    } else {
                        $name = $item_str;
                        $qty = 1;
                    }
                    $prod_info = $products_map_by_name[strtolower($name)] ?? null;
                    $unit_price = ($prod_info && $prod_info['selling-price'] !== null && $prod_info['selling-price'] !== '') ? floatval($prod_info['selling-price']) : (($total_items_qty>0) ? ( (floatval($d['amount_paid'] ?? 0) + floatval($d['balance'] ?? 0)) / max(1, $total_items_qty) ) : 0);
                    $expanded[] = [
                        'id' => $prod_info['id'] ?? 0,
                        'name' => $name,
                        'quantity' => $qty,
                        'unit_price' => $unit_price,
                        'item_expected' => $unit_price * $qty
                    ];
                }
            }
        }

        if (empty($expanded)) {
            $key = implode('|', [$sale_date, $branch_id_key, 'multiple_products']);
            if (!isset($product_summary_map[$key])) {
                $product_summary_map[$key] = [
                    'sale_date' => $sale_date,
                    'branch_name' => $branch_name,
                    'product_name' => 'Multiple Products',
                    'items_sold_full_pay' => 0,
                    'items_sold_debtors' => 0,
                    'total_items_sold' => 0,
                    'unit_price' => 0,
                    'expected_amount' => 0.0,
                    'amount_received' => 0.0
                ];
            }
            // Add to debtors
            $product_summary_map[$key]['items_sold_debtors'] += max(0, $total_items_qty);
            $product_summary_map[$key]['total_items_sold'] += max(0, $total_items_qty);
            // Don't add to amount_received (debtors haven't paid)
            continue;
        }

        $group_expected_total = 0.0;
        foreach ($expanded as $ex) $group_expected_total += floatval($ex['item_expected']);

        foreach ($expanded as $ex) {
            $prod_key = $ex['id'] ? 'id_'.$ex['id'] : 'name_'.strtolower($ex['name']);
            $key = implode('|', [$sale_date, $branch_id_key, $prod_key]);
            if (!isset($product_summary_map[$key])) {
                $product_summary_map[$key] = [
                    'sale_date' => $sale_date,
                    'branch_name' => $branch_name,
                    'product_name' => $ex['name'],
                    'items_sold_full_pay' => 0,
                    'items_sold_debtors' => 0,
                    'total_items_sold' => 0,
                    'unit_price' => $ex['unit_price'],
                    'expected_amount' => 0.0,
                    'amount_received' => 0.0
                ];
            }
            // Add to debtors
            $product_summary_map[$key]['items_sold_debtors'] += $ex['quantity'];
            $product_summary_map[$key]['total_items_sold'] += $ex['quantity'];
            $product_summary_map[$key]['expected_amount'] += $ex['item_expected'];
            // Don't add to amount_received
        }
    }
    $dr->close();
}

// --- Customer debtors (same logic) ---
$cust_debtor_where = [];
if ($user_role === 'staff') {
    $cust_debtor_where[] = "ct.branch_id = $user_branch";
} elseif ($ps_branch) {
    $cust_debtor_where[] = "ct.branch_id = " . intval($ps_branch);
}
if ($ps_date_from) {
    $cust_debtor_where[] = "DATE(ct.date_time) >= '" . $conn->real_escape_string($ps_date_from) . "'";
}
if ($ps_date_to) {
    $cust_debtor_where[] = "DATE(ct.date_time) <= '" . $conn->real_escape_string($ps_date_to) . "'";
}
$custDebtorWhereSql = count($cust_debtor_where) ? "WHERE ct.status='debtor' AND " . implode(' AND ', $cust_debtor_where) : "WHERE ct.status='debtor'";

$cdr = $conn->query("
    SELECT ct.id, ct.date_time AS date, ct.products_bought AS products_json, ct.amount_paid, ct.amount_credited,
           ct.customer_id, ct.branch_id, b.name AS branch_name
    FROM customer_transactions ct
    LEFT JOIN branch b ON ct.branch_id = b.id
    $custDebtorWhereSql
    ORDER BY ct.date_time DESC
    LIMIT 2000
");
if ($cdr) {
    while ($cd = $cdr->fetch_assoc()) {
        $sale_date = date('Y-m-d', strtotime($cd['date']));
        $branch_id_key = intval($cd['branch_id'] ?: 1);
        $branch_name = $cd['branch_name'] ?: 'Unknown';
        
        $debtor_paid = floatval($cd['amount_paid'] ?? 0);
        $items_json = $cd['products_bought'] ?? null;

        if (!$items_json || trim($items_json) === '' || trim($items_json) === '[]') {
            continue;
        }

        $expanded = [];
        $items = json_decode($items_json, true);
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $it) {
                $it_qty = floatval($it['quantity'] ?? ($it['qty'] ?? 0));
                $it_id = intval($it['id'] ?? 0);
                $it_name = trim($it['name'] ?? ($it['product'] ?? 'Unknown'));
                
                if ($it_qty <= 0) continue;
                
                $prod_info = null;
                if ($it_id && isset($products_map_by_id[$it_id])) {
                    $prod_info = $products_map_by_id[$it_id];
                } elseif ($it_name && isset($products_map_by_name[strtolower($it_name)])) {
                    $prod_info = $products_map_by_name[strtolower($it_name)];
                }
                $unit_price = ($prod_info && $prod_info['selling-price'] !== null && $prod_info['selling-price'] !== '') ? floatval($prod_info['selling-price']) : floatval($it['price'] ?? 0);
                $item_expected = $unit_price * $it_qty;
                $expanded[] = [
                    'id' => $it_id,
                    'name' => $it_name,
                    'quantity' => $it_qty,
                    'unit_price' => $unit_price,
                    'item_expected' => $item_expected
                ];
            }
        }

        if (empty($expanded)) {
            continue;
        }

        foreach ($expanded as $ex) {
            $prod_key = $ex['id'] ? 'id_'.$ex['id'] : 'name_'.strtolower($ex['name']);
            $key = implode('|', [$sale_date, $branch_id_key, $prod_key]);
            if (!isset($product_summary_map[$key])) {
                $product_summary_map[$key] = [
                    'sale_date' => $sale_date,
                    'branch_name' => $branch_name,
                    'product_name' => $ex['name'],
                    'items_sold_full_pay' => 0,
                    'items_sold_debtors' => 0,
                    'total_items_sold' => 0,
                    'unit_price' => $ex['unit_price'],
                    'expected_amount' => 0.0,
                    'amount_received' => 0.0
                ];
            }
            // Add to debtors
            $product_summary_map[$key]['items_sold_debtors'] += $ex['quantity'];
            $product_summary_map[$key]['total_items_sold'] += $ex['quantity'];
            $product_summary_map[$key]['expected_amount'] += $ex['item_expected'];
            // Don't add to amount_received
        }
    }
    $cdr->close();
}

// Convert map to sorted array for rendering (sort by date desc, branch, product)
$product_summary_rows = array_values($product_summary_map);
usort($product_summary_rows, function($a, $b){
    if ($a['sale_date'] === $b['sale_date']) {
        if ($a['branch_name'] === $b['branch_name']) return strcmp($a['product_name'],$b['product_name']);
        return strcmp($a['branch_name'],$a['branch_name']);
    }
    return strcmp($b['sale_date'],$a['sale_date']); // desc
});
?>

<!-- Link external CSS -->
<link rel="stylesheet" href="sales.css">

<!-- Tabs for Sales and Debtors -->
<div class="container-fluid mt-4">
    <!-- New pill styles (same as Till Management) -->
    <!-- Inline styles moved to sales.css -->

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

                    <!-- Chart.js and initialization (moved to external file) -->
                    <!-- expose server-side chart data to external JS -->
                    <script>
                        window.salesServerData = {
                            chart_labels: <?= json_encode($chart_labels) ?>,
                            series: <?= json_encode($series) ?>
                        };
                    </script>
                    <!-- load external sales JS (contains all page JS previously inline) -->
                    <script src="sales.js"></script>
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
                                    <th>Receipt No.</th>
                                    <th>Product(s)</th>
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
                                    // Parse products_json if available
                                    $products_display = '';
                                    if ($row['products_json']) {
                                        $products_data = json_decode($row['products_json'], true);
                                        if (is_array($products_data)) {
                                            $products_display = implode(', ', array_map(function($p) {
                                                return htmlspecialchars($p['name']) . ' x' . $p['quantity'];
                                            }, $products_data));
                                        } else {
                                            $products_display = htmlspecialchars($row['product-name']);
                                        }
                                    } else {
                                                                               $products_display = htmlspecialchars($row['product-name']);
                                    }
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <?php if ($user_role !== 'staff' && empty($selected_branch)) echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>"; ?>
                                        <td><?= htmlspecialchars($row['receipt_no'] ?? '-') ?></td>
                                        <td><span class="badge bg-primary"><?= $products_display ?></span></td>
                                        <td><?= $row['quantity'] ?></td>
                                        <td><span class="fw-bold text-success">UGX<?= number_format($row['amount'], 2) ?></span></td>
                                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                        <td><small class="text-muted"><?= $row['date'] ?></small></td>
                                        <td><?= htmlspecialchars($row['sold-by']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($sales->num_rows === 0): ?>
                                    <tr><td colspan="<?= ($user_role !== 'staff' && empty($selected_branch)) ? 9 : 8 ?>" class="text-center text-muted">No sales found.</td></tr>
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
                                                <th>Invoice No.</th>
                                                <th>Debtor Name</th>
                                                <th>Debtor Email</th>
                                                <th>Contact</th>
                                                <th>Item Taken</th>
                                                <th>Quantity Taken</th>
                                                <th>Payment Method</th>
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
                                                    // FIX: Parse products_json to get actual cart data
                                                    $products_json = $debtor['products_json'] ?? '[]';
                                                    $products_data = json_decode($products_json, true);
                                                    
                                                    // If products_json is empty/invalid, try to reconstruct from item_taken
                                                    if (!is_array($products_data) || empty($products_data)) {
                                                        $products_data = [];
                                                        // Parse item_taken (e.g., "maize x2, beans x3")
                                                        $items = array_filter(array_map('trim', explode(',', $debtor['item_taken'] ?? '')));
                                                        foreach ($items as $item_str) {
                                                            // Extract name and quantity (e.g., "maize x2")
                                                            if (preg_match('/^(.+?)\s*x(\d+)$/i', $item_str, $matches)) {
                                                                $name = trim($matches[1]);
                                                                $qty = intval($matches[2]);
                                                            } else {
                                                                $name = $item_str;
                                                                $qty = 1;
                                                            }
                                                            
                                                            // Estimate price per item (total / total_qty)
                                                            $total_qty = intval($debtor['quantity_taken'] ?? 0);
                                                            $total_amount = floatval($debtor['balance']) + floatval($debtor['amount_paid']);
                                                            $price_per_item = ($total_qty > 0) ? ($total_amount / $total_qty) : 0;
                                                            
                                                            $products_data[] = [
                                                                'name' => $name,
                                                                'quantity' => $qty,
                                                                'price' => $price_per_item
                                                            ];
                                                        }
                                                        

                                                    }
                                                    ?>
                                                    <tr id="shop-debtor-<?= $debtor['id'] ?>">
                                                        <td><?= date("M d, Y H:i", strtotime($debtor['created_at'])); ?></td>
                                                        <td><?= htmlspecialchars($debtor['invoice_no'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($debtor['debtor_name']); ?></td>
                                                        <td><?= htmlspecialchars($debtor['debtor_email']); ?></td>
                                                        <td><?= htmlspecialchars($debtor['debtor_contact'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($debtor['item_taken'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($debtor['quantity_taken'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($debtor['payment_method'] ?? '-'); ?></td>
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
                                                            <?php if ($show_due_date_btn): ?>
                                                                <button class="btn btn-sm btn-outline-primary set-due-date-btn" 
                                                                        data-type="shop"
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
                                                            <!-- UPDATED: icon-only Invoice + Pay buttons (inline) -->
                                                            <div class="d-flex gap-1">
                                                                <button class="btn btn-outline-info btn-sm btn-view-invoice"
                                                                        data-type="shop"
                                                                        data-invoice="<?= htmlspecialchars($debtor['invoice_no'] ?? 'N/A') ?>"
                                                                        data-name="<?= htmlspecialchars($debtor['debtor_name']) ?>"
                                                                        data-email="<?= htmlspecialchars($debtor['debtor_email']) ?>"
                                                                        data-contact="<?= htmlspecialchars($debtor['debtor_contact'] ?? '') ?>"
                                                                        data-products='<?= htmlspecialchars($debtor['products_json'] ?: '[]') ?>'
                                                                        data-balance="<?= $debtor['balance'] ?>"
                                                                        data-paid="<?= $debtor['amount_paid'] ?>"
                                                                        data-due-date="<?= htmlspecialchars($debtor['due_date'] ?? '') ?>"
                                                                        title="View Invoice">
                                                                    <i class="fa fa-file-invoice"></i>
                                                                </button>
                                                                <button class="btn btn-outline-primary btn-sm btn-pay-debtor"
                                                                        data-id="<?= $debtor['id'] ?>"
                                                                        data-balance="<?= htmlspecialchars($debtor['balance'] ?? 0) ?>"
                                                                        data-name="<?= htmlspecialchars($debtor['debtor_name']) ?>"
                                                                        title="Record Payment">
                                                                    <i class="fa fa-money-bill-wave"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="12" class="text-center text-muted">No shop debtors recorded yet.</td>
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
                                                <th>Due Date</th>
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
                                                    <tr id="customer-debtor-<?= $cd['id'] ?>">
                                                        <td><?= $date_obj->format('M d, Y'); ?></td>
                                                        <td><?= $date_obj->format('H:i'); ?></td>
                                                        <td><?= htmlspecialchars($cd['invoice_receipt_no'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($cd['debtor_name']) ?></td>
                                                        <td><?= htmlspecialchars($cd['debtor_email'] ?? '-'); ?></td>
                                                        <td><?= htmlspecialchars($cd['debtor_contact'] ?? '-'); ?></td>
                                                        <td><?= $products_display ?: '-'; ?></td>
                                                        <td class="text-center"><?= $total_qty; ?></td>
                                                        <td><?= $unit_prices_display ?: '-'; ?></td>
                                                        <td><?= htmlspecialchars('Customer File'); ?></td>
                                                        <td>UGX <?= number_format($cd['amount_paid'] ?? 0, 2); ?></td>
                                                        <td>UGX <?= number_format($cd['balance'] ?? 0, 2); ?></td>
                                                        <td>
                                                            <?php
                                                            // Check if due date button should be shown
                                                            $show_due_date_btn = true;
                                                            $due_date_display = '-';
                                                            if ($cd['due_date']) {
                                                                $due_date_display = date('M d, Y', strtotime($cd['due_date']));
                                                                $today = new DateTime();
                                                                $due = new DateTime($cd['due_date']);
                                                                $diff = $today->diff($due);
                                                                $days_diff = (int)$diff->format('%r%a');
                                                                // Show button if due date exceeded by 4+ days
                                                                $show_due_date_btn = ($days_diff < -3);
                                                            }
                                                            ?>
                                                            <?php if ($show_due_date_btn): ?>
                                                                <button class="btn btn-sm btn-outline-primary set-due-date-btn" 
                                                                        data-type="customer"
                                                                        data-id="<?= $cd['id'] ?>"
                                                                        data-name="<?= htmlspecialchars($cd['debtor_name']) ?>"
                                                                        title="Set Due Date">
                                                                    <i class="fa fa-calendar"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted"><?= $due_date_display ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <!-- UPDATED: icon-only Invoice + Pay buttons (inline) -->
                                                            <div class="d-flex gap-1">
                                                                <button class="btn btn-outline-info btn-sm btn-view-invoice"
                                                                        data-type="customer"
                                                                        data-invoice="<?= htmlspecialchars($cd['invoice_receipt_no'] ?? 'N/A') ?>"
                                                                        data-name="<?= htmlspecialchars($cd['debtor_name']) ?>"
                                                                        data-email="<?= htmlspecialchars($cd['debtor_email']) ?>"
                                                                        data-contact="<?= htmlspecialchars($cd['debtor_contact'] ?? '') ?>"
                                                                        data-products='<?= htmlspecialchars($cd['products_bought'] ?: '[]') ?>'
                                                                        data-balance="<?= $cd['balance'] ?>"
                                                                        data-paid="<?= $cd['amount_paid'] ?>"
                                                                        data-due-date="<?= htmlspecialchars($cd['due_date'] ?? '') ?>"
                                                                        title="View Invoice">
                                                                    <i class="fa fa-file-invoice"></i>
                                                                </button>
                                                                <button class="btn btn-outline-primary btn-sm btn-pay-customer-debtor"
                                                                        data-id="<?= $cd['id'] ?>"
                                                                        data-balance="<?= htmlspecialchars($cd['balance']) ?>"
                                                                        data-name="<?= htmlspecialchars($cd['debtor_name']) ?>"
                                                                        title="Record Payment">
                                                                    <i class="fa fa-money-bill-wave"></i>
                                                                </button>
                                                            </div>
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
                                    <th>Items Sold Full Pay</th>
                                    <th>Items Sold Debtors</th>
                                    <th>Total Items Sold</th>
                                    <th>Unit Price</th>
                                    <th>Expected Amount</th>
                                    <th>Amount Received</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($product_summary_rows)):
                                    $prev_date = null;
                                    $prev_branch = null;
                                    foreach ($product_summary_rows as $row):
                                        $show_date = ($prev_date !== $row['sale_date']);
                                        $show_branch = ($prev_branch !== $row['branch_name']) || $show_date;
                                ?>
                                    <tr>
                                        <td><?= $show_date ? htmlspecialchars($row['sale_date']) : '' ?></td>
                                        <td><?= $show_branch ? htmlspecialchars($row['branch_name']) : '' ?></td>
                                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                                        <td><?= htmlspecialchars((int)$row['items_sold_full_pay']) ?></td>
                                        <td><?= htmlspecialchars((int)$row['items_sold_debtors']) ?></td>
                                        <td><strong><?= htmlspecialchars((int)$row['total_items_sold']) ?></strong></td>
                                        <td>UGX <?= number_format((float)($row['unit_price'] ?? 0), 2) ?></td>
                                        <td>UGX <?= number_format((float)($row['expected_amount'] ?? 0), 2) ?></td>
                                        <td><strong>UGX <?= number_format((float)($row['amount_received'] ?? 0), 2) ?></strong></td>
                                    </tr>
                                <?php
                                        $prev_date = $row['sale_date'];
                                        $prev_branch = $row['branch_name'];
                                    endforeach;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No product summary data found.</td>
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
        <!-- Payment Method dropdown (ALREADY EXISTS - keep it visible for shop debtors) -->
        <div class="mb-3" id="pdMethodWrap">
          <label class="form-label">Payment Method</label>
          <select id="pdMethod" class="form-select">
            <option value="Cash">Cash</option>
            <option value="MTN MoMo">MTN MoMo</option>
            <option value="Airtel Money">Airtel Money</option>
            <option value="Bank">Bank</option>
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

<!-- NEW: Set Due Date Modal -->
<div class="modal fade" id="setDueDateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary-color);color:#fff;">
        <h5 class="modal-title">Set Due Date</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="ddDebtorLabel" class="mb-3 fw-semibold"></p>
        <input type="hidden" id="ddDebtorId" value="">
        <input type="hidden" id="ddDebtorType" value="">
        <div class="mb-3">
          <label class="form-label">Expected Payment Date</label>
          <input type="date" id="ddDueDate" class="form-control" min="<?= date('Y-m-d') ?>">
        </div>
        <div id="ddMsg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="ddConfirmBtn" class="btn btn-primary">OK</button>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
