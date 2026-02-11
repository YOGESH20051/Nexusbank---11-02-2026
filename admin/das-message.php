<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

/* =========================
   HANDLE ADD MESSAGE
========================= */
/* UPDATE MESSAGE — MUST COME FIRST */
if (isset($_POST['edit_id']) && $_POST['edit_id'] !== '') {

    $editId = (int)$_POST['edit_id'];
    $msg = trim($_POST['new_message']);
    $type = $_POST['message_type'];
    $userId = ($type === 'user') ? (int)$_POST['user_id'] : null;

    $stmt = $pdo->prepare("
        UPDATE admin_messages
        SET message = ?, message_type = ?, user_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$msg, $type, $userId, $editId]);

    $_SESSION['success'] = "Message updated successfully.";
    header("Location: das-message.php");
    exit;
}

/* ADD MESSAGE — ONLY IF NOT EDITING */
if (isset($_POST['new_message'])) {

    $msg = trim($_POST['new_message']);
    $type = $_POST['message_type'];
    $userId = ($type === 'user') ? (int)$_POST['user_id'] : null;

    if ($msg !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO admin_messages (message, message_type, user_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$msg, $type, $userId]);
    }

    $_SESSION['success'] = "Message added successfully.";
    header("Location: das-message.php");
    exit;
}


/* =========================
   TOGGLE MESSAGE STATUS
========================= */
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->query("
        UPDATE admin_messages
        SET is_active = IF(is_active = 1, 0, 1)
        WHERE id = $id
    ");
    header("Location: das-message.php");
    exit;
}

/* =========================
   DELETE MESSAGE
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->query("DELETE FROM admin_messages WHERE id = $id");
    header("Location: das-message.php");
    exit;
}

// update from edit message form
$editMessage = null;

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM admin_messages WHERE id = ?");
    $stmt->execute([$id]);
    $editMessage = $stmt->fetch(PDO::FETCH_ASSOC);
}


/* =========================
   FETCH USERS (for dropdown)
========================= */
$users = $pdo->query("
    SELECT user_id, full_name, email
    FROM users
    WHERE is_active = 1
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH MESSAGES WITH USER NAME
========================= */
$messages = $pdo->query("
    SELECT 
        am.*,
        u.full_name AS user_name,
        u.email AS user_email
    FROM admin_messages am
    LEFT JOIN users u ON am.user_id = u.user_id
    ORDER BY am.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>DAS Messages</title>
  <style>
body{
    background: radial-gradient(circle at top, #020617, #020617);
    font-family: Segoe UI, sans-serif;
    color:#e6f9ff;
}

.panel{
    width:800px;
    margin:60px auto;
    background:rgba(10,15,35,.96);
    padding:35px 40px;
    border-radius:20px;
    box-shadow:0 0 40px rgba(0,255,255,.15);
}

h2{
    color:#5ee7ff;
    margin-bottom:5px;
}

.subtitle{
    color:#9fdfff;
    margin-bottom:20px;
}

input, select{
    width:100%;
    padding:14px;
    border-radius:12px;
    border:1px solid rgba(94,231,255,.35);
    background:#020617;
    color:#e6f9ff;
    margin-bottom:14px;
}

button{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:none;
    background:linear-gradient(90deg,#00e0ff,#0077ff);
    font-weight:700;
    font-size:15px;
    cursor:pointer;
    color:#00111f;
}

button:hover{
    opacity:.9;
}

table{
    width:100%;
    margin-top:25px;
    border-collapse:collapse;
}

th{
    text-align:left;
    padding:12px;
    color:#9fdfff;
    border-bottom:1px solid rgba(94,231,255,.25);
}

td{
    padding:12px;
    border-bottom:1px solid rgba(94,231,255,.08);
}

a{
    color:#5ee7ff;
    text-decoration:none;
    font-weight:600;
}

a:hover{
    text-decoration:underline;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}

.dashboard-btn{
    background:linear-gradient(90deg,#22c55e,#16a34a);
    padding:10px 18px;
    border-radius:12px;
    color:#00111f;
    font-weight:700;
    text-decoration:none;
}

.status{
    display:flex;
    align-items:center;
    gap:8px;
    font-weight:600;
}

.dot{
    width:10px;
    height:10px;
    border-radius:50%;
}

.active-dot{
    background:#22c55e;
    box-shadow:0 0 8px #22c55e;
}

.inactive-dot{
    background:#ef4444;
    box-shadow:0 0 8px #ef4444;
}

.success{
    background:rgba(34,197,94,.18);
    color:#22c55e;
    padding:12px;
    border-radius:12px;
    text-align:center;
    margin-bottom:15px;
}
</style>


    <script>
        function toggleUserField() {
            const type = document.getElementById('message_type').value;
            document.getElementById('user_id').style.display =
                type === 'user' ? 'block' : 'none';
        }
    </script>
</head>

<body>

<div class="panel">

<div class="header">
    <div>
        <h2>🧑‍💼 Admin Message Control Center</h2>
        <div class="subtitle">Manage system broadcast messages</div>
    </div>
    <a href="dashboard.php" class="dashboard-btn">⬅ Dashboard</a>
</div>

<?php if(isset($_SESSION['success'])): ?>
<div class="success">
    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="edit_id"
       value="<?= $editMessage['id'] ?? '' ?>">

<input type="text" name="new_message"
       placeholder="Enter new message..."
       value="<?= htmlspecialchars($editMessage['message'] ?? '') ?>"
       required>

<select name="message_type" id="message_type">
    <option value="broadcast"
        <?= isset($editMessage) && $editMessage['message_type']=='broadcast' ? 'selected':'' ?>>
        🌍 All Users
    </option>
    <option value="user"
        <?= isset($editMessage) && $editMessage['message_type']=='user' ? 'selected':'' ?>>
        👤 Specific User
    </option>
</select>

<select name="user_id" id="user_id"
        style="<?= isset($editMessage) && $editMessage['message_type']=='user' ? '':'display:none' ?>">
    <option value="">Select User</option>
    <?php foreach ($users as $u): ?>
        <option value="<?= $u['user_id'] ?>"
            <?= isset($editMessage) && $editMessage['user_id']==$u['user_id'] ? 'selected':'' ?>>
            <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['email']) ?>)
        </option>
    <?php endforeach; ?>
</select>

<button>
    <?= isset($editMessage) ? 'Update Message' : 'Add Message' ?>
</button>

</form>


<table>
<tr>
    <th>Message</th>
    <th>Target</th>
    <th>Status</th>
    <th>Actions</th>
</tr>

<?php foreach($messages as $m): ?>
<tr>
    <td><?= htmlspecialchars($m['message']) ?></td>

    <td>
        <?= $m['message_type']==='broadcast'
            ? '🌍 All Users'
            : '👤 User ID: '.(int)$m['user_id']; ?>
    </td>

    <td>
        <div class="status">
            <div class="dot <?= $m['is_active'] ? 'active-dot':'inactive-dot' ?>"></div>
            <?= $m['is_active'] ? 'Active':'Inactive' ?>
        </div>
    </td>

    <td>
    <a href="?edit=<?= $m['id'] ?>">Edit</a> |
    <a href="?toggle=<?= $m['id'] ?>">Toggle</a> |
    <a href="?delete=<?= $m['id'] ?>" onclick="return confirm('Delete message?')">Delete</a>
</td>
</tr>
<?php endforeach; ?>
</table>

</div>
<script>
document.getElementById('message_type').addEventListener('change', function () {
    document.getElementById('user_id').style.display =
        this.value === 'user' ? 'block' : 'none';
});
</script>


</body>
</html>
