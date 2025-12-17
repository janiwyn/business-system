<?php
require_once '../includes/db.php';

// Get company settings
$settings = mysqli_query($conn, "SELECT * FROM company_settings LIMIT 1")->fetch_assoc();
$companyName = $settings['company_name'] ?? 'Our Business';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($companyName) ?> - Place Your Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="container hero-content">
            <h1 class="hero-title animate-fade-in"><?= htmlspecialchars($companyName) ?></h1>
            <p class="hero-subtitle animate-slide-up">Order your favorite products online</p>
            <button class="btn btn-primary btn-lg start-order-btn animate-bounce" onclick="showBranchSelection()">
                <i class="fas fa-shopping-cart me-2"></i> Start Ordering
            </button>
        </div>
    </section>

    <!-- Branch Selection Modal -->
    <div class="modal fade" id="branchModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-map-marker-alt me-2"></i>Select Your Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="branchesContainer" class="row g-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Section -->
    <section class="products-section d-none" id="productsSection">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-box me-2"></i>Available Products</h2>
                <button class="btn btn-outline-primary" onclick="showBranchSelection()">
                    <i class="fas fa-exchange-alt me-2"></i>Change Branch
                </button>
            </div>
            <div id="productsContainer" class="row g-4"></div>
        </div>
    </section>

    <!-- Cart Drawer -->
    <div class="cart-drawer" id="cartDrawer">
        <div class="cart-header">
            <h5><i class="fas fa-shopping-cart me-2"></i>Your Cart</h5>
            <button class="btn-close-cart" onclick="toggleCart()"><i class="fas fa-times"></i></button>
        </div>
        <div class="cart-body" id="cartItems"></div>
        <div class="cart-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotal">UGX 0</span>
            </div>
            <button class="btn btn-primary w-100" onclick="proceedToCheckout()">
                Proceed to Checkout <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>
    </div>

    <!-- Floating Cart Button -->
    <button class="floating-cart-btn" onclick="toggleCart()">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-count" id="cartCount">0</span>
    </button>

    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i>Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="checkoutForm">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="customerName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="customerPhone" required 
                                   pattern="[0-9]{10,15}" placeholder="e.g., 0700123456">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" id="paymentMethod">
                                <option value="cash">Cash on Pickup</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitOrder()">
                        <i class="fas fa-check me-2"></i>Place Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Order Placed Successfully!</h5>
                </div>
                <div class="modal-body text-center">
                    <div class="success-animation mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
                    </div>
                    <h5>Your Order Reference</h5>
                    <h3 class="text-primary mb-4" id="orderReference"></h3>
                    <div id="qrCodeContainer" class="mb-4"></div>
                    <p class="text-muted">Please present this QR code when picking up your order</p>
                    <div class="alert alert-info">
                        <small><i class="fas fa-clock me-2"></i>Valid for 24 hours</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print QR Code
                    </button>
                    <button class="btn btn-secondary" onclick="location.reload()">
                        <i class="fas fa-home me-2"></i>Place New Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
