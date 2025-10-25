<?php
include '../includes/db.php';
include '../includes/auth.php';

if (!isset($_GET['id']) || !isset($_GET['status'])) {
    die("<div class='alert alert-danger m-3'>Invalid request.</div>");
}

$id = (int) $_GET['id'];
$current_status = $_GET['status'];
$new_status = ($current_status === 'active') ? 'suspended' : 'active';

$stmt = $conn->prepare("UPDATE businesses SET status=? WHERE id=?");
$stmt->bind_param("si", $new_status, $id);

if ($stmt->execute()) {
    echo "<script>
            alert('Business status updated to $new_status');
            window.location.href='manage_business.php';
          </script>";
} else {
    echo "<div class='alert alert-danger m-3'>Error updating status.</div>";
}
?>
