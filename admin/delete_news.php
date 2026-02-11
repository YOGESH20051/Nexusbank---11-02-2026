<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$id = $_GET['id'] ?? 0;

// Delete news
$stmt = $pdo->prepare("DELETE FROM bank_news WHERE id=?");
$stmt->execute([$id]);

// 🔐 ADMIN AUDIT LOG — ADD HERE
logAdminAction(
    $pdo,
    $_SESSION['user_id'],
    'Delete News',
    'Admin deleted news item ID: ' . $id
);

header("Location: add_news.php");
exit;
