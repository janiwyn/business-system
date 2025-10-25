<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);
include '../pages/super_sidebar.php';
include '../includes/header.php';

// === Ensure logs directory exists ===
$logDir = __DIR__ . "/../logs";
if (!is_dir($logDir)) mkdir($logDir, 0777, true);

$logFile = $logDir . "/system.log";

// Super Admin username
$user = $_SESSION['username'] ?? 'Super Admin';

$uploadMsg = "";

// === Handle system update upload ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['update_file'])) {
    $targetDir = "../updates/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $fileName = basename($_FILES["update_file"]["name"]);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES["update_file"]["tmp_name"], $targetFile)) {
        $uploadMsg = "✅ Update file uploaded successfully!";
        file_put_contents($logFile, date("Y-m-d H:i:s") . " - [$user] Uploaded update file: $fileName\n", FILE_APPEND);
    } else {
        $uploadMsg = "❌ Failed to upload update file.";
    }
}

// === Handle database backup ===
$db_user = "root";
$db_pass = "";
$db_name = "business-system";

if (isset($_POST['backup_db'])) {
    $backupDir = "../backups/";
    if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

    $backupFile = $backupDir . "db_backup_" . date("Y_m_d_H_i_s") . ".sql";
    $command = "mysqldump --user={$db_user} --password={$db_pass} {$db_name} > $backupFile";
    system($command, $output);

    $uploadMsg = "✅ Database backup created successfully at $backupFile";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - [$user] Created database backup: $backupFile\n", FILE_APPEND);
}

// === Handle clear logs ===
if (isset($_POST['clear_logs'])) {
    file_put_contents($logFile, "");
    $uploadMsg = "🧹 System logs cleared successfully!";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - [$user] Cleared system logs\n", FILE_APPEND);
}

// === Handle clear cache (demo) ===
if (isset($_POST['clear_cache'])) {
    $uploadMsg = "⚡ Cache cleared successfully! (Demo mode)";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - [$user] Cleared cache\n", FILE_APPEND);
}
?>

<div class="container mt-4">
    <h1 class="mb-4">System Updates & Maintenance</h1>

    <?php if ($uploadMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($uploadMsg) ?></div>
    <?php endif; ?>

    <!-- Upload Update -->
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">🆕 Upload New System Update</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                <input type="file" name="update_file" class="form-control" required>
                <button type="submit" class="btn btn-success">Upload Update</button>
            </form>
            <small class="text-muted">You can upload a .zip update or SQL file to patch the system.</small>
        </div>
    </div>

    <!-- Database Backup -->
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">💾 Backup Database</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <button type="submit" name="backup_db" class="btn btn-success">Create Backup</button>
            </form>
        </div>
    </div>

    <!-- Clear Logs -->
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-warning text-white">
            <h5 class="mb-0">🧹 Clear System Logs</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <button type="submit" name="clear_logs" class="btn btn-warning">Clear Logs</button>
            </form>
        </div>
    </div>

    <!-- Clear Cache -->
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">⚡ Clear Cache</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <button type="submit" name="clear_cache" class="btn btn-info text-white">Clear Cache</button>
            </form>
        </div>
    </div>

    <!-- View System Logs -->
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">📜 View System Logs</h5>
        </div>
        <div class="card-body">
            <pre class="bg-black text-success p-3" style="height:300px; overflow-y:auto;">
<?php
if (file_exists($logFile)) {
    echo htmlspecialchars(file_get_contents($logFile));
} else {
    echo "No logs available.";
}
?>
            </pre>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
