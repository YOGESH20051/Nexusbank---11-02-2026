<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Protect admin page
redirectIfNotAdmin();

// Handle news publish
if (isset($_POST['publish'])) {
    $title   = trim($_POST['title']);
    $content = trim($_POST['content']);

    $stmt = $pdo->prepare("INSERT INTO bank_news (title, content) VALUES (?, ?)");
    
    if ($stmt->execute([$title, $content])) {
         // 🔐 ADMIN AUDIT LOG — ADD THIS HERE
        logAdminAction(
            $pdo,
            $_SESSION['user_id'],
            'Add News',
            'Admin published a news article: ' . $title
        );

        $_SESSION['success'] = "News published successfully!";
header("Location: add_news.php");
exit;
    }
}

// ------------------ EXISTING CODE BELOW ------------------

// Pagination
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$totalCount = $pdo->query("SELECT COUNT(*) FROM investments")->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT ui.investment_id, ui.user_id, ui.amount, ui.status, ui.created_at, ui.matured_at, 
           up.plan_name, up.interest_rate, u.full_name, u.email 
    FROM investments ui
    JOIN users u ON ui.user_id = u.user_id
    JOIN investment_plans up ON ui.plan_id = up.plan_id
    LIMIT :perPage OFFSET :offset
");

$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$userInvestments = $stmt->fetchAll();

// Logged in user info
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

// Profile picture
$profilePic = !empty($user['profile_picture']) 
    ? '../uploads/' . $user['profile_picture'] 
    : '../assets/images/default-avatars.png';


// Fetch published announcements
$newsStmt = $pdo->query("SELECT * FROM bank_news ORDER BY created_at DESC");
$allNews = $newsStmt->fetchAll(PDO::FETCH_ASSOC);


?>

<style>
    /* Main Card */
.news-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 25px 30px;
    max-width: 520px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    animation: fadeInUp .6s ease;
}

/* Heading */
.news-card h2 {
    font-size: 24px;
    font-weight: 700;
    color: #2d2f55;
    margin-bottom: 18px;
}

/* Inputs */
.news-input,
.news-textarea {
    width: 100%;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1.8px solid #d9d9e7;
    outline: none;
    font-size: 15px;
    margin-bottom: 15px;
    transition: 0.25s;
}

/* Focus effect */
.news-input:focus,
.news-textarea:focus {
    border-color: #6c63ff;
    box-shadow: 0 0 0 3px rgba(108,99,255,0.15);
}

/* Textarea height */
.news-textarea {
    min-height: 120px;
    resize: none;
}

/* Button */
.publish-btn {
    background: linear-gradient(135deg, #6c63ff, #8a7dff);
    border: none;
    color: #fff;
    padding: 12px 22px;
    border-radius: 9px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.publish-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 18px rgba(108,99,255,0.35);
}

/* Animation */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

.news-wrapper {
    display: flex;
    justify-content: center;   /* horizontal center */
    padding: 40px 20px;
}

/* Optional: slightly higher positioning for better visual balance */
.news-card {
    margin-top: 30px;
}

.sidebar hr {
    width: 80%;
    margin: 15px auto;
    border: none;
    border-top: 1px solid #e2e6ef;
}

.alert-success {
    background: #e6f9f0;
    color: #0f5132;
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-weight: 600;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    animation: fadeSlide 0.6s ease;
}

@keyframes fadeSlide {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.published-news {
    border-top: 1px solid #e6e9f5;
    padding: 15px 0;
}

.published-news h4 {
    color: #4f46e5;
    margin-bottom: 5px;
}

.news-actions {
    margin-top: 10px;
}

.edit-btn {
    background:#facc15;
    color:#000;
    padding:6px 12px;
    border-radius:6px;
    font-weight:600;
    text-decoration:none;
    margin-right:8px;
}

.delete-btn {
    background:#ef4444;
    color:#fff;
    padding:6px 12px;
    border-radius:6px;
    font-weight:600;
    text-decoration:none;
}

.news-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f9faff;
    border-radius: 12px;
    padding: 18px 22px;
    margin-bottom: 12px;
    box-shadow: 0 8px 18px rgba(0,0,0,0.05);
}

.news-info {
    max-width: 70%;
}

.news-info h4 {
    margin-bottom: 6px;
    color: #4f46e5;
}

.news-info p {
    color: #444;
    margin-bottom: 6px;
}

.news-actions {
    margin-top: 0; /* override old */
}

/* Announcement Container */
.news-card h2 {
    font-size: 26px;
    font-weight: 800;
    color: #2d2f55;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: 0.3px;
}

.news-card h2::after {
    content: "";
    flex: 1;
    height: 3px;
    border-radius: 50px;
    background: linear-gradient(90deg,#6c63ff,#8a7dff);
    margin-left: 10px;
}


/* Each announcement row */
.news-row {
    position: relative;
    overflow: hidden;
    transition: 0.25s ease;
}

.news-row::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    width: 6px;
    height: 100%;
    background: linear-gradient(180deg,#6c63ff,#8a7dff);
}

/* Hover effect */
.news-row:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 30px rgba(0,0,0,0.08);
}

/* Info section */
.news-info h4 {
    font-size: 18px;
    font-weight: 700;
}

.news-info p {
    font-size: 14px;
    line-height: 1.6;
    color: #555;
}

/* Date style */
.news-info small {
    display: inline-block;
    margin-top: 6px;
    background: #eef2ff;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    color: #4f46e5;
}

/* Action buttons animation */
.news-actions a {
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}

.news-actions a:hover {
    transform: translateY(-2px);
}

/* Subtle entrance animation */
.news-row {
    animation: fadeUp 0.6s ease;
}

@keyframes fadeUp {
    from { opacity:0; transform: translateY(15px); }
    to   { opacity:1; transform: translateY(0); }
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
                                <a href="add_news.php" class="btn dash-text">
                        <img 
                        src="../assets/images/announcement_active.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/announcement.png"
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
                    
</br></br>
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

        <?php if (isset($_SESSION['success'])): ?>
<div id="successMessage" class="alert-success">
    <?= $_SESSION['success']; ?>
</div>
<?php unset($_SESSION['success']); endif; ?>


<div class="news-wrapper">
       <div class="news-card">
    <h2>📰 Publish Daily Bank News</h2>
    <div class="news-marquee">
    <span>Share important updates and announcements with all bank users</span>
</div>





    <form method="post">
        <input type="text" name="title" class="news-input" placeholder="News Title" required>

        <textarea name="content" class="news-textarea" placeholder="Write news content..." required></textarea>

        <button type="submit" name="publish" class="publish-btn">Publish News</button>
    </form>
</div>
</div>

<div class="news-wrapper">
    <div class="news-card" style="max-width:1000px">

        <h2>📢 All Announcements</h2>

        <?php foreach ($allNews as $news): ?>
    <div class="news-row">

        <div class="news-info">
            <h4><?= htmlspecialchars($news['title']) ?></h4>
            <p><?= nl2br(htmlspecialchars($news['content'])) ?></p>
            <small>📅 <?= date('d M Y, h:i A', strtotime($news['created_at'])) ?></small>
        </div>

        <div class="news-actions">
            <a href="edit_news.php?id=<?= $news['id'] ?>" class="edit-btn">Edit</a>
            <a href="delete_news.php?id=<?= $news['id'] ?>"
               onclick="return confirm('Delete this announcement?')"
               class="delete-btn">Delete</a>
        </div>

    </div>
<?php endforeach; ?>

    </div>
</div>

        </div>

    </main>
    </div>

<script>
const msg = document.getElementById('successMessage');
if (msg) {
    setTimeout(() => {
        msg.style.transition = "0.6s ease";
        msg.style.opacity = "0";
        msg.style.transform = "translateY(-15px)";
        setTimeout(() => msg.remove(), 200);
    }, 5000);
}
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


















