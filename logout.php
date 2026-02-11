<?php
// Enable error reporting for debugging purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'includes/db.php';
require_once 'includes/functions.php';   // needed for logAdminAction()

// Capture user info BEFORE destroying session
$userId   = $_SESSION['user_id'] ?? null;
$isAdmin  = $_SESSION['is_admin'] ?? 0;

// 🔥 Remove this session from active user tracker
if ($userId) {
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
    $stmt->execute([session_id()]);
}

// 🧾 AUDIT LOG — LOGOUT
if ($userId) {
    logAdminAction(
        $pdo,
        $userId,
        $isAdmin ? 'Admin Logout' : 'User Logout',
        'User logged out of the system'
    );
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Timeout handling
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Session Timeout</title>
        <script>
            alert("Your session has timed out due to inactivity. You will be redirected to the login page.");
            window.location.href = "login.php";
        </script>
    </head>
    <body></body>
    </html>';
    exit();
}

// Normal logout
header("Location: login.php");
exit();
