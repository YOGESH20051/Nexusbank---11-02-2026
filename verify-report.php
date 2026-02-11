<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';



$token = $_GET['token'] ?? '';

if (!$token) {
    die("<h2 style='color:red'>Invalid verification link.</h2>");
}

// Always explicitly select the columns we need
$stmt = $pdo->prepare("
    SELECT 
        token,
        report_type,
        admin_id,
        created_at,
        expires_at
    FROM report_verifications
    WHERE token = ?
");
$stmt->execute([$token]);

$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    logAdminAction(
        $pdo,
        0,
        'Document Verification Failed',
        'Invalid or fake verification token: ' . $token
    );
    die("<h2 style='color:red'>✖ Invalid or Fake Document</h2>");
}

// Safe date handling (no warnings, ever)
$generatedOn = !empty($report['created_at']) ? $report['created_at'] : 'N/A';

// Expiry check (optional but recommended)
if (!empty($report['expires_at']) && strtotime($report['expires_at']) < time()) {
    logAdminAction(
        $pdo,
        $report['admin_id'],
        'Document Verification Expired',
        'Expired token used: ' . $token
    );
    die("<h2 style='color:red'>✖ Verification Expired</h2>");
}
// Log successful verification
logAdminAction(
    $pdo,
    $report['admin_id'],
    'Document Verified',
    'Document verified successfully. Type: ' . $report['report_type']
);
// Log verification (optional security layer)
try {
    $log = $pdo->prepare("INSERT INTO verification_logs (token, ip_address) VALUES (?, ?)");
    $log->execute([$token, $_SERVER['REMOTE_ADDR']]);
} catch (Exception $e) {
    // logging failure should never break verification page
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Nexus Bank | Document Verification</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
*{box-sizing:border-box}
body{
    margin:0;
    background:radial-gradient(circle at top, #0b132f 0%, #020617 70%);
    font-family:'Inter',sans-serif;
    color:#e5e7eb;
}

.container{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
}

.certificate{
    width:780px;
    background:linear-gradient(145deg,#0f172a,#020617);
    border-radius:20px;
    padding:40px;
    box-shadow:0 0 60px rgba(56,189,248,.25);
    position:relative;
    overflow:hidden;
}

.certificate::before{
    content:'';
    position:absolute;
    inset:0;
    background:
      linear-gradient(120deg,transparent 20%,rgba(56,189,248,.15),transparent 80%);
    animation:scan 6s linear infinite;
}

@keyframes scan{
    from{transform:translateX(-100%)}
    to{transform:translateX(100%)}
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.logo{
    font-size:22px;
    font-weight:700;
    color:#38bdf8;
}

.status{
    display:flex;
    align-items:center;
    gap:10px;
    background:#22c55e1f;
    color:#22c55e;
    padding:8px 16px;
    border-radius:999px;
    font-size:13px;
}

.title{
    font-size:28px;
    font-weight:700;
    margin:10px 0 25px;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px 40px;
}

.field{
    border-bottom:1px solid #1e293b;
    padding-bottom:8px;
}

.label{
    color:#94a3b8;
    font-size:13px;
}

.value{
    margin-top:4px;
    font-weight:600;
}

.footer{
    margin-top:35px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.seal{
    font-size:13px;
    color:#38bdf8;
    border:1px solid #38bdf8;
    padding:6px 14px;
    border-radius:999px;
}

.signature{
    text-align:right;
    font-size:13px;
    color:#94a3b8;
}

.signature strong{
    display:block;
    margin-top:4px;
    color:#e5e7eb;
}
</style>
</head>

<body>

<div class="container">
<div class="certificate">

<div class="header">
  <div class="logo">NEXUS BANK</div>
  <div class="status">✔ VERIFIED & AUTHENTIC</div>
</div>

<div class="title">Official Document Verification Certificate</div>

<div class="grid">

  <div class="field">
    <div class="label">Report Type</div>
    <div class="value"><?= htmlspecialchars($report['report_type']) ?></div>
  </div>

  <div class="field">
    <div class="label">Admin ID</div>
    <div class="value"><?= htmlspecialchars($report['admin_id']) ?></div>
  </div>

  <div class="field">
    <div class="label">Generated On</div>
    <div class="value"><?= htmlspecialchars($generatedOn) ?></div>
  </div>

  <div class="field">
    <div class="label">Verification Token</div>
    <div class="value" style="word-break:break-all"><?= htmlspecialchars($report['token']) ?></div>
  </div>

</div>

<div class="footer">
  <div class="seal">SECURE DIGITAL SEAL</div>
  <div class="signature">
      Authorized & Certified by
      <strong>Nexus Bank Verification Authority</strong>
  </div>
</div>

</div>
</div>

</body>
</html>

