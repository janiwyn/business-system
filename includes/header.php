<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Business System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../pages/assets/css/style.css" />
</head>
<body>
<nav class="navbar navbar-expand-lg px-4 header-nav">
  <div class="d-flex align-items-center me-auto">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="../uploads/logo.png" alt="" width="40" height="40" class="me-2 rounded">
      <span class="fw-bold">Business System</span>
    </a>
  </div>

  <div class="d-flex align-items-center">
    <!-- Dark/Light Toggle -->
    <div class="form-check form-switch theme-switch">
      <input class="form-check-input" type="checkbox" id="themeToggle">
      <label class="form-check-label text-white ms-2" for="themeToggle">
        <i class="fa-solid fa-moon"></i>
      </label>
    </div>
  </div>
</nav>


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
  </script>
