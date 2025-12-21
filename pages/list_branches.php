<?php
include '../includes/auth.php';
require_role(['admin', 'manager']);
include '../pages/sidebar.php';
include '../includes/header.php';
include '../includes/db.php';

// FIXED: Remove business_id check - show all branches for admin/manager
$user_role = $_SESSION['role'];

// Fetch all branches (admin/manager can see all)
$sql = "SELECT id, name, location, contact 
        FROM branch 
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);
?>

<style>
/* Add Branch Button */
.add-branch-btn {
    background: var(--primary-color) !important;
    color: #fff !important;
    border: none;
    font-weight: 600;
    padding: 0.5rem 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(44,62,80,0.08);
    transition: background 0.2s;
}
.add-branch-btn:hover, .add-branch-btn:focus {
    background: #159c8c !important;
    color: #fff !important;
}

/* Table styling like admin_dashboard */
.transactions-table table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px var(--card-shadow);
}
.transactions-table thead {
    background: var(--primary-color);
    color: #fff;
    text-transform: uppercase;
    font-size: 13px;
}
.transactions-table tbody td {
    color: var(--text-color);
    padding: 0.75rem 1rem;
}
.transactions-table tbody tr {
    background-color: #fff;
    transition: background 0.2s;
}
.transactions-table tbody tr:nth-child(even) {
    background-color: #f4f6f9;
}
.transactions-table tbody tr:hover {
    background-color: rgba(0,0,0,0.05);
}
body.dark-mode .transactions-table table {
    background: var(--card-bg);
}
body.dark-mode .transactions-table thead {
    background-color: #1abc9c;
    color: #ffffff;
}
body.dark-mode .transactions-table tbody tr {
    background-color: #2c2c3a !important;
}
body.dark-mode .transactions-table tbody tr:nth-child(even) {
    background-color: #272734 !important;
}
body.dark-mode .transactions-table tbody td {
    color: #ffffff !important;
}
body.dark-mode .transactions-table tbody td .text-muted {
    color: #cccccc !important;
}
body.dark-mode .transactions-table tbody tr:hover {
    background-color: rgba(255,255,255,0.1) !important;
}

/* Action buttons */
.btn-info, .btn-warning, .btn-danger {
    border-radius: 6px;
    font-size: 13px;
    padding: 5px 12px;
}
body.dark-mode .btn-info, body.dark-mode .btn-warning, body.dark-mode .btn-danger {
    color: #fff !important;
}
</style>

<div class="container-fluid mt-5">
    <div class="d-flex justify-content-end align-items-center mb-4">
        <a href="create_branch.php" class="btn add-branch-btn">+ Add Branch</a>
    </div>

    <!-- Success message after deletion -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success text-center">Branch deleted successfully.</div>
    <?php endif; ?>

    <!-- Responsive Table Card for Small Devices -->
    <div class="d-block d-md-none mb-4" >
      <div class="card transactions-card" style="border-left: 4px solid teal;" >
        <div class="card-body">
          <div class="table-responsive-sm">
            <div class="transactions-table">
              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Branch Name</th>
                    <th>Location</th>
                    <th>Contact</th>
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
                      <td><?= htmlspecialchars($row['contact'] ?? 'N/A'); ?></td>
                      <td>
                        <a href="branch.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-info" title="View">
                          <i class="fa fa-eye"></i>
                        </a>
                        <a href="branch_edit.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                          <i class="fa fa-edit"></i>
                        </a>
                        <a href="branch_delete.php?id=<?= $row['id']; ?>" 
                           class="btn btn-sm btn-danger"
                           title="Delete"
                           onclick="return confirm('Are you sure you want to delete this branch?');">
                           <i class="fa fa-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted">No branches found</td>
                  </tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table for medium and large devices -->
    <div class="transactions-table d-none d-md-block"  >
      <div class="card transactions-card" style="border-left: 4px solid teal;" >
        <table >
            <thead>
                <tr>
                    <th>#</th>
                    <th>Branch Name</th>
                    <th>Location</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Reset result pointer for large devices table
            mysqli_data_seek($result, 0);
            if (mysqli_num_rows($result) > 0):
                while ($row = mysqli_fetch_assoc($result)):
            ?>
                <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= htmlspecialchars($row['name']); ?></td>
                    <td><?= htmlspecialchars($row['location']); ?></td>
                    <td><?= htmlspecialchars($row['contact'] ?? 'N/A'); ?></td>
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
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">No branches found</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
