<?php
/**
 * Page: Company Settings
 *
 * Manages all global settings for the company, including legal information,
 * HR policies, SMTP, and organizational structure.
 */

// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
// Define the secure include constant, as this file is loaded by the router
define('APP_SECURE_INCLUDE', true);

// Load necessary files
require_once __DIR__ . '../../../../includes/mailer.php'; // We need the mailer function

// --- Security and Authorization ---
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed.']);
    exit;
}


// --- Logic to Send Test Email ---
try {
    $test_email_address = filter_input(INPUT_POST, 'smtp_from', FILTER_VALIDATE_EMAIL);
    if (!$test_email_address) {
        throw new Exception("The provided 'From' email address is invalid.");
    }
    
    // Use PHPMailer directly for more control over error messages
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = trim($_POST['smtp_host'] ?? '');
    $mail->SMTPAuth = true;
    $mail->Username = trim($_POST['smtp_user'] ?? '');
    $mail->Password = $_POST['smtp_pass'] ?? ''; // Use password from form directly
    $mail->SMTPSecure = in_array($_POST['smtp_secure'], ['tls', 'ssl']) ? $_POST['smtp_secure'] : 'tls';
    $mail->Port = (int)($_POST['smtp_port'] ?? 587);
    $mail->CharSet = 'UTF-8';

    // Set from and to addresses
    $mail->setFrom($test_email_address, (trim($_POST['smtp_fromname']) ?: 'HR System Test'));
    $mail->addAddress($test_email_address); // Send the test email to itself

    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from HR System';
    $mail->Body = 'This is a test email to verify your SMTP settings are correct. If you received this, everything is working!';
    $mail->AltBody = 'This is a test email to verify your SMTP settings are correct.';

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Test email sent successfully to ' . htmlspecialchars($test_email_address)
    ]);

} catch (Exception $e) {
    // Return a detailed error message from PHPMailer
    echo json_encode([
        'status' => 'error',
        'message' => 'Connection failed: ' . strip_tags($e->getMessage()) // strip_tags to prevent HTML in error
    ]);
}