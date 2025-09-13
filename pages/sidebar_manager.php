<?php
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}
$role = $_SESSION['role'];
?>
<style>
/* Sidebar styling to match sidebar_admin */
.sidebar {
    width: 250px;
    min-height: 100vh;
    background: #2c3e50;
    color: #fff;
    padding: 1rem;
    transition: width 0.3s ease;
    position: fixed;
    top: 0; left: 0;
    z-index: 10;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
}
.sidebar-title {
    text-align: center;
    margin-bottom: 1.5rem;
    font-weight: 700;
    font-size: 1.4rem;
    color: #1abc9c;
    letter-spacing: 1px;
}
.sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}
.sidebar-nav li {
    margin: 0.5rem 0;
}
.sidebar-nav li a {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 1rem;
    color: #fff;
    transition: background 0.2s, color 0.2s;
    gap: 0.5rem;
}
.sidebar-nav li a i {
    margin-right: 0.5rem;
    font-size: 1.1rem;
}
.sidebar-nav li a:hover,
.sidebar-nav li a.active {
    background: var(--primary-color, #1abc9c);
    color: #fff;
    text-decoration: none;
}
.sidebar-nav li a.text-danger {
    color: #e74c3c !important;
}
.sidebar-nav li a.text-danger:hover {
    background: #e74c3c !important;
    color: #fff !important;
}
@media (max-width: 768px) {
    .sidebar { width: 100%; min-height: auto; position: relative; border-radius: 0; }
}
</style>
<div class="sidebar">
    <div class="sidebar-title">Manager Dashboard</div>
    <ul class="sidebar-nav">
        <li><a href="../pages/branch.php"><i class="fa-solid fa-building"></i> Branches</a></li>
        <li><a href="../pages/list_branches.php"><i class="fa-solid fa-building"></i> List Branches</a></li>
        <li><a href="../pages/edit_product.php"><i class="fa-solid fa-box"></i> Edit Product</a></li>
        <li><a href="../pages/expense.php"><i class="fa-solid fa-wallet"></i> Expenses</a></li>
        <li><a href="../pages/product.php"><i class="fa-solid fa-cubes"></i> Products</a></li>
        <li><a href="../pages/sales.php"><i class="fa-solid fa-cart-shopping"></i> Sales</a></li>
        <li><a href="../pages/report.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
        <li><a href="../pages/manager_dashboard.php"><i class="fa-solid fa-crown"></i> Manager Dashboard</a></li>
        <li style="margin-top:2rem;">
            <a href="../auth/logout.php" class="text-danger fw-bold"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </li>
    </ul>
</div>
<div class="main-container">
