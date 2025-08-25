<?php
include '../includes/auth.php';
require_role(['admin', 'manager']);
include '../includes/header.php';

// branch_list.php
//session_start();
include '../includes/db.php';
// Fetch all branches
$sql = "SELECT b.id, b.name, b.location, b.created_at, u.name AS manager 
        FROM branch b
        LEFT JOIN users u ON b.manager_id = u.id
        ORDER BY b.id DESC";
$result = mysqli_query($conn, $sql);
?>


<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Branches</h2>
        <a href="branch_add.php" class="btn btn-primary">+ Add Branch</a>
    </div>

    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Branch Name</th>
                <th>Location</th>
                <th>Manager</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                    <td><?php echo $row['manager'] ?? '<span class="text-muted">No Manager</span>'; ?></td>
                    <td><?php echo date("d M Y", strtotime($row['created_at'])); ?></td>
                    <td>
                        <a href="branch_view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">View</a>
                        <a href="branch_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="branch_delete.php?id=<?php echo $row['id']; ?>" 
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
