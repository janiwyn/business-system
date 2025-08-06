<?php


if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
?>

<!-- Sidebar -->
<div class="d-flex">
    <!-- Sidebar -->
    <div class="bg-info text-white p-3" style="width: 250px; min-height: 100vh;">
        <h4 class="text-center mb-4">Business System</h4>
        <ul class="nav flex-column">

            <?php if ($role == 'admin' || $role == 'manager') : ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/branch.php">Branches</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/edit_product.php">Edit Product</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/expense.php">Expenses</a>
                </li>
            <?php endif; ?>

            <?php if ($role == 'admin' || $role == 'manager' || $role == 'staff') : ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/product.php"> Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/sales.php"> Sales</a>
                </li>
            <?php endif; ?>

            <?php if ($role == 'admin' || $role == 'manager') : ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/report.php"> Reports</a>
                </li>
            <?php endif; ?>

            <?php if ($role == 'admin') : ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/admin_dashboard.php"> Admin Dashboard</a>
                </li>
            <?php elseif ($role == 'manager') : ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/manager_dashboard.php"> Manager Dashboard</a>
                </li>
            <?php elseif ($role == 'staff') : ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../pages/staff_dashboard.php"> Staff Dashboard</a>
                </li>
            <?php endif; ?>

            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="../auth/logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content Placeholder -->
    <div class="flex-grow-1 p-3">
