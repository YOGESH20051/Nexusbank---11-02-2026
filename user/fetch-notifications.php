<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['notifications'=>[], 'unreadCount'=>0]);
    exit;
}

// Fetch latest 5 notifications
$stmt = $pdo->prepare("
    SELECT id, message, is_read, created_at
    FROM user_notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

echo json_encode([
    'notifications' => $notifications,
    'unreadCount' => $unreadCount
]);
