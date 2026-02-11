<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";   // ← needed for logAdminAction()

$data = json_decode(file_get_contents("php://input"), true);
$command = strtolower($data['command']);
$user_id = $_SESSION['user_id'];

$response = "Sorry, I did not understand.";

/* 🧾 AUDIT — Voice banking used */
logAdminAction(
    $pdo,
    $_SESSION['user_id'],
    'Voice Banking',
    'Used voice banking command'
);

if (strpos($command, "balance") !== false) {
    $q = mysqli_query($conn,
        "SELECT balance FROM accounts WHERE user_id='$user_id'");
    $row = mysqli_fetch_assoc($q);
    $response = "Your current balance is rupees " . $row['balance'];
}

elseif (strpos($command, "last transaction") !== false) {
    $q = mysqli_query($conn,
        "SELECT amount FROM transactions 
         WHERE user_id='$user_id' 
         ORDER BY id DESC LIMIT 1");
    $row = mysqli_fetch_assoc($q);
    $response = "Your last transaction was rupees " . $row['amount'];
}

/* SAFE MODE TRANSFER */
elseif (preg_match('/transfer (\d+) to (.+)/', $command, $m)) {
    $amount = $m[1];
    $name = trim($m[2]);
    $response = "Please confirm transfer of rupees $amount to $name using OTP.";
}

echo json_encode(["message" => $response]);
