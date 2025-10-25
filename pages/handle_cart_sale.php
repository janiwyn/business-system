<?php
 
include '../includes/db.php';

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$branch_id = $_SESSION['branch_id']; // ✅ fixed

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

        $date = date('Y-m-d');

        // Insert sale row, include payment_method and customer_id if "Customer File"
        if ($payment_method === 'Customer File' && $customer_id > 0) {
            $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date, payment_method, customer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiidddssi", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit, $date, $payment_method, $customer_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiidddss", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit, $date, $payment_method);
        }
        if (!$stmt->execute()) {
            $success = false;
            $messages[] = "Failed to record sale for " . htmlspecialchars($item['name']);
            $stmt->close();
            break;
        }
        $stmt->close();

        // Only update stock/profits if sale insert succeeded
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

    // Record customer transaction if payment method is Customer File
    if ($success && $payment_method === 'Customer File' && $customer_id > 0) {
        $now = date('Y-m-d H:i:s');
        $sold_by = $_SESSION['username'];
        $amount_credited = 0; // If you want to track credited amount, set accordingly
        // FIX: Use $total as amount_paid (not $amount_paid, which is always 0 for customer file)
        $ct = $conn->prepare("INSERT INTO customer_transactions (customer_id, date_time, products_bought, amount_paid, amount_credited, sold_by, status) VALUES (?, ?, ?, ?, ?, ?, 'paid')");
        $ct->bind_param("issdds", $customer_id, $now, $products_json, $total, $amount_credited, $sold_by);
        $ct->execute();
        $ct->close();

        // Deduct sale amount from customer's account_balance
        $stmt = $conn->prepare("UPDATE customers SET account_balance = account_balance - ? WHERE id = ?");
        $stmt->bind_param("di", $total, $customer_id);
        $stmt->execute();
        $stmt->close();
    }

    if ($success) {
        $conn->commit();
        $_SESSION['cart_sale_message'] = '✅ Sale recorded successfully!';
    } else {
        $conn->rollback();
        $_SESSION['cart_sale_message'] = '❌ ' . implode(' ', $messages);
    }
    // Redirect to avoid resubmission and show message
    echo "<script>window.location='staff_dashboard.php';</script>";
    exit;
}
?>