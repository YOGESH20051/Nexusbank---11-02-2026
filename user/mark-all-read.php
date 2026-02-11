<?php
session_start();
require_once 'db.php'; // your database connection

$userId = $_SESSION['user_id'] ?? 0;

if ($userId) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}
?>
