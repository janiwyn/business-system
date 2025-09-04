<?php
include '../includes/auth.php';
require_role(['admin', 'manager']);
include '../includes/db.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: list_branches.php");
    exit;
}

$branch_id = intval($_GET['id']);

// Verify branch exists
$stmt = $conn->prepare("SELECT id FROM branch WHERE id=?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: list_branches.php");
    exit;
}
$stmt->close();

// Delete branch
$stmt = $conn->prepare("DELETE FROM branch WHERE id=?");
$stmt->bind_param("i", $branch_id);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: list_branches.php?deleted=1");
    exit;
} else {
    echo "Error deleting branch: " . $conn->error;
    $stmt->close();
}
?>
