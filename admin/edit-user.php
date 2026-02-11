<?php
require_once '../includes/db.php';

if (!isset($_GET['user_id'])) {
    die("User ID missing.");
}

$user_id = $_GET['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit User — Nexus Bank</title>

<link rel="stylesheet" href="../assets/css/admin-main.css">

<style>

body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", system-ui, sans-serif;
    background:
        radial-gradient(1200px 600px at 10% 50%, #0c1c3a 0%, transparent 60%),
        radial-gradient(900px 500px at 90% 10%, #020b22 0%, transparent 55%),
        linear-gradient(180deg, #020817, #01040d 60%);
    color: #e6f9ff;
}

.edit-card {
    max-width: 520px;
    margin: 80px auto;
    background: rgba(10,15,30,.92);
    border-radius: 18px;
    padding: 35px 40px;
    box-shadow: 0 0 25px rgba(0,255,255,.12);
}

.edit-card h2 {
    color: #5ee7ff;
    text-align: center;
    margin-bottom: 28px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 6px;
    color: #9fdfff;
    font-weight: 600;
}

input, select {
    width: 100%;
    padding: 11px 14px;
    border-radius: 8px;
    border: 1px solid rgba(94,231,255,.35);
    background: rgba(5,10,20,.9);
    color: #e6f9ff;
    font-size: 15px;
}

input:focus, select:focus {
    outline: none;
    border-color: #5ee7ff;
    box-shadow: 0 0 0 2px rgba(94,231,255,.2);
}

.save-btn {
    width: 100%;
    padding: 12px;
    margin-top: 15px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(90deg,#00e0ff,#0077ff);
    color: #00121c;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
}

.save-btn:hover {
    opacity: .9;
}
.back-btn {
    display: block;
    margin: 20px auto 0;
    text-align: center;
    color: #5ee7ff;
    text-decoration: none;
}
</style>
</head>

<body>

<div class="edit-card">

<h2>Edit User</h2>

<form action="update-user.php" method="POST">

<input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">

<div class="form-group">
<label>Full Name</label>
<input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
</div>

<div class="form-group">
<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
</div>

<div class="form-group">
<label>Account Type</label>
<select name="account_type">
    <option value="savings" <?= $user['account_type']=='savings'?'selected':'' ?>>Savings</option>
    <option value="current" <?= $user['account_type']=='current'?'selected':'' ?>>Current</option>
    <option value="fd" <?= $user['account_type']=='fd'?'selected':'' ?>>Fixed Deposit</option>
</select>
</div>

<div class="form-group">
<label>Interest Rate (%)</label>
<input type="number" step="0.01" name="interest_rate" value="<?= $user['interest_rate'] ?>" required>
</div>

<button class="save-btn" type="submit">Save Changes</button>

</form>

<a class="back-btn" href="all-users.php">← Back to Users</a>

</div>
</body>
</html>
