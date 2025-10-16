<?php
include '../includes/db.php';
include '../includes/auth.php';
include '../pages/sidebar.php';
include '../includes/header.php';
require_role(["admin", "manager", "staff"]);

$message = "";

// Logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Handle form submission to add debtor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_debtor'])) {
    $debtor_name  = $conn->real_escape_string($_POST['debtor_name']);
    $debtor_email = $conn->real_escape_string($_POST['debtor_email']);
    $item_taken   = $conn->real_escape_string($_POST['item_taken']);
    $quantity     = intval($_POST['quantity_taken']);
    $amount_paid  = floatval($_POST['amount_paid']);
    $balance      = floatval($_POST['balance']);
    $branch_id    = ($user_role === 'staff') ? $user_branch : intval($_POST['branch_id']);

    $insert = $conn->query("
        INSERT INTO debtors (debtor_name, debtor_email, item_taken, quantity_taken, amount_paid, balance, `branch_id`)
        VALUES ('$debtor_name', '$debtor_email', '$item_taken', $quantity, $amount_paid, $balance, $branch_id)
    ");

    if ($insert) {
        $message = "Debtor added successfully.";
    } else {
        $message = "Error adding debtor: " . $conn->error;
    }
}

// Filters
$debtor_where = [];
if ($user_role === 'staff') {
    $debtor_where[] = "debtors.`branch_id` = $user_branch";
} elseif (!empty($_GET['branch'])) {
    $debtor_where[] = "debtors.`branch_id` = " . intval($_GET['branch']);
}

if (!empty($_GET['date_from'])) {
    $debtor_where[] = "DATE(debtors.created_at) >= '" . $conn->real_escape_string($_GET['date_from']) . "'";
}
if (!empty($_GET['date_to'])) {
    $debtor_where[] = "DATE(debtors.created_at) <= '" . $conn->real_escape_string($_GET['date_to']) . "'";
}

$debtorWhereClause = count($debtor_where) ? "WHERE " . implode(' AND ', $debtor_where) : "";

// Fetch debtors
$debtors_result = $conn->query("
    SELECT debtors.*, branch.name AS branch_name
    FROM debtors
    JOIN branch ON debtors.`branch_id` = branch.id
    $debtorWhereClause
    ORDER BY debtors.created_at DESC
    LIMIT 100
");

// Fetch branches for admin/manager
$branches = ($user_role !== 'staff') ? $conn->query("SELECT id, name FROM branch") : [];

?>

<div class="container-fluid mt-4">
    <h3 class="mb-4">Debtors Management</h3>

    <?php if($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Add Debtor Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Debtor</div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label>Debtor Name</label>
                        <input type="text" name="debtor_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Email</label>
                        <input type="email" name="debtor_email" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Item Taken</label>
                        <input type="text" name="item_taken" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label>Quantity</label>
                        <input type="number" name="quantity_taken" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-2">
                        <label>Amount Paid</label>
                        <input type="number" name="amount_paid" class="form-control" min="0" step="0.01" value="0.00">
                    </div>
                    <div class="col-md-2">
                        <label>Balance</label>
                        <input type="number" name="balance" class="form-control" min="0" step="0.01" value="0.00">
                    </div>
                    <?php if ($user_role !== 'staff'): ?>
                    <div class="col-md-4">
                        <label>Branch</label>
                        <select name="branch_id" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php while($b = $branches->fetch_assoc()): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <button type="submit" name="add_debtor" class="btn btn-primary">Add Debtor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Debtors Table & Filters -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Debtors List</span>
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                <?php if ($user_role !== 'staff'): ?>
                    <select name="branch" class="form-select">
                        <option value="">All Branches</option>
                        <?php
                        $branches = $conn->query("SELECT id, name FROM branch");
                        while($b = $branches->fetch_assoc()):
                            $selected = ($_GET['branch'] ?? '') == $b['id'] ? 'selected' : '';
                            echo "<option value='{$b['id']}' $selected>{$b['name']}</option>";
                        endwhile;
                        ?>
                    </select>
                <?php endif; ?>
                <button class="btn btn-secondary">Filter</button>
            </form>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Debtor Name</th>
                        <th>Email</th>
                        <th>Item Taken</th>
                        <th>Quantity</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Paid Status</th>
                        <?php if($user_role !== 'staff') echo "<th>Branch</th>"; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($debtors_result && $debtors_result->num_rows > 0): ?>
                        <?php while($d = $debtors_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= date("M d, Y H:i", strtotime($d['created_at'])) ?></td>
                                <td><?= htmlspecialchars($d['debtor_name']) ?></td>
                                <td><?= htmlspecialchars($d['debtor_email']) ?></td>
                                <td><?= htmlspecialchars($d['item_taken']) ?></td>
                                <td><?= $d['quantity_taken'] ?></td>
                                <td>UGX <?= number_format($d['amount_paid'],2) ?></td>
                                <td>UGX <?= number_format($d['balance'],2) ?></td>
                                <td>
                                    <?php if($d['is_paid']): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <?php if($user_role !== 'staff') echo "<td>".htmlspecialchars($d['branch_name'])."</td>"; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= ($user_role !== 'staff') ? 9 : 8 ?>" class="text-center">No debtors found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
