<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/header.php';
include '../pages/super_sidebar.php';

if (!isset($_GET['id'])) {
    die("<div class='alert alert-danger m-3'>Business ID not provided.</div>");
}

$id = (int) $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    $stmt = $conn->prepare("UPDATE businesses SET name=?, email=?, phone=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $email, $phone, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Business updated successfully!'); window.location.href='manage_business.php';</script>";
    } else {
        echo "<div class='alert alert-danger m-3'>Error updating business.</div>";
    }
}

$stmt = $conn->prepare("SELECT * FROM businesses WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$business = $result->fetch_assoc();

if (!$business) {
    die("<div class='alert alert-warning m-3'>Business not found.</div>");
}
?>

<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Edit Business</h4>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Business Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($business['name']) ?>" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($business['email']) ?>" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($business['phone']) ?>" class="form-control">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-success">💾 Save Changes</button>
                    <a href="manage_business.php" class="btn btn-secondary">← Back</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
