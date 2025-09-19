<?php
// This file provides send_smtp_mail($to, $subject, $body)
// Uses smtp_settings table and can send via PHPMailer (SMTP) or PHP mail()

require_once '../lib/PHPMailer/PHPMailer/PHPMailer.php';
require_once '../lib/PHPMailer/PHPMailer/SMTP.php';
require_once '../lib/PHPMailer/PHPMailer/Exception.php';

function send_smtp_mail($to, $subject, $body) {
    global $db;
    $row = $db->query("SELECT * FROM smtp_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception("Paramètres email non configurés.");
    }

    if ($row['method'] === 'phpmail') {
        // Use PHP mail()
        $from = $row['from_email'];
        $fromName = $row['from_name'] ?: 'SGRH';
        $headers = "From: " . ($fromName ? "$fromName <$from>" : $from) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        return mail($to, $subject, $body, $headers);
    } else {
        // Use SMTP with PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $row['host'];
            $mail->Port = $row['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $row['username'];
            $mail->Password = $row['password'];
            $mail->SMTPSecure = $row['secure'];
            $mail->setFrom($row['from_email'], $row['from_name'] ?: 'SGRH');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("SMTP error: " . $mail->ErrorInfo);
            return false;
        }
    }
}