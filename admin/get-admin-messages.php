<?php
require_once '../includes/db.php';

$stmt = $pdo->query("SELECT message FROM admin_messages WHERE is_active = 1 ORDER BY id DESC");
$messages = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($messages);
