<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);
include '../pages/super_sidebar.php';
include '../includes/header.php';

$alertMsg = "";
$alertClass = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $business_id = $_POST['business_id'];
    $role = 'admin';
    $status = 'active';

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, business_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $username, $email, $password, $role, $status, $business_id);

    if ($stmt->execute()) {
        $alertMsg = "✅ Admin account added successfully!";
        $alertClass = "success";
    } else {
        $alertMsg = "❌ Error: " . $conn->error;
        $alertClass = "danger";
    }
}
?>

<div class="container mt-4"  style="border-left: 4px solid teal;">
    <h2 class="mb-4">Add New Admin</h2>

    <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertClass ?>"><?= htmlspecialchars($alertMsg) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Admin Name <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="business_id" class="form-label">Assign to Business <span class="text-danger">*</span></label>
                    <select name="business_id" id="business_id" class="form-select" required>
                        <option value="">-- Select Business --</option>
                        <?php
                        $businesses = $conn->query("SELECT id, name FROM businesses WHERE status='active'");
                        while ($b = $businesses->fetch_assoc()) {
                            echo "<option value='{$b['id']}'>{$b['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Add Admin</button>
                <a href="manage_admin.php" class="btn btn-secondary ms-2">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
