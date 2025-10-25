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

        $date = date('Y-m-d');

        // Insert sale row
        $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiidddss", $product_id, $branch_id, $quantity, $total_price, $user_id, $cost_price, $total_profit, $date, $payment_method);
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