<?php
// Auto-cleanup script for old cancelled orders
// Run this file via cron job or include it in frequently accessed pages

// Use existing $conn variable from parent file (don't create new connection)
if (isset($conn) && $conn) {
    // Delete cancelled orders older than 24 hours
    $cleanup_query = "DELETE FROM remote_orders 
                      WHERE status = 'cancelled' 
                      AND cancelled_at IS NOT NULL 
                      AND cancelled_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    @$conn->query($cleanup_query); // Suppress errors (cleanup is optional)
}
?>
