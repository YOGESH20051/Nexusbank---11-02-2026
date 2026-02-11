<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Redirect if not admin
redirectIfNotAdmin();

// Pagination setup
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Count total loan history records
$totalCount = $pdo->query("SELECT COUNT(*) FROM loan_history")->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Fetch loan history records with loan and user info (paginated)
$stmt = $pdo->prepare("
    SELECT lh.*, l.amount, l.interest_rate, l.purpose, u.full_name, u.email,
    CONVERT_TZ(lh.changed_at, '+00:00', '+08:00') as ph_time
    FROM loan_history lh
    JOIN loans l ON lh.loan_id = l.loan_id
    JOIN users u ON l.user_id = u.user_id
    ORDER BY lh.changed_at DESC
    LIMIT :perPage OFFSET :offset
");
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$loanHistory = $stmt->fetchAll();

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
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 13px;
    text-transform: capitalize;
    display: inline-block;
}

.status-Approved,
.status-success { background:#dcfce7; color:#166534; }

.status-rejected,
.status-failed { background:#fee2e2; color:#991b1b; }


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
    <meta charset="UTF-8">
    <title>Loan History - Nexus Bank Admin</title>
    <link rel="stylesheet" href="../assets/css/admin-main.css">
    <link rel="stylesheet" href="../assets/css/admin-loan-history.css">

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
                          <a href="loan-history.php" class="btn dash-text">
                        <img 
                        src="../assets/images/loan_active.png" 
                        alt="loan-record-logo" 
                        class="nav-icon"
                        data-default="../assets/images/loan.png"
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
        <h1>Loan History</h1>
        
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
        <h1>Loan History</h1>
        <?php if (empty($loanHistory)): ?>
            <p>No loan history found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Loan ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Amount</th>
                        <th>Interest</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Changed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loanHistory as $entry): ?>
                        <tr>
                            <td data-label="Loan ID"><?= $entry['loan_id'] ?></td>
                            <td data-label="User"><?= htmlspecialchars($entry['full_name']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($entry['email']) ?></td>
                            <td data-label="Amount">₹<?= number_format($entry['amount'], 2) ?></td>
                            <td data-label="Interest"><?= $entry['interest_rate'] ?>%</td>
                            <td data-label="Purpose"><?= htmlspecialchars($entry['purpose']) ?></td>
                           <td data-label="Status">
    <span class="status-badge status-<?= strtolower($entry['status']) ?>">
        <?= ucfirst($entry['status']) ?>
    </span>
</td>

                            <td data-label="Changed At"><?= date('M d, Y - h:i A', strtotime($entry['ph_time'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
        <style>
        .pagination { text-align: center; margin: 20px 0; }
        .pagination a { display: inline-block; margin: 0 4px; padding: 6px 12px; color: #007bff; background: #fff; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; transition: background 0.2s, color 0.2s; }
        .pagination a.btn-primary, .pagination a.active { background: #007bff; color: #fff; border-color: #007bff; pointer-events: none; }
        .pagination a:hover:not(.btn-primary):not(.active) { background: #f0f0f0; }
        </style>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'btn-primary active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const status = document.getElementById('status');
    if (!status) return;

    function paint() {
        status.classList.remove('success','failed','pending');
        if (status.value) status.classList.add(status.value);
    }

    status.addEventListener('change', paint);
    paint();
});
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
