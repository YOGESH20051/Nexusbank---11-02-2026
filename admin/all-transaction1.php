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




<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="../assets/css/all-transaction1.css">
</head>

<body>
    
 <div class="wrapper">
            

<main class="container">
  
  
<div class="card transactions-card">
 <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
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
