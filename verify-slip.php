<?php
require_once __DIR__ . '/includes/db.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid verification request.");
}

// Fetch verification record
$stmt = $pdo->prepare("
    SELECT v.*, u.full_name, u.email, a.account_number 
    FROM slip_verifications v
    JOIN users u ON v.user_id = u.user_id
    JOIN accounts a ON u.user_id = a.user_id
    WHERE v.token = ? AND v.expires_at > NOW()
");
$stmt->execute([$token]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

$valid = $record ? true : false;
?>

<!DOCTYPE html>
<html>
<head>
<title>Slip Verification | Nexus Bank</title>
<style>
body{
    background:#0f172a;
    font-family:Segoe UI, sans-serif;
    color:#e5e7eb;
    display:flex;
    align-items:center;
    justify-content:center;
    height:100vh;
}
.card{
    background:#020617;
    padding:40px;
    border-radius:12px;
    width:420px;
    box-shadow:0 0 25px rgba(59,130,246,.4);
    text-align:center;
}
h1{color:#38bdf8}
.status-ok{color:#22c55e;font-size:20px}
.status-bad{color:#ef4444;font-size:20px}
.data{
    margin-top:20px;
    text-align:left;
    background:#020617;
    border:1px solid #1e293b;
    padding:15px;
    border-radius:8px;
}
.data span{color:#38bdf8}
</style>
</head>
<body>

<div class="card">
    <h1>🔐 Nexus Bank Verification</h1>

    <?php if($valid): ?>
        <p class="status-ok">✔ Slip Verified Successfully</p>
        <div class="data">
            <p><span>Name:</span> <?= htmlspecialchars($record['full_name']) ?></p>
            <p><span>Email:</span> <?= htmlspecialchars($record['email']) ?></p>
            <p><span>Account:</span> <?= htmlspecialchars($record['account_number']) ?></p>
            <p><span>Issued:</span> <?= $record['created_at'] ?></p>
            <p><span>Valid Until:</span> <?= $record['expires_at'] ?></p>
        </div>
    <?php else: ?>
        <p class="status-bad">❌ Invalid or Expired Slip</p>
    <?php endif; ?>
</div>

</body>
</html>
