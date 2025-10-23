<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);
include '../pages/super_sidebar.php';
include '../includes/header.php';

// ========== METRICS ==========

// Total businesses
$totalBusinesses = $conn->query("SELECT COUNT(*) AS total FROM businesses")->fetch_assoc()['total'];

// Active businesses
$activeBusinesses = $conn->query("SELECT COUNT(*) AS total FROM businesses WHERE subscription_status='active'")->fetch_assoc()['total'];

// Expired businesses
$expiredBusinesses = $conn->query("SELECT COUNT(*) AS total FROM businesses WHERE subscription_status='expired'")->fetch_assoc()['total'];

// Total admins (business owners)
$totalAdmins = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='admin'")->fetch_assoc()['total'];

// Total users (if you track employees or staff)
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];

// New businesses this month
$newThisMonth = $conn->query("SELECT COUNT(*) AS total FROM businesses WHERE MONTH(date_registered)=MONTH(CURDATE()) AND YEAR(date_registered)=YEAR(CURDATE())")->fetch_assoc()['total'];

// Prepare data for growth chart (business registrations per month)
$growthData = $conn->query("
    SELECT DATE_FORMAT(date_registered, '%Y-%m') AS month, COUNT(*) AS total
    FROM businesses
    GROUP BY month
    ORDER BY month ASC
");
$months = [];
$counts = [];
while($row = $growthData->fetch_assoc()) {
    $months[] = $row['month'];
    $counts[] = $row['total'];
}
?>

<div class="container">
    <h1>System Reports & Analytics</h1>

    <div class="stats-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-top:20px;">
        <div class="card">🏢 Total Businesses: <?= $totalBusinesses ?></div>
        <div class="card">✅ Active: <?= $activeBusinesses ?></div>
        <div class="card">⚠️ Expired: <?= $expiredBusinesses ?></div>
        <div class="card">👩‍💼 Admin Accounts: <?= $totalAdmins ?></div>
        <div class="card">👥 Total Users: <?= $totalUsers ?></div>
        <div class="card">📅 New This Month: <?= $newThisMonth ?></div>
    </div>

    <h2 style="margin-top:40px;">📈 Business Growth (Monthly)</h2>
    <canvas id="growthChart" width="400" height="200"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('growthChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($months); ?>,
        datasets: [{
            label: 'New Businesses per Month',
            data: <?= json_encode($counts); ?>,
            borderWidth: 2,
            tension: 0.2
        }]
    },
    options: {
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
