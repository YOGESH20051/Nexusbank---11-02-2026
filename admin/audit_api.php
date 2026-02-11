<?php
require_once '../includes/db.php';

$auditStmt = $pdo->prepare("
    SELECT action, created_at 
    FROM admin_logs
    ORDER BY created_at DESC
    LIMIT 5
");
$auditStmt->execute();

$logs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($logs);
