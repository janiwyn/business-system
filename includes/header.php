<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Business System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../pages/assets/css/style.css" />
  <style>
    .main-header {
      width: 100%;
      background: linear-gradient(90deg, #203a43 0%, #2c5364 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem 2rem;
      box-shadow: 0 2px 8px rgba(44,62,80,0.08);
      position: relative;
      z-index: 100;
    }
    .main-header .logo-area {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .main-header .logo-img {
      width: 44px;
      height: 44px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(44,62,80,0.12);
      object-fit: cover;
      background: #fff;
    }
    .main-header .logo-text {
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: 1px;
      color: #fff;
      text-shadow: 0 2px 8px rgba(44,62,80,0.08);
    }
    .main-header .header-actions {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }
    .theme-switch {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
    }
    .theme-switch input[type="checkbox"] {
      display: none;
    }
    .theme-switch label {
      cursor: pointer;
      font-size: 1.3rem;
      color: #fff;
      transition: color 0.2s;
    }
    .theme-switch label .fa-sun {
      color: #ffd200;
    }
    .theme-switch label .fa-moon {
      color: #00c6ff;
    }
    body.dark-mode .main-header {
      background: linear-gradient(90deg, #23243a 0%, #1e1e2f 100%);
      color: #f4f4f4;
      box-shadow: 0 2px 8px rgba(44,62,80,0.18);
    }
    body.dark-mode .main-header .logo-text {
      color: #1abc9c; 
      text-shadow: 0 2px 8px rgba(44,62,80,0.18);
    }
    body.dark-mode .theme-switch label {
      color: #ffd200;
    }
    @media (max-width: 768px) {
      .main-header { padding: 0.5rem 1rem; flex-direction: column; align-items: flex-start; }
      .main-header .logo-area { margin-bottom: 0.5rem; }
    }
  </style>
</head>
<body>
<header class="main-header">
  <div class="logo-area">
    <img src="../uploads/logo.png" alt="Logo" class="logo-img" />
    <span class="logo-text">Business System</span>
  </div>
  <div class="header-actions">
    <div class="notification-icon position-relative">
      <a href="../pages/notification.php" class="text-white text-decoration-none">
        <i class="fa-solid fa-bell"></i>
        <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
          0
        </span>
      </a>
    </div>
    <div class="theme-switch">
      <input type="checkbox" id="themeToggle" />
      <label for="themeToggle">
        <i class="fa-solid fa-moon"></i>
      </label>
    </div>
  </div>
</header>
<script>
  const themeToggle = document.getElementById("themeToggle");
  const body = document.body;
  const icon = document.querySelector(".theme-switch i");

  // Load saved theme
  if (localStorage.getItem("theme") === "dark") {
    body.classList.add("dark-mode");
    themeToggle.checked = true;
    icon.classList.remove("fa-moon");
    icon.classList.add("fa-sun");
  }

  // Toggle theme
  themeToggle.addEventListener("change", () => {
    body.classList.toggle("dark-mode");
    if (body.classList.contains("dark-mode")) {
      localStorage.setItem("theme", "dark");
      icon.classList.remove("fa-moon");
      icon.classList.add("fa-sun");
    } else {
      localStorage.setItem("theme", "light");
      icon.classList.remove("fa-sun");
      icon.classList.add("fa-moon");
    }
  });

  // Fetch notification count dynamically
  async function fetchNotificationCount() {
    try {
      const response = await fetch('../pages/notification_count.php');
      const data = await response.json();
      const badge = document.getElementById('notification-badge');
      if (data.count > 0) {
        badge.textContent = data.count;
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }
    } catch (error) {
      console.error('Error fetching notification count:', error);
    }
  }
  fetchNotificationCount();
</script>
