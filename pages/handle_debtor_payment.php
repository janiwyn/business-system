
<?php

include '../includes/db.php';




$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$branch_id = $_SESSION['branch_id']; // ✅ fixed

// Handle debtor payment (AJAX)
if (isset($_POST['pay_debtor']) && isset($_POST['id']) && isset($_POST['amount'])) {
    $debtor_id = intval($_POST['id']);
    $amount = floatval($_POST['amount']);
    $now = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'];

    // Fetch debtor info
    $debtor = $conn->query("SELECT * FROM debtors WHERE id=$debtor_id")->fetch_assoc();
    if (!$debtor) {
        echo json_encode(['message' => 'Debtor not found.', 'reload' => false]);
        exit;
    }
    $new_paid = $debtor['amount_paid'] + $amount;
    $new_balance = $debtor['balance'] - $amount;

    if ($new_balance > 0) {
        // Partial payment: update debtor record
        $stmt = $conn->prepare("UPDATE debtors SET amount_paid=?, balance=? WHERE id=?");
        $stmt->bind_param("ddi", $new_paid, $new_balance, $debtor_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['message' => 'Partial payment recorded. Remaining balance: UGX ' . number_format($new_balance,2), 'reload' => true]);
        exit;
    } else {
        // Full payment: remove debtor, add to sales
        $conn->begin_transaction();
        try {
            // Insert into sales table for each item (split by comma if multiple)
            $items = explode(',', $debtor['item_taken']);
            $qty = intval($debtor['quantity_taken']);
            $branch_id = $debtor['branch-id'];
            $sold_by = $debtor['created_by'];
            $amount_paid = $debtor['amount_paid'] + $amount;
            $per_item_qty = $qty;
            if (count($items) > 1) $per_item_qty = 1; // crude split if multiple items

            foreach ($items as $item_name) {
                $item_name = trim($item_name);
                // Find product id and price
                $prod = $conn->query("SELECT id, `selling-price`, `buying-price`, stock FROM products WHERE name='" . $conn->real_escape_string($item_name) . "' AND `branch-id`=$branch_id LIMIT 1")->fetch_assoc();
                if ($prod) {
                    $product_id = $prod['id'];
                    $total_price = $prod['selling-price'] * $per_item_qty;
                    $cost_price = $prod['buying-price'] * $per_item_qty;
                    $total_profit = $total_price - $cost_price;
                    // Insert sale
                    $stmt = $conn->prepare("INSERT INTO sales (`product-id`, `branch-id`, quantity, amount, `sold-by`, `cost-price`, total_profits, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiididds", $product_id, $branch_id, $per_item_qty, $total_price, $sold_by, $cost_price, $total_profit, $now);
                    $stmt->execute();
                    $stmt->close();
                    // Update stock
                    $new_stock = $prod['stock'] - $per_item_qty;
                    $conn->query("UPDATE products SET stock=$new_stock WHERE id=$product_id");
                }
            }
            // Remove debtor
            $conn->query("DELETE FROM debtors WHERE id=$debtor_id");
            $conn->commit();
            echo json_encode(['message' => 'Debt fully paid and sale recorded.', 'reload' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['message' => 'Error processing payment.', 'reload' => false]);
        }
        exit;
    }
}
?>
