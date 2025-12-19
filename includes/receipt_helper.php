<?php
/**
 * Generate next sequential receipt number
 * @param mysqli $conn Database connection
 * @param string $prefix Receipt prefix ('RP' for receipts, 'INV' for invoices)
 * @return string Receipt number (e.g., 'RP-00001' or 'INV-00001')
 */
function generateReceiptNumber($conn, $prefix = 'RP') {
    // FIXED: Support both RP and INV prefixes with separate counters
    // Validate prefix
    if (!in_array($prefix, ['RP', 'INV'])) {
        $prefix = 'RP'; // Default to RP if invalid
    }
    
    // Create table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS `receipt_counter` (
        `prefix` VARCHAR(10) NOT NULL PRIMARY KEY,
        `last_number` INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
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
        
        // If no row exists for this prefix, insert it
        if (!$result) {
            $stmt = $conn->prepare("INSERT INTO receipt_counter (prefix, last_number) VALUES (?, ?)");
            $stmt->bind_param("si", $prefix, $next_number);
            $stmt->execute();
            $stmt->close();
        } else {
            // Update counter
            $stmt = $conn->prepare("UPDATE receipt_counter SET last_number = ? WHERE prefix = ?");
            $stmt->bind_param("is", $next_number, $prefix);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        
        // Format: RP-00001 or INV-00001 (5 digits with leading zeros)
        return $prefix . '-' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        $conn->rollback();
        // Fallback to timestamp-based if counter fails
        error_log("Receipt number generation failed: " . $e->getMessage());
        return $prefix . '-' . date('YmdHis');
    }
}
?>
