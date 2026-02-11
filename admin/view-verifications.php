<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
include __DIR__ . '/../includes/loader.php';

redirectIfNotAdmin();

// 🧾 AUDIT LOG — Admin viewed verification logs
logAdminAction(
    $pdo,
    $_SESSION['user_id'],
    'Viewed Verification Logs',
    'Admin opened document verification activity page'
);


/* =======================
   FETCH VERIFICATION LOGS
======================= */
$stmt = $pdo->query("
    SELECT 
        v.token,
        r.report_type,
        r.admin_id,
        v.ip_address,
        v.verified_at
    FROM verification_logs v
    JOIN report_verifications r ON r.token = v.token
    ORDER BY v.verified_at DESC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Verification History</title>

<style>
body{
    background:#0b1020;
    color:#e5e7eb;
    font-family:Segoe UI;
}

.card{
    max-width:1000px;
    margin:60px auto;
    background:#020617;
    padding:25px;
    border-radius:14px;
}

/* Back Button */

.back-link{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:10px 20px;
    color:#00f7ff;
    background:rgba(0,255,255,0.08);
    border:1px solid rgba(0,255,255,0.4);
    border-radius:12px;
    text-decoration:none;
    font-weight:600;
    backdrop-filter: blur(6px);
    box-shadow:0 0 15px rgba(0,255,255,.4);
    transition:.3s;
}
.back-link:hover{
    background:rgba(0,255,255,0.18);
    box-shadow:0 0 25px rgba(0,255,255,.7);
    transform:translateY(-2px);
}

/* Table */
table{
    width:100%;
    border-collapse:collapse;
}
th,td{
    padding:12px;
    border-bottom:1px solid #1e293b;
}
th{
    color:#38bdf8;
    text-align:left;
}
tr:hover{
    background:#0f172a;
}

/* Scroll after ~10 rows */
.table-scroll{
    max-height:420px;
    overflow-y:auto;
    margin-top:15px;
    border-radius:10px;
}

/* Nexus Scrollbar */
.table-scroll::-webkit-scrollbar{
    width:8px;
}
.table-scroll::-webkit-scrollbar-track{
    background:transparent;
}
.table-scroll::-webkit-scrollbar-thumb{
    background:linear-gradient(180deg,#38bdf8,#2563eb);
    border-radius:10px;
}
</style>
</head>

<body>

<div class="card">

<a href="dashboard.php" class="back-link">← Back to Dashboard</a>

<h2>📄 Document Verification History</h2>

<div class="table-scroll">
<table>
<tr>
    <th>Report Type</th>
    <th>User Id</th>
    <th>Token</th>
    <th>IP Address</th>
    <th>Verified At</th>
</tr>

<?php foreach($logs as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['report_type']) ?></td>
    <td><?= $row['admin_id'] ?></td>
    <td><?= substr($row['token'],0,18) ?>...</td>
    <td><?= $row['ip_address'] ?></td>
    <td><?= $row['verified_at'] ?></td>
</tr>
<?php endforeach; ?>

</table>
</div>

</div>

</body>
</html>
