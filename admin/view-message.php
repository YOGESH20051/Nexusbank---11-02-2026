<?php
session_start();

require_once '../includes/db.php';
require_once '../includes/mailer.php';
include __DIR__ . '/../includes/loader.php';
require_once '../includes/functions.php';

// Ensure only admin can access this page
redirectIfNotAdmin();

/* ===========================
   DELETE MESSAGE
=========================== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        // 🔐 AUDIT LOG
        logAdminAction(
            $pdo,
            $_SESSION['user_id'],
            "DELETE MESSAGE",
            "Deleted contact message ID " . $_GET['delete']
        );

        $_SESSION['success'] = "Message deleted successfully.";
        header("Location: manage-messages.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error deleting message: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete message.";
    }
}

/* ===========================
   VALIDATE MESSAGE ID
=========================== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage-messages.php');
    exit();
}

$message_id = $_GET['id'];
$reply_sent = false;
$reply_error = null;

try {
    /* ===========================
       FETCH MESSAGE
    =========================== */
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        header('Location: manage-messages.php');
        exit();
    }

    /* ===========================
       AUTO MARK AS READ
    =========================== */
    if ($message['status'] === 'new') {
        $updateStmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
        $updateStmt->execute([$message_id]);

        // 🔐 AUDIT LOG
        logAdminAction(
            $pdo,
            $_SESSION['user_id'],
            "OPEN MESSAGE",
            "Opened contact message ID $message_id"
        );
    }

    /* ===========================
       UPDATE STATUS
    =========================== */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
        $newStatus = $_POST['status'];
        if (in_array($newStatus, ['read', 'replied'])) {
            $updateStmt = $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $message_id]);

            // 🔐 AUDIT LOG
            logAdminAction(
                $pdo,
                $_SESSION['user_id'],
                "UPDATE MESSAGE STATUS",
                "Changed message ID $message_id status to $newStatus"
            );

            header("Location: view-message.php?id=" . $message_id);
            exit();
        }
    }

    /* ===========================
       SEND REPLY
    =========================== */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
        $reply_message = trim($_POST['reply_message']);

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'y.panhandler@gmail.com';
            $mail->Password   = 'zywczomponbfokzn';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('y.panhandler@gmail.com', 'Nexus Bank');
            $mail->addAddress($message['email'], $message['name']);

            $mail->isHTML(true);
            $mail->Subject = 'Reg: ' . $message['subject'];
            $mail->Body    = nl2br($reply_message);
            $mail->AltBody = strip_tags($reply_message);

            $mail->send();

            $updateStmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
            $updateStmt->execute([$message_id]);

            // 🔐 AUDIT LOG
            logAdminAction(
                $pdo,
                $_SESSION['user_id'],
                "REPLY MESSAGE",
                "Replied to message ID $message_id | Email: {$message['email']}"
            );

            $reply_sent = true;

        } catch (Exception $e) {
            $reply_error = "Failed to send reply: " . $mail->ErrorInfo;
            error_log("Mail Error: " . $mail->ErrorInfo);
        }
    }

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

/* ===========================
   USER PROFILE (HEADER)
=========================== */
$stmt = $pdo->prepare("
    SELECT u.full_name, u.profile_picture, a.account_number, a.balance
    FROM users u
    JOIN accounts a ON u.user_id = a.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profilePic = !empty($user['profile_picture'])
    ? '../uploads/' . $user['profile_picture']
    : '../assets/images/default-avatars.png';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>View Message</title>
    <link rel="stylesheet" href="../assets/css/admin-main.css">
    <style>
        .message-container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .message-header {
            border-bottom: 1px solid #ddd;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .message-header h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .message-meta {
            color: #666;
            font-size: 0.9em;
        }
        .message-content {
            line-height: 1.6;
            margin: 20px 0;
        }
        .message-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .status-form {
            display: inline-block;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            display: inline-block;
            width: auto;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        .close-modal {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
        }
        .close-modal:hover {
            color: #000;
        }
        .reply-form {
            margin-top: 20px;
        }
        .reply-form textarea {
            width: 100%;
            min-height: 200px;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .modal-header h3 {
            margin: 0;
            color: #333;
        }

         .logout {
    padding: 9px 20px;
    border-radius: 50px;
    color: #444;
    font-weight: 600;
    background: #f1f3f8;
    text-decoration: none;
    box-shadow: 0 5px 12px rgba(0,0,0,0.12);
    transition: 0.3s;
}

.logout:hover {
    background: #ff4d4d;
    color: #fff;
    box-shadow: 0 10px 18px rgba(255,77,77,0.35);
}


/* Default: hide scrollbar completely */
body {
    overflow-y: hidden;
}

/* When scrolling is active */
body.scrolling {
    overflow-y: auto;
}

/* Theme scrollbar */
body.scrolling::-webkit-scrollbar {
    width: 8px;
}

body.scrolling::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg,#6366f1,#4f46e5);
    border-radius: 10px;
}

/* Firefox */
body.scrolling {
    scrollbar-width: thin;
    scrollbar-color: #6366f1 transparent;
}


/* Hide by default */
.scroll-container::-webkit-scrollbar {
    width: 0;
}

/* Visible only when scrolling */
.scrolling .scroll-container::-webkit-scrollbar {
    width: 8px;
}

.scrolling .scroll-container::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg,#6366f1,#4f46e5);
    border-radius: 12px;
}

/* Firefox */
.scroll-container {
    scrollbar-width: none;
}

.scrolling .scroll-container {
    scrollbar-width: thin;
    scrollbar-color: #6366f1 transparent;
}

/* Sidebar scrolling */
.sidebar {
    width: 250px;
    height: 100vh;
    overflow-y: auto;
}

/* Hide scrollbar completely by default */
.sidebar::-webkit-scrollbar {
    width: 0px;
}

/* When active */
.sidebar.show-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.sidebar.show-scrollbar::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg,#6366f1,#4f46e5);
    border-radius: 10px;
}

/* Firefox */
.sidebar {
    scrollbar-width: none;
}

.sidebar.show-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #6366f1 transparent;
}


    </style>
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="Logos-cont">
                <img src="../assets/images/Logo-color.png" alt="SecureBank Logo" class="logo-container">
            </div>

            
                <hr>

                <div class="profile-container">
                    <img src="<?= $profilePic ?>" alt="Profile Picture" class="img-fluid">
                    <h5><?= htmlspecialchars($user['full_name']) ?></h5>
                    <p><?= htmlspecialchars($user['account_number']) ?></p>
                </div>

                <hr>

            <nav class="dashboard-nav">
                <a href="dashboard.php" class="btn">
                                <img 
                                src="../assets/images/dashboard_logo.png" 
                                alt="dashboard-logo" 
                                class="nav-icon"
                                data-default="../assets/images/hover-dashboard.png"
                                data-hover="../assets/images/hover-dashboard.png"
                                > 
                                Dashboard
                               </a>
                <a href="manage-users.php" class="btn">
                        <img 
                        src="../assets/images/manageusers.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/manageusers_active.png"
                        data-hover="../assets/images/manageusers_active.png"
                        > 
                        Manage Users
                    </a>
                 <a href="manage-loans.php" class="btn">
                        <img 
                        src="../assets/images/manageloan.png" 
                        alt="manage-loan-logo" 
                        class="nav-icon"
                        data-default="../assets/images/manageloan_active.png"
                        data-hover="../assets/images/manageloan_active.png"
                        > 
                        Manage Loans
                    </a>
                <a href="manage-investments.php" class="btn">
                        <img 
                        src="../assets/images/investment.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/investment_active.png"
                        data-hover="../assets/images/investment_active.png"
                        > 
                        Manage Investments
                    </a>
                <a href="track-investments.php" class="btn">
                        <img 
                        src="../assets/images/userinvestment.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/userinvestment_active.png"
                        data-hover="../assets/images/userinvestment_active.png"
                        > 
                        Users Investments
                    </a>
                <a href="role.php" class="btn">
                        <img 
                        src="../assets/images/roles_logo.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/rolesact_logo.png"
                        data-hover="../assets/images/rolesactive_logo.png"
                        > 
                        Roles
                    </a>
                <a href="admin-transaction.php" class="btn">
                        <img 
                        src="../assets/images/admin.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/admin_active.png"
                        data-hover="../assets/images/admin_active.png"
                        > 
                        Admin Transaction
                    </a>
                <a href="recent_transactions.php" class="btn">
                        <img 
                        src="../assets/images/transaction.png" 
                        alt="transaction-logo" 
                        class="nav-icon"
                        data-default="../assets/images/transaction_active.png"
                        data-hover="../assets/images/transaction_active.png"
                        > 
                        Transactions
                    </a>
                <a href="loan-history.php" class="btn">
                        <img 
                        src="../assets/images/loan.png" 
                        alt="loan-record-logo" 
                        class="nav-icon"
                        data-default="../assets/images/loan_active.png"
                        data-hover="../assets/images/loan_active.png"
                        > 
                         Loan History
                    </a>
                 <a href="login-records.php" class="btn">
                        <img 
                        src="../assets/images/loginrecord.png" 
                        alt="login-record-logo" 
                        class="nav-icon"
                        data-default="../assets/images/loginrecord_active.png"
                        data-hover="../assets/images/loginrecord_active.png"
                        > 
                         Login Records
                    </a>
                <a href="add_news.php" class="btn">
                        <img 
                        src="../assets/images/announcement.png" 
                        alt="role-logo" 
                        class="nav-icon"
                        data-default="../assets/images/announcement_active.png"
                        data-hover="../assets/images/announcement_active.png"
                        > 
                         Newscast
                    </a>
                <a href="view-message.php" class="btn dash-text">
                        <img 
                        src="../assets/images/contact_active.png" 
                        alt="message-logo" 
                        class="nav-icon"
                        data-default="../assets/images/contact.png"
                        data-hover="../assets/images/contact_active.png"
                        > 
                        Messages
                    </a>
                    <a href="view-verifications.php" class="btn">
                        <img 
                        src="../assets/images/verify.png" 
                        alt="message-logo" 
                        class="nav-icon"
                        data-default="../assets/images/verify_active.png"
                        data-hover="../assets/images/verify_active.png"
                        > 
                        Verification Reports
                    </a>
                    <a href="security-logs.php" class="btn ">
                        <img 
                        src="../assets/images/audit.png" 
                        alt="message-logo" 
                        class="nav-icon"
                        data-default="../assets/images/audit_active.png"
                        data-hover="../assets/images/audit_active.png"
                        > 
                        Audit Logs
                    </a>
                    <a href="das-message.php" class="btn ">
                        <img 
                        src="../assets/images/das.png" 
                        alt="das-message-logo" 
                        class="nav-icon"
                        data-default="../assets/images/das_active.png"
                        data-hover="../assets/images/das_active.png"
                        > 
                        Manage Messages
                    </a>
            </nav>

    </br></br>
        </aside>

        <main class="container">
            <header>
                <h1>View Message</h1>
                
                 <div class="logout-wrap">
    <a href="../logout.php" class="logout" onclick="openLogoutModal()">
        ⏻
    </a>
    <span class="logout-msg">Logout</span>
</div>

<!-- Logout Modal -->
<div id="logoutModal" class="logout-modal">
    <div class="logout-modal-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>

        <div class="logout-actions">
            <button class="btn-yes" onclick="confirmLogout()">Yes, Logout</button>
            <button class="btn-no" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>
</div>
        <button class="hamburger">&#9776;</button> <!-- Hamburger icon -->
            </header>

            <div class="content scroll-container">
                <a href="manage-messages.php" class="back-link">&larr; Back to Messages</a>

                <?php if ($reply_sent): ?>
                    <div class="alert alert-success">Reply sent successfully!</div>
                <?php endif; ?>

                <?php if ($reply_error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($reply_error); ?></div>
                <?php endif; ?>

                <div class="message-container">
                    <div class="message-header">
                        <h2><?php echo htmlspecialchars($message['subject']); ?></h2>
                        <div class="message-meta">
                            <p><strong>From:</strong> <?php echo htmlspecialchars($message['name']); ?> (<?php echo htmlspecialchars($message['email']); ?>)</p>
                            <p><strong>Date:</strong> <?php echo date("F j, Y, g:i a", strtotime($message['created_at'])); ?></p>
                            <p><strong>Status:</strong> <?php echo ucfirst($message['status']); ?></p>
                        </div>
                    </div>

                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                    </div>

                    <div class="message-actions">
                        <form method="POST" class="status-form">
                            <input type="hidden" name="status" value="replied">
                            <button type="submit" class="btn btn-success">Mark as Replied</button>
                        </form>
                        <button onclick="openReplyModal()" class="btn btn-primary">Reply via Email</button>
                        <a href="view-message.php?delete=<?php echo $message['id']; ?>" 
                           class="btn btn-danger" 
                           onclick="return confirm('Are you sure you want to delete this message?')">Delete</a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Reply Modal -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeReplyModal()">&times;</span>
            <div class="modal-header">
                <h3>Reply to: <?php echo htmlspecialchars($message['name']); ?></h3>
                <p>Subject: Reg: <?php echo htmlspecialchars($message['subject']); ?></p>
            </div>
            <form method="POST" class="reply-form">
                <textarea name="reply_message" placeholder="Type your reply here..." required></textarea>
                <button type="submit" class="btn btn-primary">Send Reply</button>
            </form>
        </div>
    </div>

    <script>
        function openReplyModal() {
            document.getElementById('replyModal').style.display = 'block';
        }

        function closeReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('replyModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>


<script>
function openLogoutModal() {
    document.getElementById("logoutModal").style.display = "block";
}

function closeLogoutModal() {
    document.getElementById("logoutModal").style.display = "none";
}

function confirmLogout() {
    window.location.href = "logout.php";
}
</script>


<script>
let scrollTimer;

function handleScroll(){
    document.body.classList.add('scrolling');
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
        document.body.classList.remove('scrolling');
    }, 800);
}

window.addEventListener('wheel', handleScroll, { passive: true });
window.addEventListener('touchmove', handleScroll, { passive: true });
window.addEventListener('scroll', handleScroll, { passive: true });
</script>
<script>
const sidebar = document.querySelector('.sidebar');
let timer;

sidebar.addEventListener('scroll', () => {
    sidebar.classList.add('show-scrollbar');
    clearTimeout(timer);
    timer = setTimeout(() => {
        sidebar.classList.remove('show-scrollbar');
    }, 700);
});
</script>

</body>
</html> 