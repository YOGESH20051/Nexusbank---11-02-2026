<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit();
}




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


<style>/* ---- Transaction Form Styling Upgrade ---- */

form {
    max-width: 540px;
    background: #ffffff;
    padding: 28px 34px;
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.08);
}

label {
    font-weight: 600;
    margin-top: 14px;
    color: #1f2937;
}

input, select {
    width: 100%;
    padding: 11px 14px;
    margin-top: 6px;
    border-radius: 10px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    font-size: 14px;
    transition: 0.25s ease;
}

input:focus, select:focus {
    outline: none;
    border-color: #6366f1;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}

/* Account Holder & Recipient Name fields */
#account_holder,
#recipient_holder {
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    border: 1px solid #c7d2fe;
    color: #3730a3;
    font-weight: 600;
}

/* Transaction button */
button {
    margin-top: 22px;
    width: 100%;
    padding: 13px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #ffffff;
    font-size: 15px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: 0.3s ease;
}

button:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 25px rgba(79,70,229,0.35);
}

button:active {
    transform: scale(0.98);
}

/* Transfer section animation */
#targetBox {
    margin-top: 10px;
    padding-top: 10px;
    animation: fadeSlide 0.4s ease;
}

@keyframes fadeSlide {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }

}

.alert {
    padding: 12px 15px;
    margin-bottom: 15px;
    border-radius: 6px;
    font-weight: 500;
}

.alert-danger {
    background-color: #fdecea;
    color: #b71c1c;
    border-left: 5px solid #d32f2f;
}

.alert-success {
    background-color: #e8f5e9;
    color: #1b5e20;
    border-left: 5px solid #2e7d32;
}


.success-box {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    color: #065f46;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 16px;
    border: 1px solid #34d399;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 8px 18px rgba(16,185,129,0.25);
    animation: slideFade 0.5s ease;
}

@keyframes slideFade {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}


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



</style>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Transaction Panel</title>

<link rel="stylesheet" href="../assets/css/admin-main.css">
<link rel="stylesheet" href="../assets/css/admin-all-transactions.css">
<script src="../assets/js/sidebar.js"></script>
</head>

<body>

<div class="wrapper">

<aside class="sidebar">
    <div class="Logos-cont">
        <img src="../assets/images/Logo-color.png" class="logo-container">
    </div>

    
                <hr>

                <div class="profile-container">
                    <img src="<?= $profilePic ?>" alt="Profile Picture" class="img-fluid">
                    <h5><?= htmlspecialchars($user['full_name']) ?></h5>
                    <p><?= htmlspecialchars($user['account_number']) ?></p>
                </div>

                <hr>

    <nav class="dashboard-nav">
                                <a href="dashboard.php" class="btn">
                                <img 
                                src="../assets/images/dashboard_logo.png" 
                                alt="dashboard-logo" 
                                class="nav-icon"
                                data-default="../assets/images/hover-dashboard.png"
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
        <a href="admin-transaction.php" class="btn dash-text">
                        <img 
                        src="../assets/images/admin_active.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/admin.png"
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
</br></br>
</aside>

<main class="container scroll-container">
<header>
    <h1>All Transactions</h1>
    <button class="hamburger">&#9776;</button>
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

<div class="content ">

<h2 style="margin-top:10px;">Admin Transaction Panel</h2>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        ⚠️ <?= $_SESSION['error']; ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="success-box" id="successBox">
        ✅ <?= $_SESSION['success']; ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>



<form method="POST" action="process-admin-transaction.php">



<label>Account Number</label>
<select name="account_id" id="account_id" required>
<?php
$accounts = $pdo->query("SELECT account_id, account_number FROM accounts");
while ($a = $accounts->fetch(PDO::FETCH_ASSOC)):
?>
<option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_number']) ?></option>
<?php endwhile; ?>
</select>
</br></br>

<label>Account Holder</label>
<input type="text" id="account_holder" readonly>
</br></br>
<label>Transaction Type</label>
<select name="type" id="typeSelect" required onchange="toggleTarget()">
<option value="deposit">Deposit</option>
<option value="withdraw">Withdraw</option>
<option value="transfer">Transfer</option>
</select>

<div id="targetBox" style="display:none;">

<label>Recipient Account Number</label>
<select name="related_account" id="related_account">
<?php
$accounts = $pdo->query("SELECT account_id, account_number FROM accounts");
while ($a = $accounts->fetch(PDO::FETCH_ASSOC)):
?>
<option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_number']) ?></option>
<?php endwhile; ?>
</select>
</br></br>
<label>Recipient Name</label>
<input type="text" id="recipient_holder" readonly>

</div>
</br></br>
<label>Amount</label>
<input type="number" name="amount" required>

<button type="submit" name="submit">Process Transaction</button>

</form>

</div>
</main>
</div>

<script>
function toggleTarget() {
    const type = document.getElementById("typeSelect").value;
    document.getElementById("targetBox").style.display = type === "transfer" ? "block" : "none";
}

function fetchHolder(accountId, field) {
    fetch("get_account_holder.php?account=" + accountId)
        .then(res => res.json())
        .then(data => {
            document.getElementById(field).value = data.name || "Not Found";
        });
}

document.getElementById("account_id").addEventListener("change", function(){
    fetchHolder(this.value, "account_holder");
});

document.getElementById("related_account").addEventListener("change", function(){
    fetchHolder(this.value, "recipient_holder");
});
</script>



<script>
setTimeout(() => {
    const box = document.getElementById('successBox');
    if (box) box.style.display = 'none';
}, 4000);
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

</body>
</html>
