<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';

$message = "";

// Get logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Save payroll record
if (isset($_POST['save_payroll'])) {
    $user_id = $_POST['user-id'];
    $transport = $_POST['transport'];
    $housing = $_POST['housing'];
    $medical = $_POST['medical'];
    $overtime = $_POST['overtime'];
    $nssf = $_POST['nssf'];
    $tax = $_POST['tax'];
    $loan = $_POST['loan'];
    $other_deductions = $_POST['other_deductions'];

    // ✅ Get base salary
    $emp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT base_salary FROM employees WHERE `user-id`='$user_id'"));
    $base_salary = $emp['base_salary'] ?? 0;

    $gross = $base_salary + $transport + $housing + $medical + $overtime;
    $deductions = $nssf + $tax + $loan + $other_deductions;
    $net = $gross - $deductions;

    // ✅ Insert payroll
    $sql = "INSERT INTO payroll (`user-id`, base_salary, transport, housing, medical, overtime, nssf, tax, loan, other_deductions, gross_salary, net_salary, month, status) 
            VALUES ('$user_id','$base_salary','$transport','$housing','$medical','$overtime','$nssf','$tax','$loan','$other_deductions','$gross','$net', DATE_FORMAT(NOW(),'%Y-%m'), 'Pending')";
    mysqli_query($conn, $sql) or die(mysqli_error($conn));

    echo "<script>alert('Payroll saved successfully'); window.location='payroll.php';</script>";
}


// Mark as Paid
if (isset($_GET['mark_paid'])) {
    $id = $_GET['mark_paid'];
    mysqli_query($conn, "UPDATE payroll SET status='Paid' WHERE id='$id'");
    echo "<script>alert('Marked as Paid'); window.location='payroll.php';</script>";
}
?>

<style>
/* Form styling (like product.php) */
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
.btn-success {
    background: #27ae60 !important;
    border: none;
    border-radius: 8px;
    padding: 8px 18px;
    font-weight: 600;
    color: #fff !important;
    box-shadow: 0px 3px 8px rgba(0,0,0,0.2);
    transition: background 0.2s;
}
.btn-success:hover, .btn-success:focus {
    background: #219150 !important;
    color: #fff !important;
}
.btn-secondary, .btn-sm {
    border-radius: 8px;
    font-weight: 500;
}
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

<body class="bg-light">
<div class="container mt-4">
    <h3 class="mb-3" style="color:var(--primary-color);font-weight:700;">Payroll Management</h3>

    <!-- Payroll Form -->
    <div class="card mb-4">
        <div class="card-header">Add Payroll Record</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Employee</label>
                    <select name="user-id" class="form-select" required>
                        <option value="">-- Choose Employee --</option>
                        <?php
                        $result = mysqli_query($conn, "
                            SELECT e.id, u.username, e.base_salary
                            FROM employees e
                            JOIN users u ON e.`user-id` = u.id
                        ");
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<option value='{$row['id']}'> {$row['username']} - Salary: {$row['base_salary']} </option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Transport</label>
                        <input type="number" name="transport" class="form-control" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Housing</label>
                        <input type="number" name="housing" class="form-control" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Medical</label>
                        <input type="number" name="medical" class="form-control" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Overtime</label>
                        <input type="number" name="overtime" class="form-control" value="0">
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">NSSF</label>
                        <input type="number" name="nssf" class="form-control" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tax (PAYE)</label>
                        <input type="number" name="tax" class="form-control" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Loan</label>
                        <input type="number" name="loan" class="form-control" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Other Deductions</label>
                        <input type="number" name="other_deductions" class="form-control" value="0">
                    </div>
                </div>
                <button type="submit" name="save_payroll" class="btn btn-success mt-3">Save Payroll</button>
            </form>
        </div>
    </div>

    <!-- Payroll Records -->
    <div class="card mb-4">
        <div class="card-header">Payroll Records</div>
        <div class="card-body">
            <div class="transactions-table">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Gross</th>
                            <th>Deductions</th>
                            <th>Net</th>
                            <th>Month</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT p.*, u.username 
                        FROM payroll p
                        JOIN employees e ON p.`user-id` = e.id
                        JOIN users u ON e.`user-id` = u.id
                        ORDER BY p.id DESC";
                    $records = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($records)) {
                        $deductions = $row['nssf'] + $row['tax'] + $row['loan'] + $row['other_deductions'];
                        echo "<tr>
                                <td>{$row['username']}</td>
                                <td>{$row['gross_salary']}</td>
                                <td>{$deductions}</td>
                                <td><b>{$row['net_salary']}</b></td>
                                <td>{$row['month']}</td>
                                <td>{$row['status']}</td>
                                <td>
                                    <a href='payroll.php?mark_paid={$row['id']}' class='btn btn-success btn-sm'>Mark Paid</a>
                                    <a href='payslip.php?id={$row['id']}' class='btn btn-secondary btn-sm'>Payslip</a>
                                </td>
                              </tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payroll Summary -->
    <div class="card">
        <div class="card-header">Payroll Summary (This Month)</div>
        <div class="card-body">
            <?php
            $month = date('Y-m');
            $summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(gross_salary) as total_gross, SUM(net_salary) as total_net FROM payroll WHERE month='$month'"));
            ?>
            <p><b>Total Gross:</b> <?php echo $summary['total_gross'] ?? 0; ?></p>
            <p><b>Total Net:</b> <?php echo $summary['total_net'] ?? 0; ?></p>
        </div>
    </div>
</div>
</body>
</html>
