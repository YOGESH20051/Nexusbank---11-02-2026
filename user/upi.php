<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/notification.php";
require_once "../vendor/autoload.php";

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

$sender_id = $_SESSION['user_id'];

/* ==============================
   GET LOGGED USER DETAILS
============================== */
$stmt = $pdo->prepare("
    SELECT u.user_id, u.upi_id, u.full_name, u.email, a.balance
    FROM users u
    JOIN accounts a ON u.user_id = a.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$sender_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$my_upi   = $user['upi_id'];
$balance  = $user['balance'];
$sender_email = $user['email'];

/* ==============================
   GENERATE QR CODE
============================== */
$writer = new PngWriter();
$qrCode = QrCode::create($my_upi)
    ->setSize(200)
    ->setMargin(10);

$result = $writer->write($qrCode);
$qrDataUri = $result->getDataUri();

/* ==============================
   HANDLE TRANSFER
============================== */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $receiver_upi = trim($_POST['receiver_upi']);
    $amount = floatval($_POST['amount']);
    $entered_pin = trim($_POST['upi_pin']);

    if ($amount <= 0) {
        echo "<script>alert('Invalid amount.');</script>";
    } elseif (empty($entered_pin)) {
        echo "<script>alert('Please enter UPI PIN.');</script>";
    } else {

        try {

            /* ==============================
               VERIFY UPI PIN
            =============================== */

            $stmt = $pdo->prepare("SELECT upi_pin FROM users WHERE user_id = ?");
            $stmt->execute([$sender_id]);
            $pinData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pinData) {
                throw new Exception("Unable to verify UPI PIN.");
            }

            $stored_pin = $pinData['upi_pin'];

            // If hashed PIN
            if (!password_verify($entered_pin, $stored_pin)) {
                throw new Exception("Invalid UPI PIN.");
            }

            /* ==============================
               CHECK BALANCE
            =============================== */

            if ($balance < $amount) {
                throw new Exception("Insufficient balance.");
            }

            /* ==============================
               GET RECEIVER
            =============================== */

            $stmt = $pdo->prepare("
                SELECT user_id, email, upi_id
                FROM users
                WHERE upi_id = ?
            ");
            $stmt->execute([$receiver_upi]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$receiver) {
                throw new Exception("Receiver UPI not found.");
            }

            if ($receiver['user_id'] == $sender_id) {
                throw new Exception("Cannot send to your own UPI.");
            }

            /* ==============================
               START TRANSACTION
            =============================== */

            $pdo->beginTransaction();

            // Deduct sender
            $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE user_id = ?");
            $stmt->execute([$amount, $sender_id]);

            // Add receiver
            $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE user_id = ?");
            $stmt->execute([$amount, $receiver['user_id']]);

            // Insert transaction
            $stmt = $pdo->prepare("
                INSERT INTO upi_transactions (sender_id, receiver_id, amount, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$sender_id, $receiver['user_id'], $amount]);

            $pdo->commit();


            /* ==============================
   INSERT IN-APP NOTIFICATIONS
============================== */

// Sender notification
$stmt = $pdo->prepare("
    INSERT INTO user_notifications (user_id, title, message, created_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([
    $sender_id,
    "UPI Payment Sent",
    "You sent ₹$amount to {$receiver['upi_id']} successfully."
]);

// Receiver notification
$stmt = $pdo->prepare("
    INSERT INTO user_notifications (user_id, title, message, created_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([
    $receiver['user_id'],
    "UPI Payment Received",
    "You received ₹$amount from $my_upi."
]);


            /* ==============================
               SEND EMAIL NOTIFICATIONS
            =============================== */

            sendNotification(
                $receiver['email'],
                "UPI Payment Received - Nexus Bank",
                "<h3>Payment Received</h3>
                 <p>You received <b>₹$amount</b> from <b>$my_upi</b>.</p>
                 <p>Nexus Bank</p>"
            );

            sendNotification(
                $sender_email,
                "UPI Payment Sent - Nexus Bank",
                "<h3>Payment Successful</h3>
                 <p>You sent <b>₹$amount</b> to <b>{$receiver['upi_id']}</b>.</p>
                 <p>Nexus Bank</p>"
            );

            echo "<script>alert('Payment Successful'); window.location='upi.php';</script>";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            echo "<script>alert('".$e->getMessage()."');</script>";
        }
    }
}

?>


<!DOCTYPE html>
<html>
<head>
<title>Nexus Bank | UPI Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}

body {
    background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
    min-height: 100vh;
    padding: 40px 20px;
    color: #fff;
}

.dashboard {
    max-width: 1100px;
    margin: auto;
}

.header {
    margin-bottom: 30px;
}

.header h1 {
    font-size: 28px;
    font-weight: 600;
}

.header p {
    color: #ccc;
    font-size: 14px;
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.card {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-5px);
}

.card h2 {
    margin-bottom: 15px;
    font-size: 18px;
    font-weight: 500;
}

.balance {
    font-size: 30px;
    font-weight: 600;
    color: #00e676;
}

.upi-id {
    margin-top: 10px;
    font-size: 16px;
    color: #ddd;
}

.qr-box {
    text-align: center;
}

.qr-box img {
    margin-top: 15px;
    border-radius: 12px;
    background: #fff;
    padding: 10px;
}

.form-group {
    margin-bottom: 15px;
}

input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: none;
    outline: none;
    background: rgba(255,255,255,0.1);
    color: white;
    font-size: 14px;
}

input::placeholder {
    color: #ccc;
}

button {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(45deg, #00c6ff, #0072ff);
    color: white;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    opacity: 0.85;
}

.footer-note {
    margin-top: 10px;
    font-size: 12px;
    color: #bbb;
}

/* Responsive */
@media (max-width: 900px) {
    .grid {
        grid-template-columns: 1fr;
    }
}


.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.back-btn {
    padding: 8px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    background: rgba(255,255,255,0.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
    transition: 0.3s ease;
}

.back-btn:hover {
    background: #00c6ff;
    color: #000;
    border-color: #00c6ff;
}

</style>
</head>

<body>

<div class="dashboard">

    <div class="top-bar">
    <div class="header">
        <h1>UPI Dashboard</h1>
        <p>Secure. Instant. Seamless Payments.</p>
    </div>

    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
</div>


    <div class="grid">

        <!-- Account Overview -->
        <div class="card">
            <h2>Account Overview</h2>
            <div class="balance">₹<?= number_format($balance,2) ?></div>
            <div class="upi-id">UPI ID: <?= htmlspecialchars($my_upi) ?></div>
        </div>

        <!-- QR Code -->
        <div class="card qr-box">
            <h2>Scan & Pay</h2>
            <img src="<?= $qrDataUri ?>" width="180">
            <div class="footer-note">Share this QR to receive payments</div>
        </div>

        <!-- Transfer Section -->
        <div class="card" style="grid-column: span 2;">
            <h2>Send Money via UPI</h2>
            <form method="POST">
    <div class="form-group">
        <input type="text" name="receiver_upi" placeholder="Enter Receiver UPI ID" required>
    </div>

    <div class="form-group">
        <input type="number" name="amount" step="0.01" placeholder="Enter Amount (₹)" required>
    </div>

    <div class="form-group">
        <input type="password" name="upi_pin" placeholder="Enter 4/6 Digit UPI PIN" maxlength="4" required>
    </div>

    <button type="submit">Proceed to Pay</button>
</form>

        </div>

    </div>

</div>

</body>
</html>

