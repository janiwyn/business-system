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
    <div class="sidebar-title">Super Dashboard</div>
    <ul class="sidebar-nav">
        <li><a href="admin_dashboard.php">Admin</a></li>
        <li><a href="manager_dashboard.php">Manager</a></li>
        <li><a href="staff_dashboard.php">Staff</a></li>
        <li><a href="manage_business.php">Manage Businesses</a></li>
        <li><a href="manage_admin.php">Manage Admins</a></li>
        <li><a href="add_admin.php">Add Admins</a></li>
        <li><a href="subscription.php">Subscription</a></li>
        <li><a href="super_report.php">Reports & Analytics</a></li>
        <li><a href="system_updates.php">System Updates</a></li>




    </ul>

</div>

<div class="main-container">
