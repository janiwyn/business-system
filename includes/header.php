<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Business System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-papcS3G+N+..." crossorigin="anonymous" referrerpolicy="no-referrer" />

  <style>
    body {
      background-color: #f4f6f9;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .navbar {
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      background: linear-gradient(90deg, #0f2027, #203a43, #2c5364);
    }
    .navbar-brand span {
      font-weight: 600;
      letter-spacing: 1px;
      font-size: 1.2rem;
    }
    .navbar img {
      border-radius: 50%;
    }

.hover-effect {
    transition: all 0.3s ease-in-out;
    border-radius: 8px;
    padding: 8px 12px;
}

.hover-effect:hover {
    background-color: #0d6efd; /* Bootstrap primary blue */
    color: #fff !important;
    transform: translateX(5px);
}

.hover-logout:hover {
    background-color: #dc3545 !important; /* Bootstrap red */
    color: #fff !important;
    border-radius: 8px;
    transform: scale(1.05);
}


  </style>
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-dark px-4">
    <div class="d-flex align-items-center me-auto">
      <a class="navbar-brand d-flex align-items-center" href="#">
        <img src="../uploads/logo.png" alt="" width="40" height="40" class="me-2">
        <span>Business System</span>
      </a>

      <a href="../pages/profile.php" class="ms-3">
        <img src="../uploads/prof.png" alt="Profile" style="height: 45px; width: 45px;">
      </a>
    </div>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarContent">
      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link text-danger fw-bold" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </li>
      </ul>
    </div>
  </nav>
