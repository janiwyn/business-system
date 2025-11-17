<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Clear shop debtor notification (extend due date by 30 days to hide)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_shop_notification'])) {
    $debtor_id = intval($_POST['debtor_id'] ?? 0);
    
    if ($debtor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid debtor ID']);
        exit;
    }
    
    // Extend due date by 30 days (effectively clears notification)
    $new_due_date = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare("UPDATE debtors SET due_date = ? WHERE id = ?");
    $stmt->bind_param("si", $new_due_date, $debtor_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Clear customer debtor notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_customer_notification'])) {
    $transaction_id = intval($_POST['transaction_id'] ?? 0);
    
    if ($transaction_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
        exit;
    }
    
    // Extend due date by 30 days (effectively clears notification)
    $new_due_date = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare("UPDATE customer_transactions SET due_date = ? WHERE id = ?");
    $stmt->bind_param("si", $new_due_date, $transaction_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
?>
