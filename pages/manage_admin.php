<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);
include '../pages/super_sidebar.php';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Manage Business Admins</h2>
        <a href="add_admin.php" class="btn btn-primary">+ Add New Admin</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Admin Name</th>
                            <th>Email</th>
                            <th>Business</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Date Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "
                            SELECT u.id, u.username, u.email, u.role, u.status, u.created_at, b.name AS business_name
                            FROM users u
                            LEFT JOIN businesses b ON u.business_id = b.id
                            WHERE u.role = 'admin'
                            ORDER BY u.created_at DESC
                        ";
                        $result = $conn->query($query);

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $statusClass = $row['status'] === 'active' ? 'badge bg-success' : 'badge bg-danger';
                                echo "<tr>
                                        <td>{$row['id']}</td>
                                        <td>{$row['username']}</td>
                                        <td>{$row['email']}</td>
                                        <td>{$row['business_name']}</td>
                                        <td>{$row['role']}</td>
                                        <td><span class='{$statusClass}'>" . ucfirst($row['status']) . "</span></td>
                                        <td>{$row['created_at']}</td>
                                        <td>
                                            <a href='edit_admin.php?id={$row['id']}' class='btn btn-sm btn-warning text-white me-1 mb-1'>Edit</a>
                                            <a href='toggle_admin.php?id={$row['id']}&status={$row['status']}' class='btn btn-sm btn-secondary mb-1'>"
                                                . ($row['status'] == 'active' ? 'Deactivate' : 'Activate') . "</a>
                                        </td>
                                    </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center'>No admins found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
