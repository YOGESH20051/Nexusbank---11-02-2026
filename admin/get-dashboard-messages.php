<?php
require_once '../includes/db.php';

$stmt = $pdo->prepare("
    SELECT message 
    FROM admin_messages 
    WHERE is_active = 1
    ORDER BY id DESC
");

$stmt->execute();

$messages = $stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json');
echo json_encode($messages);
