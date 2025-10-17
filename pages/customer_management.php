<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff"]);


// Include the correct sidebar based on the user's role
if ($_SESSION['role'] === 'staff') {
    include '../pages/sidebar_staff.php';
} else {
    include '../pages/sidebar.php';
}

include '../includes/header.php';

$message = "";

// Handle form submissions (if any)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle customer creation
    if (isset($_POST['create_customer'])) {
        $name = trim($_POST['name']);
        $contact = trim($_POST['contact']);
        $email = trim($_POST['email']);

        if ($name && $contact && $email) {
            $stmt = $conn->prepare("INSERT INTO customers (name, contact, email, credited_amount, account_balance) VALUES (?, ?, ?, 0, 0)");
            $stmt->bind_param("sss", $name, $contact, $email);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Customer file created successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error creating customer file: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert alert-warning'>Please fill in all fields.</div>";
        }
    }
}

// Fetch customers for the "View Customers" and "Manage Customers" tabs
$customers = $conn->query("SELECT * FROM customers ORDER BY id DESC");
?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Customer Management</h2>
    <?= $message; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="customerTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button" role="tab" aria-controls="create" aria-selected="true">Create Customer File</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="view-tab" data-bs-toggle="tab" data-bs-target="#view" type="button" role="tab" aria-controls="view" aria-selected="false">View Customers</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="manage-tab" data-bs-toggle="tab" data-bs-target="#manage" type="button" role="tab" aria-controls="manage" aria-selected="false">Manage Customers</button>
        </li>
    </ul>

    <div class="tab-content mt-4" id="customerTabsContent">
        <!-- Create Customer File Tab -->
        <div class="tab-pane fade show active" id="create" role="tabpanel" aria-labelledby="create-tab">
            <div class="card">
                <div class="card-header">Create Customer File</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="contact" class="form-label">Contact</label>
                            <input type="text" class="form-control" id="contact" name="contact" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <button type="submit" name="create_customer" class="btn btn-primary">Create Customer</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Customers Tab -->
        <div class="tab-pane fade" id="view" role="tabpanel" aria-labelledby="view-tab">
            <div class="card">
                <div class="card-header">View Customers</div>
                <div class="card-body">
                    <?php if ($customers->num_rows > 0): ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Credited Amount</th>
                                    <th>Account Balance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $customer['id']; ?></td>
                                        <td><?= htmlspecialchars($customer['name']); ?></td>
                                        <td><?= htmlspecialchars($customer['contact']); ?></td>
                                        <td><?= htmlspecialchars($customer['email']); ?></td>
                                        <td>UGX <?= number_format($customer['credited_amount'], 2); ?></td>
                                        <td>UGX <?= number_format($customer['account_balance'], 2); ?></td>
                                        <td><a href="view_customer.php?id=<?= $customer['id']; ?>" class="btn btn-info btn-sm">Open</a></td>
                                        </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No customers found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Manage Customers Tab -->
        <div class="tab-pane fade" id="manage" role="tabpanel" aria-labelledby="manage-tab">
            <div class="card">
                <div class="card-header">Manage Customers</div>
                <div class="card-body">
                    <?php if ($customers->num_rows > 0): ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $customer['id']; ?></td>
                                        <td><?= htmlspecialchars($customer['name']); ?></td>
                                        <td><?= htmlspecialchars($customer['contact']); ?></td>
                                        <td><?= htmlspecialchars($customer['email']); ?></td>
                                        <td>
                                            <a href="edit_customer.php?id=<?= $customer['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                            <a href="delete_customer.php?id=<?= $customer['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this customer?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No customers found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
