<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) session_start();

include_once __DIR__ . '/../includes/db.php';

// Handle debtor payment (AJAX)
if (isset($_POST['pay_debtor']) && isset($_POST['id']) && isset($_POST['amount'])) {
    // Only send JSON header if possible (avoid "headers already sent" warnings)
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    $debtor_id = intval($_POST['id']);
    $amount = floatval($_POST['amount']);
    $now = date('Y-m-d H:i:s');
    $current_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

    // Fetch debtor info safely
    $stmt = $conn->prepare("SELECT * FROM debtors WHERE id = ?");
    $stmt->bind_param("i", $debtor_id);
    $stmt->execute();
    $debtor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$debtor) {
        echo json_encode(['message' => 'Debtor not found.', 'reload' => false]);
        exit;
    }

    // normalize fields (compat with different schemas)
    $debtor_amount_paid = floatval($debtor['amount_paid'] ?? 0);
    $debtor_balance = floatval($debtor['balance'] ?? 0);
    $debtor_item_taken = $debtor['item_taken'] ?? '';
    $debtor_quantity = intval($debtor['quantity_taken'] ?? 0);
    $debtor_branch_id = intval($debtor['branch_id'] ?? $debtor['branch-id'] ?? 0);
    $debtor_created_by = intval($debtor['created_by'] ?? $debtor['created-by'] ?? $current_user_id);

    $new_paid = $debtor_amount_paid + $amount;
    $new_balance = $debtor_balance - $amount;

    // Partial payment
    if ($new_balance > 0) {
        $ust = $conn->prepare("UPDATE debtors SET amount_paid = ?, balance = ? WHERE id = ?");
        $ust->bind_param("ddi", $new_paid, $new_balance, $debtor_id);
        $ok = $ust->execute();
        $ust->close();
        if ($ok) {
            echo json_encode(['message' => 'Partial payment recorded. Remaining balance: UGX ' . number_format($new_balance,2), 'reload' => true]);
        } else {
            echo json_encode(['message' => 'Failed to record partial payment.', 'reload' => false]);
        }
        exit;
    }

    // Full payment: record sales and remove debtor
    $conn->begin_transaction();
    try {
        $items = array_filter(array_map('trim', explode(',', $debtor_item_taken)));
        $per_item_qty = ($debtor_quantity > 0) ? ((count($items) > 1) ? 1 : $debtor_quantity) : 1;

        foreach ($items as $item_name) {
            if ($item_name === '') continue;
            $pstmt = $conn->prepare("SELECT id, `selling-price`, `buying-price`, stock FROM products WHERE name = ? AND `branch-id` = ? LIMIT 1");
            $pstmt->bind_param("si", $item_name, $debtor_branch_id);
            $pstmt->execute();
            $prod = $pstmt->get_result()->fetch_assoc();
            $pstmt->close();

            if (!$prod) continue;

            $product_id = intval($prod['id']);
            $quantity = max(1, intval($per_item_qty));
            $selling_price = floatval($prod['selling-price']);
            $buying_price = floatval($prod['buying-price']);
            $total_price = $selling_price * $quantity;
            $cost_price = $buying_price * $quantity;
            $total_profit = $total_price - $cost_price;

            $sold_by = $current_user_id ?? $debtor_created_by;
            $sstmt = $conn->prepare("INSERT INTO sales (`product-id`,`branch-id`,quantity,amount,`sold-by`,`cost-price`,total_profits,date,payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $payment_method = 'Debtor';
            $sstmt->bind_param("iiididdss", $product_id, $debtor_branch_id, $quantity, $total_price, $sold_by, $cost_price, $total_profit, $now, $payment_method);
            $sstmt->execute();
            $sstmt->close();

            if (isset($prod['stock'])) {
                $new_stock = max(0, intval($prod['stock']) - $quantity);
                $u = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
                $u->bind_param("ii", $new_stock, $product_id);
                $u->execute();
                $u->close();
            }

            // Update profits upsert
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

        $dstmt = $conn->prepare("DELETE FROM debtors WHERE id = ?");
        $dstmt->bind_param("i", $debtor_id);
        $dstmt->execute();
        $dstmt->close();

        $conn->commit();
        echo json_encode(['message' => 'Debt fully paid and sale recorded.', 'reload' => true]);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['message' => 'Error processing payment.', 'reload' => false]);
        exit;
    }
}
?>
