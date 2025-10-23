<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../includes/header.php';
include '../pages/super_sidebar.php';

if (!isset($_GET['id'])) {
    die("<div class='alert alert-danger m-3'>Business ID not provided.</div>");
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()):
?>
<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Business Details</h4>
        </div>
        <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($row['name']) ?></p>
            <p><strong>Admin:</strong> <?= htmlspecialchars($row['admin_name']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($row['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($row['phone']) ?></p>
            <p><strong>Status:</strong> 
                <span class="badge <?= $row['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                    <?= ucfirst($row['status']) ?>
                </span>
            </p>
            <a href="manage_business.php" class="btn btn-secondary mt-3">← Back to List</a>
        </div>
    </div>
</div>
<?php
else:
    echo "<div class='alert alert-warning m-3'>Business not found.</div>";
endif;

include '../includes/footer.php';
?>
