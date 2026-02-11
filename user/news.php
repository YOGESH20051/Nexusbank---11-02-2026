<?php
session_start();

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session_manager.php';

redirectIfNotLoggedIn();

/* ===========================
   AUDIT: USER OPENED NEWS PAGE
=========================== */
logAdminAction(
    $pdo,
    $_SESSION['user_id'],
    "VIEW NEWS",
    "User opened bank announcements page"
);

/* ===========================
   FETCH NEWS
=========================== */
$news = $pdo->query("SELECT * FROM bank_news ORDER BY created_at DESC");

/* ===========================
   USER PROFILE
=========================== */
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

$profilePic = !empty($user['profile_picture'])
    ? '../uploads/' . $user['profile_picture']
    : '../assets/images/default-avatars.png';

$error = '';
$success = '';
?>


<style>
    .news-header {
    margin-bottom: 25px;
}

.news-header h2 {
    font-size: 26px;
    font-weight: 800;
    color: #2d2f55;
}

.news-subtitle {
    color: #6b7280;
    margin-top: 5px;
}

.news-card {
    display: flex;
    background: #fff;
    border-radius: 14px;
    margin-bottom: 18px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    overflow: hidden;
    transition: 0.3s;
}

.news-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.1);
}

.news-left-border {
    width: 6px;
    background: linear-gradient(180deg,#4f46e5,#3b82f6);
}

.news-content {
    padding: 18px 22px;
    flex: 1;
}

.news-content h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 8px;
}

.news-content p {
    font-size: 15px;
    color: #374151;
    line-height: 1.6;
}

.news-footer {
    margin-top: 12px;
    font-size: 13px;
    color: #6b7280;
}

.sidebar hr {
    width: 80%;
    margin: 15px auto;
    border: none;
    border-top: 1px solid #e2e6ef;
}


.news-marquee {
    width: 100%;
    overflow: hidden;
    white-space: nowrap;
    margin-bottom: 15px;
    color: #6b7280;
    font-size: 13px;
    position: relative;
}

.news-marquee span {
    display: inline-block;
    padding-left: 100%;
    animation: marquee 15s linear infinite;
}

@keyframes marquee {
    from { transform: translateX(0); }
    to   { transform: translateX(-100%); }
}
.news-marquee{
    position: fixed;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    width: 85%;
    max-width: 1000px;
    background: linear-gradient(90deg, #fff7cc, #fffbea, #fff7cc);  /* mild yellow */
    border-radius: 40px;
    padding: 12px 0;
    box-shadow: 0 6px 18px rgba(0,0,0,0.15);
    overflow: hidden;
    z-index: 999;
}

.news-marquee span{
    display: inline-block;
    white-space: nowrap;
    color: #891414ff;
    font-weight: 600;
    font-size: 14px;
    padding-left: 100%;
    animation: runText 20s linear infinite;
}

@keyframes runText{
    from { transform: translateX(0); }
    to   { transform: translateX(-100%); }
}





</style>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SecureBank - Investments</title>
    <link rel="stylesheet" href="../assets/css/investment.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    
    <!-- NAVIGATION EFFECTS -->
    <script src="../assets/js/navhover.js"></script>
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
                    <nav>
                    <a href="dashboard.php" class="btn">
                        <img 
                        src="../assets/images/inactive-dashboard.png" 
                        alt="dashboard-logo" 
                        class="nav-icon"
                        data-default="../assets/images/inactive-dashboard.png"
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
                        data-default="../assets/images/voice_active.png"
                        data-hover="../assets/images/voice_active.png"
                        > 
                        Voice Banking
                    </a>

                    <a href="news.php" class="btn dash-text">
                        <img 
                        src="../assets/images/newsactive-logo.png" 
                        alt="news-logo" 
                        class="nav-icon"
                        data-default="../assets/images/newsactive-logo.png"
                        data-hover="../assets/images/newsactive-logo.png"
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

        

               
</body>
</html>





<main class="container">
<header>
    <h1>News</h1>
    <button class="hamburger">&#9776;</button>
</header>

<div class="content">

    <div class="news-header">
        <h2>🏦 Latest Bank Updates</h2>
        <div class="news-marquee">
    <span>Share important updates and announcements with all bank users. | Stay informed with the latest bank updates, features, and important notices — all in one place.</span>
</div>
</br></br></br>
    <?php while ($row = $news->fetch(PDO::FETCH_ASSOC)): ?>
    <div class="news-card">

        <div class="news-left-border"></div>

        <div class="news-content">
            <h3><?= htmlspecialchars($row['title']) ?></h3>

            <p><?= nl2br(htmlspecialchars($row['content'])) ?></p>

            <div class="news-footer">
                🕒 <?= date("d M Y, h:i A", strtotime($row['created_at'])) ?>
            </div>
        </div>

    </div>
    <?php endwhile; ?>

</div>
</main>
