<?php
// Catch ALL errors and return as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error: $errstr in $errfile on line $errline",
        'data' => null
    ]);
    exit;
});

// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../includes/db.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method. This endpoint only accepts POST requests. Please submit an order from the customer website.', null, 405);
}

// Handle both JSON and multipart/form-data (for file upload)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'multipart/form-data') !== false) {
    // Mobile money payment with screenshot
    $branchId = intval($_POST['branch_id'] ?? 0);
    $customerName = sanitizeInput($_POST['customer_name'] ?? '');
    $customerPhone = sanitizeInput($_POST['customer_phone'] ?? '');
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
    $deliveryLocation = sanitizeInput($_POST['delivery_location'] ?? '');
    $items = json_decode($_POST['items'] ?? '[]', true);
    
    // Validate screenshot upload
    if (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'Payment screenshot is required', null, 400);
    }
    
    // Validate delivery location
    if (empty($deliveryLocation)) {
        jsonResponse(false, 'Delivery location is required for mobile money payments', null, 400);
    }
} else {
    // Cash payment (existing logic)
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['branch_id'], $input['customer_name'], $input['customer_phone'], $input['items']) 
        || empty($input['items'])) {
        jsonResponse(false, 'Missing required fields', null, 400);
    }
    
    $branchId = intval($input['branch_id']);
    $customerName = sanitizeInput($input['customer_name']);
    $customerPhone = sanitizeInput($input['customer_phone']);
    $paymentMethod = isset($input['payment_method']) ? sanitizeInput($input['payment_method']) : 'cash';
    $deliveryLocation = null;
    $items = $input['items'];
}

// Validate phone
if (!validatePhone($customerPhone)) {
    jsonResponse(false, 'Invalid phone number', null, 400);
}

// Validate items
if (!is_array($items) || count($items) === 0) {
    jsonResponse(false, 'Cart is empty', null, 400);
}

// Get database connection
$conn = getDBConnection();

if (!$conn) {
    jsonResponse(false, 'Database connection failed', null, 500);
}

mysqli_begin_transaction($conn);

try {
    // Generate order reference
    $orderReference = generateOrderReference();
    
    // Calculate total amount
    $expectedAmount = 0;
    foreach ($items as $item) {
        if (!isset($item['product_id'], $item['product_name'], $item['unit_price'], $item['quantity'])) {
            throw new Exception('Invalid item data');
        }
        $expectedAmount += floatval($item['unit_price']) * intval($item['quantity']);
    }
    
    // Set QR expiry (24 hours from now)
    $qrExpiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Insert order (add delivery_location column)
    $stmt = mysqli_prepare($conn, "INSERT INTO remote_orders 
        (order_reference, branch_id, customer_name, customer_phone, payment_method, expected_amount, delivery_location, qr_code_expires_at, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "sisssdss", 
        $orderReference, $branchId, $customerName, $customerPhone, $paymentMethod, $expectedAmount, $deliveryLocation, $qrExpiresAt);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to create order: ' . mysqli_stmt_error($stmt));
    }
    
    $orderId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    // Generate QR code data
    $qrCodeData = generateQRCodeData($orderId, $orderReference);
    
    // Update order with QR code
    $stmt = mysqli_prepare($conn, "UPDATE remote_orders SET qr_code = ? WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare QR update: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "si", $qrCodeData, $orderId);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to update QR code: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    
    // Insert order items
    $stmt = mysqli_prepare($conn, "INSERT INTO remote_order_items 
        (order_id, product_id, product_name, quantity, unit_price, subtotal) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare items statement: ' . mysqli_error($conn));
    }
    
    foreach ($items as $item) {
        $productId = intval($item['product_id']);
        $productName = sanitizeInput($item['product_name']);
        $quantity = intval($item['quantity']);
        $unitPrice = floatval($item['unit_price']);
        $subtotal = $unitPrice * $quantity;
        
        mysqli_stmt_bind_param($stmt, "iisidd", 
            $orderId, $productId, $productName, $quantity, $unitPrice, $subtotal);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to add order items: ' . mysqli_stmt_error($stmt));
        }
    }
    
    mysqli_stmt_close($stmt);
    
    // Handle screenshot upload for mobile money payments
    if (isset($_FILES['payment_screenshot'])) {
        $uploadDir = '../uploads/payment_proofs/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION);
        $fileName = 'proof_' . $orderId . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $targetPath)) {
            throw new Exception('Failed to upload screenshot');
        }
        
        // Insert payment proof record
        $proofStmt = mysqli_prepare($conn, "INSERT INTO payment_proofs 
            (order_id, order_reference, customer_name, customer_phone, payment_method, delivery_location, screenshot_path, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        mysqli_stmt_bind_param($proofStmt, "issssss", 
            $orderId, $orderReference, $customerName, $customerPhone, $paymentMethod, $deliveryLocation, $fileName);
        
        if (!mysqli_stmt_execute($proofStmt)) {
            throw new Exception('Failed to save payment proof: ' . mysqli_stmt_error($proofStmt));
        }
        
        mysqli_stmt_close($proofStmt);
    }
    
    // Log audit
    logAuditAction($conn, $orderId, 'order_created', $customerName, null, null, 'pending', 'Order created from customer website');
    
    mysqli_commit($conn);
    
    $responseData = [
        'order_id' => $orderId,
        'order_reference' => $orderReference,
        'qr_code' => $qrCodeData,
        'expected_amount' => $expectedAmount,
        'expires_at' => $qrExpiresAt
    ];
    
    closeDBConnection($conn);
    jsonResponse(true, 'Order created successfully', $responseData);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    closeDBConnection($conn);
    
    jsonResponse(false, $e->getMessage(), null, 500);
}
?>
