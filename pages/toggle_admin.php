<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int) $_GET['id'];
    $new_status = $_GET['status'] === 'active' ? 'inactive' : 'active';

    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();

    header("Location: manage_admin.php");
    exit;
}
?>
