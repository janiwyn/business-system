<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin"]);
include '../pages/sidebar_admin.php'; // Use admin sidebar
include '../includes/header.php';

$message = "";
$message_class = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $location = trim($_POST["location"]);
    $contact = trim($_POST["contact"]);
    $branchKey = trim($_POST["branch-key"] ?? "");

    if (!empty($name) && !empty($location) && !empty($contact) && !empty($branchKey)) {
        // Prepare the SQL query with proper comparison operator and parameter placeholder
        $sql = "SELECT name, location FROM branch WHERE name = ? AND location = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $name, $location);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $message = "Branch Already Exists!!";
            $message_class = "alert-danger";
        }else{
            $stmt = $conn->prepare("INSERT INTO branch (name, location, contact, `branch-key`) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $location, $contact, $branchKey);

            if ($stmt->execute()) {
                $message = "Branch created successfully!";
                $message_class = "alert-success";

                // Redirect back to branch.php with new branch ID
                $new_branch_id = $stmt->insert_id;
                header("Location: list_branches.php");
                exit;
            } else {
                $message = "Failed to create branch. Try again.";
                $message_class = "alert-danger";
            } 
        }

        
    } else {
        $message = "All fields are required.";
        $message_class = "alert-warning";
    }
}
?>

<style>
/* Match Add Product Form styling */
.card {
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s ease-in-out;
}
.card:hover {
    transform: translateY(-2px);
}
.card-header {
    font-weight: 600;
    background: var(--primary-color);
    color: #fff !important;
    border-radius: 12px 12px 0 0 !important;
    font-size: 1.1rem;
    letter-spacing: 1px;
}
body.dark-mode .card-header {
    background-color: #2c3e50 !important;
    color: #fff !important;
}
.form-control, .form-select {
    border-radius: 8px;
}
body.dark-mode .form-label,
body.dark-mode label,
body.dark-mode .card-body {
    color: #fff !important;
}
body.dark-mode .form-control,
body.dark-mode .form-select {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .form-control:focus,
body.dark-mode .form-select:focus {
    background-color: #23243a !important;
    color: #fff !important;
}
.btn-primary {
    background: var(--primary-color) !important;
    border: none;
    border-radius: 8px;
    padding: 8px 18px;
    font-weight: 600;
    box-shadow: 0px 3px 8px rgba(0,0,0,0.2);
    color: #fff !important;
    transition: background 0.2s;
}
.btn-primary:hover, .btn-primary:focus {
    background: #159c8c !important;
    color: #fff !important;
}
.btn-secondary {
    border-radius: 8px;
    font-weight: 500;
}
</style>

<div class="container mt-5">
    <h2 class="mb-4" style="color:var(--primary-color);font-weight:700;">Create New Branch</h2>

    <?php if ($message): ?>
        <div class="alert <?= $message_class ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Branch Details</div>
        <div class="card-body">
            <form method="POST" action="create_branch.php">
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Branch Name</label>
                    <input type="text" class="form-control" id="name" name="name" required placeholder="Enter branch name">
                </div>
                <div class="mb-3">
                    <label for="location" class="form-label fw-semibold">Location</label>
                    <input type="text" class="form-control" id="location" name="location" required placeholder="Enter location">
                </div>
                <div class="mb-3">
                    <label for="contact" class="form-label fw-semibold">Contact Info</label>
                    <input type="text" class="form-control" id="contact" name="contact" required placeholder="Enter phone or email">
                </div>
                <div class="mb-3">
                    <label for="branch-key" class="form-label fw-semibold">Branch Key</label>
                    <input type="password" class="form-control" id="branch-key" name="branch-key" required placeholder="Enter the Branch's Key">
                </div>
                <button type="submit" class="btn btn-primary">Create Branch</button>
                <a href="branch.php" class="btn btn-secondary">Back to Branch Page</a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
