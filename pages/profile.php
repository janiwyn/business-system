<?php
session_start();
include '../includes/db.php';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = $_POST["username"];
    $email = $_POST["email"];
    $phone = $_POST["phone"];

    // File upload
    $imagePath = null;
    if (!empty($_FILES["profile_image"]["name"])) {
        $fileName = time() . "_" . basename($_FILES["profile_image"]["name"]);
        $targetDir = "../uploads/";
        $targetFile = $targetDir . $fileName;

        // Ensure uploads folder exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFile)) {
            $imagePath = "uploads/" . $fileName;
        } else {
            $message = "Failed to upload image.";
        }
    }

    if (empty($message)) {
        $sql = "UPDATE users SET username = ?, email = ?, phone = ?" . ($imagePath ? ", profile_image = ?" : "") . " WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($imagePath) {
            $stmt->bind_param("ssssi", $username, $email, $phone, $imagePath, $userId);
        } else {
            $stmt->bind_param("sssi", $username, $email, $phone, $userId);
        }

        if ($stmt->execute()) {
            $message = "Profile updated successfully.";
        } else {
            $message = "Error updating profile.";
        }
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Dashboard</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- CONTENT -->
    <div class="container mt-5">
        <h3>Edit Profile</h3>
        <?php if ($message): ?>
            <div class="alert alert-info"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label>Full Name</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label>Profile Image</label>
                <input type="file" name="profile_image" class="form-control">
                <?php if (!empty($user['profile_image'])): ?>
                    <img src="../<?= $user['profile_image'] ?>" alt="Profile" style="width: 100px; height: 100px; object-fit: cover; margin-top: 10px;">
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
    </div>
</body>
</html>
