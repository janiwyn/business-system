 <?php

// Admin phone number
// $admin_phone = "0781710027"; //  number

// Message to send
// $message_text = "Hello😊"; // message

// Prepare payload as a PHP array
// $sms_payload = [
//     "user_id" => 1,
//     "batch_name" => "Aliesms Tests Renewed",
//     "message_text" => $message_text,
//     "recipients" => $admin_phone
// ];


// Initialize cURL
// $ch = curl_init('https://aliesmsapi.araknerd.com/message/batch/create');
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_POST, true);
// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sms_payload));
// curl_setopt($ch, CURLOPT_HTTPHEADER, [
//     'Content-Type: application/json',
//     'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJkYXRhIjp7ImlhdCI6MTc0MzQyMjY4NCwiZXhwIjoxNzQzNDIyNzQ0LCJ1c2VyX2lkIjoiMiIsInJvbGVfaWQiOiIzIiwidXNlcm5hbWUiOiJzdXBwb3J0QGFyYWtuZXJkLmNvbSJ9fQ.kyoC0aMICw1SIwFoHX0qvPtvk9YGw3QYv4kodYD3csw'
// ]);

// Execute the request
// $response = curl_exec($ch);
// if(curl_errno($ch)){
//     echo 'SMS Error: ' . curl_error($ch);
// } else {
//     echo "SMS sent successfully! Response: $response";
// }
// curl_close($ch);
?> <?php

function sendExpirySMS($productName, $expiryDate) {
    $apiUrl = "https://aliesmsapi.araknerd.com/message/batch/create";
    $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJkYXRhIjp7ImlhdCI6MTc0MzQyMjY4NCwiZXhwIjoxNzQzNDIyNzQ0LCJ1c2VyX2lkIjoiMiIsInJvbGVfaWQiOiIzIiwidXNlcm5hbWUiOiJzdXBwb3J0QGFyYWtuZXJkLmNvbSJ9fQ.kyoC0aMICw1SIwFoHX0qvPtvk9YGw3QYv4kodYD3csw";
 

    $message = "ALIESMS Alert: The product '$productName' will expire on $expiryDate. Please remove it from stock. Thank you!";

    $payload = [
        "user_id" => 1,
        "batch_name" => "Expiry Date Alert",
        "message_text" => $message,
        "receipients" => "0781710027, 0771827046"
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log("SMS Error: " . curl_error($ch));
    } else {
        error_log("SMS sent successfully for $productName. HTTP code: $httpCode");
    }
}
?>
