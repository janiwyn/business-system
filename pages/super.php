<?php
include '../includes/db.php';
include_once '../includes/auth.php';
require_role(['super']);
include '../includes/header.php';
include 'manager_dashboard.php';


?>
<<div class="container-fluid">
  <h3 class="mt-4 mb-3">Super Admin Dashboard</h3>
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link <?= ($_GET['view'] ?? '') == 'boss' ? 'active' : '' ?>" href="?view=boss">Boss Dashboard</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($_GET['view'] ?? '') == 'manager' ? 'active' : '' ?>" href="?view=manager">Manager Dashboard</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($_GET['view'] ?? '') == 'sales' ? 'active' : '' ?>" href="?view=sales">Sales Dashboard</a>
    </li>
  </ul>

  <div class="card shadow border-0">
    <div class="card-body">
      <?php
      $view = $_GET['view'] ?? '';
      if ($view == 'boss') {
        include '../pages/admin_dashboard.php';
      } elseif ($view == 'manager') {
        include '../pages/manager_dashboard.php';
      } elseif ($view == 'sales') {
        include '../pages/staff_dashboard.php';
      } else {
        echo "<p>Select a dashboard above.</p>";
      }
      ?>
    </div>
  </div>
</div>

