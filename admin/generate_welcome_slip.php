<?php die("ACTIVE FILE: " . __FILE__); ?>


<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

/* Fetch user */
$stmt = $pdo->prepare("SELECT full_name, email, role FROM users WHERE user_id = ? AND is_admin = 0");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Invalid user.");
}

/* Ensure folders exist */
$uploadDir = __DIR__ . "/uploads";
$qrDir     = __DIR__ . "/qr";

if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
if (!is_dir($qrDir)) { mkdir($qrDir, 0777, true); }

/* Generate verification token */
$token = bin2hex(random_bytes(16));

/* Save token */
$stmt = $pdo->prepare("INSERT INTO slip_verifications (user_id, token) VALUES (?, ?)");
$stmt->execute([$user_id, $token]);

/* Generate QR */
$verifyUrl = "http://localhost/Nexus-Banksystem/verify-slip.php?token=" . $token;
$qrPath = $qrDir . "/qr_" . $token . ".png";

$qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($verifyUrl);
$qrImage = file_get_contents($qrApiUrl);
file_put_contents($qrPath, $qrImage);

/* Create PDF */
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
<tr><td><b>Issued On:</b></td><td>" . date('d M Y') . "</td></tr>
</table>

<br><b>Scan QR to verify this document</b><br><br>
<img src='{$qrPath}' width='150'>
";

$pdf->writeHTML($html, true, false, true, false, '');

$path = $uploadDir . "/welcome_" . $user_id . ".pdf";
$pdf->Output($path, 'F');

/* Send Email with attachment */
$subject = "Welcome to Nexus Bank";
$body = "
<h2>Welcome {$user['full_name']}</h2>
<p>Your official welcome document is attached.</p>
<p>Please keep it for future verification.</p>
";

sendNotification($user['email'], $subject, $body, '', $path);

echo "Welcome slip created, QR generated and email sent successfully.";
