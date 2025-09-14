<?php
include '../includes/db.php';

// PHPMailer autoload first
require __DIR__ . '/../vendor/autoload.php'; // adjust path if needed

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$alert_days = 7; // days before expiry to alert
$today = date('Y-m-d');
$alert_date = date('Y-m-d', strtotime("+$alert_days days"));

// Get products that are about to expire
$query = "SELECT * FROM products WHERE expiry_date BETWEEN '$today' AND '$alert_date'";
$result = mysqli_query($conn, $query);

$expiring_products = [];
if(mysqli_num_rows($result) > 0){
    while($row = mysqli_fetch_assoc($result)){
        $expiring_products[] = $row;
    }
}

$admin_email = "admin@example.com"; // replace with actual admin email

if(!empty($expiring_products)){
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com'; // your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'janefrancescanamutebi@gmail.com';
        $mail->Password = '12345';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('your_email@example.com', 'Inventory System');
        $mail->addAddress($admin_email);

        $mail->isHTML(true);
        $mail->Subject = 'Product Expiry Alert';

        $body = "<h3>Products About to Expire:</h3><ul>";
        foreach($expiring_products as $prod){
            $body .= "<li><b>{$prod['name']}</b> - Expiry Date: {$prod['expiry_date']}</li>";
        }
        $body .= "</ul>";

        $mail->Body = $body;
        $mail->send();
        // echo "Expiry alert sent to admin!";
    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    }
}
?>
