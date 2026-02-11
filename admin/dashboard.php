<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session at the beginning (added to avoid any issues with session usage)
session_start();

// Include database and functions
require_once '../includes/db.php';
require_once '../includes/functions.php';
include __DIR__ . '/../includes/loader.php';
// Ensure only admins can access the page
redirectIfNotAdmin();

// Get system statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAccounts = $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
$totalBalance = $pdo->query("SELECT SUM(balance) FROM accounts")->fetchColumn();
$totalBalance = $totalBalance ?: 0;
$pendingLoans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn();

// Get recent users
$recentUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();



// Get latest users for fade animation (with account number)
$latestUsers = $pdo->query("
    SELECT 
        u.full_name,
        u.email,
        a.account_number
    FROM users u
    LEFT JOIN accounts a ON u.user_id = a.user_id
    ORDER BY u.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);



// Get user account information
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT u.*, a.account_number, a.balance 
    FROM users u 
    JOIN accounts a ON u.user_id = a.user_id 
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die('User account not found.');
}

// Check if the user has a profile picture
$stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
$profilePic = $user['profile_picture'] ? '../uploads/' . $user['profile_picture'] : '../assets/images/default-avatars.png';


// Monthly transaction summary
$monthlySummary = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS total_transactions,
        SUM(CASE WHEN type='transfer_in' THEN amount ELSE 0 END) AS credit,
        SUM(CASE WHEN type IN ('transfer_out','withdrawal','loanpayment') THEN amount ELSE 0 END) AS debit
    FROM transactions
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);


// ================= System Health Live Data =================

// 1. Database status
$dbStatus = 'Offline';
try {
    $pdo->query("SELECT 1");
    $dbStatus = 'Connected';
} catch (Exception $e) {
    $dbStatus = 'Offline';
}

// 2. Failed logins today
$failedLogins = 0;
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM admin_logs
    WHERE action LIKE '%Failed%'
      AND DATE(created_at) = CURDATE()
");
$stmt->execute();
$failedLogins = $stmt->fetchColumn();

// 3. Active users now (last 5 minutes)
$activeUsers = 0;
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT user_id)
    FROM user_sessions
    WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$stmt->execute();
$activeUsers = $stmt->fetchColumn();

// 4. Server status (if code runs, server is online)
$serverStatus = 'Online';


if (isset($_SESSION['user_id'])) {

    $check = $pdo->prepare("SELECT id FROM user_sessions WHERE session_id = ?");
    $check->execute([session_id()]);

    if ($check->rowCount()) {
        $update = $pdo->prepare("
            UPDATE user_sessions 
            SET last_activity = NOW() 
            WHERE session_id = ?
        ");
        $update->execute([session_id()]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO user_sessions 
            (user_id, session_id, last_activity, ip_address, user_agent)
            VALUES (?, ?, NOW(), ?, ?)
        ");
        $insert->execute([
            $_SESSION['user_id'],
            session_id(),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
}

?>

<style>

  .sidebar hr {
    width: 80%;
    margin: 15px auto;
    border: none;
    border-top: 1px solid #e2e6ef;
}  
   .logout {
    padding: 9px 20px;
    border-radius: 50px;
    color: #444;
    font-weight: 600;
    background: #f1f3f8;
    text-decoration: none;
    box-shadow: 0 5px 12px rgba(0,0,0,0.12);
    transition: 0.3s;
}

.logout:hover {
    background: #ff4d4d;
    color: #fff;
    box-shadow: 0 10px 18px rgba(255,77,77,0.35);
}
/* Tooltip */
.logout-wrap {
    position: relative;
    display: inline-block;
}

.logout-msg {
    position: absolute;
    top: 120%;
    left: 50%;
    transform: translateX(-50%);
    background: #000;
    color: #fff;
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
    opacity: 0;
    visibility: hidden;
    transition: 0.3s;
    white-space: nowrap;
}

.logout-wrap:hover .logout-msg {
    opacity: 1;
    visibility: visible;
}

/* Modal */
.logout-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
}

.logout-modal-content {
    background: #fff;
    width: 320px;
    padding: 25px;
    border-radius: 14px;
    margin: 15% auto;
    text-align: center;
    animation: scaleIn 0.3s ease;
}

.logout-modal-content h3 {
    margin-bottom: 10px;
}

.logout-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

.btn-yes {
    background: #ff4d4d;
    color: #fff;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
}

.btn-no {
    background: #ddd;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
}

@keyframes scaleIn {
    from {
        transform: scale(0.7);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}


.loan-action-floating{
    position:absolute;
    top:250px;         /* adjust slightly if needed */
    right:40px;
    z-index:10;
}

.loan-action-btn{
    padding:10px 22px;
    background:linear-gradient(135deg,#7c6cff,#5b5cf6);
    color:#fff;
    text-decoration:none;
    border-radius:12px;
    font-weight:600;
    font-size:14px;
    box-shadow:0 8px 20px rgba(91,92,246,.35);
    transition:.25s ease;
}

.loan-action-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(91,92,246,.45);
    background:linear-gradient(135deg,#6a5df5,#4f46e5);
}



/* Default: hide scrollbar completely */
body {
    overflow-y: hidden;
}

/* When scrolling is active */
body.scrolling {
    overflow-y: auto;
}

/* Theme scrollbar */
body.scrolling::-webkit-scrollbar {
    width: 8px;
}

body.scrolling::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg,#6366f1,#4f46e5);
    border-radius: 10px;
}

/* Firefox */
body.scrolling {
    scrollbar-width: thin;
    scrollbar-color: #6366f1 transparent;
}


/* Hide by default */
.scroll-container::-webkit-scrollbar {
    width: 0;
}

/* Visible only when scrolling */
.scrolling .scroll-container::-webkit-scrollbar {
    width: 8px;
}

.scrolling .scroll-container::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg,#6366f1,#4f46e5);
    border-radius: 12px;
}

/* Firefox */
.scroll-container {
    scrollbar-width: none;
}

.scrolling .scroll-container {
    scrollbar-width: thin;
    scrollbar-color: #6366f1 transparent;
}

/* Sidebar scrolling */
.sidebar {
    width: 250px;
    height: 100vh;
    overflow-y: auto;
}

/* Hide scrollbar completely by default */
.sidebar::-webkit-scrollbar {
    width: 0px;
}

/* When active */
.sidebar.show-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.sidebar.show-scrollbar::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg,#6366f1,#4f46e5);
    border-radius: 10px;
}

/* Firefox */
.sidebar {
    scrollbar-width: none;
}

.sidebar.show-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #6366f1 transparent;
}
.quick-actions-v2{
    background:rgba(15,23,42,.65);
    backdrop-filter:blur(12px);
    border-radius:14px;
    padding:20px;
    box-shadow:0 15px 40px rgba(0,0,0,.35);
    border:1px solid rgba(255,255,255,.05);
}

.qa2-header{
    color:#e5e7eb;
    font-size:15px;
    font-weight:600;
    margin-bottom:16px;
    display:flex;
    gap:8px;
    align-items:center;
}

.qa2-list{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
    gap:14px;
}

.qa2-btn{
    padding:13px 14px;
    border-radius:12px;
    font-size:13px;
    text-align:center;
    text-decoration:none;
    color:#f8fafc;
    background:rgba(2,6,23,.7);
    border:1px solid rgba(255,255,255,.06);
    transition:.3s cubic-bezier(.4,0,.2,1);
    position:relative;
    overflow:hidden;
}

.qa2-btn::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(120deg,transparent,rgba(255,255,255,.15),transparent);
    transform:translateX(-100%);
    transition:.6s;
}

.qa2-btn:hover::before{
    transform:translateX(100%);
}

.qa2-btn:hover{
    transform:translateY(-3px);
    box-shadow:0 12px 28px rgba(0,0,0,.4);
}

/* soft glow colors */
.green{box-shadow:inset 0 0 0 rgba(34,197,94,.4);}
.green:hover{box-shadow:0 0 18px rgba(34,197,94,.5);}

.emerald:hover{box-shadow:0 0 18px rgba(52,211,153,.5);}
.blue:hover{box-shadow:0 0 18px rgba(56,189,248,.5);}
.sky:hover{box-shadow:0 0 18px rgba(147,197,253,.5);}
.yellow:hover{box-shadow:0 0 18px rgba(250,204,21,.5);}
.purple:hover{box-shadow:0 0 18px rgba(192,132,252,.5);}
.amber:hover{box-shadow:0 0 18px rgba(245,158,11,.5);}


.neon-title {
    color: #4f46e5;
    text-shadow: 0 0 6px rgba(79,70,229,0.35);
    margin-bottom: 8px;
}

.neon-activity-box {
    background: linear-gradient(145deg,#0f172a,#020617);
    border-radius: 14px;
    padding: 10px 12px;
    margin-top: 8px;
    box-shadow: 0 6px 20px rgba(79,70,229,0.18);
}

.neon-activity-item {
    display: flex;
    gap: 10px;
    padding: 8px 6px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    animation: slideIn 0.4s ease forwards;
}

.neon-activity-item:last-child { border-bottom: none; }

.activity-text h4 {
    color: #e0e7ff;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.1;
}

.activity-text p {
    color: #94a3b8;
    font-size: 12px;
    margin: 2px 0;
    line-height: 1.2;
}

.activity-text span {
    font-size: 10px;
    color: #64748b;
}

.pulse-ring {
    width: 9px;
    height: 9px;
    background: #4f46e5;
    border-radius: 50%;
    position: relative;
    margin-top: 4px;
}

.pulse-ring::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(79,70,229,0.6);
    animation: pulse 1.8s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: .6; }
    100% { transform: scale(2); opacity: 0; }
}

@keyframes slideIn {
    from { transform: translateY(6px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
}

.cyber-title {
    color: #38bdf8;
    text-shadow: 0 0 8px rgba(56,189,248,.4);
}

.cyber-security-panel {
    display: flex;
    gap: 16px;
    margin: 12px 0 20px;
}

.cyber-card {
    flex: 1;
    padding: 14px 16px;
    border-radius: 14px;
    background: linear-gradient(145deg,#020617,#0f172a);
    box-shadow: 0 0 30px rgba(56,189,248,.15);
    position: relative;
    overflow: hidden;
}

.cyber-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg,transparent 30%,rgba(56,189,248,.12),transparent 70%);
    animation: scan 5s linear infinite;
}

@keyframes scan {
    from { transform: translateX(-100%); }
    to { transform: translateX(100%); }
}

.cyber-label {
    font-size: 10px;
    letter-spacing: 1px;
    color: #94a3b8;
}

.cyber-meter {
    height: 7px;
    background: #020617;
    border-radius: 999px;
    margin: 6px 0 4px;
    overflow: hidden;
}

.cyber-fill {
    height: 100%;
    border-radius: 999px;
}

.cyber-fill.threat {
    width: 18%;
    background: linear-gradient(90deg,#22c55e,#16a34a);
    box-shadow: 0 0 12px rgba(34,197,94,.6);
}

.cyber-fill.stable {
    width: 98%;
    background: linear-gradient(90deg,#38bdf8,#2563eb);
    box-shadow: 0 0 12px rgba(56,189,248,.6);
}

.cyber-value {
    font-size: 12px;
    font-weight: 600;
    color: #e5e7eb;
}

.cyber-value.low { color: #22c55e; }

/* ================= Widget Layout ================= */

.threat-widget {
    width: 33%;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(12px);
    border-radius: 14px;
    padding: 14px;
    box-shadow: 0 10px 25px rgba(0,0,0,.4);
    animation: fadeIn 0.8s ease;
}

/* Header */
.widget-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-weight:600;
    color:#c7d2fe;
    margin-bottom:10px;
}

/* Scroll Area */
.threat-list {
    max-height: 260px;
    overflow-y: auto;
    padding-right: 5px;
}

/* Card Base */
.threat-card {
    padding:10px 12px;
    border-radius:10px;
    margin-bottom:8px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:14px;
    color:#f8fafc;
    animation: slideUp 0.5s ease;
    animation-delay: calc(var(--i) * .08s);
    box-shadow: inset 0 0 6px rgba(255,255,255,.05);
    transition:.3s;
}

.threat-card:hover {
    transform: translateX(4px);
}

/* Mild Cyber Colors */
.threat-card.high {
    background: linear-gradient(145deg,#2b1b1b,#7f1d1d);
}

.threat-card.medium {
    background: linear-gradient(145deg,#2b2417,#92400e);
}

.threat-card.low {
    background: linear-gradient(145deg,#132524,#065f46);
}

/* Animations */
@keyframes slideUp {
    from { opacity:0; transform:translateY(12px); }
    to   { opacity:1; transform:translateY(0); }
}

@keyframes fadeIn {
    from { opacity:0; transform:scale(.96); }
    to   { opacity:1; transform:scale(1); }
}

/* Soft breathing glow */
@keyframes pulseGlow {
    0%,100% { filter: brightness(1); }
    50% { filter: brightness(1.08); }
}

.threat-card {
    animation: slideUp .6s ease, pulseGlow 4s infinite;
}

.security-dashboard {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-top: 20px;
}

/* Common box design */
.security-box {
    background: #0b1220;
    border-radius: 14px;
    padding: 20px;
    min-height: 420px;           /* Equal height */
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 25px rgba(0,0,0,0.4);
}

/* Header */
.box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 16px;
    font-weight: 600;
    color: #e5e7eb;
    margin-bottom: 16px;
}

.box-header small {
    font-size: 12px;
    color: #9ca3af;
}

/* Content fills box */
.box-content {
    flex: 1;
    overflow-y: auto;
}

/* Mobile responsive */
@media (max-width: 900px) {
    .security-dashboard {
        grid-template-columns: 1fr;
    }
}

.admin-message-card.alt-style {
    background: linear-gradient(135deg, #3b0764, #7c3aed);
    border-radius: 16px;
    padding: 16px 22px;
    margin: 18px 0;
    font-weight: 700;
    font-size: 15px;
    color: #fef3c7;
    text-align: center;
    box-shadow: 0 10px 30px rgba(124,58,237,.45);
    animation: fadeScale 0.6s ease;
}

/* Soft glow */
.admin-message-card.alt-style::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 16px;
    box-shadow: 0 0 40px rgba(245,158,11,.35);
    opacity: .6;
    pointer-events: none;
}

@keyframes fadeScale {
    from {
        opacity: 0;
        transform: scale(.96);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* ===== Clean Fintech Theme ===== */

.threat-widget,
.audit-widget {
    background: #0b1220;
    border-radius: 16px;
    padding: 16px;
    border: 1px solid rgba(255,255,255,.06);
    box-shadow: 0 8px 20px rgba(0,0,0,.45);
}

/* Header */
.widget-header,
.audit-header {
    font-size: 14px;
    font-weight: 600;
    color: #e5e7eb;
    letter-spacing: .3px;
    margin-bottom: 12px;
}

.widget-header small,
.audit-header small {
    color: #94a3b8;
}

/* Items */
.threat-card,
.audit-item {
    background: #020617;
    border: 1px solid rgba(255,255,255,.05);
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 8px;
    font-size: 13px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    transition: all .25s ease;
}

/* Hover */
.threat-card:hover,
.audit-item:hover {
    background: #020617;
    border-color: #38bdf8;
    transform: translateX(3px);
}

/* Severity colors (mild) */
.threat-card.high,
.audit-item.warn {
    border-left: 3px solid #ef4444;
}

.threat-card.medium,
.audit-item.info {
    border-left: 3px solid #f59e0b;
}

.threat-card.low,
.audit-item.success {
    border-left: 3px solid #10b981;
}

/* Time text */
.threat-card small,
.audit-item small {
    color: #94a3b8;
    font-size: 11px;
}

/* Scrollbar */
.threat-list::-webkit-scrollbar,
.audit-list::-webkit-scrollbar {
    width: 6px;
}

.threat-list::-webkit-scrollbar-thumb,
.audit-list::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 10px;
}

/* ===== Text Accent Upgrade ===== */
.threat-card span,
.audit-item span {
    color: #f59e0b;
}


/* ================= System Health Card ================= */

.health-widget {
    width: 33%;
    margin-top: 14px;
    background: #0b1220;
    border-radius: 16px;
    padding: 16px;
    border: 1px solid rgba(255,255,255,.06);
    box-shadow: 0 8px 20px rgba(0,0,0,.45);
}

.health-header {
    font-size: 14px;
    font-weight: 600;
    color: #e5e7eb;
    margin-bottom: 12px;
}

.health-body {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.health-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #020617;
    border: 1px solid rgba(255,255,255,.05);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    color: #f59e0b;
    transition: .25s;
}

.health-row:hover {
    border-color: #38bdf8;
    transform: translateX(3px);
}

.health-row strong {
    font-weight: 600;
}

.health-row .ok { color: #10b981; }
.health-row .warn { color: #f59e0b; }
.health-row .info { color: #38bdf8; }

/* =======================
   CYBER SECURITY STAT CARDS
======================= */

.stats-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
    margin-bottom:28px;
}

/* Core Card */
.stat-card{
    position:relative;
    padding:22px;
    border-radius:16px;
    background:#020617;
    color:white;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.08);
    box-shadow:
        0 0 28px rgba(56,189,248,.15),
        inset 0 0 24px rgba(56,189,248,.05);
    transition:.35s ease;

    will-change:transform;
    backface-visibility:hidden;
    transform:translateZ(0);
}

.stat-card:hover{
    transform:translateY(-6px) scale(1.02);
}

/* Neon identity per card */
.stat-card:nth-child(1){ --glow:#38bdf8; } /* Blue */
.stat-card:nth-child(2){ --glow:#22c55e; } /* Green */
.stat-card:nth-child(3){ --glow:#facc15; } /* Gold */
.stat-card:nth-child(4){ --glow:#fb7185; } /* Rose */

/* Neon frame */
.stat-card::before{
    content:'';
    position:absolute;
    inset:0;
    border-radius:16px;
    border:1px solid var(--glow);
    box-shadow:0 0 18px var(--glow);
    opacity:.55;
    pointer-events:none;
}

/* Label */
.stat-card h3{
    font-size:13px;
    font-weight:600;
    color:#94a3b8;
    letter-spacing:.4px;
}

/* Value */
.stat-card p{
    font-size:34px;
    font-weight:900;
    margin-top:8px;
    color:white;
    text-shadow:0 0 10px var(--glow);

    display:inline-block;
    min-width:130px;
    white-space:nowrap;
    font-variant-numeric: tabular-nums;
}


</style>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureBank - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin-main.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">

       <script src="../assets/js/sidebar.js"></script>
</head>



<body>



<div class="wrapper">
        <aside class="sidebar">

            
                <div class="Logos-cont">
                    <img src="../assets/images/Logo-color.png" alt="SecureBank Logo" class="logo-container">
                </div>

                <hr>

                <div class="profile-container">
                    <img src="<?= $profilePic ?>" alt="Profile Picture" class="img-fluid">
                    <h5><?= htmlspecialchars($user['full_name']) ?></h5>
                    <p><?= htmlspecialchars($user['account_number']) ?></p>
                </div>

                <hr>


                <nav class="dashboard-nav">
                    <a href="dashboard.php" class="btn dash-text">
                        <img 
                        src="../assets/images/hover-dashboard.png" 
                        alt="dashboard-logo" 
                        class="nav-icon"
                        data-default="../assets/images/dashboard_logo.png"
                        data-hover="../assets/images/hover-dashboard.png"
                        > 
                        Dashboard
                    </a>
                    <a href="manage-users.php" class="btn">
                        <img 
                        src="../assets/images/manageusers.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/manageusers_active.png"
                        data-hover="../assets/images/manageusers_active.png"
                        > 
                        Manage Users
                    </a>
                   <a href="manage-loans.php" class="btn">
                        <img 
                        src="../assets/images/manageloan.png" 
                        alt="manage-loan-logo" 
                        class="nav-icon"
                        data-default="../assets/images/manageloan_active.png"
                        data-hover="../assets/images/manageloan_active.png"
                        > 
                        Manage Loans
                    </a>
                    <a href="manage-investments.php" class="btn">
                        <img 
                        src="../assets/images/investment.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/investment_active.png"
                        data-hover="../assets/images/investment_active.png"
                        > 
                        Manage Investments
                    </a>
                     <a href="track-investments.php" class="btn">
                        <img 
                        src="../assets/images/userinvestment.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/userinvestment_active.png"
                        data-hover="../assets/images/userinvestment_active.png"
                        > 
                        Users Investments
                    </a>
                    <a href="role.php" class="btn">
                        <img 
                        src="../assets/images/roles_logo.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/rolesact_logo.png"
                        data-hover="../assets/images/rolesactive_logo.png"
                        > 
                        Roles
                    </a>
                    <a href="admin-transaction.php" class="btn">
                        <img 
                        src="../assets/images/admin.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/admin_active.png"
                        data-hover="../assets/images/admin_active.png"
                        > 
                        Admin Transaction
                    </a>
                    <a href="recent_transactions.php" class="btn">
                        <img 
                        src="../assets/images/transaction.png" 
                        alt="transaction-logo" 
                        class="nav-icon"
                        data-default="../assets/images/transaction_active.png"
                        data-hover="../assets/images/transaction_active.png"
                        > 
                        Transactions
                    </a>
                    <a href="loan-history.php" class="btn">
                        <img 
                        src="../assets/images/loan.png" 
                        alt="loan-record-logo" 
                        class="nav-icon"
                        data-default="../assets/images/loan_active.png"
                        data-hover="../assets/images/loan_active.png"
                        > 
                         Loan History
                    </a>
                    <a href="login-records.php" class="btn">
                        <img 
                        src="../assets/images/loginrecord.png" 
                        alt="login-record-logo" 
                        class="nav-icon"
                        data-default="../assets/images/loginrecord_active.png"
                        data-hover="../assets/images/loginrecord_active.png"
                        > 
                         Login Records
                    </a>
                    <a href="add_news.php" class="btn">
                        <img 
                        src="../assets/images/announcement.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/announcement_active.png"
                        data-hover="../assets/images/announcement_active.png"
                        > 
                         Newscast
                    </a>
                    <a href="view-message.php" class="btn">
                        <img 
                        src="../assets/images/contact.png" 
                        alt="message-logo" 
                        class="nav-icon"
                        data-default="../assets/images/contact_active.png"
                        data-hover="../assets/images/contact_active.png"
                        > 
                        Messages
                    </a>
                    <a href="view-verifications.php" class="btn">
                        <img 
                        src="../assets/images/verify.png" 
                        alt="message-logo" 
                        class="nav-icon"
                        data-default="../assets/images/verify_active.png"
                        data-hover="../assets/images/verify_active.png"
                        > 
                        Verification Reports
                    </a>
                    <a href="security-logs.php" class="btn ">
                        <img 
                        src="../assets/images/audit.png" 
                        alt="message-logo" 
                        class="nav-icon"
                        data-default="../assets/images/audit_active.png"
                        data-hover="../assets/images/audit_active.png"
                        > 
                        Audit Logs
                    </a>
                    <a href="das-message.php" class="btn ">
                        <img 
                        src="../assets/images/das.png" 
                        alt="das-message-logo" 
                        class="nav-icon"
                        data-default="../assets/images/das_active.png"
                        data-hover="../assets/images/das_active.png"
                        > 
                        Manage Messages
                    </a>
                    
                    
                   

                    
                </nav>


</br></br></br>
    </aside>


    <main class="container scroll-container">
       

        <header>
            <h1>Admin Dashboard</h1>
           
            <div class="logout-wrap">
    <a href="../logout.php" class="logout" onclick="openLogoutModal()">
        ⏻
    </a>
    <span class="logout-msg">Logout</span>
</div>

<!-- Logout Modal -->
<div id="logoutModal" class="logout-modal">
    <div class="logout-modal-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>

        <div class="logout-actions">
            <button class="btn-yes" onclick="confirmLogout()">Yes, Logout</button>
            <button class="btn-no" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>
</div>



        <button class="hamburger">&#9776;</button> <!-- Hamburger icon -->
        </header>     

                
    <div class="content">
            <h2>System Overview</h2>

            
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p><?= $totalUsers ?></p>
                </div>

                <div class="stat-card">
                    <h3>Total Accounts</h3>
                    <p><?= $totalAccounts ?></p>
                </div>
                
                <div class="stat-card">
                    <h3>Total Balance</h3>
                    <p>₹<?= number_format($totalBalance, 2) ?></p>
                </div>
                
                <div class="stat-card">
                    <h3>Pending Loans</h3>
                    <p><?= $pendingLoans ?></p>
                </div>


                



                

                
            </div>





</br>
<div class="latest-user-row">
    <h3>New Users</h3>
    <div class="fade-user" id="fadeUser">
        👤 𝔏𝔬𝔞𝔡𝔦𝔫𝔤...
    </div>
</br></br>

    <marquee class="welcome-text" behavior="scroll" direction="left">
         <span>🏦 Welcome, <b>Administrator</b> — Command the Power of Nexus Bank
Manage Users • Monitor Transactions • Secure the System • Drive Innovation • Total Control at Your Fingertips ⚡</span>
    </marquee>


    <h3 class="section-title cyber-title">System Security Monitor</h3>

<div class="cyber-security-panel">

  <div class="cyber-card">
    <div class="cyber-label">THREAT LEVEL</div>
    <div class="cyber-meter">
      <div class="cyber-fill threat"></div>
    </div>
    <div class="cyber-value low">LOW RISK</div>
  </div>

  <div class="cyber-card">
    <div class="cyber-label">SYSTEM STABILITY</div>
    <div class="cyber-meter">
      <div class="cyber-fill stable"></div>
    </div>
    <div class="cyber-value">98% OK</div>
  </div>

</div>

</div>

<audio id="notificationSound" src="assets/sounds/notification.mp3" preload="auto"></audio>


<!-- ================= Quick Actions ================= -->
<div class="quick-actions-v2">

    <div class="qa2-header">
        <span>⚡</span> Admin Shortcuts
    </div>

    <div class="qa2-list">

        <a href="add-user.php" class="qa2-btn green">➕ Add User</a>
        <a href="all-users.php" class="qa2-btn emerald">✔ Approve Accounts</a>
        <a href="all-transaction1.php" class="qa2-btn blue">💸 View Transactions</a>
        <a href="loan-shortcut.php" class="qa2-btn sky">🏦 Loan Requests</a>
        <a href="add_news.php" class="qa2-btn yellow">📰 Post Bank News</a>
        <a href="manage-messages.php" class="qa2-btn purple">📨 Contact Inbox</a>
        <a href="view-verifications.php" class="qa2-btn amber">📄 View Verified Report</a>
        <a href="security-logs.php" class="qa2-btn sky">🏦 Security Logs</a>
        <a href="das-Message.php" class="qa2-btn green">📩 Manage Messages</a>

    </div>

</div>



<!-- Admin Welcome Message -->
<div class="admin-message-card alt-style">
    <span id="adminMessage"></span>
</div>


<div class="security-dashboard">

    <!-- LEFT BOX -->
    <div class="security-box">
        <div class="box-header">
            🛡 Live Security Monitor
            <small>Last 5 events</small>
        </div>

        <div class="box-content threat-list">

            <div class="threat-card high">
                <span>Unauthorized Login Attempt</span>
                <small>2 min ago</small>
            </div>

            <div class="threat-card medium">
                <span>Multiple OTP Failures</span>
                <small>7 min ago</small>
            </div>

            <div class="threat-card low">
                <span>Firewall Rule Updated</span>
                <small>10 min ago</small>
            </div>

            <div class="threat-card medium">
                <span>New Device Login Detected</span>
                <small>18 min ago</small>
            </div>

            <div class="threat-card low">
                <span>Backup Integrity Verified</span>
                <small>25 min ago</small>
            </div>

        </div>
    </div>

    <!-- RIGHT BOX -->
    <div class="security-box">
        <div class="box-header neon-title">
            🔵 Live Security Activity
            <small>System Logs</small>
        </div>

        <div class="box-content neon-activity-box" id="securityActivity">

        <?php
        $activity = $pdo->query("
            SELECT action, details, created_at 
            FROM admin_logs 
            ORDER BY created_at DESC 
            LIMIT 5
        ");

        while($row = $activity->fetch()){
        ?>
            <div class="neon-activity-item">
                <div class="pulse-ring"></div>
                <div class="activity-text">
                    <h4><?= htmlspecialchars($row['action']) ?></h4>
                    <p><?= htmlspecialchars($row['details']) ?></p>
                    <span><?= $row['created_at'] ?></span>
                </div>
            </div>
        <?php } ?>
        </div>
    </div>

</div>

      


</br>
 <h2 style="margin-top:25px;">Monthly Transaction Summary</h2>

<table class="users-table">
    <thead>
        <tr>
            <th>Month</th>
            <th>Total Transactions</th>
            <th>Total Credit</th>
            <th>Total Debit</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($monthlySummary as $m): ?>
        <tr>
            <td><?= $m['month'] ?></td>
            <td><?= $m['total_transactions'] ?></td>
            <td style="color:green;">₹<?= number_format($m['credit'],2) ?></td>
            <td style="color:red;">₹<?= number_format($m['debit'],2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
 
        </br>
  </br>
  <!-- ================= Quick Audit Peek ================= -->
<div class="audit-widget">

    <div class="audit-header">
        🧾 Recent Admin Actions
        <small>Last 5 logs</small>
    </div>

    <div class="audit-list" id="auditList">

        <?php
        $auditStmt = $pdo->prepare("
            SELECT action, created_at 
            FROM admin_logs
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $auditStmt->execute();
        $auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($auditLogs as $log):
            $type = 'info';
            if (stripos($log['action'], 'approved') !== false) $type = 'success';
            if (stripos($log['action'], 'locked') !== false) $type = 'warn';
        ?>
            <div class="audit-item <?= $type ?>">
                <span><?= htmlspecialchars($log['action']) ?></span>
                <small><?= date("H:i", strtotime($log['created_at'])) ?></small>
            </div>
        <?php endforeach; ?>

    </div>
</div>

</br></br>
            
            <h2>Recent Users</h2>
            <div class="table-cont">
            <?php if (empty($recentUsers)): ?>
                <p>No users found.</p>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Joined On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>


  <!-- ================= System Health Card ================= -->
<div class="health-widget">

    <div class="health-header">🩺 System Health</div>

    <div class="health-body">

        <div class="health-row">
            <span>Server Status</span>
            <strong id="serverStatus" class="ok">🟢 <?= $serverStatus ?></strong>
        </div>

        <div class="health-row">
            <span>Database</span>
            <strong id="dbStatus" class="<?= $dbStatus == 'Connected' ? 'ok' : 'warn' ?>">
                <?= $dbStatus == 'Connected' ? '🟢' : '🔴' ?> <?= $dbStatus ?>
            </strong>
        </div>

        <div class="health-row">
            <span>Failed logins today</span>
            <strong id="failedCount" class="warn"><?= $failedLogins ?></strong>
        </div>

        <div class="health-row">
            <span>Active users now</span>
            <strong id="activeUsers" class="info"><?= $activeUsers ?></strong>
        </div>

    </div>

</div>


                </div>
            <?php endif; ?>
        </div>
    </main>




    
    <!-- Inside your Admin Dashboard (dashboard.php) -->
</div>

<script>
function updateAudit(){
    fetch('audit_api.php')
        .then(res => res.json())
        .then(data => {
            let html = '';

            data.forEach(log => {
                let type = 'info';
                if (log.action.toLowerCase().includes('approved')) type = 'success';
                if (log.action.toLowerCase().includes('locked')) type = 'warn';

                html += `
                    <div class="audit-item ${type}">
                        <span>${log.action}</span>
                        <small>${new Date(log.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</small>
                    </div>
                `;
            });

            document.getElementById('auditList').innerHTML = html;
        });
}

setInterval(updateAudit, 5000);
updateAudit();
</script>


<script>
document.addEventListener("DOMContentLoaded", () => {

    document.querySelectorAll('.stat-card p').forEach(counter => {

        const raw = counter.innerText.trim();
        const number = parseFloat(raw.replace(/[^0-9.]/g, ''));
        const prefix = raw.replace(/[0-9.,]/g, '');

        let current = 0;
        const duration = 1200;
        const startTime = performance.now();

        const width = counter.offsetWidth;
        counter.style.minWidth = width + "px";

        function animate(now){
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);

            const eased = 1 - Math.pow(1 - progress, 3);
            current = number * eased;

            counter.textContent = prefix + Math.floor(current).toLocaleString();

            if(progress < 1){
                requestAnimationFrame(animate);
            } else {
                counter.textContent = prefix + number.toLocaleString();
            }
        }

        requestAnimationFrame(animate);
    });

});
</script>


<script>
const users = <?php echo json_encode($latestUsers); ?>;
let index = 0;
const box = document.getElementById("fadeUser");

function showUser() {
    box.classList.remove("show");

    setTimeout(() => {
        const u = users[index];

        let accountText = "";
        if (u.account_number && u.account_number !== "") {
           accountText = ` - <span style="color:#dc2626; font-weight:600">${u.account_number}</span>`;

        }

        box.innerHTML = `
            👤 <b>${u.full_name}</b>${accountText}<br>
            <span style="font-weight:500;color:#4f46e5">${u.email}</span>
        `;

        box.classList.add("show");
        index = (index + 1) % users.length;
    }, 600);
}



showUser();
setInterval(showUser, 3200);
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {

    document.querySelectorAll('.stat-card p').forEach(counter => {

        const raw = counter.innerText.trim();
        const number = parseFloat(raw.replace(/[^0-9.]/g, ''));
        const prefix = raw.replace(/[0-9.,]/g, '');

        let current = 0;
        const duration = 1200; // ms
        const startTime = performance.now();

        // Fix width to prevent layout jumping
        const width = counter.offsetWidth;
        counter.style.minWidth = width + "px";
        counter.style.display = "inline-block";

        function animate(now){
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Smooth easing
            const eased = 1 - Math.pow(1 - progress, 3);
            current = number * eased;

            counter.textContent = prefix + Math.floor(current).toLocaleString();

            if(progress < 1){
                requestAnimationFrame(animate);
            } else {
                counter.textContent = prefix + number.toLocaleString();
            }
        }

        requestAnimationFrame(animate);
    });

});
</script>

<script>
function updateSecurityActivity(){
    fetch('security_activity_api.php')
        .then(res => res.json())
        .then(data => {
            let html = '';

            data.forEach(row => {
                html += `
                <div class="neon-activity-item">
                    <div class="pulse-ring"></div>
                    <div class="activity-text">
                        <h4>${row.action}</h4>
                        <p>${row.details}</p>
                        <span>${new Date(row.created_at).toLocaleString()}</span>
                    </div>
                </div>
                `;
            });

            document.getElementById('securityActivity').innerHTML = html;
        });
}

setInterval(updateSecurityActivity, 5000);
updateSecurityActivity();
</script>









<script>
const adminName = "Yogesh"; // replace dynamically from PHP if needed

const messages = [
    `🏦 Welcome, <b>${adminName}</b> — Command the Power of Nexus Bank ⚡`,
    `🛡️ ${adminName}, Manage Users • Secure Transactions • Drive Innovation`,
    `🚀 Nexus Admin Console — Total Control | Smart Banking | Elite Security`,
    `⚙️ ${adminName}, You Are Running the Future of Banking`,
    `💼 Secure Decisions | Fast Operations | Complete Financial Oversight`,

    `🔐 Advanced Security Enabled — Protecting Every Transaction`,
    `📊 Real-Time Analytics — Monitor Bank Performance Instantly`,
    `👥 User Management Hub — Control, Verify & Secure Accounts`,
    `💳 Transaction Control Center — Speed, Accuracy & Trust`,
    `🧠 Smart Banking Powered by Intelligent Systems`,
    `📈 Financial Growth Starts With Smart Administration`,
    `🛠️ Admin Tools Ready — Optimize, Monitor, Execute`,
    `⚡ High-Speed Banking Operations at Your Command`,
    `🧾 Audit Logs Active — Every Action Accounted For`,
    `🌐 Nexus Bank — Where Technology Meets Trust`,
    `🚨 Fraud Detection Systems Standing Guard`,
    `🔎 Complete Visibility — No Transaction Left Untracked`,
    `🏆 Excellence in Digital Banking Administration`,
    `📡 Live System Status — Everything Running Smoothly`,
    `🔑 Admin Authority Granted — Full System Access`,
    `💼 Professional Control for a Professional Banker`,
    `🚀 Building the Future of Secure Digital Finance`,
    `🧑‍💻 Developer-Friendly | Admin-Powered | User-Secured`
];

let i = 0;
const msgEl = document.getElementById("adminMessage");

function rotateMessage() {
    msgEl.innerHTML = messages[i];
    i = (i + 1) % messages.length;
}

rotateMessage();
setInterval(rotateMessage, 6000);
</script>

<script>
function openLogoutModal() {
    document.getElementById("logoutModal").style.display = "block";
}

function closeLogoutModal() {
    document.getElementById("logoutModal").style.display = "none";
}

function confirmLogout() {
    window.location.href = "logout.php";
}
</script>


<script>
let scrollTimer;

function handleScroll(){
    document.body.classList.add('scrolling');
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
        document.body.classList.remove('scrolling');
    }, 800);
}

window.addEventListener('wheel', handleScroll, { passive: true });
window.addEventListener('touchmove', handleScroll, { passive: true });
window.addEventListener('scroll', handleScroll, { passive: true });
</script>
<script>
const sidebar = document.querySelector('.sidebar');
let timer;

sidebar.addEventListener('scroll', () => {
    sidebar.classList.add('show-scrollbar');
    clearTimeout(timer);
    timer = setTimeout(() => {
        sidebar.classList.remove('show-scrollbar');
    }, 700);
});
</script>

<script>
function updateSystemHealth(){
    fetch('system_health_api.php')
        .then(res => res.json())
        .then(data => {
            document.getElementById('dbStatus').innerHTML =
                (data.db === 'Connected' ? '🟢 ' : '🔴 ') + data.db;

            document.getElementById('failedCount').textContent = data.failed;
            document.getElementById('activeUsers').textContent = data.active;
        });
}

setInterval(updateSystemHealth, 5000);
updateSystemHealth();
</script>


<script>
    // Function to play sound
function playNotificationSound() {
    var sound = document.getElementById('notificationSound');
    sound.play().catch(e => console.log('Audio play prevented:', e));
}

// Example: checking new notifications
function checkNotifications() {
    fetch('notification.php') // your endpoint
        .then(response => response.json())
        .then(data => {
            if (data.newNotification) { // adjust according to your response
                // Show notification visually
                alert(data.message); // or update UI element
                // Play sound
                playNotificationSound();
            }
        });
}

// Check every 10 seconds (adjust as needed)
setInterval(checkNotifications, 10000);

</script>


</body>
</html>