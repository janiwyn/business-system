<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff", "super"]);

// Fix: Always use the correct sidebar for staff
if ($_SESSION['role'] === 'staff') {
    include '../pages/sidebar_staff.php';
} else {
    include '../pages/sidebar.php';
}
include '../includes/header.php';

$user_branch = $_SESSION['branch_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'staff';
$today = date('Y-m-d');

// Fetch overdue shop debtors
$where_shop = ($user_role === 'staff' && $user_branch) 
    ? "WHERE d.branch_id = $user_branch AND d.due_date IS NOT NULL AND d.due_date <= '$today' AND d.is_paid = 0"
    : "WHERE d.due_date IS NOT NULL AND d.due_date <= '$today' AND d.is_paid = 0";
$shop_debtors = $conn->query("
    SELECT d.*, b.name as branch_name 
    FROM debtors d 
    LEFT JOIN branch b ON d.branch_id = b.id 
    $where_shop 
    ORDER BY d.due_date ASC 
    LIMIT 100
");

// Fetch overdue customer debtors
$where_cust = "WHERE ct.status = 'debtor' AND ct.due_date IS NOT NULL AND ct.due_date <= '$today'";
$customer_debtors = $conn->query("
    SELECT ct.*, c.name as customer_name, c.email as customer_email, c.contact as customer_contact
    FROM customer_transactions ct
    JOIN customers c ON ct.customer_id = c.id
    $where_cust
    ORDER BY ct.due_date ASC
    LIMIT 100
");

// NEW: Fetch low stock products (stock < 10)
$where_stock = ($user_role === 'staff' && $user_branch) 
    ? "WHERE p.`branch-id` = $user_branch AND p.stock < 10"
    : "WHERE p.stock < 10";
$low_stock_products = $conn->query("
    SELECT p.id, p.name, p.stock, p.`selling-price`, b.name as branch_name
    FROM products p
    LEFT JOIN branch b ON p.`branch-id` = b.id
    $where_stock
    ORDER BY p.stock ASC
    LIMIT 100
");
?>

<link rel="stylesheet" href="assets/css/staff.css">

<div class="container-fluid mt-4">
    <div class="card mb-4" style="border-left: 4px solid teal;">
        <div class="card-header">
            <i class="fa fa-bell"></i> Notifications
        </div>
        <div class="card-body">
            
            <!-- Shop Debtors Section -->
            <h5 class="mb-3"><i class="fa fa-store"></i> Shop Debtors (Overdue)</h5>
            <?php if ($shop_debtors && $shop_debtors->num_rows > 0): ?>
                <div class="list-group mb-4">
                    <?php while ($d = $shop_debtors->fetch_assoc()): ?>
                        <?php
                        $days_overdue = floor((strtotime($today) - strtotime($d['due_date'])) / 86400);
                        $urgency_class = ($days_overdue > 7) ? 'danger' : (($days_overdue > 3) ? 'warning' : 'info');
                        ?>
                        <div class="list-group-item list-group-item-action list-group-item-<?= $urgency_class ?> mb-2">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <strong><?= htmlspecialchars($d['debtor_name']) ?></strong>
                                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars($d['branch_name'] ?? 'Unknown Branch') ?></span>
                                    </h6>
                                    <p class="mb-1">
                                        <small>
                                            <i class="fa fa-calendar"></i> Due: <?= date('M d, Y', strtotime($d['due_date'])) ?>
                                            (<?= $days_overdue ?> day<?= $days_overdue != 1 ? 's' : '' ?> overdue)
                                        </small>
                                    </p>
                                    <p class="mb-0">
                                        <small><i class="fa fa-money-bill"></i> Balance: <strong>UGX <?= number_format($d['balance'], 2) ?></strong></small>
                                    </p>
                                </div>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-success snooze-btn" 
                                            data-type="shop" 
                                            data-id="<?= $d['id'] ?>" 
                                            title="Snooze for 1 day">
                                        <i class="fa fa-clock"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary clear-btn" 
                                            data-type="shop" 
                                            data-id="<?= $d['id'] ?>" 
                                            title="Clear notification">
                                        <i class="fa fa-times"></i>
                                    </button>
                                    <a href="sales.php#shop-debtor-<?= $d['id'] ?>" 
                                       class="btn btn-sm btn-primary" 
                                       title="View debtor">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted"><i class="fa fa-check-circle"></i> No overdue shop debtors.</p>
            <?php endif; ?>

            <!-- Customer Debtors Section -->
            <h5 class="mb-3 mt-4"><i class="fa fa-users"></i> Customer Debtors (Overdue)</h5>
            <?php if ($customer_debtors && $customer_debtors->num_rows > 0): ?>
                <div class="list-group mb-4">
                    <?php while ($cd = $customer_debtors->fetch_assoc()): ?>
                        <?php
                        $days_overdue = floor((strtotime($today) - strtotime($cd['due_date'])) / 86400);
                        $urgency_class = ($days_overdue > 7) ? 'danger' : (($days_overdue > 3) ? 'warning' : 'info');
                        ?>
                        <div class="list-group-item list-group-item-action list-group-item-<?= $urgency_class ?> mb-2">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <strong><?= htmlspecialchars($cd['customer_name']) ?></strong>
                                    </h6>
                                    <p class="mb-1">
                                        <small>
                                            <i class="fa fa-calendar"></i> Due: <?= date('M d, Y', strtotime($cd['due_date'])) ?>
                                            (<?= $days_overdue ?> day<?= $days_overdue != 1 ? 's' : '' ?> overdue)
                                        </small>
                                    </p>
                                    <p class="mb-0">
                                        <small><i class="fa fa-money-bill"></i> Balance: <strong>UGX <?= number_format($cd['amount_credited'], 2) ?></strong></small>
                                    </p>
                                </div>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-success snooze-btn" 
                                            data-type="customer" 
                                            data-id="<?= $cd['id'] ?>" 
                                            title="Snooze for 1 day">
                                        <i class="fa fa-clock"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary clear-btn" 
                                            data-type="customer" 
                                            data-id="<?= $cd['id'] ?>" 
                                            title="Clear notification">
                                        <i class="fa fa-times"></i>
                                    </button>
                                    <a href="sales.php#customer-debtor-<?= $cd['id'] ?>" 
                                       class="btn btn-sm btn-primary" 
                                       title="View debtor">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted"><i class="fa fa-check-circle"></i> No overdue customer debtors.</p>
            <?php endif; ?>

            <!-- NEW: Low Stock Products Section -->
            <h5 class="mb-3 mt-4"><i class="fa fa-box"></i> Low Stock Products (Stock &lt; 10)</h5>
            <?php if ($low_stock_products && $low_stock_products->num_rows > 0): ?>
                <div class="list-group mb-4">
                    <?php while ($prod = $low_stock_products->fetch_assoc()): ?>
                        <?php
                        $stock = intval($prod['stock']);
                        $urgency_class = ($stock < 3) ? 'danger' : (($stock < 6) ? 'warning' : 'info');
                        ?>
                        <div class="list-group-item list-group-item-action list-group-item-<?= $urgency_class ?> mb-2">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <strong><?= htmlspecialchars($prod['name']) ?></strong>
                                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars($prod['branch_name'] ?? 'Unknown Branch') ?></span>
                                    </h6>
                                    <p class="mb-1">
                                        <small>
                                            <i class="fa fa-cubes"></i> Stock: <strong class="text-danger"><?= $stock ?> remaining</strong>
                                        </small>
                                    </p>
                                    <p class="mb-0">
                                        <small><i class="fa fa-tag"></i> Price: UGX <?= number_format($prod['selling-price'], 2) ?></small>
                                    </p>
                                </div>
                                <div class="btn-group" role="group">
                                    <a href="product.php?highlight=<?= $prod['id'] ?>" 
                                       class="btn btn-sm btn-primary" 
                                       title="View product">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="edit_product.php?id=<?= $prod['id'] ?>" 
                                       class="btn btn-sm btn-warning" 
                                       title="Restock product">
                                        <i class="fa fa-plus"></i> Restock
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted"><i class="fa fa-check-circle"></i> All products have sufficient stock.</p>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
// Snooze notification (extend due date by 1 day)
document.querySelectorAll('.snooze-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const type = this.getAttribute('data-type');
        const id = this.getAttribute('data-id');
        
        if (!confirm('Snooze this notification for 1 day?')) return;
        
        const formData = new FormData();
        if (type === 'shop') {
            formData.append('set_due_date', '1');
            formData.append('debtor_id', id);
        } else {
            formData.append('set_customer_due_date', '1');
            formData.append('transaction_id', id);
        }
        
        // Extend due date by 1 day
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const dueDate = tomorrow.toISOString().split('T')[0];
        formData.append('due_date', dueDate);
        
        try {
            const res = await fetch('sales.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                alert('Notification snoozed for 1 day.');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to snooze'));
            }
        } catch (err) {
            console.error(err);
            alert('Error snoozing notification. Check console.');
        }
    });
});

// Clear notification (mark as handled)
document.querySelectorAll('.clear-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const type = this.getAttribute('data-type');
        const id = this.getAttribute('data-id');
        
        if (!confirm('Clear this notification? (This will mark it as handled)')) return;
        
        const formData = new FormData();
        if (type === 'shop') {
            formData.append('clear_shop_notification', '1');
            formData.append('debtor_id', id);
        } else {
            formData.append('clear_customer_notification', '1');
            formData.append('transaction_id', id);
        }
        
        try {
            const res = await fetch('clear_notification.php', { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                alert('Notification cleared.');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to clear'));
            }
        } catch (err) {
            console.error(err);
            alert('Error clearing notification. Check console.');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
