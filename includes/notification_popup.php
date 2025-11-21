<?php
include 'db.php'; // Include your database connection

// Check if the popup has already been shown in this session
if (!isset($_SESSION['shown_login_notifications'])) {
    $_SESSION['shown_login_notifications'] = false;
}

// Only show popup if user just logged in (flag not set)
if (!isset($_SESSION['shown_login_notifications']) || $_SESSION['shown_login_notifications'] !== true) {
    
    // Fetch user's notifications
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_branch = $_SESSION['branch_id'] ?? null;
    $user_role = $_SESSION['role'] ?? 'staff';
    $today = date('Y-m-d');
    
    $notifications = [];
    $total_count = 0;
    
    // Fetch shop debtors (overdue) - FIX: Use backticks for column names with hyphens
    $where_shop = ($user_role === 'staff' && $user_branch) 
        ? "WHERE d.`branch_id` = $user_branch AND d.due_date IS NOT NULL AND d.due_date <= '$today' AND d.is_paid = 0"
        : "WHERE d.due_date IS NOT NULL AND d.due_date <= '$today' AND d.is_paid = 0";
    
    $shop_query = $conn->query("
        SELECT d.id, d.debtor_name, d.balance, d.due_date, b.name as branch_name, 'shop_debtor' as type
        FROM debtors d 
        LEFT JOIN branch b ON d.`branch_id` = b.id 
        $where_shop 
        ORDER BY d.due_date ASC 
        LIMIT 5
    ");
    
    if ($shop_query) {
        while ($row = $shop_query->fetch_assoc()) {
            $days_overdue = floor((strtotime($today) - strtotime($row['due_date'])) / 86400);
            $notifications[] = [
                'type' => 'shop_debtor',
                'icon' => 'fa-store',
                'color' => $days_overdue > 7 ? 'danger' : 'warning',
                'title' => htmlspecialchars($row['debtor_name']),
                'message' => 'Shop debt: UGX ' . number_format($row['balance'], 2) . ' - ' . $days_overdue . ' days overdue',
                'branch' => $row['branch_name'] ?? 'Unknown',
                'time' => date('M d, Y', strtotime($row['due_date']))
            ];
            $total_count++;
        }
    }
    
    // Fetch customer debtors (overdue)
    $where_cust = "WHERE ct.status = 'debtor' AND ct.due_date IS NOT NULL AND ct.due_date <= '$today'";
    $cust_query = $conn->query("
        SELECT ct.id, c.name as customer_name, ct.amount_credited, ct.due_date, 'customer_debtor' as type
        FROM customer_transactions ct
        JOIN customers c ON ct.customer_id = c.id
        $where_cust
        ORDER BY ct.due_date ASC
        LIMIT 3
    ");
    
    if ($cust_query) {
        while ($row = $cust_query->fetch_assoc()) {
            $days_overdue = floor((strtotime($today) - strtotime($row['due_date'])) / 86400);
            $notifications[] = [
                'type' => 'customer_debtor',
                'icon' => 'fa-users',
                'color' => $days_overdue > 7 ? 'danger' : 'warning',
                'title' => htmlspecialchars($row['customer_name']),
                'message' => 'Customer debt: UGX ' . number_format($row['amount_credited'], 2) . ' - ' . $days_overdue . ' days overdue',
                'branch' => '',
                'time' => date('M d, Y', strtotime($row['due_date']))
            ];
            $total_count++;
        }
    }
    
    // Fetch low stock products
    $where_stock = ($user_role === 'staff' && $user_branch) 
        ? "WHERE p.`branch-id` = $user_branch AND p.stock < 10"
        : "WHERE p.stock < 10";
    
    $stock_query = $conn->query("
        SELECT p.id, p.name, p.stock, b.name as branch_name, 'low_stock' as type
        FROM products p
        LEFT JOIN branch b ON p.`branch-id` = b.id
        $where_stock
        ORDER BY p.stock ASC
        LIMIT 3
    ");
    
    if ($stock_query) {
        while ($row = $stock_query->fetch_assoc()) {
            $stock = intval($row['stock']);
            $notifications[] = [
                'type' => 'low_stock',
                'icon' => 'fa-box',
                'color' => $stock < 3 ? 'danger' : 'warning',
                'title' => htmlspecialchars($row['name']),
                'message' => 'Low stock: Only ' . $stock . ' items remaining',
                'branch' => $row['branch_name'] ?? 'Unknown',
                'time' => 'Now'
            ];
            $total_count++;
        }
    }
    
    // Get total count for all notifications - FIX: Use backticks for column names
    $count_shop = $conn->query("SELECT COUNT(*) as cnt FROM debtors d $where_shop");
    $count_cust = $conn->query("SELECT COUNT(*) as cnt FROM customer_transactions ct WHERE ct.status = 'debtor' AND ct.due_date IS NOT NULL AND ct.due_date <= '$today'");
    $count_stock = $conn->query("SELECT COUNT(*) as cnt FROM products p $where_stock");
    
    $total_count = 0;
    if ($count_shop) $total_count += intval($count_shop->fetch_assoc()['cnt'] ?? 0);
    if ($count_cust) $total_count += intval($count_cust->fetch_assoc()['cnt'] ?? 0);
    if ($count_stock) $total_count += intval($count_stock->fetch_assoc()['cnt'] ?? 0);
    
    // Only show popup if there are notifications
    if ($total_count > 0):
?>
<!-- Notification Popup Overlay -->
<div id="notificationPopupOverlay" class="notification-popup-overlay">
    <div id="notificationPopup" class="notification-popup">
        <!-- Close Button -->
        <button class="notification-popup-close" id="closeNotificationPopup" aria-label="Close notifications">
            <i class="fa fa-times"></i>
        </button>
        
        <!-- Header -->
        <div class="notification-popup-header">
            <div class="notification-popup-icon">
                <i class="fa fa-bell"></i>
            </div>
            <h2 class="notification-popup-title">
                You have <span class="notification-count-animate" data-count="<?= min($total_count, 99) ?>">0</span> new notifications
            </h2>
            <p class="notification-popup-subtitle">Stay updated with your business activities</p>
        </div>
        
        <!-- Notifications List -->
        <div class="notification-popup-list">
            <?php 
            $displayed = 0;
            foreach (array_slice($notifications, 0, 7) as $index => $notif): 
                $displayed++;
            ?>
                <div class="notification-popup-item" style="animation-delay: <?= ($index * 0.08) ?>s;" data-type="<?= $notif['type'] ?>">
                    <div class="notification-popup-item-icon notification-icon-<?= $notif['color'] ?>">
                        <i class="fa <?= $notif['icon'] ?>"></i>
                    </div>
                    <div class="notification-popup-item-content">
                        <h4 class="notification-popup-item-title"><?= $notif['title'] ?></h4>
                        <p class="notification-popup-item-message"><?= $notif['message'] ?></p>
                        <?php if (!empty($notif['branch'])): ?>
                            <span class="notification-popup-item-branch"><?= htmlspecialchars($notif['branch']) ?></span>
                        <?php endif; ?>
                        <span class="notification-popup-item-time"><?= $notif['time'] ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Footer -->
        <div class="notification-popup-footer">
            <?php if ($total_count > 7): ?>
                <a href="../pages/notification.php" class="notification-popup-view-more">
                    View All <?= $total_count ?> Notifications <i class="fa fa-arrow-right"></i>
                </a>
            <?php endif; ?>
            <button class="notification-popup-dismiss" id="dismissNotificationPopup">
                Got it, thanks!
            </button>
        </div>
    </div>
</div>

<!-- Notification Popup Styles -->
<style>
/* Overlay */
.notification-popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(44, 62, 80, 0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    animation: notificationOverlayFadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    padding: 1rem;
}

@keyframes notificationOverlayFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Popup Container */
.notification-popup {
    background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.98) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.5) inset;
    max-width: 560px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    position: relative;
    transform: scale(0.8) translateY(40px);
    opacity: 0;
    animation: notificationPopupAppear 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s forwards;
}

@keyframes notificationPopupAppear {
    from {
        transform: scale(0.8) translateY(40px);
        opacity: 0;
    }
    to {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}

/* Dark Mode Support */
body.dark-mode .notification-popup {
    background: linear-gradient(135deg, rgba(35,36,58,0.95) 0%, rgba(30,30,47,0.98) 100%);
    box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.1) inset;
}

/* Close Button */
.notification-popup-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(44,62,80,0.08);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2c3e50;
    font-size: 1.1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10;
}

.notification-popup-close:hover {
    background: rgba(231,76,60,0.15);
    color: #e74c3c;
    transform: rotate(90deg);
}

body.dark-mode .notification-popup-close {
    background: rgba(255,255,255,0.08);
    color: #f4f4f4;
}

/* Header */
.notification-popup-header {
    padding: 2rem 2rem 1.5rem;
    text-align: center;
    background: linear-gradient(135deg, rgba(26,188,156,0.08) 0%, rgba(86,204,242,0.08) 100%);
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

body.dark-mode .notification-popup-header {
    background: linear-gradient(135deg, rgba(26,188,156,0.12) 0%, rgba(86,204,242,0.12) 100%);
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.notification-popup-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, #1abc9c 0%, #56ccf2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.8rem;
    box-shadow: 0 8px 24px rgba(26,188,156,0.3);
    animation: notificationIconPulse 2s ease-in-out infinite;
}

@keyframes notificationIconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 8px 24px rgba(26,188,156,0.3); }
    50% { transform: scale(1.05); box-shadow: 0 12px 32px rgba(26,188,156,0.4); }
}

.notification-popup-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 0.5rem;
    letter-spacing: -0.5px;
}

body.dark-mode .notification-popup-title {
    color: #f4f4f4;
}

.notification-count-animate {
    display: inline-block;
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: #fff;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
    font-size: 1.3rem;
    font-weight: 700;
    min-width: 40px;
    box-shadow: 0 4px 12px rgba(231,76,60,0.3);
    animation: notificationCountReveal 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.5s backwards;
}

@keyframes notificationCountReveal {
    from {
        transform: scale(0) rotate(-180deg);
        opacity: 0;
    }
    to {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}

.notification-popup-subtitle {
    font-size: 0.9rem;
    color: #7f8c8d;
    margin: 0;
}

body.dark-mode .notification-popup-subtitle {
    color: #95a5a6;
}

/* Notifications List */
.notification-popup-list {
    padding: 1rem;
    max-height: 400px;
    overflow-y: auto;
    overflow-x: hidden;
}

.notification-popup-list::-webkit-scrollbar {
    width: 6px;
}

.notification-popup-list::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.05);
    border-radius: 10px;
}

.notification-popup-list::-webkit-scrollbar-thumb {
    background: rgba(26,188,156,0.3);
    border-radius: 10px;
}

.notification-popup-list::-webkit-scrollbar-thumb:hover {
    background: rgba(26,188,156,0.5);
}

/* Notification Item */
.notification-popup-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255,255,255,0.6);
    border-radius: 16px;
    margin-bottom: 0.75rem;
    border: 1px solid rgba(0,0,0,0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    transform-origin: top;
    opacity: 0;
    transform: scaleY(0) translateY(-20px);
    animation: notificationItemUnfold 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes notificationItemUnfold {
    0% {
        opacity: 0;
        transform: scaleY(0) translateY(-20px);
    }
    50% {
        opacity: 0.5;
        transform: scaleY(0.5) translateY(-10px);
    }
    100% {
        opacity: 1;
        transform: scaleY(1) translateY(0);
    }
}

.notification-popup-item:hover {
    background: rgba(26,188,156,0.08);
    border-color: rgba(26,188,156,0.2);
    transform: translateX(4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}

body.dark-mode .notification-popup-item {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.08);
}

body.dark-mode .notification-popup-item:hover {
    background: rgba(26,188,156,0.12);
    border-color: rgba(26,188,156,0.3);
}

.notification-popup-item-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.notification-icon-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: #fff;
}

.notification-icon-warning {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: #fff;
}

.notification-icon-info {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: #fff;
}

.notification-popup-item-content {
    flex: 1;
}

.notification-popup-item-title {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 0.3rem;
}

body.dark-mode .notification-popup-item-title {
    color: #f4f4f4;
}

.notification-popup-item-message {
    font-size: 0.875rem;
    color: #7f8c8d;
    margin: 0 0 0.5rem;
    line-height: 1.4;
}

body.dark-mode .notification-popup-item-message {
    color: #95a5a6;
}

.notification-popup-item-branch {
    display: inline-block;
    background: rgba(26,188,156,0.15);
    color: #1abc9c;
    padding: 0.2rem 0.6rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-right: 0.5rem;
}

.notification-popup-item-time {
    font-size: 0.75rem;
    color: #95a5a6;
}

/* Footer */
.notification-popup-footer {
    padding: 1rem 2rem 1.5rem;
    border-top: 1px solid rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: rgba(0,0,0,0.02);
}

body.dark-mode .notification-popup-footer {
    border-top: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.02);
}

.notification-popup-view-more {
    display: block;
    text-align: center;
    color: #1abc9c;
    font-weight: 600;
    text-decoration: none;
    padding: 0.75rem;
    border-radius: 12px;
    background: rgba(26,188,156,0.08);
    transition: all 0.3s ease;
}

.notification-popup-view-more:hover {
    background: rgba(26,188,156,0.15);
    transform: translateX(4px);
}

.notification-popup-dismiss {
    width: 100%;
    padding: 0.875rem;
    background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(26,188,156,0.3);
}

.notification-popup-dismiss:hover {
    background: linear-gradient(135deg, #16a085 0%, #1abc9c 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26,188,156,0.4);
}

/* Responsive */
@media (max-width: 768px) {
    .notification-popup {
        max-width: 95%;
        border-radius: 20px;
    }
    
    .notification-popup-header {
        padding: 1.5rem 1rem 1rem;
    }
    
    .notification-popup-title {
        font-size: 1.25rem;
    }
    
    .notification-popup-list {
        max-height: 300px;
        padding: 0.75rem;
    }
    
    .notification-popup-item {
        padding: 0.875rem;
        gap: 0.75rem;
    }
    
    .notification-popup-item-icon {
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
    }
}
</style>

<!-- Notification Popup Script -->
<script>
(function() {
    // Animated counter
    function animateCount() {
        const countEl = document.querySelector('.notification-count-animate');
        if (!countEl) return;
        
        const target = parseInt(countEl.getAttribute('data-count') || 0);
        let current = 0;
        const duration = 800;
        const increment = target / (duration / 16);
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                countEl.textContent = target;
                clearInterval(timer);
            } else {
                countEl.textContent = Math.floor(current);
            }
        }, 16);
    }
    
    // Show popup after short delay
    setTimeout(() => {
        animateCount();
    }, 600);
    
    // Close handlers
    function closePopup() {
        const overlay = document.getElementById('notificationPopupOverlay');
        const popup = document.getElementById('notificationPopup');
        
        if (!overlay || !popup) return;
        
        // Animate out
        popup.style.animation = 'none';
        popup.style.transform = 'scale(0.9) translateY(20px)';
        popup.style.opacity = '0';
        popup.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.3s ease';
        
        setTimeout(() => {
            overlay.remove();
            document.body.style.overflow = '';
            
            // Mark as shown via AJAX
            fetch('<?= $_SERVER['PHP_SELF'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_notifications_shown=1'
            });
        }, 300);
    }
    
    // Event listeners
    document.getElementById('closeNotificationPopup')?.addEventListener('click', closePopup);
    document.getElementById('dismissNotificationPopup')?.addEventListener('click', closePopup);
    
    // Click outside to close
    document.getElementById('notificationPopupOverlay')?.addEventListener('click', function(e) {
        if (e.target.id === 'notificationPopupOverlay') {
            closePopup();
        }
    });
    
    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePopup();
        }
    });
    
    // Lock body scroll
    document.body.style.overflow = 'hidden';
})();
</script>
<?php
    endif; // end if total_count > 0
} // end if not shown
?>
