<?php
require_once '../includes/db.php';

$settings = mysqli_query($conn, "SELECT * FROM company_settings LIMIT 1")->fetch_assoc();
$companyName = $settings['company_name'] ?? 'Our Business';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - <?= htmlspecialchars($companyName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-search me-2"></i>Track Your Order</h5>
                    </div>
                    <div class="card-body">
                        <form id="trackForm">
                            <div class="mb-3">
                                <label class="form-label">Order Reference Number</label>
                                <input type="text" class="form-control" id="orderRef" 
                                       placeholder="e.g., ORD-2025-123456" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Track Order
                            </button>
                        </form>
                        
                        <div id="orderStatus" class="mt-4 d-none"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('trackForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const orderRef = document.getElementById('orderRef').value.trim();
            const statusDiv = document.getElementById('orderStatus');
            
            statusDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div></div>';
            statusDiv.classList.remove('d-none');
            
            try {
                const response = await fetch(`../api/get_order_status.php?order_reference=${orderRef}`);
                const data = await response.json();
                
                if (data.success) {
                    const order = data.data;
                    let statusHTML = `
                        <div class="alert alert-info">
                            <h6>Order: ${order.order_reference}</h6>
                            <p><strong>Customer:</strong> ${order.customer_name}</p>
                            <p><strong>Amount:</strong> UGX ${Number(order.expected_amount).toLocaleString()}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${order.status === 'pending' ? 'warning' : order.status === 'finished' ? 'success' : 'danger'}">${order.status.toUpperCase()}</span></p>
                        </div>
                        
                        <div class="status-timeline">
                            <div class="status-step ${order.status !== 'cancelled' ? 'completed' : ''}">
                                <div class="status-step-icon"><i class="fas fa-check"></i></div>
                                <small>Placed</small>
                            </div>
                            <div class="status-step ${order.status === 'finished' ? 'completed' : order.status === 'pending' ? 'active' : ''}">
                                <div class="status-step-icon"><i class="fas fa-clock"></i></div>
                                <small>Processing</small>
                            </div>
                            <div class="status-step ${order.status === 'finished' ? 'completed' : ''}">
                                <div class="status-step-icon"><i class="fas fa-check-circle"></i></div>
                                <small>Completed</small>
                            </div>
                        </div>
                    `;
                    
                    if (order.items && order.items.length > 0) {
                        statusHTML += '<h6 class="mt-3">Items:</h6><ul class="list-group">';
                        order.items.forEach(item => {
                            statusHTML += `<li class="list-group-item d-flex justify-content-between">
                                <span>${item.product_name} x ${item.quantity}</span>
                                <strong>UGX ${Number(item.subtotal).toLocaleString()}</strong>
                            </li>`;
                        });
                        statusHTML += '</ul>';
                    }
                    
                    statusDiv.innerHTML = statusHTML;
                } else {
                    statusDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            } catch (error) {
                statusDiv.innerHTML = '<div class="alert alert-danger">Failed to track order</div>';
            }
        });
    </script>
</body>
</html>
