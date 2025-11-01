<?php
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}
$role = $_SESSION['role'];
?>
<style>
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
    border-bottom: none !important;
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
    <div class="sidebar-title">Staff Dashboard</div>
    <ul class="sidebar-nav">
        <li><a href="../pages/staff_dashboard.php"><i class="fa-solid fa-crown"></i> Dashboard</a></li>
        <li><a href="../pages/product.php"><i class="fa-solid fa-cubes"></i> Products</a></li>
        <li><a href="../pages/sales.php"><i class="fa-solid fa-cart-shopping"></i> Sales</a></li>
        <li>
            <a href="customer_management.php" class="<?= basename($_SERVER['PHP_SELF']) === 'customer_management.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i> Customer Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="petty_cash.php?tab=transactions">
                <i class="fa-solid fa-coins"></i> Petty Cash
            </a>
        </li>
        <!-- <li><a href="../pages/debtor.php"><i class="fa-solid fa-users"></i> Debtors</a></li> -->
        <li style="margin-top:2rem;">
            <a href="../auth/logout.php" class="text-danger fw-bold"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </li>
    </ul>
</div>
<div class="main-container">
