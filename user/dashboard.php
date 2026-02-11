<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session_manager.php';

// Check session status
checkSessionStatus();

// Set the timeout duration (15 minutes in seconds)
$timeoutDuration = 900;  // 15 minutes

// Set the logout redirect URL
$logoutRedirectUrl = '../logout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if session has expired
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutDuration) {
    // If session expired, log out user
    session_unset();
    session_destroy();
    header("Location: $logoutRedirectUrl"); // Redirect to logout page
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

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

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT * FROM transactions 
    WHERE account_id = (SELECT account_id FROM accounts WHERE user_id = ?)
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll();

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


// das message
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT message
    FROM admin_messages
    WHERE is_active = 1
    AND (
        message_type = 'broadcast'
        OR (message_type = 'user' AND user_id = ?)
    )
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$activeMessages = $stmt->fetchAll(PDO::FETCH_COLUMN);


$userId = $_SESSION['user_id'];

/* Latest 5 notifications (for dashboard preview) */
$stmt = $pdo->prepare("
    SELECT *
    FROM user_notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

/* Unread count (for bell badge) */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM user_notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();
// Fetch user's profile information




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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus-Banksystem - Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="../assets/js/session.js"></script>

    <!-- Apexchart -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- NAVIGATION EFFECTS -->
    <script src="../assets/js/navhover.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <style>
    
       .transaction-distribution-chart, .weekly-activity-chart, .balance-over-time-chart {
            background: #fff;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }

        .btn-download {
            padding: 6px 12px;
            background: white;
            color: #706EFF;
            border: 1px solid #706EFF;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-download:hover {
            background-color: #706EFF;
            color: white;
        }

        .transactions-tabs {
            margin-bottom: 20px;
        }

        .transactions-tabs .tab {
            padding: 8px 16px;
            margin-right: 10px;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 14px;
            transition: color 0.3s;
        }

        .transactions-tabs .tab:hover {
            color: #706EFF;
        }

        .transactions-tabs .tab.active {
            color: #706EFF;
            font-weight: bold;
            position: relative;
        }

        .transactions-tabs .tab.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #706EFF;
        }

        .transactions-table-wrapper {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .voice-btn {
  background: #6359e9;
  color: white;
  padding: 12px 18px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
}
.ai-suggestion {
    margin-top: 12px;
    background: #f5f6ff;
    border-left: 4px solid #6359e9;
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
    color: #343C6A;
    overflow: hidden;   /* hide overflow text */
}

.ai-marquee {
    display: inline-block;
    white-space: nowrap;
    animation: moveRight 12s linear infinite;
}


@keyframes moveRight {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(100%);
    }
}


.welcome-marquee {
    display: inline-block;
    white-space: nowrap;
    animation: moveLeft 25s linear infinite;
}

@keyframes moveLeft {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(-100%);
    }
}

..txn-slider {
    margin-top: 14px;
    padding: 10px 14px;
    height: 46px;
    background: #f5f6ff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    overflow: hidden;
    box-shadow: inset 0 0 0 1px rgba(112,110,255,0.15);
}

.txn-item {
    font-size: 14px;
    font-weight: 600;
    color: #343C6A;
    animation: fadeSlide 3s ease-in-out forwards;
    white-space: nowrap;
}

@keyframes fadeSlide {
    0% { opacity: 0; transform: translateY(6px); }
    20% { opacity: 1; transform: translateY(0); }
    80% { opacity: 1; }
    100% { opacity: 0; transform: translateY(-6px); }
}

.dash-acc-wrapper {
    overflow: visible !important;
}

.sidebar hr {
    width: 80%;
    margin: 15px auto;
    border: none;
    border-top: 1px solid #e2e6ef;
}

.voice-box {
    max-width: 420px;
    margin: 40px auto;
    padding: 25px 30px;
    background: linear-gradient(135deg, #6359e9, #4f46e5);
    color: #fff;
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

.voice-box h3 {
    margin-bottom: 20px;
    font-size: 20px;
    letter-spacing: 0.5px;
}

#voiceBtn {
    background: #ffffff;
    color: #4f46e5;
    border: none;
    padding: 14px 28px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.2);
}

#voiceBtn:hover {
    background: #e0e7ff;
    transform: scale(1.05);
}

#voiceBtn.listening {
    background: #22c55e;
    color: #fff;
    box-shadow: 0 0 20px rgba(34, 197, 94, 0.8);
    animation: pulse 1.4s infinite;
}

#voice-text {
    margin-top: 18px;
    font-size: 15px;
    background: rgba(255, 255, 255, 0.15);
    padding: 12px 15px;
    border-radius: 12px;
    min-height: 40px;
}

/* Mic pulse animation */
@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
    }
    70% {
        box-shadow: 0 0 0 18px rgba(34, 197, 94, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
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

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    padding: 15px 20px;
}

/* Right Side Actions */
.header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* Bell Wrapper */
.notif-wrapper {
    position: relative;
}

/* Hamburger Button */
.hamburger {
    font-size: 26px;
    border: none;
    background: transparent;
    cursor: pointer;
}

.notif-popup {
    position: absolute;
    top: 50px;
    right: 0;
    width: 340px;
    max-height: 360px;
    overflow-y: auto;

    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(12px);

    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.4);

    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);

    padding: 15px;
    display: none;
    z-index: 9999;
}

/* Notification Item */
.popup-item {
    position: relative;
    padding: 14px 40px 14px 14px;
    margin-bottom: 12px;
    border-radius: 14px;

    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(220, 220, 220, 0.6);

    transition: 0.25s;
}

.popup-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
}

/* Unread */
.popup-item.unread {
    border-left: 5px solid #007bff;
    font-weight: 600;
}

/* Delete Button */
.delete-btn {
    position: absolute;
    top: 10px;
    right: 12px;

    width: 22px;
    height: 22px;
    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: rgba(255, 0, 0, 0.15);
    color: red;
}

.delete-btn:hover {
    background: rgba(255, 0, 0, 0.3);
}

.notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.mark-all-btn {
    margin-left: auto;
    background: linear-gradient(135deg, #007bff, #00c6ff);
    color: white;
    border: none;

    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 12px;

    cursor: pointer;
    transition: 0.3s ease;
}

.mark-all-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 15px rgba(0, 123, 255, 0.35);
}


    </style>
    
    <!--Google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

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

                <nav>
                    <a href="dashboard.php" class="btn dash-text">
                        <img 
                        src="../assets/images/hover-dashboard.png" 
                        alt="dashboard-logo" 
                        class="nav-icon "
                        data-default="../assets/images/hover-dashboard.png"
                        data-hover="../assets/images/hover-dashboard.png"
                        > 
                        Dashboard
                    </a>

                    <a href="deposit.php" class="btn">
                        <img 
                        src="../assets/images/inactive-deposit.png" 
                        alt="deposit-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-deposit.png"
                        data-hover="../assets/images/hover-deposit.png"
                        > 
                        Deposit
                    </a>

                    <a href="withdraw.php" class="btn">
                        <img 
                        src="../assets/images/inactive-withdraw.png" 
                        alt="withdraw-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-withdraw.png"
                        data-hover="../assets/images/hover-withdraw.png"
                        > 
                        Withdraw
                    </a>

                    <a href="transfer.php" class="btn">
                        <img 
                        src="../assets/images/inactive-transfer.png" 
                        alt="transfer-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-transfer.png"
                        data-hover="../assets/images/hover-transfer.png"
                        > 
                        Transfer
                    </a>

                    <a href="upi.php" class="btn">
                        <img 
                        src="../assets/images/voice.png" 
                        alt="upi-logo" 
                        class="nav-icon"
                        data-default="../assets/images/voice.png"
                        data-hover="../assets/images/voice_active.png"
                        > 
                        UPI Payments
                    </a>

                    <a href="transactions.php" class="btn">
                        <img 
                        src="../assets/images/inactive-transaction.png" 
                        alt="transactions-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-transaction.png"
                        data-hover="../assets/images/hover-transaction.png"
                        > 
                        Transactions
                    </a>

                    <a href="investment.php" class="btn">
                        <img 
                        src="../assets/images/inactive-investment.png" 
                        alt="investment-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-investment.png"
                        data-hover="../assets/images/hover-investment.png"
                        > 
                        Investment
                    </a>

                    <a href="loan.php" class="btn">
                        <img 
                        src="../assets/images/inactive-loans.png" 
                        alt="loans-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-loans.png"
                        data-hover="../assets/images/hover-loans.png"
                        > 
                        Loans
                    </a>

                     <a href="voicebank.php" class="btn">
                        <img 
                        src="../assets/images/voice.png" 
                        alt="voice-logo" 
                        class="nav-icon"
                        data-default="../assets/images/voice.png"
                        data-hover="../assets/images/voice_active.png"
                        > 
                        Voice Banking
                    </a>
                

                    <a href="news.php" class="btn">
                        <img 
                        src="../assets/images/news-logo.png" 
                        alt="news-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-loans.png"
                        data-hover="../assets/images/news-loans.png"
                        > 
                        News
                    </a>

                    <a href="profile.php" class="btn">
                        <img 
                        src="../assets/images/inactive-profile.png" 
                        alt="loans-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-profile.png"
                        data-hover="../assets/images/inactive-profile"
                        > 
                        Settings
                    </a>


                    
                </nav>      
                <hr>
                            <div class="logout-cont">
                                 <a href="../logout.php" class="logout">Logout</a>
                            </div>

                            
                </aside>

                <main class="container">
                   <header class="dashboard-header">
    <h1>Overview</h1>

    <!-- Right Side Actions -->
    <div class="header-actions">

        <!-- Bell Icon -->
        <div class="notif-wrapper">
            <div id="bellIcon" class="notif-bell">
                🔔
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </div>

            <!-- Notification Popup -->
            <div id="notifPopup" class="notif-popup">
    <div class="notif-header">
        <h4>🔔 Notifications</h4>
        <?php if ($unreadCount > 0): ?>
            <button class="mark-all-btn" onclick="markAllRead()">Mark All Read</button>
        <?php endif; ?>
    </div>

    <!-- NEW WRAPPER FOR NOTIFICATION ITEMS -->
    <div id="notifItems">
        <?php if (empty($notifications)): ?>
            <p class="no-notif">No notifications</p>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="popup-item <?= $n['is_read'] ? 'read' : 'unread' ?>" id="notif-<?= $n['id'] ?>">
                    <div onclick="markAsRead(<?= $n['id'] ?>, this.parentElement)">
                        <?= htmlspecialchars($n['message']) ?>
                        <small><?= date('d M h:i A', strtotime($n['created_at'])) ?></small>
                    </div>
                    <span class="delete-btn" onclick="deleteNotif(<?= $n['id'] ?>, event)">✖</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


        <!-- Hamburger Button -->
        <button class="hamburger">&#9776;</button>

    </div>
</header>

<audio id="notificationSound" src="../assets/sounds/notification.mp3" preload="auto"></audio>




                    
                    <div class="dashboard-content">

                    <div class="dash-acc-wrapper">
                        <div class="account-summary">
                             <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
                            <h2>Account Summary</h2>
                            <p>Account Number: <?= htmlspecialchars($user['account_number']) ?></p>
                            <p class="balance">Balance: <span id="accountBalance"> ₹ <?= number_format($user['balance'], 2) ?></span></p>
                            <br>
                            <?php
$balance = $user['balance'];

if ($balance < 500) {
    $suggestion = "Your balance is low. Avoid large transfers today.";
} elseif ($balance < 2000) {
    $suggestion = "Consider saving a small amount this month.";
} else {
    $suggestion = "Great! You are eligible to explore investments.";
}
?>
<br>
<div class="ai-suggestion fade-in">
    <div class="ai-marquee">
        🤖 <strong>Smart Suggestion:</strong>
        <span><?= $suggestion ?></span>
    </div>
</div>

<br>

                        </div>





                        <div class="quick-actions">
                            <h2>Quick Actions</h2>
                            <div class="action-buttons">      
                                <a href="deposit.php" class="btn1">Deposit Money</a>
                                <a href="withdraw.php" class="btn1">Withdraw Funds</a>
                                <a href="transfer.php" class="btn1">Transfer Funds</a>
                                <a href="loan.php" class="btn1">Apply for Loan</a>
                                <a href="loan-payment.php" class="btn1">Pay Loan</a>
                                <a href="profile.php" class="btn1">Profile</a>
                                <a href="news.php">🏦 Latest Bank News</a>

                                <a href="login-records.php" class="btn1">Login Records</a>
                                
                                  
                        </div>  
                        <br>
                        <div class="txn-slider">
    <div id="txnContainer">
        <?php foreach ($transactions as $t): ?>
            <span class="txn-data"
                  data-type="<?= $t['type'] ?>"
                  data-amount="<?= $t['amount'] ?>"
                  data-desc="<?= htmlspecialchars($t['description'] ?? '') ?>"
                  data-date="<?= date('d M, g:i A', strtotime($t['created_at'])) ?>">
            </span>
        <?php endforeach; ?>
    </div>
</div>


                            </div>
                        </div>
                        
                       
    <div class="welcome-marquee">
        
        <span>🏦 Welcome to <b>Nexus Bank</b> — Experience the Power of Smart & Secure Banking 🚀 | Fast Transfers | Zero Hassle | Advanced Security | Smart Loans | Seamless Payments | Powerful Analytics | Total Financial Control | Innovation You Can Trust | Banking Without Limits |</span>
    </div>
</div>


<!-- Admin Welcome Message -->
<div class="admin-message-card alt-style">
    <span id="adminMessage"></span>
</div>


                        <h2>Recent Transactions</h2>
                        <div class="transactions-container">
                            
                                        <div class="transactions-tabs">
                                            <button class="tab active" data-type="all">All Transactions</button>
                                            <button class="tab" data-type="deposit">Deposit</button>
                                            <button class="tab" data-type="withdrawal">Withdraw</button>
                                            <button class="tab" data-type="transfer">Transfer</button>
                                        </div>

                                        <div class="transactions-table-wrapper">
                                            <table class="transactions-table">
                                            <thead>
                                                <tr>
                                                <th></th>
                                                <th>Transaction ID</th>
                                                <th>Description</th>
                                                <th>Type</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Receipt</th>
                                                </tr>
                                            </thead>
                                            <tbody id="transactionsTableBody">
                                                <?php foreach ($transactions as $txn): ?>
                                                <tr class="transaction-row" data-type="<?= htmlspecialchars($txn['type']) ?>">
                                                <!-- arrow icon -->
                                                <td class="icon" style="width: 32px; text-align: center;">
                                                    <?php if (in_array($txn['type'], ['deposit','transfer_in'])): ?>
                                                        <img src="../assets/images/Trans-up.png" alt="arrow Up" style="width: 30px; height: 30px; display: inline-block;">
                                                    <?php else: ?>
                                                        <img src="../assets/images/Trans-down.png" alt="arrow down" style="width: 30px; height: 30px; display: inline-block;">
                                                    <?php endif; ?>
                                                </td>

                                                <td><?= htmlspecialchars($txn['transaction_id']) ?></td>
                                                <td><?= htmlspecialchars($txn['description'] ?? '') ?></td>
                                                <td><?= ucfirst($txn['type']) ?></td>
                                                <td><?= date('j M, g:i A', strtotime($txn['created_at'])) ?></td>
                                                <td class="amount <?= in_array($txn['type'],['deposit','transfer_in'])? 'positive':'negative' ?>">
                                                    <?= (in_array($txn['type'],['deposit','transfer_in'])? '+':'−') .
                                                        '₹'.number_format($txn['amount'],2) ?>
                                                </td>
                                                <td>
                                                    <button onclick="window.open('generate_receipt.php?transaction_id=<?= htmlspecialchars($txn['transaction_id']) ?>', '_blank')" 
                                                            class="btn-download">Download</button>
                                                </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            </table>
                                        </div>
                                </div>


                        <div class="weekly-activity-chart">
                            <h2>Weekly Activity </h2>
                            <div id="chart"></div>
                        </div>

                        <div class="transaction-distribution-chart">
                            <h2>Expense Statistics</h2>
                            <div id="pieChart"></div>
                        </div>

                        <div class="balance-over-time-chart">
                            <h2>Balance Over Time</h2>
                            <div id="balancechart"></div>
                        </div>
                    </div>
                </main>
    </div>

    <script>
         // Weekly Activiy Column Chart
 document.addEventListener("DOMContentLoaded", function () {
    fetch('get_weekly_activity.php')
        .then(response => response.json())
        .then(data => {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const deposits = Array(7).fill(0);
            const withdrawals = Array(7).fill(0);
            const transferIn = Array(7).fill(0);
            const transferOut = Array(7).fill(0);
            const loanPayments = Array(7).fill(0);

            data.forEach(entry => {
                const index = days.indexOf(entry.day);
                if (index !== -1) {
                    deposits[index] = parseFloat(entry.total_deposit);
                    withdrawals[index] = parseFloat(entry.total_withdraw);
                    transferIn[index] = parseFloat(entry.total_transfer_in);
                    transferOut[index] = parseFloat(entry.total_transfer_out);
                    loanPayments[index] = parseFloat(entry.total_loanpayment);
                }
            });

            const options = {
                chart: {
                    type: 'bar',
                    height: 400
                },
                title: {
                    text: ' '
                },
                xaxis: {
                    categories: days
                },
                yaxis: {
                    title: {
                        text: 'Amount (₹)'
                    }
                },
                series: [
                    {
                        name: 'Deposits',
                        data: deposits
                    },
                    {
                        name: 'Withdrawals',
                        data: withdrawals
                    },
                    {
                        name: 'Transfers In',
                        data: transferIn
                    },
                    {
                        name: 'Loan Payments',
                        data: loanPayments
                    },
                    {
                        name: 'Transfers Out',
                        data: transferOut
                    }
                ],
                colors: ['#706EFF', '#343C6A', '#00B8D9', '#FF6F61', '#FF9800'],
                dataLabels: {
                    enabled: false
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        endingShape: 'rounded'
                    }
                },
                legend: {
                    position: 'bottom',
                    horizontalAlign: 'center',
                    offsetX: 40
                },
                fill: {
                    opacity: 1
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function (val) {
                            return "₹" + val.toFixed(2);
                        }
                    }
                },
                grid: {
                    borderColor: '#e0e0e0',
                    strokeDashArray: 4,
                    xaxis: {
                        lines: {
                            show: true
                        }
                    },
                    yaxis: {
                        lines: {
                            show: true
                        }
                    }
                },
                responsive: [{
                    breakpoint: 480,
                    options: {
                        legend: {
                            position: 'bottom',
                            offsetX: -10,
                            offsetY: 0
                        }
                    }
                }]



            };

            const chart = new ApexCharts(document.querySelector("#chart"), options);
            chart.render();
        })
        .catch(error => {
            console.error('Error loading chart data:', error);
        });
});



        // Transaction Type Distribution Pie Chart
        document.addEventListener("DOMContentLoaded", function () {
        fetch('get_transaction_distribution.php')
            .then(response => response.json())
            .then(data => {
                const labels = [];
                const values = [];

                data.forEach(entry => {
                    let label = '';
                    switch (entry.type) {
                        case 'deposit': label = 'Deposit'; break;
                        case 'transfer_out': label = 'Transfer'; break;
                        case 'withdrawal': label = 'Withdraw'; break;
                        case 'loanpayment': label = 'Loan Payment'; break;
                        default: label = entry.type;
                    }
                    labels.push(label);
                    values.push(parseFloat(entry.total));
                });

                if (values.length === 0) {
                    document.querySelector("#pieChart").innerHTML = "<p>No transaction data available.</p>";
                    return;
                }

                const options = {
                    chart: {
                        type: 'pie',
                        height: 350
                    },
                    series: values,
                    labels: labels,
                    title: {
                        text: ' '
                    },
                    colors: ['#16DBCC', '#0052CC', '#5243AA', '#00B8D9'],
                };

                const pieChart = new ApexCharts(document.querySelector("#pieChart"), options);
                pieChart.render();
            })
            .catch(error => {
                console.error('Error loading pie chart data:', error);
            });
    });

    // Balance Over Time Area Chart
    document.addEventListener("DOMContentLoaded", function () {
        fetch('get_balance_history.php')
            .then(response => response.json())
            .then(balanceData => {
                const options = {
                    chart: {
                        type: 'area',
                        height: 450,
                        width: '100%',
                        toolbar: {
                            show: false
                        },
                        zoom: {
                            enabled: true
                        }
                    },
                    series: [{
                        name: 'Account Balance',
                        data: balanceData
                    }],
                    title: {
                        text: '',
                        style: {
                            fontSize: '20px'
                        }
                    },
                    colors: ['#706EFF'],
                    stroke: {
                        width: 3,
                        curve: 'smooth'
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.7,
                            opacityTo: 0.2,
                            stops: [0, 90, 100]
                        }
                    },
                    xaxis: {
                        type: 'datetime',
                        labels: {
                            datetimeUTC: false,
                            format: 'MMM dd, HH:mm',
                            style: {
                                fontSize: '12px'
                            }
                        },
                        title: {
                            text: 'Date & Time',
                            style: {
                                fontSize: '14px'
                            }
                        }
                    },
                    yaxis: {
                        title: {
                            text: 'Balance (₹)',
                            style: {
                                fontSize: '14px'
                            }
                        },
                        labels: {
                            formatter: function(value) {
                                return '₹' + value.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    },
                    tooltip: {
                        x: {
                            format: 'MMM dd, yyyy HH:mm'
                        },
                        y: {
                            formatter: function(value) {
                                return '₹' + value.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    },
                    grid: {
                        borderColor: '#e0e0e0',
                        strokeDashArray: 4,
                        xaxis: {
                            lines: {
                                show: true
                            }
                        },
                        yaxis: {
                            lines: {
                                show: true
                            }
                        }
                    },
                    markers: {
                        size: 4,
                        colors: ['#706EFF'],
                        strokeColors: '#fff',
                        strokeWidth: 2,
                        hover: {
                            size: 6
                        }
                    }
                };

                const chart = new ApexCharts(document.querySelector("#balancechart"), options);
                chart.render();
            })
            .catch(error => {
                console.error('Error loading balance history:', error);
                document.querySelector("#balancechart").innerHTML = "<p>Error loading balance history. Please try again later.</p>";
            });
    });



    
    </script>
    



    <!-- <script src="../assets/js/Userdash.js"></script> -->
    <script>
    // Add this before your existing scripts
    document.addEventListener('DOMContentLoaded', function() {
        // Get all tabs and transaction rows
        const tabs = document.querySelectorAll('.transactions-tabs .tab');
        const rows = document.querySelectorAll('.transaction-row');

        // Add click event listener to each tab
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                tabs.forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');

                // Get the type of transactions to show
                const type = this.getAttribute('data-type');

                // Show/hide rows based on type
                rows.forEach(row => {
                    const rowType = row.getAttribute('data-type');
                    if (type === 'all') {
                        row.style.display = '';
                    } else if (type === 'transfer') {
                        // Show both transfer_in and transfer_out for transfer tab
                        if (rowType === 'transfer_in' || rowType === 'transfer_out') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    } else {
                        row.style.display = rowType === type ? '' : 'none';
                    }
                });
            });
        });
    });

    // Your existing scripts...
</script>
<script src="../assets/js/session.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {

    const messages = <?= json_encode($activeMessages) ?>;
    const msgEl = document.getElementById("adminMessage");
    let i = 0;

    if (!messages.length) {
        msgEl.innerHTML = "⚠️ No admin messages available";
        return;
    }

    function rotateMessage() {
        msgEl.innerHTML = messages[i];
        i = (i + 1) % messages.length;
    }

    rotateMessage();
    setInterval(rotateMessage, 6000);
});
</script>


<audio id="notificationSound" src="../assets/sounds/notification.mp3" preload="auto"></audio>

<script>

    
const bell = document.getElementById("bellIcon"); // bell icon
const popup = document.getElementById("notifPopup");
const sound = document.getElementById("notificationSound");

let lastNotificationId = 0;
let soundEnabled = false;

// ====== Unlock audio after first interaction ======
function enableAudio() {
    sound.play().then(() => {
        sound.pause();
        sound.currentTime = 0;
        soundEnabled = true;
    }).catch(e => console.log('Audio unlock error:', e));

    window.removeEventListener('click', enableAudio);
    window.removeEventListener('touchstart', enableAudio);
}
window.addEventListener('click', enableAudio, { once: true });
window.addEventListener('touchstart', enableAudio, { once: true });

// ====== Initialize lastNotificationId from existing notifications ======
function initLastNotificationId() {
    const items = popup.querySelectorAll(".popup-item");
    if (items.length > 0) {
        lastNotificationId = Math.max(...Array.from(items).map(i => parseInt(i.id.replace('notif-', ''))));
    }
}
initLastNotificationId();

// ====== Play notification sound ======
function playNotificationSound() {
    if (!soundEnabled) return;
    sound.currentTime = 0;
    sound.play().catch(e => console.log('Notification sound error:', e));
}

// ====== Mark single notification as read ======
function markAsRead(id, element) {
    fetch('mark-as-read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.text())
    .then(() => {
        element.classList.remove('unread');
        element.classList.add('read');

        const badge = bell.querySelector(".notif-badge");
        if (badge) {
            let count = parseInt(badge.innerText);
            count = Math.max(0, count - 1);
            if (count === 0) badge.remove();
            else badge.innerText = count;
        }
    });
}

// ====== Mark all notifications as read ======
function markAllRead() {
    fetch('mark-all-read.php')
        .then(res => res.text())
        .then(() => {
            document.querySelectorAll('.popup-item.unread').forEach(el => {
                el.classList.remove('unread');
                el.classList.add('read');
            });

            const badge = bell.querySelector(".notif-badge");
            if (badge) badge.remove();
        });
}

// ====== Delete notification ======
function deleteNotif(id, event) {
    event.stopPropagation(); // prevent marking as read
    fetch('delete-notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const el = document.getElementById(`notif-${id}`);
            if (el) el.remove();
        } else {
            console.log('Delete error:', data.message);
        }
    })
    .catch(e => console.log(e));
}

// ====== Fetch notifications ======
function fetchNotifications() {
    fetch('fetch-notifications.php')
        .then(res => res.json())
        .then(data => {
            // Update badge
            let badge = bell.querySelector(".notif-badge");
            if (data.unreadCount > 0) {
                if (!badge) {
                    badge = document.createElement("span");
                    badge.className = "notif-badge";
                    bell.appendChild(badge);
                }
                badge.innerText = data.unreadCount;
            } else if (badge) badge.remove();

            // Add new notifications
            data.notifications.forEach(n => {
                if (n.id > lastNotificationId) {
                    lastNotificationId = n.id;

                    const div = document.createElement("div");
                    div.className = "popup-item " + (n.is_read ? "read" : "unread");
                    div.id = "notif-" + n.id;

                    const innerDiv = document.createElement("div");
                    innerDiv.textContent = n.message + " (" + new Date(n.created_at).toLocaleString() + ")";
                    innerDiv.addEventListener("click", () => markAsRead(n.id, div));

                    const delBtn = document.createElement("span");
                    delBtn.className = "delete-btn";
                    delBtn.textContent = "✖";
                    delBtn.addEventListener("click", (e) => deleteNotif(n.id, e));

                    div.appendChild(innerDiv);
                    div.appendChild(delBtn);

                    popup.prepend(div);

                    // Play sound for new notification
                    playNotificationSound();
                }
            });
        })
        .catch(e => console.log('Fetch notifications error:', e));
}

// Poll every 5 seconds
setInterval(fetchNotifications, 5000);

// ====== Toggle popup ======
bell.addEventListener('click', () => {
    popup.style.display = popup.style.display === 'block' ? 'none' : 'block';
});

// Close popup when clicking outside
document.addEventListener('click', (e) => {
    if (!bell.contains(e.target) && !popup.contains(e.target)) {
        popup.style.display = 'none';
    }
});
</script>



</body>
</html>

