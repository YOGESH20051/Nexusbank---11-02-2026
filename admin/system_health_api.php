<?php
require_once '../includes/db.php';
session_start();

/* Database status */
$dbStatus = 'Offline';
try {
    $pdo->query("SELECT 1");
    $dbStatus = 'Connected';
} catch (Exception $e) {}

/* Failed logins today */
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM admin_logs
    WHERE action LIKE '%Failed%'
    AND DATE(created_at) = CURDATE()
");
$stmt->execute();
$failedLogins = $stmt->fetchColumn();

/* Active users (last 5 min) */
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT user_id)
    FROM user_sessions
    WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$stmt->execute();
$activeUsers = $stmt->fetchColumn();

echo json_encode([
    'db' => $dbStatus,
    'failed' => $failedLogins,
    'active' => $activeUsers
]);
