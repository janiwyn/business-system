<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

// --- SQL (run once in migration or keep here as safety) ---
$conn->query("
CREATE TABLE IF NOT EXISTS tills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  creation_date DATE NOT NULL,
  name VARCHAR(100) NOT NULL,
  branch_id INT NOT NULL,
  staff_id INT NOT NULL,
  phone_number VARCHAR(30) NOT NULL,
  INDEX(branch_id), INDEX(staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Ensure table for till removals exists (idempotent)
$conn->query("
    CREATE TABLE IF NOT EXISTS till_removals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        till_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        approved_by INT DEFAULT NULL,
        balance_after DECIMAL(15,2) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(till_id), INDEX(branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// NEW: Ensure till_transactions table exists (logs removals; can be extended for sales if desired)
$conn->query("
    CREATE TABLE IF NOT EXISTS till_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        till_id INT NOT NULL,
        event_type ENUM('removal','sale','return') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        balance_after DECIMAL(15,2) NOT NULL,
        reference_removal_id INT NULL,
        approved_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (branch_id), INDEX (till_id), INDEX (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// --- Till creation handler (only runs for action=create_till) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_till') {
    $creation_date = $_POST['creation_date'] ?? null;
    $till_name     = trim($_POST['till_name'] ?? '');
    $branch_id     = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
    $staff_id      = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
    $phone_number  = trim($_POST['phone_number'] ?? '');

    if ($creation_date && $till_name !== '' && $branch_id > 0 && $staff_id > 0 && $phone_number !== '') {
        $stmt = $conn->prepare("INSERT INTO tills (creation_date, name, branch_id, staff_id, phone_number) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiis", $creation_date, $till_name, $branch_id, $staff_id, $phone_number); // fixed format (was ssiss)
        if ($stmt->execute()) {
            $success_message = "Till created successfully!";
        } else {
            $error_message = "Failed to create till: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "All till fields are required.";
    }
}

// NEW: Handle Till Safe removal submission
$safe_success = '';
$safe_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_till'])) {
    $safe_branch_id = intval($_POST['safe_branch_id'] ?? 0);
    $safe_till_id   = intval($_POST['safe_till_id'] ?? 0);
    $safe_amount    = floatval($_POST['safe_amount'] ?? 0);

    if ($safe_branch_id <= 0 || $safe_till_id <= 0 || $safe_amount <= 0) {
        $safe_error = 'Please select branch, till, and enter a valid amount.';
    } else {
        // Get till info
        $tillQ = $conn->query("SELECT id, branch_id, staff_id FROM tills WHERE id = " . $safe_till_id . " LIMIT 1");
        $till  = $tillQ ? $tillQ->fetch_assoc() : null;
        if (!$till) {
            $safe_error = 'Selected till not found.';
        } elseif (intval($till['branch_id']) !== $safe_branch_id) {
            $safe_error = 'Selected till does not belong to the chosen branch.';
        } else {
            $staff_id = intval($till['staff_id']);
            // Total sales for this till (staff + branch), all time
            $sales_where = [
                "s.`sold-by` = " . $staff_id,
                "s.`branch-id` = " . intval($till['branch_id'])
            ];
            $sales_sql = "SELECT SUM(s.amount) AS total_sales FROM sales s WHERE " . implode(' AND ', $sales_where);
            $sales_res = $conn->query($sales_sql);
            $total_sales = ($sales_res && ($r = $sales_res->fetch_assoc())) ? floatval($r['total_sales']) : 0.0;

            // Total already removed
            $rem_sql = "SELECT SUM(amount) AS total_removed FROM till_removals WHERE till_id = " . $safe_till_id;
            $rem_res = $conn->query($rem_sql);
            $total_removed = ($rem_res && ($r2 = $rem_res->fetch_assoc())) ? floatval($r2['total_removed']) : 0.0;

            $current_balance = $total_sales - $total_removed;
            if ($safe_amount > $current_balance) {
                $safe_error = 'Amount exceeds current till balance.';
            } else {
                $balance_after = $current_balance - $safe_amount;
                $approved_by = isset($_POST['safe_approved_by']) ? intval($_POST['safe_approved_by']) : null;
                $approved_by = $approved_by ?: (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : null));

                $stmt = $conn->prepare("INSERT INTO till_removals (branch_id, till_id, amount, approved_by, balance_after) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiddi", $safe_branch_id, $safe_till_id, $safe_amount, $approved_by, $balance_after);
                if ($stmt->execute()) {
                    $removal_id = $stmt->insert_id;
                    // Log into till_transactions
                    $tx = $conn->prepare("INSERT INTO till_transactions (branch_id, till_id, event_type, amount, balance_after, reference_removal_id, approved_by) VALUES (?,?,?,?,?,?,?)");
                    $etype = 'removal';
                    $tx->bind_param("iisdiii", $safe_branch_id, $safe_till_id, $etype, $safe_amount, $balance_after, $removal_id, $approved_by);
                    $tx->execute();
                    $tx->close();
                    $safe_success = 'Till removal recorded successfully.';
                } else {
                    $safe_error = 'Failed to record till removal.';
                }
                $stmt->close();
            }
        }
    }
}

// NEW: Undo (return) handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo_removal'])) {
    $undo_removal_id = intval($_POST['undo_removal_id'] ?? 0);
    $undo_branch_id  = intval($_POST['undo_branch_id'] ?? 0);
    $undo_till_id    = intval($_POST['undo_till_id'] ?? 0);
    $return_amount   = floatval($_POST['return_amount'] ?? 0);

    if ($undo_removal_id <= 0 || $undo_branch_id <= 0 || $undo_till_id <= 0 || $return_amount <= 0) {
        $safe_error = 'Invalid undo submission.';
    } else {
        // Fetch original removal row
        $orig = $conn->query("SELECT amount FROM till_removals WHERE id = $undo_removal_id AND till_id = $undo_till_id AND branch_id = $undo_branch_id");
        $orig_row = $orig ? $orig->fetch_assoc() : null;
        if (!$orig_row) {
            $safe_error = 'Original removal not found.';
        } elseif ($return_amount > floatval($orig_row['amount'])) {
            $safe_error = 'Return amount exceeds original removal.';
        } else {
            // Compute current balance (same logic as removal)
            $tillQ = $conn->query("SELECT staff_id, branch_id FROM tills WHERE id = $undo_till_id LIMIT 1");
            $tillData = $tillQ ? $tillQ->fetch_assoc() : null;
            if (!$tillData) {
                $safe_error = 'Till not found for undo.';
            } else {
                $staff_id = intval($tillData['staff_id']);
                $sales_sql = "SELECT SUM(s.amount) AS total_sales FROM sales s WHERE s.`sold-by` = $staff_id AND s.`branch-id` = " . intval($tillData['branch_id']);
                $sales_res = $conn->query($sales_sql);
                $total_sales = ($sales_res && ($r = $sales_res->fetch_assoc())) ? floatval($r['total_sales']) : 0.0;

                $rem_sql = "SELECT SUM(amount) AS total_removed FROM till_removals WHERE till_id = $undo_till_id";
                $rem_res = $conn->query($rem_sql);
                $total_removed = ($rem_res && ($r2 = $rem_res->fetch_assoc())) ? floatval($r2['total_removed']) : 0.0;

                $current_balance = $total_sales - $total_removed;
                // New balance after returning money
                $balance_after = $current_balance + $return_amount;

                // Insert negative removal row (undo entry)
                $stmt = $conn->prepare("INSERT INTO till_removals (branch_id, till_id, amount, approved_by, balance_after) VALUES (?, ?, ?, ?, ?)");
                $neg_amount = -1 * $return_amount;
                $approved_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : null);
                $stmt->bind_param("iiddi", $undo_branch_id, $undo_till_id, $neg_amount, $approved_by, $balance_after);
                if ($stmt->execute()) {
                    $undo_row_id = $stmt->insert_id;
                    // Log transaction (return)
                    $tx = $conn->prepare("INSERT INTO till_transactions (branch_id, till_id, event_type, amount, balance_after, reference_removal_id, approved_by) VALUES (?,?,?,?,?,?,?)");
                    $etype = 'return';
                    $tx->bind_param("iisdiii", $undo_branch_id, $undo_till_id, $etype, $return_amount, $balance_after, $undo_row_id, $approved_by);
                    $tx->execute();
                    $tx->close();
                    $safe_success = 'Amount returned successfully.';
                } else {
                    $safe_error = 'Failed to record undo.';
                }
                $stmt->close();
            }
        }
    }
}

// Fetch branches and staff for dropdowns
$branches = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");
$staff = $conn->query("SELECT id, username, `branch-id` FROM users WHERE role='staff' ORDER BY username ASC");
// NEW: Fetch approvers (admins/managers) for Approved By select
$approvers_res = $conn->query("SELECT id, username, role FROM users WHERE role IN ('admin','manager') ORDER BY username ASC");
?>


<div class="container mt-4">
    <h2 class="mb-4" style="color:#1abc9c;"><b>Till Management</b></h2>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php elseif (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- NEW: Top navigation pills (tabs) -->
    <style>
    /* Main Till Management top tabs styled like Till View till pills */
    .tm-main-tabs { display:flex; flex-wrap:wrap; gap:.75rem; margin-top:.25rem; border:none; }
    .tm-main-tabs .tm-tab-btn {
        border:2px solid var(--primary-color);
        background:#fff;
        color:var(--primary-color);
        font-weight:600;
        border-radius:14px;
        padding:.45rem 1.1rem;
        box-shadow:0 2px 6px rgba(0,0,0,.08);
        transition:background .18s,color .18s,box-shadow .18s,transform .18s;
        font-size:.95rem;
    }
    .tm-main-tabs .tm-tab-btn:hover {
        background:var(--primary-color);
        color:#fff;
        transform:translateY(-2px);
    }
    .tm-main-tabs .tm-tab-btn.active {
        background:var(--primary-color);
        color:#fff;
        box-shadow:0 4px 10px rgba(26,188,156,.35);
    }
    .tm-main-tabs .tm-tab-btn:focus { outline:none; box-shadow:0 0 0 3px rgba(26,188,156,.25); }

    body.dark-mode .tm-main-tabs .tm-tab-btn {
        background:#23243a;
        border-color:#1abc9c;
        color:#1abc9c;
        box-shadow:0 2px 6px rgba(0,0,0,.4);
    }
    body.dark-mode .tm-main-tabs .tm-tab-btn:hover {
        background:#1abc9c;
        color:#fff;
    }
    body.dark-mode .tm-main-tabs .tm-tab-btn.active {
        background:#1abc9c;
        color:#fff;
        box-shadow:0 4px 12px rgba(26,188,156,.55);
    }
    </style>

    <ul class="nav nav-pills tm-main-tabs" id="tillTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link tm-tab-btn<?= (!isset($_GET['tab']) || $_GET['tab'] === 'create-assign') ? ' active' : '' ?>"
                    id="create-assign-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#create-assign"
                    type="button"
                    role="tab">Create &amp; Assign Till</button>
        </li>
        <li class="nav-item">
            <button class="nav-link tm-tab-btn<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-management') ? ' active' : '' ?>"
                    id="till-management-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#till-management"
                    type="button"
                    role="tab">Till Management</button>
        </li>
        <li class="nav-item">
            <button class="nav-link tm-tab-btn<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-view') ? ' active' : '' ?>"
                    id="till-view-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#till-view"
                    type="button"
                    role="tab">Till View</button>
        </li>
        <li class="nav-item">
            <button class="nav-link tm-tab-btn<?= (isset($_GET['tab']) && $_GET['tab'] === 'summaries') ? ' active' : '' ?>"
                    id="summaries-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#summaries"
                    type="button"
                    role="tab">Summaries</button>
        </li>
        <li class="nav-item">
            <button class="nav-link tm-tab-btn<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-safes') ? ' active' : '' ?>"
                    id="till-safes-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#till-safes"
                    type="button"
                    role="tab">Till Safes</button>
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
                        <input type="hidden" name="action" value="create_till">
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
            <div class="card mb-4">
                <div class="card-header">Manage Tills</div>
                <div class="card-body">
                    <div class="transactions-table">
                        <table>
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
                                        <button class="btn btn-warning btn-sm">Edit</button>
                                        <button class="btn btn-danger btn-sm">Delete</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Till View Tab -->
        <div class="tab-pane fade<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-view') ? ' show active' : '' ?>" id="till-view" role="tabpanel">
            <!-- Filter Bar updated -->
            <form method="GET" id="tillViewFilterForm" class="pa-filter-bar till-view-filter-bar d-flex align-items-center flex-wrap gap-2 mb-3 p-2 rounded">
                <input type="hidden" name="tab" value="till-view">
                <label class="fw-bold me-2 mb-0">From:</label>
                <input type="date" class="form-select" style="width:150px;" id="filter_date_from" name="filter_date_from" value="<?= htmlspecialchars($_GET['filter_date_from'] ?? '') ?>">
                <label class="fw-bold me-2 mb-0">To:</label>
                <input type="date" class="form-select" style="width:150px;" id="filter_date_to" name="filter_date_to" value="<?= htmlspecialchars($_GET['filter_date_to'] ?? '') ?>">
                <label class="fw-bold me-2 mb-0">Branch:</label>
                <select class="form-select" style="width:180px;" id="filter_branch" name="filter_branch">
                    <option value="">-- All Branches --</option>
                    <?php
                    $branches->data_seek(0);
                    while ($branch = $branches->fetch_assoc()):
                        $selected = (isset($_GET['filter_branch']) && $_GET['filter_branch'] == $branch['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= $branch['id'] ?>" <?= $selected ?>><?= htmlspecialchars($branch['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <label class="fw-bold me-2 mb-0">Summary:</label>
                <select class="form-select" style="width:160px;" id="filter_summary" name="filter_summary">
                    <option value="detailed" <?= (($_GET['filter_summary'] ?? '') == 'detailed' ? 'selected' : '') ?>>Detailed</option>
                    <option value="summarized" <?= (($_GET['filter_summary'] ?? '') == 'summarized' ? 'selected' : '') ?>>Summarized</option>
                </select>
                <!-- NEW: Entries input -->
                <label class="fw-bold me-2 mb-0">Entries:</label>
                <input type="number" class="form-select" style="width:100px;" id="entries" name="entries" min="1" max="1000" value="<?= htmlspecialchars($_GET['entries'] ?? '50') ?>">
                <button type="submit" class="btn btn-primary ms-auto">Filter</button>
            </form>

            <?php
            // Prepare filters
            $filter_branch = $_GET['filter_branch'] ?? '';
            $filter_date_from = $_GET['filter_date_from'] ?? '';
            $filter_date_to = $_GET['filter_date_to'] ?? '';
            $filter_summary = $_GET['filter_summary'] ?? 'detailed';
            // NEW: entries (limit)
            $entries = isset($_GET['entries']) ? max(1, min(1000, intval($_GET['entries']))) : 50;

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

            <!-- Sub Tabs for Tills (styled green) -->
            <ul class="nav nav-pills mb-3" id="tillSubTabs" role="tablist">
                <?php if ($till_tabs && $till_tabs->num_rows > 0): ?>
                    <?php while ($till = $till_tabs->fetch_assoc()): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= ($selected_till_id == $till['id'] ? 'active' : '') ?>" "
                               id="till-tab-<?= $till['id'] ?>"
                               href="?tab=till-view&filter_date_from=<?= urlencode($filter_date_from) ?>&filter_date_to=<?= urlencode($filter_date_to) ?>&filter_branch=<?= urlencode($filter_branch) ?>&filter_summary=<?= urlencode($filter_summary) ?>&entries=<?= urlencode($entries) ?>&till_tab=<?= $till['id'] ?>"
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

            <?php
            // Define sales result BEFORE rendering the table
            if ($selected_till_id && $selected_staff_id) {
                $sales_where = ["s.`sold-by` = " . intval($selected_staff_id)];
                if (!empty($filter_branch)) {
                    $sales_where[] = "s.`branch-id` = " . intval($filter_branch);
                }
                if (!empty($filter_date_from)) {
                    $sales_where[] = "DATE(s.date) >= '" . $conn->real_escape_string($filter_date_from) . "'";
                }
                if (!empty($filter_date_to)) {
                    $sales_where[] = "DATE(s.date) <= '" . $conn->real_escape_string($filter_date_to) . "'";
                }
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
                    WHERE " . implode(' AND ', $sales_where) . "
                    ORDER BY s.date DESC
                    LIMIT " . $entries . "
                ";
                $sales_res = $conn->query($sales_sql);
            }
            ?>

            <?php if ($selected_till_id && $selected_staff_id): ?>
            <div class="card mt-3">
                <div class="card-header" style="color:#1abc9c;"><b>Sales Records for Till</b></div>
                <div class="card-body table-responsive">
                    <div class="transactions-table">
                        <table>
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
                                if (!empty($sales_res) && $sales_res->num_rows > 0):
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
                                    <td><small class="text-muted"><?= htmlspecialchars($row['date']) ?></small></td>
                                    <td><?= htmlspecialchars($row['sold_by']) ?></td>
                                </tr>
                                <?php endwhile; else: ?>
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
            <!-- Filter Bar styled like Till View -->
            <form method="GET" id="summariesFilterForm" class="pa-filter-bar till-view-filter-bar summaries-filter-bar d-flex align-items-center flex-wrap gap-2 mb-3 p-2 rounded">
                <input type="hidden" name="tab" value="summaries">
                <label class="fw-bold me-2 mb-0">From:</label>
                <input type="date" class="form-select" style="width:150px;" id="summaries_date_from" name="summaries_date_from" value="<?= htmlspecialchars($_GET['summaries_date_from'] ?? '') ?>">
                <label class="fw-bold me-2 mb-0">To:</label>
                <input type="date" class="form-select" style="width:150px;" id="summaries_date_to" name="summaries_date_to" value="<?= htmlspecialchars($_GET['summaries_date_to'] ?? '') ?>">
                <label class="fw-bold me-2 mb-0">Branch:</label>
                <select class="form-select" style="width:180px;" id="summaries_branch" name="summaries_branch">
                    <option value="">-- All Branches --</option>
                    <?php
                    $branches->data_seek(0);
                    while ($branch = $branches->fetch_assoc()):
                        $selected = (isset($_GET['summaries_branch']) && $_GET['summaries_branch'] == $branch['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= $branch['id'] ?>" <?= $selected ?>><?= htmlspecialchars($branch['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <label class="fw-bold me-2 mb-0">Summary:</label>
                <select class="form-select" style="width:160px;" id="summaries_summary" name="summaries_summary">
                    <option value="detailed" <?= (($_GET['summaries_summary'] ?? '') == 'detailed' ? 'selected' : '') ?>>Detailed</option>
                    <option value="summarized" <?= (($_GET['summaries_summary'] ?? '') == 'summarized' ? 'selected' : '') ?>>Summarized</option>
                </select>
                <label class="fw-bold me-2 mb-0">Entries:</label>
                <input type="number" class="form-select" style="width:100px;" id="summaries_entries" name="summaries_entries" min="1" max="1000" value="<?= htmlspecialchars($_GET['summaries_entries'] ?? '50') ?>">
                <button type="submit" class="btn btn-primary ms-auto">Filter</button>
            </form>

            <?php
            // Prepare filters for summaries
            $summaries_branch = $_GET['summaries_branch'] ?? '';
            $summaries_date_from = $_GET['summaries_date_from'] ?? '';
            $summaries_date_to = $_GET['summaries_date_to'] ?? '';
            $summaries_summary = $_GET['summaries_summary'] ?? 'detailed';
            // NEW: entries (limit) for summaries
            $summaries_entries = isset($_GET['summaries_entries']) ? max(1, min(1000, intval($_GET['summaries_entries']))) : 50;

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
            <br>
            <!-- Sub Tabs for Tills (Summaries) -->
            <ul class="nav nav-pills mb-3" id="summariesTillSubTabs" role="tablist">
                <?php if ($summaries_till_tabs && $summaries_till_tabs->num_rows > 0): ?>
                    <?php while ($till = $summaries_till_tabs->fetch_assoc()): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= ($summaries_selected_till_id == $till['id'] ? 'active' : '') ?>"
                               id="summaries-till-tab-<?= $till['id'] ?>"
                               href="?tab=summaries&summaries_date_from=<?= urlencode($summaries_date_from) ?>&summaries_date_to=<?= urlencode($summaries_date_to) ?>&summaries_branch=<?= urlencode($summaries_branch) ?>&summaries_summary=<?= urlencode($summaries_summary) ?>&summaries_entries=<?= urlencode($summaries_entries) ?>&summaries_till_tab=<?= $till['id'] ?>"
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
                       href="?tab=summaries&summaries_date_from=<?= urlencode($summaries_date_from) ?>&summaries_date_to=<?= urlencode($summaries_date_to) ?>&summaries_branch=<?= urlencode($summaries_branch) ?>&summaries_summary=<?= urlencode($summaries_summary) ?>&summaries_entries=<?= urlencode($summaries_entries) ?>&summaries_till_tab=<?= $summaries_selected_till_id ?>&summaries_subtab=product"
                       role="tab">Product Summaries</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= (isset($_GET['summaries_subtab']) && $_GET['summaries_subtab'] === 'sales') ? ' active' : '' ?>"
                       id="summaries-sales-tab"
                       href="?tab=summaries&summaries_date_from=<?= urlencode($summaries_date_from) ?>&summaries_date_to=<?= urlencode($summaries_date_to) ?>&summaries_branch=<?= urlencode($summaries_branch) ?>&summaries_summary=<?= urlencode($summaries_summary) ?>&summaries_entries=<?= urlencode($summaries_entries) ?>&summaries_till_tab=<?= $summaries_selected_till_id ?>&summaries_subtab=sales"
                       role="tab">Sales Value Summary</a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Product Summaries Tab -->
                <div class="tab-pane fade<?= (!isset($_GET['summaries_subtab']) || $_GET['summaries_subtab'] === 'product') ? ' show active' : '' ?>" id="summaries-product" role="tabpanel">
                    <?php
                    // Get staff_id for selected till
                    $summaries_selected_staff_id = null;
                    $staff_res = $conn->query("SELECT staff_id, branch_id FROM tills WHERE id = " . intval($summaries_selected_till_id));
                    $till_branch_id = null;
                    if ($staff_row = $staff_res->fetch_assoc()) {
                        $summaries_selected_staff_id = $staff_row['staff_id'];
                        $till_branch_id = $staff_row['branch_id'];
                    }
                    // Build sales filter for product summary (match sales product summary style)
                    $product_where = ["s.`sold-by` = " . intval($summaries_selected_staff_id)];
                    if ($summaries_branch) {
                        $product_where[] = "s.`branch-id` = " . intval($summaries_branch);
                    }
                    if ($summaries_date_from) {
                        $product_where[] = "DATE(s.date) >= '" . $conn->real_escape_string($summaries_date_from) . "'";
                    }
                    if ($summaries_date_to) {
                        $product_where[] = "DATE(s.date) <= '" . $conn->real_escape_string($summaries_date_to) . "'";
                    }
                    $product_sql = "
                        SELECT 
                            DATE(s.date) AS sale_date,
                            b.name AS branch_name,
                            p.name AS product_name,
                            SUM(s.quantity) AS items_sold
                        FROM sales s
                        JOIN products p ON s.`product-id` = p.id
                        JOIN branch b ON s.`branch-id` = b.id
                        WHERE " . implode(" AND ", $product_where) . "
                        GROUP BY sale_date, branch_name, product_name
                        ORDER BY sale_date DESC, branch_name ASC, product_name ASC
                        LIMIT " . $summaries_entries . "
                    ";
                    $product_res = $conn->query($product_sql);
                    ?>
                    <div class="card mt-3">
                        <div class="card-header" style="color:#1abc9c;">
                            <b>Product Summaries (Items Sold Per Day)</b>
                        </div>
                        <div class="card-body">
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
                                        if ($product_res && $product_res->num_rows > 0):
                                            $prev_date = null;
                                            $prev_branch = null;
                                            while ($row = $product_res->fetch_assoc()):
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
                                            <td colspan="4" class="text-center text-muted">No product summary data found for this till and filter.</td>
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
                        LIMIT " . $summaries_entries . "
                    ";
                    $salesval_res = $conn->query($salesval_sql);

                    // Flatten rows for output and compute totals
                    $grouped = [];
                    $grand_total = 0;
                    if ($salesval_res && $salesval_res->num_rows > 0) {
                        while ($r = $salesval_res->fetch_assoc()) {
                            $d = $r['date'];
                            if (!isset($grouped[$d])) $grouped[$d] = [];
                            $grouped[$d][] = $r;
                            $grand_total += $r['total_sales'];
                        }
                    }
                    ?>
                    <div class="card mt-3">
                        <div class="card-header" style="color:#1abc9c;"><b>Sales Value Summary (Payment Methods Per Day)</b></div>
                        <div class="card-body">
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
                                        <?php if (!empty($grouped)): ?>
                                            <?php foreach ($grouped as $day => $rows): ?>
                                                <?php
                                                    $day_total = 0;
                                                    foreach ($rows as $row):
                                                        $day_total += $row['total_sales'];
                                                ?>
                                                <tr>
                                                    <td><small class="text-muted"><?= htmlspecialchars($day) ?></small></td>
                                                    <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                                    <td><span class="fw-bold text-success">UGX <?= number_format($row['total_sales'], 2) ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td colspan="2" class="text-end fw-bold">Total for <?= htmlspecialchars($day) ?></td>
                                                    <td><span class="fw-bold text-primary">UGX <?= number_format($day_total, 2) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <td colspan="2" class="text-end fw-bold">Grand Total</td>
                                                <td><span class="fw-bold text-danger">UGX <?= number_format($grand_total, 2) ?></span></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No sales value summary found for this till and filter.</td>
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

        <!-- NEW: Till Safes Tab -->
        <div class="tab-pane fade<?= (isset($_GET['tab']) && $_GET['tab'] === 'till-safes') ? ' show active' : '' ?>" id="till-safes" role="tabpanel">
            <!-- Removal Form -->
            <div class="card mb-4">
                <div class="card-header title-card">Remove Money From Till</div>
                <div class="card-body">
                    <?php if (!empty($safe_success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($safe_success) ?></div>
                    <?php elseif (!empty($safe_error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($safe_error) ?></div>
                    <?php endif; ?>
                    <form method="POST" class="row g-3" id="tillRemovalForm">
                        <input type="hidden" name="tab" value="till-safes">
                        <div class="col-md-3">
                            <label class="form-label">Branch</label>
                            <select class="form-select" name="safe_branch_id" id="safe_branch_id" required>
                                <option value="">-- Select Branch --</option>
                                <?php
                                $bq = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");
                                while ($b = $bq->fetch_assoc()):
                                    $sel = (isset($_POST['safe_branch_id']) && intval($_POST['safe_branch_id']) == $b['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $b['id'] ?>" <?= $sel ?>><?= htmlspecialchars($b['name']) ?></option>
                                <?php endwhile; ?>
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Till</label>
                            <select class="form-select" name="safe_till_id" id="safe_till_id" required>
                                <option value="">-- Select Till --</option>
                                <?php
                                // Add data-branch attribute for filtering
                                $tq = $conn->query("
                                    SELECT t.id, t.name, t.branch_id, u.username AS staff_name, b.name AS branch_name
                                    FROM tills t
                                    JOIN users u ON t.staff_id = u.id
                                    JOIN branch b ON t.branch_id = b.id
                                    ORDER BY b.name ASC, t.name ASC
                                ");
                                while ($t = $tq->fetch_assoc()):
                                    $sel = (isset($_POST['safe_till_id']) && intval($_POST['safe_till_id']) == $t['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $t['id'] ?>" data-branch="<?= $t['branch_id'] ?>" <?= $sel ?>>
                                        [<?= htmlspecialchars($t['branch_name']) ?>] <?= htmlspecialchars($t['name']) ?> — <?= htmlspecialchars($t['staff_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Amount to Remove (UGX)</label>
                            <input type="number" class="form-control" name="safe_amount" step="0.01" min="0.01" placeholder="0.00" required value="<?= htmlspecialchars($_POST['safe_amount'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Approved By</label>
                            <select class="form-select" name="safe_approved_by" id="safe_approved_by">
                                <option value="">-- Select Approver --</option>
                                <?php while ($ap = $approvers_res->fetch_assoc()): ?>
                                    <option value="<?= $ap['id'] ?>" <?= (isset($_POST['safe_approved_by']) && intval($_POST['safe_approved_by']) == $ap['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ap['username']) ?> (<?= htmlspecialchars($ap['role']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" name="remove_from_till" class="btn btn-primary">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Inline JS: filter tills by branch -->
            <script>
            (function(){
                const branchSelect = document.getElementById('safe_branch_id');
                const tillSelect = document.getElementById('safe_till_id');
                if (branchSelect && tillSelect) {
                    function filterTills() {
                        const bid = branchSelect.value;
                        let firstVisible = null;
                        Array.from(tillSelect.options).forEach(opt => {
                            if (opt.value === '') return;
                            const match = !bid || opt.getAttribute('data-branch') === bid;
                            opt.style.display = match ? '' : 'none';
                            if (match && !firstVisible) firstVisible = opt;
                        });
                        // Reset selection if current hidden
                        if (tillSelect.selectedIndex > 0) {
                            const selOpt = tillSelect.options[tillSelect.selectedIndex];
                            if (selOpt.style.display === 'none') tillSelect.value = '';
                        }
                        // Optionally auto-select first visible
                        // if (!tillSelect.value && firstVisible) tillSelect.value = firstVisible.value;
                    }
                    branchSelect.addEventListener('change', filterTills);
                    filterTills();
                }
            })();
            </script>

            <!-- Filter Bar (like Till View) -->
            <?php
            $safe_branch = $_GET['safe_branch'] ?? '';
            $safe_date_from = $_GET['safe_date_from'] ?? '';
            $safe_date_to = $_GET['safe_date_to'] ?? '';
            ?>
            <form method="GET" class="pa-filter-bar till-view-filter-bar d-flex align-items-center flex-wrap gap-2 mb-3 p-2 rounded">
                <input type="hidden" name="tab" value="till-safes">
                <label class="fw-bold me-2 mb-0">From:</label>
                <input type="date" class="form-select" style="width:150px;" name="safe_date_from" value="<?= htmlspecialchars($safe_date_from) ?>">
                <label class="fw-bold me-2 mb-0">To:</label>
                <input type="date" class="form-select" style="width:150px;" name="safe_date_to" value="<?= htmlspecialchars($safe_date_to) ?>">
                <label class="fw-bold me-2 mb-0">Branch:</label>
                <select class="form-select" style="width:180px;" name="safe_branch">
                    <option value="">-- All Branches --</option>
                    <?php
                    $b2 = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");
                    while ($br = $b2->fetch_assoc()):
                        $sel = ($safe_branch == $br['id']) ? 'selected' : '';
                    ?>
                        <option value="<?= $br['id'] ?>" <?= $sel ?>><?= htmlspecialchars($br['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn btn-primary ms-auto">Filter</button>
            </form>

            <!-- Sub Tabs: Till Balances / Till Removals -->
            <ul class="nav nav-tabs mb-3" id="tillSafesSubTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link<?= (!isset($_GET['safes_subtab']) || $_GET['safes_subtab'] === 'balances') ? ' active' : '' ?>"
                       href="?tab=till-safes&safe_date_from=<?= urlencode($safe_date_from) ?>&safe_date_to=<?= urlencode($safe_date_to) ?>&safe_branch=<?= urlencode($safe_branch) ?>&safes_subtab=balances">Till Balances</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= (isset($_GET['safes_subtab']) && $_GET['safes_subtab'] === 'removals') ? ' active' : '' ?>"
                       href="?tab=till-safes&safe_date_from=<?= urlencode($safe_date_from) ?>&safe_date_to=<?= urlencode($safe_date_to) ?>&safe_branch=<?= urlencode($safe_branch) ?>&safes_subtab=removals">Till Removals</a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Till Balances -->
                <div class="tab-pane fade<?= (!isset($_GET['safes_subtab']) || $_GET['safes_subtab'] === 'balances') ? ' show active' : '' ?>" id="till-balances" role="tabpanel">
                    <?php
                    // Build filter constraints for balances
                    $till_list_where = [];
                    if (!empty($safe_branch)) $till_list_where[] = "t.branch_id = " . intval($safe_branch);
                    $till_list_sql = "
                        SELECT t.id, t.name, t.branch_id, b.name AS branch_name, u.username AS staff_name, u.id AS staff_id
                        FROM tills t
                        JOIN branch b ON t.branch_id = b.id
                        JOIN users u ON t.staff_id = u.id
                        " . ($till_list_where ? " WHERE " . implode(' AND ', $till_list_where) : "") . "
                        ORDER BY b.name ASC, t.name ASC
                    ";
                    $till_list_res = $conn->query($till_list_sql);
                    $till_bal_rows = [];
                    if ($till_list_res && $till_list_res->num_rows > 0) {
                        while ($trow = $till_list_res->fetch_assoc()) {
                            $sales_cond = [
                                "s.`sold-by` = " . intval($trow['staff_id']),
                                "s.`branch-id` = " . intval($trow['branch_id'])
                            ];
                            if (!empty($safe_date_from)) $sales_cond[] = "DATE(s.date) >= '" . $conn->real_escape_string($safe_date_from) . "'";
                            if (!empty($safe_date_to))   $sales_cond[] = "DATE(s.date) <= '" . $conn->real_escape_string($safe_date_to) . "'";
                            $sum_sales_sql = "SELECT SUM(s.amount) AS total_sales FROM sales s WHERE " . implode(' AND ', $sales_cond);
                            $sum_sales_res = $conn->query($sum_sales_sql);
                            $sum_sales = ($sum_sales_res && ($sr = $sum_sales_res->fetch_assoc())) ? floatval($sr['total_sales']) : 0.0;

                            $rem_cond = ["r.till_id = " . intval($trow['id'])];
                            if (!empty($safe_date_from)) $rem_cond[] = "DATE(r.created_at) >= '" . $conn->real_escape_string($safe_date_from) . "'";
                            if (!empty($safe_date_to))   $rem_cond[] = "DATE(r.created_at) <= '" . $conn->real_escape_string($safe_date_to) . "'";
                            $sum_rem_sql = "SELECT SUM(r.amount) AS total_removed FROM till_removals r " . (count($rem_cond) ? "WHERE " . implode(' AND ', $rem_cond) : "");
                            $sum_rem_res = $conn->query($sum_rem_sql);
                            $sum_removed = ($sum_rem_res && ($rr = $sum_rem_res->fetch_assoc())) ? floatval($rr['total_removed']) : 0.0;

                            $till_bal_rows[] = [
                                'branch_name' => $trow['branch_name'],
                                'till_name'   => $trow['name'],
                                'staff_name'  => $trow['staff_name'],
                                'total_sales' => $sum_sales,
                                'total_removed' => $sum_removed,
                                'balance'     => $sum_sales - $sum_removed
                            ];
                        }
                    }
                    ?>
                    <div class="card">
                        <div class="card-header" style="color:#1abc9c;"><b>Till Balances</b></div>
                        <div class="card-body">
                            <div class="transactions-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Branch</th>
                                            <th>Till</th>
                                            <th>Manager</th>
                                            <th>Total Sales</th>
                                            <th>Total Removed</th>
                                            <th>Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($till_bal_rows)): ?>
                                            <?php foreach ($till_bal_rows as $r): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($r['branch_name']) ?></td>
                                                    <td><?= htmlspecialchars($r['till_name']) ?></td>
                                                    <td><?= htmlspecialchars($r['staff_name']) ?></td>
                                                    <td><span class="fw-bold text-success">UGX <?= number_format($r['total_sales'], 2) ?></span></td>
                                                    <td><span class="fw-bold text-danger">UGX <?= number_format($r['total_removed'], 2) ?></span></td>
                                                    <td><span class="fw-bold text-primary">UGX <?= number_format($r['balance'], 2) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center text-muted">No tills found for the selected filters.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Till Removals -->
                <div class="tab-pane fade<?= (isset($_GET['safes_subtab']) && $_GET['safes_subtab'] === 'removals') ? ' show active' : '' ?>" id="till-removals" role="tabpanel">
                    <?php
                    $rem_where = [];
                    if (!empty($safe_branch)) $rem_where[] = "r.branch_id = " . intval($safe_branch);
                    if (!empty($safe_date_from)) $rem_where[] = "DATE(r.created_at) >= '" . $conn->real_escape_string($safe_date_from) . "'";
                    if (!empty($safe_date_to))   $rem_where[] = "DATE(r.created_at) <= '" . $conn->real_escape_string($safe_date_to) . "'";
                    $rem_sql = "
                        SELECT r.id, r.created_at, b.name AS branch_name, t.id AS till_id, t.name AS till_name,
                               um.username AS manager_name, r.amount, r.balance_after,
                               ua.username AS approved_by, r.branch_id
                        FROM till_removals r
                        JOIN tills t ON r.till_id = t.id
                        JOIN branch b ON r.branch_id = b.id
                        JOIN users um ON t.staff_id = um.id
                        LEFT JOIN users ua ON r.approved_by = ua.id
                        " . ($rem_where ? " WHERE " . implode(' AND ', $rem_where) : "") . "
                        ORDER BY r.created_at DESC
                        LIMIT 500
                    ";
                    $rem_res = $conn->query($rem_sql);
                    ?>
                    <div class="card">
                        <div class="card-header" style="color:#1abc9c;"><b>Till Removals</b></div>
                        <div class="card-body">
                            <div class="transactions-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Branch</th>
                                            <th>Till</th>
                                            <th>Till Manager</th>
                                            <th>Amount Removed</th>
                                            <th>Balance In Till</th>
                                            <th>Approved By</th>
                                            <th>Actions</th> <!-- NEW -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($rem_res && $rem_res->num_rows > 0): ?>
                                            <?php while ($row = $rem_res->fetch_assoc()): ?>
                                                <tr>
                                                    <td><small class="text-muted"><?= htmlspecialchars($row['created_at']) ?></small></td>
                                                    <td><?= htmlspecialchars($row['branch_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['till_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['manager_name']) ?></td>
                                                    <td><span class="fw-bold <?= ($row['amount'] < 0 ? 'text-success' : 'text-danger') ?>">UGX <?= number_format($row['amount'], 2) ?></span></td>
                                                    <td><span class="fw-bold text-primary">UGX <?= number_format($row['balance_after'], 2) ?></span></td>
                                                    <td><?= htmlspecialchars($row['approved_by'] ?? '-') ?></td>
                                                    <td>
                                                        <?php if ($row['amount'] > 0): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-warning undo-removal-btn"
                                                                title="Undo Removal"
                                                                data-removal-id="<?= $row['id'] ?>"
                                                                data-till-id="<?= $row['till_id'] ?>"
                                                                data-branch-id="<?= $row['branch_id'] ?>"
                                                                data-amount="<?= $row['amount'] ?>">
                                                                <i class="fa fa-undo"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted" style="font-size:0.75rem;">(undo)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="8" class="text-center text-muted">No till removals found for the selected filters.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- /subtab content -->
        </div> <!-- /Till Safes Tab -->
    </div>
</div>

<!-- Replace inline script with external JS -->
<script src="assets/js/till_management.js"></script>

<!-- Replace inline style with external CSS -->
<link rel="stylesheet" href="assets/css/till_management.css">

<!-- Undo Removal Modal -->
<div class="modal fade" id="undoRemovalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header" style="background:var(--primary-color);color:#fff;">
        <h5 class="modal-title">Undo Till Removal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="undo_removal" value="1">
        <input type="hidden" name="undo_removal_id" id="undo_removal_id">
        <input type="hidden" name="undo_till_id" id="undo_till_id">
        <input type="hidden" name="undo_branch_id" id="undo_branch_id">
        <p class="mb-2">Original Removed: <strong id="undo_original_amount">UGX 0.00</strong></p>
        <div class="mb-3">
          <label class="form-label">Amount to Return (UGX)</label>
          <input type="number" step="0.01" min="0.01" class="form-control" name="return_amount" id="return_amount" required>
          <small class="text-muted">Cannot exceed original removal amount.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Return</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const modalEl = document.getElementById('undoRemovalModal');
  const removalIdInput = document.getElementById('undo_removal_id');
  const tillIdInput = document.getElementById('undo_till_id');
  const branchIdInput = document.getElementById('undo_branch_id');
  const originalAmtSpan = document.getElementById('undo_original_amount');
  const returnAmtInput = document.getElementById('return_amount');
  document.querySelectorAll('.undo-removal-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const rid = btn.dataset.removalId;
      const tid = btn.dataset.tillId;
      const bid = btn.dataset.branchId;
      const amt = parseFloat(btn.dataset.amount||'0');
      removalIdInput.value = rid;
      tillIdInput.value = tid;
      branchIdInput.value = bid;
      originalAmtSpan.textContent = 'UGX ' + amt.toFixed(2);
      returnAmtInput.value = '';
      returnAmtInput.max = amt;
      new bootstrap.Modal(modalEl).show();
    });
  });
  // Simple max validation
  returnAmtInput && returnAmtInput.addEventListener('input',()=>{
    const max = parseFloat(returnAmtInput.max||'0');
    const val = parseFloat(returnAmtInput.value||'0');
    if(val > max){ returnAmtInput.value = max.toFixed(2); }
  });
})();
</script>

<!-- <style>
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
</style> -->

<?php include '../includes/footer.php'; ?>
