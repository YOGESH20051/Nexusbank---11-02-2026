<?php
session_start();
require_once '../includes/db.php';
include __DIR__ . '/../includes/loader.php';
require_once '../includes/functions.php';

redirectIfNotAdmin();

$stmt = $pdo->query("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 500");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Security Logs</title>
<style>
body{background:#0b1020;color:#e5e7eb;font-family:Segoe UI;}
.card{max-width:1300px;margin:40px auto;background:#020617;padding:25px;border-radius:14px;box-shadow:0 25px 60px rgba(0,0,0,.5);}
h2{color:#38bdf8;}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px;border-bottom:1px solid #1e293b;font-size:13px;}
th{color:#38bdf8;}
tr:hover{background:#0f172a;}
.badge{padding:4px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#9333ea;}
.back-link{display:inline-block;margin-bottom:15px;color:#22d3ee;text-decoration:none;font-weight:600;}
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

.audit-table-wrapper {
    max-height: 460px;   /* ~10 rows */
    overflow-y: auto;
    border-radius: 14px;
}

/* Nice cyber scrollbar */
.audit-table-wrapper::-webkit-scrollbar {
    width: 8px;
}

.audit-table-wrapper::-webkit-scrollbar-track {
    background: #020617;
}

.audit-table-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(#22d3ee, #6366f1);
    border-radius: 10px;
}

</style>
</head>
<body>

<div class="card">
<a class="back-link" href="dashboard.php">← Back to Dashboard</a>
<h2>🔐 Admin Security Audit Logs</h2>
<div class="audit-table-wrapper">
<table>
<tr>
<th>Time</th>
<th>User</th>
<th>Account</th>
<th>Action</th>
<th>Details</th>
<th>IP</th>
<th>Device</th>
</tr>

<?php foreach($logs as $log): ?>
<tr>
<td><?= $log['created_at'] ?></td>
<td><?= htmlspecialchars($log['user_name']) ?></td>
<td><?= htmlspecialchars($log['account_number']) ?></td>
<td><span class="badge"><?= htmlspecialchars($log['action']) ?></span></td>
<td><?= htmlspecialchars($log['details']) ?></td>
<td><?= $log['ip_address'] ?></td>
<td><?= substr($log['user_agent'],0,50) ?>...</td>
</tr>
<?php endforeach; ?>

</table>
</div>
</div>
</body>
</html>
