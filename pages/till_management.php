<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// Handle form submission for creating a till
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creation_date = $_POST['creation_date'];
    $till_name = $_POST['till_name'];
    $branch_id = $_POST['branch_id'];
    $staff_id = $_POST['staff_id'];
    $phone_number = $_POST['phone_number'];

    $stmt = $conn->prepare("INSERT INTO tills (creation_date, name, branch_id, staff_id, phone_number) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $creation_date, $till_name, $branch_id, $staff_id, $phone_number);

    if ($stmt->execute()) {
        $success_message = "Till created successfully!";
    } else {
        $error_message = "Failed to create till: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch branches and staff for dropdowns
$branches = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");
$staff = $conn->query("SELECT id, username, `branch-id` FROM users WHERE role='staff' ORDER BY username ASC");
?>

<div class="container mt-4">
    <h2 class="mb-4">Till Management</h2>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php elseif (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="tillTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link<?= (!isset($_GET['tab']) || $_GET['tab'] === 'create-assign') ? ' active' : '' ?>" id="create-assign-tab" data-bs-toggle="tab" data-bs-target="#create-assign" type="button" role="tab">Create & Assign Till</button>
        </li>
        <li class="nav-item">
            <button class="nav-link<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-management') ? ' active' : '' ?>" id="till-management-tab" data-bs-toggle="tab" data-bs-target="#till-management" type="button" role="tab">Till Management</button>
        </li>
        <li class="nav-item">
            <button class="nav-link<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-view') ? ' active' : '' ?>" id="till-view-tab" data-bs-toggle="tab" data-bs-target="#till-view" type="button" role="tab">Till View</button>
        </li>
        <li class="nav-item">
            <button class="nav-link<?= (isset($_GET['tab']) && $_GET['tab'] === 'summaries') ? ' active' : '' ?>" id="summaries-tab" data-bs-toggle="tab" data-bs-target="#summaries" type="button" role="tab">Summaries</button>
        </li>
    </ul>
    <div class="tab-content mt-4" id="tillTabsContent">
        <!-- Create & Assign Till Tab -->
        <div class="tab-pane fade<?= (!isset($_GET['tab']) || $_GET['tab'] === 'create-assign') ? ' show active' : '' ?>" id="create-assign" role="tabpanel">
            <!-- Begin styled card (replaces plain form wrapper) -->
            <div class="card mb-4 create-till-card">
                <div class="card-header title-card">Create & Assign Till</div>
                <div class="card-body">
                    <form method="POST" action="" id="createTillForm">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="creation_date" class="form-label">Date of Creation</label>
                                <input type="date" class="form-control" id="creation_date" name="creation_date" required>
                            </div>
                            <div class="col-md-4">
                                <label for="till_name" class="form-label">Till Name</label>
                                <input type="text" class="form-control" id="till_name" name="till_name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="branch_id" class="form-label">Branch</label>
                                <select class="form-select" id="branch_id" name="branch_id" required>
                                    <option value="">-- Select Branch --</option>
                                    <?php while ($branch = $branches->fetch_assoc()): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="staff_id" class="form-label">Staff Member</label>
                                <select class="form-select" id="staff_id" name="staff_id" required>
                                    <option value="">-- Select Staff --</option>
                                    <?php while ($member = $staff->fetch_assoc()): ?>
                                        <option value="<?= $member['id'] ?>" data-branch="<?= $member['branch-id'] ?>">
                                            <?= htmlspecialchars($member['username']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" required>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="submit" class="btn btn-primary">Create</button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- End styled card -->
        </div>

        <!-- Till Management Tab -->
        <div class="tab-pane fade<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-management') ? ' show active' : '' ?>" id="till-management" role="tabpanel">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date of Creation</th>
                        <th>Branch</th>
                        <th>Till ID</th>
                        <th>Till Name</th>
                        <th>Assigned Staff</th>
                        <th>Contact</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tills = $conn->query("
                        SELECT t.id, t.creation_date, t.name AS till_name, b.name AS branch_name, u.username AS staff_name, t.phone_number
                        FROM tills t
                        JOIN branch b ON t.branch_id = b.id
                        JOIN users u ON t.staff_id = u.id
                        ORDER BY t.creation_date DESC
                    ");
                    while ($till = $tills->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($till['creation_date']) ?></td>
                            <td><?= htmlspecialchars($till['branch_name']) ?></td>
                            <td><?= str_pad($till['id'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td><?= htmlspecialchars($till['till_name']) ?></td>
                            <td><?= htmlspecialchars($till['staff_name']) ?></td>
                            <td><?= htmlspecialchars($till['phone_number']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning">Edit</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Till View Tab -->
        <div class="tab-pane fade<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-view') ? ' show active' : '' ?>" id="till-view" role="tabpanel">
            <!-- Filter Bar: all filters in one row -->
            <form method="GET" id="tillViewFilterForm" class="row g-2 align-items-end mb-3">
                <div class="col-md-2">
                    <label for="filter_date_from" class="form-label mb-1">From</label>
                    <input type="date" class="form-control" id="filter_date_from" name="filter_date_from" value="<?= htmlspecialchars($_GET['filter_date_from'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="filter_date_to" class="form-label mb-1">To</label>
                    <input type="date" class="form-control" id="filter_date_to" name="filter_date_to" value="<?= htmlspecialchars($_GET['filter_date_to'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="filter_branch" class="form-label mb-1">Branch</label>
                    <select class="form-select" id="filter_branch" name="filter_branch">
                        <option value="">-- All Branches --</option>
                        <?php
                        $branches->data_seek(0);
                        while ($branch = $branches->fetch_assoc()):
                            $selected = (isset($_GET['filter_branch']) && $_GET['filter_branch'] == $branch['id']) ? 'selected' : '';
                        ?>
                            <option value="<?= $branch['id'] ?>" <?= $selected ?>><?= htmlspecialchars($branch['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_summary" class="form-label mb-1">Summary</label>
                    <select class="form-select" id="filter_summary" name="filter_summary">
                        <option value="detailed" <?= (($_GET['filter_summary'] ?? '') == 'detailed' ? 'selected' : '') ?>>Detailed</option>
                        <option value="summarized" <?= (($_GET['filter_summary'] ?? '') == 'summarized' ? 'selected' : '') ?>>Summarized</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="hidden" name="tab" value="till-view">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>

            <?php
            // Prepare filters
            $filter_branch = $_GET['filter_branch'] ?? '';
            $filter_date_from = $_GET['filter_date_from'] ?? '';
            $filter_date_to = $_GET['filter_date_to'] ?? '';
            $filter_summary = $_GET['filter_summary'] ?? 'detailed';

            // Fetch tills for the selected branch
            $till_where = [];
            if ($filter_branch) $till_where[] = "t.branch_id = " . intval($filter_branch);
            $till_sql = "SELECT t.id, t.name, u.username AS staff_name FROM tills t JOIN users u ON t.staff_id = u.id";
            if ($till_where) $till_sql .= " WHERE " . implode(" AND ", $till_where);
            $till_sql .= " ORDER BY t.name ASC";
            $till_tabs = $conn->query($till_sql);

            // Get selected till tab
            $selected_till_id = $_GET['till_tab'] ?? '';
            // If none selected, pick the first till
            if (!$selected_till_id && $till_tabs && $till_tabs->num_rows > 0) {
                $first_till = $till_tabs->fetch_assoc();
                $selected_till_id = $first_till['id'];
                // Reset pointer for loop below
                $till_tabs->data_seek(0);
            }
            ?>

            <!-- Sub Tabs for Tills -->
            <ul class="nav nav-pills mb-3" id="tillSubTabs" role="tablist">
                <?php if ($till_tabs && $till_tabs->num_rows > 0): $first = true; ?>
                    <?php while ($till = $till_tabs->fetch_assoc()): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= ($selected_till_id == $till['id'] ? 'active' : '') ?>"
                               id="till-tab-<?= $till['id'] ?>"
                               href="?tab=till-view&filter_date_from=<?= urlencode($filter_date_from) ?>&filter_date_to=<?= urlencode($filter_date_to) ?>&filter_branch=<?= urlencode($filter_branch) ?>&filter_summary=<?= urlencode($filter_summary) ?>&till_tab=<?= $till['id'] ?>"
                               role="tab">
                                <?= htmlspecialchars($till['name']) ?> <small class="text-muted">(<?= htmlspecialchars($till['staff_name']) ?>)</small>
                            </a>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li class="nav-item"><span class="nav-link disabled">No tills found for this branch.</span></li>
                <?php endif; ?>
            </ul>

            <?php
            // Get staff_id for selected till
            $selected_staff_id = null;
            if ($selected_till_id) {
                $staff_res = $conn->query("SELECT staff_id FROM tills WHERE id = " . intval($selected_till_id));
                if ($staff_row = $staff_res->fetch_assoc()) {
                    $selected_staff_id = $staff_row['staff_id'];
                }
            }
            ?>

            <!-- Sales Table for Selected Till (match sales.php style) -->
            <?php
            if ($selected_till_id && $selected_staff_id):
                // Build sales filter
                $sales_where = ["s.`sold-by` = " . intval($selected_staff_id)];
                if ($filter_branch) $sales_where[] = "s.`branch-id` = " . intval($filter_branch);
                if ($filter_date_from) $sales_where[] = "s.date >= '" . $conn->real_escape_string($filter_date_from) . "'";
                if ($filter_date_to) $sales_where[] = "s.date <= '" . $conn->real_escape_string($filter_date_to) . "'";
                $sales_sql = "
                    SELECT 
                        s.id,
                        b.name AS branch_name,
                        p.name AS product_name,
                        s.quantity,
                        s.amount,
                        s.payment_method,
                        s.date,
                        u.username AS sold_by
                    FROM sales s
                    JOIN products p ON s.`product-id` = p.id
                    JOIN branch b ON s.`branch-id` = b.id
                    JOIN users u ON s.`sold-by` = u.id
                    WHERE " . implode(" AND ", $sales_where) . "
                    ORDER BY s.date DESC
                ";
                $sales_res = $conn->query($sales_sql);
            ?>
            <div class="card mt-3">
                <div class="card-header">
                    Sales Records for Till
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Branch</th>
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
                                if ($sales_res && $sales_res->num_rows > 0):
                                    $i = 1;
                                    while ($row = $sales_res->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($row['branch_name']) ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($row['product_name']) ?></span></td>
                                        <td><?= $row['quantity'] ?></td>
                                        <td><span class="fw-bold text-success">UGX<?= number_format($row['amount'], 2) ?></span></td>
                                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($row['date']) ?></td>
                                        <td><?= htmlspecialchars($row['sold_by']) ?></td>
                                    </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No sales found for this till and filter.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Summaries Tab -->
        <div class="tab-pane fade<?= (isset($_GET['tab']) && $_GET['tab'] === 'summaries') ? ' show active' : '' ?>" id="summaries" role="tabpanel">
            <!-- Filter Bar: all filters in one row -->
            <form method="GET" id="summariesFilterForm" class="row g-2 align-items-end mb-3">
                <div class="col-md-2">
                    <label for="summaries_date_from" class="form-label mb-1">From</label>
                    <input type="date" class="form-control" id="summaries_date_from" name="summaries_date_from" value="<?= htmlspecialchars($_GET['summaries_date_from'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="summaries_date_to" class="form-label mb-1">To</label>
                    <input type="date" class="form-control" id="summaries_date_to" name="summaries_date_to" value="<?= htmlspecialchars($_GET['summaries_date_to'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="summaries_branch" class="form-label mb-1">Branch</label>
                    <select class="form-select" id="summaries_branch" name="summaries_branch">
                        <option value="">-- All Branches --</option>
                        <?php
                        $branches->data_seek(0);
                        while ($branch = $branches->fetch_assoc()):
                            $selected = (isset($_GET['summaries_branch']) && $_GET['summaries_branch'] == $branch['id']) ? 'selected' : '';
                        ?>
                            <option value="<?= $branch['id'] ?>" <?= $selected ?>><?= htmlspecialchars($branch['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="summaries_summary" class="form-label mb-1">Summary</label>
                    <select class="form-select" id="summaries_summary" name="summaries_summary">
                        <option value="detailed" <?= (($_GET['summaries_summary'] ?? '') == 'detailed' ? 'selected' : '') ?>>Detailed</option>
                        <option value="summarized" <?= (($_GET['summaries_summary'] ?? '') == 'summarized' ? 'selected' : '') ?>>Summarized</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="hidden" name="tab" value="summaries">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>

            <?php
            // Prepare filters for summaries
            $summaries_branch = $_GET['summaries_branch'] ?? '';
            $summaries_date_from = $_GET['summaries_date_from'] ?? '';
            $summaries_date_to = $_GET['summaries_date_to'] ?? '';
            $summaries_summary = $_GET['summaries_summary'] ?? 'detailed';

            // Fetch tills for the selected branch
            $summaries_till_where = [];
            if ($summaries_branch) $summaries_till_where[] = "t.branch_id = " . intval($summaries_branch);
            $summaries_till_sql = "SELECT t.id, t.name, u.username AS staff_name FROM tills t JOIN users u ON t.staff_id = u.id";
            if ($summaries_till_where) $summaries_till_sql .= " WHERE " . implode(" AND ", $summaries_till_where);
            $summaries_till_sql .= " ORDER BY t.name ASC";
            $summaries_till_tabs = $conn->query($summaries_till_sql);

            // Get selected till tab for summaries
            $summaries_selected_till_id = $_GET['summaries_till_tab'] ?? '';
            if (!$summaries_selected_till_id && $summaries_till_tabs && $summaries_till_tabs->num_rows > 0) {
                $first_till = $summaries_till_tabs->fetch_assoc();
                $summaries_selected_till_id = $first_till['id'];
                $summaries_till_tabs->data_seek(0);
            }
            ?>

            <!-- Sub Tabs for Tills (Summaries) -->
            <ul class="nav nav-pills mb-3" id="summariesTillSubTabs" role="tablist">
                <?php if ($summaries_till_tabs && $summaries_till_tabs->num_rows > 0): ?>
                    <?php while ($till = $summaries_till_tabs->fetch_assoc()): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= ($summaries_selected_till_id == $till['id'] ? 'active' : '') ?>"
                               id="summaries-till-tab-<?= $till['id'] ?>"
                               href="?tab=summaries&summaries_date_from=<?= urlencode($summaries_date_from) ?>&summaries_date_to=<?= urlencode($summaries_date_to) ?>&summaries_branch=<?= urlencode($summaries_branch) ?>&summaries_summary=<?= urlencode($summaries_summary) ?>&summaries_till_tab=<?= $till['id'] ?>"
                               role="tab">
                                <?= htmlspecialchars($till['name']) ?> <small class="text-muted">(<?= htmlspecialchars($till['staff_name']) ?>)</small>
                            </a>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li class="nav-item"><span class="nav-link disabled">No tills found for this branch.</span></li>
                <?php endif; ?>
            </ul>

            <?php
            // Only show sub-tabs and summaries if a till is selected
            if ($summaries_selected_till_id):
            ?>
            <!-- Two sub-tabs: Product Summaries and Sales Value Summary -->
            <ul class="nav nav-tabs mb-3" id="summariesSubTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link<?= (!isset($_GET['summaries_subtab']) || $_GET['summaries_subtab'] === 'product') ? ' active' : '' ?>"
                       id="summaries-product-tab"
                       href="?tab=summaries&summaries_date_from=<?= urlencode($summaries_date_from) ?>&summaries_date_to=<?= urlencode($summaries_date_to) ?>&summaries_branch=<?= urlencode($summaries_branch) ?>&summaries_summary=<?= urlencode($summaries_summary) ?>&summaries_till_tab=<?= $summaries_selected_till_id ?>&summaries_subtab=product"
                       role="tab">Product Summaries</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= (isset($_GET['summaries_subtab']) && $_GET['summaries_subtab'] === 'sales') ? ' active' : '' ?>"
                       id="summaries-sales-tab"
                       href="?tab=summaries&summaries_date_from=<?= urlencode($summaries_date_from) ?>&summaries_date_to=<?= urlencode($summaries_date_to) ?>&summaries_branch=<?= urlencode($summaries_branch) ?>&summaries_summary=<?= urlencode($summaries_summary) ?>&summaries_till_tab=<?= $summaries_selected_till_id ?>&summaries_subtab=sales"
                       role="tab">Sales Value Summary</a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Product Summaries Tab -->
                <div class="tab-pane fade<?= (!isset($_GET['summaries_subtab']) || $_GET['summaries_subtab'] === 'product') ? ' show active' : '' ?>" id="summaries-product" role="tabpanel">
                    <?php
                    // Get staff_id for selected till
                    $summaries_selected_staff_id = null;
                    $staff_res = $conn->query("SELECT staff_id FROM tills WHERE id = " . intval($summaries_selected_till_id));
                    if ($staff_row = $staff_res->fetch_assoc()) {
                        $summaries_selected_staff_id = $staff_row['staff_id'];
                    }
                    // Build sales filter for product summary
                    $product_where = ["s.`sold-by` = " . intval($summaries_selected_staff_id)];
                    if ($summaries_branch) $product_where[] = "s.`branch-id` = " . intval($summaries_branch);
                    if ($summaries_date_from) $product_where[] = "s.date >= '" . $conn->real_escape_string($summaries_date_from) . "'";
                    if ($summaries_date_to) $product_where[] = "s.date <= '" . $conn->real_escape_string($summaries_date_to) . "'";
                    $product_sql = "
                        SELECT 
                            s.date,
                            p.name AS product_name,
                            SUM(s.quantity) AS total_quantity
                        FROM sales s
                        JOIN products p ON s.`product-id` = p.id
                        WHERE " . implode(" AND ", $product_where) . "
                        GROUP BY s.date, p.name
                        ORDER BY s.date DESC, p.name ASC
                    ";
                    $product_res = $conn->query($product_sql);

                    // Group results by date for display
                    $product_summary = [];
                    $date_totals = [];
                    if ($product_res && $product_res->num_rows > 0) {
                        while ($row = $product_res->fetch_assoc()) {
                            $date = $row['date'];
                            $product_summary[$date][] = $row;
                            if (!isset($date_totals[$date])) $date_totals[$date] = 0;
                            $date_totals[$date] += $row['total_quantity'];
                        }
                    }
                    ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            Product Summaries (Grouped by Day)
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Total Quantity Sold</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($product_summary)): ?>
                                            <?php foreach ($product_summary as $date => $rows): ?>
                                                <tr>
                                                    <td colspan="2" class="fw-bold bg-light"><?= htmlspecialchars($date) ?></td>
                                                </tr>
                                                <?php foreach ($rows as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                                                        <td><?= $row['total_quantity'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td class="text-end fw-bold">Total for <?= htmlspecialchars($date) ?>:</td>
                                                    <td class="fw-bold"><?= $date_totals[$date] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">No product sales found for this till and filter.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Sales Value Summary Tab -->
                <div class="tab-pane fade<?= (isset($_GET['summaries_subtab']) && $_GET['summaries_subtab'] === 'sales') ? ' show active' : '' ?>" id="summaries-sales" role="tabpanel">
                    <?php
                    // Build sales filter for sales value summary
                    $salesval_where = ["s.`sold-by` = " . intval($summaries_selected_staff_id)];
                    if ($summaries_branch) $salesval_where[] = "s.`branch-id` = " . intval($summaries_branch);
                    if ($summaries_date_from) $salesval_where[] = "s.date >= '" . $conn->real_escape_string($summaries_date_from) . "'";
                    if ($summaries_date_to) $salesval_where[] = "s.date <= '" . $conn->real_escape_string($summaries_date_to) . "'";
                    $salesval_sql = "
                        SELECT 
                            s.date,
                            s.payment_method,
                            SUM(s.amount) AS total_sales
                        FROM sales s
                        WHERE " . implode(" AND ", $salesval_where) . "
                        GROUP BY s.date, s.payment_method
                        ORDER BY s.date DESC, s.payment_method ASC
                    ";
                    $salesval_res = $conn->query($salesval_sql);

                    // Group results by date for display
                    $salesval_summary = [];
                    $date_totals = [];
                    $grand_total = 0;
                    if ($salesval_res && $salesval_res->num_rows > 0) {
                        while ($row = $salesval_res->fetch_assoc()) {
                            $date = $row['date'];
                            $salesval_summary[$date][] = $row;
                            if (!isset($date_totals[$date])) $date_totals[$date] = 0;
                            $date_totals[$date] += $row['total_sales'];
                            $grand_total += $row['total_sales'];
                        }
                    }
                    ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            Sales Value Summary (Grouped by Day & Payment Method)
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Payment Method</th>
                                            <th>Total Sales Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($salesval_summary)): ?>
                                            <?php foreach ($salesval_summary as $date => $rows): ?>
                                                <tr>
                                                    <td colspan="2" class="fw-bold bg-light"><?= htmlspecialchars($date) ?></td>
                                                </tr>
                                                <?php foreach ($rows as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                                        <td>UGX <?= number_format($row['total_sales'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td class="text-end fw-bold">Total for <?= htmlspecialchars($date) ?>:</td>
                                                    <td class="fw-bold">UGX <?= number_format($date_totals[$date], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <!-- Grand total row -->
                                            <tr>
                                                <td class="text-end fw-bold bg-success text-white">Grand Total</td>
                                                <td class="fw-bold bg-success text-white">UGX <?= number_format($grand_total, 2) ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">No sales value summary found for this till and filter.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const branchSelect = document.getElementById('branch_id');
    const staffSelect = document.getElementById('staff_id');

    branchSelect.addEventListener('change', function () {
        const branchId = this.value;
        Array.from(staffSelect.options).forEach(option => {
            option.style.display = option.dataset.branch == branchId ? '' : 'none';
        });
        staffSelect.value = '';
    });

    // Tab state persistence using URL hash and Bootstrap events
    var tillTabs = document.getElementById('tillTabs');
    if (tillTabs) {
        var tabButtons = tillTabs.querySelectorAll('button[data-bs-toggle="tab"]');
        tabButtons.forEach(function(btn) {
            btn.addEventListener('shown.bs.tab', function (e) {
                var tabId = e.target.getAttribute('data-bs-target').replace('#', '');
                // Update URL without reload
                var url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                history.replaceState(null, '', url);
            });
        });
    }
});
</script>

<!-- Insert styling block for the new create-till-card -->
<style>
/* Create & Assign Till form styling (match Add New Expense form) */
.create-till-card {
    border-radius:12px;
    box-shadow:0 4px 12px var(--card-shadow);
    background:var(--card-bg);
}
.create-till-card .card-header.title-card {
    background:var(--primary-color);
    color:#fff !important;
    border-radius:12px 12px 0 0 !important;
    font-weight:600;
    font-size:1.1rem;
    letter-spacing:1px;
}
.create-till-card .form-label { font-weight:600; }
.create-till-card .form-control,
.create-till-card .form-select { border-radius:8px; }

body.dark-mode .create-till-card {
    background:#23243a !important;
    color:#fff !important;
}
body.dark-mode .create-till-card .card-header.title-card {
    background:#2c3e50 !important;
    color:#1abc9c !important;
}
body.dark-mode .create-till-card .form-label {
    color:#fff !important;
}
body.dark-mode .create-till-card .form-control,
body.dark-mode .create-till-card .form-select {
    background:#23243a !important;
    color:#fff !important;
    border:1px solid #444 !important;
}
body.dark-mode .create-till-card .form-control:focus,
body.dark-mode .create-till-card .form-select:focus {
    background:#23243a !important;
    color:#fff !important;
}
</style>

<?php include '../includes/footer.php'; ?>
