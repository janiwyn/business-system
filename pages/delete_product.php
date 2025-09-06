<?php
include '../includes/db.php';
include '../includes/header.php';
include '../includes/auth.php';
require_role("manager", "admin");

if (!isset($_GET['id'])) {
   echo "No product selected.";
    exit;
}

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: product.php");
    exit;
} else {
    echo "Failed to delete product.";
}
?>
