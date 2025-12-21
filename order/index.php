<?php
// Define ASSETS_URL constant before using it
define('ASSETS_URL', '../assets');
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Your Order</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/order-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Hero Section -->
    <section class="hero" id="hero">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div class="company-logo">
                <i class="fas fa-store-alt"></i>
            </div>
            <h1 class="hero-title animate-fade-in">Welcome to <span id="companyName">Our Store</span></h1>
            <p class="hero-subtitle animate-fade-in" style="animation-delay: 0.2s;">
                Order your favorite products online and pick them up at your convenience
            </p>
            <button class="btn btn-primary btn-large animate-fade-in" style="animation-delay: 0.4s;" onclick="startOrder()">
                <i class="fas fa-shopping-cart"></i> Place Order Now
            </button>
        </div>
        <div class="hero-features">
            <div class="feature-item animate-slide-up" style="animation-delay: 0.5s;">
                <i class="fas fa-clock"></i>
                <h3>Quick & Easy</h3>
                <p>Order in minutes</p>
            </div>
            <div class="feature-item animate-slide-up" style="animation-delay: 0.6s;">
                <i class="fas fa-qrcode"></i>
                <h3>QR Code Pickup</h3>
                <p>Scan and collect</p>
            </div>
            <div class="feature-item animate-slide-up" style="animation-delay: 0.7s;">
                <i class="fas fa-shield-alt"></i>
                <h3>Secure Payment</h3>
                <p>Safe transactions</p>
            </div>
        </div>
    </section>

    <!-- Ordering Section -->
    <section class="ordering-section" id="orderingSection" style="display: none;">
        <div class="container">
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Branch</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Products</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Checkout</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Complete</div>
                </div>
            </div>

            <!-- Step 1: Branch Selection -->
            <div class="step-content active" id="step1">
                <h2 class="section-title">Select Your Branch</h2>
                <div class="branches-grid" id="branchesGrid">
                    <div class="loading-skeleton">
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Product Selection -->
            <div class="step-content" id="step2">
                <div class="products-header">
                    <h2 class="section-title">Select Products</h2>
                    <button class="btn btn-secondary" onclick="goToStep(1)">
                        <i class="fas fa-arrow-left"></i> Change Branch
                    </button>
                </div>
                <div class="products-grid" id="productsGrid">
                    <div class="loading-skeleton">
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Checkout -->
            <div class="step-content" id="step3">
                <div class="checkout-container">
                    <div class="checkout-main">
                        <h2 class="section-title">Customer Information</h2>
                        <form id="checkoutForm" class="checkout-form">
                            <div class="form-group">
                                <label for="customerName">
                                    <i class="fas fa-user"></i> Full Name
                                </label>
                                <input type="text" id="customerName" required placeholder="Enter your full name">
                            </div>
                            <div class="form-group">
                                <label for="customerPhone">
                                    <i class="fas fa-phone"></i> Phone Number
                                </label>
                                <input type="tel" id="customerPhone" required placeholder="e.g., 0700000000">
                            </div>
                            <div class="form-group">
                                <label for="paymentMethod">
                                    <i class="fas fa-credit-card"></i> Payment Method
                                </label>
                                <select id="paymentMethod">
                                    <option value="cash">Cash on Pickup</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="checkout-sidebar">
                        <div class="cart-summary">
                            <h3><i class="fas fa-shopping-cart"></i> Order Summary</h3>
                            <div id="cartItemsList"></div>
                            <div class="cart-total">
                                <span>Total Amount:</span>
                                <span class="total-amount" id="totalAmount">UGX 0</span>
                            </div>
                            <button class="btn btn-primary btn-block" onclick="placeOrder()">
                                <i class="fas fa-check"></i> Place Order
                            </button>
                            <button class="btn btn-secondary btn-block" onclick="goToStep(2)">
                                <i class="fas fa-arrow-left"></i> Back to Products
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Order Confirmation -->
            <div class="step-content" id="step4">
                <div class="confirmation-container">
                    <div class="success-animation">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="confirmation-title">Order Placed Successfully!</h2>
                    <p class="confirmation-subtitle">Thank you for your order</p>
                    
                    <div class="order-info-card">
                        <div class="info-row">
                            <span class="info-label">Order Reference:</span>
                            <span class="info-value" id="orderReference">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total Amount:</span>
                            <span class="info-value" id="orderAmount">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Valid Until:</span>
                            <span class="info-value" id="orderExpiry">-</span>
                        </div>
                    </div>

                    <div class="qr-code-container">
                        <h3>Your QR Code</h3>
                        <div id="qrCodeDisplay"></div>
                        <p class="qr-instructions">
                            <i class="fas fa-info-circle"></i>
                            Present this QR code at the branch to collect your order and make payment
                        </p>
                        <button class="btn btn-secondary" onclick="downloadQR()">
                            <i class="fas fa-download"></i> Download QR Code
                        </button>
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-plus"></i> Place Another Order
                        </button>
                        <button class="btn btn-secondary" onclick="checkOrderStatus()">
                            <i class="fas fa-search"></i> Check Order Status
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cart Floating Button -->
    <div class="cart-float" id="cartFloat" style="display: none;" onclick="goToStep(3)">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cartBadge">0</span>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/order.js"></script>
</body>
</html>
