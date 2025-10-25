<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/header.php';
include '../pages/super_sidebar.php'; // Make sure sidebar is included
require_role(["super"]);

if (!isset($_GET['id'])) {
    die("<div class='alert alert-danger m-3'>No admin selected.</div>");
}

$id = (int)$_GET['id'];
$result = $conn->prepare("SELECT * FROM users WHERE id = ?");
$result->bind_param("i", $id);
$result->execute();
$admin = $result->get_result()->fetch_assoc();

if (!$admin) {
    die("<div class='alert alert-warning m-3'>Admin not found.</div>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET email=?, role=? WHERE id=?");
    $stmt->bind_param("ssi", $email, $role, $id);
    $stmt->execute();

    echo "<div class='alert alert-success m-3'>✅ Admin updated successfully!</div>";
}
?>

<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Edit Admin</h4>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select">
                        <option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="manager" <?= $admin['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                    </select>
                </div>

                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-success">💾 Save Changes</button>
                    <a href="manage_admin.php" class="btn btn-secondary">← Back</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
