<?php
include '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    $Username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $comfirm_pasword = $_POST['confirm_password'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];

    if (!empty($Username) && !empty($email) && !empty($password) && !empty($comfirm_pasword) && !empty($phone) && !empty($role)) {
        
        if ($password !== $comfirm_pasword) {
            echo "Passwords do not match";
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        //$hash = password_hash($password, PASSWORD_BCRYPT);


        $sql = $conn->prepare("INSERT INTO users (username, email, password, phone, role)
                VALUES (?,?,?,?,?)");
        $sql->bind_param("sssss", $Username, $email, $hash, $phone, $role);
        

        if ($sql->execute() === TRUE) {
            echo "Registration successful";
        } else {
            echo "Database error: " . mysqli_error($conn);
        }
    } else {
        echo "All fields are required";
    }
}
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
</head>
<body>
<div class="container mt-5 w-50">
    <h2>Sign Up</h2>
    <form action="signup.php" method="POST" >
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" class="form-control mb-3" id="email" name="email" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control mb-3" id="password" name="password" required>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" class="form-control mb-3" id="confirm_password" name="confirm_password" required>
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="tel" class="form-control mb-3" id="phone" name="phone" required>
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role" required>
                <option value="" disabled selected>Select your role</option>
                <option value="admin">Admin</option>
                <option value="staff">staff</option>
                <option value="manager">Manager</option>
            </select>
        </div>
        <button type="submit" name="signup" class="btn btn-success">Sign Up</button>
        <a href="login.php" class="btn btn-primary">login</a>
    </form>
</div>
    
</body>
</html>