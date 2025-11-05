<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <!-- Change the favicon path below to your business logo file -->
  <link rel="icon" type="image/png" href="../uploads/2.png">
  <title>Bluecrest POS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../pages/assets/css/style.css" />
  <link rel="stylesheet" href="../assets/css/responsive.css" />
  <!-- Add Google Fonts for tech logo style -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Nunito+Sans:wght@700&display=swap" rel="stylesheet">
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
      flex: 1 1 auto;
      min-width: 0;
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
      font-family: 'Montserrat', 'Nunito Sans', Arial, sans-serif;
      font-weight: 700;
      font-size: 1.5rem;
      letter-spacing: 1px;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      /* Ocean blue animated gradient wave */
      background: linear-gradient(270deg, #1abc9c, #3498db, #56ccf2, #1abc9c);
      background-size: 400% 400%;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-fill-color: transparent;
      animation: oceanWave 10s ease-in-out infinite;
      text-shadow: none;
    }
    @keyframes oceanWave {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
    .main-header .header-actions {
      display: flex;
      align-items: center;
      gap: 1.5rem; /* Increased gap */
      flex-shrink: 0;
    }
    .theme-switch {
      display: flex;
      align-items: center;
      gap: 0.7rem; /* Increased gap */
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
    .notification-icon {
      margin-right: 0.5rem;
    }
    body.dark-mode .main-header {
      background: linear-gradient(90deg, #23243a 0%, #1e1e2f 100%);
      color: #f4f4f4;
      box-shadow: 0 2px 8px rgba(44,62,80,0.18);
    }
    body.dark-mode .main-header .logo-text {
      color: #1abc9c;
      background: linear-gradient(270deg, #159c8c, #3498db, #56ccf2, #159c8c);
      background-size: 400% 400%;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-fill-color: transparent;
      animation: oceanWave 5s ease-in-out infinite;
      text-shadow: none;
    }
    body.dark-mode .theme-switch label {
      color: #ffd200;
    }
    /* Responsive styles for header */
    @media (max-width: 991.98px) {
      .main-header {
        padding: 0.7rem 1.2rem; /* Increased padding */
        font-size: 1.05rem;     /* Slightly larger font */
        min-height: 56px;       /* Increased height */
      }
      .main-header .logo-img {
        width: 36px;
        height: 36px;
      }
      .main-header .logo-text {
        font-size: 1.2rem;
        max-width: 140px;
      }
      .main-header .header-actions {
        gap: 1.2rem; /* More space between icons */
      }
      .theme-switch label {
        font-size: 1.2rem;
      }
      .notification-icon i {
        font-size: 1.2rem;
      }
      .hamburger {
        font-size: 1.7rem;
        margin-left: 1rem;
      }
      .main-header {
        flex-direction: row;
        align-items: center;
      }
      .main-header .logo-area {
        margin-bottom: 0;
      }
    }
    /* Sidebar styles */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 250px;
      background: rgba(255, 255, 255, 0.85); /* Increased opacity for better visibility */
      backdrop-filter: blur(16px);
      box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
      transform: translateX(-100%);
      transition: transform 0.3s ease;
      z-index: 2010; /* Higher than overlay */
      pointer-events: none; /* Default: not clickable */
    }
    .sidebar.sidebar-open {
      transform: translateX(0);
      pointer-events: auto; /* Enable clicks when open */
    }
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(44, 62, 80, 0.25); /* Slightly lighter overlay */
      backdrop-filter: blur(5px);
      z-index: 2000; /* Lower than sidebar */
      pointer-events: auto;
    }
    /* Responsive styles */
    @media (max-width: 991.98px) {
      .sidebar-nav li a {
        color: #222 !important; /* Black text for small/medium devices */
        background: transparent !important;
        transition: background 0.2s, color 0.2s;
      }
      .sidebar-nav li a:hover,
      .sidebar-nav li a.active {
        background: var(--primary-color, #1abc9c) !important;
        color: #fff !important;
      }
      .sidebar-title {
        color: #1abc9c !important;
      }
    }
    @media (min-width: 992px) {
      .hamburger {
        display: none;
      }
      .sidebar {
        display: block;
        position: static;
        transform: none;
        width: 250px;
        height: 100vh; /* Ensure full viewport height */
        background: transparent;
        backdrop-filter: none;
        box-shadow: none;
        pointer-events: auto;
        z-index: 10;
        overflow-y: auto; /* Restore vertical scrolling */
        overflow-x: auto;
        border-top-right-radius: 16px;    /* Rounded right top */
        border-bottom-right-radius: 16px; /* Rounded right bottom */
        border-top-left-radius: 0;        /* Straight left top */
        border-bottom-left-radius: 0;     /* Straight left bottom */
      }
      .sidebar-overlay {
        display: none;
      }
    }

    /* Animated Hamburger/X Icon */
    .hamburger {
      width: 40px;
      height: 40px;
      background: none;
      border: none;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
      z-index: 2100;
    }
    .hamburger .bar {
      position: absolute;
      left: 8px;
      right: 8px;
      height: 3px;
      background: #fff;
      border-radius: 2px;
      transition: all 0.35s cubic-bezier(.4,0,.2,1);
    }
    .hamburger .bar1 { top: 13px; }
    .hamburger .bar2 { top: 19px; }
    .hamburger .bar3 { top: 25px; }

    .hamburger.active .bar1 {
      top: 19px;
      transform: rotate(45deg);
      background: #e74c3c;
    }
    .hamburger.active .bar2 {
      opacity: 0;
      transform: scaleX(0.5);
    }
    .hamburger.active .bar3 {
      top: 19px;
      transform: rotate(-45deg);
      background: #e74c3c;
    }
  </style>
</head>
<body>
<header class="main-header">
  <div class="logo-area">
    <img src="../uploads/2.png" alt="Logo" class="logo-img" />
    <span class="logo-text">Bluecrest Technologies</span>
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
    <!-- Hamburger icon should be after theme toggle -->
    <button id="sidebarToggle" class="hamburger d-lg-none ms-auto" aria-label="Open sidebar">
      <span class="bar bar1"></span>
      <span class="bar bar2"></span>
      <span class="bar bar3"></span>
    </button>
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

  // Sidebar toggle for small/medium devices
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');

  function openSidebar() {
    if (sidebar) {
      sidebar.classList.add('sidebar-open');
      document.body.classList.add('sidebar-overlay');
      sidebarToggle.classList.add('active'); // Animate to X
    }
  }
  function closeSidebar() {
    if (sidebar) {
      sidebar.classList.remove('sidebar-open');
      document.body.classList.remove('sidebar-overlay');
      sidebarToggle.classList.remove('active'); // Animate to hamburger
    }
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
      if (sidebar.classList.contains('sidebar-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }
  // Close sidebar when clicking outside (overlay)
  document.addEventListener('click', function(e) {
    if (document.body.classList.contains('sidebar-overlay')) {
      if (sidebar && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
        closeSidebar();
      }
    }
  });
  // Optional: close sidebar on ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
  });
</script>
