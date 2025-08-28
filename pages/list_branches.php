<?php
include '../includes/auth.php';
require_role(['admin', 'manager']);
include '../includes/header.php';
include '../includes/db.php';

// Fetch all branches
$sql = "SELECT id, name, location, contact FROM branch ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Branches</h2>
        <a href="create_branch.php" class="btn btn-primary">+ Add Branch</a>
    </div>

    <!-- Success message after deletion -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success text-center">Branch deleted successfully.</div>
    <?php endif; ?>

    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Branch Name</th>
                <th>Location</th>
                <th>Manager</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= htmlspecialchars($row['name']); ?></td>
                    <td><?= htmlspecialchars($row['location']); ?></td>
                    <td><?= $row['manager'] ?? '<span class="text-muted">No Manager</span>'; ?></td>
                    <td>
                        <a href="branch.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-info">View</a>
                        <a href="branch_edit.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="branch_delete.php?id=<?= $row['id']; ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this branch?');">
                           Delete
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="text-center text-muted">No branches found</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
