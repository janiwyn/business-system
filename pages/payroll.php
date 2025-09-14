<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(['admin', 'manager']);
include '../pages/sidebar.php';
include '../includes/header.php';

// Handle form submission
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = intval($_POST['staff_id']);
    $month = trim($_POST['month']);
    $amount = floatval($_POST['amount']);
    $status = trim($_POST['status']);

    if ($staff_id && $month && $amount && $status) {
        $stmt = $conn->prepare("INSERT INTO payroll (staff_id, month, amount, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $staff_id, $month, $amount, $status);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Payroll record added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>All fields are required.</div>";
    }
}

// Fetch staff for dropdown
$staff_result = $conn->query("SELECT id, username FROM users WHERE role='staff'");

// Fetch payroll records
$payroll_result = $conn->query("
    SELECT p.*, u.username 
    FROM payroll p 
    LEFT JOIN users u ON p.staff_id = u.id 
    ORDER BY p.id DESC
");
?>

<style>
/* Form styling */
.card {
    border-radius: 12px;
    box-shadow: 0px 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s ease-in-out;
    background: var(--card-bg);
}
.card-header {
    font-weight: 600;
    background: var(--primary-color);
    color: #fff !important;
    border-radius: 12px 12px 0 0 !important;
    font-size: 1.1rem;
    letter-spacing: 1px;
}
body.dark-mode .card-header {
    background-color: #2c3e50 !important;
    color: #fff !important;
}
.form-control, .form-select {
    border-radius: 8px;
}
body.dark-mode .form-label,
body.dark-mode label,
body.dark-mode .card-body {
    color: #fff !important;
}
body.dark-mode .form-control,
body.dark-mode .form-select {
    background-color: #23243a !important;
    color: #fff !important;
    border: 1px solid #444 !important;
}
body.dark-mode .form-control:focus,
body.dark-mode .form-select:focus {
    background-color: #23243a !important;
    color: #fff !important;
}
.btn-primary {
    background: var(--primary-color) !important;
    border: none;
    border-radius: 8px;
    padding: 8px 18px;
    font-weight: 600;
    box-shadow: 0px 3px 8px rgba(0,0,0,0.2);
    color: #fff !important;
    transition: background 0.2s;
}
.btn-primary:hover, .btn-primary:focus {
    background: #159c8c !important;
    color: #fff !important;
}

/* Table styling (like admin_dashboard) */
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
    text-transform: uppercase;
    font-size: 13px;
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
body.dark-mode .transactions-table tbody tr:hover {
    background-color: rgba(255,255,255,0.1) !important;
}
</style>

<div class="container mt-5">
    <div class="card mb-4" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">Add Payroll Record</div>
        <div class="card-body">
            <?= $message ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Staff</label>
                    <select name="staff_id" class="form-select" required>
                        <option value="">-- Select Staff --</option>
                        <?php while ($row = $staff_result->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['username']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Month</label>
                    <input type="month" name="month" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Amount (UGX)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Add Payroll</button>
            </form>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-header">Payroll Records</div>
        <div class="card-body">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Staff</th>
                            <th>Month</th>
                            <th>Amount (UGX)</th>
                            <th>Status</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        if ($payroll_result->num_rows > 0):
                            while ($row = $payroll_result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['month']) ?></td>
                            <td><?= number_format($row['amount'], 2) ?></td>
                            <td>
                                <?php if ($row['status'] === 'Paid'): ?>
                                    <span class="badge bg-success"><?= $row['status'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><?= $row['status'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d-M-Y', strtotime($row['created_at'] ?? $row['month'])) ?></td>
                        </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No payroll records found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
