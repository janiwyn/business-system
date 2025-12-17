<?php
if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit();
}
$role = $_SESSION['role'];
?>
<style>
/* Sidebar styling */
.sidebar {
    width: 250px;
    height: 100vh; /* Full viewport height */
    background: #2c3e50;
    color: #fff;
    padding: 1rem;
    position: fixed;
    top: 0; left: 0;
    z-index: 10;
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
    overflow-y: auto; /* Makes sidebar scrollable */
    overflow-x: auto; /* Enable horizontal scroll if needed */
    scrollbar-width: thin;
    scrollbar-color: #1abc9c #23243a;
}
/* Custom vertical scrollbar */
.sidebar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
    background: #23243a;
    border-radius: 8px;
}
/* Custom horizontal scrollbar */
.sidebar::-webkit-scrollbar:horizontal {
    height: 8px;
    background: #23243a;
    border-radius: 8px;
}
.sidebar::-webkit-scrollbar-thumb {
    background: linear-gradient(90deg, #1abc9c 0%, #56ccf2 100%);
    border-radius: 8px;
    min-height: 40px;
}
.sidebar::-webkit-scrollbar-thumb:horizontal {
    background: linear-gradient(90deg, #1abc9c 0%, #56ccf2 100%);
    border-radius: 8px;
}
.sidebar::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(90deg, #159c8c 0%, #3498db 100%);
}
.sidebar::-webkit-scrollbar-track {
    background: #23243a;
    border-radius: 8px;
}
.sidebar::-webkit-scrollbar-track:horizontal {
    background: #23243a;
    border-radius: 8px;
}
/* Firefox */
.sidebar {
    scrollbar-width: thin;
    scrollbar-color: #1abc9c #23243a;
}
body.dark-mode .sidebar {
    scrollbar-color:  #1abc9c #23243a;
}
body.dark-mode .sidebar::-webkit-scrollbar-thumb,
body.dark-mode .sidebar::-webkit-scrollbar-thumb:horizontal {
    background: linear-gradient(90deg, #1abc9c 0%, #1abc9c 100%);
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
    text-decoration: none;
}
.sidebar-nav li a i {
    margin-right: 0.5rem;
    font-size: 1.1rem;
}
.sidebar-nav li a:hover,
.sidebar-nav li a.active {
    background: var(--primary-color, #1abc9c);
    color: #fff;
}
.sidebar-nav li a.text-danger {
    color: #e74c3c !important;
}
.sidebar-nav li a.text-danger:hover {
    background: #e74c3c !important;
    color: #fff !important;
}
.sidebar-nav .collapse ul li a {
    padding-left: 2rem; /* indent submenu items */
}
</style>

<div class="sidebar">
    <div class="sidebar-title">Manager Dashboard</div>
    <ul class="sidebar-nav">
        <li><a href="../pages/manager_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
        <li><a href="../pages/branch.php"><i class="fa-solid fa-building"></i> Branches</a></li>
        <li><a href="../pages/list_branches.php"><i class="fa-solid fa-building"></i> List Branches</a></li>
        <li><a href="../pages/edit_product.php"><i class="fa-solid fa-box"></i> Edit Product</a></li>
        <li><a href="../pages/expense.php"><i class="fa-solid fa-wallet"></i> Expenses</a></li>
        <li><a href="../pages/product.php"><i class="fa-solid fa-cubes"></i> Products</a></li>
        <li><a href="../pages/sales.php"><i class="fa-solid fa-cart-shopping"></i> Sales</a></li>  
        <li><a href="../pages/report.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
        <li><a href="../pages/employees.php"><i class="fa-solid fa-cart-shopping"></i> Employees</a></li>
        <li><a href="../pages/accounting.php"><i class="fa-solid fa-briefcase"></i> Accounting</a></li>
        <li><a href="../pages/report.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
        <li><a href="../pages/payroll.php"><i class="fa-solid fa-money-check-dollar"></i> Payroll</a></li>
        <li><a href="../pages/suppliers.php"><i class="fa-solid fa-truck"></i> Suppliers</a></li>
        <li><a href="../pages/customer_management.php"><i class="fa-solid fa-users"></i> Customer Management</a></li>
        <li><a href="petty_cash.php"><i class="fa fa-money-bill"></i> Petty Cash</a></li>
        <li><a href="../pages/till_management.php"><i class="fa-solid fa-cash-register"></i> Till Management</a></li>
        <!-- Remote Orders Menu Items -->
        <li><a href="../pages/remote_orders.php"><i class="fa-solid fa-shopping-bag"></i> Remote Orders</a></li>
        <li><a href="../pages/qr_scanner.php"><i class="fa-solid fa-qrcode"></i> QR Scanner</a></li>
        <li><a href="../pages/payment_proofs.php"><i class="fa-solid fa-receipt"></i> Payment Proofs</a></li>
        <li style="margin-top:2rem;">
            <a href="../auth/logout.php" class="text-danger fw-bold"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </li>
    </ul>
</div>

<div class="main-container">
