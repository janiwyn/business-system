<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Business System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-dark bg-primary px-4">
    <div class="d-flex align-items-center me-auto">
      <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="log.png" alt="" width="40" height="40" class="me-2">
        <span>Business System</span>
      </a>

      <!-- Profile image right next to logo -->
      <a href="../pages/profile.php" class="ms-3">
        <img src="../uploads/prof.png" alt="Profile" style="height: 45px; width: 45px; border-radius: 50%;">
      </a>
    </div>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarContent">
      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link text-danger" href="../auth/logout.php">Logout</a>
        </li>
      </ul>
    </div>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
