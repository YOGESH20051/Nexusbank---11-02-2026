<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

$user_id = intval($_GET['user_id'] ?? 0);
if (!$user_id) die("User missing");

// Only normal users
$stmt = $pdo->prepare("SELECT full_name, email, role FROM users WHERE user_id=? AND is_admin=0");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) die("Invalid user");

// Create folders
$uploadDir = __DIR__ . "/uploads";
$qrDir     = $uploadDir . "/qr";

if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);

// Create verification token
$token = bin2hex(random_bytes(32));
$verifyUrl = "http://localhost/Nexus-Banksystem/verify-slip.php?token=$token";

$pdo->prepare("INSERT INTO slip_verifications (user_id, token, expires_at)
               VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))")
    ->execute([$user_id, $token]);

// Generate QR
$qrPath = "$qrDir/qr_$token.png";

Builder::create()
    ->writer(new PngWriter())
    ->data($verifyUrl)
    ->size(300)
    ->build()
    ->saveToFile($qrPath);

// Build PDF
$pdf = new TCPDF();
$pdf->AddPage();

$html = "
<h1 style='color:#0ea5e9;'>NEXUS BANK</h1>
<hr>
<h3>Welcome {$user['full_name']}</h3>

<p>Your Nexus Bank account has been successfully activated.</p>

<table cellpadding='6'>
<tr><td><b>Email:</b></td><td>{$user['email']}</td></tr>
<tr><td><b>Role:</b></td><td>{$user['role']}</td></tr>
<tr><td><b>Issued On:</b></td><td>".date('d M Y')."</td></tr>
</table>

<br><h3>Scan QR to verify this document</h3>
<img src='$qrPath' width='140'>
";

$pdf->writeHTML($html, true, false, true, false, '');

$pdfPath = "$uploadDir/welcome_$user_id.pdf";
$pdf->Output($pdfPath, 'F');

// Send Email with PDF attached
$body = "
<h2>Welcome to Nexus Bank</h2>
<p>Hello <b>{$user['full_name']}</b>,</p>
<p>Your official welcome document is attached.</p>
<p>Please keep it for future verification.</p>
";

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'y.panhandler@gmail.com';
$mail->Password = 'zywczomponbfokzn';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;

$mail->setFrom('y.panhandler@gmail.com', 'Nexus Bank');
$mail->addAddress($user['email']);
$mail->Subject = "Welcome to Nexus Bank";
$mail->Body = $body;
$mail->isHTML(true);
$mail->addAttachment($pdfPath);

$mail->send();

echo "Welcome slip created, QR generated and email sent successfully.";
