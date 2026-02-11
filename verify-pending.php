<?php
session_start();
require_once __DIR__ . '/includes/db.php';

date_default_timezone_set('UTC');

$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

try {
    $stmt = $pdo->prepare("DELETE FROM login_verifications 
                          WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)");
    $stmt->execute();
} catch (Exception $e) {
    error_log("Failed to cleanup expired tokens: " . $e->getMessage());
}

if (!isset($_SESSION['login_verification_token'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['check_status'])) {
    try {
        $token = $_SESSION['login_verification_token'];

        $stmt = $pdo->prepare("SELECT v.verified, v.user_id, u.is_admin, v.ip_address, v.user_agent 
                              FROM login_verifications v 
                              JOIN users u ON v.user_id = u.user_id 
                              WHERE v.token = ? AND v.expires_at > UTC_TIMESTAMP()");
        $stmt->execute([$token]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($verification) {
            if ($verification['verified'] == 1) {
                $_SESSION['user_id'] = $verification['user_id'];
                $_SESSION['is_admin'] = $verification['is_admin'];

                unset($_SESSION['login_verification_token'], $_SESSION['login_verification_email'], $_SESSION['temp_user_id'], $_SESSION['temp_is_admin']);

                echo json_encode(['verified'=>true,'redirect'=>$verification['is_admin'] ? 'admin/dashboard.php' : 'user/dashboard.php']);
            } elseif ($verification['verified'] == 2) {
                session_destroy();
                session_start();
                echo json_encode(['verified'=>false,'redirect'=>'login.php?error=unauthorized_login']);
            } else {
                echo json_encode(['verified'=>false]);
            }
        } else {
            echo json_encode(['verified'=>false,'redirect'=>'login.php?error=verification_expired']);
        }
    } catch (Exception $e) {
        echo json_encode(['verified'=>false,'error'=>true]);
    }
    exit();
}

try {
    $token = $_SESSION['login_verification_token'];
    $stmt = $pdo->prepare("SELECT verified, user_id FROM login_verifications WHERE token = ? AND expires_at > UTC_TIMESTAMP()");
    $stmt->execute([$token]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($verification) {
        if ($verification['verified'] == 1) {
            header("Location: " . ($_SESSION['is_admin'] ? 'admin/dashboard.php' : 'user/dashboard.php'));
            exit();
        } elseif ($verification['verified'] == 2) {
            header("Location: login.php?error=unauthorized_login");
            exit();
        }
    }
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verification Pending - Nexus E-Banking</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
*{box-sizing:border-box}
body{
    margin:0;
    min-height:100vh;
    background:radial-gradient(circle at top,#0b132f 0%,#020617 70%);
    font-family:'Inter',sans-serif;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#e5e7eb;
}

.pending-container{
    width:440px;
    padding:40px;
    border-radius:18px;
    background:linear-gradient(145deg,#0f172a,#020617);
    box-shadow:0 0 60px rgba(56,189,248,.25);
    text-align:center;
    position:relative;
    overflow:hidden;
}

.pending-container::before{
    content:'';
    position:absolute;
    inset:0;
    background:linear-gradient(120deg,transparent 20%,rgba(56,189,248,.15),transparent 80%);
    animation:scan 6s linear infinite;
}

@keyframes scan{from{transform:translateX(-100%)}to{transform:translateX(100%)}}

.logo{width:160px;margin-bottom:25px}
h2{font-size:26px;margin-bottom:10px}
p{color:#94a3b8;font-size:14px}

.spinner{
    width:50px;height:50px;
    border:4px solid #1e293b;
    border-top:4px solid #38bdf8;
    border-radius:50%;
    animation:spin 1s linear infinite;
    margin:25px auto;
}

@keyframes spin{to{transform:rotate(360deg)}}

.btn-cancel{
    display:inline-block;
    margin-top:25px;
    padding:12px 28px;
    border-radius:10px;
    background:#ef4444;
    color:white;
    text-decoration:none;
    font-weight:600;
}

.btn-cancel:hover{opacity:.9}

.error-message{color:#f87171;font-size:13px;margin-top:12px;display:none}
</style>
</head>

<body>

<div class="pending-container">
    <img src="./assets/images/Logo.png" class="logo">
    <h2>Verification Pending</h2>

    <div class="spinner"></div>

    <p>We've sent a verification email to your registered email address.</p>
    <p>Please check your email and approve the login attempt.</p>
    <p><small>This page will automatically update once verified.</small></p>

    <p class="error-message" id="errorMessage">There was an error checking verification status.</p>

    <a href="login.php" class="btn-cancel">Cancel Login</a>
</div>

<script>
let errorCount=0,maxErrors=3;
const errorMessage=document.getElementById('errorMessage');

function checkVerificationStatus(){
    fetch('verify-pending.php?check_status=1')
    .then(r=>r.json())
    .then(data=>{
        if(data.error){
            errorCount++;
            if(errorCount>=maxErrors) errorMessage.style.display='block';
        } else {
            errorCount=0;
            errorMessage.style.display='none';
            if(data.verified||data.redirect){
                window.location.href=data.redirect||(data.verified?'user/dashboard.php':'login.php');
            }
        }
    })
    .catch(()=>{
        errorCount++;
        if(errorCount>=maxErrors) errorMessage.style.display='block';
    });
}

setInterval(checkVerificationStatus,2000);
checkVerificationStatus();
</script>

</body>
</html>
