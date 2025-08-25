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

    // Fetch user from database
    $query = "SELECT id, username, password, role FROM users WHERE username = ?";
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
      overflow: hidden;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Background graphics */
    .background-shapes {
      position: absolute;
      width: 100%;
      height: 100%;
      overflow: hidden;
      z-index: 0;
    }
    .shape {
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,0.05);
      animation: float 12s infinite ease-in-out;
    }
    .shape:nth-child(1) { width: 200px; height: 200px; top: -50px; left: -50px; }
    .shape:nth-child(2) { width: 300px; height: 300px; bottom: -80px; right: -80px; animation-duration: 18s; }
    .shape:nth-child(3) { width: 150px; height: 150px; top: 20%; right: 10%; animation-duration: 15s; }

    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0); }
      50% { transform: translateY(-20px) rotate(20deg); }
    }

    /* Login Card */
    .login-card {
      position: relative;
      z-index: 1;
      max-width: 420px;
      width: 100%;
      background: #fff;
      border-radius: 20px;
      padding: 2.5rem;
      box-shadow: 0 10px 35px rgba(0,0,0,0.2);
      animation: slideUp 0.8s ease;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(40px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .login-header {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .login-header img {
      width: 70px;
      margin-bottom: 0.5rem;
    }
    .login-header h3 {
      font-weight: 700;
      color: #203a43;
    }
    .login-header p {
      font-size: 0.9rem;
      color: #6c757d;
    }

    /* Inputs & button */
    .form-control {
      border-radius: 50px;
      padding: 0.7rem 1rem;
    }
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

    .footer-text {
      text-align: center;
      margin-top: 1.2rem;
      font-size: 0.85rem;
      color: #6c757d;
    }
  </style>
</head>
<body>

  <!-- background animated shapes -->
  <div class="background-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
  </div>

  <div class="login-card">
    <div class="login-header">
      <img src="../uploads/logo.png" alt="Logo">
      <h3>Business System</h3>
      <p>Secure Login Portal</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger text-center py-2">
        <?php echo htmlspecialchars($error); ?>
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
      <p>Dont have an account? <a href="signup.php">signup here</a></p>
    </div

    <div class="footer-text">
      © <?php echo date("Y"); ?> Business System. All Rights Reserved.
    </div>
  </div>

</body>
</html>



<!-- <!DOCTYPE html>
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