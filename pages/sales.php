<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../pages/handle_debtor_payment.php';
require_role(["admin", "manager", "staff"]);
// Fix: Always use the correct sidebar for staff
if ($_SESSION['role'] === 'staff') {
    include '../pages/sidebar_staff.php';
} else {
    include '../pages/sidebar.php';
}
include '../includes/header.php';

$message = "";

// Logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Filters
$selected_branch = $_GET['branch'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';


// Build WHERE clause for filters
$where = [];
if ($user_role === 'staff') {
    $where[] = "sales.`branch-id` = $user_branch";
} elseif ($selected_branch) {
    $where[] = "sales.`branch-id` = " . intval($selected_branch);
}
if ($date_from) {
    $where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($date_to) . "'";
}
$whereClause = count($where) ? "WHERE " . implode(' AND ', $where) : "";

// Pagination setup
$items_per_page = 60;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Count total sales for pagination
$count_query = "SELECT COUNT(*) as total FROM sales JOIN products ON sales.`product-id` = products.id $whereClause";
$total_result = $conn->query($count_query);
$total_row = $total_result->fetch_assoc();
$total_items = $total_row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Fetch sales for current page
$sales_query = "
    SELECT sales.id, products.name AS `product-name`, sales.quantity, sales.amount, sales.`sold-by`, sales.date, branch.name AS branch_name, sales.payment_method
    FROM sales
    JOIN products ON sales.`product-id` = products.id
    JOIN branch ON sales.`branch-id` = branch.id
    $whereClause
    ORDER BY sales.id DESC
    LIMIT $items_per_page OFFSET $offset
";
$sales = $conn->query($sales_query);

// Fetch branches for admin/manager filter
$branches = ($user_role !== 'staff') ? $conn->query("SELECT id, name FROM branch") : [];

// Calculate total sum of sales (filtered)
$sum_query = "
    SELECT SUM(sales.amount) AS total_sales
    FROM sales
    JOIN products ON sales.`product-id` = products.id
    $whereClause
";
$sum_result = $conn->query($sum_query);
$sum_row = $sum_result->fetch_assoc();
$total_sales_sum = $sum_row['total_sales'] ?? 0;

// Debtors filters
$debtor_where = [];
if ($user_role === 'staff') {
    $debtor_where[] = "debtors.branch_id = $user_branch";
} elseif (!empty($_GET['debtor_branch'])) {
    $debtor_where[] = "debtors.branch_id = " . intval($_GET['debtor_branch']);
}
if (!empty($_GET['debtor_date_from'])) {
    $debtor_where[] = "DATE(debtors.created_at) >= '" . $conn->real_escape_string($_GET['debtor_date_from']) . "'";
}
if (!empty($_GET['debtor_date_to'])) {
    $debtor_where[] = "DATE(debtors.created_at) <= '" . $conn->real_escape_string($_GET['debtor_date_to']) . "'";
}
$debtorWhereClause = count($debtor_where) ? "WHERE " . implode(' AND ', $debtor_where) : "";

// Fetch debtors for the table
$debtors_result = $conn->query("
    SELECT id, debtor_name, debtor_email, item_taken, quantity_taken, amount_paid, balance, is_paid, created_at
    FROM debtors
    $debtorWhereClause
    ORDER BY created_at DESC
    LIMIT 100
");

// --- Product Summary Query ---
$product_summary_where = $whereClause; // use same filters as sales
$product_summary_sql = "
    SELECT DATE(sales.date) AS sale_date, products.name AS product_name, SUM(sales.quantity) AS items_sold
    FROM sales
    JOIN products ON sales.`product-id` = products.id
    $product_summary_where
    GROUP BY sale_date, product_name
    ORDER BY sale_date DESC, product_name ASC
    LIMIT 200
";
$product_summary_res = $conn->query($product_summary_sql);

// --- Product Summary Filters ---
$ps_date_from = $_GET['ps_date_from'] ?? '';
$ps_date_to = $_GET['ps_date_to'] ?? '';
$ps_branch = $_GET['ps_branch'] ?? '';

// --- Product Summary Query ---
$product_summary_where = [];
if ($user_role === 'staff') {
    $product_summary_where[] = "sales.`branch-id` = $user_branch";
} elseif ($ps_branch) {
    $product_summary_where[] = "sales.`branch-id` = " . intval($ps_branch);
}
if ($ps_date_from) {
    $product_summary_where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($ps_date_from) . "'";
}
if ($ps_date_to) {
    $product_summary_where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($ps_date_to) . "'";
}
$product_summary_whereClause = count($product_summary_where) ? "WHERE " . implode(' AND ', $product_summary_where) : "";

$product_summary_sql = "
    SELECT DATE(sales.date) AS sale_date, branch.name AS branch_name, products.name AS product_name, SUM(sales.quantity) AS items_sold
    FROM sales
    JOIN products ON sales.`product-id` = products.id
    JOIN branch ON sales.`branch-id` = branch.id
    $product_summary_whereClause
    GROUP BY sale_date, branch_name, product_name
    ORDER BY sale_date DESC, branch_name ASC, product_name ASC
    LIMIT 200
";
$product_summary_res = $conn->query($product_summary_sql);
?>

<!-- Tabs for Sales and Debtors -->
<div class="container-fluid mt-4">
    <ul class="nav nav-tabs mb-4" id="salesTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales-table" type="button" role="tab" aria-controls="sales-table" aria-selected="true">
                Sales Records
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="debtors-tab" data-bs-toggle="tab" data-bs-target="#debtors-table" type="button" role="tab" aria-controls="debtors-table" aria-selected="false">
                Debtors
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payment-analysis-tab" data-bs-toggle="tab" data-bs-target="#payment-analysis" type="button" role="tab" aria-controls="payment-analysis" aria-selected="false">
                Payment Method Analysis
            </button>
        </li>
        <!-- NEW: Product Summary Tab -->
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="product-summary-tab" data-bs-toggle="tab" data-bs-target="#product-summary" type="button" role="tab" aria-controls="product-summary" aria-selected="false">
                Product Summary
            </button>
        </li>
    </ul>
    <div class="tab-content" id="salesTabsContent">
        <!-- Payment Method Analysis Tab -->
        <div class="tab-pane fade" id="payment-analysis" role="tabpanel" aria-labelledby="payment-analysis-tab">
            <div class="card mb-4 chart-card">
                <div class="card-header bg-light text-black fw-bold" style="border-radius:12px 12px 0 0;">
                    Payment Method Analysis
                </div>
                <div class="card-body">
                    <?php
                    // Filters for Payment Analysis (separate from Sales tab filters)
                    $pa_selected_branch = $_GET['pa_branch'] ?? '';
                    $pa_date_from = $_GET['pa_date_from'] ?? '';
                    $pa_date_to = $_GET['pa_date_to'] ?? '';

                    // Build monthly totals per payment method for charts
                    $methods = ['Cash','MTN MoMo','Airtel Money','Bank'];
                    $pa_where = [];
                    if ($user_role === 'staff') {
                        $pa_where[] = "sales.`branch-id` = $user_branch";
                    } elseif ($pa_selected_branch) {
                        $pa_where[] = "sales.`branch-id` = " . intval($pa_selected_branch);
                    }
                    if ($pa_date_from) {
                        $pa_where[] = "DATE(sales.date) >= '" . $conn->real_escape_string($pa_date_from) . "'";
                    }
                    if ($pa_date_to) {
                        $pa_where[] = "DATE(sales.date) <= '" . $conn->real_escape_string($pa_date_to) . "'";
                    }
                    $pa_whereClause = count($pa_where) ? "WHERE " . implode(' AND ', $pa_where) : "";

                    $pm_monthly_sql = "
                        SELECT DATE_FORMAT(sales.date, '%Y-%m') AS ym, COALESCE(sales.payment_method,'Cash') AS pm, SUM(sales.amount) AS total
                        FROM sales
                        $pa_whereClause
                        GROUP BY ym, pm
                        ORDER BY ym ASC
                    ";
                    $pm_monthly_res = $conn->query($pm_monthly_sql);
                    $month_set = [];
                    $data_map = [];
                    foreach ($methods as $m) { $data_map[$m] = []; }
                    if ($pm_monthly_res) {
                        while ($r = $pm_monthly_res->fetch_assoc()) {
                            $ym = $r['ym'];
                            $pm = $r['pm'];
                            if (!in_array($ym, $month_set, true)) $month_set[] = $ym;
                            if (!isset($data_map[$pm])) $data_map[$pm] = [];
                            $data_map[$pm][$ym] = (float)$r['total'];
                        }
                    }
                    // Ensure months sorted
                    sort($month_set);
                    // Build aligned series
                    $chart_labels = $month_set;
                    $series = [];
                    foreach ($methods as $m) {
                        $row = [];
                        foreach ($chart_labels as $ym) {
                            $row[] = isset($data_map[$m][$ym]) ? round($data_map[$m][$ym], 2) : 0;
                        }
                        $series[$m] = $row;
                    }

                    // Daily totals table
                    $dailyWhere = $pa_whereClause;
                    if (empty($pa_date_from) && empty($pa_date_to)) {
                        $dailyWhere .= ($dailyWhere ? " AND " : " WHERE ") . "DATE(sales.date) >= CURDATE() - INTERVAL 30 DAY";
                    }
                    $daily_sql = "
                        SELECT DATE(sales.date) AS day, COALESCE(sales.payment_method,'Cash') AS pm, SUM(sales.amount) AS total
                        FROM sales
                        $dailyWhere
                        GROUP BY day, pm
                        ORDER BY day DESC, pm ASC
                        LIMIT 500
                    ";
                    $daily_res = $conn->query($daily_sql);
                    ?>

                    <!-- Charts Grid -->
                    <div class="row">
                        <div class="col-md-6 mb-4"><div style="height:300px"><canvas id="chartCash"></canvas></div></div>
                        <div class="col-md-6 mb-4"><div style="height:300px"><canvas id="chartMtn"></canvas></div></div>
                        <div class="col-md-6 mb-4"><div style="height:300px"><canvas id="chartAirtel"></canvas></div></div>
                        <div class="col-md-6 mb-4"><div style="height:300px"><canvas id="chartBank"></canvas></div></div>
                    </div>

                    <!-- Filters (Payment Analysis) -->
                    <div class="pa-filter-bar d-flex align-items-center flex-wrap gap-2 mb-3 p-2 rounded">
                        <form method="GET" class="d-flex align-items-center flex-wrap gap-2" style="gap:1rem;">
                            <label class="fw-bold me-2">From:</label>
                            <input type="date" name="pa_date_from" class="form-select me-2" value="<?= htmlspecialchars($pa_date_from) ?>" style="width:150px;">
                            <label class="fw-bold me-2">To:</label>
                            <input type="date" name="pa_date_to" class="form-select me-2" value="<?= htmlspecialchars($pa_date_to) ?>" style="width:150px;">
                            <?php if ($user_role !== 'staff'): ?>
                            <label class="fw-bold me-2">Branch:</label>
                            <select name="pa_branch" class="form-select me-2" style="width:180px;">
                                <option value="">-- All Branches --</option>
                                <?php $branches_pa = $conn->query("SELECT id, name FROM branch"); while ($b = $branches_pa->fetch_assoc()): $sel = ($pa_selected_branch == $b['id']) ? 'selected' : ''; ?>
                                    <option value="<?= $b['id'] ?>" <?= $sel ?>><?= htmlspecialchars($b['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary ms-2">Filter</button>
                        </form>
                        <!-- Report Button: icon for small, full for md+ -->
                        <button type="button" class="btn btn-success ms-2 d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('payment_analysis')">
                            <i class="fa fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-success ms-2 d-none d-md-inline-flex" onclick="openReportGen('payment_analysis')">
                            <i class="fa fa-file-pdf"></i> Generate Report
                        </button>
                    </div>

                    <!-- Daily Totals Table -->
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($daily_res && $daily_res->num_rows > 0): ?>
                                    <?php
                                        $currentDay = null;
                                        $grandTotal = 0;
                                        $dayTotal = 0;
                                        $dailyRows = [];
                                        while ($r = $daily_res->fetch_assoc()) { $dailyRows[] = $r; }
                                        foreach ($dailyRows as $r):
                                            if ($currentDay !== null && $currentDay !== $r['day']):
                                    ?>
                                        <tr>
                                            <td colspan="2" class="text-end fw-bold">Total for <?= htmlspecialchars($currentDay) ?></td>
                                            <td><span class="fw-bold text-primary">UGX <?= number_format($dayTotal, 2) ?></span></td>
                                        </tr>
                                    <?php
                                                $grandTotal += $dayTotal;
                                                $dayTotal = 0;
                                            endif;
                                            $currentDay = $r['day'];
                                            $dayTotal += (float)$r['total'];
                                    ?>
                                        <tr>
                                            <td><small class="text-muted"><?= htmlspecialchars($r['day']) ?></small></td>
                                            <td><?= htmlspecialchars($r['pm']) ?></td>
                                            <td><span class="fw-bold text-success">UGX <?= number_format($r['total'], 2) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($currentDay !== null): ?>
                                        <tr>
                                            <td colspan="2" class="text-end fw-bold">Total for <?= htmlspecialchars($currentDay) ?></td>
                                            <td><span class="fw-bold text-primary">UGX <?= number_format($dayTotal, 2) ?></span></td>
                                        </tr>
                                        <?php $grandTotal += $dayTotal; ?>
                                        <tr>
                                            <td colspan="2" class="text-end fw-bold">Grand Total</td>
                                            <td><span class="fw-bold text-danger">UGX <?= number_format($grandTotal, 2) ?></span></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No payments found for the selected period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Chart.js and initialization -->
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                    window.addEventListener('DOMContentLoaded', function() {
                        const labels = <?= json_encode($chart_labels) ?>.map(m => {
                            const [y, mth] = m.split('-');
                            const date = new Date(parseInt(y), parseInt(mth)-1, 1);
                            return date.toLocaleString('en-US', { month: 'short', year: 'numeric' });
                        });
                        const dataByMethod = <?= json_encode($series) ?>;
                        const colors = {
                            'Cash': '#1abc9c',
                            'MTN MoMo': '#f1c40f',
                            'Airtel Money': '#e74c3c',
                            'Bank': '#3498db'
                        };

                        const getThemeColors = () => {
                            const isDark = document.body.classList.contains('dark-mode');
                            return {
                                textColor: isDark ? '#ffffff' : '#000000',
                                gridColor: isDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)'
                            };
                        };

                        const makeOptions = (title) => {
                            const { textColor, gridColor } = getThemeColors();
                            return {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: title,
                                        data: dataByMethod[title] || [],
                                        backgroundColor: colors[title] + '88',
                                        borderColor: colors[title],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: { ticks: { color: textColor }, grid: { color: gridColor } },
                                        y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }
                                    },
                                    plugins: {
                                        legend: { display: false, labels: { color: textColor } },
                                        tooltip: { titleColor: textColor, bodyColor: textColor }
                                    }
                                }
                            };
                        };

                        const charts = {};
                        const makeChart = (id, title) => {
                            const el = document.getElementById(id)?.getContext('2d');
                            if (!el) return;
                            charts[id] = new Chart(el, makeOptions(title));
                        };
                        makeChart('chartCash', 'Cash');
                        makeChart('chartMtn', 'MTN MoMo');
                        makeChart('chartAirtel', 'Airtel Money');
                        makeChart('chartBank', 'Bank');

                        const applyThemeToCharts = () => {
                            const { textColor, gridColor } = getThemeColors();
                            Object.values(charts).forEach(ch => {
                                ch.options.scales.x.ticks.color = textColor;
                                ch.options.scales.y.ticks.color = textColor;
                                ch.options.scales.x.grid.color = gridColor;
                                ch.options.scales.y.grid.color = gridColor;
                                if (ch.options.plugins && ch.options.plugins.legend && ch.options.plugins.legend.labels) {
                                    ch.options.plugins.legend.labels.color = textColor;
                                }
                                if (ch.options.plugins && ch.options.plugins.tooltip) {
                                    ch.options.plugins.tooltip.titleColor = textColor;
                                    ch.options.plugins.tooltip.bodyColor = textColor;
                                }
                                ch.update();
                            });
                        };

                        const mo = new MutationObserver(applyThemeToCharts);
                        mo.observe(document.body, { attributes: true, attributeFilter: ['class'] });
                        window.addEventListener('storage', applyThemeToCharts);
                    });
                    </script>
                </div>
            </div>
        </div>
        <!-- Sales Table Tab -->
        <div class="tab-pane fade show active" id="sales-table" role="tabpanel" aria-labelledby="sales-tab">
            <div class="card mb-4 chart-card">
                <div class="card-header bg-light text-black d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span class="fw-bold title-card"><i class="fa-solid fa-receipt"></i> Recent Sales</span>
                    <form method="GET" class="d-flex align-items-center flex-wrap gap-2" style="gap:1rem;">
                        <label class="fw-bold me-2">From:</label>
                        <input type="date" name="date_from" class="form-select me-2" value="<?= htmlspecialchars($date_from) ?>" style="width:150px;">
                        <label class="fw-bold me-2">To:</label>
                        <input type="date" name="date_to" class="form-select me-2" value="<?= htmlspecialchars($date_to) ?>" style="width:150px;">
                        <?php if ($user_role !== 'staff'): ?>
                        <label class="fw-bold me-2">Branch:</label>
                        <select name="branch" class="form-select me-2" onchange="this.form.submit()" style="width:180px;">
                            <option value="">-- All Branches --</option>
                            <?php
                            $branches = $conn->query("SELECT id, name FROM branch");
                            while ($b = $branches->fetch_assoc()):
                                $selected = ($selected_branch == $b['id']) ? 'selected' : '';
                                echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                            endwhile;
                            ?>
                        </select>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary ms-2">Filter</button>
                    </form>
                </div>
                <div class="card-body table-responsive">
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <?php if ($user_role !== 'staff' && empty($selected_branch)) echo "<th>Branch</th>"; ?>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Total Price</th>
                                    <th>Payment Method</th>
                                    <th>Sold At</th>
                                    <th>Sold By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = $offset + 1;
                                while ($row = $sales->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <?php if ($user_role !== 'staff' && empty($selected_branch)) echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>"; ?>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($row['product-name']) ?></span></td>
                                        <td><?= $row['quantity'] ?></td>
                                        <td><span class="fw-bold text-success">$<?= number_format($row['amount'], 2) ?></span></td>
                                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                        <td><small class="text-muted"><?= $row['date'] ?></small></td>
                                        <td><?= htmlspecialchars($row['sold-by']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($sales->num_rows === 0): ?>
                                    <tr><td colspan="<?= ($user_role !== 'staff' && empty($selected_branch)) ? 8 : 7 ?>" class="text-center text-muted">No sales found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-3">
                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $p ?><?= ($selected_branch ? '&branch=' . $selected_branch : '') ?><?= ($date_from ? '&date_from=' . $date_from : '') ?><?= ($date_to ? '&date_to=' . $date_to : '') ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    <!-- Total Sales Sum -->
                    <div class="mt-4 text-end">
                        <h5 class="fw-bold">Total Sales Value: <span class="text-success">$<?= number_format($total_sales_sum, 2) ?></span></h5>
                    </div>
                </div>
            </div>
        </div>
        <!-- Debtors Table Tab -->
        <div class="tab-pane fade" id="debtors-table" role="tabpanel" aria-labelledby="debtors-tab">
            <div class="card mb-4 chart-card">
                <div class="card-header bg-light text-black fw-bold d-flex flex-wrap justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span><i class="fa-solid fa-user-clock"></i> Debtors</span>
                    <!-- Generate Report Button -->
                    <button type="button" class="btn btn-success ms-3 d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('debtors')">
                        <i class="fa fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-success ms-3 d-none d-md-inline-flex" onclick="openReportGen('debtors')">
                        <i class="fa fa-file-pdf"></i> Generate Report
                    </button>
                </div>
                <div class="card-body table-responsive">
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Debtor Name</th>
                                    <th>Debtor Email</th>
                                    <th>Item Taken</th>
                                    <th>Quantity Taken</th>
                                    <th>Amount Paid</th>
                                    <th>Balance</th>
                                    <th>Paid Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($debtors_result && $debtors_result->num_rows > 0): ?>
                                    <?php while ($debtor = $debtors_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date("M d, Y H:i", strtotime($debtor['created_at'])); ?></td>
                                            <td><?= htmlspecialchars($debtor['debtor_name']); ?></td>
                                            <td><?= htmlspecialchars($debtor['debtor_email']); ?></td>
                                            <td><?= htmlspecialchars($debtor['item_taken'] ?? '-'); ?></td>
                                            <td><?= htmlspecialchars($debtor['quantity_taken'] ?? '-'); ?></td>
                                            <td>UGX <?= number_format($debtor['amount_paid'] ?? 0, 2); ?></td>
                                            <td>UGX <?= number_format($debtor['balance'] ?? 0, 2); ?></td>
                                            <td>
                                                <?php if (!empty($debtor['is_paid'])): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Unpaid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <!-- Only show Pay button. Pass debtor metadata for the modal -->
                                                <button class="btn btn-primary btn-sm btn-pay-debtor"
                                                    data-id="<?= $debtor['id'] ?>"
                                                    data-balance="<?= htmlspecialchars($debtor['balance'] ?? 0) ?>"
                                                    data-name="<?= htmlspecialchars($debtor['debtor_name']) ?>">
                                                    Pay
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No debtors recorded yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- NEW: Product Summary Tab -->
        <div class="tab-pane fade" id="product-summary" role="tabpanel" aria-labelledby="product-summary-tab">
            <div class="card mb-4 chart-card">
                <div class="card-header bg-light text-black fw-bold" style="border-radius:12px 12px 0 0;">
                    Product Summary (Items Sold Per Day)
                    <!-- Generate Report Button -->
                    <button type="button" class="btn btn-success ms-3 d-inline-flex d-md-none" title="Generate Report" onclick="openReportGen('product_summary')">
                        <i class="fa fa-file-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-success ms-3 d-none d-md-inline-flex" onclick="openReportGen('product_summary')">
                        <i class="fa fa-file-pdf"></i> Generate Report
                    </button>
                </div>
                <div class="card-body table-responsive">
                    <!-- Product Summary Filters -->
                    <form method="GET" class="d-flex align-items-center flex-wrap gap-2 mb-3 product-summary-filter" style="gap:1rem;">
                        <input type="hidden" name="tab" value="product-summary">
                        <label class="fw-bold me-2">From:</label>
                        <input type="date" name="ps_date_from" class="form-select me-2" value="<?= htmlspecialchars($ps_date_from) ?>" style="width:150px;">
                        <label class="fw-bold me-2">To:</label>
                        <input type="date" name="ps_date_to" class="form-select me-2" value="<?= htmlspecialchars($ps_date_to) ?>" style="width:150px;">
                        <?php if ($user_role !== 'staff'): ?>
                        <label class="fw-bold me-2">Branch:</label>
                        <select name="ps_branch" class="form-select me-2" style="width:180px;">
                            <option value="">-- All Branches --</option>
                            <?php $branches_ps = $conn->query("SELECT id, name FROM branch"); while ($b = $branches_ps->fetch_assoc()): $sel = ($ps_branch == $b['id']) ? 'selected' : ''; ?>
                                <option value="<?= $b['id'] ?>" <?= $sel ?>><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary ms-2">Filter</button>
                    </form>
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Product</th>
                                    <th>Items Sold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($product_summary_res && $product_summary_res->num_rows > 0):
                                    $prev_date = null;
                                    $prev_branch = null;
                                    while ($row = $product_summary_res->fetch_assoc()):
                                        $show_date = ($prev_date !== $row['sale_date']);
                                        $show_branch = ($prev_branch !== $row['branch_name']) || $show_date;
                                ?>
                                    <tr>
                                        <td><?= $show_date ? htmlspecialchars($row['sale_date']) : '' ?></td>
                                        <td><?= $show_branch ? htmlspecialchars($row['branch_name']) : '' ?></td>
                                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                                        <td><?= htmlspecialchars($row['items_sold']) ?></td>
                                    </tr>
                                <?php
                                        $prev_date = $row['sale_date'];
                                        $prev_branch = $row['branch_name'];
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No product summary data found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for report generation -->
<div class="modal fade" id="reportGenModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="reportGenForm">
      <div class="modal-header">
        <h5 class="modal-title" id="reportGenModalTitle">Generate Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6">
          <label class="form-label">From</label>
          <input type="date" name="date_from" id="report_date_from" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">To</label>
          <input type="date" name="date_to" id="report_date_to" class="form-control" required>
        </div>
        <div class="col-md-12">
          <label class="form-label">Branch</label>
          <select name="branch" id="report_branch" class="form-select">
            <option value="">All Branches</option>
            <?php
            $branches = $conn->query("SELECT id, name FROM branch");
            while ($b = $branches->fetch_assoc()):
                echo "<option value='{$b['id']}'>" . htmlspecialchars($b['name']) . "</option>";
            endwhile;
            ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Generate & Print</button>
      </div>
    </form>
  </div>
</div>

<!-- Pay Debtor Modal -->
<div class="modal fade" id="payDebtorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary-color);color:#fff;">
        <h5 class="modal-title">Record Debtor Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="pdDebtorLabel" class="mb-2 fw-semibold"></p>
        <p>Outstanding Balance: <strong id="pdBalanceText">UGX 0.00</strong></p>
        <input type="hidden" id="pdDebtorId" value="">
        <div class="mb-3">
          <label class="form-label">Amount Paid (UGX)</label>
          <input type="number" id="pdAmount" class="form-control" min="0" step="0.01" placeholder="Enter amount">
        </div>
        <div id="pdMsg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="pdConfirmBtn" class="btn btn-primary">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
/* Ensure Bootstrap is loaded, then init modal logic.
   This avoids "bootstrap is not defined" when our script runs before the Bootstrap bundle. */
(function() {
  function ensureBootstrap(cb) {
    if (window.bootstrap) return cb();
    const src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
    // If script already injected, poll until available
    if (document.querySelector('script[src="'+src+'"]')) {
      const t = setInterval(() => { if (window.bootstrap) { clearInterval(t); cb(); } }, 50);
      return;
    }
    const s = document.createElement('script');
    s.src = src;
    s.onload = cb;
    s.onerror = function() { console.error('Failed to load Bootstrap bundle.'); cb(); };
    document.head.appendChild(s);
  }

  function initPayModal() {
    const payButtons = document.querySelectorAll('.btn-pay-debtor');
    if (!payButtons.length) return;

    const payModalEl = document.getElementById('payDebtorModal');
    const payModal = new bootstrap.Modal(payModalEl);
    const pdDebtorLabel = document.getElementById('pdDebtorLabel');
    const pdBalanceText = document.getElementById('pdBalanceText');
    const pdDebtorId = document.getElementById('pdDebtorId');
    const pdAmount = document.getElementById('pdAmount');
    const pdMsg = document.getElementById('pdMsg');
    const pdConfirmBtn = document.getElementById('pdConfirmBtn');

    payButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const balance = parseFloat(btn.getAttribute('data-balance') || 0);
        const name = btn.getAttribute('data-name') || 'Debtor';
        pdDebtorId.value = id;
        pdAmount.value = '';
        pdDebtorLabel.textContent = `Debtor: ${name}`;
        pdBalanceText.textContent = 'UGX ' + balance.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        pdMsg.innerHTML = '';
        payModalEl.dataset.outstanding = String(balance);
        payModal.show();
      });
    });

    pdConfirmBtn.addEventListener('click', async () => {
      const id = pdDebtorId.value;
      let amount = parseFloat(pdAmount.value || 0);
      const outstanding = parseFloat(payModalEl.dataset.outstanding || 0);

      pdMsg.innerHTML = '';
      if (!id) { pdMsg.innerHTML = '<div class="alert alert-warning">Invalid debtor selected.</div>'; return; }
      if (!amount || amount <= 0) { pdMsg.innerHTML = '<div class="alert alert-warning">Enter a valid amount.</div>'; return; }
      if (amount > outstanding) { pdMsg.innerHTML = '<div class="alert alert-warning">Amount cannot exceed outstanding balance.</div>'; return; }

      pdConfirmBtn.disabled = true;
      pdConfirmBtn.textContent = 'Processing...';
      try {
        // POST to the dedicated handler that returns JSON (avoid HTML page responses)
        const res = await fetch('handle_debtor_payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `pay_debtor=1&id=${encodeURIComponent(id)}&amount=${encodeURIComponent(amount)}`
        });

        const text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (parseErr) {
          console.error('Invalid JSON response from server:', text);
          pdMsg.innerHTML = '<div class="alert alert-danger">Server returned an invalid response. See console for details.</div>';
          pdConfirmBtn.disabled = false;
          pdConfirmBtn.textContent = 'OK';
          return;
        }

        pdConfirmBtn.disabled = false;
        pdConfirmBtn.textContent = 'OK';

        if (data && data.reload) {
          payModal.hide();
          window.location.reload();
        } else {
          pdMsg.innerHTML = '<div class="alert alert-info">' + (data.message || 'Payment recorded') + '</div>';
        }
      } catch (err) {
        console.error('Request error:', err);
        pdConfirmBtn.disabled = false;
        pdConfirmBtn.textContent = 'OK';
        pdMsg.innerHTML = '<div class="alert alert-danger">Error processing payment. Check console.</div>';
      }
    });
  }

  // Run: ensure bootstrap then init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ensureBootstrap(initPayModal));
  } else {
    ensureBootstrap(initPayModal);
  }
})();

function openReportGen(type) {
    let title = 'Generate Report';
    if (type === 'payment_analysis') title = 'Generate Payment Analysis Report';
    else if (type === 'debtors') title = 'Generate Debtors Report';
    else if (type === 'product_summary') title = 'Generate Product Summary Report';
    document.getElementById('reportGenModalTitle').textContent = title;
    document.getElementById('reportGenForm').dataset.reportType = type;
    new bootstrap.Modal(document.getElementById('reportGenModal')).show();
}

document.getElementById('reportGenForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const type = this.dataset.reportType || 'sales';
    const date_from = document.getElementById('report_date_from').value;
    const date_to = document.getElementById('report_date_to').value;
    const branch = document.getElementById('report_branch').value;
    const url = `reports_generator.php?type=${encodeURIComponent(type)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&branch=${encodeURIComponent(branch)}`;
    window.open(url, '_blank');
    bootstrap.Modal.getInstance(document.getElementById('reportGenModal')).hide();
});
</script>

<style>
/* ...existing code... */
.transactions-table table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px var(--card-shadow);
}
.transactions-table thead {
    background: var(--primary-color);
    color: #fff;
}
.transactions-table tbody td {
    color: var(--text-color);
    padding: 0.75rem 1rem;
}
.transactions-table tbody tr {
    background-color: #fff;
    transition: background 0.2s;
}
.transactions-table tbody tr:nth-child(even) {
    background-color: #f4f6f9;
}
.transactions-table tbody tr:hover {
    background-color: rgba(0,0,0,0.05);
}
body.dark-mode .transactions-table table {
    background: var(--card-bg);
}
body.dark-mode .transactions-table thead {
    background-color: #1abc9c;
    color: #ffffff;
}
body.dark-mode .transactions-table tbody tr {
    background-color: #2c2c3a !important;
}
body.dark-mode .transactions-table tbody tr:nth-child(even) {
    background-color: #272734 !important;
}
body.dark-mode .transactions-table tbody td {
    color: #ffffff !important;
}
body.dark-mode .transactions-table tbody td small.text-muted {
    color: #ffffff !important;
}
body.dark-mode .card .card-header.bg-light {
    background-color: #2c3e50 !important;
    color: #fff !important;
    border-bottom: none;
}
body.dark-mode .card .card-header.bg-light label,
body.dark-mode .card .card-header.bg-light select,
body.dark-mode .card .card-header.bg-light span {
    color: #fff !important;
}
body.dark-mode .card .card-header.bg-light .form-select {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .card .card-header.bg-light .form-select:focus {
    background-color: #23243a !important;
    color: #fff !important;
}
body.dark-mode .card .card-header.bg-light input[type="date"]::-webkit-input-placeholder {
    color: #fff !important;
}
body.dark-mode .card .card-header.bg-light input[type="date"] {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .card .card-header.bg-light input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1);
}
body.dark-mode .card .card-header.bg-light input[type="date"]::-moz-calendar-picker-indicator {
    filter: invert(1);
}
body.dark-mode .card .card-header.bg-light input[type="date"]::-ms-input-placeholder {
    color: #fff !important;
}
.title-card {
    color: var(--primary-color);
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0;
    text-align: left;
}

/* Payment Analysis filter bar styles */
.pa-filter-bar {
    background-color: #ffffff;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    box-shadow: 0 2px 8px var(--card-shadow);
}
body.dark-mode .pa-filter-bar {
    background-color: #23243a !important;
    color: #ffffff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .pa-filter-bar label {
    color: #ffffff !important;
}
body.dark-mode .pa-filter-bar .form-select,
body.dark-mode .pa-filter-bar input[type="date"] {
    background-color: #23243a !important;
    color: #ffffff !important;
    border: 1px solid #444 !important;
}

/* Product Summary filter form label/input colors */
.product-summary-filter label {
    color: #222 !important;
    font-weight: 600;
}
.product-summary-filter .form-select,
.product-summary-filter input[type="date"] {
    color: #222 !important;
    background-color: #fff !important;
    border: 1px solid #dee2e6 !important;
}
.product-summary-filter .form-select:focus,
.product-summary-filter input[type="date"]:focus {
    color: #222 !important;
    background-color: #fff !important;
    border-color: var(--primary-color, #1abc9c);
}

/* Dark mode overrides for Product Summary filter */
body.dark-mode .product-summary-filter label {
    color: #ffd200 !important;
}
body.dark-mode .product-summary-filter .form-select,
body.dark-mode .product-summary-filter input[type="date"] {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .product-summary-filter .form-select:focus,
body.dark-mode .product-summary-filter input[type="date"]:focus {
    background-color: #23243a !important;
    color: #fff !important;
    border-color: #ffd200 !important;
}
</style>

<!-- Bootstrap JS for tabs (if not already included) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../includes/footer.php'; ?>
