<?php
session_start();
include "../includes/db.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Fetch user from database
    $query = "SELECT id, username, password, role, `branch-id` FROM users WHERE username = ?";
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
        $_SESSION['branch_id'] = $user['branch-id'];

        // ✅ If role is staff or manager, insert into employees table if not exists
        if ($_SESSION['role'] === 'staff' || $_SESSION['role'] === 'manager') {
            $checkEmployee = $conn->prepare("SELECT id FROM employees WHERE `user-id` = ?");
            $checkEmployee->bind_param("i", $user['id']);
            $checkEmployee->execute();
            $checkEmployee->store_result();

            if ($checkEmployee->num_rows === 0) {
                $checkEmployee->close();

                $insertEmployee = $conn->prepare("INSERT INTO employees (`user-id`, `branch-id`, base_salary) VALUES (?, ?, ?)");
                $defaultSalary = 0.00; // ✅ You can set default salary here
                $insertEmployee->bind_param("iid", $user['id'], $user['branch-id'], $defaultSalary);
                $insertEmployee->execute();
                $insertEmployee->close();
            } else {
                $checkEmployee->close();
            }
        }

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
  <title>Business System - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      padding: 0;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .login-card {
      background: #fff;
      border-radius: 20px;
      padding: 2.5rem;
      box-shadow: 0 10px 35px rgba(0,0,0,0.2);
      max-width: 420px;
      width: 100%;
    }
    .login-header {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .form-control { border-radius: 50px; padding: 0.7rem 1rem; }
    .btn-corporate {
      background: linear-gradient(90deg, #1e3c72, #2a5298);
      color: #fff;
      font-weight: 600;
      border-radius: 50px;
      padding: 0.7rem;
      transition: all 0.3s ease;
    }
    .btn-corporate:hover {
      background: linear-gradient(90deg, #2a5298, #1e3c72);
      transform: scale(1.03);
      box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }
    .footer-text { text-align: center; margin-top: 1.2rem; font-size: 0.85rem; color: #6c757d; }
  </style>
</head>
<body>

  <div class="login-card">
    <div class="login-header">
      <img src="../uploads/logo.png" alt="Logo" width="70">
      <h3>Business System</h3>
      <p>Secure Login Portal</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger text-center py-2">
        <?= htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
      <div class="mb-3">
        <label for="username" class="form-label fw-semibold">Username</label>
        <input type="text" class="form-control" id="username" name="username" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label fw-semibold">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <button type="submit" name="login" class="btn btn-corporate w-100">Login</button>
    </form>

    <div class="text-center mt-3">
      <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
    </div>

    <div class="footer-text">
      © <?= date("Y"); ?> Business System. All Rights Reserved.
    </div>
  </div>

</body>
</html>
