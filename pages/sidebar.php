<?php
// ensure session started
if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if not authenticated
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];

// Include the correct sidebar for the current user role and stop further output here.
// Each sidebar_* file should output the sidebar HTML (as other pages expect).
if ($role === 'admin') {
    include __DIR__ . '/sidebar_admin.php';
    return;
} elseif ($role === 'manager') {
    include __DIR__ . '/sidebar_manager.php';
    return;
} elseif ($role === 'staff') {
    include __DIR__ . '/sidebar_staff.php';
    return;
}

// Fallback (should not be reached): include admin sidebar
include __DIR__ . '/sidebar_admin.php';
return;
?>
<?php if ($role == 'admin' || $role == 'manager') : ?>
 <li class="nav-item mb-2">
        <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/branch.php">
          <i class="fa-solid fa-building me-2"></i> Branches
        </a>
      </li>
         <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/list_branches.php">
            <i class="fa-solid fa-building me-2"></i> List Branches
          </a>
        </li>
        <li class="nav-item mb-2">
          <a class="nav-link text-white d-flex align-items-center hover-effect" href="../pages/debtor.php">
            <i class="fa-solid fa-building me-2"></i> Debtors
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

</div>
<?php
// Add a navigation link to the Customer Management page in the admin/manager sidebar.
if ($role == 'admin' || $role == 'manager' || $role == 'staff') {
    echo '<li>
    <a href="customer_management.php" class="' . basename($_SERVER['PHP_SELF']) === 'customer_management.php' ? 'active' : '' . '">
        <i class="fa-solid fa-users"></i> Customer Management
    </a>
</li>';
}

// Add a navigation link to the Suppliers page for admin and manager users.
if ($role == 'admin' || $role == 'manager') {
    echo '<li>
    <a href="suppliers.php" class="' . basename($_SERVER['PHP_SELF']) === 'suppliers.php' ? 'active' : '' . '">
        <i class="fa-solid fa-truck"></i> Suppliers
    </a>
</li>';
}
?>
