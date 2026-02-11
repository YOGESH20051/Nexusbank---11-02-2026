<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

redirectIfNotLoggedIn();
?>

<!DOCTYPE html>
<html>
<head>
<title>Add New User — Nexus Bank</title>
<link rel="stylesheet" href="../assets/css/add-user.css">
</head>

<body>

<main class="page">

<a href="dashboard.php" class="back-link">← Back to Dashboard</a>

<h1>Add New User</h1>

<!-- 🔔 STATUS MESSAGES -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert success">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert error">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert success">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert error">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>


<form action="add_user.php" method="post" class="add-user-card">

<div class="form-grid">
    <input type="text" name="name" placeholder="Full Name" required>
    <input type="tel" name="phone" placeholder="Phone Number" required>
    <input type="email" name="email" placeholder="Email Address" required>
    <input type="password" name="password" placeholder="Temporary Password" required>
    <input type="number" name="balance" placeholder="Opening Balance" required>
</div>

<button class="create-btn">Create Account</button>

</form>

</main>
<script>
setTimeout(() => {
    const alert = document.querySelector('.alert');
    if(alert){
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 400);
    }
}, 5000);
</script>

</body>
</html>
