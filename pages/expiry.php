<?php
include '../includes/db.php';

// Expiry alert settings
$alert_days = 7; // days before expiry
$today = date('Y-m-d');
$alert_date = date('Y-m-d', strtotime("+$alert_days days"));

// Get products about to expire
$query = "SELECT * FROM products WHERE expiry_date BETWEEN '$today' AND '$alert_date'";
$result = mysqli_query($conn, $query);

$expiring_products = [];
if(mysqli_num_rows($result) > 0){
    while($row = mysqli_fetch_assoc($result)){
        $expiring_products[] = $row;
    }
}
echo "<pre>";
print_r($expiring_products);
echo "</pre>";

// Admin/Manager phone number
$admin_phone = "0781710027"; // number

if(!empty($expiring_products)){
    // Create message text
    $message_text = "Products about to expire:\n";
    foreach($expiring_products as $prod){
        $message_text .= "{$prod['name']} - {$prod['expiry_date']}\n";
    }

    // Prepare payload for Aliesms API
    $sms_payload = [
        "user_id" => 1,
        "batch_name" => "Expiry Alerts",
        "message_text" => $message_text,
        "receipients" => $admin_phone
    ];
 


    // Send SMS via cURL
    $ch = curl_init('https://aliesmsapi.araknerd.com/message/batch/create');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sms_payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJkYXRhIjp7ImlhdCI6MTc0MzQyMjY4NCwiZXhwIjoxNzQzNDIyNzQ0LCJ1c2VyX2lkIjoiMiIsInJvbGVfaWQiOiIzIiwidXNlcm5hbWUiOiJzdXBwb3J0QGFyYWtuZXJkLmNvbSJ9fQ.kyoC0aMICw1SIwFoHX0qvPtvk9YGw3QYv4kodYD3csw'
    ]);

    $response = curl_exec($ch);
if(curl_errno($ch)){
    echo 'SMS Error: ' . curl_error($ch);
} else {
    $resp_data = json_decode($response, true);
    if(isset($resp_data['status']) && $resp_data['status'] == 'success'){
        echo "SMS notification sent successfully!";
    } else {
        echo "SMS API responded with an error: " . $response;
    }
}
curl_close($ch);
}
?>
