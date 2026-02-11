<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once '../includes/db.php';
require_once '../includes/functions.php';

/* =======================
   ADMIN AUTH CHECK
======================= */
redirectIfNotAdmin(); // this already checks role=admin

/* =======================
   FETCH LOGGED-IN ADMIN (FROM USERS TABLE)
======================= */
if (!isset($_SESSION['user_id'])) {
    die("Session expired. Please login again.");
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT user_id, full_name, email, profile_picture
    FROM users
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("Admin account not found.");
}

$profilePic = !empty($admin['profile_picture'])
    ? '../uploads/' . $admin['profile_picture']
    : '../assets/images/default-avatars.png';

/* =====================================================
   🔽 STEP 1 GOES EXACTLY HERE (ADD THIS)
===================================================== */
$userFilter = $_GET['user_id'] ?? '';
$fromDate   = $_GET['from_date'] ?? '';
$toDate     = $_GET['to_date'] ?? '';
/* ===================================================== */




/* =======================
   PAGINATION
======================= */
$perPage = 10;
$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;
/* =======================
   FILTERS (DATE + USER)
======================= */
$whereParts = [];
$params = [];

/* USER FILTER */
if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
    $whereParts[] = "u.user_id = :user_id";
    $params[':user_id'] = (int) $_GET['user_id'];
}

/* DATE FILTERS */
if (!empty($_GET['from_date'])) {
    $whereParts[] = "DATE(t.created_at) >= :from_date";
    $params[':from_date'] = $_GET['from_date'];
}

if (!empty($_GET['to_date'])) {
    $whereParts[] = "DATE(t.created_at) <= :to_date";
    $params[':to_date'] = $_GET['to_date'];
}

$where = $whereParts ? "WHERE " . implode(" AND ", $whereParts) : "";



/* =======================
   TOTAL RECORDS COUNT
======================= */

$countSql = "
SELECT COUNT(*) 
FROM transactions t
JOIN accounts a ON t.account_id = a.account_id
JOIN users u ON a.user_id = u.user_id
LEFT JOIN accounts ra ON t.related_account_id = ra.account_id
$where
";

$countStmt = $pdo->prepare($countSql);

/* Bind filters for count query */
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}

$countStmt->execute();
$totalRecords = $countStmt->fetchColumn();

/* Calculate total pages */
$totalPages = ceil($totalRecords / $perPage);




/* =======================
   FETCH TRANSACTIONS
======================= */
$sql = "
SELECT 
    t.transaction_id,
    t.type,
    t.amount,
    t.description,
    t.created_at,
    u.user_id,
    u.full_name,
    u.email,
    COALESCE(ra.account_number, 'N/A') AS related_account_number
FROM transactions t
JOIN accounts a ON t.account_id = a.account_id
JOIN users u ON a.user_id = u.user_id
LEFT JOIN accounts ra ON t.related_account_id = ra.account_id

$where
ORDER BY t.created_at DESC
LIMIT :limit OFFSET :offset

";

$stmt = $pdo->prepare($sql);

/* Bind filters */
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}

/* Bind pagination */
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);


$stmt->execute();

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
.pagination {
    text-align: center;
    margin: 20px 0;
}
.pagination a {
    display: inline-block;
    margin: 0 4px;
    padding: 6px 12px;
    color: #007bff;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}
.pagination a.btn-primary, .pagination a.active {
    background: #007bff;
    color: #fff;
    border-color: #007bff;
    pointer-events: none;
}
.pagination a:hover:not(.btn-primary):not(.active) {
    background: #f0f0f0;
}

.transactions-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 12px 25px rgba(0,0,0,0.08);
    padding: 20px 25px;
    margin-top: 20px;
}

.card-title {
    font-size: 22px;
    font-weight: 600;
    color: #3c3cc4;
    border-bottom: 3px solid #6b63ff;
    padding-bottom: 10px;
    margin-bottom: 18px;
}

/* Table layout */
.table-wrapper {
    overflow-x: auto;
}

.styled-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

/* Header */
.styled-table thead {
    background: #625ce3;
    color: white;
}

.styled-table th {
    padding: 12px 10px;
    text-align: left;
    font-weight: 600;
}

/* Body */
.styled-table td {
    padding: 11px 10px;
    border-bottom: 1px solid #e6e6f5;
}

.styled-table tbody tr:nth-child(even) {
    background: #f4f4ff;
}

.styled-table tbody tr:hover {
    background: #ecebff;
    transition: 0.2s;
}

.sidebar hr {
    width: 80%;
    margin: 15px auto;
    border: none;
    border-top: 1px solid #e2e6ef;
}

/* FILTER BAR */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
    align-items: center;
}

/* DATE INPUTS */
.filter-form input[type="date"] {
    padding: 9px 12px;
    border-radius: 10px;
    border: 2px solid #dcdcff;
    font-size: 14px;
    outline: none;
    transition: 0.25s;
}

.filter-form input[type="date"]:focus {
    border-color: #625ce3;
    box-shadow: 0 0 0 3px rgba(98,92,227,0.15);
}

/* BUTTON BASE */
.filter-form .btn {
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: 0.25s;
    font-weight: 500;
}

/* INDIVIDUAL BUTTONS */
.filter-form .btn-primary {
    background: #625ce3;
    color: #fff;
}

.filter-form .btn-primary:hover {
    background: #524bdc;
}

.filter-form .btn-danger {
    background: #ff5c5c;
    color: white;
}

.filter-form .btn-danger:hover {
    background: #e64b4b;
}

.filter-form .btn-success {
    background: #22c55e;
    color: white;
}

.filter-form .btn-success:hover {
    background: #16a34a;
}

.filter-form .btn-secondary {
    background: #f1f1f9;
    color: #333;
    border: 2px solid #ccc;
}

.filter-form .btn-secondary:hover {
    background: #e4e4f5;
}


/* USER FILTER DROPDOWN */
.filter-form select {
    padding: 9px 14px;
    min-width: 190px;
    border-radius: 12px;
    border: 2px solid #dcdcff;
    background: #ffffff;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    outline: none;
    cursor: pointer;
    appearance: none;
    background-image:
        linear-gradient(45deg, transparent 50%, #6b63ff 50%),
        linear-gradient(135deg, #6b63ff 50%, transparent 50%);
    background-position:
        calc(100% - 18px) 50%,
        calc(100% - 12px) 50%;
    background-size: 6px 6px, 6px 6px;
    background-repeat: no-repeat;
    transition: all 0.25s ease;
}

/* Hover */
.filter-form select:hover {
    border-color: #6b63ff;
}

/* Focus */
.filter-form select:focus {
    border-color: #625ce3;
    box-shadow: 0 0 0 3px rgba(98,92,227,0.18);
}

/* Dropdown options */
.filter-form select option {
    padding: 10px;
    font-size: 14px;
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
<title>All Transactions</title>
<link rel="stylesheet" href="../assets/css/admin-main.css">
<link rel="stylesheet" href="../assets/css/admin-all-transactions.css">

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
                    <h5><?= htmlspecialchars($admin['full_name']) ?></h5>
<p>Administrator</p>

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
                    <a href="recent_transactions.php" class="btn dash-text">
                        <img 
                        src="../assets/images/transaction_active.png" 
                        alt="transaction-logo" 
                        class="nav-icon"
                        data-default="../assets/images/transaction.png"
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
    <h1>All Transactions</h1>
    
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
  
<div class="card transactions-card">
 <a href="recent_transactions.php" class="back-link">&larr; Back to Recent Transactions</a>
</br></br>
    <h2 class="card-title">All Transactions</h2>
    

    <!-- FILTER FORM -->
    <form method="get" class="filter-form">

        <input type="date" name="from_date" value="<?= $_GET['from_date'] ?? '' ?>" required>
        <input type="date" name="to_date" value="<?= $_GET['to_date'] ?? '' ?>" required>

      <select name="user_id">
    <option value="">All Users</option>

    <?php
    $stmt = $pdo->query("SELECT user_id, full_name FROM users ORDER BY full_name");
    while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $selected = (isset($_GET['user_id']) && $_GET['user_id'] == $u['user_id']) ? 'selected' : '';
        echo "<option value='{$u['user_id']}' $selected>{$u['full_name']}</option>";
    }
    ?>
</select>



        <button type="submit" class="btn btn-primary">🔍 Filter</button>

        <?php if (!empty($_GET['from_date']) && !empty($_GET['to_date'])): ?>

<a href="download-transactions-pdf.php?
from_date=<?= urlencode($_GET['from_date']) ?>
&to_date=<?= urlencode($_GET['to_date']) ?>
&user_id=<?= urlencode($_GET['user_id'] ?? '') ?>"
class="btn btn-danger">
    🧾 Download PDF
</a>

<?php endif; ?>

<?php if (!empty($_GET['from_date']) && !empty($_GET['to_date'])): ?>

<a href="download-transactions-excel.php?
from_date=<?= urlencode($_GET['from_date']) ?>
&to_date=<?= urlencode($_GET['to_date']) ?>
&user_id=<?= urlencode($_GET['user_id'] ?? '') ?>"
class="btn btn-success">
    📁 Download Excel
</a>

<?php endif; ?>


        <button type="button" onclick="window.print()" class="btn btn-secondary">🖨 Print</button>
    </form>

    <!-- TABLE -->
    <div class="table-wrapper">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>User ID</th>
                    <th>User Full Name</th>
                    <th>User Email</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Related Account</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?= htmlspecialchars($transaction['transaction_id']) ?></td>
                            <td><?= htmlspecialchars($transaction['user_id']) ?></td>
                            <td><?= htmlspecialchars($transaction['full_name']) ?></td>
                            <td><?= htmlspecialchars($transaction['email']) ?></td>
                            <td><?= htmlspecialchars($transaction['type']) ?></td>
                            <td>₹<?= number_format($transaction['amount'], 2) ?></td>
                            <td><?= !empty($transaction['description']) ? htmlspecialchars($transaction['description']) : 'N/A' ?></td>
                            <td><?= !empty($transaction['related_account_number']) ? htmlspecialchars($transaction['related_account_number']) : 'N/A' ?></td>
                            <td><?= !empty($transaction['created_at']) ? date("Y-m-d H:i:s", strtotime($transaction['created_at'])) : 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:20px;">No transactions found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Pagination Controls (keep yours below this) -->

<?php if ($totalPages > 1): ?>

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
   
</main>
</div>

 </table>
    </div>
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
