<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);
include '../pages/super_sidebar.php';
include '../includes/header.php';

$alertMsg = "";
$alertClass = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $admin_name = $_POST['admin_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $stmt = $conn->prepare("INSERT INTO businesses (name, admin_name, email, phone, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $admin_name, $email, $phone, $address);

    if ($stmt->execute()) {
        $alertMsg = "✅ Business added successfully!";
        $alertClass = "success";
    } else {
        $alertMsg = "❌ Error adding business: " . $conn->error;
        $alertClass = "danger";
    }
}
?>

<div class="container mt-4">
    <h2 class="mb-4">Add New Business</h2>

    <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertClass ?>"><?= htmlspecialchars($alertMsg) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">Business Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="admin_name" class="form-label">Admin Name</label>
                    <input type="text" name="admin_name" id="admin_name" class="form-control">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control">
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" name="phone" id="phone" class="form-control">
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" name="address" id="address" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary">Add Business</button>
                <a href="manage_business.php" class="btn btn-secondary ms-2">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
