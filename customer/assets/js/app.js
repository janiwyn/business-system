const API_BASE = '../api';
let selectedBranch = null;
let cart = [];
let productsCache = []; // Cache products data

// Show Branch Selection
function showBranchSelection() {
    fetchBranches();
    const modal = new bootstrap.Modal(document.getElementById('branchModal'));
    modal.show();
}

// Fetch Branches - FIXED
async function fetchBranches() {
    const container = document.getElementById('branchesContainer');
    container.innerHTML = '<div class="col-12 text-center"><div class="spinner-border text-primary"></div></div>';
    
    try {
        const response = await fetch(`${API_BASE}/get_branches.php?business_id=1`);
        const data = await response.json();
        
        if (data.success && data.data && data.data.length > 0) {
            // Build complete HTML string FIRST
            let branchesHTML = '';
            data.data.forEach(branch => {
                branchesHTML += `
                    <div class="col-md-6">
                        <div class="branch-card" onclick="selectBranch(${branch.id}, '${escapeHtml(branch.name)}')">
                            <h5><i class="fas fa-store me-2"></i>${escapeHtml(branch.name)}</h5>
                            <p class="mb-1"><i class="fas fa-map-marker-alt me-2"></i>${escapeHtml(branch.location || 'Location not specified')}</p>
                        </div>
                    </div>
                `;
            });
            // Set innerHTML ONCE
            container.innerHTML = branchesHTML;
        } else {
            container.innerHTML = '<div class="col-12 text-center text-danger">No branches found.</div>';
        }
    } catch (error) {
        console.error('Fetch error:', error);
        container.innerHTML = `<div class="col-12 text-center text-danger">Failed to load branches: ${error.message}</div>`;
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Select Branch
function selectBranch(branchId, branchName) {
    selectedBranch = { id: branchId, name: branchName };
    bootstrap.Modal.getInstance(document.getElementById('branchModal')).hide();
    fetchProducts(branchId);
    document.getElementById('productsSection').classList.remove('d-none');
    document.querySelector('.hero-section').style.display = 'none';
}

// Fetch Products - FINAL CLEAN VERSION (no string escaping issues)
async function fetchProducts(branchId) {
    const container = document.getElementById('productsContainer');
    container.innerHTML = '<div class="col-12 text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Loading products...</p></div>';
    
    try {
        const response = await fetch(`${API_BASE}/get_products.php?branch_id=${branchId}`);
        const data = await response.json();
        
        console.log('Products API Response:', data);
        
        if (data.success) {
            if (data.data && data.data.length > 0) {
                // Store products in global cache
                productsCache = data.data;
                
                // Clear container first
                container.innerHTML = '';
                
                // Create product cards using DOM methods (NOT string concatenation)
                data.data.forEach(product => {
                    const productCard = createProductCard(product);
                    container.appendChild(productCard);
                });
            } else {
                container.innerHTML = '<div class="col-12 text-center"><div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No products available in this branch.</div></div>';
            }
        } else {
            container.innerHTML = `<div class="col-12 text-center text-danger">Error: ${data.message}</div>`;
        }
    } catch (error) {
        console.error('Fetch products error:', error);
        container.innerHTML = `<div class="col-12 text-center text-danger">Failed to load products: ${error.message}</div>`;
    }
}

// NEW: Create product card using DOM methods (avoids all string escaping issues)
function createProductCard(product) {
    // Create column wrapper
    const col = document.createElement('div');
    col.className = 'col-md-4 col-lg-3';
    
    // Create card
    const card = document.createElement('div');
    card.className = 'product-card';
    
    // Create image
    const placeholderImage = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-family="Arial" font-size="16"%3ENo Image%3C/text%3E%3C/svg%3E';
    const img = document.createElement('img');
    img.src = product.image ? `../uploads/${product.image}` : placeholderImage;
    img.alt = 'Product Image';
    img.className = 'product-image';
    img.onerror = function() {
        this.onerror = null;
        this.src = placeholderImage;
    };
    
    // Create product info div
    const productInfo = document.createElement('div');
    productInfo.className = 'product-info';
    
    // Product name
    const productName = document.createElement('h6');
    productName.textContent = product.name;
    
    // Product price
    const productPrice = document.createElement('p');
    productPrice.className = 'product-price';
    productPrice.textContent = `UGX ${Number(product.price).toLocaleString()}`;
    
    // Stock info
    const stockInfo = document.createElement('small');
    stockInfo.className = 'text-muted';
    stockInfo.textContent = `Stock: ${product.stock}`;
    
    // Quantity controls div
    const qtyControls = document.createElement('div');
    qtyControls.className = 'quantity-controls';
    
    // Decrease button
    const decreaseBtn = document.createElement('button');
    decreaseBtn.className = 'quantity-btn';
    decreaseBtn.textContent = '-';
    decreaseBtn.addEventListener('click', () => {
        const input = document.getElementById(`qty-${product.id}`);
        const currentQty = parseInt(input.value);
        input.value = Math.max(0, currentQty - 1);
    });
    
    // Quantity input
    const qtyInput = document.createElement('input');
    qtyInput.type = 'number';
    qtyInput.className = 'quantity-input';
    qtyInput.id = `qty-${product.id}`;
    qtyInput.value = '0';
    qtyInput.min = '0';
    qtyInput.max = product.stock;
    qtyInput.readOnly = true;
    
    // Increase button
    const increaseBtn = document.createElement('button');
    increaseBtn.className = 'quantity-btn';
    increaseBtn.textContent = '+';
    increaseBtn.addEventListener('click', () => {
        const input = document.getElementById(`qty-${product.id}`);
        const currentQty = parseInt(input.value);
        const maxStock = parseInt(product.stock);
        input.value = Math.min(maxStock, currentQty + 1);
    });
    
    // Add to cart button
    const addToCartBtn = document.createElement('button');
    addToCartBtn.className = 'btn btn-sm btn-primary w-100 mt-2';
    addToCartBtn.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Add to Cart';
    addToCartBtn.addEventListener('click', () => {
        const qtyInput = document.getElementById(`qty-${product.id}`);
        const quantity = parseInt(qtyInput.value);
        
        if (quantity === 0) {
            alert('Please select quantity');
            return;
        }
        
        const existingItem = cart.find(item => item.product_id === product.id);
        
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            cart.push({
                product_id: product.id,
                product_name: product.name,
                unit_price: parseFloat(product.price),
                quantity: quantity
            });
        }
        
        qtyInput.value = 0;
        updateCartUI();
        showToast('Product added to cart!', 'success');
    });
    
    // Assemble quantity controls
    qtyControls.appendChild(decreaseBtn);
    qtyControls.appendChild(qtyInput);
    qtyControls.appendChild(increaseBtn);
    
    // Assemble product info
    productInfo.appendChild(productName);
    productInfo.appendChild(productPrice);
    productInfo.appendChild(stockInfo);
    productInfo.appendChild(qtyControls);
    productInfo.appendChild(addToCartBtn);
    
    // Assemble card
    card.appendChild(img);
    card.appendChild(productInfo);
    
    // Assemble column
    col.appendChild(card);
    
    return col;
}

// Helper function to get product from cache by ID
function getProductById(productId) {
    return productsCache.find(p => p.id == productId);
}

// NEW: Attach event listeners to product buttons (called after products are loaded)
function attachProductEventListeners() {
    // Quantity decrease buttons
    document.querySelectorAll('.qty-decrease').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const input = document.getElementById(`qty-${productId}`);
            const currentQty = parseInt(input.value);
            const newQty = Math.max(0, currentQty - 1);
            input.value = newQty;
        });
    });
    
    // Quantity increase buttons
    document.querySelectorAll('.qty-increase').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const maxStock = parseInt(this.dataset.maxStock);
            const input = document.getElementById(`qty-${productId}`);
            const currentQty = parseInt(input.value);
            const newQty = Math.min(maxStock, currentQty + 1);
            input.value = newQty;
        });
    });
    
    // Add to cart buttons - FIXED: Get product data from cache
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const productId = parseInt(this.dataset.productId);
                
                // Get product from cache
                const product = getProductById(productId);
                
                if (!product) {
                    alert('Product not found');
                    return;
                }
                
                const qtyInput = document.getElementById(`qty-${productId}`);
                const quantity = parseInt(qtyInput.value);
                
                if (quantity === 0) {
                    alert('Please select quantity');
                    return;
                }
                
                const existingItem = cart.find(item => item.product_id === productId);
                
                if (existingItem) {
                    existingItem.quantity += quantity;
                } else {
                    cart.push({
                        product_id: productId,
                        product_name: product.name,
                        unit_price: parseFloat(product.price),
                        quantity: quantity
                    });
                }
                
                qtyInput.value = 0;
                updateCartUI();
                showToast('Product added to cart!', 'success');
            } catch (e) {
                console.error('Error adding to cart:', e);
                alert('Error adding product to cart');
            }
        });
    });
}

// Update Cart UI
function updateCartUI() {
    const cartItems = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    const cartTotal = document.getElementById('cartTotal');
    
    if (cart.length === 0) {
        cartItems.innerHTML = '<p class="text-center text-muted">Your cart is empty</p>';
        cartCount.textContent = '0';
        cartTotal.textContent = 'UGX 0';
        return;
    }
    
    let total = 0;
    let itemCount = 0;
    
    cartItems.innerHTML = '';
    cart.forEach((item, index) => {
        const subtotal = item.unit_price * item.quantity;
        total += subtotal;
        itemCount += item.quantity;
        
        cartItems.innerHTML += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <h6>${item.product_name}</h6>
                    <p class="mb-1">UGX ${Number(item.unit_price).toLocaleString()} × ${item.quantity}</p>
                    <strong>UGX ${Number(subtotal).toLocaleString()}</strong>
                </div>
                <button class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    });
    
    cartCount.textContent = itemCount;
    cartTotal.textContent = `UGX ${Number(total).toLocaleString()}`;
}

// Remove from Cart
function removeFromCart(index) {
    cart.splice(index, 1);
    updateCartUI();
    showToast('Item removed from cart', 'info');
}

// Toggle Cart
function toggleCart() {
    document.getElementById('cartDrawer').classList.toggle('active');
}

// Proceed to Checkout
function proceedToCheckout() {
    if (cart.length === 0) {
        alert('Your cart is empty!');
        return;
    }
    
    toggleCart();
    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    modal.show();
}

// Submit Order
async function submitOrder() {
    const customerName = document.getElementById('customerName').value.trim();
    const customerPhone = document.getElementById('customerPhone').value.trim();
    const paymentMethod = document.getElementById('paymentMethod').value;
    
    if (!customerName || !customerPhone) {
        alert('Please fill all required fields');
        return;
    }
    
    if (!/^[0-9]{10,15}$/.test(customerPhone)) {
        alert('Invalid phone number');
        return;
    }
    
    // FIXED: Check if mobile money payment method selected - show form instead of submitting
    if (paymentMethod === 'MTN Merchant' || paymentMethod === 'Airtel Merchant') {
        // Show mobile money payment section (expand form)
        showMobileMoneySection(paymentMethod);
        return; // Stop here - don't generate QR yet
    }
    
    // Cash payment - existing logic (generate QR immediately)
    const orderData = {
        branch_id: selectedBranch.id,
        customer_name: customerName,
        customer_phone: customerPhone,
        payment_method: paymentMethod,
        items: cart
    };
    
    try {
        const response = await fetch(`${API_BASE}/create_order.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();
            showSuccessModal(data.data);
            cart = [];
            updateCartUI();
        } else {
            alert('Order failed: ' + data.message);
        }
    } catch (error) {
        alert('Failed to place order. Please try again.');
    }
}

// NEW: Show mobile money payment section
function showMobileMoneySection(paymentMethod) {
    const checkoutBody = document.querySelector('#checkoutModal .modal-body');
    
    // Check if mobile money section already exists
    if (document.getElementById('mobileMoneySection')) {
        return; // Already showing
    }
    
    // Get merchant code
    const merchantCode = paymentMethod === 'MTN Merchant' ? '6438439' : '884984';
    const provider = paymentMethod === 'MTN Merchant' ? 'MTN' : 'Airtel';
    const bgColor = paymentMethod === 'MTN Merchant' ? '#FFCC00' : '#FF0000';
    
    // Add mobile money fields
    const mobileMoneyHTML = `
        <div id="mobileMoneySection" class="mt-4 p-4 border rounded" style="background: #f8f9fa;">
            <h5 class="mb-3 text-center"><i class="fas fa-mobile-alt"></i> ${provider} Mobile Money Payment</h5>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Follow these steps to complete your order
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">📍 Delivery Location <span class="text-danger">*</span></label>
                <textarea id="deliveryLocation" class="form-control" rows="3" 
                    placeholder="Enter your complete delivery address (e.g., Plot 123, Kampala Road, near City Mall)" required></textarea>
                <small class="text-muted">We'll deliver to this address after payment verification</small>
            </div>
            
            <div class="mb-3 p-3 rounded" style="background: ${bgColor}20; border: 2px solid ${bgColor};">
                <label class="form-label fw-bold" style="color: ${bgColor};">💳 ${provider} Merchant Code</label>
                <div class="input-group">
                    <input type="text" id="merchantCode" class="form-control form-control-lg fw-bold text-center" 
                        value="${merchantCode}" readonly style="font-size: 1.5rem; letter-spacing: 2px;">
                    <button class="btn btn-primary" type="button" onclick="copyMerchantCode()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <small class="text-muted d-block mt-2">
                    <strong>Instructions:</strong><br>
                    1. Open your ${provider} Mobile Money app<br>
                    2. Go to "Pay Merchant"<br>
                    3. Enter the code above: <strong>${merchantCode}</strong><br>
                    4. Enter amount: <strong>UGX ${calculateCartTotal().toLocaleString()}</strong><br>
                    5. Complete the payment
                </small>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">📸 Upload Payment Screenshot <span class="text-danger">*</span></label>
                <input type="file" id="paymentScreenshot" class="form-control" 
                    accept="image/*" required>
                <small class="text-muted">
                    After completing payment, take a screenshot of the confirmation message and upload it here
                </small>
                <div id="screenshotPreview" class="mt-2"></div>
            </div>
            
            <div class="d-grid gap-2">
                <button class="btn btn-success btn-lg" onclick="finishMobileMoneyOrder()">
                    <i class="fas fa-check-circle"></i> Finish & Generate QR Code
                </button>
                <button class="btn btn-secondary" onclick="cancelMobileMoneyPayment()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    `;
    
    checkoutBody.insertAdjacentHTML('beforeend', mobileMoneyHTML);
    
    // Hide the original place order button
    const modalFooter = document.querySelector('#checkoutModal .modal-footer');
    if (modalFooter) modalFooter.style.display = 'none';
    
    // Disable customer info fields
    document.getElementById('customerName').disabled = true;
    document.getElementById('customerPhone').disabled = true;
    document.getElementById('paymentMethod').disabled = true;
    
    // Add preview for screenshot
    document.getElementById('paymentScreenshot').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const preview = document.getElementById('screenshotPreview');
                preview.innerHTML = `
                    <img src="${event.target.result}" class="img-thumbnail" style="max-width: 200px;">
                    <p class="text-success mt-2"><i class="fas fa-check"></i> Screenshot ready to upload</p>
                `;
            };
            reader.readAsDataURL(file);
        }
    });
}

// NEW: Calculate cart total
function calculateCartTotal() {
    return cart.reduce((total, item) => total + (item.unit_price * item.quantity), 0);
}

// NEW: Copy merchant code to clipboard
function copyMerchantCode() {
    const codeInput = document.getElementById('merchantCode');
    codeInput.select();
    codeInput.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        showToast('Merchant code copied! Paste it in your mobile money app', 'success');
    } catch (err) {
        // Fallback for modern browsers
        navigator.clipboard.writeText(codeInput.value).then(() => {
            showToast('Merchant code copied! Paste it in your mobile money app', 'success');
        });
    }
}

// NEW: Cancel mobile money payment
function cancelMobileMoneyPayment() {
    if (confirm('Are you sure you want to cancel? You will need to start over.')) {
        // Remove mobile money section
        const section = document.getElementById('mobileMoneySection');
        if (section) section.remove();
        
        // Re-enable form fields
        document.getElementById('customerName').disabled = false;
        document.getElementById('customerPhone').disabled = false;
        document.getElementById('paymentMethod').disabled = false;
        
        // Show footer again
        const modalFooter = document.querySelector('#checkoutModal .modal-footer');
        if (modalFooter) modalFooter.style.display = '';
    }
}

// NEW: Finish mobile money order (submit with screenshot)
async function finishMobileMoneyOrder() {
    const customerName = document.getElementById('customerName').value.trim();
    const customerPhone = document.getElementById('customerPhone').value.trim();
    const paymentMethod = document.getElementById('paymentMethod').value;
    const deliveryLocation = document.getElementById('deliveryLocation').value.trim();
    const screenshotFile = document.getElementById('paymentScreenshot').files[0];
    
    // Validation
    if (!deliveryLocation) {
        alert('Please enter your delivery location');
        document.getElementById('deliveryLocation').focus();
        return;
    }
    
    if (!screenshotFile) {
        alert('Please upload payment screenshot');
        document.getElementById('paymentScreenshot').focus();
        return;
    }
    
    // Show loading state
    const finishBtn = event.target;
    const originalText = finishBtn.innerHTML;
    finishBtn.disabled = true;
    finishBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    const formData = new FormData();
    formData.append('branch_id', selectedBranch.id);
    formData.append('customer_name', customerName);
    formData.append('customer_phone', customerPhone);
    formData.append('payment_method', paymentMethod);
    formData.append('delivery_location', deliveryLocation);
    formData.append('items', JSON.stringify(cart));
    formData.append('payment_screenshot', screenshotFile);
    
    try {
        const response = await fetch(`${API_BASE}/create_order.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Success! Close checkout modal
            bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();
            
            // Show success modal with QR code
            showSuccessModal(data.data);
            
            // Clear cart
            cart = [];
            updateCartUI();
            
            // Reset checkout form
            document.getElementById('mobileMoneySection')?.remove();
            document.getElementById('customerName').value = '';
            document.getElementById('customerPhone').value = '';
            document.getElementById('customerName').disabled = false;
            document.getElementById('customerPhone').disabled = false;
            document.getElementById('paymentMethod').disabled = false;
            document.querySelector('#checkoutModal .modal-footer').style.display = '';
        } else {
            alert('Order failed: ' + data.message);
            finishBtn.disabled = false;
            finishBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Order error:', error);
        alert('Failed to place order. Please try again.');
        finishBtn.disabled = false;
        finishBtn.innerHTML = originalText;
    }
}

// Show Success Modal
function showSuccessModal(orderData) {
    document.getElementById('orderReference').textContent = orderData.order_reference;
    
    const qrContainer = document.getElementById('qrCodeContainer');
    qrContainer.innerHTML = '';
    new QRCode(qrContainer, {
        text: orderData.qr_code,
        width: 200,
        height: 200
    });
    
    const modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
}

// Show Toast Notification
function showToast(message, type = 'info') {
    // Simple toast implementation
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3`;
    toast.style.zIndex = '9999';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
