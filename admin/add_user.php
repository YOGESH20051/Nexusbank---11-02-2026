<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';


/* ============================================
   ACCESS CONTROL
============================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage-users.php");
    exit;
}

/* ============================================
   INPUT SANITIZATION
============================================ */
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$balance  = is_numeric($_POST['balance'] ?? null) ? $_POST['balance'] : 0;
$password = $_POST['password'] ?? '';

if (!$name || !$email || !$password) {
    $_SESSION['error'] = "All required fields must be filled.";
    header("Location: manage-users.php");
    exit;
}

/* ============================================
   DUPLICATE PROTECTION
============================================ */
$duplicateCheck = $pdo->prepare(
    "SELECT user_id FROM users WHERE email = :email OR phone = :phone LIMIT 1"
);
$duplicateCheck->execute([
    ':email' => $email,
    ':phone' => $phone
]);

if ($duplicateCheck->fetch()) {
    $_SESSION['error'] = "This email or phone number already exists.";
    header("Location: add-user.php");
    exit;
}

/* ============================================
   PASSWORD & ACCOUNT SETUP
============================================ */
$securePassword = password_hash($password, PASSWORD_DEFAULT);
$accountNumber  = 'SB' . random_int(10000000, 99999999);

/* ============================================
   DATABASE TRANSACTION
============================================ */
try {

    $pdo->beginTransaction();

    /* Create User */
    $createUser = $pdo->prepare("
        INSERT INTO users (full_name, email, phone, password_hash)
        VALUES (:name, :email, :phone, :password)
    ");
    $createUser->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':phone'    => $phone,
        ':password' => $securePassword
    ]);

    $userId = $pdo->lastInsertId();

    /* Create Bank Account */
    $createAccount = $pdo->prepare("
        INSERT INTO accounts (user_id, account_number, balance)
        VALUES (:uid, :acc, :bal)
    ");
    $createAccount->execute([
        ':uid' => $userId,
        ':acc' => $accountNumber,
        ':bal' => $balance
    ]);

    /* Admin Audit */
    logAdminAction(
        $pdo,
        $_SESSION['user_id'],
        'CREATE_USER',
        "Admin created user {$name} ({$email})"
    );

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "System error. Please try again.";
    header("Location: manage-users.php");
    exit;
}
$_SESSION['success'] = "User account created successfully.";
header("Location: add-user.php");
exit();
