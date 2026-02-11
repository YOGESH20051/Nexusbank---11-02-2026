<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session_manager.php';

redirectIfNotLoggedIn();

// 🧾 AUDIT — Voice Banking page accessed
logAdminAction(
    $pdo,
    $_SESSION['user_id'],
    'Voice Banking',
    'Opened voice banking interface'
);


$userId = $_SESSION['user_id'];
$error = '';
$success = '';


// Fetch user's account details
$stmt = $pdo->prepare("SELECT account_id, account_number, balance FROM accounts WHERE user_id = ?");
$stmt->execute([$userId]);

$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    die("Account not found for this user.");
}

$accountId     = $account['account_id'];
$accountNumber = $account['account_number'];
$balance       = $account['balance'];


// Fetch available investment plans
$stmt = $pdo->query("SELECT * FROM investment_plans ORDER BY duration_months ASC");
$plans = $stmt->fetchAll();

// Fetch user's account balance
$stmt = $pdo->prepare("SELECT account_id, account_number, balance FROM accounts WHERE user_id = ?");
$stmt->execute([$userId]);
$account = $stmt->fetch();
$balance = $account['balance'];

// ===== TOTAL MONTHLY SPENDING =====
$stmt = $pdo->prepare("
SELECT 
    (
        SELECT IFNULL(SUM(ABS(amount)),0)
        FROM transactions
        WHERE account_id = ?
        AND amount < 0
        AND MONTH(created_at) = MONTH(CURDATE())
        AND YEAR(created_at) = YEAR(CURDATE())
    )
    +
    (
        SELECT IFNULL(SUM(total_due + penalty_amount),0)
        FROM loans
        WHERE user_id = ?
        AND status = 'paid'
        AND MONTH(created_at) = MONTH(CURDATE())
        AND YEAR(created_at) = YEAR(CURDATE())
    )
    AS total_spending
");

$stmt->execute([$accountId, $userId]);
$monthlySpending = $stmt->fetchColumn();


// Handle new investment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'], $_POST['amount'])) {
    $planId = $_POST['plan_id'];
    $amount = (float) $_POST['amount'];

    $stmt = $pdo->prepare("SELECT * FROM investment_plans WHERE plan_id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        $error = "Invalid investment plan.";
    } elseif ($amount < $plan['min_amount']) {
        $error = "Amount must be at least ₹" . number_format($plan['min_amount'], 2);
    } elseif ($amount > $plan['max_amount']) {
        $error = "Amount cannot exceed ₹" . number_format($plan['max_amount'], 2);
    } elseif ($amount > $balance) {
        $error = "Insufficient balance.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE user_id = ?");
            $stmt->execute([$amount, $userId]);

            $stmt = $pdo->prepare("INSERT INTO investments (user_id, plan_id, amount, created_at, status) VALUES (?, ?, ?, NOW(), 'active')");
            $stmt->execute([$userId, $planId, $amount]);

            // Insert transaction record for investment
            $stmt = $pdo->prepare("SELECT account_id FROM accounts WHERE user_id = ?");
            $stmt->execute([$userId]);
            $account = $stmt->fetch();
            $accountId = $account['account_id'];

            $stmt = $pdo->prepare('INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, \'investment\', ?, ?, NOW())');
            $stmt->execute([$accountId, -$amount, 'Investment in ' . $plan['plan_name']]);

            $pdo->commit();
            $_SESSION['success'] = "Investment of ₹" . number_format($amount, 2) . " placed in " . htmlspecialchars($plan['plan_name']) . "!";
            header("Location: investment.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error placing investment: " . $e->getMessage();
        }
    }
}

// Handle withdrawal of matured investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_investment_id'])) {
    $investmentId = $_POST['withdraw_investment_id'];

    // Fetch the investment to withdraw
    $stmt = $pdo->prepare("
        SELECT inv.*, plans.interest_rate 
        FROM investments inv
        JOIN investment_plans plans ON inv.plan_id = plans.plan_id
        WHERE inv.investment_id = ? 
        AND inv.user_id = ? 
        AND inv.status = 'matured'
        AND inv.withdrawn_at IS NULL
    ");
    $stmt->execute([$investmentId, $userId]);
    $investment = $stmt->fetch();

    if ($investment) {
        try {
            $pdo->beginTransaction();

            $totalReturn = $investment['amount'] + ($investment['amount'] * $investment['interest_rate'] / 100);

            $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE user_id = ?");
            $stmt->execute([$totalReturn, $userId]);

            $stmt = $pdo->prepare("UPDATE investments SET withdrawn_at = NOW(), status = 'withdrawn' WHERE investment_id = ?");
            $stmt->execute([$investmentId]);

            // Insert transaction record for withdrawal of matured investment
            $stmt = $pdo->prepare("SELECT account_id FROM accounts WHERE user_id = ?");
            $stmt->execute([$userId]);
            $account = $stmt->fetch();
            $accountId = $account['account_id'];

            $stmt = $pdo->prepare('INSERT INTO transactions (account_id, type, amount, description, created_at) VALUES (?, \'withdrawal_matured_investment\', ?, ?, NOW())');
            $stmt->execute([$accountId, $totalReturn, 'Withdrawal of matured investment: ₹' . number_format($totalReturn, 2)]);

            $pdo->commit();
            $_SESSION['success'] = "Successfully withdrawn ₹" . number_format($totalReturn, 2) . " from matured investment.";
            header("Location: investment.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Withdrawal failed: " . $e->getMessage();
        }
    } else {
        $error = "Invalid or already withdrawn investment.";
    }


}




/**
 * Update matured investments (once matured, mark them as such)
 * This function can be called on page load or via a scheduled job (cron)
 */
function updateMaturedInvestments($pdo) {
    $stmt = $pdo->prepare("
        UPDATE investments inv
        JOIN investment_plans plans ON inv.plan_id = plans.plan_id
        SET inv.status = 'matured', inv.matured_at = NOW()
        WHERE inv.status = 'active' 
        AND DATE_ADD(inv.created_at, INTERVAL plans.duration_months MONTH) <= NOW()
        AND (inv.matured_at IS NULL OR inv.matured_at = '0000-00-00 00:00:00')
    ");
    $stmt->execute();
}

// Call the function to update matured investments
updateMaturedInvestments($pdo);

// Fetch user's investment history
$stmt = $pdo->prepare("
    SELECT inv.*, plans.plan_name, plans.interest_rate, plans.duration_months 
    FROM investments inv
    JOIN investment_plans plans ON inv.plan_id = plans.plan_id
    WHERE inv.user_id = ?
    ORDER BY inv.created_at DESC
");
$stmt->execute([$userId]);
$investments = $stmt->fetchAll();

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
// Fetch user's profile information
?>

<style>

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


/* Page wrapper */
.voice-page {
    min-height: calc(100vh - 120px); /* adjust if header height differs */
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Voice box (your existing style or improved one) */
.voice-box {
    width: 420px;
    padding: 30px;
    background: linear-gradient(135deg, #6359e9, #4f46e5);
    color: #fff;
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
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

                    <a href="voicebank.php" class="btn dash-text">
                        <img 
                        src="../assets/images/voice_active.png" 
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
                <header>
                    <h1>VOICE BANKNG</h1>
                    <button class="hamburger">&#9776;</button> <!-- Hamburger icon -->
                </header>

                

                    
<div class="voice-page">
    <div class="voice-box">
        <h3>🎙️ Nexus Voice Banking</h3>
        <button id="voiceBtn">Start Speaking</button>
        <p id="voice-text"></p>
    </div>
</div>


<!-- ✅ Voice banking balance (REQUIRED) -->
<span id="accountBalance" style="display:none;">
    <?= number_format($account['balance'], 2, '.', '') ?>
</span>


<!-- 🌍 Language Selector (STEP-1) -->
<select id="languageSelect">
    <option value="en-IN">English</option>
    <option value="ta-IN">தமிழ்</option>
</select>





<!-- Account Summary Data -->
<div id="accountSummary" style="display:none">
Balance <?= number_format($account['balance'],2) ?>,
Account number <?= $account['account_number'] ?>,
Account type Savings
</div>

<!--spend analysis-->
<div id="spendingData" style="display:none">
<?php echo number_format($monthlySpending, 2); ?>
</div>

<!-- Voice Data -->
<div id="balanceData" style="display:none"><?= number_format($balance,2) ?></div>

<?php
$stmt = $pdo->prepare("SELECT type, amount FROM transactions 
                       WHERE account_id = ? 
                       ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$account['account_id']]);
$lastTx = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="txData" style="display:none">
<?php foreach($lastTx as $t){ echo "{$t['type']} {$t['amount']} , "; } ?>
</div>


<script src="../voice/controller.js"></script>

                <script src="../assets/js/session.js"></script>
</body>
</html>
