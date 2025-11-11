<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["manager", "admin"]);

// Sidebar for admin/manager
if ($_SESSION['role'] === 'manager') {
    include '../pages/sidebar.php';
} elseif ($_SESSION['role'] === 'admin') {
    include '../pages/sidebar.php';
} else {
    include '../pages/sidebar_staff.php';
}
include '../includes/header.php';
?>

<link rel="stylesheet" href="assets/css/staff.css">

<div class="container mt-4">
    <div class="accounting-cards-grid">
        <!-- Add Account -->
        <div class="col-md-3">
            <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <a href="add_account.php" class="text-decoration-none">
                <div class="card shadow-sm border-0 h-100 hover-card">
                    <div class="card-body text-center">
                        <i class="fa-solid fa-plus fa-2x mb-3 text-primary"></i>
                        <h5 class="card-title">Add Account</h5>
                        <p class="card-text text-muted">Create a new account for your business.</p>
                    </div>
                </div>
            </a>
            </div>
        </div>

        <!-- Add Transaction -->
        <div class="col-md-3">
            <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <a href="add_transaction.php" class="text-decoration-none">
                <div class="card shadow-sm border-0 h-100 hover-card">
                    <div class="card-body text-center">
                        <i class="fa-solid fa-money-bill-wave fa-2x mb-3 text-success"></i>
                        <h5 class="card-title">Add Transaction</h5>
                        <p class="card-text text-muted">Record income or expenses.</p>
                    </div>
                </div>
            </a>
        </div>
        </div>

        <!-- Ledger -->
        <div class="col-md-3">
            <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <a href="ledger.php" class="text-decoration-none">
                <div class="card shadow-sm border-0 h-100 hover-card">
                    <div class="card-body text-center">
                        <i class="fa-solid fa-book fa-2x mb-3 text-info"></i>
                        <h5 class="card-title">Ledger</h5>
                        <p class="card-text text-muted">View detailed account transactions.</p>
                    </div>
                </div>
            </a>
            </div>
        </div>

        <!-- Trial Balance -->
        <div class="col-md-3">
            <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <a href="trail_balance.php" class="text-decoration-none">
                <div class="card shadow-sm border-0 h-100 hover-card">
                    <div class="card-body text-center">
                        <i class="fa-solid fa-balance-scale fa-2x mb-3 text-warning"></i>
                        <h5 class="card-title">Trial Balance</h5>
                        <p class="card-text text-muted">Check account balances quickly.</p>
                    </div>
                </div>
            </a>
            </div>
        </div>

        <!-- Cash Entry -->
        <div class="col-md-3"  >
            <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <a href="add_cash_entry.php" class="text-decoration-none">
                <div class="card shadow-sm border-0 h-100 hover-card">
                    <div class="card-body text-center">
                        <i class="fa-solid fa-cash-register fa-2x mb-3 text-secondary"></i>
                        <h5 class="card-title">Cash Entry</h5>
                        <p class="card-text text-muted">Record daily cash movements.</p>
                    </div>
                </div>
            </a>
            </div>
        </div>

        <!-- Cash Book -->
        <div class="col-md-3">
            <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <a href="cash_book.php" class="text-decoration-none">
                <div class="card shadow-sm border-0 h-100 hover-card">
                    <div class="card-body text-center">
                        <i class="fa-solid fa-book-open fa-2x mb-3 text-dark"></i>
                        <h5 class="card-title">Cash Book</h5>
                        <p class="card-text text-muted">View cash flow overview.</p>
                    </div>
                </div>
            </a>
            </div>
        </div>

        <!-- Income Statement -->
        <div class="col-md-3">
            <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <a href="income_statement.php" class="text-decoration-none">
                <div class="card shadow-sm border-0 h-100 hover-card">
                    <div class="card-body text-center">
                        <i class="fa-solid fa-file-invoice-dollar fa-2x mb-3 text-primary"></i>
                        <h5 class="card-title">Income Statement</h5>
                        <p class="card-text text-muted">Check your business profitability.</p>
                    </div>
                </div>
            </a>
            </div>
        </div>

        <!-- Balance Sheet -->
        <div class="col-md-3" >
            <div class="card transactions-card"  style="border-left: 4px solid teal;">
            <a href="balance_sheet.php" class="text-decoration-none" >
                <div class="card shadow-sm border-0 h-100 hover-card"  style="border-left: 4px solid teal;">
                    <div class="card-body text-center" >
                        <i class="fa-solid fa-balance-scale fa-2x mb-3 text-success"></i>
                        <h5 class="card-title">Balance Sheet</h5>
                        <p class="card-text text-muted">View assets, liabilities, and equity.</p>
                    </div>
                </div>
            </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
