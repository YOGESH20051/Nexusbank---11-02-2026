<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification.php';


/* ================= ADMIN PROTECTION ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login.php");
    exit;
}

/* ================= FORM SUBMIT CHECK ================= */
if (isset($_POST['submit'])) {

    $account_id = $_POST['account_id'] ?? null;
    $type       = $_POST['type'] ?? null;
    $amount     = floatval($_POST['amount'] ?? 0);
    $target     = $_POST['related_account'] ?? null;

    /* ================= BASIC VALIDATION ================= */
    if ($amount <= 0) {
        $_SESSION['error'] = "Invalid amount entered.";
        header("Location: admin-transaction.php");
        exit;
    }

    try {

        $pdo->beginTransaction();

        /* ================= FETCH SOURCE ACCOUNT + USER ================= */
        $stmt = $pdo->prepare("
            SELECT a.account_number, a.balance,
                   u.user_id, u.full_name, u.email
            FROM accounts a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.account_id = ?
        ");
        $stmt->execute([$account_id]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$source) {
            throw new Exception("Source account not found.");
        }

        /* ✅ FIX: Define Sender User ID */
        $user_id = $source['user_id'];

        /* ================= DEPOSIT ================= */
        if ($type === 'deposit') {

            // Update balance
            $stmt = $pdo->prepare("
                UPDATE accounts 
                SET balance = balance + ?
                WHERE account_id = ?
            ");
            $stmt->execute([$amount, $account_id]);

            // Insert transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions
                (account_id, type, amount, description, created_at, performed_by)
                VALUES (?, 'deposit', ?, 'Deposit by Admin', NOW(), 'admin')
            ");
            $stmt->execute([$account_id, $amount]);

            // ✅ Popup Notification (Credit)
            $stmt = $pdo->prepare("
                INSERT INTO user_notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'credit')
            ");
            $stmt->execute([
                $user_id,
                "Deposit Successful",
                "₹" . number_format($amount, 2) .
                " deposited into your account by Admin."
            ]);

            // ✅ Email Alert
            sendNotification(
                $source['email'],
                "Deposit Successful - Nexus Bank",
                "
                Hello <strong>" . htmlspecialchars($source['full_name']) . "</strong>,<br><br>
                ✅ Admin deposited <strong>₹" . number_format($amount, 2) . "</strong> into your account.<br>
                Account Number: <strong>{$source['account_number']}</strong><br><br>
                Thank you,<br><b>Nexus Bank</b>
                "
            );
        }

        /* ================= WITHDRAW ================= */
        elseif ($type === 'withdraw') {

            if ($source['balance'] < $amount) {
                throw new Exception("Insufficient balance in user account.");
            }

            // Update balance
            $stmt = $pdo->prepare("
                UPDATE accounts 
                SET balance = balance - ?
                WHERE account_id = ?
            ");
            $stmt->execute([$amount, $account_id]);

            // Insert transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions
                (account_id, type, amount, description, created_at, performed_by)
                VALUES (?, 'withdraw', ?, 'Withdraw by Admin', NOW(), 'admin')
            ");
            $stmt->execute([$account_id, -$amount]);

            // ✅ Popup Notification (Debit)
            $stmt = $pdo->prepare("
                INSERT INTO user_notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'debit')
            ");
            $stmt->execute([
                $user_id,
                "Withdrawal Alert",
                "₹" . number_format($amount, 2) .
                " withdrawn from your account by Admin."
            ]);

            // ✅ Email Alert
            sendNotification(
                $source['email'],
                "Withdrawal Alert - Nexus Bank",
                "
                Hello <strong>" . htmlspecialchars($source['full_name']) . "</strong>,<br><br>
                ⚠ Admin withdrew <strong>₹" . number_format($amount, 2) . "</strong> from your account.<br>
                Account Number: <strong>{$source['account_number']}</strong><br><br>
                If this was not authorized, contact Nexus Bank immediately.<br><br>
                Thank you,<br><b>Nexus Bank</b>
                "
            );
        }

        /* ================= TRANSFER ================= */
        elseif ($type === 'transfer') {

            if (empty($target)) {
                throw new Exception("Target account is required.");
            }

            /* Fetch Target Account + User */
            $stmt = $pdo->prepare("
                SELECT a.account_id, a.account_number, a.balance,
                       u.user_id, u.full_name, u.email
                FROM accounts a
                JOIN users u ON a.user_id = u.user_id
                WHERE a.account_id = ?
            ");
            $stmt->execute([$target]);
            $targetAcc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetAcc) {
                throw new Exception("Target account not found.");
            }

            if ($account_id == $targetAcc['account_id']) {
                throw new Exception("Cannot transfer to the same account.");
            }

            if ($source['balance'] < $amount) {
                throw new Exception("Insufficient balance for transfer.");
            }

            /* Deduct Sender */
            $stmt = $pdo->prepare("
                UPDATE accounts 
                SET balance = balance - ?
                WHERE account_id = ?
            ");
            $stmt->execute([$amount, $account_id]);

            /* Credit Recipient */
            $stmt = $pdo->prepare("
                UPDATE accounts 
                SET balance = balance + ?
                WHERE account_id = ?
            ");
            $stmt->execute([$amount, $targetAcc['account_id']]);

            /* Sender Transaction */
            $stmt = $pdo->prepare("
                INSERT INTO transactions
                (account_id, type, amount, description, related_account_id, created_at, performed_by)
                VALUES (?, 'transfer_out', ?, ?, ?, NOW(), 'admin')
            ");
            $stmt->execute([
                $account_id,
                -$amount,
                "Transfer to account " . $targetAcc['account_number'],
                $targetAcc['account_id']
            ]);

            /* Recipient Transaction */
            $stmt = $pdo->prepare("
                INSERT INTO transactions
                (account_id, type, amount, description, related_account_id, created_at, performed_by)
                VALUES (?, 'transfer_in', ?, ?, ?, NOW(), 'admin')
            ");
            $stmt->execute([
                $targetAcc['account_id'],
                $amount,
                "Received from account " . $source['account_number'],
                $account_id
            ]);

            /* ================= USER NOTIFICATIONS ================= */

            // ✅ Sender Notification
            $stmt = $pdo->prepare("
                INSERT INTO user_notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'debit')
            ");
            $stmt->execute([
                $user_id,
                "Money Sent",
                "₹" . number_format($amount, 2) .
                " transferred to account " . $targetAcc['account_number']
            ]);

            // ✅ Receiver Notification
            $stmt = $pdo->prepare("
                INSERT INTO user_notifications (user_id, title, message, type)
                VALUES (?, ?, ?, 'credit')
            ");
            $stmt->execute([
                $targetAcc['user_id'],
                "Money Received",
                "₹" . number_format($amount, 2) .
                " received from account " . $source['account_number']
            ]);

            /* ================= EMAIL ALERTS ================= */

            // Sender Email
            sendNotification(
                $source['email'],
                "Transfer Successful - Nexus Bank",
                "
                Hello <strong>" . htmlspecialchars($source['full_name']) . "</strong>,<br><br>
                ✅ Admin transferred <strong>₹" . number_format($amount, 2) . "</strong> from your account.<br>
                Sent To: <strong>{$targetAcc['account_number']}</strong><br><br>
                Thank you,<br><b>Nexus Bank</b>
                "
            );

            // Recipient Email
            sendNotification(
                $targetAcc['email'],
                "You've Received a Transfer - Nexus Bank",
                "
                Hello <strong>" . htmlspecialchars($targetAcc['full_name']) . "</strong>,<br><br>
                🎉 Admin credited <strong>₹" . number_format($amount, 2) . "</strong> into your account.<br>
                From Account: <strong>{$source['account_number']}</strong><br><br>
                Thank you,<br><b>Nexus Bank</b>
                "
            );
        }

        /* ================= COMMIT ================= */
        $pdo->commit();

        /* ================= AUDIT LOG ================= */
        $actionDesc = "Admin performed $type transaction of ₹" . number_format($amount, 2);

        logAdminAction(
            $pdo,
            $_SESSION['user_id'],
            strtoupper($type),
            $actionDesc
        );

        $_SESSION['success'] = "Transaction completed successfully.";
        header("Location: admin-transaction.php");
        exit;

    } catch (Exception $e) {

        $pdo->rollBack();
        $_SESSION['error'] = "Transaction failed: " . $e->getMessage();
        header("Location: admin-transaction.php");
        exit;
    }
}
?>
