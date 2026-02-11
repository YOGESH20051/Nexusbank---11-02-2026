<?php
session_start();

// Include necessary files and check if the user is an admin
require_once '../includes/db.php';
require_once '../includes/functions.php';
include __DIR__ . '/../includes/loader.php';

// Ensure the user is an admin
redirectIfNotAdmin();

/* ============================
   ADD NEW INVESTMENT PLAN
============================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plan'])) {

    $plan_name = htmlspecialchars($_POST['plan_name']);
    $interest_rate = (float) $_POST['interest_rate'];
    $min_amount = (float) $_POST['min_amount'];
    $max_amount = (float) $_POST['max_amount'];
    $duration_months = (int) $_POST['duration_months'];
    $risk_level = htmlspecialchars($_POST['risk_level']);

    $stmt = $pdo->prepare("
        INSERT INTO investment_plans 
        (plan_name, interest_rate, min_amount, max_amount, duration_months, risk_level) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$plan_name, $interest_rate, $min_amount, $max_amount, $duration_months, $risk_level]);

    // ✅ AUDIT LOG
    logAdminAction(
        $pdo,
        $_SESSION['user_id'],
        'Investment Plan Created',
        "Plan: $plan_name | Rate: $interest_rate%"
    );

    header("Location: manage-investments.php");
    exit();
}

/* ============================
   EDIT INVESTMENT PLAN
============================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_plan'])) {

    $plan_id = (int) $_POST['plan_id'];
    $plan_name = htmlspecialchars($_POST['plan_name']);
    $interest_rate = (float) $_POST['interest_rate'];
    $min_amount = (float) $_POST['min_amount'];
    $max_amount = (float) $_POST['max_amount'];
    $duration_months = (int) $_POST['duration_months'];
    $risk_level = htmlspecialchars($_POST['risk_level']);

    $stmt = $pdo->prepare("
        UPDATE investment_plans 
        SET plan_name = ?, interest_rate = ?, min_amount = ?, max_amount = ?, 
            duration_months = ?, risk_level = ?
        WHERE plan_id = ?
    ");
    $stmt->execute([$plan_name, $interest_rate, $min_amount, $max_amount, $duration_months, $risk_level, $plan_id]);

    // ✅ AUDIT LOG
    logAdminAction(
        $pdo,
        $_SESSION['user_id'],
        'Investment Plan Updated',
        "Plan ID: $plan_id | Name: $plan_name"
    );

    header("Location: manage-investments.php");
    exit();
}

/* ============================
   FETCH INVESTMENT PLANS
============================ */

$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$totalCount = $pdo->query("SELECT COUNT(*) FROM investment_plans")->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$investmentPlans = $pdo->prepare("
    SELECT * FROM investment_plans 
    LIMIT :perPage OFFSET :offset
");
$investmentPlans->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$investmentPlans->bindValue(':offset', $offset, PDO::PARAM_INT);
$investmentPlans->execute();
$investmentPlans = $investmentPlans->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   ADMIN PROFILE INFORMATION
============================ */

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT u.*, a.account_number, a.balance 
    FROM users u 
    LEFT JOIN accounts a ON u.user_id = a.user_id 
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('User account not found.');
}

$profilePic = !empty($user['profile_picture'])
    ? '../uploads/' . $user['profile_picture']
    : '../assets/images/default-avatars.png';

?>

<style>
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
.sidebar hr {
    width: 80%;
    margin: 15px auto;
    border: none;
    border-top: 1px solid #e2e6ef;
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
    <title>Manage Investments - Nexus Bank Admin</title>
    <link rel="stylesheet" href="../assets/css/admin-main.css">
    <link rel="stylesheet" href="../assets/css/admin-investment.css">

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
                                <a href="manage-investments.php" class="btn dash-text">
                        <img 
                        src="../assets/images/investment_active.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/investment.png"
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
            <h1>Manage Investments</h1>
            
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
            <!-- Add New Investment Plan Form -->
            <h2>Add New Investment Plan</h2>
            <form action="manage-investments.php" method="POST">
                <label for="plan_name">Plan Name:</label>
                <input type="text" id="plan_name" name="plan_name" required>
                
                <label for="interest_rate">Interest Rate (%):</label>
                <input type="number" id="interest_rate" name="interest_rate" required step="0.01">
                
                <label for="min_amount">Min Investment Amount:</label>
                <input type="number" id="min_amount" name="min_amount" required step="0.01">
                
                <label for="max_amount">Max Investment Amount:</label>
                <input type="number" id="max_amount" name="max_amount" required step="0.01">
                
                <label for="duration_months">Duration (Months):</label>
                <input type="number" id="duration_months" name="duration_months" required>
                
                <label for="risk_level">Risk Level:</label>
                <select id="risk_level" name="risk_level" required>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
                
                <button type="submit" name="add_plan">Add Plan</button>
            </form>

            <h2>Investment Plans</h2>
            <?php if (empty($investmentPlans)): ?>
                <p>No investment plans found.</p>
            <?php else: ?>
                <table class="investment-plans-table">
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Interest Rate</th>
                            <th>Min Investment</th>
                            <th>Max Investment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investmentPlans as $plan): ?>
                            <tr>
                                <td data-label="Plan Name"><?= htmlspecialchars($plan['plan_name']) ?></td>
                                <td data-label="Interest Rate"><?= htmlspecialchars($plan['interest_rate']) ?>%</td>
                                <td data-label="Min Investment">₹<?= number_format($plan['min_amount'] ?? 0, 2) ?></td>
                                <td data-label="Max Investment">₹<?= number_format($plan['max_amount'] ?? 0, 2) ?></td>
                                <td data-label="Action">
                                    <!-- Edit button for each plan -->
                                    <button onclick="openEditForm(<?= $plan['plan_id'] ?>, '<?= htmlspecialchars($plan['plan_name']) ?>', <?= $plan['interest_rate'] ?>, <?= $plan['min_amount'] ?>, <?= $plan['max_amount'] ?>, <?= $plan['duration_months'] ?>, '<?= htmlspecialchars($plan['risk_level']) ?>')">Edit</button>
                                </td>
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
    
    <!-- Edit Investment Plan Modal -->
    <div id="edit-modal" style="display:none;">
        <h2>Edit Investment Plan</h2>
        <form action="manage-investments.php" method="POST">
            <input type="hidden" id="edit_plan_id" name="plan_id">
            
            <label for="edit_plan_name">Plan Name:</label>
            <input type="text" id="edit_plan_name" name="plan_name" required>
            
            <label for="edit_interest_rate">Interest Rate (%):</label>
            <input type="number" id="edit_interest_rate" name="interest_rate" required step="0.01">
            
            <label for="edit_min_amount">Min Investment Amount:</label>
            <input type="number" id="edit_min_amount" name="min_amount" required step="0.01">
            
            <label for="edit_max_amount">Max Investment Amount:</label>
            <input type="number" id="edit_max_amount" name="max_amount" required step="0.01">
            
            <label for="edit_duration_months">Duration (Months):</label>
            <input type="number" id="edit_duration_months" name="duration_months" required>
            
            <label for="edit_risk_level">Risk Level:</label>
            <select id="edit_risk_level" name="risk_level" required>
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
            </select>
            
            <button type="submit" name="edit_plan">Save Changes</button>
            <button type="button" onclick="closeEditForm()">Cancel</button>
        </form>
    </div>
</main>
</div>
    <script>
        // Function to open the edit form modal and populate it with plan data
        function openEditForm(plan_id, plan_name, interest_rate, min_amount, max_amount, duration_months, risk_level) {
            document.getElementById('edit_plan_id').value = plan_id;
            document.getElementById('edit_plan_name').value = plan_name;
            document.getElementById('edit_interest_rate').value = interest_rate;
            document.getElementById('edit_min_amount').value = min_amount;
            document.getElementById('edit_max_amount').value = max_amount;
            document.getElementById('edit_duration_months').value = duration_months;
            document.getElementById('edit_risk_level').value = risk_level;
            
            document.getElementById('edit-modal').style.display = 'block';
        }

        // Function to close the edit form modal
        function closeEditForm() {
            document.getElementById('edit-modal').style.display = 'none';
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
