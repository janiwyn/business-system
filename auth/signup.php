<?php
include '../includes/db.php';

$error = "";
$success = "";
$message = "";
$message_class = "";
$isSuperSignup = isset($_GET['super']); // true only if ?super in URL

function getInitials($string) {
    $words = explode(" ", trim($string));
    $initials = "";

    foreach ($words as $w) {
        if ($w !== "") {
            $initials .= strtoupper($w[0]);
        }
    }

    return $initials;
}

function generateUniqueBusinessCode($prefix, $conn) {
    while (true) {
        // random 4-digit number
        $number = rand(1000, 9999);

        // final code
        $business_code = $prefix . $number;

        // check database if it exists
        $stmt = $conn->prepare("SELECT id FROM businesses WHERE business_code = ?");
        $stmt->bind_param("s", $business_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return $business_code; // Unique → OK
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    // Collect POST data safely
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $assigned_branch = NULL;
    $new_business_name = trim($_POST['new_business_name'] ?? '');
    $new_business_address = trim($_POST['new_business_address'] ?? '');
    $new_business_phone = trim($_POST['new_business_phone'] ?? '');
    $business_code = "";
    $business_id = "";

    

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

        // Manager branch validation
        if ($role === 'manager') {
            $branch_id_input  = $_POST['branch_id'] ?? null;
            $business_code = $branch_id_input;

            if (!$branch_id_input) {
                $error = "Manager must enter a business code!!";
            } else {
                $stmt = $conn->prepare("SELECT business_code FROM businesses WHERE business_code = ?");
                $stmt->bind_param("s", $branch_id_input);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) {
                    $error = "Invalid business code.";
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
            // Generate business code
            if (!empty($new_business_name) && !empty($new_business_address)) {
                $name_initials = getInitials($new_business_name);
                $address_initials = getInitials($new_business_address);
                $prefix = $name_initials . $address_initials;
                $business_code = generateUniqueBusinessCode($prefix, $conn);

                $stmt = $conn->prepare("INSERT INTO businesses (business_code, name, address, phone, admin_name, email) VALUES (?, ?,  ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $business_code, $new_business_name, $new_business_address, $new_business_phone, $username, $email);
                $stmt->execute();
                $stmt->close();                

            }
        }

        $stmt = $conn->prepare("SELECT id FROM businesses WHERE business_code = ?");
        $stmt->bind_param("s", $business_code);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $business_id = $row['id'] ?? null;   // store the ID
        $stmt->close();

        // Insert user if still no errors
        if (empty($error)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            if ($role === 'staff') {
                $stmt2 = $conn->prepare(
                    "INSERT INTO users (username, email, password, role, phone, `branch-id`, business_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt2->bind_param("sssssi", $username, $email, $hash, $role, $phone, $assigned_branch, $business_id);
            } elseif ($role === 'admin') {
                $stmt2 = $conn->prepare(
                    "INSERT INTO users (username, email, password, role, phone, business_id)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt2->bind_param("sssssi", $username, $email, $hash, $role, $phone, $business_id);
            } else {
              $status = 'active';
            $stmt2 = $conn->prepare(
                "INSERT INTO users (username, email, password, role, phone, business_id)
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt2->bind_param("sssssi", $username, $email, $hash, $role, $phone, $business_id);

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
        <div class="text-center mb-2"><a href="../index.php" class="btn btn-sm btn-success rounded-pill px-3">Go to Login</a></div>
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
                <label class="form-label">Business Code</label>
                <input type="text" name="branch_id" class="form-control form-control-sm" >
            </div>
            <div class="col-md-6">
                <label class="form-label">Branch Password</label>
                <input name="branch_key" type="password" class="form-control form-control-sm">
            </div>
        </div>

        <!-- Manager Branch Fields -->
        <div class="row g-2 mt-1 manager-branch-fields" style="display:none;">
            <div class="col-md-6">
                <label class="form-label">Business Code</label>
                <input type="text" name="branch_id" class="form-control form-control-sm" >
            </div>
        </div>

        <!-- Admin Business Fields -->
        <div class="mt-2 admin-business-fields" style="display:none;">
            <label class="form-label">Select Existing Business</label>

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
        <div class="text-center mt-2"><p>Already have an account? <a href="../index.php">Login here</a></p></div>
    </form>
</div>

<script>
    const roleSelect = document.getElementById('role');
    const branchFields = document.querySelector('.staff-branch-fields');
    const managerFields = document.querySelector('.manager-branch-fields');
    const businessFields = document.querySelector('.admin-business-fields');

    roleSelect.addEventListener('change', () => {
        branchFields.style.display = (roleSelect.value === 'staff') ? 'flex' : 'none';
        businessFields.style.display = (roleSelect.value === 'admin') ? 'block' : 'none';
        managerFields.style.display = (roleSelect.value === 'manager') ? 'flex' : 'none';
    });
</script>
</body>
</html>
