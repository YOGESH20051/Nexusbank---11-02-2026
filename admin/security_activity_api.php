<?php
require_once '../includes/db.php';

$stmt = $pdo->query("
    SELECT action, details, created_at
    FROM admin_logs
    ORDER BY created_at DESC
    LIMIT 5
");

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($logs);
