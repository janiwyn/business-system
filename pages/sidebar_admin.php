<?php
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
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
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        border-radius: 0;
    }
}
</style>

<div class="sidebar">
    <div class="sidebar-title">Admin Dashboard</div>
    <ul class="sidebar-nav">
        <li><a href="../pages/admin_dashboard.php"><i class="fa-solid fa-crown"></i> Dashboard</a></li>
        <li><a href="../pages/branch.php"><i class="fa-solid fa-building"></i> Branches</a></li>
        <li><a href="../pages/list_branches.php"><i class="fa-solid fa-list"></i> List Branches</a></li>
        <li><a href="../pages/edit_product.php"><i class="fa-solid fa-box"></i> Edit Product</a></li>
        <li><a href="../pages/expense.php"><i class="fa-solid fa-wallet"></i> Expenses</a></li>
        <li><a href="../pages/product.php"><i class="fa-solid fa-cubes"></i> Products</a></li>
        <li><a href="../pages/sales.php"><i class="fa-solid fa-cart-shopping"></i> Sales</a></li>
        <li><a href="../pages/employees.php"><i class="fa-solid fa-users"></i> Employees</a></li>
        <!-- Accounting dropdown -->
        <li>
            <a class="d-flex justify-content-between align-items-center" 
               data-bs-toggle="collapse" 
               href="#accountingMenu" 
               role="button" 
               aria-expanded="false" 
               aria-controls="accountingMenu">
                💼 Accounting
                <i class="bi bi-caret-down-fill"></i>
            </a>
            <div class="collapse" id="accountingMenu">
                <ul class="nav flex-column">
                    <li><a href="../pages/add_account.php"><i class="fa-solid fa-plus"></i> Add Account</a></li>
                    <li><a href="../pages/add_transaction.php"><i class="fa-solid fa-plus"></i> Add Transaction</a></li>
                    <li><a href="../pages/ledger.php"><i class="fa-solid fa-plus"></i> Ledger</a></li>
                    <li><a href="../pages/trail_balance.php"><i class="fa-solid fa-plus"></i> Trial Balance</a></li>
                    <li><a href="../pages/add_cash_entry.php"><i class="fa-solid fa-plus"></i> Cash Entry</a></li>
                    <li><a href="../pages/cash_book.php"><i class="fa-solid fa-plus"></i> Cash Book</a></li>
                    <li><a href="../pages/income_statement.php"><i class="fa-solid fa-plus"></i> Income Statement</a></li>
                    <li><a href="../pages/balance_sheet.php"><i class="fa-solid fa-balance-scale"></i> Balance Sheet</a></li>
                </ul>
            </div>
        </li>

        <li><a href="../pages/report.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
        <li><a href="../pages/payroll.php"><i class="fa-solid fa-money-check-dollar"></i> Payroll</a></li>
        <li style="margin-top:2rem;">
            <a href="../auth/logout.php" class="text-danger fw-bold"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </li>
    </ul>
</div>

<div class="main-container">
