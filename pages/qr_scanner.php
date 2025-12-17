<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["admin", "staff", "manager"]);
include '../pages/sidebar.php';
include '../includes/header.php';
require_once '../includes/functions.php';

// Get user info
$user_role = $_SESSION['role'];
$user_branch = $_SESSION['branch_id'] ?? null;
?>

<div class="container-fluid mt-5">
    <div class="card" style="border-left: 4px solid teal;">
        <div class="card-header title-card">
            <i class="fas fa-qrcode me-2"></i>QR Code Scanner
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="scanner-container">
                        <video id="qr-video" autoplay playsinline></video>
                        <div id="scan-status" class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>Point camera at QR code
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div id="order-details" class="d-none">
                        <h5 class="mb-3">Order Details</h5>
                        <div class="order-info">
                            <p><strong>Order Reference:</strong> <span id="order-ref"></span></p>
                            <p><strong>Customer Name:</strong> <span id="customer-name"></span></p>
                            <p><strong>Customer Phone:</strong> <span id="customer-phone"></span></p>
                            <p><strong>Branch:</strong> <span id="branch-name"></span></p>
                            <p><strong>Expected Amount:</strong> <span id="expected-amount"></span></p>
                            <p><strong>Order Date:</strong> <span id="order-date"></span></p>
                        </div>
                        
                        <h5 class="mt-4 mb-3">Order Items</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="order-items"></tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            <button class="btn btn-success btn-lg w-100" onclick="completeOrder()">
                                <i class="fas fa-check me-2"></i>Complete Order
                            </button>
                            <button class="btn btn-secondary mt-2 w-100" onclick="resetScanner()">
                                <i class="fas fa-redo me-2"></i>Scan Another Code
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.scanner-container {
    position: relative;
    max-width: 500px;
    margin: 0 auto;
}

#qr-video {
    width: 100%;
    border-radius: 8px;
    border: 3px solid var(--primary-color);
}

.order-info p {
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
    margin-bottom: 8px;
}

body.dark-mode .order-info p {
    background: #2c2c3a;
    color: #fff;
}
</style>

<script>
let currentOrderId = null;
let videoStream = null;

// Initialize QR scanner
async function initScanner() {
    const video = document.getElementById('qr-video');
    const statusDiv = document.getElementById('scan-status');
    
    try {
        // Get camera stream
        videoStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        
        video.srcObject = videoStream;
        
        // Check for BarcodeDetector API
        if ('BarcodeDetector' in window) {
            const barcodeDetector = new BarcodeDetector({
                formats: ['qr_code']
            });
            
            // Scan loop
            const scanFrame = async () => {
                if (!videoStream) return;
                
                try {
                    const barcodes = await barcodeDetector.detect(video);
                    
                    if (barcodes.length > 0) {
                        const qrCode = barcodes[0].rawValue;
                        console.log('QR Code detected:', qrCode);
                        await processQRCode(qrCode);
                    } else {
                        requestAnimationFrame(scanFrame);
                    }
                } catch (e) {
                    console.error('Scan error:', e);
                    requestAnimationFrame(scanFrame);
                }
            };
            
            scanFrame();
        } else {
            statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>BarcodeDetector not supported. Please use Chrome/Edge browser.</div>';
        }
    } catch (error) {
        console.error('Camera error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Camera access denied. Please enable camera permissions.</div>';
    }
}

// Process QR code
async function processQRCode(qrCode) {
    const statusDiv = document.getElementById('scan-status');
    
    // Stop scanning
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    
    statusDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Validating QR code...</div>';
    
    try {
        const response = await fetch('../pos/ajax/validate_qr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qr_code: qrCode })
        });
        
        const data = await response.json();
        console.log('Validation response:', data);
        
        if (data.success) {
            displayOrderDetails(data.data);
            statusDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Valid QR code!</div>';
        } else {
            statusDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times me-2"></i>${data.message}</div>`;
            setTimeout(resetScanner, 3000);
        }
    } catch (error) {
        console.error('Validation error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Failed to process QR code. Please try again.</div>';
        setTimeout(resetScanner, 3000);
    }
}

// Display order details
function displayOrderDetails(order) {
    currentOrderId = order.id;
    
    document.getElementById('order-ref').textContent = order.order_reference;
    document.getElementById('customer-name').textContent = order.customer_name;
    document.getElementById('customer-phone').textContent = order.customer_phone;
    document.getElementById('branch-name').textContent = order.branch_name;
    document.getElementById('expected-amount').textContent = 'UGX ' + Number(order.expected_amount).toLocaleString();
    document.getElementById('order-date').textContent = new Date(order.created_at).toLocaleString();
    
    // Display order items
    const itemsBody = document.getElementById('order-items');
    itemsBody.innerHTML = '';
    
    order.items.forEach(item => {
        const row = `
            <tr>
                <td>${escapeHtml(item.product_name)}</td>
                <td>${item.quantity}</td>
                <td>UGX ${Number(item.unit_price).toLocaleString()}</td>
                <td>UGX ${Number(item.subtotal).toLocaleString()}</td>
            </tr>
        `;
        itemsBody.innerHTML += row;
    });
    
    // Show order details
    document.getElementById('order-details').classList.remove('d-none');
}

// Complete order
async function completeOrder() {
    if (!currentOrderId) {
        alert('No order selected');
        return;
    }
    
    if (!confirm('Mark this order as finished?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'finish');
        formData.append('order_id', currentOrderId);
        
        const response = await fetch('../pages/remote_orders.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Order completed successfully!');
            resetScanner();
        } else {
            alert('Failed to complete order: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to complete order. Please try again.');
    }
}

// Reset scanner
function resetScanner() {
    currentOrderId = null;
    document.getElementById('order-details').classList.add('d-none');
    document.getElementById('scan-status').innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Point camera at QR code</div>';
    initScanner();
}

// Helper function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Start scanner on page load
window.addEventListener('load', initScanner);

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
});
</script>

<?php include '../includes/footer.php'; ?>
