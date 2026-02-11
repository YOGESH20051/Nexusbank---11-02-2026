<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Dompdf\Dompdf;

$user_id = $_GET['user_id'] ?? null;
if (!$user_id) die("User missing");

// Fetch user & account
$stmt = $pdo->prepare("
    SELECT u.full_name, u.email, a.account_number, a.balance
    FROM users u
    JOIN accounts a ON u.user_id = a.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) die("User not found");

// 1️⃣ Create verification token
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+30 days'));

$pdo->prepare("
    INSERT INTO slip_verifications (user_id, token, expires_at)
    VALUES (?, ?, ?)
")->execute([$user_id, $token, $expires]);

$verifyUrl = "http://localhost/Nexus-Banksystem/verify-slip.php?token=$token";

// 2️⃣ Generate QR
$qrPath = __DIR__ . "/../uploads/qr_$token.png";
Builder::create()
    ->writer(new PngWriter())
    ->data($verifyUrl)
    ->size(220)
    ->build()
    ->saveToFile($qrPath);

// 3️⃣ Generate Cyber PDF
$dompdf = new Dompdf();

$html = "
<style>
body { background:#020617; color:#e5e7eb; font-family:Segoe UI }
.card { padding:40px; border-radius:16px; box-shadow:0 0 30px rgba(56,189,248,.6) }
h1 { color:#38bdf8 }
.badge { color:#22c55e }
</style>

<div class='card'>
<h1>NEXUS BANK</h1>
<p class='badge'>Official Welcome Slip</p>
<p><b>Name:</b> {$user['full_name']}</p>
<p><b>Email:</b> {$user['email']}</p>
<p><b>Account:</b> {$user['account_number']}</p>
<p><b>Balance:</b> ₹" . number_format($user['balance'],2) . "</p>
<p><b>Issued:</b> ".date('d M Y')."</p>
<img src='$qrPath' width='140'>
<p>Scan QR to verify authenticity</p>
</div>
";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

$pdfPath = __DIR__ . "/../uploads/welcome_$user_id.pdf";
file_put_contents($pdfPath, $dompdf->output());

// 4️⃣ Send email
require_once __DIR__ . '/../includes/send_welcome_email.php';
sendWelcomeEmail($user['email'], $user['full_name'], $pdfPath);

echo "Welcome system completed successfully.";
