<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff"]);
include '../includes/header.php';
if ($_SESSION['role'] === 'staff') {
    include 'sidebar_staff.php';
} else {
    include 'sidebar.php';
}

if (!isset($_GET['id'])) {
    die('Customer ID is required');
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    die('Customer not found');
}
$customer = $result->fetch_assoc();

?>
<div class="container mt-5">
    <h2>Customer File #<?= $customer['id']; ?></h2>
    <div class="card">
        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($customer['name']); ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($customer['contact']); ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($customer['email']); ?></p>
            <p><strong>Amount Credited:</strong> UGX <?= number_format($customer['credited_amount'], 2); ?></p>
            <p><strong>Account Balance:</strong> UGX <?= number_format($customer['account_balance'], 2); ?></p>
            <a href="customer_management.php" class="btn btn-secondary">Back to Management</a>
        </div>
    </div>
</div>
<?php include '../includes/footer.php';
