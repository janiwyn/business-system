<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$stmt = mysqli_query($conn, "SELECT company_name, logo, phone, email, address FROM company_settings LIMIT 1");

if ($row = mysqli_fetch_assoc($stmt)) {
    jsonResponse(true, 'Company info fetched', $row);
} else {
    jsonResponse(false, 'Company info not found', null, 404);
}

closeDBConnection($conn);
?>
