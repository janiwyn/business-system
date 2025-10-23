<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);
include '../pages/super_sidebar.php';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Manage Businesses</h2>
        <a href="add_business.php" class="btn btn-primary">+ Add New Business</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Business Name</th>
                            <th>Admin Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Date Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM businesses ORDER BY date_registered DESC");

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $statusClass = $row['status'] === 'active' ? 'badge bg-success' : 'badge bg-danger';
                                echo "<tr>
                                        <td>{$row['id']}</td>
                                        <td>{$row['name']}</td>
                                        <td>{$row['admin_name']}</td>
                                        <td>{$row['email']}</td>
                                        <td>{$row['phone']}</td>
                                        <td>{$row['date_registered']}</td>
                                        <td><span class='{$statusClass}'>" . ucfirst($row['status']) . "</span></td>
                                        <td>
                                            <a href='view_business.php?id={$row['id']}' class='btn btn-sm btn-info'>View</a>
                                            <a href='edit_business.php?id={$row['id']}' class='btn btn-sm btn-warning text-white'>Edit</a>
                                            <a href='toggle_business.php?id={$row['id']}&status={$row['status']}' class='btn btn-sm btn-secondary'>"
                                            . ($row['status'] == 'active' ? 'Suspend' : 'Activate') . "</a>
                                        </td>
                                    </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center'>No businesses registered yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
