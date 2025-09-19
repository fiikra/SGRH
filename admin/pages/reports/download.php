<?php
// --- Security Headers: Set before any output ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

if (isset($_GET['nin']) && isset($_GET['date']) && isset($_GET['type'])) {
    $nin = sanitize($_GET['nin']);
    $date = sanitize($_GET['date']);
    $type = sanitize($_GET['type']);
    
    // Generate the filename as stored on server
    $filename = 'attestation_' . $nin . '_' . $date . '.pdf';
    $filepath = __DIR__ . '/../../assets/uploads/certificates/' . $filename;
    
    // Generate the download filename
    $downloadName = 'attestation_' . $nin . '_' . $date . '.pdf';
    
    // Verify file exists
    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

// If file doesn't exist or parameters missing
header("HTTP/1.0 404 Not Found");
echo "Fichier non trouvé ou paramètres manquants.";
?>