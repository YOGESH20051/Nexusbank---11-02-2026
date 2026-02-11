<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Notification ID missing']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notif_id = intval($_POST['id']); // sanitize input

try {
    $stmt = $pdo->prepare("DELETE FROM user_notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Notification not found or already deleted']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
