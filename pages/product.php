<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "manager", "staff", "super"]);
include 'sms.php';

// Fix: Always use the correct sidebar for staff
if ($_SESSION['role'] === 'staff') {
    include '../pages/sidebar_staff.php';
} else {
    include '../pages/sidebar.php';
}
include '../includes/header.php';

$message = "";
$expiring_products = []; // add this near the top of your PHP file


// Get logged-in user info
$user_role   = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;

// Add product form
if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $category = trim($_POST['category'] ?? "");
    $price = trim($_POST['price'] ?? "");
    $cost = trim($_POST['cost'] ?? "");
    $stock = trim($_POST['stock'] ?? "");
    $branch_id = $_POST['branch_id'];
    $barcode = $_POST['barcode'] ?? ""; // Get barcode from form

    $stmt = $conn->prepare("INSERT INTO products (name, `selling-price`, `buying-price`, stock, `branch-id`, barcode) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sddiis", $name, $price, $cost, $stock, $branch_id, $barcode);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success shadow-sm'> Product added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger shadow-sm'> Error adding product: " . $stmt->error . "</div>";
    }
}

// ==========================
// Pagination setup
// ==========================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Branch filter
$where = "";
if ($user_role === 'staff' && $user_branch) {
    // Staff: always restrict to their branch
    $selected_branch = $user_branch;
    $where = "WHERE products.`branch-id` = $user_branch";
} elseif (!empty($_GET['branch'])) {
    $selected_branch = (int)$_GET['branch'];
    $where = "WHERE products.`branch-id` = $selected_branch";
} else {
    $selected_branch = null;
}

// Count products
$countRes = $conn->query("SELECT COUNT(*) AS total FROM products $where");
$total_products = ($countRes->fetch_assoc())['total'] ?? 0;
$total_pages = ceil($total_products / $limit);

// Fetch products with branch name
$result = $conn->query("
    SELECT products.*, branch.name AS branch_name 
    FROM products 
    JOIN branch ON products.`branch-id` = branch.id 
    $where 
    ORDER BY products.id DESC 
    LIMIT $offset,$limit
");

// Fetch all products into an array for reuse in both tables
$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!-- Custom Styling -->
<style>
    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 2rem;
        text-align: center;
        letter-spacing: 1px;
        /* animation: fadeInDown 0.8s; */
    }
    .card {
        border-radius: 12px;
        box-shadow: 0px 4px 12px rgba(0,0,0,0.08);
        transition: transform 0.2s ease-in-out;
    }
    .card:hover {
        transform: translateY(-2px);
    }
    .card-header,
    .title-card {
        color: #fff !important;
        background: var(--primary-color);
    }
    .card-header {
        font-weight: 600;
        background: var(--primary-color);
        color: #fff;
        border-radius: 12px 12px 0 0 !important;
        font-size: 1.1rem;
        letter-spacing: 1px;
    }
    .form-control, .form-select {
        border-radius: 8px;
    }
    .btn-primary {
        background: var(--primary-color) !important;
        border: none;
        border-radius: 8px;
        padding: 8px 18px;
        font-weight: 600;
        box-shadow: 0px 3px 8px rgba(0,0,0,0.2);
        color: #fff !important;
        transition: background 0.2s;
    }
    .btn-primary:hover, .btn-primary:focus {
        background: #159c8c !important;
        color: #fff !important;
    }
    .btn-warning, .btn-danger {
        border-radius: 6px;
        font-size: 13px;
        padding: 5px 12px;
    }
    .transactions-table table {
        width: 100%;
        border-collapse: collapse;
        background: var(--card-bg);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px var(--card-shadow);
    }
    .transactions-table thead {
        background: var(--primary-color);
        color: #fff;
        text-transform: uppercase;
        font-size: 13px;
    }
    .transactions-table tbody td {
        color: var(--text-color);
        padding: 0.75rem 1rem;
    }
    .transactions-table tbody tr {
        background-color: #fff;
        transition: background 0.2s;
    }
    .transactions-table tbody tr:nth-child(even) {
        background-color: #f4f6f9;
    }
    .transactions-table tbody tr:hover {
        background-color: rgba(0,0,0,0.05);
    }
    body.dark-mode .transactions-table table {
        background: var(--card-bg);
    }
    body.dark-mode .transactions-table thead {
        background-color: #1abc9c;
        color: #ffffff;
    }
    body.dark-mode .transactions-table tbody tr {
        background-color: #2c2c3a !important;
    }
    body.dark-mode .transactions-table tbody tr:nth-child(even) {
        background-color: #272734 !important;
    }
    body.dark-mode .transactions-table tbody td {
        color: #ffffff !important;
    }
    body.dark-mode .transactions-table tbody tr:hover {
        background-color: rgba(255,255,255,0.1) !important;
    }
    body.dark-mode .card-header,
    body.dark-mode .title-card {
        color: #fff !important;
        background-color: #2c3e50 !important;
    }
    body.dark-mode .card .card-header {
        color: #fff !important;
        background-color: #2c3e50 !important;
    }
    body.dark-mode .form-label,
    body.dark-mode .fw-semibold,
    body.dark-mode label,
    body.dark-mode .card-body {
        color: #fff !important;
    }
    body.dark-mode .form-control,
    body.dark-mode .form-select {
        background-color: #23243a !important;
        color: #fff !important;
        border: 1px solid #444 !important;
    }
    body.dark-mode .form-control:focus,
    body.dark-mode .form-select:focus {
        background-color: #23243a !important;
        color: #fff !important;
    }
    .title-card {
        color: var(--primary-color);
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0;
        text-align: left;
    }

    /* Barcode scan modal styles */
    .barcode-scan-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1050;
    }
    .barcode-scan-card {
        background: #fff;
        border-radius: 12px;
        padding: 1.5rem;
        width: 90%;
        max-width: 600px;
        position: relative;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    .barcode-scan-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .barcode-scan-header span {
        font-weight: 600;
        color: var(--primary-color);
    }
    .barcode-scan-body {
        text-align: center;
    }
    .barcode-scan-view-area {
        position: relative;
        width: 100%;
        height: 200px;
        margin-bottom: 1rem;
        border-radius: 8px;
        overflow: hidden;
        background: #f4f6f9;
    }
    #productBarcodeScanVideo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
    }
    #productBarcodeScanCanvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }
    .barcode-scan-text {
        margin: 0.5rem 0;
        color: #333;
    }
    .barcode-scan-mode {
        margin-bottom: 1rem;
    }
    .barcode-scan-status {
        font-weight: 500;
        color: #666;
    }
    .btn-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #333;
        cursor: pointer;
    }
    .btn-secondary.barcode-rotate-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
    }
</style>

<div class="container mt-5">

    <div class="card mb-4">
        <div class="card-header title-card d-flex justify-content-between align-items-center">
            <span>➕ Add New Product</span>
            <!-- Scan icon button -->
            <button type="button" id="scanProductBarcodeBtn" class="btn btn-outline-primary btn-scan-barcode" title="Scan Barcode">
                <i class="fa-solid fa-barcode"></i>
            </button>
        </div>
        <div class="card-body">
            <?= isset($message) ? $message : "" ?>
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="barcode" class="form-label fw-semibold">Barcode</label>
                        <input type="text" name="barcode" id="barcode" class="form-control" placeholder="Scan or enter barcode" required>
                    </div>
                    <div class="col-md-3">
                        <label for="name" class="form-label fw-semibold">Product Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Coca-Cola 500ml" required>
                    </div>
                    <div class="col-md-3">
                        <label for="price" class="form-label fw-semibold">Selling Price</label>
                        <input type="number" step="0.01" name="price" id="price" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label for="cost" class="form-label fw-semibold">Buying Price</label>
                        <input type="number" step="0.01" name="cost" id="cost" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label for="stock" class="form-label fw-semibold">Stock Quantity</label>
                        <input type="number" name="stock" id="stock" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-md-3">
                        <label for="branch" class="form-label fw-semibold">Branch</label>
                        <select name="branch_id" id="branch" class="form-select" required>
                            <option value="">-- Select Branch --</option>
                            <?php
                            if ($user_role === 'staff' && $user_branch) {
                                // Staff: only show their branch
                                $branch = $conn->prepare("SELECT id, name FROM branch WHERE id = ?");
                                $branch->bind_param("i", $user_branch);
                                $branch->execute();
                                $branch_res = $branch->get_result();
                                if ($b = $branch_res->fetch_assoc()) {
                                    echo "<option value='{$b['id']}' selected>" . htmlspecialchars($b['name']) . "</option>";
                                }
                                $branch->close();
                            } else {
                                // Other roles: show all branches
                                $branches = $conn->query("SELECT id, name FROM branch");
                                while ($b = $branches->fetch_assoc()) {
                                    echo "<option value='{$b['id']}'>" . htmlspecialchars($b['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="expiry_date" class="form-label fw-semibold">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry_date" class="form-control" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" name="add_product" class="btn btn-primary">➕ Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Barcode Scan Modal/Card -->
    <div id="productBarcodeScanModal" class="barcode-scan-modal" style="display:none;">
        <div class="barcode-scan-card">
            <div class="barcode-scan-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-barcode"></i> Scan Product Barcode</span>
                <button type="button" id="closeProductBarcodeScan" class="btn btn-close"></button>
            </div>
            <div class="barcode-scan-body">
                <div class="barcode-scan-view-area">
                    <video id="productBarcodeScanVideo" autoplay muted playsinline></video>
                    <canvas id="productBarcodeScanCanvas" style="display:none;"></canvas>
                    <button type="button" id="rotateProductBarcodeCameraBtn" class="btn btn-secondary barcode-rotate-btn" title="Switch Camera">
                        <i class="fa-solid fa-camera-rotate"></i>
                    </button>
                </div>
                <div class="barcode-scan-text mt-3 mb-2 text-center">
                    <span>Scan product barcode to auto-fill.</span>
                </div>
                <div class="barcode-scan-mode mb-3 text-center">
                    <label class="me-2">Scan Mode:</label>
                    <select id="productBarcodeScanMode" class="form-select d-inline-block" style="width:auto;">
                        <option value="camera">Camera</option>
                        <option value="hardware">Barcode Hardware</option>
                    </select>
                </div>
                <div id="productBarcodeScanStatus" class="barcode-scan-status text-center"></div>
            </div>
        </div>
    </div>

    <!-- Product List -->
    <!-- Card wrapper for small devices -->
    <div class="d-block d-md-none mb-4">
      <div class="card transactions-card">
        <!-- Add title and search bar for small devices -->
        <div class="card-header d-flex justify-content-between align-items-center title-card">
            <span>📋 Product List</span>
            <input type="text" id="productSearchInputMobile" class="form-control" placeholder="Search by product name..." style="width: 170px;">
        </div>
        <div class="card-body">
          <div class="table-responsive-sm">
            <div class="transactions-table">
              <table id="productTableMobile">
                <thead>
                  <tr>
                    <th>#</th>
                    <?php if (empty($selected_branch) && $user_role !== 'staff') echo "<th>Branch</th>"; ?>
                    <th>Name</th>
                    <th>Barcode</th>
                    <th>Selling Price</th>
                    <th>Buying Price</th>
                    <th>Stock</th>
                    <th>Expiry Date</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  if (count($products) > 0) {
                      $i = $offset + 1;
                      foreach ($products as $row) {
                          // Check expiry
$today = date('Y-m-d');
$expiry = $row['expiry_date'];

// Calculate days left (difference in days)
$daysLeft = floor((strtotime($expiry) - strtotime($today)) / 86400);


// Check if the product expires in - 7 to 0 to +7 days (inclusive)
if (abs($daysLeft) <= 7 && !$row['sms_sent']) {
    sendExpirySMS($row['name'], $expiry);
    $conn->query("UPDATE products SET sms_sent = 1 WHERE id = {$row['id']}");
}


        // Highlight expiring products
        $highlight = "";
        foreach($expiring_products as $exp){
            if($row['id'] == $exp['id']){
                $highlight = "style='background-color: #ffcccc;'"; // light red
                break;
            }
        }

        echo "<tr $highlight>
            <td>{$i}</td>";
        if (empty($selected_branch) && $user_role !== 'staff') {
            echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
        }
        echo "<td>" . htmlspecialchars($row['name']) . "</td>
            <td>" . htmlspecialchars($row['barcode']) . "</td>
            <td>UGX " . number_format($row['selling-price'], 2) . "</td>
            <td>UGX " . number_format($row['buying-price'], 2) . "</td>
            <td>{$row['stock']}</td>
            <td>{$row['expiry_date']}</td>
            <td>
                <a href='edit_product.php?id={$row['id']}' class='btn btn-sm btn-warning me-1' title='Edit'>
                  <i class='fa fa-edit'></i>
                </a>
                <a href='delete_product.php?id={$row['id']}' class='btn btn-sm btn-danger' title='Delete' onclick='return confirm(\"Are you sure you want to delete this product?\")'>
                  <i class='fa fa-trash'></i>
                </a>
            </td>
        </tr>";
        $i++;
    }
} else {
    $colspan = (empty($selected_branch) && $user_role !== 'staff') ? 9 : 8;
    echo "<tr><td colspan='$colspan' class='text-center text-muted'>No products found.</td></tr>";
}
?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table for medium and large devices -->
    <div class="card mb-5 d-none d-md-block">
        <div class="card-header d-flex justify-content-between align-items-center title-card">
            <span>📋 Product List</span>
            <div class="d-flex align-items-center gap-2">
                <!-- Search box -->
                <input type="text" id="productSearchInput" class="form-control" placeholder="Search by product name..." style="width:220px;">
                <?php if ($user_role !== 'staff'): ?>
                <form method="GET" class="d-flex align-items-center ms-2">
                    <label class="me-2 fw-bold">Filter by Branch:</label>
                    <select name="branch" class="form-select" onchange="this.form.submit()">
                        <option value="">-- All Branches --</option>
                        <?php
                        $branches = $conn->query("SELECT id, name FROM branch");
                        while ($b = $branches->fetch_assoc()) {
                            $selected = ($selected_branch == $b['id']) ? "selected" : "";
                            echo "<option value='{$b['id']}' $selected>" . htmlspecialchars($b['name']) . "</option>";
                        }
                        ?>
                    </select>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="transactions-table">
                <table id="productTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if (empty($selected_branch) && $user_role !== 'staff') echo "<th>Branch</th>"; ?>
                            <th>Name</th>
                            <th>Barcode</th>
                            <th>Selling Price</th>
                            <th>Buying Price</th>
                            <th>Stock</th>
                            <th>Expiry Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($products) > 0) {
                            $i = $offset + 1;
                            foreach ($products as $row) {
                                // Check expiry
$today = date('Y-m-d');
$expiry = $row['expiry_date'];

// Calculate days left (difference in days)
$daysLeft = floor((strtotime($expiry) - strtotime($today)) / 86400);


// Check if the product expires in - 7 to 0 to +7 days (inclusive)
if (abs($daysLeft) <= 7 && !$row['sms_sent']) {
    sendExpirySMS($row['name'], $expiry);
    $conn->query("UPDATE products SET sms_sent = 1 WHERE id = {$row['id']}");
}


                                // Highlight expiring products
                                $highlight = "";
                                foreach($expiring_products as $exp){
                                    if($row['id'] == $exp['id']){
                                        $highlight = "style='background-color: #ffcccc;'"; // light red
                                        break;
                                    }
                                }

                                echo "<tr $highlight>
                                    <td>{$i}</td>";
                                if (empty($selected_branch) && $user_role !== 'staff') {
                                    echo "<td>" . htmlspecialchars($row['branch_name']) . "</td>";
                                }
                                echo "<td>" . htmlspecialchars($row['name']) . "</td>
                                    <td>" . htmlspecialchars($row['barcode']) . "</td>
                                    <td>UGX " . number_format($row['selling-price'], 2) . "</td>
                                    <td>UGX " . number_format($row['buying-price'], 2) . "</td>
                                    <td>{$row['stock']}</td>
                                    <td>{$row['expiry_date']}</td>
                                    <td>
                                        <a href='edit_product.php?id={$row['id']}' class='btn btn-sm btn-warning me-1'>✏️ Edit</a>
                                        <a href='delete_product.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this product?\")'>🗑️ Delete</a>
                                    </td>
                                </tr>";
                                $i++;
                            }
                        } else {
                            $colspan = (empty($selected_branch) && $user_role !== 'staff') ? 9 : 8;
                            echo "<tr><td colspan='$colspan' class='text-center text-muted'>No products found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-3">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?><?= ($selected_branch ? '&branch=' . $selected_branch : '') ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Barcode scanning logic for Add Product
(function() {
    const scanBtn = document.getElementById('scanProductBarcodeBtn');
    const scanModal = document.getElementById('productBarcodeScanModal');
    const closeScanBtn = document.getElementById('closeProductBarcodeScan');
    const scanVideo = document.getElementById('productBarcodeScanVideo');
    const scanCanvas = document.getElementById('productBarcodeScanCanvas');
    const rotateBtn = document.getElementById('rotateProductBarcodeCameraBtn');
    const scanModeSel = document.getElementById('productBarcodeScanMode');
    const scanStatus = document.getElementById('productBarcodeScanStatus');
    let currentStream = null;
    let currentFacing = 'environment';
    let scanActive = false;

    scanBtn?.addEventListener('click', () => {
        scanModal.style.display = 'flex';
        scanStatus.textContent = '';
        startCameraScan();
    });

    closeScanBtn?.addEventListener('click', () => {
        scanModal.style.display = 'none';
        stopCameraScan();
    });

    rotateBtn?.addEventListener('click', () => {
        currentFacing = (currentFacing === 'environment') ? 'user' : 'environment';
        startCameraScan();
    });

    scanModeSel?.addEventListener('change', () => {
        if (scanModeSel.value === 'hardware') {
            stopCameraScan();
            scanVideo.style.display = 'none';
            scanCanvas.style.display = 'none';
            scanStatus.textContent = 'Focus barcode input field and scan using hardware scanner.';
            ensureHardwareInput();
        } else {
            scanVideo.style.display = '';
            scanStatus.textContent = '';
            startCameraScan();
        }
    });

    function startCameraScan() {
        stopCameraScan();
        scanActive = true;
        scanVideo.style.display = '';
        scanCanvas.style.display = 'none';
        scanStatus.textContent = 'Initializing camera...';
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({
                video: { facingMode: currentFacing }
            }).then(stream => {
                currentStream = stream;
                scanVideo.srcObject = stream;
                scanVideo.play();
                scanStatus.textContent = 'Point camera at barcode.';
                if ('BarcodeDetector' in window) {
                    const detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'upc_a', 'upc_e'] });
                    const scanFrame = () => {
                        if (!scanActive) return;
                        detector.detect(scanVideo).then(barcodes => {
                            if (barcodes.length > 0) {
                                handleBarcode(barcodes[0].rawValue);
                            } else {
                                requestAnimationFrame(scanFrame);
                            }
                        }).catch(() => requestAnimationFrame(scanFrame));
                    };
                    scanFrame();
                } else {
                    scanStatus.textContent = 'BarcodeDetector not supported. Please use Chrome/Edge or hardware scanner.';
                }
            }).catch(err => {
                scanStatus.textContent = 'Camera error: ' + err.message;
            });
        } else {
            scanStatus.textContent = 'Camera not supported.';
        }
    }

    function stopCameraScan() {
        scanActive = false;
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }
        scanVideo.srcObject = null;
    }

    function ensureHardwareInput() {
        let hwInput = document.getElementById('hardwareProductBarcodeInput');
        if (!hwInput) {
            hwInput = document.createElement('input');
            hwInput.type = 'text';
            hwInput.id = 'hardwareProductBarcodeInput';
            hwInput.style.position = 'absolute';
            hwInput.style.opacity = 0;
            hwInput.style.pointerEvents = 'none';
            scanModal.appendChild(hwInput);
        }
        hwInput.value = '';
        hwInput.focus();
        hwInput.oninput = function() {
            if (hwInput.value.length >= 6) {
                handleBarcode(hwInput.value.trim());
                hwInput.value = '';
            }
        };
    }

    function handleBarcode(barcode) {
        scanStatus.textContent = 'Barcode detected: ' + barcode;
        document.getElementById('barcode').value = barcode;
        scanModal.style.display = 'none';
        stopCameraScan();
        document.getElementById('name').focus();
    }

    scanModal?.addEventListener('click', function(e) {
        if (e.target === scanModal) {
            scanModal.style.display = 'none';
            stopCameraScan();
        }
    });
})();

// Product search filter for large device table
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearchInput');
    const table = document.getElementById('productTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                // Find the product name cell (skip branch column if present)
                let nameCell = row.querySelectorAll('td')[ (<?php echo (empty($selected_branch) && $user_role !== 'staff') ? '2' : '1'; ?>) ];
                if (nameCell) {
                    const name = nameCell.textContent.trim().toLowerCase();
                    row.style.display = (name.includes(filter) || filter === '') ? '' : 'none';
                }
            });
        });
    }
});

// Product search filter for small device table
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearchInputMobile');
    const table = document.getElementById('productTableMobile');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                // Find the product name cell (skip branch column if present)
                let nameCell = row.querySelectorAll('td')[ (<?php echo (empty($selected_branch) && $user_role !== 'staff') ? '2' : '1'; ?>) ];
                if (nameCell) {
                    const name = nameCell.textContent.trim().toLowerCase();
                    row.style.display = (name.includes(filter) || filter === '') ? '' : 'none';
                }
            });
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
