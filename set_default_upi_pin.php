<?php
require_once 'includes/db.php'; // adjust path if needed

try {

    // Hash default PIN
    $defaultPin = password_hash("1234", PASSWORD_DEFAULT);

    // Update only users with NULL UPI PIN
    $stmt = $pdo->prepare("UPDATE users SET upi_pin = ? WHERE upi_pin IS NULL");
    $stmt->execute([$defaultPin]);

    echo "Default UPI PIN assigned successfully!";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
