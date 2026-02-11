<?php 
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/includes/mailer.php';
require_once 'includes/db.php';

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);

    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }

    // If no errors, proceed with sending email
    if (empty($errors)) {
        try {
            // Create a new PHPMailer instance
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

              // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'y.panhandler@gmail.com'; // Your Gmail
        $mail->Password   = 'zywczomponbfokzn'; // App Password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->SMTPOptions = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ],
];


            // Recipients
            $mail->setFrom('y.panhandler@gmail.com', 'Nexus Bank');
            $mail->addAddress('y.panhandler@gmail.com'); // Your receiving email
            $mail->addReplyTo($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Contact Form: " . $subject;
            
            // Create HTML email body
            $htmlBody = "
                <h2>New Contact Form Submission</h2>
                <p><strong>Name:</strong> {$name}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Subject:</strong> {$subject}</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br($message) . "</p>
            ";
            
            $mail->Body = $htmlBody;
            $mail->AltBody = "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}";

            // Send email
            $mail->send();
// Store contact message in database
try {
    $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, status) VALUES (?, ?, ?, ?, 'new')");
    $stmt->execute([$name, $email, $subject, $message]);

    // Clear error and set success
    unset($_SESSION['contact_error']);
    $_SESSION['contact_success'] = "Thank you for your message! We will get back to you soon.";

} catch (PDOException $e) {
    error_log("Error storing contact message: " . $e->getMessage());

    unset($_SESSION['contact_error']);
    $_SESSION['contact_success'] = "Thank you for your message! We will get back to you soon.";
}

} catch (Exception $e) {

    // Email failed — still keep success because message is saved
    error_log("Email sending failed: " . $mail->ErrorInfo);

    unset($_SESSION['contact_error']);
    $_SESSION['contact_success'] = "Message saved successfully, but email could not be delivered.";
}

} else {

    // Validation errors — show only error
    unset($_SESSION['contact_success']);
    $_SESSION['contact_error'] = implode("<br>", $errors);
}


    // Redirect back to contact page
    header("Location: contact.php");
    exit();
} else {
    // Invalid request method
    header('Location: contact.php');
    exit();
}
?>
