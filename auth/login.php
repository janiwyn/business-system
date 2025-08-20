<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "../includes/db.php";



$error = "";
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $username = trim($_POST['username']);
//     $password = trim(($_POST['password']));

//     $hash = password_hash($password, PASSWORD_DEFAULT);
//    // $hash = password_hash($password, PASSWORD_BCRYPT);



    // Fetch user from database
//     $query = "SELECT id, username, password, role FROM users WHERE username = ?";
//     $stmt = $conn->prepare($query);
//     $stmt->bind_param("s", $username);
//     $stmt->execute();
//     $user = $stmt->get_result()->fetch_assoc();
//    // print_r($user);
   // print(password_verify($password, $user["password"]));

    // print($password);
    //print($hash);

    // $stmt->close();

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
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
$_SESSION['role'] = strtolower(trim($user['role']));

        // redirect based on role
        if ($_SESSION['role'] === 'admin') {
            header('Location: ../pages/admin_dashboard.php');
        } elseif ($_SESSION['role'] === 'manager') {
            header('Location: ../pages/manager_dashboard.php'); // make sure this filename is correct
        } elseif ($_SESSION['role'] === 'staff') {
            header('Location: ../pages/staff_dashboard.php');
        } else {
            $error = 'Unknown role';
        }
        var_dump($_SESSION['role']);
exit;

        
    } else {
        $error = 'Invalid username or password';
    }
}


    // Use prepared statement
    // $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    // if (!$stmt) {
    //     $error = "Database error: " . $conn->error;
    // } else {
    //     $stmt->bind_param("s", $username);
    //     if ($stmt->execute()) {
    //         $result = $stmt->get_result();
    //         if ($result && $result->num_rows === 1) {
    //             $user = $result->fetch_assoc();
    //             if (password_verify($password, $user['password'])) {
    //                 $_SESSION['user_id'] = $user['id'];
    //                 $_SESSION['role'] = $user['role'];
    //                 $_SESSION['name'] = $user['username'];

    //                 // Redirect based on role
    //                 if ($user['role'] === 'admin') {
    //                     header("Location: ../pages/admin_dashboard.php");   
    //                 } elseif ($user['role'] === 'manager') {
    //                     header("Location: ../pages/manage_dashboard.php");
    //                 } elseif ($user['role'] === 'branch') {
    //                     header("Location: ../pages/staff_dashboard.php");
    //                 } else {
    //                     $error = "Unknown role!";
    //                 }
    //                 exit;
    //             } else {
    //                 $error = "Wrong password";
    //             }
    //         } else {
    //             $error = "User not found";
    //         }
    //     } else {
    //         $error = "Query execution failed: " . $stmt->error;
    //     }
    //     $stmt->close();
    // }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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