<?php
require_once "../includes/db.php";

logAdminAction(
    $pdo,
    $_SESSION['user_id'],
    'Report Download',
    'Downloaded transaction report Excel'
);

$where = "1";
if (!empty($_GET['from'])) $where .= " AND DATE(t.created_at) >= '{$_GET['from']}'";
if (!empty($_GET['to']))   $where .= " AND DATE(t.created_at) <= '{$_GET['to']}'";
if (!empty($_GET['type'])) $where .= " AND t.type = '{$_GET['type']}'";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=transactions.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['ID','User','Email','Type','Amount','Description','Date']);

$result = mysqli_query($conn, "
    SELECT t.id, u.full_name, u.email, t.type, t.amount, t.description, t.created_at
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE $where
    ORDER BY t.id DESC
");

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, $row);
}
exit;
