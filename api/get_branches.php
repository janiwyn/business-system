<?php
require_once '../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Simply get ALL branches from the system - no business_id filter needed
    $stmt = mysqli_prepare($conn, "SELECT id, name, location FROM branch ORDER BY name ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $branches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Branches fetched successfully',
        'data' => $branches
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => null
    ]);
}
?>
