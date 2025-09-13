<?php
ob_start();
include '../includes/auth.php';
require_role(['admin', 'manager']);
include '../includes/db.php';
include '../pages/sidebar.php';
include '../includes/header.php';

// Fetch branch info
$branch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$branch = null;
if ($branch_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM branch WHERE id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $branch = $stmt->get_result()->fetch_assoc();
}

$message = "";

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $branch_id > 0) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $contact = trim($_POST['contact']);

    if ($name && $location && $contact) {
        $stmt = $conn->prepare("UPDATE branch SET name=?, location=?, contact=? WHERE id=?");
        $stmt->bind_param("sssi", $name, $location, $contact, $branch_id);
        if ($stmt->execute()) {
            $message = "Branch updated successfully!";
            // Refresh branch info
            $branch = ['name' => $name, 'location' => $location, 'contact' => $contact];
        } else {
            $message = "Failed to update branch.";
        }
    } else {
        $message = "All fields are required.";
    }
}
?>

<style>
.card {
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s ease-in-out;
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
</style>

<div class="container mt-5">
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">Edit Branch</div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert <?= $message_class ?>"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($branch): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Branch Name</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($branch['name']) ?>">
                </div>
                <div class="mb-3">
                    <label for="location" class="form-label fw-semibold">Location</label>
                    <input type="text" class="form-control" id="location" name="location" required value="<?= htmlspecialchars($branch['location']) ?>">
                </div>
                <div class="mb-3">
                    <label for="contact" class="form-label fw-semibold">Contact</label>
                    <input type="text" class="form-control" id="contact" name="contact" required value="<?= htmlspecialchars($branch['contact']) ?>">
                </div>
                <button type="submit" class="btn btn-primary">Update Branch</button>
                <a href="list_branches.php" class="btn btn-secondary ms-2">Back</a>
            </form>
            <?php else: ?>
                <div class="alert alert-warning">Branch not found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
