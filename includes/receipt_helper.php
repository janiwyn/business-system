<?php
/**
 * Generate next sequential receipt number
 * @param mysqli $conn Database connection
 * @param string $prefix Receipt prefix (default: 'RP')
 * @return string Receipt number (e.g., 'RP-00001')
 */
function generateReceiptNumber($conn, $prefix = 'RP') {
    // Start transaction to prevent race conditions
    $conn->begin_transaction();
    
    try {
        // Lock the counter row for update
        $stmt = $conn->prepare("SELECT last_number FROM receipt_counter WHERE prefix = ? FOR UPDATE");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get next number
        $next_number = ($result['last_number'] ?? 0) + 1;
        
        // Update counter
        $stmt = $conn->prepare("UPDATE receipt_counter SET last_number = ? WHERE prefix = ?");
        $stmt->bind_param("is", $next_number, $prefix);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // Format: RP-00001 (5 digits with leading zeros)
        return $prefix . '-' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        $conn->rollback();
        // Fallback to timestamp-based if counter fails
        error_log("Receipt number generation failed: " . $e->getMessage());
        return $prefix . '-' . date('YmdHis');
    }
}
?>
