<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Improved role-based access control
function require_role($allowed_roles) {
    // Support a single role or array of roles
    if (!isset($_SESSION['role'])) {
        header("Location: ../auth/login.php");
        exit;
    }

    $user_role = $_SESSION['role'];

    if (is_array($allowed_roles)) {
        // if (!in_array($user_role, $allowed_roles)) {
        //     // Redirect to user's dashboard instead of login
        //     redirect_to_dashboard($user_role);
        //     exit;
        // }
    } else {
        // if ($user_role !== $allowed_roles) {
        //     redirect_to_dashboard($user_role);
        //     exit;
        // }
    }
}

// Redirects user to their appropriate dashboard
// function redirect_to_dashboard($role) {
//     switch ($role) {
//         case 'admin':
//             header("Location: ../pages/admin_dashboard.php");
//             break;
//         case 'manager':
//             header("Location: ../pages/manager_dashboard.php");
//             break;
//         case 'staff':
//             header("Location: ../pages/staff_dashboard.php");
//             break;
//         default:
//             header("Location: ../auth/login.php");
//             break;
//     }
// }
?>
