<?php
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}
$role = $_SESSION['role'];
?>
<div class="d-flex">
  <div class="sidebar bg-dark text-white p-3 shadow-lg" style="width: 250px; min-height: 100vh; border-top-right-radius: 9px; border-bottom-right-radius: 12px;">
    <h4 class="text-left mb-4 fw-bold text-primary">Dashboard</h4>
    <ul class="nav flex-column">
      <?php if ($role == 'admin' || $role == 'manager') : ?>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/branch.php">
            <i class="fa-solid fa-building me-2"></i> Branches
          </a>
        </li>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/edit_product.php">
            <i class="fa-solid fa-box me-2"></i> Edit Product
          </a>
        </li>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/expense.php">
            <i class="fa-solid fa-wallet me-2"></i> Expenses
          </a>
        </li>
      <?php endif; ?>

      <?php if ($role == 'admin' || $role == 'manager' || $role == 'staff') : ?>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/product.php">
            <i class="fa-solid fa-cubes me-2"></i> Products
          </a>
        </li>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/sales.php">
            <i class="fa-solid fa-cart-shopping me-2"></i> Sales
          </a>
        </li>
      <?php endif; ?>

      <?php if ($role == 'admin' || $role == 'manager') : ?>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/report.php">
            <i class="fa-solid fa-chart-line me-2"></i> Reports
          </a>
        </li>
      <?php endif; ?>

      <?php if ($role == 'admin') : ?>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/admin_dashboard.php">
            <i class="fa-solid fa-crown me-2"></i> Admin Dashboard
          </a>
        </li>
      <?php elseif ($role == 'manager') : ?>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/manager_dashboard.php">
            <i class="fa-solid fa-crown me-2"></i> Manager Dashboard
          </a>
        </li>
      <?php elseif ($role == 'staff') : ?>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/staff_dashboard.php">
            <i class="fa-solid fa-crown me-2"></i> Staff Dashboard
          </a>
        </li>
      <?php endif; ?>

      <li class="nav-item mt-4">
        <a class="nav-link text-danger fw-bold d-flex align-items-center hover-logout" href="../auth/logout.php">
          <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
      </li>
    </ul>
  </div>

  <!-- Main Content -->
  <div class="flex-grow-1 p-4">
