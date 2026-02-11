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

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Loan History — Nexus Bank Admin</title>

<link rel="stylesheet" href="../assets/css/admin-main.css">
<link rel="stylesheet" href="../assets/css/loan-shortcut.css">

<script src="../assets/js/sidebar.js" defer></script>
</head>
<body>

</br></br>


 <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
   </br></br>

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
                    <td><?= $entry['loan_id'] ?></td>
                    <td><?= htmlspecialchars($entry['full_name']) ?></td>
                    <td><?= htmlspecialchars($entry['email']) ?></td>
                    <td>₹<?= number_format($entry['amount'], 2) ?></td>
                    <td><?= $entry['interest_rate'] ?>%</td>
                    <td><?= htmlspecialchars($entry['purpose']) ?></td>

                    <td>
                        <span class="status-badge status-<?= strtolower($entry['status']) ?>">
                            <?= ucfirst($entry['status']) ?>
                        </span>
                    </td>

                    <td><?= date('M d, Y - h:i A', strtotime($entry['ph_time'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

        <!-- ================= PAGINATION ================= -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>


</body>
</html>
