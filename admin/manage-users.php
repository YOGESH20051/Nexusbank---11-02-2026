<?php 
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/notification.php';
include __DIR__ . '/../includes/loader.php';

// Ensure only admin can access this page
redirectIfNotAdmin();

/* ===========================
   APPROVE USER
=========================== */
if (isset($_GET['accept']) && is_numeric($_GET['accept'])) {
    $userId = $_GET['accept'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE user_id = ?");
        if (!$stmt->execute([$userId])) {
            throw new Exception("Failed to update user status.");
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $hasAccount = $stmt->fetchColumn();

        if (!$hasAccount) {
            $accountNumber = generateUniqueAccountNumber($pdo);
            $stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_number, balance) VALUES (?, ?, 0)");
            if (!$stmt->execute([$userId, $accountNumber])) {
                throw new Exception("Failed to create account for user.");
            }
        }

        $pdo->commit();

        logAdminAction($pdo, $_SESSION['user_id'], 'USER_APPROVED', "Approved user ID {$userId} and created bank account");

        $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($userNotify = $stmt->fetch()) {
            sendNotification($userNotify['email'], "Your Nexus Bank Account Has Been Approved",
                "<p>Hi <strong>{$userNotify['full_name']}</strong>,</p><p>Your account has been approved.</p>");
        }

        $_SESSION['success'] = "User approved and account created.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to approve user: " . $e->getMessage();
    }

    header("Location: manage-users.php");
    exit();
}

/* ===========================
   REJECT USER
=========================== */
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $userId = $_GET['reject'];

    try {
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);

        logAdminAction($pdo, $_SESSION['user_id'], 'USER_REJECTED', "Rejected and deleted user ID {$userId}");

        $_SESSION['success'] = "User rejected and deleted.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to reject user: " . $e->getMessage();
    }

    header("Location: manage-users.php");
    exit();
}

/* ===========================
   DELETE USER
=========================== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = $_GET['delete'];

    $stmt = $pdo->prepare("SELECT balance, email, full_name FROM accounts LEFT JOIN users ON accounts.user_id = users.user_id WHERE users.user_id = ?");
    $stmt->execute([$userId]);
    $account = $stmt->fetch();

    if (!$account || $account['balance'] > 0) {
        $_SESSION['error'] = "Cannot delete user. Balance must be 0.";
        header("Location: manage-users.php");
        exit();
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM accounts WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
        $pdo->commit();

        logAdminAction($pdo, $_SESSION['user_id'], 'USER_DELETED', "Deleted user ID {$userId} after verifying zero balance");

        sendNotification($account['email'], "Your Nexus Bank Account Has Been Deleted",
            "<p>Hi <strong>{$account['full_name']}</strong>,</p><p>Your account has been deleted by admin.</p>");

        $_SESSION['success'] = "User deleted successfully and notified.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to delete user: " . $e->getMessage();
    }

    header("Location: manage-users.php");
    exit();
}

/* ===========================
   TOGGLE USER ACTIVE
=========================== */
if (isset($_GET['toggle_active']) && is_numeric($_GET['toggle_active'])) {
    $userId = $_GET['toggle_active'];
    $newStatus = $_GET['status'] == '1' ? 0 : 1;

    try {
        $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?")->execute([$newStatus, $userId]);

        $action = $newStatus ? 'USER_ACTIVATED' : 'USER_DEACTIVATED';
        logAdminAction($pdo, $_SESSION['user_id'], $action, ucfirst(strtolower(str_replace('_',' ',$action))) . " user ID {$userId}");

        $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($userNotify = $stmt->fetch()) {
            sendNotification($userNotify['email'], "Account Status Changed",
                "<p>Hi <strong>{$userNotify['full_name']}</strong>,</p><p>Your account has been updated by admin.</p>");
        }

        $_SESSION['success'] = $newStatus ? "User account activated." : "User account deactivated.";

    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to change activation status: " . $e->getMessage();
    }

    header("Location: manage-users.php");
    exit();
}

/* ===========================
   FETCH USERS
=========================== */
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$totalCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$users = $pdo->prepare("
    SELECT u.*, a.account_number, a.balance, iv.id_type, iv.id_file_path, iv.verification_status
    FROM users u 
    LEFT JOIN accounts a ON u.user_id = a.user_id 
    LEFT JOIN id_verifications iv ON u.user_id = iv.user_id
    ORDER BY u.created_at DESC
    LIMIT :perPage OFFSET :offset
");
$users->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$users->bindValue(':offset', $offset, PDO::PARAM_INT);
$users->execute();
$users = $users->fetchAll();

/* ===========================
   FETCH LOGGED IN ADMIN PROFILE
=========================== */
$stmt = $pdo->prepare("SELECT full_name, account_number, profile_picture FROM users 
                       LEFT JOIN accounts ON users.user_id = accounts.user_id
                       WHERE users.user_id = ?");

$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ===========================
   PROFILE IMAGE SAFE HANDLING
=========================== */
$profilePic = (!empty($user['profile_picture']))
    ? '../uploads/' . $user['profile_picture']
    : '../assets/images/default-avatars.png';
?>


<style>
.status-dot{
    width:12px;
    height:12px;
    border-radius:50%;
    display:inline-block;
    margin-right:6px;
    vertical-align:middle;
}

@keyframes nexusBlink{
    0%{transform:scale(1);opacity:1;}
    50%{transform:scale(1.6);opacity:.3;}
    100%{transform:scale(1);opacity:1;}
}

.nexus-active{
    background:#28c76f;
    animation:nexusBlink 1s infinite;
    box-shadow:0 0 8px rgba(40,199,111,.9);
}

.nexus-inactive{
    background:#ff3b3b;
}
.active-text{color:#28c76f;font-weight:600;}
.inactive-text{color:#ff3b3b;font-weight:600;}

.user-actions-bar{
    display:flex;
    justify-content:flex-end;
    margin-bottom:15px;
}

.sidebar hr {
    width: 80%;
    margin: 15px auto;
    border: none;
    border-top: 1px solid #e2e6ef;
}


.add-user-btn{
    background:#4f46e5;
    color:#fff;
    border:none;
    padding:10px 18px;
    border-radius:8px;
    font-weight:600;
    cursor:pointer;
}
.add-user-btn:hover{opacity:.9;}

.table-scroll-wrapper{
    max-height: 65vh;
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 12px;
}

/* Smooth scrollbar */
.table-scroll-wrapper::-webkit-scrollbar{
    width: 8px;
    height: 8px;
}
.table-scroll-wrapper::-webkit-scrollbar-thumb{
    background: #c7c7f5;
    border-radius: 10px;
}
.table-scroll-wrapper::-webkit-scrollbar-track{
    background: transparent;
}

.add-user-row{
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 14px;
}

.add-user-row input{
    flex: 1;
    padding: 10px 12px;
    border: 1px solid #cfcfe6;
    border-radius: 6px;
    font-size: 14px;
}

.add-user-row input:focus{
    border-color: #5b5be0;
    box-shadow: 0 0 0 2px rgba(91,91,224,.12);
    outline: none;
}

.add-user-row button{
    padding: 10px 18px;
    border-radius: 6px;
    border: none;
    background: #5b5be0;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: .2s;
}

.add-user-row button:hover{
    opacity: .9;
}


.alert{
    padding: 14px 22px;
    border-radius: 10px;
    margin: 15px 0;
    font-weight: 600;
    font-size: 14px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.15);
    animation: fadeIn 0.4s ease-in-out;
}

/* Error message */
.alert.error{
    background: linear-gradient(135deg, #ff4d4d, #ff7b7b);
    color: #ffffff;
    border-left: 5px solid #c82333;
}

/* Success message (use later) */
.alert.success{
    background: linear-gradient(135deg, #0d6efd, #4da3ff);
    color: #ffffff;
    border-left: 5px solid #084298;
}

@keyframes fadeIn{
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}

.alert{
    position: relative;
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
    <title>Nexus Bank - Manage Users</title>
    <link rel="stylesheet" href="../assets/css/admin-main.css">
    <link rel="stylesheet" href="../assets/css/admin-users.css">

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
                                <a href="manage-users.php" class="btn dash-text">
                        <img 
                        src="../assets/images/manageusers_active.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/manageusers.png"
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

</br></br>
                </aside>


<main class="container scroll-container">
    <header>
        <h1>Manage Users</h1>
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

    </header>

    <?php if (isset($_SESSION['error'])): ?>
<div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>


    <div class="content scroll-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
   <div class="table-wrapper">
        <h2>All Users</h2>
        </br>
        <div class="table-cont scroll-wrapper">
        <?php if (empty($users)): ?>
            <p>No users found.</p>
        <?php else: ?>

<h3>Add New User</h3>

<form action="add_user.php" method="post" id="add-user-form">
    <div class="add-user-row">
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="tel" name="phone" placeholder="Phone Number" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" class="form-control" placeholder="Temporary Password" required>
        <input type="number" name="balance" placeholder="Opening Balance" required>

        <button type="submit">Create User</button>
        
    </div>
</form>





            <div class="table-wrapper table-scroll-wrapper">
            <table class="users-table">
                <thead>
                    <tr>
                    
                        <th>Name</th>
                        <th>Email</th>
                        <th>Account</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>ID Verification</th>
                        <th>Active</th>
                        <th>Joined On</th>
                        <th class="actions">Actions</th>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            
                            <td data-label="Name"><?= htmlspecialchars($user['full_name']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($user['email']) ?></td>
                            <td data-label="Account"><?= $user['account_number'] ?: 'N/A' ?></td>
                            <td data-label="Balance">₹<?= number_format($user['balance'] ?? 0, 2) ?></td>
                            <td data-label="Status"><?= $user['status'] === 'approved' ? '✅ Approved' : '⏳ Pending' ?></td>
                            <td data-label="ID Verification">
                                <?php if ($user['id_file_path']): ?>
                                    <span class="id-status <?= $user['verification_status'] ?>">
                                        <?= ucfirst($user['verification_status'] ?? 'pending') ?>
                                    </span>
                                    <a href="view-id.php?user_id=<?= $user['user_id'] ?>" class="btn btn-sm btn-info">View ID</a>
                                <?php else: ?>
                                    <span class="id-status pending">No ID Uploaded</span>
                                <?php endif; ?>
                            </td>
                 <td data-label="Active">
<?php if($user['is_active']): ?>
    <span class="status-dot nexus-active"></span>
    <span class="active-text">Active</span>
<?php else: ?>
    <span class="status-dot nexus-inactive"></span>
    <span class="inactive-text">Inactive</span>
<?php endif; ?>

</td>



                            <td data-label="Joined On"><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                            <td data-label="Actions" class="actions">
                                <a href="edit-user.php?user_id=<?= $user['user_id'] ?>" class="btn btn-sm btn-info">Edit</a>


                                <?php if ($user['status'] !== 'approved'): ?>
                                    <a href="manage-users.php?accept=<?= $user['user_id'] ?>" class="btn btn-sm btn-success">Accept</a>
                                    <a href="manage-users.php?reject=<?= $user['user_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject and delete this user?')">Reject</a>
                                <?php endif; ?>

                                <?php if ($user['status'] === 'approved'): ?>
                                    <?php if ($user['is_active']): ?>
                                        <a href="manage-users.php?toggle_active=<?= $user['user_id'] ?>&status=1" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate this user?')">Deactivate</a>
                                    <?php else: ?>
                                        <a href="manage-users.php?toggle_active=<?= $user['user_id'] ?>&status=0" class="btn btn-sm btn-success" onclick="return confirm('Activate this user?')">Activate</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['balance'] == 0): ?>
                                        <a href="manage-users.php?delete=<?= $user['user_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        </div>
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
</div>

<script>
setTimeout(() => {
    const alertBox = document.querySelector('.alert');
    if(alertBox){
        alertBox.style.transition = "all 0.5s ease";
        alertBox.style.opacity = "0";
        alertBox.style.transform = "translateY(-10px)";
        setTimeout(() => alertBox.remove(), 500);
    }
}, 5000);
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
