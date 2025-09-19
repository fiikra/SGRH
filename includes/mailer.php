<?php
// --- Bring in PHPMailer classes into the global namespace ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- Manually include the PHPMailer files from your lib directory ---
// The path goes up one level from 'includes' to the project root, then into 'lib'.
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer/SMTP.php';

function send_otp_email(string $recipient_email, string $otp_code): bool {
    global $db; // Use the global database connection from config.php

    // --- Fetch SMTP settings from the database ---
    $settings = $db->query("SELECT * FROM smtp_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        error_log("Mailer Error: SMTP settings are not configured.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // --- Server settings ---
        if ($settings['method'] === 'smtp') {
            $mail->isSMTP();
            $mail->Host       = $settings['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['username'];
            $mail->Password   = $settings['password']; // This should be the actual password
            $mail->SMTPSecure = $settings['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$settings['port'];
        }

        // --- Recipients ---
        $mail->setFrom($settings['from_email'], $settings['from_name'] ?: 'Mon SGRH');
        $mail->addAddress($recipient_email);

        // --- Content ---
        $mail->isHTML(true);
        $mail->Subject = 'Your One-Time Password';
        $mail->Body    = "Your OTP code is: <b>{$otp_code}</b>. It will expire in 10 minutes.";
        $mail->AltBody = "Your OTP code is: {$otp_code}. It will expire in 10 minutes.";
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function send_password_reset_email(string $recipient_email, string $reset_link): bool {
    global $db; // Use the global database connection

    // --- Fetch SMTP settings from the database ---
    $stmt = $db->query("SELECT * FROM smtp_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        error_log("Mailer Error: SMTP settings are not configured in the database.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // --- Server settings ---
        if ($settings['method'] === 'smtp') {
            $mail->isSMTP();
            $mail->Host       = $settings['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['username'];
            $mail->Password   = $settings['password'];
            $mail->SMTPSecure = $settings['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$settings['port'];
        }

        // --- Recipients ---
        $mail->setFrom($settings['from_email'], $settings['from_name'] ?: 'Your Application Name');
        $mail->addAddress($recipient_email);

        // --- Content ---
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Réinitialisation de votre mot de passe Mon SGRH'; // Subject in French

        // Email Body (HTML)
        $mail->Body = "Bonjour,<br><br>" .
                      "Pour réinitialiser votre mot de passe, veuillez cliquer sur le lien ci-dessous :<br>" .
                      "<a href=\"{$reset_link}\">{$reset_link}</a><br><br>" .
                      "Ce lien expirera dans 1 heure.<br><br>" .
                      "Si vous n'avez pas demandé de réinitialisation de mot de passe, veuillez ignorer cet e-mail.";

        // Email Body (Plain Text for non-HTML clients)
        $mail->AltBody = "Bonjour,\n\n" .
                         "Pour réinitialiser votre mot de passe, veuillez copier et coller le lien suivant dans votre navigateur :\n" .
                         "{$reset_link}\n\n" .
                         "Ce lien expirera dans 1 heure.\n\n" .
                         "Si vous n'avez pas demandé de réinitialisation de mot de passe, veuillez ignorer cet e-mail.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log the detailed error from PHPMailer
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}