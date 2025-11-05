<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff", "super"]);

$type = $_GET['type'] ?? 'expenses'; // expenses, total_expenses, debtors, payment_analysis, product_summary
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$branch = $_GET['branch'] ?? '';

function getBranchName($conn, $branchId) {
    if (!$branchId) return 'All Branches';
    $res = $conn->query("SELECT name FROM branch WHERE id=" . intval($branchId));
    $row = $res ? $res->fetch_assoc() : null;
    return $row ? $row['name'] : 'Branch ' . $branchId;
}

// Build WHERE clause
$where = [];
if ($branch) $where[] = "e.`branch-id` = " . intval($branch);
if ($date_from) $where[] = "DATE(e.date) >= '" . $conn->real_escape_string($date_from) . "'";
if ($date_to) $where[] = "DATE(e.date) <= '" . $conn->real_escape_string($date_to) . "'";
$whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";

// --- Query data based on type ---
$report_title = '';
$thead = '';
$rows = [];
if ($type === 'expenses') {
    $report_title = 'Expenses Report';
    $thead = '<tr>
        <th>ID</th><th>Date & Time</th><th>Supplier</th><th>Branch</th><th>Category</th>
        <th>Product</th><th>Quantity</th><th>Unit Price</th><th>Amount</th><th>Spent By</th><th>Description</th>
    </tr>';
    $sql = "
        SELECT e.*, u.username, b.name AS branch_name, s.name AS supplier_name
        FROM expenses e
        LEFT JOIN users u ON e.`spent-by` = u.id
        LEFT JOIN branch b ON e.`branch-id` = b.id
        LEFT JOIN suppliers s ON e.supplier_id = s.id
        $whereClause
        ORDER BY e.date DESC
        LIMIT 500
    ";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) $rows[] = $row;
} elseif ($type === 'total_expenses') {
    $report_title = 'Total Expenses Report';
    $thead = '<tr><th>Date</th><th>Branch</th><th>Expenses</th><th>Total</th></tr>';
    $sql = "
        SELECT DATE(e.date) as expense_date, b.name as branch_name, COUNT(e.id) as expenses_count, SUM(e.amount) as total_expenses
        FROM expenses e
        LEFT JOIN branch b ON e.`branch-id` = b.id
        $whereClause
        GROUP BY expense_date, branch_name
        ORDER BY expense_date DESC, branch_name ASC
        LIMIT 500
    ";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) $rows[] = $row;
} elseif ($type === 'debtors') {
    $report_title = 'Debtors Report';
    $thead = '<tr>
        <th>Date</th><th>Debtor Name</th><th>Debtor Email</th><th>Item Taken</th>
        <th>Quantity Taken</th><th>Amount Paid</th><th>Balance</th><th>Paid Status</th>
    </tr>';
    $where = [];
    if ($branch) $where[] = "branch_id = " . intval($branch);
    if ($date_from) $where[] = "DATE(created_at) >= '" . $conn->real_escape_string($date_from) . "'";
    if ($date_to) $where[] = "DATE(created_at) <= '" . $conn->real_escape_string($date_to) . "'";
    $whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";
    $sql = "
        SELECT * FROM debtors
        $whereClause
        ORDER BY created_at DESC
        LIMIT 500
    ";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) $rows[] = $row;
} elseif ($type === 'payment_analysis') {
    $report_title = 'Payment Method Analysis Report';
    $thead = '<tr>
        <th>Date</th><th>Payment Method</th><th>Amount</th>
    </tr>';
    $where = [];
    if ($branch) $where[] = "sales.`branch-id` = " . intval($branch);
    if ($date_from) $where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($date_from) . "'";
    if ($date_to) $where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($date_to) . "'";
    $whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";
    $sql = "
        SELECT DATE(sales.date) AS day, COALESCE(sales.payment_method,'Cash') AS pm, SUM(sales.amount) AS total
        FROM sales
        $whereClause
        GROUP BY day, pm
        ORDER BY day DESC, pm ASC
        LIMIT 500
    ";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) $rows[] = $row;
} elseif ($type === 'product_summary') {
    $report_title = 'Product Summary Report';
    $thead = '<tr>
        <th>Date</th><th>Product</th><th>Items Sold</th>
    </tr>';
    $where = [];
    if ($branch) $where[] = "sales.`branch-id` = " . intval($branch);
    if ($date_from) $where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($date_from) . "'";
    if ($date_to) $where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($date_to) . "'";
    $whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";
    $sql = "
        SELECT DATE(sales.date) AS sale_date, products.name AS product_name, SUM(sales.quantity) AS items_sold
        FROM sales
        JOIN products ON sales.`product-id` = products.id
        $whereClause
        GROUP BY sale_date, product_name
        ORDER BY sale_date DESC, product_name ASC
        LIMIT 500
    ";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) $rows[] = $row;
} elseif ($type === 'sales') {
    $report_title = 'Sales Report';
    $thead = '<tr>
        <th>Date</th><th>Branch</th><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Total</th><th>Sold By</th>
    </tr>';
    $where = [];
    if ($branch) $where[] = "sales.`branch-id` = " . intval($branch);
    if ($date_from) $where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($date_from) . "'";
    if ($date_to) $where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($date_to) . "'";
    $whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";
    $sql = "
        SELECT sales.date, branch.name AS branch_name, products.name AS product_name, sales.quantity, sales.amount, sales.`sold-by`
        FROM sales
        JOIN products ON sales.`product-id` = products.id
        JOIN branch ON sales.`branch-id` = branch.id
        $whereClause
        ORDER BY sales.date DESC
        LIMIT 500
    ";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) $rows[] = $row;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($report_title) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f8f9fa;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .report-container {
            max-width: 900px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 24px #0002;
            padding: 2rem 2.5rem;
        }
        .report-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .report-title {
            font-size: 2rem;
            font-weight: bold;
            color: #1abc9c;
            margin-bottom: 0.5rem;
        }
        .report-meta {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 1rem;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        .report-table th, .report-table td {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid #e0e0e0;
            font-size: 1rem;
        }
        .report-table th {
            background: #1abc9c;
            color: #fff;
            font-weight: 600;
        }
        .report-table tbody tr:nth-child(even) {
            background: #f4f6f9;
        }
        .report-table tbody tr:hover {
            background: #e0f7fa;
        }
        .print-btn {
            display: block;
            margin: 2rem auto 0 auto;
            padding: 0.7rem 2.5rem;
            font-size: 1.1rem;
            background: #1abc9c;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 8px #0002;
        }
        @media print {
            .print-btn { display: none; }
            .report-container { box-shadow: none; border-radius: 0; padding: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <div class="report-title"><?= htmlspecialchars($report_title) ?></div>
            <div class="report-meta">
                Period: <?= htmlspecialchars($date_from ?: '...') ?> to <?= htmlspecialchars($date_to ?: '...') ?> <br>
                Branch: <?= htmlspecialchars(getBranchName($conn, $branch)) ?>
            </div>
        </div>
        <table class="report-table">
            <thead><?= $thead ?></thead>
            <tbody>
                <?php if ($type === 'expenses'): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= htmlspecialchars($row['product']) ?></td>
                            <td><?= htmlspecialchars($row['quantity']) ?></td>
                            <td>UGX <?= number_format($row['unit_price'],2) ?></td>
                            <td>UGX <?= number_format($row['amount'],2) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif ($type === 'total_expenses'): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['expense_date']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['expenses_count']) ?></td>
                            <td>UGX <?= number_format($row['total_expenses'],2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif ($type === 'debtors'): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= date("Y-m-d H:i", strtotime($row['created_at'])) ?></td>
                            <td><?= htmlspecialchars($row['debtor_name']) ?></td>
                            <td><?= htmlspecialchars($row['debtor_email']) ?></td>
                            <td><?= htmlspecialchars($row['item_taken']) ?></td>
                            <td><?= htmlspecialchars($row['quantity_taken']) ?></td>
                            <td>UGX <?= number_format($row['amount_paid'],2) ?></td>
                            <td>UGX <?= number_format($row['balance'],2) ?></td>
                            <td>
                                <?= !empty($row['is_paid']) ? '<span style="color:green;font-weight:bold;">Paid</span>' : '<span style="color:orange;font-weight:bold;">Unpaid</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif ($type === 'payment_analysis'): ?>
                    <?php
                    $prev_day = null;
                    foreach ($rows as $row):
                        $show_day = ($prev_day !== $row['day']);
                    ?>
                        <tr>
                            <td><?= $show_day ? htmlspecialchars($row['day']) : '' ?></td>
                            <td><?= htmlspecialchars($row['pm']) ?></td>
                            <td>UGX <?= number_format($row['total'],2) ?></td>
                        </tr>
                    <?php
                        $prev_day = $row['day'];
                    endforeach; ?>
                <?php elseif ($type === 'product_summary'): ?>
                    <?php
                    $prev_date = null;
                    foreach ($rows as $row):
                        $show_date = ($prev_date !== $row['sale_date']);
                    ?>
                        <tr>
                            <td><?= $show_date ? htmlspecialchars($row['sale_date']) : '' ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td><?= htmlspecialchars($row['items_sold']) ?></td>
                        </tr>
                    <?php
                        $prev_date = $row['sale_date'];
                    endforeach; ?>
                <?php elseif ($type === 'sales'): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td><?= htmlspecialchars($row['quantity']) ?></td>
                            <td>UGX <?= number_format($row['amount'],2) ?></td>
                            <td>UGX <?= number_format($row['quantity']*$row['amount'],2) ?></td>
                            <td><?= htmlspecialchars($row['sold-by']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="20" style="text-align:center;color:#888;">No data found.</td></tr>
                <?php endif; ?>
                <?php if (count($rows) === 0): ?>
                    <tr><td colspan="20" style="text-align:center;color:#888;">No data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <button class="print-btn" onclick="window.print()">Print Report</button>
    </div>
</body>
</html>
