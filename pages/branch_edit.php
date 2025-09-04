<?php
ob_start();
include '../includes/auth.php';
require_role(['admin', 'manager']);
include '../includes/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Branch not found.");
}
$branch_id = intval($_GET['id']);

// Fetch branch info
$sql = "SELECT * FROM branch WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
if (!$branch) die("Branch not found.");

// Handle form submission
if (isset($_POST['update_branch'])) {
    $name = $_POST['name'];
    $location = $_POST['location'];
    $contact = $_POST['contact'];

    $update_sql = "UPDATE branch SET name=?, location=?, contact=? WHERE id=?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssi", $name, $location, $contact, $branch_id);
    $stmt->execute();
    header("Location: branch_view.php?id=$branch_id");
    exit;
}
  include '../includes/header.php';
    include '../pages/sidebar.php';
?>

<div class="container mt-5">
    <h2>Edit Branch</h2>
    <form method="POST">
        <div class="mb-3">
            <label>Branch Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($branch['name']); ?>" required>
        </div>
        <div class="mb-3">
            <label>Location</label>
            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($branch['location']); ?>" required>
        </div>
        <div class="mb-3">
            <label>Contact</label>
            <input type="text" name="contact" class="form-control" value="<?php echo htmlspecialchars($branch['contact']); ?>" required>
        </div>
        <button type="submit" name="update_branch" class="btn btn-primary">Update Branch</button>
        <a href="branch_view.php?id=<?php echo $branch_id; ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>
