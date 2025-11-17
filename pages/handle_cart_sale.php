<?php
 
include '../includes/db.php';
include_once '../includes/receipt_helper.php'; // <-- Include helper

// --- NEW: Ensure products_json column exists in sales table ---
$check_col = $conn->query("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'sales' 
      AND COLUMN_NAME = 'products_json'
");
if (!$check_col || $check_col->num_rows === 0) {
    // Add products_json column
    $conn->query("ALTER TABLE sales ADD COLUMN products_json TEXT NULL");
    if ($conn->errno) {
        // Fallback for older MySQL versions
        @$conn->query("ALTER TABLE sales ADD COLUMN products_json TEXT NULL");
    }
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$branch_id = $_SESSION['branch_id'];

// Handle cart sale submission
if (isset($_POST['submit_cart']) && !empty($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $currentDate = date("Y-m-d");
    $conn->begin_transaction();
    $success = true;
    $messages = [];
    $total = 0;

    // Track products for customer_transactions
    $products_json = json_encode($cart);

    // Calculate total first
    foreach ($cart as $item) {
        $total += floatval($item['price']) * intval($item['quantity']);
    }

    // --- Check if Customer File payment with insufficient balance ---
    if ($payment_method === 'Customer File' && $customer_id > 0) {
        $cust_stmt = $conn->prepare("SELECT account_balance FROM customers WHERE id = ?");
        $cust_stmt->bind_param("i", $customer_id);
        $cust_stmt->execute();
        $cust_res = $cust_stmt->get_result()->fetch_assoc();
        $cust_stmt->close();
        
        $customer_balance = floatval($cust_res['account_balance'] ?? 0);
        
        // If insufficient balance: ONLY record customer debtor, DO NOT record sales
        if ($customer_balance < $total) {
            // Generate INVOICE number
            try {
                $inv4 = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            } catch (Throwable $e) {
                $inv4 = str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            }
            $receipt_invoice_no = 'INV-' . $inv4;

            $now = date('Y-m-d H:i:s');
            $sold_by = $_SESSION['username'];
            
            // Record ONLY in customer_transactions (debtor)
            $amount_paid_val = $customer_balance; // They can pay whatever balance they have
            $amount_credited = $total - $customer_balance; // Remaining debt
            $status = 'debtor';

            // Deduct customer's available balance (if any)
            if ($customer_balance > 0) {
                $stmt = $conn->prepare("UPDATE customers SET account_balance = 0, amount_credited = amount_credited + ? WHERE id = ?");
                $stmt->bind_param("di", $amount_credited, $customer_id);
                $stmt->execute();
                $stmt->close();
            } else {
                // No balance at all, just add to credited
                $stmt = $conn->prepare("UPDATE customers SET amount_credited = amount_credited + ? WHERE id = ?");
                $stmt->bind_param("di", $amount_credited, $customer_id);
                $stmt->execute();
                $stmt->close();
            }

            // Record customer transaction (debtor record)
            $ct = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by, status, invoice_receipt_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ct->bind_param("issddsss", $customer_id, $now, $products_json, $amount_paid_val, $amount_credited, $sold_by, $status, $receipt_invoice_no);
            $ct->execute();
            $ct->close();

            $conn->commit();
            $_SESSION['cart_sale_message'] = '✅ Customer debtor recorded successfully! Invoice: ' . $receipt_invoice_no;
            echo "<script>window.location='staff_dashboard.php';</script>";
            exit;
        }
    }

    // --- Generate receipt number for ALL sales (not just Customer File) ---
    $receipt_invoice_no = generateReceiptNumber($conn, 'RP'); // <-- SEQUENTIAL RECEIPT NUMBER

    // --- NEW: Validate stock and update stock for all items FIRST ---
    $total_quantity = 0;
    $total_cost = 0;
    $total_profit = 0;
    
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
        
        // Calculate totals for grouped sale record
        $item_total = $product['selling-price'] * $quantity;
        $item_cost = $product['buying-price'] * $quantity;
        $total_quantity += $quantity;
        $total_cost += $item_cost;
        $total_profit += ($item_total - $item_cost);

        // Update stock
        $new_stock = $product['stock'] - $quantity;
        $update = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $update->bind_param("ii", $new_stock, $product_id);
        $update->execute();
        $update->close();
    }

    // --- NEW: Insert SINGLE grouped sales record with receipt number ---
    if ($success) {
        $date = date('Y-m-d');
        
        // Insert ONE sales record for the entire cart (WITH RECEIPT NUMBER)
        if ($payment_method === 'Customer File' && $customer_id > 0) {
            // FIX: Correct type string - 11 parameters
            $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date, payment_method, customer_id, receipt_no, products_json) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // Type string: i(branch), i(qty), d(amount), i(sold_by), d(cost), d(profit), s(date), s(pm), i(customer_id), s(receipt_no), s(products_json)
            $stmt->bind_param("iididdsiss", $branch_id, $total_quantity, $total, $user_id, $total_cost, $total_profit, $date, $payment_method, $customer_id, $receipt_invoice_no, $products_json);
        } else {
            // FIX: Correct type string - 10 parameters
            $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date, payment_method, receipt_no, products_json) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // Type string: i(branch), i(qty), d(amount), i(sold_by), d(cost), d(profit), s(date), s(pm), s(receipt_no), s(products_json)
            $stmt->bind_param("iididdssss", $branch_id, $total_quantity, $total, $user_id, $total_cost, $total_profit, $date, $payment_method, $receipt_invoice_no, $products_json);
        }
        
        if (!$stmt->execute()) {
            $success = false;
            $messages[] = "Failed to record sale";
        }
        $stmt->close();

        // Update profits
        if ($success) {
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

    // Record customer transaction if payment method is Customer File (sufficient balance case)
    if ($success && $payment_method === 'Customer File' && $customer_id > 0) {
        $now = date('Y-m-d H:i:s');
        $sold_by = $_SESSION['username'];
        $amount_paid_val = $total;
        $amount_credited = 0;
        $status = 'paid';
        
        $stmt = $conn->prepare("UPDATE customers SET account_balance = account_balance - ? WHERE id = ?");
        $stmt->bind_param("di", $total, $customer_id);
        $stmt->execute();
        $stmt->close();

        $ct = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by, status, invoice_receipt_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ct->bind_param("issddsss", $customer_id, $now, $products_json, $amount_paid_val, $amount_credited, $sold_by, $status, $receipt_invoice_no);
        $ct->execute();
        $ct->close();
    }

    if ($success) {
        $conn->commit();
        $_SESSION['cart_sale_message'] = '✅ Sale recorded successfully!';
    } else {
        $conn->rollback();
        $_SESSION['cart_sale_message'] = '❌ ' . implode(' ', $messages);
    }
    
    echo "<script>window.location='staff_dashboard.php';</script>";
    exit;
}
?>