<?php
include '../includes/db.php';
include '../includes/header.php';
include '../includes/auth.php';

//session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : NULL;

    if (!empty($username) && !empty($password) && !empty($role)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, role, branch_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $username, $hashed_password, $role, $branch_id);
        
        if ($stmt->execute()) {
            echo " User created successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }
    } else {
        echo " Please fill in all required fields.";
    }
} else {
    echo " Invalid request.";
}
?>




<form action="create_user.php" method="POST">
    <label for="username"></label>
    <input type="text" name="username" required>

    <label for="password"></label>
    <input type="password" name="password" required>

    <label for="role"></label>
    <select name="role" required>
        <option value="admin">Admin</option>
        <option value="manager">Manager</option>
        <option value="staff">Staff</option>
    </select>
    <label>Assign to branch</label>
    <select name="branch_id">
        <option value="">None(Admin)</option>
        <?php
        $branches = $conn->query("SELECT id, name FROM branches");
        while ($branch = $branches->fetch_assoc()) {
            echo '<option value="' . $branch['id'] . '">' . $branch['name'] . '</option>';
        }
        ?>
    </select>
    <button type="submit" name="submit">Create User</button>
</form>

