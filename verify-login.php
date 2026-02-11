<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notification.php';
require_once __DIR__ . '/includes/functions.php';

date_default_timezone_set('UTC');

$token  = $_GET['token']  ?? '';
$action = $_GET['action'] ?? '';

if (!$token || !$action) {
    header("Location: login.php?error=missing_parameters");
    exit();
}

try {

    $stmt = $pdo->prepare("
        SELECT v.*, u.email
        FROM login_verifications v
        JOIN users u ON v.user_id = u.user_id
        WHERE v.token = ? 
          AND v.verified = 0 
          AND v.expires_at > UTC_TIMESTAMP()
    ");
    $stmt->execute([$token]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$verification) {
        header("Location: login.php?error=invalid_verification");
        exit();
    }

    $statusMessage = '';
    $statusClass   = '';
    $icon          = '';

    if ($action === 'verify') {

        $pdo->beginTransaction();

        $pdo->prepare("UPDATE login_verifications SET verified = 1 WHERE id = ?")
            ->execute([$verification['id']]);

        logAdminAction(
            $pdo,
            $verification['user_id'],
            'Login Verification',
            'Login verification APPROVED from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        $pdo->commit();

        sendNotification(
            $verification['email'],
            "Login Approved - Nexus Bank",
            "Your login was approved from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        $statusMessage = "Login Approved Successfully";
        $statusClass   = "success";
        $icon          = "✔";
    }

    elseif ($action === 'deny') {

        $pdo->beginTransaction();

        $pdo->prepare("UPDATE login_verifications SET verified = 2 WHERE id = ?")
            ->execute([$verification['id']]);

        logAdminAction(
            $pdo,
            $verification['user_id'],
            'Login Verification',
            'Login verification DENIED from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        $pdo->commit();

        sendNotification(
            $verification['email'],
            "Security Alert - Login Denied",
            "A login attempt was denied from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        $statusMessage = "Login Request Denied";
        $statusClass   = "denied";
        $icon          = "✖";
    }

    else {
        header("Location: login.php?error=invalid_action");
        exit();
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("System Error");
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Verification Result - Nexus Bank</title>
<style>
body {
    background: radial-gradient(circle at top, #0b1120, #020617);
    font-family: 'Segoe UI', sans-serif;
    color: white;
    margin: 0;
}
.container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 50px 60px;
    width: 420px;
    text-align: center;
    box-shadow: 0 0 60px rgba(0,255,255,0.15);
}
.icon {
    font-size: 70px;
    margin-bottom: 20px;
}
.success { color: #22c55e; }
.denied  { color: #ef4444; }
h1 {
    font-size: 26px;
    margin-bottom: 15px;
}
p {
    opacity: 0.8;
}
.btn {
    margin-top: 30px;
    display: inline-block;
    padding: 12px 28px;
    border-radius: 25px;
    text-decoration: none;
    background: linear-gradient(135deg,#22d3ee,#6366f1);
    color: black;
    font-weight: 600;
}
.btn:hover {
    opacity: 0.9;
}
</style>
</head>

<body>
<div class="container">
    <div class="card">
        <div class="icon <?= $statusClass ?>"><?= $icon ?></div>
        <h1><?= $statusMessage ?></h1>
        <p>You may now safely close this window.</p>
        <a href="login.php" class="btn">Return to Login</a>
    </div>
</div>
</body>
</html>
