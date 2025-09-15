<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Add or Update Employee
if (isset($_POST['add_employee'])) {
    $user_id    = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
    $name       = mysqli_real_escape_string($conn, $_POST['name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $phone      = mysqli_real_escape_string($conn, $_POST['phone']);
    $branch_id  = intval($_POST['branch_id']);
    $position   = mysqli_real_escape_string($conn, $_POST['position']);
    $base_salary= floatval($_POST['base_salary']);
    $hire_date  = $_POST['hire_date'];
    $status     = $_POST['status'];

    if ($user_id) {
        // System user exists → UPDATE their details
        $sql = "UPDATE employees 
                SET `base_salary`='$base_salary', `branch-id`='$branch_id', `position`='$position', `status`='$status'
                WHERE `user-id` = $user_id";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_affected_rows($conn) > 0) {
            echo "<script>alert('Employee details updated successfully!'); window.location='employees.php';</script>";
        } else {
            // If not found, insert new record for system user
            $sql = "INSERT INTO employees (`user-id`, `name`, `email`, `phone`, `branch-id`, `position`, `base_salary`, `hire_date`, `status`)
                    VALUES ($user_id, '$name','$email','$phone','$branch_id','$position','$base_salary','$hire_date','$status')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                echo "<script>alert('Employee added successfully!'); window.location='employees.php';</script>";
            } else {
                die("Error adding employee: " . mysqli_error($conn));
            }
        }
    } else {
        // Not a system user → INSERT new employee
        $sql = "INSERT INTO employees (`user-id`, `name`, `email`, `phone`, `branch-id`, `position`, `base_salary`, `hire_date`, `status`)
                VALUES (NULL, '$name','$email','$phone','$branch_id','$position','$base_salary','$hire_date','$status')";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            echo "<script>alert('Employee added successfully!'); window.location='employees.php';</script>";
        } else {
            die("Error adding employee: " . mysqli_error($conn));
        }
    }
}

// Delete Employee
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM employees WHERE id=$id");
    echo "<script>alert('Employee deleted!'); window.location='employees.php';</script>";
}
?>

<body class="bg-light">
<div class="container mt-4">
    <h3 class="mb-3">Employee Management</h3>

    <!-- Add/Update Employee Form -->
    <div class="card mb-4">
        <div class="card-header">Add or Update Employee</div>
        <div class="card-body">
            <form method="POST">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label>System User (Optional)</label>
                        <select name="user_id" class="form-control" id="user-select">
                            <option value="">-- Not a system user --</option>
                            <?php
                            $users = mysqli_query($conn, "SELECT id, username FROM users ORDER BY username ASC");
                            while ($u = mysqli_fetch_assoc($users)) {
                                echo "<option value='{$u['id']}'>{$u['username']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label>Branch</label>
                        <select name="branch_id" class="form-control" required>
                            <option value="">-- Select Branch --</option>
                            <?php
                            $branches = mysqli_query($conn, "SELECT id, name FROM branch");
                            while ($b = mysqli_fetch_assoc($branches)) {
                                echo "<option value='{$b['id']}'>{$b['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Position</label>
                        <input type="text" name="position" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label>Base Salary</label>
                        <input type="number" step="0.01" name="base_salary" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Hire Date</label>
                        <input type="date" name="hire_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="add_employee" class="btn btn-primary" id="employee-btn">Add Employee</button>
            </form>
        </div>
    </div>

    <!-- Employee List -->
    <div class="card">
        <div class="card-header">Employee List</div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>System User</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Position</th>
                        <th>Base Salary</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $employees = mysqli_query($conn, "
                    SELECT e.*, b.name as branch_name, u.username as system_user
                    FROM employees e
                    LEFT JOIN branch b ON e.`branch-id` = b.id
                    LEFT JOIN users u ON e.`user-id` = u.id
                    ORDER BY e.id DESC
                ");
                while ($row = mysqli_fetch_assoc($employees)) {
                    echo "<tr>
                        <td>" . ($row['system_user'] ?? 'N/A') . "</td>
                        <td>{$row['name']}</td>
                        <td>{$row['email']}</td>
                        <td>{$row['phone']}</td>
                        <td>{$row['branch_name']}</td>
                        <td>{$row['position']}</td>
                        <td>{$row['base_salary']}</td>
                        <td>{$row['hire_date']}</td>
                        <td>{$row['status']}</td>
                        <td>
                            <a href='employees.php?delete={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this employee?\")'>Delete</a>
                        </td>
                    </tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Change button text dynamically
const userSelect = document.getElementById('user-select');
const btn = document.getElementById('employee-btn');

userSelect.addEventListener('change', () => {
    btn.textContent = userSelect.value ? 'Update Employee Salary' : 'Add Employee';
});
</script>

</body>
</html>
