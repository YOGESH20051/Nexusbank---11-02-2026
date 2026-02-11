<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 🔒 Protect admin page
redirectIfNotAdmin();




// ==============================
// GET NEWS ID
// ==============================
if (!isset($_GET['id'])) {
    header("Location: add_news.php");
    exit;
}

$id = (int) $_GET['id'];

// ==============================
// UPDATE NEWS + AUDIT LOG
// ==============================
if (isset($_POST['update'])) {

    $title   = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content cannot be empty.";
        header("Location: edit_news.php?id=$id");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE bank_news SET title = ?, content = ? WHERE id = ?");
    $stmt->execute([$title, $content, $id]);

   // 🔐 AUDIT LOG
$details = "Updated announcement ID $id | Title: $title";

logAdminAction(
    $pdo,
    $_SESSION['user_id'],   // ✅ correct
    "UPDATE NEWS",
    $details
);


    $_SESSION['success'] = "Announcement updated successfully!";
    header("Location: add_news.php");
    exit;
}



$stmt = $pdo->prepare("
    SELECT u.*, a.account_number 
    FROM users u 
    JOIN accounts a ON u.user_id = a.user_id 
    WHERE u.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// ==============================
// FETCH EXISTING NEWS
// ==============================
$stmt = $pdo->prepare("SELECT * FROM bank_news WHERE id = ?");
$stmt->execute([$id]);
$news = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$news) {
    header("Location: add_news.php");
    exit;
}

// ==============================
// PROFILE PICTURE
// ==============================
$profilePic = !empty($user['profile_picture'])
    ? '../uploads/' . $user['profile_picture']
    : '../assets/images/default-avatars.png';
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
</style>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Investments Tracking - Nexus Bank Admin</title>
    <link rel="stylesheet" href="../assets/css/admin-main.css">
    <link rel="stylesheet" href="../assets/css/admin-track-investment.css">

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

                             <div class="logout-cont">
                                <a href="../logout.php" class="logout">Logout</a>
                            </div>
                </aside>


                


    <main class="container scroll-container">
        <header>
            <h1>📢Announcements</h1>
           
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

        
<head>
    
    <title>Edit Announcement</title>
    <style>
        .edit-card{
    max-width:560px;
    margin:60px auto;
    background:#ffffff;
    border-radius:16px;
    padding:30px 32px;
    box-shadow:0 20px 40px rgba(0,0,0,.08);
}

/* Outer Glow */
.edit-card::before{
    content:"";
    position:absolute;
    inset:-2px;
    border-radius:24px;
    background:linear-gradient(135deg,#6366f1,#22d3ee,#6366f1);
    filter:blur(22px);
    opacity:.35;
    z-index:-1;
}
@keyframes pulseGlow {
    0%   { opacity:.25; }
    50%  { opacity:.55; }
    100% { opacity:.25; }
}

.edit-card::before{
    animation:pulseGlow 4s ease-in-out infinite;
}

.edit-card h2{
    margin-bottom:22px;
    font-size:22px;
    color:#4338ca;
    display:flex;
    align-items:center;
    gap:8px;
}

.edit-form input,
.edit-form textarea{
    width:100%;
    padding:14px 16px;
    border-radius:10px;
    border:1px solid #e5e7eb;
    font-size:14px;
    outline:none;
    transition:.25s ease;
}

.edit-form textarea{
    resize:none;
    margin-top:12px;
    line-height:1.6;
}

.edit-form input:focus,
.edit-form textarea:focus{
    border-color:#6366f1;
    box-shadow:0 0 0 3px rgba(99,102,241,.15);
}

.edit-form button{
    margin-top:20px;
    padding:12px 22px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#6366f1,#4f46e5);
    color:white;
    font-weight:600;
    font-size:14px;
    cursor:pointer;
    box-shadow:0 10px 22px rgba(79,70,229,.35);
    transition:.25s ease;
}

.edit-form button:hover{
    transform:translateY(-2px);
    box-shadow:0 14px 28px rgba(79,70,229,.45);
}

        .container{
    position:relative;
}

.page-bottom-action{
    position:absolute;
    right:40px;
    bottom:30px;
    z-index:20;
}
/* make sure sidebar is relative */
.sidebar{
    position:relative;
}

/* container at bottom */
.sidebar-bottom-action{
    position:absolute;
    top: 80px;
    right:15px;
    width:calc(25% - 70px);
    text-align:center;
}

/* button style */
.sidebar-back-btn{
    display:block;
    padding:10px 14px;
    border-radius:10px;
    background:linear-gradient(135deg,#6d5dfc,#5b5cf6);
    color:#fff;
    font-weight:600;
    font-size:13px;
    text-decoration:none;
    box-shadow:0 8px 18px rgba(91,92,246,.35);
    transition:.25s ease;
}

.sidebar-back-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 26px rgba(91,92,246,.45);
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
</head>

<body>

<div class="sidebar-bottom-action">
    <a href="add_news.php" class="sidebar-back-btn">← Back to Add News</a>
</div>


<div class="edit-box ">
    
    </br>
    <div class="edit-card">
    <h2>✏️ Edit Announcement</h2>

    <form method="post" class="edit-form">
        <input type="text" name="title" value="<?= htmlspecialchars($news['title']) ?>" required>
        <textarea name="content" rows="6" required><?= htmlspecialchars($news['content']) ?></textarea>
        <button type="submit" name="update">Update Announcement</button>
    </form>
</div>



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


















