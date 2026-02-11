<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===========================
   SANITIZATION & VALIDATION
=========================== */

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validatePassword($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
}

/* ===========================
   AUTH HELPERS
=========================== */

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

function redirectIfNotAdmin() {
    redirectIfNotLoggedIn();
    if (!isAdmin()) {
        header("Location: ../user/dashboard.php");
        exit();
    }
}

/* ===========================
   ACCOUNT UTILITIES
=========================== */

function generateAccountNumber() {
    return 'SB' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
}

function generateUniqueAccountNumber($pdo) {
    do {
        $number = generateAccountNumber();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE account_number = ?");
        $stmt->execute([$number]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    return $number;
}

/* ===========================
   FORMATTING
=========================== */

function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',');
}

function formatDate($dateString) {
    return date('M j, Y H:i', strtotime($dateString));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

/* ===========================
   SECURITY & LOGGING
=========================== */

function getRecentLoginRecords($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT 
            lr.login_time,
            lr.status,
            u.full_name,
            u.email
        FROM login_records lr
        JOIN users u ON u.user_id = lr.user_id
        ORDER BY lr.login_time DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ===========================
   REAL CLIENT IP DETECTION
=========================== */

function getClientIP() {

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } 
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } 
    else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }

    // Convert IPv6 localhost (::1) to actual LAN IP
    if ($ip === '::1') {
        $ip = getHostByName(getHostName());
    }

    return $ip;
}

function logAdminAction($pdo, $userId, $action, $details) {

    $stmt = $pdo->prepare("
        SELECT u.full_name, a.account_number 
        FROM users u 
        LEFT JOIN accounts a ON u.user_id = a.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$userId]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    $name = $info['full_name'] ?? 'Unknown';
    $acc  = $info['account_number'] ?? 'N/A';

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $stmt = $pdo->prepare("
        INSERT INTO admin_logs
        (user_name, account_number, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([$name, $acc, $action, $details, $ip, $agent]);
}



?>
