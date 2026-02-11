<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
include __DIR__ . '/../includes/loader.php';
redirectIfNotAdmin();

/* Fetch logs */
$stmt = $pdo->query("
    SELECT 
        created_at,
        actor_name,
        actor_role,
        target_user_id,
        target_account_no,
        action,
        details,
        ip_address,
        user_agent
    FROM admin_audit_logs
    ORDER BY created_at DESC
    LIMIT 500
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Security Audit Logs</title>
<style>
body{
    background:#0b1020;
    color:#e5e7eb;
    font-family:Segoe UI, sans-serif;
}
.card{
    max-width:1200px;
    margin:50px auto;
    background:#020617;
    padding:25px;
    border-radius:14px;
    box-shadow:0 25px 60px rgba(0,0,0,.5);
}
h2{
    color:#38bdf8;
    margin-bottom:15px;
}
table{
    width:100%;
    border-collapse:collapse;
}
th,td{
    padding:10px;
    border-bottom:1px solid #1e293b;
    font-size:13px;
}
th{
    color:#38bdf8;
    text-align:left;
}
tr:hover{
    background:#0f172a;
}
.badge{
    padding:4px 8px;
    border-radius:6px;
    font-size:11px;
    font-weight:600;
}
.login{ background:#1d4ed8; }
.withdrawal{ background:#dc2626; }
.deposit{ background:#16a34a; }
.system{ background:#9333ea; }

.log-details strong { color:#38bdf8; }
.log-details .detail-text { color:#cbd5e1; font-size:0.9rem; }

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
</style>
</head>
<body>

<div class="card">

<a href="dashboard.php" class="back-link">← Back to Dashboard</a>
<h2>🔐 Admin Security Audit Logs</h2>

<table>
<tr>
    <th>Time</th>
    <th>Actor</th>
    <th>Role</th>
    <th>User ID</th>
    <th>Account No</th>
    <th>Action</th>
    <th>Details</th>
    <th>IP</th>
    <th>Agent</th>
</tr>


<?php foreach($logs as $log): ?>
<tr>
    <td><?= $log['created_at'] ?></td>
    <td><?= htmlspecialchars($log['actor_name']) ?></td>
    <td><?= strtoupper($log['actor_role']) ?></td>
    <td><?= $log['target_user_id'] ?? '—' ?></td>
    <td><?= $log['target_account_no'] ?? '—' ?></td>
    <td><span class="badge system"><?= htmlspecialchars($log['action']) ?></span></td>
    <td><?= htmlspecialchars($log['details']) ?></td>
    <td><?= $log['ip_address'] ?></td>
    <td><?= substr($log['user_agent'],0,50) ?>...</td>
</tr>

<?php endforeach; ?>

</table>
</div>

</body>
</html>
