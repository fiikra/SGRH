<?php
// --- Security Headers: Set before any output ---
header('X-Frame-Options: DENY'); // Prevent clickjacking
header('X-Content-Type-Options: nosniff'); // Prevent MIME sniffing
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload'); // Only works over HTTPS
/*
// --- Force HTTPS if not already (optional, best to do in server config) ---
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $httpsUrl", true, 301);
    exit();
}
*/
// --- Harden Session Handling ---
session_set_cookie_params([
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'domain' => '', // Set to your production domain if needed
    'secure' => true,   // Only send cookie over HTTPS
    'httponly' => true, // JavaScript can't access
    'samesite' => 'Strict' // CSRF protection
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
session_regenerate_id(true);
// --- Generic error handler (don't leak errors to users in production) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- Prevent session fixation ---
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

redirectIfNotHR();

$pdf = $_GET['pdf'] ?? '';
$nin = $_GET['nin'] ?? '';
if (!$pdf) {
    header("Location: view.php?nin=" . urlencode($nin));
    exit;
}

// Optional: Choose where to redirect after launching PDF
$redirect_after = "view.php?nin=" . urlencode($nin);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Notification PDF</title>
  <script>
    window.onload = function() {
      window.open("<?= htmlspecialchars($pdf) ?>", "_blank");
      setTimeout(function() {
        window.location.href = "<?= htmlspecialchars($redirect_after) ?>";
      }, 1000);
    }
  </script>
</head>
<body>
  <p>Votre notification PDF s'ouvre dans un nouvel onglet...</p>
  <p><a href="<?= htmlspecialchars($pdf) ?>" target="_blank">Cliquez ici si le PDF ne s'ouvre pas automatiquement.</a></p>
</body>
</html>