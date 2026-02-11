<?php
require_once '../includes/db.php';

if (!isset($_POST['user_id'])) {
    die("Invalid request.");
}

$user_id = $_POST['user_id'];
$full_name = $_POST['full_name'];
$email = $_POST['email'];
$account_type = $_POST['account_type'];
$interest_rate = $_POST['interest_rate'];

$stmt = $pdo->prepare("
    UPDATE users 
    SET full_name = ?, email = ?, account_type = ?, interest_rate = ?
    WHERE user_id = ?
");

$stmt->execute([$full_name, $email, $account_type, $interest_rate, $user_id]);

header("Location: all-users.php?updated=1");
exit;
