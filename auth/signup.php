<?php
include '../includes/db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    // Collect POST data safely
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $assigned_branch = NULL;

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role) || empty($phone)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Staff role requires branch assignment
        if ($role === 'staff') {
            $branch_id_input = intval($_POST['branch_id'] ?? 0);
            $branch_key_input = trim($_POST['branch_key'] ?? '');

            if (!$branch_id_input || empty($branch_key_input)) {
                $error = "Staff must select a branch and enter branch password.";
            } 
            // else {
            //     // Verify branch exists and password matches
            //     $stmt = $conn->prepare("SELECT `branch-key` FROM branch WHERE id=?");
            //     $stmt->bind_param("i", $branch_id_input);
            //     $stmt->execute();
            //     $branch = $stmt->get_result()->fetch_assoc();
            //     $stmt->close();

            //     if (!$branch) {
            //         $error = "Selected branch does not exist.";
            //     } elseif ($branch['branch-key'] !== $branch_key_input) {
            //         $error = "Invalid branch password.";
            //     } else {
            //         $assigned_branch = $branch_id_input;
            //     }
            // }
        }

        // Insert user if no errors
        if (empty($error)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "SELECT `branch-key` FROM branch WHERE `branch-key` = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $branch_key_input);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                // ✅ Branch key exists and matches
                $row = $result->fetch_assoc();
                if ($row['branch-key'] === $branch_key_input) {
                    if ($role === 'staff') {
                        $stmt2 = $conn->prepare("INSERT INTO users (username, email, password, role, phone, `branch-id`) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt2->bind_param("sssssi", $username, $email, $hash, $role, $phone, $assigned_branch);


                    } else {
                        $stmt2 = $conn->prepare("INSERT INTO users (username, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
                        $stmt2->bind_param("sssss", $username, $email, $hash, $role, $phone);
                    }

                    if ($stmt2->execute()) {
                        $success = "Registration successful! You can now login.";
                    } else {
                        $error = "Database error: " . $conn->error;
                    }

                    $stmt2->close();
                }
            } else {
                $message = "Invalid branch key!!";
                $message_class = "alert-danger";
            }

            $stmt->close();
        }
    }
}

// Fetch branches for staff dropdown
$branches = $conn->query("SELECT id, name FROM branch ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Business System - Sign Up</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* === YOUR ORIGINAL CSS START === */
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

    .background-shapes{position:absolute; inset:0; z-index:0; overflow:hidden;}
    .shape{
      position:absolute; border-radius:50%;
      background: rgba(255,255,255,0.04);
      animation: float 14s infinite ease-in-out;
      filter: blur(0.2px);
    }
    .shape:nth-child(1){ width:160px; height:160px; top:-40px; left:-40px;}
    .shape:nth-child(2){ width:240px; height:240px; bottom:-70px; right:-70px; animation-duration:18s;}
    .shape:nth-child(3){ width:120px; height:120px; top:18%; right:8%; animation-duration:16s;}
    @keyframes float{ 0%,100%{ transform:translateY(0) rotate(0);} 50%{ transform:translateY(-16px) rotate(16deg);} }

    .signup-card{
      position:relative; z-index:1;
      width:100%; max-width: 420px;
      background:#fff; border-radius:16px;
      padding:1.25rem 1.25rem 1rem;
      box-shadow: 0 10px 28px rgba(0,0,0,0.18);
      animation: rise 0.5s ease;
    }
    @keyframes rise{from{opacity:0; transform:translateY(20px);} to{opacity:1; transform:translateY(0);} }

    .signup-header{text-align:center; margin-bottom:0.5rem;}
    .signup-header img{width:56px; margin-bottom:0.25rem;}
    .signup-header h3{font-weight:700; color:var(--ink); font-size:1.25rem; margin:0;}
    .signup-header p{color:var(--muted); font-size:0.85rem; margin:0.25rem 0 0;}

    .form-label{font-weight:600; font-size:0.875rem; color:#34495e; margin-bottom:0.25rem;}
    .form-control, .form-select{
      border-radius:10px; padding:0.5rem 0.7rem;
    }
    .form-control-sm, .form-select-sm{ font-size:0.9rem; }
    .field-note{font-size:0.75rem; color:var(--muted);}

    .btn-corporate{
      background: linear-gradient(90deg, var(--brand1), var(--brand2));
      color:#fff; font-weight:600; border-radius:999px; padding:0.55rem 1rem;
      transition: transform .2s ease, box-shadow .2s ease, opacity .2s ease;
    }
    .btn-corporate:hover{ transform:translateY(-1px); box-shadow:0 6px 18px rgba(0,0,0,0.18); }
    .btn-outline-muted{ border-radius:999px; }

    .divider{ height:1px; background:#e9ecef; margin:0.75rem 0; }
    .footer-text{ text-align:center; font-size:0.8rem; color:var(--muted); margin-top:0.5rem; }

    .alt-actions{
      display:flex; gap:0.5rem; align-items:center; justify-content:center;
      font-size:0.9rem;
    }

    .toggle-btn{
      border:none; background:transparent; font-size:0.85rem; color:#0d6efd; padding:0 .25rem;
    }

    @media (max-height: 640px){
      .signup-card{ margin: 0.75rem; padding: 1rem; }
    }
    /* === YOUR ORIGINAL CSS END === */
  </style>
</head>
<body>
<!-- === YOUR ORIGINAL HTML START === -->
<div class="background-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
</div>

<div class="signup-card">
    <div class="signup-header">
      <img src="../uploads/logo.png" alt="Logo" />
      <h3>Create Account</h3>
      <p>Register to access the Business System</p>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $message_class ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 text-center mb-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 text-center mb-2"><?= htmlspecialchars($success) ?></div>
        <div class="text-center mb-2">
            <a href="login.php" class="btn btn-sm btn-success rounded-pill px-3">Go to Login</a>
        </div>
    <?php endif; ?>

    <form action="signup.php" method="POST" class="needs-validation" novalidate>
        <div class="mb-2">
            <label for="username" class="form-label">Username</label>
            <input id="username" name="username" type="text" class="form-control form-control-sm" required>
        </div>

        <div class="mb-2">
            <label for="email" class="form-label">Email Address</label>
            <input id="email" name="email" type="email" class="form-control form-control-sm" required>
        </div>

        <div class="row g-2">
            <div class="col-12 col-md-6">
                <label for="password" class="form-label">Password</label>
                <div class="input-group input-group-sm">
                    <input id="password" name="password" type="password" class="form-control" required>
                    <button class="toggle-btn" type="button" data-target="password">Show</button>
                </div>
                <div class="field-note mt-1">Min 8 characters recommended.</div>
            </div>
            <div class="col-12 col-md-6">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="input-group input-group-sm">
                    <input id="confirm_password" name="confirm_password" type="password" class="form-control" required>
                    <button class="toggle-btn" type="button" data-target="confirm_password">Show</button>
                </div>
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-12 col-md-6">
                <label for="phone" class="form-label">Phone Number</label>
                <input id="phone" name="phone" type="tel" class="form-control form-control-sm" required>
            </div>
            <div class="col-12 col-md-6">
                <label for="role" class="form-label">Role</label>
                <select id="role" name="role" class="form-select form-select-sm" required>
                    <option value="" disabled selected>Select role</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="staff">Staff</option>
                </select>

                <div class="row g-2 mt-1 staff-branch-fields" style="display:none;">
                    <div class="col-12 col-md-6">
                        <label for="branch_id" class="form-label">Branch</label>
                        <select id="branch_id" name="branch_id" class="form-select form-select-sm">
                            <option value="" disabled selected>Select branch</option>
                            <?php while ($b = $branches->fetch_assoc()): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="branch_key" class="form-label">Branch Password</label>
                        <input id="branch_key" name="branch_key" type="password" required class="form-control form-control-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="divider"></div>
        <button type="submit" name="signup" class="btn btn-corporate w-100 mb-2">Create Account</button>
        <div class="text-center mt-3">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </form>

    <div class="footer-text">© <?= date("Y") ?> Business System</div>
</div>
<!-- === YOUR ORIGINAL HTML END === -->

<script>
    // password visibility toggles
    document.querySelectorAll('.toggle-btn').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-target');
        const input = document.getElementById(id);
        if(!input) return;
        const isPwd = input.type === 'password';
        input.type = isPwd ? 'text' : 'password';
        btn.textContent = isPwd ? 'Hide' : 'Show';
      });
    });

    (function () {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    })();

    const roleSelect = document.getElementById('role');
    const branchFields = document.querySelector('.staff-branch-fields');
    roleSelect.addEventListener('change', () => {
        if (roleSelect.value === 'staff') {
            branchFields.style.display = 'flex';
            document.getElementById('branch_id').required = true;
            document.getElementById('branch_key').required = true;
        } else {
            branchFields.style.display = 'none';
            document.getElementById('branch_id').required = false;
            document.getElementById('branch_key').required = false;
        }
    });
</script>
</body>
</html>
//script