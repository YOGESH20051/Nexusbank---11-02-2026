<?php

// PHPMailer namespaces
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader (very important)
require_once __DIR__ . '/../vendor/autoload.php';

function sendWelcomeEmail($email, $name, $pdfPath) {

    $mail = new PHPMailer(true);

    try {
         // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'y.panhandler@gmail.com'; // Your Gmail
        $mail->Password   = 'zywczomponbfokzn'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('y.panhandler@gmail.com', 'Nexus Bank Notifications');
        $mail->addAddress($recipientEmail);
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Nexus Bank';
        $mail->Body    = "
            <h2>Welcome to Nexus Bank</h2>
            <p>Hello <b>$name</b>,</p>
            <p>Your official welcome document is attached.</p>
            <p>Thank you for choosing Nexus Bank.</p>
        ";

        // Attach the PDF
        $mail->addAttachment($pdfPath);

        $mail->send();
    } 
    catch (Exception $e) {
        error_log("Email send failed: {$mail->ErrorInfo}");
    }
}
