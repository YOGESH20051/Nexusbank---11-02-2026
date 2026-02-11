<?php
session_start();

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/notification.php';
include __DIR__ . '/../includes/loader.php';

redirectIfNotAdmin();

/* ==========================
   GLOBAL USER SAFETY OBJECT
========================== */

$user = [
    'full_name' => 'Administrator',
    'email' => '',
    'account_number' => '',
    'balance' => 0,
    'profile_picture' => null
];

/* ==========================
   HANDLE LOAN ACTIONS
========================== */

if (isset($_GET['id'], $_GET['action'])) {

    $loanId = (int) $_GET['id'];
    $action = $_GET['action'];

    $stmt = $pdo->prepare("SELECT * FROM loans WHERE loan_id = ?");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

   if ($loan) {

    if ($action === 'approve') {

        $status     = 'approved';
        $approvedAt = date('Y-m-d H:i:s');
        $amount     = $loan['amount'];
        $interest   = $loan['interest_rate'];
        $totalDue   = $amount + ($amount * ($interest / 100));

        // Update loan
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET status = ?, approved_at = ?, total_due = ?, 
                due_date = DATE_ADD(?, INTERVAL term_months MONTH)
            WHERE loan_id = ?
        ");
        $stmt->execute([$status, $approvedAt, $totalDue, $approvedAt, $loanId]);

        // Credit user's account
        $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$amount, $loan['user_id']]);

        $stmt = $pdo->prepare("SELECT account_id FROM accounts WHERE user_id = ?");
        $stmt->execute([$loan['user_id']]);
        $accountId = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO transactions (account_id, type, amount, description)
            VALUES (?, 'loan_credit', ?, 'Loan approved')
        ");
        $stmt->execute([$accountId, $amount]);

        // ✅ Notification for user
        $stmt = $pdo->prepare("
            INSERT INTO user_notifications (user_id, title, message, type)
            VALUES (?, ?, ?, 'credit')
        ");
        $stmt->execute([
            $loan['user_id'],
            "Loan Approved",
            "Your loan of ₹" . number_format($amount, 2) . " has been approved by Admin."
        ]);

        logAdminAction($pdo, $_SESSION['user_id'], 'Loan Approved', "Loan ID: $loanId | Amount: ₹$amount");
    }

    elseif ($action === 'reject') {

        $status = 'rejected';

        $stmt = $pdo->prepare("
            UPDATE loans SET status = ? WHERE loan_id = ?
        ");
        $stmt->execute([$status, $loanId]);

        // ✅ Notification for user
        $stmt = $pdo->prepare("
            INSERT INTO user_notifications (user_id, title, message, type)
            VALUES (?, ?, ?, 'error')
        ");
        $stmt->execute([
            $loan['user_id'],
            "Loan Rejected",
            "Your loan application for ₹" . number_format($loan['amount'], 2) . " has been rejected by Admin."
        ]);

        logAdminAction($pdo, $_SESSION['user_id'], 'Loan Rejected', "Loan ID: $loanId");
    }

    elseif ($action === 'delete') {

        $stmt = $pdo->prepare("DELETE FROM loans WHERE loan_id = ?");
        $stmt->execute([$loanId]);

        // ✅ Notification for user
        $stmt = $pdo->prepare("
            INSERT INTO user_notifications (user_id, title, message, type)
            VALUES (?, ?, ?, 'warning')
        ");
        $stmt->execute([
            $loan['user_id'],
            "Loan Deleted",
            "Your loan application has been removed by Admin."
        ]);

        logAdminAction($pdo, $_SESSION['user_id'], 'Loan Deleted', "Loan ID: $loanId");
    }
}


    header("Location: manage-loans.php");
    exit;
}

/* ==========================
   PAGINATION SETTINGS
========================== */

$perPage = 10;

// Pending
$totalPending = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn();
$totalPendingPages = max(1, ceil($totalPending / $perPage));

// Approved
$totalApproved = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'approved'")->fetchColumn();
$totalApprovedPages = max(1, ceil($totalApproved / $perPage));

// Page numbers
$pendingPage  = isset($_GET['pending_page'])  ? max(1, (int)$_GET['pending_page'])  : 1;
$approvedPage = isset($_GET['approved_page']) ? max(1, (int)$_GET['approved_page']) : 1;

$pendingOffset  = ($pendingPage  - 1) * $perPage;
$approvedOffset = ($approvedPage - 1) * $perPage;

/* ==========================
   FETCH LOANS
========================== */

$pendingLoans = $pdo->prepare("
    SELECT l.*, u.full_name, u.email
    FROM loans l
    JOIN users u ON l.user_id = u.user_id
    WHERE l.status = 'pending'
    LIMIT ? OFFSET ?
");
$pendingLoans->execute([$perPage, $pendingOffset]);
$pendingLoans = $pendingLoans->fetchAll(PDO::FETCH_ASSOC);

$approvedLoans = $pdo->prepare("
    SELECT l.*, u.full_name, u.email
    FROM loans l
    JOIN users u ON l.user_id = u.user_id
    WHERE l.status = 'approved'
    LIMIT ? OFFSET ?
");
$approvedLoans->execute([$perPage, $approvedOffset]);
$approvedLoans = $approvedLoans->fetchAll(PDO::FETCH_ASSOC);

/* ==========================
   ADMIN PROFILE INFORMATION
========================== */

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT u.full_name, u.email, u.profile_picture, a.account_number, a.balance
    FROM users u
    LEFT JOIN accounts a ON u.user_id = a.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);
if ($data) {
    $user = array_merge($user, $data);
}

/* ==========================
   PROFILE IMAGE
========================== */

$profilePic = (!empty($user['profile_picture']))
    ? '../uploads/' . $user['profile_picture']
    : '../assets/images/default-avatars.png';

?>



<style>

/* ==========================
   PAGINATION STYLING
========================== */

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 25px 0 10px;
    flex-wrap: wrap;
}

.pagination a {
    min-width: 40px;
    height: 40px;
    padding: 0 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f6fb;
    border-radius: 10px;
    color: #444;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.pagination a:hover {
    background: #6c63ff;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(108,99,255,0.3);
}

.pagination a.active {
    background: linear-gradient(135deg, #6c63ff, #4f46e5);
    color: white;
    box-shadow: 0 8px 18px rgba(108,99,255,0.45);
    pointer-events: none;
}

.pagination a.disabled {
    background: #e4e7f2;
    color: #aaa;
    pointer-events: none;
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
    <meta charset="UTF-8">
    <title>Manage Loans - Nexus Bank Admin</title>
    <link rel="stylesheet" href="../assets/css/admin-main.css">
    <link rel="stylesheet" href="../assets/css/admin-loans.css">

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
                                <a href="manage-loans.php" class="btn dash-text">
                        <img 
                        src="../assets/images/manageloan_active.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/manageloan.png"
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
</br></br>
                </aside>
                            
                    <main class="container">
                    <header>
                    <h1>Loan Management</h1>
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

                    <div class="content scroll-container">

                    <h2>🕒 Pending Loan Requests (Latest 10)</h2>

                    <?php if (empty($pendingLoans)): ?>
                        <p>No pending loan applications at the moment.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Loan ID</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Amount</th>
                                    <th>Interest</th>
                                    <th>Term</th>
                                    <th>Purpose</th>
                                    <th>Requested</th>
                                    <th>Verification</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingLoans as $loan): ?>
                                    <tr>
                                        <td data-label="Loan ID"><?= $loan['loan_id'] ?></td>
                                        <td data-label="User"><?= htmlspecialchars($loan['full_name']) ?></td>
                                        <td data-label="Email"><?= htmlspecialchars($loan['email']) ?></td>
                                        <td data-label="Amount">₹<?= number_format($loan['amount'], 2) ?></td>
                                        <td data-label="Interest"><?= $loan['interest_rate'] ?>%</td>
                                        <td data-label="Term"><?= $loan['term_months'] ?> months</td>
                                        <td data-label="Purpose"><?= htmlspecialchars($loan['purpose']) ?></td>
                                        <td data-label="Requested"><?= date('M d, Y', strtotime($loan['created_at'])) ?></td>
                                        <td data-label="Verification">
                                            <?php if ($loan['id_selfie_file_path'] && $loan['id_document_file_path']): ?>
                                                <a href="view-loan-verification.php?loan_id=<?= $loan['loan_id'] ?>" class="btn3 btn-info" style="color: #000;">View Verification</a>
                                            <?php else: ?>
                                                No files uploaded
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions">
                                            <a href="manage-loans.php?id=<?= $loan['loan_id'] ?>&action=approve" class="btn3 btn-approve" onclick="return confirm('Approve this loan?')">Approve</a>
                                            <a href="manage-loans.php?id=<?= $loan['loan_id'] ?>&action=reject" class="btn3 btn-reject" onclick="return confirm('Reject this loan?')">Reject</a>
                                            <a href="manage-loans.php?id=<?= $loan['loan_id'] ?>&action=delete" class="btn3 btn-delete" onclick="return confirm('Are you sure you want to delete this loan?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <!-- Pagination Controls for Pending Loans -->
                    <?php if ($totalPendingPages > 1): ?>
                    <style>
                    .pagination { text-align: center; margin: 20px 0; }
                    .pagination a { display: inline-block; margin: 0 4px; padding: 6px 12px; color: #007bff; background: #fff; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; transition: background 0.2s, color 0.2s; }
                    .pagination a.btn-primary, .pagination a.active { background: #007bff; color: #fff; border-color: #007bff; pointer-events: none; }
                    .pagination a:hover:not(.btn-primary):not(.active) { background: #f0f0f0; }
                    </style>
                    <div class="pagination">
                        <?php if ($pendingPage > 1): ?>
                            <a href="?pending_page=<?= $pendingPage - 1 ?>">&laquo; Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPendingPages; $i++): ?>
                            <a href="?pending_page=<?= $i ?>" class="<?= $i == $pendingPage ? 'btn-primary active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($pendingPage < $totalPendingPages): ?>
                            <a href="?pending_page=<?= $pendingPage + 1 ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <hr>

                    <h2>✅ Active Loans</h2>

                    <?php if (empty($approvedLoans)): ?>
                        <p>No active loans at the moment.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Loan ID</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Amount</th>
                                    <th>Interest</th>
                                    <th>Term</th>
                                    <th>Due Date</th>
                                    <th>Total Due</th>
                                    <th>Purpose</th>
                                    <th>Approved On</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approvedLoans as $loan): ?>
                                    <tr>
                                        <td data-label="Loan ID"><?= $loan['loan_id'] ?></td>
                                        <td data-label="User"><?= htmlspecialchars($loan['full_name']) ?></td>
                                        <td data-label="Email"><?= htmlspecialchars($loan['email']) ?></td>
                                        <td data-label="Amount">₹<?= number_format($loan['amount'], 2) ?></td>
                                        <td data-label="Interest"><?= $loan['interest_rate'] ?>%</td>
                                        <td data-label="Term"><?= $loan['term_months'] ?> months</td>
                                        <td data-label="Due Date">
                                            <?php 
                                            if ($loan['approved_at'] !== null) {
                                                $dueDate = new DateTime($loan['approved_at']);
                                                $dueDate->modify('+1 year');
                                                echo $dueDate->format('M d, Y');
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Total Due">₹<?= number_format($loan['total_due'] + ($loan['penalty_amount'] ?? 0), 2) ?></td>
                                        <td data-label="Purpose"><?= htmlspecialchars($loan['purpose']) ?></td>
                                        <td data-label="Approved On">
                                            <?php 
                                            if ($loan['approved_at'] !== null) {
                                                echo date('M d, Y', strtotime($loan['approved_at']));
                                            } else {
                                                echo 'Not approved yet';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Status">
                                            <?php 
                                            if ($loan['approved_at'] !== null) {
                                                $currentDate = new DateTime();
                                                $approvedDate = new DateTime($loan['approved_at']);
                                                $termEndDate = clone $approvedDate;
                                                $termEndDate->modify('+' . $loan['term_months'] . ' months');
                                                
                                                if ($currentDate > $termEndDate && $loan['is_paid'] === 'no') {
                                                    echo '<span class="status-overdue">Overdue</span>';
                                                } else {
                                                    echo '<span class="status-' . $loan['status'] . '">' . ucfirst($loan['status']) . '</span>';
                                                }
                                            } else {
                                                echo '<span class="status-' . $loan['status'] . '">' . ucfirst($loan['status']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <!-- Pagination Controls for Approved Loans -->
                    <?php if ($totalApprovedPages > 1): ?>
                    <div class="pagination">
                        <?php if ($approvedPage > 1): ?>
                            <a href="?approved_page=<?= $approvedPage - 1 ?>">&laquo; Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalApprovedPages; $i++): ?>
                            <a href="?approved_page=<?= $i ?>" class="<?= $i == $approvedPage ? 'btn-primary active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($approvedPage < $totalApprovedPages): ?>
                            <a href="?approved_page=<?= $approvedPage + 1 ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    </div>
        </main>
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
