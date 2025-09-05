<?php
include '../includes/db.php';
include '../includes/header.php';
include '../includes/auth.php';

// Correct usage: roles as an array
require_role(["manager", "admin"]);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid product ID.";
    exit;
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: product.php");
    exit;
} else {
    echo "Failed to delete product: " . $stmt->error;
}
?>
