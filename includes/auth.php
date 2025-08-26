<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check that branch_id is set for non-admin users
// if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
//     if (!isset($_SESSION['branch_id'])) {
//         die("No branch assigned to this account. Contact the administrator.");
//     }
// }

// Improved role-based access control
function require_role($allowed_roles) {
    if (!isset($_SESSION['role'])) {
        header("Location: ../auth/login.php");
        exit;
    }

    $user_role = $_SESSION['role'];

    // Handle both string and array input
    if (is_array($allowed_roles)) {
        if (!in_array($user_role, $allowed_roles)) {
            redirect_to_dashboard($user_role);
            exit;
        }
    } else {
        if ($user_role !== $allowed_roles) {
            redirect_to_dashboard($user_role);
            exit;
        }
    }
}

// Redirects user to their appropriate dashboard
function redirect_to_dashboard($role) {
    switch ($role) {
        case 'admin':
            header("Location: ../pages/admin_dashboard.php");
            break;
        case 'manager':
            header("Location: ../pages/manager_dashboard.php");
            break;
        case 'staff':
            header("Location: ../pages/staff_dashboard.php");
            break;
        default:
            header("Location: ../auth/login.php");
            break;
    }
    exit;
}
