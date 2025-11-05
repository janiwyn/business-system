<?php
include '../includes/db.php';

$error = "";
$success = "";
$message = "";
$message_class = "";
$isSuperSignup = isset($_GET['super']); // true only if ?super in URL

if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    // Collect POST data safely
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $assigned_branch = NULL;
    $business_id = $_POST['business_id'] ?? '';
    $new_business_name = trim($_POST['new_business_name'] ?? '');
    $new_business_address = trim($_POST['new_business_address'] ?? '');
    $new_business_phone = trim($_POST['new_business_phone'] ?? '');

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role) || empty($phone)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Staff branch validation
        if ($role === 'staff') {
            $branch_id_input  = intval($_POST['branch_id'] ?? 0);
            $branch_key_input = trim($_POST['branch_key'] ?? '');

            if (!$branch_id_input || $branch_key_input === '') {
                $error = "Staff must select a branch and enter branch password.";
            } else {
                $stmt = $conn->prepare("SELECT id FROM branch WHERE id = ? AND `branch-key` = ?");
                $stmt->bind_param("is", $branch_id_input, $branch_key_input);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) {
                    $error = "Invalid branch or branch password.";
                } else {
                    $assigned_branch = $branch_id_input;
                }
                $stmt->close();
            }
        }

        // Email uniqueness check
        if (empty($error)) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = "An account with this email already exists.";
            }
            $check->close();
        }

        // ✅ Admin: handle business registration/linking
        if (empty($error) && $role === 'admin') {
            if (empty($business_id) && !empty($new_business_name)) {
                $stmt = $conn->prepare("INSERT INTO businesses (name, address, phone) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $new_business_name, $new_business_address, $new_business_phone);
                $stmt->execute();
                $business_id = $conn->insert_id;
                $stmt->close();
            }
        }

        // Insert user if still no errors
        if (empty($error)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            if ($role === 'staff') {
                $stmt2 = $conn->prepare(
                    "INSERT INTO users (username, email, password, role, phone, `branch-id`)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt2->bind_param("sssssi", $username, $email, $hash, $role, $phone, $assigned_branch);
            } elseif ($role === 'admin') {
                $stmt2 = $conn->prepare(
                    "INSERT INTO users (username, email, password, role, phone, business_id)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt2->bind_param("sssssi", $username, $email, $hash, $role, $phone, $business_id);
            } else {
              $status = 'active';
$stmt2 = $conn->prepare(
    "INSERT INTO users (username, email, password, role, phone, status)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt2->bind_param("ssssss", $username, $email, $hash, $role, $phone, $status);

            }

            if ($stmt2->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Database error: " . $stmt2->error;
            }
            $stmt2->close();
        }
    }
}

// Fetch branches (for staff)
$branches = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");

// Fetch businesses (for admins)
$businesses = $conn->query("SELECT id, name FROM businesses ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Business System - Sign Up</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/responsive.css">
  <style>
    /* Keep your existing CSS unchanged */
    :root{
      --bg1:#0f2027; --bg2:#203a43; --bg3:#2c5364;
      --brand1:#1e3c72; --brand2:#2a5298;
      --ink:#203a43; --muted:#6c757d;
    }
    body{
      margin:0; padding:0; min-height:100vh;
      display:flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg, var(--bg1), var(--bg2), var(--bg3));
      overflow:hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .signup-card{ position:relative; z-index:1; width:100%; max-width:420px; background:#fff; border-radius:16px;
      padding:1.25rem 1.25rem 1rem; box-shadow:0 10px 28px rgba(0,0,0,0.18);}
  </style>
</head>
<body>
<div class="background-shapes"><div class="shape"></div><div class="shape"></div><div class="shape"></div></div>

<div class="signup-card">
    <div class="signup-header text-center mb-3">
        <img src="../uploads/2.png" alt="Logo" width="60">
        <h3>Create Account</h3>
        <p>Register to access the Business System</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 text-center mb-2"><?= htmlspecialchars($error) ?></div>
    <?php elseif (!empty($success)): ?>
        <div class="alert alert-success py-2 text-center mb-2"><?= htmlspecialchars($success) ?></div>
        <div class="text-center mb-2"><a href="login.php" class="btn btn-sm btn-success rounded-pill px-3">Go to Login</a></div>
    <?php endif; ?>

    <form action="signup.php" method="POST" class="needs-validation" novalidate>
        <div class="mb-2">
            <label class="form-label">Username</label>
            <input name="username" type="text" class="form-control form-control-sm" required>
        </div>

        <div class="mb-2">
            <label class="form-label">Email Address</label>
            <input name="email" type="email" class="form-control form-control-sm" required>
        </div>

        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input name="password" type="password" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <input name="confirm_password" type="password" class="form-control form-control-sm" required>
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input name="phone" type="tel" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Role</label>
                <select id="role" name="role" class="form-select form-select-sm" required>
                    <option value="" disabled selected>Select role</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="staff">Staff</option>
                    <?php if ($isSuperSignup): ?><option value="super">Super</option><?php endif; ?>
                </select>
            </div>
        </div>

        <!-- Staff Branch Fields -->
        <div class="row g-2 mt-1 staff-branch-fields" style="display:none;">
            <div class="col-md-6">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="" disabled selected>Select branch</option>
                    <?php while ($b = $branches->fetch_assoc()): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Branch Password</label>
                <input name="branch_key" type="password" class="form-control form-control-sm">
            </div>
        </div>

        <!-- Admin Business Fields -->
        <div class="mt-2 admin-business-fields" style="display:none;">
            <label class="form-label">Select Existing Business</label>
            <select name="business_id" class="form-select form-select-sm mb-2">
                <option value="">-- Create New Business --</option>
                <?php while ($biz = $businesses->fetch_assoc()): ?>
                    <option value="<?= $biz['id'] ?>"><?= htmlspecialchars($biz['name']) ?></option>
                <?php endwhile; ?>
            </select>

            <div class="new-business-fields">
                <label class="form-label">New Business Name</label>
                <input name="new_business_name" type="text" class="form-control form-control-sm mb-2">
                <label class="form-label">Business Address</label>
                <input name="new_business_address" type="text" class="form-control form-control-sm mb-2">
                <label class="form-label">Business Phone</label>
                <input name="new_business_phone" type="text" class="form-control form-control-sm mb-2">
            </div>
        </div>

        <div class="divider"></div>
        <button type="submit" name="signup" class="btn btn-corporate w-100 mb-2">Create Account</button>
        <div class="text-center mt-2"><p>Already have an account? <a href="login.php">Login here</a></p></div>
    </form>
</div>

<script>
    const roleSelect = document.getElementById('role');
    const branchFields = document.querySelector('.staff-branch-fields');
    const businessFields = document.querySelector('.admin-business-fields');

    roleSelect.addEventListener('change', () => {
        branchFields.style.display = (roleSelect.value === 'staff') ? 'flex' : 'none';
        businessFields.style.display = (roleSelect.value === 'admin') ? 'block' : 'none';
    });
</script>
</body>
</html>
