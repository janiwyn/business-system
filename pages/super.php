<?php
session_start();
include '../includes/db.php';

// Optional: Check if logged in as super admin
if(!isset($_SESSION['super_admin'])) {
    header("Location: login.php");
    exit;
}

// Function to generate user tables
function generate_user_table($conn, $role){
    $query = "SELECT * FROM users WHERE role='$role'";
    $result = $conn->query($query);

    echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
    echo '<thead><tr>
            <th>ID</th><th>Username</th><th>Email</th>
            <th>Status</th><th>Last Login</th><th>Actions</th>
          </tr></thead><tbody>';

    while($row = $result->fetch_assoc()){
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['username']}</td>
                <td>{$row['email']}</td>
                <td>{$row['status']}</td>
                <td>{$row['last_login']}</td>
                <td>
                    <a href='edit_user.php?id={$row['id']}' class='btn btn-sm btn-primary'>Edit</a>
                    <a href='delete_user.php?id={$row['id']}' class='btn btn-sm btn-danger'>Delete</a>
                </td>
              </tr>";
    }

    echo '</tbody></table></div>';
}

// Function to show error logs
function generate_error_logs($conn){
    $query = "SELECT * FROM error_logs ORDER BY created_at DESC LIMIT 100";
    $result = $conn->query($query);

    echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
    echo '<thead><tr>
            <th>ID</th><th>User</th><th>Error Message</th>
            <th>Page</th><th>Time</th>
          </tr></thead><tbody>';

    while($row = $result->fetch_assoc()){
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['username']}</td>
                <td>{$row['error_message']}</td>
                <td>{$row['page']}</td>
                <td>{$row['created_at']}</td>
              </tr>";
    }

    echo '</tbody></table></div>';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Super Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Super Admin Dashboard</h2>
    <ul class="nav nav-tabs mt-4" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="admins-tab" data-bs-toggle="tab" data-bs-target="#admins" type="button" role="tab">Admins</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="managers-tab" data-bs-toggle="tab" data-bs-target="#managers" type="button" role="tab">Managers</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab">Staff</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">Error Logs</button>
        </li>
    </ul>

    <div class="tab-content mt-3" id="dashboardTabsContent">
        <!-- Admins Tab -->
        <div class="tab-pane fade show active" id="admins" role="tabpanel">
            <?php generate_user_table($conn, 'admin'); ?>
        </div>
        <!-- Managers Tab -->
        <div class="tab-pane fade" id="managers" role="tabpanel">
            <?php generate_user_table($conn, 'manager'); ?>
        </div>
        <!-- Staff Tab -->
        <div class="tab-pane fade" id="staff" role="tabpanel">
            <?php generate_user_table($conn, 'staff'); ?>
        </div>
        <!-- Error Logs Tab -->
        <div class="tab-pane fade" id="logs" role="tabpanel">
            <?php generate_error_logs($conn); ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
