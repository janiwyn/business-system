<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $new_password = password_hash("123456", PASSWORD_DEFAULT); // default reset password

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_password, $id);
    $stmt->execute();

    echo "<script>alert('Password reset to 123456'); window.location='manage_admins.php';</script>";
}
?>
