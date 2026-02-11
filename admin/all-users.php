<?php 
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification.php';


/* ===========================
   ADMIN ACCESS
=========================== */
redirectIfNotAdmin();

/* ===========================
   REDIRECT HELPER
=========================== */
function redirectBack() {
    header("Location: all-users.php");
    exit();
}

/* ===========================
   APPROVE USER
=========================== */
if (isset($_GET['accept']) && is_numeric($_GET['accept'])) {
    $userId = (int)$_GET['accept'];

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE users SET status='approved' WHERE user_id=?")->execute([$userId]);

        $check = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE user_id=?");
        $check->execute([$userId]);

        if (!$check->fetchColumn()) {
            $acc = generateUniqueAccountNumber($pdo);
            $pdo->prepare("INSERT INTO accounts (user_id,account_number,balance) VALUES (?,?,0)")
                ->execute([$userId,$acc]);
        }

        $pdo->commit();

        logAdminAction($pdo, $_SESSION['user_id'], 'USER_APPROVED', "Approved user ID $userId");
        $_SESSION['success'] = "User approved successfully.";

    } catch(Exception $e){
        $pdo->rollBack();
        $_SESSION['error'] = "Approval failed.";
    }

    redirectBack();
}

/* ===========================
   REJECT USER
=========================== */
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $userId = (int)$_GET['reject'];

    $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$userId]);

    logAdminAction($pdo, $_SESSION['user_id'], 'USER_REJECTED', "Rejected user ID $userId");
    $_SESSION['success'] = "User rejected & removed.";

    redirectBack();
}

/* ===========================
   DELETE USER
=========================== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int)$_GET['delete'];

    $stmt = $pdo->prepare("SELECT balance FROM accounts WHERE user_id=?");
    $stmt->execute([$userId]);
    $balance = $stmt->fetchColumn();

    if ($balance > 0) {
        $_SESSION['error'] = "Cannot delete user with balance.";
        redirectBack();
    }

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM accounts WHERE user_id=?")->execute([$userId]);
    $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$userId]);
    $pdo->commit();

    logAdminAction($pdo, $_SESSION['user_id'], 'USER_DELETED', "Deleted user ID $userId");
    $_SESSION['success'] = "User deleted successfully.";

    redirectBack();
}

/* ===========================
   TOGGLE ACTIVE STATUS
=========================== */
if (isset($_GET['toggle_active']) && is_numeric($_GET['toggle_active'])) {
    $userId = (int)$_GET['toggle_active'];
    $new = ($_GET['status'] == 1) ? 0 : 1;

    $pdo->prepare("UPDATE users SET is_active=? WHERE user_id=?")->execute([$new,$userId]);

    logAdminAction($pdo, $_SESSION['user_id'], 
        $new ? 'USER_ACTIVATED' : 'USER_DEACTIVATED', 
        "Changed status of user $userId"
    );

    $_SESSION['success'] = $new ? "User activated." : "User deactivated.";

    redirectBack();
}

/* ===========================
   FETCH USERS
=========================== */
$users = $pdo->query("
    SELECT u.*, a.account_number, a.balance
    FROM users u
    LEFT JOIN accounts a ON u.user_id = a.user_id
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>All Users — Nexus Bank</title>
<link rel="stylesheet" href="../assets/css/all-users.css">
</head>

<body>

<div class="card">

<a href="dashboard.php" class="back-link">← Back to Dashboard</a>

<h2>All Users</h2>

<!-- 🔔 STATUS MESSAGES -->
<?php if(isset($_SESSION['success'])): ?>
<div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="audit-table-wrapper">

<table>

<thead>
<tr>
<th>Name</th>
<th>Email</th>
<th>Account</th>
<th>Balance</th>
<th>Status</th>
<th>Active</th>
<th>Joined</th>
<th>Actions</th>
</tr>
</thead>

<tbody>
<?php foreach($users as $u): ?>
<tr>
<td><?= htmlspecialchars($u['full_name']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= $u['account_number'] ?? '—' ?></td>
<td>₹<?= number_format($u['balance'] ?? 0,2) ?></td>

<td>
<span class="badge <?= $u['status'] ?>">
<?= ucfirst($u['status']) ?>
</span>
</td>

<td>
<?php if($u['is_active']): ?>
<span class="status-dot active-dot"></span>
<span class="status-text active-text">Active</span>
<?php else: ?>
<span class="status-dot inactive-dot"></span>
<span class="status-text inactive-text">Inactive</span>
<?php endif; ?>
</td>

<td><?= date('d M Y', strtotime($u['created_at'])) ?></td>

<td class="actions">
    <a href="edit-user.php?user_id=<?= $u['user_id'] ?>" class="btn btn-sm btn-info">Edit</a>


<?php if($u['status'] !== 'approved'): ?>
<a href="?accept=<?= $u['user_id'] ?>">Approve</a>
<a class="danger" href="?reject=<?= $u['user_id'] ?>" onclick="return confirm('Reject this user?')">Reject</a>
<?php endif; ?>

<?php if($u['is_active']): ?>
<a class="warn" href="?toggle_active=<?= $u['user_id'] ?>&status=1"
   onclick="return confirm('Deactivate this user account?')">Deactivate</a>
<?php else: ?>
<a class="warn" href="?toggle_active=<?= $u['user_id'] ?>&status=0"
   onclick="return confirm('Activate this user account?')">Activate</a>
<?php endif; ?>

<?php if(($u['balance'] ?? 0) == 0): ?>
<a class="danger" href="?delete=<?= $u['user_id'] ?>"
   onclick="return confirm('Delete this user permanently?')">Delete</a>
<?php endif; ?>

</td>
</tr>
<?php endforeach ?>
</tbody>

</table>

</div>
</div>

</body>
</html>
