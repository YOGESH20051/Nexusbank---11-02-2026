<?php
require_once '../includes/db.php';

$account = $_GET['account'] ?? '';

$stmt = $pdo->prepare("
    SELECT users.full_name AS name
    FROM accounts
    JOIN users ON users.user_id = accounts.user_id
    WHERE accounts.account_id = ?
");

$stmt->execute([$account]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($row ?: ["name" => "Not Found"]);
