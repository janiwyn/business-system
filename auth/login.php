<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "../includes/db.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Fetch user (include branch_id)
    $query = "SELECT id, username, password, role, branch_id FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user["password"])) {
        // Store session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = strtolower(trim($user['role']));
        $_SESSION['branch_id'] = $user['branch_id']; // 👈 branch stored here

        // Redirect based on role
        if ($_SESSION['role'] === 'admin') {
            header('Location: ../pages/admin_dashboard.php');
        } elseif ($_SESSION['role'] === 'manager') {
            header('Location: ../pages/manager_dashboard.php');
        } elseif ($_SESSION['role'] === 'staff') {
            header('Location: ../pages/staff_dashboard.php');
        } else {
            $error = 'Unknown role';
        }
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5 w-50">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control mb-3" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn btn-success">Login</button>
        </form>
    </div>
</body>
</html>
