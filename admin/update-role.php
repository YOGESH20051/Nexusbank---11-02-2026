<?php
session_start();

require_once '../includes/db.php';
include __DIR__ . '/../includes/loader.php';
require_once '../includes/functions.php';

// Only admin allowed
redirectIfNotAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id  = (int) $_POST['user_id'];
    $is_admin = (int) $_POST['is_admin'];

    // Prevent admin from removing their own admin access
    if ($_SESSION['user_id'] == $user_id) {
        header("Location: role.php?error=self");
        exit;
    }

    // Update role
    $stmt = $pdo->prepare("UPDATE users SET is_admin = :is_admin WHERE user_id = :user_id");
    $stmt->execute([
        ':is_admin' => $is_admin,
        ':user_id'  => $user_id
    ]);

    // 🔐 AUDIT LOG — ADD RIGHT HERE
    $roleName = $is_admin ? 'Admin' : 'User';

    logAdminAction(
        $pdo,
        $_SESSION['user_id'],
        'Change User Role',
        "Admin changed user #$user_id role to: $roleName"
    );

    header("Location: role.php?success=1");
    exit;
}
