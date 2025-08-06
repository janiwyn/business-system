<?php
include '../includes/db.php';
include '../includes/header.php';
include '../includes/auth.php';
require_role("manager", "admin");
include '../pages/sidebar.php';

$message = "";
$message_class = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $location = trim($_POST["location"]);
    $contact = trim($_POST["contact"]);

    if (!empty($name) && !empty($location) && !empty($contact)) {
        $stmt = $conn->prepare("INSERT INTO branches (name, location, contact) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $location, $contact);

        if ($stmt->execute()) {
            $message = "Branch created successfully!";
            $message_class = "alert-success";

            // Redirect back to branch.php with new branch ID
            $new_branch_id = $stmt->insert_id;
            header("Location: branch.php?id=$new_branch_id");
            exit;
        } else {
            $message = "Failed to create branch. Try again.";
            $message_class = "alert-danger";
        }
    } else {
        $message = "All fields are required.";
        $message_class = "alert-warning";
    }
}
?>

<div class="container mt-5">
    <h2 class="mb-4">Create New Branch</h2>

    <?php if ($message): ?>
        <div class="alert <?= $message_class ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Branch Details</div>
        <div class="card-body">
            <form method="POST" action="create_branch.php">
                <div class="mb-3">
                    <label for="name" class="form-label">Branch Name</label>
                    <input type="text" class="form-control" id="name" name="name" required placeholder="Enter branch name">
                </div>
                <div class="mb-3">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" class="form-control" id="location" name="location" required placeholder="Enter location">
                </div>
                <div class="mb-3">
                    <label for="contact" class="form-label">Contact Info</label>
                    <input type="text" class="form-control" id="contact" name="contact" required placeholder="Enter phone or email">
                </div>
                <button type="submit" class="btn btn-primary">Create Branch</button>
                <a href="branch.php" class="btn btn-secondary">Back to Branch Page</a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
